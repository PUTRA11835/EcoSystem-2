@extends('dashboard')

@section('title', 'Cash Advance Reports')
@section('page-title', 'Cash Advance Reports')
@section('page-subtitle', 'Review settlement reports and close the book on cash advances')

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

    {{-- ── Kartu ringkasan ─────────────────────────────────────────────────
         🔴 Dua kartu uang di kanan sengaja TERPISAH, bukan dijumlahkan.
         Refund adalah uang yang harus MASUK dari karyawan; Claim adalah uang
         yang harus KELUAR ke karyawan. Menjumlahkannya menghasilkan satu angka
         yang tidak berarti apa-apa bagi siapa pun. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <a href="{{ route('general.cash-advance-report.index', ['scope' => 'mine', 'status' => 'open']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">Waiting for you</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['mine'] }}</p>
        </a>
        <a href="{{ route('general.cash-advance-report.index', ['status' => 'open']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">In progress</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['pending'] }}</p>
        </a>
        <a href="{{ route('general.cash-advance-report.index', ['status' => 'approved', 'settlement' => 'refund']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">To collect (refund)</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $amounts->format($money['refund'], false) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">employees return this</p>
        </a>
        <a href="{{ route('general.cash-advance-report.index', ['status' => 'approved', 'settlement' => 'claim']) }}"
           class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow block">
            <p class="text-xs text-gray-500">To pay (claim)</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $amounts->format($money['claim'], false) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">company reimburses this</p>
        </a>
    </div>

    <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Cash Advance Reports</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Approving the final step settles the cash advance it belongs to.
                </p>
            </div>

            @if($canExport)
                {{-- Membawa filter yang SEDANG aktif, supaya isi berkas selalu
                     sama dengan yang dilihat di layar (D48). --}}
                <a href="{{ route('general.cash-advance-report.export', request()->query()) }}"
                   class="w-full sm:w-auto text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors whitespace-nowrap shrink-0">
                    Export Excel
                </a>
            @endif
        </div>

        {{-- ── CA yang menunggu dilaporkan, SELURUH karyawan ────────────────
             🔴 Inilah pintu masuk "New CAR", dan bentuknya memang daftar —
             BUKAN dropdown karyawan seperti "New CA".

             Sebuah CAR selalu lahir dari sebuah CA, dan pemiliknya sudah
             ditentukan CA itu. Menanyakan "atas nama siapa?" lebih dulu akan
             membuka kemungkinan laporan milik satu orang menempel pada uang
             muka orang lain — keadaan yang tidak punya arti dan tidak dapat
             diperbaiki tanpa menghapus dokumennya.

             Isinya disaring CashAdvanceReportService::checkEligibility()
             (D154), jadi setiap tombol di sini pasti diterima server.
             ──────────────────────────────────────────────────────────────── --}}
        @if($canCreate && $reportable->isNotEmpty())
            <div class="border border-amber-200 bg-amber-50 rounded-lg p-4 mb-5">
                <p class="text-sm font-semibold text-amber-900 mb-3">
                    Waiting to be reported — {{ $reportable->count() }} cash
                    {{ Str::plural('advance', $reportable->count()) }}
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                    @foreach($reportable as $advance)
                        <div class="bg-white rounded-lg border border-amber-200 px-3 py-2 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $advance->request_no }}
                                </p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ $advance->employee?->basicData?->nick_name ?? $advance->employee?->eci ?? '—' }}
                                    · {{ $advance->currency }}
                                    {{ number_format((float) $advance->amount, 0, ',', '.') }}
                                </p>
                            </div>
                            <a href="{{ route('general.cash-advance-report.create', ['ca' => $advance->id]) }}"
                               class="shrink-0 px-3 py-1.5 bg-gray-800 text-white rounded text-xs font-medium hover:bg-gray-900 transition-colors whitespace-nowrap">
                                + New CAR
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('general.cash-advance-report.index') }}"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
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
                        $statusOptions = ['open' => 'In progress', 'all' => 'All']
                            + collect(CashAdvanceReport::STATUSES)
                                ->mapWithKeys(fn ($s) => [$s => CashAdvanceReport::STATUS_LABELS[$s]])
                                ->all();

                        if ($canManage) {
                            $statusOptions['deleted'] = 'Deleted';
                        }
                    @endphp
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Menyaring pada ARAH uangnya. Inilah gunanya `settlement_type`
                 DISIMPAN dan bukan dihitung saat dibaca. --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Settlement</label>
                <select name="settlement"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                    <option value="">All</option>
                    @foreach(CashAdvanceReport::SETTLEMENT_TYPES as $type)
                        <option value="{{ $type }}" @selected($filters['settlement'] === $type)>
                            {{ CashAdvanceReport::SETTLEMENT_LABELS[$type] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] }}"
                       placeholder="Report no, CA no, requester..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>

            <div class="flex items-end gap-2">
                <input type="hidden" name="scope" value="{{ $filters['scope'] }}">
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                    Apply
                </button>
                <a href="{{ route('general.cash-advance-report.index') }}"
                   class="flex-1 text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Reset
                </a>
            </div>
        </form>

        @if($filters['scope'] === 'mine')
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2.5 mb-4 flex items-center justify-between">
                <p class="text-sm text-blue-900">Showing only reports waiting for your decision.</p>
                <a href="{{ route('general.cash-advance-report.index', ['status' => $filters['status']]) }}"
                   class="text-sm font-medium text-blue-900 underline">Show all</a>
            </div>
        @endif

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Report No.</th>
                        <th class="px-4 py-3">Cash Advance</th>
                        <th class="px-4 py-3 w-32">Date</th>
                        <th class="px-4 py-3">Requester</th>
                        <th class="px-4 py-3 w-32 text-right">Advance</th>
                        <th class="px-4 py-3 w-32 text-right">Reported</th>
                        <th class="px-4 py-3 w-40">Settlement</th>
                        <th class="px-4 py-3 w-44">Status</th>
                        <th class="px-4 py-3 w-32 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($reports as $report)
                    @php $isMine = in_array($report->id, $mineIds, true); @endphp
                    <tr class="hover:bg-gray-50 transition-colors align-top {{ $isMine ? 'bg-blue-50/40' : '' }}">
                        <td class="px-4 py-3 text-gray-500">{{ $reports->firstItem() + $loop->index }}</td>

                        <td class="px-4 py-3">
                            <a href="{{ route('general.cash-advance-report.show', $report) }}"
                               class="font-medium primary-text hover:underline">{{ $report->report_no }}</a>
                            @if($report->trashed())
                                <span class="block text-xs text-red-600 mt-0.5">Deleted</span>
                            @elseif($isMine)
                                <span class="block text-xs text-blue-700 mt-0.5">Waiting for you</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <a href="{{ route('general.cash-advance.show', $report->cash_advance_id) }}"
                               class="text-gray-700 hover:underline">
                                {{ $report->cashAdvance?->request_no ?? '—' }}
                            </a>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">{{ $report->report_date->format('d.m.Y') }}</td>

                        <td class="px-4 py-3">
                            {{ $report->employee?->basicData?->nick_name ?? $report->employee?->eci ?? '—' }}
                        </td>

                        <td class="px-4 py-3 text-right whitespace-nowrap text-gray-600">
                            {{ $amounts->format((float) $report->advance_amount, false) }}
                        </td>

                        <td class="px-4 py-3 text-right whitespace-nowrap font-medium">
                            {{ $amounts->format((float) $report->reported_amount, false) }}
                        </td>

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
                                <a href="{{ route('general.cash-advance-report.show', $report) }}"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    View
                                </a>
                                <a href="{{ route('general.cash-advance-report.print', $report) }}" target="_blank"
                                   class="px-2 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50 transition-colors">
                                    Print
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">
                            No cash advance report matches this filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
            <div class="mt-4">{{ $reports->links() }}</div>
        @endif
    </div>
</div>
@endsection
