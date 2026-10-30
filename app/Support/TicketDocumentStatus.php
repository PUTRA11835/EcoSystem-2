<?php

namespace App\Support;

use App\Models\TicketDeliverable;
use Illuminate\Support\Facades\DB;

/**
 * "Document Status" kolom di list ticket: kelengkapan dokumen deliverable
 * berdasarkan doc type MANDATORY tiap tiket (default per ticket type, atau
 * pengaturan tiket itu sendiri; lihat DeliverableRequirements).
 * Complete = semua doc type mandatory sudah punya minimal satu dokumen;
 * Incomplete = ada yang belum. Tiket tanpa doc type mandatory tidak punya status.
 */
final class TicketDocumentStatus
{
    public const COMPLETE   = 'complete';
    public const INCOMPLETE = 'incomplete';

    /** Nilai filter "tiket tanpa doc type mandatory" (kolom menampilkan strip). */
    public const NONE = 'none';

    /**
     * Dihitung batch untuk satu halaman tiket, bukan per tiket.
     *
     * @param  iterable<int> $ticketIds
     * @return array<int, string> ticket_id => complete|incomplete (tiket tanpa mandatory tidak ada di array)
     */
    public static function forTickets(iterable $ticketIds): array
    {
        $mandatory = array_filter(DeliverableRequirements::mandatoryByTicket($ticketIds));

        if (empty($mandatory)) {
            return [];
        }

        $uploaded = TicketDeliverable::whereIn('ticket_id', array_keys($mandatory))
            ->get(['ticket_id', 'doc_type'])
            ->groupBy('ticket_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => self::key($r->doc_type))->unique()->all());

        $result = [];
        foreach ($mandatory as $ticketId => $names) {
            $have = $uploaded->get($ticketId, []);
            $missing = collect($names)->contains(fn ($n) => !in_array(self::key($n), $have, true));
            $result[$ticketId] = $missing ? self::INCOMPLETE : self::COMPLETE;
        }

        return $result;
    }

    /**
     * Filter list ticket by Document Status (multi-select, OR antar nilai).
     * $raw = nilai dipisah koma dari complete | incomplete | none. Logikanya
     * sama dengan forTickets() tapi dalam SQL, supaya berlaku ke seluruh
     * hasil (bukan hanya halaman yang dimuat). Query harus memuat tabel
     * `ticket`.
     */
    public static function applyFilter($query, ?string $raw): void
    {
        $values = array_values(array_intersect(
            array_filter(explode(',', (string) $raw), fn ($v) => $v !== ''),
            [self::COMPLETE, self::INCOMPLETE, self::NONE]
        ));
        if (empty($values)) {
            return;
        }

        // Mandatory yang berlaku per tiket (baris sendiri, atau default tipenya).
        $effective = DeliverableRequirements::effectiveMandatoryQuery();

        $hasMandatory = fn () => DB::query()
            ->fromSub($effective, 'e')
            ->selectRaw('1')
            ->whereColumn('e.ticket_id', 'ticket.ticket_id');

        // Ada doc type mandatory yang belum punya dokumen.
        $hasMissing = fn () => $hasMandatory()->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('ticket_deliverables as td')
                ->whereColumn('td.ticket_id', 'ticket.ticket_id')
                ->whereRaw('UPPER(TRIM(td.doc_type)) = UPPER(TRIM(e.doc_type))');
        });

        $query->where(function ($q) use ($values, $hasMandatory, $hasMissing) {
            if (in_array(self::INCOMPLETE, $values, true)) {
                $q->orWhereExists($hasMissing());
            }
            if (in_array(self::COMPLETE, $values, true)) {
                $q->orWhere(function ($c) use ($hasMandatory, $hasMissing) {
                    $c->whereExists($hasMandatory())->whereNotExists($hasMissing());
                });
            }
            if (in_array(self::NONE, $values, true)) {
                $q->orWhereNotExists($hasMandatory());
            }
        });
    }

    private static function key(?string $docType): string
    {
        return strtoupper(trim((string) $docType));
    }
}
