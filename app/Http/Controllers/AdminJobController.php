<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminJobController extends Controller
{
    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === RoleId::EC_ADMINISTRATOR->value;
    }

    public function index(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $perPage = max(1, min((int) $request->get('per_page', 200), 500));
            $page    = max(1, (int) $request->get('page', 1));
            $offset  = ($page - 1) * $perPage;

            $total = DB::table('failed_jobs')->count();

            $jobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    $job->job_name     = $payload['displayName'] ?? class_basename($payload['job'] ?? 'Unknown');
                    $job->attempts     = $payload['attempts'] ?? 0;
                    $job->queue_name   = $job->queue;
                    $job->exception_short = $this->shortException($job->exception ?? '');
                    unset($job->payload);
                    return $job;
                });

            return response()->json([
                'success' => true,
                'data'    => $jobs,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $perPage,
                    'current_page' => $page,
                    'last_page'    => max(1, (int) ceil($total / $perPage)),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AdminJobController@index error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch failed jobs'], 500);
        }
    }

    public function show(string $uuid)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        $payload = json_decode($job->payload, true);
        return response()->json([
            'success' => true,
            'data'    => [
                'uuid'       => $job->uuid,
                'connection' => $job->connection,
                'queue'      => $job->queue,
                'failed_at'  => $job->failed_at,
                'job_name'   => $payload['displayName'] ?? 'Unknown',
                'attempts'   => $payload['attempts'] ?? 0,
                'exception'  => $job->exception,
            ],
        ]);
    }

    public function retry(Request $request, string $uuid)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
            Log::info('AdminJobController: retried job', [
                'uuid' => $uuid,
                'by'   => session('user.eci') ?? session('user.name') ?? 'admin',
            ]);
            return response()->json(['success' => true, 'message' => 'Job queued for retry']);
        } catch (\Exception $e) {
            Log::error('AdminJobController@retry error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retry job'], 500);
        }
    }

    public function retryAll(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $count = DB::table('failed_jobs')->count();
            Artisan::call('queue:retry', ['id' => ['all']]);
            Log::info('AdminJobController: retried ALL jobs', [
                'count' => $count,
                'by'    => session('user.eci') ?? session('user.name') ?? 'admin',
            ]);
            return response()->json(['success' => true, 'message' => "Queued {$count} job(s) for retry", 'count' => $count]);
        } catch (\Exception $e) {
            Log::error('AdminJobController@retryAll error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retry all jobs'], 500);
        }
    }

    public function destroy(string $uuid)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $deleted = DB::table('failed_jobs')->where('uuid', $uuid)->delete();
            if (!$deleted) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }
            Log::info('AdminJobController: deleted failed job', [
                'uuid' => $uuid,
                'by'   => session('user.eci') ?? session('user.name') ?? 'admin',
            ]);
            return response()->json(['success' => true, 'message' => 'Failed job deleted']);
        } catch (\Exception $e) {
            Log::error('AdminJobController@destroy error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to delete job'], 500);
        }
    }

    public function clearAll(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $count = DB::table('failed_jobs')->count();
            Artisan::call('queue:flush');
            Log::info('AdminJobController: cleared ALL failed jobs', [
                'count' => $count,
                'by'    => session('user.eci') ?? session('user.name') ?? 'admin',
            ]);
            return response()->json(['success' => true, 'message' => "Cleared {$count} failed job(s)", 'count' => $count]);
        } catch (\Exception $e) {
            Log::error('AdminJobController@clearAll error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to clear jobs'], 500);
        }
    }

    public function page()
    {
        if (!$this->assertAdmin()) {
            abort(403);
        }
        return view('admin.failed-jobs');
    }

    private function shortException(string $exception): string
    {
        $lines = explode("\n", $exception);
        return $lines[0] ?? '';
    }
}
