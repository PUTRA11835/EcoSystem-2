<?php

namespace App\Models\CashAdvance;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi global sub-modul Cash Advance & Cash Advance Report. Tabel satu baris.
 *
 * Selalu diambil lewat CashAdvanceSetting::current() supaya kode pemanggil tidak
 * perlu tahu bahwa tabelnya hanya berisi satu baris, dan supaya pembacaannya
 * tidak berulang dalam satu request. Meniru OvertimeSetting, ReimbursementSetting,
 * dan PurchaseRequestSetting.
 *
 * 🔴 SATU BARIS UNTUK DUA MODUL. Kolom berawalan `car_` mengatur pelaporan,
 * sisanya mengatur pengajuan. Halamannya pun satu (Keputusan C11), dan letaknya
 * di MANAGEMENT — bukan di HR & General (Keputusan D141), supaya haknya dapat
 * diberikan ke role mana pun tanpa ikut membuka halaman kepegawaian.
 *
 * 🔴 HANYA DUA penanda tangan: Accounting dan Cashier. Kolom ketiga
 * (`approver_signer_employee_id` seperti di ReimbursementSetting) SENGAJA tidak
 * ada — kolom "Approved by" pada cetakan diambil dari orang yang BENAR-BENAR
 * menyetujui pada langkah ber-actor_role = approver (Keputusan D129 & D137).
 * Menyimpan penanda tangan di dua tempat hanya melahirkan satu kelas kesalahan
 * baru: setelan berkata A, riwayat persetujuan berkata B.
 */
class CashAdvanceSetting extends Model
{
    protected $table = 'cash_advance_settings';

    protected $fillable = [
        'company_name', 'use_branch_name_in_header',
        'allow_future_date', 'max_backdate_days', 'allow_date_range',
        'min_amount', 'max_amount', 'over_limit_policy',
        'allowed_currencies', 'default_currency',
        'require_detail_url', 'detail_url_allowed_hosts',
        'require_description_min_chars',
        'require_cost_center', 'allowed_cost_center_types',
        'allow_self_approval', 'self_approval_fallback_role_id',
        'allow_approver_adjust_amount',
        'allow_requester_cancel',
        'locked_period_policy',
        'accounting_signer_employee_id', 'cashier_signer_employee_id',
        'require_car_before_new_ca', 'car_due_days',
        'car_allow_over_amount', 'car_require_receipt_url', 'car_multiple_per_ca',
        'updated_by',
    ];

    protected $casts = [
        'use_branch_name_in_header'     => 'boolean',
        'allow_future_date'             => 'boolean',
        'max_backdate_days'             => 'integer',
        'allow_date_range'              => 'boolean',
        'min_amount'                    => 'decimal:2',
        'max_amount'                    => 'decimal:2',
        'require_detail_url'            => 'boolean',
        'require_description_min_chars' => 'integer',
        'require_cost_center'           => 'boolean',
        'allow_self_approval'           => 'boolean',
        'allow_approver_adjust_amount'  => 'boolean',
        'allow_requester_cancel'        => 'boolean',
        'require_car_before_new_ca'     => 'boolean',
        'car_due_days'                  => 'integer',
        'car_allow_over_amount'         => 'boolean',
        'car_require_receipt_url'       => 'boolean',
        'car_multiple_per_ca'           => 'boolean',
    ];

    // ── Kebijakan batas nominal ─────────────────────────────────────────────

    /** Lewat batas tetap diterima, tetapi ditandai di kolom `flags`. */
    public const LIMIT_FLAG = 'flag';

    /** Lewat batas ditolak validasi. */
    public const LIMIT_BLOCK = 'block';

    public const LIMIT_POLICIES = [self::LIMIT_FLAG, self::LIMIT_BLOCK];

    // ── Kebijakan periode terkunci ──────────────────────────────────────────

    public const LOCK_OFF            = 'off';
    public const LOCK_BLOCK_EMPLOYEE = 'block_employee';
    public const LOCK_BLOCK_ALL      = 'block_all';

    public const LOCK_POLICIES = [
        self::LOCK_OFF,
        self::LOCK_BLOCK_EMPLOYEE,
        self::LOCK_BLOCK_ALL,
    ];

