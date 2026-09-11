<?php

namespace App\Models\CashAdvance;

use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CETAKAN langkah persetujuan — untuk Cash Advance DAN Cash Advance Report.
 *
 * Ini KONFIGURASI, bukan riwayat. Riwayat tiap dokumen disimpan terpisah di
 * CashAdvanceApproval / CashAdvanceReportApproval, DISALIN dari tabel ini saat
 * dokumen dibuat.
 *
 * 🔴 SATU TABEL, DUA MODUL (Keputusan D136). Kolom `module` membedakannya.
 * Konsekuensinya wajib dipegang disiplin: SETIAP kueri harus menyaring `module`.
 * Scope forModule() di bawah dibuat supaya lupa-menyaring menjadi sulit, bukan
 * sekadar terlarang — pakai itu, jangan `where('module', ...)` manual.
 *
 * ── actor_role: kolom yang tidak ada di tiga modul sebelumnya ───────────────
 *
 * Editor alur pada aplikasi acuan punya EMPAT kontrol per langkah, dan ACTOR
 * adalah salah satunya. Ia mengerjakan dua hal yang keduanya terlihat di layar:
 *
 *   1. Approval Timeline menulis "Verificator - Verification" dan
 *      "Approver - Position Approval". Bagian kiri itu actor_role, kanan `name`.
 *   2. Kolom "Approved by," pada CETAKAN mengambil pelaku langkah
 *      ber-actor_role = approver yang TERAKHIR menyetujui.
 *
 * 🔴 Kenapa tidak ditebak dari urutan ("langkah terakhir pasti approver"):
 * tebakan itu langsung salah begitu ada dua verifikator berturutan, atau begitu
 * alurnya dibalik lewat tombol naik/turun yang memang disediakan editor. Dan
 * salahnya tidak muncul sebagai galat — ia muncul di KERTAS YANG SUDAH
 * DITANDATANGANI, dengan nama orang yang keliru di kolom persetujuan.
 */
class CashAdvanceApprovalStep extends Model
{
    protected $table = 'cash_advance_approval_steps';

    protected $fillable = [
        'module', 'order_seq', 'name',
        'approver_type', 'approver_role_id', 'approver_employee_ids',
        'actor_role', 'requester_selectable', 'is_active',
    ];

    protected $casts = [
        'order_seq'             => 'integer',
        'approver_role_id'      => 'integer',
        'approver_employee_ids' => 'array',
        'requester_selectable'  => 'boolean',
        'is_active'             => 'boolean',
    ];

    // ── Modul ───────────────────────────────────────────────────────────────

    public const MODULE_CA  = 'cash_advance';
    public const MODULE_CAR = 'cash_advance_report';

    public const MODULES = [self::MODULE_CA, self::MODULE_CAR];

    // ── Tipe penyetuju ──────────────────────────────────────────────────────
    // Padanan istilah acuan: By Position | Direct User | Direct Manager

    public const TYPE_ROLE           = 'role';
    public const TYPE_EMPLOYEE       = 'employee';
    public const TYPE_DIRECT_MANAGER = 'direct_manager';

    public const TYPES = [
        self::TYPE_ROLE,
        self::TYPE_EMPLOYEE,
        self::TYPE_DIRECT_MANAGER,
    ];

    /**
     * Tipe yang benar-benar dapat DIPILIH hari ini.
     *
     * `direct_manager` sengaja di luar daftar ini tetapi TETAP terdaftar di
     * TYPES: ia ditampilkan di layar dalam keadaan mati beserta alasannya, bukan
     * disembunyikan. Menyembunyikannya membuat orang mengira fitur itu tidak
     * pernah direncanakan; menampilkannya mati menyatakan bahwa ia menunggu data
     * hierarki atasan (pekerjaan tertunda T.2).
     */
    public const SELECTABLE_TYPES = [
        self::TYPE_ROLE,
        self::TYPE_EMPLOYEE,
    ];

    /** Label yang dipakai di layar, meniru istilah aplikasi acuan. */
    public const TYPE_LABELS = [
        self::TYPE_ROLE           => 'By Position',
        self::TYPE_EMPLOYEE       => 'Direct User',
        self::TYPE_DIRECT_MANAGER => 'Direct Manager',
    ];

    // ── Peran pelaku (kolom ACTOR pada editor acuan) ────────────────────────

    public const ACTOR_VERIFICATOR = 'verificator';
    public const ACTOR_APPROVER    = 'approver';

