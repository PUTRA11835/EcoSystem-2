@extends('dashboard')
@section('title', 'PPh 21 Settings')
@section('page-title', 'PPh 21 Settings')
@section('page-subtitle', 'PTKP, progressive rates and monthly effective rates (TER) for each tax year, used by payroll.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $idr   = fn ($n) => \App\Support\Payroll\Money::format($n);
    $tabs  = ['ptkp' => 'PTKP', 'progressive' => 'Progressive Rates', 'ter' => 'TER Rates'];
    $terInfo = [
        'A' => 'TK/0, TK/1, K/0',
        'B' => 'TK/2, TK/3, K/1, K/2',
        'C' => 'K/3',
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.pph21.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Year selector + new year --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs text-gray-500 leading-relaxed">
                Legal basis: UU HPP, PP 58/2023 and PMK 168/2023 on PPh 21 withholding.<br>
                Rates are stored per tax year. Payroll reads them from here — nothing is hard-coded.
            </p>
            @if($ready && $locked)
                <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1">
                    <i class="fas fa-lock text-[10px]"></i> This year is used by an approved payroll and can no longer be changed.
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-end gap-3">
            @if($years)
                <form method="GET" action="{{ route('finance.pph21.settings') }}" data-unsaved-ignore class="flex items-end gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Tax year</label>
                        <select name="year" onchange="this.form.submit()" class="{{ $input }}" aria-label="Tax year">
                            @foreach($years as $y)
                                <option value="{{ $y }}" @selected($y === $year)>Year {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            @endif
            @if($canCreate)
                <form method="POST" action="{{ route('finance.pph21.years.store') }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="source_year" value="{{ $ready ? $year : ($years[0] ?? '') }}">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">New year (copy of {{ $ready ? $year : '—' }})</label>
                        <input type="number" name="new_year" min="2024" max="2100" value="{{ old('new_year', ($years[0] ?? now()->year) + 1) }}" class="{{ $input }} w-28" aria-label="New tax year">
                    </div>
                    <button type="submit" @disabled(!$ready) class="px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">Create year</button>
                </form>
            @endif
        </div>
    </div>

    @if(!$ready)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
            <i class="fas fa-percent text-3xl text-gray-300 mb-3"></i>
            <p class="text-sm font-semibold text-gray-700">No PPh 21 settings have been set up yet.</p>
            <p class="text-xs text-gray-500 mt-1">Run the database migrations to load tax year 2026, or create a year above.</p>
        </div>
    @else
        {{-- In-page tabs --}}
        <div class="flex flex-wrap gap-2">
            @foreach($tabs as $key => $label)
                <a href="{{ route('finance.pph21.settings', ['year' => $year, 'tab' => $key]) }}"
                   class="px-4 py-2 text-sm font-semibold rounded-lg transition-all {{ $tab === $key ? 'primary-gradient text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>

        {{-- ───────── PTKP ───────── --}}
        @if($tab === 'ptkp')
            <form method="POST" action="{{ route('finance.pph21.ptkp.save', $year) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                @csrf
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">PTKP — Year {{ $year }}</h3>
                    @if($canEdit && !$locked)
                        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90">Save PTKP</button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                                <th class="px-5 py-3 w-12">No</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Description</th><th class="px-3 py-3">TER category</th><th class="px-5 py-3 w-64">Annual amount (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($ptkpCodes as $i => $code)
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2.5 font-semibold text-gray-900">{{ $code }}</td>
                                    <td class="px-3 py-2.5 text-gray-600">{{ $ptkpLabels[$code] ?? '' }}</td>
                                    <td class="px-3 py-2.5"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded tone-primary">{{ \App\Support\Payroll\PtkpRules::terCategory($code) }}</span></td>
                                    <td class="px-5 py-2.5">
                                        <input type="text" inputmode="decimal" name="ptkp[{{ $code }}]" value="{{ old("ptkp.$code", $idr($ptkp[$code] ?? 0)) }}"
                                               @disabled(!$canEdit || $locked) class="{{ $input }} js-money text-right tabular-nums disabled:bg-gray-50 disabled:text-gray-500">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="px-5 py-3 text-xs text-gray-500 border-t border-gray-100">Each dependant adds the same step (TK/n = TK/0 + n steps; K/n = TK/0 + n+1 steps). The dependants count of an employee follows the status automatically.</p>
            </form>

            {{-- Occupational cost & treatment of employer premiums --}}
            <form method="POST" action="{{ route('finance.pph21.settings.save', $year) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
                @csrf
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">Calculation settings — Year {{ $year }}</h3>
                    @if($canEdit && !$locked)
                        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90">Save settings</button>
                    @endif
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Occupational cost rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="occupational_cost_rate" value="{{ old('occupational_cost_rate', $settings['occupational_cost_rate']) }}" @disabled(!$canEdit || $locked) class="{{ $input }} disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Occupational cost maximum per month (IDR)</label>
                        <input type="text" inputmode="decimal" name="occupational_cost_monthly_max" value="{{ old('occupational_cost_monthly_max', $idr($settings['occupational_cost_monthly_max'])) }}" @disabled(!$canEdit || $locked) class="{{ $input }} js-money text-right tabular-nums disabled:bg-gray-50">
                    </div>
                    <label class="flex items-start gap-2 text-sm text-gray-700 mt-6">
                        <input type="checkbox" name="include_employer_premiums" value="1" @checked(old('include_employer_premiums', $settings['include_employer_premiums'])) @disabled(!$canEdit || $locked) class="mt-0.5 rounded border-gray-300 text-indigo-600">
                        <span>Employer-paid BPJS premiums (JKK, JKM, Health) count as gross income<span class="block text-xs text-gray-400">Default per regulation; change only on Accounting's decision.</span></span>
                    </label>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Notes</label>
                        <textarea name="notes" rows="2" maxlength="1000" @disabled(!$canEdit || $locked) class="{{ $input }} disabled:bg-gray-50">{{ old('notes', $settings['notes']) }}</textarea>
                    </div>
                </div>
            </form>
        @endif

        {{-- ───────── Progressive ───────── --}}
        @if($tab === 'progressive')
            <form method="POST" action="{{ route('finance.pph21.brackets.save', $year) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                @csrf
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Progressive rates (Pasal 17) — Year {{ $year }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Applied to annual taxable income (PKP) — used in the final-month true-up.</p>
                    </div>
                    @if($canEdit && !$locked)
                        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90">Save rates</button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                                <th class="px-5 py-3 w-12">No</th><th class="px-3 py-3">Annual taxable income above (IDR)</th><th class="px-3 py-3 w-64">Up to (IDR)</th><th class="px-5 py-3 w-40">Rate (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php $prevLimit = 0; @endphp
                            @foreach($brackets as $i => $b)
                                @php $isLast = $b['upper_limit'] === null; @endphp
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2.5 text-gray-600 tabular-nums text-right">{{ $idr($prevLimit) }}</td>
                                    <td class="px-3 py-2.5">
                                        @if($isLast)
                                            <span class="text-gray-500 text-sm">and above</span>
                                            <input type="hidden" name="rows[{{ $i }}][upper_limit]" value="">
                                        @else
                                            <input type="text" inputmode="decimal" name="rows[{{ $i }}][upper_limit]" value="{{ old("rows.$i.upper_limit", $idr($b['upper_limit'])) }}" @disabled(!$canEdit || $locked) class="{{ $input }} js-money text-right tabular-nums disabled:bg-gray-50">
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5"><input type="number" step="0.01" min="0" max="100" name="rows[{{ $i }}][rate]" value="{{ old("rows.$i.rate", rtrim(rtrim(number_format($b['rate'], 2, '.', ''), '0'), '.')) }}" @disabled(!$canEdit || $locked) class="{{ $input }} tabular-nums disabled:bg-gray-50"></td>
                                </tr>
                                @php $prevLimit = $b['upper_limit'] ?? $prevLimit; @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
        @endif

        {{-- ───────── TER ───────── --}}
        @if($tab === 'ter')
            <div class="flex flex-wrap gap-2">
                @foreach(['A', 'B', 'C'] as $c)
                    <a href="{{ route('finance.pph21.settings', ['year' => $year, 'tab' => 'ter', 'cat' => $c]) }}"
                       class="px-3 py-1.5 text-xs font-semibold rounded-lg {{ $cat === $c ? 'tone-primary-strong' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                        Category {{ $c }} <span class="font-normal opacity-70">· {{ $terInfo[$c] }}</span>
                    </a>
                @endforeach
            </div>

            @php $layers = $ter[$cat] ?? []; @endphp
            <form method="POST" action="{{ route('finance.pph21.ter.save', [$year, $cat]) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                @csrf
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Monthly effective rates (TER) — Category {{ $cat }}, Year {{ $year }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">For {{ $terInfo[$cat] }}. Applied to monthly gross income, January to the month before the final tax period. {{ count($layers) }} rows.</p>
                    </div>
                    @if($canEdit && !$locked)
                        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90">Save category {{ $cat }}</button>
                    @endif
                </div>
                <div class="overflow-x-auto max-h-[70vh]">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3 w-12">No</th><th class="px-3 py-3">Monthly gross above (IDR)</th><th class="px-3 py-3 w-64">Up to (IDR)</th><th class="px-5 py-3 w-40">Rate (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($layers as $i => $l)
                                @php $isLast = $l['gross_upto'] === null; @endphp
                                <tr>
                                    <td class="px-5 py-2 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 text-gray-600 tabular-nums text-right">{{ $idr($l['gross_over']) }}</td>
                                    <td class="px-3 py-2">
                                        @if($isLast)
                                            <span class="text-gray-500 text-sm">and above</span>
                                            <input type="hidden" name="rows[{{ $i }}][upper_limit]" value="">
                                        @else
                                            <input type="text" inputmode="decimal" name="rows[{{ $i }}][upper_limit]" value="{{ old("rows.$i.upper_limit", $idr($l['gross_upto'])) }}" @disabled(!$canEdit || $locked) class="{{ $input }} js-money text-right tabular-nums disabled:bg-gray-50">
                                        @endif
                                    </td>
                                    <td class="px-5 py-2"><input type="number" step="0.01" min="0" max="100" name="rows[{{ $i }}][rate]" value="{{ old("rows.$i.rate", rtrim(rtrim(number_format($l['rate'], 2, '.', ''), '0'), '.')) }}" @disabled(!$canEdit || $locked) class="{{ $input }} tabular-nums disabled:bg-gray-50"></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No TER rows for this category and year.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        @endif
    @endif
</div>
@endsection
