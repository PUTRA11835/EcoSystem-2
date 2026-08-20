<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penyedia geocoding
    |--------------------------------------------------------------------------
    |
    | Saat ini hanya 'nominatim' (OpenStreetMap). Dipisah sebagai konfigurasi
    | supaya berpindah penyedia (Geoapify, LocationIQ, Google) nanti tidak
    | menyentuh controller maupun Blade — cukup satu service baru dan satu
    | nilai di sini.
    |
    */

    'provider' => env('GEOCODING_PROVIDER', 'nominatim'),

    'nominatim' => [

        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),

        /*
        | Kebijakan pemakaian Nominatim MEWAJIBKAN setiap aplikasi
        | mengidentifikasi diri lewat User-Agent. Permintaan tanpa identitas
        | yang jelas diblokir, dan pemblokirannya berlaku per alamat IP —
        | artinya satu kesalahan di sini bisa mematikan pencarian lokasi untuk
        | seluruh kantor.
        |
        | Isi dengan domain atau email yang benar-benar dapat dihubungi.
        | Lihat https://operations.osmfoundation.org/policies/nominatim/
        */
        'user_agent' => env(
            'NOMINATIM_USER_AGENT',
            'EcoSystem-2/1.0 (+http://dev-help.eclectic.co.id)'
        ),

        /*
        | Batas kebijakan: maksimum 1 permintaan per detik. Nilai dalam
        | milidetik; jangan diturunkan di bawah 1000 tanpa memakai server
        | Nominatim sendiri.
        */
        'min_interval_ms' => (int) env('NOMINATIM_MIN_INTERVAL_MS', 1100),

        /*
        | Hasil pencarian disimpan agar kueri yang sama tidak memukul server
        | mereka dua kali. Alamat kantor praktis tidak pernah berubah, jadi
        | masa simpan panjang aman.
        */
        'cache_ttl_hours' => (int) env('NOMINATIM_CACHE_TTL_HOURS', 24),

        'timeout_seconds' => (int) env('NOMINATIM_TIMEOUT', 8),

        /*
        | Membatasi hasil ke Indonesia. Kosongkan untuk pencarian global.
        */
        'country_codes' => env('NOMINATIM_COUNTRY_CODES', 'id'),

        'max_results' => (int) env('NOMINATIM_MAX_RESULTS', 6),
    ],

];
