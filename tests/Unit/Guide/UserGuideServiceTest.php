<?php

namespace Tests\Unit\Guide;

use App\Services\Guide\UserGuideService;
use Tests\TestCase;

/** User Guide: bahasa, penyaringan menurut izin, render Markdown (kotak catatan, tautan, id judul, tabel), tanggal terbaru. */
class UserGuideServiceTest extends TestCase
{
    private UserGuideService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new UserGuideService();
    }

    public function test_locale_prefers_request_then_preference_then_default(): void
    {
        $this->assertSame('id', $this->svc->resolveLocale('id', 'en'));
        $this->assertSame('id', $this->svc->resolveLocale(null, 'id'));
        $this->assertSame('en', $this->svc->resolveLocale('xx', 'zz'));      // tak sah → bawaan
        $this->assertSame('en', $this->svc->resolveLocale(null, null));
    }

    public function test_visibility_is_any_of_and_empty_means_everyone(): void
    {
        $topics = [
            'a' => ['requires' => []],
            'b' => ['requires' => ['x.one', 'x.two']],
            'c' => ['requires' => ['y.only']],
        ];

        $this->assertSame(['a'], array_keys($this->svc->visible($topics, [])));
        $this->assertSame(['a', 'b'], array_keys($this->svc->visible($topics, ['x.two'])));
        $this->assertSame(['a', 'b', 'c'], array_keys($this->svc->visible($topics, ['x.one', 'y.only'])));
        $this->assertSame(['a', 'b'], array_keys($this->svc->visible($topics, [], ['x.one'])));    // izin semu (mis. "@approver") dihitung sama dengan izin menu
        $this->assertSame(['a'], array_keys($this->svc->visible($topics, ['unrelated'])));          // tidak ada jalur "lihat semua": hanya izin yang cocok yang membuka topik
    }

    public function test_render_builds_callouts_ids_tables_and_topic_links(): void
    {
        $md = <<<'MD'
# Title becomes h2

Open `Settings` first. See [[other-topic]] and [[missing-topic]].

## Step by step

1. One
2. Two

### A subtask

> [!WARNING]
> This cannot be undone.

| A | B |
|---|---|
| 1 | 2 |

<script>alert(1)</script>
MD;
        $r = $this->svc->render($md, 'demo', 'en', ['other-topic' => 'Other topic']);

        $this->assertStringContainsString('<a href="#topic-other-topic">Other topic</a>', $r['html']);
        $this->assertStringNotContainsString('missing-topic]]', $r['html']);          // key tak dikenal → teks biasa, bukan tautan rusak
        $this->assertStringContainsString('class="callout callout-warning"', $r['html']);
        $this->assertStringContainsString('<p class="callout-title">Warning</p>', $r['html']);
        $this->assertStringNotContainsString('[!WARNING]', $r['html']);
        $this->assertStringContainsString('<div class="g-table"><table>', str_replace("\n", '', $r['html']));
        $this->assertStringNotContainsString('<script', $r['html']);                  // HTML mentah dibuang
        $this->assertStringContainsString('<code>Settings</code>', $r['html']);

        $ids = array_column($r['headings'], 'id');
        $this->assertContains('demo--title-becomes-h2', $ids);                        // h1 diturunkan jadi h2 dan diberi id
        $this->assertContains('demo--step-by-step', $ids);
        $this->assertContains('demo--a-subtask', $ids);
        $this->assertSame(3, $r['headings'][2]['level']);
        $this->assertCount(2, $r['sections']);      // satu per judul h2 (h3 ikut bagian induknya)
    }

    public function test_front_matter_parser_handles_the_supported_syntax_without_a_yaml_package(): void
    {
        $m = $this->svc->parseFrontMatter(<<<'FM'
key: demo-topic
chapter: my-workspace          # komentar di akhir baris
order: 40
title: Reports: overview & more
summary: "Quoted: with colon, and # hash"
requires: [general.approvals, "@approver", 'ess.my_leave_permit']
empty: []
updated: 2026-10-27
FM);

        $this->assertSame('demo-topic', $m['key']);
        $this->assertSame('my-workspace', $m['chapter']);
        $this->assertSame(40, $m['order']);
        $this->assertSame('Reports: overview & more', $m['title']);          // titik dua di dalam nilai dipertahankan
        $this->assertSame('Quoted: with colon, and # hash', $m['summary']);
        $this->assertSame(['general.approvals', '@approver', 'ess.my_leave_permit'], $m['requires']);
        $this->assertSame([], $m['empty']);
        $this->assertSame('2026-10-27', $m['updated']);                      // tetap teks (bukan tanggal), format Y-m-d
    }

    public function test_every_real_topic_parses_with_valid_metadata(): void
    {
        foreach (['en', 'id'] as $loc) {
            foreach ($this->svc->files($loc) as $f) {
                $p = $this->svc->parseFile($f);
                $this->assertNotNull($p, basename($f));
                $this->assertIsArray($p['requires'], basename($f));
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $p['updated'], basename($f));
            }
        }
    }

    public function test_chapters_and_topics_are_numbered_by_what_the_user_sees(): void
    {
        $visible = [
            'a' => ['chapter' => 'my-workspace', 'title' => 'A'],
            'b' => ['chapter' => 'my-workspace', 'title' => 'B'],
            'c' => ['chapter' => 'finance', 'title' => 'C'],
        ];
        $ch = $this->svc->chapters($visible, 'en');

        $this->assertSame(['my-workspace', 'finance'], array_keys($ch));          // urutan bab mengikuti config, bab kosong dibuang
        $this->assertSame('1', $ch['my-workspace']['no']);
        $this->assertSame('2', $ch['finance']['no']);                              // bab Getting started tidak tampil → penomoran tidak berlubang
        $this->assertSame(['1.1', '1.2'], array_column($ch['my-workspace']['topics'], 'no'));
        $this->assertSame('2.1', $ch['finance']['topics']['c']['no']);
    }

    public function test_indonesian_callout_title_is_localized(): void
    {
        $r = $this->svc->render("> [!TIP]\n> Gunakan pencarian.", 'demo', 'id');

        $this->assertStringContainsString('<p class="callout-title">Tips</p>', $r['html']);
    }

    public function test_duplicate_headings_get_unique_ids(): void
    {
        $r = $this->svc->render("## Notes\n\ntext\n\n## Notes\n\ntext", 'demo', 'en');

        $this->assertSame(['demo--notes', 'demo--notes-2'], array_column($r['headings'], 'id'));
    }

    public function test_last_updated_is_the_newest_visible_topic_date(): void
    {
        $this->assertSame('2026-10-30', $this->svc->lastUpdated([['updated' => '2026-10-27'], ['updated' => '2026-10-30'], ['updated' => null]]));
        $this->assertNull($this->svc->lastUpdated([]));
    }

    public function test_every_chapter_in_content_exists_in_config_and_pairs_match(): void
    {
        $en = $this->svc->load('en', false);
        $id = $this->svc->load('id', false);
        $chapters = array_keys(config('user_guide.chapters'));

        foreach ($en as $key => $t) {
            $this->assertContains($t['chapter'], $chapters, "bab tak dikenal pada topik {$key}");
        }
        $this->assertSame(array_keys($en), array_keys($id), 'daftar topik EN dan ID harus sama');
    }
}
