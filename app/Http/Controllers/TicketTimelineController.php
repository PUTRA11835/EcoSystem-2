<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketSla;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Log perjalanan sebuah tiket untuk panel "Detail" di list ticket: siapa
 * mengubah apa dan jam berapa. Sumbernya tabel audit_logs (sudah mencatat
 * Ticket, anggota, dokumen deliverable, attachment, SLA, dst), jadi tidak ada
 * data baru yang ditulis. Baris anak (member, deliverable, ...) tidak
 * menyimpan ticket_id yang bisa di-query langsung, tapi deskripsinya selalu
 * diakhiri "Ticket #<ticket_number>" (lihat AuditObserver::subjectSuffix()),
 * dan itulah yang dipakai untuk mengaitkannya ke tiket.
 */
class TicketTimelineController extends Controller
{
    private const LIMIT = 500;

    /** Perubahan yang hanya menyentuh kolom bookkeeping ini tidak ditampilkan. */
    private const NOISE_FIELDS = [
        'last_internal_note_at',
        'last_internal_note_sender_id',
        'last_message_at',
        'last_customer_reply_at',
        'last_agent_reply_at',
        'updated_at',
        'email_thread_id',
    ];

    /** Daftar alamat email: dibandingkan sebagai himpunan, supaya perubahan format saja tidak muncul. */
    private const EMAIL_LIST_FIELDS = ['cc_emails', 'to_emails', 'bcc_emails'];

    /** Kolom berisi employee_id / customer_id / module_id yang diterjemahkan jadi nama. */
    private const EMPLOYEE_FIELDS = ['ticket_lead_id', 'employee_id', 'uploaded_by', 'created_by', 'last_internal_note_sender_id'];
    private const CUSTOMER_FIELDS = ['customer_id', 'end_customer_id'];
    private const MODULE_FIELDS   = ['module_id'];

    /** Kolom yang tidak berguna ditampilkan sebagai "perubahan". */
    private const HIDDEN_FIELDS = ['id', 'ticket_id', 'created_at', 'file_path', 'password'];

    /**
     * GET /api/tickets/{id}/timeline
     */
    public function index($id)
    {
        if (!session('user')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ticket = Ticket::where('ticket_id', $id)->firstOrFail(['ticket_id', 'ticket_number']);
        $suffix = 'Ticket #' . $ticket->ticket_number;

        // TicketSla dibuat tanpa ticket_id di snapshot-nya, jadi dikaitkan lewat id SLA milik tiket ini.
        $slaIds = TicketSla::where('ticket_id', $ticket->ticket_id)->pluck('id');

        $rows = DB::table('audit_logs')
            ->where(function ($q) use ($ticket, $suffix, $slaIds) {
                $q->where(fn ($x) => $x->where('auditable_type', 'Ticket')->where('auditable_id', $ticket->ticket_id))
                  ->orWhere('description', 'like', '%' . $suffix)
                  ->orWhere(fn ($x) => $x->where('auditable_type', 'TicketSla')->whereIn('auditable_id', $slaIds));
            })
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $entries = [];
        $ids = ['employee' => [], 'customer' => [], 'module' => []];

        foreach ($rows as $row) {
            $old = $this->decode($row->old_values);
            $new = $this->decode($row->new_values);

            if ($row->event === 'updated' && $this->isNoise($new)) {
                continue;
            }

            $entries[] = compact('row', 'old', 'new');
            $this->collectIds($ids, $old, $new);
        }

        $names = $this->resolveNames($ids);

        $data = [];
        foreach ($entries as $e) {
            $item = $this->format($e['row'], $e['old'], $e['new'], $names);
            // Update yang setelah disaring tidak punya perubahan berarti (mis. hanya format CC email) disembunyikan.
            if ($e['row']->event === 'updated' && empty($item['changes']) && !$this->isStatusChange($e['row'], $e['new'])) {
                continue;
            }
            $data[] = $item;
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
            'limit'   => self::LIMIT,
        ]);
    }

    private function isStatusChange(object $row, array $new): bool
    {
        return $row->auditable_type === 'TicketMember' && array_key_exists('is_active', $new);
    }

    private function decode($json): array
    {
        $arr = is_string($json) ? json_decode($json, true) : $json;

        return is_array($arr) ? $arr : [];
    }

