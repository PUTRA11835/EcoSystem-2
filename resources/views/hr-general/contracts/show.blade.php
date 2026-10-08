@extends('dashboard')
@section('title', $employee->name . ' — Contract')
@section('page-title', 'Contract')
@section('page-subtitle', 'Contract readiness, salary reference and contract history of one employee.')

@php
    use App\Support\Contracts\ContractRules;

    $canCreate = $canDo('general.contracts.list', 'create');
    $canEdit   = $canDo('general.contracts.list', 'edit');
    $canDelete = $canDo('general.contracts.list', 'delete');
    $badge = [
        'active' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-200 text-gray-600', 'expired' => 'bg-red-100 text-red-700',
        'terminated' => 'bg-red-100 text-red-700', 'inactive' => 'bg-gray-100 text-gray-500',
    ];
    $typeBadge = ['PKWT' => 'tone-primary', 'PKWTT' => 'tone-primary-strong', 'EXTERNAL' => 'bg-amber-100 text-amber-700'];
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';

    // The contract the salary reference is read from: the running one, else the newest.
    $reference = collect($history)->firstWhere('effective_status', 'active') ?? ($history[0] ?? null);
    $canAdd = $canCreate && ($gate !== 'create' || $readiness['ready']);
    $activeLocked = $gate !== 'off' && !$readiness['ready']; // "Active" cannot be chosen for a new contract yet
    $pct = $readiness['percent'];
    $pctTone = $pct >= 100 ? 'bg-green-100 text-green-700' : ($pct >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
    $employeeUrl = route('master.employee.detail', $employee->employee_id);
    $reopen = session('reopen_modal');
    $oldComponents = old('components', []);
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.contracts.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-gray-800">{{ $employee->name }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $employee->eci }} • {{ $employee->employee_position ?: '-' }} • {{ $employee->employee_department ?: '-' }}
                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $employee->employee_type === 'External' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">{{ $employee->employee_type ?: 'Internal' }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canDo('master.employee'))<a href="{{ $employeeUrl }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">Employee Profile</a>@endif
            <a href="{{ route('general.contracts.list') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">Contract List</a>
            @if($canDo('general.contracts.templates'))<a href="{{ route('general.contracts.templates.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">Templates</a>@endif
        </div>
    </div>

    {{-- Contract Readiness --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Contract Readiness</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">
                    @if($gate === 'create') A contract can only be created after the employee master data and minimum documents are complete.
                    @elseif($gate === 'activate') A contract can be prepared as a Draft at any time, but it can only be set to Active after the employee master data and minimum documents are complete.
                    @else The minimum data for a contract. Contracts can still be created and activated when it is incomplete. @endif
                </p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $pctTone }}">{{ $pct }}%</span>
        </div>
        <div class="px-5 pb-4">
            @if($readiness['ready'])
                <div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> Minimum data is complete. The contract can be created and activated.</div>
            @else
                <div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3">
                    <p class="font-semibold mb-1.5">Complete the following data{{ $gate === 'create' ? ' before creating a contract' : ($gate === 'activate' ? ' before activating a contract' : '') }}:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($readiness['missing'] as $item)
                            @if($canDo('master.employee'))
                                <a href="{{ $employeeUrl }}?section={{ $item['section'] ?? '' }}" class="px-2 py-0.5 rounded-full bg-white border border-amber-300 text-amber-900 hover:bg-amber-100">{{ $item['label'] }}</a>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-white border border-amber-300">{{ $item['label'] }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @if($canDo('master.employee'))
                    <a href="{{ $employeeUrl }}" class="inline-block mt-3 px-3 py-1.5 rounded-full border border-indigo-300 text-indigo-700 text-xs font-semibold hover:bg-indigo-50">Complete Employee Data</a>
                @endif
            @endif
        </div>
    </div>

    {{-- Salary reference --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Salary Component Reference</h3>
            <p class="text-[11px] text-gray-500 mt-0.5">Taken from the {{ $reference && $reference->effective_status === 'active' ? 'running' : 'latest' }} contract. Each contract keeps its own copy of the amounts at signing.</p>
        </div>
        @if(!$canSalary)
            <p class="px-5 py-6 text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> Salary amounts are visible to holders of the “View Salary” permission only.</p>
        @elseif(!$reference)
            <p class="px-5 py-6 text-xs text-gray-400">No contract yet.</p>
        @else
            @php $allowance = array_sum(array_column($reference->components, 'amount')); @endphp
            <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach([['Basic Salary', (float) $reference->salary], ['Allowances', $allowance], ['Total Compensation', $reference->total_salary]] as [$k, $v])
                    <div class="rounded-lg border border-gray-200 px-4 py-3"><p class="text-[11px] text-gray-500">{{ $k }}</p><p class="text-sm font-bold {{ $loop->last ? 'text-indigo-700' : 'text-gray-800' }}">{{ ContractRules::money($v) }}</p></div>
                @endforeach
            </div>
            @if($reference->components)
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500"><tr><th class="px-5 py-2 text-left w-10">No</th><th class="px-5 py-2 text-left">Component</th><th class="px-5 py-2 text-right">Amount</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr><td class="px-5 py-2 text-gray-400">1</td><td class="px-5 py-2">Basic Salary</td><td class="px-5 py-2 text-right">{{ ContractRules::money((float) $reference->salary) }}</td></tr>
                        @foreach($reference->components as $c)
                            <tr><td class="px-5 py-2 text-gray-400">{{ $loop->iteration + 1 }}</td><td class="px-5 py-2">{{ $c['name'] }}</td><td class="px-5 py-2 text-right">{{ ContractRules::money($c['amount']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>

    {{-- History --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Contract History</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">{{ count($history) }} document{{ count($history) === 1 ? '' : 's' }} available</p>
            </div>
            @if($canAdd)
                <button type="button" onclick="openContractModal()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Contract</button>
            @elseif($canCreate)
                <span class="text-[11px] text-gray-400" title="Complete the employee data first"><i class="fas fa-lock mr-1"></i> Add Contract unlocks at 100%</span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Number</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">THP / Total Salary</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($history as $c)
                        @php
                            $payload = [
                                'id' => $c->contract_id, 'number' => $c->contract_number, 'type' => $c->type_key ?? $c->contract_type, 'status' => $c->lifecycle_status ?? ($c->effective_status === 'active' ? 'active' : 'draft'),
                                'start' => $c->start_date?->toDateString(), 'end' => $c->end_date?->toDateString(), 'signed' => $c->signed_date?->toDateString(),
                                'position' => $c->position, 'department' => $c->department, 'location' => $c->work_location, 'volume' => $c->work_volume, 'template' => $c->template_id,
                                'salary' => $canSalary ? $c->salary : null, 'components' => $canSalary ? $c->components : [], 'notes' => $c->notes, 'signatory' => $c->signatory_employee_id,
                                'isActive' => (bool) $c->is_active,
                                'update' => route('general.contracts.update', $c->contract_id),
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3"><span class="font-semibold text-gray-800">{{ $c->contract_number }}</span><span class="block text-[11px] text-gray-400">{{ $c->position ?: '-' }}</span></td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $typeBadge[$c->type_key] ?? 'bg-gray-100 text-gray-600' }}">{{ $c->type_key ?? $c->contract_type }}</span></td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $c->start_date?->format('d M Y') }}<span class="block text-[11px] text-gray-400">until {{ $c->end_date?->format('d M Y') ?? '—' }}</span></td>
                            <td class="px-4 py-3">
                                @if($canSalary)
                                    <span class="font-semibold text-indigo-700">{{ ContractRules::money($c->total_salary) }}</span>
                                    <span class="block text-[11px] text-gray-400">Basic {{ ContractRules::money((float) $c->salary) }}@if($c->components) + allowance {{ ContractRules::money(array_sum(array_column($c->components, 'amount'))) }}@endif</span>
                                @else <span class="text-gray-300">•••••</span> @endif
                            </td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badge[$c->effective_status] ?? '' }}">{{ $statuses[$c->effective_status] ?? $c->effective_status }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="seg">
                                        <a href="{{ route('general.contracts.document', $c->contract_id) }}" class="seg-view">View</a>
                                        @if($canSalary)<a href="{{ route('general.contracts.document', $c->contract_id) }}?print=1" class="seg-print">Print</a>@endif
                                        @if($canEdit)<button type="button" data-contract-id="{{ $c->contract_id }}" data-contract="{{ json_encode($payload) }}" onclick="openContractModal(JSON.parse(this.dataset.contract))" class="seg-edit">Edit</button>@endif
                                    </div>
                                    @if($canDelete && $c->lifecycle_status === 'draft')
                                        <form method="POST" action="{{ route('general.contracts.destroy', $c->contract_id) }}" data-confirm="Delete draft {{ $c->contract_number }}? This cannot be undone." data-confirm-title="Delete Draft" data-confirm-ok="Delete" data-unsaved-ignore>@csrf
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-[11px] font-semibold">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No contract history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add / Edit contract --}}
@if($canCreate || $canEdit)
<div id="contractModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[92vh] flex flex-col">
        <form id="contractForm" method="POST" class="flex flex-col min-h-0">
            @csrf
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between">
                <div><h3 class="text-sm font-bold text-gray-800" id="contractModalTitle">Add Contract</h3><p class="text-[11px] text-gray-400" id="contractModalSub"></p></div>
                <button type="button" onclick="closeContractModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-5 py-4 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-4">
                @error('contract')
                    <div class="md:col-span-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert"><i class="fas fa-circle-exclamation mr-1"></i> {{ $message }}</div>
                @enderror
                <div id="fNumberWrap" class="hidden"><label class="{{ $label }}" for="fNumber">Contract number</label><input id="fNumber" name="contract_number" maxlength="100" class="{{ $input }}"><p class="text-[10px] text-gray-400 mt-1">Generated by the system; correct it only if needed.</p></div>
                <div><label class="{{ $label }}" for="fType">Contract type</label>
                    <select id="fType" name="contract_type" class="{{ $input }}" required>
                        @foreach($types as $value => $name)<option value="{{ $value }}">{{ $name }}</option>@endforeach
                    </select></div>
                <div><label class="{{ $label }}" for="fStart">Start date</label><input type="date" id="fStart" name="start_date" class="{{ $input }}" required></div>
                <div><label class="{{ $label }}" for="fEnd">End date</label><input type="date" id="fEnd" name="end_date" class="{{ $input }}"><p class="text-[10px] text-gray-400 mt-1" id="fEndHelp"></p></div>
                <div><label class="{{ $label }}" for="fSigned">Signed date</label><input type="date" id="fSigned" name="signed_date" class="{{ $input }}"></div>
                <div><label class="{{ $label }}" for="fStatus">Document status</label>
                    <select id="fStatus" name="status" class="{{ $input }}">
                        @foreach($settable as $s)<option value="{{ $s }}">{{ $statuses[$s] }}</option>@endforeach
                    </select><p class="text-[10px] text-gray-400 mt-1">Active freezes the template text. “Expired” follows the end date by itself.</p></div>
                <div><label class="{{ $label }}" for="fDept">Department</label>
                    <select id="fDept" name="department" class="{{ $input }}"><option value="">Follow employee data</option>
                        @foreach($departments as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach</select></div>
                <div><label class="{{ $label }}" for="fPos">Position</label>
                    <select id="fPos" name="position" class="{{ $input }}"><option value="">Follow employee data</option>
                        @foreach($positions as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select></div>
                <div><label class="{{ $label }}" for="fLoc">Work location</label><input id="fLoc" name="work_location" maxlength="255" placeholder="Follow employee data" class="{{ $input }}"></div>
                <div id="fVolumeWrap" class="hidden"><label class="{{ $label }}" for="fVolume">Work volume</label><input id="fVolume" name="work_volume" maxlength="100" placeholder="e.g. 20,00 mandays" class="{{ $input }}"><p class="text-[10px] text-gray-400 mt-1">Printed as “Jumlah waktu pelaksanaan” in a consultant contract.</p></div>
                <div><label class="{{ $label }}" for="fTpl">Template</label>
                    <select id="fTpl" name="template_id" class="{{ $input }}"></select>
                    <p class="text-[10px] text-gray-400 mt-1">Automatic = the template of this position, else the general one.</p></div>
                <div><label class="{{ $label }}" for="fSign">Company signatory</label>
                    <select id="fSign" name="signatory_employee_id" class="{{ $input }}"><option value="">Template default (if the template sets one)</option>
                        @foreach($signatories as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>@endforeach</select>
                    <p class="text-[10px] text-gray-400 mt-1">From Letter Templates → Settings → Signers.@if($canDo('general.letter-templates')) <a href="{{ route('general.letters.settings.index', ['section' => 'signers']) }}" class="underline font-semibold">Manage signers</a>@endif</p></div>

                @if($canSalary)
                    <div class="md:col-span-2 border-t border-gray-100 pt-4">
                        <label class="{{ $label }}" for="fSalary">Contract basic salary</label>
                        <input id="fSalary" name="salary" inputmode="decimal" placeholder="e.g. 4.500.000" class="{{ $input }} md:w-1/2">
                        <div class="mt-3 flex items-center justify-between"><span class="{{ $label }} mb-0">Allowances</span>
                            <button type="button" onclick="addComponent()" class="text-[11px] font-semibold text-indigo-700 hover:underline"><i class="fas fa-plus mr-1"></i>Add allowance</button></div>
                        <div id="fComponents" class="mt-2 space-y-2"></div>
                        <p class="text-[11px] text-gray-500 mt-2">Total: <strong id="fTotal">Rp 0</strong></p>
                    </div>
                @else
                    <p class="md:col-span-2 text-[11px] text-gray-400"><i class="fas fa-lock mr-1"></i> Salary fields are hidden: they need the “View Salary” permission. Saving here never changes the salary.</p>
                @endif
                <div class="md:col-span-2"><label class="{{ $label }}" for="fNotes">Notes</label><textarea id="fNotes" name="notes" rows="2" maxlength="2000" class="{{ $input }}"></textarea></div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeContractModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90" id="contractSubmit">Save Contract</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('contractModal');
    const form = document.getElementById('contractForm');
    const templates = @json($templates);
    const storeUrl = @json(route('general.contracts.store', $employee->employee_id));
    const defaultType = @json($defaultType);
    const pkwtMax = {{ $pkwtMaxMonths }};
    const editId = {{ (int) $editId }};
    const activeLocked = {{ $activeLocked ? 'true' : 'false' }};
    const canSalary = {{ $canSalary ? 'true' : 'false' }};
    const $ = id => document.getElementById(id);

    function money(n) { return 'Rp ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function parse(s) {
        s = String(s ?? '').trim(); if (!s) return 0;
        const d = s.lastIndexOf('.'), c = s.lastIndexOf(',');
        let dec = null;
        if (d > -1 && c > -1) dec = d > c ? '.' : ','; else if (c > -1) dec = s.length - c - 1 === 3 ? null : ','; else if (d > -1) dec = s.length - d - 1 === 3 ? null : '.';
        let t = s.replace(new RegExp('[^0-9' + (dec ? '\\' + dec : '') + ']', 'g'), '');
        if (dec) t = t.replace(dec, '.');
        return parseFloat(t) || 0;
    }

    function fillTemplates(type, selected) {
        const sel = $('fTpl');
        sel.innerHTML = '<option value="">Automatic</option>' + (templates[type] || []).map(t =>
            `<option value="${t.id}">${t.name}${t.position ? ' — ' + t.position : ''}${t.is_system_default ? ' (system default)' : ''}</option>`).join('');
        sel.value = selected ?? '';
    }

    function typeRules() {
        const t = $('fType').value;
        $('fEndHelp').textContent = t === 'PKWT' ? `Required, at most ${pkwtMax} months after the start date.` : (t === 'PKWTT' ? 'Leave empty: a PKWTT has no end date.' : 'Optional.');
        $('fEnd').required = t === 'PKWT';
        if (t === 'PKWTT') $('fEnd').value = '';
        $('fEnd').disabled = t === 'PKWTT';
        $('fVolumeWrap').classList.toggle('hidden', t !== 'EXTERNAL');
        fillTemplates(t, null);
    }

    function recalc() {
        if (!canSalary) return;
        let total = parse($('fSalary').value);
        document.querySelectorAll('#fComponents [data-amount]').forEach(i => total += parse(i.value));
        $('fTotal').textContent = money(total);
    }

    window.addComponent = function (name = '', amount = '') {
        const box = $('fComponents'), i = box.children.length;
        const row = document.createElement('div');
        row.className = 'flex gap-2';
        row.innerHTML = `<input name="components[${i}][name]" maxlength="100" placeholder="e.g. Tunjangan Transportasi" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <input name="components[${i}][amount]" data-amount inputmode="decimal" placeholder="Amount" class="w-40 border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <button type="button" class="px-2 text-red-500 hover:text-red-700" aria-label="Remove">&times;</button>`;
        row.querySelector('[name$="[name]"]').value = name;
        row.querySelector('[data-amount]').value = amount;
        row.querySelector('button').onclick = () => { row.remove(); renumber(); recalc(); };
        row.querySelector('[data-amount]').addEventListener('input', recalc);
        box.appendChild(row);
    };
    function renumber() {
        [...$('fComponents').children].forEach((row, i) => {
            row.querySelector('[name$="[name]"]').name = `components[${i}][name]`;
            row.querySelector('[data-amount]').name = `components[${i}][amount]`;
        });
    }

    window.openContractModal = function (c) {
        form.reset();
        if (canSalary) $('fComponents').innerHTML = '';
        const edit = !!c;
        delete form.dataset.confirmed;
        openedStatus = edit ? (['draft','active','terminated'].includes(c.status) ? c.status : 'draft') : null;
        form.action = edit ? c.update : storeUrl;
        $('contractModalTitle').textContent = edit ? 'Edit Contract' : 'Add Contract';
        $('contractModalSub').textContent = edit ? c.number : @json($employee->name);
        $('fNumberWrap').classList.toggle('hidden', !edit);
        $('fType').value = edit ? c.type : defaultType;
        typeRules();
        if (edit) {
            $('fNumber').value = c.number; $('fStart').value = c.start || ''; $('fEnd').value = c.end || ''; $('fSigned').value = c.signed || '';
            $('fStatus').value = ['draft','active','terminated'].includes(c.status) ? c.status : 'draft';
            $('fDept').value = c.department || ''; $('fPos').value = c.position || ''; $('fLoc').value = c.location || ''; $('fVolume').value = c.volume || '';
            $('fSign').value = c.signatory || ''; $('fNotes').value = c.notes || '';
            fillTemplates(c.type, c.template);
            if (canSalary) { $('fSalary').value = c.salary ?? ''; (c.components || []).forEach(r => addComponent(r.name, r.amount)); }
        } else {
            $('fStatus').value = 'draft';
        }
        // A new contract cannot be set to Active while the employee data is incomplete (the server checks it again).
        const activeOpt = $('fStatus').querySelector('option[value="active"]');
        activeOpt.disabled = activeLocked && !(edit && c.isActive);
        activeOpt.textContent = activeOpt.disabled ? 'Active (needs complete employee data)' : 'Active';
        recalc();
        modal.style.display = 'flex'; modal.classList.remove('hidden');
    };
    window.closeContractModal = function () { modal.style.display = 'none'; modal.classList.add('hidden'); };

    // Status changes that matter get the app's confirm dialog before the form is sent.
    let openedStatus = null;
    form.addEventListener('submit', async e => {
        if (form.dataset.confirmed === '1') return;
        const next = $('fStatus').value;
        if (!openedStatus || next === openedStatus) return;
        const ask = next === 'terminated'
            ? ['Terminate this contract? It stops counting as the employee’s running contract.', 'Terminate Contract', 'danger', 'Terminate']
            : (next === 'active' ? ['Activate this contract? The template text is frozen from now on, so later template edits will not change it.', 'Activate Contract', 'primary', 'Activate'] : null);
        if (!ask) return;
        e.preventDefault();
        if (await window.showConfirm(ask[0], ask[1], ask[2], { okText: ask[3], cancelText: 'Cancel' })) { form.dataset.confirmed = '1'; form.submit(); }
    });

    $('fType').addEventListener('change', typeRules);
    if (canSalary) $('fSalary').addEventListener('input', recalc);

    if (editId) document.querySelector('[data-contract-id="' + editId + '"]')?.click();

    @if($reopen)
        // A rule refused the save: open the dialog again with what was typed.
        (function () {
            const old = @json(old());
            const mode = @json($reopen['mode'] ?? 'create');
            openContractModal();
            if (mode === 'edit') { $('contractModalTitle').textContent = 'Edit Contract'; form.action = @json(isset($reopen['id']) ? route('general.contracts.update', $reopen['id']) : ''); $('fNumberWrap').classList.remove('hidden'); }
            const set = (id, v) => { if (v !== undefined && v !== null) $(id).value = v; };
            set('fType', old.contract_type); typeRules(); fillTemplates(old.contract_type || defaultType, old.template_id);
            set('fNumber', old.contract_number); set('fStart', old.start_date); set('fEnd', old.end_date); set('fSigned', old.signed_date);
            set('fStatus', old.status); set('fDept', old.department); set('fPos', old.position); set('fLoc', old.work_location); set('fVolume', old.work_volume);
            set('fSign', old.signatory_employee_id); set('fNotes', old.notes);
            if (canSalary) { set('fSalary', old.salary); (old.components || []).forEach(r => addComponent(r.name, r.amount)); recalc(); }
        })();
    @endif
})();
</script>
@endpush
@endif
@endsection
