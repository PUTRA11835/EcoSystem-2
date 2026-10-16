{{--
    One approval-workflow row. Used for the saved steps and, with key "__KEY__", for the template of a newly added row.

    Parameters: $wf   — the editor's configuration (see editor.blade.php): grid, name, select, mandatory
                $key  — row key ("k0", "k1"… or "__KEY__")
                $row  — array: id, name, sign, approver_role_id, ids[], mandatory, is_active
                $roles · $people · $employeeRoles · $cell
--}}
@php
    $field  = fn (string $f) => "steps[{$key}][{$f}]";
    $ticked = array_map('intval', $row['ids'] ?? []);
@endphp
<div class="grid gap-3 items-start p-3 border border-gray-200 rounded-lg bg-white transition-colors"
     style="grid-template-columns: {{ $wf['grid'] }}"
     data-step-row data-new="{{ empty($row['id']) ? '1' : '0' }}">

    <input type="hidden" name="{{ $field('id') }}" value="{{ $row['id'] ?? '' }}">

    <div class="flex items-center gap-1">
        <span class="w-7 h-7 flex items-center justify-center bg-red-800 text-white rounded-full text-xs font-bold" data-step-no></span>
        <span class="flex flex-col leading-none">
            <button type="button" data-move="up" title="Move up" class="px-1 text-gray-400 hover:text-gray-900">&#9650;</button>
            <button type="button" data-move="down" title="Move down" class="px-1 text-gray-400 hover:text-gray-900">&#9660;</button>
        </span>
    </div>

    {{-- Step name — for workflows whose steps are named by the admin (Overtime, Purchase, Cash Advance). --}}
    @if($wf['name'])
        <div>
            <input type="text" name="{{ $field('name') }}" value="{{ $row['name'] ?? '' }}" required maxlength="100"
                   placeholder="{{ $wf['name_placeholder'] ?? 'Step name' }}" data-name-input class="{{ $cell }}">
            @unless($wf['select'])
                <span class="hidden mt-1 text-[10px] font-bold text-amber-700 uppercase" data-unsaved-badge>Unsaved</span>
            @endunless
        </div>
    @endif

    {{-- What the step signs as / its actor — Reimbursement (Accounting · Cashier · Approver), Cash Advance (Verificator · Approver). --}}
    @if($wf['select'])
        <div>
            <select name="{{ $field($wf['select']['field']) }}" required data-sign class="{{ $cell }}">
                <option value="">— choose —</option>
                @foreach($wf['select']['options'] as $value => $label)
                    <option value="{{ $value }}" @selected(($row['sign'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="hidden mt-1 text-[10px] font-bold text-amber-700 uppercase" data-unsaved-badge>Unsaved</span>
        </div>
    @endif

    <div>
        <select name="{{ $field('approver_role_id') }}" data-position data-searchable="true" data-search-placeholder="Search position…" class="{{ $cell }}">
            <option value="">— any / by name —</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected((int) ($row['approver_role_id'] ?? 0) === (int) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        {{-- The position(s) the chosen names hold, shown even when no position was picked. --}}
        <p class="mt-1 text-[10px] text-gray-500 leading-tight" data-role-hint>
            @php
                $held = collect($ticked)->flatMap(fn ($id) => $employeeRoles[$id] ?? [])->unique()->values();
            @endphp
            @if($held->isNotEmpty())Names hold: {{ $held->implode(', ') }}@endif
        </p>
    </div>

    <div>
        @include('hr-general.partials.approval-workflow.people-picker', [
            'people' => $people, 'ticked' => $ticked, 'fieldName' => $field('approver_employee_ids') . '[]',
        ])
    </div>

    @if($wf['mandatory'])
        <label class="flex justify-center pt-1.5" title="Checked: fixed person — the requester does not choose. Unchecked: the requester must choose.">
            <input type="checkbox" name="{{ $field('mandatory') }}" value="1" @checked(!empty($row['mandatory']))
                   class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
        </label>
    @endif

    <div class="flex justify-center">
        @include('hr-general.recruitment.components.toggle-switch', [
            'toggleName' => $field('is_active'), 'toggleChecked' => $row['is_active'] ?? true, 'toggleText' => ' ',
        ])
    </div>

    <div class="flex justify-center">
        <button type="button" data-remove title="Remove this step (applied when you save)" aria-label="Remove step"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border bg-white border-red-200 text-red-600 hover:bg-red-50 transition-all">
            <i class="fas fa-trash text-xs"></i>
        </button>
    </div>
</div>
