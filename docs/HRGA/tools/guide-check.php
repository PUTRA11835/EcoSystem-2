<?php
/**
 * Pemeriksa User Guide (resources/guide/{en,id}/*.md): konsistensi dan kelengkapan. HANYA MEMBACA.
 *
 *   php docs/HRGA/tools/guide-check.php            # semua topik
 *   php docs/HRGA/tools/guide-check.php payroll bpjs   # hanya topik ber-key tersebut (kedua bahasa)
 *
 * GALAT (exit 1): front matter rusak, key ganda, nama berkas ≠ NNN-key, pasangan bahasa hilang, bab tidak dikenal, slug `requires` tidak ada di
 *   tabel menu, tautan [[key]] ke topik yang tidak ada, HTML mentah / tautan keluar / gambar, tanda terima sumber hilang, tanggal `updated` tak sah, badan kosong.
 * PERINGATAN: topik sangat pendek/panjang, topik ID yang hampir identik dengan EN (belum diterjemahkan), slug/istilah teknis bocor, tanpa `## `.
 */
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Guide\UserGuideService;
use Illuminate\Support\Facades\DB;

$only = array_slice($argv, 1);
$svc = app(UserGuideService::class);
$chapters = array_keys(config('user_guide.chapters'));
$slugs = DB::table('menu')->pluck('slug')->flip()->all();
$roleNames = DB::table('employee_role')->pluck('name')->flip()->all();   // untuk izin semu "@role:Nama Role"
$errors = []; $warns = []; $n = 0;
$err = function (string $m) use (&$errors) { $errors[] = $m; };
$warn = function (string $m) use (&$warns) { $warns[] = $m; };

$data = [];
foreach (['en', 'id'] as $loc) {
    foreach ($svc->files($loc) as $file) {
        $base = basename($file);
        $p = $svc->parseFile($file);
        if ($p === null) { $err("[$loc/$base] front matter tidak valid (key, chapter, title wajib)"); continue; }
        if ($only && !in_array($p['key'], $only, true)) { continue; }
        if (isset($data[$loc][$p['key']])) { $err("[$loc/$base] key ganda: {$p['key']}"); }
        $data[$loc][$p['key']] = $p + ['file' => $base, 'raw' => file_get_contents($file)];
    }
}

foreach (['en' => 'id', 'id' => 'en'] as $a => $b) {
    foreach (($data[$a] ?? []) as $key => $t) {
        if (!isset($data[$b][$key])) { $err("[$a/{$t['file']}] tidak ada pasangan di $b/ (key: $key)"); }
    }
}

$allKeys = array_unique(array_merge(array_keys($data['en'] ?? []), array_keys($data['id'] ?? [])));
// Untuk validasi tautan, pakai SEMUA key yang ada walau pemeriksaan dibatasi
foreach (['en', 'id'] as $loc) { foreach ($svc->files($loc) as $f) { $pp = $svc->parseFile($f); if ($pp) { $allKeys[] = $pp['key']; } } }
$allKeys = array_flip(array_unique($allKeys));

