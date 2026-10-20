{{--
    Report (CAR) deadline badge of one cash advance — only while the requester still has to file it (approved, nothing reported).
    Overdue → red "Report overdue · N days"; due within the reminder window → amber; otherwise nothing.

    Uses: $request (CashAdvance), $settings (CashAdvanceSetting).
--}}
@php $due = $request->reportDue($settings); @endphp
@if($due['state'] === 'overdue')
    <span class="inline-block px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-semibold"
          title="Was due {{ $due['due_on']->format('d M Y') }}">Report overdue · {{ $due['days_late'] }} day{{ $due['days_late'] === 1 ? '' : 's' }}</span>
@elseif($due['state'] === 'due_soon')
    <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold"
          title="Due {{ $due['due_on']->format('d M Y') }}">{{ $due['days_left'] === 0 ? 'Report due today' : 'Report due in ' . $due['days_left'] . ' day' . ($due['days_left'] === 1 ? '' : 's') }}</span>
@endif
