<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Penjaga konsistensi config/menu_access.php dengan rute (HC-D63/D64).
 *
 * Halaman Menu Access hanya menampilkan kolom Create/Edit/Delete untuk slug di `crud_enforced`. Bila sebuah rute
 * memakai `menu.can:<slug>,<aksi>` tetapi slug-nya tak terdaftar di sana, halaman akan menyembunyikan kolom yang
 * sebenarnya berfungsi — uji ini gagal agar daftar selalu diperbarui bersama rute.
 */
class MenuAccessConfigTest extends TestCase
{
    public function test_semua_slug_menu_can_terdaftar_di_crud_enforced(): void
    {
        $enforced = config('menu_access.crud_enforced');
        $found = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && str_starts_with($mw, 'menu.can:')) {
                    [$slug, $action] = array_pad(explode(',', substr($mw, 9)), 2, null);
                    $this->assertContains($action, ['view', 'create', 'edit', 'delete'], "aksi tak dikenal pada {$route->uri()}: {$mw}");
                    $found[$slug] = true;
                }
            }
        }

        $this->assertNotEmpty($found, 'tidak ada rute menu.can: ditemukan — pemindaian rusak?');
        $missing = array_values(array_diff(array_keys($found), $enforced));
        $this->assertSame([], $missing, 'slug menu.can: yang belum ada di config/menu_access.php › crud_enforced: ' . implode(', ', $missing));
    }

    public function test_slug_terlindung_digerbang_pada_rute_api_role_dan_menu(): void
    {
        // Regresi celah HC-D63: rute pengubah izin dulu hanya `auth.session`.
        $unguarded = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!preg_match('~^api/(roles|menus)(/|$)~', $uri) || $uri === 'api/my-menus') {
                continue;
            }
            $mws = array_filter($route->gatherMiddleware(), 'is_string');
            if (!collect($mws)->contains(fn ($m) => str_starts_with($m, 'menu:management.'))) {
                $unguarded[] = implode('|', $route->methods()) . ' ' . $uri;
            }
        }
        $this->assertSame([], $unguarded, 'rute role/menu tanpa gerbang menu:management.*: ' . implode('; ', $unguarded));
    }
}
