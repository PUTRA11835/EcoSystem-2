<?php

namespace App\Support\Contracts;

use Carbon\Carbon;

/**
 * Aturan MURNI menu Contract (tanpa database, mudah diuji): jenis & status kontrak, nomor, tanggal,
 * gaji, pemilihan template, penggantian placeholder dan kesiapan data (HC-D66).
 */
class ContractRules
{
    public const TYPE_PKWT = 'PKWT';
    public const TYPE_PKWTT = 'PKWTT';
    public const TYPE_EXTERNAL = 'EXTERNAL';

    /** @var array<string,string> */
    public const TYPES = [
        self::TYPE_PKWT     => 'PKWT',
        self::TYPE_PKWTT    => 'PKWTT',
        self::TYPE_EXTERNAL => 'External consultant',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TERMINATED = 'terminated';
    /** Baris lama (Master Employee) yang tidak aktif dan tak berstatus. */
    public const STATUS_INACTIVE = 'inactive';

    /** @var array<string,string> */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT      => 'Draft',
        self::STATUS_ACTIVE     => 'Active',
        self::STATUS_EXPIRED    => 'Expired',
        self::STATUS_TERMINATED => 'Terminated',
        self::STATUS_INACTIVE   => 'Inactive',
    ];

    /** Status yang boleh dipilih HR di formulir (Expired dihitung otomatis dari tanggal berakhir). */
    public const SETTABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_TERMINATED];

    /** @var array<string,string> label singkat → jenis */
    private const TYPE_ALIASES = [
        'PKWT' => self::TYPE_PKWT, 'SPKWT' => self::TYPE_PKWT,
        'PKWTT' => self::TYPE_PKWTT, 'SPKWTT' => self::TYPE_PKWTT, 'PERMANENT' => self::TYPE_PKWTT,
        'EXTERNAL' => self::TYPE_EXTERNAL, 'EXT' => self::TYPE_EXTERNAL, 'PKS' => self::TYPE_EXTERNAL,
    ];

    /** Jenis kanonik dari teks bebas (data lama); null bila tak dikenali. */
    public static function normalizeType(?string $type): ?string
    {
        $key = strtoupper(trim((string) $type));

        return self::TYPE_ALIASES[$key] ?? null;
    }

    // ── Status ────────────────────────────────────────────────────────────

    /**
     * Status yang ditampilkan. `status` tersimpan hanya draft/active/terminated; "expired" selalu dihitung dari
     * tanggal berakhir, sehingga kontrak lama (status NULL) dan kontrak yang lewat tanggal tidak perlu pekerjaan terjadwal.
     */
    public static function effectiveStatus(?string $status, bool $isActive, ?string $endDate, string $today): string
    {
        if ($status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }
        if ($status === self::STATUS_TERMINATED) {
            return self::STATUS_TERMINATED;
        }
        if ($isActive) {
            return ($endDate !== null && $endDate !== '' && substr($endDate, 0, 10) < $today)
                ? self::STATUS_EXPIRED
                : self::STATUS_ACTIVE;
        }

        return $status === self::STATUS_EXPIRED ? self::STATUS_EXPIRED : self::STATUS_INACTIVE;
    }

    /** Kolom is_active yang harus ikut tersimpan: Onboarding & Master Employee membacanya. */
    public static function isActiveFor(string $status): bool
    {
        return $status === self::STATUS_ACTIVE;
    }

    // ── Nomor ─────────────────────────────────────────────────────────────

    /** @param array<string,string> $prefixes */
    public static function formatNumber(string $type, Carbon $date, string $unitCode, int $sequence, int $digits, array $prefixes): string
    {
        $prefix = $prefixes[$type] ?? $type;

        return sprintf('%s/%s/%s/%s%s', $prefix, $date->format('Y'), $date->format('m'), $unitCode, str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT));
    }

    // ── Tanggal ───────────────────────────────────────────────────────────

    /**
     * Pesan galat tanggal, atau null bila sah. PKWT wajib punya tanggal berakhir (≤ batas bulan);
     * PKWTT tidak boleh punya (kontrak tanpa batas waktu); External boleh kosong.
     */
    public static function dateError(string $type, ?string $start, ?string $end, int $pkwtMaxMonths): ?string
    {
        if ($start === null || $start === '') {
            return 'Start date is required.';
        }
        if ($end !== null && $end !== '' && $end < $start) {
            return 'End date must be on or after the start date.';
        }
        if ($type === self::TYPE_PKWT) {
            if ($end === null || $end === '') {
                return 'A PKWT contract needs an end date.';
            }
            if (Carbon::parse($start)->addMonths($pkwtMaxMonths)->lt(Carbon::parse($end))) {
                return "A PKWT contract cannot be longer than {$pkwtMaxMonths} months.";
            }
        }
        if ($type === self::TYPE_PKWTT && $end !== null && $end !== '') {
            return 'A PKWTT contract has no end date. Leave it empty.';
        }

        return null;
    }

    // ── Gaji ──────────────────────────────────────────────────────────────

    /**
     * Rapikan baris tunjangan dari formulir: buang baris kosong, nama dipotong 100 karakter, nilai ≥ 0 (dua desimal),
     * maksimal 20 baris. Baris dengan nama tanpa nilai tetap disimpan sebagai 0.
     *
     * @param  mixed  $raw
     * @return array<int,array{name:string,amount:float}>
     */
    public static function cleanComponents($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $amount = self::parseAmount($row['amount'] ?? 0);
            $rows[] = ['name' => mb_substr($name, 0, 100), 'amount' => max(0.0, $amount)];
            if (count($rows) >= 20) {
                break;
            }
        }

        return $rows;
    }

    /** "4.500.000,50" · "4500000.50" · 4500000 → 4500000.5 */
    public static function parseAmount($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        $negative = (bool) preg_match('/^[^0-9]*-/', $s); // tanda minus tidak boleh hilang diam-diam menjadi positif
        // Dua pemisah: yang terakhir = desimal. Satu pemisah dengan 3 angka di belakangnya = ribuan.
        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');
        if ($lastDot !== false && $lastComma !== false) {
            $decimal = $lastDot > $lastComma ? '.' : ',';
        } elseif ($lastComma !== false) {
            $decimal = strlen($s) - $lastComma - 1 === 3 ? null : ',';
        } elseif ($lastDot !== false) {
            $decimal = strlen($s) - $lastDot - 1 === 3 ? null : '.';
        } else {
            $decimal = null;
        }
        $digits = preg_replace('/[^0-9' . ($decimal ? preg_quote($decimal, '/') : '') . ']/', '', $s);
        if ($decimal) {
            $digits = str_replace($decimal, '.', $digits);
        }

        $value = is_numeric($digits) ? (float) $digits : 0.0;

        return $negative ? -$value : $value;
    }

    /** @param array<int,array{name:string,amount:float}> $components */
    public static function totalSalary(?float $basic, array $components): float
    {
        return round((float) $basic + array_sum(array_column($components, 'amount')), 2);
    }

    public static function money(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    // ── Template ──────────────────────────────────────────────────────────

    /**
     * Template yang dipakai otomatis: yang cocok dengan Position → template umum buatan HR (terbaru) → bawaan sistem.
     * Hanya template aktif dengan jenis yang sama.
     *
     * @param  array<int,array<string,mixed>>  $templates  baris contract_templates (id, contract_type, position, status, is_system_default, updated_at)
     */
    public static function pickTemplate(array $templates, string $type, ?string $position): ?array
    {
        $pool = array_values(array_filter($templates, fn ($t) => ($t['contract_type'] ?? null) === $type && ($t['status'] ?? 'active') === 'active'));
        $position = strtolower(trim((string) $position));

        if ($position !== '') {
            foreach ($pool as $t) {
                if (strtolower(trim((string) ($t['position'] ?? ''))) === $position) {
                    return $t;
                }
            }
        }

        $general = array_values(array_filter($pool, fn ($t) => trim((string) ($t['position'] ?? '')) === '' && empty($t['is_system_default'])));
        usort($general, fn ($a, $b) => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
        if ($general) {
            return $general[0];
        }

        foreach ($pool as $t) {
            if (!empty($t['is_system_default'])) {
                return $t;
            }
        }

        return null;
    }

    // ── Placeholder ───────────────────────────────────────────────────────

    /**
     * Ganti {{kunci}} dengan nilainya. Nilai teks di-escape; nilai ['html' => '...'] dipakai apa adanya
     * (hanya dibuat internal dari data yang sudah di-escape). Kunci tak dikenal dibiarkan agar HR melihat salah ketiknya.
     * Nilai kosong menjadi "-".
     *
     * @param  array<string,string|array{html:string}>  $values
     */
    public static function render(string $html, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($values) {
            $key = strtolower($m[1]);
            if (!array_key_exists($key, $values)) {
                return $m[0];
            }
            $value = $values[$key];
            if (is_array($value)) {
                return (string) ($value['html'] ?? '-');
            }
            $text = trim((string) $value);

            return $text === '' ? '-' : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }, $html);
    }

    /**
     * Placeholder di teks yang tidak ada di daftar jenisnya (salah ketik).
     *
     * @param  string[]  $known
     * @return string[]
     */
    public static function unknownPlaceholders(string $html, array $known): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $html, $m);

        return array_values(array_unique(array_diff(array_map('strtolower', $m[1]), $known)));
    }

    // ── Kesiapan data ─────────────────────────────────────────────────────

    /**
     * @param  array<int,array{key:string,label:string,section?:string}>  $items
     * @param  array<string,bool>  $ok  kunci → terpenuhi
     * @return array{items:array<int,array>,missing:array<int,array>,done:int,total:int,percent:int,ready:bool}
     */
    public static function readiness(array $items, array $ok): array
    {
        $rows = [];
        $missing = [];
        foreach ($items as $item) {
            $met = !empty($ok[$item['key']]);
            $row = $item + ['ok' => $met];
            $rows[] = $row;
            if (!$met) {
                $missing[] = $row;
            }
        }
        $total = count($rows);
        $done = $total - count($missing);

        return [
            'items'   => $rows,
            'missing' => $missing,
            'done'    => $done,
            'total'   => $total,
            'percent' => $total > 0 ? (int) round(100 * $done / $total) : 100,
            'ready'   => $missing === [],
        ];
    }
}
