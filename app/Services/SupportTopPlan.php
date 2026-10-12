<?php

namespace App\Services;

use App\Models\DeliveryProjectPaymentTerm;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportContractLineItem;
use App\Models\DeliverySupportPaymentTerm;
use Illuminate\Support\Collection;

/**
 * Logika bersama Term Of Payment Plan Delivery Support — mirror ProjectTopPlan:
 *
 *   percentage → semua termin = % × revenue Sales Data. Total % ≤ 100 (diblokir).
 *   line_item  → termin dikaitkan ke Contract Line Item. Amount diambil dari
 *                nilai kontrak line item: basis "line_item" (% × nilai line item)
 *                atau "fixed" (nominal diisi user). Basis % dari revenue dikunci
 *                untuk termin baru. Melebihi revenue hanya menghasilkan peringatan.
 *
 * Tanpa activity tracking / reminder — keduanya hanya berlaku untuk Delivery Project.
 */
class SupportTopPlan
{
    public const MODES = ProjectTopPlan::MODES;

    public function __construct(private DeliverySupport $support)
    {
    }

    public function revenue(): float
    {
        return (float) ($this->support->revenue ?? 0);
    }

    public function mode(): string
    {
        return $this->support->top_mode === 'line_item' ? 'line_item' : 'percentage';
    }

