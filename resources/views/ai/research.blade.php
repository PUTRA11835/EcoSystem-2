@extends('dashboard')

@section('title', 'AI Research')
@section('page-title', 'AI Research')
@section('page-subtitle', 'Look things up outside EcoSystem — TCODEs, errors, vendor documentation')

@push('styles')
<style>
    /* Tinggi panel chat: sisa viewport setelah header aplikasi + padding konten. */
    .air-shell { height: calc(100vh - 11.5rem); min-height: 520px; }

    .air-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
    .air-scroll::-webkit-scrollbar { width: 6px; }
    .air-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
    .air-scroll::-webkit-scrollbar-track { background: transparent; }

    .air-bubble-in { animation: airFadeUp .18s ease-out both; }
    @keyframes airFadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    .air-dot { animation: airBlink 1.2s infinite ease-in-out both; }
    .air-dot:nth-child(2) { animation-delay: .18s; }
    .air-dot:nth-child(3) { animation-delay: .36s; }
    @keyframes airBlink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }

    #airInput { max-height: 200px; }

    /* Sidebar riwayat. Di layar lebar ia kolom biasa; di layar sempit ruang
       tidak cukup untuk dua kolom, jadi ia muncul sebagai drawer melayang.
       Selektor ber-id dipakai supaya menang atas .hidden milik Tailwind. */
    @media (max-width: 1023px) {
        #airHistory.is-open {
            display: flex; position: fixed; z-index: 60;
            top: 5.5rem; left: .75rem; bottom: 1rem; width: 17rem;
            box-shadow: 0 12px 40px rgba(0,0,0,.18);
        }
    }
    .air-history-item.is-active { background: rgba(99,102,241,.10); border-color: #c7d2fe; }

    /* Zona drop/paste menyala saat file diseret ke atas composer. */
    .air-dropzone.is-dragging { border-color: #6366f1; background: rgba(99,102,241,.04); }

    /* ── Markdown jawaban ──────────────────────────────────────────────────
       Preflight Tailwind me-reset list-style dan ukuran heading secara global,
       jadi semuanya HARUS dinyalakan ulang di sini — kalau tidak, "- item"
       tampil tanpa bullet dan "## Judul" tampil sebesar teks biasa.
       Lihat catatan yang sama pada editor Quill di halaman ticket. */
    .air-prose { font-size: .875rem; line-height: 1.6; word-break: break-word; }

    .air-prose > :first-child { margin-top: 0; }
    .air-prose > :last-child  { margin-bottom: 0; }

    .air-prose p { margin: 0 0 .6rem; }

    .air-prose h1, .air-prose h2, .air-prose h3,
    .air-prose h4, .air-prose h5, .air-prose h6 {
        font-weight: 700; line-height: 1.3; color: #111827;
        margin: 1.1rem 0 .5rem;
    }
    .air-prose h1 { font-size: 1.125rem; }
    .air-prose h2 { font-size: 1rem; }
    .air-prose h3 { font-size: .9375rem; }
    .air-prose h4, .air-prose h5, .air-prose h6 { font-size: .875rem; }

    .air-prose ul, .air-prose ol { margin: 0 0 .6rem; padding-left: 1.35rem; }
    .air-prose ul { list-style: disc outside; }
    .air-prose ul ul { list-style: circle outside; }
    .air-prose ol { list-style: decimal outside; }
    .air-prose li { margin: .2rem 0; }
    .air-prose li > ul, .air-prose li > ol { margin: .2rem 0 0; }

    .air-prose strong { font-weight: 700; color: #111827; }
    .air-prose em { font-style: italic; }
    .air-prose del { text-decoration: line-through; opacity: .7; }

    .air-prose code {
        background: rgba(0,0,0,.06); padding: .1rem .3rem; border-radius: .3rem;
        font-size: .8125rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    .air-prose pre {
        background: #0f172a; color: #e2e8f0; padding: .75rem .9rem; border-radius: .6rem;
        overflow-x: auto; margin: 0 0 .6rem; font-size: .8125rem; line-height: 1.5;
    }
    .air-prose pre code { background: none; padding: 0; color: inherit; font-size: inherit; }

    .air-prose a { color: #4f46e5; text-decoration: underline; text-underline-offset: 2px; }
    .air-prose a:hover { color: #4338ca; }

    .air-prose blockquote {
        border-left: 3px solid #c7d2fe; padding: .1rem 0 .1rem .75rem;
        margin: 0 0 .6rem; color: #4b5563;
    }
    .air-prose hr { border: 0; border-top: 1px solid #e5e7eb; margin: .9rem 0; }

    /* Tabel bisa lebih lebar dari bubble — biarkan menggulir sendiri. */
    .air-table-wrap { overflow-x: auto; margin: 0 0 .6rem; }
    .air-prose table { border-collapse: collapse; font-size: .8125rem; }
    .air-prose th, .air-prose td { border: 1px solid #e5e7eb; padding: .35rem .6rem; text-align: left; }
    .air-prose th { background: rgba(0,0,0,.04); font-weight: 700; }

    /* ── Lampiran gambar ───────────────────────────────────────────────────── */
    .air-thumb {
        display: block; border-radius: .5rem; object-fit: cover;
        cursor: zoom-in; transition: transform .12s ease, box-shadow .12s ease;
    }
    .air-thumb:hover { transform: scale(1.02); box-shadow: 0 4px 14px rgba(0,0,0,.18); }

    #airLightbox { background: rgba(3,7,18,.88); backdrop-filter: blur(2px); }
    #airLightbox img { max-width: 92vw; max-height: 86vh; border-radius: .5rem; }

    @if(session('user_preferences.theme', 'light') === 'dark')
    /* Dark mode: aturan di atas memakai CSS mentah, jadi override utilitas
       Tailwind di dashboard tidak menyentuhnya — warnanya dipetakan ulang di sini. */
    .air-prose h1, .air-prose h2, .air-prose h3,
    .air-prose h4, .air-prose h5, .air-prose h6,
    .air-prose strong { color: #f3f4f6; }
    .air-prose code { background: rgba(255,255,255,.10); }
    .air-prose pre { background: #030712; }
    .air-prose a { color: #a5b4fc; }
    .air-prose a:hover { color: #c7d2fe; }
    .air-prose blockquote { border-left-color: #4338ca; color: #9ca3af; }
    .air-prose hr { border-top-color: rgba(255,255,255,.12); }
    .air-prose th, .air-prose td { border-color: rgba(255,255,255,.12); }
    .air-prose th { background: rgba(255,255,255,.05); }
    .air-scroll { scrollbar-color: #4b5563 transparent; }
    .air-scroll::-webkit-scrollbar-thumb { background: #4b5563; }
    @endif
</style>
@endpush

@section('content')

<div class="air-shell flex gap-4">

    {{-- ── Riwayat percakapan ───────────────────────────────────────────────
         Isinya hanya milik user yang login — tidak ada jalur untuk melihat
         riwayat orang lain, termasuk untuk admin (lihat AiResearchController). --}}
    <aside id="airHistory" class="hidden lg:flex w-64 shrink-0 flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center gap-2 px-3 py-3 border-b border-gray-100">
            <h3 class="flex-1 text-xs font-bold text-gray-900">History</h3>
            <button type="button" onclick="airNewChat()" title="Start a new chat"
                    class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-indigo-600 text-white text-[10px] font-semibold hover:bg-indigo-700 transition-all">
                <i class="fas fa-plus text-[9px]"></i> New
            </button>
            <button type="button" onclick="airToggleHistory()" title="Close"
                    class="lg:hidden w-6 h-6 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100">
                <i class="fas fa-xmark text-[11px]"></i>
            </button>
        </div>

        <div id="airHistoryList" class="flex-1 overflow-y-auto air-scroll px-2 py-2 space-y-1">
            <p class="px-2 py-6 text-center text-[11px] text-gray-400">Loading…</p>
        </div>

        <p class="px-3 py-2.5 border-t border-gray-100 text-[10px] text-gray-400 leading-snug">
            Only you can see these. Attachments aren't archived — reopened chats keep the text, not the images.
        </p>
    </aside>

    {{-- ── Panel chat ───────────────────────────────────────────────────────── --}}
    <section class="flex-1 min-w-0 flex flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden">

        {{-- Header --}}
        <header class="flex items-center gap-3 px-4 sm:px-5 py-3 border-b border-gray-100">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white shrink-0">
                <i class="fas fa-magnifying-glass-chart text-sm"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-gray-900 truncate">Research Assistant</h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-[10px] font-semibold text-indigo-700">
                        <i class="fas fa-globe text-[9px]"></i> Web access
                    </span>
                </div>
                <p class="text-[11px] text-gray-400 truncate">Answers come from the web — not from EcoSystem data</p>
            </div>

            <div class="flex items-center gap-1.5">
                <button type="button" onclick="airToggleHistory()" title="Conversation history"
                        class="lg:hidden w-8 h-8 inline-flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
                    <i class="fas fa-clock-rotate-left text-xs"></i>
                </button>
                <select id="airModel"
                        class="hidden sm:block px-2.5 py-1.5 border border-gray-200 rounded-xl text-[11px] font-semibold text-gray-600 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400">
                    <option value="default">Balanced</option>
                    <option value="deep">Deep research</option>
                </select>
                <button type="button" onclick="airNewChat()" title="New chat"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
                    <i class="fas fa-rotate-left text-xs"></i>
                </button>
            </div>
        </header>

        {{-- Thread --}}
        <div id="airThread" class="flex-1 overflow-y-auto air-scroll px-4 sm:px-6 py-5">

            {{-- Empty state --}}
            <div id="airEmptyState" class="h-full flex flex-col items-center justify-center text-center">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white mb-4">
                    <i class="fas fa-magnifying-glass text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900">What would you like to look up?</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-md">
                    Paste a screenshot (Ctrl + V) or attach an image — for example, ask "which TCODE is this?".
                    Answers are researched on the web and come with source links.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-6 w-full max-w-2xl text-left">
                    @foreach ([
                        ['fa-image',                'Identify a screenshot', 'Which SAP screen is this, and what is its TCODE?'],
                        ['fa-triangle-exclamation', 'Investigate an error',  'What causes the SAP dump "TSV_TNEW_PAGE_ALLOC_FAILED"?'],
                        ['fa-book',                 'Find documentation',    'What is the difference between BAPI_SALESORDER_CREATEFROMDAT1 and DAT2?'],
                        ['fa-code-compare',         'Compare options',       'Compare SAP S/4HANA Cloud Public vs Private Edition'],
                    ] as [$icon, $label, $prompt])
                        <button type="button" onclick="airUseSuggestion(@js($prompt))"
                                class="group flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/40 transition-all">
                            <span class="w-8 h-8 shrink-0 rounded-lg bg-gray-50 group-hover:bg-white flex items-center justify-center text-gray-400 group-hover:text-indigo-600 transition-all">
                                <i class="fas {{ $icon }} text-xs"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold text-gray-800">{{ $label }}</span>
                                <span class="block text-[11px] text-gray-500 mt-0.5 leading-snug">{{ $prompt }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Pesan disisipkan di sini --}}
            <div id="airMessages" class="space-y-5 hidden"></div>
        </div>

        {{-- Composer --}}
        <div class="border-t border-gray-100 px-3 sm:px-5 py-3">

            {{-- Chip lampiran --}}
            <div id="airAttachments" class="hidden flex-wrap gap-2 mb-2.5"></div>

            <div id="airDropzone"
                 class="air-dropzone rounded-2xl border border-gray-200 bg-white focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                <textarea id="airInput" rows="1" placeholder="Ask anything…  (Enter to send, Shift + Enter for a new line, Ctrl + V to paste an image)"
                          oninput="airAutoGrow(this)" onkeydown="airOnKeydown(event)"
                          class="w-full resize-none bg-transparent px-4 pt-3 pb-1 text-sm text-gray-800 placeholder-gray-400 focus:outline-none air-scroll"></textarea>

                <div class="flex items-center gap-1.5 px-2.5 pb-2.5 pt-1">
                    <input type="file" id="airFile" class="hidden" multiple accept="image/*,application/pdf" onchange="airOnFilesPicked(this)">

                    <button type="button" onclick="document.getElementById('airFile').click()" title="Attach an image or PDF (max 2 files, 5 MB each)"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-paperclip text-sm"></i>
                    </button>

                    <span class="flex-1"></span>

                    <span id="airCounter" class="hidden sm:inline text-[10px] text-gray-400 tabular-nums mr-1">0 / 4000</span>

                    <button type="button" id="airStopBtn" onclick="airStop()"
                            class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-all">
                        <i class="fas fa-stop text-[10px]"></i> Stop
                    </button>

                    <button type="button" id="airSendBtn" onclick="airSend()" disabled
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <i class="fas fa-paper-plane text-[10px]"></i> Send
                    </button>
                </div>
            </div>

            <p class="mt-2 text-[10px] text-gray-400 text-center">
                Search results can be wrong — always check the source links before acting on them.
                For internal data (tickets, SLA, projects), use the AI Assistant page.
            </p>
        </div>
    </section>
</div>

{{-- Lightbox gambar (di luar thread supaya tidak ikut ter-scroll) --}}
<div id="airLightbox" class="hidden fixed inset-0 z-[70] items-center justify-center p-6" onclick="airCloseLightbox(event)">
    <button type="button" onclick="airCloseLightbox()" title="Close"
            class="absolute top-4 right-4 w-10 h-10 inline-flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-all">
        <i class="fas fa-xmark"></i>
    </button>
    <figure class="max-w-full max-h-full flex flex-col items-center gap-3">
        <img id="airLightboxImg" src="" alt="">
        <figcaption id="airLightboxCaption" class="text-xs text-white/70"></figcaption>
    </figure>
</div>
@endsection

@push('scripts')
<script>
/* ──────────────────────────────────────────────────────────────────────────
   AI Research.

   Kembar dengan halaman AI Assistant, dengan empat tambahan:
     1. Tempel gambar dari clipboard (Ctrl + V) dan drag-and-drop file.
     2. Baris status progres selagi model menjalankan pencarian di server.
     3. Daftar sumber (URL) yang dipakai, dirender di bawah tiap jawaban.
     4. Jawaban dirender sebagai Markdown, dan lampiran gambar tampil sebagai
        thumbnail yang bisa diklik untuk dibuka besar.

   Satu-satunya titik sentuh backend adalah airSendToBackend().
   ────────────────────────────────────────────────────────────────────────── */

const AIR_MAX_CHARS = 4000;

/* Harus sama dengan AiResearchController::MAX_ATTACHMENTS / MAX_ATTACHMENT_KB.
   Dicek di sini hanya supaya user dapat pesan yang jelas — server tetap yang
   menegakkannya (lihat catatan alasan batas ini di controller). */
const AIR_MAX_FILES = 2;
const AIR_MAX_FILE_MB = 5;
const AIR_CHAT_ENDPOINT = @json(route('ai-research.chat'));
const AIR_LIST_ENDPOINT = @json(route('ai-research.conversations'));
/* {id} diganti saat dipakai — route() butuh parameter, dan menyusun URL-nya
   di sisi JS dengan string mentah gampang salah kalau prefiks app berubah. */
const AIR_CONV_ENDPOINT   = @json(route('ai-research.conversation', ['conversation' => '__ID__']));
const AIR_DELETE_ENDPOINT = @json(route('ai-research.conversation.delete', ['conversation' => '__ID__']));
const AIR_CONVERSATION_STORAGE_KEY = 'ai_research_conversation_id';

let airFiles = [];      // File[] yang dipilih untuk pesan berikutnya
let airBusy  = false;   // sedang menunggu balasan
let airAbort = null;    // AbortController pembatal request berjalan
let airConversationId = null;

/* Object URL pratinjau gambar. Dipegang sampai percakapan direset — bubble
   yang sudah terkirim masih memakai URL yang sama dengan chip composer. */
const airPreviews = new Map();  // File → object URL
let airPreviewUrls = [];

function airEnsureConversationId() {
    if (airConversationId) return airConversationId;

    airConversationId = sessionStorage.getItem(AIR_CONVERSATION_STORAGE_KEY);
    if (!airConversationId) {
        airConversationId = crypto.randomUUID();
        sessionStorage.setItem(AIR_CONVERSATION_STORAGE_KEY, airConversationId);
    }
    return airConversationId;
}

function airCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/* ── Composer ──────────────────────────────────────────────────────────── */

function airAutoGrow(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';

    const len = el.value.length;
    document.getElementById('airCounter').textContent = len + ' / ' + AIR_MAX_CHARS;
    document.getElementById('airSendBtn').disabled = airBusy || (len === 0 && airFiles.length === 0);
}

function airOnKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        airSend();
    }
}

function airUseSuggestion(text) {
    const input = document.getElementById('airInput');
    input.value = text;
    input.focus();
    airAutoGrow(input);
}

/* ── Lampiran ──────────────────────────────────────────────────────────── */

function airIsImage(file) {
    return (file.type || '').startsWith('image/');
}

/** Object URL pratinjau, dibuat sekali per File. */
function airPreviewUrl(file) {
    if (!airPreviews.has(file)) {
        const url = URL.createObjectURL(file);
        airPreviews.set(file, url);
        airPreviewUrls.push(url);
    }
    return airPreviews.get(file);
}

function airAddFiles(files) {
    let incoming = Array.from(files || []);
    if (incoming.length === 0) return;

    // Tolak yang kebesaran di sini, supaya user tahu berkas mana yang bermasalah
    // — kalau dibiarkan sampai server, yang muncul cuma "HTTP 422".
    const tooBig = incoming.filter(f => f.size > AIR_MAX_FILE_MB * 1024 * 1024);
    if (tooBig.length > 0) {
        incoming = incoming.filter(f => f.size <= AIR_MAX_FILE_MB * 1024 * 1024);
        showToast(
            tooBig.map(f => f.name).join(', ') + ' — larger than ' + AIR_MAX_FILE_MB + ' MB, skipped.',
            'warning',
        );
    }
    if (incoming.length === 0) return;

    const room = AIR_MAX_FILES - airFiles.length;
    if (room <= 0) {
        showToast('You can attach at most ' + AIR_MAX_FILES + ' files per message.', 'warning');
        return;
    }
    if (incoming.length > room) {
        showToast('Only the first ' + room + ' file(s) were added (max ' + AIR_MAX_FILES + ').', 'warning');
    }

    airFiles = airFiles.concat(incoming.slice(0, room));
    airRenderAttachments();
    airAutoGrow(document.getElementById('airInput'));
}

function airOnFilesPicked(input) {
    airAddFiles(input.files);
    input.value = '';  // supaya file yang sama bisa dipilih lagi
}

function airRemoveFile(index) {
    airFiles.splice(index, 1);
    airRenderAttachments();
    airAutoGrow(document.getElementById('airInput'));
}

function airRenderAttachments() {
    const box = document.getElementById('airAttachments');
    box.classList.toggle('hidden', airFiles.length === 0);
    box.classList.toggle('flex', airFiles.length > 0);

    box.innerHTML = airFiles.map((file, i) => {
        const thumb = airIsImage(file)
            ? `<img src="${airPreviewUrl(file)}" alt="" class="air-thumb w-9 h-9 shrink-0"
                    data-air-full="${airPreviewUrl(file)}" data-air-caption="${airEsc(file.name)}">`
            : `<i class="fas ${airFileIcon(file.name)} text-xs text-gray-400"></i>`;

        return `
        <span class="inline-flex items-center gap-2 pl-2 pr-1.5 py-1.5 rounded-xl border border-gray-200 bg-gray-50 max-w-[240px]">
            ${thumb}
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold text-gray-700 truncate">${airEsc(file.name)}</span>
                <span class="block text-[10px] text-gray-400">${airFileSize(file.size)}</span>
            </span>
            <button type="button" onclick="airRemoveFile(${i})" title="Remove"
                    class="w-5 h-5 shrink-0 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-all">
                <i class="fas fa-xmark text-[10px]"></i>
            </button>
        </span>`;
    }).join('');
}

function airFileIcon(name) {
    const ext = (name.split('.').pop() || '').toLowerCase();
    if (['png','jpg','jpeg','gif','webp','bmp','svg'].includes(ext)) return 'fa-file-image';
    if (ext === 'pdf')                                                return 'fa-file-pdf';
    return 'fa-file-lines';
}

function airFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

/**
 * Tempel gambar dari clipboard.
 *
 * Screenshot yang di-paste datang sebagai File tanpa nama (name === ''), jadi
 * kita beri nama sendiri — kalau tidak, chip lampiran tampil kosong dan
 * backend kehilangan ekstensi untuk ditebak.
 */
function airOnPaste(e) {
    const items = Array.from(e.clipboardData?.items || []);
    const images = items
        .filter(item => item.kind === 'file' && item.type.startsWith('image/'))
        .map(item => item.getAsFile())
        .filter(Boolean)
        .map(file => file.name
            ? file
            : new File([file], 'screenshot-' + Date.now() + '.' + (file.type.split('/')[1] || 'png'), { type: file.type }));

    if (images.length === 0) return;   // paste teks biasa: biarkan default

    e.preventDefault();
    airAddFiles(images);
}

function airOnDragOver(e) {
    e.preventDefault();
    document.getElementById('airDropzone').classList.add('is-dragging');
}

function airOnDragLeave() {
    document.getElementById('airDropzone').classList.remove('is-dragging');
}

function airOnDrop(e) {
    e.preventDefault();
    airOnDragLeave();
    airAddFiles(e.dataTransfer?.files);
}

/* ── Lightbox ──────────────────────────────────────────────────────────── */

function airOpenLightbox(url, caption) {
    const box = document.getElementById('airLightbox');
    document.getElementById('airLightboxImg').src = url;
    document.getElementById('airLightboxCaption').textContent = caption || '';
    box.classList.remove('hidden');
    box.classList.add('flex');
}

/** Klik di area gelap (bukan pada gambar) menutup lightbox. */
function airCloseLightbox(e) {
    if (e && e.target.tagName === 'IMG') return;

    const box = document.getElementById('airLightbox');
    box.classList.add('hidden');
    box.classList.remove('flex');
    document.getElementById('airLightboxImg').src = '';
}

/* ── Markdown ──────────────────────────────────────────────────────────── */

/* Penanda sementara saat mengurai. Sengaja hanya huruf/angka/@ — karakter yang
   TIDAK diubah oleh airEsc() — supaya penanda tetap utuh setelah escaping.
   (Jangan pakai byte kontrol seperti NUL: itu membuat file dianggap biner.) */
const AIR_PH_CODE = '@@AIRCODE';
const AIR_PH_INLINE = '@@AIRINL';

function airEsc(s) {
    return String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function airLink(href, label) {
    return `<a href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`;
}

/**
 * Markdown inline → HTML. Teks yang masuk SUDAH di-escape.
 *
 * Emphasis dengan garis bawah (_x_ / __x__) SENGAJA tidak didukung: domain
 * halaman ini penuh identifier seperti BAPI_SALESORDER_CREATEFROMDAT1 dan
 * TSV_TNEW_PAGE_ALLOC_FAILED, yang akan hancur kalau underscore dianggap
 * penanda gaya. Bintang (**x** / *x*) sudah cukup — itu yang dipakai model.
 */
function airInline(t) {
    const stash = [];
    const keep = html => AIR_PH_INLINE + (stash.push(html) - 1) + '@@';

    // Kode inline lebih dulu: isinya harus kebal dari aturan lain.
    t = t.replace(/`([^`\n]+)`/g, (m, code) => keep('<code>' + code + '</code>'));

    // [teks](url) — hanya http/https.
    t = t.replace(/\[([^\]\n]*)\]\((https?:\/\/[^\s)]+)\)/g,
        (m, label, href) => keep(airLink(href, label || href)));

    // URL telanjang. Tanda baca di ujung dikeluarkan dari tautan.
    t = t.replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, (m, lead, url) => {
        const tail = (url.match(/[.,;:!?]+$/) || [''])[0];
        const clean = url.slice(0, url.length - tail.length);
        return lead + keep(airLink(clean, clean)) + tail;
    });

    t = t.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    t = t.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
    t = t.replace(/~~([^~\n]+)~~/g, '<del>$1</del>');

    return t.replace(new RegExp(AIR_PH_INLINE + '(\\d+)@@', 'g'), (m, n) => stash[+n]);
}

/**
 * Markdown blok → HTML.
 *
 * Ditulis sendiri, bukan memakai pustaka: proyek ini tidak punya build step
 * (Tailwind pun lewat CDN), dan kebutuhannya sempit — hanya bentuk yang benar
 * dipakai model. Urutannya penting: blok kode dipisahkan SEBELUM escaping,
 * sisanya di-escape lebih dulu supaya tidak ada HTML dari model yang lolos.
 */
function airMarkdown(src) {
    const blocks = [];

    // Blok kode berpagar. Pagar penutup boleh belum ada (masih streaming).
    let s = String(src).replace(/```[a-zA-Z0-9_+-]*\n?([\s\S]*?)(?:```|$)/g, (m, body) => {
        blocks.push(body.replace(/\n+$/, ''));
        return '\n' + AIR_PH_CODE + (blocks.length - 1) + '@@\n';
    });

    s = airEsc(s);

    const codeLine = new RegExp('^' + AIR_PH_CODE + '(\\d+)@@$');
    const lines = s.split('\n');
    const out = [];
    const stack = [];          // list yang sedang terbuka: [{ tag, indent }]
    let para = [];

    const closeLists = (toIndent = 0) => {
        while (stack.length && stack[stack.length - 1].indent >= toIndent) {
            out.push('</li></' + stack.pop().tag + '>');
        }
    };
    const flushPara = () => {
        if (para.length) {
            out.push('<p>' + airInline(para.join('<br>')) + '</p>');
            para = [];
        }
    };
    const flushAll = () => { flushPara(); closeLists(0); };

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        let m;

        if (!line.trim()) { flushPara(); continue; }

        // Blok kode
        if ((m = line.match(codeLine))) {
            flushAll();
            out.push('<pre><code>' + airEsc(blocks[+m[1]]) + '</code></pre>');
            continue;
        }

        // Garis pemisah
        if (/^\s*([-*_])\s*\1\s*\1[\s\-*_]*$/.test(line)) {
            flushAll();
            out.push('<hr>');
            continue;
        }

        // Heading
        if ((m = line.match(/^(#{1,6})\s+(.*)$/))) {
            flushAll();
            const level = m[1].length;
            out.push(`<h${level}>` + airInline(m[2].trim()) + `</h${level}>`);
            continue;
        }

        // Kutipan ('>' sudah jadi '&gt;' setelah escaping)
        if ((m = line.match(/^\s*&gt;\s?(.*)$/))) {
            flushAll();
            const quote = [m[1]];
            while (i + 1 < lines.length && (m = lines[i + 1].match(/^\s*&gt;\s?(.*)$/))) {
                quote.push(m[1]);
                i++;
            }
            out.push('<blockquote>' + airInline(quote.join('<br>')) + '</blockquote>');
            continue;
        }

        // Tabel: baris pipa diikuti baris pemisah (|---|---|)
        if (line.includes('|') && /^\s*\|?[\s:|-]*-[\s:|-]*\|?\s*$/.test(lines[i + 1] || '')) {
            flushAll();
            out.push(airTable(lines, i));
            i++;                                                   // lewati baris pemisah
            while (i + 1 < lines.length && lines[i + 1].includes('|')) i++;
            continue;
        }

        // Item list (berpoin atau bernomor), termasuk sarang lewat indentasi
        if ((m = line.match(/^(\s*)([-*+]|\d+[.)])\s+(.*)$/))) {
            flushPara();

            const indent = m[1].replace(/\t/g, '  ').length;
            const tag = /\d/.test(m[2]) ? 'ol' : 'ul';
            const top = stack[stack.length - 1];

            if (!top || indent > top.indent) {
                stack.push({ tag, indent });
                out.push('<' + tag + '><li>');
            } else {
                closeLists(indent + 1);
                if (!stack.length || stack[stack.length - 1].tag !== tag) {
                    closeLists(indent);
                    stack.push({ tag, indent });
                    out.push('<' + tag + '><li>');
                } else {
                    out.push('</li><li>');
                }
            }

            out.push(airInline(m[3]));
            continue;
        }

        // Lanjutan teks di dalam item list
        if (stack.length && /^\s{2,}/.test(line)) {
            out.push('<br>' + airInline(line.trim()));
            continue;
        }

        closeLists(0);
        para.push(line.trim());
    }

    flushAll();
    return out.join('');
}

/** Render tabel pipa mulai dari baris header di index `start`. */
function airTable(lines, start) {
    const cells = row => row.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|').map(c => c.trim());

    const head = cells(lines[start]);
    const body = [];

    for (let i = start + 2; i < lines.length && lines[i].includes('|'); i++) {
        body.push(cells(lines[i]));
    }

    return '<div class="air-table-wrap"><table><thead><tr>'
        + head.map(c => '<th>' + airInline(c) + '</th>').join('')
        + '</tr></thead><tbody>'
        + body.map(r => '<tr>' + r.map(c => '<td>' + airInline(c) + '</td>').join('') + '</tr>').join('')
        + '</tbody></table></div>';
}

/* ── Thread ────────────────────────────────────────────────────────────── */

/* Tanpa argumen = sekarang. Riwayat yang dimuat ulang mengirim waktu aslinya —
   kalau tidak, percakapan bulan lalu tampil seolah baru saja terjadi. */
function airTime(at) {
    const d = at ? new Date(at) : new Date();
    if (isNaN(d)) return '';

    const sameDay = d.toDateString() === new Date().toDateString();
    const clock = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    return sameDay ? clock : d.toLocaleDateString([], { day: '2-digit', month: 'short' }) + ' ' + clock;
}

function airShowThread() {
    document.getElementById('airEmptyState').classList.add('hidden');
    document.getElementById('airMessages').classList.remove('hidden');
}

function airScrollToBottom() {
    const thread = document.getElementById('airThread');
    thread.scrollTop = thread.scrollHeight;
}

function airAppendUser(text, files) {
    const images = files.filter(airIsImage);
    const others = files.filter(f => !airIsImage(f));

    // Gambar tampil utuh sebagai thumbnail; file lain tetap sebagai chip nama.
    const thumbs = images.length === 0 ? '' : `
        <div class="flex flex-wrap gap-1.5 justify-end ${text ? 'mt-2' : ''}">
            ${images.map(f => `
                <img src="${airPreviewUrl(f)}" alt="${airEsc(f.name)}" title="${airEsc(f.name)}"
                     class="air-thumb w-28 h-28 border border-white/25"
                     data-air-full="${airPreviewUrl(f)}" data-air-caption="${airEsc(f.name)}">`).join('')}
        </div>`;

    const chips = others.length === 0 ? '' : `
        <div class="flex flex-wrap gap-1.5 justify-end mt-2">
            ${others.map(f => `
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-white/15 border border-white/20">
                    <i class="fas ${airFileIcon(f.name)} text-[10px]"></i>
                    <span class="text-[10px] font-medium truncate max-w-[140px]">${airEsc(f.name)}</span>
                </span>`).join('')}
        </div>`;

    const body = text ? `<p class="whitespace-pre-wrap break-words">${airEsc(text)}</p>` : '';

    document.getElementById('airMessages').insertAdjacentHTML('beforeend', `
        <div class="air-bubble-in flex justify-end gap-3">
            <div class="max-w-[85%] sm:max-w-[70%]">
                <div class="bg-indigo-600 text-white text-sm rounded-2xl rounded-br-md px-4 py-2.5">
                    ${body}${thumbs}${chips}
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-right">${airTime()}</p>
            </div>
        </div>
    `);
    airScrollToBottom();
}

/** Bubble assistant kosong + indikator mengetik. Kembalikan id-nya. */
/* Hidrasi riwayat membuat banyak bubble dalam milidetik yang sama, jadi id-nya
   TIDAK boleh berbasis Date.now() — id kembar membuat delta jawaban baru
   mendarat di bubble lama. */
let airMsgSeq = 0;

function airAppendAssistantPending(at) {
    const id = 'airMsg' + (++airMsgSeq);

    document.getElementById('airMessages').insertAdjacentHTML('beforeend', `
        <div class="air-bubble-in flex gap-3" id="${id}">
            <div class="w-8 h-8 shrink-0 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white">
                <i class="fas fa-magnifying-glass text-[11px]"></i>
            </div>
            <div class="max-w-[85%] sm:max-w-[75%] min-w-0">
                <div class="air-status hidden items-center gap-2 mb-1.5 text-[11px] text-indigo-600">
                    <i class="fas fa-circle-notch fa-spin text-[10px]"></i>
                    <span class="air-status-label"></span>
                </div>
                <div class="bg-gray-50 border border-gray-100 text-sm text-gray-800 rounded-2xl rounded-bl-md px-4 py-3">
                    <div class="air-body air-prose">
                        <span class="inline-flex items-center gap-1 py-1">
                            <span class="air-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <span class="air-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <span class="air-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        </span>
                    </div>
                </div>
                <div class="air-sources hidden mt-2"></div>
                <div class="air-actions hidden items-center gap-1 mt-1.5">
                    <button type="button" onclick="airCopy('${id}')" title="Copy"
                            class="w-6 h-6 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-copy text-[10px]"></i>
                    </button>
                    <span class="text-[10px] text-gray-400 ml-1">${airTime(at)}</span>
                </div>
            </div>
        </div>
    `);
    airScrollToBottom();
    return id;
}

/** Baris progres di atas bubble ("Searching the web…"). */
function airSetStatus(id, label) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    const status = wrap.querySelector('.air-status');
    status.querySelector('.air-status-label').textContent = label;
    status.classList.remove('hidden');
    status.classList.add('flex');
    airScrollToBottom();
}

function airClearStatus(id) {
    const status = document.querySelector('#' + id + ' .air-status');
    if (!status) return;
    status.classList.add('hidden');
    status.classList.remove('flex');
}

/** Daftar sumber di bawah bubble. Dipanggil berkali-kali: gabungkan per URL. */
function airRenderSources(id, items) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    const box = wrap.querySelector('.air-sources');
    const known = JSON.parse(box.dataset.items || '[]');

    items.forEach(item => {
        if (item.url && !known.some(k => k.url === item.url)) known.push(item);
    });
    box.dataset.items = JSON.stringify(known);

    if (known.length === 0) return;

    box.innerHTML = `
        <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Sources</div>
        <div class="flex flex-wrap gap-1.5">
            ${known.map((s, i) => `
                <a href="${airEsc(s.url)}" target="_blank" rel="noopener noreferrer" title="${airEsc(s.title || s.url)}"
                   class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-gray-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/40 transition-all max-w-[260px]">
                    <span class="text-[10px] font-bold text-gray-400">${i + 1}</span>
                    <span class="text-[10px] text-gray-600 truncate">${airEsc(airHostOf(s.url))}</span>
                </a>`).join('')}
        </div>`;
    box.classList.remove('hidden');
    airScrollToBottom();
}

function airHostOf(url) {
    try { return new URL(url).hostname.replace(/^www\./, ''); }
    catch { return url; }
}

/**
 * Bertahap: tambahkan potongan teks lalu render ulang seluruh jawaban.
 *
 * Sengaja render ulang dari awal, bukan menambal ujungnya: penanda markdown
 * bisa datang terpotong antar-delta ("**Cata" … "tan**"), jadi hanya teks utuh
 * yang bisa diurai dengan benar.
 */
function airAppendAssistantDelta(id, deltaText) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    const body = wrap.querySelector('.air-body');
    if (!body.dataset.streaming) {
        body.dataset.streaming = '1';
        body.dataset.text = '';
    }

    body.dataset.text += deltaText;
    body.innerHTML = airMarkdown(body.dataset.text);
    airScrollToBottom();
}

function airResolveAssistant(id, text, isError) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    airClearStatus(id);

    const body = wrap.querySelector('.air-body');
    // Kalau sudah ada teks yang di-stream dan ini bukan error, biarkan apa
    // adanya (text di sini kosong pada jalur sukses — lihat airSendToBackend).
    if (!(body.dataset.streaming && !isError)) {
        body.innerHTML = `<p class="${isError ? 'text-red-600' : ''}">${airEsc(text)}</p>`;
    }

    const actions = wrap.querySelector('.air-actions');
    actions.classList.remove('hidden');
    actions.classList.add('flex');

    airScrollToBottom();
}

function airCopy(id) {
    const body = document.querySelector('#' + id + ' .air-body');
    if (!body) return;
    // Salin sumber Markdown-nya kalau ada, bukan hasil render — lebih berguna
    // untuk ditempel ke ticket, email, atau dokumen.
    const text = body.dataset.text || body.innerText;
    navigator.clipboard.writeText(text.trim())
        .then(() => showToast('Response copied.', 'success'))
        .catch(() => showToast('Could not copy the response.', 'error'));
}

/* ── Kirim ─────────────────────────────────────────────────────────────── */

function airSend() {
    if (airBusy) return;

    const input = document.getElementById('airInput');
    const text  = input.value.trim();
    if (!text && airFiles.length === 0) return;

    if (text.length > AIR_MAX_CHARS) {
        showToast('Message is too long (max ' + AIR_MAX_CHARS + ' characters).', 'warning');
        return;
    }

    const files = airFiles.slice();
    const model = document.getElementById('airModel')?.value || 'default';

    airShowThread();
    airAppendUser(text, files);

    input.value = '';
    airFiles = [];
    airRenderAttachments();
    airAutoGrow(input);

    airSetBusy(true);
    const pendingId = airAppendAssistantPending();
    airSetStatus(pendingId, 'Getting ready…');

    airSendToBackend(text, files, model, pendingId)
        .then(() => airResolveAssistant(pendingId, '', false))
        .catch(err => {
            // Stop oleh user: pertahankan teks yang sudah masuk.
            const stopped = err.name === 'AbortError';
            airResolveAssistant(pendingId, stopped ? '' : (err.message || 'Something went wrong.'), !stopped);
        })
        .finally(() => {
            airSetBusy(false);
            airLoadHistory();   // judul percakapan baru & urutan terbaru
        });
}

function airSetBusy(busy) {
    airBusy = busy;
    document.getElementById('airSendBtn').classList.toggle('hidden', busy);
    document.getElementById('airStopBtn').classList.toggle('hidden', !busy);
    airAutoGrow(document.getElementById('airInput'));
}

function airStop() {
    if (airAbort) airAbort.abort();
}

/**
 * SATU-SATUNYA titik sentuh backend.
 *
 * Streaming Server-Sent Events dari POST /ai-research/chat:
 *   delta   → potongan teks jawaban
 *   status  → label progres selagi server tool berjalan
 *   sources → daftar {url, title} sumber yang dipakai
 *   done / error → penutup stream (backend selalu mengirim salah satunya)
 */
async function airSendToBackend(text, files, model, pendingId) {
    const controller = new AbortController();
    airAbort = controller;

    const form = new FormData();
    form.append('conversation_id', airEnsureConversationId());
    form.append('message', text);
    form.append('model', model);
    files.forEach(file => form.append('files[]', file));

    const response = await fetch(AIR_CHAT_ENDPOINT, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': airCsrfToken(),
            'Accept': 'text/event-stream',
        },
        body: form,
        signal: controller.signal,
    });

    if (!response.ok || !response.body) {
        throw new Error('Could not reach the assistant (HTTP ' + response.status + ').');
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let sawError = null;

    try {
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            let boundary;
            while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                const frame = buffer.slice(0, boundary);
                buffer = buffer.slice(boundary + 2);

                let eventName = 'message';
                let dataLine = '';
                frame.split('\n').forEach(line => {
                    if (line.startsWith('event:')) eventName = line.slice(6).trim();
                    if (line.startsWith('data:')) dataLine = line.slice(5).trim();
                });
                if (!dataLine) continue;

                let payload;
                try { payload = JSON.parse(dataLine); } catch { continue; }

                if (eventName === 'delta' && payload.text) {
                    airClearStatus(pendingId);
                    airAppendAssistantDelta(pendingId, payload.text);
                } else if (eventName === 'status' && payload.label) {
                    airSetStatus(pendingId, payload.label);
                } else if (eventName === 'sources' && Array.isArray(payload.items)) {
                    airRenderSources(pendingId, payload.items);
                } else if (eventName === 'error') {
                    sawError = payload.message || 'Something went wrong.';
                }
                // 'done' tidak perlu ditangani — promise luar tinggal resolve.
            }
        }
    } finally {
        airAbort = null;
    }

    if (sawError) throw new Error(sawError);
}

/* ── Percakapan ────────────────────────────────────────────────────────── */

/** Kosongkan layar tanpa menyentuh identitas percakapan. */
function airResetThread() {
    if (airBusy) airStop();

    document.getElementById('airMessages').innerHTML = '';
    document.getElementById('airMessages').classList.add('hidden');
    document.getElementById('airEmptyState').classList.remove('hidden');

    airFiles = [];
    airRenderAttachments();

    // Bubble lama sudah dibuang, jadi object URL pratinjau boleh dilepas.
    airPreviewUrls.forEach(URL.revokeObjectURL);
    airPreviewUrls = [];
    airPreviews.clear();

    const input = document.getElementById('airInput');
    input.value = '';
    airAutoGrow(input);
}

function airNewChat() {
    airResetThread();

    // Percakapan lama TIDAK dihapus — ia tetap ada di sidebar. Yang dilepas
    // hanya kaitan tab ini dengannya; giliran berikutnya membuat percakapan baru.
    airConversationId = null;
    sessionStorage.removeItem(AIR_CONVERSATION_STORAGE_KEY);
    airMarkActive(null);

    document.getElementById('airInput').focus();
}

/* ── Riwayat ───────────────────────────────────────────────────────────
   Percakapan disimpan di server dan hanya bisa dilihat pemiliknya. Lampiran
   sengaja TIDAK ikut diarsipkan: byte gambarnya hanya hidup selama konteks di
   cache masih berlaku (1 jam). Karena itu percakapan lama tetap bisa dibaca,
   tapi melanjutkannya dengan pertanyaan tentang gambar butuh unggah ulang.
   ────────────────────────────────────────────────────────────────────── */

async function airLoadHistory() {
    const box = document.getElementById('airHistoryList');

    try {
        const res = await fetch(AIR_LIST_ENDPOINT, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        airRenderHistory((await res.json()).items || []);
    } catch {
        box.innerHTML = '<p class="px-2 py-6 text-center text-[11px] text-gray-400">Could not load history.</p>';
    }
}

function airRenderHistory(items) {
    const box = document.getElementById('airHistoryList');

    if (items.length === 0) {
        box.innerHTML = '<p class="px-2 py-6 text-center text-[11px] text-gray-400">No saved conversations yet.</p>';
        return;
    }

    box.innerHTML = items.map(item => `
        <div class="air-history-item group flex items-start gap-1 rounded-xl border border-transparent hover:bg-gray-50 transition-all"
             data-air-conv="${airEsc(item.id)}">
            <button type="button" onclick="airOpenConversation('${airEsc(item.id)}')"
                    class="flex-1 min-w-0 text-left px-2.5 py-2">
                <span class="block text-[11px] font-semibold text-gray-700 truncate">${airEsc(item.title)}</span>
                <span class="block text-[10px] text-gray-400 mt-0.5">${airEsc(airHistoryDate(item.updated_at))}</span>
            </button>
            <button type="button" onclick="airDeleteConversation('${airEsc(item.id)}', ${airAttr(item.title)})" title="Delete"
                    class="w-6 h-6 mt-2 mr-1.5 shrink-0 inline-flex items-center justify-center rounded-lg text-gray-300 opacity-0 group-hover:opacity-100 hover:bg-gray-200 hover:text-gray-600 transition-all">
                <i class="fas fa-trash text-[9px]"></i>
            </button>
        </div>`).join('');

    airMarkActive(airConversationId);
}

/* Judul percakapan adalah teks user, jadi ia bisa memuat kutip atau backslash.
   JSON.stringify membuat literal JS yang aman, airEsc menjaganya tetap utuh
   sebagai atribut HTML. */
function airAttr(value) {
    return airEsc(JSON.stringify(String(value ?? '')));
}

function airHistoryDate(iso) {
    if (!iso) return '';

    const d = new Date(iso);
    if (isNaN(d)) return '';

    const today = new Date();
    if (d.toDateString() === today.toDateString()) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    const sameYear = d.getFullYear() === today.getFullYear();
    return d.toLocaleDateString([], sameYear
        ? { day: '2-digit', month: 'short' }
        : { day: '2-digit', month: 'short', year: 'numeric' });
}

function airMarkActive(id) {
    document.querySelectorAll('.air-history-item').forEach(el => {
        el.classList.toggle('is-active', !!id && el.dataset.airConv === id);
    });
}

/**
 * Buka percakapan tersimpan.
 *
 * silent = dipanggil otomatis saat halaman dimuat ulang; percakapan yang
 * belum sempat diarsipkan (belum ada jawaban) wajar tidak ditemukan, dan
 * memunculkan toast error untuk itu hanya membingungkan.
 */
async function airOpenConversation(id, silent = false) {
    try {
        const res = await fetch(AIR_CONV_ENDPOINT.replace('__ID__', encodeURIComponent(id)),
            { headers: { 'Accept': 'application/json' } });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json();

        airResetThread();

        airConversationId = data.id;
        sessionStorage.setItem(AIR_CONVERSATION_STORAGE_KEY, data.id);

        const model = document.getElementById('airModel');
        if (model && data.model_tier) model.value = data.model_tier;

        if ((data.messages || []).length > 0) {
            airShowThread();
            data.messages.forEach(m => m.role === 'user'
                ? airAppendUserStored(m.content, m.attachments, m.at)
                : airAppendAssistantStored(m.content, m.sources, m.at));
        }

        airMarkActive(data.id);
        airCloseHistoryDrawer();
        document.getElementById('airInput').focus();
    } catch {
        if (!silent) showToast('Could not open that conversation.', 'error');
    }
}

async function airDeleteConversation(id, title) {
    if (!confirm('Delete "' + title + '"? This cannot be undone.')) return;

    try {
        const res = await fetch(AIR_DELETE_ENDPOINT.replace('__ID__', encodeURIComponent(id)), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': airCsrfToken(), 'Accept': 'application/json' },
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        // Menghapus percakapan yang sedang dibuka juga harus membersihkan layar,
        // kalau tidak user masih melihat isi sesuatu yang sudah tidak ada.
        if (airConversationId === id) airNewChat();

        airLoadHistory();
        showToast('Conversation deleted.', 'success');
    } catch {
        showToast('Could not delete that conversation.', 'error');
    }
}

/** Bubble user dari arsip: teksnya tersimpan, lampirannya tidak. */
function airAppendUserStored(text, attachmentCount, at) {
    const note = attachmentCount > 0 ? `
        <div class="flex items-center gap-1.5 justify-end ${text ? 'mt-2' : ''} text-[10px] text-white/70">
            <i class="fas fa-paperclip text-[9px]"></i>
            ${attachmentCount} attachment${attachmentCount > 1 ? 's' : ''} — not kept in history
        </div>` : '';

    const body = text ? `<p class="whitespace-pre-wrap break-words">${airEsc(text)}</p>` : '';

    document.getElementById('airMessages').insertAdjacentHTML('beforeend', `
        <div class="flex justify-end gap-3">
            <div class="max-w-[85%] sm:max-w-[70%]">
                <div class="bg-indigo-600 text-white text-sm rounded-2xl rounded-br-md px-4 py-2.5">
                    ${body}${note}
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-right">${airTime(at)}</p>
            </div>
        </div>
    `);
    airScrollToBottom();
}

/** Bubble assistant dari arsip — bentuknya sama dengan hasil streaming. */
function airAppendAssistantStored(text, sources, at) {
    const id = airAppendAssistantPending(at);
    const wrap = document.getElementById(id);
    const body = wrap.querySelector('.air-body');

    // dataset.text diisi supaya tombol Copy tetap menyalin sumber Markdown-nya.
    body.dataset.streaming = '1';
    body.dataset.text = text || '';
    body.innerHTML = airMarkdown(body.dataset.text);

    if (Array.isArray(sources) && sources.length > 0) airRenderSources(id, sources);

    const actions = wrap.querySelector('.air-actions');
    actions.classList.remove('hidden');
    actions.classList.add('flex');

    airScrollToBottom();
}

/* ── Drawer riwayat (layar sempit) ─────────────────────────────────────── */

function airToggleHistory() {
    document.getElementById('airHistory').classList.toggle('is-open');
}

function airCloseHistoryDrawer() {
    document.getElementById('airHistory').classList.remove('is-open');
}

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('airInput');
    airAutoGrow(input);
    input.focus();

    airLoadHistory();

    // Refresh halaman tidak lagi mengosongkan layar: percakapan tab ini
    // dimuat ulang dari arsip. Silent, karena percakapan yang baru dimulai
    // (belum ada jawaban) memang belum diarsipkan.
    const saved = sessionStorage.getItem(AIR_CONVERSATION_STORAGE_KEY);
    if (saved) airOpenConversation(saved, true);

    // Paste didengarkan di level dokumen supaya screenshot bisa ditempel
    // tanpa harus mengklik textarea lebih dulu.
    document.addEventListener('paste', airOnPaste);

    // Klik thumbnail → lightbox. Delegasi, karena thumbnail dibuat dinamis;
    // nama file masuk lewat data-attribute (bukan onclick inline) supaya nama
    // yang mengandung kutip tidak merusak markup.
    document.addEventListener('click', e => {
        const thumb = e.target.closest?.('img.air-thumb');
        if (thumb) airOpenLightbox(thumb.dataset.airFull, thumb.dataset.airCaption);
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') airCloseLightbox();
    });

    const zone = document.getElementById('airDropzone');
    zone.addEventListener('dragover', airOnDragOver);
    zone.addEventListener('dragleave', airOnDragLeave);
    zone.addEventListener('drop', airOnDrop);
});
</script>
@endpush
