{{--
    One list of the Settings tab (contract types or position types), shared by the internal and the external menu.

    Parameters:
      $listId       DOM id of this list
      $title, $hint heading and one-line explanation
      $kind         'contract_type' | 'position_type'
      $rows         ContractOption rows of this list (ordered)
      $usage        [option id => ['contracts' => n, 'templates' => n]]
      $routes       ['update', 'destroy', 'move'] route names
      $baseLabels   [built-in code => label], to say what a custom contract type behaves like
      $canCreate, $canEdit, $canDelete   the Create / Edit / Delete boxes of the menu
      $addModal     id of the modal that adds to this list; $addLabel its button text
      $bulkModal    optional: id of the "add several" modal

    Each row is one form (fields tied to it with the form attribute). Save stays disabled until the row really changed.
--}}
@php
    $isType = $kind === 'contract_type';
    $field = 'w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300 bg-white disabled:bg-gray-50 disabled:text-gray-500';
    $arrow = 'w-6 h-6 inline-flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300';
    $on = $rows->where('is_active', true)->count();
    $heading = $listId . 'Title';
@endphp

<section id="{{ $listId }}" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" aria-labelledby="{{ $heading }}">
    <header class="px-5 pt-4 pb-3 flex flex-col lg:flex-row lg:items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h3 id="{{ $heading }}" class="text-base font-bold text-gray-900">{{ $title }}</h3>
                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[11px] font-semibold">{{ $on }} of {{ $rows->count() }} on</span>
            </div>
            <p class="text-xs text-gray-500 mt-1 max-w-2xl">{{ $hint }}</p>
        </div>
        @if($canCreate)
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if(!empty($bulkModal))
                    <button type="button" data-open="{{ $bulkModal }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300"><i class="fas fa-list text-[10px]"></i> Add several</button>
                @endif
                <button type="button" data-open="{{ $addModal }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300"><i class="fas fa-plus text-[10px]"></i> {{ $addLabel }}</button>
            </div>
        @endif
    </header>

    {{-- Search and On / Off filter --}}
    <div class="px-5 pb-3 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-48 max-w-xs">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
            <input type="search" data-list-search placeholder="Search {{ strtolower($title) }}…" aria-label="Search {{ strtolower($title) }}" autocomplete="off"
                class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200">
        </div>
        <div class="inline-flex rounded-lg border border-gray-200 p-0.5 bg-gray-50" role="group" aria-label="Show">
            @foreach(['' => 'All', 'on' => 'On', 'off' => 'Off'] as $value => $text)
                <button type="button" data-list-filter="{{ $value }}" aria-pressed="{{ $value === '' ? 'true' : 'false' }}"
                    class="px-3 py-1 text-xs font-semibold rounded-md text-gray-600 aria-pressed:bg-white aria-pressed:text-gray-900 aria-pressed:shadow-sm">{{ $text }}</button>
            @endforeach
        </div>
    </div>

    <div class="overflow-x-auto border-t border-gray-100">
        <table class="w-full text-sm text-left min-w-176">
            <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                <tr>
                    <th scope="col" class="pl-5 pr-2 py-2.5 w-14"><span class="sr-only">Order</span><i class="fas fa-sort text-gray-300" aria-hidden="true"></i></th>
                    <th scope="col" class="px-3 py-2.5">Name and description</th>
                    <th scope="col" class="px-3 py-2.5 w-40">Used by</th>
                    <th scope="col" class="px-3 py-2.5 w-20 text-center">Default</th>
                    <th scope="col" class="px-3 py-2.5 w-20 text-center">On form</th>
                    <th scope="col" class="pl-3 pr-5 py-2.5 w-36 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" data-list-body>
                @forelse($rows as $opt)
                    @php
                        $u = $usage[$opt->id] ?? ['contracts' => 0, 'templates' => 0];
                        $inUse = ($u['contracts'] + $u['templates']) > 0;
                        $locked = $isType && $opt->is_system;            // a built-in type: the engine's, never deleted
                        $form = $listId . '-f' . $opt->id;
                    @endphp
                    <tr class="align-top hover:bg-gray-50/60 transition-colors {{ $opt->is_active ? '' : 'bg-gray-50/40' }}" data-row data-active="{{ $opt->is_active ? 'on' : 'off' }}"
                        data-text="{{ strtolower($opt->label . ' ' . $opt->description . ' ' . $opt->code) }}">
                        <td class="pl-5 pr-2 py-3">
                            <div class="flex flex-col items-center">
                                <button type="submit" form="{{ $form }}" formaction="{{ route($routes['move'], $opt->id) }}" name="direction" value="up" class="{{ $arrow }}" title="Earlier on the form" aria-label="Move {{ $opt->label }} up" @disabled(!$canEdit || $loop->first)><i class="fas fa-chevron-up text-[10px]"></i></button>
                                <button type="submit" form="{{ $form }}" formaction="{{ route($routes['move'], $opt->id) }}" name="direction" value="down" class="{{ $arrow }}" title="Later on the form" aria-label="Move {{ $opt->label }} down" @disabled(!$canEdit || $loop->last)><i class="fas fa-chevron-down text-[10px]"></i></button>
                            </div>
                        </td>

                        <td class="px-3 py-3">
                            <form id="{{ $form }}" method="POST" action="{{ route($routes['update'], $opt->id) }}" data-unsaved-ignore data-row-form>@csrf</form>
                            <label class="sr-only" for="{{ $form }}-name">Name</label>
                            <input id="{{ $form }}-name" form="{{ $form }}" name="label" value="{{ $opt->label }}" maxlength="100" required class="{{ $field }} font-semibold" @disabled(!$canEdit)>
                            <label class="sr-only" for="{{ $form }}-desc">Description of {{ $opt->label }}</label>
                            <input id="{{ $form }}-desc" form="{{ $form }}" name="description" value="{{ $opt->description }}" maxlength="255" placeholder="Add a short hint (optional)" class="{{ $field }} mt-1.5 text-xs" @disabled(!$canEdit)>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                                @if($isType)
                                    @if($opt->is_system)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-semibold"><i class="fas fa-lock text-[9px]"></i> Built-in</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-semibold">Custom · behaves like {{ $baseLabels[$opt->base_type] ?? $opt->base_type }}</span>
                                    @endif
                                    <code class="text-gray-400">{{ $opt->code }}</code>
                                @endif
                                @unless($opt->is_active)<span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-semibold">Hidden from the form</span>@endunless
                            </div>
                        </td>

                        <td class="px-3 py-3 whitespace-nowrap text-xs">
                            <span class="inline-flex items-center gap-1.5 {{ $inUse ? 'text-gray-800 font-semibold' : 'text-gray-400' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $inUse ? 'bg-green-500' : 'bg-gray-300' }}" aria-hidden="true"></span>
                                {{ $u['contracts'] }} contract{{ $u['contracts'] === 1 ? '' : 's' }}
                            </span>
                            @if(!$isType || $u['templates'] > 0)
                                <span class="block text-gray-400 mt-0.5 pl-3">{{ $u['templates'] }} template{{ $u['templates'] === 1 ? '' : 's' }}</span>
                            @endif
                        </td>

                        <td class="px-3 py-3 text-center">
                            <input type="hidden" form="{{ $form }}" name="is_default" value="0">
                            <label class="inline-flex items-center justify-center w-8 h-8 rounded-lg cursor-pointer hover:bg-gray-100 {{ $canEdit ? '' : 'opacity-50 cursor-not-allowed' }}" title="Preselected on a new contract">
                                <input type="checkbox" form="{{ $form }}" name="is_default" value="1" data-default class="peer sr-only" @checked($opt->is_default) @disabled(!$canEdit)>
                                <span class="sr-only">Default</span>
                                <i class="fas fa-star text-base text-gray-300 peer-checked:text-amber-400 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300 rounded" aria-hidden="true"></i>
                            </label>
                        </td>

                        <td class="px-3 py-3 text-center">
                            <input type="hidden" form="{{ $form }}" name="is_active" value="0">
                            <label class="inline-flex items-center cursor-pointer {{ $canEdit ? '' : 'opacity-50 cursor-not-allowed' }}">
                                <input type="checkbox" form="{{ $form }}" name="is_active" value="1" data-active-toggle class="peer sr-only" @checked($opt->is_active) @disabled(!$canEdit)>
                                <span class="sr-only">On the form</span>
                                <span class="relative w-9 h-5 rounded-full bg-gray-300 peer-checked:bg-green-600 transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300
                                    after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4" aria-hidden="true"></span>
                            </label>
                        </td>

                        <td class="pl-3 pr-5 py-3">
                            <div class="flex justify-end items-center gap-1.5">
                                @if($canEdit)
                                    <button type="submit" form="{{ $form }}" data-save disabled
                                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-400 text-xs font-semibold transition-colors
                                               enabled:bg-gray-900 enabled:border-gray-900 enabled:text-white enabled:hover:bg-gray-700 disabled:cursor-not-allowed">Save</button>
                                @endif
                                @if($canDelete)
                                    @if($locked)
                                        <span class="w-8 h-8 inline-flex items-center justify-center text-gray-300" title="A built-in type is the engine's: switch it off instead of deleting it."><i class="fas fa-lock text-xs"></i><span class="sr-only">Cannot be deleted</span></span>
                                    @elseif($inUse)
                                        <span class="w-8 h-8 inline-flex items-center justify-center text-gray-300" title="Used by {{ $u['contracts'] }} contract(s) and {{ $u['templates'] }} template(s): switch it off instead."><i class="fas fa-trash-can text-xs"></i><span class="sr-only">In use, cannot be deleted</span></span>
                                    @else
                                        <form method="POST" action="{{ route($routes['destroy'], $opt->id) }}" data-confirm="Delete “{{ $opt->label }}”? This cannot be undone." data-confirm-title="Delete {{ $isType ? 'Contract Type' : 'Position Type' }}" data-confirm-ok="Delete" data-confirm-variant="danger" data-unsaved-ignore>@csrf
                                            <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300" title="Delete" aria-label="Delete {{ $opt->label }}"><i class="fas fa-trash-can text-xs"></i></button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center">
                            <i class="fas fa-layer-group text-2xl text-gray-300 mb-2 block"></i>
                            <p class="text-sm font-semibold text-gray-700">Nothing here yet</p>
                            @if($canCreate)<p class="text-xs text-gray-500 mt-1">Use “{{ $addLabel }}” to create the first one.</p>@endif
                        </td>
                    </tr>
                @endforelse
                <tr data-list-empty class="hidden">
                    <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500"><i class="fas fa-magnifying-glass text-gray-300 mr-1"></i> No option matches your search.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
