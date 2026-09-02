{{--
    Tabel baris permintaan — dipakai bersama oleh form pengajuan (ESS), form
    "New PR" milik admin, dan form Edit. Satu berkas supaya ketiganya tidak
    pernah menyimpang satu sama lain.

    Variabel yang diharapkan:
      $costCenters    array{branch: [{id,label}], project: [{id,label}]}
      $settings       PurchaseRequestSetting
      $existingItems  Collection|array|null  — baris yang sudah ada (form Edit)

    🔴 NAMA FIELD MEMAKAI KUNCI ACAK: items[<key>][qty], BUKAN indeks berurutan.
    Dengan indeks berurutan, menghapus baris di tengah membuat nomornya bolong
    dan baris berikutnya tertimpa saat dikirim. Nomor urut yang dilihat pengguna
    dibangun ulang di server oleh PurchaseRequestSummaryService::normaliseItems().

    🔴 CHARGED TO ADALAH DUA KONTROL BERPASANGAN (Keputusan D127): dropdown tipe
    menentukan dropdown mana yang aktif. Saat tipenya diganti, JavaScript
    MENGOSONGKAN dropdown yang ditinggalkan — bukan sekadar menyembunyikannya.
    Kalau hanya disembunyikan, form membawa cabang DAN proyek sekaligus, dan
    PurchaseRequestService::checkRawItems() akan menolaknya.
--}}
@php
    use App\Models\PurchaseRequest\PurchaseRequestItem;

    $types      = $settings->costCenterTypeOptions();
    $units      = $settings->unitOptions();
    $unitDefault = $settings->defaultUnit();

    // Sumber baris, berurutan: isian yang gagal validasi -> data yang sudah ada
    // -> satu baris kosong. Tanpa cabang pertama, isian pengguna hilang setiap
    // kali ada satu field yang salah.
    $rows = old('items');

    if ($rows === null) {
        $rows = collect($existingItems ?? [])->mapWithKeys(fn ($item, $i) => [
            'row' . $i => [
                'description'         => $item->description ?? '',
                'qty'                 => $item->qty !== null ? 0 + $item->qty : '',
                'unit'                => $item->unit ?? $unitDefault,
                'period_from'         => optional($item->period_from)->toDateString() ?? '',
                'period_to'           => optional($item->period_to)->toDateString() ?? '',
                'use_date'            => optional($item->use_date)->toDateString() ?? '',
                'cost_center_type'    => $item->cost_center_type ?? ($types[0] ?? PurchaseRequestItem::COST_CENTER_BRANCH),
                'branch_id'           => $item->branch_id ?? '',
                'delivery_project_id' => $item->delivery_project_id ?? '',
            ],
        ])->all();
    }

    if ($rows === []) {
        $rows = ['row0' => []];
    }
@endphp

