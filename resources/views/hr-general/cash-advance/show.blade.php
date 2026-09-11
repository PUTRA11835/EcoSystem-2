@extends('dashboard')

@section('title', 'Cash Advance Details')
@section('page-title', 'Cash Advance Details')
@section('page-subtitle', 'Cash advance / petty cash request detail')

@section('content')
@php
    use App\Models\CashAdvance\CashAdvance;
    use App\Models\CashAdvance\CashAdvanceApproval;

    $amounts = app(\App\Services\CashAdvance\CashAdvanceAmountService::class);

    /*
     | 🔴 Variabel di bawah OPSIONAL, dan itu disengaja.
     |
     | Halaman ini dipakai DUA sisi: karyawan (A4) dan HR (A5). Sisi karyawan
     | tidak mengirim tombol putusan; sisi HR mengirimnya. Membuat dua berkas
     | detail yang mirip akan menyimpang cepat atau lambat — dan yang menyimpang
     | di sini adalah tampilan dokumen keuangan.
     |
     | Pola yang sama sudah teruji di Purchase Request: `show.blade.php` tidak
     | dibuat ulang saat langkah P5, sisi HR tinggal mengisi variabelnya.
     */
    $canApprove   = $canApprove   ?? false;
    $approveRoute = $approveRoute ?? null;
    $rejectRoute  = $rejectRoute  ?? null;
    $editRoute    = $editRoute    ?? null;
    $deleteRoute  = $deleteRoute  ?? null;
    $cancelRoute  = $cancelRoute  ?? null;
    $exportRoute  = $exportRoute  ?? null;
    $canCreateReport = $canCreateReport ?? false;
    $reportRoute     = $reportRoute     ?? null;

    // Sebab penolakan, kalimat utuh dari CashAdvanceReportService (D154).
    $reportBlockedReason = $reportBlockedReason ?? null;
@endphp

