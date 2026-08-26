<?php

namespace Tests\Unit\Overtime;

use App\Services\Overtime\OvertimeRateService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit OvertimeRateService.
 *
 * Sengaja memakai PHPUnit\Framework\TestCase polos, BUKAN Tests\TestCase milik
 * Laravel: service ini tidak menyentuh database maupun container. Itu justru
 * intinya — aturan pengali lembur dapat diuji lengkap SEKARANG meski data gaji
 * belum ada di sistem.
 *
 * Beberapa kasus di bawah diambil dari sistem acuan (Ferosoft) dan dipakai
 * sebagai patokan kebenaran: bila angkanya cocok, penafsiran kita atas
 * PP 35/2021 sama dengan sistem yang sudah berjalan.
 */
class OvertimeRateServiceTest extends TestCase
{
    private OvertimeRateService $rates;

    /** Upah sejam pada contoh acuan: Rp 5.000.000 / 173. */
    private const REFERENCE_HOURLY = 5000000 / 173;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rates = new OvertimeRateService();
    }

    // ── Upah sejam ──────────────────────────────────────────────────────────

    public function test_upah_sejam_memakai_pembagi_173(): void
    {
        $this->assertEqualsWithDelta(
            28901.73,
            $this->rates->hourlyRateFromMonthly(5_000_000),
            0.01
        );
    }

    public function test_upah_sebulan_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->rates->hourlyRateFromMonthly(-1);
    }

    // ── Hari kerja ──────────────────────────────────────────────────────────

    public function test_hari_kerja_satu_jam_memakai_pengali_1_5(): void
    {
        $result = $this->rates->calculate(60, OvertimeRateService::DAY_WORKDAY);

        $this->assertSame(1.5, $result['weighted_hours']);
        $this->assertCount(1, $result['segments']);
        $this->assertSame(1.5, $result['segments'][0]['multiplier']);
    }

    public function test_hari_kerja_tiga_jam_menghasilkan_5_5_jam_terbobot(): void
    {
        // 1 jam x 1,5  +  2 jam x 2  =  5,5
        $result = $this->rates->calculate(180, OvertimeRateService::DAY_WORKDAY);

        $this->assertSame(5.5, $result['weighted_hours']);
        $this->assertCount(2, $result['segments']);
    }

    public function test_hari_kerja_cocok_dengan_nominal_sistem_acuan(): void
    {
        // Acuan: 19 Agu 2026 (Rabu), 17:00-20:00 = 3 jam, Rp 158.960
        $result = $this->rates->calculate(
            180,
            OvertimeRateService::DAY_WORKDAY,
            self::REFERENCE_HOURLY
        );

        $this->assertEqualsWithDelta(158_960, $result['amount'], 50);
    }

    public function test_hari_kerja_menit_pecahan_dihitung_proporsional(): void
    {
        // Acuan: 20 Agu 2026 (Kamis), 18:00-23:59 = 359 menit
        // 1 jam x 1,5  +  299 menit (4,9833 jam) x 2  =  11,4667
        $result = $this->rates->calculate(359, OvertimeRateService::DAY_WORKDAY);

        $this->assertEqualsWithDelta(11.4667, $result['weighted_hours'], 0.001);
    }

    public function test_hari_kerja_kurang_dari_satu_jam_tidak_naik_ke_pengali_2(): void
    {
        $result = $this->rates->calculate(30, OvertimeRateService::DAY_WORKDAY);

        $this->assertSame(0.75, $result['weighted_hours']); // 0,5 jam x 1,5
        $this->assertCount(1, $result['segments']);
    }

    // ── Hari libur ──────────────────────────────────────────────────────────

    public function test_hari_libur_delapan_jam_pertama_memakai_pengali_2(): void
    {
        $result = $this->rates->calculate(480, OvertimeRateService::DAY_PUBLIC_HOLIDAY);

        $this->assertSame(16.0, $result['weighted_hours']);
        $this->assertCount(1, $result['segments']);
    }

    public function test_hari_libur_cocok_dengan_nominal_sistem_acuan(): void
    {
        // Acuan: 02 Agu 2026 (Minggu), 17:00-23:59 = 419 menit, Rp 403.468
        $result = $this->rates->calculate(
            419,
            OvertimeRateService::DAY_WEEKEND,
            self::REFERENCE_HOURLY
        );

        $this->assertEqualsWithDelta(403_468, $result['amount'], 250);
    }

    public function test_hari_libur_jam_kesembilan_memakai_pengali_3(): void
    {
        // 8 jam x 2  +  1 jam x 3  =  19
        $result = $this->rates->calculate(540, OvertimeRateService::DAY_WEEKEND);

        $this->assertSame(19.0, $result['weighted_hours']);
        $this->assertCount(2, $result['segments']);
        $this->assertSame(3.0, $result['segments'][1]['multiplier']);
    }

    public function test_hari_libur_jam_kesepuluh_dan_seterusnya_memakai_pengali_4(): void
    {
        // 8 jam x 2  +  1 jam x 3  +  2 jam x 4  =  27
        $result = $this->rates->calculate(660, OvertimeRateService::DAY_PUBLIC_HOLIDAY);

        $this->assertSame(27.0, $result['weighted_hours']);
        $this->assertCount(3, $result['segments']);
        $this->assertSame(4.0, $result['segments'][2]['multiplier']);
    }

    public function test_pola_enam_hari_kerja_menggeser_batas_satu_jam_lebih_awal(): void
    {
        // Pola 6 hari: 7 jam x 2  +  1 jam x 3  =  17
        $result = $this->rates->calculate(
            480,
            OvertimeRateService::DAY_WEEKEND,
            null,
            6
        );

        $this->assertSame(17.0, $result['weighted_hours']);
    }

    public function test_akhir_pekan_dan_libur_resmi_diperlakukan_sama(): void
    {
        $weekend = $this->rates->calculate(300, OvertimeRateService::DAY_WEEKEND);
        $holiday = $this->rates->calculate(300, OvertimeRateService::DAY_PUBLIC_HOLIDAY);

        $this->assertSame($weekend['weighted_hours'], $holiday['weighted_hours']);
    }

    // ── Nominal ─────────────────────────────────────────────────────────────

    public function test_tanpa_tarif_nominal_dikembalikan_null(): void
    {
        $result = $this->rates->calculate(180, OvertimeRateService::DAY_WORKDAY);

        $this->assertNull($result['amount']);
        $this->assertNull($result['hourly_rate']);
        $this->assertNull($result['segments'][0]['amount']);
    }

    public function test_nominal_adalah_jam_terbobot_dikali_tarif(): void
    {
        $result = $this->rates->calculate(180, OvertimeRateService::DAY_WORKDAY, 10_000);

        $this->assertSame(55_000.0, $result['amount']); // 5,5 x 10.000
    }

    public function test_jumlah_nominal_segmen_sama_dengan_nominal_total(): void
    {
        $result = $this->rates->calculate(660, OvertimeRateService::DAY_PUBLIC_HOLIDAY, 12_345.67);

        $sum = array_sum(array_column($result['segments'], 'amount'));

        $this->assertEqualsWithDelta($result['amount'], $sum, 0.05);
    }

    public function test_tarif_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->rates->calculate(60, OvertimeRateService::DAY_WORKDAY, -1);
    }

    // ── Batas & masukan janggal ─────────────────────────────────────────────

    public function test_durasi_nol_menghasilkan_nol_tanpa_error(): void
    {
        $result = $this->rates->calculate(0, OvertimeRateService::DAY_WORKDAY, 10_000);

        $this->assertSame(0.0, $result['weighted_hours']);
        $this->assertSame(0.0, $result['amount']);
        $this->assertSame([], $result['segments']);
    }

    public function test_durasi_negatif_diperlakukan_sebagai_nol(): void
    {
        $result = $this->rates->calculate(-120, OvertimeRateService::DAY_WORKDAY);

        $this->assertSame(0, $result['duration_minutes']);
        $this->assertSame(0.0, $result['weighted_hours']);
    }

    public function test_jenis_hari_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->rates->calculate(60, 'hari_kejepit');
    }

    public function test_seluruh_menit_selalu_habis_terbagi_ke_segmen(): void
    {
        foreach ([1, 59, 60, 61, 419, 480, 540, 1439] as $minutes) {
            foreach (OvertimeRateService::dayTypes() as $dayType) {
                $result = $this->rates->calculate($minutes, $dayType);

                $this->assertSame(
                    $minutes,
                    array_sum(array_column($result['segments'], 'minutes')),
                    "Menit tidak habis terbagi untuk {$minutes} menit pada {$dayType}"
                );
            }
        }
    }

    public function test_jam_terbobot_tidak_pernah_lebih_kecil_dari_jam_sebenarnya(): void
    {
        // Pengali terkecil adalah 1,5 — jadi hasil terbobot selalu lebih besar.
        foreach ([30, 120, 480, 900] as $minutes) {
            foreach (OvertimeRateService::dayTypes() as $dayType) {
                $result = $this->rates->calculate($minutes, $dayType);

                $this->assertGreaterThanOrEqual(
                    $result['total_hours'],
                    $result['weighted_hours']
                );
            }
        }
    }
}
