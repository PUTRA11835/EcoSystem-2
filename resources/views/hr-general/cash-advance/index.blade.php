@extends('dashboard')

@section('title', 'Cash Advances')
@section('page-title', 'Cash Advances')
@section('page-subtitle', 'Manage and process employee cash advance (CA) requests')

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

    {{-- ── Kartu ringkasan ─────────────────────────────────────────────────
         Kartu "Waiting for You" menjawab pertanyaan pertama setiap penyetuju
         saat membuka halaman ini, dan kartu "Outstanding" menjawab pertanyaan
         pertama bagian keuangan — uang yang sudah keluar tetapi bukunya belum
         ditutup. Yang kedua hanya mungkin karena `status` dan
         `settlement_status` dipisah jadi dua sumbu (D140). --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <a href="{{ route('general.cash-advance.index', ['scope' => 'mine', 'status' => 'open']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">Waiting for you</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['mine'] }}</p>
        </a>
        <a href="{{ route('general.cash-advance.index', ['status' => 'open']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">In progress</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['pending'] }}</p>
        </a>
        <a href="{{ route('general.cash-advance.index', ['status' => 'approved']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">Approved</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['approved'] }}</p>
        </a>
        <a href="{{ route('general.cash-advance.index', ['status' => 'outstanding']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">Outstanding</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $amounts->format($outstanding['amount'], false) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $outstanding['count'] }} not yet reported</p>
        </a>
    </div>

    <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Cash Advances</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Manage and process employee cash advance (CA) requests.
                </p>
            </div>

            {{-- 🔴 Masing-masing tombol digantungkan pada SLUG-nya sendiri, bukan
                 pada satu hak "admin". Membuat dokumen atas nama orang lain dan
                 mengunduh seluruh data sebulan adalah dua kewenangan berbeda;
                 menggabungkannya berarti memberi yang satu selalu memberi yang
                 lain (D77). --}}
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                @if($canExport)
                    {{-- Membawa filter yang SEDANG aktif, supaya isi berkas selalu
                         sama dengan yang dilihat di layar (D48). --}}
                    <a href="{{ route('general.cash-advance.export', request()->query()) }}"
                       class="w-full sm:w-auto text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors whitespace-nowrap">
                        Export Excel
                    </a>
                @endif

                @if($canCreate)
                    <a href="{{ route('general.cash-advance.create') }}"
                       class="w-full sm:w-auto text-center px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors whitespace-nowrap">
                        + New CA
                    </a>
                @endif
            </div>
        </div>

        {{-- ── Filter, susunan persis acuan ────────────────────────────── --}}
        <form method="GET" action="{{ route('general.cash-advance.index') }}"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Month</label>
                <input type="month" name="month" value="{{ $filters['month'] }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                <select name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    @php
                        $statusOptions = [
                            'open'        => 'In progress',
                            'all'         => 'All',
                            'outstanding' => 'Outstanding (approved, not reported)',
                        ] + collect(CashAdvance::STATUSES)
                            ->mapWithKeys(fn ($s) => [$s => CashAdvance::STATUS_LABELS[$s]])
                            ->all();

                        // Dokumen terhapus hanya ditawarkan kepada pemegang hak
                        // kelola — ia ada demi audit, bukan demi rekap harian.
                        if ($canManage) {
                            $statusOptions['deleted'] = 'Deleted';
                        }
                    @endphp
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] }}"
                       placeholder="Document no, requester, notes..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>

            <div class="flex items-end gap-2">
                <input type="hidden" name="scope" value="{{ $filters['scope'] }}">
                {{-- bg-gray-800 SENGAJA netral, bukan warna aksen — konvensi UI
                     modul ini untuk tombol filter. --}}
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                    Apply
                </button>
                <a href="{{ route('general.cash-advance.index') }}"
                   class="flex-1 text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Reset
                </a>
            </div>
        </form>

        @if($filters['scope'] === 'mine')
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2.5 mb-4 flex items-center justify-between">
                <p class="text-sm text-blue-900">Showing only documents waiting for your decision.</p>
                <a href="{{ route('general.cash-advance.index', ['status' => $filters['status']]) }}"
                   class="text-sm font-medium text-blue-900 underline">Show all</a>
            </div>
        @endif

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Document No.</th>
                        <th class="px-4 py-3 w-28">CAR</th>
                        <th class="px-4 py-3 w-32">Date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Requester</th>
                        <th class="px-4 py-3">Approver</th>
                        <th class="px-4 py-3 w-32 text-right">Amount</th>
                        <th class="px-4 py-3 w-44">Status</th>
                        <th class="px-4 py-3 w-32 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $request)
                    @php $isMine = in_array($request->id, $mineIds, true); @endphp
                    <tr class="hover:bg-gray-50 transition-colors align-top {{ $isMine ? 'bg-blue-50/40' : '' }}">
                        <td class="px-4 py-3 text-gray-500">
                            {{ $requests->firstItem() + $loop->index }}
                        </td>

                        <td class="px-4 py-3">
                            <a href="{{ route('general.cash-advance.show', $request) }}"
                               class="font-medium primary-text hover:underline">
                                {{ $request->request_no }}
                            </a>
                            @if($request->trashed())
                                <span class="block text-xs text-red-600 mt-0.5">Deleted</span>
                            @elseif($isMine)
                                <span class="block text-xs text-blue-700 mt-0.5">Waiting for you</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            {{-- 🔴 "Report due" berasal dari $reportableIds, yaitu
                                 CashAdvanceReportService::checkEligibility()
                                 (Keputusan D154). Halaman HR dan halaman karyawan
                                 harus menjawab pertanyaan yang sama dengan cara yang
                                 sama; kalau tidak, dua layar bercerita berbeda
                                 tentang dokumen yang sama. --}}
                            @php $canReport = in_array($request->id, $reportableIds, true); @endphp

                            @if($request->car_count > 0 || $canReport)
                                <div class="flex flex-col items-start gap-1">
                                    @if($request->car_count > 0)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-medium">
                                            {{ $request->settlementLabel() }}
                                        </span>
                                    @endif

                                    @if($canReport)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-medium">
                                            Report due
                                        </span>
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

                        <td class="px-4 py-3">
                            {{ $request->employee?->basicData?->nick_name ?? $request->employee?->eci ?? '—' }}
                        </td>

                        <td class="px-4 py-3">{{ $request->approverLabel() }}</td>

                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span class="text-xs text-gray-400">{{ $request->currency }}</span>
                            {{ $amounts->format((float) $request->amount, false) }}
                        </td>

                        {{-- Dua baris, persis acuan: "Submitted" di atas,
                             "Current step: Verification" di bawah. --}}
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
                                    {{ $request->statusLabel('Pending') }}: {{ $request->completed_at->format('d.m.Y') }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('general.cash-advance.show', $request) }}" title="View details"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    View
                                </a>
                                <a href="{{ route('general.cash-advance.print', $request) }}" target="_blank" title="Print"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    Print
                                </a>
                                {{-- 🔴 Edit / Export / Delete menyusul di A6.
                                     Approve & Reject ada di halaman detail: yang
                                     kedua menuntut alasan tertulis, dan itu tidak
                                     muat sebagai tombol sekali klik di rekap. --}}
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">
                            No cash advance matches this filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
