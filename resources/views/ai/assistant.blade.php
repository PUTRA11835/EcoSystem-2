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
                <select id="aiModel"
                        class="hidden sm:block px-2.5 py-1.5 border border-gray-200 rounded-xl text-[11px] font-semibold text-gray-600 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                    <option value="default">Default model</option>
                    <option value="fast">Fast</option>
                    <option value="reasoning">Reasoning</option>
                </select>
                <button type="button" onclick="aiNewChat()" title="Clear conversation"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
                    <i class="fas fa-rotate-left text-xs"></i>
                </button>
            </div>
        </header>

        {{-- Thread --}}
        <div id="aiThread" class="flex-1 overflow-y-auto ai-scroll px-4 sm:px-6 py-5">

            {{-- Empty state --}}
            <div id="aiEmptyState" class="min-h-full flex flex-col items-center justify-center py-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white mb-4">
                    <i class="fas fa-wand-magic-sparkles text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900">How can I help you today?</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-md">
                    Ask about tickets, projects, or delivery data. Attach a document and I can summarize it for you.
                </p>

                <p class="text-[10.5px] font-semibold text-gray-400 uppercase tracking-wide mt-6 mb-2.5">Pilih topik supaya lebih spesifik</p>
                <div id="aiTopics" class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full max-w-xl text-left"></div>
            </div>

            {{-- Pesan disisipkan di sini --}}
            <div id="aiMessages" class="space-y-5 hidden"></div>
        </div>

        {{-- Composer --}}
        <div class="border-t border-gray-100 px-3 sm:px-5 py-3">

            {{-- Chip lampiran --}}
            <div id="aiAttachments" class="hidden flex-wrap gap-2 mb-2.5"></div>

            <div class="rounded-2xl border border-gray-200 bg-white focus-within:border-red-400 focus-within:ring-2 focus-within:ring-red-100 transition-all">
                <textarea id="aiInput" rows="1" placeholder="Send a message…  (Enter to send, Shift + Enter for a new line)"
                          oninput="aiAutoGrow(this)" onkeydown="aiOnKeydown(event)"
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
                Responses may be inaccurate — always verify important information before acting on it.
            </p>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<script>
/* ──────────────────────────────────────────────────────────────────────────
   AI Assistant.

   Backend touchpoint is aiSendToBackend(), which streams the reply from
   POST /ai-assistant/chat as Server-Sent Events. No conversation history is
   stored server-side beyond a short-lived cache keyed by a client-generated
   conversation id (see aiEnsureConversationId()) — refreshing or switching
   tabs keeps the same conversation id, so the backend's cached context
   picks up where it left off; a brand-new tab starts a fresh conversation.
   ────────────────────────────────────────────────────────────────────────── */

const AI_MAX_CHARS = 4000;
const AI_CHAT_ENDPOINT = @json(route('ai-assistant.chat'));
const AI_CONVERSATION_STORAGE_KEY = 'ai_conversation_id';

let aiFiles     = [];     // File[] yang dipilih untuk pesan berikutnya
let aiBusy      = false;  // sedang menunggu balasan
let aiAbort     = null;   // AbortController pembatal request berjalan
let aiConversationId = null;
let aiActiveTopic = null; // tag topik yang sedang dipilih di empty state

/* Topik pembuka — dipetakan dari 8 kategori yang assistant sendiri sebutkan
   saat ditanya "bisa bantu apa saja". Memilih satu menyisipkan "#Tag" di
   awal pesan supaya pertanyaan lebih spesifik dan gampang ditelusuri. */
const AI_TOPICS = [
    { tag: 'TiketSupport',  icon: 'fa-ticket',           label: 'Tiket Support',          desc: 'Status, prioritas & ringkasan tiket' },
    { tag: 'SLA',           icon: 'fa-clock',             label: 'SLA',                     desc: 'Tenggat, tiket berisiko & kebijakan SLA' },
    { tag: 'ProyekDelivery',icon: 'fa-diagram-project',   label: 'Proyek Delivery',         desc: 'Progres, jadwal & data finansial proyek' },
    { tag: 'Mandays',       icon: 'fa-business-time',     label: 'Mandays',                 desc: 'Pengajuan mandays & resolution days' },
    { tag: 'DataKaryawan',  icon: 'fa-users',             label: 'Data Karyawan/HR',        desc: 'Kepegawaian, divisi & jabatan' },
    { tag: 'DataCustomer',  icon: 'fa-address-book',      label: 'Data Customer',           desc: 'Informasi terkait pelanggan' },
    { tag: 'Timesheet',     icon: 'fa-table-list',        label: 'Timesheet & Master Data', desc: 'Timesheet & data referensi lainnya' },
    { tag: 'DraftBalasan',  icon: 'fa-reply',             label: 'Draft Balasan',           desc: 'Bantuan menulis balasan ke customer' },
];

