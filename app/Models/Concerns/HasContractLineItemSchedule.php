<?php

namespace App\Models\Concerns;

use App\Models\DeliveryProjectContractLineItem;

/**
 * Jadwal & nilai kontrak sebuah Contract Line Item — dipakai bersama oleh
 * Delivery Project (DeliveryProjectContractLineItem) dan Delivery Support
 * (DeliverySupportContractLineItem). Konstanta frekuensi & buildPeriods()
 * tetap satu sumber di DeliveryProjectContractLineItem.
 *
 * Model pemakai wajib punya kolom: type, frequency, start_date, end_date, amount.
 */
trait HasContractLineItemSchedule
{
    public function isRecurring(): bool
    {
        return $this->type === 'recurring';
    }

    public function isMilestone(): bool
    {
        return $this->type === 'milestone';
    }

    /**
     * Periode tagihan untuk item recurring: [['label' => 'Jan 2027', 'start' => Carbon], ...].
     * Item one-time → satu periode (label bulan mulai bila ada).
     *
     * @return array<int,array{label:?string,start:?\Illuminate\Support\Carbon}>
     */
    public function periods(): array
    {
        if (!$this->isRecurring()) {
            return [[
                'label' => $this->start_date?->format('M Y'),
                'start' => $this->start_date?->copy(),
            ]];
        }

        return DeliveryProjectContractLineItem::buildPeriods($this->frequency, $this->start_date, $this->end_date);
    }

    public function occurrences(): int
    {
        return $this->isRecurring() ? count($this->periods()) : 1;
    }

    /** Nilai kontrak line item = nominal per periode × jumlah periode. */
    public function total(): float
    {
        return round((float) $this->amount * $this->occurrences(), 2);
    }

    /** Teks kolom "Schedule", mis. "Once" atau "Jan 2027 – Dec 2027 (12x)". */
    public function scheduleLabel(): string
    {
        if ($this->isMilestone()) {
            return 'Billed per milestone';
        }

        if (!$this->isRecurring()) {
            return $this->start_date ? 'Once · ' . $this->start_date->format('M Y') : 'Once';
        }

        $n = $this->occurrences();
        return $this->start_date->format('M Y') . ' – ' . $this->end_date->format('M Y') . " ({$n}x)";
    }

    public function typeLabel(): string
    {
        if ($this->isMilestone()) {
            return 'Milestone / Term-based';
        }

        return $this->isRecurring()
            ? 'Recurring ' . strtolower(DeliveryProjectContractLineItem::FREQUENCY_LABELS[$this->frequency] ?? '')
            : 'One-time';
    }
}
