@extends('dashboard')

@section('title', ($evaluation->isUpwardType() ? 'Upward Assessment' : 'Self-Assessment') . ' — ' . ($evaluation->template?->name ?? 'KPI'))
@section('page-title', $evaluation->isUpwardType() ? 'Upward Assessment' : 'Self-Assessment')

@section('content')
@php
    use Carbon\Carbon;
    $user   = session('user');
    $emp    = $evaluation->employee;
    $bd     = $emp?->basicData;
    $supBd  = $evaluation->supervisor?->basicData;
    $periodObj = Carbon::createFromFormat('Y-m', $evaluation->period_month);
    $periodLabel = $periodObj->format('F Y');
    $isApproved = $evaluation->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED;
    $isUpward = $evaluation->isUpwardType();
    // View-only once submitted, or once HR approves.
    $locked = $locked ?? ($evaluation->hasSelfAssessment() || $isApproved);
    $scaleMax  = $evaluation->template?->scaleMax() ?: 5;
    $scaleRows = $evaluation->template ? $evaluation->template->scaleRows() : collect();
@endphp

<div class="space-y-6">

    {{-- ── Breadcrumb & Header ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-1.5">
                <a href="{{ route('general.my-kpi.index') }}" class="hover:text-gray-600">My KPI</a>
                <i class="fas fa-chevron-right text-[10px]"></i>
                <span class="text-gray-700 font-medium">{{ $isUpward ? 'Upward Assessment' : 'Self-Assessment' }}</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ $evaluation->template?->name ?? 'KPI Self-Assessment' }}</h1>
            <p class="text-xs text-gray-500 mt-0.5">Evaluation Period: <strong>{{ $periodLabel }}</strong></p>
            @if($isUpward)
            <p class="text-xs text-gray-500 mt-0.5">Evaluating: <strong>{{ $supBd?->full_name ?? 'your supervisor' }}</strong></p>
            @endif
        </div>
        <a href="{{ route('general.my-kpi.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
            <i class="fas fa-arrow-left text-xs"></i> Back
        </a>
    </div>

    {{-- ── Guidelines & Locked Warning ───────────────────────────────────── --}}
    <div class="space-y-3">
        @if($isUpward)
        <div class="bg-indigo-700 text-white rounded-2xl p-4 shadow-sm flex items-start gap-3">
            <i class="fas fa-arrow-up text-lg mt-0.5 shrink-0 text-indigo-200"></i>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-100">Upward Assessment — You Are Scoring Your Supervisor</p>
                <p class="text-xs text-indigo-100 mt-1">
                    Every rating and note below is about
                    <strong>{{ $supBd?->full_name ?? 'your supervisor' }}</strong>, not about you. This is not a self-assessment of your own performance.
                </p>
            </div>
        </div>
        @endif
        @if($evaluation->is_anonymous)
        <div class="bg-slate-800 text-white rounded-2xl p-4 shadow-sm flex items-start gap-3">
            <i class="fas fa-user-secret text-lg mt-0.5 shrink-0 text-slate-300"></i>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-200">Anonymous Evaluation</p>
                <p class="text-xs text-slate-300 mt-1">This evaluation is anonymous; please evaluate honestly. Your responses are never shown individually to the person you're evaluating — HR only shares the average score.</p>
            </div>
        </div>
        @endif
        @if($evaluation->status === \App\Models\KpiEvaluation::STATUS_HR_REJECTED && !$locked)
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm flex items-start gap-3">
            <i class="fas fa-rotate-left text-red-600 text-lg mt-0.5 shrink-0"></i>
            <div>
                <h4 class="text-xs font-bold text-red-900 uppercase tracking-wider">HR asked for a revision</h4>
                @if($evaluation->hr_notes)
                <p class="text-xs text-red-800 mt-1 leading-relaxed"><span class="font-semibold">HR's note:</span> {{ $evaluation->hr_notes }}</p>
                @endif
                <p class="text-xs text-red-800 mt-1">Update your answers below and submit again. Your previous answers are kept.</p>
            </div>
        </div>
        @endif
        @if($locked)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 shadow-sm flex items-start gap-3">
            <i class="fas fa-lock text-amber-600 text-lg mt-0.5 shrink-0"></i>
            <div>
                <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider">
                    {{ $isUpward ? 'Your upward assessment has been submitted and locked' : 'Your self-assessment has been submitted and locked' }}
                </h4>
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                    You can still review your answers below, but they <strong>can no longer be changed</strong>.
                </p>
            </div>
        </div>
        @else
        <div class="bg-amber-50 border-l-4 border-amber-500 rounded-2xl p-4 shadow-sm flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-amber-600 text-lg mt-0.5 shrink-0"></i>
            <div>
                <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider">Important — Submission Locks Your Evaluation</h4>
                @if($isUpward)
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                    Once submitted, your upward assessment of <strong>{{ $supBd?->full_name ?? 'your supervisor' }}</strong> is <strong>locked permanently and cannot be changed</strong>. Your score is combined with the other raters, and only the average is shown to your supervisor after HR approves it. Please double-check your star ratings and notes.
                </p>
                @else
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                    Once submitted, this self-assessment is <strong>locked permanently and cannot be changed</strong>, so it can be used in your lead's assessment. Please double-check your star ratings and achievement notes.
                </p>
                @endif
            </div>
        </div>
        @endif

        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-blue-800">
                    {{ $isUpward ? 'How to Fill In the Upward Assessment' : 'How to Fill In the Self-Assessment' }}
                </p>
                <ul class="text-[11px] text-blue-700 mt-1 space-y-0.5 list-disc list-inside">
                    @if($isUpward)
                    <li>Give <strong>{{ $supBd?->full_name ?? 'your supervisor' }}</strong> a star rating on each indicator, <strong>following the "Rating Scale" table above</strong></li>
                    @else
                    <li>Give a star rating on each indicator, <strong>following the "Rating Scale" table above</strong></li>
                    @endif
                    <li>Fill in the actual result and notes if needed (optional) — they only explain the score and do not add to it</li>
                    <li>Indicator score = (stars &divide; max scale) &times; weight. Indicators not yet filled are highlighted in <span class="font-bold text-amber-700">Amber</span></li>
                    <li>The submit button only becomes active once every rating and text answer is filled (actual result is optional)</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── Scoring Scale reference (read-only) ───────────────────────────────── --}}
    @if($scaleRows->isNotEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 bg-amber-50/60 border-b border-amber-100 flex items-center gap-2">
            <i class="fas fa-table-list text-amber-600 text-xs"></i>
            <h3 class="text-xs font-bold text-amber-900 uppercase tracking-wider">Rating Scale</h3>
            <span class="text-[11px] text-amber-700">Weighted Score = Score &divide; {{ $scaleMax }} &times; Weight</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs table-fixed min-w-200 text-center">
                <colgroup>
                    <col style="width:8%">
                    <col style="width:16%">
                    <col style="width:28%">
                    <col style="width:16%">
                    <col style="width:32%">
                </colgroup>
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Scale</th>
                        <th class="px-4 py-2.5 font-semibold">Category</th>
                        <th class="px-4 py-2.5 font-semibold">Definition</th>
                        <th class="px-4 py-2.5 font-semibold">Achievement</th>
                        <th class="px-4 py-2.5 font-semibold">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($scaleRows as $sc)
                    <tr>
                        <td class="px-4 py-2.5 font-bold text-indigo-700">{{ $sc->scale_value }}</td>
                        <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $sc->category }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $sc->definition }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $sc->achievement_label }}</td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $sc->description }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Form ────────────────────────────────────────────────────────────── --}}
    <form id="selfAssessmentForm" onsubmit="submitSelfAssessment(event)">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden space-y-6">

            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list-check text-indigo-500"></i> KPI Indicators — {{ $isUpward ? 'Upward Assessment' : 'Self-Assessment' }}
                </h3>
                <span class="text-xs text-gray-400">
                    {{ $evaluation->details->count() }} indicators &middot; Total weight:
                    <span class="font-bold text-gray-700">{{ $evaluation->template?->indicators->sum('weight') ?? 0 }}%</span>
                </span>
            </div>

            {{-- Table --}}
            <div class="px-6">
                <div class="overflow-x-auto border border-gray-100 rounded-xl">
                    {{-- Fixed 50 / 50 split: NO + INDIKATOR KPI = 50%, BOBOT + REALISASI + RATING = 50% --}}
                    <table class="w-full text-xs table-fixed min-w-200">
                        <colgroup>
                            <col style="width:5%">
                            <col style="width:45%">
                            <col style="width:8%">
                            <col style="width:26%">
                            <col style="width:16%">
                        </colgroup>
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 uppercase">NO</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 uppercase">KPI INDICATOR</th>
                                <th class="text-center px-3 py-3 font-semibold text-gray-500 uppercase">WEIGHT</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase">ACTUAL</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase">RATING</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($evaluation->details->sortBy('indicator.order_seq') as $i => $detail)
                            @php
                                $ind = $detail->indicator;
                                $isPara = $ind && $ind->isParagraph();
                                $max = $ind?->rating_max ?: $scaleMax;
                                $weight = $ind?->weight ?? 0;
                                $currentRating = $detail->star_rating ?? ($detail->self_achievement ? min($max, max(1, (int) round($detail->self_achievement / 100 * $max))) : null);
                                // A text-answer indicator counts as filled once its text is written.
                                $isUnfilled = $isPara ? trim((string) old("achievements.{$detail->id}.notes", $detail->self_notes)) === '' : is_null($currentRating);
                            @endphp
                            <tr class="ind-row hover:bg-gray-50/50 transition-colors {{ $isUnfilled ? 'bg-amber-50/20' : '' }}">
                                <td class="px-4 py-4 font-bold text-gray-400 align-top">{{ $i + 1 }}</td>
                                <td class="px-4 py-4 align-top space-y-2 {{ $isPara ? '' : '' }}" @if($isPara) colspan="1" @endif>
                                    <div>
                                        <p class="font-bold text-gray-900 text-xs">
                                            {{ $ind?->name ?? '—' }}
                                            @if($isPara)<span class="ml-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 text-[10px] font-semibold">Text answer</span>@endif
                                        </p>
                                        @if($ind?->description)
                                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $ind->description }}</p>
                                        @endif
                                    </div>
                                    @if($isPara)
                                    <textarea name="achievements[{{ $detail->id }}][notes]" rows="3"
                                        {{ $locked ? 'readonly' : '' }}
                                        placeholder="Write your answer..."
                                        oninput="updateSubmitState()"
                                        class="req-field w-full px-3 py-2 text-[11px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 resize-y {{ $locked ? 'bg-gray-50 text-gray-500' : 'bg-white' }}">{{ old("achievements.{$detail->id}.notes", $detail->self_notes) }}</textarea>
                                    @else
                                    <input type="text" name="achievements[{{ $detail->id }}][notes]"
                                        value="{{ old("achievements.{$detail->id}.notes", $detail->self_notes) }}"
                                        {{ $locked ? 'readonly' : '' }}
                                        placeholder="Add a note for this indicator (optional)..."
                                        class="w-full px-3 py-1.5 text-[11px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 {{ $locked ? 'bg-gray-50 text-gray-500' : 'bg-white' }}">
                                    @endif
                                </td>
                                @if($isPara)
                                <td colspan="3" class="px-4 py-4 align-top text-center text-[11px] text-gray-300 italic">Text answer — not scored</td>
                                @else
                                <td class="px-3 py-4 align-top text-center font-bold text-indigo-700">
                                    {{ rtrim(rtrim(number_format($weight, 2), '0'), '.') }}%
                                </td>
                                <td class="px-4 py-4 align-top text-center">
                                    <textarea name="achievements[{{ $detail->id }}][actual]" rows="3" maxlength="255"
                                        {{ $locked ? 'readonly' : '' }}
                                        placeholder="Enter the actual result..."
                                        class="w-full min-h-18 px-3 py-2 text-sm text-left border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-400 resize-y {{ $locked ? 'bg-gray-50 text-gray-600' : 'bg-white' }}">{{ old("achievements.{$detail->id}.actual", $detail->actual_achievement) }}</textarea>
                                </td>
                                <td class="px-4 py-4 align-top text-center">
                                    <input type="hidden" name="achievements[{{ $detail->id }}][rating]" id="rating_val_{{ $detail->id }}" class="rating-val" data-weight="{{ $weight }}" data-max="{{ $max }}" value="{{ $currentRating ?? '' }}">

                                    <div id="stars_{{ $detail->id }}" class="flex items-center justify-center gap-1 my-1 flex-wrap">
                                        @for($star = 1; $star <= $max; $star++)
                                        <button type="button"
                                            {{ $locked ? 'disabled' : '' }}
                                            onclick="setStarRating({{ $detail->id }}, {{ $star }}, {{ $weight }}, {{ $max }})"
                                            class="star-btn text-base transition-transform focus:outline-none {{ $locked ? 'cursor-not-allowed' : 'hover:scale-125' }} {{ ($currentRating && $star <= $currentRating) ? 'text-amber-400' : 'text-gray-300' }}">
                                            ★
                                        </button>
                                        @endfor
                                    </div>

                                    <span id="rating_badge_{{ $detail->id }}" class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full transition-all
                                        {{ $isUnfilled ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $currentRating ? "{$currentRating}/{$max}" : 'Select (Not filled)' }}
                                    </span>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Total Summary --}}
                <div class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-200/80 flex items-center justify-between text-xs">
                    <span class="font-bold text-gray-700">Total Weight: 100.00%</span>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-700">Final Self-Assessment Score:</span>
                        <span id="finalScoreDisplay" class="text-lg font-bold text-indigo-700">{{ !is_null($evaluation->overall_score) ? number_format($evaluation->overall_score, 2) : '0.00' }}</span>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="p-5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-400">
                    <i class="fas fa-lock mr-1"></i>
                    @if($locked)
                        {{ $isUpward ? 'This upward assessment is locked. You are viewing a read-only copy.' : 'This self-assessment is locked. You are viewing a read-only copy.' }}
                    @else
                        {{ $isUpward
                            ? 'This score is about ' . ($supBd?->full_name ?? 'your supervisor') . '. HR reviews it and only shares the averaged, anonymous result with them.'
                            : 'Self-assessment details will be submitted to your supervisor & HR.' }}
                    @endif
                </p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('general.my-kpi.index') }}"
                       class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-300 transition-all">
                        {{ $locked ? 'Back to My KPI' : 'Cancel' }}
                    </a>
                    @unless($locked)
                    <span id="incompleteHint" class="hidden text-[11px] font-semibold text-amber-700">
                        <i class="fas fa-circle-exclamation mr-1"></i><span id="incompleteCount"></span> not filled
                    </span>
                    <button type="submit" id="submitSelfAssessBtn" disabled
                        class="inline-flex items-center gap-2 px-6 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:opacity-40">
                        <i class="fas fa-paper-plane text-xs"></i>
                        {{ $isUpward ? 'Submit Upward Assessment' : 'Submit Self-Assessment' }}
                    </button>
                    @endunless
                </div>
            </div>
        </div>
    </form>

