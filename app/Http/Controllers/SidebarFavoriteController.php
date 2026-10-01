<?php

namespace App\Http\Controllers;

use App\Services\Sidebar\SidebarFavoriteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Menyimpan daftar menu favorit sidebar milik karyawan yang sedang login.
 *
 * SATU rute, semantik "replace": browser mengirim daftar lengkap jalur terurut
 * setiap kali pengguna menambah/menghapus bintang. Identitas SELALU dari sesi —
 * request tidak membawa employee_id, sehingga tidak ada jalan mengubah favorit
 * orang lain.
 */
class SidebarFavoriteController extends Controller
{
    public function sync(Request $request, SidebarFavoriteService $favorites)
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'paths'   => ['present', 'array', 'max:20'],
            'paths.*' => ['string', 'max:191'],
        ]);

        try {
            $saved = $favorites->replace((int) $user['id'], $request->input('paths', []));
        } catch (\Throwable $e) {
            Log::error('Sidebar favorites: gagal menyimpan', ['employee_id' => $user['id'] ?? null, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not save your favorites.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Favorites saved.',
            'data'    => ['paths' => $saved, 'max' => SidebarFavoriteService::MAX],
        ]);
    }
}
