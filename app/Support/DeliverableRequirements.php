<?php

namespace App\Support;

use App\Models\DeliverableDocumentType;
use App\Models\DeliverableDocumentTypeTicketType;
use App\Models\Ticket;
use App\Models\TicketDeliverableRequirement;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Doc type mana yang MANDATORY untuk sebuah tiket. Dua lapis:
 *
 *  1. Default per ticket type, diatur di Management > Master Ticket Settings >
 *     Document Type (tabel deliverable_document_type_ticket_types). Tiket yang
 *     belum pernah diatur sendiri mengikuti default ini secara live: mengubah
 *     default langsung berlaku ke semua tiket tipe itu yang belum
 *     di-customize.
 *  2. Pengaturan per tiket (ticket_deliverable_requirements), diisi dari window
 *     "Deliverable Documents" di tiket. Begitu tiket punya baris sendiri, baris
 *     itulah yang berlaku (default diabaikan) sampai di-reset atau ticket_type
 *     tiket berubah (lihat Ticket::booted()).
 *
 * Hanya doc type yang aktif yang dihitung.
 */
final class DeliverableRequirements
{
    /**
     * Mandatory default per ticket type, urut sesuai order_seq doc type.
     *
     * @param  array<int,string>|null $ticketTypes batasi ke tipe tertentu; null = semua
     * @return array<string, array<int,string>> ticket_type => nama doc type
     */
    public static function defaultsByType(?array $ticketTypes = null): array
    {
        $query = DeliverableDocumentTypeTicketType::where('is_mandatory', true)
            ->whereHas('documentType', fn ($q) => $q->active())
            ->with('documentType');

        if ($ticketTypes !== null) {
            $query->whereIn('ticket_type', $ticketTypes);
        }

        return $query->get()
            ->sortBy(fn ($row) => $row->documentType->order_seq)
            ->groupBy('ticket_type')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->documentType->name)->values()->all())
            ->all();
    }

    /**
     * Nama doc type aktif, urut order_seq.
     *
     * @return array<int,string>
     */
    public static function activeTypeNames(): array
    {
        return DeliverableDocumentType::active()
            ->orderBy('order_seq')->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Untuk satu tiket: mandatory yang berlaku, default tipenya, dan apakah
     * tiket ini sudah punya pengaturan sendiri.
     *
     * @return array{mandatory: array<int,string>, default: array<int,string>, customized: bool}
     */
    public static function forTicket(Ticket $ticket): array
    {
        $default = $ticket->ticket_type
            ? (self::defaultsByType([$ticket->ticket_type])[$ticket->ticket_type] ?? [])
            : [];

        $rows = $ticket->deliverableRequirements()->get(['doc_type', 'is_mandatory']);

        if ($rows->isEmpty()) {
            return ['mandatory' => $default, 'default' => $default, 'customized' => false];
        }

        $marked = $rows->where('is_mandatory', true)->pluck('doc_type')->all();

        return [
            'mandatory'  => array_values(array_filter(self::activeTypeNames(), fn ($n) => in_array($n, $marked, true))),
            'default'    => $default,
            'customized' => true,
        ];
    }

    /**
     * Mandatory yang berlaku untuk banyak tiket sekaligus (3 query), dipakai
     * list ticket supaya tidak N+1.
     *
     * @param  iterable<int> $ticketIds
     * @return array<int, array<int,string>> ticket_id => nama doc type mandatory
     */
    public static function mandatoryByTicket(iterable $ticketIds): array
    {
        $ticketIds = collect($ticketIds)->unique()->values();
        if ($ticketIds->isEmpty()) {
            return [];
        }

        $types    = Ticket::whereIn('ticket_id', $ticketIds)->pluck('ticket_type', 'ticket_id');
        $own      = TicketDeliverableRequirement::whereIn('ticket_id', $ticketIds)
            ->get(['ticket_id', 'doc_type', 'is_mandatory'])
            ->groupBy('ticket_id');
        $defaults = self::defaultsByType($types->filter()->unique()->values()->all());
        $active   = self::activeTypeNames();

        $result = [];
        foreach ($ticketIds as $id) {
            if ($own->has($id)) {
                $marked = $own[$id]->where('is_mandatory', true)->pluck('doc_type')->all();
                $result[$id] = array_values(array_filter($active, fn ($n) => in_array($n, $marked, true)));
            } else {
                $result[$id] = $defaults[$types[$id] ?? ''] ?? [];
            }
        }

        return $result;
    }

    /**
     * Subquery (ticket_id, doc_type) berisi mandatory yang berlaku untuk SETIAP
     * tiket: baris sendiri bila ada, selain itu default tipenya. Versi SQL dari
     * mandatoryByTicket(), untuk filter list ticket. Query pemakai harus memuat
     * tabel `ticket`.
     */
    public static function effectiveMandatoryQuery(): QueryBuilder
    {
        $own = DB::table('ticket_deliverable_requirements as r')
            ->join('deliverable_document_types as d', function ($join) {
                $join->on('d.name', '=', 'r.doc_type')->where('d.is_active', true);
            })
            ->where('r.is_mandatory', true)
            ->select('r.ticket_id', 'r.doc_type');

        $default = DB::table('ticket as t2')
            ->join('deliverable_document_type_ticket_types as l', function ($join) {
                $join->on('l.ticket_type', '=', 't2.ticket_type')->where('l.is_mandatory', true);
            })
            ->join('deliverable_document_types as d2', function ($join) {
                $join->on('d2.id', '=', 'l.document_type_id')->where('d2.is_active', true);
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('ticket_deliverable_requirements as x')
                    ->whereColumn('x.ticket_id', 't2.ticket_id');
            })
            ->select('t2.ticket_id', 'd2.name as doc_type');

        return $own->unionAll($default);
    }
}
