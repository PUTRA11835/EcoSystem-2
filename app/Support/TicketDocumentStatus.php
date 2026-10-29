<?php

namespace App\Support;

use App\Models\TicketDeliverable;
use App\Models\TicketDeliverableRequirement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "Document Status" kolom di list ticket: kelengkapan dokumen deliverable
 * berdasarkan doc type yang ditandai Mandatory di window Deliverable Documents.
 * Complete = semua doc type mandatory sudah punya minimal satu dokumen;
 * Incomplete = ada yang belum. Tiket tanpa doc type mandatory tidak punya status.
 */
final class TicketDocumentStatus
{
    public const COMPLETE   = 'complete';
    public const INCOMPLETE = 'incomplete';

    /**
     * Dihitung batch (2 query) untuk satu halaman tiket, bukan per tiket.
     *
     * @param  iterable<int> $ticketIds
     * @return array<int, string> ticket_id => complete|incomplete (tiket tanpa mandatory tidak ada di array)
     */
    public static function forTickets(iterable $ticketIds): array
    {
        $ticketIds = collect($ticketIds)->unique()->values();
        if ($ticketIds->isEmpty()) {
            return [];
        }

        $mandatory = TicketDeliverableRequirement::whereIn('ticket_id', $ticketIds)
            ->where('is_mandatory', true)
            ->get(['ticket_id', 'doc_type'])
            ->groupBy('ticket_id');

        if ($mandatory->isEmpty()) {
            return [];
        }

        $uploaded = TicketDeliverable::whereIn('ticket_id', $mandatory->keys())
            ->get(['ticket_id', 'doc_type'])
            ->groupBy('ticket_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => self::key($r->doc_type))->unique()->all());

        $result = [];
        foreach ($mandatory as $ticketId => $rows) {
            $have = $uploaded->get($ticketId, []);
            $missing = $rows->contains(fn ($r) => !in_array(self::key($r->doc_type), $have, true));
            $result[$ticketId] = $missing ? self::INCOMPLETE : self::COMPLETE;
        }

        return $result;
    }

    /** Nilai filter "tiket tanpa doc type mandatory" (kolom menampilkan strip). */
    public const NONE = 'none';

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

        $hasMandatory = fn () => DB::table('ticket_deliverable_requirements as r')
            ->selectRaw('1')
            ->whereColumn('r.ticket_id', 'ticket.ticket_id')
            ->where('r.is_mandatory', true);

        // Ada doc type mandatory yang belum punya dokumen.
        $hasMissing = fn () => $hasMandatory()->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('ticket_deliverables as td')
                ->whereColumn('td.ticket_id', 'ticket.ticket_id')
                ->whereRaw('UPPER(TRIM(td.doc_type)) = UPPER(TRIM(r.doc_type))');
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
