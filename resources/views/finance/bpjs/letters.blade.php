@extends('dashboard')
@section('title', 'BPJS Letters')
@section('page-title', 'BPJS Letters')
@section('page-subtitle', 'Generate BPJS deactivation letters in bulk from employees who already have BPJS Health numbers.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $label = 'block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.bpjs.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    @if(session('bpjs_letter_id'))
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-green-800 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
            <span><i class="fas fa-circle-check mr-2"></i>{{ session('success') }}</span>
            <a href="{{ route('finance.bpjs.letters.pdf', [session('bpjs_letter_id'), 'download' => 1]) }}" class="px-4 py-1.5 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-download mr-2"></i>Download again</a>
        </div>
        {{-- Setelah Generate, PDF langsung terunduh. --}}
        <iframe src="{{ route('finance.bpjs.letters.pdf', [session('bpjs_letter_id'), 'download' => 1]) }}" class="hidden" aria-hidden="true"></iframe>
    @endif

    @if(!$hasLetterhead)
        <div class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5"><i class="fas fa-circle-info mr-1"></i>No letterhead is assigned to “Surat Penonaktifan BPJS Kesehatan”. The letter prints without a letterhead; assign one in Letter Templates → Settings if you want it.</div>
    @endif

    @if(!$hasLetterCode)
        <div class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5"><i class="fas fa-circle-info mr-1"></i>No letter code is set for “Surat Penonaktifan BPJS Kesehatan”, so the {code} part of the letter number stays empty. Choose one in Letter Templates → Settings.</div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('finance.bpjs.letters') }}" data-unsaved-ignore class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-5"><label class="{{ $label }}">Search employee</label>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, employee no. or BPJS no." class="{{ $input }}"></div>
        <div class="md:col-span-3"><label class="{{ $label }}">Branch</label>
            <select name="branch" class="{{ $input }}"><option value="">All branches</option>@foreach($bases as $bs)<option value="{{ $bs['key'] }}" @selected($filters['branch'] === $bs['key'])>{{ $bs['label'] }}</option>@endforeach</select></div>
        <div class="md:col-span-2"><label class="{{ $label }}">Status</label>
            <select name="status" class="{{ $input }}"><option value="">All</option><option value="inactive" @selected($filters['status'] === 'inactive')>Inactive (leavers)</option><option value="active" @selected($filters['status'] === 'active')>Active</option></select></div>
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" class="flex-1 px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90">Filter</button>
            <a href="{{ route('finance.bpjs.letters') }}" class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    <form method="POST" action="{{ route('finance.bpjs.letters.store') }}" id="letterForm" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        @csrf
        {{-- Candidates --}}
        <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg tone-primary text-sm font-semibold">Total candidates <span id="candTotal">{{ count($candidates) }}</span></span>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600" @disabled(!$canCreate)> Select all visible</label>
            </div>
            @if($capped)<p class="px-5 py-2 text-xs text-amber-700 bg-amber-50 border-b border-amber-100">Showing the first 500 matches — narrow the search to see others.</p>@endif
            <div class="overflow-x-auto max-h-[70vh]">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 bg-gray-50"><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-10"></th><th class="px-2 py-3">Employee no.</th><th class="px-2 py-3">Employee</th><th class="px-2 py-3">BPJS no.</th>
                        <th class="px-2 py-3">Phone no.</th><th class="px-2 py-3">Branch</th><th class="px-4 py-3 min-w-[11rem]">Reason</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($candidates as $c)
                            <tr class="cand-row">
                                <td class="px-4 py-2.5"><input type="checkbox" name="employee_ids[]" value="{{ $c['id'] }}" class="cand-check rounded border-gray-300 text-indigo-600" @disabled(!$canCreate) @checked(in_array($c['id'], array_map('intval', (array) old('employee_ids', []))))></td>
                                <td class="px-2 py-2.5 font-mono text-xs text-gray-600">{{ $c['eci'] }}</td>
                                <td class="px-2 py-2.5"><p class="font-semibold text-gray-900">{{ $c['name'] }}</p>
                                    <p class="text-xs"><span class="inline-block px-1.5 rounded {{ $c['active'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-700' }}">{{ $c['active'] ? 'Active' : 'Inactive' }}</span> <span class="text-gray-500">{{ $c['type'] }}</span></p></td>
                                <td class="px-2 py-2.5 text-gray-700">{{ $c['bpjs_no'] }}</td>
                                <td class="px-2 py-2.5 text-gray-700">{{ $c['phone'] ?: '—' }}</td>
                                <td class="px-2 py-2.5 text-gray-700">{{ $c['branch'] ?: '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <select name="reasons[{{ $c['id'] }}]" class="cand-reason {{ $input }}" disabled>
                                        <option value="">Select reason</option>
                                        @foreach($reasons as $key => $text)<option value="{{ $key }}" @selected(old("reasons.{$c['id']}", $c['active'] ? '' : 'resignation') === $key)>{{ $text }}</option>@endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">No employee with a BPJS Health number matches. Numbers are kept in Master → Employee → Identification.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Letter parameters --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
                <h3 class="text-sm font-bold text-gray-900">Letter parameters</h3>
                <div><label class="{{ $label }}">Letter date</label><input type="date" name="letter_date" value="{{ old('letter_date', $defaults['letter_date']) }}" class="{{ $input }}" @disabled(!$canCreate)></div>
                <div><label class="{{ $label }}">Signer name</label><input type="text" name="signer_name" maxlength="150" value="{{ old('signer_name', $defaults['signer_name']) }}" class="{{ $input }}" @disabled(!$canCreate)></div>
                <div><label class="{{ $label }}">Signer position</label><input type="text" name="signer_position" maxlength="150" value="{{ old('signer_position', $defaults['signer_position']) }}" class="{{ $input }}" @disabled(!$canCreate)></div>
                <div><label class="{{ $label }}">Contact (phone / email)</label><input type="text" name="contact" maxlength="200" value="{{ old('contact') }}" placeholder="Printed under the signer’s details" class="{{ $input }}" @disabled(!$canCreate)></div>
                <div><label class="{{ $label }}">Staff name</label><input type="text" name="staff_name" maxlength="150" value="{{ old('staff_name') }}" placeholder="Optional — contact person in the letter" class="{{ $input }}" @disabled(!$canCreate)></div>
                <div><label class="{{ $label }}">City</label><input type="text" name="city" maxlength="100" value="{{ old('city', $defaults['city']) }}" class="{{ $input }}" @disabled(!$canCreate)></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900">Generate</h3>
                <p class="text-xs text-gray-500 mt-1">The system creates a two-page PDF: the statement letter and the attachment list of the selected employees, and registers it in the Letter Register with its own number.</p>
                <p class="text-xs text-gray-700 mt-2"><span id="selCount">0</span> selected</p>
                @if($canCreate)
                    <button type="submit" id="genBtn" class="mt-3 w-full px-4 py-2.5 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90 disabled:opacity-50" disabled><i class="fas fa-file-pdf mr-2"></i>Generate BPJS Letter</button>
                @else
                    <p class="text-xs text-gray-500 mt-3"><i class="fas fa-lock mr-1"></i> View only — you cannot generate letters.</p>
                @endif
            </div>
        </div>
    </form>

    {{-- History --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-900">Generated letters</h3><p class="text-xs text-gray-500 mt-0.5">The latest 25. All BPJS letters are also in Letter Templates → Letter Register.</p></div>
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50"><th class="px-5 py-3">Number</th><th class="px-3 py-3">Date</th><th class="px-3 py-3">Employees</th><th class="px-3 py-3">Signer</th><th class="px-3 py-3">Status</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($history as $h)
                    <tr>
                        <td class="px-5 py-2.5 font-semibold text-gray-900">{{ $h->letter_number }}</td>
                        <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ $h->letter_date->format('d M Y') }}</td>
                        <td class="px-3 py-2.5 text-gray-600">{{ count($h->fields['employees'] ?? []) }}</td>
                        <td class="px-3 py-2.5 text-gray-600">{{ $h->signatory_name }}<span class="block text-xs text-gray-400">{{ $h->signatory_title }}</span></td>
                        <td class="px-3 py-2.5"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $h->status === 'void' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">{{ $h->status === 'void' ? 'Void' : 'Issued' }}</span></td>
                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                            <a href="{{ route('finance.bpjs.letters.pdf', $h->id) }}" target="_blank" class="px-2.5 py-1 text-xs font-semibold rounded border border-indigo-200 text-indigo-700 hover:bg-indigo-50">PDF</a>
                            @if($canDelete && $h->status !== 'void')
                                <form method="POST" action="{{ route('finance.bpjs.letters.void', $h->id) }}" class="inline" onsubmit="var r = prompt('Reason for voiding this letter (the number stays used):'); if (!r) { return false; } this.reason.value = r; return true;">@csrf
                                    <input type="hidden" name="reason" value=""><button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded border border-red-300 text-red-700 hover:bg-red-50">Void</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No BPJS letter generated yet.</td></tr>
                @endforelse
            </tbody></table></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('letterForm');
    if (!form) { return; }
    const checks = () => Array.from(form.querySelectorAll('.cand-check'));
    const btn = document.getElementById('genBtn');

    function sync() {
        let n = 0, missing = 0;
        checks().forEach(c => {
            const sel = c.closest('tr').querySelector('.cand-reason');
            sel.disabled = !c.checked;
            if (c.checked) { n++; if (!sel.value) { missing++; } }
        });
        document.getElementById('selCount').textContent = n;
        if (btn) { btn.disabled = n === 0 || missing > 0; btn.title = missing ? 'Choose a reason for every selected employee' : ''; }
        const all = document.getElementById('selectAll');
        const list = checks().filter(c => !c.disabled);
        all.checked = list.length > 0 && list.every(c => c.checked);
    }
    form.addEventListener('change', (e) => {
        if (e.target.id === 'selectAll') { checks().forEach(c => { if (!c.disabled) { c.checked = e.target.checked; } }); }
        sync();
    });
    sync();
})();
</script>
@endpush
