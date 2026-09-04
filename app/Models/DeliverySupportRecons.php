<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

/**
 * Satu batch rekonsiliasi tiket milik sebuah Delivery Support.
 *
 * Status:
 *   draft     → masih bisa diedit (header & daftar tiket) atau dihapus
 *   submitted → terkunci; tiket di dalamnya permanen terikat ke batch ini
 */
class DeliverySupportRecons extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_recons';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'delivery_support_id',
        'recons_number',
        'description',
        'recons_date',
        'status',
        'created_by_id',
        'submitted_by_id',
        'submitted_at',
    ];

    protected $casts = [
        'recons_date'  => 'date',
        'submitted_at' => 'datetime',
    ];

    /** Label untuk audit log — nomor recons lebih informatif dari description. */
    public function auditRecordLabel(): string
    {
        return $this->recons_number ?: ('Recons #' . $this->getKey());
    }

    // ── Relationships ──────────────────────────────────────────────

    public function support()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function lines()
    {
        return $this->hasMany(DeliverySupportReconsTicket::class, 'delivery_support_recons_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by_id', 'employee_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(Employee::class, 'submitted_by_id', 'employee_id');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->isSubmitted() ? 'Submitted' : 'Draft';
    }

    /** Awalan seluruh nomor Recons. */
    public const NUMBER_PREFIX = 'MDRC';

    /**
     * Nomor Recons berikutnya: MDRC-[customer_code]-[yymm]-[xxxx].
     *
     *   customer_code : dari master Business Partner (`customer.customer_code`),
     *                   dipakai apa adanya tanpa batas panjang.
     *   yymm          : 2 digit tahun + 2 digit bulan dari tanggal Recons.
     *   xxxx          : counter GLOBAL (semua customer) yang reset tiap tahun.
     *
     * Counter dihitung dari nomor yang sudah ada pada TAHUN yang sama, bukan
     * dari COUNT baris, supaya draft yang dihapus tidak membuat nomor dipakai
     * ulang. Nomor berformat lain (mis. data lama RCN-*) diabaikan.
     *
     * Catatan konkurensi: pemanggil wajib menyiapkan retry, karena dua
     * penyimpanan bersamaan bisa menghasilkan counter yang sama — kebentrokan
     * itu ditangkap oleh unique index `uniq_recons_number`.
     */
    public static function nextNumberFor(DeliverySupport $support, ?\DateTimeInterface $date = null): string
    {
        $date = $date ? \Illuminate\Support\Carbon::instance($date) : \Illuminate\Support\Carbon::now();
        $yy   = $date->format('y');
        $mm   = $date->format('m');

        $counter = static::nextCounterForYear($yy);

        return implode('-', [
            self::NUMBER_PREFIX,
            self::customerCodeFor($support),
            $yy . $mm,
            str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Counter berikutnya untuk sebuah tahun (2 digit), global lintas customer.
     *
     * Nomor dibaca dengan regex yang di-anchor ke DUA segmen terakhir
     * (`-yymm-xxxx`), bukan dengan explode('-'), karena kode customer ditulis
     * apa adanya dari master dan boleh saja memuat tanda hubung.
     */
    private static function nextCounterForYear(string $yy): int
    {
        $pattern = '/^' . preg_quote(self::NUMBER_PREFIX, '/') . '-(?<code>.*)-(?<yymm>\d{4})-(?<counter>\d{4})$/';

        $highest = static::where('recons_number', 'like', self::NUMBER_PREFIX . '-%')
            ->pluck('recons_number')
            ->map(function (string $number) use ($pattern, $yy) {
                if (!preg_match($pattern, $number, $m)) {
                    return 0;
                }

                return substr($m['yymm'], 0, 2) === $yy ? (int) $m['counter'] : 0;
            })
            ->max() ?? 0;

        return $highest + 1;
    }

    /**
     * Kode customer dari master Business Partner → General Information
     * (`customer.customer_code`), dipakai **apa adanya**.
     *
     * Tidak di-uppercase, tidak disingkat, dan tidak ada karakter yang dibuang
     * — hanya spasi di ujung yang dirapikan. Ketentuan pemilik sistem: "tulis
     * apa adanya berdasarkan apa yang ada di customer code business partner,
     * jangan diubah atau disingkat".
     *
     * Support tanpa client (atau kodenya kosong) memakai 'NA' supaya nomor
     * tetap terbentuk dan penyimpanan tidak gagal.
     */
    public static function customerCodeFor(DeliverySupport $support): string
    {
        $support->loadMissing('client');

        $code = trim((string) ($support->client->customer_code ?? ''));

        return $code !== '' ? $code : 'NA';
    }
}
