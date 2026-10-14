{{--
    Approval workflow of Reimbursement — ONE form, ONE Save for every step.

    Rows are added and removed on the page and only written when Save is
    pressed. Unsaved rows are marked; leaving with unsaved changes asks first
    (the page includes the shared unsaved guard).

    Variables: $steps · $openCount · $roles · $employees (Employee models) · $roleMembers [role_id => employee ids]
--}}
@php
    $cell   = 'w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white';
    $people = $employees->map(fn ($e) => ['id' => (int) $e->employee_id, 'name' => $e->fullNameLabel()])->values();
    $grid   = 'grid grid-cols-[4.5rem_9.5rem_13rem_minmax(14rem,1fr)_6rem_6.5rem_3rem] gap-3';

    // Saved steps — or, after a failed save, what was typed.
    $old = old('steps');
    $rows = $old !== null
        ? collect($old)->map(fn ($r) => [
            'id' => $r['id'] ?? '', 'signs_as' => $r['signs_as'] ?? '', 'approver_role_id' => $r['approver_role_id'] ?? '',
            'ids' => $r['approver_employee_ids'] ?? [], 'mandatory' => !empty($r['mandatory']), 'is_active' => !empty($r['is_active']),
        ])->values()
        : $steps->map(fn ($s) => [
            'id' => $s->id, 'signs_as' => $s->signs_as ?? '', 'approver_role_id' => $s->approver_role_id,
            'ids' => $s->approver_employee_ids ?? [], 'mandatory' => !$s->requester_selectable, 'is_active' => $s->is_active,
        ]);
@endphp

<form method="POST" action="{{ route('general.reimbursement.settings.steps.sync') }}"
      class="bg-white rounded-xl p-6 shadow-sm" id="workflowForm" data-unsaved-label="Approval Workflow">
    @csrf

    <div class="mb-4 pb-3 border-b-2 border-gray-100">
        <h2 class="text-2xl font-bold text-gray-900">Approval Workflow</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            Steps run top to bottom; one person per step is enough. Changes are applied together when you press <strong>Save Workflow</strong>.
        </p>
    </div>

    @if(session('workflow_saved'))
        <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-2.5" role="status">
            <i class="fas fa-circle-check"></i> Approval workflow saved successfully.
        </div>
    @endif

    @foreach($errors->getBag('default')->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'steps.'))
            <p class="mb-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2">{{ $messages[0] }}</p>
        @endif
    @endforeach

    <div class="overflow-x-auto">
        <div class="min-w-[58rem]">
            <div class="{{ $grid }} px-3 pb-2 text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                <span>Order</span>
                <span>Signs as</span>
                <span>Position</span>
                <span>Name</span>
                <span class="text-center" title="Checked: the person on this row always acts and the requester only sees it. Unchecked: the requester must choose.">Mandatory</span>
                <span class="text-center">Active</span>
                <span class="text-center">Del</span>
            </div>

            <div class="space-y-2" id="stepRows">
                @foreach($rows as $i => $row)
                    @include('hr-general.reimbursement.components.step-row', ['key' => 'k' . $i, 'row' => $row])
                @endforeach
            </div>

            <p id="stepEmpty" class="hidden text-sm text-gray-400 text-center py-6">No step yet — add one below.</p>

            {{-- Add: centered; it appends one more row, nothing is saved yet. --}}
            <div class="flex justify-center mt-4">
                <button type="button" id="addStep"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white text-red-800 text-sm font-semibold rounded-lg border border-red-200 hover:bg-red-50 transition-all">
                    <i class="fas fa-plus text-xs"></i> Add Step
                </button>
            </div>
        </div>
    </div>

    <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-gray-500 space-y-1">
            <p><strong>Mandatory</strong> — checked: the person here always acts; New RB only shows signs as, position and name.
               Unchecked: the requester must choose from them.</p>
            @if($openCount > 0)
                <label class="inline-flex items-center gap-1.5 text-amber-800"
                       title="Documents in progress also need the NEW steps. Approved or rejected ones are never touched.">
                    <input type="checkbox" name="apply_to_open" value="1" class="w-3.5 h-3.5 rounded border-gray-300">
                    Also apply new steps to {{ $openCount }} document(s) in progress
                </label>
            @endif
        </div>

        <button type="submit" class="px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
            <i class="fas fa-floppy-disk mr-1.5"></i> Save Workflow
        </button>
    </div>
</form>

<template id="stepRowTemplate">
    @include('hr-general.reimbursement.components.step-row', [
        'key' => '__KEY__',
        'row' => ['id' => '', 'signs_as' => '', 'approver_role_id' => '', 'ids' => [], 'mandatory' => true, 'is_active' => true],
    ])
</template>

