@extends('dashboard')

@section('title', 'Overtime Request')
@section('page-title', 'Overtime Request')
@section('page-subtitle', 'Document ' . $request->request_no)

@section('content')
@php
    $badge = match($request->status) {
        'approved'  => 'bg-green-100 text-green-700',
        'rejected'  => 'bg-red-100 text-red-700',
        'in_review' => 'bg-blue-100 text-blue-700',
        'cancelled' => 'bg-gray-100 text-gray-600',
        default     => 'bg-amber-100 text-amber-700',
    };
@endphp

<div class="max-w-4xl mx-auto space-y-5">
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-5 pb-4 border-b-2 border-gray-100">
            <div>
                <p class="font-mono text-xs font-semibold text-red-800">{{ $request->request_no }}</p>
                <h2 class="text-2xl font-bold text-gray-900 mt-1">{{ $request->employee?->fullNameLabel() ?? '—' }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $request->overtime_date->format('l, d M Y') }} · {{ $request->dayTypeLabel() }}</p>
            </div>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ $request->statusLabel() }}</span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div><p class="text-xs text-gray-500">Start</p><p class="text-lg font-bold text-gray-900">{{ substr((string) $request->start_time, 0, 5) }}</p></div>
            <div><p class="text-xs text-gray-500">End</p><p class="text-lg font-bold text-gray-900">{{ substr((string) $request->end_time, 0, 5) }}{{ $request->crosses_midnight ? ' (+1)' : '' }}</p></div>
            <div><p class="text-xs text-gray-500">Duration</p><p class="text-lg font-bold text-gray-900">{{ $request->durationLabel() }}</p></div>
            <div><p class="text-xs text-gray-500">Attendance overtime</p><p class="text-lg font-bold text-gray-900">{{ $request->attendance_overtime_minutes !== null ? \App\Models\Overtime\OvertimeRequest::formatMinutes($request->attendance_overtime_minutes) : '—' }}</p></div>
        </div>

        @if($request->original_start_time)
            <p class="mt-3 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                Times were adjusted by an approver. Claimed: {{ substr((string) $request->original_start_time, 0, 5) }} – {{ substr((string) $request->original_end_time, 0, 5) }}.
            </p>
        @endif

        <div class="mt-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Reason</p>
            <p class="text-sm text-gray-900 whitespace-pre-line">{{ $request->reason }}</p>
        </div>

        @if($request->attentionFlags())
            <div class="flex flex-wrap gap-1 mt-4">
                @foreach($request->attentionFlags() as $flag)
                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-semibold rounded">{{ \App\Models\Overtime\OvertimeRequest::FLAG_LABELS[$flag] ?? $flag }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Approval steps</h3>
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            @foreach($request->approvals as $approval)
                @php
                    $dot = match($approval->status) {
                        'approved' => 'bg-green-500', 'rejected' => 'bg-red-500', 'skipped' => 'bg-gray-300', default => 'bg-amber-400',
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $dot }}"></span>
                    <span class="text-gray-700">{{ $approval->step_name }}</span>
                    @if($approval->acted_at)<span class="text-gray-400">· {{ $approval->acted_at->format('d M') }}</span>@endif
                    @if($approval->notes)<span class="text-gray-400 italic">"{{ $approval->notes }}"</span>@endif
                </span>
            @endforeach
        </div>
    </div>

    @if($canAct)
        <form method="POST" action="{{ route('general.overtime.approve', $request) }}" class="bg-white rounded-xl p-6 shadow-sm space-y-4">
            @csrf
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide">Your decision</h3>

            @if($settings->allow_approver_adjust_time)
                <div class="grid grid-cols-2 gap-4 md:w-1/2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Adjust start</label>
                        <input type="time" name="start_time" value="{{ substr((string) $request->start_time, 0, 5) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Adjust end</label>
                        <input type="time" name="end_time" value="{{ substr((string) $request->end_time, 0, 5) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    </div>
                </div>
            @endif

            <textarea name="notes" rows="2" maxlength="255" placeholder="Note (required when rejecting)..."
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>

            <div class="flex gap-2">
                <button type="submit" class="px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90">Approve</button>
                <button type="submit" formaction="{{ route('general.overtime.reject', $request) }}"
                        class="px-5 py-2.5 border border-red-300 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-50">Reject</button>
            </div>
        </form>
    @elseif($request->isOpen())
        <p class="text-sm text-gray-500 text-center">This request is not waiting for your decision right now.</p>
    @endif
</div>
@endsection