</div>

{{-- ── Custom Submission Confirmation Modal ────────────────────────────── --}}
<div id="confirmSubmitModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 text-center border border-gray-100 transform transition-all scale-100">
        <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-2xl shadow-sm">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="space-y-1.5">
            <h3 class="text-base font-bold text-gray-900">{{ $isUpward ? 'Confirm Upward Assessment Submission' : 'Confirm Self-Assessment Submission' }}</h3>
            <p class="text-xs text-gray-500 leading-relaxed px-2">
                @if($isUpward)
                    Are you sure you want to submit this assessment of <strong>{{ $supBd?->full_name ?? 'your supervisor' }}</strong>? Once submitted, it will be <strong class="text-amber-800">locked permanently</strong> and cannot be changed.
                @else
                    Are you sure you want to submit this self-assessment? Once submitted, your answers will be <strong class="text-amber-800">locked permanently</strong> and cannot be changed.
                @endif
            </p>
        </div>
        <div class="flex items-center justify-center gap-3 pt-2">
            <button type="button" onclick="closeConfirmSubmitModal()"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
                Cancel
            </button>
            <button type="button" onclick="executeSubmitSelfAssessment()" id="confirmSubmitModalBtn"
                class="inline-flex items-center gap-1.5 px-6 py-2.5 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all">
                <i class="fas fa-paper-plane text-xs"></i> Yes, Send Now
            </button>
        </div>
    </div>
