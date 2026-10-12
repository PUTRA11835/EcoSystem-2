<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Koordinat GPS → alamat, via OpenStreetMap Nominatim (gratis, tanpa API key).
 * Kebijakan Nominatim mewajibkan User-Agent yang jelas dan maks. ~1 request/detik —
 * aman karena dipanggil sekali per timesheet yang dibuat.
 * Gagal = null (non-fatal); UI approval tetap menampilkan koordinat + link Maps.
 */
class ReverseGeocodingService
{
    public function reverse(float $lat, float $lng): ?string
    {
        try {
            $res = Http::timeout(5)
                ->withHeaders(['User-Agent' => config('app.name', 'EcoSystem') . ' timesheet (' . config('app.url') . ')'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format'          => 'jsonv2',
                    'lat'             => $lat,
                    'lon'             => $lng,
                    'zoom'            => 18,
                    'accept-language' => 'id',
                ]);

            if ($res->successful()) {
                $address = $res->json('display_name');
                return is_string($address) && $address !== '' ? $address : null;
            }
        } catch (\Throwable $e) {
            Log::warning('Reverse geocoding failed', ['lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
