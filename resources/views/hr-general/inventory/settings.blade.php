@extends('dashboard')
@section('title', 'Inventory Settings')
@section('page-title', 'Inventory & Assets')
@section('page-subtitle', 'Set the choices the dropdowns of the Inventory and Assets forms offer.')

@php
    // Capabilities of this tab, as ticked in Management → Roles.
    $canCreate = $canDo('general.inventory.settings', 'create');
    $canEdit   = $canDo('general.inventory.settings', 'edit');
    $canDelete = $canDo('general.inventory.settings', 'delete');

    $keyed = $config['keyed'];
    $action = 'hr-general.recruitment.components.icon-action';
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $groups = collect($fields)->groupBy('group', true);
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.inventory.components.tabs')

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 items-start">
        {{-- Which dropdown --}}
        <nav class="bg-white rounded-xl border border-gray-200 shadow-sm p-3 space-y-4 lg:sticky lg:top-4" aria-label="Dropdown lists">
            @foreach($groups as $group => $items)
                <div>
                    <p class="px-2 mb-1 text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ $group }}</p>
                    @foreach($items as $key => $item)
                        <a href="{{ route('general.inventory.settings.index', ['field' => $key]) }}"
                            class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition-colors {{ $key === $field ? 'tone-primary' : 'text-gray-600 hover:bg-gray-50' }}"
                            @if($key === $field) aria-current="page" @endif>
                            <span>{{ $item['title'] }}</span>
                            <i class="fas fa-chevron-right text-[9px] opacity-50"></i>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        {{-- The list --}}
        <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-gray-800">{{ $config['group'] }} · {{ $config['title'] }}</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $config['about'] }}</p>
                </div>
                @if($canCreate)
                    <button type="button" onclick="invOpen('inventoryOptionModal')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> Add option
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 w-12">No.</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3 text-right">Used by</th>
                            <th class="px-4 py-3" title="Switched off = no longer offered in the forms">Active</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse($options as $option)
                            @php
                                $used = $usage[$option->value] ?? 0;
                                $payload = ['_id' => $option->id, 'label' => $option->label, 'tone' => $option->tone ?? 'gray', 'is_active' => $option->is_active, 'is_system' => $option->is_system];
                            @endphp
                            <tr data-option-row class="align-middle {{ $option->is_active ? '' : 'bg-gray-50/60 text-gray-400' }}">
                                <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3">
                                    @if($keyed)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ \App\Models\InventoryOption::TONES[$option->tone] ?? \App\Models\InventoryOption::TONES['gray'] }}">{{ $option->label }}</span>
                                    @else
                                        <span data-option-name class="font-semibold {{ $option->is_active ? 'text-gray-800' : '' }}">{{ $option->label }}</span>
                                    @endif
                                    @if($option->is_system)
                                        <span class="ml-1 text-[10px] text-gray-400" title="The app's rules rely on this option"><i class="fas fa-lock"></i> built-in</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($used) }} <span class="text-gray-400">{{ $used === 1 ? 'record' : 'records' }}</span></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{-- The switch applies at once (the page posts it in the background). A built-in option, or no Edit box, locks it. --}}
                                    <form action="{{ route('general.inventory.settings.toggle', $option) }}" method="POST" data-option-toggle>
                                        @csrf
                                        <fieldset class="contents" @disabled(!$canEdit || $option->is_system)>
                                            @include('hr-general.recruitment.components.toggle-switch', [
                                                'toggleName' => 'is_active', 'toggleChecked' => $option->is_active,
                                                'toggleTitle' => $option->is_system ? 'Built-in: always on, the app\'s rules rely on it' : 'Offer this option in the forms',
                                            ])
                                        </fieldset>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        @if($canEdit)
                                            @unless($loop->first)
                                                @include($action, ['icon' => 'arrow-up', 'tone' => 'gray', 'label' => 'Move up', 'post' => route('general.inventory.settings.move', [$option, 'up'])])
                                            @endunless
                                            @unless($loop->last)
                                                @include($action, ['icon' => 'arrow-down', 'tone' => 'gray', 'label' => 'Move down', 'post' => route('general.inventory.settings.move', [$option, 'down'])])
                                            @endunless
                                            <button type="button" title="Edit" aria-label="Edit" onclick="invEdit('inventoryOptionModal', this)"
                                                data-payload="{{ json_encode($payload) }}"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border bg-white transition-all shrink-0 border-blue-200 text-blue-600 hover:bg-blue-50"><i class="fas fa-pen text-xs"></i></button>
                                        @endif
                                        @if($canDelete && !$option->is_system && $used === 0)
                                            @include($action, [
                                                'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete', 'post' => route('general.inventory.settings.destroy', $option),
                                                'confirm' => 'Delete "' . $option->label . '" from this list?', 'confirmTitle' => 'Delete Option',
                                            ])
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">No options yet. @if($canCreate) Use “Add option” to create the first one. @endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="px-5 py-3 border-t border-gray-100 text-[11px] text-gray-400">
                <i class="fas fa-circle-info mr-1"></i>
                Use the switch to turn an option off: it disappears from the forms at once, and records that already use it keep it. An option in use cannot be deleted, only switched off.
                @unless($keyed) Renaming an option renames it on every record that uses it. @endunless
            </p>
        </div>
    </div>
