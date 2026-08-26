@extends('dashboard')

@section('title', 'My Reimbursement')
@section('page-title', 'My Reimbursement')
@section('page-subtitle', 'Submit and track your own reimbursement claims')

@section('page-actions')
    <a href="{{ route('general.my-reimbursement.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Submit Reimbursement
    </a>
@endsection

@section('content')
@php use App\Models\Reimbursement\ReimbursementRequest; @endphp

<div class="space-y-5">

    {{-- Kartu ringkasan bulan berjalan. Kartu "Approved Amount" sengaja TIDAK
         ada — sama seperti di sisi HR. Nominal per dokumen sudah terbaca di
         kolom Amount pada tabel di bawah; total sebulan dibaca dari cetakan
         atau ekspor, dengan periode yang jelas. --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach([
            ['label' => 'Submitted',      'value' => $summary['submitted'], 'class' => 'text-gray-900'],
            ['label' => 'Approved',       'value' => $summary['approved'],  'class' => 'text-green-600'],
            ['label' => 'Waiting Review', 'value' => $summary['pending'],   'class' => 'text-amber-600'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-2xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $month }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Reimbursement Requests</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Click a row to see its items. A submitted claim can only be changed by HR.
                </p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Document No.</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Summary</th>
                        <th class="px-4 py-3 w-20 text-center">Item</th>
                        <th class="px-4 py-3 w-36 text-right">Amount</th>
                        <th class="px-4 py-3 w-52 text-center">Status</th>
                        <th class="px-4 py-3 w-32 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $index => $request)
                    @php $attention = $request->attentionFlags(); @endphp

                    <tr class="align-top hover:bg-gray-50 transition-colors {{ $attention ? 'bg-amber-50/60' : '' }}">
                        <td class="px-4 py-3 text-gray-400">{{ $index + 1 }}</td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <button type="button" class="js-toggle-items font-mono text-xs text-red-800 font-semibold hover:underline"
                                    data-target="items-{{ $request->id }}">
                                {{ $request->request_no }}
                            </button>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap text-gray-900">
                            {{ $request->request_date->format('d M Y') }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $request->title }}</div>
                            <div class="text-xs text-gray-400">{{ $request->charged_to_label ?? '—' }}</div>

                            @if($attention)
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                @foreach($attention as $flag)
                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-semibold rounded">
                                        {{ ReimbursementRequest::FLAG_LABELS[$flag] ?? $flag }}
                                    </span>
                                @endforeach
                            </div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-center text-gray-600">{{ $request->item_count }}</td>

                        <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">
                            {{ $request->totalLabel() }}
                        </td>

                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @php
                                $badge = match($request->status) {
                                    'approved'  => 'bg-green-100 text-green-700',
                                    'rejected'  => 'bg-red-100 text-red-700',
                                    'in_review' => 'bg-blue-100 text-blue-700',
                                    default     => 'bg-amber-100 text-amber-700',
                                };
                            @endphp
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge }}">
                                {{ $request->statusLabel() }}
                            </span>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('general.my-reimbursement.show', $request) }}" title="View details"
                                   class="px-2 py-1.5 text-blue-700 hover:bg-blue-50 rounded transition-all">
                                    <i class="fas fa-file-lines text-xs"></i>
                                </a>
                                <a href="{{ route('general.my-reimbursement.print', $request) }}" target="_blank" title="Print"
                                   class="px-2 py-1.5 text-gray-600 hover:bg-gray-100 rounded transition-all">
                                    <i class="fas fa-print text-xs"></i>
                                </a>
                                @if($request->supporting_url)
                                <a href="{{ $request->supporting_url }}" target="_blank" rel="noopener noreferrer"
                                   title="Open supporting file"
                                   class="px-2 py-1.5 text-teal-700 hover:bg-teal-50 rounded transition-all">
                                    <i class="fas fa-up-right-from-square text-xs"></i>
                                </a>
                                @else
                                <span class="px-2 py-1.5 text-gray-300" title="No supporting document">
                                    <i class="fas fa-up-right-from-square text-xs"></i>
                                </span>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Rincian item. Disembunyikan sampai nomor dokumennya
                         ditekan — kolom yang selalu terbuka membuat daftar 20
                         dokumen tidak terbaca sama sekali. --}}
                    <tr id="items-{{ $request->id }}" class="hidden bg-gray-50">
                        <td colspan="8" class="px-4 py-4">
                            <div class="border border-gray-200 rounded-lg overflow-x-auto bg-white">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr class="text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                                            <th class="px-3 py-2 w-10">No</th>
                                            <th class="px-3 py-2">Description</th>
                                            <th class="px-3 py-2">Category</th>
                                            <th class="px-3 py-2 w-28">Receipt No.</th>
                                            <th class="px-3 py-2 w-56">Receipt Date</th>
                                            <th class="px-3 py-2 w-32 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($request->items as $item)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-400">{{ $item->line_no }}</td>
                                            <td class="px-3 py-2 text-gray-900">{{ $item->description }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $item->cost_center_label ?? '—' }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $item->receipt_no ?? '—' }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $item->receiptDateLabel() }}</td>
                                            <td class="px-3 py-2 text-right font-medium text-gray-900">
                                                {{ ReimbursementRequest::formatRupiah($item->amount) }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-gray-50 border-t border-gray-200">
                                        <tr>
                                            <td colspan="5" class="px-3 py-2 text-right text-[10px] font-semibold text-gray-500 uppercase tracking-wide">Grand Total</td>
                                            <td class="px-3 py-2 text-right font-bold text-red-800">{{ $request->totalLabel() }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Jejak persetujuan: karyawan tahu dokumennya
                                 menunggu siapa tanpa perlu bertanya ke HR. --}}
                            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs">
                                @foreach($request->approvals as $approval)
                                @php
                                    $dot = match($approval->status) {
                                        'approved' => 'bg-green-500',
                                        'rejected' => 'bg-red-500',
                                        'skipped'  => 'bg-gray-300',
                                        default    => 'bg-amber-400',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>
                                    <span class="text-gray-600">{{ $approval->step_name }}</span>
                                    @if($approval->acted_at)
                                        <span class="text-gray-400">· {{ $approval->acted_at->format('d M') }}</span>
                                    @endif
                                    @if($approval->notes)
                                        <span class="text-gray-400 italic">"{{ $approval->notes }}"</span>
                                    @endif
                                </span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            No reimbursement yet.
                            <a href="{{ route('general.my-reimbursement.create') }}" class="text-red-800 font-semibold hover:underline">Submit your first one.</a>
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
    document.querySelectorAll('.js-toggle-items').forEach(function (button) {
        button.addEventListener('click', function () {
            const row = document.getElementById(button.dataset.target);
            if (row) row.classList.toggle('hidden');
        });
    });
</script>
@endpush
