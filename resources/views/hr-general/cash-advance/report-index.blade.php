@extends('dashboard')

@section('title', 'My Cash Advance Report')
@section('page-title', 'My Cash Advance Report')
@section('page-subtitle', 'Account for the cash advances you have received')

@section('content')
@php
    use App\Models\CashAdvance\CashAdvanceReport;

    $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);
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

    {{-- ── CA yang menunggu dipertanggungjawabkan ───────────────────────────
         🔴 Ditaruh PALING ATAS, bukan disembunyikan di halaman Cash Advance.
         Laporan selalu lahir dari sebuah CA, jadi pintu masuknya harus ada di
         sini — kalau tidak, karyawan harus tahu lebih dulu bahwa ia perlu
         kembali ke halaman lain untuk memulai. --}}
    @if($reportable->isNotEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 md:p-6">
            <h2 class="text-sm font-bold text-amber-900 mb-1">Waiting to be reported</h2>
            <p class="text-xs text-amber-800 mb-4">
                These cash advances are approved but not settled yet. A report closes the book on them.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach($reportable as $advance)
                    <div class="bg-white border border-amber-200 rounded-lg p-4 flex flex-col gap-2">
                        <div>
                            <p class="text-sm font-medium primary-text">{{ $advance->request_no }}</p>
                            <p class="text-xs text-gray-500">{{ $advance->request_date->format('d M Y') }}</p>
                        </div>
                        <p class="text-sm text-gray-700">{{ $advance->description }}</p>
                        <p class="text-lg font-bold text-gray-900">
                            {{ $advance->currency }} {{ $amounts->format((float) $advance->amount) }}
                        </p>
                        <a href="{{ route('general.my-cash-advance-report.create', ['ca' => $advance->id]) }}"
                           class="mt-auto text-center px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                            Create CAR
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
        <div class="mb-5 pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">My Cash Advance Report</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Track your settlement reports, check status, and print documents.
            </p>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Report No.</th>
                        <th class="px-4 py-3">Cash Advance</th>
                        <th class="px-4 py-3 w-32">Date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 w-32 text-right">Advance</th>
                        <th class="px-4 py-3 w-32 text-right">Reported</th>
                        <th class="px-4 py-3 w-40">Settlement</th>
                        <th class="px-4 py-3 w-44">Status</th>
                        <th class="px-4 py-3 w-36 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($reports as $report)
                    <tr class="hover:bg-gray-50 transition-colors align-top">
                        <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>

                        <td class="px-4 py-3">
                            <a href="{{ route('general.my-cash-advance-report.show', $report) }}"
                               class="font-medium primary-text hover:underline">{{ $report->report_no }}</a>
                        </td>

                        <td class="px-4 py-3">
                            <a href="{{ route('general.my-cash-advance.show', $report->cash_advance_id) }}"
                               class="text-gray-700 hover:underline">
                                {{ $report->cashAdvance?->request_no ?? '—' }}
                            </a>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">{{ $report->report_date->format('d.m.Y') }}</td>

                        <td class="px-4 py-3">{{ $report->description }}</td>

                        <td class="px-4 py-3 text-right whitespace-nowrap text-gray-600">
                            {{ $amounts->format((float) $report->advance_amount, false) }}
                        </td>

                        <td class="px-4 py-3 text-right whitespace-nowrap font-medium">
                            {{ $amounts->format((float) $report->reported_amount, false) }}
                        </td>

                        {{-- Arah uangnya, bukan sekadar angkanya. `difference_amount`
                             positif berarti karyawan MENGEMBALIKAN sisa; negatif
                             berarti perusahaan mengganti. Arahnya mudah tertukar
                             saat dibaca cepat. --}}
                        <td class="px-4 py-3">
                            @php
                                $settleBadge = match ($report->settlement_type) {
                                    CashAdvanceReport::SETTLEMENT_REFUND => 'bg-green-50 text-green-700',
                                    CashAdvanceReport::SETTLEMENT_CLAIM  => 'bg-amber-50 text-amber-700',
                                    default                              => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $settleBadge }}">
                                {{ $report->settlementLabel() }}
                            </span>
                            @if($report->settlement_type !== CashAdvanceReport::SETTLEMENT_EXACT)
                                <span class="block text-xs text-gray-500 mt-0.5">
                                    {{ $amounts->format(abs((float) $report->difference_amount), false) }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            @php
                                $badge = match ($report->status) {
                                    CashAdvanceReport::STATUS_APPROVED  => 'bg-green-50 text-green-700',
                                    CashAdvanceReport::STATUS_REJECTED  => 'bg-red-50 text-red-700',
                                    CashAdvanceReport::STATUS_CANCELLED => 'bg-gray-100 text-gray-600',
                                    default                            => 'bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ CashAdvanceReport::STATUS_LABELS[$report->status] ?? $report->status }}
                            </span>
                            @if($report->isOpen() && $report->currentStepName())
                                <span class="block text-xs text-gray-400 mt-0.5">
                                    Current step: {{ $report->currentStepName() }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('general.my-cash-advance-report.show', $report) }}"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    View
                                </a>
                                <a href="{{ route('general.my-cash-advance-report.print', $report) }}" target="_blank"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    Print
                                </a>
                                @if($settings->allow_requester_cancel && $report->isCancellable())
                                <form method="POST" action="{{ route('general.my-cash-advance-report.cancel', $report) }}"
                                      class="js-car-cancel inline" data-no="{{ $report->report_no }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-2 py-1 border border-red-300 text-red-700 rounded text-xs hover:bg-red-50 transition-colors">
                                        Cancel
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">
                            No cash advance report yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // 🔴 showConfirm(), bukan confirm() bawaan peramban.
    document.querySelectorAll('.js-car-cancel').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm(
                `Cancel report ${form.dataset.no}? Its cash advance goes back to waiting for a report.`,
                'Cancel report',
                'danger',
                { okText: 'Yes, cancel it', cancelText: 'Keep it' }
            );

            if (!ok) return;
            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });
</script>
@endpush
