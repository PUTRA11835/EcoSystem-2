{{-- Input period expense Plan Cost: bulan + tahun (disimpan sebagai "YYYY-MM").
     $prefix: 'ad' (form Add Expense) | 'ae' (modal Edit Expense).
     Rentang tahun mengikuti masa kontrak project, minimal 5 tahun ke belakang
     dan 2 tahun ke depan; tahun di luar rentang (data lama) ditambahkan JS. --}}
@php
    $pcYearFrom = min(now()->year - 5, optional($project->contract_start_date)->year ?? now()->year);
    $pcYearTo   = max(now()->year + 2, optional($project->contract_end_date)->year ?? now()->year);
@endphp
<div class="grid grid-cols-2 gap-2">
    <select id="{{ $prefix }}PeriodMonth"
            class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm">
        <option value="">Month</option>
        @foreach(range(1, 12) as $m)
            <option value="{{ sprintf('%02d', $m) }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
        @endforeach
    </select>
    <select id="{{ $prefix }}PeriodYear"
            class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm">
        <option value="">Year</option>
        @foreach(range($pcYearTo, $pcYearFrom) as $y)
            <option value="{{ $y }}">{{ $y }}</option>
        @endforeach
    </select>
</div>
