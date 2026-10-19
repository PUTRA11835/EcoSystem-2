@extends('dashboard')
@section('title', 'Timesheet Settings')
@section('page-title', 'Timesheet & Payment')
@section('page-subtitle', 'Who approves the timesheets and verifies the invoices of external consultants.')

@php
    $canCreate = $canDo('general.external.timesheet.settings', 'create');
    $canEdit = $canDo('general.external.timesheet.settings', 'edit');
    $canDelete = $canDo('general.external.timesheet.settings', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $roleName = collect($roles)->pluck('name', 'id');
    $personName = collect($people)->pluck('name', 'id');
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.external.timesheet.components.tabs')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif

    <p class="text-xs text-gray-500 max-w-3xl">Anyone listed in a step may act on that stage. A change applies to timesheets <strong>submitted afterwards</strong>; timesheets already waiting keep the approvers frozen when they were submitted.</p>

    {{-- Steps per stage --}}
    @foreach($stages as $stage => $stageName)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
                <div><h3 class="text-sm font-bold text-gray-800">{{ $stageName }}</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $stage === 'timesheet_approval' ? 'Reviews the hours and approves or returns the timesheet.' : 'Verifies the uploaded invoice and records the payment.' }}</p></div>
                @if($canCreate)<button type="button" onclick="openStep(null, '{{ $stage }}')" class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-plus mr-1"></i> Add step</button>@endif
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($steps[$stage] as $step)
                    <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $step->name }}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                @if($step->approver_type === 'role') Role: {{ collect($step->role_ids)->map(fn ($id) => $roleName[$id] ?? '#' . $id)->implode(', ') }}
                                @else People: {{ collect($step->employee_ids)->map(fn ($id) => $personName[$id] ?? '#' . $id)->implode(', ') }} @endif
                            </p>
                        </div>
                        <div class="flex gap-2">
                            @if($canEdit)<button type="button" class="px-3 py-1.5 rounded-lg border border-gray-300 text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                                data-step="{{ json_encode(['id' => $step->id, 'stage' => $step->stage, 'name' => $step->name, 'type' => $step->approver_type, 'roles' => $step->role_ids, 'people' => $step->employee_ids]) }}" onclick="openStep(JSON.parse(this.dataset.step))">Edit</button>@endif
                            @if($canDelete)<form method="POST" action="{{ route('general.external.timesheet.settings.steps.destroy', $step->id) }}" data-confirm="Delete the step “{{ $step->name }}”?" data-confirm-title="Delete Step" data-confirm-ok="Delete" data-unsaved-ignore>@csrf
                                <button class="px-3 py-1.5 rounded-lg border border-red-300 text-red-600 text-[11px] font-semibold hover:bg-red-50">Delete</button></form>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Exceptions per consultant --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
            <div><h3 class="text-sm font-bold text-gray-800">Exceptions per consultant</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">A consultant with an exception is approved / verified by the people chosen here instead of the steps above (e.g. their own project lead).</p></div>
            @if($canCreate)<button type="button" onclick="openOverride()" class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-plus mr-1"></i> Add exception</button>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500"><tr><th class="px-4 py-3">Consultant</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Approvers</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($overrides as $o)
                        <tr>
                            <td class="px-4 py-3"><span class="font-semibold text-gray-800">{{ $o->name }}</span><span class="block text-[11px] text-gray-400">{{ $o->eci }}</span></td>
                            <td class="px-4 py-3">{{ $stages[$o->stage] ?? $o->stage }}</td>
                            <td class="px-4 py-3">{{ implode(', ', $o->approver_names) }}</td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-2">
                                @if($canEdit)<button type="button" class="px-3 py-1.5 rounded-lg border border-gray-300 text-[11px] font-semibold text-gray-700 hover:bg-gray-50" data-override="{{ json_encode(['employee_id' => $o->employee_id, 'stage' => $o->stage, 'people' => $o->employee_ids]) }}" onclick="openOverride(JSON.parse(this.dataset.override))">Edit</button>@endif
                                @if($canDelete)<form method="POST" action="{{ route('general.external.timesheet.settings.overrides.destroy', $o->id) }}" data-confirm="Remove the exception of {{ $o->name }}? The steps above apply again." data-confirm-title="Remove Exception" data-confirm-ok="Remove" data-unsaved-ignore>@csrf
                                    <button class="px-3 py-1.5 rounded-lg border border-red-300 text-red-600 text-[11px] font-semibold hover:bg-red-50">Remove</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No exception. Every consultant follows the steps above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Options --}}
    <form method="POST" action="{{ route('general.external.timesheet.settings.options') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm" data-unsaved-ignore>@csrf
        <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Options</h3></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
            <label class="flex items-start gap-3 text-xs text-gray-700"><input type="checkbox" name="allow_self_approval" value="1" class="mt-0.5 rounded" @checked($settings->allow_self_approval) @disabled(!$canEdit)>
                <span><span class="font-semibold text-gray-800">Allow an approver to act on their own timesheet</span><br><span class="text-gray-500">Off by default: nobody approves or verifies their own timesheet or invoice.</span></span></label>
            <div><label class="{{ $label }}" for="backfill">Back-fill limit (days)</label>
                <input id="backfill" type="number" min="0" max="365" name="backfill_days" value="{{ $settings->backfill_days }}" class="{{ $input }} md:w-40" @disabled(!$canEdit)>
                <p class="text-[10px] text-gray-400 mt-1">How far back a consultant may log a day. 0 = no limit.</p></div>
        </div>
        @if($canEdit)<div class="px-5 py-3 border-t border-gray-100 flex justify-end"><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save options</button></div>@endif
    </form>
