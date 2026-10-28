<?php

namespace Tests\Unit\Guide;

use App\Services\Guide\UserGuideService;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/** Gaya penulisan User Guide: tanpa em dash (U+2014) dan tanpa en dash (U+2013) di isi, teks tampilan, maupun hasil render. */
class UserGuideStyleTest extends TestCase
{
    private const EM = "\u{2014}";
    private const EN = "\u{2013}";

    private function hasDash(string $text): bool
    {
        return str_contains($text, self::EM) || str_contains($text, self::EN);
    }

    public function test_topic_files_contain_no_em_or_en_dash(): void
    {
        $svc = new UserGuideService();

        foreach (['en', 'id'] as $loc) {
            foreach ($svc->files($loc) as $f) {
                $this->assertFalse($this->hasDash((string) file_get_contents($f)), "$loc/" . basename($f));
            }
        }
    }

    public function test_guide_documents_and_ui_strings_contain_no_em_or_en_dash(): void
    {
        foreach (glob(resource_path('guide/_*.md')) ?: [] as $f) {
            $this->assertFalse($this->hasDash((string) file_get_contents($f)), basename($f));
        }

        $this->assertFalse($this->hasDash(json_encode(config('user_guide.ui'), JSON_UNESCAPED_UNICODE)), 'config user_guide.ui');
        $this->assertFalse($this->hasDash(json_encode(config('user_guide.chapters'), JSON_UNESCAPED_UNICODE)), 'config user_guide.chapters');
    }

    public function test_rendered_topics_and_pdf_contain_no_em_or_en_dash(): void
    {
        $svc = new UserGuideService();

        foreach (['en', 'id'] as $loc) {
            $topics = $svc->topics($loc);
            $chapters = $svc->chapters($topics, $loc);
            $ui = config("user_guide.ui.$loc");

            foreach ($topics as $key => $t) {
                $this->assertFalse($this->hasDash($t['html'] . $t['title'] . $t['summary']), "$loc/$key (render)");
            }

            $pdf = View::make('user-guide.pdf', [
                'locale' => $loc, 'ui' => $ui, 'locales' => config('user_guide.locales'), 'chapters' => $chapters,
                'visibleCount' => count($topics), 'totalCount' => count($topics), 'updated' => $svc->lastUpdated($topics),
                'generatedAt' => 'x', 'who' => 'Uji',
            ])->render();
            $this->assertFalse($this->hasDash($pdf), "$loc (pdf view)");
        }
    }
}
