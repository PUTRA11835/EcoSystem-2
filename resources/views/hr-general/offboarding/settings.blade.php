@extends('dashboard')
@section('title', 'Offboarding Settings')
@section('page-title', 'Offboarding')
@section('page-subtitle', 'Switch Offboarding and its parts on or off, and decide which tasks every offboarding starts with.')

@php
    $canCreate = $canDo('general.offboarding.settings', 'create');
    $canEdit = $canDo('general.offboarding.settings', 'edit');
    $canDelete = $canDo('general.offboarding.settings', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white disabled:bg-gray-50';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $appliesLabel = ['internal' => 'Employees', 'external' => 'Consultants', 'both' => 'Everyone'];
    $switches = [
        ['module_enabled', 'Offboarding', 'Master switch. Off: no new offboarding can be started and the daily job does nothing. Existing records stay visible and read-only.'],
        ['calculator_enabled', 'Final settlement calculator', 'Computes severance, service award, PKWT compensation, unused leave and the final tax from the Legal Basis register. It can only be switched on when every required rule has been reviewed.'],
        ['auto_deactivate', 'Deactivate access automatically', 'On the effective date the person can no longer log in (roles and history are kept). Off: HR presses “Deactivate access” by hand.'],
        ['include_external', 'Include external consultants', 'External consultants can be offboarded too (checklist only — they have no final settlement).'],
        ['require_tasks_before_deactivate', 'Require the other tasks first', 'Access is only deactivated once every other task of the checklist is done.'],
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.offboarding.components.tabs')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif
    @if(session('warning'))<div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-triangle-exclamation mr-1"></i> {{ session('warning') }}</div>@endif

    {{-- How it works --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800">How offboarding works</h3>
        <ol class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mt-3">
            @foreach([
                ['1. Start', 'HR starts an offboarding: who, why (resignation, end of PKWT, retirement, termination, mutual agreement, death — or end of engagement for a consultant) and the effective date.'],
                ['2. Checklist', 'The tasks below are created with their due dates. Overdue tasks are flagged and HR gets a reminder in the notification bell.'],
                ['3. Settlement', 'For employees: the final settlement is calculated from the contract and the legal basis. Consultants skip this step.'],
                ['4. Access', 'On the effective date access is deactivated — automatically or by HR. The person keeps their history; nothing is deleted.'],
                ['5. Complete', 'When every task is done and access is off, Complete closes the contract (and ends a consultant’s engagement).'],
            ] as [$t, $d])
                <li class="rounded-lg bg-gray-50 border border-gray-100 p-3"><p class="text-xs font-bold text-gray-800">{{ $t }}</p><p class="text-[11px] text-gray-500 mt-1">{{ $d }}</p></li>
            @endforeach
        </ol>
    </div>

    {{-- Switches --}}
    <form method="POST" action="{{ route('general.offboarding.settings.save') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm" data-unsaved-ignore>@csrf
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between"><h3 class="text-sm font-bold text-gray-800">Switches</h3>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $settings->module_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">Offboarding is {{ $settings->module_enabled ? 'ON' : 'OFF' }}</span></div>
        <div class="divide-y divide-gray-100">
            @foreach($switches as [$name, $title, $text])
                <label class="px-5 py-4 flex items-start justify-between gap-4 {{ $canEdit ? 'cursor-pointer' : '' }}">
                    <span class="min-w-0"><span class="block text-sm font-semibold text-gray-800">{{ $title }}</span><span class="block text-[11px] text-gray-500 mt-0.5 max-w-2xl">{{ $text }}</span>
                        @if($name === 'calculator_enabled' && $missingLegal)<span class="block text-[11px] text-amber-700 mt-1"><i class="fas fa-scale-balanced mr-1"></i> Cannot be switched on yet — not reviewed: {{ implode(', ', $missingLegal) }}.</span>@endif</span>
                    <span class="relative shrink-0 mt-0.5"><input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($settings->$name) @disabled(!$canEdit)>
                        <span class="block w-11 h-6 rounded-full bg-gray-300 peer-checked:bg-green-500 transition peer-disabled:opacity-60"></span>
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span></span>
                </label>
            @endforeach
            <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="{{ $label }}" for="rem">Remind about tasks (days before the due date)</label><input id="rem" type="number" min="0" max="30" name="reminder_days" value="{{ $settings->reminder_days }}" class="{{ $input }} md:w-40" @disabled(!$canEdit)></div>
                <div><label class="{{ $label }}" for="div">Daily-wage divisor for unused leave</label><input id="div" type="number" step="0.5" min="1" max="31" name="leave_divisor" value="{{ rtrim(rtrim((string) $settings->leave_divisor, '0'), '.') }}" class="{{ $input }} md:w-40" @disabled(!$canEdit)>
                    <p class="text-[10px] text-gray-400 mt-1">21 for a five-day week, 25 for six days (see the register).</p></div>
            </div>
        </div>
        @if($canEdit)<div class="px-5 py-3 border-t border-gray-100 flex justify-end"><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 shadow-sm">Save settings</button></div>@endif
    </form>

    {{-- Task templates --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
            <div><h3 class="text-sm font-bold text-gray-800">Checklist tasks</h3><p class="text-[11px] text-gray-500 mt-0.5">Every new offboarding starts with these tasks. Changes do not affect offboardings already started.</p></div>
            @if($canCreate)<button type="button" onclick="openTask()" class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-plus mr-1"></i> Add task</button>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500"><tr><th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Task</th><th class="px-4 py-3">Applies to</th><th class="px-4 py-3">Due (days from the effective date)</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @foreach($templates as $t)
                        <tr class="align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3"><span class="font-semibold text-gray-800">{{ $t->name }}</span><span class="block text-[11px] text-gray-400">{{ $t->description }}</span></td>
                            <td class="px-4 py-3">{{ $appliesLabel[$t->applies_to] ?? $t->applies_to }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $t->due_offset_days === 0 ? 'On the day' : (abs($t->due_offset_days) . ' day(s) ' . ($t->due_offset_days < 0 ? 'before' : 'after')) }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $t->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">{{ $t->is_active ? 'Active' : 'Off' }}</span></td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-2">
                                @if($canEdit)<button type="button" class="px-3 py-1.5 rounded-lg border border-gray-300 text-[11px] font-semibold text-gray-700 hover:bg-gray-50" data-task="{{ json_encode(['id' => $t->id, 'name' => $t->name, 'description' => $t->description, 'due' => $t->due_offset_days, 'applies' => $t->applies_to, 'active' => (bool) $t->is_active]) }}" onclick="openTask(JSON.parse(this.dataset.task))">Edit</button>@endif
                                @if($canDelete && $t->task_key !== 'deactivate_access')<form method="POST" action="{{ route('general.offboarding.settings.tasks.destroy', $t->id) }}" data-confirm="Delete the task “{{ $t->name }}”?" data-confirm-title="Delete Task" data-confirm-ok="Delete" data-unsaved-ignore>@csrf<button class="px-3 py-1.5 rounded-lg border border-red-300 text-red-600 text-[11px] font-semibold hover:bg-red-50">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @include('partials.legal-basis-panel', ['legalModule' => 'offboarding'])
</div>

<div id="taskModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <form id="taskForm" method="POST" class="bg-white rounded-xl shadow-xl w-full max-w-lg" data-unsaved-ignore>@csrf
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between"><h3 class="text-sm font-bold text-gray-800" id="taskTitle">Add task</h3><button type="button" onclick="closeTask()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button></div>
        <div class="px-5 py-4 space-y-3">
            <div><label class="{{ $label }}" for="tName">Task name</label><input id="tName" name="name" required maxlength="150" class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="tDesc">Description</label><textarea id="tDesc" name="description" rows="2" maxlength="500" class="{{ $input }}"></textarea></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="tApplies">Applies to</label><select id="tApplies" name="applies_to" class="{{ $input }}"><option value="both">Everyone</option><option value="internal">Employees</option><option value="external">Consultants</option></select></div>
                <div><label class="{{ $label }}" for="tDue">Due (days from effective date)</label><input id="tDue" type="number" name="due_offset_days" min="-365" max="365" value="0" class="{{ $input }}"></div>
            </div>
            <label class="flex items-center gap-2 text-xs text-gray-700"><input type="checkbox" name="is_active" value="1" id="tActive" class="rounded" checked> Active (added to new offboardings)</label>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2"><button type="button" onclick="closeTask()" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save task</button></div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const $ = id => document.getElementById(id), modal = $('taskModal');
    const store = @json(route('general.offboarding.settings.tasks.store')), base = @json(url('general/offboarding/settings/tasks'));
    window.openTask = function (t) {
        $('taskForm').reset(); $('taskTitle').textContent = t ? 'Edit task' : 'Add task';
        $('taskForm').action = t ? base + '/' + t.id + '/update' : store;
        if (t) { $('tName').value = t.name; $('tDesc').value = t.description || ''; $('tApplies').value = t.applies; $('tDue').value = t.due; $('tActive').checked = t.active; }
        modal.style.display = 'flex'; modal.classList.remove('hidden');
    };
    window.closeTask = () => { modal.style.display = 'none'; modal.classList.add('hidden'); };
})();
</script>
@endpush
@endsection
