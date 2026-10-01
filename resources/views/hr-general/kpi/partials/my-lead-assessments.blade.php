{{-- Assessments the current user received from their own lead (data comes from
     the lead's review on lead-type evaluations where this user is the subject).
     Included by my-kpi only when the user actually has a lead assigned. --}}
@php
    $receivedFromLead = ($leadOnlyEvals ?? $leadEvals)
        ->filter(fn($e) => !empty($e->supervisor_id))
        ->sortByDesc('period_month')
        ->values();
@endphp
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center gap-2">
        <i class="fas fa-user-tie text-indigo-400"></i>
        <h3 class="text-sm font-bold text-gray-800">Assessments From My Lead</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">No</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Template</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Month</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Score</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">HR Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($receivedFromLead as $eval)
                @php
                    $reviewed = $eval->hasSupervisorReview();
                    $approved = $eval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED;
                @endphp
                <tr class="hover:bg-gray-50/70 transition-colors">
                    <td class="px-5 py-3.5 text-gray-400 text-xs font-medium">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3.5 text-xs font-semibold text-gray-900">{{ $eval->template?->name ?? 'Lead Assessment' }}</td>
                    <td class="px-4 py-3.5 text-xs text-gray-700">{{ $eval->supervisor?->basicData?->full_name ?? 'Assigned' }}</td>
                    <td class="px-4 py-3.5 text-xs text-gray-600 whitespace-nowrap">
                        {{ \Carbon\Carbon::createFromFormat('Y-m', $eval->period_month)->format('F Y') }}
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        @if($reviewed)
                            <span class="text-sm font-bold {{ $approved ? 'text-emerald-600' : 'text-indigo-600' }}">{{ number_format($eval->overall_score ?? 0, 1) }}</span>
                            <span class="text-[11px] text-gray-400"> / 100</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">Awaiting lead</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-600 max-w-xs">
                        @if($approved && $eval->hr_notes)
                            {{ $eval->hr_notes }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="py-10 text-center text-xs text-gray-400">No lead assessments yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
