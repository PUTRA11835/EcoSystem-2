{{--
    Approval workflow editor — ONE form, ONE Save for every step. Shared by Reimbursement, Overtime, Purchase Request
    and Cash Advance (CA and CAR), so all four look and behave the same; each module only switches on the columns it needs.

    Rows are added and removed on the page and only written when Save is pressed. Unsaved rows are marked; leaving with
    unsaved changes asks first (the page includes the shared unsaved guard).

    $wf (array):
      uid           unique per editor on the page ("reimbursement", "ca", "car", …)
      action        URL of the sync route
      title, blurb  heading and the sentence under it
      module        optional: sent as a hidden field (Cash Advance serves two workflows from one route)
      name          bool   a free "Step name" column (Overtime, Purchase Request, Cash Advance)
      name_placeholder
      select        null | ['field' => 'signs_as', 'label' => 'Signs as', 'options' => [value => label]]
      mandatory     bool   the "Mandatory" column (steps the requester may have to choose)
      dynamic       bool   an "Approver" column: fixed position / names, the requester's supervisor, or the leader of the requester's project (Overtime)
      mandatory_hint  explanation printed under the table
      open_count    int|null  documents in progress that a NEW step could also be applied to
      open_label    "document(s)" / "request(s)" …
      note          optional extra paragraph (an info box above the table)
    $steps · $roles · $employees (Employee models) · $roleMembers [role_id => employee ids] · $employeeRoles [employee id => [role names]]
--}}
@php
    $cell   = 'w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-red-800 bg-white';
    $people = $employees->map(fn ($e) => ['id' => (int) $e->employee_id, 'name' => $e->fullNameLabel()])->values();

    $wf += ['module' => null, 'name' => false, 'select' => null, 'mandatory' => false, 'dynamic' => false, 'open_count' => null, 'open_label' => 'document(s)', 'note' => null];

    // Column widths follow the columns this workflow actually has.
    $columns = array_values(array_filter([
        '4.5rem',
        $wf['name'] ? '11rem' : null,
        $wf['select'] ? '9.5rem' : null,
        $wf['dynamic'] ? '11rem' : null,
        '13rem',
        'minmax(14rem,1fr)',
        $wf['mandatory'] ? '6rem' : null,
        '6.5rem',
        '3rem',
    ]));
    $wf['grid'] = implode(' ', $columns);
    $minWidth = 40 + (int) $wf['name'] * 11 + (int) (bool) $wf['select'] * 9.5 + (int) $wf['mandatory'] * 6 + (int) $wf['dynamic'] * 11;

    // After a failed save only the editor that was submitted shows what was typed (Cash Advance has two).
    $submitted = old('steps') !== null && (old('module') === null || old('module') === ($wf['module'] ?? old('module')));
    $field = $wf['select']['field'] ?? null;

    $rows = $submitted
        ? collect(old('steps'))->map(fn ($r) => [
            'id' => $r['id'] ?? '', 'name' => $r['name'] ?? '', 'sign' => $field ? ($r[$field] ?? '') : '', 'source' => $r['source'] ?? 'fixed',
            'approver_role_id' => $r['approver_role_id'] ?? '',
            'ids' => $r['approver_employee_ids'] ?? [], 'mandatory' => !empty($r['mandatory']), 'is_active' => !empty($r['is_active']),
        ])->values()
        : $steps->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'sign' => $field ? ($s->{$field} ?? '') : '',
            'source' => ['direct_manager' => 'supervisor', 'project_lead' => 'project_lead'][$s->approver_type ?? ''] ?? 'fixed',
            'approver_role_id' => $s->approver_role_id, 'ids' => $s->approver_employee_ids ?? [],
            'mandatory' => !($s->requester_selectable ?? false), 'is_active' => $s->is_active,
        ]);

    $blank = ['id' => '', 'name' => '', 'sign' => '', 'source' => 'fixed', 'approver_role_id' => '', 'ids' => [], 'mandatory' => true, 'is_active' => true];
    $savedFlag = session('workflow_saved') === ($wf['module'] ?: true) || session('workflow_saved') === true;
@endphp

