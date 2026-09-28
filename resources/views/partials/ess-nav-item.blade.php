{{--
    Satu baris navigasi ESS — dipakai untuk KEDUA level tampilan (D182):
    item mandiri di puncak sidebar, ATAU anggota di dalam dropdown grup.
    Markup untuk kedua level SUDAH ADA di sidebar sebelum fitur ini (pola
    yang sama dipakai baris "Attendance"/"Overtime Management" untuk level
    atas, dan Events/Timesheets di dalam dropdown Calendar untuk level
    dalam) — dipindah ke sini SUPAYA SATU baris kode melayani baik item
    yang tetap flat maupun yang dilipat ke dalam grup, tanpa menyalin markup
    dua kali dan berisiko keduanya menyimpang.

    Variabel wajib:
      $href    URL tujuan
      $icon    kelas Font Awesome lengkap (mis. "fas fa-user-clock")
      $label   teks yang tampil
      $active  bool — status halaman sedang dibuka
      $nested  bool — true bila dirender DI DALAM dropdown grup
--}}
@if($nested)
    <a href="{{ $href }}"
        class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ $active ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
        <span class="nav-icon w-5 h-5 flex items-center justify-center">
            <i class="{{ $icon }}"></i>
        </span>
        <span class="nav-text text-sm">{{ $label }}</span>
    </a>
@else
    <div class="mb-2">
        <a href="{{ $href }}"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ $active ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
            <span class="nav-icon w-5 h-5 flex items-center justify-center">
                <i class="{{ $icon }}"></i>
            </span>
            <span class="nav-text font-medium">{{ $label }}</span>
        </a>
    </div>
@endif
