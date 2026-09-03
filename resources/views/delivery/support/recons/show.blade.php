@extends('dashboard')

@section('title', 'Recons Detail')
@section('page-title', 'Recons Detail')
@section('page-subtitle', $recons->recons_number)

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Back --}}
    <div class="mb-4">
        <a href="{{ route('delivery.support.show', $support->id) }}#recons"
           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Support Details
        </a>
    </div>

    {{-- ── Header batch ───────────────────────────────────────────────── --}}
    <div class="bg-white shadow-md rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex items-start justify-between flex-wrap gap-3">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $recons->recons_number }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-semibold
                                 {{ $recons->isSubmitted() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $recons->status_label }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $support->client->basicData->name_1 ?? 'N/A' }} • {{ $support->name ?? ('Support #' . $support->id) }}
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap justify-end">
                <a href="{{ route('delivery.support.recons.export', [$support->id, $recons->id]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export Excel
                </a>

                @if($recons->isSubmitted() && $can('delivery-support.recons.edit'))
                {{-- Batch tersubmit masih bisa dibatalkan → kembali jadi draft. --}}
                <button type="button" id="btnCancelRecons" onclick="ReconsDetail.cancel()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-amber-300 text-amber-800 text-sm font-semibold rounded-lg hover:bg-amber-50 transition disabled:opacity-60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4"/>
                    </svg>
                    Cancel Submit
                </button>
                @endif

                @if(!$recons->isSubmitted() && $can('delivery-support.recons.edit'))
                <a href="{{ route('delivery.support.recons.edit', [$support->id, $recons->id]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Draft
                </a>
                <button type="button" id="btnSubmitRecons" onclick="ReconsDetail.submit()"
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Submit
                </button>
                @endif
            </div>
        </div>

        <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 text-sm">
            <div>
                <p class="text-xs text-gray-500 mb-1">Recons Date</p>
                <p class="font-medium text-gray-900">{{ $recons->recons_date?->format('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Description</p>
                <p class="font-medium text-gray-900">{{ $recons->description ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Created By</p>
                <p class="font-medium text-gray-900">
                    {{ trim(($recons->createdBy->basicData->first_name ?? '') . ' ' . ($recons->createdBy->basicData->last_name ?? '')) ?: '-' }}
                </p>
                <p class="text-xs text-gray-400">{{ $recons->created_at?->format('d M Y H:i') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Submitted By</p>
                <p class="font-medium text-gray-900">
                    {{ trim(($recons->submittedBy->basicData->first_name ?? '') . ' ' . ($recons->submittedBy->basicData->last_name ?? '')) ?: '-' }}
                </p>
                <p class="text-xs text-gray-400">{{ $recons->submitted_at?->format('d M Y H:i') ?? '-' }}</p>
            </div>
        </div>
    </div>

    {{-- ── Detail tiket ───────────────────────────────────────────────── --}}
    <div class="bg-white shadow-md rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
            <h3 class="text-base font-semibold text-gray-800">Reconciled Tickets</h3>
            <div class="flex items-center gap-6 text-sm">
                <div class="text-right">
                    <p class="text-xs text-gray-500">Tickets</p>
                    <p class="font-bold text-gray-900">{{ $summary['ticket_count'] }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Total MD</p>
                    <p class="font-bold text-gray-900">{{ number_format($summary['total_md'], 2) }}</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-5">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Ticket Number</th>
                            <th class="px-4 py-3 text-left font-semibold" style="min-width:260px;">Description</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Start Date</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Close Date</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Status</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Type</th>
                            <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Customer MD</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">{{ $row['ticket_number'] ?: ('#' . $row['ticket_id']) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row['description'] ?: '-' }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">{{ $row['start_date_label'] }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">{{ $row['close_date_label'] }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">{{ $row['status_label'] }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">{{ $row['type'] ?: '-' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">
                                {{ $row['man_days'] !== null ? number_format($row['man_days'], 2) : '-' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-10 text-gray-500 text-xs">No ticket in this recons.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                * Customer MD shows the value recorded when the ticket was added to this recons,
                so a later change on the ticket does not alter a reconciled figure.
            </p>
        </div>
    </div>
</div>

@if($can('delivery-support.recons.edit'))
<script>
window.ReconsDetail = (function () {
    'use strict';

    // URL relatif — konsisten dengan section lain & aman di belakang proxy HTTPS.
    const BASE_URL   = `/delivery/support/{{ $support->id }}/recons/{{ $recons->id }}`;
    const SUBMIT_URL = `${BASE_URL}/submit`;
    const CANCEL_URL = `${BASE_URL}/cancel`;

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // Pola toast identik dengan section lain (Plan Cost / Financial).
    function notify(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
        else if (typeof window.showNotification === 'function') window.showNotification(msg, type);
        else alert(msg);
    }

    async function submit() {
        // Modal konfirmasi global (partials/confirm-modal), bukan confirm() browser.
        const ok = await showConfirm(
            @json('Submit recons ' . $recons->recons_number . ' with ' . $summary['ticket_count']
                . ' ticket(s) and ' . number_format($summary['total_md'], 2) . " MD?\n\n"
                . 'After submitting, its header and ticket list can no longer be changed.'),
            'Submit Recons',
            'primary',
            { okText: 'Submit' },
        );

        if (!ok) return;

        // Script ini ikut termuat saat batch sudah submitted (untuk aksi cancel),
        // jadi tombol submit-nya bisa saja tidak ada di halaman.
        const btn = document.getElementById('btnSubmitRecons');
        if (btn) btn.disabled = true;

        try {
            const res = await fetch(SUBMIT_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to submit recons.');

            notify(data.message, 'success');
            window.location.reload();
        } catch (e) {
            notify(e.message || 'Failed to submit recons.', 'error');
            if (btn) btn.disabled = false;
        }
    }

    /** Batalkan submit — status kembali ke Draft sehingga bisa diedit lagi. */
    async function cancel() {
        const ok = await showConfirm(
            @json('Cancel recons ' . $recons->recons_number . "? Its status returns to Draft so the header and ticket list can be edited again.\n\n"
                . 'Its tickets stay reserved for this recons and cannot be picked by another one.'),
            'Cancel Recons',
            'danger',
            { okText: 'Return to Draft', cancelText: 'Keep Submitted' },
        );

        if (!ok) return;

        const btn = document.getElementById('btnCancelRecons');
        if (btn) btn.disabled = true;

        try {
            const res = await fetch(CANCEL_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to cancel recons.');

            notify(data.message, 'success');
            window.location.reload();
        } catch (e) {
            notify(e.message || 'Failed to cancel recons.', 'error');
            if (btn) btn.disabled = false;
        }
    }

    return { submit, cancel };
})();
</script>
@endif
@endsection
