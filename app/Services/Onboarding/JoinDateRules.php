<?php

namespace App\Services\Onboarding;

/**
 * Aturan MURNI untuk alat join date HR (HC-D64): membaca tanggal dari berbagai format dan memecah teks CSV/tempel
 * (ECI, tanggal) menjadi baris. Tanpa database — diuji unit. Pencocokan karyawan ada di JoinDateService.
 *
 * Format tanggal yang diterima (dibaca sebagai HARI/BULAN/TAHUN, gaya Indonesia, bukan bulan/hari):
 *   2026-10-05 · 05/10/2026 · 5/10/2026 · 05-10-2026 · 05.10.2026 · 5 Oct 2026 · 05 October 2026
 */
final class JoinDateRules
{
    public const MIN_DATE = '1990-01-01';
    public const MAX_AHEAD_DAYS = 30;
    public const MAX_ROWS = 1000;

    private const FORMATS = ['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'j.n.Y', 'd M Y', 'j M Y', 'd F Y', 'j F Y'];

    /** Tanggal ISO (Y-m-d) atau null bila tak terbaca / tak sah (mis. 31/02/2026). */
    public static function parseDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        foreach (self::FORMATS as $format) {
            $d = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($d && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /** Kode galat rentang tanggal, atau null bila wajar: 'too_old' | 'in_future'. */
    public static function rangeError(string $iso, ?\DateTimeInterface $today = null): ?string
    {
        $today = $today ? \DateTimeImmutable::createFromInterface($today) : new \DateTimeImmutable('today');
        if ($iso < self::MIN_DATE) {
            return 'too_old';
        }
        if ($iso > $today->modify('+' . self::MAX_AHEAD_DAYS . ' days')->format('Y-m-d')) {
            return 'in_future';
        }

        return null;
    }

    /**
     * Pecah teks tempel/CSV menjadi baris. Pemisah (koma, titik koma, tab) dideteksi dari baris pertama; kutip dan BOM
     * (hasil ekspor Excel) ditangani; baris judul ("ECI, Join date") dilewati; baris kosong diabaikan.
     *
     * @return array<int, array{line:int, eci:string, raw:string, date:?string, error:?string}>
     *         error: null | 'missing_eci' | 'missing_date' | 'invalid_date' | 'too_old' | 'in_future'
     */
    public static function parseCsv(string $text, ?\DateTimeInterface $today = null): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $lines = preg_split('/\r\n|\n|\r/', $text);
        $delimiter = ',';
        foreach ($lines as $l) {
            if (trim($l) === '') {
                continue;
            }
            $delimiter = collect([',' => substr_count($l, ','), ';' => substr_count($l, ';'), "\t" => substr_count($l, "\t")])->sortDesc()->keys()->first();
            break;
        }

        $rows = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map('trim', str_getcsv($line, $delimiter));
            $eci = $cells[0] ?? '';
            $raw = $cells[1] ?? '';
            if ($i === 0 || count($rows) === 0) {
                if (in_array(mb_strtolower($eci), ['eci', 'employee id', 'employee_id', 'nip', 'id'], true)) {
                    continue; // baris judul
                }
            }
            $row = ['line' => $i + 1, 'eci' => $eci, 'raw' => $raw, 'date' => null, 'error' => null];
            if ($eci === '') {
                $row['error'] = 'missing_eci';
            } elseif ($raw === '') {
                $row['error'] = 'missing_date';
            } else {
                $iso = self::parseDate($raw);
                if ($iso === null) {
                    $row['error'] = 'invalid_date';
                } else {
                    $row['date'] = $iso;
                    $row['error'] = self::rangeError($iso, $today);
                }
            }
            $rows[] = $row;
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        return $rows;
    }
}
