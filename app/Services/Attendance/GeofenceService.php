<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecord;

/**
 * Perhitungan jarak dan vonis geofence.
 *
 * SENGAJA MURNI: kelas ini tidak menyentuh database, session, request, maupun
 * waktu. Seluruh masukannya diberikan pemanggil sebagai array biasa. Itu yang
 * membuatnya dapat di-unit-test tanpa database — dan pada basis kode yang
 * cakupan tesnya nyaris nol, bagian inilah yang paling murah sekaligus paling
 * penting untuk diuji, karena kesalahan di sini merusak SELURUH catatan
 * presensi secara diam-diam.
 *
 * Kenapa Haversine di PHP, bukan ST_Distance_Sphere() di SQL:
 * fungsi SQL-nya ternyata TERSEDIA di MariaDB 10.4.32 (sudah diuji), jadi ini
 * pilihan sadar. Alasannya jumlah lokasi pembanding per presensi hanya
 * segelintir sehingga indeks spasial tidak relevan, hasilnya dapat diuji tanpa
 * database, dan implementasinya tidak terikat versi maupun jenis DB.
 *
 * PENTING: kelas ini TIDAK PERNAH menolak presensi. Ia hanya melaporkan
 * jaraknya. Keputusan menolak atau menerima ada di AttendanceService,
 * bergantung `geofence_mode`. Pemisahan ini disengaja supaya mengubah
 * kebijakan perusahaan tidak menyentuh kode perhitungan.
 */
class GeofenceService
{
    /**
     * Jari-jari rata-rata Bumi dalam meter (IUGG mean radius).
     * Dengan nilai ini, 1 derajat lintang = 111.194,9 m.
     */
    private const EARTH_RADIUS_M = 6371008.8;

    /** Kelipatan radius yang dianggap "jauh sekali di luar area". */
    private const FAR_OUTSIDE_MULTIPLIER = 3;

    /**
     * Jarak dua titik di permukaan Bumi, dalam METER (rumus Haversine).
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latFrom = deg2rad($lat1);
        $latTo   = deg2rad($lat2);
        $latDiff = $latTo - $latFrom;
        $lngDiff = deg2rad($lng2) - deg2rad($lng1);

        $a = sin($latDiff / 2) ** 2
           + cos($latFrom) * cos($latTo) * sin($lngDiff / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Evaluasi satu titik terhadap daftar lokasi pembanding.
     *
     * @param  float|null  $lat        NULL bila browser tidak memberi lokasi
     * @param  float|null  $lng
     * @param  float|null  $accuracy   perkiraan galat dalam meter dari browser
     * @param  array<int, array{type:string, id:int, latitude:float|string, longitude:float|string, radius_meters:int}>  $candidates
     * @param  int  $minAccuracyMeters ambang untuk menandai akurasi buruk
     *
     * @return array{
     *     match_type: string,
     *     branch_id: int|null,
     *     project_site_id: int|null,
     *     distance_m: float|null,
     *     radius_m: int|null,
     *     flags: string[]
     * }
     */
    public function evaluate(
        ?float $lat,
        ?float $lng,
        ?float $accuracy,
        array $candidates,
        int $minAccuracyMeters = 100
    ): array {
        $flags = [];

        // Akurasi ditandai lebih dulu supaya sinyalnya tetap tercatat walau
        // langkah berikutnya berujung "tidak ada lokasi pembanding".
        if ($accuracy !== null && $accuracy > $minAccuracyMeters) {
            $flags[] = AttendanceRecord::FLAG_LOW_ACCURACY;
        }

        if ($lat === null || $lng === null) {
            $flags[] = AttendanceRecord::FLAG_NO_COORDINATES;

            return $this->emptyResult($flags);
        }

        if (empty($candidates)) {
            $flags[] = AttendanceRecord::FLAG_NO_LOCATION_SETUP;

            return $this->emptyResult($flags);
        }

        $nearest         = null;
        $nearestDistance = null;

        foreach ($candidates as $candidate) {
            $distance = $this->distanceMeters(
                $lat,
                $lng,
                (float) $candidate['latitude'],
                (float) $candidate['longitude']
            );

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest         = $candidate;
            }
        }

        $radius = (int) $nearest['radius_meters'];
        $isProject = ($nearest['type'] ?? 'office') === 'project';

        // Batas inklusif: tepat di garis radius dihitung MASUK. Karyawan yang
        // persis di batas tidak boleh dirugikan oleh pembulatan.
        $inside = $nearestDistance <= $radius;

        if (!$inside && $nearestDistance > $radius * self::FAR_OUTSIDE_MULTIPLIER) {
            $flags[] = AttendanceRecord::FLAG_FAR_OUTSIDE;
        }

        $matchType = match (true) {
            $isProject && $inside  => AttendanceRecord::MATCH_PROJECT_IN,
            $isProject && !$inside => AttendanceRecord::MATCH_PROJECT_OUT,
            $inside                => AttendanceRecord::MATCH_OFFICE_IN,
            default                => AttendanceRecord::MATCH_OFFICE_OUT,
        };

        return [
            'match_type'      => $matchType,
            'branch_id'       => $isProject ? null : (int) $nearest['id'],
            'project_site_id' => $isProject ? (int) $nearest['id'] : null,
            'distance_m'      => round($nearestDistance, 2),
            'radius_m'        => $radius,
            'flags'           => $flags,
        ];
    }

    /**
     * Apakah hasil evaluasi berarti "di luar radius"?
     * Dipakai AttendanceService untuk memutuskan penolakan pada mode enforce.
     */
    public function isOutside(array $result): bool
    {
        return in_array($result['match_type'], [
            AttendanceRecord::MATCH_OFFICE_OUT,
            AttendanceRecord::MATCH_PROJECT_OUT,
        ], true);
    }

    /** Apakah lokasi sama sekali tidak dapat ditentukan? */
    public function isUnavailable(array $result): bool
    {
        return $result['match_type'] === AttendanceRecord::MATCH_NONE;
    }

    /**
     * @param  string[]  $flags
     * @return array{match_type:string, branch_id:null, project_site_id:null, distance_m:null, radius_m:null, flags:string[]}
     */
    private function emptyResult(array $flags): array
    {
        return [
            'match_type'      => AttendanceRecord::MATCH_NONE,
            'branch_id'       => null,
            'project_site_id' => null,
            'distance_m'      => null,
            'radius_m'        => null,
            'flags'           => $flags,
        ];
    }
}
