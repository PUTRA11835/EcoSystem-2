<?php

namespace App\Support\Contracts;

/**
 * Template bawaan sistem (tidak dapat dihapus, isinya boleh diedit). Dipakai migrasi pembuat tabel dan migrasi
 * penyegar teks bawaan.
 *
 * Isi = PDF template ESH yang diberikan pemilik (7 Okt 2026): Perjanjian Jangka Waktu Tertentu (PKWT), Perjanjian
 * Jangka Waktu Tidak Tertentu (PKWTT) dan Perjanjian Kerja Sama Jasa Konsultan (PKS). Nilai contoh di PDF diganti
 * placeholder ({{...}}, daftar di ContractPlaceholders); kalimat hukum disalin apa adanya — termasuk kalimat yang
 * di PDF terpotong — agar HR yang menyuntingnya.
 *
 * HTML hanya memakai tag dan properti CSS yang diizinkan ContractHtmlSanitizer, supaya teks tetap utuh saat
 * template disimpan ulang dari editor.
 */
class DefaultTemplates
{
    /** @return array<int,array{name:string,contract_type:string,description:string,body_html:string}> */
    public static function all(): array
    {
        return [
            [
                'name'          => 'Kontrak Default PKWT',
                'contract_type' => ContractRules::TYPE_PKWT,
                'description'   => 'Perjanjian Jangka Waktu Tertentu (14 pasal), disalin dari template ESH. Dipakai sebagai acuan dan fallback kontrak PKWT baru.',
                'body_html'     => self::pkwt(),
            ],
            [
                'name'          => 'Kontrak Default PKWTT',
                'contract_type' => ContractRules::TYPE_PKWTT,
                'description'   => 'Perjanjian Jangka Waktu Tidak Tertentu (16 pasal), disalin dari template ESH. Dipakai sebagai acuan dan fallback kontrak PKWTT baru.',
                'body_html'     => self::pkwtt(),
            ],
            [
                'name'          => 'Template Default PKS Konsultan',
                'contract_type' => ContractRules::TYPE_EXTERNAL,
                'description'   => 'Perjanjian Kerja Sama Jasa Konsultan (9 pasal), disalin dari template ESH. Dapat diedit dari halaman ini.',
                'body_html'     => self::external(),
            ],
        ];
    }

    // ── pembangun HTML ────────────────────────────────────────────────────

    private static function p(string $text, string $align = 'justify'): string
    {
        return '<p style="text-align:' . $align . '">' . $text . '</p>';
    }

    private static function title(string $title, string $number = ''): string
    {
        return '<p style="text-align:center"><u><strong>' . $title . '</strong></u></p>'
            . '<p style="text-align:center"><strong>' . $number . '</strong></p>';
    }

    private static function pasal(int $n, string $heading): string
    {
        return '<p style="text-align:center"><strong>PASAL ' . $n . '</strong></p>'
            . '<p style="text-align:center"><u><strong>' . $heading . '</strong></u></p>';
    }

    /** Daftar bernomor/berhuruf. $type: decimal | lower-alpha | upper-roman | disc (ul). Item boleh berisi HTML (daftar bersarang). */
    private static function list(array $items, string $type = 'decimal'): string
    {
        $tag = $type === 'disc' ? 'ul' : 'ol';
        $html = "<{$tag} style=\"list-style-type:{$type}\">";
        foreach ($items as $item) {
            $html .= '<li style="text-align:justify">' . $item . '</li>';
        }

        return $html . "</{$tag}>";
    }

