@extends('dashboard')

@section('title', 'Self-Assessment — ' . ($evaluation->template?->name ?? 'KPI'))
@section('page-title', 'Self-Assessment')

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
@endphp

<div class="space-y-6">

    {{-- ── Breadcrumb & Header ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-1.5">
                <a href="{{ route('general.my-kpi.index') }}" class="hover:text-gray-600">My KPI</a>
                <i class="fas fa-chevron-right text-[10px]"></i>
                <span class="text-gray-700 font-medium">Self-Assessment</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ $evaluation->template?->name ?? 'KPI Self-Assessment' }}</h1>
            <p class="text-xs text-gray-500 mt-0.5">Evaluation Period: <strong>{{ $periodLabel }}</strong></p>
        </div>
        <a href="{{ route('general.my-kpi.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
            <i class="fas fa-arrow-left text-xs"></i> Back
        </a>
    </div>

    {{-- ── Guidelines & Locked Warning ───────────────────────────────────── --}}
    <div class="space-y-3">
        <div class="bg-amber-50 border-l-4 border-amber-500 rounded-2xl p-4 shadow-sm flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-amber-600 text-lg mt-0.5 shrink-0"></i>
            <div>
                <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider">Penting — Konfirmasi Pengiriman & Kunci Evaluasi</h4>
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                    Setelah dikirim, evaluasi mandiri (self-assessment) ini akan <strong>terkunci secara permanen dan tidak dapat diubah kembali</strong> untuk diproses dalam penilaian atasan. Mohon periksa kembali rating bintang dan catatan pencapaian Anda secara teliti.
                </p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-blue-800">Petunjuk Pengisian Evaluasi Mandiri (Self-Assessment)</p>
                <ul class="text-[11px] text-blue-700 mt-1 space-y-0.5 list-disc list-inside">
                    <li>Berikan penilaian mandiri dengan memilih <strong>Rating 1–5 bintang</strong> pada setiap indikator</li>
                    <li>Isi angka realisasi (actual) dan berikan catatan pencapaian khusus untuk memperjelas konteks</li>
                    <li>Indikator yang belum diisi akan ditandai dengan warna <span class="font-bold text-amber-700">Amber (Pilih)</span></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── Form ────────────────────────────────────────────────────────────── --}}
    <form id="selfAssessmentForm" onsubmit="submitSelfAssessment(event)">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden space-y-6">

            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list-check text-indigo-500"></i> Indikator KPI — Self-Assessment
                </h3>
                <span class="text-xs text-gray-400">
                    {{ $evaluation->details->count() }} indicators &middot; Total weight:
                    <span class="font-bold text-gray-700">{{ $evaluation->template?->indicators->sum('weight') ?? 0 }}%</span>
                </span>
            </div>

            {{-- Table --}}
            <div class="px-6">
                <div class="overflow-x-auto border border-gray-100 rounded-xl">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 uppercase w-10">NO</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 uppercase">INDIKATOR KPI</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-500 uppercase w-48">TARGET</th>
                                <th class="text-center px-3 py-3 font-semibold text-gray-500 uppercase w-16">BOBOT</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase w-36">REALISASI (ACTUAL)</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase w-48">RATING (1-5)</th>
                                <th class="text-center px-4 py-3 font-semibold text-gray-500 uppercase w-28">WEIGHTED SCORE</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($evaluation->details->sortBy('indicator.order_seq') as $i => $detail)
                            @php
                                $ind = $detail->indicator;
                                $weight = $ind?->weight ?? 0;
                                $currentRating = $detail->star_rating ?? ($detail->self_achievement ? min(5, max(1, (int)round($detail->self_achievement / 20))) : null);
                                $isUnfilled = is_null($currentRating);
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors {{ $isUnfilled ? 'bg-amber-50/20' : '' }}">
                                <td class="px-4 py-4 font-bold text-gray-400 align-top">{{ $i + 1 }}</td>
                                <td class="px-4 py-4 align-top space-y-2">
                                    <div>
                                        <p class="font-bold text-gray-900 text-xs">{{ $ind?->name ?? '—' }}</p>
                                        @if($ind?->description)
                                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $ind->description }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <input type="text" name="achievements[{{ $detail->id }}][notes]"
                                            value="{{ old("achievements.{$detail->id}.notes", $detail->self_notes) }}"
                                            placeholder="Tambahkan catatan khusus untuk indikator ini (opsional)..."
                                            class="w-full px-3 py-1.5 text-[11px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 bg-white">
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top text-gray-600 font-medium">
                                    {{ $ind?->target_value ? ($ind->target_value . ($ind->measurement_unit ? ' ' . $ind->measurement_unit : '')) : '>= 90%' }}
                                </td>
                                <td class="px-3 py-4 align-top text-center font-bold text-indigo-700">
                                    {{ $weight }}%
                                </td>
                                <td class="px-4 py-4 align-top text-center">
                                    <input type="text" name="achievements[{{ $detail->id }}][actual]"
                                        value="{{ old("achievements.{$detail->id}.actual", $detail->actual_achievement ?? $detail->self_achievement) }}"
                                        placeholder="Realisasi..."
                                        class="w-full px-2.5 py-1.5 text-xs text-center border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                                </td>
                                <td class="px-4 py-4 align-top text-center">
                                    <input type="hidden" name="achievements[{{ $detail->id }}][rating]" id="rating_val_{{ $detail->id }}" value="{{ $currentRating ?? '' }}">

                                    <div class="flex items-center justify-center gap-1 my-1">
                                        @for($star = 1; $star <= 5; $star++)
                                        <button type="button"
                                            onclick="setStarRating({{ $detail->id }}, {{ $star }}, {{ $weight }})"
                                            id="star_{{ $detail->id }}_{{ $star }}"
                                            class="star-btn text-base transition-transform hover:scale-125 focus:outline-none {{ ($currentRating && $star <= $currentRating) ? 'text-amber-400' : 'text-gray-300' }}">
                                            ★
                                        </button>
                                        @endfor
                                    </div>

                                    <span id="rating_badge_{{ $detail->id }}" class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full transition-all
                                        {{ $isUnfilled ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $currentRating ? "{$currentRating}/5" : 'Pilih (Belum Diisi)' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top text-center font-bold text-sm">
                                    <span id="weighted_score_{{ $detail->id }}" class="weighted-cell text-gray-800">
                                        {{ !is_null($detail->weighted_score) ? number_format($detail->weighted_score, 2) : '0.00' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Total Summary --}}
                <div class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-200/80 flex items-center justify-between text-xs">
                    <span class="font-bold text-gray-700">Total Bobot: 100.00%</span>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-700">Nilai Akhir Evaluasi Mandiri:</span>
                        <span id="finalScoreDisplay" class="text-lg font-bold text-indigo-700">0.00</span>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="p-5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-400">
                    <i class="fas fa-lock mr-1"></i> Self-assessment details will be submitted to your supervisor & HR.
                </p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('general.my-kpi.index') }}"
                       class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-300 transition-all">
                        Cancel
                    </a>
                    <button type="submit" id="submitSelfAssessBtn"
                        class="inline-flex items-center gap-2 px-6 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all">
                        <i class="fas fa-paper-plane text-xs"></i>
                        {{ $evaluation->hasSelfAssessment() ? 'Update Self-Assessment' : 'Kirim Self-Assessment' }}
                    </button>
                </div>
            </div>
        </div>
    </form>