    /** Cache per-request; bukan Cache facade, supaya perubahan langsung terasa. */
    private static ?self $cached = null;

    /**
     * Baris konfigurasi yang berlaku.
     *
     * Membuatkan baris default bila belum ada. Ini pengaman: migrasi sudah
     * menyisipkannya, tetapi database yang dipulihkan dari dump lama bisa saja
     * tidak punya, dan seluruh modul akan mati bila mengembalikan null.
     */
    public static function current(): self
    {
        if (self::$cached) {
            return self::$cached;
        }

        return self::$cached = self::first() ?? self::create([
            'locked_period_policy'      => self::LOCK_BLOCK_EMPLOYEE,
            'over_limit_policy'         => self::LIMIT_FLAG,
            'allowed_currencies'        => 'IDR',
            'default_currency'          => 'IDR',
            'allowed_cost_center_types' => CashAdvance::COST_CENTER_BRANCH
                                           . ',' . CashAdvance::COST_CENTER_PROJECT,
        ]);
    }

    /** Dipanggil setelah menyimpan perubahan agar pembacaan berikutnya segar. */
    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function accountingSigner()
    {
        return $this->belongsTo(Employee::class, 'accounting_signer_employee_id', 'employee_id');
    }

    public function cashierSigner()
    {
        return $this->belongsTo(Employee::class, 'cashier_signer_employee_id', 'employee_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Nilai 0 berarti "tanpa batas", bukan "nol". Konsisten dengan Overtime. */
    public function hasBackdateLimit(): bool
    {
        return $this->max_backdate_days > 0;
    }

    public function hasMinAmount(): bool
    {
        return (float) $this->min_amount > 0;
    }

    public function hasMaxAmount(): bool
    {
        return (float) $this->max_amount > 0;
    }

    public function blocksOverLimit(): bool
    {
        return $this->over_limit_policy === self::LIMIT_BLOCK;
    }

    /** Tenggat pelaporan CAR aktif? 0 = tanpa tenggat (Keputusan C10). */
    public function hasCarDueLimit(): bool
    {
        return $this->car_due_days > 0;
    }

    /**
     * Mata uang yang boleh dipakai (Keputusan C5).
     *
     * Hari ini isinya TEPAT SATU nilai: IDR. Wadah CSV-nya tetap dipakai supaya
     * menambah mata uang kelak tidak menuntut migrasi — tetapi konversi kurs
     * TIDAK dibangun, dan CAR menuntut mata uangnya sama dengan CA induknya.
     *
     * @return array<string>
     */
    public function currencyOptions(): array
    {
        $list = collect(explode(',', (string) $this->allowed_currencies))
            ->map(fn ($code) => strtoupper(trim($code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Jaring pengaman: daftar kosong membuat setiap pengajuan ditolak tanpa
        // cara memperbaikinya dari layar mana pun (pelajaran D52).
        return $list !== [] ? $list : ['IDR'];
    }

    /** Mata uang bawaan; selalu salah satu dari currencyOptions(). */
    public function defaultCurrency(): string
    {
        $default = strtoupper(trim((string) $this->default_currency));
        $options = $this->currencyOptions();

        return in_array($default, $options, true) ? $default : $options[0];
    }

    /**
     * Jenis pembebanan yang boleh dipilih (Keputusan C4).
     *
     * Katup pengaman: bila kelak hanya cabang yang dipakai, buang `project` dari
     * CSV ini dan dropdown proyek hilang — nol perubahan kode.
     *
     * @return array<string>
     */
    public function costCenterTypeOptions(): array
    {
        return collect(explode(',', (string) $this->allowed_cost_center_types))
            ->map(fn ($type) => strtolower(trim($type)))
            ->filter(fn ($type) => in_array($type, CashAdvance::COST_CENTER_TYPES, true))
            ->unique()
            ->values()
            ->all();
    }

    public function allowsCostCenterType(string $type): bool
    {
        return in_array($type, $this->costCenterTypeOptions(), true);
    }

    /**
     * Host yang boleh dipakai pada Detail URL / bukti realisasi.
     *
     * @return array<string>
     */
    public function allowedUrlHosts(): array
    {
        return collect(explode(',', (string) $this->detail_url_allowed_hosts))
            ->map(fn ($host) => strtolower(trim($host)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
