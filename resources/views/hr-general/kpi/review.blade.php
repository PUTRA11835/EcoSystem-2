@extends('dashboard')

@section('title', 'KPI Evaluation Review — ' . ($evaluation->employee?->basicData?->full_name ?? 'Employee'))
@section('page-title', 'KPI Review')

@section('content')
@php
    use Carbon\Carbon;
    $user  = session('user');
    $emp   = $evaluation->employee;
    $bd    = $emp?->basicData;
    $supBd = $evaluation->supervisor?->basicData;
    $periodObj = Carbon::createFromFormat('Y-m', $evaluation->period_month);
    $periodLabel = $periodObj->format('F Y');
    $isApproved = $evaluation->status === \App\Models\KpiEvaluation::STATUS_HR_APPROVED;
    $isSelf = (int)($user['id'] ?? 0) === (int)$evaluation->employee_id && empty($user['is_admin']);
    $isReadOnly = $isApproved || $isSelf;
    $canReviewNow = $can('general.kpi-evaluation.review') && !$isReadOnly;
    $canApproveNow = $canApprove && $evaluation->isReadyForApproval() && !$isApproved && !$isSelf;
@endphp

<div class="space-y-6">

    {{-- ── Page Header ─────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">
                {{ $isReadOnly ? 'KPI Evaluation Detail' : ($evaluation->status === 'draft' ? 'Continue KPI Draft' : 'KPI Supervisor Review') }}
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ $isReadOnly ? 'Review scorecard and final approval status.' : 'Continue evaluation for the selected period and assign scores.' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($isSelf)
            <a href="{{ route('general.my-kpi.index', ['period' => $evaluation->period_month]) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
                <i class="fas fa-chevron-left text-xs"></i> Back to My KPI
            </a>
            @else
            <a href="{{ route('general.kpi-evaluation.index', ['period' => $evaluation->period_month]) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
                <i class="fas fa-chevron-left text-xs"></i> Back to KPI Dashboard
            </a>
            @endif
            @if($canApproveNow)
            <button onclick="openApproveModal()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow hover:bg-emerald-700 transition-all">
                <i class="fas fa-check-circle text-xs"></i> Approve Evaluation
            </button>
            @endif
        </div>
    </div>

    {{-- ── Section 1: Employee & Template Info (Screenshot 3 & 4) ──────────── --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-id-card text-indigo-500"></i> Detail Karyawan
            </h3>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border
                {{ $isApproved ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                {{ $evaluation->status_label }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-gray-400 font-medium block">NAMA KARYAWAN</span>
                <span class="font-bold text-gray-900 text-sm mt-0.5 block">{{ $bd?->full_name ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">NO. KARYAWAN</span>
                <span class="font-mono text-red-500 font-bold mt-0.5 block">{{ $emp?->eci ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">POSISI</span>
                <span class="font-semibold text-gray-700 mt-0.5 block">{{ $bd?->position ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">DEPARTEMEN</span>
                <span class="font-semibold text-gray-700 mt-0.5 block">{{ $bd?->department ?? '—' }}</span>
            </div>
        </div>

        <div class="pt-3 border-t border-gray-100">
            <span class="text-xs text-gray-400 font-medium block mb-1">TEMPLATE KPI</span>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-indigo-700 text-sm">{{ $evaluation->template?->name ?? '—' }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-600 border border-indigo-100">
                    Evaluator Role: {{ $evaluation->template?->target_type_label ?? 'Penilaian Atasan' }}
                </span>
            </div>
            @if($evaluation->template?->description)
            <p class="text-xs text-gray-500 mt-1 italic">{{ $evaluation->template->description }}</p>
            @endif
        </div>
    </div>

    {{-- ── Section 2: Analisa Timesheet Widget (Screenshot 3) ──────────────── --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-clock text-blue-500"></i> Analisa Timesheet
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Basis data KPI dari aktivitas timesheet pegawai pada periode {{ $periodLabel }}.</p>
            </div>
            <button type="button" onclick="showToast('Analisa AI sedang memproses data timesheet...', 'info')"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-xl shadow-sm hover:bg-slate-800 transition-all">
                <i class="fas fa-wand-magic-sparkles text-xs"></i> Analisa AI
            </button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-gray-50/70 rounded-xl p-4 border border-gray-200/60">
                <span class="text-xs font-medium text-gray-500 block">Total Jam</span>
                <span class="text-2xl font-bold text-blue-600 mt-1 block">168,00</span>
            </div>
            <div class="bg-gray-50/70 rounded-xl p-4 border border-gray-200/60">
                <span class="text-xs font-medium text-gray-500 block">Lembur</span>
                <span class="text-2xl font-bold text-indigo-600 mt-1 block">12,00</span>
            </div>
            <div class="bg-gray-50/70 rounded-xl p-4 border border-gray-200/60">
                <span class="text-xs font-medium text-gray-500 block">Hari Kerja Tercatat</span>
                <span class="text-2xl font-bold text-gray-800 mt-1 block">21</span>
            </div>
            <div class="bg-gray-50/70 rounded-xl p-4 border border-gray-200/60">
                <span class="text-xs font-medium text-gray-500 block">Rata-rata Jam / Hari</span>
                <span class="text-2xl font-bold text-emerald-600 mt-1 block">8,00</span>
            </div>
        </div>
    </div>

    {{-- ── Section 3: Scoring Form & Rating 1-5 (Screenshot 4 & 5) ───────────── --}}
    <form id="kpiReviewForm" onsubmit="submitKpiReview(event)">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden space-y-6">

            {{-- Period & General Notes --}}
            <div class="p-6 border-b border-gray-100 space-y-4">
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-amber-500"></i> Periode Penilaian & Catatan Evaluasi Umum
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">TIPE PERIODE</label>
                        <input type="text" readonly value="{{ $evaluation->template?->period_type_label ?? 'Bulanan (Monthly)' }}"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl bg-gray-50 font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">NAMA PERIODE</label>
                        <input type="text" readonly value="{{ $periodObj->format('F') }}"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl bg-gray-50 font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">TAHUN</label>
                        <input type="text" readonly value="{{ $periodObj->format('Y') }}"
                            class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl bg-gray-50 font-medium">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">CATATAN EVALUASI UMUM</label>
                    <textarea name="general_notes" rows="2" {{ $isReadOnly ? 'readonly' : '' }}
                        placeholder="Ulasan umum kinerja karyawan selama periode ini..."
                        class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-400 resize-none">{{ old('general_notes', $evaluation->general_notes) }}</textarea>
                </div>
            </div>

            {{-- Indicator Scoring Table --}}
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-star-half-alt text-yellow-500"></i> Formulir Pengisian Skor Indikator
                    </h3>
                    <span class="text-xs text-gray-400">
                        Select <strong>1–5 stars</strong> for rating. Unfilled indicators are highlighted in <span class="text-amber-600 font-bold">amber</span>.
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50/80 border-b border-gray-200">
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
                        <tbody class="divide-y divide-gray-100" id="indicatorRows">
                            @foreach($evaluation->details->sortBy('indicator.order_seq') as $i => $detail)
                            @php
                                $ind = $detail->indicator;
                                $weight = $ind?->weight ?? 0;
                                $currentRating = $detail->star_rating ?? ($detail->supervisor_score ? min(5, max(1, (int)round($detail->supervisor_score / 20))) : null);
                                $isUnfilled = is_null($currentRating);
                            @endphp
                            <tr class="indicator-tr hover:bg-gray-50/50 transition-colors {{ $isUnfilled ? 'bg-amber-50/20' : '' }}" data-weight="{{ $weight }}">
                                <td class="px-4 py-4 font-bold text-gray-400 align-top">{{ $i + 1 }}</td>
                                <td class="px-4 py-4 align-top space-y-2">
                                    <div>
                                        <p class="font-bold text-gray-900 text-xs">{{ $ind?->name ?? '—' }}</p>
                                        @if($ind?->description)
                                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $ind->description }}</p>
                                        @endif
                                    </div>
                                    {{-- Full-width notes text input directly under indicator name --}}
                                    <div>
                                        <input type="text" name="scores[{{ $detail->id }}][notes]"
                                            value="{{ old("scores.{$detail->id}.notes", $detail->supervisor_notes) }}"
                                            {{ $isReadOnly ? 'readonly' : '' }}
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
                                    <input type="text" name="scores[{{ $detail->id }}][actual]"
                                        value="{{ old("scores.{$detail->id}.actual", $detail->actual_achievement ?? $detail->self_achievement) }}"
                                        {{ $isReadOnly ? 'readonly' : '' }}
                                        placeholder="Realisasi..."
                                        class="w-full px-2.5 py-1.5 text-xs text-center border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                                </td>
                                <td class="px-4 py-4 align-top text-center">
                                    <input type="hidden" name="scores[{{ $detail->id }}][rating]" id="rating_val_{{ $detail->id }}" value="{{ $currentRating ?? '' }}">

                                    <div class="flex items-center justify-center gap-1 my-1">
                                        @for($star = 1; $star <= 5; $star++)
                                        <button type="button"
                                            {{ $isReadOnly ? 'disabled' : '' }}
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

                {{-- Bottom Summary Bar (Screenshot 4 & 5) --}}
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-700">Total Bobot:</span>
                        <span class="font-bold text-indigo-700 text-sm">100.00%</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-gray-700">Nilai Akhir Evaluasi:</span>
                        <span id="finalScoreDisplay" class="text-xl font-bold text-gray-900">
                            {{ !is_null($evaluation->overall_score) ? number_format($evaluation->overall_score, 2) : '0.00' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Action Footer Buttons --}}
            @if(!$isReadOnly)
            <div class="p-5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <a href="{{ route('general.kpi-evaluation.index', ['period' => $evaluation->period_month]) }}"
                   class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-300 transition-all">
                    Batal
                </a>
                <div class="flex items-center gap-2">
                    <button type="submit" name="action" value="draft"
                        class="px-5 py-2 bg-white text-indigo-600 border border-indigo-200 text-xs font-bold rounded-xl shadow-sm hover:bg-indigo-50 transition-all">
                        Simpan Draft
                    </button>
                    <button type="submit" name="action" value="submit"
                        class="px-6 py-2 primary-gradient text-white text-xs font-bold rounded-xl shadow hover:opacity-90 transition-all">
                        <i class="fas fa-paper-plane text-xs mr-1"></i> Kirim Ke HR
                    </button>
                </div>
            </div>
            @endif

        </div>
    </form>

</div>

{{-- Approve Modal --}}
<div id="approveModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 space-y-4">
        <h3 class="text-base font-bold text-gray-900">Approve Evaluation</h3>
        <p class="text-sm text-gray-600">Once approved, <strong>{{ $bd?->full_name ?? 'the employee' }}</strong> will be able to view their final KPI scorecard.</p>
        <textarea id="approveNotes" rows="3" placeholder="HR notes (optional)..."
            class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-300 resize-none"></textarea>
        <div class="flex items-center justify-end gap-3">
            <button onclick="document.getElementById('approveModal').classList.add('hidden')" class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl">Cancel</button>
            <button onclick="confirmApprove()" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl shadow hover:bg-emerald-700">
                <i class="fas fa-check text-xs"></i> Confirm Approval
            </button>
        </div>
    </div>
</div>

<script>
function setStarRating(detailId, star, weight) {
    document.getElementById(`rating_val_${detailId}`).value = star;

    // Update star visual state
    for (let s = 1; s <= 5; s++) {
        const btn = document.getElementById(`star_${detailId}_${s}`);
        if (btn) {
            if (s <= star) {
                btn.className = 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-amber-400';
            } else {
                btn.className = 'star-btn text-base transition-transform hover:scale-125 focus:outline-none text-gray-300';
            }
        }
    }

    // Update rating badge (removes amber "Pilih" highlight)
    const badge = document.getElementById(`rating_badge_${detailId}`);
    if (badge) {
        badge.textContent = `${star}/5`;
        badge.className = 'inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100';
    }

    // Calculate weighted score: (star / 5 * 100) * weight / 100 => (star * 20) * weight / 100
    const score100 = star * 20;
    const weighted = (weight * score100) / 100;
    const cell = document.getElementById(`weighted_score_${detailId}`);
    if (cell) {
        cell.textContent = weighted.toFixed(2);
    }

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

async function submitKpiReview(e) {
    e.preventDefault();
    const form = e.target;
    const res  = await fetch('{{ route("general.kpi-evaluation.review.submit", $evaluation->id) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: new FormData(form),
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
}

function openApproveModal() { document.getElementById('approveModal').classList.remove('hidden'); }
async function confirmApprove() {
    const notes = document.getElementById('approveNotes').value;
    const form  = new FormData(); form.append('hr_notes', notes);
    const res   = await fetch('{{ route("general.kpi-evaluation.approve", $evaluation->id) }}', {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: form,
    });
    const data = await res.json();
    document.getElementById('approveModal').classList.add('hidden');
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
}
</script>
@endsection
