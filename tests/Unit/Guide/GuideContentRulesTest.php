<?php

namespace Tests\Unit\Guide;

use App\Services\Guide\GuideContentRules;
use PHPUnit\Framework\TestCase;

/** Aturan isi topik yang disunting lewat UI (murni, tanpa database). */
class GuideContentRulesTest extends TestCase
{
    private string $imageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'guide-rules-' . uniqid();
        mkdir($this->imageDir);
        file_put_contents($this->imageDir . '/ok.png', 'x');
    }

    protected function tearDown(): void
    {
        @unlink($this->imageDir . '/ok.png');
        @rmdir($this->imageDir);
        parent::tearDown();
    }

    private function rules(): GuideContentRules
    {
        return new GuideContentRules(['other', 'mine'], ['getting-started'], ['rpmo.periods'], ['Delivery RPMO Head'], $this->imageDir);
    }

    private function draft(array $over = []): array
    {
        return $over + ['key' => 'mine', 'chapter' => 'getting-started', 'title' => 'Title', 'summary' => 'S', 'requires' => [], 'body' => "## Hello\n\nText."];
    }

    public function test_a_clean_draft_passes(): void
    {
        $this->assertSame([], $this->rules()->violations($this->draft([
            'requires' => ['rpmo.periods', '@approver', '@role:Delivery RPMO Head'],
            'body'     => "See [[other]].\n\n![Screen](images/guide/ok.png)\n\n<!-- sources: x -->",
        ])));
    }

    public function test_em_and_en_dashes_are_rejected_everywhere(): void
    {
        $dash = "\u{2014}";
        $e = $this->rules()->violations($this->draft(['title' => "A $dash B", 'summary' => "A \u{2013} B", 'body' => "x $dash y"]));
        $this->assertArrayHasKey('title', $e);
        $this->assertArrayHasKey('summary', $e);
        $this->assertArrayHasKey('body', $e);
    }

    public function test_key_chapter_and_required_fields(): void
    {
        $e = $this->rules()->violations($this->draft(['key' => 'Bad Key', 'chapter' => 'nope', 'title' => ' ', 'body' => '']));
        $this->assertSame(['key', 'chapter', 'title', 'body'], array_keys($e));
    }

    public function test_requires_must_reference_known_menus_roles_or_approver(): void
    {
        $e = $this->rules()->violations($this->draft(['requires' => ['ghost.menu', '@role:Nobody', '@approver']]));
        $this->assertCount(2, $e['requires']);
    }

    public function test_raw_html_and_external_links_are_rejected_but_html_comments_are_not(): void
    {
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'x <script>alert(1)</script>'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'x <b>bold</b>'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => '[go](https://example.com)'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => '[go](javascript:alert(1))'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => '[go](//evil.test/x)'])));
        $this->assertSame([], $this->rules()->violations($this->draft(['body' => "ok <!-- note -->"])));
    }

    public function test_images_need_alt_text_an_allowed_extension_and_an_existing_file(): void
    {
        foreach (['![](images/guide/ok.png)', '![a](images/guide/missing.png)', '![a](images/guide/ok.gif)', '![a](https://x.test/a.png)', '![a](images/other/ok.png)', '![a](images/guide/../ok.png)'] as $body) {
            $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => $body])), $body);
        }
    }

    public function test_topic_links_must_exist_and_not_point_to_itself(): void
    {
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'See [[ghost]].'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'See [[mine]].'])));
        $this->assertSame([], $this->rules()->violations($this->draft(['body' => 'See [[other|the other topic]].'])));
    }
}
