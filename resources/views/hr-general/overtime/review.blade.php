@extends('dashboard')

@section('title', 'Overtime Management')
@section('page-title', 'Overtime Management')
@section('page-subtitle', 'Review, approve, and export employee overtime claims')

@section('page-actions')
    @if($can('general.overtime.export'))
    <a href="{{ route('general.overtime.export', request()->query()) }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
        </svg>
        Download Excel
    </a>
    @endif
@endsection

@section('content')
<div class="space-y-5">

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach([
            ['label' => 'Waiting for You', 'value' => $counts['mine'],     'class' => 'text-red-800'],
            ['label' => 'Pending Total',   'value' => $counts['pending'],  'class' => 'text-amber-600'],
            ['label' => 'Approved',        'value' => $counts['approved'], 'class' => 'text-green-600'],
            ['label' => 'Rejected',        'value' => $counts['rejected'], 'class' => 'text-red-600'],
            ['label' => 'Approved Hours',  'value' => \App\Models\Overtime\OvertimeRequest::formatMinutes($counts['minutes']), 'class' => 'text-gray-900'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Selama nominal rupiah belum dapat dihitung, keadaannya disampaikan
         terbuka. Kolom uang yang kosong di setiap baris lebih membingungkan
         daripada keterangan yang jujur. --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">Amounts are not calculated yet.</span>
            Overtime multipliers follow Government Regulation No. 35 of 2021 and are already implemented,
            but the payroll module that supplies each employee's wage does not exist yet.
            Durations and day types are recorded now so amounts can be calculated later without re-reading history.
        </p>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="mb-6 pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Overtime Requests</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                A request moves to the next step only after the current one is approved.
                The employee's claim is what gets reviewed; the attendance column is shown for comparison.
            </p>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('general.overtime.index') }}"
              class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Request no, employee, or reason..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        @foreach([
                            'open' => 'Open', 'approved' => 'Approved', 'rejected' => 'Rejected',
                            'cancelled' => 'Cancelled', 'all' => 'All',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Scope</label>
                    <select name="scope" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="all"  @selected($filters['scope'] === 'all')>Everyone</option>
                        <option value="mine" @selected($filters['scope'] === 'mine')>Waiting for me</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <div class="md:col-span-12 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">Apply</button>
                    <a href="{{ route('general.overtime.index') }}"
                       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Reset</a>
                </div>
            </div>
        </form>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Claimed</th>
                        <th class="px-4 py-3">Attendance</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Approval</th>
                        @if($can('general.overtime.approve'))
                        <th class="px-4 py-3 w-80">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $index => $request)
                    @php
                        $attention = $request->attentionFlags();
                        $isMine    = in_array($request->id, $mineIds, true);
                    @endphp
                    <tr class="align-top {{ $attention ? 'bg-amber-50/60' : '' }} hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-gray-400">{{ $requests->firstItem() + $index }}</td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-mono text-xs text-red-800 font-semibold">{{ $request->request_no }}</span>
                            @if($isMine)
                                <span class="block mt-1 px-1.5 py-0.5 bg-red-800 text-white text-[10px] font-bold rounded uppercase tracking-wide text-center">Your turn</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $request->employee?->basicData?->nick_name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">
                                {{ $request->employee?->eci }}
                                @if($request->employee?->basicData?->department)
                                    · {{ $request->employee->basicData->department }}
                                @endif
                            </div>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-gray-900">{{ $request->overtime_date->format('d M Y') }}</div>
                            <div class="text-xs {{ $request->day_type === 'workday' ? 'text-gray-400' : 'text-orange-600 font-semibold' }}">
                                {{ $request->dayTypeLabel() }}
                            </div>
                        </td>

                        <td class="px-4 py-3 font-mono text-xs text-gray-700 whitespace-nowrap">
                            {{ substr($request->start_time, 0, 5) }} – {{ substr($request->end_time, 0, 5) }}
                            @if($request->crosses_midnight)
                                <span class="block text-amber-600">next day</span>
                            @endif
                            @if($request->original_start_time)
                                <span class="block mt-1 text-purple-600">
                                    was {{ substr($request->original_start_time, 0, 5) }}–{{ substr($request->original_end_time, 0, 5) }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">
                            {{ $request->durationLabel() }}
                        </td>

                        {{-- Pembanding, bukan penentu. Angka absensi ditampilkan
                             berdampingan supaya penyetuju memutuskan dengan
                             kedua sisi terlihat. --}}
                        <td class="px-4 py-3 text-xs whitespace-nowrap">
                            @if($request->hasFlag(\App\Models\Overtime\OvertimeRequest::FLAG_FUTURE_CLAIM))
                                <span class="text-gray-400">not yet</span>
                            @elseif($request->attendance_overtime_minutes !== null)
                                <span class="text-gray-700">
                                    {{ \App\Models\Overtime\OvertimeRequest::formatMinutes($request->attendance_overtime_minutes) }}
                                </span>
                                @if($request->hasFlag(\App\Models\Overtime\OvertimeRequest::FLAG_DURATION_MISMATCH))
                                    <span class="block text-amber-700 font-semibold">
                                        differs by
                                        {{ \App\Models\Overtime\OvertimeRequest::formatMinutes(abs($request->duration_minutes - $request->attendance_overtime_minutes)) }}
                                    </span>
                                @endif
                            @else
                                <span class="text-amber-700 font-semibold">no record</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-xs text-gray-600 max-w-xs">
                            {{ $request->reason }}

                            @if($attention)
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @foreach($attention as $flag)
                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded text-[10px] font-semibold">
                                        {{ \App\Models\Overtime\OvertimeRequest::FLAG_LABELS[$flag] ?? $flag }}
                                    </span>
                                @endforeach
                            </div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-xs">
                            @foreach($request->approvals as $approval)
                                @php
                                    $dot = match($approval->status) {
                                        'approved' => 'bg-green-500',
                                        'rejected' => 'bg-red-500',
                                        'skipped'  => 'bg-gray-300',
                                        default    => 'bg-amber-400',
                                    };
                                @endphp
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dot }} shrink-0"></span>
                                    <span class="text-gray-700">{{ $approval->step_name }}</span>
                                </div>
                                <div class="ml-3 text-gray-400">
                                    {{ $approval->approverLabel() }}
                                    @if($approval->actor)
                                        <span class="block text-gray-500">
                                            by {{ $approval->actor->basicData?->nick_name ?? $approval->actor->eci }}
                                            · {{ $approval->acted_at?->format('d M H:i') }}
                                        </span>
                                    @endif
                                    @if($approval->notes)
                                        <span class="block italic">"{{ $approval->notes }}"</span>
                                    @endif
                                </div>
                            @endforeach
                        </td>

                        @if($can('general.overtime.approve'))
                        <td class="px-4 py-3">
                            @if($request->isOpen() && $isMine)
                            <form method="POST" action="{{ route('general.overtime.approve', $request) }}"
                                  class="js-decision-form space-y-2"
                                  data-no="{{ $request->request_no }}"
                                  data-employee="{{ $request->employee?->basicData?->nick_name ?? 'this employee' }}">
                                @csrf

                                @if($settings->allow_approver_adjust_time)
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="time" name="start_time" value="{{ substr($request->start_time, 0, 5) }}"
                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                                    <input type="time" name="end_time" value="{{ substr($request->end_time, 0, 5) }}"
                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800">
                                </div>
                                <p class="text-[10px] text-gray-400 -mt-1">
                                    Adjust the times only if needed. The original claim stays on record.
                                </p>
                                @endif

                                <textarea name="notes" rows="2"
                                          placeholder="Note (required when rejecting)..."
                                          class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-red-800"></textarea>

                                <div class="flex gap-2">
                                    <button type="submit" data-action="approve"
                                            class="flex-1 px-3 py-1.5 bg-green-700 text-white text-xs font-semibold rounded hover:bg-green-800 transition-all">
                                        Approve
                                    </button>
                                    <button type="submit" data-action="reject"
                                            class="flex-1 px-3 py-1.5 bg-white text-red-700 text-xs font-semibold rounded border border-red-200 hover:bg-red-50 transition-all">
                                        Reject
                                    </button>
                                </div>
                            </form>
                            @elseif($request->isOpen())
                                <span class="text-xs text-gray-400">
                                    Waiting for {{ $request->currentApproval()?->step_name ?? 'another step' }}
                                </span>
                            @else
                                <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            No overtime requests match this filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Tombol Approve dan Reject berbagi satu form. Tujuan kirimnya ditentukan
    // saat diklik, bukan lewat dua form terpisah — supaya catatan dan jam yang
    // sudah diisi tidak hilang saat penyetuju berganti pikiran.
    document.querySelectorAll('.js-decision-form').forEach(function (form) {
        let chosen = 'approve';

        form.querySelectorAll('button[data-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                chosen = button.dataset.action;
            });
        });

        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;

            event.preventDefault();

            const notes = form.querySelector('textarea[name="notes"]');

            if (chosen === 'reject' && notes.value.trim().length < 5) {
                showNotification('Please explain why the request is rejected (at least 5 characters).', 'error');
                notes.focus();
                return;
            }

            const message = chosen === 'approve'
                ? `Approve overtime ${form.dataset.no} for ${form.dataset.employee}?`
                : `Reject overtime ${form.dataset.no} for ${form.dataset.employee}? The employee will be notified with your note.`;

            const ok = await showConfirm(
                message,
                chosen === 'approve' ? 'Approve Overtime' : 'Reject Overtime',
                chosen === 'approve' ? 'primary' : 'danger',
                { okText: chosen === 'approve' ? 'Approve' : 'Reject', cancelText: 'Cancel' }
            );

            if (!ok) return;

            if (chosen === 'reject') {
                form.action = form.action.replace(/\/approve$/, '/reject');
            }

            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });
</script>
@endpush