function aiEnsureConversationId() {
    if (aiConversationId) return aiConversationId;

    aiConversationId = sessionStorage.getItem(AI_CONVERSATION_STORAGE_KEY);
    if (!aiConversationId) {
        aiConversationId = crypto.randomUUID();
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

function aiUseSuggestion(text) {
    const input = document.getElementById('aiInput');
    input.value = text;
    input.focus();
    aiAutoGrow(input);
}

function aiNotYet(feature) {
    showToast((feature || 'This feature') + ' is not available yet.', 'warning');
}

/* ── Topik pembuka ─────────────────────────────────────────────────────── */

function aiRenderTopics() {
    const box = document.getElementById('aiTopics');
    if (!box) return;

    box.innerHTML = AI_TOPICS.map(t => {
        const active = aiActiveTopic === t.tag;
        return `
        <button type="button" onclick="aiSelectTopic('${t.tag}')"
                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border text-left transition-all ${active
                    ? 'border-red-500 bg-red-50 ring-1 ring-red-200'
                    : 'border-gray-200 bg-white hover:border-red-300 hover:bg-red-50/40'}">
            <span class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center ${active ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-500'}">
                <i class="fas ${t.icon} text-xs"></i>
            </span>
            <span class="min-w-0">
                <span class="block text-xs font-semibold ${active ? 'text-red-700' : 'text-gray-800'}">${aiEsc(t.label)}</span>
                <span class="block text-[10.5px] text-gray-400 truncate">${aiEsc(t.desc)}</span>
            </span>
        </button>`;
    }).join('');
}

/** Klik topik menyisipkan/mengganti "#Tag" di awal input. Klik ulang topik
 *  yang sama membatalkan pilihannya (hashtag dilepas dari input). */
function aiSelectTopic(tag) {
    const input = document.getElementById('aiInput');
    let value = input.value;

    const current = AI_TOPICS.find(t => value.trimStart().startsWith('#' + t.tag));
    if (current) {
        value = value.trimStart().slice(('#' + current.tag).length).trimStart();
    }

    aiActiveTopic = (aiActiveTopic === tag) ? null : tag;
    input.value = aiActiveTopic ? `#${aiActiveTopic} ${value}` : value;

    aiRenderTopics();
    aiAutoGrow(input);
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
}

/* ── Lampiran ──────────────────────────────────────────────────────────── */

function aiOnFilesPicked(input) {
    aiFiles = aiFiles.concat(Array.from(input.files || []));
    input.value = '';  // supaya file yang sama bisa dipilih lagi
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

    box.innerHTML = aiFiles.map((file, i) => `
        <span class="inline-flex items-center gap-2 pl-2.5 pr-1.5 py-1.5 rounded-xl border border-gray-200 bg-gray-50 max-w-[220px]">
            <i class="fas ${aiFileIcon(file.name)} text-xs text-gray-400"></i>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold text-gray-700 truncate">${aiEsc(file.name)}</span>
                <span class="block text-[10px] text-gray-400">${aiFileSize(file.size)}</span>
            </span>
            <button type="button" onclick="aiRemoveFile(${i})" title="Remove"
                    class="w-5 h-5 shrink-0 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-all">
                <i class="fas fa-xmark text-[10px]"></i>
            </button>
        </span>
    `).join('');
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

function aiTime() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
    const chips = files.length === 0 ? '' : `
        <div class="flex flex-wrap gap-1.5 justify-end mt-2">
            ${files.map(f => `
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-white/15 border border-white/20">
                    <i class="fas ${aiFileIcon(f.name)} text-[10px]"></i>
                    <span class="text-[10px] font-medium truncate max-w-[140px]">${aiEsc(f.name)}</span>
                </span>`).join('')}
        </div>`;

    const body = text ? `<p class="whitespace-pre-wrap break-words">${aiEsc(text)}</p>` : '';

    document.getElementById('aiMessages').insertAdjacentHTML('beforeend', `
        <div class="ai-bubble-in flex justify-end gap-3">
            <div class="max-w-[85%] sm:max-w-[70%]">
                <div class="bg-red-600 text-white text-sm rounded-2xl rounded-br-md px-4 py-2.5">
                    ${body}${chips}
                </div>
                <p class="text-[10px] text-gray-400 mt-1 text-right">${aiTime()}</p>
            </div>
        </div>
    `);
    aiScrollToBottom();
}

/** Bubble assistant kosong + indikator mengetik. Kembalikan id-nya. */
function aiAppendAssistantPending() {
    const id = 'aiMsg' + Date.now();

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
                    <span class="text-[10px] text-gray-400 ml-1">${aiTime()}</span>
                </div>
            </div>
        </div>
    `);
    aiScrollToBottom();
    return id;
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
    const model = document.getElementById('aiModel')?.value || 'default';

    aiShowThread();
    aiAppendUser(text, files);

    input.value = '';
    aiFiles = [];
    aiRenderAttachments();
    aiAutoGrow(input);

    aiActiveTopic = null;

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

    aiActiveTopic = null;
    aiRenderTopics();

    // Start a fresh conversation — the backend's cached context for the old
    // id is simply left to expire, nothing to explicitly tear down.
    aiConversationId = null;
    sessionStorage.removeItem(AI_CONVERSATION_STORAGE_KEY);

    const input = document.getElementById('aiInput');
    input.value = '';
    aiAutoGrow(input);
    input.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('aiInput');
    aiAutoGrow(input);
    aiRenderTopics();
    input.focus();
});
</script>
@endpush
