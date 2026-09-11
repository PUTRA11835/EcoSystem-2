<?php

namespace App\Services\CashAdvance;

use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceReport;
use App\Models\CashAdvance\CashAdvanceSetting;

/**
 * Aritmetika uang sub-modul Cash Advance — MURNI, tanpa satu pun sentuhan
 * database, session, atau container.
 *
 * Kenapa dipisah begini (pola yang sama dengan OvertimeRateService,
 * ReimbursementTotalService, PurchaseRequestSummaryService): angka yang sama
 * harus keluar saat karyawan mengajukan, saat admin membuat atas nama orang
 * lain, saat penyetuju mengubah nominal, dan saat dokumen dicetak. Menguji satu
 * fungsi murni jauh lebih murah — dan jauh lebih meyakinkan — daripada menguji
 * keempat jalur itu masing-masing.
 *
 * 🔴 INI MODUL YANG MENGELUARKAN UANG PERUSAHAAN. Salah hitung di sini tidak
 * muncul sebagai galat; ia muncul sebagai angka keliru di dokumen yang sudah
 * ditandatangani. Itulah sebabnya seluruh isi berkas ini punya unit test.
 */
class CashAdvanceAmountService
{
    /**
     * Membaca nominal yang diketik pengguna menjadi angka.
     *
     * Form acuan menampilkan "100.000" dan "350.000,00" — format Indonesia,
     * titik sebagai pemisah ribuan dan koma sebagai desimal. Peramban mengirimkan
     * apa adanya, jadi server tidak boleh mengandalkan `(float)` polos: PHP
     * membaca "100.000" sebagai 100,0 — seratus rupiah, bukan seratus ribu.
     *
     * 🔴 Kesalahan itu tidak akan pernah memunculkan galat. Ia hanya membuat
     * pengajuan seratus ribu tersimpan sebagai seratus, dan baru ketahuan saat
     * kas tidak cocok.
     *
     * Yang ditangani:
     *   "100.000"      -> 100000.0   (titik = ribuan)
     *   "350.000,50"   -> 350000.5   (koma = desimal)
     *   "100000.50"    -> 100000.5   (titik = desimal, tidak ada koma & pola
     *                                  ribuannya tidak cocok)
     *   "Rp 1.500.000" -> 1500000.0  (simbol dibuang)
     *   1500000        -> 1500000.0  (sudah berupa angka, lewat apa adanya)
     */
    public function parseAmount(mixed $raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            return round((float) $raw, 2);
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return 0.0;
        }

        // Buang segala yang bukan angka, titik, koma, atau minus.
        $text = preg_replace('/[^0-9,.\-]/', '', $text) ?? '';
        if ($text === '' || $text === '-') {
            return 0.0;
        }

        $hasComma = str_contains($text, ',');
        $hasDot   = str_contains($text, '.');

        if ($hasComma && $hasDot) {
            // Keduanya ada: yang MUNCUL TERAKHIR adalah pemisah desimal.
            // "1.500,50" (Indonesia) vs "1,500.50" (Inggris) — keduanya benar
            // di tempat asalnya, dan aturan ini membaca keduanya dengan benar.
            $decimalSep = strrpos($text, ',') > strrpos($text, '.') ? ',' : '.';
            $thousandSep = $decimalSep === ',' ? '.' : ',';

            $text = str_replace($thousandSep, '', $text);
            $text = str_replace($decimalSep, '.', $text);

            return round((float) $text, 2);
        }

        if ($hasComma) {
            // Hanya koma. Kalau bentuknya persis kelompok tiga digit
            // ("1,500,000") itu pemisah ribuan gaya Inggris; selain itu desimal.
            return round((float) $this->resolveSingleSeparator($text, ','), 2);
        }

        if ($hasDot) {
            // Hanya titik. "100.000" itu ribuan gaya Indonesia; "100.5" desimal.
            return round((float) $this->resolveSingleSeparator($text, '.'), 2);
        }

