<?php

namespace App\Services\HrProfile;

/**
 * Siapa boleh mengisi field profil HR mana — DITEGAKKAN DI SERVER (bukan sekadar disembunyikan
 * di layar). MURNI: tanpa database/router.
 *
 * Peran:
 *  - self : pemilik profil (My Profile). Hanya data pribadi, dan hanya selama profil TIDAK terkunci
 *           (lihat ProfileLockPolicy — pemeriksaan kunci dilakukan pemanggil).
 *  - hr   : HR/admin yang punya izin `employee.section.hr_profile.update`.
 *
 * Field yang tak dikenal, field sistem (kunci, pengubah, path berkas) dan field payroll NONAKTIF
 * tidak pernah diterima lewat jalur ini — kunci mengembalikannya sebagai `rejected` agar pemanggil
 * dapat menolak permintaan (bukan diam-diam membuangnya) dan mencatat percobaannya.
 */
class HrProfileFieldPolicy
{
    /** Data pribadi yang boleh diubah pemilik (T1; berlaku selama profil belum dikunci). */
    public const SELF_EDITABLE = [
        'blood_type', 'mother_maiden_name',
        'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
    ];

    /** Kepegawaian: HR saja. */
    public const HR_ONLY = [
        'employment_status', 'grade_id', 'probation_end_date', 'hr_notes',
    ];

    /**
     * Payroll NONAKTIF (HC-D21): butuh izin `employee.section.compensation.update` (seksi Compensation, tab
     * Master → Employee). Tidak diterima lewat jalur profil HR biasa.
     */
    public const PAYROLL_DORMANT = [
        'ptkp_code', 'dependents_count', 'bpjs_health_active', 'bpjs_employment_active',
        'bpjs_dependents_count', 'payroll_activated',
    ];

    /** @return string[] field yang boleh diisi peran ini lewat jalur profil HR */
    public static function allowedFor(string $actor): array
    {
        return match ($actor) {
            'hr'    => array_merge(self::SELF_EDITABLE, self::HR_ONLY),
            'self'  => self::SELF_EDITABLE,
            default => [],
        };
    }

    /**
     * Pisahkan input menjadi yang diterima dan yang ditolak.
     *
     * @param  array<string,mixed>  $input
     * @return array{allowed: array<string,mixed>, rejected: string[]}
     */
    public static function filter(array $input, string $actor): array
    {
        $allowedKeys = self::allowedFor($actor);
        $allowed  = [];
        $rejected = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                $allowed[$key] = $value;
            } else {
                $rejected[] = (string) $key;
            }
        }

        return ['allowed' => $allowed, 'rejected' => $rejected];
    }

    /** Golongan darah yang sah (kosong = belum diisi). */
    public static function normalizeBloodType(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $v = strtoupper(trim($value));

        return in_array($v, ['A', 'B', 'AB', 'O'], true) ? $v : null;
    }
}
