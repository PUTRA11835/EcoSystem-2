{{--
    Seksi sidebar berjudul yang bisa dilipat (pola "judul + isi menu", seperti aplikasi acuan ESH,
    Atlassian, dan IBM Carbon). Default TERBUKA; keadaan lipat diingat per peramban
    (lihat toggleSidebarSection di partials/sidebar.blade.php).

    Variabel: $id (huruf & angka, dipakai untuk id elemen), $title (teks judul), $body (HTML menu),
    $icon (nama ikon Tabler untuk mode RAIL — tampil hanya saat rail aktif; lihat CSS .sb-sec-icon).
    Panah mengikuti aturan yang sama dengan dropdown lain: terbuka = menghadap atas (rotate-180).
    Id wadah berakhiran "Section" — sengaja BUKAN "Dropdown"/"Submenu" supaya tidak ikut mekanisme
    penyimpanan dropdown (sessionStorage) milik sidebar; keadaan seksi punya penyimpanan sendiri.
--}}
<div class="sb-sec" data-sec="{{ $id }}">
    <button type="button" class="sb-sec-btn" onclick="toggleSidebarSection('{{ $id }}')"
        aria-expanded="true" aria-controls="sbSec{{ $id }}Section">
        <svg class="sb-ico sb-sec-icon" aria-hidden="true" focusable="false"><use href="#ti-{{ $icon ?? 'smart-home' }}"/></svg>
        <span class="sb-sec-title nav-text">{{ $title }}</span>
        <span class="sb-dot hidden" title="Something here needs your attention" aria-hidden="true"></span>
        <i class="fas fa-chevron-down nav-text transition-transform rotate-180" id="sbSec{{ $id }}Chevron"></i>
    </button>
    <div id="sbSec{{ $id }}Section" class="sb-sec-body" data-title="{{ $title }}">{!! $body !!}</div>
</div>
