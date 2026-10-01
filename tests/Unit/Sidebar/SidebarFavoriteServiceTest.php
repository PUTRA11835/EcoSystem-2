<?php

namespace Tests\Unit\Sidebar;

use App\Services\Sidebar\SidebarFavoriteService;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit aturan pembersihan jalur favorit (murni: tanpa database maupun router).
 * Bagian yang bergantung pada router/database diuji lewat uji asap HTTP.
 */
class SidebarFavoriteServiceTest extends TestCase
{
    public function test_jalur_wajar_diterima_dan_urutan_dipertahankan(): void
    {
        $this->assertSame(
            ['/general/onboarding', '/master/employee', '/calendar'],
            SidebarFavoriteService::normalize(['/general/onboarding', '/master/employee', '/calendar'])
        );
    }

    public function test_query_dan_fragmen_dibuang_serta_garis_penutup_disamakan(): void
    {
        $this->assertSame(['/general/onboarding'], SidebarFavoriteService::normalize([
            '/general/onboarding?status=all', '/general/onboarding/', '/general/onboarding#x',
        ]));
    }

    public function test_url_luar_skema_berbahaya_dan_traversal_ditolak(): void
    {
        $bad = [
            'https://evil.example/x', '//evil.example/x', 'javascript:alert(1)', 'data:text/html,x',
            '/../etc/passwd', '/a/../b', '/a//b', 'relative/path', '', '   ', '/with space',
            "/new\nline", "/tab\tchar", '/<script>', '/a"b', "/a'b", "/nul\0byte",
        ];
        $this->assertSame([], SidebarFavoriteService::normalize($bad));
        // Spasi/newline di UJUNG dipangkas (trim) — bukan pintu masuk karakter berbahaya.
        $this->assertSame(['/ok'], SidebarFavoriteService::normalize(["/ok\n"]));
    }

    public function test_bukan_string_diabaikan(): void
    {
        $this->assertSame(['/ok'], SidebarFavoriteService::normalize([123, null, ['/x'], true, '/ok']));
    }

    public function test_aksi_dan_api_tidak_boleh_menjadi_favorit(): void
    {
        $this->assertSame([], SidebarFavoriteService::normalize([
            '/api/employees', '/logout', '/auth/login', '/sidebar/favorites', '/api',
        ]));
        // Awalan yang mirip TIDAK ikut terlarang.
        $this->assertSame(['/apiary/list', '/logbook'], SidebarFavoriteService::normalize(['/apiary/list', '/logbook']));
    }

    public function test_duplikat_dibuang_dan_batas_maksimal_ditegakkan(): void
    {
        $many = [];
        for ($i = 1; $i <= 12; $i++) {
            $many[] = "/menu/item-$i";
            $many[] = "/menu/item-$i"; // duplikat
        }

        $out = SidebarFavoriteService::normalize($many);

        $this->assertCount(SidebarFavoriteService::MAX, $out);
        $this->assertSame('/menu/item-1', $out[0]);
        $this->assertSame('/menu/item-6', $out[5]);
        $this->assertSame(6, SidebarFavoriteService::MAX);
    }

    public function test_jalur_terlalu_panjang_ditolak(): void
    {
        $this->assertSame([], SidebarFavoriteService::normalize(['/' . str_repeat('a', 200)]));
        $this->assertCount(1, SidebarFavoriteService::normalize(['/' . str_repeat('a', 190)]));
    }
}