    private function isNoise(array $new): bool
    {
        return !empty($new) && empty(array_diff(array_keys($new), self::NOISE_FIELDS));
    }

    private function collectIds(array &$ids, array $old, array $new): void
    {
        foreach ([$old, $new] as $set) {
            foreach ($set as $key => $value) {
                if (!is_numeric($value)) {
                    continue;
                }
                if (in_array($key, self::EMPLOYEE_FIELDS, true)) {
                    $ids['employee'][(int) $value] = true;
                } elseif (in_array($key, self::CUSTOMER_FIELDS, true)) {
                    $ids['customer'][(int) $value] = true;
                } elseif (in_array($key, self::MODULE_FIELDS, true)) {
                    $ids['module'][(int) $value] = true;
                }
            }
        }
    }

    /** @return array{employee: array<int,string>, customer: array<int,string>, module: array<int,string>} */
    private function resolveNames(array $ids): array
    {
        $names = ['employee' => [], 'customer' => [], 'module' => []];

        if ($ids['employee']) {
            $names['employee'] = DB::table('employee_basic_data')
                ->whereIn('employee_id', array_keys($ids['employee']))
                ->get(['employee_id', 'first_name', 'last_name'])
                ->mapWithKeys(fn ($r) => [(int) $r->employee_id => trim($r->first_name . ' ' . $r->last_name)])
                ->all();
        }

        if ($ids['customer']) {
            $names['customer'] = DB::table('customer_basic_data')
                ->whereIn('customer_id', array_keys($ids['customer']))
                ->pluck('name_1', 'customer_id')
                ->all();
        }

        if ($ids['module']) {
            $names['module'] = DB::table('modules')
                ->whereIn('id', array_keys($ids['module']))
                ->pluck('name', 'id')
                ->all();
        }

        return $names;
    }

    private function format(object $row, array $old, array $new, array $names): array
    {
        // created_at di audit_logs disimpan UTC (useCurrent pada DB), bukan waktu lokal aplikasi.
        $at = Carbon::parse($row->created_at, 'UTC')->setTimezone(config('app.timezone'))->locale('id');

        // Untuk created/deleted tampilkan isi singkat record-nya; untuk updated, tiap kolom yang berubah.
        $changes = $row->event === 'updated'
            ? $this->changes($old, $new, $names)
            : $this->snapshotDetails($row->auditable_type, $row->event === 'deleted' ? $old : $new, $names);

        return [
            'id'       => $row->id,
            'type'     => $row->auditable_type,
            'event'    => $row->event,
            'title'    => $this->title($row, $old, $new, $names),
            'actor'    => $row->actor_name ?: 'System',
            'at'       => $at->toIso8601String(),
            'at_label' => $at->isoFormat('dddd, DD MMM YYYY | HH:mm'),
            'changes'  => $changes,
        ];
    }

    private function title(object $row, array $old, array $new, array $names): string
    {
        $snap = $row->event === 'deleted' ? $old : $new;
        $verb = ['created' => 'added', 'deleted' => 'deleted', 'updated' => 'updated'][$row->event] ?? $row->event;

        switch ($row->auditable_type) {
            case 'Ticket':
                if ($row->event === 'created') {
                    return 'Ticket created';
                }
                if ($row->event === 'deleted') {
                    return 'Ticket deleted';
                }
                if (array_key_exists('status', $new)) {
                    return 'Ticket status changed to ' . $this->humanize((string) $new['status']);
                }

                return 'Ticket updated';

            case 'TicketMember':
                $name = $names['employee'][(int) ($snap['employee_id'] ?? 0)]
                    ?? trim(explode(' on Ticket', (string) $row->record_label)[0]);
                if ($row->event === 'created') {
                    return "Member added: {$name}";
                }
                if ($row->event === 'deleted' || (array_key_exists('is_active', $new) && !$new['is_active'])) {
                    return "Member removed: {$name}";
                }

                return "Member updated: {$name}";

            case 'TicketDeliverable':
                $label = $snap['doc_type'] ?? 'Document';

                return $row->event === 'created'
                    ? "Deliverable document uploaded: {$label}"
                    : ($row->event === 'deleted' ? "Deliverable document deleted: {$label}" : 'Deliverable document updated');

            case 'TicketAttachment':
                $file = $snap['file_name'] ?? 'file';

                return $row->event === 'created'
                    ? "Attachment added: {$file}"
                    : ($row->event === 'deleted' ? "Attachment deleted: {$file}" : 'Attachment updated');

            case 'TicketSla':
                return 'SLA ' . $verb;
        }

        // Record anak lain (mandays, dst): pakai deskripsi audit tanpa ringkasan kurung dan buntut nomor tiket.
        $text = preg_replace('/\s*\([^)]*\)\s*/', ' ', (string) $row->description);
        $text = preg_replace('/\s*[-\x{2014}]\s*Ticket #\S+$/u', '', trim($text));

        return trim($text) !== '' ? trim($text) : ucfirst($verb) . ' ' . $row->auditable_type;
    }

