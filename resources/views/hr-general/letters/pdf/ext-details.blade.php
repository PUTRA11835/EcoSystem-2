{{-- Konsultan eksternal pada surat: nama, ID, peran, klien (dari engagement yang disimpan bersama surat) — Indonesia & Inggris. --}}
@php
    $eng = $f['engagement'] ?? [];
    $rows = array_filter([
        ($lang === 'en' ? 'Name' : 'Nama')                       => $employee['name'] ?? null,
        ($lang === 'en' ? 'Consultant ID' : 'ID Konsultan')      => $employee['eci'] ?? null,
        ($lang === 'en' ? 'Role' : 'Peran')                      => $eng['role'] ?? ($employee['position'] ?? null),
        ($lang === 'en' ? 'Client' : 'Klien')                    => $eng['client'] ?? null,
        ($lang === 'en' ? 'Vendor / partner' : 'Vendor / mitra') => $eng['vendor'] ?? null,
    ]);
@endphp
<table class="details">
    @foreach($rows as $label => $value)
        <tr><td class="label">{{ $label }}</td><td class="colon">:</td><td>{{ $value }}</td></tr>
    @endforeach
</table>
