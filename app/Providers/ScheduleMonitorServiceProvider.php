<?php

namespace App\Providers;

use App\Services\ScheduleMonitorService;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Feeds the Control Center "Schedule Monitor" page by listening to
 * Laravel's own scheduler events - registered globally (bootstrap/
 * providers.php) so it's active both for web requests and for the
 * `schedule:run` CLI process where these events actually fire.
 *
 * Every scheduled task in routes/console.php must carry an explicit
 * ->name('some-slug') - that value is what Laravel reports back as
 * $event->task->description (name() is a plain alias for description()),
 * and it's the only stable identifier available: $event->task->command is
 * the fully-built shell invocation, not the original artisan command name.
 *
 * A task's outcome only becomes knowable through $event->task->exitCode at
 * ScheduledTaskFinished time: 0 = success, non-zero = failure (ScheduledTaskFailed
 * fires right after and is treated as the source of truth for that case), and
 * null = the task's run() returned early without ever executing - which is
 * exactly what happens when withoutOverlapping() finds the mutex already
 * held. There is no distinct "skip" event for that case, so it is detected
 * here instead of relying solely on ScheduledTaskSkipped (which only fires
 * for ->when()/->skip() filter conditions, evaluated before the task is even
 * attempted).
 */
class ScheduleMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event) {
            $this->guarded($event->task, fn ($name) => ScheduleMonitorService::recordStarting($name));
        });

        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            $this->guarded($event->task, function ($name) use ($event) {
                $exitCode = $event->task->exitCode;

                if ($exitCode === 0) {
                    ScheduleMonitorService::recordFinished($name, $event->runtime);
                } elseif ($exitCode === null) {
                    // run() returned before finish() ever set exitCode - a
                    // withoutOverlapping() mutex block, not a real execution.
                    ScheduleMonitorService::recordSkipped($name);
                }
                // Non-zero, non-null exit codes are left to ScheduledTaskFailed,
                // which Laravel dispatches right after this for the same run.
            });
        });

        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) {
            $this->guarded($event->task, fn ($name) => ScheduleMonitorService::recordFailed(
                $name,
                $event->exception,
                $event->task->exitCode
            ));
        });

        Event::listen(ScheduledTaskSkipped::class, function (ScheduledTaskSkipped $event) {
            $this->guarded($event->task, fn ($name) => ScheduleMonitorService::recordSkipped($name));
        });
    }

    /**
     * Every listener follows the same fire-and-forget contract as
     * AuditObserver/AuthController::recordActivity(): a monitoring bug must
     * never break the actual scheduled task it's observing, and a task
     * without a ->name() is silently skipped rather than recorded under a
     * misleading fallback identifier.
     */
    private function guarded($task, \Closure $callback): void
    {
        $name = is_string($task->description ?? null) ? $task->description : null;
        if (!$name) {
            return;
        }

        try {
            $callback($name);
        } catch (\Throwable $e) {
            Log::warning('ScheduleMonitorServiceProvider failed to record task event', [
                'task_name' => $name,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
