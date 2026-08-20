@extends('dashboard')

@section('title', 'Attendance Corrections')
@section('page-title', 'Attendance Corrections')
@section('page-subtitle', 'Review employee check-in and check-out changes before applying them to attendance data')

@section('content')
<div class="space-y-5">

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach([
            ['label' => 'Pending Review', 'value' => $counts['pending'],  'class' => 'text-amber-600'],
            ['label' => 'Approved',       'value' => $counts['approved'], 'class' => 'text-green-600'],
            ['label' => 'Rejected',       'value' => $counts['rejected'], 'class' => 'text-red-600'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Correction Requests</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Approving a request updates the employee's attendance and keeps the previous times on record.
                </p>
            </div>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('general.attendance.corrections.index') }}"
              class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-7">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Search</label>
                    <input type="text" name="search" value="{{ $search }}"
                           placeholder="Employee name, code, or reason..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                    <select name="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">Apply</button>
                    <a href="{{ route('general.attendance.corrections.index') }}"
                       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Reset</a>
                </div>
            </div>
        </form>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Current Data</th>
                        <th class="px-4 py-3">Proposed Correction</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        @if($can('general.attendance.correction.approve'))
                        <th class="px-4 py-3 w-72">HR Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($corrections as $index => $correction)
                    <tr class="hover:bg-gray-50 transition-colors align-top">
                        <td class="px-4 py-3 text-gray-400">{{ $corrections->firstItem() + $index }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $correction->employee?->basicData?->nick_name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">
                                {{ $correction->employee?->eci }}
                                @if($correction->employee?->basicData?->department)
                                    · {{ $correction->employee->basicData->department }}
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $correction->attendance_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-xs font-mono text-gray-600 whitespace-nowrap">
                            In: {{ $correction->record?->check_in_at?->format('H:i') ?? '–' }}<br>
                            Out: {{ $correction->record?->check_out_at?->format('H:i') ?? '–' }}<br>
                            <span class="text-gray-400">{{ $correction->record ? ucfirst($correction->record->day_status) : 'No record' }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-gray-900 whitespace-nowrap">
                            In: <strong>{{ $correction->requested_check_in ? substr($correction->requested_check_in, 0, 5) : '–' }}</strong><br>
                            Out: <strong>{{ $correction->requested_check_out ? substr($correction->requested_check_out, 0, 5) : '–' }}</strong>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 max-w-xs">
                            {{ $correction->reason }}
                            @if($correction->hr_note)
                                <span class="block mt-1 text-gray-400">HR note: {{ $correction->hr_note }}</span>
                            @endif
                            @if($correction->status === 'approved' && $correction->original_check_in)
                                <span class="block mt-1 text-gray-400">
                                    Replaced: in {{ substr($correction->original_check_in, 0, 5) }},
                                    out {{ $correction->original_check_out ? substr($correction->original_check_out, 0, 5) : '–' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusClass = match ($correction->status) {
                                    'approved'  => 'bg-green-100 text-green-700',
                                    'rejected'  => 'bg-red-100 text-red-700',
                                    'cancelled' => 'bg-gray-100 text-gray-500',
                                    default     => 'bg-amber-100 text-amber-700',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $statusClass }}">
                                {{ ucfirst($correction->status) }}
                            </span>
                            @if($correction->approved_at)
                                <span class="block text-xs text-gray-400 mt-1 whitespace-nowrap">
                                    {{ $correction->approved_at->format('d M H:i') }}
                                </span>
                            @endif
                        </td>
                        @if($can('general.attendance.correction.approve'))
                        <td class="px-4 py-3">
                            @if($correction->isPending())
                            <form method="POST" class="space-y-2 correction-action"
                                  data-employee="{{ $correction->employee?->basicData?->nick_name ?? 'this employee' }}"
                                  data-date="{{ $correction->attendance_date->format('d M Y') }}"
                                  action="{{ route('general.attendance.corrections.approve', $correction) }}">
                                @csrf
                                <textarea name="hr_note" rows="2" maxlength="255"
                                          placeholder="HR note (optional to approve, required to reject)"
                                          class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>
                                <div class="flex gap-2">
                                    <button type="button" onclick="submitCorrection(this, 'approve')"
                                            class="flex-1 px-3 py-1.5 bg-green-700 text-white text-xs font-semibold rounded-lg hover:bg-green-800 transition-all">
                                        Approve
                                    </button>
                                    <button type="button" onclick="submitCorrection(this, 'reject')"
                                            class="flex-1 px-3 py-1.5 bg-white text-red-600 text-xs font-semibold rounded-lg border border-red-200 hover:bg-red-50 transition-all">
                                        Reject
                                    </button>
                                </div>
                            </form>
                            @else
                            <span class="text-xs text-gray-400">
                                Reviewed by {{ $correction->approver?->basicData?->nick_name ?? 'HR' }}
                            </span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <i class="fas fa-inbox text-3xl"></i>
                                <p class="text-sm font-medium">No correction requests with this status.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($corrections->hasPages())
        <div class="mt-4">{{ $corrections->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
const APPROVE_BASE = @js(url('general/attendance/corrections'));

async function submitCorrection(button, action) {
    const form     = button.closest('form');
    const note     = form.querySelector('textarea[name="hr_note"]');
    const employee = form.dataset.employee;
    const date     = form.dataset.date;

    // Catatan wajib saat menolak. Diperiksa di sini agar pengguna tahu sebelum
    // halaman dimuat ulang; server memeriksa hal yang sama sebagai penjaga akhir.
    if (action === 'reject' && note.value.trim().length < 5) {
        showToast('Please explain why the correction is rejected (at least 5 characters).', 'warning');
        note.focus();
        return;
    }

    const message = action === 'approve'
        ? `Approve the correction for ${employee} on ${date}? Their attendance will be updated and the previous times kept on record.`
        : `Reject the correction for ${employee} on ${date}? Their attendance will stay unchanged.`;

    const ok = await showConfirm(
        message,
        action === 'approve' ? 'Approve Correction' : 'Reject Correction',
        action === 'approve' ? 'primary' : 'danger',
        { okText: action === 'approve' ? 'Approve' : 'Reject', cancelText: 'Cancel' }
    );

    if (!ok) return;

    if (action === 'reject') {
        form.action = form.action.replace(/\/approve$/, '/reject');
    }

    form.submit();
}
</script>
@endpush