<div>
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide">Request Items</h3>
        @if($settings->hasItemLimit())
            <span class="text-xs text-gray-400">Maximum {{ $settings->max_items_per_request }} items</span>
        @endif
    </div>

    <div class="border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    <th class="px-3 py-3 w-10">No</th>
                    <th class="px-3 py-3">Description</th>
                    <th class="px-3 py-3 w-24 text-right">Qty</th>
                    <th class="px-3 py-3 w-28">Unit</th>
                    <th class="px-3 py-3 w-64">Period</th>
                    <th class="px-3 py-3 w-36">Use Date</th>
                    <th class="px-3 py-3 w-64">Charged To</th>
                    <th class="px-3 py-3 w-12"></th>
                </tr>
            </thead>
            <tbody id="itemRows" class="divide-y divide-gray-100">
                @foreach($rows as $key => $row)
                @php
                    $rowType = $row['cost_center_type'] ?? ($types[0] ?? PurchaseRequestItem::COST_CENTER_BRANCH);
                    if (!in_array($rowType, $types, true)) {
                        $rowType = $types[0] ?? PurchaseRequestItem::COST_CENTER_BRANCH;
                    }
                @endphp
                <tr class="js-item-row align-top" data-key="{{ $key }}">
                    <td class="px-3 py-3 text-gray-400 js-line-no">{{ $loop->iteration }}</td>

                    <td class="px-3 py-3">
                        <input type="text" name="items[{{ $key }}][description]" maxlength="200" required
                               value="{{ $row['description'] ?? '' }}" placeholder="e.g. Laptop 14 inch"
                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                        @error("items.{$key}.description")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <input type="number" name="items[{{ $key }}][qty]" min="0.01" step="0.01" required
                               value="{{ $row['qty'] ?? '' }}" placeholder="0"
                               class="js-qty w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right focus:outline-none focus:ring-1 focus:ring-red-800">
                        @error("items.{$key}.qty")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <select name="items[{{ $key }}][unit]" required
                                class="js-unit w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                            @foreach($units as $unit)
                                <option value="{{ $unit }}" @selected(($row['unit'] ?? $unitDefault) === $unit)>{{ $unit }}</option>
                            @endforeach
                        </select>
                        @error("items.{$key}.unit")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <div class="flex items-center gap-1">
                            <input type="date" name="items[{{ $key }}][period_from]"
                                   @required($settings->require_period)
                                   value="{{ $row['period_from'] ?? '' }}"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                            <span class="text-xs text-gray-400 shrink-0">s.d</span>
                            <input type="date" name="items[{{ $key }}][period_to]"
                                   value="{{ $row['period_to'] ?? '' }}"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Leave the second date empty for a single day.</p>
                        @error("items.{$key}.period_from")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        @error("items.{$key}.period_to")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <input type="date" name="items[{{ $key }}][use_date]"
                               @required($settings->require_use_date)
                               value="{{ $row['use_date'] ?? '' }}"
                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                        @error("items.{$key}.use_date")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <select name="items[{{ $key }}][cost_center_type]"
                                @required($settings->require_cost_center_per_item)
                                class="js-cc-type w-full px-2 py-1.5 border border-gray-300 rounded text-xs mb-1 focus:outline-none focus:ring-1 focus:ring-red-800">
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected($rowType === $type)>
                                    {{ PurchaseRequestItem::COST_CENTER_LABELS[$type] }}
                                </option>
                            @endforeach
                        </select>

                        @if(in_array(PurchaseRequestItem::COST_CENTER_BRANCH, $types, true))
                        <select name="items[{{ $key }}][branch_id]"
                                class="js-cc-branch w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 {{ $rowType === PurchaseRequestItem::COST_CENTER_BRANCH ? '' : 'hidden' }}">
                            <option value="">— choose a branch —</option>
                            @foreach($costCenters['branch'] as $branch)
                                <option value="{{ $branch['id'] }}" @selected((string) ($row['branch_id'] ?? '') === (string) $branch['id'])>
                                    {{ $branch['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @endif

                        @if(in_array(PurchaseRequestItem::COST_CENTER_PROJECT, $types, true))
                        <select name="items[{{ $key }}][delivery_project_id]"
                                class="js-cc-project w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 {{ $rowType === PurchaseRequestItem::COST_CENTER_PROJECT ? '' : 'hidden' }}">
                            <option value="">— choose a project —</option>
                            @foreach($costCenters['project'] as $project)
                                <option value="{{ $project['id'] }}" @selected((string) ($row['delivery_project_id'] ?? '') === (string) $project['id'])>
                                    {{ $project['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @endif

                        @error("items.{$key}.branch_id")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        @error("items.{$key}.delivery_project_id")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        @error("items.{$key}.cost_center_type")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3 text-center">
                        <button type="button" title="Remove this item"
                                class="js-remove-row px-2 py-1 text-red-600 hover:bg-red-50 rounded transition-all">&times;</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="2" class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        Total
                    </td>
                    <td colspan="6" class="px-3 py-3 text-sm">
                        <span class="font-bold text-gray-900" id="itemsCount">0 items</span>
                        <span class="text-gray-400 mx-2">·</span>
                        <span class="font-bold text-red-800" id="itemsQty">—</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-3">
        <button type="button" id="addItemRow"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white text-red-800 text-sm font-semibold rounded-lg border border-red-200 hover:bg-red-50 transition-all">
            <span class="text-base leading-none">+</span> Add Item Row
        </button>
        @error('items')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
    </div>
</div>

{{-- Cetakan baris baru. Kunci `__KEY__` diganti nilai acak saat digandakan. --}}
<template id="itemRowTemplate">
    <tr class="js-item-row align-top" data-key="__KEY__">
        <td class="px-3 py-3 text-gray-400 js-line-no"></td>
        <td class="px-3 py-3">
            <input type="text" name="items[__KEY__][description]" maxlength="200" required placeholder="e.g. Laptop 14 inch"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
        </td>
        <td class="px-3 py-3">
            <input type="number" name="items[__KEY__][qty]" min="0.01" step="0.01" required placeholder="0"
                   class="js-qty w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right focus:outline-none focus:ring-1 focus:ring-red-800">
        </td>
        <td class="px-3 py-3">
            <select name="items[__KEY__][unit]" required
                    class="js-unit w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                @foreach($units as $unit)
                    <option value="{{ $unit }}" @selected($unit === $unitDefault)>{{ $unit }}</option>
                @endforeach
            </select>
        </td>
        <td class="px-3 py-3">
            <div class="flex items-center gap-1">
                <input type="date" name="items[__KEY__][period_from]" @required($settings->require_period)
                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                <span class="text-xs text-gray-400 shrink-0">s.d</span>
                <input type="date" name="items[__KEY__][period_to]"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
            </div>
        </td>
        <td class="px-3 py-3">
            <input type="date" name="items[__KEY__][use_date]" @required($settings->require_use_date)
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
        </td>
        <td class="px-3 py-3">
            <select name="items[__KEY__][cost_center_type]" @required($settings->require_cost_center_per_item)
                    class="js-cc-type w-full px-2 py-1.5 border border-gray-300 rounded text-xs mb-1 focus:outline-none focus:ring-1 focus:ring-red-800">
                @foreach($types as $type)
                    <option value="{{ $type }}">{{ PurchaseRequestItem::COST_CENTER_LABELS[$type] }}</option>
                @endforeach
            </select>

            @if(in_array(PurchaseRequestItem::COST_CENTER_BRANCH, $types, true))
            <select name="items[__KEY__][branch_id]"
                    class="js-cc-branch w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 {{ ($types[0] ?? '') === PurchaseRequestItem::COST_CENTER_BRANCH ? '' : 'hidden' }}">
                <option value="">— choose a branch —</option>
                @foreach($costCenters['branch'] as $branch)
                    <option value="{{ $branch['id'] }}">{{ $branch['label'] }}</option>
                @endforeach
            </select>
            @endif

            @if(in_array(PurchaseRequestItem::COST_CENTER_PROJECT, $types, true))
            <select name="items[__KEY__][delivery_project_id]"
                    class="js-cc-project w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 {{ ($types[0] ?? '') === PurchaseRequestItem::COST_CENTER_PROJECT ? '' : 'hidden' }}">
                <option value="">— choose a project —</option>
                @foreach($costCenters['project'] as $project)
                    <option value="{{ $project['id'] }}">{{ $project['label'] }}</option>
                @endforeach
            </select>
            @endif
        </td>
        <td class="px-3 py-3 text-center">
            <button type="button" title="Remove this item"
                    class="js-remove-row px-2 py-1 text-red-600 hover:bg-red-50 rounded transition-all">&times;</button>
        </td>
    </tr>
</template>
