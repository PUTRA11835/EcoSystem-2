<?php

namespace App\Services\HrProfile;

/**
 * Kunci profil setelah onboarding penuh (HC-D29). MURNI: tanpa database/router.
 *
 * Alur yang diputuskan pemilik:
 *  - Selama onboarding BELUM penuh, pemilik bebas mengubah seksi datanya.
 *  - Setelah progres 100%, HR menekan "Verify & Lock" (eksplisit, tercatat). Sebelum HR menekan,
 *    TIDAK ada yang terkunci — jadi rilis fitur ini tidak mengubah perilaku pengguna yang sudah live.
 *  - Setelah terkunci, pemilik tidak dapat mengubah seksi yang dinilai Onboarding. HR/admin
 *    "Unlock" per karyawan dengan alasan wajib (tercatat); setelah diperbaiki HR mengunci lagi.
 *
 * Yang dikunci = seksi yang DINILAI Onboarding (config/hc_onboarding.php) ditambah `hr_profile`.
 * Seksi lain (family, education, qualification, payment, attachment, contract) tetap seperti
 * sekarang. HR/admin tidak pernah tertahan kunci ini: kunci hanya berlaku untuk perubahan oleh
 * pemilik atas profilnya sendiri.
 */
class ProfileLockPolicy
{
    /** Kunci seksi (gaya PROFILE_SECTIONS: garis bawah) yang terkunci bagi pemilik. */
    public const LOCKED_SECTIONS = ['basic_data', 'address', 'identification', 'bank', 'hr_profile'];

    /** Hanya progres PENUH yang boleh dikunci (mencegah mengunci data yang masih kosong). */
    public static function canLock(?string $onboardingStatus): bool
    {
        return $onboardingStatus === 'complete';
    }

    /**
     * Apakah pemilik dilarang mengubah seksi ini?
     *
     * @param  bool    $locked      profil terkunci (locked_at terisi)
     * @param  string  $sectionKey  kunci seksi, garis bawah atau tanda hubung
     */
    public static function blocksOwnerUpdate(bool $locked, string $sectionKey): bool
    {
        if (!$locked) {
            return false;
        }

        return in_array(str_replace('-', '_', $sectionKey), self::LOCKED_SECTIONS, true);
    }

    /** Alasan wajib saat membuka kunci (tercatat di riwayat). Minimal 5 karakter bermakna. */
    public static function validUnlockReason(?string $reason): bool
    {
        return $reason !== null && mb_strlen(trim($reason)) >= 5;
    }

    /** Deskripsi untuk riwayat karyawan (employee_history). */
    public static function historyDescription(string $action, ?string $reason = null): string
    {
        return match ($action) {
            'lock'   => 'Profile verified and locked by HR',
            'unlock' => 'Profile unlocked by HR. Reason: ' . trim((string) $reason),
            default  => $action,
        };
    }
}