    /** @return Collection<int,DeliverySupportContractLineItem> */
    public function lineItems(): Collection
    {
        return DeliverySupportContractLineItem::where('delivery_support_id', $this->support->id)
            ->orderBy('order_sequence')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int,DeliverySupportPaymentTerm> */
    public function terms(): Collection
    {
        return DeliverySupportPaymentTerm::where('delivery_support_id', $this->support->id)
            ->with('contractLineItem')
            ->orderBy('term_number')
            ->get();
    }

    /**
     * Samakan amount tersimpan termin basis "line_item" dengan % × nilai kontrak
     * line item terkini (nilai line item bisa berubah setelah termin dibuat).
     * Efek samping, bukan perubahan oleh user → timestamps tidak disentuh.
     */
    public function resyncLineItemAmounts(): void
    {
        $this->terms()
            ->filter(fn(DeliverySupportPaymentTerm $t) => $t->isLineItemShare() && $t->contractLineItem)
            ->each(function (DeliverySupportPaymentTerm $t) {
                $amount = DeliveryProjectPaymentTerm::lineItemAmount($t->payment_percentage, $t->contractLineItem->total());
                if (abs((float) $t->amount - $amount) > 0.001) {
                    $t->timestamps = false;
                    $t->update(['amount' => $amount]);
                    $t->timestamps = true;
                }
            });
    }

    /**
     * Nomor termin ("No") mengikuti urutan tampil: pada mode line_item termin
     * dikelompokkan per line item (urutan line item), lalu urutan dibuatnya.
     * Termin tanpa line item ditaruh paling akhir.
     */
    public function resequence(): void
    {
        $terms = $this->terms();

        if ($this->mode() === 'line_item') {
            $position = $this->lineItems()->pluck('id')->flip();
            $terms = $terms->sortBy([
                fn($a, $b) => ($position[$a->contract_line_item_id] ?? PHP_INT_MAX) <=> ($position[$b->contract_line_item_id] ?? PHP_INT_MAX),
                fn($a, $b) => $a->term_number <=> $b->term_number,
            ])->values();
        }

        $terms->each(function (DeliverySupportPaymentTerm $t, int $i) {
            if ($t->term_number !== $i + 1) {
                $t->timestamps = false;
                $t->update(['term_number' => $i + 1]);
                $t->timestamps = true;
            }
        });
    }

    /**
     * Peringatan non-blocking — ditampilkan sebagai banner & notifikasi saat simpan.
     *
     * @return array<int,string>
     */
    public function warnings(): array
    {
        $terms    = $this->terms();
        $revenue  = $this->revenue();
        $warnings = [];

        $hasFixed = $terms->contains(fn($t) => !$t->isRevenueShare());
        $total    = $terms->sum(fn($t) => $t->effectiveAmount($revenue));

        if ($hasFixed && $revenue <= 0) {
            $warnings[] = 'Revenue in Sales Data is still empty, so line item terms cannot be checked against it.';
        } elseif ($total > $revenue + 0.01) {
            $warnings[] = 'Total payment terms (' . ProjectTopPlan::rp($total) . ') exceed the revenue recorded in Sales Data ('
                . ProjectTopPlan::rp($revenue) . ') by ' . ProjectTopPlan::rp($total - $revenue) . '.';
        }

        if ($this->mode() === 'line_item') {
            $billed = $terms->groupBy('contract_line_item_id');
            foreach ($this->lineItems() as $item) {
                $sum = ($billed[$item->id] ?? collect())->sum(fn($t) => $t->effectiveAmount($revenue));
                if ($sum > $item->total() + 0.01) {
                    $warnings[] = "Payment terms for line item \"{$item->name}\" (" . ProjectTopPlan::rp($sum)
                        . ') exceed its contract value (' . ProjectTopPlan::rp($item->total()) . ').';
                }
            }
        }

        return $warnings;
    }

    /** Payload lengkap untuk GET /delivery/support/{support}/payment-terms. */
    public function payload(): array
    {
        $revenue   = $this->revenue();
        $terms     = $this->terms();
        $billed    = $terms->groupBy('contract_line_item_id');
        $lineItems = $this->lineItems()->map(function (DeliverySupportContractLineItem $item) use ($billed, $revenue) {
            $own = $billed[$item->id] ?? collect();
            return self::formatLineItem($item) + [
                'terms_count'  => $own->count(),
                'billed_total' => (float) $own->sum(fn($t) => $t->effectiveAmount($revenue)),
            ];
        });

        return [
            'top_mode'        => $this->mode(),
            'support_revenue' => $revenue,
            'line_items'      => $lineItems->values(),
            'payment_terms'   => $terms->map(fn($t) => self::formatTerm($t, $revenue))->values(),
            'warnings'        => $this->warnings(),
        ];
    }

    public static function formatTerm(DeliverySupportPaymentTerm $term, float $revenue): array
    {
        $fixed  = $term->isFixed();
        $amount = $term->effectiveAmount($revenue);

        // Porsi termin terhadap nilai kontrak line item-nya (info untuk nominal tetap).
        $liTotal = $term->contractLineItem?->total() ?? 0;
        $share   = $term->isLineItemShare()
            ? (float) $term->payment_percentage
            : ($fixed && $liTotal > 0 ? round($amount / $liTotal * 100, 2) : null);

        return [
            'id'                        => $term->id,
            'term_number'               => $term->term_number,
            'basis'                     => $fixed ? 'fixed' : ($term->isLineItemShare() ? 'line_item' : 'percentage'),
            'contract_line_item_id'     => $term->contract_line_item_id,
            'payment_term'              => $term->payment_term,
            'period'                    => $term->period,
            // Termin nominal tetap tidak punya persentase (lihat line_item_share).
            'payment_percentage'        => $fixed ? null : (float) $term->payment_percentage,
            'line_item_share'           => $share,
            'amount'                    => $amount,
            'requirements'              => $term->requirements,
            'estimated_date'            => $term->estimated_date?->format('Y-m-d'),
            'estimated_date_label'      => $term->estimated_date?->format('d M Y'),
            'submit_invoice_date'       => $term->submit_invoice_date?->format('Y-m-d'),
            'submit_invoice_date_label' => $term->submit_invoice_date?->format('d M Y'),
            'invoice_number'            => $term->invoice_number,
            'paid_date'                 => $term->paid_date?->format('Y-m-d'),
            'paid_date_label'           => $term->paid_date?->format('d M Y'),
            'status'                    => $term->status,
        ];
    }

    public static function formatLineItem(DeliverySupportContractLineItem $item): array
    {
        return [
            'id'             => $item->id,
            'name'           => $item->name,
            'type'           => $item->type,
            'type_label'     => $item->typeLabel(),
            'frequency'      => $item->frequency,
            'start_date'     => $item->start_date?->format('Y-m-d'),
            'end_date'       => $item->end_date?->format('Y-m-d'),
            'amount'         => (float) $item->amount,
            'occurrences'    => $item->occurrences(),
            'total'          => $item->total(),
            'schedule_label' => $item->scheduleLabel(),
        ];
    }
}