    /** Daftar from -> to untuk event "updated". */
    private function changes(array $old, array $new, array $names): array
    {
        $out = [];
        foreach ($new as $key => $value) {
            if (in_array($key, self::HIDDEN_FIELDS, true) || in_array($key, self::NOISE_FIELDS, true)) {
                continue;
            }
            if (in_array($key, self::EMAIL_LIST_FIELDS, true)) {
                $from = $this->addresses($old[$key] ?? null);
                $to   = $this->addresses($value);
                if ($from === $to) {
                    continue;
                }
                $out[] = [
                    'label' => $this->fieldLabel($key),
                    'from'  => $from ? implode(', ', $from) : '(empty)',
                    'to'    => $to ? implode(', ', $to) : '(empty)',
                ];
                continue;
            }

            $out[] = [
                'label' => $this->fieldLabel($key),
                'from'  => $this->value($key, $old[$key] ?? null, $names),
                'to'    => $this->value($key, $value, $names),
            ];
        }

        return $out;
    }

    /** @return array<int,string> alamat email unik terurut, dari array string / array {address,name} / JSON string. */
    private function addresses($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : ($value === '' ? [] : [$value]);
        }

        $list = [];
        foreach ((array) $value as $item) {
            $addr = is_array($item) ? ($item['address'] ?? '') : (string) $item;
            if ($addr !== '') {
                $list[] = strtolower(trim($addr));
            }
        }
        $list = array_values(array_unique($list));
        sort($list);

        return $list;
    }

    /** Isi singkat record untuk event created/deleted (tanpa from/to). */
    private function snapshotDetails(string $type, array $snap, array $names): array
    {
        $keys = match ($type) {
            'TicketDeliverable' => ['file_name', 'description', 'status'],
            'TicketAttachment'  => [],
            default             => [],
        };

        $out = [];
        foreach ($keys as $key) {
            if (isset($snap[$key]) && $snap[$key] !== '') {
                $out[] = ['label' => $this->fieldLabel($key), 'from' => null, 'to' => $this->value($key, $snap[$key], $names)];
            }
        }

        return $out;
    }

    private function fieldLabel(string $key): string
    {
        $label = ucwords(str_replace('_', ' ', preg_replace('/_(id|at)$/', '', $key)));

        return preg_replace_callback('/\b(Sla|Pic|Md)\b/', fn ($m) => strtoupper($m[1]), $label);
    }

    private function value(string $key, $value, array $names): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_numeric($value)) {
            $v = (int) $value;
            if (in_array($key, self::EMPLOYEE_FIELDS, true)) {
                return $names['employee'][$v] ?? "#{$value}";
            }
            if (in_array($key, self::CUSTOMER_FIELDS, true)) {
                return $names['customer'][$v] ?? "#{$value}";
            }
            if (in_array($key, self::MODULE_FIELDS, true)) {
                return $names['module'][$v] ?? "#{$value}";
            }
        }

        $str = (string) $value;

        // Timestamp dari audit: ISO dengan "Z" = UTC, tanpa zona = sudah waktu lokal aplikasi.
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $str)) {
            return Carbon::parse($str)->setTimezone(config('app.timezone'))->format('d M Y H:i');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $str)) {
            return Carbon::parse($str)->format('d M Y H:i');
        }

        if (in_array($key, ['status', 'ticket_priority', 'scale', 'ball_holder'], true) || str_ends_with($key, '_status')) {
            return $this->humanize($str);
        }

        return mb_strlen($str) > 200 ? mb_substr($str, 0, 200) . '...' : $str;
    }

    private function humanize(string $status): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $status));
    }
}
