@extends('dashboard')
@section('title', 'Word Report Generator')
@section('page-title', 'Word Report Generator')
@section('page-subtitle', 'Generate laporan .docx dari template, terisi data nyata EcoSystem')

@push('styles')
<style>
    .wr-shell { height: calc(100vh - 11.5rem); min-height: 560px; }
    .wr-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
    .wr-scroll::-webkit-scrollbar { width: 6px; }
    .wr-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
    .wr-scroll::-webkit-scrollbar-track { background: transparent; }
    .wr-history-item.is-active { background: rgba(220,38,38,.08); border-color: #fecaca; }
    .wr-bubble-in { animation: wrFadeUp .18s ease-out both; }
    @keyframes wrFadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    .wr-dot { animation: wrBlink 1.2s infinite ease-in-out both; }
    .wr-dot:nth-child(2) { animation-delay: .18s; }
    .wr-dot:nth-child(3) { animation-delay: .36s; }
    @keyframes wrBlink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
    #wrInstructions { max-height: 140px; }
    .wr-msg-body p { margin: 0 0 6px; }
    .wr-msg-body p:last-child { margin-bottom: 0; }
    .wr-msg-body ul { margin: 2px 0 6px; }
    .wr-msg-body ul:last-child { margin-bottom: 0; }
    .wr-msg-body strong { font-weight: 700; }
    .wr-msg-body a { word-break: break-word; }

    .wr-step-item { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 76px; }
    .wr-step-dot {
        width: 24px; height: 24px; border-radius: 9999px; display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700; border: 2px solid #e5e7eb; color: #9ca3af; background: #fff;
        transition: background-color .2s, border-color .2s, color .2s;
    }
    .wr-step-label { font-size: 10px; font-weight: 600; color: #9ca3af; white-space: nowrap; }
    .wr-step-line { width: 28px; height: 2px; background: #e5e7eb; margin-bottom: 16px; }
    .wr-step-item.is-done .wr-step-dot { background: #dc2626; border-color: #dc2626; color: #fff; }
    .wr-step-item.is-done .wr-step-label { color: #374151; }
    .wr-step-item.is-active .wr-step-dot { border-color: #dc2626; color: #dc2626; box-shadow: 0 0 0 0 rgba(220,38,38,.25); animation: wrStepPulse 1.4s infinite; }
    .wr-step-item.is-active .wr-step-label { color: #dc2626; }
    .wr-step-item.is-error .wr-step-dot { background: #dc2626; border-color: #dc2626; color: #fff; }
    .wr-step-item.is-error .wr-step-label { color: #dc2626; }
    @keyframes wrStepPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,.25); } 50% { box-shadow: 0 0 0 4px rgba(220,38,38,.12); } }
</style>
@endpush

@section('content')

<div class="wr-shell flex gap-4">

    {{-- ── History panel (bisa diminimize) ─────────────────────────────── --}}
    <aside id="wrHistoryPanel" class="w-64 shrink-0 flex flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center gap-2 px-3 py-3 border-b border-gray-100">
            <h3 class="flex-1 text-xs font-bold text-gray-900">History</h3>
            <button type="button" onclick="wrNewReport()" title="Laporan baru"
                    class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-red-700 transition-all">
                <i class="fas fa-plus text-xs"></i>
            </button>
            <button type="button" onclick="wrToggleHistory()" title="Minimize"
                    class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
        </div>
        <div id="wrHistoryList" class="flex-1 overflow-y-auto wr-scroll p-2 space-y-1">
            <p class="px-2 py-6 text-center text-[11px] text-gray-400">Memuat riwayat...</p>
        </div>
    </aside>

    {{-- Rail sempit saat History diminimize --}}
    <div id="wrHistoryRail" class="hidden w-11 shrink-0 flex-col items-center gap-2 bg-white rounded-2xl border border-gray-200 py-3">
        <button type="button" onclick="wrToggleHistory()" title="Buka History"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
            <i class="fas fa-chevron-right text-xs"></i>
        </button>
        <button type="button" onclick="wrNewReport()" title="Laporan baru"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 hover:text-red-700 transition-all">
            <i class="fas fa-plus text-xs"></i>
        </button>
        <div class="w-8 h-8 flex items-center justify-center text-gray-300">
            <i class="fas fa-clock-rotate-left text-xs"></i>
        </div>
    </div>

    {{-- ── Center: pratinjau penuh ──────────────────────────────────────── --}}
    <section class="flex-1 min-w-0 flex flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden">

        <header class="flex items-center gap-3 px-4 sm:px-5 py-3 border-b border-gray-100">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white shrink-0">
                <i class="fas fa-file-word text-sm"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-gray-900 truncate">Pratinjau Laporan</h2>
                <p id="wrHeaderSub" class="text-[11px] text-gray-400 truncate">Belum ada laporan dipilih</p>
            </div>
            <span id="wrStatusBadge" class="hidden inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"></span>
        </header>

        <div id="wrPreviewArea" class="flex-1 min-h-0 flex flex-col p-4">

            <div id="wrPhaseStepper" class="hidden shrink-0 flex items-center justify-center gap-2 pb-4 mb-2 border-b border-gray-100">
                <div class="wr-step-item" data-phase-step="structure">
                    <span class="wr-step-dot">1</span>
                    <span class="wr-step-label">Baca Struktur</span>
                </div>
                <div class="wr-step-line"></div>
                <div class="wr-step-item" data-phase-step="data">
                    <span class="wr-step-dot">2</span>
                    <span class="wr-step-label">Ambil Data</span>
                </div>
                <div class="wr-step-line"></div>
                <div class="wr-step-item" data-phase-step="document">
                    <span class="wr-step-dot">3</span>
                    <span class="wr-step-label">Susun Dokumen</span>
                </div>
            </div>

            <div id="wrEmptyState" class="flex-1 flex flex-col items-center justify-center text-center py-6">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white mb-4">
                    <i class="fas fa-file-word text-lg"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Belum ada laporan dipilih</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-sm">Pilih template &amp; tulis instruksi di panel Chat sebelah kanan, atau klik salah satu riwayat di panel History.</p>
            </div>

            <div id="wrProcessingState" class="hidden flex-1 flex flex-col items-center justify-center text-center py-6">
                <svg class="animate-spin w-8 h-8 text-red-500 mb-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <p id="wrProcessingText" class="text-sm font-medium text-gray-700">Diproses AI...</p>
                <p class="text-xs text-gray-400 mt-1">Bisa beberapa menit — baca template, ambil data, susun dokumen.</p>
            </div>

            <div id="wrErrorState" class="hidden flex-1 flex flex-col items-center justify-center text-center py-6">
                <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center text-red-500 mb-3">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900">Gagal generate laporan</p>
                <p id="wrErrorText" class="text-xs text-red-500 mt-1 max-w-sm"></p>
                <button type="button" id="wrRetryBtn" onclick="submitRetry()"
                        class="hidden mt-3 px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-all">
                    <i class="fas fa-rotate-right text-[10px] mr-1"></i>Coba Lagi
                </button>
            </div>

            <div id="wrPausedState" class="hidden flex-1 flex flex-col items-center justify-center text-center py-6">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500 mb-3">
                    <i class="fas fa-circle-pause"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900">Dijeda sementara</p>
                <p id="wrPausedText" class="text-xs text-gray-500 mt-1 max-w-sm">Layanan AI sedang sibuk. Progres yang sudah selesai tersimpan.</p>
                <button type="button" id="wrContinueBtn" onclick="submitRetry()"
                        class="mt-3 px-3 py-1.5 text-xs font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition-all">
                    <i class="fas fa-play text-[10px] mr-1"></i>Lanjutkan
                </button>
            </div>

            <div id="wrAwaitingState" class="hidden flex-1 flex flex-col items-center justify-center text-center py-6">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-500 mb-3">
                    <i class="fas fa-circle-question"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900">AI butuh klarifikasi</p>
                <p class="text-xs text-gray-500 mt-1 max-w-sm">Jawab pertanyaan di panel Chat sebelah kanan untuk melanjutkan.</p>
            </div>

            <div id="wrResultState" class="hidden flex-1 min-h-0 flex flex-col">
                <div class="flex items-center justify-end gap-2 mb-2 shrink-0">
                    <a id="wrDocxLink" href="#" class="px-3 py-1.5 text-xs font-semibold text-red-700 border border-red-200 rounded-lg hover:bg-red-50 transition-all">
                        <i class="fas fa-download text-[10px] mr-1"></i>.docx
                    </a>
                    <a id="wrPdfLink" href="#" class="px-3 py-1.5 text-xs font-semibold text-red-700 border border-red-200 rounded-lg hover:bg-red-50 transition-all">
                        <i class="fas fa-download text-[10px] mr-1"></i>.pdf
                    </a>
                </div>
                <iframe id="wrPdfFrame" class="flex-1 w-full rounded-xl border border-gray-200 bg-gray-50"></iframe>
            </div>
        </div>
    </section>

    {{-- ── Right: room chat ─────────────────────────────────────────────── --}}
    <aside class="w-80 xl:w-96 shrink-0 flex flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden">

        <header class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white shrink-0">
                <i class="fas fa-comments text-xs"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-gray-900 truncate">Chat</h2>
                <p class="text-[11px] text-gray-400 truncate">Instruksi &amp; pilihan template</p>
            </div>
        </header>

        <div id="wrChatThread" class="flex-1 overflow-y-auto wr-scroll px-3 py-4 space-y-4">
            <div id="wrChatEmpty" class="min-h-full flex flex-col items-center justify-center text-center py-6">
                <i class="fas fa-file-word text-2xl text-gray-200 mb-3"></i>
                <p class="text-xs text-gray-400 max-w-[220px]">Pilih template &amp; tulis instruksi di bawah untuk mulai generate laporan.</p>
            </div>
            <div id="wrChatMessages" class="space-y-4 hidden"></div>
        </div>

        {{-- Composer --}}
        <div class="border-t border-gray-100 px-3 py-3 space-y-2">

            <button type="button" id="wrScopeToggle" onclick="wrToggleScopePanel()"
                    class="w-full flex items-center gap-1.5 text-[11px] font-medium text-gray-600 hover:text-gray-800 transition-all">
                <i class="fas fa-file-lines text-gray-400"></i>
                <span class="truncate">Template: <span id="wrTemplateLabel" class="font-semibold text-gray-800">(belum dipilih)</span></span>
                <i id="wrScopeChevron" class="fas fa-chevron-down text-[9px] text-gray-400 transition-transform ml-auto shrink-0"></i>
            </button>

            <div id="wrScopePanel" class="hidden border border-gray-200 rounded-xl p-3 space-y-2 bg-gray-50 max-h-72 overflow-y-auto wr-scroll">
                <label class="block text-xs font-semibold text-gray-700">Pilih Template</label>
                <p class="text-[11px] text-gray-400 -mt-1">Upload template baru dilakukan dari halaman Customer (tab "Report Templates").</p>
                <div class="relative">
                    <input type="text" id="templateSearchInput" placeholder="Klik untuk pilih dari daftar, atau ketik untuk cari"
                           autocomplete="off"
                           class="block w-full text-sm border border-gray-300 rounded-lg p-2 bg-white">
                </div>
                <div id="templateList" class="space-y-2">
                    <p class="text-xs text-gray-400">Memuat...</p>
                </div>
            </div>

            <form id="reportForm" class="rounded-2xl border border-gray-200 bg-white focus-within:border-red-400 focus-within:ring-2 focus-within:ring-red-100 transition-all">
                <textarea id="wrInstructions" rows="1" placeholder="Tulis instruksi laporan..."
                          class="w-full resize-none bg-transparent px-3 pt-2.5 pb-1 text-sm text-gray-800 placeholder-gray-400 focus:outline-none wr-scroll"></textarea>
                <div class="flex items-center justify-end px-2 pb-2 pt-1">
                    <button type="submit" id="submitBtn"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-xl hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <i class="fas fa-paper-plane text-[10px]"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </aside>
</div>

<script>
(function () {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ── Elements ──────────────────────────────────────────────────────
    const reportForm = document.getElementById('reportForm');
    const submitBtn = document.getElementById('submitBtn');
    const wrInstructions = document.getElementById('wrInstructions');

    const wrHistoryPanel = document.getElementById('wrHistoryPanel');
    const wrHistoryRail = document.getElementById('wrHistoryRail');
    const wrHistoryList = document.getElementById('wrHistoryList');

    const wrHeaderSub = document.getElementById('wrHeaderSub');
    const wrStatusBadge = document.getElementById('wrStatusBadge');
    const wrPhaseStepper = document.getElementById('wrPhaseStepper');
    const wrEmptyState = document.getElementById('wrEmptyState');
    const wrProcessingState = document.getElementById('wrProcessingState');
    const wrProcessingText = document.getElementById('wrProcessingText');
    const wrErrorState = document.getElementById('wrErrorState');
    const wrErrorText = document.getElementById('wrErrorText');
    const wrRetryBtn = document.getElementById('wrRetryBtn');
    const wrPausedState = document.getElementById('wrPausedState');
    const wrPausedText = document.getElementById('wrPausedText');
    const wrContinueBtn = document.getElementById('wrContinueBtn');
    const wrAwaitingState = document.getElementById('wrAwaitingState');
    const wrResultState = document.getElementById('wrResultState');
    const wrDocxLink = document.getElementById('wrDocxLink');
    const wrPdfLink = document.getElementById('wrPdfLink');
    const wrPdfFrame = document.getElementById('wrPdfFrame');

    const wrChatEmpty = document.getElementById('wrChatEmpty');
    const wrChatMessages = document.getElementById('wrChatMessages');
    const wrChatThread = document.getElementById('wrChatThread');

    const wrScopeToggle = document.getElementById('wrScopeToggle');
    const wrScopePanel = document.getElementById('wrScopePanel');
    const wrScopeChevron = document.getElementById('wrScopeChevron');
    const wrTemplateLabel = document.getElementById('wrTemplateLabel');

    const templateSearchInput = document.getElementById('templateSearchInput');
    const templateList = document.getElementById('templateList');

    let pollTimer = null;
    let activeReportId = null;
    let awaitingAnswer = false;

    // ── History minimize ──────────────────────────────────────────────
    window.wrToggleHistory = function () {
        const collapsed = !wrHistoryPanel.classList.contains('hidden');
        wrHistoryPanel.classList.toggle('hidden', collapsed);
        wrHistoryRail.classList.toggle('hidden', !collapsed);
        wrHistoryRail.classList.toggle('flex', collapsed);
    };

    // ── Preview state helpers ────────────────────────────────────────
    function showState(state) {
        [wrEmptyState, wrProcessingState, wrErrorState, wrPausedState, wrAwaitingState, wrResultState].forEach(el => el.classList.add('hidden'));
        wrRetryBtn.classList.add('hidden');
        state.classList.remove('hidden');
    }

    function setStatusBadge(status) {
        const map = {
            pending:         ['Menunggu', 'bg-gray-100 text-gray-500'],
            processing:      ['Diproses', 'bg-amber-50 text-amber-700'],
            awaiting_input:  ['Butuh Jawaban', 'bg-indigo-50 text-indigo-700'],
            paused:          ['Dijeda', 'bg-amber-50 text-amber-700'],
            completed:       ['Selesai', 'bg-emerald-50 text-emerald-700'],
            failed:          ['Gagal', 'bg-red-50 text-red-700'],
        };
        const [label, cls] = map[status] || ['-', 'bg-gray-100 text-gray-500'];
        wrStatusBadge.textContent = label;
        wrStatusBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold ' + cls;
        wrStatusBadge.classList.remove('hidden');
    }

    // ── Progress fase (Tahap 1 baca struktur / Tahap 2 ambil data / Tahap 3 susun dokumen) ──
    const wrPhaseOrder = ['structure', 'data', 'document'];
    const wrPhaseStepEls = {};
    wrPhaseOrder.forEach(p => { wrPhaseStepEls[p] = document.querySelector(`[data-phase-step="${p}"]`); });

    function renderPhaseStepper(phase, status) {
        const isDone = !phase && status === 'completed';
        if (!phase && !isDone) {
            wrPhaseStepper.classList.add('hidden');
            return;
        }
        wrPhaseStepper.classList.remove('hidden');

        const currentIndex = isDone ? wrPhaseOrder.length : wrPhaseOrder.indexOf(phase);

        wrPhaseOrder.forEach((p, i) => {
            const el = wrPhaseStepEls[p];
            const dot = el.querySelector('.wr-step-dot');
            el.classList.remove('is-done', 'is-active', 'is-error');

            if (i < currentIndex) {
                el.classList.add('is-done');
                dot.innerHTML = '<i class="fas fa-check text-[10px]"></i>';
            } else if (i === currentIndex && 'failed' === status) {
                el.classList.add('is-error');
                dot.innerHTML = '<i class="fas fa-xmark text-[10px]"></i>';
            } else if (i === currentIndex && 'paused' === status) {
                el.classList.add('is-active');
                dot.innerHTML = '<i class="fas fa-pause text-[10px]"></i>';
            } else if (i === currentIndex) {
                el.classList.add('is-active');
                dot.innerHTML = '<i class="fas fa-spinner fa-spin text-[10px]"></i>';
            } else {
                dot.textContent = String(i + 1);
            }
        });
    }

    // ── Chat bubbles ──────────────────────────────────────────────────
    function wrEsc(s) {
        const div = document.createElement('div');
        div.textContent = s ?? '';
        return div.innerHTML;
    }
    function wrTime() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    function wrScrollChatBottom() {
        wrChatThread.scrollTop = wrChatThread.scrollHeight;
    }
    function wrClearChat() {
        wrChatMessages.innerHTML = '';
        wrChatMessages.classList.add('hidden');
        wrChatEmpty.classList.remove('hidden');
    }
    function wrAppendUserBubble(text) {
        wrChatEmpty.classList.add('hidden');
        wrChatMessages.classList.remove('hidden');
        const body = text ? `<p class="whitespace-pre-wrap break-words">${wrEsc(text)}</p>` : '<p class="italic opacity-75">(tanpa instruksi tambahan)</p>';
        wrChatMessages.insertAdjacentHTML('beforeend', `
            <div class="wr-bubble-in flex justify-end">
                <div class="max-w-[90%]">
                    <div class="bg-red-600 text-white text-sm rounded-2xl rounded-br-md px-3.5 py-2.5">${body}</div>
                    <p class="text-[10px] text-gray-400 mt-1 text-right">${wrTime()}</p>
                </div>
            </div>`);
        wrScrollChatBottom();
    }
    function wrAppendAssistantPending() {
        const id = 'wrMsg' + Date.now();
        wrChatMessages.insertAdjacentHTML('beforeend', `
            <div class="wr-bubble-in flex gap-2.5" id="${id}">
                <div class="w-7 h-7 shrink-0 rounded-xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white">
                    <i class="fas fa-file-word text-[10px]"></i>
                </div>
                <div class="max-w-[85%] min-w-0">
                    <div class="wr-msg-body bg-gray-50 border border-gray-100 text-sm text-gray-800 rounded-2xl rounded-bl-md px-3.5 py-2.5">
                        <span class="inline-flex items-center gap-1 py-1">
                            <span class="wr-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <span class="wr-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <span class="wr-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        </span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">${wrTime()}</p>
                </div>
            </div>`);
        wrScrollChatBottom();
        return id;
    }
    // Ringkasan hasil generate ditulis AI dalam markdown ringan (bold, list,
    // link ke file) -- render jadi HTML supaya kebaca, bukan tanda **/- / []
    // mentah. Escape HTML DULU (wrEsc), baru terapkan pola markdown di atas
    // teks yang sudah aman, supaya konten AI tidak bisa menyuntik HTML asli.
    function wrRenderMarkdown(text) {
        if (!text) return '';

        const inlineFormat = (line) => {
            let s = wrEsc(line);
            // Link ke sandbox provider (mis. "sandbox:/mnt/data/laporan.docx")
            // tidak pernah bisa dibuka dari browser -- tombol unduh docx/pdf
            // yang asli sudah ada di atas pratinjau, jadi tampilkan sebagai
            // teks tebal biasa saja, bukan link mati.
            s = s.replace(/\[([^\]]+)\]\(sandbox:[^\s)]+\)/g, '<strong>$1</strong>');
            s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener" class="text-red-600 underline">$1</a>');
            s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            s = s.replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>');
            s = s.replace(/(?<!_)_([^_\n]+)_(?!_)/g, '<em>$1</em>');
            s = s.replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 rounded bg-black/5 text-[11px]">$1</code>');
            return s;
        };

        let html = '';
        let inList = false;

        String(text).split('\n').forEach(raw => {
            const line = raw.trim();

            if (!line) {
                if (inList) { html += '</ul>'; inList = false; }
                return;
            }

            const bullet = line.match(/^[-*]\s+(.*)$/);
            if (bullet) {
                if (!inList) { html += '<ul class="list-disc pl-4 space-y-0.5">'; inList = true; }
                html += `<li>${inlineFormat(bullet[1])}</li>`;
                return;
            }

            if (inList) { html += '</ul>'; inList = false; }
            html += `<p>${inlineFormat(line)}</p>`;
        });

        if (inList) html += '</ul>';
        return html;
    }

    function wrUpdateAssistantBubble(id, text, isError) {
        const el = document.querySelector(`#${id} .wr-msg-body`);
        if (!el) return;
        el.classList.toggle('text-red-600', !!isError);
        if (isError) {
            el.textContent = text || 'Terjadi kesalahan.';
        } else {
            el.innerHTML = wrRenderMarkdown(text) || 'Selesai.';
        }
        wrScrollChatBottom();
    }

    // ── Scope panel (pilih template — upload dilakukan di halaman Customer) ──
    window.wrToggleScopePanel = function () {
        wrScopePanel.classList.toggle('hidden');
        wrScopeChevron.classList.toggle('rotate-180');
        if (!wrScopePanel.classList.contains('hidden')) loadTemplates(templateSearchInput.value.trim());
    };

    let selectedTemplateId = null;
    let templateSearchTimer = null;

    templateSearchInput.addEventListener('focus', () => loadTemplates(templateSearchInput.value.trim()));
    templateSearchInput.addEventListener('input', () => {
        clearTimeout(templateSearchTimer);
        templateSearchTimer = setTimeout(() => loadTemplates(templateSearchInput.value.trim()), 300);
    });

    async function loadTemplates(q) {
        templateList.innerHTML = '<p class="text-xs text-gray-400">Memuat...</p>';

        try {
            const res = await fetch('{{ route('reports.templates') }}' + (q ? ('?q=' + encodeURIComponent(q)) : ''));
            const json = await res.json();

            if (!json.success || !json.data.length) {
                templateList.innerHTML = '<p class="text-xs text-gray-400">Belum ada template. Upload dulu lewat halaman Customer (tab "Report Templates").</p>';
                return;
            }

            templateList.innerHTML = json.data.map(t => `
                <label class="flex items-center gap-2 text-sm border border-gray-200 rounded-lg p-2 cursor-pointer hover:bg-white bg-white">
                    <input type="radio" name="template_choice" value="${t.id}" data-name="${String(t.name).replace(/"/g, '&quot;')}" ${t.id === selectedTemplateId ? 'checked' : ''}>
                    <span class="min-w-0 flex-1 truncate">${t.name}
                        <span class="text-gray-400 text-xs">(${t.customer_name || 'Umum / Internal'})</span>
                    </span>
                </label>
            `).join('');

            document.querySelectorAll('input[name="template_choice"]').forEach(el => {
                el.addEventListener('change', onTemplateChoiceChange);
            });
        } catch (err) {
            templateList.innerHTML = '<p class="text-xs text-red-500">Gagal memuat.</p>';
        }
    }

    function onTemplateChoiceChange(e) {
        selectedTemplateId = e.target.value;
        wrTemplateLabel.textContent = e.target.dataset.name || '(dipilih)';
        wrScopePanel.classList.add('hidden');
        wrScopeChevron.classList.remove('rotate-180');
    }

    loadTemplates();

    // ── History panel ─────────────────────────────────────────────────
    async function loadHistory() {
        try {
            const res = await fetch('{{ route('reports.history') }}');
            const json = await res.json();

            if (!json.success || !json.data.length) {
                wrHistoryList.innerHTML = '<p class="px-2 py-6 text-center text-[11px] text-gray-400">Belum ada riwayat.</p>';
                return;
            }

            wrHistoryList.innerHTML = json.data.map(r => {
                const title = r.template_name || 'Laporan #' + r.id;
                const sub = r.customer_name || 'Umum / Internal';
                const dot = { pending: 'bg-gray-300', processing: 'bg-amber-400', awaiting_input: 'bg-indigo-500', paused: 'bg-amber-500', completed: 'bg-emerald-500', failed: 'bg-red-500' }[r.status] || 'bg-gray-300';
                return `
                <div class="wr-history-item flex items-start gap-2 p-2.5 rounded-xl border border-transparent hover:bg-gray-50 cursor-pointer transition-all"
                     data-id="${r.id}">
                    <span class="w-1.5 h-1.5 rounded-full ${dot} mt-1.5 shrink-0"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-gray-800 truncate">${title}</p>
                        <p class="text-[11px] text-gray-400 truncate">${sub}</p>
                    </div>
                </div>`;
            }).join('');

            wrHistoryList.querySelectorAll('[data-id]').forEach(el => {
                el.addEventListener('click', () => openReport(parseInt(el.dataset.id, 10)));
            });
        } catch (err) {
            wrHistoryList.innerHTML = '<p class="px-2 py-6 text-center text-[11px] text-gray-400">Gagal memuat riwayat.</p>';
        }
    }

    function markActiveHistoryItem(id) {
        wrHistoryList.querySelectorAll('.wr-history-item').forEach(el => {
            el.classList.toggle('is-active', parseInt(el.dataset.id, 10) === id);
        });
    }

    window.wrNewReport = function () {
        clearTimeout(pollTimer);
        activeReportId = null;
        awaitingAnswer = false;
        wrInstructions.value = '';
        wrInstructions.placeholder = 'Tulis instruksi laporan...';
        wrStatusBadge.classList.add('hidden');
        wrHeaderSub.textContent = 'Belum ada laporan dipilih';
        submitBtn.disabled = false;
        renderPhaseStepper(null, null);
        showState(wrEmptyState);
        wrClearChat();
        markActiveHistoryItem(null);
    };

    async function openReport(id) {
        clearTimeout(pollTimer);
        activeReportId = id;
        markActiveHistoryItem(id);
        wrClearChat();
        showState(wrProcessingState);
        wrProcessingText.textContent = 'Memuat...';
        await pollOnce('{{ url('/reports') }}/' + id + '/status', false, true);
    }

    // ── Submit ────────────────────────────────────────────────────────
    reportForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (awaitingAnswer) {
            return submitAnswer();
        }

        if (!selectedTemplateId) {
            alert('Pilih template dulu di panel atas.');
            wrScopePanel.classList.remove('hidden');
            loadTemplates(templateSearchInput.value.trim());
            return;
        }

        const formData = new FormData();
        const instructionsText = wrInstructions.value;
        formData.append('instructions', instructionsText);
        formData.append('template_id', selectedTemplateId);

        wrClearChat();
        wrAppendUserBubble(instructionsText);
        const pendingId = wrAppendAssistantPending();
        wrInstructions.value = '';

        submitBtn.disabled = true;
        wrStatusBadge.classList.add('hidden');
        wrHeaderSub.textContent = 'Mengirim...';
        showState(wrProcessingState);
        wrProcessingText.textContent = 'Mengirim...';

        try {
            const res = await fetch('{{ route('reports.generate') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf() },
                body: formData,
            });
            const json = await res.json();

            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Gagal mengirim template.');
            }

            activeReportId = json.report_id;
            wrProcessingText.textContent = 'Diproses AI (bisa beberapa menit)...';
            loadHistory();
            poll(json.status_url, pendingId);
        } catch (err) {
            showState(wrErrorState);
            wrErrorText.textContent = err.message;
            wrUpdateAssistantBubble(pendingId, err.message, true);
            submitBtn.disabled = false;
        }
    });

    async function submitAnswer() {
        const answerText = wrInstructions.value.trim();
        if (!answerText) {
            alert('Tulis jawaban Anda dulu.');
            return;
        }

        wrAppendUserBubble(answerText);
        const pendingId = wrAppendAssistantPending();
        wrInstructions.value = '';
        awaitingAnswer = false;
        wrInstructions.placeholder = 'Tulis instruksi laporan...';

        submitBtn.disabled = true;
        wrHeaderSub.textContent = 'Mengirim jawaban...';
        showState(wrProcessingState);
        wrProcessingText.textContent = 'Mengirim jawaban...';

        try {
            const res = await fetch('{{ url('/reports') }}/' + activeReportId + '/answer', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ answer: answerText }),
            });
            const json = await res.json();

            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Gagal mengirim jawaban.');
            }

            wrProcessingText.textContent = 'Diproses AI (bisa beberapa menit)...';
            loadHistory();
            poll(json.status_url, pendingId);
        } catch (err) {
            showState(wrErrorState);
            wrErrorText.textContent = err.message;
            wrUpdateAssistantBubble(pendingId, err.message, true);
            submitBtn.disabled = false;
        }
    }

    window.submitRetry = async function () {
        if (!activeReportId) return;

        wrRetryBtn.disabled = true;
        wrContinueBtn.disabled = true;
        wrHeaderSub.textContent = 'Melanjutkan...';
        showState(wrProcessingState);
        wrProcessingText.textContent = 'Melanjutkan...';

        try {
            const res = await fetch('{{ url('/reports') }}/' + activeReportId + '/retry', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            });
            const json = await res.json();

            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Gagal mencoba lagi.');
            }

            poll(json.status_url, null);
        } catch (err) {
            showState(wrErrorState);
            wrErrorText.textContent = err.message;
            wrRetryBtn.classList.remove('hidden');
        } finally {
            wrRetryBtn.disabled = false;
            wrContinueBtn.disabled = false;
        }
    };

    function poll(statusUrl, pendingId) {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(() => pollOnce(statusUrl, true, false, pendingId), 4000);
    }

    async function pollOnce(statusUrl, isPolling, isHistoryOpen, pendingId) {
        try {
            const res = await fetch(statusUrl, { headers: { 'X-CSRF-TOKEN': csrf() } });
            const json = await res.json();

            if (!json.success) {
                throw new Error('Gagal memuat status.');
            }

            setStatusBadge(json.status);
            renderPhaseStepper(json.phase, json.status);

            if (isHistoryOpen) {
                wrAppendUserBubble(json.instructions);
                // Q&A yang sudah tuntas dulu (kalau ada beberapa putaran),
                // pertanyaan yang masih menunggu jawaban ditangani lewat
                // pendingId di bawah supaya tidak dobel dirender.
                (json.qa_log || []).forEach(qa => {
                    if (qa.answer === null || qa.answer === undefined) return;
                    const qId = wrAppendAssistantPending();
                    wrUpdateAssistantBubble(qId, qa.question, false);
                    wrAppendUserBubble(qa.answer);
                });
                pendingId = wrAppendAssistantPending();
            }

            if (json.status === 'awaiting_input') {
                awaitingAnswer = true;
                submitBtn.disabled = false;
                wrHeaderSub.textContent = 'Menunggu jawaban Anda';
                wrInstructions.placeholder = 'Tulis jawaban Anda...';
                if (pendingId) wrUpdateAssistantBubble(pendingId, json.question, false);
                showState(wrAwaitingState);
                return;
            }

            awaitingAnswer = false;

            if (json.status === 'completed') {
                submitBtn.disabled = false;
                wrHeaderSub.textContent = 'Selesai';
                if (pendingId) wrUpdateAssistantBubble(pendingId, json.summary || 'Selesai.', false);
                if (json.docx_url) { wrDocxLink.href = json.docx_url; wrDocxLink.classList.remove('hidden'); } else { wrDocxLink.classList.add('hidden'); }
                if (json.pdf_url) { wrPdfLink.href = json.pdf_url; wrPdfLink.classList.remove('hidden'); } else { wrPdfLink.classList.add('hidden'); }
                wrPdfFrame.src = json.pdf_preview_url || '';
                showState(wrResultState);
                return;
            }

            if (json.status === 'paused') {
                submitBtn.disabled = false;
                wrHeaderSub.textContent = 'Dijeda sementara';
                showState(wrPausedState);
                wrPausedText.textContent = json.error_message
                    || 'Layanan AI sedang sibuk. Progres yang sudah selesai tersimpan — klik "Lanjutkan" untuk meneruskan.';
                if (pendingId) wrUpdateAssistantBubble(pendingId, json.error_message
                    || 'Dijeda sementara — klik "Lanjutkan" untuk meneruskan.', false);
                return;
            }

            if (json.status === 'failed') {
                submitBtn.disabled = false;
                wrHeaderSub.textContent = 'Gagal';
                showState(wrErrorState);
                wrErrorText.textContent = json.error_message || 'Terjadi kesalahan saat generate laporan.';
                wrRetryBtn.classList.remove('hidden');
                if (pendingId) wrUpdateAssistantBubble(pendingId, json.error_message || 'Gagal generate laporan.', true);
                return;
            }

            const phaseLabel = { structure: 'Membaca struktur template...', data: 'Mengambil data...', document: 'Menyusun dokumen...' }[json.phase] || '';
            wrHeaderSub.textContent = 'processing' === json.status ? 'Diproses AI...' : 'Menunggu giliran...';
            wrProcessingText.textContent = 'processing' === json.status
                ? (phaseLabel || 'Diproses AI (bisa beberapa menit)...')
                : 'Menunggu giliran diproses...';
            showState(wrProcessingState);
            poll(statusUrl, pendingId);
        } catch (err) {
            submitBtn.disabled = false;
            showState(wrErrorState);
            wrErrorText.textContent = err.message;
        }
    }

    loadHistory();
})();
</script>
@endsection
