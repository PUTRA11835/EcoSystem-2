@extends('dashboard')

@php
    $isEdit = $mode === 'edit';

    // Baris sumber, berurutan: isian yang gagal validasi -> dokumen -> satu baris kosong.
    $rows = old('items');
    if ($rows === null) {
        $rows = $isEdit
            ? $order->items->mapWithKeys(fn ($item, $i) => ['row' . $i => [
                'description' => $item->description, 'qty' => $item->qty, 'unit' => $item->unit,
                'currency' => $item->currency, 'unit_price' => $item->unit_price,
                'project_label' => $item->project_label,
                'period_from' => $item->period_from?->toDateString(), 'period_to' => $item->period_to?->toDateString(),
                'discount_type' => $item->discount_type, 'discount_value' => $item->discount_value,
                'tax_keys' => $item->tax_keys ?? [],
            ]])->all()
            : [];
    }
    if ($rows === []) {
        $rows = ['row0' => ['qty' => 1, 'unit' => $units[0] ?? 'PC', 'currency' => 'IDR', 'discount_type' => 'percent', 'discount_value' => 0, 'tax_keys' => []]];
    }

    $value = fn (string $field, $default = '') => old($field, $isEdit ? ($order->{$field} instanceof \Carbon\Carbon ? $order->{$field}->toDateString() : $order->{$field}) : $default);

    $input  = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800';
    $label  = 'block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5';
    $cell   = 'w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800';
@endphp

@section('title', $isEdit ? 'Edit Purchase Order' : 'Create Purchase Order')
@section('page-title', $isEdit ? 'Edit Purchase Order' : 'Create Purchase Order')
@section('page-subtitle', $isEdit ? 'Document ' . $order->po_no : 'Create a purchase order manually or from an approved purchase request')

@section('content')
@include('partials.page-toolbar', ['back' => $isEdit ? route('general.purchase-order.show', $order) : route('general.purchase-order.index'), 'backLabel' => 'Back'])
@include('hr-general.reimbursement.components.date-picker')

