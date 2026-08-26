@extends('dashboard')

@section('title', 'Reimbursement Details')
@section('page-title', 'Reimbursement Details')
@section('page-subtitle', 'Document ' . $request->request_no)

{{--
    Satu halaman detail untuk KEDUA sisi. Sisi karyawan memanggilnya dengan
    variabel opsional dibiarkan kosong; sisi HR mengisinya. Dibuat satu berkas
    supaya dokumen yang sama tidak pernah tampil berbeda tergantung siapa yang
    membukanya — kecuali pada tombol, yang memang berbeda kewenangannya.

    Variabel wajib : $request · $signatories · $backRoute · $printRoute
    Variabel opsional: $canApprove · $approveRoute · $rejectRoute · $editRoute
                       $exportRoute · $deleteRoute · $waitingForMe
--}}
@php
    use App\Models\Reimbursement\ReimbursementRequest;

    $canApprove   = $canApprove   ?? false;
    $approveRoute = $approveRoute ?? null;
    $rejectRoute  = $rejectRoute  ?? null;
    $editRoute    = $editRoute    ?? null;
    $exportRoute  = $exportRoute  ?? null;
    $deleteRoute  = $deleteRoute  ?? null;
    $waitingForMe = $waitingForMe ?? false;

    $attention = $request->attentionFlags();
@endphp

