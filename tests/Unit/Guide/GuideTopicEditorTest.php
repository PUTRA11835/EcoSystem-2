<?php

namespace Tests\Unit\Guide;

use App\Models\UserGuideTopic;
use App\Services\Guide\DatabaseTopicOverrides;
use App\Services\Guide\GuideTopicEditor;
use App\Services\Guide\UserGuideService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Lapisan sunting: timpa topik berkas, topik baru, kembali ke bawaan, status "default berubah", dan efeknya pada UserGuideService. */
class GuideTopicEditorTest extends TestCase
{
    private const KEY = 'welcome';

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not loaded (run: php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit).');
        }
        (require base_path('database/migrations/2026_10_28_000001_create_user_guide_topics_table.php'))->up();
        Schema::create('menu', fn ($t) => $t->string('slug'));
        Schema::create('employee_role', fn ($t) => $t->string('name'));
        Cache::flush();
    }

    private function editor(): GuideTopicEditor
    {
        return new GuideTopicEditor($this->service());
    }

    private function service(): UserGuideService
    {
        // Instans baru per panggilan: DatabaseTopicOverrides membaca database sekali per instans.
        return new UserGuideService(new DatabaseTopicOverrides());
    }

    private function data(array $over = []): array
    {
        return $over + ['chapter' => 'getting-started', 'order' => 7, 'title' => 'Edited title', 'summary' => 'Edited summary', 'requires' => [], 'body' => "## Edited\n\nNew text."];
    }

    public function test_an_untouched_topic_is_default_and_comes_from_the_file(): void
    {
        $row = collect($this->editor()->rows('en'))->firstWhere('key', self::KEY);
        $this->assertSame('default', $row['state']);
        $this->assertFalse($row['stale']);
        $this->assertNotSame('Edited title', $this->service()->topics('en')[self::KEY]['title']);
    }

    public function test_saving_overrides_the_file_topic_for_readers_in_that_language_only(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(), 5);

        $en = $this->service()->topics('en')[self::KEY];
        $this->assertSame('Edited title', $en['title']);
        $this->assertStringContainsString('New text.', $en['html']);
        $this->assertSame(7, $en['order']);
        $this->assertNotSame('Edited title', $this->service()->topics('id')[self::KEY]['title']);
        $this->assertSame('edited', collect($this->editor()->rows('en'))->firstWhere('key', self::KEY)['state']);
        $this->assertSame(5, UserGuideTopic::first()->edited_by);
    }

    public function test_a_new_edit_busts_the_cache_even_within_the_same_second(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(['title' => 'First']), null);
        $this->assertSame('First', $this->service()->topics('en')[self::KEY]['title']);

        $this->editor()->save(self::KEY, 'en', $this->data(['title' => 'Second']), null);
        $this->assertSame('Second', $this->service()->topics('en')[self::KEY]['title']);
    }

    public function test_reset_restores_the_file_version(): void
    {
        $original = $this->service()->topics('en')[self::KEY]['title'];
        $this->editor()->save(self::KEY, 'en', $this->data(), null);

        $this->assertTrue($this->editor()->reset(self::KEY, 'en'));
        $this->assertSame($original, $this->service()->topics('en')[self::KEY]['title']);
        $this->assertFalse($this->editor()->reset(self::KEY, 'en'));
    }

    public function test_a_custom_topic_is_added_and_deleting_it_removes_it(): void
    {
        $this->editor()->save('brand-new', 'en', $this->data(['title' => 'Brand new']), null);

        $this->assertArrayHasKey('brand-new', $this->service()->topics('en'));
        $this->assertSame('custom', collect($this->editor()->rows('en'))->firstWhere('key', 'brand-new')['state']);
        $this->assertNull(UserGuideTopic::where('key', 'brand-new')->first()->base_hash);

        $this->editor()->reset('brand-new', 'en');
        $this->assertArrayNotHasKey('brand-new', $this->service()->topics('en'));
    }

    public function test_a_changed_bundled_file_marks_the_edit_as_stale(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(), null);
        $this->assertFalse(collect($this->editor()->rows('en'))->firstWhere('key', self::KEY)['stale']);

        UserGuideTopic::where('key', self::KEY)->update(['base_hash' => str_repeat('0', 32)]);
        $this->assertTrue(collect($this->editor()->rows('en'))->firstWhere('key', self::KEY)['stale']);
        $this->assertTrue($this->editor()->find(self::KEY, 'en')['stale']);
    }

    public function test_the_base_hash_is_captured_once_and_kept_on_later_saves(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(), null);
        UserGuideTopic::where('key', self::KEY)->update(['base_hash' => str_repeat('a', 32)]);
        $this->editor()->save(self::KEY, 'en', $this->data(['title' => 'Again']), null);

        $this->assertSame(str_repeat('a', 32), UserGuideTopic::first()->base_hash);
    }

    public function test_a_missing_translation_starts_from_the_english_text_and_is_flagged(): void
    {
        $this->assertNull($this->editor()->find('brand-new-en-only', 'id'));

        $this->editor()->save('only-english', 'en', $this->data(), null);
        $this->assertTrue($this->editor()->find('only-english', 'id') === null || $this->editor()->find('only-english', 'id')['borrowed']);
    }

    public function test_requires_are_stored_and_enforced_by_the_engine(): void
    {
        $this->editor()->save('gated', 'en', $this->data(['requires' => ['@role:Some Role']]), null);

        $topics = $this->service()->topics('en');
        $this->assertArrayNotHasKey('gated', $this->service()->visible($topics, []));
        $this->assertArrayHasKey('gated', $this->service()->visible($topics, [], ['@role:Some Role']));
    }

    public function test_the_engine_still_works_when_the_table_is_missing(): void
    {
        Schema::drop('user_guide_topics');
        Cache::flush();

        $this->assertArrayHasKey(self::KEY, $this->service()->topics('en'));
    }

    public function test_the_preview_renders_images_from_public_and_links_known_topics(): void
    {
        $html = $this->editor()->preview("See [[" . self::KEY . "]].\n\n![Alt](images/guide/example.png)", 'x', 'en');

        $this->assertStringContainsString('src="' . asset('images/guide') . '/example.png"', $html);
        $this->assertStringContainsString('href="#topic-' . self::KEY . '"', $html);
    }

    public function test_link_targets_feed_the_visual_editor_with_titles_and_chapter_names(): void
    {
        $this->editor()->save('custom-link', 'en', $this->data(['title' => 'Custom link target']), null);

        $targets = $this->editor()->linkTargets('en');
        $this->assertSame('Custom link target', $targets['titles']['custom-link']);
        $this->assertArrayHasKey(self::KEY, $targets['titles']);
        $row = collect($targets['topics'])->firstWhere('key', 'custom-link');
        $this->assertSame('Getting started', $row['chapter']);
        $this->assertSame('Memulai', collect($this->editor()->linkTargets('id')['topics'])->firstWhere('key', self::KEY)['chapter']);
    }

    public function test_the_content_rules_see_database_topics_and_reference_data(): void
    {
        DB::table('menu')->insert(['slug' => 'rpmo.periods']);
        DB::table('employee_role')->insert(['name' => 'Delivery RPMO Head']);
        $this->editor()->save('custom-one', 'en', $this->data(), null);

        $ok = $this->editor()->rules()->violations([
            'key' => 'x', 'chapter' => 'getting-started', 'title' => 'T', 'summary' => '', 'requires' => ['rpmo.periods', '@role:Delivery RPMO Head'],
            'body' => 'See [[custom-one]] and [[' . self::KEY . ']].',
        ]);
        $this->assertSame([], $ok);
        $this->assertTrue($this->editor()->keyExists('custom-one'));
        $this->assertTrue($this->editor()->keyExists(self::KEY));
        $this->assertFalse($this->editor()->keyExists('never-used'));
    }
}
