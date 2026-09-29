{{--
    Dekorasi tema musiman — dipakai ulang di 3 tempat: sidebar & header
    dashboard.blade.php (per-user lewat SeasonalThemeResolver) dan nanti
    auth/login.blade.php (halaman penuh, lewat GlobalSeasonalTheme). Efek
    animasi dari field `decoration`, aksen statis (emoji) dari `accents` —
    semua di SeasonalThemes::CATALOG.

    Sengaja TIDAK menutupi seluruh halaman/konten utama (feedback user: salju
    di halaman utama mengganggu) — hanya area yang di-scope oleh $placement.
    Placement 'header' sengaja MINIMAL (feedback user: garland/bunting full-width
    "terlalu mengganggu") — cuma 1 aksen kecil, tanpa garland dan tanpa salju.

    Params:
      $themeKey        string  key di SeasonalThemes (hasil SeasonalThemeResolver,
                                bukan 'default' mentah).
      $showAnimations  bool    gerbang pertama animasi (toggle Settings user).
      $placement       string  'sidebar' (absolute, mengisi <aside> yang sudah
                                fixed+z-50) | 'header' (absolute, mengisi
                                <header> yang sudah sticky+z-40 — cuma 1 aksen
                                kecil, tanpa salju/garland, sengaja minimal) |
                                'login-panel' (absolute, mengisi panel kiri
                                #deco-panel di auth/login.blade.php yang sudah
                                position:relative+overflow:hidden — TIDAK
                                menutupi form login di panel kanan sama
                                sekali) | 'fullpage' (fixed, dipakai kalau
                                suatu saat perlu menutupi seluruh viewport).
                                Default 'fullpage'.
--}}
@php
    $seasonalMeta = \App\Support\SeasonalThemes::get($themeKey);
    $seasonalDecoration = $seasonalMeta['decoration'] ?? null;
    $seasonalAccents = $seasonalMeta['accents'] ?? [];
    $seasonalPlacement = $placement ?? 'fullpage';
    $seasonalIsHeader = $seasonalPlacement === 'header';
    // Navbar sengaja dibuat SANGAT minim (feedback user): 1 aksen kecil saja, tanpa salju/garland.
    $seasonalHeaderAccents = $seasonalIsHeader ? array_slice($seasonalAccents, 0, 1) : $seasonalAccents;
    // 'fullpage' SEKARANG khusus dipakai untuk loading screen login (~2.6 detik
    // tampil) — bukan halaman yang dilihat lama seperti sidebar/login-panel.
    // Delay/durasi salju yang cocok untuk sidebar (acak 0-12s, jatuh 9-18s)
    // kalau dipakai di sini bikin SEBAGIAN BESAR kepingan tidak sempat muncul
    // sama sekali dalam jendela waktu sesingkat itu — makanya timing-nya beda.
    $seasonalIsBrief = $seasonalPlacement === 'fullpage';
@endphp
@if($seasonalDecoration || count($seasonalAccents))
<div class="seasonal-decoration seasonal-decoration--{{ $seasonalPlacement }}" aria-hidden="true">
    @if(!$seasonalIsHeader)
        @switch($seasonalDecoration)
            @case('snow')
                @for ($i = 0; $i < ($seasonalIsBrief ? 35 : 20); $i++)
                    @php
                        $flakeLeft = rand(0, 100);
                        $flakeDelay = $seasonalIsBrief ? (rand(0, 600) / 1000) : (rand(0, 12000) / 1000);
                        $flakeDuration = $seasonalIsBrief ? (rand(18, 30) / 10) : rand(9, 18);
                        $flakeSize = rand(8, 16);
                        $flakeOpacity = rand(25, 55) / 100;
                    @endphp
                    {{-- Unicode, bukan ikon Font Awesome: partial ini juga dipakai di halaman
                         Login yang tidak memuat Font Awesome sama sekali. --}}
                    <span class="seasonal-snowflake"
                       style="left:{{ $flakeLeft }}%; animation-delay:{{ $flakeDelay }}s; --seasonal-fall-duration:{{ $flakeDuration }}s; font-size:{{ $flakeSize }}px; opacity:{{ $flakeOpacity }};">❄</span>
                @endfor
                @break
        @endswitch
    @endif

    @if(count($seasonalHeaderAccents))
        {{-- Aksen statis (bukan animasi) — pohon/santa/dekorasi lain, opacity
             rendah supaya tetap terbaca sebagai "wallpaper", tidak menutupi teks. --}}
        @foreach($seasonalHeaderAccents as $j => $emoji)
            @php
                // Header cuma 1 aksen: ditaruh di kanan-tengah, jauh dari judul halaman
                // di kiri, supaya tidak pernah terasa "menabrak" teks judul.
                $accentTop = $seasonalIsHeader ? 30 : 8 + ($j * (84 / max(1, count($seasonalHeaderAccents) - 1)));
                $accentSide = $seasonalIsHeader ? 'right:22%;' : ($j % 2 === 0 ? 'left:6%;' : 'right:6%;');
                $accentSize = match(true) {
                    $seasonalIsHeader => rand(14, 18),
                    $seasonalPlacement === 'sidebar' => rand(20, 30),
                    default => rand(28, 44),
                };
                // Header sengaja jauh lebih pudar (feedback user: dekorasi navbar sebelumnya
                // terlalu ramai) — cuma 1 aksen kecil, nyaris tidak terlihat kecuali diperhatikan.
                $accentOpacity = ($seasonalIsHeader ? rand(12, 18) : rand(30, 50)) / 100;
            @endphp
            @php
                // Santa dapat animasi sendiri (bukan cuma diam kayak aksen lain) — TAPI
                // tidak di header (sengaja tetap minimal, lihat feedback navbar di atas).
                $isSanta = $emoji === '🎅' && !$seasonalIsHeader;
            @endphp
            <span class="seasonal-accent {{ $isSanta ? 'seasonal-accent--santa' : '' }}" style="top:{{ $accentTop }}%; {{ $accentSide }} font-size:{{ $accentSize }}px; opacity:{{ $accentOpacity }};">{{ $emoji }}</span>
        @endforeach
    @endif
