@extends('dashboard')

@section('title', 'AI Assistant')
@section('page-title', 'AI Assistant')
@section('page-subtitle', 'Ask questions, draft replies, and summarize your work')

@push('styles')
<style>
    /* Tinggi panel chat: sisa viewport setelah header aplikasi + padding konten. */
    .ai-shell { height: calc(100vh - 11.5rem); min-height: 520px; }

    /* Scrollbar tipis supaya thread panjang tidak berisik. */
    .ai-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
    .ai-scroll::-webkit-scrollbar { width: 6px; }
    .ai-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
    .ai-scroll::-webkit-scrollbar-track { background: transparent; }

    .ai-bubble-in  { animation: aiFadeUp .18s ease-out both; }
    @keyframes aiFadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    /* Titik "sedang mengetik". */
    .ai-dot { animation: aiBlink 1.2s infinite ease-in-out both; }
    .ai-dot:nth-child(2) { animation-delay: .18s; }
    .ai-dot:nth-child(3) { animation-delay: .36s; }
    @keyframes aiBlink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }

    #aiInput { max-height: 200px; }

    /* Isi jawaban assistant dirender dari markdown (lihat aiRenderMarkdown). */
    .ai-prose p { margin: 0 0 .6rem; }
    .ai-prose p:last-child, .ai-prose > *:last-child { margin-bottom: 0; }
    .ai-prose strong { font-weight: 600; }
    .ai-prose code { background: rgba(0,0,0,.06); padding: .1rem .3rem; border-radius: .3rem; font-size: .8125rem; }

    /* Pratinjau tempelan besar: monospace + gulir sendiri, jangan mendorong
       lebar modal (baris kode panjang tidak boleh membuat halaman melebar). */
    .ai-paste-preview {
        white-space: pre;
        overflow: auto;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 11.5px;
        line-height: 1.55;
        tab-size: 4;
    }
    .ai-prose pre { background: rgba(0,0,0,.06); padding: .6rem .75rem; border-radius: .5rem; overflow-x: auto; margin: 0 0 .6rem; }
    .ai-prose pre code { background: none; padding: 0; }
    .ai-prose ul, .ai-prose ol { margin: 0 0 .6rem; padding-left: 1.25rem; }
    .ai-prose ul { list-style: disc; }
    .ai-prose ol { list-style: decimal; }
    .ai-prose li { margin: .15rem 0; }
    .ai-prose li > ul, .ai-prose li > ol { margin: .15rem 0 0; }
    .ai-prose h1, .ai-prose h2, .ai-prose h3, .ai-prose h4 { font-weight: 600; margin: .9rem 0 .4rem; }
    .ai-prose h1:first-child, .ai-prose h2:first-child, .ai-prose h3:first-child, .ai-prose h4:first-child { margin-top: 0; }
    .ai-prose h1 { font-size: 1.05rem; }
    .ai-prose h2 { font-size: 1rem; }
    .ai-prose h3, .ai-prose h4 { font-size: .9rem; }
    .ai-prose table { width: 100%; border-collapse: collapse; margin: 0 0 .6rem; font-size: .8125rem; }
    .ai-prose th, .ai-prose td { border: 1px solid #e5e7eb; padding: .35rem .55rem; text-align: left; vertical-align: top; }
    .ai-prose th { background: rgba(0,0,0,.03); font-weight: 600; }
    .ai-prose a { color: #dc2626; text-decoration: underline; }
    .ai-prose blockquote { border-left: 3px solid #e5e7eb; padding-left: .75rem; margin: 0 0 .6rem; color: #6b7280; }
    .ai-prose hr { border: none; border-top: 1px solid #e5e7eb; margin: .6rem 0; }

    /* ── Lampiran gambar ───────────────────────────────────────────────────── */
    .ai-thumb {
        display: block; border-radius: .5rem; object-fit: cover;
        cursor: zoom-in; transition: transform .12s ease, box-shadow .12s ease;
    }
    .ai-thumb:hover { transform: scale(1.02); box-shadow: 0 4px 14px rgba(0,0,0,.18); }

    #aiLightbox { background: rgba(3,7,18,.88); backdrop-filter: blur(2px); }
    #aiLightbox img { max-width: 92vw; max-height: 86vh; border-radius: .5rem; }
</style>
@endpush

@section('content')

<div class="ai-shell flex gap-4">

    {{-- ── Panel chat ───────────────────────────────────────────────────────── --}}
    <section class="flex-1 min-w-0 flex flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden">

        {{-- Header --}}
        <header class="flex items-center gap-3 px-4 sm:px-5 py-3 border-b border-gray-100">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white shrink-0">
                <i class="fas fa-robot text-sm"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-gray-900 truncate">EcoSystem Assistant</h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-[10px] font-semibold text-emerald-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Connected
                    </span>
                </div>
                <p class="text-[11px] text-gray-400 truncate">Ask about your tickets, SLA, and delivery projects</p>
            </div>

            <div class="flex items-center gap-1.5">
                {{-- Pemilih model DIHAPUS: modelnya ditentukan super admin di
                     Control Center → AI Settings, sama untuk semua orang. --}}
                <button type="button" onclick="aiNewChat()" title="Clear conversation"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
                    <i class="fas fa-rotate-left text-xs"></i>
                </button>
            </div>
        </header>

        {{-- Thread --}}
        <div id="aiThread" class="flex-1 overflow-y-auto ai-scroll px-4 sm:px-6 py-5">

            {{-- Empty state: sapaan singkat saja, bukan daftar topik. Nama diambil
                 dari sesi (pola yang sama dipakai dashboard.blade.php), waktu
                 harinya dihitung di klien lewat aiGreeting() supaya mengikuti jam
                 lokal browser, bukan zona waktu server. --}}
            <div id="aiEmptyState" class="min-h-full flex flex-col items-center justify-center py-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white mb-4">
                    <i class="fas fa-wand-magic-sparkles text-lg"></i>
                </div>
                <h3 id="aiGreeting" class="text-lg font-bold text-gray-900">Hi</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-sm">
                    Ask about tickets, projects, or delivery data, or attach a document to summarize it.
                </p>
            </div>

            {{-- Pesan disisipkan di sini --}}
            <div id="aiMessages" class="space-y-5 hidden"></div>
        </div>

        {{-- Composer --}}
        <div class="border-t border-gray-100 px-3 sm:px-5 py-3">

            {{-- Chip lampiran --}}
            <div id="aiAttachments" class="hidden flex-wrap gap-2 mb-2.5"></div>

            <div class="rounded-2xl border border-gray-200 bg-white focus-within:border-red-400 focus-within:ring-2 focus-within:ring-red-100 transition-all">
                <textarea id="aiInput" rows="1" placeholder="How can I help you today?"
                          oninput="aiAutoGrow(this)" onkeydown="aiOnKeydown(event)" onpaste="aiOnPaste(event)"
                          class="w-full resize-none bg-transparent px-4 pt-3 pb-1 text-sm text-gray-800 placeholder-gray-400 focus:outline-none ai-scroll"></textarea>

                <div class="flex items-center gap-1.5 px-2.5 pb-2.5 pt-1">
                    <input type="file" id="aiFile" class="hidden" multiple onchange="aiOnFilesPicked(this)">

                    <button type="button" onclick="document.getElementById('aiFile').click()" title="Attach file"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-paperclip text-sm"></i>
                    </button>
                    <span class="flex-1"></span>

                    <span id="aiCounter" class="hidden sm:inline text-[10px] text-gray-400 tabular-nums mr-1">0 / 4000</span>

                    <button type="button" id="aiStopBtn" onclick="aiStop()"
                            class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-50 transition-all">
                        <i class="fas fa-stop text-[10px]"></i> Stop
                    </button>

                    <button type="button" id="aiSendBtn" onclick="aiSend()" disabled
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-xl hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <i class="fas fa-paper-plane text-[10px]"></i> Send
                    </button>
                </div>
            </div>

            <p class="mt-2 text-[10px] text-gray-400 text-center">
                Responses may be inaccurate, so always verify important information before acting on it.
            </p>
        </div>
    </section>
</div>

{{-- Lightbox gambar (di luar thread supaya tidak ikut ter-scroll) --}}
<div id="aiLightbox" class="hidden fixed inset-0 z-[70] items-center justify-center p-6" onclick="aiCloseLightbox(event)">
    <button type="button" onclick="aiCloseLightbox()" title="Close"
            class="absolute top-4 right-4 w-10 h-10 inline-flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-all">
        <i class="fas fa-xmark"></i>
    </button>
    <figure class="max-w-full max-h-full flex flex-col items-center gap-3">
        <img id="aiLightboxImg" src="" alt="">
        <figcaption id="aiLightboxCaption" class="text-xs text-white/70"></figcaption>
    </figure>
</div>

{{-- Pratinjau tempelan besar. Chip di composer maupun di bubble yang sudah
     terkirim membuka modal ini — isi tempelan tidak pernah dirender inline,
     karena 8.000 baris di dalam thread membuat halaman tak bisa dipakai. --}}
<div id="aiPasteModal" onclick="aiClosePaste(event)"
     class="hidden fixed inset-0 z-50 bg-black/50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-gray-200 w-full max-w-3xl max-h-[80vh] flex flex-col overflow-hidden">
        <header class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
            <i class="fas fa-align-left text-xs text-gray-400"></i>
            <div class="min-w-0 flex-1">
                <p id="aiPasteTitle" class="text-sm font-bold text-gray-900 truncate">Pasted text</p>
                <p id="aiPasteMeta" class="text-[11px] text-gray-400"></p>
            </div>
            <button type="button" onclick="aiClosePaste()" title="Close"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <i class="fas fa-xmark text-xs"></i>
            </button>
        </header>
        <pre id="aiPasteBody" class="ai-paste-preview ai-scroll flex-1 m-0 px-4 py-3 text-gray-700 bg-gray-50"></pre>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<script>
/* ──────────────────────────────────────────────────────────────────────────
   AI Assistant.

   Backend touchpoint for sending is aiSendToBackend(), which streams the
   reply from POST /ai-assistant/chat as Server-Sent Events. The conversation
   id is a client-generated UUID kept in sessionStorage (see
   aiEnsureConversationId()) — same tab/id, so refreshing or switching menus
   and coming back reuses it; a brand-new tab starts a fresh conversation.

   The chat itself IS persisted server-side (assistant's working memory lasts
   24h, and a text-only archive survives well past that — see AiChatService).
   What used to reset on every page load was purely the DOM: the thread was
   never re-fetched after a reload. aiOpenConversation() (called from
   DOMContentLoaded when a saved id exists) fixes that by pulling the archived
   transcript from GET /ai-assistant/conversations/{id} and re-rendering it.
   ────────────────────────────────────────────────────────────────────────── */

const AI_MAX_CHARS = 4000;
const AI_CHAT_ENDPOINT = @json(route('ai-assistant.chat'));
/* {id} diganti saat dipakai — route() butuh parameter, dan menyusun URL-nya
   di sisi JS dengan string mentah gampang salah kalau prefiks app berubah. */
const AI_CONV_ENDPOINT = @json(route('ai-assistant.conversation', ['conversation' => '__ID__']));
const AI_CONVERSATION_STORAGE_KEY = 'ai_conversation_id';

/* Ambang "ini tempelan, bukan ketikan". Di atas salah satu angka ini, teks
   yang di-paste TIDAK masuk ke textarea melainkan jadi lampiran — persis
   seperti composer claude.ai. Alasannya bukan kosmetik: `message` dibatasi
   4.000 karakter di server, jadi menempel 8.000 baris kode ke textarea
   berakhir sebagai penolakan validasi, bukan sebagai jawaban. */
const AI_PASTE_MIN_CHARS = @json(\App\Support\AiTextAttachment::PASTE_THRESHOLD_CHARS);
const AI_PASTE_MIN_LINES = @json(\App\Support\AiTextAttachment::PASTE_THRESHOLD_LINES);

/* Sinkron dengan AiTextAttachment::MAX_CHARS — di atas ini server memotong,
   dan user berhak tahu SEBELUM mengirim, bukan sesudah. */
const AI_TEXT_MAX_CHARS = @json(\App\Support\AiTextAttachment::MAX_CHARS);

const AI_MAX_FILES = 5;

let aiFiles     = [];     // File[] yang dipilih untuk pesan berikutnya
let aiBusy      = false;  // sedang menunggu balasan
let aiAbort     = null;   // AbortController pembatal request berjalan
let aiConversationId = null;
let aiAttachSeq = 0;      // id unik elemen badge lampiran arsip, lihat aiAppendUserStored()

const aiPreviews = new Map();  // File → object URL, dibuat sekali per File
let aiPreviewUrls = [];        // dibuang saat chat baru, lihat aiNewChat()

/* Nama user yang login, untuk sapaan di empty state (aiGreeting()). */
const AI_USER_NAME = @json(session('user.name', ''));

/* Placeholder composer: satu dipilih acak tiap halaman dimuat / chat baru,
   supaya terasa hidup alih-alih satu kalimat instruksi yang sama terus —
   pola yang sama seperti composer claude.ai. Instruksi keyboard (Enter/
   Shift+Enter) sengaja tidak dijejalkan ke sini lagi; itu perilaku standar
   yang tidak perlu diiklankan di setiap kunjungan. */
const AI_PLACEHOLDERS = [
    'How can I help you today?',
    'Ask about a ticket, project, or SLA…',
    'What would you like to know?',
    'Need a summary or a quick answer?',
    'Ask me anything about EcoSystem…',
];

function aiRandomPlaceholder() {
    return AI_PLACEHOLDERS[Math.floor(Math.random() * AI_PLACEHOLDERS.length)];
}

/* Tempelan besar: File → metadata chip. WeakMap supaya berkas yang sudah
   dibuang dari aiFiles tidak menahan isinya di memori. */
const aiPasteMeta = new WeakMap();

/* Isi tempelan per id, dipakai modal pratinjau. Ini BUKAN duplikat WeakMap
   di atas: chip pada bubble yang sudah terkirim tidak lagi memegang objek
   File-nya, jadi ia perlu kunci yang bisa ditulis ke dalam HTML. */
const aiPasteById = {};
let aiPasteSeq = 0;

/* crypto.randomUUID hanya ada di secure context (HTTPS / localhost). Di server
   yang diakses lewat HTTP biasa ia undefined, jadi sediakan fallback RFC 4122. */
function aiRandomUUID() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    const b = (typeof crypto !== 'undefined' && crypto.getRandomValues)
        ? crypto.getRandomValues(new Uint8Array(16))
        : Array.from({ length: 16 }, () => Math.floor(Math.random() * 256));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = Array.from(b, (x) => x.toString(16).padStart(2, '0'));
    return `${h[0]}${h[1]}${h[2]}${h[3]}-${h[4]}${h[5]}-${h[6]}${h[7]}-${h[8]}${h[9]}-${h[10]}${h[11]}${h[12]}${h[13]}${h[14]}${h[15]}`;
}

function aiEnsureConversationId() {
    if (aiConversationId) return aiConversationId;

    aiConversationId = sessionStorage.getItem(AI_CONVERSATION_STORAGE_KEY);
    if (!aiConversationId) {
        aiConversationId = aiRandomUUID();
        sessionStorage.setItem(AI_CONVERSATION_STORAGE_KEY, aiConversationId);
    }
    return aiConversationId;
}

function aiCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/* ── Composer ──────────────────────────────────────────────────────────── */

function aiAutoGrow(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';

    const len = el.value.length;
    document.getElementById('aiCounter').textContent = len + ' / ' + AI_MAX_CHARS;
    document.getElementById('aiSendBtn').disabled = aiBusy || (len === 0 && aiFiles.length === 0);
}

function aiOnKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        aiSend();
    }
}

function aiNotYet(feature) {
    showToast((feature || 'This feature') + ' is not available yet.', 'warning');
}

/* ── Sapaan ────────────────────────────────────────────────────────────── */

/** Sapaan berdasar jam LOKAL BROWSER (bukan zona waktu server) + nama depan
 *  user yang login. Dipanggil sekali saat halaman dimuat: sapaan ini bukan
 *  status yang berubah-ubah selama sesi, jadi tidak perlu jam berjalan. */
function aiGreeting() {
    const hour = new Date().getHours();
    const time = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    const firstName = AI_USER_NAME.trim().split(/\s+/)[0] || '';

    return firstName ? `${time}, ${firstName}` : time;
}

/* ── Lampiran ──────────────────────────────────────────────────────────── */

function aiIsImage(file) {
    return (file.type || '').startsWith('image/');
}

/** Object URL pratinjau, dibuat sekali per File. */
function aiPreviewUrl(file) {
    if (!aiPreviews.has(file)) {
        const url = URL.createObjectURL(file);
        aiPreviews.set(file, url);
        aiPreviewUrls.push(url);
    }
    return aiPreviews.get(file);
}

/* Cache lampiran di browser (opsional, murni kenyamanan) — server TIDAK
   PERNAH menyimpan byte gambar, arsip cuma mencatat JUMLAH lampiran
   (lihat migration create_ai_conversation_tables: "tanpa menyimpan
   screenshot sistem customer selamanya"). Supaya thumbnail tetap muncul
   lagi saat conversation dibuka ulang DI BROWSER/DEVICE YANG SAMA,
   file-nya disimpan di IndexedDB milik user sendiri, dikunci dari teks
   pesan (bukan posisi/index — lebih tahan terhadap pesan yang gagal/
   ditolak terkirim, yang tidak pernah benar-benar sampai ke arsip).
   Kegagalan di jalur ini SELALU diam: ini cuma penambah, bukan pengganti,
   badge teks "N attachment" yang sudah ada. Database TERPISAH dari AI
   Research (nama beda) supaya lampiran dua fitur tidak tercampur. */
const AI_ATTACHMENT_DB = 'ai_assistant_attachment_cache';
const AI_ATTACHMENT_STORE = 'files';
const AI_ATTACHMENT_MAX_AGE_MS = 30 * 24 * 60 * 60 * 1000; // 30 hari

function aiAttachmentDb() {
    return new Promise((resolve, reject) => {
        if (!window.indexedDB) { reject(new Error('no indexedDB')); return; }
        const req = indexedDB.open(AI_ATTACHMENT_DB, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(AI_ATTACHMENT_STORE);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function aiAttachmentCacheKey(conversationId, text, fileIndex) {
    return conversationId + '::' + (text || '').trim().slice(0, 200) + '::' + fileIndex;
}

async function aiCacheAttachmentFile(key, file) {
    try {
        const db = await aiAttachmentDb();
        await new Promise((resolve) => {
            const tx = db.transaction(AI_ATTACHMENT_STORE, 'readwrite');
            tx.objectStore(AI_ATTACHMENT_STORE).put({ file, at: Date.now() }, key);
            tx.oncomplete = resolve;
            tx.onerror = resolve;
        });
    } catch { /* diam - lihat catatan di atas */ }
}

async function aiGetCachedAttachmentFile(key) {
    try {
        const db = await aiAttachmentDb();
        return await new Promise((resolve) => {
            const tx = db.transaction(AI_ATTACHMENT_STORE, 'readonly');
            const req = tx.objectStore(AI_ATTACHMENT_STORE).get(key);
            req.onsuccess = () => resolve(req.result?.file || null);
            req.onerror = () => resolve(null);
        });
    } catch {
        return null;
    }
}

/** Buang entri lebih dari 30 hari. Dipanggil sekali tiap halaman dimuat, non-blocking. */
async function aiPruneAttachmentCache() {
    try {
        const db = await aiAttachmentDb();
        const cutoff = Date.now() - AI_ATTACHMENT_MAX_AGE_MS;
        const store = db.transaction(AI_ATTACHMENT_STORE, 'readwrite').objectStore(AI_ATTACHMENT_STORE);
        const req = store.openCursor();
        req.onsuccess = () => {
            const cursor = req.result;
            if (!cursor) return;
            if ((cursor.value?.at || 0) < cutoff) cursor.delete();
            cursor.continue();
        };
    } catch { /* diam */ }
}

/* Lightbox */

function aiOpenLightbox(url, caption) {
    const box = document.getElementById('aiLightbox');
    document.getElementById('aiLightboxImg').src = url;
    document.getElementById('aiLightboxCaption').textContent = caption || '';
    box.classList.remove('hidden');
    box.classList.add('flex');
}

/** Klik di area gelap (bukan pada gambar) menutup lightbox. */
function aiCloseLightbox(e) {
    if (e && e.target.tagName === 'IMG') return;

    const box = document.getElementById('aiLightbox');
    box.classList.add('hidden');
    box.classList.remove('flex');
}

function aiOnFilesPicked(input) {
    aiAddFiles(Array.from(input.files || []));
    input.value = '';  // supaya file yang sama bisa dipilih lagi
}

function aiAddFiles(files) {
    const room = AI_MAX_FILES - aiFiles.length;

    if (room <= 0) {
        showToast('You can attach up to ' + AI_MAX_FILES + ' items per message.', 'warning');
        return;
    }

    if (files.length > room) {
        showToast('Only the first ' + room + ' attachment(s) were added; the limit is '
            + AI_MAX_FILES + ' per message.', 'warning');
    }

    aiFiles = aiFiles.concat(files.slice(0, room));
    aiRenderAttachments();
    aiAutoGrow(document.getElementById('aiInput'));
}

function aiRemoveFile(index) {
    aiFiles.splice(index, 1);
    aiRenderAttachments();
    aiAutoGrow(document.getElementById('aiInput'));
}

function aiRenderAttachments() {
    const box = document.getElementById('aiAttachments');
    box.classList.toggle('hidden', aiFiles.length === 0);
    box.classList.toggle('flex', aiFiles.length > 0);

    // Gambar tampil sebagai thumbnail sungguhan (klik untuk lightbox), sama
    // seperti bubble terkirim — bukan cuma chip ikon+nama. Tempelan teks
    // TETAP lewat aiChipFace() apa adanya (paste selalu menang atas image-
    // ness, walau secara teknis jarang/tidak pernah keduanya sekaligus).
    box.innerHTML = aiFiles.map((file, i) => {
        const isImage = aiIsImage(file) && !aiPasteMeta.has(file);

        const face = isImage ? `
            <img src="${aiPreviewUrl(file)}" alt="${aiEsc(file.name)}" title="${aiEsc(file.name)}"
                 class="ai-thumb w-9 h-9 shrink-0"
                 data-ai-full="${aiPreviewUrl(file)}" data-ai-caption="${aiEsc(file.name)}">
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold text-gray-700 truncate">${aiEsc(file.name)}</span>
                <span class="block text-[10px] text-gray-400">${aiFileSize(file.size)}</span>
            </span>`
            : aiChipFace(file, 'text-gray-400', 'text-gray-700', 'bg-gray-200/70');

        return `
        <span class="inline-flex items-center gap-2 pl-2.5 pr-1.5 py-1.5 rounded-xl border border-gray-200 bg-gray-50 max-w-[240px]">
            ${face}
            <button type="button" onclick="aiRemoveFile(${i})" title="Remove"
                    class="w-5 h-5 shrink-0 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-all">
                <i class="fas fa-xmark text-[10px]"></i>
            </button>
        </span>`;
    }).join('');
}

/**
 * Bagian "wajah" chip — dipakai bersama oleh composer dan bubble terkirim,
 * supaya lampiran yang sama tidak tampil berbeda di dua tempat.
 */
function aiChipFace(file, iconClass, labelClass, badgeClass) {
    const paste = aiPasteMeta.get(file);

    if (paste) {
        return `
            <button type="button" onclick="aiOpenPaste('${paste.id}')" title="View pasted text"
                    class="flex items-center gap-2 min-w-0 text-left">
                <span class="px-1.5 py-0.5 rounded-md ${badgeClass} text-[9px] font-bold tracking-wide ${labelClass}">PASTED</span>
                <span class="min-w-0">
                    <span class="block text-[11px] font-semibold ${labelClass} truncate">${aiEsc(paste.label)}</span>
                    <span class="block text-[10px] ${iconClass}">${paste.lines.toLocaleString()} lines · ${aiFileSize(file.size)}</span>
                </span>
            </button>`;
    }

    return `
        <span class="flex items-center gap-2 min-w-0">
            <i class="fas ${aiFileIcon(file.name)} text-xs ${iconClass}"></i>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold ${labelClass} truncate">${aiEsc(file.name)}</span>
                <span class="block text-[10px] ${iconClass}">${aiFileSize(file.size)}</span>
            </span>
        </span>`;
}

/* ── Tempelan besar ────────────────────────────────────────────────────── */

/**
 * Tempelan besar dan gambar dari clipboard sama-sama berakhir sebagai
 * LAMPIRAN, bukan sebagai isi textarea.
 *
 * Tempelan kecil dibiarkan lewat (return tanpa preventDefault) — mengubah
 * setiap paste jadi lampiran akan merusak hal paling biasa yang dilakukan
 * orang di composer: menempel satu kalimat.
 */
function aiOnPaste(e) {
    const data = e.clipboardData;
    if (!data) return;

    const images = Array.from(data.items || [])
        .filter(item => item.kind === 'file' && item.type.startsWith('image/'))
        .map(item => item.getAsFile())
        .filter(Boolean)
        // Screenshot dari clipboard datang tanpa nama; tanpa nama buatan,
        // chip-nya kosong dan server kehilangan ekstensi untuk ditebak.
        .map(file => file.name
            ? file
            : new File([file], 'screenshot-' + Date.now() + '.' + (file.type.split('/')[1] || 'png'), { type: file.type }));

    if (images.length > 0) {
        e.preventDefault();
        aiAddFiles(images);
        return;
    }

    const text = data.getData('text/plain') || '';
    if (!aiIsLargePaste(text)) return;   // tempelan wajar: biarkan default

    e.preventDefault();
    aiAddPastedText(text);
}

function aiIsLargePaste(text) {
    if (!text) return false;

    return text.length > AI_PASTE_MIN_CHARS
        || (text.match(/\n/g) || []).length + 1 > AI_PASTE_MIN_LINES;
}

/**
 * Teks tempelan → berkas .txt biasa.
 *
 * Sengaja lewat jalur lampiran yang sudah ada, bukan lewat field request
 * baru: server memperlakukan berkas teks dan tempelan dengan kode yang sama
 * (App\Support\AiTextAttachment), jadi tidak ada jalur kedua yang bisa
 * menyimpang diam-diam.
 */
function aiAddPastedText(text) {
    const lines = (text.match(/\n/g) || []).length + 1;
    const id = 'paste' + (++aiPasteSeq) + '-' + Date.now();

    if (text.length > AI_TEXT_MAX_CHARS) {
        showToast('That paste is very large, so only the first '
            + AI_TEXT_MAX_CHARS.toLocaleString() + ' characters will be sent.', 'warning');
    }

    const file = new File([text], aiPasteFileName(text, aiPasteSeq), { type: 'text/plain' });

    aiPasteMeta.set(file, { id, label: 'Pasted text', lines: lines, text: text });
    aiPasteById[id] = { label: 'Pasted text', lines: lines, chars: text.length, text: text };

    aiAddFiles([file]);
}

/**
 * Ekstensi ditebak dari isinya supaya nama berkas yang sampai ke model
 * memberi petunjuk bahasa — model membaca nama lampiran, dan "pasted-1.txt"
 * untuk file Blade membuang konteks yang sebenarnya gratis.
 */
function aiPasteFileName(text, seq) {
    const head = text.slice(0, 2000);
    let ext = 'txt';

    if (/^\s*[{[]/.test(head) && /["}\]]\s*$/.test(text.slice(-200)))            ext = 'json';
    // Escape heksadesimal DIPAKAI DENGAN SENGAJA di baris berikut (\x40 =
    // karakter at, \x7b/\x7d = kurung kurawal): file ini Blade, dan penanda
    // direktif Blade yang ditulis literal di dalam <script> tetap ikut
    // dikompilasi — hasilnya view gagal dirender (500), bukan sekadar regex
    // yang salah.
    else if (/\x40extends|\x40section|\x40php|\x7b\x7b.*\x7d\x7d/.test(head))  ext = 'blade.php';
    else if (/<\?php|namespace\s+\w+|public function /.test(head))               ext = 'php';
    else if (/<\/?(div|html|body|span|table)\b/i.test(head))                     ext = 'html';
    else if (/\b(function|const|let|=>|document\.)\b/.test(head))                ext = 'js';
    else if (/\b(SELECT|INSERT|UPDATE|CREATE TABLE)\b/i.test(head))              ext = 'sql';

    return 'pasted-' + seq + '.' + ext;
}

function aiOpenPaste(id) {
    const paste = aiPasteById[id];
    if (!paste) return;

    document.getElementById('aiPasteTitle').textContent = paste.label;
    document.getElementById('aiPasteMeta').textContent =
        paste.lines.toLocaleString() + ' lines · ' + paste.chars.toLocaleString() + ' characters'
        + (paste.chars > AI_TEXT_MAX_CHARS
            ? ' · only the first ' + AI_TEXT_MAX_CHARS.toLocaleString() + ' are sent'
            : '');
    document.getElementById('aiPasteBody').textContent = paste.text;

    const box = document.getElementById('aiPasteModal');
    box.classList.remove('hidden');
    box.classList.add('flex');
}

/** Klik latar (bukan isi panel) menutup. */
function aiClosePaste(e) {
    if (e && e.target !== e.currentTarget) return;

    const box = document.getElementById('aiPasteModal');
    box.classList.add('hidden');
    box.classList.remove('flex');
    document.getElementById('aiPasteBody').textContent = '';
}

function aiFileIcon(name) {
    const ext = (name.split('.').pop() || '').toLowerCase();
    if (['png','jpg','jpeg','gif','webp','bmp','svg'].includes(ext)) return 'fa-file-image';
    if (['pdf'].includes(ext))                                       return 'fa-file-pdf';
    if (['xls','xlsx','csv'].includes(ext))                          return 'fa-file-excel';
    if (['doc','docx'].includes(ext))                                return 'fa-file-word';
    if (['ppt','pptx'].includes(ext))                                return 'fa-file-powerpoint';
    if (['zip','rar','7z'].includes(ext))                            return 'fa-file-zipper';
    return 'fa-file-lines';
}

function aiFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

/* ── Thread ────────────────────────────────────────────────────────────── */

marked.setOptions({ breaks: true });

/** Markdown -> HTML, lalu disaring DOMPurify sebelum dipasang via innerHTML —
 *  teksnya berasal dari model/data DB, jadi tidak boleh dipercaya mentah. */
function aiRenderMarkdown(text) {
    return DOMPurify.sanitize(marked.parse(String(text ?? '')));
}

function aiEsc(s) {
    return String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

/* Tanpa argumen = sekarang. Bubble yang dipulihkan dari arsip mengirim waktu
   aslinya — kalau tidak, percakapan kemarin tampil seolah baru saja terjadi. */
function aiTime(at) {
    const d = at ? new Date(at) : new Date();
    if (isNaN(d)) return '';

    const sameDay = d.toDateString() === new Date().toDateString();
    const clock = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    return sameDay ? clock : d.toLocaleDateString([], { day: '2-digit', month: 'short' }) + ' ' + clock;
}

function aiShowThread() {
    document.getElementById('aiEmptyState').classList.add('hidden');
    document.getElementById('aiMessages').classList.remove('hidden');
}

function aiScrollToBottom() {
    const thread = document.getElementById('aiThread');
    thread.scrollTop = thread.scrollHeight;
}

function aiAppendUser(text, files) {
    // Gambar (kecuali tempelan teks yang kebetulan lolos deteksi MIME — tidak
    // pernah terjadi di praktik, tapi aiPasteMeta.get(f) di aiChipFace() sudah
    // menjaganya) tampil utuh sebagai thumbnail; file lain tetap chip nama.
    const images = files.filter(f => aiIsImage(f) && !aiPasteMeta.has(f));
    const others = files.filter(f => !images.includes(f));

    // Lampiran DULU, teks/prompt di bawahnya — sama seperti composer
    // claude.ai (gambar/berkas adalah "subjek", teksnya keterangan/pertanyaan
    // tentangnya).
    const thumbs = images.length === 0 ? '' : `
        <div class="flex flex-wrap gap-1.5 justify-end">
            ${images.map(f => `
                <img src="${aiPreviewUrl(f)}" alt="${aiEsc(f.name)}" title="${aiEsc(f.name)}"
                     class="ai-thumb w-28 h-28 border border-white/25"
                     data-ai-full="${aiPreviewUrl(f)}" data-ai-caption="${aiEsc(f.name)}">`).join('')}
        </div>`;

    const chips = others.length === 0 ? '' : `
        <div class="flex flex-wrap gap-1.5 justify-end ${images.length ? 'mt-2' : ''}">
            ${others.map(f => `
                <span class="inline-flex items-center gap-2 px-2 py-1 rounded-lg bg-white/15 border border-white/20 max-w-[220px]">
                    ${aiChipFace(f, 'text-white/70', 'text-white', 'bg-white/20')}
                </span>`).join('')}
        </div>`;

    const body = text
        ? `<p class="whitespace-pre-wrap break-words ${(images.length || others.length) ? 'mt-2' : ''}">${aiEsc(text)}</p>`
        : '';

    document.getElementById('aiMessages').insertAdjacentHTML('beforeend', `
        <div class="ai-bubble-in flex justify-end gap-3">
            <div class="max-w-[85%] sm:max-w-[70%]">
                <div class="bg-red-600 text-white text-sm rounded-2xl rounded-br-md px-4 py-2.5">
                    ${thumbs}${chips}${body}
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-right">${aiTime()}</p>
            </div>
        </div>
    `);
    aiScrollToBottom();
}

/* Hidrasi riwayat membuat banyak bubble dalam milidetik yang sama, jadi id-nya
   TIDAK boleh berbasis Date.now() — id kembar membuat delta jawaban baru
   mendarat di bubble lama. */
let aiMsgSeq = 0;

/** Bubble assistant kosong + indikator mengetik. Kembalikan id-nya. */
function aiAppendAssistantPending(at) {
    const id = 'aiMsg' + (++aiMsgSeq);

    document.getElementById('aiMessages').insertAdjacentHTML('beforeend', `
        <div class="ai-bubble-in flex gap-3" id="${id}">
            <div class="w-8 h-8 shrink-0 rounded-xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white">
                <i class="fas fa-robot text-[11px]"></i>
            </div>
            <div class="max-w-[85%] sm:max-w-[75%] min-w-0">
                <div class="bg-gray-50 border border-gray-100 text-sm text-gray-800 rounded-2xl rounded-bl-md px-4 py-3">
                    <div class="ai-body ai-prose">
                        <span class="inline-flex items-center gap-1 py-1">
                            <span class="ai-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <span class="ai-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <span class="ai-dot w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        </span>
                    </div>
                </div>
                <div class="ai-actions hidden items-center gap-1 mt-1.5">
                    <button type="button" onclick="aiCopy('${id}')" title="Copy"
                            class="w-6 h-6 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-copy text-[10px]"></i>
                    </button>
                    <button type="button" onclick="aiNotYet('Feedback')" title="Good response"
                            class="w-6 h-6 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-thumbs-up text-[10px]"></i>
                    </button>
                    <button type="button" onclick="aiNotYet('Feedback')" title="Bad response"
                            class="w-6 h-6 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-thumbs-down text-[10px]"></i>
                    </button>
                    <span class="text-[10px] text-gray-400 ml-1">${aiTime(at)}</span>
                </div>
            </div>
        </div>
    `);
    aiScrollToBottom();
    return id;
}

/**
 * Bubble user dari arsip: teksnya tersimpan, lampirannya (di server) tidak.
 * Badge teks ditampilkan LEBIH DULU (selalu benar, tidak pernah menunggu apa
 * pun); kalau file aslinya kebetulan masih ada di cache browser (dikirim
 * dari browser/device yang sama, lihat aiCacheAttachmentFile()), badge-nya
 * ditingkatkan jadi thumbnail sungguhan secara asinkron begitu ketemu.
 */
function aiAppendUserStored(text, attachmentCount, at, conversationId) {
    const noteId = attachmentCount > 0 ? 'aiAtt' + (++aiAttachSeq) : null;

    const note = attachmentCount > 0 ? `
        <div id="${noteId}" class="flex items-center gap-1.5 justify-end text-[10px] text-white/70">
            <i class="fas fa-paperclip text-[9px]"></i>
            ${attachmentCount} attachment${attachmentCount > 1 ? 's' : ''} (not kept in history)
        </div>` : '';

    const body = text
        ? `<p class="whitespace-pre-wrap break-words ${attachmentCount > 0 ? 'mt-2' : ''}">${aiEsc(text)}</p>`
        : '';

    document.getElementById('aiMessages').insertAdjacentHTML('beforeend', `
        <div class="flex justify-end gap-3">
            <div class="max-w-[85%] sm:max-w-[70%]">
                <div class="bg-red-600 text-white text-sm rounded-2xl rounded-br-md px-4 py-2.5">
                    ${note}${body}
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-right">${aiTime(at)}</p>
            </div>
        </div>
    `);
    aiScrollToBottom();

    if (attachmentCount > 0 && conversationId) {
        aiUpgradeStoredAttachments(noteId, conversationId, text, attachmentCount);
    }
}

/** Ganti badge teks jadi thumbnail sungguhan kalau SEMUA file-nya ketemu di cache browser. */
async function aiUpgradeStoredAttachments(noteId, conversationId, text, count) {
    const files = await Promise.all(
        Array.from({ length: count }, (_, fi) => aiGetCachedAttachmentFile(aiAttachmentCacheKey(conversationId, text, fi)))
    );

    if (files.some(f => !f)) return; // sebagian/semua tidak ada — biarkan badge teks apa adanya

    const el = document.getElementById(noteId);
    if (!el) return;

    const images = files.filter(aiIsImage);
    const others = files.filter(f => !aiIsImage(f));

    const thumbs = images.length === 0 ? '' : `
        <div class="flex flex-wrap gap-1.5 justify-end">
            ${images.map(f => `
                <img src="${aiPreviewUrl(f)}" alt="${aiEsc(f.name)}" title="${aiEsc(f.name)}"
                     class="ai-thumb w-28 h-28 border border-white/25"
                     data-ai-full="${aiPreviewUrl(f)}" data-ai-caption="${aiEsc(f.name)}">`).join('')}
        </div>`;

    const chips = others.length === 0 ? '' : `
        <div class="flex flex-wrap gap-1.5 justify-end ${images.length ? 'mt-2' : ''}">
            ${others.map(f => `
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-white/15 border border-white/20">
                    <i class="fas ${aiFileIcon(f.name)} text-[10px]"></i>
                    <span class="text-[10px] font-medium truncate max-w-[140px]">${aiEsc(f.name)}</span>
                </span>`).join('')}
        </div>`;

    // Note (badge lampiran) diganti thumbs+chips di POSISI YANG SAMA — bubble
    // sudah dirakit dengan lampiran DI ATAS teks (lihat aiAppendUserStored()),
    // jadi tidak perlu urus ulang posisi teksnya di sini.
    el.outerHTML = thumbs + chips;
}

/** Bubble assistant dari arsip — bentuknya sama dengan hasil streaming. */
function aiAppendAssistantStored(text, at) {
    const id = aiAppendAssistantPending(at);
    const wrap = document.getElementById(id);
    const body = wrap.querySelector('.ai-body');

    // dataset.text diisi supaya tombol Copy tetap menyalin teks aslinya.
    body.dataset.streaming = '1';
    body.dataset.text = text || '';
    body.innerHTML = aiRenderMarkdown(body.dataset.text);

    const actions = wrap.querySelector('.ai-actions');
    actions.classList.remove('hidden');
    actions.classList.add('flex');

    aiScrollToBottom();
}

/**
 * Garis "dari sini yang diingat model".
 *
 * Ditempatkan DI DALAM transkrip, bukan sebagai banner di atas halaman: yang
 * perlu diketahui user bukan "ada batas", melainkan batasnya ada DI MANA.
 */
function aiAppendMemoryDivider() {
    document.getElementById('aiMessages').insertAdjacentHTML('beforeend', `
        <div class="flex items-center gap-2 py-1" title="Older messages are still readable here; the assistant just no longer has them in context.">
            <span class="flex-1 h-px bg-gray-200"></span>
            <span class="text-[10px] text-gray-400 px-1 text-center leading-snug">
                The assistant remembers the conversation from here on; earlier messages are no longer in its context
            </span>
            <span class="flex-1 h-px bg-gray-200"></span>
        </div>
    `);
}

/** Bertahap: append satu potongan teks ke bubble yang sedang streaming, lalu
 *  render ulang seluruh teks yang terkumpul sebagai markdown. Re-parse penuh
 *  tiap delta (bukan cuma append HTML) supaya syntax markdown yang terpotong
 *  di tengah delta (mis. "**Data Prib") tetap dirender benar begitu utuh. */
function aiAppendAssistantDelta(id, deltaText) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    const body = wrap.querySelector('.ai-body');
    if (!body.dataset.streaming) {
        body.dataset.streaming = '1';
        body.dataset.text = '';
    }

    body.dataset.text += deltaText;
    body.innerHTML = aiRenderMarkdown(body.dataset.text);
    aiScrollToBottom();
}

function aiResolveAssistant(id, text, isError) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    const body = wrap.querySelector('.ai-body');

    if (isError) {
        body.innerHTML = `<p class="whitespace-pre-wrap break-words text-red-600">${aiEsc(text)}</p>`;
    } else if (!body.dataset.streaming) {
        // Tidak ada delta yang masuk (text penuh langsung) — render sekarang.
        body.innerHTML = aiRenderMarkdown(text);
    }
    // else: sudah dirender bertahap oleh aiAppendAssistantDelta.

    const actions = wrap.querySelector('.ai-actions');
    actions.classList.remove('hidden');
    actions.classList.add('flex');

    aiScrollToBottom();
}

function aiCopy(id) {
    const body = document.querySelector('#' + id + ' .ai-body');
    if (!body) return;
    navigator.clipboard.writeText(body.innerText.trim())
        .then(() => showToast('Response copied.', 'success'))
        .catch(() => showToast('Could not copy the response.', 'error'));
}

/* ── Kirim ─────────────────────────────────────────────────────────────── */

function aiSend() {
    if (aiBusy) return;

    const input = document.getElementById('aiInput');
    const text  = input.value.trim();
    if (!text && aiFiles.length === 0) return;

    if (text.length > AI_MAX_CHARS) {
        showToast('Message is too long (max ' + AI_MAX_CHARS + ' characters).', 'warning');
        return;
    }

    const files = aiFiles.slice();
    // Server memakai model yang ditetapkan admin; nilai ini diabaikan.
    const model = 'default';

    aiShowThread();
    aiAppendUser(text, files);

    // Cache opsional di browser sendiri (lihat blok "Cache lampiran" dekat
    // aiIsImage()) — kalau turn ini ternyata gagal/ditolak dan tidak pernah
    // sampai ke arsip, entrinya cuma jadi sisa yang tidak pernah dicocokkan
    // siapa pun; tidak ada salahnya menyimpan lebih awal daripada menunggu
    // konfirmasi sukses.
    // aiEnsureConversationId(), bukan aiConversationId mentah: untuk pesan
    // PERTAMA di percakapan baru, id-nya baru dijamin ada di titik ini
    // (aiSendToBackend() di bawah memanggil fungsi yang sama, jadi id-nya
    // dijamin sama persis dengan yang nanti dipakai server untuk arsip).
    const cacheConversationId = aiEnsureConversationId();
    files.forEach((f, fi) => aiCacheAttachmentFile(aiAttachmentCacheKey(cacheConversationId, text, fi), f));

    input.value = '';
    aiFiles = [];
    aiRenderAttachments();
    aiAutoGrow(input);

    aiSetBusy(true);
    const pendingId = aiAppendAssistantPending();

    aiSendToBackend(text, files, model, pendingId)
        .then(() => aiResolveAssistant(pendingId, '', false))
        .catch(err => {
            // User-initiated Stop: keep whatever text already streamed in
            // rather than overwriting it with an error message.
            const stopped = err.name === 'AbortError';
            aiResolveAssistant(pendingId, stopped ? '' : (err.message || 'Something went wrong.'), !stopped);
        })
        .finally(() => aiSetBusy(false));
}

function aiSetBusy(busy) {
    aiBusy = busy;
    document.getElementById('aiSendBtn').classList.toggle('hidden', busy);
    document.getElementById('aiStopBtn').classList.toggle('hidden', !busy);
    aiAutoGrow(document.getElementById('aiInput'));
}

function aiStop() {
    if (aiAbort) aiAbort.abort();
}

/**
 * SATU-SATUNYA titik sentuh backend.
 *
 * Streaming Server-Sent Events dari POST /ai-assistant/chat. Tiap potongan
 * teks ("delta" event) langsung ditambahkan ke bubble `pendingId` lewat
 * aiAppendAssistantDelta(); "done" menandai selesai normal, "error" menandai
 * kegagalan (backend selalu mengirim salah satu dari keduanya sebagai
 * penutup stream).
 */
async function aiSendToBackend(text, files, model, pendingId) {
    const controller = new AbortController();
    aiAbort = controller;

    const form = new FormData();
    form.append('conversation_id', aiEnsureConversationId());
    form.append('message', text);
    form.append('model', model);
    files.forEach(file => form.append('files[]', file));

    const response = await fetch(AI_CHAT_ENDPOINT, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': aiCsrfToken(),
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
                    aiAppendAssistantDelta(pendingId, payload.text);
                } else if (eventName === 'error') {
                    sawError = payload.message || 'Something went wrong.';
                }
                // 'done' needs no handling here — the outer promise just resolves.
            }
        }
    } finally {
        aiAbort = null;
    }

    if (sawError) throw new Error(sawError);
}

/* ── Percakapan ────────────────────────────────────────────────────────── */

function aiNewChat() {
    if (aiBusy) aiStop();

    document.getElementById('aiMessages').innerHTML = '';
    document.getElementById('aiMessages').classList.add('hidden');
    document.getElementById('aiEmptyState').classList.remove('hidden');

    aiFiles = [];
    aiRenderAttachments();

    // Bubble lama sudah dibuang, jadi object URL pratinjau boleh dilepas.
    aiPreviewUrls.forEach(URL.revokeObjectURL);
    aiPreviewUrls = [];
    aiPreviews.clear();

    // Bubble yang memegang id-nya sudah ikut terhapus di atas, jadi isi
    // tempelan lama tidak lagi bisa dibuka; jangan ditahan di memori.
    Object.keys(aiPasteById).forEach(id => delete aiPasteById[id]);

    // Start a fresh conversation. The old one is NOT deleted — it stays in
    // the archive, just no longer linked to this tab — so nothing needs to
    // be explicitly torn down beyond dropping the id.
    aiConversationId = null;
    sessionStorage.removeItem(AI_CONVERSATION_STORAGE_KEY);

    const input = document.getElementById('aiInput');
    input.value = '';
    input.placeholder = aiRandomPlaceholder();
    aiAutoGrow(input);
    input.focus();
}

/**
 * Restore a conversation's transcript from the archive.
 *
 * silent = called automatically on page load; a conversation that was
 * started but never got a reply is legitimately not archived yet, and
 * surfacing an error toast for that would just be confusing.
 */
async function aiOpenConversation(id, silent = false) {
    try {
        const res = await fetch(AI_CONV_ENDPOINT.replace('__ID__', encodeURIComponent(id)),
            { headers: { 'Accept': 'application/json' } });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json();

        document.getElementById('aiMessages').innerHTML = '';
        document.getElementById('aiMessages').classList.add('hidden');
        document.getElementById('aiEmptyState').classList.remove('hidden');

        aiConversationId = data.id;
        sessionStorage.setItem(AI_CONVERSATION_STORAGE_KEY, data.id);

        const messages = data.messages || [];

        if (messages.length > 0) {
            aiShowThread();

            // Batas ingatan model. Saat konteks kerjanya sudah kedaluwarsa,
            // yang disemai ulang ke model hanya `window` pesan TERAKHIR —
            // layar menampilkan lebih banyak daripada yang diingat model,
            // dan itu harus dikatakan, bukan dibiarkan ditebak dari jawaban
            // yang pelupa.
            const ctx = data.context || {};
            const cut = (!ctx.warm && ctx.window && messages.length > ctx.window)
                ? messages.length - ctx.window
                : -1;

            messages.forEach((m, i) => {
                if (i === cut) aiAppendMemoryDivider();

                m.role === 'user'
                    ? aiAppendUserStored(m.content, m.attachments, m.at, data.id)
                    : aiAppendAssistantStored(m.content, m.at);
            });
        }

        document.getElementById('aiInput').focus();
    } catch {
        if (!silent) showToast('Could not open that conversation.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('aiInput');
    input.placeholder = aiRandomPlaceholder();
    aiAutoGrow(input);
    document.getElementById('aiGreeting').textContent = aiGreeting();
    input.focus();

    // Switching menus and coming back no longer means losing the chat: this
    // tab's conversation id (if any) is reloaded from the archive.
    const saved = sessionStorage.getItem(AI_CONVERSATION_STORAGE_KEY);
    if (saved) aiOpenConversation(saved, true);

    aiPruneAttachmentCache();

    // Klik thumbnail → lightbox. Delegasi, karena thumbnail dibuat dinamis;
    // nama file masuk lewat data-attribute (bukan onclick inline) supaya
    // nama yang mengandung kutip tidak merusak markup.
    document.addEventListener('click', e => {
        const thumb = e.target.closest?.('img.ai-thumb');
        if (thumb) aiOpenLightbox(thumb.dataset.aiFull, thumb.dataset.aiCaption);
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') aiCloseLightbox();
    });
});
</script>
@endpush
