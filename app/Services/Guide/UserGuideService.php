<?php

namespace App\Services\Guide;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * User Guide: membaca topik Markdown (resources/guide/{locale}/*.md), menyaring menurut izin menu pengguna,
 * dan menyajikannya sebagai HTML + daftar isi + indeks pencarian. MURNI file + cache; tidak menulis apa pun.
 *
 * Satu berkas = satu topik. Awal berkas berisi front matter YAML:
 *   key, chapter, order, title, summary, requires (daftar slug menu; kosong = semua pengguna; cukup salah satu), updated (Y-m-d).
 * Tanggal "Updated" di halaman = `updated` terbaru dari topik yang tampil (bukan waktu berkas, yang berubah saat deploy).
 */
final class UserGuideService
{
    public const DIR = 'guide';
    private const CACHE_VER = 'v1';

    /** Sumber tambahan (hasil sunting admin) di atas berkas; null = hanya berkas. */
    public function __construct(private readonly ?TopicOverrides $overrides = null)
    {
    }

    /** Bahasa yang dipakai: ?lang= (sah) → preferensi pengguna (sah) → bawaan. */
    public function resolveLocale(?string $requested, ?string $preference): string
    {
        $allowed = array_keys(config('user_guide.locales'));
        foreach ([$requested, $preference] as $cand) {
            if (is_string($cand) && in_array($cand, $allowed, true)) {
                return $cand;
            }
        }

        return (string) config('user_guide.default_locale', 'en');
    }

    /**
     * Semua topik satu bahasa, diurutkan menurut bab lalu `order`. Topik yang belum ada terjemahannya diisi dari bahasa Inggris
     * (`fallback` = true) supaya pengguna tidak kehilangan topik.
     *
     * @return array<string,array<string,mixed>> key => topik
     */
    public function topics(string $locale): array
    {
        $sig = $this->signature($locale);

        return Cache::remember('user_guide:' . self::CACHE_VER . ":{$locale}:{$sig}", now()->addHours(12), function () use ($locale) {
            $base = $this->load('en', false);
            $mine = $locale === 'en' ? [] : $this->load($locale, false);
            $titleMap = [];
            foreach ($base as $k => $t) {
                $titleMap[$k] = $mine[$k]['title'] ?? $t['title'];
            }
            $out = [];
            foreach ($base as $key => $en) {
                $t = $mine[$key] ?? $en + ['fallback' => $locale !== 'en'];
                $t['fallback'] = $locale !== 'en' && !isset($mine[$key]);
                $out[$key] = $t;
            }
            // Topik yang hanya ada di bahasa non-Inggris tetap ikut.
            foreach ($mine as $key => $t) {
                $out[$key] ??= $t + ['fallback' => false];
            }
            foreach ($out as $key => &$t) {
                $built = $this->render($t['body'], $key, $locale, $titleMap);
                $t['html'] = $built['html'];
                $t['sections'] = $built['sections'];
                $t['headings'] = $built['headings'];
                $t['text'] = $built['text'];
                unset($t['body']);
            }
            unset($t);

            $chapterOrder = array_flip(array_keys(config('user_guide.chapters')));
            uasort($out, fn ($a, $b) => [$chapterOrder[$a['chapter']] ?? 99, $a['order'], $a['key']] <=> [$chapterOrder[$b['chapter']] ?? 99, $b['order'], $b['key']]);

            return $out;
        });
    }

    /**
     * Topik yang boleh dilihat: tanpa `requires` = semua orang; selain itu cukup SATU izin yang dimiliki. Tidak ada pengecualian
     * (tak ada "lihat semua"): siapa pun, termasuk administrator, hanya menerima topik sesuai izin menunya sendiri.
     *
     * @param  array<string,array<string,mixed>>  $topics
     * @param  string[]  $permSlugs
     * @param  string[]  $extra  izin semu yang dimiliki pengguna (mis. "@approver" = ditunjuk sebagai penyetuju di alur persetujuan,
     *                           sehingga menu My Approvals tampil walau tanpa izin menu)
     * @return array<string,array<string,mixed>>
     */
    public function visible(array $topics, array $permSlugs, array $extra = []): array
    {
        $have = array_flip(array_merge($permSlugs, $extra));

        return array_filter($topics, function ($t) use ($have) {
            if (empty($t['requires'])) {
                return true;
            }
            foreach ($t['requires'] as $slug) {
                if (isset($have[$slug])) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Topik dikelompokkan per bab (bab kosong dibuang).
     *
     * @param  array<string,array<string,mixed>>  $visible
     * @return array<string,array{id:string,icon:string,title:string,topics:array<string,array<string,mixed>>}>
     */
    public function chapters(array $visible, string $locale): array
    {
        $out = [];
        $n = 0;
        foreach (config('user_guide.chapters') as $id => $def) {
            $topics = array_filter($visible, fn ($t) => $t['chapter'] === $id);
            if ($topics) {
                $n++;
                // Penomoran mengikuti yang TAMPIL bagi pengguna ini (1, 1.1, 1.2 …), sama di daftar isi, isi, dan PDF.
                $i = 0;
                foreach ($topics as $key => &$t) {
                    $t['no'] = $n . '.' . (++$i);
                }
                unset($t);
                $out[$id] = ['id' => $id, 'no' => (string) $n, 'icon' => $def['icon'], 'title' => $def['title'][$locale] ?? $def['title']['en'], 'topics' => $topics];
            }
        }

        return $out;
    }

    /** Tanggal `updated` terbaru (Y-m-d) dari topik yang ditampilkan. */
    public function lastUpdated(array $visible): ?string
    {
        $dates = array_filter(array_column($visible, 'updated'));

        return $dates ? max($dates) : null;
    }

    /**
     * Indeks pencarian ringan (dikirim ke peramban): satu entri per bagian (h2) tiap topik yang boleh dilihat.
     *
     * @return array<int,array{t:string,k:string,c:string,h:string,a:string,x:string}>
     */
    public function searchIndex(array $visible, array $chapters): array
    {
        $titles = [];
        foreach ($chapters as $c) {
            foreach ($c['topics'] as $k => $_) {
                $titles[$k] = $c['title'];
            }
        }
        $out = [];
        foreach ($visible as $key => $t) {
            foreach ($t['sections'] as $s) {
                $out[] = ['t' => $t['title'], 'k' => $key, 'c' => $titles[$key] ?? '', 'h' => $s['heading'], 'a' => $s['id'], 'x' => $s['text']];
            }
        }

        return $out;
    }

    /** Berkas panduan yang ada (untuk pemeriksa dan tanda tangan cache). @return string[] */
    public function files(string $locale): array
    {
        $dir = resource_path(self::DIR . '/' . $locale);

        return is_dir($dir) ? array_values(array_filter(glob($dir . '/*.md') ?: [], fn ($f) => !str_starts_with(basename($f), '_'))) : [];
    }

    /**
     * Topik satu bahasa tanpa render: berkas bawaan, lalu ditimpa/ditambah hasil sunting bila ada.
     *
     * @return array<string,array<string,mixed>>
     */
    public function load(string $locale, bool $strict): array
    {
        return ($this->overrides?->forLocale($locale) ?? []) + $this->loadFiles($locale, $strict);
    }

    /** Hanya berkas bawaan (tanpa hasil sunting). Dipakai pemeriksa dan editor. @return array<string,array<string,mixed>> */
    public function loadFiles(string $locale, bool $strict = false): array
    {
        $out = [];
        foreach ($this->files($locale) as $file) {
            $parsed = $this->parseFile($file);
            if ($parsed === null) {
                if ($strict) {
                    throw new \RuntimeException('Front matter tidak valid: ' . basename($file));
                }
                continue;
            }
            $out[$parsed['key']] = $parsed + ['file' => basename($file)];
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function parseFile(string $file): ?array
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
        if (!preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $raw, $m)) {
            return null;
        }
        $meta = $this->parseFrontMatter($m[1]);
        if (!is_array($meta) || empty($meta['key']) || empty($meta['chapter']) || empty($meta['title'])) {
            return null;
        }
        $updated = $meta['updated'] ?? null;
        if (is_int($updated)) {
            $updated = date('Y-m-d', $updated);
        }

        return [
            'key'      => (string) $meta['key'],
            'chapter'  => (string) $meta['chapter'],
            'order'    => (int) ($meta['order'] ?? 100),
            'title'    => (string) $meta['title'],
            'summary'  => (string) ($meta['summary'] ?? ''),
            'requires' => array_values(array_map('strval', (array) ($meta['requires'] ?? []))),
            'updated'  => is_string($updated) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $updated) ? $updated : null,
            'body'     => trim($m[2]),
        ];
    }

    /**
     * Front matter sederhana (tanpa paket YAML, karena symfony/yaml hanya ada di lingkungan pengembangan, tidak di produksi):
     * baris `kunci: nilai`; nilai berupa teks (boleh diberi tanda kutip), angka, atau daftar sebaris `[a, "b"]`.
     * Komentar ` # …` di akhir baris diabaikan.
     *
     * @return array<string,mixed>
     */
    public function parseFrontMatter(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n/', $text) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#') || !preg_match('/^([A-Za-z_][A-Za-z0-9_\-]*)\s*:\s*(.*)$/', $line, $m)) {
                continue;
            }
            $out[$m[1]] = $this->scalar(trim($m[2]));
        }

        return $out;
    }

    /** @return string|int|array<int,string> */
    private function scalar(string $v)
    {
        if ($v === '') {
            return '';
        }
        if ($v[0] === '[') {
            $end = strrpos($v, ']');
            $inner = trim(substr($v, 1, ($end === false ? strlen($v) : $end) - 1));
            if ($inner === '') {
                return [];
            }
            $items = [];
            preg_match_all('/\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'|([^,]+))/', $inner, $mm, PREG_SET_ORDER);
            foreach ($mm as $x) {
                $items[] = trim(($x[1] ?? '') !== '' ? $x[1] : (($x[2] ?? '') !== '' ? $x[2] : ($x[3] ?? '')));
            }

            return array_values(array_filter($items, fn ($i) => $i !== ''));
        }
        if ($v[0] === '"' || $v[0] === "'") {
            $close = strpos($v, $v[0], 1);

            return $close === false ? substr($v, 1) : stripcslashes(substr($v, 1, $close - 1));
        }
        $v = preg_replace('/\s+#.*$/', '', $v);
        if (preg_match('/^-?\d+$/', $v)) {
            return (int) $v;
        }

        return trim($v);
    }

    // ── render ────────────────────────────────────────────────────────────

    /**
     * Markdown → HTML. Tambahan: tautan antartopik [[key]] / [[key|teks]], kotak catatan `> [!NOTE|TIP|IMPORTANT|WARNING]`,
     * id pada judul (key--slug), tabel dibungkus agar bisa digulir di layar sempit.
     *
     * @param  array<string,string>  $titleMap  key → judul (bahasa pengguna)
     * @return array{html:string,sections:array<int,array{id:string,heading:string,text:string}>,headings:array<int,array{id:string,text:string,level:int}>,text:string}
     */
    public function render(string $markdown, string $key, string $locale, array $titleMap = []): array
    {
        $markdown = preg_replace_callback('/\[\[([a-z0-9\-]+)(?:\|([^\]]+))?\]\]/', function ($m) use ($titleMap) {
            $label = $m[2] ?? ($titleMap[$m[1]] ?? $m[1]);

            return isset($titleMap[$m[1]]) ? '[' . $label . '](#topic-' . $m[1] . ')' : $label;
        }, $markdown);

        $env = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new TableExtension());
        $env->addExtension(new StrikethroughExtension());
        $html = (new MarkdownConverter($env))->convert($markdown)->getContent();

        $ui = config("user_guide.ui.{$locale}", config('user_guide.ui.en'));
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $dom->getElementById('__root');
        $xp = new \DOMXPath($dom);

        // Kotak catatan
        foreach (iterator_to_array($xp->query('//blockquote')) as $bq) {
            $first = $xp->query('./p[1]', $bq)->item(0);
            if (!$first || !preg_match('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING)\]\s*/i', $first->textContent, $mm)) {
                continue;
            }
            $type = strtolower($mm[1]);
            // buang penanda dari teks (simpul teks pertama)
            $firstText = $first->firstChild;
            if ($firstText && $firstText->nodeType === XML_TEXT_NODE) {
                $firstText->nodeValue = preg_replace('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING)\]\s*/i', '', $firstText->nodeValue);
            }
            $box = $dom->createElement('div');
            $box->setAttribute('class', "callout callout-{$type}");
            $title = $dom->createElement('p');
            $title->appendChild($dom->createTextNode($ui[$type] ?? ucfirst($type)));
            $title->setAttribute('class', 'callout-title');
            $box->appendChild($title);
            while ($bq->firstChild) {
                $box->appendChild($bq->firstChild);
            }
            $bq->parentNode->replaceChild($box, $bq);
        }

        // Tabel dibungkus
        foreach (iterator_to_array($xp->query('//table')) as $tb) {
            $wrap = $dom->createElement('div');
            $wrap->setAttribute('class', 'g-table');
            $tb->parentNode->replaceChild($wrap, $tb);
            $wrap->appendChild($tb);
        }

        // Judul: h1 → h2; beri id
        $used = [];
        $headings = [];
        foreach (iterator_to_array($xp->query('//h1|//h2|//h3')) as $h) {
            $level = $h->nodeName === 'h3' ? 3 : 2;
            if ($h->nodeName === 'h1') {
                $new = $dom->createElement('h2');
                while ($h->firstChild) {
                    $new->appendChild($h->firstChild);
                }
                $h->parentNode->replaceChild($new, $h);
                $h = $new;
            }
            $text = trim($h->textContent);
            $slug = Str::slug($text) ?: 'section';
            $id = "{$key}--{$slug}";
            for ($i = 2; isset($used[$id]); $i++) {
                $id = "{$key}--{$slug}-{$i}";
            }
            $used[$id] = true;
            $h->setAttribute('id', $id);
            $headings[] = ['id' => $id, 'text' => $text, 'level' => $level];
        }

        // Bagian (per h2) untuk pencarian
        $sections = [];
        $cur = ['id' => "topic-{$key}", 'heading' => '', 'text' => ''];
        foreach ($root->childNodes as $node) {
            if ($node->nodeName === 'h2') {
                if (trim($cur['text']) !== '' || $cur['heading'] !== '') {
                    $sections[] = $cur;
                }
                $cur = ['id' => $node->getAttribute('id'), 'heading' => trim($node->textContent), 'text' => ''];
                continue;
            }
            $cur['text'] .= ' ' . preg_replace('/\s+/u', ' ', $node->textContent);
        }
        $sections[] = $cur;
        foreach ($sections as &$s) {
            $s['text'] = trim(preg_replace('/\s+/u', ' ', $s['text']));
        }
        unset($s);

        $inner = '';
        foreach ($root->childNodes as $node) {
            $inner .= $dom->saveHTML($node);
        }

        return ['html' => $inner, 'sections' => $sections, 'headings' => $headings, 'text' => trim(preg_replace('/\s+/u', ' ', $root->textContent))];
    }

    private function signature(string $locale): string
    {
        $parts = [];
        foreach (array_unique(['en', $locale]) as $loc) {
            foreach ($this->files($loc) as $f) {
                $parts[] = $f . '|' . filemtime($f) . '|' . filesize($f);
            }
        }
        $parts[] = (string) filemtime(config_path('user_guide.php'));
        $parts[] = $this->overrides?->version() ?? '';

        return md5(implode("\n", $parts));
    }
}