</div>

<script>
function setStarRating(detailId, star, weight, max) {
    max = max || 5;
    document.getElementById(`rating_val_${detailId}`).value = star;

    const box = document.getElementById(`stars_${detailId}`);
    if (box) box.querySelectorAll('.star-btn').forEach((btn, idx) => {
        btn.className = (idx + 1) <= star
            ? 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-amber-400'
            : 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-gray-300';
    });

    const badge = document.getElementById(`rating_badge_${detailId}`);
    if (badge) {
        badge.textContent = `${star}/${max}`;
        badge.className = 'inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100';
    }

    recalcTotalScore();
    updateSubmitState();
}

// Final score = sum of (stars ÷ scale max × weight) over every rated indicator.
function recalcTotalScore() {
    let total = 0;
    document.querySelectorAll('.rating-val').forEach(inp => {
        const stars = parseFloat(inp.value);
        if (isNaN(stars)) return;
        total += stars / parseFloat(inp.dataset.max) * parseFloat(inp.dataset.weight);
    });
    const display = document.getElementById('finalScoreDisplay');
    if (display) display.textContent = total.toFixed(2);
}

// Submit stays disabled until every indicator is complete: rating picked (scale
// rows) or the uraian answer filled (paragraph rows). Realisasi is optional. The counter is
// per indicator (question), not per field, so 30 questions never reads as 58.
// Realisasi and catatan (notes) remain optional.
function updateSubmitState() {
    const btn = document.getElementById('submitSelfAssessBtn');
    if (!btn) return;
    let missing = 0;
    document.querySelectorAll('tr.ind-row').forEach(tr => {
        const ratingMissing = [...tr.querySelectorAll('.rating-val')].some(i => i.value === '');
        const textMissing   = [...tr.querySelectorAll('.req-field')].some(el => el.value.trim() === '');
        const rowMissing = ratingMissing || textMissing;
        if (rowMissing) missing++;
        tr.classList.toggle('bg-amber-50/20', rowMissing); // amber = indicator not filled yet
    });
    btn.disabled = missing > 0;
    const hint = document.getElementById('incompleteHint');
    if (hint) {
        hint.classList.toggle('hidden', missing === 0);
        document.getElementById('incompleteCount').textContent = `${missing} indicator(s)`;
    }
}
document.addEventListener('DOMContentLoaded', updateSubmitState);

