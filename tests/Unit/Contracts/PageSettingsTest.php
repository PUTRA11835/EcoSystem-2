<?php

namespace Tests\Unit\Contracts;

use App\Support\Contracts\PageSettings as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Ukuran kertas, orientasi, dan margin dokumen kontrak (HC-D72). */
class PageSettingsTest extends TestCase
{
    public function test_without_settings_the_page_is_exactly_the_old_a4(): void
    {
        $p = P::resolve(null, false);

        $this->assertSame('A4', $p['size']);
        $this->assertSame('portrait', $p['orientation']);
        $this->assertEqualsWithDelta(595.28, $p['width'], 0.01);
        $this->assertEqualsWithDelta(841.89, $p['height'], 0.01);
        $this->assertEqualsWithDelta(56, $p['margins_pt']['left'], 0.1);
        $this->assertEqualsWithDelta(56, $p['margins_pt']['top'], 0.1);
        $this->assertFalse($p['custom_margins']);
    }

    public function test_letterhead_default_margins_match_the_old_fixed_values(): void
    {
        $p = P::resolve(null, true);

        $this->assertEqualsWithDelta(120, $p['margins_pt']['top'], 0.1);
        $this->assertEqualsWithDelta(100, $p['margins_pt']['bottom'], 0.1);
        $this->assertEqualsWithDelta(56, $p['margins_pt']['left'], 0.1);
    }

    #[DataProvider('sizes')]
    public function test_every_size_resolves_to_its_dimensions(string $key, float $w, float $h): void
    {
        $p = P::resolve(['size' => $key], false);

        $this->assertSame($key, $p['size']);
        $this->assertEqualsWithDelta($w, $p['width'], 0.01);
        $this->assertEqualsWithDelta($h, $p['height'], 0.01);
    }

    public static function sizes(): array
    {
        return [['A3', 841.89, 1190.55], ['A4', 595.28, 841.89], ['A5', 419.53, 595.28], ['B5', 498.90, 708.66], ['F4', 609.45, 935.43], ['LETTER', 612, 792], ['LEGAL', 612, 1008]];
    }

    public function test_landscape_swaps_width_and_height(): void
    {
        $p = P::resolve(['size' => 'A3', 'orientation' => 'landscape'], false);

        $this->assertSame('landscape', $p['orientation']);
        $this->assertEqualsWithDelta(1190.55, $p['width'], 0.01);
        $this->assertEqualsWithDelta(841.89, $p['height'], 0.01);
    }

    public function test_unknown_size_and_orientation_fall_back_to_a4_portrait(): void
    {
        $p = P::resolve(['size' => 'POSTER', 'orientation' => 'diagonal'], false);

        $this->assertSame('A4', $p['size']);
        $this->assertSame('portrait', $p['orientation']);
        $this->assertSame('a4', strtolower(P::resolve(['size' => 'a4'], false)['size']));
    }

    public function test_custom_margins_are_used_and_flagged(): void
    {
        $p = P::resolve(['margins' => ['top' => 3, 'left' => 2.5]], false);

        $this->assertTrue($p['custom_margins']);
        $this->assertSame(3.0, $p['margins_cm']['top']);
        $this->assertSame(2.5, $p['margins_cm']['left']);
        $this->assertEqualsWithDelta(P::defaultMarginsCm(false)['right'], $p['margins_cm']['right'], 0.001, 'an empty side keeps its default');
        $this->assertEqualsWithDelta(3 * P::CM, $p['margins_pt']['top'], 0.01);
    }

    public function test_margins_are_clamped_to_the_allowed_range(): void
    {
        $p = P::resolve(['margins' => ['top' => 0.1, 'bottom' => 40, 'left' => -3]], false);

        $this->assertSame(P::MARGIN_MIN_CM, $p['margins_cm']['top']);
        $this->assertLessThanOrEqual(P::MARGIN_MAX_CM, $p['margins_cm']['bottom']);
        $this->assertSame(P::MARGIN_MIN_CM, $p['margins_cm']['left']);
    }

    public function test_the_text_area_never_gets_narrower_than_five_cm(): void
    {
        $p = P::resolve(['size' => 'A5', 'margins' => ['left' => 8, 'right' => 8, 'top' => 8, 'bottom' => 8]], false);

        $this->assertGreaterThanOrEqual(5 - 0.01, $p['width'] / P::CM - $p['margins_cm']['left'] - $p['margins_cm']['right']);
        $this->assertGreaterThanOrEqual(5 - 0.01, $p['height'] / P::CM - $p['margins_cm']['top'] - $p['margins_cm']['bottom']);
    }

    public function test_store_keeps_empty_margins_empty_and_normalises_the_rest(): void
    {
        $s = P::store('legal', 'LANDSCAPE', ['top' => '', 'right' => null, 'bottom' => '2.456', 'left' => 'abc']);

        $this->assertSame('LEGAL', $s['size']);
        $this->assertSame('landscape', $s['orientation']);
        $this->assertNull($s['margins']['top']);
        $this->assertNull($s['margins']['right']);
        $this->assertSame(2.46, $s['margins']['bottom']);
        $this->assertNull($s['margins']['left'], 'a non-number is the same as empty');
    }

    public function test_store_then_resolve_round_trips(): void
    {
        $s = P::store('F4', 'landscape', ['top' => 3, 'right' => null, 'bottom' => null, 'left' => 2]);
        $p = P::resolve($s, true);

        $this->assertSame('F4', $p['size']);
        $this->assertSame('landscape', $p['orientation']);
        $this->assertSame(3.0, $p['margins_cm']['top']);
        $this->assertEqualsWithDelta(P::defaultMarginsCm(true)['bottom'], $p['margins_cm']['bottom'], 0.001, 'an empty margin follows the letterhead default');
    }

    public function test_paper_gives_the_oriented_size_to_dompdf(): void
    {
        $this->assertSame([0, 0, 595.28, 841.89], P::paper(null));
        $this->assertSame([0, 0, 1190.55, 841.89], P::paper(['page' => P::resolve(['size' => 'A3', 'orientation' => 'landscape'], false)]));
    }

    public function test_size_options_list_every_size_with_a_label(): void
    {
        $o = P::sizeOptions();

        $this->assertSame(array_keys(P::SIZES), array_keys($o));
        $this->assertStringContainsString('A4', $o['A4']);
    }
}
