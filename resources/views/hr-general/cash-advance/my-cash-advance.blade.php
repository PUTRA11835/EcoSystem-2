@extends('dashboard')

@section('title', 'My Cash Advance')
@section('page-title', 'My Cash Advance')
@section('page-subtitle', 'Manage your personal cash advance requests, check status, and print documents')

@section('content')
@php
    use App\Models\CashAdvance\CashAdvance;

    $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);
@endphp

<div class="space-y-5">

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

    {{-- ── Kartu ringkasan bulan berjalan ──────────────────────────────────
         Kartu "Outstanding" adalah alasan kedua sumbu status dipisah (D140):
         uang yang sudah keluar tetapi bukunya belum ditutup. Itu angka yang
         paling perlu dilihat pemohon maupun bagian keuangan. --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Requests · {{ $month }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Waiting review</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $summary['submitted'] + $summary['in_review'] }}
            </p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Approved</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $summary['approved'] }}</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Outstanding</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $amounts->format($summary['outstanding'], false) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">approved, not yet reported</p>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex items-start justify-between mb-5 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">My Cash Advance</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Manage your personal cash advance requests, check status, and print documents.
                </p>
            </div>
            <a href="{{ route('general.my-cash-advance.create') }}"
               class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors whitespace-nowrap">
                + Submit CA
            </a>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Document No.</th>
                        <th class="px-4 py-3 w-32">CAR</th>
                        <th class="px-4 py-3 w-36">Date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Approver</th>
                        <th class="px-4 py-3 w-32 text-right">Amount</th>
                        <th class="px-4 py-3 w-44">Status</th>
                        <th class="px-4 py-3 w-36 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $request)
                    <tr class="hover:bg-gray-50 transition-colors align-top">
                        <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>

                        <td class="px-4 py-3">
                            <a href="{{ route('general.my-cash-advance.show', $request) }}"
                               class="font-medium primary-text hover:underline">
                                {{ $request->request_no }}
                            </a>
                        </td>

                        {{-- Kolom CAR, persis acuan — tetapi tombolnya baru muncul
                             setelah CA DISETUJUI (jawaban C7).

                             🔴 Tombolnya digantungkan pada $reportableIds, yang
                             berasal dari CashAdvanceReportService::checkEligibility()
                             (Keputusan D154) — BUKAN dihitung ulang di layar. Layar
                             yang menghitung sendiri pernah menawarkan "Create CAR"
                             untuk CA yang laporannya sudah berjalan, dan tombol itu
                             selalu berakhir ditolak.

                             Keadaan dan tindakan ditumpuk, tidak saling meniadakan:
                             bila setelan mengizinkan beberapa laporan per CA, satu
                             baris memang boleh memperlihatkan keduanya. --}}
                        <td class="px-4 py-3">
                            @php $canReport = in_array($request->id, $reportableIds, true); @endphp

                            @if($request->car_count > 0 || $canReport)
                                <div class="flex flex-col items-start gap-1">
                                    @if($request->car_count > 0)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-medium">
                                            {{ $request->settlementLabel() }}
                                        </span>
                                    @endif

                                    @if($canReport)
                                        {{-- Pintu masuk laporan ada di tempat orang melihat
                                             uang mukanya, bukan hanya di halaman lain. --}}
                                        <a href="{{ route('general.my-cash-advance-report.create', ['ca' => $request->id]) }}"
                                           class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-medium hover:bg-amber-200 transition-colors">
                                            Create CAR
                                        </a>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $request->request_date->format('d.m.Y') }}
                            @if($request->dateRangeLabel())
                                <span class="block text-xs text-gray-400">{{ $request->dateRangeLabel() }}</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">{{ $request->description }}</td>

                        <td class="px-4 py-3">{{ $request->approverLabel() }}</td>

                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span class="text-xs text-gray-400">{{ $request->currency }}</span>
                            {{ $amounts->format((float) $request->amount, false) }}
                        </td>

                        {{-- Dua baris, meniru acuan: keadaan dokumen di atas,
                             langkah yang sedang ditunggu di bawah. --}}
                        <td class="px-4 py-3">
                            @php
                                $badge = match ($request->status) {
                                    CashAdvance::STATUS_APPROVED  => 'bg-green-50 text-green-700',
                                    CashAdvance::STATUS_REJECTED  => 'bg-red-50 text-red-700',
                                    CashAdvance::STATUS_CANCELLED => 'bg-gray-100 text-gray-600',
                                    default                       => 'bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ CashAdvance::STATUS_LABELS[$request->status] ?? $request->status }}
                            </span>
                            @if($request->isOpen() && $request->currentStepName())
                                <span class="block text-xs text-gray-400 mt-0.5">
                                    Current step: {{ $request->currentStepName() }}
                                </span>
                            @elseif($request->completed_at)
                                <span class="block text-xs text-gray-400 mt-0.5">
                                    {{ $request->completed_at->format('d.m.Y') }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('general.my-cash-advance.show', $request) }}"
                                   title="View details"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    View
                                </a>
                                <a href="{{ route('general.my-cash-advance.print', $request) }}" target="_blank"
                                   title="Print"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    Print
                                </a>

                                {{-- DUA gerbang: setelan menyalakannya, keadaan
                                     dokumen mengizinkannya. Keduanya diperiksa
                                     ULANG di service — tombol yang disembunyikan
                                     bukan penjagaan. --}}
                                @if($settings->allow_requester_cancel && $request->isCancellable())
                                <form method="POST" action="{{ route('general.my-cash-advance.cancel', $request) }}"
                                      class="js-ca-cancel inline" data-no="{{ $request->request_no }}">
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
                        <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">
                            No cash advance requests yet.
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
    // 🔴 showConfirm(), bukan confirm() bawaan peramban. Form-nya sungguhan dan
    // sudah membawa @csrf dari Blade; JavaScript hanya menahan submit sebentar.
    document.querySelectorAll('.js-ca-cancel').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm(
                `Cancel cash advance ${form.dataset.no}? This cannot be undone, and you would need to `
                + `submit a new request.`,
                'Cancel cash advance',
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