@once
@push('scripts')
<script>
    (function () {
        const form = document.getElementById('workflowForm');
        const rows = document.getElementById('stepRows');
        const template = document.getElementById('stepRowTemplate');
        const members = @json($roleMembers);
        const rolesOf = @json($employeeRoles);
        if (!form || !rows) return;

        function serialize(row) {
            return Array.from(row.querySelectorAll('input, select'))
                .filter(el => el.name && !(el.type === 'hidden' && el.name.endsWith('[id]')))
                .map(el => (el.type === 'checkbox' ? el.name.replace(/\[k\d+\]|\[r\w+\]/, '') + ':' + el.value + ':' + el.checked : el.name.replace(/\[k\d+\]|\[r\w+\]/, '') + '=' + el.value))
                .join('|');
        }

        function renumber() {
            const all = rows.querySelectorAll('[data-step-row]');
            all.forEach((row, i) => {
                row.querySelector('[data-step-no]').textContent = i + 1;
                row.querySelector('[data-move="up"]').classList.toggle('invisible', i === 0);
                row.querySelector('[data-move="down"]').classList.toggle('invisible', i === all.length - 1);
            });
            document.getElementById('stepEmpty').classList.toggle('hidden', all.length > 0);
        }

        // An unsaved row (new, or different from how it was loaded) is marked.
        function mark(row) {
            const dirty = row.dataset.new === '1' || serialize(row) !== row.dataset.initial;
            row.classList.toggle('border-amber-400', dirty);
            row.classList.toggle('bg-amber-50/60', dirty);
            row.classList.toggle('border-gray-200', !dirty);
            row.querySelector('[data-unsaved-badge]').classList.toggle('hidden', !dirty);
        }

        // A chosen position narrows the names to its holders; ticked names always stay.
        function applyPosition(row) {
            const role = row.querySelector('[data-position]').value;
            const allowed = role && members[role] ? members[role].map(String) : null;

            row.querySelectorAll('[data-pp-item]').forEach(function (item) {
                const box = item.querySelector('input[type="checkbox"]');
                item.classList.toggle('hidden', !!(allowed && !allowed.includes(box.value) && !box.checked));
            });
        }

        // Ticked names go to the top of the list, the rest keep their order.
        function sortPicker(picker) {
            const list = picker.querySelector('[data-pp-list]');
            Array.from(list.children)
                .sort((a, b) => {
                    const ca = a.querySelector('input').checked ? 0 : 1;
                    const cb = b.querySelector('input').checked ? 0 : 1;
                    return ca - cb || (a.dataset.order - b.dataset.order);
                })
                .forEach(el => list.appendChild(el));
        }

        function wire(row) {
            const picker = row.querySelector('[data-pp]');
            const count = row.querySelector('[data-pp-count]');
            const search = row.querySelector('[data-pp-search]');

            row.querySelector('[data-position]').addEventListener('change', () => applyPosition(row));

            picker.addEventListener('change', function () {
                // The summary line shows WHO is chosen, not how many.
                const names = Array.from(picker.querySelectorAll('input[type="checkbox"]:checked'))
                    .map(box => box.closest('[data-pp-item]').dataset.label);
                count.textContent = names.length ? names.join(', ') : 'Everyone in the position';
                count.title = names.join(', ');
                count.classList.toggle('text-gray-400', names.length === 0);
                count.classList.toggle('text-gray-800', names.length > 0);

                // Positions held by the ticked names, under the Position column.
                const ids = Array.from(picker.querySelectorAll('input[type="checkbox"]:checked')).map(b => b.value);
                const held = [...new Set(ids.flatMap(id => rolesOf[id] || []))];
                row.querySelector('[data-role-hint]').textContent = held.length ? 'Names hold: ' + held.join(', ') : '';
                sortPicker(picker);
                applyPosition(row);
            });

            search.addEventListener('input', function () {
                const term = search.value.trim().toLowerCase();
                picker.querySelectorAll('[data-pp-item]').forEach(function (item) {
                    item.style.display = term !== '' && !item.dataset.name.includes(term) ? 'none' : '';
                });
            });

            row.addEventListener('input', () => mark(row));
            row.addEventListener('change', () => mark(row));

            row.querySelector('[data-move="up"]').addEventListener('click', function () {
                const prev = row.previousElementSibling;
                if (prev) { rows.insertBefore(row, prev); renumber(); form.dispatchEvent(new Event('change', { bubbles: true })); }
            });
            row.querySelector('[data-move="down"]').addEventListener('click', function () {
                const next = row.nextElementSibling;
                if (next) { rows.insertBefore(next, row); renumber(); form.dispatchEvent(new Event('change', { bubbles: true })); }
            });
            row.querySelector('[data-remove]').addEventListener('click', function () {
                row.remove();
                renumber();
                form.dispatchEvent(new Event('change', { bubbles: true }));
            });

            sortPicker(picker);
            applyPosition(row);
            row.dataset.initial = serialize(row);
            mark(row);
        }

        rows.querySelectorAll('[data-step-row]').forEach(wire);
        renumber();

        document.getElementById('addStep').addEventListener('click', function () {
            const key = 'r' + Math.random().toString(36).slice(2, 10);
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.replace(/__KEY__/g, key).trim();
            const row = holder.firstElementChild;
            rows.appendChild(row);
            wire(row);
            renumber();
            form.dispatchEvent(new Event('change', { bubbles: true }));
            row.querySelector('select').focus();
        });

        // A row that cannot be saved is stopped here, with the row marked.
        form.addEventListener('submit', function (event) {
            const all = rows.querySelectorAll('[data-step-row]');
            let bad = null;

            all.forEach(function (row) {
                const signs = row.querySelector('select[name$="[signs_as]"]').value;
                const role = row.querySelector('[data-position]').value;
                const names = row.querySelectorAll('[data-pp] input:checked').length;
                const ok = signs !== '' && (role !== '' || names > 0);
                row.classList.toggle('border-red-400', !ok);
                if (!ok && !bad) bad = row;
            });

            if (all.length === 0 || bad) {
                event.preventDefault();
                window.showNotification && window.showNotification(
                    all.length === 0 ? 'Add at least one step.' : 'Every step needs "Signs as" and a position or a name.', 'error');
                if (bad) bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // Success banner fades; the saved rows are already what is shown.
        const banner = form.querySelector('[role="status"]');
        if (banner) setTimeout(() => banner.remove(), 6000);
    })();
</script>
@endpush
@endonce
