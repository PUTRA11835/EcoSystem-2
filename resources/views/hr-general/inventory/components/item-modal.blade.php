{{--
    "New / Edit inventory item" modal of Inventory & Assets → Inventory. Opened with invOpen('inventoryItemModal', …)
    (see components/modal-helpers). Posts to items.store, or items.update for a record. After a failed save the page
    reloads and this modal reopens with the typed values and the error list.
    Parameters: $choices (the options each dropdown offers — Settings): category · unit · location
--}}
@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
@endphp

<div id="inventoryItemModal" class="inv-modal hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4"
    data-title-new="Add Inventory Item" data-title-edit="Edit Inventory Item" data-submit-new="Save Inventory">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[92vh] flex flex-col">
        <form method="POST" enctype="multipart/form-data" class="flex flex-col min-h-0"
            data-store-action="{{ route('general.inventory.items.store') }}"
            data-update-action="{{ route('general.inventory.items.update', '__ID__') }}">
            @csrf
            <input type="hidden" name="_modal" value="inventory-item">
            <input type="hidden" name="_id" value="">

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 data-modal-title class="text-sm font-bold text-gray-800">Add Inventory Item</h3>
                <button type="button" onclick="invClose('inventoryItemModal')" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>

            <div class="px-5 py-4 space-y-4 overflow-y-auto">
                <div data-form-errors>@include('hr-general.recruitment.components.form-errors')</div>

                <div class="flex flex-col sm:flex-row gap-4">
                    @include('hr-general.inventory.components.photo-field', ['label' => 'Item photo', 'hint' => 'Optional. JPG, PNG or WebP, up to 2 MB.'])
                    <div class="flex-1 grid grid-cols-1 gap-3 content-start">
                        <div>
                            <label for="imCode" class="{{ $label }}">Item code <span class="font-normal text-gray-400">(auto if empty)</span></label>
                            <input type="text" name="code" id="imCode" maxlength="30" class="{{ $input }} font-mono">
                        </div>
                        <div>
                            <label for="imName" class="{{ $label }}">Item name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="imName" required maxlength="150" class="{{ $input }}">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="imCategory" class="{{ $label }}">Category <span class="text-red-500">*</span></label>
                        <select name="category" id="imCategory" required class="{{ $input }}">
                            <option value="">-- Select category --</option>
                            @foreach($choices['category'] as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="imUnit" class="{{ $label }}">Unit <span class="text-red-500">*</span></label>
                        <select name="unit" id="imUnit" required class="{{ $input }}">
                            <option value="">-- Select unit --</option>
                            @foreach($choices['unit'] as $value => $text)<option value="{{ $value }}" @selected($value === 'pcs')>{{ $text }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="imQty" class="{{ $label }}">Qty <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" id="imQty" required min="0" step="1" value="0" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="imMin" class="{{ $label }}">Minimum stock <span class="text-red-500">*</span></label>
                        <input type="number" name="min_stock" id="imMin" required min="0" step="1" value="0" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="imPrice" class="{{ $label }}">Unit price (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" name="unit_price" id="imPrice" required inputmode="numeric" data-money value="0" class="{{ $input }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="imLocation" class="{{ $label }}">Location</label>
                        <select name="location" id="imLocation" class="{{ $input }}">
                            <option value="">-- None --</option>
                            @foreach($choices['location'] as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
                        </select>
                        @if(!$choices['location'])
                            <p class="text-[11px] text-gray-400 mt-1">No locations yet.@if($can('general.inventory.settings')) Add them in <a href="{{ route('general.inventory.settings.index', ['field' => 'location']) }}" class="underline">Settings</a>.@endif</p>
                        @endif
                    </div>
                    <div>
                        <label for="imStatus" class="{{ $label }}">Status</label>
                        <select name="status_override" id="imStatus" class="{{ $input }}">
                            <option value="">Auto from stock</option>
                            @foreach(\App\Models\InventoryItem::STATUSES as $key => $text)<option value="{{ $key }}">{{ $text }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="imNotes" class="{{ $label }}">Notes</label>
                    <textarea name="notes" id="imNotes" rows="2" maxlength="2000" class="{{ $input }}"></textarea>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="invClose('inventoryItemModal')" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><span data-submit-label>Save Inventory</span></button>
            </div>
        </form>
    </div>
</div>

@if(old('_modal') === 'inventory-item')
    @push('scripts')
    <script>document.addEventListener('DOMContentLoaded', () => invOpen('inventoryItemModal', Object.assign({!! \Illuminate\Support\Js::from(old()) !!}, { _reopen: true })));</script>
    @endpush
@endif
