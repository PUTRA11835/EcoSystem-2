<?php

namespace Tests\Unit\Guide;

use App\Http\Controllers\UserGuideController;
use App\Services\Guide\UserGuideService;
use Tests\TestCase;

/** Gambar User Guide: hanya berkas lokal images/guide/*, ada di disk, sama di kedua bahasa, dan tersisip sebagai data URI di PDF. */
class UserGuideImagesTest extends TestCase
{
    private UserGuideService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new UserGuideService();
    }

    /** @return array<string,string[]> berkas => daftar sumber gambar */
    private function imagesPerFile(string $locale): array
    {
        $out = [];
        foreach ($this->svc->files($locale) as $f) {
            preg_match_all('/!\[[^\]]*\]\(([^)\s]*)\)/', (string) file_get_contents($f), $m);
            $out[basename($f)] = $m[1];
        }

        return $out;
    }

    public function test_every_referenced_image_is_a_local_guide_image_that_exists(): void
    {
        foreach (['en', 'id'] as $loc) {
            foreach ($this->imagesPerFile($loc) as $file => $sources) {
                foreach ($sources as $src) {
                    $this->assertMatchesRegularExpression('#^images/guide/[A-Za-z0-9._-]+\.(png|jpe?g)$#', $src, "$loc/$file");
                    $this->assertFileExists(public_path($src), "$loc/$file -> $src");
                }
            }
        }
    }

    public function test_both_languages_use_the_same_images_in_the_same_order(): void
    {
        $this->assertSame($this->imagesPerFile('en'), $this->imagesPerFile('id'));
    }

    public function test_every_image_has_alternative_text(): void
    {
        foreach (['en', 'id'] as $loc) {
            foreach ($this->svc->files($loc) as $f) {
                preg_match_all('/!\[([^\]]*)\]\(/', (string) file_get_contents($f), $m);
                foreach ($m[1] as $alt) {
                    $this->assertNotSame('', trim($alt), basename($f));
                }
            }
        }
    }

    public function test_images_render_as_img_tags_with_the_relative_source(): void
    {
        $r = $this->svc->render('![Alt text](images/guide/my-profile.png)', 'demo', 'en');

        $this->assertStringContainsString('<img src="images/guide/my-profile.png" alt="Alt text"', $r['html']);
    }

    public function test_pdf_inlines_guide_images_as_data_uris_and_leaves_unknown_ones(): void
    {
        $topics = [
            'a' => ['html' => '<p><img src="images/guide/my-profile.png" alt="x"></p><img src="images/guide/does-not-exist.png" alt="y">'],
        ];
        $chapters = ['c' => ['topics' => $topics]];

        $m = new \ReflectionMethod(UserGuideController::class, 'inlineImages');
        $m->setAccessible(true);
        $out = $m->invoke(new UserGuideController($this->svc), $chapters);
        $html = $out['c']['topics']['a']['html'];

        $this->assertStringContainsString('src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('src="images/guide/my-profile.png"', $html);
        $this->assertStringContainsString('src="images/guide/does-not-exist.png"', $html);   // berkas tak ada: dibiarkan, tidak merusak PDF
    }

    public function test_image_files_stay_small_enough_for_the_pdf(): void
    {
        foreach (glob(public_path('images/guide/*')) ?: [] as $f) {
            $this->assertLessThanOrEqual(600 * 1024, filesize($f), basename($f));
        }
    }
}
