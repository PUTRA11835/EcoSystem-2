{{--
    One item of the purchase order form (used for the rows and the template).

    Parameters: $key (field key, "__KEY__" in the template) · $row · plus the
    form's $units, $taxes.
--}}
@php
    $cell  = 'w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800';
    $small = 'block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1';
    $name  = fn (string $f) => "items[{$key}][{$f}]";
    $ticked = array_map('strval', (array) ($row['tax_keys'] ?? []));
@endphp
<div class="border border-gray-200 rounded-lg p-3 space-y-3" data-po-row>
    <div class="grid grid-cols-2 lg:grid-cols-12 gap-3">
        <div class="col-span-2 lg:col-span-5">
            <label class="{{ $small }}">Description</label>
            <input type="text" name="{{ $name('description') }}" data-f="description" required maxlength="255"
                   value="{{ $row['description'] ?? '' }}" class="{{ $cell }}">
        </div>
        <div class="lg:col-span-1">
            <label class="{{ $small }}">Qty</label>
            <input type="number" name="{{ $name('qty') }}" data-f="qty" required min="0.01" step="0.01" value="{{ $row['qty'] ?? 1 }}" class="{{ $cell }}">
        </div>
        <div class="lg:col-span-1">
            <label class="{{ $small }}">Unit</label>
            <select name="{{ $name('unit') }}" data-f="unit" class="{{ $cell }}">
                @foreach($units as $unit)
                    <option value="{{ $unit }}" @selected(($row['unit'] ?? '') === $unit)>{{ $unit }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-1">
            <label class="{{ $small }}">Curr</label>
            <select name="{{ $name('currency') }}" data-f="currency" class="{{ $cell }}">
                @foreach(['IDR', 'USD', 'EUR', 'SGD'] as $code)
                    <option value="{{ $code }}" @selected(($row['currency'] ?? 'IDR') === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2">
            <label class="{{ $small }}">Amount (unit price)</label>
            <input type="number" name="{{ $name('unit_price') }}" data-f="unit_price" required min="0" step="0.01" value="{{ $row['unit_price'] ?? 0 }}" class="{{ $cell }} text-right">
        </div>
        <div class="col-span-2 lg:col-span-2">
            <label class="{{ $small }}">Project</label>
            <input type="text" name="{{ $name('project_label') }}" data-f="project_label" maxlength="150" value="{{ $row['project_label'] ?? '' }}" placeholder="Project / CA" class="{{ $cell }}">
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
        <div class="lg:col-span-4">
            <label class="{{ $small }}">Period</label>
            <div class="flex items-center gap-1">
                <input type="text" name="{{ $name('period_from') }}" data-f="period_from" data-date autocomplete="off" value="{{ $row['period_from'] ?? '' }}" placeholder="From" class="{{ $cell }}">
                <span class="text-xs text-gray-400">s.d</span>
                <input type="text" name="{{ $name('period_to') }}" data-f="period_to" data-date autocomplete="off" value="{{ $row['period_to'] ?? '' }}" placeholder="To" class="{{ $cell }}">
            </div>
        </div>

        <div class="lg:col-span-3">
            <label class="{{ $small }}">Discount</label>
            <div class="flex gap-1">
                <select name="{{ $name('discount_type') }}" data-f="discount_type" class="{{ $cell }} !w-20">
                    <option value="percent" @selected(($row['discount_type'] ?? 'percent') === 'percent')>%</option>
                    <option value="amount" @selected(($row['discount_type'] ?? '') === 'amount')>Rp</option>
                </select>
                <input type="number" name="{{ $name('discount_value') }}" data-f="discount_value" min="0" step="0.01" value="{{ $row['discount_value'] ?? 0 }}" class="{{ $cell }}">
            </div>
        </div>

        <div class="lg:col-span-4">
            <label class="{{ $small }}">Tax detail</label>
            <div class="flex flex-wrap gap-x-3 gap-y-1">
                @foreach($taxes as $taxKey => $tax)
                    <label class="inline-flex items-center gap-1 text-xs text-gray-700">
                        <input type="checkbox" name="{{ $name('tax_keys') }}[]" value="{{ $taxKey }}" data-tax="{{ $taxKey }}"
                               @checked(in_array($taxKey, $ticked, true)) class="rounded border-gray-300 text-red-800 focus:ring-red-800">
                        {{ $tax['label'] }} ({{ rtrim(rtrim(number_format($tax['rate'], 2), '0'), '.') }}%)
                    </label>
                @endforeach
            </div>
        </div>

        <div class="lg:col-span-1 text-right">
            <button type="button" data-po-remove title="Remove this item" class="px-2 py-1 text-red-600 hover:bg-red-50 rounded">&times;</button>
        </div>
    </div>
</div>
