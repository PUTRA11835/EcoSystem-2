@extends('dashboard')

@section('title', 'Cash Advance Report Details')
@section('page-title', 'Cash Advance Report Details')
@section('page-subtitle', 'Settlement of a cash advance')

@section('content')
@php
    use App\Models\CashAdvance\CashAdvanceReport;
    use App\Models\CashAdvance\CashAdvanceReportApproval;

    $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);

    /*
     | 🔴 Variabel opsional — halaman ini dipakai DUA sisi: karyawan (R2) dan HR
     | (R3). Pola yang sama sudah teruji pada `show.blade.php` milik CA di A5:
     | dua berkas detail yang mirip akan menyimpang cepat atau lambat, dan yang
     | menyimpang di sini adalah tampilan dokumen keuangan.
     */
    $canApprove   = $canApprove   ?? false;
    $approveRoute = $approveRoute ?? null;
    $rejectRoute  = $rejectRoute  ?? null;
    $cancelRoute  = $cancelRoute  ?? null;
    $editRoute    = $editRoute    ?? null;
    $deleteRoute  = $deleteRoute  ?? null;
    $exportRoute  = $exportRoute  ?? null;
@endphp

<div class="w-full space-y-5">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3">
            <p class="text-sm text-green-900">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <p class="text-sm text-red-900">{{ session('error') }}</p>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:flex-wrap sm:justify-end gap-2">
        @if($editRoute)
            <a href="{{ $editRoute }}"
               class="text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                Edit
            </a>
        @endif
        @if($exportRoute)
            <a href="{{ $exportRoute }}"
               class="text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                Export Excel
            </a>
        @endif
        <a href="{{ $printRoute }}" target="_blank"
           class="text-center px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
            Print CAR Form
        </a>
        <a href="{{ $backRoute }}"
           class="text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            Back to List
        </a>
    </div>

    {{-- ── Hapus beralasan (D109) ──────────────────────────────────────────
         🔴 Menghapus laporan MEMBUKA KEMBALI buku CA induknya — dikatakan di
         layar, bukan hanya dikerjakan diam-diam di service. Orang yang menekan
         tombol ini berhak tahu bahwa akibatnya menyentuh dokumen lain. --}}
    @if($deleteRoute)
        <div class="bg-white rounded-xl shadow-sm">
            <button type="button" id="carDeleteToggle"
                    class="w-full text-left px-5 py-3 text-sm font-medium text-red-700 hover:bg-red-50 rounded-xl transition-colors">
                Delete this report…
            </button>

            <form method="POST" action="{{ $deleteRoute }}" id="carDeleteForm" hidden
                  class="px-5 pb-5 pt-1 border-t border-gray-100">
                @csrf

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reason for deletion <span class="text-red-600">*</span>
                </label>
                <input type="text" name="delete_reason" required minlength="5" maxlength="255"
                       placeholder="Example: wrong receipts attached, will be re-filed"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                <p class="text-xs text-gray-400 mt-1">
                    At least 5 characters. The report stays on record, and cash advance
                    {{ $report->cashAdvance?->request_no ?? '—' }} becomes open for reporting again.
                </p>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 mt-4">
                    <button type="button" id="carDeleteCancel"
                            class="w-full sm:w-auto px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Keep it
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                        Delete report
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- ══════════════════ RINGKASAN UANG ══════════════════
         Tiga angka berdampingan, karena hanya bersama-sama mereka punya arti:
         berapa yang diterima, berapa yang dipakai, dan siapa yang harus
         membayar siapa. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Advance received</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $amounts->format((float) $report->advance_amount, false) }}
            </p>
            {{-- 🔴 DIBEKUKAN saat laporan dibuat (D139): mengubah nominal CA
                 sesudahnya tidak boleh menggeser selisih pada kertas yang sudah
                 ditandatangani. --}}
            <p class="text-xs text-gray-400 mt-0.5">frozen when this report was created</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Reported spending</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $amounts->format((float) $report->reported_amount, false) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $report->item_count }} expense line(s)</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">{{ $report->settlementLabel() }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $amounts->format(abs((float) $report->difference_amount), false) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $report->settlementDirection() }}</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Cash advance</p>
            <a href="{{ $advanceRoute }}" class="text-lg font-bold primary-text hover:underline mt-1 block">
                {{ $report->cashAdvance?->request_no ?? '—' }}
            </a>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $report->cashAdvance?->settlementLabel() ?? '—' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- ══════════════════ KIRI ══════════════════ --}}
        <div class="space-y-5">

            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Report Information</h2>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Report No.</dt>
                        <dd class="font-medium primary-text text-right">{{ $report->report_no }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Report Date</dt>
                        <dd class="text-right">{{ $report->report_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Requester</dt>
                        <dd class="font-medium text-right">
                            {{ $report->employee?->basicData?->nick_name ?? $report->employee?->eci ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Employee No.</dt>
                        <dd class="text-right text-gray-600">{{ $report->employee?->eci ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Status</dt>
                        <dd class="text-right">
                            @php
                                $badge = match ($report->status) {
                                    CashAdvanceReport::STATUS_APPROVED  => 'bg-green-50 text-green-700',
                                    CashAdvanceReport::STATUS_REJECTED  => 'bg-red-50 text-red-700',
                                    CashAdvanceReport::STATUS_CANCELLED => 'bg-gray-100 text-gray-600',
                                    default                            => 'bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ $report->statusLabel() }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Description</p>
                    <p class="text-sm text-gray-800">{{ $report->description }}</p>
                    @if($report->notes)
                        <p class="text-xs text-gray-500 mt-2">
                            <span class="font-medium">Notes:</span> {{ $report->notes }}
                        </p>
                    @endif
                </div>
            </div>

            @if($canApprove || $cancelRoute)
            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 text-center mb-4">Document Actions</h2>

                <div class="space-y-2">
                    @if($canApprove && $approveRoute)
                    {{-- 🔴 Menyetujui langkah TERAKHIR ikut MENUTUP BUKU CA
                         induknya, dalam transaksi yang sama. Dikatakan di layar
                         supaya penyetuju tahu akibat tombolnya. --}}
                    <form method="POST" action="{{ $approveRoute }}" class="js-car-approve"
                          data-no="{{ $report->report_no }}">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                            Approve
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 text-center">
                        Approving the final step settles cash advance
                        {{ $report->cashAdvance?->request_no ?? '' }}.
                    </p>
                    @endif

                    @if($canApprove && $rejectRoute)
                    <details class="border border-red-200 rounded-lg">
                        <summary class="px-4 py-2.5 text-sm font-medium text-red-700 cursor-pointer">
                            Reject…
                        </summary>
                        <form method="POST" action="{{ $rejectRoute }}" class="p-4 pt-0 space-y-2">
                            @csrf
                            <textarea name="notes" rows="3" required minlength="5" maxlength="500"
                                      placeholder="Why is this rejected? At least 5 characters."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border"></textarea>
                            <button type="submit"
                                    class="w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                                Confirm rejection
                            </button>
                        </form>
                    </details>
                    @endif

                    @if($cancelRoute)
                    <form method="POST" action="{{ $cancelRoute }}" class="js-car-cancel"
                          data-no="{{ $report->report_no }}">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2.5 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                            Cancel Report
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endif

            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-4">Approval Timeline</h2>

                <div class="space-y-2">
                    @forelse($report->approvals as $approval)
                        @php
                            $stateBadge = match ($approval->status) {
                                CashAdvanceReportApproval::STATUS_APPROVED => 'bg-green-50 text-green-700',
                                CashAdvanceReportApproval::STATUS_REJECTED => 'bg-red-50 text-red-700',
                                CashAdvanceReportApproval::STATUS_SKIPPED  => 'bg-gray-100 text-gray-500',
                                default                                   => 'bg-amber-50 text-amber-700',
                            };
                        @endphp
                        <div class="flex items-start justify-between gap-3 border border-gray-200 rounded-lg px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $approval->timelineLabel() }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $approval->acted_by
                                        ? ($approval->actor?->basicData?->nick_name ?? $approval->actor?->eci ?? '—')
                                        : $approval->approverLabel() }}
                                </p>
                                @if($approval->notes)
                                    <p class="text-xs text-gray-500 mt-1 italic">“{{ $approval->notes }}”</p>
                                @endif
                            </div>
                            <div class="text-right whitespace-nowrap">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $stateBadge }}">
                                    {{ ucfirst($approval->status === CashAdvanceReportApproval::STATUS_WAITING
                                        ? 'pending' : $approval->status) }}
                                </span>
                                @if($approval->acted_at)
                                    <span class="block text-xs text-gray-400 mt-0.5">
                                        {{ $approval->acted_at->format('d.m.Y') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No approval step recorded on this report.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══════════════════ KANAN ══════════════════ --}}
        <div class="space-y-5">

            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-4">Expense Lines</h2>

                <div class="border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-3 py-2 w-10">No</th>
                                <th class="px-3 py-2 w-28">Date</th>
                                <th class="px-3 py-2">Description</th>
                                <th class="px-3 py-2">Charged To</th>
                                <th class="px-3 py-2 w-32 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($report->items as $item)
                            <tr>
                                <td class="px-3 py-2 text-gray-500">{{ $item->line_no }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $item->expense_date->format('d.m.Y') }}</td>
                                <td class="px-3 py-2">
                                    {{ $item->description }}
                                    @if($item->receipt_no)
                                        <span class="block text-xs text-gray-400">Receipt {{ $item->receipt_no }}</span>
                                    @endif
                                    @if($item->receipt_url)
                                        <a href="{{ $item->receipt_url }}" target="_blank" rel="noopener noreferrer"
                                           class="block text-xs primary-text hover:underline break-all">proof</a>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $item->costCenterLabel() }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    {{ $amounts->format((float) $item->amount) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold text-gray-600">
                                    Reported total
                                </td>
                                <td class="px-3 py-2 text-right font-bold whitespace-nowrap">
                                    {{ $amounts->format((float) $report->reported_amount) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-3">Document Signatures</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($signatures as $column)
                        <div class="border border-gray-200 rounded-lg px-3 py-2 text-center">
                            <p class="text-xs text-gray-500">{{ $column['title'] }}</p>
                            <p class="text-sm mt-1 {{ $column['pending'] ? 'text-gray-400 italic' : 'text-gray-900 font-medium' }}">
                                {{ $column['name'] !== '' ? $column['name'] : '—' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // 🔴 showConfirm(), bukan confirm() bawaan peramban. Form-nya sungguhan dan
    // sudah membawa @csrf dari Blade.
    function carGuard(selector, title, message, okText) {
        document.querySelectorAll(selector).forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                if (form.dataset.confirmed === 'yes') return;
                event.preventDefault();

                const ok = await showConfirm(message(form.dataset.no), title, 'danger',
                    { okText: okText, cancelText: 'Cancel' });

                if (!ok) return;
                form.dataset.confirmed = 'yes';
                form.submit();
            });
        });
    }

    carGuard('.js-car-cancel', 'Cancel report',
        no => `Cancel report ${no}? Its cash advance goes back to waiting for a report.`,
        'Yes, cancel it');

    carGuard('.js-car-approve', 'Approve report',
        no => `Approve report ${no}? Approving the final step settles its cash advance and closes the book.`,
        'Approve');

    // ── Hapus beralasan (D109) — bentuk yang sama dengan halaman CA ────────
    const carDeleteToggle = document.getElementById('carDeleteToggle');
    const carDeleteForm   = document.getElementById('carDeleteForm');
    const carDeleteCancel = document.getElementById('carDeleteCancel');

    if (carDeleteToggle && carDeleteForm) {
        carDeleteToggle.addEventListener('click', function () {
            carDeleteForm.hidden = false;
            carDeleteToggle.hidden = true;
            carDeleteForm.querySelector('[name="delete_reason"]').focus();
        });

        carDeleteCancel.addEventListener('click', function () {
            carDeleteForm.hidden = true;
            carDeleteToggle.hidden = false;
            carDeleteForm.reset();
        });

        carDeleteForm.addEventListener('submit', async function (event) {
            if (carDeleteForm.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const reason = carDeleteForm.querySelector('[name="delete_reason"]').value.trim();

            if (reason.length < 5) {
                showToast('Please give at least 5 characters of reason.', 'error');
                return;
            }

            // 🔴 Akibatnya menyentuh DOKUMEN LAIN, dan itu disebutkan.
            const ok = await showConfirm(
                `Delete report {{ $report->report_no }}? Cash advance `
                + `{{ $report->cashAdvance?->request_no ?? 'it belongs to' }} becomes open for `
                + `reporting again. Reason: "${reason}"`,
                'Delete report',
                'danger',
                { okText: 'Yes, delete it', cancelText: 'Keep it' }
            );

            if (!ok) return;
            carDeleteForm.dataset.confirmed = 'yes';
            carDeleteForm.submit();
        });
    }
</script>
@endpush
