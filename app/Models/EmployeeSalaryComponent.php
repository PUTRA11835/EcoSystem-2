<?php

namespace App\Models;

use App\Models\Recruitment\Offer;
use App\Models\Recruitment\OfferComponent;
use App\Support\Payroll\SalaryComponentRules;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One active compensation line of an employee — the primary source for payroll, BPJS and contract
 * references. Created from an accepted offering letter, then adjusted by HR in Master Employee → Contract
 * (Salary Components box). `name` and `kind` are copied from the component (see OfferComponent), so a row
 * stays readable if the component is renamed afterwards.
 *
 * MODEL GABUNGAN (merge alnaf_hr + aldy_hr, 8 Okt 2026). Satu tabel, dua pemakai:
 *  - Kotak Salary Components (alnaf_hr): kolom component_id, name, kind, amount, effective_from, source, offer_id, notes, created_by.
 *  - Payroll (aldy_hr): menambah kolom effective_to, is_active, taxable, bpjs_base, updated_by — semuanya punya default
 *    aman, jadi baris yang dibuat kotak Salary Components langsung bisa dipakai payroll tanpa langkah tambahan.
 * Payroll membaca `kind` lewat padanan KIND_TO_CATEGORY (base→base, fixed→fixed_allowance, variable→variable_allowance,
 * deduction→deduction) supaya aturan murni SalaryComponentRules tidak berubah. Lihat toRuleRow().
 */
class EmployeeSalaryComponent extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Employee';

    protected $table = 'employee_salary_components';

    protected $fillable = [
        'employee_id', 'component_id', 'name', 'kind', 'amount', 'effective_from',
        'source', 'offer_id', 'notes', 'created_by',
        // payroll
        'effective_to', 'is_active', 'taxable', 'bpjs_base', 'updated_by',
    ];

    protected $casts = [
        'amount'         => 'float',
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'is_active'      => 'boolean',
        'taxable'        => 'boolean',
        'bpjs_base'      => 'boolean',
    ];

    /** Nominal gaji tidak ikut serialisasi JSON/array model secara tak sengaja (controller memakai present()). */
    protected $hidden = ['amount'];

    public const SOURCE_OFFER  = 'offer';
    public const SOURCE_MANUAL = 'manual';

    /** Jenis tambahan khusus payroll (tidak ada di katalog Offering): potongan tetap. */
    public const KIND_DEDUCTION = 'deduction';

    /** How each component kind is called on the Salary Components box. */
    public const KIND_LABELS = [
        OfferComponent::KIND_BASE     => 'Base Salary',
        OfferComponent::KIND_FIXED    => 'Fixed Allowance',
        OfferComponent::KIND_VARIABLE => 'Other Income',
    ];

    /** kind kotak Salary Components → kategori aturan payroll (SalaryComponentRules). */
    public const KIND_TO_CATEGORY = [
        OfferComponent::KIND_BASE     => SalaryComponentRules::BASE,
        OfferComponent::KIND_FIXED    => SalaryComponentRules::FIXED_ALLOWANCE,
        OfferComponent::KIND_VARIABLE => SalaryComponentRules::VARIABLE_ALLOWANCE,
        self::KIND_DEDUCTION          => SalaryComponentRules::DEDUCTION,
    ];

    public function component()
    {
        return $this->belongsTo(OfferComponent::class, 'component_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Baris datar untuk aturan murni payroll (SalaryComponentRules / PayrollCalculator).
     *
     * @return array<string,mixed>
     */
    public function toRuleRow(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'category'       => self::KIND_TO_CATEGORY[$this->kind] ?? SalaryComponentRules::VARIABLE_ALLOWANCE,
            'amount'         => (float) $this->amount,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to'   => $this->effective_to?->toDateString(),
            'is_mandatory'   => $this->kind === OfferComponent::KIND_BASE,
            'is_active'      => (bool) $this->is_active,
            'taxable'        => (bool) $this->taxable,
            // Dasar upah BPJS = gaji pokok + tunjangan TETAP. Tunjangan tidak tetap (Other Income / variable) tidak pernah
            // ikut, berapa pun nilai kolomnya — kotak Salary Components tidak punya pilihan ini dan kolom bawaannya true.
            'bpjs_base'      => (bool) $this->bpjs_base && in_array($this->kind, [OfferComponent::KIND_BASE, OfferComponent::KIND_FIXED], true),
        ];
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
