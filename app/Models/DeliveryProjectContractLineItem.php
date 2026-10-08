<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Contract Line Item — pekerjaan dalam kontrak (mis. Service, License, ATS) yang
 * menjadi acuan termin pada mode TOP "line_item".
 *
 * Type:
 *   - one_time  : ditagih sekali.
 *   - recurring : ditagih berkala (nominal per periode × jumlah periode).
 *   - milestone : satu nilai kontrak yang ditagih dalam beberapa termin
 *                 (mis. 30% / 40% / 30% per milestone).
 */
class DeliveryProjectContractLineItem extends Model
{
    use HasFactory, Auditable, Concerns\TouchesProjectActivity, Concerns\HasContractLineItemSchedule;

    protected static ?string $auditModule = 'Delivery Project';

    protected $table = 'delivery_project_contract_line_items';

    /** Panjang satu periode (bulan) per frekuensi recurring. */
    public const FREQUENCY_MONTHS = [
        'monthly'    => 1,
        'quarterly'  => 3,
        'semiannual' => 6,
        'yearly'     => 12,
    ];

    public const TYPES = ['one_time', 'recurring', 'milestone'];

    public const FREQUENCY_LABELS = [
        'monthly'    => 'Monthly',
        'quarterly'  => 'Quarterly',
        'semiannual' => 'Semi-annual',
        'yearly'     => 'Yearly',
    ];

    /** Batas jumlah periode agar input tanggal yang salah tidak membuat ribuan termin. */
    public const MAX_PERIODS = 120;

    protected $fillable = [
        'delivery_projects_id',
        'name',
        'type',
        'frequency',
        'start_date',
        'end_date',
        'amount',
        'order_sequence',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'amount'         => 'decimal:2',
        'order_sequence' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    public function paymentTerms()
    {
        return $this->hasMany(DeliveryProjectPaymentTerm::class, 'contract_line_item_id');
    }

    /** @return array<int,array{label:string,start:Carbon}> */
    public static function buildPeriods(?string $frequency, $start, $end): array
    {
        $months = self::FREQUENCY_MONTHS[$frequency] ?? null;
        if (!$months || !$start || !$end) {
            return [];
        }

        $cursor  = Carbon::parse($start)->startOfMonth();
        $last    = Carbon::parse($end);
        $periods = [];

        while ($cursor->lte($last) && count($periods) < self::MAX_PERIODS) {
            $periodEnd = $cursor->copy()->addMonths($months - 1);
            $periods[] = [
                'label' => $months === 1
                    ? $cursor->format('M Y')
                    : $cursor->format('M Y') . ' – ' . $periodEnd->format('M Y'),
                'start' => $cursor->copy(),
            ];
            $cursor->addMonths($months);
        }

        return $periods;
    }

    // isRecurring() / isMilestone() / periods() / occurrences() / total() /
    // scheduleLabel() / typeLabel() → Concerns\HasContractLineItemSchedule
    // (dipakai juga oleh DeliverySupportContractLineItem).
}
