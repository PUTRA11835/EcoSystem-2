{{--
    The settings of one tab of Management → Employee: the dropdown lists of that section of the employee profile and the rules its data is
    saved under (App\Support\Management\EmployeeMasterSettings, read by the profile forms and the employee API).

    Needs $panel (EmployeeSettingsController::panel()). The page must include hr-general.recruitment.components.confirm-forms.
    What a person may do follows the boxes of the tab's slug: Create adds an option, Edit changes / moves one and the rules, Delete removes one.
    The blocks carry the ids sec-{section}-{field} / sec-{section}-rules that components/mini-sidebar points at.
--}}
@php
    $section = $panel['section'];
    $slug = \App\Support\Management\EmployeeMasterSettings::SLUGS[$section];
    $canCreate = $canDo($slug, 'create');
    $canEdit = $canDo($slug, 'edit');
    $canDelete = $canDo($slug, 'delete');
    $route = fn (string $name, ...$args) => route("management.employee.{$section}.{$name}", ...$args);
    $field = 'w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300 bg-white disabled:bg-gray-50 disabled:text-gray-500';
    $arrow = 'w-6 h-6 inline-flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300';
    $action = 'hr-general.recruitment.components.icon-action';
    $toggle = 'relative inline-block w-9 h-5 shrink-0 rounded-full bg-gray-300 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300 peer-disabled:opacity-50 after:content-[\'\'] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4';

    $listCount = count($panel['lists']);
    $optionsOn = collect($panel['lists'])->sum(fn ($l) => collect($l['rows'])->where('is_active', true)->count());
    $ruleCount = count($panel['rules']);
    $isContract = $section === 'contract';

    // Rules split by how they are set: numbers and text are limits, yes/no are switches.
    $limits = collect($panel['rules'])->reject(fn ($r) => $r['type'] === 'bool');
    $switches = collect($panel['rules'])->filter(fn ($r) => $r['type'] === 'bool');
@endphp

