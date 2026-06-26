<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TicketMigrationController extends Controller
{
    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === RoleId::EC_ADMINISTRATOR->value;
    }

    // ── Export ZIP ────────────────────────────────────────────────────────────

    public function exportZip(Request $request)
    {
        if (!$this->assertAdmin()) abort(403);

        set_time_limit(300);

        $year  = (int) $request->get('year',  0);
        $month = (int) $request->get('month', 0);

        $ticketQuery = DB::table('ticket')->whereNull('deleted_at');
        if ($year > 0)  $ticketQuery->whereYear('created_at', $year);
        if ($month > 0) $ticketQuery->whereMonth('created_at', $month);

        $tickets = $ticketQuery->get();

        $tempPath = sys_get_temp_dir() . '/ticket_migration_' . uniqid() . '.zip';
        $zip      = new ZipArchive();

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Failed to create ZIP archive');
        }

        $publicDisk      = Storage::disk('public');
        $ticketsData     = [];
        $attachmentCount = 0;

        foreach ($tickets as $ticket) {
            $ticketArr             = (array) $ticket;
            $ticketArr['cc_emails'] = $ticket->cc_emails ? json_decode($ticket->cc_emails, true) : [];

            // Members
            $members = DB::table('ticket_member as tm')
                ->leftJoin('employee as e', 'tm.employee_id', '=', 'e.employee_id')
                ->where('tm.ticket_id', $ticket->ticket_id)
                ->select('tm.employee_id', 'e.eci', 'tm.is_active', 'tm.created_at', 'tm.updated_at')
                ->get();
            $ticketArr['members'] = array_map(fn($m) => (array) $m, $members->toArray());

            // Messages
            $messages    = DB::table('ticket_message')
                ->where('ticket_id', $ticket->ticket_id)
                ->orderBy('id')
                ->get();

            $messagesArr = [];
            foreach ($messages as $msg) {
                $msgArr                          = (array) $msg;
                $msgArr['cc_emails']              = $msg->cc_emails ? json_decode($msg->cc_emails, true) : [];
                $msgArr['mentioned_employee_ids'] = isset($msg->mentioned_employee_ids) && $msg->mentioned_employee_ids
                    ? json_decode($msg->mentioned_employee_ids, true) : [];
                $msgArr['mentioned_role_ids']     = isset($msg->mentioned_role_ids) && $msg->mentioned_role_ids
                    ? json_decode($msg->mentioned_role_ids, true) : [];

                // Attachments for this message
                $attachments = DB::table('ticket_attachment')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->where('message_id', $msg->id)
                    ->get();

                $attArr = [];
                foreach ($attachments as $att) {
                    $a = (array) $att;
                    if (!empty($att->file_path) && $publicDisk->exists($att->file_path)) {
                        $safeTicket  = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticket->ticket_number ?? 'ticket_' . $ticket->ticket_id);
                        $safeFile    = preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($att->file_path));
                        $archivePath = "attachments/{$safeTicket}/msg_{$msg->id}_{$safeFile}";
                        $a['archive_path'] = $archivePath;

                        try {
                            $zip->addFromString($archivePath, $publicDisk->get($att->file_path));
                            $attachmentCount++;
                        } catch (\Exception $e) {
                            $a['archive_path'] = null;
                            Log::warning('TicketMigrationController: attachment read failed', [
                                'file' => $att->file_path, 'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        $a['archive_path'] = null;
                    }
                    $attArr[] = $a;
                }

                $msgArr['attachments'] = $attArr;
                $messagesArr[]         = $msgArr;
            }

            $ticketArr['messages'] = $messagesArr;
            $ticketsData[]         = $ticketArr;
        }

        $meta = [
            'version'          => '1.0',
            'exported_at'      => now()->toISOString(),
            'exported_by'      => session('user.eci') ?? session('user.name') ?? 'admin',
            'ticket_count'     => count($ticketsData),
            'attachment_count' => $attachmentCount,
            'filter'           => ['year' => $year ?: null, 'month' => $month ?: null],
        ];

        $zip->addFromString('manifest.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('tickets.json',  json_encode(['meta' => $meta, 'tickets' => $ticketsData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $periodLabel = $year > 0
            ? ($month > 0 ? date('F', mktime(0, 0, 0, $month, 1)) . '_' . $year : 'year_' . $year)
            : 'all';
        $filename = 'tickets_migration_' . $periodLabel . '_' . date('Ymd_His') . '.zip';

        Log::info('TicketMigrationController: export', [
            'tickets'     => count($ticketsData),
            'attachments' => $attachmentCount,
            'by'          => session('user.eci') ?? 'admin',
        ]);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    // ── Import ZIP ────────────────────────────────────────────────────────────

    public function importZip(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['file' => 'required|file|mimes:zip|max:524288']); // 512 MB

        set_time_limit(300);

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ticket_import_' . uniqid();
        @mkdir($tempDir, 0755, true);

        try {
            $zip = new ZipArchive();
            if ($zip->open($request->file('file')->getRealPath()) !== true) {
                throw new \Exception('File ZIP tidak valid');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $jsonPath = $tempDir . DIRECTORY_SEPARATOR . 'tickets.json';
            if (!file_exists($jsonPath)) {
                throw new \Exception('tickets.json tidak ditemukan di dalam ZIP');
            }

            $data = json_decode(file_get_contents($jsonPath), true);
            if (!$data || !isset($data['tickets'])) {
                throw new \Exception('Format tickets.json tidak valid');
            }

            $tickets    = $data['tickets'];
            $imported   = 0;
            $updated    = 0;
            $skipped    = 0;
            $msgAdded   = 0;
            $filesRestored = 0;
            $errors     = [];
            $publicDisk = Storage::disk('public');

            foreach ($tickets as $idx => $t) {
                $ticketNum = $t['ticket_number'] ?? null;
                if (!$ticketNum) {
                    $errors[] = "Tiket #{$idx}: ticket_number kosong — dilewati";
                    $skipped++;
                    continue;
                }

                try {
                    DB::transaction(function () use (
                        $t, $ticketNum, $tempDir, $publicDisk,
                        &$imported, &$updated, &$msgAdded, &$filesRestored
                    ) {
                        // Resolve FKs by original ID (same-system restore)
                        $customerId   = (isset($t['customer_id'])    && DB::table('customer')->where('customer_id', $t['customer_id'])->exists())    ? (int) $t['customer_id']    : null;
                        $endCustId    = (isset($t['end_customer_id']) && DB::table('customer')->where('customer_id', $t['end_customer_id'])->exists()) ? (int) $t['end_customer_id'] : null;
                        $ticketLeadId = (isset($t['ticket_lead_id'])  && DB::table('employee')->where('employee_id', $t['ticket_lead_id'])->exists())  ? (int) $t['ticket_lead_id']  : null;

                        $ticketData = [
                            'ticket_number'           => $ticketNum,
                            'customer_id'             => $customerId,
                            'end_customer_id'         => $endCustId,
                            'ticket_lead_id'          => $ticketLeadId,
                            'pic'                     => $t['pic'] ?? null,
                            'subject'                 => $t['subject'] ?? null,
                            'description'             => $t['description'] ?? null,
                            'status'                  => $t['status'] ?? 'open',
                            'ticket_priority'         => $t['ticket_priority'] ?? 'Low',
                            'ticket_type'             => $t['ticket_type'] ?? null,
                            'scale'                   => $t['scale'] ?? null,
                            'channel'                 => $t['channel'] ?? 'web',
                            'module'                  => $t['module'] ?? null,
                            'client'                  => $t['client'] ?? null,
                            'name'                    => $t['name'] ?? null,
                            'no_hp'                   => $t['no_hp'] ?? null,
                            'man_days'                => $t['man_days'] ?? null,
                            'wait_close'              => $t['wait_close'] ?? null,
                            'start_date'              => $t['start_date'] ?? null,
                            'end_date'                => $t['end_date'] ?? null,
                            'cc_emails'               => !empty($t['cc_emails']) ? json_encode($t['cc_emails']) : null,
                            'submitted_by_email'      => $t['submitted_by_email'] ?? null,
                            'submitted_by_name'       => $t['submitted_by_name'] ?? null,
                            'progress_percentage'     => $t['progress_percentage'] ?? null,
                            'progress_note'           => $t['progress_note'] ?? null,
                            'last_message_at'         => $t['last_message_at'] ?? null,
                            'last_customer_reply_at'  => $t['last_customer_reply_at'] ?? null,
                            'last_agent_reply_at'     => $t['last_agent_reply_at'] ?? null,
                            'last_internal_note_at'   => $t['last_internal_note_at'] ?? null,
                            'mandays_proposal_status' => $t['mandays_proposal_status'] ?? null,
                            'resolution_days_status'  => $t['resolution_days_status'] ?? null,
                            'email_thread_id'         => $t['email_thread_id'] ?? null,
                            'created_at'              => $t['created_at'] ?? now(),
                            'updated_at'              => now(),
                        ];

                        $existing    = DB::table('ticket')->where('ticket_number', $ticketNum)->first();
                        $isNewTicket = !$existing;

                        if ($existing) {
                            $ticketId = $existing->ticket_id;
                            DB::table('ticket')->where('ticket_id', $ticketId)->update($ticketData);
                            $updated++;
                        } else {
                            $ticketId = DB::table('ticket')->insertGetId($ticketData);
                            $imported++;
                        }

                        // Restore members & messages only for new tickets.
                        // Skipping for existing tickets prevents imported messages
                        // from contaminating a ticket's room chat with messages that
                        // belong to a different context (the source system).
                        if (!$isNewTicket) return;

                        // Restore members
                        foreach ($t['members'] ?? [] as $member) {
                            $empId = $member['employee_id'] ?? null;
                            if (!$empId || !DB::table('employee')->where('employee_id', $empId)->exists()) continue;

                            $alreadyMember = DB::table('ticket_member')
                                ->where('ticket_id', $ticketId)
                                ->where('employee_id', $empId)
                                ->exists();

                            if (!$alreadyMember) {
                                DB::table('ticket_member')->insert([
                                    'ticket_id'   => $ticketId,
                                    'employee_id' => $empId,
                                    'is_active'   => (bool) ($member['is_active'] ?? true),
                                    'created_at'  => $member['created_at'] ?? now(),
                                    'updated_at'  => $member['updated_at'] ?? now(),
                                ]);
                            }
                        }

                        // Restore messages
                        foreach ($t['messages'] ?? [] as $msgData) {
                            // Dedup: match by ticket_id + created_at + sender_email
                            $dedupQuery = DB::table('ticket_message')
                                ->where('ticket_id', $ticketId)
                                ->where('created_at', $msgData['created_at'] ?? null);
                            $senderEmail = $msgData['sender_email'] ?? null;
                            if ($senderEmail === null || $senderEmail === '') {
                                $dedupQuery->whereNull('sender_email');
                            } else {
                                $dedupQuery->where('sender_email', $senderEmail);
                            }

                            if ($dedupQuery->exists()) continue;

                            $newMsgId = DB::table('ticket_message')->insertGetId([
                                'ticket_id'              => $ticketId,
                                'sender_type'            => $msgData['sender_type'] ?? 'system',
                                'sender_id'              => $msgData['sender_id'] ?? null,
                                'sender_email'           => $msgData['sender_email'] ?? null,
                                'sender_name'            => $msgData['sender_name'] ?? null,
                                'message'                => $msgData['message'] ?? '',
                                'message_html'           => $msgData['message_html'] ?? null,
                                'is_internal_note'       => (int) (bool) ($msgData['is_internal_note'] ?? false),
                                'reply_to_id'            => null,
                                'channel'                => $msgData['channel'] ?? 'web',
                                'email_message_id'       => $msgData['email_message_id'] ?? null,
                                'email_in_reply_to'      => $msgData['email_in_reply_to'] ?? null,
                                'cc_emails'              => !empty($msgData['cc_emails']) ? json_encode($msgData['cc_emails']) : null,
                                'mentioned_employee_ids' => !empty($msgData['mentioned_employee_ids']) ? json_encode($msgData['mentioned_employee_ids']) : null,
                                'mentioned_role_ids'     => !empty($msgData['mentioned_role_ids']) ? json_encode($msgData['mentioned_role_ids']) : null,
                                'is_read_by_customer'    => (int) (bool) ($msgData['is_read_by_customer'] ?? false),
                                'is_read_by_agent'       => (int) (bool) ($msgData['is_read_by_agent'] ?? false),
                                'read_at'                => $msgData['read_at'] ?? null,
                                'created_at'             => $msgData['created_at'] ?? now(),
                                'updated_at'             => $msgData['updated_at'] ?? now(),
                            ]);
                            $msgAdded++;

                            // Restore attachments
                            foreach ($msgData['attachments'] ?? [] as $att) {
                                $restoredPath = null;

                                if (!empty($att['archive_path'])) {
                                    $localFile = $tempDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $att['archive_path']);
                                    if (file_exists($localFile)) {
                                        $safeTicket   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticketNum);
                                        $safeFile     = preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($att['archive_path']));
                                        $destPath     = "ticket-attachments/{$safeTicket}/{$safeFile}";
                                        $publicDisk->put($destPath, file_get_contents($localFile));
                                        $restoredPath = $destPath;
                                        $filesRestored++;
                                    }
                                }

                                DB::table('ticket_attachment')->insert([
                                    'ticket_id'        => $ticketId,
                                    'message_id'       => $newMsgId,
                                    'uploaded_by_type' => $att['uploaded_by_type'] ?? 'employee',
                                    'uploaded_by_id'   => $att['uploaded_by_id'] ?? 0,
                                    'attachment_type'  => $att['attachment_type'] ?? 'file',
                                    'link_url'         => $restoredPath ?? $att['link_url'] ?? null,
                                    'link_title'       => $att['link_title'] ?? null,
                                    'description'      => $att['description'] ?? null,
                                    'file_path'        => $restoredPath ?? $att['file_path'] ?? null,
                                    'file_name'        => $att['file_name'] ?? basename((string) ($att['archive_path'] ?? '')),
                                    'file_size'        => $att['file_size'] ?? null,
                                    'mime_type'        => $att['mime_type'] ?? null,
                                    'is_inline'        => (int) (bool) ($att['is_inline'] ?? false),
                                    'created_at'       => $att['created_at'] ?? now(),
                                    'updated_at'       => $att['updated_at'] ?? now(),
                                ]);
                            }
                        }
                    });
                } catch (\Exception $e) {
                    $errors[] = "Tiket {$ticketNum}: " . $e->getMessage();
                    $skipped++;
                }
            }

            $this->removeTempDir($tempDir);

            Log::info('TicketMigrationController: import', [
                'imported'      => $imported,
                'updated'       => $updated,
                'skipped'       => $skipped,
                'messages'      => $msgAdded,
                'files'         => $filesRestored,
                'errors'        => count($errors),
                'by'            => session('user.eci') ?? 'admin',
            ]);

            $summary = "Import selesai: {$imported} tiket baru, {$updated} diperbarui, {$skipped} dilewati, {$msgAdded} pesan, {$filesRestored} file";
            if (count($errors)) $summary .= ', ' . count($errors) . ' error';

            return response()->json([
                'success'       => true,
                'message'       => $summary,
                'imported'      => $imported,
                'updated'       => $updated,
                'skipped'       => $skipped,
                'messages'      => $msgAdded,
                'files'         => $filesRestored,
                'errors'        => $errors,
            ]);
        } catch (\Exception $e) {
            $this->removeTempDir($tempDir);
            Log::error('TicketMigrationController@importZip', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Import gagal: ' . $e->getMessage()], 500);
        }
    }

    // ── Import from External API ──────────────────────────────────────────────

    public function importFromApi(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'url_pattern'      => 'required|string|max:500',
            'file'             => 'required|file|mimes:csv,txt|max:10240',
            'default_status'   => 'required|string',
            'default_customer' => 'nullable|integer',
        ]);

        set_time_limit(300);

        $urlPattern     = trim($request->input('url_pattern'));
        $defaultStatus  = $request->input('default_status');
        $defaultCustId  = $request->input('default_customer') ? (int) $request->input('default_customer') : null;

        // Validate URL pattern contains placeholder
        if (!str_contains($urlPattern, '{ticket_number}')) {
            return response()->json(['success' => false, 'message' => 'URL pattern harus mengandung {ticket_number}'], 422);
        }

        // Parse CSV — get list of ticket numbers
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $bom    = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $ticketNumbers = [];
        $skipHeaders   = ['ticket_number', 'no ticket', 'no. ticket', 'nomor tiket', 'ticket_no', 'objid', 'p_objid', 'no'];
        while (($row = fgetcsv($handle)) !== false) {
            $val = trim($row[0] ?? '');
            if ($val !== '' && !in_array(strtolower($val), $skipHeaders, true)) {
                $ticketNumbers[] = $val;
            }
        }
        fclose($handle);

        if (empty($ticketNumbers)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada nomor tiket ditemukan di CSV'], 422);
        }

        $imported   = 0;
        $updated    = 0;
        $skipped    = 0;
        $msgAdded   = 0;
        $errors     = [];

        foreach ($ticketNumbers as $ticketNum) {
            $url = str_replace('{ticket_number}', rawurlencode($ticketNum), $urlPattern);

            try {
                $response = Http::timeout(30)->get($url);

                if (!$response->successful()) {
                    $errors[] = "{$ticketNum}: HTTP {$response->status()} dari API";
                    $skipped++;
                    continue;
                }

                $lines = $response->json();
                if (!is_array($lines) || empty($lines)) {
                    $errors[] = "{$ticketNum}: Response kosong atau bukan array";
                    $skipped++;
                    continue;
                }

                // Parse blocks (split by hdr:X, merge continuation lines)
                $blocks = $this->parseBlocks($lines);
                if (empty($blocks)) {
                    $errors[] = "{$ticketNum}: Tidak ada blok pesan ditemukan";
                    $skipped++;
                    continue;
                }

                // First block = ticket metadata + initial description
                $meta   = $this->parseFirstBlock($blocks[0]['lines']);
                $emails = $this->splitEmails($meta['email'] ?? '');

                // Map priority
                $priorityMap = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
                $priority    = $priorityMap[strtolower($meta['priority'] ?? '')] ?? 'Low';

                // Verify customer FK
                $customerId = $defaultCustId;
                if ($customerId && !DB::table('customer')->where('customer_id', $customerId)->exists()) {
                    $customerId = null;
                }

                DB::transaction(function () use (
                    $ticketNum, $meta, $emails, $priority, $customerId,
                    $defaultStatus, $blocks,
                    &$imported, &$updated, &$msgAdded
                ) {
                    $ticketData = [
                        'ticket_number'      => $ticketNum,
                        'customer_id'        => $customerId,
                        'subject'            => $ticketNum,
                        'description'        => $meta['description'] ?? null,
                        'status'             => $defaultStatus,
                        'ticket_priority'    => $priority,
                        'channel'            => 'web',
                        'module'             => $meta['modul'] ?? null,
                        'name'               => $meta['nama'] ?? null,
                        'no_hp'              => $meta['no_hp'] ?? null,
                        'submitted_by_name'  => $meta['nama'] ?? null,
                        'submitted_by_email' => $emails[0] ?? null,
                        'cc_emails'          => count($emails) > 1 ? json_encode(array_slice($emails, 1)) : null,
                        'created_at'         => $this->parseDmyTimestamp($blocks[0]['ket']),
                        'updated_at'         => now(),
                    ];

                    $existing = DB::table('ticket')->where('ticket_number', $ticketNum)->first();
                    if ($existing) {
                        $ticketId = $existing->ticket_id;
                        DB::table('ticket')->where('ticket_id', $ticketId)->update($ticketData);
                        $updated++;
                    } else {
                        $ticketId = DB::table('ticket')->insertGetId($ticketData);
                        $imported++;
                    }

                    // Import all blocks as messages (first block = opening message)
                    foreach ($blocks as $i => $block) {
                        $msgText  = implode("\n", array_filter($block['lines'], fn($l) => trim($l) !== ''));
                        $msgText  = trim($msgText);
                        if ($msgText === '') continue;

                        $msgAt   = $this->parseDmyTimestamp($block['ket']);
                        $isAgent = $this->detectAgentMessage($block['lines']);

                        // Dedup
                        $exists = DB::table('ticket_message')
                            ->where('ticket_id', $ticketId)
                            ->where('created_at', $msgAt)
                            ->exists();
                        if ($exists) continue;

                        DB::table('ticket_message')->insert([
                            'ticket_id'    => $ticketId,
                            'sender_type'  => $isAgent ? 'employee' : 'customer',
                            'sender_id'    => null,
                            'sender_email' => $isAgent ? null : ($emails[0] ?? null),
                            'sender_name'  => $isAgent ? 'Eclectic Support' : ($meta['nama'] ?? null),
                            'message'      => $msgText,
                            'message_html' => null,
                            'is_internal_note' => 0,
                            'channel'      => 'web',
                            'created_at'   => $msgAt,
                            'updated_at'   => $msgAt,
                        ]);
                        $msgAdded++;
                    }
                });
            } catch (\Exception $e) {
                $errors[] = "{$ticketNum}: " . $e->getMessage();
                $skipped++;
            }
        }

        Log::info('TicketMigrationController: importFromApi', [
            'total'    => count($ticketNumbers),
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'messages' => $msgAdded,
            'errors'   => count($errors),
            'by'       => session('user.eci') ?? 'admin',
        ]);

        $summary = "Selesai: {$imported} tiket baru, {$updated} diperbarui, {$skipped} dilewati, {$msgAdded} pesan";
        if (count($errors)) $summary .= ', ' . count($errors) . ' error';

        return response()->json([
            'success'  => true,
            'message'  => $summary,
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'messages' => $msgAdded,
            'errors'   => $errors,
        ]);
    }

    // ── Parser helpers ────────────────────────────────────────────────────────

    /**
     * Split raw API lines into message blocks.
     * Each block starts at hdr:"X". Lines with tdformat:"=" are merged
     * into the previous line (SAP long-text continuation convention).
     *
     * @return array<int, array{ket: string, lines: string[]}>
     */
    private function parseBlocks(array $lines): array
    {
        $blocks  = [];
        $curKet  = null;
        $curRaw  = [];

        foreach ($lines as $line) {
            if (($line['hdr'] ?? '') === 'X') {
                if ($curKet !== null) {
                    $blocks[] = ['ket' => $curKet, 'lines' => $this->mergeContinuations($curRaw)];
                }
                $curKet = $line['ket'] ?? '';
                $curRaw = [];
            } else {
                $curRaw[] = $line;
            }
        }

        if ($curKet !== null && !empty($curRaw)) {
            $blocks[] = ['ket' => $curKet, 'lines' => $this->mergeContinuations($curRaw)];
        }

        return $blocks;
    }

    /** Merge tdformat:"=" continuation lines into their predecessor. */
    private function mergeContinuations(array $rawLines): array
    {
        $merged = [];
        foreach ($rawLines as $line) {
            $text = $line['tdline'] ?? '';
            if (($line['tdformat'] ?? '') === '=' && !empty($merged)) {
                $merged[count($merged) - 1] .= $text;
            } else {
                $merged[] = $text;
            }
        }
        return $merged;
    }

    /**
     * Parse the first block which contains structured ticket metadata.
     * Returns associative array: nama, divisi, modul, no_hp, email, priority, description.
     */
    private function parseFirstBlock(array $lines): array
    {
        $meta      = [];
        $descLines = [];
        $inDesc    = false;

        $keyAliases = [
            'nama'     => ['nama'],
            'divisi'   => ['divisi'],
            'modul'    => ['modul', 'module'],
            'no_hp'    => ['no hp', 'nohp', 'no. hp', 'telp', 'telepon', 'phone'],
            'email'    => ['email', 'e-mail', 'e mail'],
            'priority' => ['priority', 'prioritas'],
        ];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (!$inDesc && preg_match('/^(.+?)\s*:\s*(.*)$/', $trimmed, $m)) {
                $keyRaw = strtolower(trim($m[1]));
                $val    = trim($m[2]);

                $matched = null;
                foreach ($keyAliases as $canonical => $aliases) {
                    if (in_array($keyRaw, $aliases, true)) {
                        $matched = $canonical;
                        break;
                    }
                }

                if ($matched) {
                    $meta[$matched] = $val;
                    // "issued :" signals end of metadata, rest is description
                    if (strtolower(trim($m[1])) === 'issued') {
                        $inDesc = true;
                    }
                    continue;
                }
            }

            // Non-key line → part of description
            $inDesc = true;
            if ($trimmed !== '') {
                $descLines[] = $trimmed;
            }
        }

        $meta['description'] = trim(implode("\n", $descLines));
        return $meta;
    }

    /** Split a comma-separated email string into a clean array. */
    private function splitEmails(string $raw): array
    {
        if ($raw === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Detect whether a message block was sent by the support team.
     * Heuristic: contains signature keywords.
     */
    private function detectAgentMessage(array $lines): bool
    {
        $agentMarkers = ['eclectic support', 'regards', 'eclectic support team'];
        foreach ($lines as $line) {
            foreach ($agentMarkers as $marker) {
                if (str_contains(strtolower($line), $marker)) return true;
            }
        }
        return false;
    }

    /**
     * Parse a timestamp in "d.m.Y H:i:s" format (e.g. "15.01.2026 01:38:02")
     * into a MySQL-compatible datetime string.
     */
    private function parseDmyTimestamp(string $raw): string
    {
        try {
            return \Carbon\Carbon::createFromFormat('d.m.Y H:i:s', trim($raw))->toDateTimeString();
        } catch (\Exception $e) {
            return now()->toDateTimeString();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        try {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
            }
            @rmdir($dir);
        } catch (\Exception $e) {
            Log::warning('TicketMigrationController: temp dir cleanup failed', ['dir' => $dir]);
        }
    }
}