</div>

{{-- ── Custom Submission Confirmation Modal ────────────────────────────── --}}
<div id="confirmSubmitModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 text-center border border-gray-100 transform transition-all scale-100">
        <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-2xl shadow-sm">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="space-y-1.5">
            <h3 class="text-base font-bold text-gray-900">Konfirmasi Kirim Self-Assessment</h3>
            <p class="text-xs text-gray-500 leading-relaxed px-2">
                Apakah Anda yakin ingin mengirim evaluasi mandiri ini? Setelah dikirim, data Anda akan <strong class="text-amber-800">terkunci secara permanen</strong> dan tidak dapat diubah kembali.
            </p>
        </div>
        <div class="flex items-center justify-center gap-3 pt-2">
            <button type="button" onclick="closeConfirmSubmitModal()"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
                Batal
            </button>
            <button type="button" onclick="executeSubmitSelfAssessment()" id="confirmSubmitModalBtn"
                class="inline-flex items-center gap-1.5 px-6 py-2.5 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all">
                <i class="fas fa-paper-plane text-xs"></i> Ya, Kirim Sekarang
            </button>
        </div>
    </div>
</div>

<script>
function setStarRating(detailId, star, weight) {
    document.getElementById(`rating_val_${detailId}`).value = star;

    for (let s = 1; s <= 5; s++) {
        const btn = document.getElementById(`star_${detailId}_${s}`);
        if (btn) {
            btn.className = s <= star
                ? 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-amber-400'
                : 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-gray-300';
        }
    }

    const badge = document.getElementById(`rating_badge_${detailId}`);
    if (badge) {
        badge.textContent = `${star}/5`;
        badge.className = 'inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100';
    }

    const score100 = star * 20;
    const weighted = (weight * score100) / 100;
    const cell = document.getElementById(`weighted_score_${detailId}`);
    if (cell) cell.textContent = weighted.toFixed(2);

    recalcTotalScore();
}

function recalcTotalScore() {
    let total = 0;
    document.querySelectorAll('.weighted-cell').forEach(cell => {
        const v = parseFloat(cell.textContent);
        if (!isNaN(v)) total += v;
    });
    const display = document.getElementById('finalScoreDisplay');
    if (display) display.textContent = total.toFixed(2);
}

function openConfirmSubmitModal() {
    document.getElementById('confirmSubmitModal').classList.remove('hidden');
}
function closeConfirmSubmitModal() {
    document.getElementById('confirmSubmitModal').classList.add('hidden');
}

function submitSelfAssessment(e) {
    e.preventDefault();
    openConfirmSubmitModal();
}

async function executeSubmitSelfAssessment() {
    closeConfirmSubmitModal();

    const btn = document.getElementById('submitSelfAssessBtn');
    btn.disabled = true; btn.innerHTML = `<i class="fas fa-circle-notch fa-spin text-xs"></i> Submitting...`;

    try {
        const res = await fetch('{{ route("general.my-kpi.self-assessment.submit", $evaluation->id) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: new FormData(document.getElementById('selfAssessmentForm')),
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Self-assessment submitted successfully!', 'success');
            setTimeout(() => { window.location.href = '{{ route("general.my-kpi.index") }}'; }, 1000);
        } else {
            showToast(data.message || 'Failed to submit self-assessment.', 'error');
            btn.disabled = false; btn.innerHTML = `<i class="fas fa-paper-plane text-xs"></i> Kirim Self-Assessment`;
        }
    } catch (err) {
        showToast('An error occurred. Please try again.', 'error');
        btn.disabled = false; btn.innerHTML = `<i class="fas fa-paper-plane text-xs"></i> Kirim Self-Assessment`;
    }
}
</script>
@endsection
