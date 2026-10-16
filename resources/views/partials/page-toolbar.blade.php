{{--
    Baris aksi halaman detail / form: "Back" di kiri, aksi utama (Edit) di kanan — DI DALAM halaman,
    bukan di navbar layout (`@section('page-actions')`).

    Variabel:
      $back      — URL tujuan Back (wajib)
      $backLabel — teks tombol, bawaan "Back"
      $edit      — URL Edit; kosong = tombol Edit tidak dirender
      $editLabel — teks tombol Edit, bawaan "Edit"
--}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <a href="{{ $back }}"
       class="inline-flex items-center gap-2 px-3.5 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
        <i class="fas fa-arrow-left text-xs"></i> {{ $backLabel ?? 'Back' }}
    </a>

    @if(!empty($edit))
    <a href="{{ $edit }}"
       class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
        <i class="fas fa-pen-to-square text-xs"></i> {{ $editLabel ?? 'Edit' }}
    </a>
    @endif
</div>
