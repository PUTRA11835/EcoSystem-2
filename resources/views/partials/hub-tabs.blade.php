{{--
    Tab bar bersama untuk halaman "hub" — Attendance, Overtime, dan yang
    menyusul nanti (Keputusan D175).

    Pola tab-nya meniru Delivery Support (`Support` | `Planning`): setiap tab
    adalah HALAMAN/RUTE SUNGGUHAN yang di-render server, ditandai aktif lewat
    `Request::is()`, BUKAN panel JavaScript. Yang beda dari referensinya, dan
    disengaja: markup-nya SATU partial dipakai bersama, bukan disalin-tempel
    ke tiap halaman — referensinya menyalin blok yang sama ke 2 halaman;
    kebutuhan kita 5–7 tab per hub, menyalin berarti tujuh salinan yang harus
    dijaga sejalan.

    🔴 SETIAP TAB PUNYA GERBANG SENDIRI, DAN ITU BUKAN OPSIONAL.
    Delivery Support hanya punya satu slug untuk kedua tabnya. Attendance/
    Overtime SENGAJA mempertahankan slug terpisah per tab (enam untuk
    Attendance, dua untuk Overtime) — itulah yang membuat Control Center bisa
    memberi HR junior akses Corrections tanpa otomatis memberi akses Branches.
    Tab yang gate-nya tidak dipegang pengguna TIDAK DIRENDER SAMA SEKALI,
    bukan ditampilkan lalu 403 saat diklik.

    Parameter:
      $hubTabs — array of [
          'label'  => string,          teks yang tampil
          'icon'   => string,          nama kelas fa- TANPA prefix "fas "
          'route'  => string,          nama rute (dilewatkan ke route())
          'is'     => string,          pola Request::is() untuk status aktif
          'gate'   => string|null,     slug menu; null = selalu tampil
          'strict' => bool,            true = JANGAN ikut `|| $can('general')`
      ]

    Pemanggil TIDAK PERLU membungkus $can — partial ini yang memeriksa,
    persis pola `|| $can('general')` yang sudah dipakai di sidebar.

    🔴 'strict' (D177). Fallback `|| $can('general')` benar untuk tab mana pun
    yang slug-nya di dalam payung `general.*` — begitulah slug induk `general`
    dipakai di seluruh sidebar. Tab Settings Cash Advance BEDA: slug-nya
    `management.cash-advance-settings` sengaja di LUAR payung itu (D141), justru
    supaya haknya bisa diberikan TANPA slug `general` sama sekali. Tanpa
    'strict', seseorang yang cuma pegang `general` akan MELIHAT tab itu lalu
    kena 403 saat diklik — tombol jebakan, persis kelas cacat D154. --}}
<div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
    <nav class="flex flex-wrap gap-1 p-1" aria-label="Tabs">
        @foreach($hubTabs as $tab)
            @continue(!empty($tab['gate']) && !($can($tab['gate']) || (empty($tab['strict']) && $can('general'))))
            <a href="{{ route($tab['route']) }}"
               class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium text-center rounded-lg transition-all
                      {{ Request::is($tab['is']) ? 'primary-gradient text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                <i class="fas fa-{{ $tab['icon'] }} mr-2"></i>
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