foreach ($data as $loc => $topics) {
    foreach ($topics as $key => $t) {
        $n++;
        $tag = "[$loc/{$t['file']}]";
        if (!preg_match('/^\d{3}-' . preg_quote($key, '/') . '\.md$/', $t['file'])) { $err("$tag nama berkas harus NNN-{$key}.md"); }
        if (!in_array($t['chapter'], $chapters, true)) { $err("$tag bab tidak dikenal: {$t['chapter']}"); }
        if ($t['updated'] === null) { $err("$tag `updated` wajib berformat Y-m-d"); }
        if ($t['summary'] === '' || mb_strlen($t['summary']) > 160) { $warn("$tag `summary` kosong atau > 160 karakter"); }
        foreach ($t['requires'] as $s) {
            if (str_starts_with($s, '@role:')) { if (!isset($roleNames[substr($s, 6)])) { $err("$tag requires role tidak ada di tabel employee_role: $s"); } continue; }
            if (!isset($slugs[$s]) && $s !== '@approver') { $err("$tag requires slug tidak ada di tabel menu: $s"); }
        }
        $body = $t['body'];
        if (trim($body) === '') { $err("$tag badan kosong"); continue; }
        $visible = preg_replace('/<!--.*?-->/s', '', $body);
        if (!str_contains($body, '<!-- sources:')) { $err("$tag komentar `<!-- sources: ... -->` hilang"); }
        if (preg_match('/<(?!\/?(?:br)\b)[a-z][^>]*>/i', $visible)) { $err("$tag HTML mentah di badan"); }
        if (preg_match('/https?:\/\//i', $visible)) { $err("$tag tautan keluar/alamat server (dilarang)"); }
        // Gambar: HANYA berkas lokal images/guide/*.png|jpg, dengan teks alternatif, dan berkasnya harus ada (diizinkan di branch yang memakai tangkapan layar).
        if (preg_match_all('/!\[([^\]]*)\]\(([^)\s]*)\)/', $visible, $im, PREG_SET_ORDER)) {
            foreach ($im as $g) {
                [, $alt, $src] = $g;
                if (!preg_match('#^images/guide/[A-Za-z0-9._-]+\.(png|jpe?g)$#', $src)) { $err("$tag gambar hanya boleh berkas lokal images/guide/*.png|jpg: $src"); continue; }
                if (trim($alt) === '') { $err("$tag gambar tanpa teks alternatif: $src"); }
                $img = __DIR__ . '/../../../public/' . $src;
                if (!is_file($img)) { $err("$tag berkas gambar tidak ada: $src"); } elseif (filesize($img) > 600 * 1024) { $warn("$tag gambar > 600 KB: $src"); }
            }
        }
        if (preg_match_all('/\[\[([a-z0-9\-]+)(?:\|[^\]]+)?\]\]/', $visible, $m)) {
            foreach ($m[1] as $lk) { if (!isset($allKeys[$lk])) { $err("$tag tautan [[{$lk}]] ke topik yang tidak ada"); } }
        }
        if (preg_match_all('/^> \[!([A-Z]+)\]/m', $visible, $m)) { foreach ($m[1] as $c) { if (!in_array($c, ['NOTE', 'TIP', 'IMPORTANT', 'WARNING'], true)) { $err("$tag jenis kotak tak sah [!$c]"); } } }
        if (!preg_match('/^## /m', $visible)) { $warn("$tag tidak punya judul `## `"); }
        if (preg_match('/^# /m', $visible)) { $warn("$tag memakai `# ` (judul h1 diturunkan menjadi h2; pakai `## `)"); }
        $words = str_word_count(strip_tags($visible));
        if ($words < 120) { $warn("$tag sangat pendek ($words kata)"); }
        if ($words > 1300) { $warn("$tag sangat panjang ($words kata), pertimbangkan dipecah"); }
        if (preg_match('/\b(?:general|finance|management|control-center|hr_general|reporting|calendar|tickets?|delivery|rpmo|sla|legal|master|ess|my-profile|external\.section|employee\.section)\.[a-z0-9_\-]+(?:\.[a-z0-9_\-]+)*\b/', $visible, $mm) && isset($slugs[$mm[0]])) { $warn("$tag slug teknis bocor di teks: {$mm[0]}"); }
        // ID hampir identik dengan EN?
        if ($loc === 'id' && isset($data['en'][$key])) {
            $en = preg_split('/\R/', preg_replace('/<!--.*?-->/s', '', $data['en'][$key]['body'])); $id = preg_split('/\R/', $visible);
            $same = 0; $tot = 0; foreach ($id as $ln) { $ln = trim($ln); if (mb_strlen($ln) < 25) continue; $tot++; if (in_array($ln, array_map('trim', $en), true)) $same++; }
            if ($tot > 3 && $same / $tot > 0.4) { $warn("$tag tampak belum diterjemahkan ($same/$tot baris sama dengan EN)"); }
            // Struktur harus sejajar: jumlah judul ## sama
            $hEn = preg_match_all('/^## /m', $data['en'][$key]['body']); $hId = preg_match_all('/^## /m', $visible);
            if ($hEn !== $hId) { $warn("$tag jumlah judul `## ` ($hId) beda dengan EN ($hEn)"); }
            if (substr_count($data['en'][$key]['body'], '![') !== substr_count($visible, '![')) { $warn("$tag jumlah gambar beda dengan EN"); }
        }
    }
}

foreach ($errors as $e) echo "  [GALAT]      $e\n";
foreach ($warns as $w) echo "  [PERINGATAN] $w\n";
echo "\nTopik diperiksa: " . ($n / 2) . " pasang ($n berkas) · galat: " . count($errors) . " · peringatan: " . count($warns) . "\n";
exit($errors ? 1 : 0);
