{{--
    Daftar centang yang bisa dicari, menggantikan <select multiple> bawaan peramban.
    Menulis ke requires[] pada form #uge-main, jadi server tidak tahu bedanya.

    @param string $id       id unik
    @param string $label    judul bagian
    @param array  $items    [['value' => ..., 'label' => ..., 'hint' => ...], ...]
    @param array  $picked   nilai yang sudah terpilih
    @param string $name     nama field (bawaan requires[])
    @param bool   $single   true = pilih satu (radio), tanpa penghitung
    @param bool   $search   tampilkan kolom cari (bawaan true)
--}}
@php
    $name = $name ?? 'requires[]';
    $single = $single ?? false;
    $search = $search ?? true;
@endphp
<div class="uge-field uge-picker" id="{{ $id }}">
    <label for="{{ $id }}-q">{{ $label }}@unless ($single) <span class="uge-count" data-count>0</span>@endunless</label>
    @if ($search)<input id="{{ $id }}-q" type="search" class="uge-in uge-picker-q" placeholder="Search..." autocomplete="off">@endif
    <div class="uge-picker-list {{ $single ? 'is-single' : '' }}">
        @foreach ($items as $item)
            <label class="uge-opt" data-text="{{ strtolower($item['label'] . ' ' . ($item['hint'] ?? '')) }}">
                <input type="{{ $single ? 'radio' : 'checkbox' }}" name="{{ $name }}" form="uge-main" value="{{ $item['value'] }}" @checked(in_array($item['value'], $picked, true))>
                <span>{{ $item['label'] }}@if (!empty($item['hint']))<em>{{ $item['hint'] }}</em>@endif</span>
            </label>
        @endforeach
        <div class="uge-none" hidden>No match.</div>
    </div>
</div>