<form method="POST" action="{{ $action }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6" id="poForm">
    @csrf

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="text-center">
        <p class="text-[11px] font-bold text-gray-500 tracking-wide uppercase">Purchase Order</p>
        <p class="text-sm font-bold text-gray-800">{{ $settings->company_name }}</p>
    </div>

    {{-- Konfigurasi aktif, ditampilkan apa adanya --}}
    <div class="flex flex-wrap items-center justify-between gap-3 bg-sky-50 border border-sky-200 rounded-lg px-4 py-3">
        <div>
            <p class="text-sm font-semibold text-sky-900">PO Configuration</p>
            <p class="text-xs text-sky-800">{{ $settings->requires_pr ? 'Every PO must be based on an approved purchase request.' : 'Manual PO is allowed for this company.' }}</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            @foreach($taxes as $tax)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tax['kind'] === 'add' ? 'bg-blue-600 text-white' : 'bg-amber-400 text-amber-950' }}">
                    {{ $tax['label'] }} {{ rtrim(rtrim(number_format($tax['rate'], 2), '0'), '.') }}%
                </span>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
            <label class="{{ $label }}">Document No.</label>
            <input type="text" disabled value="{{ $isEdit ? $order->po_no : 'Auto-generated on submit' }}" class="{{ $input }} bg-gray-50 text-gray-500">
        </div>

        <div>
            <label class="{{ $label }}">PR No. @if($settings->requires_pr)<span class="text-red-600">*</span>@endif</label>
            <select name="purchase_request_id" id="poRequest" data-searchable="true" data-search-placeholder="Search PR number…" @required($settings->requires_pr) class="{{ $input }}">
                <option value="">{{ $settings->requires_pr ? '— choose a purchase request —' : '— Optional: select PR document —' }}</option>
                @foreach($requests as $pr)
                    <option value="{{ $pr->id }}" @selected((string) $value('purchase_request_id') === (string) $pr->id)
                            data-items="{{ json_encode($pr->items->map(fn ($it) => ['description' => $it->description, 'qty' => (float) $it->qty, 'unit' => $it->unit])->values()) }}">
                        {{ $pr->request_no }} — {{ \Illuminate\Support\Str::limit($pr->title, 50) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $label }}">Purchase Date <span class="text-red-600">*</span></label>
            <input type="text" name="po_date" required data-date autocomplete="off" value="{{ $value('po_date', now()->toDateString()) }}" class="{{ $input }}">
        </div>

        <div>
            <label class="{{ $label }}">Supplier <span class="text-red-600">*</span></label>
            <input type="text" name="supplier_name" required maxlength="150" list="supplierList" value="{{ $value('supplier_name') }}" placeholder="Supplier name" class="{{ $input }}">
            <datalist id="supplierList">@foreach($suppliers as $s)<option value="{{ $s }}">@endforeach</datalist>
        </div>

        <div>
            <label class="{{ $label }}">Transaction No.</label>
            <input type="text" name="transaction_no" maxlength="100" value="{{ $value('transaction_no') }}" class="{{ $input }}">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="{{ $label }}">Invoice No.</label>
                <input type="text" name="invoice_no" maxlength="100" value="{{ $value('invoice_no') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Invoice Date</label>
                <input type="text" name="invoice_date" data-date autocomplete="off" value="{{ $value('invoice_date') }}" class="{{ $input }}">
            </div>
        </div>
    </div>

    {{-- Items --}}
    <div>
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Items</h3>
        <div id="poRows" class="space-y-3">
            @foreach($rows as $key => $row)
                @include('hr-general.purchase-order.components.item-row', ['key' => $key, 'row' => $row])
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <button type="button" id="poAddRow" class="inline-flex items-center gap-2 px-4 py-2 bg-white text-red-800 text-sm font-semibold rounded-lg border border-red-200 hover:bg-red-50">
                <span class="text-base leading-none">+</span> Add Item Row
            </button>

            <dl class="text-sm grid grid-cols-[auto_auto] gap-x-6 gap-y-0.5 text-right">
                <dt class="text-gray-500">Subtotal</dt><dd class="font-medium" id="poSubtotal">Rp 0</dd>
                <dt class="text-gray-500">Discount</dt><dd class="font-medium" id="poDiscount">Rp 0</dd>
                <dt class="text-gray-500">VAT</dt><dd class="font-medium" id="poVat">Rp 0</dd>
                <dt class="text-gray-500">Withholding (PPh)</dt><dd class="font-medium" id="poWithholding">Rp 0</dd>
                <dt class="font-bold text-gray-800">Grand Total</dt><dd class="font-bold text-red-800" id="poGrand">Rp 0</dd>
            </dl>
        </div>
    </div>

    {{-- Participants --}}
    <div>
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Document Participants</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
            @foreach([
                ['requester_id', 'Requester', true, $value('requester_id', session('user.id')), 'Who the purchase is for.'],
                ['accounting_id', 'Accounting', false, $value('accounting_id', $defaultAccountingId ?: ''), 'Signs the accounting column.'],
                ['cashier_id', 'Cashier', false, $value('cashier_id', $defaultCashierId ?: ''), 'Signs the cashier column.'],
                ['approver_id', 'Approver', true, $value('approver_id'), 'Decides this purchase order.'],
            ] as [$field, $title, $required, $selected, $hint])
            <div>
                <label class="{{ $label }}">{{ $title }} @if($required)<span class="text-red-600">*</span>@endif</label>
                <select name="{{ $field }}" @required($required) data-searchable="true" data-search-placeholder="Search name…" class="{{ $input }}">
                    <option value="">— {{ $required ? 'choose a person' : 'none' }} —</option>
                    @foreach($people as $person)
                        <option value="{{ $person['id'] }}" @selected((string) $selected === (string) $person['id'])>{{ $person['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">{{ $hint }}</p>
            </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
            <label class="{{ $label }}">Delivered Date</label>
            <input type="text" name="delivered_date" data-date autocomplete="off" value="{{ $value('delivered_date') }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-2">
            <label class="{{ $label }}">Notes</label>
            <input type="text" name="notes" maxlength="255" value="{{ $value('notes') }}" placeholder="Additional notes" class="{{ $input }}">
        </div>
    </div>

    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90">
            <i class="fas fa-floppy-disk"></i> {{ $isEdit ? 'Save Changes' : 'Submit Purchase Order' }}
        </button>
        <a href="{{ $isEdit ? route('general.purchase-order.show', $order) : route('general.purchase-order.index') }}"
           class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50">Cancel</a>
    </div>
</form>

<template id="poRowTemplate">
    @include('hr-general.purchase-order.components.item-row', [
        'key' => '__KEY__',
        'row' => ['qty' => 1, 'unit' => $units[0] ?? 'PC', 'currency' => 'IDR', 'discount_type' => 'percent', 'discount_value' => 0, 'tax_keys' => []],
    ])
</template>
@endsection

@push('scripts')
<script>
(function () {
    const TAX = @json(collect($taxes)->map(fn ($t) => ['rate' => $t['rate'], 'kind' => $t['kind']])->all());
    const rows = document.getElementById('poRows');
    const template = document.getElementById('poRowTemplate');
    const money = n => 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function recalc() {
        let sub = 0, disc = 0, vat = 0, wh = 0;

        rows.querySelectorAll('[data-po-row]').forEach(function (row) {
            const qty = parseFloat(row.querySelector('[data-f="qty"]').value) || 0;
            const price = parseFloat(row.querySelector('[data-f="unit_price"]').value) || 0;
            const type = row.querySelector('[data-f="discount_type"]').value;
            const dval = parseFloat(row.querySelector('[data-f="discount_value"]').value) || 0;

            const s = Math.round(qty * price * 100) / 100;
            const d = type === 'percent' ? s * Math.min(dval, 100) / 100 : Math.min(dval, s);
            const base = s - d;

            row.querySelectorAll('[data-tax]:checked').forEach(function (box) {
                const t = TAX[box.dataset.tax];
                if (!t) return;
                const amount = base * t.rate / 100;
                if (t.kind === 'add') vat += amount; else wh += amount;
            });

            sub += s; disc += d;
        });

        document.getElementById('poSubtotal').textContent = money(sub);
        document.getElementById('poDiscount').textContent = money(disc);
        document.getElementById('poVat').textContent = money(vat);
        document.getElementById('poWithholding').textContent = money(wh);
        document.getElementById('poGrand').textContent = money(sub - disc + vat - wh);
    }

    function addRow(values) {
        const key = 'r' + Math.random().toString(36).slice(2, 10);
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replace(/__KEY__/g, key).trim();
        const row = holder.firstElementChild;
        rows.appendChild(row);

        if (values) {
            Object.keys(values).forEach(function (f) {
                const el = row.querySelector('[data-f="' + f + '"]');
                if (el) el.value = values[f];
            });
        }
        recalc();
    }

    rows.addEventListener('input', recalc);
    rows.addEventListener('change', recalc);
    rows.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-po-remove]');
        if (!btn || rows.querySelectorAll('[data-po-row]').length === 1) return;
        btn.closest('[data-po-row]').remove();
        recalc();
    });
    document.getElementById('poAddRow').addEventListener('click', () => addRow());

    // Choosing a PR fills the items from it, but only while the rows are still empty.
    const pr = document.getElementById('poRequest');
    pr.addEventListener('change', function () {
        const option = pr.selectedOptions[0];
        const items = option && option.dataset.items ? JSON.parse(option.dataset.items) : [];
        const pristine = Array.from(rows.querySelectorAll('[data-f="description"]')).every(i => i.value.trim() === '');

        if (!items.length || !pristine) return;

        rows.innerHTML = '';
        items.forEach(it => addRow({ description: it.description, qty: it.qty, unit: it.unit }));
    });

    recalc();
})();
</script>
@endpush
