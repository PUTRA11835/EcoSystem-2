<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Services\Attendance\GeocodingService;
use Illuminate\Http\Request;

/**
 * Proxy pencarian alamat ke Nominatim (OpenStreetMap).
 *
 * Browser tidak boleh memanggil Nominatim langsung: kebijakan mereka
 * mewajibkan header User-Agent yang mengidentifikasi aplikasi, dan browser
 * tidak dapat mengaturnya. Lihat GeocodingService untuk uraian lengkapnya.
 *
 * Dilindungi izin `general.settings.branches.manage` karena hanya dipakai saat
 * mengisi data cabang — bukan endpoint umum, dan tidak boleh menjadi jalur
 * bagi siapa pun untuk memakai kuota Nominatim atas nama kantor.
 */
class GeoLookupController extends Controller
{
    public function search(Request $request, GeocodingService $geocoding)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        $result = $geocoding->search($validated['q']);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data'    => $result['results'],
        ]);
    }

    public function reverse(Request $request, GeocodingService $geocoding)
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $geocoding->reverse((float) $validated['lat'], (float) $validated['lng']);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data'    => $result['address'],
        ]);
    }
}