<div class="space-y-5">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3">
            <p class="text-sm text-green-900">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <p class="text-sm text-red-900">{{ session('error') }}</p>
        </div>
    @endif

    <div class="flex flex-wrap justify-end gap-2">
        @if($editRoute)
            <a href="{{ $editRoute }}"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                Edit
            </a>
        @endif
        @if($exportRoute)
            <a href="{{ $exportRoute }}"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                Export Excel
            </a>
        @endif

        <a href="{{ $printRoute }}" target="_blank"
           class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
            Print CA Form
        </a>
        <a href="{{ $backRoute }}"
           class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            Back to List
        </a>
    </div>

    {{-- ── Hapus beralasan (Keputusan D109) ────────────────────────────────
         🔴 Alasannya WAJIB, dan kolomnya ada di layar — bukan field tersembunyi
         yang diisi JavaScript. Menyiapkan `delete_reason` tanpa cara mengisinya
         adalah cacat yang menunggu terjadi: server menolak, pengguna tidak tahu
         kenapa. Dokumen keuangan tidak hilang tanpa jejak; ini hanya
         menyembunyikannya dari daftar, dan alasannya ikut tercetak di ekspor.
         ──────────────────────────────────────────────────────────────────── --}}
    @if($deleteRoute)
        <div class="bg-white rounded-xl shadow-sm">
            <button type="button" id="caDeleteToggle"
                    class="w-full text-left px-5 py-3 text-sm font-medium text-red-700 hover:bg-red-50 rounded-xl transition-colors">
                Delete this document…
            </button>

            <form method="POST" action="{{ $deleteRoute }}" id="caDeleteForm" hidden
                  class="px-5 pb-5 pt-1 border-t border-gray-100">
                @csrf

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reason for deletion <span class="text-red-600">*</span>
                </label>
                <input type="text" name="delete_reason" required minlength="5" maxlength="255"
                       placeholder="Example: duplicated by mistake, replaced by CA/2026/09/00007"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                <p class="text-xs text-gray-400 mt-1">
                    At least 5 characters. The document stays on record and is still printable —
                    it moves to Status → Deleted.
                </p>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 mt-4">
                    <button type="button" id="caDeleteCancel"
                            class="w-full sm:w-auto px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Keep it
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                        Delete document
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- ══════════════════ KOLOM KIRI ══════════════════ --}}
        <div class="space-y-5">

            {{-- ── Cash Advance Information ────────────────────────────── --}}
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Cash Advance Information</h2>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Document No.</dt>
                        <dd class="font-medium primary-text text-right">{{ $request->request_no }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Request Date</dt>
                        <dd class="text-right">
                            {{ $request->request_date->format('d M Y') }}
                            @if($request->request_date_to)
                                &ndash; {{ $request->request_date_to->format('d M Y') }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Requester</dt>
                        <dd class="font-medium text-right">
                            {{ $request->employee?->basicData?->nick_name ?? $request->employee?->eci ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Employee No.</dt>
                        <dd class="text-right text-gray-600">{{ $request->employee?->eci ?? '—' }}</dd>
                    </div>
                    @if($request->charged_to_label)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Charged To</dt>
                        {{-- Label DIBEKUKAN saat submit: cabang bisa dinonaktifkan
                             dan proyek bisa ditutup, dokumen lama tetap terbaca. --}}
                        <dd class="text-right">{{ $request->charged_to_label }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-4 pt-3 border-t border-gray-100">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">CA Amount</dt>
                        <dd class="text-lg font-bold text-gray-900 text-right">
                            {{ $request->currency }} {{ $amounts->format((float) $request->amount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 uppercase text-xs tracking-wide">Status</dt>
                        <dd class="text-right">
                            @php
                                $badge = match ($request->status) {
                                    CashAdvance::STATUS_APPROVED  => 'bg-green-50 text-green-700',
                                    CashAdvance::STATUS_REJECTED  => 'bg-red-50 text-red-700',
                                    CashAdvance::STATUS_CANCELLED => 'bg-gray-100 text-gray-600',
                                    default                       => 'bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ $request->statusLabel() }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- ── Document Actions ────────────────────────────────────── --}}
            @if($canApprove || $cancelRoute || $editRoute)
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 text-center mb-4">Document Actions</h2>

                <div class="space-y-2">
                    @if($canApprove && $approveRoute)
                    <form method="POST" action="{{ $approveRoute }}" class="js-ca-approve"
                          data-no="{{ $request->request_no }}">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                            Approve
                        </button>
                    </form>
                    @endif

                    @if($canApprove && $rejectRoute)
                    {{-- Penolakan MENUNTUT alasan tertulis, jadi ia tidak boleh
                         berupa tombol sekali klik. Formnya dibuka di tempat. --}}
                    <details class="border border-red-200 rounded-lg">
                        <summary class="px-4 py-2.5 text-sm font-medium text-red-700 cursor-pointer">
                            Reject…
                        </summary>
                        <form method="POST" action="{{ $rejectRoute }}" class="p-4 pt-0 space-y-2">
                            @csrf
                            <textarea name="notes" rows="3" required minlength="5" maxlength="500"
                                      placeholder="Why is this rejected? At least 5 characters."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border"></textarea>
                            <button type="submit"
                                    class="w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                                Confirm rejection
                            </button>
                        </form>
                    </details>
                    @endif

                    @if($editRoute)
                    <a href="{{ $editRoute }}"
                       class="block text-center w-full px-4 py-2.5 border primary-border rounded-lg text-sm font-medium primary-text hover:bg-gray-50 transition-colors">
                        Edit Cash Advance
                    </a>
                    @endif

                    @if($cancelRoute)
                    <form method="POST" action="{{ $cancelRoute }}" class="js-ca-cancel"
                          data-no="{{ $request->request_no }}">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2.5 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                            Cancel Request
                        </button>
                    </form>
                    @endif

                    {{-- Tombol Delete SENGAJA belum ada di sini. Ia menuntut alasan
                         tertulis yang wajib (D109), dan menyiapkan field tersembunyi
                         tanpa cara mengisinya adalah cacat yang menunggu terjadi.
                         Ditambahkan di langkah A6 bersama rutenya. --}}
                </div>
            </div>
            @endif

            {{-- ── Approval Timeline ───────────────────────────────────── --}}
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-4">Approval Timeline</h2>

                <div class="space-y-2">
                    @forelse($request->approvals as $approval)
                        @php
                            $stateBadge = match ($approval->status) {
                                CashAdvanceApproval::STATUS_APPROVED => 'bg-green-50 text-green-700',
                                CashAdvanceApproval::STATUS_REJECTED => 'bg-red-50 text-red-700',
                                CashAdvanceApproval::STATUS_SKIPPED  => 'bg-gray-100 text-gray-500',
                                default                             => 'bg-amber-50 text-amber-700',
                            };
                        @endphp
                        <div class="flex items-start justify-between gap-3 border border-gray-200 rounded-lg px-4 py-3">
                            <div>
                                {{-- 🔴 "Verificator - Verification" — bagian kiri
                                     dari `actor_role` yang DIBEKUKAN, kanan dari
                                     `step_name`. Persis bentuk pada acuan. --}}
                                <p class="text-sm font-medium text-gray-900">{{ $approval->timelineLabel() }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $approval->acted_by
                                        ? ($approval->actor?->basicData?->nick_name ?? $approval->actor?->eci ?? '—')
                                        : $approval->approverLabel() }}
                                </p>
                                @if($approval->notes)
                                    <p class="text-xs text-gray-500 mt-1 italic">“{{ $approval->notes }}”</p>
                                @endif
                            </div>
                            <div class="text-right whitespace-nowrap">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $stateBadge }}">
                                    {{ ucfirst($approval->status === CashAdvanceApproval::STATUS_WAITING
                                        ? 'pending' : $approval->status) }}
                                </span>
                                @if($approval->acted_at)
                                    <span class="block text-xs text-gray-400 mt-0.5">
                                        {{ $approval->acted_at->format('d.m.Y') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No approval step recorded on this document.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══════════════════ KOLOM KANAN ══════════════════ --}}
        <div class="space-y-5">

            {{-- ── Description / Purpose ───────────────────────────────── --}}
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-3">Description / Purpose</h2>
                <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                    <p class="text-sm text-gray-800">{{ $request->description }}</p>
                </div>

                @if($request->notes)
                <p class="text-xs text-gray-500 mt-3">
                    <span class="font-medium">Notes:</span> {{ $request->notes }}
                </p>
                @endif

                @if($request->detail_url)
                <p class="text-xs mt-2">
                    <span class="font-medium text-gray-500">Supporting document:</span>
                    <a href="{{ $request->detail_url }}" target="_blank" rel="noopener noreferrer"
                       class="primary-text hover:underline break-all">{{ $request->detail_url }}</a>
                </p>
                @endif
            </div>

            {{-- ── Settlement (CAR) ────────────────────────────────────────
                 🔴 Sumbu KEDUA (D140). CA yang `approved` tetapi `unreported`
                 adalah keadaan yang sepenuhnya normal — dan justru itulah yang
                 paling perlu terlihat. --}}
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-3">Settlement (CAR)</h2>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Status</dt>
                        <dd class="font-medium">{{ $request->settlementLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Reports attached</dt>
                        <dd>{{ $request->car_count }}</dd>
                    </div>
                    @if($request->reported_amount !== null)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Reported</dt>
                        <dd>{{ $request->currency }} {{ $amounts->format((float) $request->reported_amount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 pt-2 border-t border-gray-100">
                        <dt class="text-gray-500">
                            {{ (float) $request->outstanding_amount >= 0 ? 'To refund' : 'To claim' }}
                        </dt>
                        <dd class="font-bold">
                            {{ $request->currency }}
                            {{ $amounts->format(abs((float) $request->outstanding_amount)) }}
                        </dd>
                    </div>
                    @endif
                    @if($request->settled_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Settled on</dt>
                        <dd>{{ $request->settled_at->format('d M Y') }}</dd>
                    </div>
                    @endif
                </dl>

                @if($canCreateReport)
                    {{-- Rutenya ada sejak R2. Tombol ini hanya muncul untuk PEMILIK
                         dokumen — sisi HR tidak mengirim $reportRoute, karena laporan
                         adalah pertanggungjawaban orang yang menerima uangnya. --}}
                    @if($reportRoute ?? null)
                        <a href="{{ $reportRoute }}"
                           class="mt-4 block text-center w-full px-4 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 transition-colors">
                            Create CAR
                        </a>
                    @else
                        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                            <p class="text-sm text-amber-900">
                                <span class="font-semibold">Waiting to be reported.</span>
                                Only the requester can create the settlement report.
                            </p>
                        </div>
                    @endif
                @elseif($reportBlockedReason)
                    {{-- 🔴 Alasannya berasal dari service, bukan ditebak layar
                         (Keputusan D154). Sebelum ini, tombol "Create CAR" tetap
                         muncul untuk CA yang laporannya sudah berjalan dan selalu
                         berakhir ditolak; sekarang tombolnya hilang DAN pemilik
                         dokumen membaca sebabnya di tempat tombolnya tadi berada. --}}
                    <p class="text-xs text-gray-500 mt-3">{{ $reportBlockedReason }}</p>
                @endif
            </div>

            {{-- ── Approval Log ────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-3">Approval Log</h2>

                @php
                    $acted = $request->approvals->whereNotNull('acted_at')->sortBy('order_seq');
                @endphp

                @forelse($acted as $entry)
                    <div class="flex items-start justify-between gap-3 py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <p class="text-xs text-gray-500">{{ $entry->timelineLabel() }}</p>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $entry->actor?->basicData?->nick_name ?? $entry->actor?->eci ?? '—' }}
                            </p>
                        </div>
                        <p class="text-xs text-gray-400 whitespace-nowrap">
                            {{ $entry->acted_at->format('d.m.Y H:i') }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nobody has acted on this document yet.</p>
                @endforelse

                @if($request->cancelled_at)
                    <p class="text-xs text-gray-500 mt-3">
                        Cancelled by
                        {{ $request->canceller?->basicData?->nick_name ?? $request->canceller?->eci ?? '—' }}
                        on {{ $request->cancelled_at->format('d.m.Y H:i') }}.
                    </p>
                @endif
            </div>

            {{-- ── Blok tanda tangan, pratinjau cetakan ────────────────── --}}
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900 mb-3">Document Signatures</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($signatures as $column)
                        <div class="border border-gray-200 rounded-lg px-3 py-2 text-center">
                            <p class="text-xs text-gray-500">{{ $column['title'] }}</p>
                            <p class="text-sm mt-1 {{ $column['pending'] ? 'text-gray-400 italic' : 'text-gray-900 font-medium' }}">
                                {{ $column['name'] !== '' ? $column['name'] : '—' }}
                            </p>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-3">
                    “Approved by” shows whoever actually approves the step whose actor is “Approver”.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // 🔴 Seluruh tindakan yang tidak dapat dibatalkan meminta showConfirm() —
    // bukan confirm() bawaan peramban. Form-nya sungguhan dan sudah membawa
    // @csrf dari Blade; JavaScript hanya menahan submit sebentar.
    function caGuard(selector, title, message, okText) {
        document.querySelectorAll(selector).forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                if (form.dataset.confirmed === 'yes') return;
                event.preventDefault();

                const ok = await showConfirm(message(form.dataset.no), title, 'danger',
                    { okText: okText, cancelText: 'Cancel' });

                if (!ok) return;
                form.dataset.confirmed = 'yes';
                form.submit();
            });
        });
    }

    caGuard('.js-ca-cancel', 'Cancel cash advance',
        no => `Cancel cash advance ${no}? This cannot be undone, and you would need to submit a new request.`,
        'Yes, cancel it');

    caGuard('.js-ca-approve', 'Approve cash advance',
        no => `Approve cash advance ${no}? This releases it to the next step.`,
        'Approve');

    // ── Hapus beralasan (D109) ─────────────────────────────────────────────
    // Panelnya tertutup sampai diminta: tombol hapus yang selalu terbuka di
    // halaman dokumen keuangan adalah undangan untuk salah tekan.
    const caDeleteToggle = document.getElementById('caDeleteToggle');
    const caDeleteForm   = document.getElementById('caDeleteForm');
    const caDeleteCancel = document.getElementById('caDeleteCancel');

    if (caDeleteToggle && caDeleteForm) {
        caDeleteToggle.addEventListener('click', function () {
            caDeleteForm.hidden = false;
            caDeleteToggle.hidden = true;
            caDeleteForm.querySelector('[name="delete_reason"]').focus();
        });

        caDeleteCancel.addEventListener('click', function () {
            caDeleteForm.hidden = true;
            caDeleteToggle.hidden = false;
            caDeleteForm.reset();
        });

        caDeleteForm.addEventListener('submit', async function (event) {
            if (caDeleteForm.dataset.confirmed === 'yes') return;
            event.preventDefault();

            // 🔴 Alasannya diperiksa DI SINI juga, bukan hanya oleh atribut
            // `required`. Kalau kosong, dialognya tidak muncul sama sekali —
            // meminta konfirmasi atas sesuatu yang pasti ditolak server hanya
            // membuat pengguna menekan dua kali untuk satu galat.
            const reason = caDeleteForm.querySelector('[name="delete_reason"]').value.trim();

            if (reason.length < 5) {
                showToast('Please give at least 5 characters of reason.', 'error');
                return;
            }

            const ok = await showConfirm(
                `Delete cash advance {{ $request->request_no }}? It stays on record and can still be `
                + `printed, but it leaves the active list. Reason: "${reason}"`,
                'Delete document',
                'danger',
                { okText: 'Yes, delete it', cancelText: 'Keep it' }
            );

            if (!ok) return;
            caDeleteForm.dataset.confirmed = 'yes';
            caDeleteForm.submit();
        });
    }
</script>
@endpush
