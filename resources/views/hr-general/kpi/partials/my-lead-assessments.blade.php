{{-- Assessments the current user received from their own lead (data comes from
     the lead's review on lead-type evaluations where this user is the subject).
     Included by my-kpi only when the user actually has a lead assigned. --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center gap-2">
        <i class="fas fa-user-tie text-indigo-400"></i>
        <h3 class="text-sm font-bold text-gray-800">Assessments From My Lead</h3>
    </div>

    <div class="divide-y divide-gray-100">
        @foreach($leadEvals->filter(fn($e) => !empty($e->supervisor_id))->sortByDesc('period_month') as $eval)
        @php
            $reviewed = $eval->hasSupervisorReview();
            $approved = $eval->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED;
        @endphp
        <div class="p-5 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-gray-900 text-sm">{{ $eval->template?->name ?? 'Lead Assessment' }}</span>
                        <span class="text-xs text-gray-400">·</span>
                        <span class="text-xs text-gray-500">{{ \Carbon\Carbon::createFromFormat('Y-m', $eval->period_month)->format('F Y') }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Lead: {{ $eval->supervisor?->basicData?->full_name ?? 'Assigned' }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    @if($reviewed)
                        <span class="text-2xl font-bold {{ $approved ? 'text-emerald-600' : 'text-indigo-600' }}">
                            {{ number_format($eval->overall_score ?? 0, 1) }}
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold
                        {{ $approved ? 'bg-emerald-100 text-emerald-800' : ($reviewed ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800') }}">
                        {{ $approved ? 'Approved' : ($reviewed ? 'Reviewed by Lead' : 'Awaiting Lead Review') }}
                    </span>
                </div>
            </div>

            @if($reviewed)
                @if($canSeeLeadDetails)
                {{-- HR / KPI-Evaluation users: full per-indicator breakdown --}}
                <div class="overflow-x-auto border border-gray-100 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Indicator</th>
                                <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-16">Weight</th>
                                <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">Lead Rating</th>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($eval->details->sortBy('indicator.order_seq') as $detail)
                            @php $starMax = $detail->indicator?->rating_max ?: ($eval->template?->scaleMax() ?: 5); @endphp
                            <tr>
                                <td class="px-5 py-3 font-semibold text-gray-900 text-xs">{{ $detail->indicator?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-center text-xs font-semibold text-indigo-600">{{ $detail->indicator?->weight ?? 0 }}%</td>
                                <td class="px-4 py-3 text-center">
                                    @if(!is_null($detail->supervisor_score))
                                        <div class="flex items-center justify-center gap-0.5 text-amber-400 text-xs">
                                            @for($s = 1; $s <= $starMax; $s++)
                                                <span>{{ $s <= ($detail->star_rating ?? round($detail->supervisor_score / 100 * $starMax)) ? '★' : '☆' }}</span>
                                            @endfor
                                        </div>
                                        <span class="text-[11px] text-gray-500">{{ number_format($detail->supervisor_score, 1) }}</span>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 italic">{{ $detail->supervisor_notes ?: '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                {{-- Regular employee: score + overall note only, no indicator detail --}}
                <div class="flex items-center gap-4 p-4 bg-gray-50 border border-gray-100 rounded-xl">
                    <div class="text-center">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Overall Score</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($eval->overall_score ?? 0, 1) }}<span class="text-sm text-gray-400"> / 100</span></p>
                    </div>
                </div>
                @endif

                @if($eval->general_notes)
                <div class="p-4 bg-indigo-50/60 border border-indigo-100 rounded-xl">
                    <p class="text-xs font-bold text-indigo-800 mb-0.5"><i class="fas fa-comment-alt mr-1"></i> Note from Lead</p>
                    <p class="text-xs text-indigo-900">{{ $eval->general_notes }}</p>
                </div>
                @endif

                @if($eval->hr_notes && $approved)
                <div class="p-4 bg-blue-50/70 border border-blue-100 rounded-xl">
                    <p class="text-xs font-bold text-blue-800 mb-0.5"><i class="fas fa-comment-alt mr-1"></i> HR Notes</p>
                    <p class="text-xs text-blue-900">{{ $eval->hr_notes }}</p>
                </div>
                @endif
            @else
                <p class="text-xs text-gray-400 italic">Your lead has not submitted this assessment yet.</p>
            @endif
        </div>
        @endforeach
    </div>
</div>