@section('page-actions')
    <div class="flex items-center gap-2">
        @if($editRoute && $request->isEditable())
        <a href="{{ $editRoute }}"
           class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
            Edit Reimbursement
        </a>
        @endif
        <a href="{{ $backRoute }}"
           class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
            Back to List
        </a>
    </div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

    {{-- ── Kolom kiri ──────────────────────────────────────────────────── --}}
    <div class="space-y-5">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b border-gray-100">Main Information</h2>

            <dl class="space-y-3 text-sm">
                @foreach([
                    'Document No.' => $request->request_no,
                    'Date'         => $request->request_date->format('d M Y'),
                    'Requester'    => $request->employee?->basicData?->nick_name ?? $request->employee?->eci ?? '—',
                    'Employee No.' => $request->employee?->eci ?? '—',
                    'Charged To'   => $request->charged_to_label ?? '—',
                    'Title'        => $request->title,
                ] as $label => $value)
                <div class="flex gap-3">
                    <dt class="w-32 shrink-0 text-gray-500">{{ $label }}</dt>
                    <dd class="text-gray-900 font-medium break-words">{{ $value }}</dd>
                </div>
                @endforeach

                <div class="flex gap-3">
                    <dt class="w-32 shrink-0 text-gray-500">Total Amount</dt>
                    <dd class="text-lg font-bold text-red-800">{{ $request->totalLabel() }}</dd>
                </div>

                <div class="flex gap-3">
                    <dt class="w-32 shrink-0 text-gray-500">Status</dt>
                    <dd>
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
                    </dd>
                </div>

                @if($request->created_by)
                <div class="flex gap-3">
                    <dt class="w-32 shrink-0 text-gray-500">Created by</dt>
                    <dd class="text-gray-600">
                        {{ $request->creator?->basicData?->nick_name ?? $request->creator?->eci ?? '—' }}
                        <span class="text-xs text-gray-400">(on behalf)</span>
                    </dd>
                </div>
                @endif
            </dl>

            @if($attention)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Needs attention</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($attention as $flag)
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs font-semibold rounded">
                            {{ ReimbursementRequest::FLAG_LABELS[$flag] ?? $flag }}
                        </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        @if($canApprove && $request->isOpen())
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-1 text-center">Approval Actions</h2>
            <p class="text-xs text-gray-500 text-center mb-4">
                Current step: {{ $request->currentStepName() ?? '—' }}
            </p>

            @if($waitingForMe)
            <form method="POST" action="{{ $approveRoute }}" class="js-approve-form mb-2">
                @csrf
                <input type="hidden" name="notes" value="">
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800 transition-all">
                    <i class="fas fa-circle-check"></i> Approve
                </button>
            </form>

            <button type="button" id="rejectToggle"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white text-red-700 text-sm font-semibold rounded-lg border border-red-300 hover:bg-red-50 transition-all">
                <i class="fas fa-circle-xmark"></i> Reject
            </button>

            <form method="POST" action="{{ $rejectRoute }}" id="rejectForm" class="hidden mt-3 space-y-2">
                @csrf
                <textarea name="notes" rows="3" required minlength="5" maxlength="255"
                          placeholder="Explain why this reimbursement is rejected..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>
                <button type="submit"
                        class="w-full px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                    Confirm Rejection
                </button>
            </form>
            @else
            <p class="text-sm text-gray-500 text-center">
                This document is waiting for <strong>{{ $request->currentStepName() ?? 'another step' }}</strong>,
                which you are not an approver for.
            </p>
            @endif
        </div>
        @endif

        {{-- Jejak persetujuan --}}
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b border-gray-100">Approval Trail</h2>

            <ol class="space-y-3">
                @forelse($request->approvals as $approval)
                @php
                    $dot = match($approval->status) {
                        'approved' => 'bg-green-500',
                        'rejected' => 'bg-red-500',
                        'skipped'  => 'bg-gray-300',
                        default    => 'bg-amber-400',
                    };
                @endphp
                <li class="flex gap-3 text-sm">
                    <span class="w-2 h-2 mt-1.5 rounded-full shrink-0 {{ $dot }}"></span>
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">{{ $approval->step_name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ ucfirst($approval->status) }}
                            @if($approval->actor)
                                · {{ $approval->actor->basicData?->nick_name ?? $approval->actor->eci }}
                            @else
                                · {{ $approval->approverLabel() }}
                            @endif
                            @if($approval->acted_at)
                                · {{ $approval->acted_at->format('d M Y H:i') }}
                            @endif
                        </p>
                        @if($approval->notes)
                            <p class="text-xs text-gray-500 italic mt-0.5">"{{ $approval->notes }}"</p>
                        @endif
                    </div>
                </li>
                @empty
                <li class="text-sm text-gray-400">No approval step recorded.</li>
                @endforelse
            </ol>
        </div>
    </div>

    {{-- ── Kolom kanan ─────────────────────────────────────────────────── --}}
    <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 pb-3 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">Reimbursement Item Details</h2>

            @if($request->supporting_url)
            <a href="{{ $request->supporting_url }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-white text-teal-700 text-xs font-semibold rounded-lg border border-teal-200 hover:bg-teal-50 transition-all">
                <i class="fas fa-up-right-from-square"></i> View Document
            </a>
            @else
            <span class="text-xs text-amber-700">No supporting document attached.</span>
            @endif
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 w-48">Receipt Date</th>
                        <th class="px-4 py-3 w-36 text-right">Amount (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($request->items as $item)
                    <tr class="align-top">
                        <td class="px-4 py-3 text-gray-400">{{ $item->line_no }}</td>
                        <td class="px-4 py-3">
                            <div class="text-gray-900">{{ $item->cost_center_label ?? '—' }}</div>
                            <div class="text-xs text-gray-400">Receipt No.: {{ $item->receipt_no ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $item->description }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $item->receiptDateLabel() }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900 whitespace-nowrap">
                            {{ number_format((float) $item->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Grand Total</td>
                        <td class="px-4 py-3 text-right text-lg font-bold text-red-800 whitespace-nowrap">
                            {{ number_format((float) $request->total_amount, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Blok tanda tangan yang akan tercetak --}}
        <div class="mt-5 grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                'Requester'   => $signatories['requester'],
                'Accounting'  => $signatories['accounting'],
                'Cashier'     => $signatories['cashier'],
                'Approved by' => $signatories['approver'],
            ] as $label => $name)
            <div class="border border-gray-200 rounded-lg px-3 py-2">
                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                <p class="text-sm text-gray-800 mt-0.5">{{ $name }}</p>
            </div>
            @endforeach
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap gap-2 justify-end">
            @if($exportRoute)
            <a href="{{ $exportRoute }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800 transition-all">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            @endif

            <a href="{{ $printRoute }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">
                <i class="fas fa-print"></i> Print Form
            </a>

            @if($deleteRoute)
            <button type="button" id="deleteToggle"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white text-red-700 text-sm font-semibold rounded-lg border border-red-300 hover:bg-red-50 transition-all">
                <i class="fas fa-trash"></i> Delete
            </button>
            @endif
        </div>

        {{-- Alasan penghapusan diminta lewat form yang disingkap, BUKAN lewat
             prompt JavaScript: satu-satunya helper global yang tersedia di
             aplikasi ini adalah showConfirm(), dan memperkenalkan helper baru
             hanya untuk satu tombol bukan alasan yang cukup. Service menolak
             alasan kosong, jadi field ini `required`. --}}
        @if($deleteRoute)
        <form method="POST" action="{{ $deleteRoute }}" id="deleteForm"
              class="hidden mt-4 border border-red-200 bg-red-50 rounded-lg p-4 space-y-2">
            @csrf
            <p class="text-sm text-red-900">
                <span class="font-semibold">{{ $request->request_no }} will be removed from the list.</span>
                It stays in the database with your name and the reason below, so it can still be audited.
            </p>
            <textarea name="delete_reason" rows="2" required minlength="3" maxlength="255"
                      placeholder="Why is this document being deleted?"
                      class="w-full px-3 py-2 border border-red-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>
            <button type="submit"
                    class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                Confirm Deletion
            </button>
        </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    const rejectToggle = document.getElementById('rejectToggle');
    if (rejectToggle) {
        rejectToggle.addEventListener('click', function () {
            document.getElementById('rejectForm').classList.toggle('hidden');
        });
    }

    document.querySelectorAll('.js-approve-form').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm(
                'Approve this reimbursement?',
                'Approve Reimbursement',
                'warning',
                { okText: 'Approve', cancelText: 'Cancel' }
            );

            if (!ok) return;
            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });

    const deleteToggle = document.getElementById('deleteToggle');
    if (deleteToggle) {
        deleteToggle.addEventListener('click', function () {
            document.getElementById('deleteForm').classList.toggle('hidden');
        });
    }
</script>
@endpush
