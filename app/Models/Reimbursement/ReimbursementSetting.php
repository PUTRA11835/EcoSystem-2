<?php

namespace App\Models\Reimbursement;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi global sub-modul Reimbursement. Tabel satu baris.
 *
 * Selalu diambil lewat ReimbursementSetting::current() supaya kode pemanggil
 * tidak perlu tahu bahwa tabelnya hanya berisi satu baris, dan supaya
 * pembacaannya tidak berulang dalam satu request. Meniru OvertimeSetting.
 */
class ReimbursementSetting extends Model
{
    protected $table = 'reimbursement_settings';

    protected $fillable = [
        'company_name', 'use_branch_name_in_header',
        'allow_future_date', 'max_backdate_days',
        'max_items_per_request', 'min_item_amount',
        'max_request_amount', 'over_limit_policy',
        'require_title_min_chars', 'require_supporting_url',
        'supporting_url_allowed_hosts', 'require_receipt_no',
        'allow_self_approval', 'self_approval_fallback_role_id',
        'allow_approver_adjust_amount', 'locked_period_policy',
        'accounting_signer_employee_id', 'cashier_signer_employee_id',
        'approver_signer_employee_id',
        'updated_by',
    ];

    protected $casts = [
        'use_branch_name_in_header'      => 'boolean',
        'allow_future_date'              => 'boolean',
        'max_backdate_days'              => 'integer',
        'max_items_per_request'          => 'integer',
        'min_item_amount'                => 'decimal:2',
        'max_request_amount'             => 'decimal:2',
        'require_title_min_chars'        => 'integer',
        'require_supporting_url'         => 'boolean',
        'require_receipt_no'             => 'boolean',
        'allow_self_approval'            => 'boolean',
        'allow_approver_adjust_amount'   => 'boolean',
    ];

    // ── Kebijakan periode terkunci ──────────────────────────────────────────

    /** Penguncian periode diabaikan sepenuhnya. */
    public const LOCK_OFF = 'off';

    /** Karyawan tidak bisa; pemegang `general.reimbursement.manage` tetap bisa. */
    public const LOCK_BLOCK_EMPLOYEE = 'block_employee';

    /** Siapa pun tidak bisa. */
    public const LOCK_BLOCK_ALL = 'block_all';

    public const LOCK_POLICIES = [
        self::LOCK_OFF,
        self::LOCK_BLOCK_EMPLOYEE,
        self::LOCK_BLOCK_ALL,
    ];

    // ── Kebijakan batas nominal (Keputusan D107) ────────────────────────────
    //
    // Dua mode yang tidak tumpang tindih. Dibuat pilihan, bukan boolean, karena
    // pemilik sistem menolak perilaku setengah-setengah: "tandai gitu, reject ya
    // reject".

    /** Diterima, diberi flag, barisnya disorot di rekap. */
    public const OVER_LIMIT_FLAG = 'flag';

    /** Ditolak saat submit. */
    public const OVER_LIMIT_BLOCK = 'block';

    public const OVER_LIMIT_POLICIES = [
        self::OVER_LIMIT_FLAG,
        self::OVER_LIMIT_BLOCK,
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
            'locked_period_policy' => self::LOCK_BLOCK_EMPLOYEE,
            'over_limit_policy'    => self::OVER_LIMIT_FLAG,
        ]);
    }

    /** Dipanggil setelah menyimpan perubahan agar pembacaan berikutnya segar. */
    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    // ── Relationships ───────────────────────────────────────────────────────
    //
    // Penanda tangan disimpan sebagai employee_id, BUKAN teks nama (Keputusan
    // D108): saat gambar tanda tangan hadir di profil karyawan nanti, cetakan
    // tinggal merender gambarnya tanpa migrasi apa pun di modul ini.

    public function accountingSigner()
    {
        return $this->belongsTo(Employee::class, 'accounting_signer_employee_id', 'employee_id');
    }

    public function cashierSigner()
    {
        return $this->belongsTo(Employee::class, 'cashier_signer_employee_id', 'employee_id');
    }

    public function approverSigner()
    {
        return $this->belongsTo(Employee::class, 'approver_signer_employee_id', 'employee_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Nilai 0 berarti "tanpa batas", bukan "nol". Konsisten dengan Overtime. */
    public function hasBackdateLimit(): bool
    {
        return $this->max_backdate_days > 0;
    }

    public function hasItemLimit(): bool
    {
        return $this->max_items_per_request > 0;
    }

    public function hasAmountLimit(): bool
    {
        return (float) $this->max_request_amount > 0;
    }

    public function hasMinimumItemAmount(): bool
    {
        return (float) $this->min_item_amount > 0;
    }

    /** Pengajuan di atas batas ditolak, bukan sekadar ditandai? */
    public function blocksOverLimit(): bool
    {
        return $this->over_limit_policy === self::OVER_LIMIT_BLOCK;
    }

    /**
     * Host yang boleh dipakai pada tautan bukti.
     *
     * @return array<string>
     */
    public function allowedSupportingHosts(): array
    {
        return collect(explode(',', (string) $this->supporting_url_allowed_hosts))
            ->map(fn ($host) => strtolower(trim($host)))
            ->filter()
            ->values()
            ->all();
    }
}
