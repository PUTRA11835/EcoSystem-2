<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MeetingTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MeetingTemplateController extends Controller
{
    // Sama seperti SlaController::assertMeetingAccess() — diatur lewat Role
    // Management (menu slug: ticket.meeting), bukan role hardcode.
    private function assertAccess(): bool
    {
        $employee = Employee::find(session('user.id'));
        return (bool) $employee?->hasPermission('ticket.meeting');
    }

    public function index($ticketId)
    {
        if (!$this->assertAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $employeeId = (int) session('user.id');

        $templates = MeetingTemplate::with('employee.basicData')
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (MeetingTemplate $t) use ($employeeId) {
                $basicData = $t->employee?->basicData;
                $creatorName = trim(($basicData->first_name ?? '') . ' ' . ($basicData->last_name ?? '')) ?: null;

                return [
                    'id'               => $t->id,
                    'name'             => $t->name,
                    'meeting_link'     => $t->meeting_link,
                    'notes'            => $t->notes,
                    'is_owner'         => $t->employee_id === $employeeId,
                    'created_by_name'  => $creatorName,
                ];
            });

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function store(Request $request, $ticketId)
    {
        if (!$this->assertAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $v = Validator::make($request->all(), [
            'name'         => 'required|string|max:150',
            'meeting_link' => 'nullable|url|max:2048',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $template = MeetingTemplate::create([
                'ticket_id'    => $ticketId,
                'employee_id'  => (int) session('user.id'),
                'name'         => $request->input('name'),
                'meeting_link' => $request->input('meeting_link'),
                'notes'        => $request->input('notes'),
            ]);

            return response()->json(['success' => true, 'message' => 'Template tersimpan.', 'data' => $template], 201);
        } catch (\Throwable $e) {
            Log::error('MeetingTemplateController@store failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan template.'], 500);
        }
    }

    public function update(Request $request, $ticketId, $id)
    {
        if (!$this->assertAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $template = MeetingTemplate::where('ticket_id', $ticketId)->where('id', $id)->first();
        if (!$template) {
            return response()->json(['success' => false, 'message' => 'Template tidak ditemukan.'], 404);
        }
        if ($template->employee_id !== (int) session('user.id')) {
            return response()->json(['success' => false, 'message' => 'Hanya pemilik yang bisa mengubah template ini.'], 403);
        }

        $v = Validator::make($request->all(), [
            'name'         => 'required|string|max:150',
            'meeting_link' => 'nullable|url|max:2048',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $template->update([
                'name'         => $request->input('name'),
                'meeting_link' => $request->input('meeting_link'),
                'notes'        => $request->input('notes'),
            ]);

            return response()->json(['success' => true, 'message' => 'Template diperbarui.', 'data' => $template->fresh()]);
        } catch (\Throwable $e) {
            Log::error('MeetingTemplateController@update failed', ['ticket_id' => $ticketId, 'id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui template.'], 500);
        }
    }

    public function destroy($ticketId, $id)
    {
        if (!$this->assertAccess()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $template = MeetingTemplate::where('ticket_id', $ticketId)->where('id', $id)->first();
        if (!$template) {
            return response()->json(['success' => false, 'message' => 'Template tidak ditemukan.'], 404);
        }
        if ($template->employee_id !== (int) session('user.id')) {
            return response()->json(['success' => false, 'message' => 'Hanya pemilik yang bisa menghapus template ini.'], 403);
        }

        $template->delete();

        return response()->json(['success' => true, 'message' => 'Template dihapus.']);
    }
}
