<?php

namespace App\Services\HrProfile;

/**
 * Status kepegawaian (keputusan M6 = A: kolom + riwayat; HC-D30).
 *
 * MURNI: tanpa database/router, sehingga dapat diuji sendiri. Daftar nilai dibatasi di sini
 * (bukan ENUM DB) supaya dapat bertambah tanpa migrasi.
 *
 * Aturan yang divalidasi sekarang hanya yang tak terbantahkan: status Probation wajib punya
 * tanggal akhir probation. Aturan hukum (mis. batas masa percobaan) SENGAJA belum dikunci —
 * menunggu konfirmasi HR/legal; keselarasan dengan kontrak aktif menunggu modul Kontrak.
 */
class EmploymentStatus
{
    public const PROBATION  = 'probation';
    public const CONTRACT   = 'contract';    // PKWT
    public const PERMANENT  = 'permanent';   // PKWTT
    public const INTERNSHIP = 'internship';
    public const CONSULTANT = 'consultant';  // untuk karyawan External

    /** @return array<string,string> nilai => label tampilan (UI berbahasa Inggris) */
    public static function options(): array
    {
        return [
            self::PROBATION  => 'Probation',
            self::CONTRACT   => 'Contract (PKWT)',
            self::PERMANENT  => 'Permanent (PKWTT)',
            self::INTERNSHIP => 'Internship',
            self::CONSULTANT => 'Consultant',
        ];
    }

    public static function isValid(?string $status): bool
    {
        return $status !== null && array_key_exists($status, self::options());
    }

    public static function label(?string $status): string
    {
        return self::options()[$status] ?? '—';
    }

    /**
     * Kesalahan validasi (kosong = valid). Status boleh kosong (belum diisi).
     *
     * @return array<string,string> field => pesan
     */
    public static function validate(?string $status, ?string $probationEnd): array
    {
        $errors = [];

        if ($status === null || $status === '') {
            return $errors;
        }

        if (!self::isValid($status)) {
            $errors['employment_status'] = 'Choose a valid employment status.';
            return $errors;
        }

        if ($status === self::PROBATION && ($probationEnd === null || trim($probationEnd) === '')) {
            $errors['probation_end_date'] = 'Probation end date is required for the Probation status.';
        }

        return $errors;
    }

    /** Deskripsi perubahan untuk riwayat karyawan (employee_history). */
    public static function changeDescription(?string $from, ?string $to, ?string $reason): string
    {
        $text = 'Employment status: ' . self::label($from) . ' → ' . self::label($to);

        return ($reason !== null && trim($reason) !== '') ? $text . '. Reason: ' . trim($reason) : $text;
    }
}
