<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pencarian alamat <-> koordinat lewat Nominatim (OpenStreetMap).
 *
 * KENAPA LEWAT BACKEND, BUKAN LANGSUNG DARI BROWSER:
 * kebijakan Nominatim mewajibkan setiap aplikasi mengidentifikasi diri lewat
 * header User-Agent, dan browser TIDAK DAPAT mengatur header itu — spesifikasi
 * fetch mengunci User-Agent sebagai forbidden header. Memanggil dari server
 * juga memberi tiga hal yang tidak mungkin didapat dari sisi klien: throttle
 * terpusat, cache bersama antar pengguna, dan satu titik ganti bila kelak
 * pindah penyedia.
 *
 * BATASAN YANG DIPATUHI (https://operations.osmfoundation.org/policies/nominatim/):
 *  - maksimum 1 permintaan per detik  -> throttle di bawah
 *  - wajib User-Agent yang jelas      -> config('geocoding.nominatim.user_agent')
 *  - dilarang autocomplete saat mengetik -> UI memakai TOMBOL "Cari Lokasi",
 *    bukan pencarian otomatis. Ini bukan pilihan gaya, melainkan syarat.
 *  - dilarang geocoding massal        -> tidak relevan; pemakaian hanya
 *    beberapa kali saat mendaftarkan kantor
 *
 * CATATAN PENTING: layanan ini TIDAK ada hubungannya dengan perhitungan
 * geofence. Presensi karyawan tidak pernah memanggil Nominatim — koordinatnya
 * datang dari GPS perangkat, dan jaraknya dihitung GeofenceService secara
 * lokal. Bila Nominatim mati, presensi tetap berjalan normal; yang hilang
 * hanya kemudahan mencari alamat saat admin mengisi data cabang.
 */
class GeocodingService
{
    private const THROTTLE_KEY = 'nominatim:last_request_at';

    /**
     * Nama provinsi dari kode ISO 3166-2:ID.
     *
     * Nominatim tidak selalu mengembalikan kunci `state`. Untuk Jakarta,
     * misalnya, balasannya hanya berisi `city = Jakarta Selatan` dan
     * `ISO3166-2-lvl4 = ID-JK` — tanpa tabel ini kolom Provinsi selalu kosong
     * justru di kota tempat kantor pusat berada.
     */
    private const ISO_PROVINCES = [
        'ID-AC' => 'Aceh',                      'ID-BA' => 'Bali',
        'ID-BB' => 'Kepulauan Bangka Belitung', 'ID-BE' => 'Bengkulu',
        'ID-BT' => 'Banten',                    'ID-GO' => 'Gorontalo',
        'ID-JA' => 'Jambi',                     'ID-JB' => 'Jawa Barat',
        'ID-JI' => 'Jawa Timur',                'ID-JK' => 'DKI Jakarta',
        'ID-JT' => 'Jawa Tengah',               'ID-KB' => 'Kalimantan Barat',
        'ID-KI' => 'Kalimantan Timur',          'ID-KR' => 'Kepulauan Riau',
        'ID-KS' => 'Kalimantan Selatan',        'ID-KT' => 'Kalimantan Tengah',
        'ID-KU' => 'Kalimantan Utara',          'ID-LA' => 'Lampung',
        'ID-MA' => 'Maluku',                    'ID-MU' => 'Maluku Utara',
        'ID-NB' => 'Nusa Tenggara Barat',       'ID-NT' => 'Nusa Tenggara Timur',
        'ID-PA' => 'Papua',                     'ID-PB' => 'Papua Barat',
        'ID-PD' => 'Papua Barat Daya',          'ID-PE' => 'Papua Pegunungan',
        'ID-PS' => 'Papua Selatan',             'ID-PT' => 'Papua Tengah',
        'ID-RI' => 'Riau',                      'ID-SA' => 'Sulawesi Utara',
        'ID-SB' => 'Sumatera Barat',            'ID-SG' => 'Sulawesi Tenggara',
        'ID-SN' => 'Sulawesi Selatan',          'ID-SR' => 'Sulawesi Barat',
        'ID-SS' => 'Sumatera Selatan',          'ID-ST' => 'Sulawesi Tengah',
        'ID-SU' => 'Sumatera Utara',            'ID-YO' => 'DI Yogyakarta',
    ];

    /**
     * Cari koordinat dari teks alamat / nama gedung.
     *
     * @return array{ok: bool, results: array<int, array{label:string, latitude:float, longitude:float, type:string}>, message: string}
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return $this->failure('Please enter at least 3 characters to search.');
        }

        $cacheKey = 'geocode:search:' . md5(mb_strtolower($query));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return ['ok' => true, 'results' => $cached, 'message' => ''];
        }

        $config = config('geocoding.nominatim');

        $params = [
            'q'              => $query,
            'format'         => 'jsonv2',
            'limit'          => $config['max_results'],
            'addressdetails' => 1,
        ];

        if (!empty($config['country_codes'])) {
            $params['countrycodes'] = $config['country_codes'];
        }

        $response = $this->request($config['base_url'] . '/search', $params);

        if ($response === null) {
            return $this->failure('Location search is unavailable right now. Enter the coordinates manually or click directly on the map.');
        }

        $results = collect($response)
            ->map(fn ($item) => [
                'label'     => (string) ($item['display_name'] ?? ''),
                'latitude'  => (float) ($item['lat'] ?? 0),
                'longitude' => (float) ($item['lon'] ?? 0),
                'type'      => (string) ($item['type'] ?? ''),
            ])
            ->filter(fn ($item) => $item['latitude'] !== 0.0 || $item['longitude'] !== 0.0)
            ->values()
            ->all();

        Cache::put($cacheKey, $results, now()->addHours($config['cache_ttl_hours']));

        return [
            'ok'      => true,
            'results' => $results,
            'message' => $results === [] ? 'No location found. Try different keywords or click directly on the map.' : '',
        ];
    }

    /**
     * Cari alamat dari koordinat. Dipakai untuk mengisi Kota / Provinsi /
     * Alamat secara otomatis saat admin mengklik peta.
     *
     * @return array{ok: bool, address: array{label:string, city:string, province:string, postcode:string}|null, message: string}
     */
    public function reverse(float $latitude, float $longitude): array
    {
        $cacheKey = 'geocode:reverse:' . md5(round($latitude, 5) . ',' . round($longitude, 5));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return ['ok' => true, 'address' => $cached, 'message' => ''];
        }

        $config = config('geocoding.nominatim');

        $response = $this->request($config['base_url'] . '/reverse', [
            'lat'            => $latitude,
            'lon'            => $longitude,
            'format'         => 'jsonv2',
            'addressdetails' => 1,
            'zoom'           => 18,
        ]);

        if ($response === null) {
            return ['ok' => false, 'address' => null, 'message' => 'Address lookup is unavailable right now.'];
        }

        $address = $response['address'] ?? [];

        $result = [
            'label'    => (string) ($response['display_name'] ?? ''),
            // Nominatim memakai kunci berbeda tergantung tingkat wilayah;
            // urutan ini dari yang paling spesifik ke paling umum.
            'city'     => (string) ($address['city']
                ?? $address['town']
                ?? $address['municipality']
                ?? $address['county']
                ?? $address['village']
                ?? ''),
            'province' => (string) ($address['state']
                ?? $address['region']
                ?? self::ISO_PROVINCES[$address['ISO3166-2-lvl4'] ?? ''] // Jakarta & sejenisnya
                ?? ''),
            'postcode' => (string) ($address['postcode'] ?? ''),
        ];

        Cache::put($cacheKey, $result, now()->addHours($config['cache_ttl_hours']));

        return ['ok' => true, 'address' => $result, 'message' => ''];
    }

    /**
     * Panggilan HTTP ke Nominatim dengan throttle dan penanganan galat.
     *
     * Mengembalikan null bila gagal — pemanggil yang memutuskan pesan untuk
     * pengguna. Kegagalan SELALU dicatat ke log; menelannya diam-diam akan
     * membuat pemblokiran oleh Nominatim tampak seperti "tidak ada hasil",
     * dan penyebab sebenarnya tidak akan pernah ketahuan.
     */
    private function request(string $url, array $params): ?array
    {
        $config = config('geocoding.nominatim');

        $this->throttle((int) $config['min_interval_ms']);

        try {
            $response = Http::withHeaders([
                    'User-Agent'      => $config['user_agent'],
                    'Accept-Language' => 'id,en',
                ])
                ->timeout((int) $config['timeout_seconds'])
                ->get($url, $params);

            if ($response->failed()) {
                Log::error('Nominatim menolak permintaan.', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::error('Gagal menghubungi Nominatim.', [
                'url'     => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Tahan permintaan agar jarak antar panggilan minimal 1 detik.
     *
     * Memakai Cache sebagai penyimpan waktu panggilan terakhir supaya
     * throttle-nya berlaku LINTAS PROSES — beberapa admin yang mencari
     * bersamaan tetap dihitung sebagai satu antrean, sesuai kebijakan yang
     * membatasi per aplikasi, bukan per pengguna.
     */
    private function throttle(int $minIntervalMs): void
    {
        $lastAt = (float) Cache::get(self::THROTTLE_KEY, 0);
        $now    = microtime(true);
        $elapsed = ($now - $lastAt) * 1000;

        if ($lastAt > 0 && $elapsed < $minIntervalMs) {
            usleep((int) (($minIntervalMs - $elapsed) * 1000));
        }

        Cache::put(self::THROTTLE_KEY, microtime(true), now()->addMinutes(5));
    }

    /** @return array{ok: bool, results: array, message: string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'results' => [], 'message' => $message];
    }
}