</div>

<style>
    /* pointer-events:none supaya tidak pernah menghalangi klik. Placement
       'sidebar'/'header': z-index negatif, mengandalkan <aside>/<header> yang
       SUDAH punya z-50/z-40 (jadi sudah membentuk stacking context sendiri)
       supaya lapisan ini pasti tetap di BELAKANG label menu/tombol (elemen
       positioned dengan z-index:auto tetap dicat di atas konten statis meski
       z-index tidak diatur — makanya harus negatif, bukan 0, dan ini tetap
       terkurung di dalam kontainernya, tidak bocor ke belakang seluruh
       halaman). Placement 'fullpage': fixed menutupi viewport, z-index rendah
       (di bawah header/dropdown z-40/z-50 dan toast z-9999). */
    .seasonal-decoration--sidebar,
    .seasonal-decoration--header {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        z-index: -1;
    }
    /* #deco-panel (auth/login.blade.php) TIDAK punya z-index sendiri (cuma
       position:relative), jadi TIDAK membentuk stacking context baru seperti
       <aside>/<header> — z-index negatif di sini bisa "bocor" ke belakang body
       alih-alih terkurung di panel ini. Dipakai z-index:0 (bukan -1) supaya
       tetap konsisten dengan elemen dekoratif lain yang SUDAH ada di panel ini
       (orb/ring/dot, semua z-index:auto) — wrapper konten asli eksplisit
       z-index:10, jadi tetap pasti di atas apa pun dekorasi tema musiman ini. */
    .seasonal-decoration--login-panel {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        z-index: 0;
    }
    .seasonal-decoration--fullpage {
        position: fixed;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        z-index: 5;
    }
    .seasonal-decoration .seasonal-snowflake {
        position: absolute;
        top: -8%;
        color: #bfdbfe;
        filter: drop-shadow(0 1px 1px rgba(0,0,0,0.18));
    }
    .seasonal-decoration .seasonal-accent {
        position: absolute;
        line-height: 1;
        filter: drop-shadow(0 1px 1px rgba(0,0,0,0.15));
    }
    @if($showAnimations)
    /* Gerbang kedua: toggle "Show animations" saja tidak cukup, hormati juga
       preferensi OS-level supaya reduced-motion tetap dipatuhi meski toggle on. */
    @media (prefers-reduced-motion: no-preference) {
        .seasonal-decoration .seasonal-snowflake {
            animation: seasonal-snow-fall var(--seasonal-fall-duration, 12s) linear infinite;
        }
        @keyframes seasonal-snow-fall {
            0%   { transform: translateY(-10vh) translateX(0) rotate(0deg); }
            100% { transform: translateY(110vh) translateX(20px) rotate(360deg); }
        }
        /* Santa "meluncur" pelan bolak-balik + naik-turun tipis — beda dari aksen
           lain yang diam, tapi tetap lembut (bukan animasi besar/mencolok). */
        .seasonal-decoration .seasonal-accent--santa {
            animation: seasonal-santa-glide 10s ease-in-out infinite;
        }
        @keyframes seasonal-santa-glide {
            0%, 100% { transform: translate(0, 0) rotate(-4deg); }
            50%      { transform: translate(18px, -10px) rotate(4deg); }
        }
    }
    @endif
</style>
@endif
