<?php

namespace App\Support\Contracts;

/**
 * Daftar placeholder teks kontrak, per jenis. Kunci memakai bahasa Indonesia karena dokumennya berbahasa Indonesia
 * (sama dengan ESH); label penjelasnya berbahasa Inggris karena tampil di UI.
 */
class ContractPlaceholders
{
    /** Kunci → penjelasan, untuk PKWT / PKWTT (karyawan). */
    public const EMPLOYEE = [
        'nomor_kontrak'        => 'Contract number',
        'jenis_kontrak'        => 'Contract type',
        'nama_karyawan'        => 'Employee full name',
        'nik'                  => 'National ID (NIK)',
        'tempat_tanggal_lahir' => 'Place and date of birth',
        'jenis_kelamin'        => 'Gender',
        'alamat'               => 'Domicile address',
        'npwp'                 => 'Tax ID (NPWP)',
        'posisi'               => 'Position',
        'jabatan'              => 'Job title (same as position)',
        'departemen'           => 'Department',
        'lokasi_kerja'         => 'Work location',
        'tanggal_mulai'        => 'Start date',
        'tanggal_berakhir'     => 'End date',
        'gaji'                 => 'Total salary',
        'gaji_pokok'           => 'Basic salary',
        'tunjangan'            => 'Total allowances',
        'rincian_gaji'         => 'Salary breakdown (basic + allowances)',
        'perusahaan'           => 'Company name',
        'kota_penandatanganan' => 'Signing city',
        'tanggal_tanda_tangan' => 'Signing date (weekday, date)',
        'tanggal_surat'        => 'Document date (07 Oktober 2026)',
        'penandatangan'        => 'Company signatory name',
        'jabatan_penandatangan' => 'Company signatory title',
        'tanda_tangan_penandatangan' => 'Company signatory signature image (from My Profile; shown once the contract is not a Draft)',
        'tanda_tangan_karyawan' => 'Employee signature image (from My Profile; shown once the contract is not a Draft)',
        'meterai'              => 'Stamp-duty (meterai) box, aligned left — where the stamp is stuck',
        'meterai_tengah'       => 'Stamp-duty box, centred',
        'meterai_kanan'        => 'Stamp-duty box, aligned right',
    ];

    /** Kunci → penjelasan, untuk konsultan eksternal. */
    public const EXTERNAL = [
        'nomor_kontrak'        => 'Contract number',
        'nama_konsultan'       => 'Consultant full name',
        'nomor_identitas'      => 'ID number (NIK)',
        'tempat_tanggal_lahir' => 'Place and date of birth',
        'jenis_kelamin'        => 'Gender',
        'npwp'                 => 'Tax ID (NPWP)',
        'vendor'               => 'Vendor / partner',
        'perusahaan_prinsipal' => 'Principal (client) company',
        'spesialisasi'         => 'Specialisation / role',
        'penugasan'            => 'Assignment',
        'skema'                => 'Engagement scheme',
        'alamat'               => 'Address',
        'tanggal_mulai'        => 'Start date',
        'tanggal_berakhir'     => 'End date',
        'tanggal_tanda_tangan' => 'Signing date (weekday, date)',
        'tarif'                => 'Rate',
        'satuan_tagihan'       => 'Billing unit',
        'volume_kerja'         => 'Work volume (set on the contract)',
        'lokasi_kerja'         => 'Project model / work location',
        'tanggal_surat'        => 'Document date (07 Oktober 2026)',
        'catatan'              => 'Notes',
        'perusahaan'           => 'Company name',
        'kota_penandatanganan' => 'Signing city',
        'penandatangan'        => 'Company signatory name',
        'jabatan_penandatangan' => 'Company signatory title',
        'tanda_tangan_penandatangan' => 'Company signatory signature image (from My Profile; shown once the contract is not a Draft)',
        'tanda_tangan_karyawan' => 'Consultant signature image (from My Profile; shown once the contract is not a Draft)',
        'meterai'              => 'Stamp-duty (meterai) box, aligned left — where the stamp is stuck',
        'meterai_tengah'       => 'Stamp-duty box, centred',
        'meterai_kanan'        => 'Stamp-duty box, aligned right',
    ];

    /** @return array<string,string> */
    public static function forType(string $type): array
    {
        return $type === ContractRules::TYPE_EXTERNAL ? self::EXTERNAL : self::EMPLOYEE;
    }

    /** @return string[] */
    public static function keysFor(string $type): array
    {
        return array_keys(self::forType($type));
    }
}