<form method="POST" action="{{ $wf['action'] }}" class="bg-white rounded-xl p-6 shadow-sm" id="workflowForm-{{ $wf['uid'] }}"
      data-wf data-unsaved-label="{{ $wf['title'] }}">
    @csrf
    @if($wf['module'])
        <input type="hidden" name="module" value="{{ $wf['module'] }}">
    @endif

    <div class="mb-4 pb-3 border-b-2 border-gray-100">
        <h2 class="text-2xl font-bold text-gray-900">{{ $wf['title'] }}</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ $wf['blurb'] }} Changes are applied together when you press <strong>Save Workflow</strong>.
        </p>
    </div>

    @if($wf['note'])
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-4">
            <p class="text-sm text-blue-900">{!! $wf['note'] !!}</p>
        </div>
    @endif

    @if($savedFlag)
        <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-2.5" role="status">
            <i class="fas fa-circle-check"></i> Approval workflow saved successfully.
        </div>
    @endif

    @if($submitted)
        @foreach($errors->getBag('default')->getMessages() as $errorField => $messages)
            @if(str_starts_with($errorField, 'steps.'))
                <p class="mb-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2">{{ $messages[0] }}</p>
            @endif
        @endforeach
    @endif

    <div class="overflow-x-auto">
        <div style="min-width: {{ $minWidth + 18 }}rem">
            <div class="grid gap-3 px-3 pb-2 text-[11px] font-semibold text-gray-500 uppercase tracking-wide" style="grid-template-columns: {{ $wf['grid'] }}">
                <span>Order</span>
                @if($wf['name'])<span>Step name</span>@endif
                @if($wf['select'])<span>{{ $wf['select']['label'] }}</span>@endif
                @if($wf['dynamic'])<span>Approver</span>@endif
                <span>{{ $wf['dynamic'] ? 'Position / fallback' : 'Position' }}</span>
                <span>Name</span>
                @if($wf['mandatory'])
                    <span class="text-center" title="Checked: the person on this row always acts and the requester only sees it. Unchecked: the requester must choose.">Mandatory</span>
                @endif
                <span class="text-center">Active</span>
                <span class="text-center">Del</span>
            </div>

            <div class="space-y-2" data-wf-rows>
                @foreach($rows as $i => $row)
                    @include('hr-general.partials.approval-workflow.row', ['key' => 'k' . $i, 'row' => $row])
                @endforeach
            </div>

            <p data-wf-empty class="hidden text-sm text-gray-400 text-center py-6">No step yet — add one below.</p>

            {{-- Add: centered; it appends one more row, nothing is saved yet. --}}
            <div class="flex justify-center mt-4">
                <button type="button" data-wf-add
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white text-red-800 text-sm font-semibold rounded-lg border border-red-200 hover:bg-red-50 transition-all">
                    <i class="fas fa-plus text-xs"></i> Add Step
                </button>
            </div>
        </div>
    </div>

    <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-gray-500 space-y-1">
            @if($wf['mandatory'])
                <p><strong>Mandatory</strong> — {{ $wf['mandatory_hint'] ?? 'checked: the person here always acts and the requester does not choose. Unchecked: the requester must choose from them.' }}</p>
            @endif
            <p>Steps run top to bottom; one person per step is enough. Empty names = everyone holding the position.</p>
            @if($wf['dynamic'])
                <p><strong>Employee's supervisor</strong> / <strong>Project leader</strong> — found from each requester's own data when the request is made (Direct Supervision in the employee data; Lead / Project Manager / Co Project Manager of the project they are in). The position is then the <strong>fallback</strong> when there is nobody; without one, a requester with no supervisor or project cannot submit.</p>
            @endif
            @if(($wf['open_count'] ?? 0) > 0)
                <label class="inline-flex items-center gap-1.5 text-amber-800"
                       title="Documents in progress also need the NEW steps. Approved or rejected ones are never touched.">
                    <input type="checkbox" name="apply_to_open" value="1" class="w-3.5 h-3.5 rounded border-gray-300">
                    Also apply new steps to {{ $wf['open_count'] }} {{ $wf['open_label'] }} in progress
                </label>
            @endif
        </div>

        <button type="submit" class="px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
            <i class="fas fa-floppy-disk mr-1.5"></i> Save Workflow
        </button>
    </div>

    <template data-wf-template>
        @include('hr-general.partials.approval-workflow.row', ['key' => '__KEY__', 'row' => $blank])
    </template>
</form>