</div>

{{-- Step dialog --}}
<div id="stepModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <form id="stepForm" method="POST" class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[92vh] flex flex-col" data-unsaved-ignore>@csrf
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between"><h3 class="text-sm font-bold text-gray-800" id="stepTitle">Add step</h3><button type="button" onclick="closeModal('stepModal')" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button></div>
        <div class="px-5 py-4 space-y-3 overflow-y-auto">
            <input type="hidden" name="stage" id="stepStage">
            <div><label class="{{ $label }}" for="stepName">Step name</label><input id="stepName" name="name" required maxlength="120" class="{{ $input }}" placeholder="e.g. Head of RPMO"></div>
            <div><label class="{{ $label }}" for="stepType">Approvers are</label>
                <select id="stepType" name="approver_type" class="{{ $input }}"><option value="role">Everyone holding a role</option><option value="employee">Specific people</option></select></div>
            <div id="stepRoles"><label class="{{ $label }}">Roles</label>
                <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">@foreach($roles as $r)<label class="flex items-center gap-2 text-xs text-gray-700"><input type="checkbox" name="role_ids[]" value="{{ $r->id }}" class="rounded"> {{ $r->name }}</label>@endforeach</div></div>
            <div id="stepPeople" class="hidden"><label class="{{ $label }}">People</label>
                <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">@foreach($people as $p)<label class="flex items-center gap-2 text-xs text-gray-700"><input type="checkbox" name="employee_ids[]" value="{{ $p['id'] }}" class="rounded"> {{ $p['name'] }}</label>@endforeach</div></div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2"><button type="button" onclick="closeModal('stepModal')" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save step</button></div>
    </form>
</div>

{{-- Exception dialog --}}
<div id="overrideModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <form method="POST" action="{{ route('general.external.timesheet.settings.overrides.store') }}" class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[92vh] flex flex-col" data-unsaved-ignore>@csrf
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between"><h3 class="text-sm font-bold text-gray-800">Exception for a consultant</h3><button type="button" onclick="closeModal('overrideModal')" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button></div>
        <div class="px-5 py-4 space-y-3 overflow-y-auto">
            <div><label class="{{ $label }}" for="ovEmployee">External consultant</label>
                <select id="ovEmployee" name="employee_id" required class="{{ $input }}"><option value="">Select a consultant</option>@foreach($consultants as $c)<option value="{{ $c['id'] }}">{{ $c['eci'] }} — {{ $c['name'] }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}" for="ovStage">Stage</label><select id="ovStage" name="stage" class="{{ $input }}">@foreach($stages as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}">Approvers</label>
                <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1" id="ovPeople">@foreach($people as $p)<label class="flex items-center gap-2 text-xs text-gray-700"><input type="checkbox" name="approver_employee_ids[]" value="{{ $p['id'] }}" class="rounded"> {{ $p['name'] }}</label>@endforeach</div></div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2"><button type="button" onclick="closeModal('overrideModal')" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save exception</button></div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const $ = id => document.getElementById(id);
    const show = id => { const m = $(id); m.style.display = 'flex'; m.classList.remove('hidden'); };
    window.closeModal = id => { const m = $(id); m.style.display = 'none'; m.classList.add('hidden'); };
    const storeUrl = @json(route('general.external.timesheet.settings.steps.store'));
    const updateBase = @json(url('general/external/timesheet/settings/steps'));

    function typeUi() { const t = $('stepType').value; $('stepRoles').classList.toggle('hidden', t !== 'role'); $('stepPeople').classList.toggle('hidden', t !== 'employee'); }
    $('stepType').addEventListener('change', typeUi);

    window.openStep = function (step, stage) {
        const f = $('stepForm'); f.reset();
        $('stepTitle').textContent = step ? 'Edit step' : 'Add step';
        f.action = step ? updateBase + '/' + step.id + '/update' : storeUrl;
        $('stepStage').value = step ? step.stage : stage;
        if (step) {
            $('stepName').value = step.name; $('stepType').value = step.type;
            f.querySelectorAll('[name="role_ids[]"]').forEach(c => c.checked = step.roles.includes(+c.value));
            f.querySelectorAll('[name="employee_ids[]"]').forEach(c => c.checked = step.people.includes(+c.value));
        }
        typeUi(); show('stepModal');
    };

    window.openOverride = function (o) {
        const root = $('overrideModal'); root.querySelector('form').reset();
        if (o) { $('ovEmployee').value = o.employee_id; $('ovStage').value = o.stage; root.querySelectorAll('[name="approver_employee_ids[]"]').forEach(c => c.checked = o.people.includes(+c.value)); }
        show('overrideModal');
    };
})();
</script>
@endpush
@endsection
