<?php

namespace App\Models\PurchaseOrder;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/** Konfigurasi global Purchase Order. Tabel satu baris. */
class PurchaseOrderSetting extends Model
{
    protected $table = 'purchase_order_settings';

    protected $fillable = [
        'company_name', 'requires_pr',
        'vat_rate', 'pph23_rate', 'pph4_2_rate', 'pp23_rate', 'pph21_rate', 'pph26_rate',
        'accounting_employee_id', 'cashier_employee_id', 'updated_by',
    ];

    protected $casts = [
        'requires_pr' => 'boolean',
        'vat_rate'    => 'float',
        'pph23_rate'  => 'float',
        'pph4_2_rate' => 'float',
        'pp23_rate'   => 'float',
        'pph21_rate'  => 'float',
        'pph26_rate'  => 'float',
    ];

    /**
     * Kunci pajak => [label, kolom tarif, jenis]. `vat` MENAMBAH nilai;
     * seluruh PPh dipotong dari pembayaran.
     */
    public const TAXES = [
        'vat'    => ['VAT',          'vat_rate',    'add'],
        'pph23'  => ['PPh 23',       'pph23_rate',  'withhold'],
        'pph4_2' => ['PPh 4 Ayat 2', 'pph4_2_rate', 'withhold'],
        'pp23'   => ['PP 23',        'pp23_rate',   'withhold'],
        'pph21'  => ['PPh 21',       'pph21_rate',  'withhold'],
        'pph26'  => ['PPh 26',       'pph26_rate',  'withhold'],
    ];

    private static ?self $cached = null;

    public static function current(): self
    {
        return self::$cached ??= (self::first() ?? self::create([]));
    }

    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    public function rate(string $taxKey): float
    {
        return (float) $this->{self::TAXES[$taxKey][1]};
    }

    /** @return array<string, array{label: string, rate: float, kind: string}> */
    public function taxTable(): array
    {
        $table = [];

        foreach (self::TAXES as $key => [$label, , $kind]) {
            $table[$key] = ['label' => $label, 'rate' => $this->rate($key), 'kind' => $kind];
        }

        return $table;
    }

    public function accountingSigner()
    {
        return $this->belongsTo(Employee::class, 'accounting_employee_id', 'employee_id');
    }

    public function cashierSigner()
    {
        return $this->belongsTo(Employee::class, 'cashier_employee_id', 'employee_id');
    }
}