        return round((float) $text, 2);
    }

    /**
     * Menentukan apakah satu jenis pemisah berperan sebagai ribuan atau desimal.
     *
     * Aturannya berdasar BENTUK, bukan tebakan: pemisah ribuan selalu diikuti
     * kelompok tepat tiga digit, dan boleh muncul lebih dari sekali. Apa pun di
     * luar itu adalah desimal.
     */
    private function resolveSingleSeparator(string $text, string $separator): string
    {
        $negative = str_starts_with($text, '-');
        $digits   = ltrim($text, '-');

        $quoted = preg_quote($separator, '/');

        // "1.000" · "1.000.000" · "12.345.678" -> pemisah ribuan
        $isThousandGrouping = (bool) preg_match('/^\d{1,3}(' . $quoted . '\d{3})+$/', $digits);

        $clean = $isThousandGrouping
            ? str_replace($separator, '', $digits)
            : str_replace($separator, '.', $digits);

        return ($negative ? '-' : '') . $clean;
    }

    /** Membentuk nominal untuk ditampilkan: 350000 -> "350.000,00". */
    public function format(float $amount, bool $withDecimals = true): string
    {
        return number_format($amount, $withDecimals ? 2 : 0, ',', '.');
    }

    /**
     * Menghitung penyelesaian sebuah Cash Advance dari SELURUH realisasi.
     *
     * 🔴 SYARAT MENGIKAT KEPUTUSAN D143 — baca sebelum mengubah apa pun di sini.
     *
     * Parameternya adalah ARRAY nominal, bukan satu angka, dan itu disengaja.
     * Setelan `car_multiple_per_ca` hari ini bawaannya mati (satu CAR per CA),
     * tetapi kalau perhitungan ini ditulis "ambil CAR-nya" lebih dulu, menyalakan
     * sakelar itu kelak akan diam-diam menghasilkan SISA UANG YANG SALAH — dan
     * salahnya tidak muncul sebagai galat, hanya sebagai angka keliru di dokumen
     * keuangan.
     *
     * Dengan sakelar mati, array-nya berisi satu elemen dan hasilnya identik.
     * Dengan sakelar hidup, ia langsung benar. Nol perubahan kode.
     *
     * @param  float          $advanceAmount    Nominal CA yang DIBEKUKAN
     * @param  array<float>   $reportedAmounts  Nominal tiap CAR yang DISETUJUI
     * @return array{reported: float, outstanding: float, type: string}
     *         outstanding POSITIF = sisa dikembalikan karyawan (refund)
     *         outstanding NEGATIF = kekurangan ditagihkan ke perusahaan (claim)
     */
    public function settlement(float $advanceAmount, array $reportedAmounts): array
    {
        $reported = round(array_sum(array_map('floatval', $reportedAmounts)), 2);
        $advance  = round($advanceAmount, 2);

        $outstanding = round($advance - $reported, 2);

        return [
            'reported'    => $reported,
            'outstanding' => $outstanding,
            'type'        => $this->settlementType($outstanding),
        ];
    }

    /**
     * Jenis penyelesaian dari selisihnya.
     *
     * 🔴 `exact` BUKAN kemewahan. Selisih nol berbeda artinya dari "belum
     * dihitung", dan keduanya tidak boleh terlihat sama di layar keuangan.
     *
     * Perbandingannya memakai ambang 0,005 — bukan `=== 0.0` — karena angka
     * pecahan biner tidak pernah persis nol setelah dijumlahkan. Nilai di bawah
     * setengah sen tidak punya arti pada dokumen rupiah.
     */
    public function settlementType(float $outstanding): string
    {
        if (abs($outstanding) < 0.005) {
            return CashAdvanceReport::SETTLEMENT_EXACT;
        }

        return $outstanding > 0
            ? CashAdvanceReport::SETTLEMENT_REFUND
            : CashAdvanceReport::SETTLEMENT_CLAIM;
    }

    /**
     * Status penyelesaian CA dari keadaan CAR-nya.
     *
     * @param  int  $totalReports     Berapa CAR menempel (apa pun statusnya)
     * @param  int  $approvedReports  Berapa di antaranya sudah disetujui
     */
    public function settlementStatus(int $totalReports, int $approvedReports): string
    {
        if ($totalReports === 0) {
            return CashAdvance::SETTLE_UNREPORTED;
        }

        // Buku ditutup hanya kalau SELURUH laporan yang menempel sudah lulus.
        // Satu CAR yang masih menunggu berarti angkanya masih bisa berubah.
        return $approvedReports >= $totalReports
            ? CashAdvance::SETTLE_SETTLED
            : CashAdvance::SETTLE_REPORTING;
    }

    /**
     * Memeriksa nominal terhadap batas di Settings.
     *
     * Mengembalikan bentuk ['allowed' => bool, 'reason' => string, ...] meniru
     * PeriodService — gerbang bisnis di aplikasi ini selalu berbentuk begitu,
     * supaya pemanggilnya tidak perlu menebak apakah `false` berarti "ditolak"
     * atau "gagal memeriksa".
     *
     * 🔴 Dua kebijakan yang berbeda tegas (bukan satu boolean):
     *   flag  -> lewat batas TETAP DITERIMA, tetapi ditandai di kolom `flags`
     *   block -> lewat batas DITOLAK validasi
     * Batas 0 berarti "tanpa batas", bukan "nol" — konsisten dengan Overtime,
     * Reimbursement, dan Purchase Request.
     *
     * @return array{allowed: bool, reason: string, flagged: bool, flags: array<string,mixed>}
     */
    public function checkLimits(float $amount, CashAdvanceSetting $setting): array
    {
        $min    = (float) $setting->min_amount;
        $max    = (float) $setting->max_amount;
        $blocks = $setting->blocksOverLimit();
        $flags  = [];

        if ($amount <= 0) {
            return [
                'allowed' => false,
                'reason'  => 'Amount must be greater than zero.',
                'flagged' => false,
                'flags'   => [],
            ];
        }

        // Batas BAWAH selalu memblokir, apa pun over_limit_policy-nya.
        // Kebijakan `flag` menjawab "nominalnya besar sekali, tetap saya
        // teruskan tapi saya tandai" — itu tidak punya padanan yang masuk akal
        // untuk nominal yang terlalu KECIL, yang biasanya salah ketik.
        if ($setting->hasMinAmount() && $amount < $min) {
            return [
                'allowed' => false,
                'reason'  => 'Amount is below the minimum of ' . $this->format($min) . '.',
                'flagged' => false,
                'flags'   => [],
            ];
        }

        if ($setting->hasMaxAmount() && $amount > $max) {
            if ($blocks) {
                return [
                    'allowed' => false,
                    'reason'  => 'Amount exceeds the maximum of ' . $this->format($max) . '.',
                    'flagged' => false,
                    'flags'   => [],
                ];
            }

            $flags['over_limit'] = [
                'max'    => $max,
                'amount' => $amount,
            ];
        }

        return [
            'allowed' => true,
            'reason'  => '',
            'flagged' => $flags !== [],
            'flags'   => $flags,
        ];
    }

    /**
     * Memeriksa realisasi CAR terhadap nominal CA-nya.
     *
     * Melebihi nominal BUKAN kesalahan menurut dirinya sendiri — itu keadaan
     * `claim` yang sah, dan setelan `car_allow_over_amount` yang memutuskan
     * apakah organisasi ini menerimanya.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function checkReportedAgainstAdvance(
        float $reported,
        float $advanceAmount,
        CashAdvanceSetting $setting
    ): array {
        if ($reported <= 0) {
            return [
                'allowed' => false,
                'reason'  => 'Reported amount must be greater than zero.',
            ];
        }

        if (! $setting->car_allow_over_amount && $reported > round($advanceAmount, 2) + 0.005) {
            return [
                'allowed' => false,
                'reason'  => 'Reported amount (' . $this->format($reported) . ') exceeds the cash advance of '
                             . $this->format($advanceAmount) . '. Enable "Allow over amount" in settings to permit this.',
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Berapa hari sebuah CA terlambat dipertanggungjawabkan (Keputusan C10).
     *
     * `car_due_days` = 0 berarti tanpa tenggat, dan fungsi ini mengembalikan
     * `overdue = false` tanpa menghitung apa pun — supaya setelan bawaan tidak
     * pernah menandai dokumen siapa pun.
     *
     * 🔴 Batas yang disebut apa adanya: penilaian ini dilakukan SAAT DIBACA,
     * bukan lewat perintah terjadwal. Tidak ada notifikasi otomatis yang terkirim
     * tepat pada hari tenggat; yang ada adalah penanda yang muncul begitu
     * halamannya dibuka. Scheduler-nya belum dibangun (jawaban C12).
     *
     * @param  int  $daysSinceApproved  Hari sejak CA disetujui
     * @return array{overdue: bool, days_late: int, due_in: int|null}
     */
    public function reportDueStatus(int $daysSinceApproved, CashAdvanceSetting $setting): array
    {
        if (! $setting->hasCarDueLimit()) {
            return ['overdue' => false, 'days_late' => 0, 'due_in' => null];
        }

        $limit = (int) $setting->car_due_days;
        $late  = $daysSinceApproved - $limit;

        return [
            'overdue'   => $late > 0,
            'days_late' => max(0, $late),
            'due_in'    => max(0, -$late),
        ];
    }
}