<div class="space-y-5" id="employeeSettings-{{ $section }}">
    {{-- At a glance --}}
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 font-semibold"><i class="fas fa-link text-[10px]"></i> {{ $panel['applies'] }}</span>
        @if($listCount)<span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 font-semibold">{{ $listCount }} {{ \Illuminate\Support\Str::plural('list', $listCount) }} · {{ $optionsOn }} on the form</span>@endif
        @if($ruleCount)<span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 font-semibold">{{ $ruleCount }} {{ \Illuminate\Support\Str::plural('rule', $ruleCount) }}</span>@endif
        <span class="text-gray-400">Live as soon as it is saved · saved records are never changed</span>
    </div>

    @if(!$canEdit && !$canCreate && !$canDelete)
        <p class="text-xs bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-gray-600"><i class="fas fa-eye mr-1.5"></i> View only — your role has no Create / Edit / Delete here (Management → Roles).</p>
    @endif

    {{-- ── Dropdown lists ─────────────────────────────────────────────────────── --}}
    @foreach($panel['lists'] as $fieldKey => $list)
        @php $on = collect($list['rows'])->where('is_active', true)->count(); @endphp
        <section id="sec-{{ $section }}-{{ $fieldKey }}" class="scroll-mt-24 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" aria-labelledby="list-{{ $section }}-{{ $fieldKey }}">
            <header class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 id="list-{{ $section }}-{{ $fieldKey }}" class="text-base font-bold text-gray-900">{{ $list['label'] }}</h3>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[11px] font-semibold">{{ $on }}/{{ count($list['rows']) }} on</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $list['hint'] }}</p>
                </div>
                <div class="flex items-center gap-4">
                    @if(count($list['rows']) > 8)
                        <label class="relative">
                            <span class="sr-only">Filter {{ $list['label'] }}</span>
                            <i class="fas fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] text-gray-400"></i>
                            <input type="search" data-list-filter="{{ $section }}-{{ $fieldKey }}" placeholder="Filter…" class="{{ $field }} !w-40 !pl-7 !py-1 !text-xs">
                        </label>
                    @endif
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 {{ $canEdit ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed' }}" title="On: saving a value that is not on this list is refused. Off: the list is only a suggestion. Saved with “Save changes” below.">
                        <input type="checkbox" name="restrict[{{ $fieldKey }}]" value="1" form="rulesForm-{{ $section }}" class="peer sr-only" @checked($list['restrict']) @disabled(!$canEdit)>
                        <span class="{{ $toggle }}" aria-hidden="true"></span>
                        Strict list
                    </label>
                </div>
            </header>

            <div class="overflow-x-auto border-t border-gray-100">
                <table class="w-full text-sm text-left min-w-[44rem]" data-list-table="{{ $section }}-{{ $fieldKey }}">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th scope="col" class="pl-5 pr-2 py-2 w-14"><span class="sr-only">Order</span><i class="fas fa-sort text-gray-300" aria-hidden="true"></i></th>
                            <th scope="col" class="px-3 py-2">Name on the form</th>
                            <th scope="col" class="px-3 py-2 w-44">Saved as</th>
                            <th scope="col" class="px-3 py-2 w-24">Used by</th>
                            @if($list['internal'])<th scope="col" class="px-3 py-2 w-24 text-center" title="Not offered for External consultants">Internal</th>@endif
                            <th scope="col" class="px-3 py-2 w-16 text-center">Show</th>
                            <th scope="col" class="pl-3 pr-5 py-2 w-28 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($list['rows'] as $row)
                            @php $form = "opt-{$section}-{$row->id}"; @endphp
                            <tr class="align-middle hover:bg-gray-50/60 {{ $row->is_active ? '' : 'bg-gray-50/40' }}" data-row-text="{{ mb_strtolower($row->label . ' ' . $row->value) }}">
                                <td class="pl-5 pr-2 py-2">
                                    <div class="flex flex-col items-center">
                                        <button type="submit" form="{{ $form }}" formaction="{{ $route('options.move', $row->id) }}" name="direction" value="up" class="{{ $arrow }}" title="Earlier on the form" aria-label="Move {{ $row->label }} up" @disabled(!$canEdit || $loop->first)><i class="fas fa-chevron-up text-[10px]"></i></button>
                                        <button type="submit" form="{{ $form }}" formaction="{{ $route('options.move', $row->id) }}" name="direction" value="down" class="{{ $arrow }}" title="Later on the form" aria-label="Move {{ $row->label }} down" @disabled(!$canEdit || $loop->last)><i class="fas fa-chevron-down text-[10px]"></i></button>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <form id="{{ $form }}" method="POST" action="{{ $route('options.update', $row->id) }}" data-unsaved-ignore>@csrf</form>
                                    <label class="sr-only" for="{{ $form }}-label">Name</label>
                                    <input id="{{ $form }}-label" form="{{ $form }}" name="label" value="{{ $row->label }}" maxlength="150" required class="{{ $field }} font-semibold" @disabled(!$canEdit)>
                                </td>
                                <td class="px-3 py-2"><code class="text-xs text-gray-500 break-all">{{ $row->value }}</code></td>
                                <td class="px-3 py-2 text-xs whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 {{ $row->used ? 'text-gray-800 font-semibold' : 'text-gray-400' }}" title="{{ $row->used ? 'Records that hold this value' : 'No record holds this value yet' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $row->used ? 'bg-green-500' : 'bg-gray-300' }}" aria-hidden="true"></span>{{ number_format($row->used) }}
                                    </span>
                                </td>
                                @if($list['internal'])
                                    <td class="px-3 py-2 text-center"><input type="checkbox" form="{{ $form }}" name="internal_only" value="1" class="rounded border-gray-300" @checked($row->internal_only) @disabled(!$canEdit) aria-label="{{ $row->label }}: internal employees only"></td>
                                @endif
                                <td class="px-3 py-2 text-center"><input type="checkbox" form="{{ $form }}" name="is_active" value="1" class="rounded border-gray-300" @checked($row->is_active) @disabled(!$canEdit) aria-label="Show {{ $row->label }} on the form"></td>
                                <td class="pl-3 pr-5 py-2">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($canEdit)
                                            @include($action, ['icon' => 'check', 'tone' => 'green', 'label' => 'Save ' . $row->label, 'submit' => true, 'submitForm' => $form])
                                        @endif
                                        @if($canDelete)
                                            @if($row->used)
                                                <span class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-300 cursor-not-allowed" title="Used by {{ $row->used }} record(s) — switch it off instead"><i class="fas fa-trash text-xs"></i></span>
                                            @else
                                                @include($action, [
                                                    'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete ' . $row->label,
                                                    'post' => $route('options.destroy', $row->id),
                                                    'confirm' => "Delete “{$row->label}” from {$list['label']}? Nothing uses it.", 'confirmTitle' => 'Delete Option', 'confirmOk' => 'Delete',
                                                ])
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $list['internal'] ? 7 : 6 }}" class="px-5 py-6 text-center text-sm text-gray-400">No option yet — the dropdown on the form is empty.</td></tr>
                        @endforelse
                        <tr class="hidden" data-no-match="{{ $section }}-{{ $fieldKey }}"><td colspan="{{ $list['internal'] ? 7 : 6 }}" class="px-5 py-5 text-center text-sm text-gray-400">Nothing matches.</td></tr>
                    </tbody>
                </table>
            </div>

            @if($canCreate)
                <form method="POST" action="{{ $route('options.store') }}" class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 grid grid-cols-1 md:grid-cols-12 gap-3 items-end" data-unsaved-ignore>
                    @csrf
                    <input type="hidden" name="field" value="{{ $fieldKey }}">
                    <div class="md:col-span-5">
                        <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1" for="add-{{ $section }}-{{ $fieldKey }}">Add an option</label>
                        <input id="add-{{ $section }}-{{ $fieldKey }}" name="label" maxlength="150" required placeholder="Name on the form" class="{{ $field }}">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1" for="addv-{{ $section }}-{{ $fieldKey }}">Saved as <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                        <input id="addv-{{ $section }}-{{ $fieldKey }}" name="value" maxlength="150" placeholder="Same as the name" class="{{ $field }}">
                    </div>
                    <div class="md:col-span-3 flex items-center justify-between gap-2">
                        @if($list['internal'])
                            <label class="inline-flex items-center gap-1.5 text-xs text-gray-600"><input type="checkbox" name="internal_only" value="1" class="rounded border-gray-300"> Internal only</label>
                        @else <span></span> @endif
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus text-[10px]"></i> Add</button>
                    </div>
                </form>
            @endif
        </section>
    @endforeach

    {{-- ── Rules (and the "Strict list" switches above) ───────────────────────── --}}
    @if($ruleCount || $listCount)
        <form id="rulesForm-{{ $section }}" method="POST" action="{{ $route('settings.update') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm" data-unsaved-label="{{ $panel['title'] }} settings">
            @csrf
            <header id="sec-{{ $section }}-rules" class="scroll-mt-24 px-5 py-3.5 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">{{ $isContract ? 'What employees see' : 'Rules' }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $isContract ? 'On their own profile only. HR sees every contract in HR & General.' : 'Checked whenever this section is saved — from the profile, an import or the API.' }}</p>
            </header>

            @if($ruleCount)
                <div class="px-5 py-4 space-y-5">
                    @if($switches->isNotEmpty())
                        <div>
                            @if($limits->isNotEmpty())<p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">{{ $isContract ? 'Columns & rows' : 'Switches' }}</p>@endif
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($switches as $key => $rule)
                                    <input type="hidden" name="rules[{{ $key }}]" value="0">
                                    <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3.5 py-2.5 hover:bg-gray-50 {{ $canEdit ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed' }}" title="{{ $rule['hint'] }}">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-gray-800">{{ $rule['label'] }}</span>
                                            <span class="block text-xs text-gray-500 truncate">{{ $rule['hint'] }}</span>
                                        </span>
                                        <input type="checkbox" name="rules[{{ $key }}]" value="1" class="peer sr-only" @checked(old("rules.{$key}", $rule['value'])) @disabled(!$canEdit)>
                                        <span class="{{ $toggle }}" aria-hidden="true"></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($limits->isNotEmpty())
                        <div>
                            @if($switches->isNotEmpty())<p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">{{ $isContract ? 'Timing' : 'Limits' }}</p>@endif
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                                @foreach($limits as $key => $rule)
                                    <div class="rounded-lg border border-gray-200 px-3.5 py-3">
                                        <label class="block text-sm font-semibold text-gray-800 mb-1.5" for="rule-{{ $section }}-{{ $key }}">{{ $rule['label'] }}</label>
                                        @if($rule['type'] === 'int')
                                            <div class="flex items-center gap-2">
                                                <input id="rule-{{ $section }}-{{ $key }}" type="number" name="rules[{{ $key }}]" value="{{ old("rules.{$key}", $rule['value']) }}" min="{{ $rule['min'] }}" max="{{ $rule['max'] }}" required class="{{ $field }} !w-24" @disabled(!$canEdit)>
                                                <span class="text-[11px] text-gray-400">{{ $rule['min'] }}–{{ $rule['max'] }}</span>
                                            </div>
                                        @else
                                            <input id="rule-{{ $section }}-{{ $key }}" type="text" name="rules[{{ $key }}]" value="{{ old("rules.{$key}", $rule['value']) }}" maxlength="255" required class="{{ $field }}" @disabled(!$canEdit)>
                                        @endif
                                        <p class="text-xs text-gray-500 mt-1.5">{{ $rule['hint'] }}</p>
                                        @error("rules.{$key}")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <p class="px-5 py-4 text-sm text-gray-500">No limits here — only the lists above. “Save changes” stores the Strict list switches.</p>
            @endif

            @if($canEdit)
                <div class="sticky bottom-0 z-10 px-5 py-3 border-t border-gray-100 bg-white/95 backdrop-blur flex items-center justify-between gap-3 rounded-b-xl">
                    <span class="text-xs text-gray-400">Rules and Strict list switches save together.</span>
                    <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 shadow-sm">Save changes</button>
                </div>
            @endif
        </form>
    @endif
</div>

<script>
(function () {
    // Filter the rows of a long list by name or saved value.
    document.querySelectorAll('[data-list-filter]').forEach(input => {
        const key = input.dataset.listFilter;
        const rows = [...document.querySelectorAll(`[data-list-table="${key}"] tbody tr[data-row-text]`)];
        const none = document.querySelector(`[data-no-match="${key}"]`);
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach(r => { const hit = !q || r.dataset.rowText.includes(q); r.classList.toggle('hidden', !hit); if (hit) shown++; });
            none?.classList.toggle('hidden', shown > 0);
        });
    });
})();
</script>