    public const ACTOR_ROLES = [self::ACTOR_VERIFICATOR, self::ACTOR_APPROVER];

    public const ACTOR_LABELS = [
        self::ACTOR_VERIFICATOR => 'Verificator',
        self::ACTOR_APPROVER    => 'Approver',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    /**
     * 🔴 Satu-satunya cara yang benar membaca tabel ini.
     *
     * Tanpa penyaringan modul, alur CA akan ikut memuat langkah CAR dan
     * sebaliknya — dan hasilnya bukan galat, melainkan dokumen yang menunggu
     * persetujuan orang yang tidak pernah diminta menyetujuinya.
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_seq');
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'approver_role_id', 'id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isApproverStep(): bool
    {
        return $this->actor_role === self::ACTOR_APPROVER;
    }

    public function actorLabel(): string
    {
        return self::ACTOR_LABELS[$this->actor_role] ?? ucfirst((string) $this->actor_role);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->approver_type] ?? (string) $this->approver_type;
    }

    /**
     * Ringkasan penyetuju untuk ditampilkan di layar.
     *
     * Kembarannya ada di CashAdvanceApproval dan itu DISENGAJA: yang ini
     * menjawab "langkah ini ditujukan ke siapa" (konfigurasi), yang di sana
     * menjawab "dokumen ini menunggu siapa" (riwayat yang sudah dibekukan).
     * Menggabungkannya berarti salah satu pertanyaan kehilangan jawabannya —
     * pelajaran dari bug 500 yang ditemukan pemilik sistem di langkah P8.
     */
    public function approverLabel(): string
    {
        return match ($this->approver_type) {
            self::TYPE_ROLE
                => $this->role?->name ?? 'Role #' . $this->approver_role_id,

            self::TYPE_EMPLOYEE
                => count($this->approver_employee_ids ?? []) . ' selected employee(s)',

            self::TYPE_DIRECT_MANAGER => 'Direct manager (not available yet)',

            default => (string) $this->approver_type,
        };
    }

    /**
     * Apakah langkah ini punya kandidat penyetuju yang dapat dituju?
     *
     * Dipakai penjagaan di CashAdvanceSettingController: langkah
     * `requester_selectable` tanpa kandidat ditolak saat disimpan, karena
     * dokumen yang lahir darinya tidak akan punya jalan keluar.
     */
    public function hasCandidates(): bool
    {
        return match ($this->approver_type) {
            self::TYPE_ROLE     => $this->approver_role_id !== null,
            self::TYPE_EMPLOYEE => ($this->approver_employee_ids ?? []) !== [],

            // Belum dapat dijalankan: `employee.reports_to_id` belum ada dan
            // `employee_basic_data.direct_supervision` 100% NULL (T.2).
            self::TYPE_DIRECT_MANAGER => false,

            default => false,
        };
    }

    /** Tipe ini benar-benar dapat dijalankan hari ini? */
    public function isTypeSelectable(): bool
    {
        return in_array($this->approver_type, self::SELECTABLE_TYPES, true);
    }

    /**
     * Langkah ini menawarkan pilihan penyetuju kepada pemohon?
     *
     * TIGA syarat, dan ketiganya harus dipenuhi (D126):
     *   1. ditandai `requester_selectable` di Settings
     *   2. tipenya dapat dijalankan — `direct_manager` tidak
     *   3. kandidatnya benar-benar ADA
     *
     * 🔴 Syarat ketiga bukan formalitas. Posisi yang pemegangnya habis — pensiun,
     * pindah role — membuat dropdown lahir kosong, dan dokumen yang lahir dari
     * situ menunggu orang yang tidak ada. Halaman Settings menolak langkah tanpa
     * kandidat saat DISIMPAN, tetapi data dapat berubah setelahnya; ini
     * pemeriksaan kedua di saat yang benar-benar menentukan.
     */
    public function offersChoice(): bool
    {
        return $this->requester_selectable
            && $this->isTypeSelectable()
            && $this->candidateEmployeeIds() !== [];
    }
    /**
     * Daftar employee_id yang boleh dipilih pemohon pada langkah ini.
     *
     * Dipakai dua tempat: mengisi dropdown Approver di form pengajuan, dan
     * memeriksa bahwa pilihan yang dikirim memang salah satu kandidat — form
     * yang boleh dipercaya adalah form yang tidak pernah dipercaya.
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
}
