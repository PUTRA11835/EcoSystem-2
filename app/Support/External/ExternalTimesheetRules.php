<?php

namespace App\Support\External;

use App\Support\Contracts\ContractRules;

/**
 * Aturan MURNI lembar jam (timesheet) dan invoice konsultan eksternal: status & transisi, validasi baris, nomor, jatuh tempo,
 * kode verifikasi cetak. Tanpa database, mudah diuji.
 */
class ExternalTimesheetRules
{
    // ── Status lembar ─────────────────────────────────────────────────────

    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const RETURNED = 'returned';
    public const APPROVED = 'approved';

    /** @var array<string,string> */
    public const STATUS_LABELS = [
        self::DRAFT => 'Draft', self::SUBMITTED => 'Submitted', self::RETURNED => 'Returned', self::APPROVED => 'Approved',
    ];

    /** @var array<string,string> */
    public const STATUS_BADGES = [
        self::DRAFT => 'bg-gray-200 text-gray-600', self::SUBMITTED => 'bg-blue-100 text-blue-700',
        self::RETURNED => 'bg-amber-100 text-amber-700', self::APPROVED => 'bg-green-100 text-green-700',
    ];

    private const SHEET_TRANSITIONS = [
        self::DRAFT     => [self::SUBMITTED],
        self::RETURNED  => [self::SUBMITTED],
        self::SUBMITTED => [self::APPROVED, self::RETURNED],
        self::APPROVED  => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::SHEET_TRANSITIONS[$from] ?? [], true);
    }

    /** Isi lembar hanya boleh diubah selagi belum diajukan (Draft) atau setelah dikembalikan. */
    public static function isEditable(string $status): bool
    {
        return in_array($status, [self::DRAFT, self::RETURNED], true);
    }

    // ── Status invoice ────────────────────────────────────────────────────

    public const INV_UPLOADED = 'uploaded';
    public const INV_VERIFIED = 'verified';
    public const INV_REJECTED = 'rejected';
    public const INV_PAID = 'paid';

    /** @var array<string,string> */
    public const INVOICE_LABELS = [
        self::INV_UPLOADED => 'Waiting verification', self::INV_VERIFIED => 'Verified', self::INV_REJECTED => 'Rejected', self::INV_PAID => 'Paid',
    ];

    /** @var array<string,string> */
    public const INVOICE_BADGES = [
        self::INV_UPLOADED => 'bg-blue-100 text-blue-700', self::INV_VERIFIED => 'bg-indigo-100 text-indigo-700',
        self::INV_REJECTED => 'bg-red-100 text-red-700', self::INV_PAID => 'bg-green-100 text-green-700',
    ];

    private const INVOICE_TRANSITIONS = [
        self::INV_UPLOADED => [self::INV_VERIFIED, self::INV_REJECTED],
        self::INV_REJECTED => [self::INV_UPLOADED],     // unggah ulang setelah ditolak
        self::INV_VERIFIED => [self::INV_PAID],
        self::INV_PAID     => [],
    ];

    public static function canInvoiceTransition(string $from, string $to): bool
    {
        return in_array($to, self::INVOICE_TRANSITIONS[$from] ?? [], true);
    }

    // ── Tahap penyetuju ───────────────────────────────────────────────────

    public const STAGE_APPROVAL = 'timesheet_approval';
    public const STAGE_INVOICE = 'invoice_verification';

    /** @var array<string,string> */
    public const STAGES = [self::STAGE_APPROVAL => 'Timesheet approval', self::STAGE_INVOICE => 'Invoice verification'];

    // ── Lokasi ────────────────────────────────────────────────────────────

    /** @var array<string,string> */
    public const LOCATIONS = ['ONSITE' => 'Onsite', 'REMOTE' => 'Remote', 'HYBRID' => 'Hybrid'];

    // ── Nomor, jatuh tempo, kode cetak ────────────────────────────────────

    public static function sheetNumber(int $year, int $month, int $sequence): string
    {
        return sprintf('EXT-TS/%04d/%02d/%04d', $year, $month, $sequence);
    }

    public static function dueDate(string $approvedDate, int $termDays): string
    {
        return date('Y-m-d', strtotime(substr($approvedDate, 0, 10) . ' +' . max(0, $termDays) . ' days'));
    }

    /** Kode verifikasi pada cetakan: sama selama nomor dan id sama, sulit ditebak tanpa kunci aplikasi. */
    public static function verificationCode(int $id, string $sheetNo, string $key): string
    {
        return 'EXT-TS-' . $id . '-' . substr(hash_hmac('sha256', $id . '|' . $sheetNo, $key), 0, 12);
    }

    // ── Periode & baris ───────────────────────────────────────────────────

    /** @return array{0:string,1:string} [hari pertama, hari terakhir] bulan itu */
    public static function periodBounds(int $year, int $month): array
    {
        $first = sprintf('%04d-%02d-01', $year, $month);

        return [$first, date('Y-m-t', strtotime($first))];
    }

    /**
     * Rapikan dan periksa baris dari formulir. Jam dihitung dari jam mulai–selesai bila keduanya diisi, selain itu dari isian jam.
     * Baris yang seluruhnya kosong dibuang. Satu hari tidak boleh lebih dari 24 jam (jumlah semua baris hari itu).
     *
     * @param  array<int,mixed>  $raw
     * @return array{0:array<int,array<string,mixed>>,1:array<int,string>}  [baris bersih, galat]
     */
    public static function normalizeEntries(array $raw, string $periodStart, string $periodEnd, ?string $contractStart, ?string $contractEnd, string $today, ?string $earliest = null, array $keepDates = [], ?array $knownProjects = null): array
    {
        $clean = [];
        $errors = [];
        $perDay = [];
        $n = 0;

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Nilai bukan teks/angka (mis. entries[0][activity][]=x) dianggap kosong — tidak boleh menjadi galat server.
            $str = fn ($v) => is_scalar($v) ? trim((string) $v) : '';
            $date = $str($row['work_date'] ?? '');
            $start = $str($row['start_time'] ?? '');
            $end = $str($row['end_time'] ?? '');
            $hoursIn = $str($row['hours'] ?? '');
            $activity = $str($row['activity'] ?? '');
            $project = $str($row['project_label'] ?? '');
            if ($date === '' && $start === '' && $end === '' && $hoursIn === '' && $activity === '' && $project === '') {
                continue;
            }
            $n++;
            $label = "Row {$n}";

            $d = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) {
                $errors[] = "{$label}: enter a valid date.";
                continue;
            }
            if ($date < $periodStart || $date > $periodEnd) {
                $errors[] = "{$label}: the date must be inside the timesheet month.";
                continue;
            }
            if (($contractStart && $date < substr($contractStart, 0, 10)) || ($contractEnd && $date > substr($contractEnd, 0, 10))) {
                $errors[] = "{$label}: the date is outside the contract period.";
                continue;
            }
            if ($date > $today) {
                $errors[] = "{$label}: a future date cannot be logged.";
                continue;
            }
            // Batas mundur hanya untuk TANGGAL BARU: tanggal yang sudah tersimpan di lembar ini tetap boleh disimpan ulang.
            if ($earliest !== null && $date < $earliest && !in_array($date, $keepDates, true)) {
                $errors[] = "{$label}: the date is older than the allowed back-fill period.";
                continue;
            }

            if ($start !== '' || $end !== '') {
                $hours = ExternalBilling::hoursBetween($start, $end);
                if ($hours === null) {
                    $errors[] = "{$label}: the end time must be after the start time.";
                    continue;
                }
            } else {
                $hours = $hoursIn === '' ? null : ContractRules::parseAmount($hoursIn);
                if ($hours === null || $hours <= 0 || $hours > 24) {
                    $errors[] = "{$label}: enter the start and end time, or hours between 0 and 24.";
                    continue;
                }
                $hours = round($hours, 2);
            }

            if ($activity === '') {
                $errors[] = "{$label}: describe the activity.";
                continue;
            }
            $location = strtoupper($str($row['location'] ?? ''));
            if ($location !== '' && !isset(self::LOCATIONS[$location])) {
                $errors[] = "{$label}: choose Onsite, Remote or Hybrid.";
                continue;
            }

            $perDay[$date] = ($perDay[$date] ?? 0) + $hours;
            if ($perDay[$date] > 24) {
                $errors[] = "{$label}: more than 24 hours on {$date}.";
                continue;
            }

            $clean[] = [
                'work_date' => $date,
                'project_id' => ctype_digit($str($row['project_id'] ?? '')) && ($knownProjects === null || in_array((int) $str($row['project_id']), $knownProjects, true)) ? (int) $str($row['project_id']) : null,
                'project_label' => $project === '' ? null : mb_substr($project, 0, 255),
                'start_time' => $start === '' ? null : $start,
                'end_time' => $end === '' ? null : $end,
                'hours' => $hours,
                'location' => $location === '' ? null : $location,
                'activity' => mb_substr($activity, 0, 500),
            ];
        }

        usort($clean, fn ($a, $b) => [$a['work_date'], $a['start_time']] <=> [$b['work_date'], $b['start_time']]);

        return [$clean, $errors];
    }
}
