<?php

namespace App\Models\PurchaseRequest;

use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Cetakan satu langkah persetujuan Purchase Request — KONFIGURASI, bukan riwayat.
 *
 * Riwayat tiap dokumen ada di PurchaseRequestApproval, disalin dari sini saat
 * dokumen dibuat. Lihat docblock migrasinya untuk alasan pemisahan itu, dan
 * mengapa tabelnya dibuat sendiri alih-alih menumpang tabel modul lain (D102).
 */
class PurchaseRequestApprovalStep extends Model
{
    protected $table = 'purchase_request_approval_steps';

    protected $fillable = [
        'module', 'order_seq', 'name',
        'approver_type', 'approver_role_id', 'approver_employee_ids',
        'requester_selectable', 'is_active',
    ];

    protected $casts = [
        'order_seq'             => 'integer',
        'approver_role_id'      => 'integer',
        'approver_employee_ids' => 'array',
        'requester_selectable'  => 'boolean',
        'is_active'             => 'boolean',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const MODULE_PURCHASE_REQUEST = 'purchase_request';

    /** Seluruh pemegang role boleh bertindak; cukup satu di antaranya. */
    public const TYPE_ROLE = 'role';

    /** Orang-orang tertentu yang disebut namanya. */
    public const TYPE_EMPLOYEE = 'employee';

    /**
     * Atasan langsung pemohon.
     *
     * 🔴 BELUM DAPAT DIJALANKAN. Tabel `employee` tidak punya `reports_to_id`,
     * dan `employee_basic_data.direct_supervision` / `.manager` 100% NULL untuk
     * seluruh karyawan (pekerjaan tertunda T.2). Nilainya didaftarkan sejak awal
     * supaya pengaktifannya nanti tidak memerlukan migrasi — pilihannya
     * dinonaktifkan di UI.
     */
    public const TYPE_DIRECT_MANAGER = 'direct_manager';

    public const TYPES = [
        self::TYPE_ROLE,
        self::TYPE_EMPLOYEE,
        self::TYPE_DIRECT_MANAGER,
    ];

    /** Tipe yang benar-benar dapat dipilih hari ini. */
    public const SELECTABLE_TYPES = [
        self::TYPE_ROLE,
        self::TYPE_EMPLOYEE,
    ];

    public const TYPE_LABELS = [
        self::TYPE_ROLE           => 'By Role',
        self::TYPE_EMPLOYEE       => 'Specific Employees',
        self::TYPE_DIRECT_MANAGER => 'Direct Manager',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'approver_role_id', 'id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForPurchaseRequest($query)
    {
        return $query->where('module', self::MODULE_PURCHASE_REQUEST);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isTypeSelectable(): bool
    {
        return in_array($this->approver_type, self::SELECTABLE_TYPES, true);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->approver_type] ?? $this->approver_type;
    }

    /**
     * Ringkasan penyetuju langkah ini untuk ditampilkan di layar.
     *
     * 🔴 Kembarannya ada di PurchaseRequestApproval — dan keduanya memang harus
     * ada, bukan salah satu. Yang di sana menjawab "dokumen INI menunggu siapa?"
     * dan membaca SALINAN yang sudah dibekukan; yang di sini menjawab "menurut
     * konfigurasi hari ini, langkah ini dipegang siapa?".
     *
     * Ketiadaannya adalah cacat yang lolos sampai ke layar pengguna: halaman
     * Submit memanggil method ini pada objek langkah, dan Laravel meneruskannya
     * ke Model::__call() yang melempar BadMethodCallException — 500, bukan
     * sekadar teks kosong. Lolos karena `submit.blade.php` memakai layout
     * `dashboard`, sehingga uji asap P4 hanya sanggup memeriksa KOMPILASI Blade,
     * bukan merendernya. Sejak P8 seluruh view dirender sungguhan.
     */
    public function approverLabel(): string
    {
        return match ($this->approver_type) {
            self::TYPE_ROLE
                => $this->role?->name ?? ($this->approver_role_id
                    ? 'Role #' . $this->approver_role_id
                    : 'No role chosen yet'),

            self::TYPE_EMPLOYEE => match (count($this->approver_employee_ids ?? [])) {
                0       => 'No employee chosen yet',
                1       => Employee::with('basicData')
                            ->find($this->approver_employee_ids[0])?->basicData?->nick_name
                            ?? 'Employee #' . $this->approver_employee_ids[0],
                default => count($this->approver_employee_ids) . ' selected employee(s)',
            },

            self::TYPE_DIRECT_MANAGER => 'Direct manager (not available yet)',

            default => $this->approver_type,
        };
    }

    /**
     * Karyawan yang boleh dipilih pemohon pada langkah ini (Keputusan D126).
     *
     * Dipakai DUA kali dengan tujuan berbeda, dan itulah alasannya berada di
     * model dan bukan di controller:
     *
     *   1. Merender dropdown "Approver" di form pengajuan
     *   2. MEMVALIDASI pilihan yang dikirim balik — pilihan di luar daftar ini
     *      ditolak, kalau tidak siapa pun dapat menunjuk penyetuju sesukanya
     *      dengan menyunting HTML-nya
     *
     * Untuk tipe `role`, kandidatnya seluruh pemegang role tersebut. Untuk tipe
     * `employee`, isi `approver_employee_ids`. Untuk `direct_manager`, kosong —
     * hierarkinya belum ada.
     *
     * @return array<int>
     */
    public function candidateEmployeeIds(): array
    {
        return match ($this->approver_type) {
            self::TYPE_EMPLOYEE => array_values(array_unique(array_map(
                'intval',
                $this->approver_employee_ids ?? []
            ))),

            self::TYPE_ROLE => $this->approver_role_id === null
                ? []
                : Employee::withRole((int) $this->approver_role_id)
                    ->pluck('employee_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),

            default => [],
        };
    }

    /**
     * Apakah langkah ini benar-benar dapat dipilih pemohon SEKARANG?
     *
     * `requester_selectable` saja tidak cukup: langkah bertipe `direct_manager`
     * tidak punya kandidat sama sekali, dan langkah bertipe `role` yang role-nya
     * tidak dipegang siapa pun juga tidak. Dua-duanya akan menghasilkan dropdown
     * kosong — dan dokumen yang lahir dari situ tidak punya jalan keluar.
     */
    public function offersChoice(): bool
    {
        return $this->requester_selectable
            && $this->isTypeSelectable()
            && $this->candidateEmployeeIds() !== [];
    }
}
