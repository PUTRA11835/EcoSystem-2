<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TicketActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TicketActivityLogController extends Controller
{
    public function index(int $ticketId): JsonResponse
    {
        $session = session('user');
        if (!$session) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $logs = TicketActivityLog::with('employee.basicData')
            ->where('ticket_id', $ticketId)
            ->orderBy('activity_date')
            ->orderBy('id')
            ->get()
            ->map(fn($log) => $this->format($log));

        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function store(Request $request, int $ticketId): JsonResponse
    {
        $session = session('user');
        if (!$session) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $employee  = Employee::find($session['id']);
        $permSlugs = $employee ? Cache::get("perm_slugs_{$session['id']}", fn() => $employee->allPermissionSlugs()) : [];
        if (!in_array('ticket.activity-log', $permSlugs)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'activity_date' => 'required|date',
            'activity'      => 'required|string|max:5000',
            'file_ref_url'  => 'nullable|url|max:2000',
            'file_ref'      => 'nullable|file|max:10240',
        ]);

        $filePath = null;
        $fileName = null;

        if ($request->hasFile('file_ref') && $request->file('file_ref')->isValid()) {
            $file     = $request->file('file_ref');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('ticket-activity-logs/' . $ticketId, 'public');
        }

        $log = TicketActivityLog::create([
            'ticket_id'     => $ticketId,
            'employee_id'   => $session['id'],
            'activity_date' => $validated['activity_date'],
            'activity'      => $validated['activity'],
            'file_ref_url'  => $validated['file_ref_url'] ?? null,
            'file_ref_path' => $filePath,
            'file_ref_name' => $fileName,
        ]);

        $log->load('employee.basicData');

        return response()->json(['success' => true, 'data' => $this->format($log)], 201);
    }

    public function update(Request $request, int $ticketId, int $logId): JsonResponse
    {
        $session = session('user');
        if (!$session) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $log = TicketActivityLog::where('ticket_id', $ticketId)->findOrFail($logId);

        if ((int) $log->employee_id !== (int) $session['id']) {
            return response()->json(['success' => false, 'message' => 'You can only edit your own entries'], 403);
        }

        $validated = $request->validate([
            'activity_date'     => 'required|date',
            'activity'          => 'required|string|max:5000',
            'file_ref_url'      => 'nullable|url|max:2000',
            'file_ref'          => 'nullable|file|max:10240',
            'remove_file'       => 'nullable|boolean',
        ]);

        // Handle file
        if ($request->hasFile('file_ref') && $request->file('file_ref')->isValid()) {
            // Delete old file
            if ($log->file_ref_path) {
                Storage::disk('public')->delete($log->file_ref_path);
            }
            $file     = $request->file('file_ref');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('ticket-activity-logs/' . $ticketId, 'public');
            $log->file_ref_path = $filePath;
            $log->file_ref_name = $fileName;
        } elseif ($request->boolean('remove_file')) {
            if ($log->file_ref_path) {
                Storage::disk('public')->delete($log->file_ref_path);
            }
            $log->file_ref_path = null;
            $log->file_ref_name = null;
        }

        $log->activity_date = $validated['activity_date'];
        $log->activity      = $validated['activity'];
        $log->file_ref_url  = $validated['file_ref_url'] ?? null;
        $log->save();

        $log->load('employee.basicData');

        return response()->json(['success' => true, 'data' => $this->format($log)]);
    }

    public function destroy(int $ticketId, int $logId): JsonResponse
    {
        $session = session('user');
        if (!$session) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $log = TicketActivityLog::where('ticket_id', $ticketId)->findOrFail($logId);

        if ((int) $log->employee_id !== (int) $session['id']) {
            return response()->json(['success' => false, 'message' => 'You can only delete your own entries'], 403);
        }

        if ($log->file_ref_path) {
            Storage::disk('public')->delete($log->file_ref_path);
        }

        $log->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    private function format(TicketActivityLog $log): array
    {
        $bd  = $log->employee?->basicData;
        $pic = trim(($bd->first_name ?? '') . ' ' . ($bd->last_name ?? ''))
             ?: ($log->employee?->eci ?? 'Unknown');

        $fileRefUrl = null;
        if ($log->file_ref_path) {
            $fileRefUrl = Storage::disk('public')->url($log->file_ref_path);
        }

        return [
            'id'            => $log->id,
            'ticket_id'     => $log->ticket_id,
            'employee_id'   => $log->employee_id,
            'pic'           => $pic,
            'activity_date' => $log->activity_date?->format('Y-m-d'),
            'activity'      => $log->activity,
            'file_ref_url'  => $log->file_ref_url,
            'file_ref_path' => $fileRefUrl,
            'file_ref_name' => $log->file_ref_name,
            'created_at'    => $log->created_at?->toISOString(),
        ];
    }
}
