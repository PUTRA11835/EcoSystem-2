<?php

namespace App\Http\Controllers;

use App\Models\Wilayah;
use Illuminate\Http\Request;

/**
 * Endpoint referensi wilayah Indonesia untuk dropdown alamat cascading.
 *
 * GET /api/regions/children?parent=<kode>
 *   parent ''         → daftar provinsi          (Region)
 *   parent '34'       → kab/kota provinsi tsb    (City)
 *   parent '34.04'    → kecamatan                (District)
 *   parent '34.04.11' → kelurahan/desa           (Rural/Urban Village)
 *
 * Respons: { success: true, data: [{ code, name }, ...] } terurut menurut nama.
 */
class RegionController extends Controller
{
    public function children(Request $request)
    {
        $parent = trim((string) $request->query('parent', ''));

        // Validasi format kode agar aman untuk klausa LIKE: hanya provinsi,
        // kab/kota, atau kecamatan (level yang punya anak). Selain itu → kosong.
        if ($parent !== '' && !preg_match('/^\d{2}(\.\d{2}){0,2}$/', $parent)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $data = Wilayah::children($parent)->map(fn ($w) => [
            'code' => $w->kode,
            'name' => $w->nama,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
