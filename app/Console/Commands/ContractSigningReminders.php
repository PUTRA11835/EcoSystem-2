<?php

namespace App\Console\Commands;

use App\Models\EmployeeContract;
use App\Models\Notification;
use App\Support\Contracts\SigningRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reminds the people a contract is waiting for, once a day, through the notification bell:
 *
 *   · the employee, when the deadline set by Legal is close (hc_contract.signing.remind_days) or has passed;
 *   · the approver, when a forwarded contract has been waiting that many days.
 *
 * `--dry-run` only counts. A person is reminded at most once per contract per day.
 */
class ContractSigningReminders extends Command
{
    protected $signature = 'contracts:signing-reminders {--dry-run : Count what would be sent, send nothing}';

    protected $description = 'Remind employees and approvers of contracts waiting for their signature';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $days = max(0, (int) config('hc_contract.signing.remind_days', 2));
        $today = now()->toDateString();
        $sent = 0;

        $employees = EmployeeContract::where('sign_stage', SigningRules::WITH_EMPLOYEE)
            ->whereNotNull('sign_deadline')->whereDate('sign_deadline', '<=', now()->addDays($days)->toDateString())->get();

        foreach ($employees as $c) {
            $left = SigningRules::daysLeft($c->sign_deadline->toDateString(), $today);
            $text = $left < 0
                ? "Contract {$c->contract_number} is " . abs($left) . ' day(s) past its signing deadline.'
                : ($left === 0 ? "Contract {$c->contract_number} must be signed today." : "Contract {$c->contract_number} must be signed within {$left} day(s).");
            $sent += $this->remind((int) $c->employee_id, $text, route('profile.contracts.show', $c->contract_id), $dry) ? 1 : 0;
        }

        $approvers = EmployeeContract::where('sign_stage', SigningRules::WITH_APPROVER)->whereNotNull('approver_employee_id')->get();

        foreach ($approvers as $c) {
            $since = DB::table('contract_sign_events')->where('contract_id', $c->contract_id)->where('event', 'forwarded')->max('created_at');
            if (!$since || \Carbon\Carbon::parse($since)->gt(now()->subDays($days))) {
                continue;
            }
            $sent += $this->remind((int) $c->approver_employee_id,
                "Contract {$c->contract_number} has been waiting for your approval since " . \Carbon\Carbon::parse($since)->format('d M Y') . '.',
                route('profile.contracts.show', $c->contract_id), $dry) ? 1 : 0;
        }

        $this->info(($dry ? '[DRY RUN] Would send ' : 'Sent ') . $sent . ' reminder(s).');

        return self::SUCCESS;
    }

    /** One reminder per person, link and day. */
    private function remind(int $employeeId, string $text, string $link, bool $dry): bool
    {
        $already = Notification::where('employee_id', $employeeId)->where('type', 'contract_sign_reminder')
            ->where('link', $link)->whereDate('created_at', now()->toDateString())->exists();
        if ($already) {
            return false;
        }
        if (!$dry) {
            Notification::create(['employee_id' => $employeeId, 'type' => 'contract_sign_reminder', 'preview' => mb_substr($text, 0, 250), 'link' => $link]);
        }

        return true;
    }
}
