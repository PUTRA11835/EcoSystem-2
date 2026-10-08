@extends('dashboard')
@section('title', 'BPJS Settings')
@section('page-title', 'BPJS Settings')
@section('page-subtitle', 'BPJS rates, wage caps and effective dates for the company. Payroll uses the version effective on the pay period.')

@php
    use App\Support\Payroll\Money;

    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $t     = $template;                                    // versi yang menjadi isian awal formulir
    $val   = fn (string $k, $default = '') => old($k, $t[$k] ?? $default);
    $money = fn (string $k) => old($k, $t ? Money::format($t[$k] ?? 0) : '');
    $rate  = fn (string $k) => old($k, $t ? rtrim(rtrim(number_format($t[$k] ?? 0, 2, '.', ''), '0'), '.') : '');
    $pct   = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
    $existing = array_column($history, 'effective_date');
    $defaultDate = $today;
    while (in_array($defaultDate, $existing, true)) { $defaultDate = date('Y-m-d', strtotime($defaultDate . ' +1 day')); }
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.bpjs.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- ───────── Form: new version ───────── --}}
        <form method="POST" action="{{ route('finance.bpjs.settings.store') }}" class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Active rates and new settings</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($active)
                            Currently effective: version of <span class="font-semibold text-gray-700">{{ \Carbon\Carbon::parse($active['effective_date'])->format('d M Y') }}</span>.
                        @else
                            No version is effective yet.
                        @endif
                        Saving creates a <strong>new version</strong>; earlier versions are never changed.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Effective date</label>
                    <input type="date" name="effective_date" value="{{ old('effective_date', $defaultDate) }}" @disabled(!$canCreate) class="{{ $input }} disabled:bg-gray-50">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 md:mt-6">
                    <input type="checkbox" name="health_in_payroll" value="1" @checked(old('_token') ? old('health_in_payroll') : ($t['health_in_payroll'] ?? true)) @disabled(!$canCreate) class="rounded border-gray-300 text-indigo-600">
                    BPJS Health calculated in payroll
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 md:mt-6">
                    <input type="checkbox" name="employment_in_payroll" value="1" @checked(old('_token') ? old('employment_in_payroll') : ($t['employment_in_payroll'] ?? true)) @disabled(!$canCreate) class="rounded border-gray-300 text-indigo-600">
                    BPJS Employment calculated in payroll
                </label>
            </div>

            {{-- Health --}}
            <div>
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">BPJS Health <span class="font-normal normal-case text-gray-400">— Presidential Regulation No. 64 of 2020</span></h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @foreach([['health_employer_rate', 'Employer (%)'], ['health_employee_rate', 'Employee (%)']] as [$k, $label])
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $label }}</label>
                            <input type="number" step="0.01" min="0" max="100" name="{{ $k }}" value="{{ $rate($k) }}" @disabled(!$canCreate) class="{{ $input }} text-right tabular-nums disabled:bg-gray-50">
                        </div>
                    @endforeach
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum base (IDR)</label>
                        <input type="text" inputmode="decimal" name="health_min_base" value="{{ $money('health_min_base') }}" @disabled(!$canCreate) class="{{ $input }} js-money text-right tabular-nums disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Cap (IDR)</label>
                        <input type="text" inputmode="decimal" name="health_cap" value="{{ $money('health_cap') }}" @disabled(!$canCreate) class="{{ $input }} js-money text-right tabular-nums disabled:bg-gray-50">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">Minimum base is normally the regional minimum wage (UMK) of the work location; 0 means no minimum is applied.</p>
            </div>

            {{-- Employment --}}
            <div>
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">BPJS Employment <span class="font-normal normal-case text-gray-400">— Government Regulations No. 44, 45 and 46 of 2015</span></h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach([
                        ['jht_employer_rate', 'JHT employer (%)'], ['jht_employee_rate', 'JHT employee (%)'],
                        ['jp_employer_rate', 'JP employer (%)'], ['jp_employee_rate', 'JP employee (%)'],
                        ['jkk_rate', 'JKK rate (%)'], ['jkm_rate', 'JKM rate (%)'],
                    ] as [$k, $label])
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $label }}</label>
                            <input type="number" step="0.01" min="0" max="100" name="{{ $k }}" value="{{ $rate($k) }}" @disabled(!$canCreate) class="{{ $input }} text-right tabular-nums disabled:bg-gray-50">
                        </div>
                    @endforeach
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">JP cap (IDR)</label>
                        <input type="text" inputmode="decimal" name="jp_cap" value="{{ $money('jp_cap') }}" @disabled(!$canCreate) class="{{ $input }} js-money text-right tabular-nums disabled:bg-gray-50">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">The JP wage cap is adjusted every 1 March. JKK depends on the company's risk class (0.24% = class I, office work).</p>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Notes</label>
                <textarea name="notes" rows="2" maxlength="1000" placeholder="Optional — required when a JP rate is 0%" @disabled(!$canCreate) class="{{ $input }} disabled:bg-gray-50">{{ old('notes') }}</textarea>
            </div>

            @if($canCreate)
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-5 py-2.5 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-floppy-disk mr-2"></i>Save settings</button>
                    <a href="{{ route('finance.bpjs.settings') }}" data-unsaved-ignore class="px-4 py-2.5 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            @else
                <p class="text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> View only — you do not have permission to add a new version.</p>
            @endif
        </form>

        {{-- ───────── Simulation ───────── --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" id="bpjsSim" data-url="{{ route('finance.bpjs.simulate') }}">
                <h3 class="text-sm font-bold text-gray-900">BPJS base simulation</h3>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Sample wage base (IDR)</label>
                        <input type="text" inputmode="decimal" id="simWage" value="10.000.000,00" class="{{ $input }} js-money text-right tabular-nums">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">As of date</label>
                            <input type="date" id="simDate" value="{{ $today }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Extra family members</label>
                            <input type="number" id="simDeps" min="0" max="5" value="0" class="{{ $input }}">
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-gray-700">
                        <label class="flex items-center gap-2"><input type="checkbox" id="simHealth" checked class="rounded border-gray-300 text-indigo-600"> Health</label>
                        <label class="flex items-center gap-2"><input type="checkbox" id="simEmp" checked class="rounded border-gray-300 text-indigo-600"> Employment</label>
                        <button type="button" id="simBtn" class="ml-auto px-4 py-2 text-sm font-semibold rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50">Preview</button>
                    </div>
                    <p id="simError" class="hidden text-xs text-red-600"></p>
                    <div id="simOut" class="text-sm text-gray-500 bg-gray-50 rounded-lg px-3 py-3">Enter an amount and click Preview to see the BPJS simulation.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────── History ───────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Settings history</h3>
            <p class="text-xs text-gray-500 mt-0.5">Every version stays here. A payroll period uses the latest version whose effective date is on or before it.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                        <th class="px-5 py-3 w-12">No</th><th class="px-3 py-3">Effective</th><th class="px-3 py-3">Health<br><span class="font-normal normal-case">employer / employee</span></th>
                        <th class="px-3 py-3">JHT</th><th class="px-3 py-3">JP</th><th class="px-3 py-3 text-right">Health cap</th><th class="px-3 py-3 text-right">JP cap</th><th class="px-3 py-3">JKK / JKM</th><th class="px-5 py-3">Status / notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($history as $i => $h)
                        @php $isActive = $active && $active['id'] === $h['id']; $isFuture = $h['effective_date'] > $today; @endphp
                        <tr>
                            <td class="px-5 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 font-semibold text-gray-900 whitespace-nowrap">{{ \Carbon\Carbon::parse($h['effective_date'])->format('d M Y') }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $pct($h['health_employer_rate']) }}% / {{ $pct($h['health_employee_rate']) }}%</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $pct($h['jht_employer_rate']) }}% / {{ $pct($h['jht_employee_rate']) }}%</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $pct($h['jp_employer_rate']) }}% / {{ $pct($h['jp_employee_rate']) }}%</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ Money::format($h['health_cap']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ Money::format($h['jp_cap']) }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $pct($h['jkk_rate']) }}% / {{ $pct($h['jkm_rate']) }}%</td>
                            <td class="px-5 py-2.5 text-xs">
                                @if($isActive)<span class="inline-block px-2 py-0.5 font-semibold rounded bg-green-100 text-green-700">Active</span>
                                @elseif($isFuture)<span class="inline-block px-2 py-0.5 font-semibold rounded bg-blue-100 text-blue-700">Scheduled</span>
                                @else<span class="inline-block px-2 py-0.5 font-semibold rounded bg-gray-100 text-gray-600">Superseded</span>@endif
                                @if(!$h['health_in_payroll'] || !$h['employment_in_payroll'])
                                    <span class="inline-block px-2 py-0.5 font-semibold rounded bg-amber-100 text-amber-700" title="Not calculated in payroll">{{ !$h['health_in_payroll'] ? 'Health off' : '' }}{{ !$h['health_in_payroll'] && !$h['employment_in_payroll'] ? ' · ' : '' }}{{ !$h['employment_in_payroll'] ? 'Employment off' : '' }}</span>
                                @endif
                                @if(!empty($h['notes']))<p class="text-gray-500 mt-1 max-w-xs">{{ $h['notes'] }}</p>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-8 text-center text-sm text-gray-500">No BPJS setting yet. Run the database migrations or save the first version above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const box = document.getElementById('bpjsSim');
    if (!box) { return; }
    const $ = (id) => document.getElementById(id);
    // 1.000.000,00 — sama dengan App\Support\Payroll\Money::format
    const num = (n) => Number(n).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const cell = (text, cls) => { const td = document.createElement('td'); td.className = cls || 'py-1 pr-2'; td.textContent = text; return td; };

    async function preview() {
        const err = $('simError'); err.classList.add('hidden');
        const q = new URLSearchParams({
            wage: $('simWage').value, date: $('simDate').value, dependents: $('simDeps').value || 0,
            health: $('simHealth').checked ? 1 : 0, employment: $('simEmp').checked ? 1 : 0,
        });
        const res = await fetch(box.dataset.url + '?' + q, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        let json = null; try { json = await res.json(); } catch (e) { /* bukan JSON */ }
        if (!res.ok || !json?.success) { err.textContent = json?.message || 'Could not run the simulation.'; err.classList.remove('hidden'); return; }

        const r = json.result;
        const rows = [
            ['BPJS Health', r.health.base, r.health.employer, r.health.employee + r.health.dependents_amount],
            ['JHT', r.jht.base, r.jht.employer, r.jht.employee],
            ['JP', r.jp.base, r.jp.employer, r.jp.employee],
            ['JKK', r.jkk.base, r.jkk.employer, 0],
            ['JKM', r.jkm.base, r.jkm.employer, 0],
        ];
        const out = $('simOut'); out.innerHTML = '';
        const note = document.createElement('p'); note.className = 'text-xs text-gray-500 mb-2';
        note.textContent = 'Setting effective ' + json.effective_date + '. Amounts in IDR.'; out.appendChild(note);
        const t = document.createElement('table'); t.className = 'w-full text-xs';
        const head = document.createElement('tr'); head.className = 'text-left text-gray-500';
        ['Program', 'Base', 'Employer', 'Employee'].forEach((h, i) => { const th = document.createElement('th'); th.className = 'py-1 pr-2 font-semibold' + (i ? ' text-right' : ''); th.textContent = h; head.appendChild(th); });
        t.appendChild(head);
        rows.forEach(([n, b, er, ee]) => { const tr = document.createElement('tr'); tr.className = 'border-t border-gray-200'; tr.appendChild(cell(n, 'py-1 pr-2 font-medium text-gray-700')); [b, er, ee].forEach(v => tr.appendChild(cell(num(v), 'py-1 pr-2 text-right tabular-nums'))); t.appendChild(tr); });
        const tot = document.createElement('tr'); tot.className = 'border-t-2 border-gray-300 font-bold text-gray-900';
        tot.appendChild(cell('Total', 'py-1.5 pr-2')); tot.appendChild(cell('', 'py-1.5 pr-2')); tot.appendChild(cell(num(r.totals.employer), 'py-1.5 pr-2 text-right tabular-nums')); tot.appendChild(cell(num(r.totals.employee), 'py-1.5 pr-2 text-right tabular-nums'));
        t.appendChild(tot); out.appendChild(t);
    }
    $('simBtn').addEventListener('click', preview);
})();
</script>
@endpush
