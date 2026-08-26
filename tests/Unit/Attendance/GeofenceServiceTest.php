<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance\AttendanceRecord;
use App\Services\Attendance\GeofenceService;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit GeofenceService.
 *
 * Sengaja memakai PHPUnit\Framework\TestCase polos, BUKAN Tests\TestCase milik
 * Laravel: service ini tidak menyentuh database maupun container, jadi tesnya
 * tidak perlu mem-boot aplikasi. Hasilnya jauh lebih cepat dan tidak bisa
 * gagal karena sebab di luar dirinya.
 */
class GeofenceServiceTest extends TestCase
{
    private GeofenceService $geofence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geofence = new GeofenceService();
    }

    /** Titik acuan: kantor contoh di Jakarta Selatan. */
    private function office(int $id = 1, int $radius = 100): array
    {
        return [
            'type'          => 'office',
            'id'            => $id,
            'latitude'      => -6.23807410,
            'longitude'     => 106.78974070,
            'radius_meters' => $radius,
        ];
    }

    // ── distanceMeters() ────────────────────────────────────────────────────

    public function test_jarak_dua_titik_identik_adalah_nol(): void
    {
        $this->assertSame(
            0.0,
            $this->geofence->distanceMeters(-6.2, 106.8, -6.2, 106.8)
        );
    }

    public function test_satu_derajat_lintang_kira_kira_111_km(): void
    {
        $distance = $this->geofence->distanceMeters(0.0, 0.0, 1.0, 0.0);

        // 1 derajat lintang = 111.194,9 m. Toleransi 0,5%.
        $this->assertEqualsWithDelta(111194.9, $distance, 111194.9 * 0.005);
    }

    public function test_jarak_jakarta_ke_bandung_kira_kira_120_km(): void
    {
        // Monas -> Gedung Sate
        $distance = $this->geofence->distanceMeters(
            -6.175392, 106.827153,
            -6.902478, 107.618904
        );

        $this->assertEqualsWithDelta(120000, $distance, 120000 * 0.02);
    }

    public function test_jarak_positif_di_belahan_selatan_dan_barat(): void
    {
        $distance = $this->geofence->distanceMeters(-33.8688, -70.6693, -34.6037, -58.3816);

        $this->assertGreaterThan(0, $distance);
    }

    public function test_jarak_simetris_bolak_balik(): void
    {
        $a = $this->geofence->distanceMeters(-6.2, 106.8, -7.29, 112.72);
        $b = $this->geofence->distanceMeters(-7.29, 112.72, -6.2, 106.8);

        $this->assertEqualsWithDelta($a, $b, 0.001);
    }

    // ── evaluate(): di dalam / di luar ──────────────────────────────────────

    public function test_titik_persis_di_kantor_dinyatakan_di_dalam_radius(): void
    {
        $result = $this->geofence->evaluate(-6.23807410, 106.78974070, 10.0, [$this->office()]);

        $this->assertSame(AttendanceRecord::MATCH_OFFICE_IN, $result['match_type']);
        $this->assertSame(1, $result['branch_id']);
        $this->assertNull($result['project_site_id']);
        $this->assertSame(0.0, $result['distance_m']);
        $this->assertSame([], $result['flags']);
    }

    public function test_titik_dalam_radius_dinyatakan_di_dalam(): void
    {
        // ~55 m di utara kantor (0,0005 derajat lintang = ~55,6 m)
        $result = $this->geofence->evaluate(-6.23757410, 106.78974070, 10.0, [$this->office()]);

        $this->assertSame(AttendanceRecord::MATCH_OFFICE_IN, $result['match_type']);
        $this->assertLessThan(100, $result['distance_m']);
    }

    public function test_jarak_tepat_sama_dengan_radius_dihitung_masuk(): void
    {
        // Satu-satunya jarak yang dapat direpresentasikan PERSIS sama dengan
        // radius bertipe integer adalah nol. Kasus ini menguji operator
        // pembandingnya: <= (inklusif), bukan < (eksklusif).
        $result = $this->geofence->evaluate(
            -6.23807410, 106.78974070, 10.0,
            [$this->office(1, 0)]
        );

        $this->assertSame(0.0, $result['distance_m']);
        $this->assertSame(
            AttendanceRecord::MATCH_OFFICE_IN,
            $result['match_type'],
            'Jarak tepat di garis radius harus dihitung MASUK, bukan di luar.'
        );
    }

    public function test_batas_radius_terletak_di_antara_floor_dan_ceil_jarak(): void
    {
        // Menjepit batasnya dari dua sisi: dengan radius dibulatkan ke ATAS
        // titik harus masuk, dengan dibulatkan ke BAWAH harus di luar.
        $lat      = -6.23757410;
        $distance = $this->geofence->distanceMeters(-6.23807410, 106.78974070, $lat, 106.78974070);

        $withCeil = $this->geofence->evaluate(
            $lat, 106.78974070, 10.0,
            [$this->office(1, (int) ceil($distance))]
        );

        $withFloor = $this->geofence->evaluate(
            $lat, 106.78974070, 10.0,
            [$this->office(1, (int) floor($distance))]
        );

        $this->assertSame(AttendanceRecord::MATCH_OFFICE_IN, $withCeil['match_type']);
        $this->assertSame(AttendanceRecord::MATCH_OFFICE_OUT, $withFloor['match_type']);
    }

    public function test_titik_jauh_dinyatakan_di_luar_radius(): void
    {
        // ~1,1 km di utara kantor
        $result = $this->geofence->evaluate(-6.22807410, 106.78974070, 10.0, [$this->office()]);

        $this->assertSame(AttendanceRecord::MATCH_OFFICE_OUT, $result['match_type']);
        $this->assertTrue($this->geofence->isOutside($result));
        $this->assertGreaterThan(1000, $result['distance_m']);
    }

    // ── evaluate(): pemilihan lokasi terdekat ───────────────────────────────

    public function test_memilih_lokasi_terdekat_bukan_yang_pertama(): void
    {
        $candidates = [
            ['type' => 'office', 'id' => 10, 'latitude' => -7.29327450, 'longitude' => 112.72069670, 'radius_meters' => 100], // Surabaya
            ['type' => 'office', 'id' => 20, 'latitude' => -7.79558000, 'longitude' => 110.36949000, 'radius_meters' => 100], // Yogyakarta
            $this->office(30),                                                                                                // Jakarta
        ];

        $result = $this->geofence->evaluate(-6.23807410, 106.78974070, 10.0, $candidates);

        $this->assertSame(30, $result['branch_id'], 'Harus memilih cabang Jakarta, bukan yang pertama di daftar.');
        $this->assertSame(AttendanceRecord::MATCH_OFFICE_IN, $result['match_type']);
    }

    public function test_lokasi_proyek_lebih_dekat_mengalahkan_kantor(): void
    {
        $candidates = [
            $this->office(1),
            ['type' => 'project', 'id' => 7, 'latitude' => -6.16612900, 'longitude' => 106.72775300, 'radius_meters' => 150],
        ];

        // Titik presensi berada di lokasi proyek.
        $result = $this->geofence->evaluate(-6.16612900, 106.72775300, 12.0, $candidates);

        $this->assertSame(AttendanceRecord::MATCH_PROJECT_IN, $result['match_type']);
        $this->assertSame(7, $result['project_site_id']);
        $this->assertNull($result['branch_id'], 'Saat yang cocok adalah lokasi proyek, branch_id harus kosong.');
    }

    // ── evaluate(): kondisi tanpa lokasi ────────────────────────────────────

    public function test_koordinat_kosong_menghasilkan_none(): void
    {
        $result = $this->geofence->evaluate(null, null, null, [$this->office()]);

        $this->assertSame(AttendanceRecord::MATCH_NONE, $result['match_type']);
        $this->assertContains(AttendanceRecord::FLAG_NO_COORDINATES, $result['flags']);
        $this->assertNull($result['distance_m']);
        $this->assertTrue($this->geofence->isUnavailable($result));
    }

    public function test_tanpa_lokasi_pembanding_menghasilkan_none(): void
    {
        $result = $this->geofence->evaluate(-6.2, 106.8, 10.0, []);

        $this->assertSame(AttendanceRecord::MATCH_NONE, $result['match_type']);
        $this->assertContains(AttendanceRecord::FLAG_NO_LOCATION_SETUP, $result['flags']);
    }

    // ── evaluate(): flag ────────────────────────────────────────────────────

    public function test_akurasi_buruk_ditandai(): void
    {
        $result = $this->geofence->evaluate(-6.23807410, 106.78974070, 250.0, [$this->office()]);

        $this->assertContains(AttendanceRecord::FLAG_LOW_ACCURACY, $result['flags']);
        $this->assertSame(
            AttendanceRecord::MATCH_OFFICE_IN,
            $result['match_type'],
            'Akurasi buruk hanya menandai, tidak mengubah vonis.'
        );
    }

    public function test_akurasi_buruk_tetap_ditandai_walau_koordinat_kosong(): void
    {
        $result = $this->geofence->evaluate(null, null, 250.0, [$this->office()]);

        $this->assertContains(AttendanceRecord::FLAG_LOW_ACCURACY, $result['flags']);
        $this->assertContains(AttendanceRecord::FLAG_NO_COORDINATES, $result['flags']);
    }

    public function test_sangat_jauh_di_luar_radius_diberi_flag_tambahan(): void
    {
        // > 3x radius (100 m) dari kantor
        $result = $this->geofence->evaluate(-6.22807410, 106.78974070, 10.0, [$this->office()]);

        $this->assertContains(AttendanceRecord::FLAG_FAR_OUTSIDE, $result['flags']);
    }

    public function test_sedikit_di_luar_radius_tidak_diberi_flag_far_outside(): void
    {
        // ~111 m dari kantor: di luar radius 100 m, tapi belum 3x lipat.
        $result = $this->geofence->evaluate(-6.23707410, 106.78974070, 10.0, [$this->office()]);

        $this->assertSame(AttendanceRecord::MATCH_OFFICE_OUT, $result['match_type']);
        $this->assertNotContains(AttendanceRecord::FLAG_FAR_OUTSIDE, $result['flags']);
    }

    public function test_radius_nol_tidak_menyebabkan_error(): void
    {
        $result = $this->geofence->evaluate(-6.23757410, 106.78974070, 10.0, [$this->office(1, 0)]);

        $this->assertSame(AttendanceRecord::MATCH_OFFICE_OUT, $result['match_type']);
        $this->assertIsFloat($result['distance_m']);
    }
}
