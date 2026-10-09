{{--
    "New / Edit asset" modal of Inventory & Assets → Assets. Opened with invOpen('inventoryAssetModal', …)
    (see components/modal-helpers, which also gives it the calendar). Posts to assets.store, or assets.update for a record. After a failed save the page
    reloads and this modal reopens with the typed values and the error list.
    Parameters: $choices (the options each dropdown offers — Settings): category · condition · status · location
                $employees ([id => "Name (ECI)"] an asset can be handed to)
--}}
@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
@endphp

<div id="inventoryAssetModal" class="inv-modal hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4"
    data-title-new="Add Asset" data-title-edit="Edit Asset" data-submit-new="Save Asset">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[92vh] flex flex-col">
        <form method="POST" enctype="multipart/form-data" class="flex flex-col min-h-0"
            data-store-action="{{ route('general.inventory.assets.store') }}"
            data-update-action="{{ route('general.inventory.assets.update', '__ID__') }}">
            @csrf
            <input type="hidden" name="_modal" value="inventory-asset">
            <input type="hidden" name="_id" value="">

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 data-modal-title class="text-sm font-bold text-gray-800">Add Asset</h3>
                <button type="button" onclick="invClose('inventoryAssetModal')" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>

            <div class="px-5 py-4 space-y-4 overflow-y-auto">
                <div data-form-errors>@include('hr-general.recruitment.components.form-errors')</div>

                <div class="flex flex-col sm:flex-row gap-4">
                    @include('hr-general.inventory.components.photo-field', ['label' => 'Asset photo', 'hint' => 'Optional. JPG, PNG or WebP, up to 2 MB.'])
                    <div class="flex-1 grid grid-cols-1 gap-3 content-start">
                        <div>
                            <label for="amCode" class="{{ $label }}">Asset code <span class="font-normal text-gray-400">(auto if empty)</span></label>
                            <input type="text" name="code" id="amCode" maxlength="30" class="{{ $input }} font-mono">
                        </div>
                        <div>
                            <label for="amName" class="{{ $label }}">Asset name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="amName" required maxlength="150" class="{{ $input }}">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="amCategory" class="{{ $label }}">Category <span class="text-red-500">*</span></label>
                        <select name="category" id="amCategory" required class="{{ $input }}">
                            <option value="">-- Select category --</option>
                            @foreach($choices['category'] as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="amBrand" class="{{ $label }}">Brand / make</label>
                        <input type="text" name="brand" id="amBrand" maxlength="100" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="amSerial" class="{{ $label }}">Serial number</label>
                        <input type="text" name="serial_number" id="amSerial" maxlength="100" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="amAssignee" class="{{ $label }}">Assignee</label>
                        <select name="assignee_employee_id" id="amAssignee" data-searchable="true" data-search-placeholder="Search name or ECI…" class="{{ $input }}">
                            <option value="">-- Nobody --</option>
                            @foreach($employees as $id => $text)<option value="{{ $id }}">{{ $text }}</option>@endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Pick an employee, or choose “Nobody” to unassign.</p>
                    </div>
                    <div>
                        <label for="amDate" class="{{ $label }}">Purchase date</label>
                        <input type="text" name="purchase_date" id="amDate" data-date autocomplete="off" placeholder="Select date" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="amPrice" class="{{ $label }}">Purchase price (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" name="purchase_price" id="amPrice" required inputmode="numeric" data-money value="0" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="amCondition" class="{{ $label }}">Condition <span class="text-red-500">*</span></label>
                        <select name="condition" id="amCondition" required class="{{ $input }}">
                            <option value="">-- Select condition --</option>
                            @foreach($choices['condition'] as $value => $text)<option value="{{ $value }}" @selected($value === 'good')>{{ $text }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="amStatus" class="{{ $label }}">Asset status <span class="text-red-500">*</span></label>
                        <select name="status" id="amStatus" required class="{{ $input }}">
                            <option value="">-- Select status --</option>
                            @foreach($choices['status'] as $value => $text)<option value="{{ $value }}" @selected($value === 'available')>{{ $text }}</option>@endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">An assignee makes Available → In use; none makes In use → Available.</p>
                    </div>
                </div>

                <div>
                    <label for="amLocation" class="{{ $label }}">Location</label>
                    <select name="location" id="amLocation" class="{{ $input }}">
                        <option value="">-- None --</option>
                        @foreach($choices['location'] as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
                    </select>
                    @if(!$choices['location'])
                        <p class="text-[11px] text-gray-400 mt-1">No locations yet.@if($can('general.inventory.settings')) Add them in <a href="{{ route('general.inventory.settings.index', ['field' => 'location']) }}" class="underline">Settings</a>.@endif</p>
                    @endif
                </div>
                <div>
                    <label for="amNotes" class="{{ $label }}">Notes</label>
                    <textarea name="notes" id="amNotes" rows="2" maxlength="2000" class="{{ $input }}"></textarea>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="invClose('inventoryAssetModal')" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><span data-submit-label>Save Asset</span></button>
            </div>
        </form>
    </div>
</div>

@if(old('_modal') === 'inventory-asset')
    @push('scripts')
    <script>document.addEventListener('DOMContentLoaded', () => invOpen('inventoryAssetModal', Object.assign({!! \Illuminate\Support\Js::from(old()) !!}, { _reopen: true })));</script>
    @endpush
@endif
