<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pembaruan Register Dasar Hukum setelah PEMERIKSAAN DARING 19 Okt 2026 (pencarian web terhadap sumber sekunder: hukumonline, DDTC, DJP/pajak.go.id,
 * BPJS Ketenagakerjaan, media bisnis). NASKAH RESMI (JDIH/peraturan.go.id) belum dibuka — karena itu SEMUA baris tetap `needs_review`
 * sampai HR / Legal / Tax menandai Reviewed. Hanya baris yang masih `needs_review` yang diubah (ulasan manusia tidak pernah ditimpa);
 * perubahan angka dibuat sebagai VERSI baru, versi lama ditandai superseded (riwayat tetap).
 *
 * Temuan utama:
 *  - Batas upah Jaminan Pensiun naik jadi Rp11.086.300 sejak Maret 2026 (Rp10.547.400 sejak Maret 2025) — surat BPJS Ketenagakerjaan.
 *  - PP 6/2025 (berlaku 7 Feb 2025): iuran JKP 0,36% (0,22% pemerintah + 0,14% rekomposisi JKK); tarif JKK turun menjadi 0,10%–1,60%.
 *  - Biaya jabatan kini bersumber PMK 168/2023 Pasal 10 ayat (2) (5%, maks. Rp500.000/bulan atau Rp6 juta/tahun).
 *  - Pajak pesangon: PP 68/2009 jo. PMK 16/2010 Pasal 3 ayat (1) masih dipakai DJP setelah UU HPP.
 *  - Putusan MK 168/PUU-XXI/2023 (31 Okt 2024) mewajibkan UU Ketenagakerjaan baru paling lambat 31 Okt 2026; DPR dilaporkan mengesahkan UU
 *    Pelindungan Ketenagakerjaan pada 6 Okt 2026 (nomor/pengundangan belum diketahui, masa transisi 1 tahun) — dicatat sebagai butir
 *    PANTAUAN; kalkulator TIDAK diubah sebelum UU itu diundangkan dan berlaku.
 */
