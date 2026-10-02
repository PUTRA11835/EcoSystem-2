<?php

namespace App\Services;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectContractLineItem;
use App\Models\DeliveryProjectPaymentTerm;
use Illuminate\Support\Collection;

/**
 * Logika bersama Term Of Payment Plan (Delivery Project) untuk dua mode penagihan:
 *
 *   percentage → semua termin = % × revenue Sales Data. Total % ≤ 100 (diblokir).
 *   line_item  → termin dikaitkan ke Contract Line Item; boleh bernominal tetap
 *                (diisi user, TIDAK diturunkan dari revenue). Melebihi revenue
 *                hanya menghasilkan peringatan, karena kontrak bisa memuat
 *                pekerjaan yang ditagih di luar TOP (mis. license).
 */
class ProjectTopPlan
{
    public const MODES = ['percentage', 'line_item'];

    public function __construct(private DeliveryProject $project)
    {
    }

    public function revenue(): float
    {
        return (float) ($this->project->revenue ?? 0);
    }

    public function mode(): string
    {
        return $this->project->top_mode === 'line_item' ? 'line_item' : 'percentage';
    }

    /** @return Collection<int,DeliveryProjectContractLineItem> */
    public function lineItems(): Collection
    {
        return DeliveryProjectContractLineItem::where('delivery_projects_id', $this->project->id)
            ->orderBy('order_sequence')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int,DeliveryProjectPaymentTerm> */
    public function terms(): Collection
    {
        return DeliveryProjectPaymentTerm::where('delivery_projects_id', $this->project->id)
            ->orderBy('term_number')
            ->get();
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

        $terms->each(function (DeliveryProjectPaymentTerm $t, int $i) {
            if ($t->term_number !== $i + 1) {
                // Penomoran ulang adalah efek samping, bukan perubahan oleh user.
                $t->timestamps = false;
                DeliveryProject::withoutActivityTracking(fn () => $t->update(['term_number' => $i + 1]));
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

        $hasFixed = $terms->contains(fn($t) => $t->isFixed());
        $total    = $terms->sum(fn($t) => $t->effectiveAmount($revenue));

        if ($hasFixed && $revenue <= 0) {
            $warnings[] = 'Revenue in Sales Data is still empty, so fixed-amount terms cannot be checked against it.';
        } elseif ($total > $revenue + 0.01) {
            $warnings[] = 'Total payment terms (' . self::rp($total) . ') exceed the revenue recorded in Sales Data ('
                . self::rp($revenue) . ') by ' . self::rp($total - $revenue) . '.';
        }

        if ($this->mode() === 'line_item') {
            $billed = $terms->groupBy('contract_line_item_id');
            foreach ($this->lineItems() as $item) {
                $sum = ($billed[$item->id] ?? collect())->sum(fn($t) => $t->effectiveAmount($revenue));
                if ($sum > $item->total() + 0.01) {
                    $warnings[] = "Payment terms for line item \"{$item->name}\" (" . self::rp($sum)
                        . ') exceed its contract value (' . self::rp($item->total()) . ').';
                }
            }
        }

        return $warnings;
    }

    /** Payload lengkap untuk GET /projects/{project}/payment-terms. */
    public function payload(): array
    {
        $revenue   = $this->revenue();
        $terms     = $this->terms();
        $billed    = $terms->groupBy('contract_line_item_id');
        $lineItems = $this->lineItems()->map(function (DeliveryProjectContractLineItem $item) use ($billed, $revenue) {
            $own = $billed[$item->id] ?? collect();
            return self::formatLineItem($item) + [
                'terms_count'  => $own->count(),
                'billed_total' => (float) $own->sum(fn($t) => $t->effectiveAmount($revenue)),
            ];
        });

        return [
            'top_mode'        => $this->mode(),
            'project_revenue' => $revenue,
            'line_items'      => $lineItems->values(),
            'payment_terms'   => $terms->map(fn($t) => self::formatTerm($t, $revenue))->values(),
            'warnings'        => $this->warnings(),
        ];
    }

    public static function formatTerm(DeliveryProjectPaymentTerm $term, float $revenue): array
    {
        $fixed = $term->isFixed();

        return [
            'id'                        => $term->id,
            'term_number'               => $term->term_number,
            'basis'                     => $fixed ? 'fixed' : 'percentage',
            'contract_line_item_id'     => $term->contract_line_item_id,
            'payment_term'              => $term->payment_term,
            'period'                    => $term->period,
            // Termin nominal tetap tidak punya persentase.
            'payment_percentage'        => $fixed ? null : (float) $term->payment_percentage,
            'amount'                    => $term->effectiveAmount($revenue),
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

    public static function formatLineItem(DeliveryProjectContractLineItem $item): array
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

    public static function rp(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}
