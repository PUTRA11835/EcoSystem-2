{{--
    Purchase Order rules — one section of the Purchase Management Settings tab.

    Parameters: $poSettings (PurchaseOrderSetting) · $employees (Collection<Employee>)
--}}
<form method="POST" action="{{ route('general.purchase-order.settings.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6">
    @csrf

    <div class="pb-4 border-b-2 border-gray-100">
        <h2 class="text-2xl font-bold text-gray-900">Purchase Order</h2>
        <p class="text-sm text-gray-500 mt-0.5">Whether a PO needs a purchase request, the tax rates it uses, and its default signers.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Company name</label>
            <input type="text" name="company_name" maxlength="150" required value="{{ old('company_name', $poSettings->company_name) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <p class="text-xs text-gray-400 mt-1">Printed under the title of the purchase order.</p>
        </div>

        <label class="flex items-start gap-2">
            <input type="checkbox" name="requires_pr" value="1" @checked($poSettings->requires_pr)
                   class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
            <span class="text-sm">
                <span class="font-semibold text-gray-800">Require a purchase request</span>
                <span class="block text-xs text-gray-500">When off, a manual PO is allowed.</span>
            </span>
        </label>
    </div>

    <div>
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Tax Rates (%)</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
            @foreach(\App\Models\PurchaseOrder\PurchaseOrderSetting::TAXES as $key => [$taxLabel, $column, $kind])
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $taxLabel }}</label>
                <input type="number" name="{{ $column }}" min="0" max="100" step="0.01" required
                       value="{{ old($column, $poSettings->{$column}) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-[10px] text-gray-400 mt-1">{{ $kind === 'add' ? 'Added to the total' : 'Withheld from payment' }}</p>
            </div>
            @endforeach
        </div>
    </div>

    <div>
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Default Signers</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach(['accounting_employee_id' => 'Accounting', 'cashier_employee_id' => 'Cashier'] as $field => $title)
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $title }}</label>
                <select name="{{ $field }}" data-searchable="true" data-search-placeholder="Search employee name…"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                    <option value="">— none —</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected((int) $poSettings->{$field} === (int) $employee->employee_id)>
                            {{ $employee->fullNameLabel() }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Preselected on a new purchase order; it can be changed per document.</p>
            </div>
            @endforeach
        </div>
    </div>

    <div class="sticky bottom-0 -mx-6 -mb-6 px-6 py-3 bg-white/95 backdrop-blur border-t border-gray-100 rounded-b-xl">
        <button type="submit" class="px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
            Save Purchase Order Settings
        </button>
    </div>
</form>