return new class extends Migration
{
    private const TAG = '[Dicek daring 19 Okt 2026] ';

    public function up(): void
    {
        $now = now();

        // ── Catatan/koreksi pada baris yang ada (hanya yang masih needs_review) ─────────────────────
        $patch = function (string $key, string $effective, array $set, ?string $appendNote = null) use ($now) {
            $row = DB::table('legal_basis_register')->where('rule_key', $key)->where('effective_date', $effective)->first();
            if (!$row || $row->status !== 'needs_review') {
                return;
            }
            if ($appendNote !== null) {
                $set['notes'] = trim(($row->notes ? $row->notes . ' ' : '') . self::TAG . $appendNote);
            }
            DB::table('legal_basis_register')->where('id', $row->id)->update($set + ['updated_at' => $now]);
        };

        $patch('pph21.ter', '2024-01-01', [], 'Dasar TER (PP 58/2023 + PMK 168/2023, berlaku 1 Jan 2024) sesuai sumber DJP/DDTC; tidak ditemukan perubahan untuk 2026. Stimulus PPh 21 DTP TA 2026 (PMK 105/2025, sektor tertentu, sampai 31 Des 2026) TIDAK dihitung oleh Payroll.');
        $patch('pph21.progressive', '2022-01-01', [], 'Tidak ada pencarian yang menunjukkan perubahan lapisan Pasal 17 setelah UU HPP.');
        $patch('pph21.ptkp', '2016-01-01', [], 'Angka PTKP Rp54 juta/tahun BELUM terkonfirmasi untuk 2026 dari sumber mana pun dalam pencarian — periksa Tax sebelum Reviewed.');
        $patch('pph21.occupational_cost', '2009-01-01', [
            'regulation_type' => 'PMK', 'number' => '168', 'year' => 2023,
            'article' => 'Pasal 10 ayat (2) PMK 168/2023: 5% dari penghasilan bruto, paling banyak Rp500.000 sebulan / Rp6.000.000 setahun (ketentuan semula PMK 250/PMK.03/2008)',
            'amended_by' => 'PMK 168/2023 mempertahankan ketentuan PMK 250/PMK.03/2008', 'last_amended_date' => '2024-01-01', 'confidence' => 'high',
        ], 'Dikonfirmasi DDTC/Kring Pajak (Des 2025) dan Talenta. Tanggal "terakhir diubah" = mulai berlaku PMK 168/2023.');
        $patch('pph21.non_npwp', '2009-01-01', [], 'Pasal 21 ayat (5a) UU PPh: tarif lebih tinggi 20% bagi yang tidak ber-NPWP; perlakuan sejak NPWP = NIK belum dicek ke naskah.');

        $patch('bpjs.kesehatan', '2020-07-01', [
            'amended_by' => 'Perpres 64/2020; Perpres 59/2024 (perubahan ketiga Perpres 82/2018)', 'last_amended_date' => '2024-05-08', 'confidence' => 'medium',
        ], 'Iuran pekerja penerima upah 5% (4% pemberi kerja + 1% pekerja) masih berlaku per Agustus 2026 menurut Metro TV. Batas upah Rp12 juta BELUM terverifikasi. Perpres 59/2024 membuka kenaikan iuran tiap dua tahun; rencana 2026 dilaporkan media tetapi belum ada penetapan. Tanggal Perpres 59/2024 dari pengetahuan penyusun, belum dicek naskah.');
        $patch('bpjs.jht', '2015-07-01', [], 'Tarif JHT 5,7% (3,7% + 2%) konsisten di semua sumber sekunder.');
        $patch('overtime.multipliers', '2004-06-25', [], 'Pembagi 173 dan pengali lembur dari Kepmenakertrans 102/2004; nomor pasal PP 35/2021 untuk lembur belum dicek ke naskah.');
        $patch('pkwt.max_term', '2021-02-02', [], 'PERHATIAN: UU Pelindungan Ketenagakerjaan yang dilaporkan disahkan DPR 6 Okt 2026 memuat PKWT maksimal 4 tahun (2 + 1 + 1) — baru berlaku setelah diundangkan + masa transisi 1 tahun; aturan 5 tahun ini tetap berlaku sampai saat itu. Lihat butir labor.new_law dan labor.mk_168.');

        $patch('offboarding.pkwt_compensation', '2021-02-02', [], 'Rumus kompensasi PKWT (Pasal 15–17) belum dikonfirmasi ke naskah PP.');
        $patch('offboarding.severance_table', '2021-02-02', [
            'amended_by' => 'UU 6/2023 (penetapan Perppu 2/2022); Putusan MK 168/PUU-XXI/2023 (31 Okt 2024) — UU Ketenagakerjaan baru wajib paling lambat 31 Okt 2026',
        ], 'Tabel uang pesangon (1–9 bulan) dan UPMK (2–10 bulan mulai 3 tahun) cocok dengan ringkasan hukumonline/Fortune/Kompas. UU baru dilaporkan menghapus pengali 0,5× sehingga minimum 1× — BELUM berlaku, jangan diubah sebelum diundangkan.');
        $patch('offboarding.reason_multipliers', '2021-02-02', [], 'Pensiun 1,75× + UPMK 1× (Pasal 56) dan pelanggaran perjanjian 0,5× (Pasal 52) terkonfirmasi sumber sekunder. BELUM terkonfirmasi: pembagian Pasal 43/44 (efisiensi karena rugi = 0,5× vs mencegah rugi = 1×; sumber saling berbeda) dan Pasal 57 (meninggal dunia 2×). Bila ragu, hitung manual lewat "Separation pay".');
        $patch('offboarding.severance_tax', '2009-01-01', [
            'article' => 'Pasal 2 PP 68/2009 dan Pasal 3 ayat (1) PMK 16/2010 (lapisan: sampai Rp50 jt 0%; >50–100 jt 5%; >100–500 jt 15%; >500 jt 25%)', 'confidence' => 'high',
        ], 'Lapisan 0/5/15/25% dikonfirmasi hukumonline, Klikpajak, DDTC; DJP menyatakan PP 68/2009 masih menjadi rujukan setelah UU HPP (selama belum diganti/dicabut). Pembayaran bertahap dalam 2 tahun kalender dianggap sekaligus. Pelaporan lewat e-Bupot Unifikasi Coretax sejak Jan 2025.');
        $patch('offboarding.leave_divisor', '2021-02-02', [], 'Pembagi upah harian 25 (6 hari kerja) / 21 (5 hari kerja) belum dikonfirmasi ke naskah — keyakinan tetap rendah.');

        // ── Versi baru: JP (batas upah) ────────────────────────────────────────────────────────────
        $base = DB::table('legal_basis_register')->where('rule_key', 'bpjs.jp')->where('effective_date', '2015-07-01')->first();
        $versions = [
            ['2025-03-01', 10547400, 'Batas upah JP sejak Maret 2025 Rp10.547.400 (surat BPJS Ketenagakerjaan 27 Feb 2025; PDB 2024 tumbuh 5,03%).'],
            ['2026-03-01', 11086300, 'Batas upah JP sejak Maret 2026 Rp11.086.300 (Rp10.547.400 × 1,0511 — pertumbuhan PDB 2025 5,11% menurut BPS; surat BPJS Ketenagakerjaan B/1226/022026 menurut sumber sekunder dan jawaban resmi akun @BPJSTKinfo). Satu pengguna melaporkan angka Rp11.086.372 — cocokkan dengan surat resmi.'],
        ];
        if ($base) {
            foreach ($versions as [$date, $cap, $note]) {
                DB::table('legal_basis_register')->insertOrIgnore([
                    'rule_key' => 'bpjs.jp', 'module' => 'bpjs', 'regulation_type' => 'PP', 'number' => '45', 'year' => 2015,
                    'title' => 'Jaminan Pensiun — iuran 3% (2% pemberi kerja + 1% pekerja) dengan batas upah tertinggi tahunan Rp' . number_format($cap, 0, ',', '.'),
                    'article' => 'PP 45/2015 (iuran JP); batas upah = batas tahun lalu × (1 + pertumbuhan PDB), ditetapkan BPJS Ketenagakerjaan setiap tahun',
                    'effective_date' => $date, 'last_amended_date' => $date, 'amended_by' => 'Surat penyesuaian batas upah BPJS Ketenagakerjaan', 'source_url' => null,
                    'status' => 'needs_review', 'confidence' => 'medium', 'payload' => json_encode(['employer' => 0.02, 'employee' => 0.01, 'wage_cap' => $cap]),
                    'notes' => self::TAG . $note, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('legal_basis_register')->where('rule_key', 'bpjs.jp')->whereIn('effective_date', ['2015-07-01', '2025-03-01'])->where('status', 'needs_review')->update(['status' => 'superseded', 'updated_at' => $now]);
        }

        // ── Versi baru: JKK/JKM sesuai PP 6/2025 + butir baru JKP ──────────────────────────────────
        if (DB::table('legal_basis_register')->where('rule_key', 'bpjs.jkk_jkm')->exists()) {
            DB::table('legal_basis_register')->insertOrIgnore([
                'rule_key' => 'bpjs.jkk_jkm', 'module' => 'bpjs', 'regulation_type' => 'PP', 'number' => '6', 'year' => 2025,
                'title' => 'Jaminan Kecelakaan Kerja (0,10%–1,60% sesuai kelompok risiko setelah rekomposisi) dan Jaminan Kematian (0,30%)',
                'article' => 'PP 44/2015 sebagaimana diubah PP 82/2019 dan PP 6/2025 (rekomposisi 0,14% iuran JKK ke JKP)', 'effective_date' => '2025-02-07',
                'last_amended_date' => '2025-02-07', 'amended_by' => 'PP 6/2025', 'source_url' => null, 'status' => 'needs_review', 'confidence' => 'medium',
                'payload' => json_encode(['jkk_min' => 0.001, 'jkk_max' => 0.016, 'jkm' => 0.003]),
                'notes' => self::TAG . 'Kisaran 0,10%–1,60% = kisaran lama 0,24%–1,74% dikurangi 0,14% yang dialihkan ke JKP; angka ini disimpulkan dari satu sumber sekunder (Dealls) dan perhitungan selisih — konfirmasi ke naskah PP 6/2025. PP 7/2025 memberi relaksasi iuran JKK 50% Feb–Jul 2025 untuk industri padat karya (sudah lewat).',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('legal_basis_register')->where('rule_key', 'bpjs.jkk_jkm')->where('effective_date', '2015-07-01')->where('status', 'needs_review')->update(['status' => 'superseded', 'updated_at' => $now]);
        }
        $newRows = [
            [
                'rule_key' => 'bpjs.jkp', 'module' => 'bpjs', 'regulation_type' => 'PP', 'number' => '6', 'year' => 2025,
                'title' => 'Jaminan Kehilangan Pekerjaan (JKP) — iuran 0,36% (0,22% pemerintah + 0,14% rekomposisi JKK); manfaat tunai 60% upah paling lama 6 bulan; batas upah Rp5.000.000',
                'article' => 'PP 37/2021 sebagaimana diubah PP 6/2025 (Pasal 11 ayat (2) iuran)', 'effective_date' => '2025-02-07', 'last_amended_date' => '2025-02-07',
                'amended_by' => 'PP 6/2025 (iuran turun dari 0,46% menjadi 0,36%)', 'confidence' => 'medium', 'payload' => ['total' => 0.0036, 'government' => 0.0022, 'jkk_recomposition' => 0.0014, 'wage_cap' => 5000000, 'benefit_rate' => 0.6, 'benefit_months' => 6],
                'notes' => 'Pekerja tidak membayar iuran JKP. PP 6/2025 ditandatangani dan berlaku 7 Feb 2025 (bisnis.com, BPJS Ketenagakerjaan).',
            ],
            [
                'rule_key' => 'labor.mk_168', 'module' => 'contract', 'regulation_type' => 'Putusan', 'number' => '168/PUU-XXI/2023', 'year' => 2024,
                'title' => 'Putusan Mahkamah Konstitusi tentang klaster ketenagakerjaan UU Cipta Kerja — perintah membentuk UU Ketenagakerjaan baru paling lambat 2 tahun (31 Okt 2026)',
                'article' => 'Amar putusan (diucapkan 31 Oktober 2024)', 'effective_date' => '2024-10-31', 'last_amended_date' => '2024-10-31',
                'amended_by' => null, 'confidence' => 'medium', 'payload' => null,
                'notes' => 'Butir PANTAUAN — tidak dibaca kalkulator mana pun. Mengubah pemaknaan sejumlah norma (PKWT, alih daya, pesangon/PHK) dalam UU 6/2023 dan peraturan turunannya. Nomor perkara dan tanggal dikonfirmasi lewat beberapa sumber sekunder.',
            ],
            [
                'rule_key' => 'labor.new_law', 'module' => 'contract', 'regulation_type' => 'UU', 'number' => 'Belum bernomor', 'year' => 2026,
                'title' => 'Undang-Undang Pelindungan Ketenagakerjaan — dilaporkan disahkan DPR 6 Oktober 2026 (23 bab, 313 pasal)',
                'article' => 'Memuat antara lain: PKWT maksimal 4 tahun (2 + 1 + 1), pengali pesangon minimum 1×, dasar hukum dana cadangan pesangon, pembatasan alih daya',
                'effective_date' => '2026-10-06', 'last_amended_date' => '2026-10-06', 'amended_by' => null, 'confidence' => 'low', 'payload' => null,
                'notes' => 'BELUM BERLAKU: nomor UU dan tanggal pengundangan belum diketahui; berlaku setelah masa transisi 1 tahun sejak diundangkan (menurut Talenta dan hukumonline, sumber sekunder; naskah final masih disinkronkan). Butir PANTAUAN — jangan mengubah PKWT/kalkulator sebelum UU berlaku; setelah diundangkan, tambahkan versi baru aturan terkait dan ulas ulang.',
            ],
        ];
        foreach ($newRows as $r) {
            DB::table('legal_basis_register')->insertOrIgnore([
                'rule_key' => $r['rule_key'], 'module' => $r['module'], 'regulation_type' => $r['regulation_type'], 'number' => $r['number'], 'year' => $r['year'],
                'title' => $r['title'], 'article' => $r['article'], 'effective_date' => $r['effective_date'], 'last_amended_date' => $r['last_amended_date'],
                'amended_by' => $r['amended_by'], 'source_url' => null, 'status' => 'needs_review', 'confidence' => $r['confidence'],
                'payload' => $r['payload'] === null ? null : json_encode($r['payload']), 'notes' => self::TAG . $r['notes'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Tidak dibalik: baris yang ditambahkan dan catatan pemeriksaan daring dipertahankan sebagai riwayat. Hapus manual lewat Legal Basis bila perlu.
    }
};
