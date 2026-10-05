<?php

namespace App\Services\HrProfile;

/**
 * Aturan murni blok Engagement konsultan (HC-D47): nilai yang sah, normalisasi tarif, validasi. Tanpa DB/router.
 *
 * Daftar skema: form konsultan ESH hanya memperlihatkan satu nilai ("Mandays"); tiga lainnya adalah
 * USULAN umum untuk kontrak konsultan dan dapat diubah di sini tanpa migrasi (kolom bukan ENUM).
 */
class EngagementRules
{
    public const SCHEMES = [
        'mandays'     => 'Mandays',
        'monthly'     => 'Monthly',
        'fixed_price' => 'Fixed price',
        'hourly'      => 'Hourly',
    ];

    public const CURRENCIES = ['IDR', 'USD', 'EUR', 'SGD'];

    /** Field non-sensitif (izin seksi `engagement`). */
    public const BASIC_FIELDS = [
        'engagement_scheme', 'vendor_partner', 'client_company', 'assignment_role', 'managed_by', 'start_date', 'end_date',
    ];

    /** Field sensitif (izin terpisah `engagement_rate`). */
    public const RATE_FIELDS = ['rate', 'currency'];

    private const TEXT_MAX = 150;

    /**
     * Ubah isian tarif ("2.700.000,00", "2,700,000.50", "2700000") menjadi "2700000.00"; null bila tidak sah.
     * Aturan: bila ada koma DAN titik, yang paling kanan adalah pemisah desimal; bila hanya satu jenis
     * pemisah dan ia muncul lebih dari sekali atau diikuti tepat 3 digit, itu pemisah ribuan.
     */
    public static function normalizeRate(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $v = trim(str_replace(["\u{00A0}", ' '], '', $raw));
        if ($v === '' || !preg_match('/^[0-9.,]+$/', $v)) {
            return null;
        }

        $lastDot = strrpos($v, '.');
        $lastComma = strrpos($v, ',');

        if ($lastDot !== false && $lastComma !== false) {
            $decimalSep = $lastDot > $lastComma ? '.' : ',';
        } elseif ($lastDot !== false || $lastComma !== false) {
            $sep = $lastDot !== false ? '.' : ',';
            $count = substr_count($v, $sep);
            $after = strlen($v) - 1 - strrpos($v, $sep);
            $decimalSep = ($count === 1 && $after !== 3) ? $sep : null;   // "2.700" → ribuan; "2.5" → desimal
        } else {
            $decimalSep = null;
        }

        if ($decimalSep === null) {
            $int = str_replace(['.', ','], '', $v);
            $dec = '00';
        } else {
            $pos = strrpos($v, $decimalSep);
            $int = str_replace(['.', ','], '', substr($v, 0, $pos));
            $dec = substr($v, $pos + 1);
            if ($dec === '' || !ctype_digit($dec) || strlen($dec) > 2) {
                return null;
            }
            $dec = str_pad($dec, 2, '0');
        }

        if ($int === '' || !ctype_digit($int) || strlen(ltrim($int, '0') ?: '0') > 13) {
            return null;                                   // kolom decimal(15,2) → maks 13 digit bulat
        }

        return (ltrim($int, '0') ?: '0') . '.' . $dec;
    }

    /**
     * Validasi + normalisasi satu set input (hanya kunci yang dikirim).
     *
     * @param  array<string,mixed>  $data  kunci dari BASIC_FIELDS / RATE_FIELDS
     * @return array{0: array<string,mixed>, 1: array<string,string>}  [bersih, galat]
     */
    public static function clean(array $data, ?string $existingStart = null, ?string $existingEnd = null): array
    {
        $clean = [];
        $errors = [];

        foreach ($data as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;
            if ($value === '' || $value === null) {
                $clean[$key] = null;
                continue;
            }

            switch ($key) {
                case 'engagement_scheme':
                    array_key_exists((string) $value, self::SCHEMES)
                        ? $clean[$key] = (string) $value
                        : $errors[$key] = 'Choose a valid engagement scheme.';
                    break;

                case 'start_date':
                case 'end_date':
                    $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
                    ($d && $d->format('Y-m-d') === $value)
                        ? $clean[$key] = (string) $value
                        : $errors[$key] = 'Enter a valid date.';
                    break;

                case 'rate':
                    $n = self::normalizeRate((string) $value);
                    $n === null ? $errors[$key] = 'Enter a valid amount, e.g. 2.700.000,00.' : $clean[$key] = $n;
                    break;

                case 'currency':
                    $c = strtoupper((string) $value);
                    in_array($c, self::CURRENCIES, true)
                        ? $clean[$key] = $c
                        : $errors[$key] = 'Currency must be one of: ' . implode(', ', self::CURRENCIES) . '.';
                    break;

                default: // teks bebas
                    if (!is_string($value) || mb_strlen($value) > self::TEXT_MAX) {
                        $errors[$key] = 'Maximum ' . self::TEXT_MAX . ' characters.';
                    } elseif (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                        $errors[$key] = 'Contains invalid characters.';
                    } else {
                        $clean[$key] = $value;
                    }
            }
        }

        // Lintas-field: selesai tidak boleh sebelum mulai (nilai akhir setelah simpan).
        $start = array_key_exists('start_date', $clean) ? $clean['start_date'] : $existingStart;
        $end   = array_key_exists('end_date', $clean) ? $clean['end_date'] : $existingEnd;
        if (!isset($errors['start_date']) && !isset($errors['end_date']) && $start && $end && $end < $start) {
            $errors['end_date'] = 'End date cannot be earlier than the start date.';
        }

        // Tarif tanpa mata uang → bawaan IDR (form konsultan ESH: IDR).
        if (isset($clean['rate']) && !array_key_exists('currency', $clean)) {
            $clean['currency'] = 'IDR';
        }

        return [$clean, $errors];
    }
}
