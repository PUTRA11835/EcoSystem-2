<?php

namespace App\Models;

use App\Models\Recruitment\Offer;
use App\Models\Recruitment\OfferComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One active compensation line of an employee — the primary source for payroll, BPJS and contract
 * references. Created from an accepted offering letter, then adjusted by HR in Master Employee → Contract.
 * `name` and `kind` are copied from the component (see OfferComponent), so a row stays readable if the
 * component is renamed afterwards.
 */
class EmployeeSalaryComponent extends Model
{
    protected $table = 'employee_salary_components';

    protected $fillable = [
        'employee_id', 'component_id', 'name', 'kind', 'amount', 'effective_from',
        'source', 'offer_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'         => 'float',
        'effective_from' => 'date',
    ];

    public const SOURCE_OFFER  = 'offer';
    public const SOURCE_MANUAL = 'manual';

    /** How each component kind is called on the Salary Components box. */
    public const KIND_LABELS = [
        OfferComponent::KIND_BASE     => 'Base Salary',
        OfferComponent::KIND_FIXED    => 'Fixed Allowance',
        OfferComponent::KIND_VARIABLE => 'Other Income',
    ];

    public function component()
    {
        return $this->belongsTo(OfferComponent::class, 'component_id');
    }

    /** The four figures of the summary cards: base, fixed allowances, other income, total. */
    public static function summaryOf(Collection $rows): array
    {
        $sum = fn (string $kind) => (float) $rows->where('kind', $kind)->sum('amount');

        return [
            'base'     => $sum(OfferComponent::KIND_BASE),
            'fixed'    => $sum(OfferComponent::KIND_FIXED),
            'variable' => $sum(OfferComponent::KIND_VARIABLE),
            'total'    => (float) $rows->sum('amount'),
        ];
    }

    /**
     * Copies the compensation lines of an accepted offering letter onto the new employee, starting on the
     * join date. Does nothing when the employee already holds lines from that letter, so it is safe to repeat.
     */
    public static function seedFromOffer(Offer $offer, int $employeeId, ?string $joinDate): int
    {
        if (static::where('employee_id', $employeeId)->where('offer_id', $offer->id)->exists()) {
            return 0;
        }

        $from = $joinDate ?: now()->toDateString();
        $created = 0;

        foreach ($offer->lines() as $line) {
            $component = OfferComponent::find($line['component_id'] ?? 0);

            static::create([
                'employee_id'    => $employeeId,
                'component_id'   => $component?->id,
                'name'           => $line['name'],
                'kind'           => $line['kind'],
                'amount'         => (float) $line['amount'],
                'effective_from' => $from,
                'source'         => self::SOURCE_OFFER,
                'offer_id'       => $offer->id,
                'created_by'     => session('user.eci', 'Recruitment'),
            ]);
            $created++;
        }

        return $created;
    }
}