    /** Tabel dua kolom "label : nilai" tanpa garis. @param array<int,array{0:string,1:string}> $rows */
    private static function kv(array $rows, int $labelWidth = 24, bool $letters = false): string
    {
        $html = '<table style="width:100%"><tbody>';
        foreach ($rows as $i => [$label, $value]) {
            $prefix = $letters ? chr(97 + $i) . '. ' : '';
            $html .= '<tr><td style="width:' . $labelWidth . '%">' . $prefix . $label . '</td><td style="width:3%">:</td><td>' . $value . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    /** Tabel "label ..... nilai" rata kanan (cuti khusus). @param array<int,array{0:string,1:string}> $rows */
    private static function leaveTable(array $rows): string
    {
        $html = '<table style="width:100%"><tbody>';
        foreach ($rows as [$label, $days]) {
            $html .= '<tr><td>' . $label . '</td><td style="width:20%;text-align:right">' . $days . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private static function signatories(string $rightIntro, string $rightRole, string $rightName): string
    {
        return '<table style="width:100%"><tbody>'
            . '<tr><td style="width:50%">Ditandatangani untuk dan atas nama<br><strong>{{perusahaan}}</strong></td>'
            . '<td>' . $rightIntro . '<br><strong>' . $rightRole . '</strong></td></tr>'
            . '<tr><td style="height:62pt;vertical-align:bottom">{{tanda_tangan_penandatangan}}</td><td style="height:62pt;vertical-align:bottom">{{meterai}}</td></tr>'
            . '<tr><td><strong><u>{{penandatangan}}</u></strong><br>{{jabatan_penandatangan}}</td>'
            . '<td><strong><u>' . $rightName . '</u></strong><br>' . $rightRole . '</td></tr>'
            . '</tbody></table>';
    }

    /** Blok pihak-pihak yang sama pada PKWT & PKWTT. */
    private static function employeeParties(string $together): string
    {
        return self::p('Bertempat di {{kota_penandatanganan}}, pada hari {{tanggal_tanda_tangan}}, yang bertanda tangan di bawah ini:', 'left')
            . self::kv([['Nama', '{{penandatangan}}'], ['Jabatan', '{{jabatan_penandatangan}}']], 22)
            . self::p('Dalam hal ini bertindak atas nama <strong>{{perusahaan}}</strong>, sebuah perusahaan yang bergerak di bidang jasa konsultasi teknologi informasi, selanjutnya disebut <strong>"Perusahaan"</strong>, dengan perorangan yaitu:')
            . self::kv([
                ['Nama', '{{nama_karyawan}}'], ['Tempat/Tgl Lahir', '{{tempat_tanggal_lahir}}'], ['NIK', '{{nik}}'],
                ['Jenis Kelamin', '{{jenis_kelamin}}'], ['Alamat', '{{alamat}}'], ['NPWP', '{{npwp}}'],
            ], 22)
            . self::p('Dalam hal ini bertindak untuk dan atas namanya sendiri, selanjutnya disebut <strong>"Pekerja"</strong>.')
            . self::p('<strong>Perusahaan</strong> dan <strong>Pekerja</strong>, masing-masing disebut sebagai <strong>Pihak</strong> dan bersama-sama disebut sebagai <strong>' . $together . '</strong>, dengan ini sepakat untuk mengikatkan diri dalam Perjanjian Kerja, dengan ketentuan-ketentuan sebagai berikut:');
    }

    private static function wageBlock(string $absence): string
    {
        return self::kv([
            ['Gaji Pokok', '{{gaji_pokok}}/bulan'],
            ['Tunjangan', '{{tunjangan}}'],
            ['<strong>Jumlah Total Gaji yang Diterima</strong>', '<strong>{{gaji}}/bulan</strong>'],
            ['Lembur', 'Sesuai form yang telah disetujui oleh atasan dan dihitung sesuai peraturan perusahaan dan/atau hukum yang berlaku.'],
            ['Fasilitas Kesehatan', 'Dibayarkan oleh Perusahaan sesuai ketentuan peraturan perundang-undangan yang berlaku.'],
            ['BPJS Ketenagakerjaan', 'Dibayarkan oleh Perusahaan sesuai ketentuan peraturan perundang-undangan yang berlaku.'],
        ], 30, true)
            . self::p('2. Atas upah yang diterima oleh Pekerja, maka akan dipotong kewajiban-kewajiban sesuai perundang-undangan yaitu:', 'left')
            . self::kv([
                ['BPJS Kesehatan', 'Dibayarkan oleh Pekerja sesuai ketentuan perundang-undangan yang berlaku.'],
                ['BPJS Ketenagakerjaan', 'Dibayarkan oleh Pekerja sesuai ketentuan perundang-undangan yang berlaku.'],
                ['PPh 21', 'Dibayarkan oleh Pekerja sesuai ketentuan perpajakan.'],
                ['Mangkir', $absence],
            ], 30, true)
            . self::p('3. Gaji berikut tunjangan akan dibayarkan kepada Pekerja sesuai dengan peraturan Perusahaan yang berlaku yaitu setiap akhir bulan, melalui transfer bank ke nomor rekening Pekerja.');
    }

    private static function specialLeave(): string
    {
        return self::leaveTable([
            ['Pernikahan Pekerja sendiri (untuk pertama kalinya)', '3 hari kerja'], ['Pernikahan anak Pekerja', '2 hari kerja'],
            ['Kematian Istri, suami, anak, orang tua, menantu, atau mertua', '2 hari kerja'], ['Kelahiran anak Pekerja', '2 hari kerja'],
            ['Sunatan atau Baptisan anak Pekerja', '2 hari kerja'], ['Kematian anggota keluarga dalam satu rumah', '1 hari kerja'],
            ['Cuti sebelum dan sesudah melahirkan', '3 bulan'], ['Cuti keguguran', '1,5 bulan'],
        ]);
    }

    private static function confidentiality(string $who, string $scope): string
    {
        return self::list([
            'Dalam hal Perusahaan memiliki informasi atau materi rahasia termasuk tetapi tidak terbatas pada konsep, teknik, proses, desain, sirkit, data biaya, program komputer, formula, pekerjaan pengembangan atau eksperimen, pekerjaan dalam proses, dan keahlian teknis lainnya, maka ' . $who . ' tidak akan menyingkapkan kepada pihak manapun kecuali dimintakan untuk kepentingan Negara.',
            $who . ' berkewajiban untuk melindungi seluruh Informasi Rahasia Perusahaan dan mengakui bahwa Informasi Rahasia tersebut hanya dipergunakan untuk kepentingan hubungan bisnis Perusahaan.',
            'Setiap informasi rahasia yang diungkap baik selama masa kerja maupun setelah masa kerja berakhir tidak dapat dianggap sebagai pemberian hak atau lisensi apapun kepada ' . $who . '.',
            $who . ' dilarang mengungkapkan, menyebarkan, atau menggunakan Informasi Rahasia tanpa persetujuan atasan di Perusahaan kepada pihak manapun.',
            $scope,
        ]);
    }

    // ── PKWT ──────────────────────────────────────────────────────────────

    private static function pkwt(): string
    {
        return self::title('PERJANJIAN JANGKA WAKTU TERTENTU', 'No : {{nomor_kontrak}}')
            . self::employeeParties('Para Pihak')
            . self::pasal(1, 'ISTILAH DAN DEFINISI')
            . self::list([
                'Kecuali ditentukan lain dalam hubungan kalimat di dalam Perjanjian ini, istilah-istilah yang dipakai dalam Perjanjian ini haruslah diartikan sebagai berikut:'
                . self::list([
                    'Perjanjian adalah Perjanjian Kerja Waktu Tertentu (PKWT) yang dilakukan oleh Perusahaan dengan Pekerja yang didasarkan atas jangka waktu tertentu.',
                    'Perusahaan adalah {{perusahaan}} sebagai perusahaan pemberi kerja.',
                    'Pekerja adalah tenaga kerja yang ditempatkan dan difungsikan oleh Perusahaan sesuai jabatan, bagian, lokasi kerja, kantor atau tempat-tempat lain yang ditunjuk oleh Perusahaan.',
                ], 'lower-alpha'),
                'Judul suatu pasal atau ayat yang digunakan dalam Perjanjian ini semata-mata hanya untuk memberi kemudahan referensi saja dan tidak dapat dianggap mempunyai arti dalam menafsirkan ketentuan yang lain.',
            ])
            . self::pasal(2, 'JABATAN, PENEMPATAN, WAKTU KERJA, DAN JANGKA WAKTU PKWT')
            . self::p('1. Perusahaan menerima Pekerja untuk bekerja dengan kondisi sebagai berikut:', 'left')
            . '<table style="width:100%"><tbody>'
            . '<tr><td style="width:34%">a. Posisi Awal</td><td style="width:3%">:</td><td>{{posisi}}</td></tr>'
            . '<tr><td>b. Jabatan</td><td>:</td><td>{{jabatan}}</td></tr>'
            . '<tr><td>c. Tanggal Mulai Bekerja</td><td>:</td><td>{{tanggal_mulai}}</td></tr>'
            . '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;dan Tanggal Berakhir</td><td>:</td><td>{{tanggal_berakhir}}</td></tr>'
            . '<tr><td>d. Ketentuan Perpanjangan</td><td>:</td><td>1 (satu) bulan sebelum masa kerja berakhir</td></tr>'
            . '<tr><td>e. Hari Kerja</td><td>:</td><td>Senin - Jumat</td></tr>'
            . '<tr><td>f. Waktu Kerja</td><td>:</td><td>08.00 - 17.00</td></tr>'
            . '<tr><td>g. Lokasi Karyawan</td><td>:</td><td>{{lokasi_kerja}}</td></tr>'
            . '</tbody></table>'
            . self::p('2. Perjanjian ini berlaku efektif mulai tanggal bekerja, kecuali:', 'left')
            . self::list([
                'Pekerja mengundurkan diri dari Perusahaan;',
                'Diberhentikan oleh Perusahaan dikarenakan pelanggaran atas ketentuan dalam Perjanjian ini;',
                'Pekerja diangkat secara permanen oleh Perusahaan dengan pemberitahuan sebelumnya.',
            ], 'lower-alpha')
            . self::p('3. Dalam pelaksanaan pekerjaan, Pekerja akan dievaluasi sesuai dengan performa kerja, yang mana hasil evaluasi tersebut akan menentukan kelanjutan hubungan kerja antara Perusahaan dengan Pekerja.')
            . self::p('4. Perusahaan wajib memberikan uang kompensasi kepada Pekerja setelah berakhirnya Perjanjian Kerja Waktu Tertentu ini, berdasarkan PP No. 35 Tahun 2021.')
            . self::pasal(3, 'TUGAS DAN TANGGUNG JAWAB')
            . self::list([
                'Pekerja akan bertanggung jawab untuk melaksanakan tugasnya.',
                'Pekerja juga akan melaksanakan tugas-tugas lainnya yang sewaktu-waktu diperintahkan oleh atasan di Perusahaan.',
                'Selama Perjanjian ini berjalan, Pekerja setuju untuk mengerahkan upayanya dan seluruh waktu kerjanya hanya untuk kepentingan Perusahaan dan tidak menerima pekerjaan lain yang dapat bertentangan dengan tanggung jawabnya tanpa pengetahuan dan izin tertulis dari Perusahaan.',
                'Apabila ketentuan ini dilanggar dan merugikan Perusahaan, maka Pekerja setuju untuk dapat diproses lebih lanjut secara hukum.',
                'Waktu, hari dan jam kerja Pekerja akan disesuaikan dengan waktu kerja yang berlaku di Perusahaan.',
                'Pekerja setuju dan bersedia untuk ditempatkan di seluruh wilayah usaha Perusahaan sesuai dengan kebutuhan Perusahaan.',
            ])
            . self::pasal(4, 'HAK DAN KEWAJIBAN TENAGA KERJA')
            . self::list([
                '<strong>Hak Pekerja</strong><br>Untuk pekerjaan yang dilaksanakan berdasarkan penugasan dari Perusahaan baik yang disampaikan melalui atasan langsung maupun rekan kerja, maka Pekerja berhak untuk:'
                . self::list([
                    'Memperoleh upah;', 'Memperoleh libur mingguan atau libur hari raya resmi;', 'Memperoleh BPJS Kesehatan;', 'Tunjangan hari raya keagamaan;',
                    'Memperoleh perlindungan asuransi BPJS Ketenagakerjaan;',
                    'Dalam hal Pekerja mengalami kecelakaan kerja, maka Pekerja berhak mendapatkan pengurusan klaim oleh Perusahaan atas kecelakaan kerja tersebut sesuai dengan kepesertaan Pekerja di BPJS Ketenagakerjaan.',
                ], 'lower-alpha'),
                '<strong>Kewajiban Pekerja</strong>'
                . self::list([
                    'Memberikan keterangan yang sebenarnya baik mengenai diri sendiri maupun pekerjaan kepada Perusahaan.',
                    'Melaksanakan setiap peraturan yang berlaku di Perusahaan dan lingkungan tempat bekerja.',
                    'Bertanggung jawab penuh atas tugas yang dibebankan oleh atasan.',
                    'Pekerja bersedia untuk masuk dan melaksanakan tugas-tugasnya apabila dibutuhkan oleh Perusahaan.',
                    'Menjaga dan memelihara kebersihan serta kerapihan diri dan juga tempat/lingkungan pekerjaan.',
                    'Memelihara dan menjaga segala milik Perusahaan yang diserahkan atau yang dipercayakan kepada Pekerja untuk menunjang kelangsungan kerja baik secara langsung maupun tidak langsung.',
                    'Pekerja mempunyai Nomor Pokok Wajib Pajak (NPWP).',
                    'Pekerja diwajibkan untuk mempunyai nomor rekening bank untuk proses pembayaran gaji setiap bulannya.',
                    'Hadir di tempat kerja sesuai dengan waktu yang ditentukan Perusahaan.',
                ], 'lower-alpha'),
            ])
            . self::pasal(5, 'UPAH')
            . self::p('1. Akan disesuaikan dengan perjanjian antara yang meliputi:', 'left')
            . self::wageBlock('Jika tidak masuk kerja tanpa izin dan tanpa bukti/keterangan tertulis apapun.')
            . self::p('4. Pekerja yang tidak memiliki NPWP karena alasan apapun, maka Pekerja akan dikenakan denda pajak penghasilan (PPh 21) 20% lebih besar dari total pajak penghasilan (PPh 21) yang terhitung dan/atau sesuai dengan ketentuan perpajakan yang berlaku.')
            . self::pasal(6, 'TUNJANGAN HARI RAYA')
            . self::p('Pekerja berhak mendapat Tunjangan Hari Raya (THR) keagamaan yang dibayarkan pada hari Raya dan akan dihitung sesuai dengan masa kerja dengan memperhatikan Perjanjian antara Perusahaan dan Pekerja.')
            . self::p('Perhitungan dan mekanisme pembayaran Tunjangan Hari Raya (THR) keagamaan ini akan disesuaikan dengan perundang-undangan yang berlaku.')
            . self::pasal(7, 'PERATURAN MENGENAI CUTI')
            . self::list([
                '<strong>Cuti Tahunan</strong>'
                . self::list([
                    'Pekerja berhak mengambil cuti tahunan selama 12 (dua belas) hari kerja setelah ia bekerja selama 12 (dua belas) bulan secara terus-menerus pada Perusahaan, apabila kontrak Pekerja lebih dari satu tahun.',
                    'Pekerja yang telah memperoleh hak cuti tahunan wajib mengikuti aturan pengambilan cuti tahunan yang ditentukan oleh Perusahaan.',
                    'Pengajuan cuti dilakukan dengan terlebih dahulu berkoordinasi dengan atasan dan/atau Team Lead/Project Manager.',
                ], 'lower-alpha'),
                '<strong>Cuti Khusus</strong><br>Cuti khusus adalah hak cuti karyawan di luar hak cuti tahunan karena adanya kondisi-kondisi berikut ini:' . self::specialLeave(),
            ])
            . self::pasal(8, 'KERAHASIAAN')
            . self::confidentiality('Pekerja', 'Sifat kerahasiaan ini berlaku baik pada saat bekerja maupun setelah selesai bekerja pada Perusahaan.')
            . self::pasal(9, 'DISIPLIN DAN TINGKAH LAKU')
            . self::list([
                'Pekerja setiap waktu diwajibkan untuk menaati tata cara, peraturan, dan syarat-syarat Perusahaan dan melaksanakan tugasnya secara profesional, sesuai standar etis Perusahaan dan juga hukum yang berlaku di Republik Indonesia.',
                'Pekerja diwajibkan menjaga reputasi dan nama baik Perusahaan dalam menjalankan tugas secara profesional.',
            ])
            . self::pasal(10, 'PENGUNDURAN DIRI')
            . self::list([
                'Pekerja yang ingin mengundurkan diri dari Perusahaan wajib memberikan pemberitahuan secara tertulis kepada Perusahaan paling lambat 30 (tiga puluh) hari sebelum tanggal efektif pengunduran diri.',
                'Apabila Pekerja mengundurkan diri tanpa pemberitahuan sebagaimana yang di atur dalam ayat 1 atau tidak hadir selama 5 (lima) hari berturut-turut tanpa pemberitahuan sebelum masa Perjanjian Kerja Waktu Tertentu berakhir , maka Pekerja dianggap melakukan pengakhiran hubungan kerja atas kehendak sendiri. Dalam hal tersebut, Pekerja wajib membayar ganti rugi sesuai dengan ketentuan peraturan perundang-undangan yang berlaku.',
                'Selain diwajibkan untuk membayar sisa kontrak yang masih berjalan, maka Perusahaan tidak akan membayarkan atau memberikan sisa upah beserta hak-hak lainnya.',
                'Dalam hal pekerja mengundurkan diri sebelum masa Perjanjian Kerja Waktu Tertentu berakhir , maka pekerja wajib membayar ganti rugi kepada perusahaan sesuai dengan ketentuan peraturan perundang-undangan yang berlaku.',
                'Pekerja yang telah ditugaskan pada suatu proyek tidak diperkenankan mengundurkan diri sebelum proyek tersebut dinyatakan selesai secara resmi.',
            ])
            . self::pasal(11, 'PENGHENTIAN/TERMINASI')
            . self::list([
                'Perusahaan mempunyai hak untuk mengakhiri terminasi Perjanjian ini kepada pihak Pekerja sewaktu-waktu apabila terpenuhinya kondisi-kondisi dibawah ini, termasuk namun tidak terbatas:'
                . self::list([
                    'Perjanjian Kerja Waktu Tertentu Pekerja antara Perusahaan telah berakhir.',
                    'Melanggar Peraturan dari Perusahaan.',
                    'Tidak berhasil atau gagal melaksanakan tugas dan tanggung jawabnya dengan memuaskan sesuai dengan Perjanjian ini berdasarkan pendapat dan penilaian dari Perusahaan.',
                    'Berkelakuan tidak baik, di luar kewajaran, dan/atau untuk kepentingan pribadi selama waktu kerja di lingkungan Perusahaan yang menurut pendapat dan penilaian dari Perusahaan dapat merusak reputasi atau usaha.',
                    'Diduga ataupun telah terbukti melakukan penipuan, pencurian, atau penggelapan barang dan/atau uang milik Perusahaan.',
                    'Menerima pekerjaan lain yang dapat bertentangan dengan tanggung jawabnya.',
                    'Memberikan keterangan palsu atau yang dipalsukan sehingga merugikan Perusahaan.',
                    'Mabuk, meminum minuman keras yang memabukkan, memakai dan atau mengedarkan narkotika, psikotropika, dan zat adiktif lainnya di lingkungan kerja.',
                    'Melakukan perbuatan asusila atau perjudian di lingkungan kerja.',
                    'Menyerang, menganiaya, mengancam, atau mengintimidasi rekan kerja atau pengusaha di lingkungan kerja.',
                    'Membujuk rekan kerja untuk melakukan perbuatan yang bertentangan dengan peraturan perundang-undangan.',
                    'Dengan ceroboh atau sengaja merusak atau membiarkan dalam keadaan bahaya barang milik Perusahaan yang menimbulkan kerugian bagi Perusahaan.',
                    'Dengan ceroboh atau sengaja membiarkan rekan kerja atau pengusaha dalam keadaan bahaya di tempat kerja.',
                    'Membongkar atau membocorkan rahasia Perusahaan yang seharusnya dirahasiakan kecuali untuk kepentingan hukum.',
                    'Melakukan perbuatan lainnya di lingkungan kerja yang diancam dengan pidana penjara 5 (lima) tahun atau lebih.',
                    'Pekerja sudah diperingatkan oleh Perusahaan baik secara lisan dan tulisan.',
                    'Membuat/menimbulkan kesan yang tidak baik terhadap Perusahaan atau orang lain yang berada pada klien mengenai Perusahaan.',
                    'Patut diduga atau telah terbukti secara tertulis mempunyai riwayat kesehatan atau gangguan kejiwaan yang dapat mengganggu absensi/pekerjaan.',
                ], 'lower-alpha'),
                'Apabila pelanggaran tersebut menimbulkan kerugian bagi Perusahaan, Perusahaan berhak menuntut penggantian atas kerugian nyata yang dapat dibuktikan sesuai dengan mekanisme dan ketentuan peraturan perundang-undangan yang berlaku.',
                'Pelaksanaan Pemutusan Hubungan Kerja ini mengacu pada Undang-Undang Nomor 13 Tahun 2003 tentang Ketenagakerjaan sebagaimana telah diubah berdasarkan Undang-Undang Nomor 6 Tahun 2023 serta peraturan pelaksanaannya.',
            ])
            . self::pasal(12, 'SANKSI TENAGA KERJA')
            . self::p('Jika dalam jangka waktu Perjanjian ini Pekerja melakukan pelanggaran terhadap ketentuan yang diatur dalam Perjanjian ini, Peraturan Perusahaan, dan/atau kebijakan Perusahaan, Perusahaan dapat menjatuhkan sanksi sampai dengan Pemutusan Hubungan Kerja sesuai dengan jenis dan tingkat pelanggaran serta ketentuan peraturan perundang-undangan.')
            . self::pasal(13, 'UNDANG-UNDANG BERLAKU')
            . self::list([
                'Perjanjian ini dibuat di wilayah Republik Indonesia dan berdasarkan peraturan perundang-undangan di bidang ketenagakerjaan yang berlaku di wilayah Republik Indonesia.',
                'Dalam hal terjadi perbedaan kepentingan antara Perusahaan dan Pekerja sehubungan dengan Perjanjian ini, maka Perusahaan dan Pekerja setuju untuk menyelesaikannya terlebih dahulu secara musyawarah mufakat.',
                'Hal-hal yang tidak atau belum diatur dalam Perjanjian ini akan dirundingkan dan disetujui bersama dalam suatu Addendum bila dianggap perlu, yang merupakan bagian yang tidak terpisahkan dari Perjanjian ini.',
            ])
            . self::pasal(14, 'PENUTUP')
            . self::list([
                'Para Pihak dengan ini menyatakan telah membaca dengan teliti, menyetujui, mengerti serta menerima seluruh isi dari Perjanjian ini.',
                'Para Pihak menyatakan bahwa pada saat menandatangani Perjanjian ini sedang berada di dalam kondisi jasmani/rohani, dan dengan pikiran yang sadar, sehat serta tanpa ada paksaan atau tekanan dari pihak manapun.',
            ])
            . self::signatories('Diterima dan dimengerti oleh', 'Pekerja', '{{nama_karyawan}}');
    }

    // ── PKWTT ─────────────────────────────────────────────────────────────

    private static function pkwtt(): string
    {
        return self::title('PERJANJIAN JANGKA WAKTU TIDAK TERTENTU', 'No : {{nomor_kontrak}}')
            . self::employeeParties('Kedua Belah Pihak')
            . self::pasal(1, 'ISTILAH DAN DEFINISI')
            . self::list([
                'Kecuali ditentukan lain dalam hubungan kalimat di dalam Perjanjian ini, istilah-istilah yang dipakai dalam Perjanjian ini haruslah diartikan sebagai berikut:'
                . self::list([
                    'Perjanjian adalah Perjanjian Kerja Waktu Tidak Tertentu (PKWTT) yang dilakukan oleh Perusahaan dengan Pekerja pada jangka waktu tidak terbatas.',
                    'Perusahaan adalah {{perusahaan}} sebagai perusahaan pemberi kerja.',
                    'Pekerja adalah tenaga kerja yang ditempatkan di fungsi, bagian, lokasi kerja, kantor atau tempat-tempat yang ditunjuk dan ditentukan oleh Perusahaan.',
                ], 'lower-alpha'),
                'Judul suatu pasal atau ayat yang digunakan dalam Perjanjian ini semata-mata hanya untuk memberi kemudahan referensi saja dan tidak dapat dianggap mempunyai arti dalam menafsirkan ketentuan yang lain.',
            ])
            . self::pasal(2, 'JABATAN, PENEMPATAN, WAKTU KERJA')
            . self::p('1. Perusahaan menerima Pekerja untuk bekerja dengan kondisi sebagai berikut:', 'left')
            . self::kv([
                ['Posisi Awal', '{{posisi}}'], ['Jabatan', '{{jabatan}}'],
                ['Status Hubungan Kerja', 'Perjanjian Kerja Waktu Tidak Tertentu (PKWTT) / Pekerja Tetap'],
                ['Hari Kerja', 'Senin - Jumat'], ['Waktu Kerja', '08.00 - 17.00'], ['Lokasi Karyawan', '{{lokasi_kerja}}'],
            ], 34, true)
            . self::p('2. Perjanjian ini berlaku efektif, kecuali:', 'left')
            . self::list(['Pekerja mengundurkan diri dari Perusahaan;', 'Diberhentikan oleh Perusahaan dikarenakan pelanggaran atas ketentuan dalam Perjanjian ini;'], 'lower-alpha')
            . self::pasal(3, 'MASA PERCOBAAN')
            . self::list([
                'Pekerja menjalani masa percobaan selama 3 (tiga) bulan, terhitung sejak tanggal {{tanggal_mulai}}.',
                'Selama masa percobaan, Pekerja tetap memperoleh upah dan hak-hak lainnya sesuai dengan ketentuan Perjanjian ini dan peraturan perundang-undangan.',
                'Apabila Pekerja dinilai memenuhi standar kinerja berdasarkan hasil evaluasi Perusahaan, hubungan kerja berdasarkan PKWTT ini tetap berlanjut tanpa memerlukan perjanjian atau pengangkatan baru.',
                'Apabila Pekerja tidak memenuhi standar kinerja selama masa percobaan, Perusahaan dapat melakukan Pemutusan Hubungan Kerja dengan pemberitahuan tertulis dan pemenuhan hak Pekerja sesuai dengan ketentuan peraturan perundang-undangan.',
            ])
            . self::pasal(4, 'TUGAS DAN TANGGUNG JAWAB')
            . self::list([
                'Pekerja akan bertanggung jawab untuk melaksanakan tugasnya.',
                'Pekerja juga akan melaksanakan tugas-tugas tetap dalam lingkup jabatannya sesuai dengan tanggung jawab yang telah diberikan oleh Perusahaan.',
                'Selama Perjanjian ini berjalan, Pekerja setuju untuk mengerahkan upayanya dan seluruh waktu kerjanya hanya untuk kepentingan Perusahaan dan tidak menerima pekerjaan lain yang dapat bertentangan dengan tanggung jawabnya tanpa pengetahuan dan izin tertulis dari Perusahaan.',
                'Apabila ketentuan ini dilanggar dan merugikan Perusahaan, maka Pekerja setuju untuk dapat diproses lebih lanjut secara hukum.',
                'Waktu, hari dan jam kerja Pekerja disesuaikan dengan jadwal waktu kerja yang berlaku di Perusahaan.',
                'Pekerja setuju dan bersedia untuk ditempatkan di seluruh wilayah usaha Perusahaan sesuai dengan kebutuhan Perusahaan.',
            ])
            . self::pasal(5, 'HAK DAN KEWAJIBAN TENAGA KERJA')
            . self::list([
                '<strong>Hak Pekerja</strong><br>Untuk pekerjaan yang dilaksanakan berdasarkan penugasan dari Perusahaan baik yang disampaikan melalui atasan langsung maupun rekan kerja, maka Pekerja berhak untuk:'
                . self::list([
                    'Memperoleh upah.', 'Memperoleh libur mingguan atau libur hari raya resmi.', 'Memperoleh BPJS Kesehatan.', 'Tunjangan hari raya keagamaan.',
                    'Memperoleh perlindungan asuransi BPJS Ketenagakerjaan.',
                    'Dalam hal Pekerja mengalami kecelakaan kerja, maka Pekerja berhak mendapatkan pengurusan klaim oleh Perusahaan.',
                    'Mendapat pesangon secara penuh bilamana terjadi Pemutusan Hubungan Kerja (PHK) sesuai peraturan ketenagakerjaan yang berlaku.',
                ], 'lower-alpha'),
                '<strong>Kewajiban Pekerja</strong>'
                . self::list([
                    'Memberikan keterangan yang sebenarnya baik mengenai diri sendiri maupun pekerjaan kepada Perusahaan.',
                    'Melaksanakan setiap peraturan yang berlaku di Perusahaan dan lingkungan tempat bekerja.',
                    'Bertanggung jawab penuh atas tugas yang dibebankan oleh atasan.',
                    'Pekerja bersedia untuk masuk dan melaksanakan tugas-tugasnya apabila dibutuhkan oleh Perusahaan.',
                    'Menjaga dan memelihara kebersihan serta kerapihan diri dan juga tempat/lingkungan pekerjaan.',
                    'Memelihara dan menjaga segala milik Perusahaan yang diserahkan atau yang dipercayakan kepada Pekerja.',
                    'Pekerja mempunyai Nomor Pokok Wajib Pajak (NPWP).',
                    'Pekerja diwajibkan untuk mempunyai nomor rekening bank untuk proses pembayaran gaji setiap bulannya.',
                    'Hadir di tempat kerja sesuai dengan waktu yang ditentukan oleh Perusahaan.',
                    'Bersedia memenuhi ketentuan-ketentuan keselamatan dan keamanan kerja yang berlaku di Perusahaan.',
                ], 'lower-alpha'),
            ])
            . self::pasal(6, 'UPAH')
            . self::p('1. Penyesuaian nilai upah dilaksanakan berdasarkan Perjanjian ini yang meliputi:', 'left')
            . self::wageBlock('Jika tidak masuk kerja tanpa bukti/keterangan tertulis apapun.')
            . self::pasal(7, 'TUNJANGAN HARI RAYA')
            . self::p('Pekerja berhak mendapat Tunjangan Hari Raya (THR) keagamaan yang dibayarkan pada hari Raya dan akan dihitung sesuai masa kerja dengan memperhatikan Perjanjian antara Perusahaan dan Pekerja.')
            . self::p('Perhitungan dan mekanisme pembayaran THR keagamaan ini akan disesuaikan dengan perundang-undangan yang berlaku.')
            . self::pasal(8, 'PERATURAN MENGENAI CUTI')
            . self::list([
                '<strong>Cuti Tahunan</strong>'
                . self::list([
                    'Pekerja berhak mengambil cuti tahunan selama 12 (dua belas) hari kerja setelah ia bekerja selama 12 (dua belas) bulan secara terus-menerus pada Perusahaan.',
                    'Pekerja yang telah memperoleh hak cuti tahunan wajib mengikuti aturan pengambilan cuti tahunan yang ditentukan oleh Perusahaan, yaitu sebagai berikut:'
                    . self::list([
                        'Pekerja wajib mengajukan form cuti paling lambat 14 (empat belas) hari sebelum cuti dilaksanakan.',
                        'Jumlah hari cuti yang dapat diajukan ialah proporsional setiap bulannya, dikecualikan cuti bersama dan cuti khusus.',
                        'Apabila ada cuti bersama dari Pemerintah, maka Perusahaan akan memotong hak cuti tahunan Pekerja secara otomatis tanpa permohonan cuti.',
                    ], 'disc'),
                    'Pengajuan cuti dilakukan dengan terlebih dahulu berkoordinasi dengan Supervisor dan/atau Team Lead/Project Manager.',
                ], 'lower-alpha'),
                '<strong>Cuti Khusus</strong>' . self::specialLeave(),
            ])
            . self::pasal(9, 'PEMUTUSAN HUBUNGAN KERJA PKWTT')
            . self::list([
                'Perusahaan dapat memutuskan hubungan kerja apabila:'
                . self::list([
                    'Perusahaan melakukan penggabungan (merger), diambil alih, melakukan efisiensi, tutup, dalam keadaan PKPU, atau pailit;',
                    'Adanya Putusan lembaga PHI;', 'Pekerja mangkir selama 5 (lima) hari berturut-turut;', 'Pekerja mengundurkan diri;',
                    'Pekerja melakukan pelanggaran berat;', 'Pekerja memasuki usia pensiun;', 'Pekerja meninggal dunia;', 'Pekerja mengalami sakit berkepanjangan.',
                ], 'lower-alpha'),
                'Pemutusan Hubungan Kerja dilakukan dengan pemberitahuan tertulis kepada Pekerja sesuai dengan jangka waktu dan tata cara yang ditentukan dalam peraturan perundang-undangan.',
                'Atas Pemutusan Hubungan Kerja, Perusahaan wajib melakukan kewajibannya sesuai dengan ketentuan Undang-Undang Nomor 13 Tahun 2003 tentang Ketenagakerjaan sebagaimana telah diubah berdasarkan Undang-Undang Nomor 6 Tahun 2023 serta peraturan pelaksanaannya.',
            ])
            . self::pasal(10, 'PENGUNDURAN DIRI')
            . self::list([
                'Pekerja yang ingin mengundurkan diri dari Perusahaan wajib untuk memberikan pemberitahuan secara tertulis kepada Perusahaan paling lambat 1 (Satu) bulan sebelum tanggal efektif pengunduran diri.',
                'Pekerja yang telah ditugaskan pada suatu proyek tidak diperkenankan mengundurkan diri sebelum proyek tersebut dinyatakan selesai secara resmi.',
            ])
            . self::pasal(11, 'PENGHENTIAN/TERMINASI')
            . self::list([
                'Perusahaan mempunyai hak untuk mengakhiri terminasi Perjanjian ini kepada pihak Pekerja sewaktu-waktu apabila terpenuhinya kondisi-kondisi di bawah ini termasuk namun tidak terbatas:'
                . self::list([
                    'Melanggar Peraturan dari Perusahaan.',
                    'Tidak berhasil atau gagal melaksanakan tugas dan tanggung jawabnya dengan memuaskan sesuai dengan Perjanjian ini berdasarkan pendapat dan penilaian dari Perusahaan.',
                    'Berkelakuan tidak baik, di luar kewajaran, dan/atau untuk kepentingan pribadi selama waktu kerja di lingkungan perusahaan.',
                    'Diduga ataupun telah terbukti melakukan penipuan, pencurian, atau penggelapan barang dan/atau uang milik Perusahaan.',
                    'Menerima pekerjaan lain yang dapat bertentangan dengan tanggung jawabnya terhadap Perusahaan.',
                    'Memberikan keterangan palsu atau yang dipalsukan sehingga merugikan Perusahaan.',
                    'Mabuk, meminum minuman keras yang memabukkan, memakai dan atau mengedarkan narkotika, psikotropika, dan zat adiktif lainnya di lingkungan kerja.',
                    'Melakukan perbuatan asusila atau perjudian di lingkungan kerja.',
                    'Menyerang, menganiaya, mengancam, atau mengintimidasi rekan kerja atau pengusaha di lingkungan kerja.',
                    'Membujuk rekan kerja untuk melakukan perbuatan yang bertentangan dengan peraturan perundang-undangan.',
                    'Dengan ceroboh atau sengaja merusak atau membiarkan dalam keadaan bahaya barang milik Perusahaan yang menimbulkan kerugian bagi Perusahaan.',
                    'Membongkar atau membocorkan rahasia Perusahaan yang seharusnya dirahasiakan kecuali untuk kepentingan hukum.',
                    'Melakukan perbuatan lainnya di lingkungan kerja yang diancam pidana penjara 5 (lima) tahun atau lebih.',
                    'Pekerja sudah diperingatkan oleh Perusahaan baik secara lisan dan tulisan.',
                    'Membuat/menimbulkan kesan yang tidak baik terhadap Perusahaan atau orang lain yang bekerja di tempat klien mengenai Perusahaan.',
                    'Patut diduga atau telah terbukti secara tertulis mempunyai riwayat kesehatan atau gangguan kejiwaan yang dapat mengganggu absensi/pekerjaan.',
                    'Melanggar ketentuan Undang-Undang Nomor 13 Tahun 2003 atau perundang-undangan lain tentang ketenagakerjaan yang belum diatur di dalam Perjanjian Kerja ini.',
                ], 'lower-alpha'),
                'Apabila pelanggaran tersebut menimbulkan kerugian bagi Perusahaan, Perusahaan berhak menuntut penggantian atas kerugian nyata yang dapat dibuktikan sesuai dengan mekanisme dan ketentuan peraturan perundang-undangan yang berlaku.',
                'Pelaksanaan Pemutusan Hubungan Kerja ini mengacu pada Undang-Undang Nomor 13 Tahun 2003 tentang Ketenagakerjaan sebagaimana telah diubah berdasarkan Undang-Undang Nomor 6 Tahun 2023 serta Peraturan Pemerintah Nomor 35 Tahun 2021 dan peraturan pelaksanaannya.',
            ])
            . self::pasal(12, 'KERAHASIAAN')
            . self::confidentiality('Pekerja', 'Sifat kerahasiaan ini berlaku baik pada saat bekerja maupun setelah selesai bekerja pada Perusahaan.')
            . self::pasal(13, 'DISIPLIN DAN TINGKAH LAKU')
            . self::list([
                'Pekerja wajib memahami setiap tanggung jawab, peran serta tugasnya dalam jabatan yang diberikan.',
                'Pekerja setiap waktu diwajibkan untuk menaati tata cara, peraturan, dan syarat-syarat Perusahaan serta melaksanakan tugasnya dengan cara profesional, sesuai standar etis Perusahaan dan hukum yang berlaku di Republik Indonesia.',
                'Pekerja diwajibkan menjaga reputasi dan nama baik Perusahaan dalam menjalankan tugas secara profesional.',
            ])
            . self::pasal(14, 'SANKSI TENAGA KERJA')
            . self::list([
                'Apabila terjadi wanprestasi yang dilakukan oleh salah satu pihak yang menyebabkan batalnya perjanjian ini, maka Para Pihak sepakat untuk menyelesaikan dengan musyawarah terlebih dahulu untuk mencapai mufakat.',
                'Jika dalam jangka waktu Perjanjian ini Pekerja melakukan kesalahan dan/atau terpenuhinya kondisi penghentian yang diatur dalam Perjanjian ini, maka Perusahaan akan melakukan Pemutusan Hubungan Kerja.',
            ])
            . self::pasal(15, 'UNDANG-UNDANG BERLAKU')
            . self::list([
                'Perjanjian ini dibuat di wilayah Republik Indonesia dibuat berdasarkan peraturan perundang-undangan di bidang ketenagakerjaan yang berlaku di wilayah Republik Indonesia.',
                'Dalam hal terjadi perbedaan kepentingan antara Perusahaan dan Pekerja sehubungan dengan Perjanjian ini, maka Perusahaan dan Pekerja setuju untuk menyelesaikannya terlebih dahulu secara musyawarah mufakat.',
                'Hal-hal yang tidak atau belum diatur dalam Perjanjian ini akan dirundingkan dan disetujui bersama dalam suatu Addendum bila dianggap perlu, yang merupakan bagian yang tidak terpisahkan dari Perjanjian ini.',
            ])
            . self::pasal(16, 'PENUTUP')
            . self::list([
                'Para Pihak dengan ini menyatakan telah membaca dengan teliti, menyetujui, mengerti serta menerima seluruh isi dari Perjanjian ini.',
                'Para Pihak menyatakan bahwa pada saat menandatangani Perjanjian ini sedang berada di dalam kondisi jasmani/rohani, dan dengan pikiran yang sadar, sehat serta tanpa ada paksaan atau tekanan dari pihak manapun.',
            ])
            . self::signatories('Diterima dan dimengerti oleh', 'Pekerja', '{{nama_karyawan}}');
    }

    // ── PKS Konsultan (External) ──────────────────────────────────────────

    private static function external(): string
    {
        $hak = [
            'Konsultan wajib mematuhi ketentuan operasional yang berlaku di lokasi Perusahaan dan/atau Klien sepanjang berkaitan dengan keamanan, keselamatan, kerahasiaan, akses sistem, tata kelola data, dan kelancaran pelaksanaan Proyek.',
            'Konsultan wajib melaksanakan seluruh pekerjaan sesuai dengan ruang lingkup pekerjaan yang telah disepakati, sejak dimulainya pelaksanaan pekerjaan sampai dengan berakhirnya proyek, serta menyelesaikan seluruh tugas, tanggung jawab, dan <em>deliverables</em> yang menjadi kewajibannya sesuai dengan ketentuan dalam Perjanjian ini.',
            'Konsultan berkewajiban melapor secara berkala terkait proses pelaksanaan layanan jasa yang diberikan kepada Perusahaan.',
            'Konsultan berhak menerima pembayaran perjanjian sesuai tenggat waktu yang disepakati setelah melaksanakan seluruh pelaksanaan layanan Jasa.',
            'Konsultan berhak mendapatkan informasi yang dianggap perlu oleh Perusahaan untuk kepentingan pelaksanaan layanan Jasa.',
            'Konsultan wajib melaksanakan Layanan secara profesional, cermat, bertanggung jawab, dan sesuai keahlian yang dimilikinya.',
            'Konsultan wajib menyampaikan laporan perkembangan pekerjaan, timesheet, atau dokumen pendukung lain secara berkala sesuai mekanisme koordinasi Proyek.',
            'Konsultan wajib menjaga koordinasi dengan Project Manager, Project Director, atau perwakilan Perusahaan yang ditunjuk untuk kepentingan pelaksanaan Proyek.',
            'Konsultan tidak dapat mengalihkan pelaksanaan Layanan, baik sebagian maupun seluruhnya, kepada pihak lain tanpa persetujuan tertulis dari Perusahaan.',
            'Konsultan wajib menggunakan akses, data, perangkat, sistem, dan informasi milik Perusahaan dan/atau Klien hanya untuk kepentingan pelaksanaan Layanan.',
            'Konsultan wajib mengembalikan seluruh dokumen, data, perangkat, akses, dan/atau material milik Perusahaan dan/atau Klien setelah Perjanjian berakhir atau setelah diminta oleh Perusahaan.',
            'Konsultan wajib menghindari benturan kepentingan yang dapat mengganggu pelaksanaan Layanan atau merugikan kepentingan Perusahaan dan/atau Klien.',
            'Konsultan hanya dapat mengajukan ketidakhadiran dalam pelaksanaan proyek selama 1 (satu) hari dengan tetap memperhatikan jadwal agar proyek tetap terlaksana dengan baik.',
            'Apabila Konsultan berhalangan hadir saat pelaksanaan proyek karena alasan apa pun, maka Konsultan wajib memberitahukan terlebih dahulu kepada Project Manager dan/atau Project Director.',
            'Konsultan dilarang memberikan jasa sejenis kepada pelanggan Perusahaan atau pihak yang berpotensi menjadi pesaing selama masa Perjanjian.',
        ];

        $terminate = [
            'Melanggar Peraturan dari Perusahaan dan/atau Klien;',
            'Tidak berhasil atau Gagal melaksanakan tugas dan tanggung jawabnya dengan sesuai dengan Perjanjian ini berdasarkan pendapat dan penilaian dari Perusahaan dan atau Klien (sesuai dengan penilaian performa);',
            'Tidak hadir dalam pelaksanaan pengerjaan proyek tanpa pemberitahuan selama 5 (lima) hari berturut-turut;',
            'Berkelakuan tidak baik, di luar kewajaran, dan/atau untuk kepentingan pribadi selama waktu kerja di lingkungan perusahaan yang menurut pendapat dan penilaian dari Perusahaan dan/atau Klien dapat merusak reputasi atau usaha;',
            'Diduga ataupun telah terbukti melakukan penipuan, pencurian, atau penggelapan barang dan/atau uang milik Perusahaan dan/atau Klien;',
            'Menerima pekerjaan lain yang dapat bertentangan dengan tanggung jawabnya terhadap Klien;',
            'Memberikan keterangan palsu atau yang dipalsukan sehingga merugikan Perusahaan dan/atau Klien;',
            'Mabuk, meminum minuman keras yang memabukan, memakai dan atau mengedarkan narkotika, psikotropika, dan zat adiktif lainnya di lingkungan Perusahaan/Klien;',
            'Melakukan perbuatan asusila di lingkungan Perusahaan/Klien;',
            'Menyerang, menganiaya, mengancam, atau mengintimidasi rekan proyek atau pengusaha di lingkungan Perusahaan/Klien;',
            'Membujuk rekan proyek untuk melakukan perbuatan yang bertentangan dengan peraturan perundang-undangan;',
            'Dengan ceroboh atau sengaja merusak atau membiarkan dalam keadaan bahaya barang milik Perusahaan dan/atau Klien yang menimbulkan kerugian bagi Perusahaan dan/atau Klien;',
            'Membongkar atau membocorkan rahasia Perusahaan dan/atau Klien yang seharusnya dirahasiakan kecuali untuk kepentingan hukum;',
            'Melakukan perbuatan lainnya di lingkungan Perusahaan yang diancam pidana;',
            'Konsultan sudah diperingatkan oleh Perusahaan dan atau Klien baik secara lisan dan tulisan;',
            'Membuat/menimbulkan kesan yang tidak baik terhadap Perusahaan atau rekan proyek pada saat pelaksanaan proyek;',
            'Jika layanan jasa konsultasi atau project telah selesai atau diberhentikan oleh klien karena alasan apapun;',
        ];

        return self::title('PERJANJIAN KERJA SAMA JASA KONSULTAN', 'No. {{nomor_kontrak}}')
            . self::p('Pada tanggal {{tanggal_surat}} di {{kota_penandatanganan}} dibuat Perjanjian Kerja Sama Konsultan (selanjutnya disebut sebagai “Perjanjian”) antara:')
            . self::kv([['Nama', '{{penandatangan}}'], ['Jabatan', '{{jabatan_penandatangan}}']], 22)
            . self::p('Dalam hal ini bertindak secara sah untuk dan atas nama <strong>{{perusahaan}}</strong>, yaitu sebuah perusahaan yang bergerak di bidang Jasa Konsultasi Teknologi Informasi (Selanjutnya disebut "<strong>Perusahaan</strong>"), dengan perseorangan yaitu:')
            . self::kv([
                ['Nama', '<strong>{{nama_konsultan}}</strong>'], ['Tempat/Tgl Lahir', '{{tempat_tanggal_lahir}}'], ['NIK', '{{nomor_identitas}}'],
                ['Jenis Kelamin', '{{jenis_kelamin}}'], ['Alamat', '{{alamat}}'], ['NPWP', '{{npwp}}'],
            ], 22)
            . self::p('Dalam hal ini bertindak untuk dan atas namanya sendiri (Selanjutnya disebut "<strong>Konsultan</strong>").')
            . self::p('<strong>Perusahaan</strong> dan <strong>Konsultan</strong> masing-masing disebut sebagai <strong>Pihak</strong> dan secara bersama-sama disebut sebagai <strong>Para Pihak</strong>.')
            . self::p('Para Pihak menerangkan terlebih dahulu bahwa:')
            . self::list([
                'Perusahaan merupakan suatu badan hukum Perseroan Terbatas (PT) yang bergerak di bidang Jasa Konsultasi Teknologi Informasi, yang dalam hal ini membutuhkan keahlian konsultan untuk kepentingan pelaksanaan proyek kerja dalam masa tertentu.',
                'Konsultan merupakan individu independen yang memiliki keahlian, pengalaman, dan kemampuan profesional di bidang teknologi informasi sesuai kebutuhan proyek.',
                'Klien merupakan pihak ketiga yang menggunakan, menerima, atau memanfaatkan layanan Perusahaan yang terkait dengan pelaksanaan layanan oleh Konsultan.',
                'Para Pihak sepakat untuk mengikatkan diri dalam Perjanjian ini berdasarkan prinsip kerja sama profesional, itikad baik, dan kepatuhan terhadap ketentuan hukum yang berlaku.',
            ], 'upper-roman')
            . self::p('Berdasarkan hal-hal tersebut di atas, Para Pihak sepakat untuk membuat Perjanjian ini dengan ketentuan sebagai berikut:')
            . self::pasal(1, 'RUANG LINGKUP')
            . self::list([
                'Perjanjian ini meliputi seluruh aspek pekerjaan dan layanan jasa yang dibutuhkan untuk proyek milik Perusahaan termasuk pada ketentuan sebagai berikut dengan kualifikasi :'
                . self::kv([
                    ['Posisi', '{{penugasan}}'], ['Tanggal mulai proyek', '{{tanggal_mulai}}'], ['Tanggal berakhir proyek', '{{tanggal_berakhir}}'],
                    ['Jadwal pelaksanaan proyek', 'Senin s/d Jumat'], ['Jumlah waktu pelaksanaan', '{{volume_kerja}}'],
                    ['Model proyek', '{{lokasi_kerja}}'], ['Penempatan', '{{perusahaan_prinsipal}}'],
                ], 40, true),
                'Ruang lingkup teknis, target pekerjaan, standar layanan, dan Hasil Pekerjaan dapat dituangkan lebih lanjut dalam dokumen <em>scope of work</em>, <em>term of reference</em>, <em>work order</em>, <em>timesheet</em>, berita acara, atau dokumen lain yang disetujui Para Pihak.',
                'Konsultan melaksanakan layanan berdasarkan ruang lingkup pekerjaan, standar layanan, jadwal pelaksanaan, serta mekanisme koordinasi Proyek yang disepakati Para Pihak.',
                'Perubahan ruang lingkup, jadwal, lokasi, model pelaksanaan, atau kebutuhan Proyek dilakukan berdasarkan pemberitahuan dan/atau persetujuan tertulis Para Pihak, sepanjang perubahan tersebut diperlukan untuk kelancaran pelaksanaan Proyek.',
                'Perpanjangan Perjanjian ini hanya dapat dilakukan berdasarkan kesepakatan tertulis Para Pihak. Pihak yang bermaksud memperpanjang Perjanjian wajib menyampaikan pemberitahuan kepada pihak lainnya paling lambat 30 (tiga puluh) hari kalender sebelum berakhirnya Perjanjian ini.',
            ])
            . self::pasal(2, 'STATUS HUBUNGAN PARA PIHAK')
            . self::list([
                'Para Pihak sepakat bahwa hubungan hukum yang timbul berdasarkan Perjanjian ini adalah hubungan kerja sama jasa profesional antara Perusahaan dan Konsultan.',
                'Perjanjian ini tidak dimaksudkan, tidak ditafsirkan, serta tidak menimbulkan hubungan kepegawaian, hubungan ketenagakerjaan dalam bentuk apa pun antara Perusahaan dan Konsultan.',
                'Konsultan bertindak sebagai pihak independen dalam melaksanakan Layanan berdasarkan Perjanjian ini.',
                'Konsultan berwenang untuk mewakili, berkoordinasi, menyampaikan informasi, memberikan penjelasan teknis, serta bertindak untuk dan atas nama Perusahaan sepanjang berkaitan langsung dengan pelaksanaan layanan jasa di lingkungan Klien berdasarkan ruang lingkup Perjanjian ini',
                'Konsultan bertanggung jawab atas pemenuhan kewajiban administrasi pribadi, perpajakan pribadi, serta kelengkapan lain yang diperlukan untuk pelaksanaan Layanan, sepanjang',
            ])
            . self::pasal(3, 'NILAI JASA, PENAGIHAN, PEMBAYARAN, DAN PAJAK')
            . self::list([
                'Para Pihak sepakat bahwa nilai Perjanjian ini adalah sebesar <strong>{{tarif}}</strong> {{satuan_tagihan}} (netto/gross).',
                'Nilai Perjanjian sebagaimana dimaksud pada ayat (1) belum termasuk pajak yang berlaku, termasuk namun tidak terbatas pada Pajak Pertambahan Nilai (PPN), apabila dikenakan sesuai ketentuan peraturan perundang-undangan yang berlaku.',
                'Penagihan dilakukan oleh Konsultan setiap bulan setelah Konsultan menyampaikan faktur/invoice dan dokumen pendukung kepada Perusahaan melalui alamat email resmi berikut:'
                . self::list(['To : accounting@eclecticsolusi.com', 'Cc : controller@eclecticsolusi.com; rpmo@eclecticsolusi.com'], 'lower-alpha'),
                'Dokumen pendukung penagihan terdiri atas:'
                . self::list([
                    'faktur/invoice;',
                    'timesheet atau catatan waktu pelaksanaan Proyek yang telah ditandatangani oleh Project Manager, Head of Support, atau perwakilan Perusahaan yang ditunjuk;',
                    'berita acara serah terima, berita acara pekerjaan, atau dokumen lain yang dipersyaratkan Perusahaan sesuai kebutuhan Proyek. (jika ada)',
                ], 'lower-alpha'),
                'Pengajuan invoice dilakukan paling lambat pada tanggal 1 (satu) sampai dengan tanggal 5 (lima) setiap bulan, kecuali disepakati lain secara tertulis oleh Para Pihak.',
                'Pembayaran kepada Konsultan dilakukan paling lambat 15 (lima belas) hari kerja sejak faktur/invoice dan dokumen pendukung diterima secara lengkap, divalidasi, serta disetujui oleh General Manager, Direktur, atau pejabat lain yang berwenang di Perusahaan.',
                'Pemungutan, pemotongan, penyetoran, dan pelaporan pajak yang timbul sehubungan dengan pelaksanaan Perjanjian ini dilakukan sesuai ketentuan perpajakan yang berlaku di Indonesia.',
                'Konsultan tidak berhak atas pembayaran, fasilitas, tunjangan, kompensasi, atau manfaat lain yang tidak secara tegas diatur dalam Perjanjian ini.',
            ])
            . self::pasal(4, 'TUGAS DAN TANGGUNG JAWAB')
            . self::list([
                'Konsultan berkewajiban menjalankan tugas yang diberikan oleh Perusahaan atas pelaksanaan proyek yang mencakup ruang lingkup yang diatur dalam Pasal 1 dalam Perjanjian ini.',
                'Konsultan berkewajiban mengerjakan tugas atas proyek yang dijalani dengan bersungguh-sungguh menurut keahlian dan kemampuan secara maksimal.',
                'Konsultan dilarang, baik secara langsung maupun tidak langsung, melakukan kerja sama, memberikan jasa, menjalin hubungan bisnis, menerima penugasan, atau mengadakan perikatan dalam bentuk apa pun dengan klien maupun pihak ketiga tempat Konsultan ditempatkan oleh Perusahaan, selama masa Perjanjian ini berlaku dan untuk jangka waktu 5 (lima) tahun terhitung sejak tanggal berakhirnya Perjanjian ini.',
                'Konsultan tidak dapat mengalihkan isi perjanjian dan/atau pekerjaan yang diterima, baik sebagian maupun seluruhnya, kepada pihak lain tanpa persetujuan tertulis dari Perusahaan.',
                'Konsultan wajib menjaga kerahasiaan semua informasi milik Perusahaan, baik saat ini maupun di kemudian hari setelah berhenti bekerja, dan dilarang memberitahukan pihak lain segala sesuatu yang berhubungan dengan kegiatan usaha atau kepentingan Perusahaan serta tidak menyalahgunakan rahasia atau informasi yang diperoleh selama bekerja di Perusahaan yang dapat merugikan Perusahaan.',
            ])
            . self::pasal(5, 'HAK DAN KEWAJIBAN PARA PIHAK')
            . self::p('Hak dan Kewajiban Perusahaan:', 'left')
            . self::list([
                'Perusahaan wajib membayar nilai perjanjian atas layanan jasa yang dilakukan oleh Konsultan tiap bulan maksimal di tangggal 15 (Lima Belas ) selama perjanjian ini berlaku.',
                'Perusahaan berhak menerima hasil layanan jasa dari Konsultan sesuai dengan nilai Perjanjian.',
                'Perusahaan berhak mengawasi dan memeriksa layanan jasa yang dikerjakan oleh Konsultan.',
                'Perusahaan berhak menilai secara patut kinerja dan hasil pekerjaan dari Konsultan secara konstan jika diperlukan untuk pertimbangan Perusahaan.',
                'Perusahaan berhak untuk mendapatkan informasi terhadap setiap penundaan layanan jasa yang disebabkan dari urusan pribadi Konsultan termasuk namun tak terbatas dalam hal : sakit, berhalangan hadir, memiliki kegiatan/urusan diluar ruang lingkup perjanjian, dan lain-lain.',
                'Seluruh hasil pekerjaan, laporan, dokumen, metode, sistem, dan material lain yang dihasilkan Konsultan dalam pelaksanaan Perjanjian ini menjadi milik penuh Perusahaan.',
            ], 'lower-alpha')
            . self::p('Hak dan Kewajiban Konsultan:', 'left')
            . self::list($hak, 'lower-alpha')
            . self::pasal(6, 'PENGAKHIRAN')
            . self::list([
                'Perusahaan mempunyai hak untuk mengakhiri kerja sama dengan Konsultan secara sepihak dengan pemberitahuan tertulis jika terjadi kondisi-kondisi di bawah ini termasuk namun tak terbatas pada:' . self::list($terminate, 'lower-alpha'),
                'Konsultan yang bermaksud untuk mengundurkan diri wajib menyampaikan pemberitahuan tertulis kepada Perusahaan paling lambat 1 <strong>(satu ) bulan</strong> sebelum tanggal efektif pengunduran diri, serta dalam hal Konsultan sedang ditugaskan pada suatu proyek, Konsultan wajib melakukan koordinasi dengan Perusahaan untuk memastikan penyelesaian pekerjaan, serah terima tugas, dan pemenuhan seluruh kewajiban serta tanggung jawab yang masih berjalan sebelum tanggal efektif pengunduran diri, sesuai dengan ketentuan Perjanjian ini dan peraturan perundang-undangan yang berlaku.',
            ])
            . self::pasal(7, 'SANKSI')
            . self::list([
                'Apabila terjadi wanprestasi yang dilakukan oleh salah satu pihak yang menyebabkan batalnya perjanjian ini, maka Para Pihak sepakat untuk menyelesaikan dengan musyawarah terlebih dahulu untuk mencapai mufakat;',
                'Jika dalam jangka waktu Perjanjian ini Konsultan melakukan kesalahan dan/atau terpenuhinya kondisi yang diatur dalam Pasal 6 Perjanjian ini, maka Perusahaan akan melakukan pengakhiran Perjanjian;',
                'Apabila Konsultan melanggar ketentuan sebagaimana dimaksud dalam Pasal 4 (empat) ayat 3 dan 4 dalam Perjanjian ini, maka Konsultan dianggap melakukan wanprestasi dan wajib bertanggung jawab penuh atas seluruh kerugian yang timbul, baik langsung maupun tidak langsung, yang diderita oleh Perusahaan;',
                'Perusahaan berhak untuk mengakhiri Perjanjian ini secara sepihak dengan melakukan pemberitahuan terlebih dahulu jika Konsultan terbukti melanggar ketentuan ayat 3 (tiga) dan menuntut ganti rugi dan/atau denda yang besarnya ditetapkan berdasarkan kebijakan Perusahaan, serta mengambil seluruh langkah hukum yang diperlukan, baik secara perdata maupun pidana, sesuai dengan ketentuan peraturan perundang-undangan yang berlaku, tanpa mengesampingkan hak Perusahaan untuk menuntut pemenuhan kewajiban lain berdasarkan Perjanjian ini;',
                'Dalam hal pengakhiran lebih awal, Konsultan hanya berhak atas pembayaran pekerjaan yang telah diselesaikan;',
                'Apabila Konsultan melakukan pelanggaran yang menimbulkan kerugian bagi Perusahaan, maka Konsultan wajib mengganti seluruh kerugian nyata yang timbul, termasuk biaya hukum;',
            ])
            . self::pasal(8, 'KERAHASIAAN')
            . self::list([
                'Dalam hal Perusahaan memiliki Informasi atau materi rahasia termasuk tetapi tidak terbatas pada konsep, teknik, proses, desain, sirkit, data biaya, program komputer, formula, pekerjaan pengembangan atau eksperimen, pekerjaan dalam proses, dan keahlian teknis lainnya, informasi keuangan, pemasaran dan bisnis lainnya, atau rahasia dagang lainnya ("<strong>Informasi Rahasia</strong>") maka Konsultan tidak akan mengungkapkannya kepada pihak mana pun kecuali dimintakan untuk kepentingan Negara.',
                'Konsultan berkewajiban untuk melindungi seluruh Informasi Rahasia Perusahaan dan mengakui bahwa Informasi Rahasia tersebut hanya dipergunakan untuk kepentingan hubungan bisnis Perusahaan.',
                'Setiap Informasi Rahasia yang diungkap baik selama masa Perjanjian maupun setelah masa Perjanjian berakhir tidak dapat dianggap sebagai pemberian hak atau lisensi apapun kepada Konsultan.',
                'Konsultan dilarang mengungkapkan, menyebarkan, atau menggunakan Informasi Rahasia tanpa persetujuan Perusahaan kepada pihak manapun.',
                'Sifat kerahasiaan ini berlaku baik pada saat masa berlaku Perjanjian ataupun 5 (lima) Tahun selesai bekerja pada perusahaan yang ditempatkan di perusahaan Klien.',
                'Apabila suatu saat ditemukan pengungkapan kerahasiaan tersebut, maka Perusahaan berhak untuk menindaklanjuti sesuai dengan jalur hukum yang berlaku.',
            ])
            . self::pasal(9, 'KETENTUAN LAIN-LAIN')
            . self::list(['Para Pihak dan seluruh isi perjanjian ini tunduk pada ketentuan hukum yang berlaku di Republik Indonesia.'])
            . self::p('Setiap perselisihan atau sengketa yang timbul sehubungan dengan pelaksanaan Perjanjian ini wajib diselesaikan terlebih dahulu oleh Para Pihak secara musyawarah untuk mencapai mufakat. Musyawarah sebagaimana dimaksud pada ayat (2) dilakukan dalam jangka waktu paling lama 30 (tiga puluh) hari kalender sejak salah satu Pihak menyampaikan pemberitahuan tertulis kepada Pihak lainnya. Hasil musyawarah dituangkan secara tertulis dan ditandatangani oleh Para Pihak.')
            . self::p('Apabila sengketa tidak dapat diselesaikan secara musyawarah untuk mencapai mufakat dalam jangka waktu 30 (tiga puluh) hari kalender sejak salah satu Pihak menyampaikan pemberitahuan tertulis kepada Pihak lainnya, akan diselesaikan melalui jalur litigasi di Pengadilan Negeri Surabaya sesuai dengan ketentuan hukum yang berlaku.')
            . self::p('Para Pihak dengan ini menyatakan telah membaca dengan teliti, menyetujui, mengerti serta menerima seluruh isi dari Perjanjian ini.')
            . self::p('Para Pihak menyatakan bahwa pada saat menandatangani Perjanjian ini sedang berada di dalam kondisi jasmani/rohani, dan dengan pikiran yang sadar, sehat serta tanpa ada paksaan atau tekanan dari pihak manapun.')
            . self::p('Jika terdapat hal-hal yang belum diatur dalam perjanjian ini, baik penambahan maupun perubahan akan dibuatkan addendum yang disetujui secara tertulis oleh Para Pihak')
            . self::p('Perjanjian Kerja Sama Jasa Konsultansi ini telah dibaca, dipahami, dan disetujui oleh Para Pihak. Perjanjian ini ditandatangani di atas meterai yang cukup dalam 2 (dua) rangkap asli, masing-masing mempunyai kekuatan hukum yang sama.')
            . '<table style="width:100%"><tbody>'
            . '<tr><td style="width:50%;text-align:center"><strong>{{perusahaan}}</strong></td><td style="text-align:center"><strong>Konsultan</strong></td></tr>'
            . '<tr><td style="height:62pt;vertical-align:bottom">{{tanda_tangan_penandatangan}}</td><td style="height:62pt;vertical-align:bottom">{{meterai}}</td></tr>'
            . '<tr><td style="text-align:center">{{penandatangan}}<br>{{jabatan_penandatangan}}</td><td style="text-align:center">{{nama_konsultan}}</td></tr>'
            . '</tbody></table>';
    }
}
