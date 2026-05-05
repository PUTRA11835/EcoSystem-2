<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Enums\RoleId;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\CustomerMandays;
use App\Models\CustomerMandaysDetail;
use App\Models\ConsultantMandays;
use App\Models\ConsultantMandaysDetail;
use App\Models\Employee;
use App\Models\EmployeeQualification;
use App\Models\TicketMessage;

class MandaysController extends Controller
{
    // =========================================================================
    // SHARED UTILITY
    // =========================================================================

    /**
     * GET /api/tickets/{ticketId}/mandays/modules
     * Daftar modul unik dari kualifikasi PIC + semua member tiket.
     */
    public function getModules($ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        // Kumpulkan employee_id: PIC + members
        $employeeIds = collect([$ticket->employee_id])
            ->merge($ticket->members->pluck('employee_id'))
            ->filter()
            ->unique()
            ->values();

        $modules = EmployeeQualification::whereIn('employee_id', $employeeIds)
            ->whereNotNull('module')
            ->where('module', '<>', '')
            ->distinct()
            ->pluck('module')
            ->sort()
            ->values();

        return response()->json(['success' => true, 'data' => $modules]);
    }

    // =========================================================================
    // CUSTOMER MANDAYS — PIC ENDPOINTS
    // =========================================================================

    /**
     * GET /api/tickets/{ticketId}/mandays/pic-draft
     * Ambil draft/proposal terbaru customer mandays untuk PIC.
     */
    public function getCustomerDraft($ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $proposal = CustomerMandays::where('ticket_id', $ticketId)
            ->latestVersion()
            ->with(['details', 'canceledBy.basicData'])
            ->first();

        return response()->json([
            'success'                 => true,
            'data'                    => $proposal ? $this->formatCustomerProposal($proposal) : null,
            'ticket_mandays_status'   => $ticket->mandays_proposal_status ?? 'none',
        ]);
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/pic-draft
     * PIC simpan/update draft.
     */
    public function saveCustomerDraft(Request $request, $ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $sessionUser = session('user');
        $employeeId  = $sessionUser['id'] ?? null;

        $request->validate([
            'details'           => 'required|array|min:1',
            'details.*.activity'=> 'nullable|string|max:150',
            'details.*.module'  => 'required|string|max:100',
            'details.*.mandays' => 'required|numeric|min:0',
            'description'       => 'nullable|string|max:255',
            'proposal_notes'    => 'nullable|string|max:2000',
        ]);

        $existing = CustomerMandays::where('ticket_id', $ticketId)->latestVersion()->first();

        // Blokir edit saat sedang dalam proses (pending_helpdesk / sent_to_chat)
        if ($existing && in_array($existing->status, ['pending_helpdesk', 'sent_to_chat'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit while proposal is being reviewed.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $total = collect($request->details)->sum('mandays');

            if (!$existing || in_array($existing->status, ['canceled', 'approved'])) {
                // Buat versi baru
                $latestVersion = CustomerMandays::where('ticket_id', $ticketId)->max('version') ?? 0;
                $proposal = CustomerMandays::create([
                    'ticket_id'           => $ticketId,
                    'version'             => $latestVersion + 1,
                    'description'         => $request->description ?: null,
                    'proposal_notes'      => $request->proposal_notes ?: null,
                    'proposed_by_agent_id'=> $employeeId,
                    'proposed_at'         => now(),
                    'status'              => 'draft',
                    'total_mandays'       => $total,
                ]);
            } else {
                // Update existing draft
                $proposal = $existing;
                $proposal->update([
                    'description'    => $request->description ?: $proposal->description,
                    'proposal_notes' => $request->has('proposal_notes') ? ($request->proposal_notes ?: null) : $proposal->proposal_notes,
                    'total_mandays'  => $total,
                    'proposed_at'    => now(),
                ]);
                $proposal->details()->delete();
            }

            foreach ($request->details as $d) {
                if (($d['mandays'] ?? 0) > 0) {
                    CustomerMandaysDetail::create([
                        'customer_mandays_id' => $proposal->id,
                        'activity'            => $d['activity'] ?? null,
                        'module'              => $d['module'],
                        'mandays'             => $d['mandays'],
                    ]);
                }
            }

            $ticket->update(['mandays_proposal_status' => 'pic_draft']);

            DB::commit();

            return response()->json([
                'success'               => true,
                'message'               => 'Draft saved.',
                'data'                  => $this->formatCustomerProposal($proposal->fresh(['details'])),
                'ticket_mandays_status' => 'pic_draft',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('saveCustomerDraft error', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to save the customer mandays draft. Please try again.'], 500);
        }
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/pic-draft/submit
     * PIC submit ke Helpdesk.
     */
    public function submitCustomerDraft($ticketId)
    {
        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = CustomerMandays::where('ticket_id', $ticketId)->latestVersion()->first();

        if (!$proposal || $proposal->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'No draft to submit.'], 422);
        }

        $proposal->update([
            'status'                  => 'pending_helpdesk',
            'submitted_to_customer_at'=> now(),
        ]);
        $ticket->update(['mandays_proposal_status' => 'pending_helpdesk']);

        $sessionUser = session('user');
        $fromName    = $sessionUser['name'] ?? 'PIC';
        $fromId      = $sessionUser['id'] ?? null;
        $ticketNum   = $ticket->ticket_number ?? $ticketId;
        $this->notifyRoles(
            [RoleId::HELPDESK->value],
            'customer_mandays_proposed',
            $fromName,
            $fromId,
            "Ticket #{$ticketNum} — Customer mandays proposal submitted for review",
            "/ticket/{$ticketId}"
        );

        return response()->json([
            'success'               => true,
            'message'               => 'Proposal submitted to Helpdesk.',
            'ticket_mandays_status' => 'pending_helpdesk',
        ]);
    }

    // =========================================================================
    // CUSTOMER MANDAYS — HELPDESK ENDPOINTS
    // =========================================================================

    /**
     * GET /api/tickets/{ticketId}/mandays/hd-draft
     * Helpdesk ambil proposal terbaru untuk direview.
     */
    public function getHelpdeskDraft($ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $proposal = CustomerMandays::where('ticket_id', $ticketId)
            ->latestVersion()
            ->with(['details', 'canceledBy.basicData', 'proposedByAgent.basicData'])
            ->first();

        return response()->json([
            'success'               => true,
            'data'                  => $proposal ? $this->formatCustomerProposal($proposal) : null,
            'ticket_mandays_status' => $ticket->mandays_proposal_status ?? 'none',
        ]);
    }

    /**
     * PUT /api/tickets/{ticketId}/mandays/hd-draft
     * Helpdesk edit detail proposal.
     */
    public function saveHelpdeskDraft(Request $request, $ticketId)
    {
        $request->validate([
            'details'           => 'required|array|min:1',
            'details.*.activity'=> 'nullable|string|max:150',
            'details.*.module'  => 'required|string|max:100',
            'details.*.mandays' => 'required|numeric|min:0',
        ]);

        $proposal = CustomerMandays::where('ticket_id', $ticketId)->latestVersion()->first();

        if (!$proposal || !in_array($proposal->status, ['pending_helpdesk', 'sent_to_chat'])) {
            return response()->json(['success' => false, 'message' => 'No proposal to edit.'], 422);
        }

        $total = collect($request->details)->sum('mandays');

        $proposal->details()->delete();
        foreach ($request->details as $d) {
            if (($d['mandays'] ?? 0) > 0) {
                CustomerMandaysDetail::create([
                    'customer_mandays_id' => $proposal->id,
                    'activity'            => $d['activity'] ?? null,
                    'module'              => $d['module'],
                    'mandays'             => $d['mandays'],
                ]);
            }
        }

        $proposal->update([
            'total_mandays' => $total,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk draft updated.',
            'data'    => $this->formatCustomerProposal($proposal->fresh(['details'])),
        ]);
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/hd-draft/submit-chat
     * Helpdesk kirim ke chat customer via email.
     */
    public function submitToChat($ticketId)
    {
        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = CustomerMandays::where('ticket_id', $ticketId)
            ->latestVersion()
            ->with('details')
            ->first();

        if (!$proposal || $proposal->status !== 'pending_helpdesk') {
            return response()->json(['success' => false, 'message' => 'Proposal is not in pending_helpdesk status.'], 422);
        }

        $sessionUser = session('user');
        $senderName  = $sessionUser['name'] ?? 'Helpdesk Support';
        $senderId    = $sessionUser['id'] ?? null;

        // Update status terlebih dahulu (bersihkan rejection_reason jika re-send setelah ditolak customer)
        $proposal->update([
            'status'           => 'sent_to_chat',
            'sent_to_chat_at'  => now(),
            'rejection_reason' => null,
            'customer_notes'   => null,
        ]);
        $ticket->update(['mandays_proposal_status' => 'sent_to_chat']);

        // Selalu buat TicketMessage terlebih dahulu agar proposal tampil di chat
        $htmlBody  = $this->buildMandaysEmailHtml($proposal, $ticket, $senderName);
        $plainText = 'Mandays proposal sent to customer. Total: ' . number_format((float) $proposal->total_mandays, 1) . ' mandays.';

        $ticketMsg = TicketMessage::create([
            'ticket_id'           => $ticketId,
            'sender_type'         => 'employee',
            'sender_id'           => $senderId,
            'sender_name'         => $senderName,
            'message'             => $plainText,
            'message_html'        => $htmlBody,
            'is_internal_note'    => false,
            'channel'             => 'web',
            'is_read_by_customer' => false,
            'is_read_by_agent'    => true,
        ]);

        $ticket->update([
            'last_message_at'     => now(),
            'last_agent_reply_at' => now(),
        ]);

        // Coba kirim email ke customer (non-fatal jika gagal — pesan sudah tersimpan di chat)
        $emailSent    = false;
        $emailWarning = null;

        try {
            $customerEmail = $this->resolveCustomerEmailForTicket($ticket);

            if (!$customerEmail) {
                $emailWarning = 'No customer email address found. Proposal saved to chat only.';
                Log::warning('MandaysController@submitToChat: no customer email', ['ticket_id' => $ticketId]);
            } else {
                $lastEmailMsg = TicketMessage::where('ticket_id', $ticketId)
                    ->where('channel', 'email')
                    ->whereNotNull('email_message_id')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $inReplyTo = $lastEmailMsg?->email_message_id;

                $subject = 'Ticket #' . $ticket->ticket_number . ': ' . mb_substr($ticket->description ?? '', 0, 80);

                // CC: prioritaskan ticket.cc_emails, fallback ke pesan pertama dengan CC.
                // Gunakan is_array check karena model cast 'array' sudah decode JSON → array.
                $rawTicketCc = $ticket->cc_emails;
                $ccList = $rawTicketCc
                    ? (is_array($rawTicketCc) ? $rawTicketCc : (json_decode($rawTicketCc, true) ?? []))
                    : [];
                if (empty($ccList)) {
                    $firstMsgWithCc = TicketMessage::where('ticket_id', $ticketId)
                        ->whereNotNull('cc_emails')
                        ->orderBy('created_at', 'asc')
                        ->first();
                    $rawMsgCc = $firstMsgWithCc?->cc_emails;
                    $ccList = $rawMsgCc
                        ? (is_array($rawMsgCc) ? $rawMsgCc : (json_decode($rawMsgCc, true) ?? []))
                        : [];
                }

                $result = app(EmailController::class)->sendTicketReply(
                    $customerEmail,
                    $subject,
                    $htmlBody,
                    $inReplyTo,
                    [],
                    $ccList,
                    true,
                    $ticket->email_thread_id ?? null
                );

                // Update message channel dan email_message_id setelah email berhasil terkirim
                $ticketMsg->update([
                    'channel'          => 'email',
                    'email_message_id' => $result['internet_message_id'] ?? null,
                ]);

                // Selalu sync email_thread_id ke conversationId terbaru (bukan hanya saat kosong)
                if (!empty($result['conversation_id']) && $result['conversation_id'] !== $ticket->email_thread_id) {
                    $ticket->update(['email_thread_id' => $result['conversation_id']]);
                }

                $emailSent = true;
                Log::info('MandaysController@submitToChat: email sent', [
                    'ticket_id' => $ticketId,
                    'to'        => $customerEmail,
                ]);
            }
        } catch (\Throwable $e) {
            $emailWarning = 'Proposal saved to chat but email could not be sent.';
            Log::error('MandaysController@submitToChat: email failed', [
                'ticket_id' => $ticketId,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'success'               => true,
            'message'               => $emailSent
                ? 'Proposal sent to customer via email.'
                : ($emailWarning ?? 'Status updated.'),
            'email_sent'            => $emailSent,
            'email_warning'         => $emailWarning,
            'ticket_mandays_status' => 'sent_to_chat',
        ]);
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/hd-draft/approve
     * Helpdesk approve → update man_days ticket.
     */
    public function approveCustomerMandays($ticketId)
    {
        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = CustomerMandays::where('ticket_id', $ticketId)->latestVersion()->first();

        if (!$proposal || $proposal->status !== 'sent_to_chat') {
            return response()->json(['success' => false, 'message' => 'Proposal must be sent to customer chat before it can be approved.'], 422);
        }

        $proposal->update(['status' => 'approved']);
        $ticket->update([
            'mandays_proposal_status' => 'approved',
            'man_days'                => $proposal->total_mandays,
        ]);

        return response()->json([
            'success'               => true,
            'message'               => 'Customer mandays approved.',
            'ticket_mandays_status' => 'approved',
        ]);
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/hd-draft/cancel
     * Helpdesk cancel proposal (with optional notes for PIC).
     */
    public function cancelCustomerMandays(Request $request, $ticketId)
    {
        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = CustomerMandays::where('ticket_id', $ticketId)->latestVersion()->first();

        if (!$proposal || !in_array($proposal->status, ['pending_helpdesk', 'sent_to_chat'])) {
            return response()->json(['success' => false, 'message' => 'No active proposal to cancel.'], 422);
        }

        $cancelNotes    = $request->input('cancel_notes');
        $sessionUser    = session('user');
        $canceledById   = $sessionUser['id'] ?? null;

        $proposal->update([
            'status'          => 'canceled',
            'notes'           => $cancelNotes ?: null,
            'canceled_by_id'  => $canceledById,
        ]);
        $ticket->update(['mandays_proposal_status' => 'canceled']);

        // Notify PIC and all members
        $fromName  = $sessionUser['name'] ?? 'Helpdesk';
        $ticketNum = $ticket->ticket_number ?? $ticketId;
        $preview   = "Ticket #{$ticketNum} — Customer mandays proposal has been canceled"
                   . ($cancelNotes ? ': ' . mb_substr($cancelNotes, 0, 80) : '');
        $link      = "/ticket/{$ticketId}";

        $recipients = collect();
        if ($ticket->employee_id) {
            $recipients->push($ticket->employee_id);
        }
        $ticket->members()->pluck('employee_id')->each(fn ($id) => $recipients->push($id));

        $recipients->unique()->each(function ($empId) use ($canceledById, $fromName, $preview, $link) {
            Notification::create([
                'employee_id'      => $empId,
                'type'             => 'customer_mandays_canceled',
                'from_employee_id' => $canceledById,
                'from_name'        => $fromName,
                'preview'          => $preview,
                'link'             => $link,
                'is_read'          => false,
            ]);
        });

        return response()->json([
            'success'               => true,
            'message'               => 'Proposal canceled.',
            'ticket_mandays_status' => 'canceled',
        ]);
    }

    // =========================================================================
    // INTERNAL MANDAYS — PIC + HEAD OF SUPPORT
    // =========================================================================

    /**
     * GET /api/tickets/{ticketId}/mandays/internal
     * Ambil proposal internal + daftar orang.
     */
    public function getInternalProposal($ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $proposal = ConsultantMandays::where('ticket_id', $ticketId)
            ->latestPerTicket()
            ->with(['details.employee.basicData', 'proposedByAgent.basicData', 'approvedByHead.basicData'])
            ->first();

        // Bangun people list: PIC + Members aktif + Past Members
        $people = $this->buildPeopleList($ticket, $proposal);

        $internalStatus = $ticket->internal_mandays_status ?? 'none';

        return response()->json([
            'success'                 => true,
            'data'                    => $proposal ? $this->formatInternalProposal($proposal) : null,
            'internal_mandays_status' => $internalStatus,
            'people'                  => $people,
        ]);
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/internal
     * PIC simpan/update draft internal.
     */
    public function saveInternalProposal(Request $request, $ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $sessionUser = session('user');
        $employeeId  = $sessionUser['id'] ?? null;

        $validator = Validator::make($request->all(), [
            'details'                      => 'required|array|min:1',
            'details.*.employee_id'        => 'required|integer',
            'details.*.module'             => 'nullable|string|max:100',
            'details.*.mandays'            => 'required|numeric|min:0',
            'details.*.additional_mandays' => 'nullable|numeric|min:0',
            'details.*.notes'              => 'nullable|string|max:500',
            'notes'                        => 'nullable|string|max:1000',
        ]);

        // Jika additional_mandays diisi (> 0) maka notes wajib diisi
        $validator->after(function ($v) use ($request) {
            foreach ($request->input('details', []) as $i => $detail) {
                $add = (float) ($detail['additional_mandays'] ?? 0);
                if ($add > 0 && empty(trim($detail['notes'] ?? ''))) {
                    $v->errors()->add(
                        "details.{$i}.notes",
                        'Notes wajib diisi jika Additional MD diisi.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $existing = ConsultantMandays::where('ticket_id', $ticketId)->latestPerTicket()->first();

        if ($existing && $existing->status === 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit while pending Delivery Support Head approval.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $total = collect($request->details)->sum('mandays');

            if (!$existing) {
                $proposal = ConsultantMandays::create([
                    'ticket_id'           => $ticketId,
                    'proposed_by_agent_id'=> $employeeId,
                    'proposed_at'         => now(),
                    'last_edited_at'      => now(),
                    'status'              => 'draft',
                    'helpdesk_notes'      => $request->notes,
                    'total_mandays'       => $total,
                ]);
            } else {
                $proposal = $existing;
                $proposal->update([
                    'status'           => 'draft',
                    'last_edited_at'   => now(),
                    'helpdesk_notes'   => $request->notes,
                    'total_mandays'    => $total,
                    'rejection_reason' => null,
                ]);
            }

            // Preserve progress: load existing detail rows keyed by employee_id
            $existingDetails = $proposal->details()->get()->keyBy('employee_id');
            $newEmployeeIds  = collect($request->details)->pluck('employee_id')->map('intval')->toArray();

            // Hapus hanya row yang tidak ada di request baru
            $proposal->details()->whereNotIn('employee_id', $newEmployeeIds)->delete();

            foreach ($request->details as $d) {
                if (($d['mandays'] ?? 0) > 0 || ($d['additional_mandays'] ?? 0) > 0) {
                    $empId   = (int) $d['employee_id'];
                    $existing = $existingDetails->get($empId);

                    if ($existing) {
                        // Update mandays saja — progress_percentage/progress_note tetap
                        $existing->update([
                            'module'             => $d['module'] ?? null,
                            'mandays'            => $d['mandays'] ?? 0,
                            'additional_mandays' => $d['additional_mandays'] ?? 0,
                            'notes'              => $d['notes'] ?? null,
                        ]);
                    } else {
                        ConsultantMandaysDetail::create([
                            'consultant_mandays_id' => $proposal->id,
                            'employee_id'           => $empId,
                            'module'                => $d['module'] ?? null,
                            'mandays'               => $d['mandays'] ?? 0,
                            'additional_mandays'    => $d['additional_mandays'] ?? 0,
                            'approved_additional'   => 0,
                            'notes'                 => $d['notes'] ?? null,
                        ]);
                    }
                }
            }

            $ticket->update(['internal_mandays_status' => 'draft']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('saveInternalProposal error', ['e' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to save the internal mandays proposal. Please try again.'], 500);
        }

        return response()->json([
            'success'                 => true,
            'message'                 => 'Internal draft saved.',
            'data'                    => $this->formatInternalProposal($proposal->fresh(['details.employee.basicData', 'proposedByAgent.basicData', 'approvedByHead.basicData'])),
            'internal_mandays_status' => 'draft',
        ]);
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/internal/submit
     * PIC submit ke Head of Support.
     */
    public function submitInternalProposal($ticketId)
    {
        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = ConsultantMandays::where('ticket_id', $ticketId)->latestPerTicket()->first();

        if (!$proposal || !in_array($proposal->status, ['draft', 'needs_revision', 'approved'])) {
            return response()->json(['success' => false, 'message' => 'No draft to submit.'], 422);
        }

        $proposal->update(['status' => 'pending_approval', 'proposed_at' => now()]);
        $ticket->update(['internal_mandays_status' => 'pending_head']);

        $sessionUser = session('user');
        $fromName    = $sessionUser['name'] ?? 'PIC';
        $fromId      = $sessionUser['id'] ?? null;
        $ticketNum   = $ticket->ticket_number ?? $ticketId;
        $this->notifyRoles(
            [RoleId::HEAD_OF_SUPPORT->value, RoleId::HEAD_OF_PROJECT->value],
            'internal_mandays_proposed',
            $fromName,
            $fromId,
            "Ticket #{$ticketNum} — Internal mandays proposal submitted for your review",
            "/ticket/{$ticketId}"
        );

        return response()->json([
            'success'                 => true,
            'message'                 => 'Internal proposal submitted to Delivery Support Head.',
            'internal_mandays_status' => 'pending_head',
        ]);
    }

    /**
     * POST /api/tickets/{ticketId}/mandays/internal/approve
     * Head of Support approve.
     */
    public function approveInternalProposal(Request $request, $ticketId)
    {
        $request->validate([
            'approved_details'                      => 'nullable|array',
            'approved_details.*.employee_id'        => 'required|integer',
            'approved_details.*.approved_additional'=> 'required|numeric|min:0',
        ]);

        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = ConsultantMandays::where('ticket_id', $ticketId)->latestPerTicket()->first();
        $sessionUser = session('user');
        $headId   = $sessionUser['id'] ?? null;

        // Head of support dapat menyimpan additional mandays dari status apapun kecuali belum ada proposal
        $saveable = ['draft', 'pending_approval', 'needs_revision', 'approved'];
        if (!$proposal || !in_array($proposal->status, $saveable)) {
            return response()->json(['success' => false, 'message' => 'No proposal to save.'], 422);
        }

        DB::beginTransaction();
        try {
            // Update approved_additional per employee
            if (!empty($request->approved_details)) {
                foreach ($request->approved_details as $ad) {
                    $proposal->details()
                        ->where('employee_id', $ad['employee_id'])
                        ->update(['approved_additional' => $ad['approved_additional']]);
                }
            }

            // Recalculate total_mandays = sum(mandays + approved_additional)
            $total = $proposal->details()->get()->sum(fn($d) => $d->mandays + $d->approved_additional);

            $proposal->update([
                'status'              => 'approved',
                'approved_by_head_id' => $headId,
                'approved_at'         => now(),
                'total_mandays'       => $total,
            ]);
            $ticket->update([
                'internal_mandays_status' => 'approved',
                'man_days'                => $total,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('approveInternalProposal error', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to approve the internal mandays proposal. Please try again.'], 500);
        }

        return response()->json([
            'success'                 => true,
            'message'                 => 'Internal proposal approved.',
            'internal_mandays_status' => 'approved',
            'total_mandays'           => $total,
        ]);
    }

    // =========================================================================
    // JARVIES CUSTOMER ENDPOINTS (accessed via API Key, no session)
    // =========================================================================

    /**
     * GET /api/jarvies/tickets/{ticketId}/mandays/customer
     * Ambil proposal aktif yang terlihat oleh customer.
     */
    public function customerMandaysForJarvies($ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        // Hanya tampilkan jika proposal sudah sampai ke customer
        $visibleStatuses = ['sent_to_chat', 'approved', 'canceled'];

        $proposal = CustomerMandays::where('ticket_id', $ticketId)
            ->whereIn('status', $visibleStatuses)
            ->latestVersion()
            ->with('details')
            ->first();

        $ticketStatus = $ticket->mandays_proposal_status ?? 'none';
        $visible = $proposal !== null;

        if (!$visible) {
            return response()->json([
                'success'                => true,
                'visible'                => false,
                'mandays_proposal_status'=> $ticketStatus,
                'proposal'               => null,
            ]);
        }

        // Build grid Activity × Module
        $activities = [];
        $modules    = [];
        $grid       = [];
        foreach ($proposal->details as $d) {
            $act = $d->activity ?? 'General';
            if (!in_array($act, $activities)) $activities[] = $act;
            if (!in_array($d->module, $modules)) $modules[] = $d->module;
            $grid[$act][$d->module] = ($grid[$act][$d->module] ?? 0) + (float) $d->mandays;
        }
        sort($modules);

        $columnTotals = [];
        foreach ($modules as $mod) {
            $columnTotals[$mod] = array_sum(array_column(array_map(fn($actVals) => [$actVals[$mod] ?? 0], $grid), 0));
        }
        // Recompute column totals properly
        $columnTotals = [];
        foreach ($modules as $mod) {
            $total = 0;
            foreach ($grid as $actVals) {
                $total += $actVals[$mod] ?? 0;
            }
            $columnTotals[$mod] = $total;
        }

        return response()->json([
            'success'                => true,
            'visible'                => true,
            'mandays_proposal_status'=> $ticketStatus,
            'proposal'               => [
                'id'                   => $proposal->id,
                'version'              => $proposal->version,
                'status'               => $proposal->status,
                'total_mandays'        => (float) $proposal->total_mandays,
                'notes'                => $proposal->notes,
                'customer_notes'       => $proposal->customer_notes,
                'proposed_at'          => $proposal->proposed_at?->toISOString(),
                'customer_response_at' => $proposal->customer_response_at?->toISOString(),
            ],
            'activities'             => $activities,
            'modules'                => $modules,
            'grid'                   => $grid,
            'column_totals'          => $columnTotals,
        ]);
    }

    /**
     * POST /api/jarvies/tickets/{ticketId}/mandays/customer/approve
     * Customer approve proposal.
     */
    public function customerApproveMandays($ticketId)
    {
        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = CustomerMandays::where('ticket_id', $ticketId)
            ->where('status', 'sent_to_chat')
            ->latestVersion()
            ->first();

        if (!$proposal) {
            return response()->json(['success' => false, 'message' => 'No active proposal to approve.'], 422);
        }

        $proposal->update([
            'status'               => 'approved',
            'customer_response_at' => now(),
        ]);
        $ticket->update([
            'mandays_proposal_status' => 'approved',
            'man_days'                => $proposal->total_mandays,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proposal approved.',
            'mandays_proposal_status' => 'approved',
        ]);
    }

    /**
     * POST /api/jarvies/tickets/{ticketId}/mandays/customer/reject
     * Customer reject proposal dengan alasan.
     */
    public function customerRejectMandays(Request $request, $ticketId)
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $ticket   = Ticket::where('ticket_id', $ticketId)->firstOrFail();
        $proposal = CustomerMandays::where('ticket_id', $ticketId)
            ->where('status', 'sent_to_chat')
            ->latestVersion()
            ->first();

        if (!$proposal) {
            return response()->json(['success' => false, 'message' => 'No active proposal to reject.'], 422);
        }

        $reason = $request->input('reason') ?: null;

        $proposal->update([
            'status'               => 'pending_helpdesk',
            'rejection_reason'     => $reason,
            'customer_notes'       => $reason,
            'customer_response_at' => now(),
        ]);
        $ticket->update(['mandays_proposal_status' => 'pending_helpdesk']);

        return response()->json([
            'success' => true,
            'message' => 'Proposal rejected.',
            'mandays_proposal_status' => 'pending_helpdesk',
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * GET /api/tickets/{ticketId}/mandays/history
     * Seluruh versi propose customer mandays untuk satu tiket (ringkasan, tanpa details).
     */
    public function getCustomerMandaysHistory($ticketId)
    {
        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $versions = CustomerMandays::where('ticket_id', $ticketId)
            ->orderBy('version', 'asc')
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'version'        => $p->version,
                'description'    => $p->description,
                'proposal_notes' => $p->proposal_notes,
                'status'         => $p->status,
                'total_mandays'  => (float) $p->total_mandays,
                'last_update'    => ($p->updated_at ?? $p->proposed_at)?->toISOString(),
            ]);

        return response()->json([
            'success'               => true,
            'data'                  => $versions,
            'ticket_mandays_status' => $ticket->mandays_proposal_status ?? 'none',
        ]);
    }

    /**
     * GET /api/tickets/{ticketId}/consultant-mandays/approved
     * Returns the latest approved consultant (internal) mandays total for a ticket.
     * Used by the timesheet modal to auto-fill Jatah MD.
     */
    public function getApprovedConsultantMandays($ticketId)
    {
        $approved = ConsultantMandays::where('ticket_id', $ticketId)
            ->where('status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $approved ? [
                'total_mandays' => (float) $approved->total_mandays,
                'approved_at'   => $approved->approved_at?->toISOString(),
            ] : null,
        ]);
    }

    /**
     * GET /api/tickets/{ticketId}/mandays/approved
     * Returns the latest approved customer mandays total for a ticket.
     */
    public function getApprovedMandays($ticketId)
    {
        $approved = CustomerMandays::where('ticket_id', $ticketId)
            ->where('status', 'approved')
            ->orderBy('version', 'desc')
            ->first();

        return response()->json([
            'success'       => true,
            'data'          => $approved ? [
                'total_mandays' => (float) $approved->total_mandays,
                'version'       => $approved->version,
            ] : null,
        ]);
    }

    /**
     * GET /api/tickets/{ticketId}/mandays/version/{mandaysId}
     * Detail lengkap satu versi propose customer mandays (read-only, untuk semua role).
     */
    public function getCustomerMandaysVersionDetail($ticketId, $mandaysId)
    {
        $proposal = CustomerMandays::where('ticket_id', $ticketId)
            ->where('id', $mandaysId)
            ->with(['details', 'canceledBy.basicData', 'proposedByAgent.basicData'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatCustomerProposal($proposal),
        ]);
    }

    private function formatCustomerProposal(CustomerMandays $p): array
    {
        $canceledBy = $p->canceledBy?->basicData;
        $canceledByName = $canceledBy
            ? trim(($canceledBy->first_name ?? '') . ' ' . ($canceledBy->last_name ?? ''))
            : null;

        $proposedBy = $p->proposedByAgent?->basicData;
        $proposedByName = $proposedBy
            ? trim(($proposedBy->first_name ?? '') . ' ' . ($proposedBy->last_name ?? ''))
            : null;

        return [
            'id'                   => $p->id,
            'version'              => $p->version,
            'description'          => $p->description,
            'proposal_notes'       => $p->proposal_notes,
            'status'               => $p->status,
            'total_mandays'        => (float) $p->total_mandays,
            'cancel_notes'         => $p->notes,
            'canceled_by_name'     => $canceledByName,
            'proposed_by_name'     => $proposedByName,
            'rejection_reason'     => $p->rejection_reason,
            'customer_notes'       => $p->customer_notes,
            'proposed_at'          => $p->proposed_at?->toISOString(),
            'sent_to_chat_at'      => $p->sent_to_chat_at?->toISOString(),
            'customer_response_at' => $p->customer_response_at?->toISOString(),
            'last_update'          => ($p->updated_at ?? $p->proposed_at)?->toISOString(),
            'details'              => $p->details->map(fn($d) => [
                'id'       => $d->id,
                'activity' => $d->activity,
                'module'   => $d->module,
                'mandays'  => (float) $d->mandays,
            ])->values()->all(),
        ];
    }

    private function formatInternalProposal(ConsultantMandays $p): array
    {
        return [
            'id'               => $p->id,
            'status'           => $p->status,
            'notes'            => $p->helpdesk_notes,
            'rejection_reason' => $p->rejection_reason,
            'total_mandays'    => (float) $p->total_mandays,
            'proposed_by'      => $p->proposedByAgent?->basicData?->first_name
                                 . ' ' . $p->proposedByAgent?->basicData?->last_name,
            'approved_by_head' => $p->approvedByHead
                                 ? ($p->approvedByHead->basicData?->first_name . ' ' . $p->approvedByHead->basicData?->last_name)
                                 : null,
            'approved_at'      => $p->approved_at?->toISOString(),
            'details'          => $p->details->map(fn($d) => [
                'id'                  => $d->id,
                'employee_id'         => $d->employee_id,
                'employee_name'       => $d->employee?->basicData?->first_name . ' ' . $d->employee?->basicData?->last_name,
                'module'              => $d->module,
                'mandays'             => (float) $d->mandays,
                'additional_mandays'  => (float) ($d->additional_mandays ?? 0),
                'approved_additional' => (float) ($d->approved_additional ?? 0),
                'notes'               => $d->notes,
            ])->values()->all(),
        ];
    }

    private function buildPeopleList(Ticket $ticket, ?ConsultantMandays $proposal): array
    {
        $people = [];

        // PIC
        if ($ticket->employee_id) {
            $pic = Employee::with(['basicData', 'qualifications'])->find($ticket->employee_id);
            if ($pic) {
                $people[$ticket->employee_id] = [
                    'employee_id' => $ticket->employee_id,
                    'name'        => trim(($pic->basicData?->first_name ?? '') . ' ' . ($pic->basicData?->last_name ?? '')),
                    'role'        => 'PIC',
                    'modules'     => $pic->qualifications->pluck('module')->filter()->unique()->values()->all(),
                ];
            }
        }

        // Active members
        $members = $ticket->members()->with(['basicData', 'qualifications'])->get();
        foreach ($members as $m) {
            if (!isset($people[$m->employee_id])) {
                $people[$m->employee_id] = [
                    'employee_id' => $m->employee_id,
                    'name'        => trim(($m->basicData?->first_name ?? '') . ' ' . ($m->basicData?->last_name ?? '')),
                    'role'        => 'Member',
                    'modules'     => $m->qualifications->pluck('module')->filter()->unique()->values()->all(),
                ];
            }
        }

        // Past members from consultant_mandays_detail history
        if ($proposal) {
            $pastIds = ConsultantMandaysDetail::where('consultant_mandays_id', $proposal->id)
                ->pluck('employee_id')
                ->unique();
            foreach ($pastIds as $empId) {
                if (!isset($people[$empId])) {
                    $emp = Employee::with(['basicData', 'qualifications'])->find($empId);
                    if ($emp) {
                        $people[$empId] = [
                            'employee_id' => $empId,
                            'name'        => trim(($emp->basicData?->first_name ?? '') . ' ' . ($emp->basicData?->last_name ?? '')),
                            'role'        => 'Past Member',
                            'modules'     => $emp->qualifications->pluck('module')->filter()->unique()->values()->all(),
                        ];
                    }
                }
            }
        }

        return array_values($people);
    }

    private function notifyRoles(array $roleIds, string $type, string $fromName, ?int $fromId, string $preview, string $link): void
    {
        try {
            Employee::whereIn('role_id', $roleIds)
                ->where('is_active', true)
                ->pluck('employee_id')
                ->each(function ($empId) use ($type, $fromName, $fromId, $preview, $link) {
                    Notification::create([
                        'employee_id'      => $empId,
                        'type'             => $type,
                        'from_employee_id' => $fromId,
                        'from_name'        => $fromName,
                        'preview'          => $preview,
                        'link'             => $link,
                        'is_read'          => false,
                    ]);
                });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('MandaysController::notifyRoles failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Resolve email customer dari ticket (mirror resolveCustomerEmail di TicketMessageController).
     */
    private function resolveCustomerEmailForTicket(Ticket $ticket): ?string
    {
        $submittedEmail = DB::table('staging_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->whereNotNull('submitted_by_email')
            ->value('submitted_by_email');
        if ($submittedEmail) return $submittedEmail;

        $firstMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
            ->where('sender_type', 'customer')
            ->whereNotNull('sender_email')
            ->orderBy('created_at', 'asc')
            ->first();
        if ($firstMsg?->sender_email) return $firstMsg->sender_email;

        if ($ticket->customer_id) {
            return Customer::find($ticket->customer_id)?->email;
        }

        return null;
    }

    /**
     * Build HTML email body untuk mandays proposal yang dikirim ke customer.
     */
    private function buildMandaysEmailHtml(CustomerMandays $proposal, Ticket $ticket, string $agentName): string
    {
        $ticketNum = htmlspecialchars($ticket->ticket_number ?? '', ENT_QUOTES, 'UTF-8');
        $agent     = htmlspecialchars($agentName, ENT_QUOTES, 'UTF-8');
        $version   = $proposal->version ?? 1;
        $total     = number_format((float) $proposal->total_mandays, 1);
        $notes     = $proposal->notes ? '<p style="margin:8px 0;font-size:13px;color:#444;">' . nl2br(htmlspecialchars($proposal->notes, ENT_QUOTES, 'UTF-8')) . '</p>' : '';

        // Bangun kolom modul unik
        $modules = $proposal->details->pluck('module')->unique()->sort()->values()->all();

        // Bangun matrix: activity → module → total mandays
        $matrix = [];
        foreach ($proposal->details as $d) {
            $act = $d->activity ?: 'General';
            $matrix[$act][$d->module] = ($matrix[$act][$d->module] ?? 0) + (float) $d->mandays;
        }

        // Header kolom tabel
        $headerCols = '';
        foreach ($modules as $mod) {
            $headerCols .= '<th style="padding:8px 12px;border:1px solid #ddd;background:#8b1a1a;color:#fff;white-space:nowrap;">' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') . '</th>';
        }

        // Baris data
        $rows = '';
        $moduleTotals = array_fill_keys($modules, 0);
        foreach ($matrix as $act => $modValues) {
            $rows .= '<tr>';
            $rows .= '<td style="padding:7px 12px;border:1px solid #ddd;font-weight:600;">' . htmlspecialchars($act, ENT_QUOTES, 'UTF-8') . '</td>';
            foreach ($modules as $mod) {
                $val = $modValues[$mod] ?? 0;
                $moduleTotals[$mod] += $val;
                $rows .= '<td style="padding:7px 12px;border:1px solid #ddd;text-align:center;">' . ($val > 0 ? number_format($val, 1) : '-') . '</td>';
            }
            $rows .= '</tr>';
        }

        // Baris total
        $totalCols = '';
        foreach ($modules as $mod) {
            $totalCols .= '<td style="padding:7px 12px;border:1px solid #ddd;text-align:center;font-weight:bold;">' . number_format($moduleTotals[$mod], 1) . '</td>';
        }

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="font-family:Arial,Helvetica,sans-serif;max-width:650px;border-collapse:collapse;">
            <tr>
                <td style="background-color:#8b1a1a;padding:16px 24px;border-radius:6px 6px 0 0;">
                    <p style="color:#ffffff;font-size:16px;font-weight:bold;margin:0;">PT Eclectic Consulting</p>
                    <p style="color:rgba(255,255,255,0.75);font-size:11px;margin:3px 0 0 0;">Helpdesk Support &nbsp;&middot;&nbsp; Ticket #{$ticketNum}</p>
                </td>
            </tr>
            <tr>
                <td style="padding:20px 24px;background:#fff;border:1px solid #e5e7eb;border-top:none;">
                    <p style="margin:0 0 12px;font-size:14px;color:#222;">Yth. Bapak/Ibu,</p>
                    <p style="margin:0 0 16px;font-size:13px;color:#444;line-height:1.6;">
                        Berikut kami sampaikan proposal <strong>Man Days</strong> untuk Ticket <strong>#{$ticketNum}</strong>
                        (Versi {$version}). Mohon konfirmasi persetujuan Anda.
                    </p>
                    {$notes}
                    <div style="overflow-x:auto;margin:16px 0;">
                        <table cellpadding="0" cellspacing="0" border="0"
                               style="border-collapse:collapse;font-size:13px;min-width:100%;">
                            <thead>
                                <tr>
                                    <th style="padding:8px 12px;border:1px solid #ddd;background:#8b1a1a;color:#fff;text-align:left;">Activity</th>
                                    {$headerCols}
                                </tr>
                            </thead>
                            <tbody>
                                {$rows}
                            </tbody>
                            <tfoot>
                                <tr style="background:#f9f9f9;">
                                    <td style="padding:7px 12px;border:1px solid #ddd;font-weight:bold;">Total</td>
                                    {$totalCols}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p style="margin:16px 0 4px;font-size:13px;color:#444;">
                        <strong>Total Man Days: {$total}</strong>
                    </p>
                    <p style="margin:16px 0 0;font-size:12px;color:#666;">
                        Please contact us if you have any questions regarding this proposal.
                    </p>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 24px;background:#f3f4f6;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;">
                    <p style="margin:0;font-size:11px;color:#6b7280;">
                        Sent by: <strong>{$agent}</strong> &nbsp;&middot;&nbsp; PT Eclectic Consulting Helpdesk
                    </p>
                </td>
            </tr>
        </table>
        HTML;
    }
}