</div>

@include('hr-general.recruitment.components.confirm-forms')

@if($canEdit)
    @push('scripts')
    <script>
    // The Active switch: post it in the background, then dim / undim the row. A refused change puts the switch back.
    document.addEventListener('change', async e => {
        const box = e.target;
        const form = box.closest('form[data-option-toggle]');
        if (!form || box.name !== 'is_active') return;

        const row = form.closest('[data-option-row]');
        const paint = on => {
            row.classList.toggle('bg-gray-50/60', !on);
            row.classList.toggle('text-gray-400', !on);
            row.querySelector('[data-option-name]')?.classList.toggle('text-gray-800', on);
        };
        paint(box.checked);
        box.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'The change could not be saved.');
            showToast(result.message, 'success', 3000);
        } catch (error) {
            box.checked = !box.checked;
            paint(box.checked);
            showToast(error.message, 'error', 5000);
        } finally {
            box.disabled = false;
        }
    });
    </script>
    @endpush
@endif

@if($canCreate || $canEdit)
    @include('hr-general.inventory.components.modal-helpers')

    <div id="inventoryOptionModal" class="inv-modal hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4"
        data-title-new="Add Option" data-title-edit="Edit Option" data-submit-new="Add option">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[92vh] flex flex-col">
            <form method="POST" class="flex flex-col min-h-0"
                data-store-action="{{ route('general.inventory.settings.store') }}"
                data-update-action="{{ route('general.inventory.settings.update', '__ID__') }}">
                @csrf
                <input type="hidden" name="_modal" value="inventory-option">
                <input type="hidden" name="_id" value="">
                <input type="hidden" name="field" value="{{ $field }}">

                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 data-modal-title class="text-sm font-bold text-gray-800">Add Option</h3>
                        <p class="text-[11px] text-gray-400">{{ $config['group'] }} · {{ $config['title'] }}</p>
                    </div>
                    <button type="button" onclick="invClose('inventoryOptionModal')" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>

                <div class="px-5 py-4 space-y-4 overflow-y-auto">
                    <div data-form-errors>@include('hr-general.recruitment.components.form-errors')</div>

                    <div>
                        <label for="optLabel" class="{{ $label }}">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="label" id="optLabel" required maxlength="60" class="{{ $input }}">
                    </div>

                    @if($keyed)
                        <div>
                            <label for="optTone" class="{{ $label }}">Badge colour</label>
                            <select name="tone" id="optTone" class="{{ $input }}">
                                @foreach(array_keys(\App\Models\InventoryOption::TONES) as $tone)
                                    <option value="{{ $tone }}">{{ ucfirst($tone === 'blue' ? 'blue (theme colour)' : $tone) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <label class="inline-flex items-center gap-2 text-xs text-gray-700" data-active-row>
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Offer it in the forms
                    </label>
                    <p data-system-note class="hidden text-[11px] text-gray-400"><i class="fas fa-lock mr-1"></i> A built-in option stays on: the app's rules rely on it. You can still rename and recolour it.</p>
                    @unless($keyed)
                        <p class="text-[11px] text-gray-400" data-rename-note>Renaming also renames it on every record that uses it.</p>
                    @endunless
                </div>

                <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" onclick="invClose('inventoryOptionModal')" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><span data-submit-label>Add option</span></button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const modal = document.getElementById('inventoryOptionModal');
        const box = modal.querySelector('input[name="is_active"]');
        // A built-in option cannot be switched off.
        modal.addEventListener('inv:open', e => {
            const system = !!(e.detail && e.detail.is_system);
            box.disabled = system;
            if (system) box.checked = true;
            modal.querySelector('[data-system-note]').classList.toggle('hidden', !system);
            modal.querySelector('[data-rename-note]')?.classList.toggle('hidden', !(e.detail && e.detail._id));
        });
    })();
    </script>
    @endpush

    @if(old('_modal') === 'inventory-option')
        @push('scripts')
        <script>document.addEventListener('DOMContentLoaded', () => invOpen('inventoryOptionModal', Object.assign({!! \Illuminate\Support\Js::from(old()) !!}, { _reopen: true })));</script>
        @endpush
    @endif
@endif
@endsection
