{{--
    Tabel baris biaya — dipakai bersama oleh form pengajuan (ESS), form "New RB"
    milik admin, dan form Edit milik HR. Satu berkas supaya ketiganya tidak
    pernah menyimpang satu sama lain.

    Variabel yang diharapkan:
      $branches       Collection [{id, label}]
      $settings       ReimbursementSetting
      $existingItems  Collection|array|null  — baris yang sudah ada (form Edit)

    🔴 NAMA FIELD MEMAKAI KUNCI ACAK: items[<key>][amount], BUKAN indeks
    berurutan. Dengan indeks berurutan, menghapus baris di tengah membuat
    nomornya bolong dan baris berikutnya tertimpa saat dikirim. Nomor urut yang
    dilihat pengguna dibangun ulang di server oleh
    ReimbursementService::normaliseItems().

    🔴 NOMINAL MEMAKAI type="number": nilainya dikirim mentah, bukan sebagai
    "Rp 300.000". Yang berformat hanya total di bawah tabel, yang tidak pernah
    dikirim ke server.
--}}
@php
    // Sumber baris, berurutan: isian yang gagal validasi -> data yang sudah ada
    // -> satu baris kosong. Tanpa cabang pertama, isian pengguna hilang setiap
    // kali ada satu field yang salah.
    $rows = old('items');

    if ($rows === null) {
        $rows = collect($existingItems ?? [])->mapWithKeys(fn ($item, $i) => [
            'row' . $i => [
                'description'       => $item->description ?? '',
                'branch_id'         => $item->branch_id ?? '',
                'receipt_no'        => $item->receipt_no ?? '',
                'receipt_date_from' => optional($item->receipt_date_from)->toDateString() ?? '',
                'receipt_date_to'   => optional($item->receipt_date_to)->toDateString() ?? '',
                'amount'            => $item->amount ?? '',
            ],
        ])->all();
    }

    if ($rows === []) {
        $rows = ['row0' => []];
    }
@endphp

<div>
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide">Reimbursement Items</h3>
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
                    <th class="px-3 py-3 w-56">Branch</th>
                    <th class="px-3 py-3 w-32">Receipt No.</th>
                    <th class="px-3 py-3 w-64">Receipt Date</th>
                    <th class="px-3 py-3 w-16">Curr</th>
                    <th class="px-3 py-3 w-40 text-right">Amount</th>
                    <th class="px-3 py-3 w-12"></th>
                </tr>
            </thead>
            <tbody id="itemRows" class="divide-y divide-gray-100">
                @foreach($rows as $key => $row)
                <tr class="js-item-row align-top" data-key="{{ $key }}">
                    <td class="px-3 py-3 text-gray-400 js-line-no">{{ $loop->iteration }}</td>

                    <td class="px-3 py-3">
                        <input type="text" name="items[{{ $key }}][description]" maxlength="200" required
                               value="{{ $row['description'] ?? '' }}" placeholder="e.g. Claude AI Subscription"
                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                        @error("items.{$key}.description")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <select name="items[{{ $key }}][branch_id]" required
                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                            <option value="">— choose a branch —</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch['id'] }}" @selected((string) ($row['branch_id'] ?? '') === (string) $branch['id'])>
                                    {{ $branch['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error("items.{$key}.branch_id")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <input type="text" name="items[{{ $key }}][receipt_no]" maxlength="50"
                               @required($settings->require_receipt_no)
                               value="{{ $row['receipt_no'] ?? '' }}" placeholder="No."
                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                        @error("items.{$key}.receipt_no")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3">
                        <div class="flex items-center gap-1">
                            <input type="date" name="items[{{ $key }}][receipt_date_from]" required
                                   value="{{ $row['receipt_date_from'] ?? '' }}"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                            <span class="text-xs text-gray-400 shrink-0">s.d</span>
                            <input type="date" name="items[{{ $key }}][receipt_date_to]"
                                   value="{{ $row['receipt_date_to'] ?? '' }}"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Leave the second date empty for a single-day receipt.</p>
                        @error("items.{$key}.receipt_date_from")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        @error("items.{$key}.receipt_date_to")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </td>

                    <td class="px-3 py-3 text-xs text-gray-500">IDR</td>

                    <td class="px-3 py-3">
                        <input type="number" name="items[{{ $key }}][amount]" min="0" step="1" required
                               value="{{ $row['amount'] ?? '' }}" placeholder="0"
                               class="js-amount w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right focus:outline-none focus:ring-1 focus:ring-red-800">
                        @error("items.{$key}.amount")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
                    <td colspan="6" class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        Total Reimbursement
                    </td>
                    <td class="px-3 py-3 text-right font-bold text-gray-900" id="itemsTotal">Rp 0</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-3">
        <button type="button" id="addItemRow"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white text-red-800 text-sm font-semibold rounded-lg border border-red-200 hover:bg-red-50 transition-all">
            <span class="text-base leading-none">+</span> Add More Reimbursement
        </button>
        @error('items')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
    </div>
</div>

{{-- Cetakan baris baru. Kunci `__KEY__` diganti nilai acak saat digandakan. --}}
<template id="itemRowTemplate">
    <tr class="js-item-row align-top" data-key="__KEY__">
        <td class="px-3 py-3 text-gray-400 js-line-no"></td>
        <td class="px-3 py-3">
            <input type="text" name="items[__KEY__][description]" maxlength="200" required placeholder="e.g. Claude AI Subscription"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
        </td>
        <td class="px-3 py-3">
            <select name="items[__KEY__][branch_id]" required
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
                <option value="">— choose a branch —</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch['id'] }}">{{ $branch['label'] }}</option>
                @endforeach
            </select>
        </td>
        <td class="px-3 py-3">
            <input type="text" name="items[__KEY__][receipt_no]" maxlength="50" placeholder="No."
                   @required($settings->require_receipt_no)
                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800">
        </td>
        <td class="px-3 py-3">
            <div class="flex items-center gap-1">
                <input type="date" name="items[__KEY__][receipt_date_from]" required
                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                <span class="text-xs text-gray-400 shrink-0">s.d</span>
                <input type="date" name="items[__KEY__][receipt_date_to]"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
            </div>
        </td>
        <td class="px-3 py-3 text-xs text-gray-500">IDR</td>
        <td class="px-3 py-3">
            <input type="number" name="items[__KEY__][amount]" min="0" step="1" required placeholder="0"
                   class="js-amount w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right focus:outline-none focus:ring-1 focus:ring-red-800">
        </td>
        <td class="px-3 py-3 text-center">
            <button type="button" title="Remove this item"
                    class="js-remove-row px-2 py-1 text-red-600 hover:bg-red-50 rounded transition-all">&times;</button>
        </td>
    </tr>
</template>