// The modal wrapper stays 'flex' only while visible — kept off the static
// class list (and toggled in lockstep with 'hidden' here) so the two never
// sit on the element at the same time.
function openConfirmSubmitModal() {
    const el = document.getElementById('confirmSubmitModal');
    el.classList.remove('hidden');
    el.classList.add('flex');
}
function closeConfirmSubmitModal() {
    const el = document.getElementById('confirmSubmitModal');
    el.classList.add('hidden');
    el.classList.remove('flex');
}

function submitSelfAssessment(e) {
    e.preventDefault();
    updateSubmitState();
    if (document.getElementById('submitSelfAssessBtn')?.disabled) return;
    openConfirmSubmitModal();
}

async function executeSubmitSelfAssessment() {
    closeConfirmSubmitModal();

    const isUpward = {{ $isUpward ? 'true' : 'false' }};
    const submitLabel = isUpward ? 'Submit Upward Assessment' : 'Submit Self-Assessment';
    const btn = document.getElementById('submitSelfAssessBtn');
    btn.disabled = true; btn.innerHTML = `<i class="fas fa-circle-notch fa-spin text-xs"></i> Submitting...`;

    try {
        const res = await fetch('{{ $isUpward ? route("general.my-kpi.upward-assessment.submit", $evaluation->id) : route("general.my-kpi.self-assessment.submit", $evaluation->id) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: new FormData(document.getElementById('selfAssessmentForm')),
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || (isUpward ? 'Upward assessment submitted successfully!' : 'Self-assessment submitted successfully!'), 'success');
            setTimeout(() => { window.location.href = '{{ route("general.my-kpi.index") }}'; }, 1000);
        } else {
            showToast(data.message || (isUpward ? 'Failed to submit upward assessment.' : 'Failed to submit self-assessment.'), 'error');
            btn.disabled = false; btn.innerHTML = `<i class="fas fa-paper-plane text-xs"></i> ${submitLabel}`;
        }
    } catch (err) {
        showToast('An error occurred. Please try again.', 'error');
        btn.disabled = false; btn.innerHTML = `<i class="fas fa-paper-plane text-xs"></i> ${submitLabel}`;
    }
}
</script>
@endsection