@once
@push('scripts')
<script>
    (function () {
        const members = @json($roleMembers);
        const rolesOf = @json($employeeRoles);

        function init(form) {
            const rows = form.querySelector('[data-wf-rows]');
            const template = form.querySelector('[data-wf-template]');
            if (!rows || !template) return;

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
                form.querySelector('[data-wf-empty]').classList.toggle('hidden', all.length > 0);
            }

            // An unsaved row (new, or different from how it was loaded) is marked.
            function mark(row) {
                const dirty = row.dataset.new === '1' || serialize(row) !== row.dataset.initial;
                row.classList.toggle('border-amber-400', dirty);
                row.classList.toggle('bg-amber-50/60', dirty);
                row.classList.toggle('border-gray-200', !dirty);
                const badge = row.querySelector('[data-unsaved-badge]');
                if (badge) badge.classList.toggle('hidden', !dirty);
            }

            // "Approver" (dynamic workflows): the supervisor / project leader come from the requester, so names are not used and the
            // position becomes the fallback.
            function applySource(row) {
                const src = row.querySelector('[data-source]');
                if (!src) return;
                const dyn = src.value !== 'fixed';
                const cell = row.querySelector('[data-names-cell]');
                cell.classList.toggle('opacity-40', dyn);
                cell.classList.toggle('pointer-events-none', dyn);
                cell.querySelectorAll('input[type="checkbox"]').forEach(box => { box.disabled = dyn; });
                const pos = row.querySelector('[data-position]');
                pos.options[0].textContent = dyn ? '— no fallback —' : '— any / by name —';
                const hint = row.querySelector('[data-source-hint]');
                hint.classList.toggle('hidden', !dyn);
                hint.textContent = src.value === 'supervisor' ? "The requester's Direct Supervision. Fallback: the position here."
                    : src.value === 'project_lead' ? "Lead / Project Manager of the requester's project. Fallback: the position here." : '';
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

            function changed() { form.dispatchEvent(new Event('change', { bubbles: true })); }

            function wire(row) {
                const picker = row.querySelector('[data-pp]');
                const count = row.querySelector('[data-pp-count]');
                const search = row.querySelector('[data-pp-search]');

                row.querySelector('[data-position]').addEventListener('change', () => applyPosition(row));
                row.querySelector('[data-source]')?.addEventListener('change', () => applySource(row));

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
                    if (prev) { rows.insertBefore(row, prev); renumber(); changed(); }
                });
                row.querySelector('[data-move="down"]').addEventListener('click', function () {
                    const next = row.nextElementSibling;
                    if (next) { rows.insertBefore(next, row); renumber(); changed(); }
                });
                row.querySelector('[data-remove]').addEventListener('click', function () {
                    row.remove();
                    renumber();
                    changed();
                });

                sortPicker(picker);
                applyPosition(row);
                applySource(row);
                row.dataset.initial = serialize(row);
                mark(row);
            }

            rows.querySelectorAll('[data-step-row]').forEach(wire);
            renumber();

            form.querySelector('[data-wf-add]').addEventListener('click', function () {
                const key = 'r' + Math.random().toString(36).slice(2, 10);
                const holder = document.createElement('div');
                holder.innerHTML = template.innerHTML.replace(/__KEY__/g, key).trim();
                const row = holder.firstElementChild;
                rows.appendChild(row);
                wire(row);
                renumber();
                changed();
                const first = row.querySelector('[data-name-input], select');
                if (first) first.focus();
            });

            // A row that cannot be saved is stopped here, with the row marked.
            form.addEventListener('submit', function (event) {
                const all = rows.querySelectorAll('[data-step-row]');
                let bad = null;

                all.forEach(function (row) {
                    const nameInput = row.querySelector('[data-name-input]');
                    const sign = row.querySelector('[data-sign]');
                    const role = row.querySelector('[data-position]').value;
                    const names = row.querySelectorAll('[data-pp] input:checked').length;
                    const src = row.querySelector('[data-source]');
                    const dyn = !!src && src.value !== 'fixed';
                    const ok = (!nameInput || nameInput.value.trim() !== '') && (!sign || sign.value !== '') && (dyn || role !== '' || names > 0);
                    row.classList.toggle('border-red-400', !ok);
                    if (!ok && !bad) bad = row;
                });

                if (all.length === 0 || bad) {
                    event.preventDefault();
                    window.showNotification && window.showNotification(
                        all.length === 0 ? 'Add at least one step.' : 'Every step needs its name / role and a position or a name.', 'error');
                    if (bad) bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            // Success banner fades; the saved rows are already what is shown.
            const banner = form.querySelector('[role="status"]');
            if (banner) setTimeout(() => banner.remove(), 6000);
        }

        document.querySelectorAll('form[data-wf]').forEach(init);
    })();
</script>
@endpush
@endonce
