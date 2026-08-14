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

    /* Isi jawaban assistant dirender sebagai teks biasa untuk sekarang;
       aturan ini menyiapkan tampilannya saat markdown sudah dipasang. */
    .ai-prose p { margin: 0 0 .6rem; }
    .ai-prose p:last-child { margin-bottom: 0; }
    .ai-prose code { background: rgba(0,0,0,.06); padding: .1rem .3rem; border-radius: .3rem; font-size: .8125rem; }
</style>
@endpush

@section('content')

<div class="ai-shell flex gap-4">

    {{-- ── Rail percakapan ──────────────────────────────────────────────────── --}}
    <aside class="hidden xl:flex w-64 shrink-0 flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="p-3 border-b border-gray-100">
            <button type="button" onclick="aiNewChat()"
                    class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 bg-red-600 text-white text-xs font-semibold rounded-xl hover:bg-red-700 transition-all">
                <i class="fas fa-plus text-[10px]"></i> New Chat
            </button>
            <div class="relative mt-2.5">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                    </svg>
                </span>
                <input type="text" id="aiSearch" placeholder="Search chats…" oninput="aiFilterHistory(this.value)"
                       class="w-full pl-9 pr-3 py-1.5 border border-gray-200 rounded-xl text-xs text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
            </div>
        </div>

        <div class="flex-1 overflow-y-auto ai-scroll px-2 py-2" id="aiHistoryList">
            <p class="px-2 pt-1 pb-1.5 text-[10px] font-semibold text-gray-400 uppercase tracking-widest ai-history-label">Recent</p>
            {{-- Riwayat masih contoh; akan diisi dari server saat API siap. --}}
            <button type="button" class="ai-history-item w-full text-left px-3 py-2 rounded-xl bg-red-50 border border-red-100 mb-1">
                <span class="block text-xs font-semibold text-gray-800 truncate">New conversation</span>
                <span class="block text-[10px] text-gray-400 mt-0.5">Just now</span>
            </button>
        </div>

        <div class="px-3 py-2.5 border-t border-gray-100">
            <p class="text-[10px] text-gray-400 leading-relaxed">
                Chat history is not stored yet. It will be enabled together with the API configuration.
            </p>
        </div>
    </aside>

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
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-[10px] font-semibold text-amber-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Not connected
                    </span>
                </div>
                <p class="text-[11px] text-gray-400 truncate">Waiting for API configuration — the interface is ready</p>
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
            <div id="aiEmptyState" class="h-full flex flex-col items-center justify-center text-center">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white mb-4">
                    <i class="fas fa-wand-magic-sparkles text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900">How can I help you today?</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-md">
                    Ask about tickets, projects, or delivery data. Attach a document and I can summarize it for you.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-6 w-full max-w-2xl text-left">
                    @foreach ([
                        ['fa-ticket-alt',      'Summarize a ticket',      'Summarize the latest open tickets for this week'],
                        ['fa-diagram-project', 'Check project progress',  'Which delivery projects are behind schedule?'],
                        ['fa-envelope-open-text', 'Draft a reply',        'Draft a polite reply telling the customer the issue is resolved'],
                        ['fa-chart-line',      'Explain a report',        'Explain the Collection Outlook numbers for this month'],
                    ] as [$icon, $label, $prompt])
                        <button type="button" onclick="aiUseSuggestion(@js($prompt))"
                                class="group flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-white hover:border-red-300 hover:bg-red-50/40 transition-all">
                            <span class="w-8 h-8 shrink-0 rounded-lg bg-gray-50 group-hover:bg-white flex items-center justify-center text-gray-400 group-hover:text-red-600 transition-all">
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
                    <button type="button" onclick="aiNotYet('Voice input')" title="Voice input"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-microphone text-sm"></i>
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
<script>
/* ──────────────────────────────────────────────────────────────────────────
   AI Assistant — UI saja.

   Semua yang menyentuh backend dikumpulkan di aiSendToBackend(). Ketika
   endpoint chat sudah ada, ganti isi fungsi itu (dan hapus balasan
   placeholder-nya); sisa file ini tidak perlu berubah.
   ────────────────────────────────────────────────────────────────────────── */

const AI_MAX_CHARS = 4000;

let aiFiles     = [];     // File[] yang dipilih untuk pesan berikutnya
let aiBusy      = false;  // sedang menunggu balasan
let aiAbort     = null;   // pembatal balasan berjalan

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

function aiResolveAssistant(id, text, isError) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    const body = wrap.querySelector('.ai-body');
    body.innerHTML = `<p class="whitespace-pre-wrap break-words${isError ? ' text-red-600' : ''}">${aiEsc(text)}</p>`;

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

    aiShowThread();
    aiAppendUser(text, files);

    input.value = '';
    aiFiles = [];
    aiRenderAttachments();
    aiAutoGrow(input);

    aiSetBusy(true);
    const pendingId = aiAppendAssistantPending();

    aiSendToBackend(text, files)
        .then(reply => aiResolveAssistant(pendingId, reply, false))
        .catch(err  => aiResolveAssistant(pendingId, err.message || 'Something went wrong.', true))
        .finally(()  => aiSetBusy(false));
}

function aiSetBusy(busy) {
    aiBusy = busy;
    document.getElementById('aiSendBtn').classList.toggle('hidden', busy);
    document.getElementById('aiStopBtn').classList.toggle('hidden', !busy);
    aiAutoGrow(document.getElementById('aiInput'));
}

function aiStop() {
    if (aiAbort) aiAbort();
}

/**
 * SATU-SATUNYA titik sentuh backend.
 *
 * Sementara ini mengembalikan balasan placeholder supaya alur UI bisa diuji.
 * Saat konfigurasi API siap, ganti isinya dengan fetch ke endpoint chat —
 * kirim `text` + `files` sebagai FormData, dan buang seluruh blok placeholder.
 */
function aiSendToBackend(text, files) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            aiAbort = null;
            resolve(
                'The assistant is not connected to a provider yet, so I cannot answer this.\n\n' +
                'The interface is complete — sending, attachments, and the conversation view all work. ' +
                'Once the API configuration is in place, this reply will be replaced by a real answer.'
            );
        }, 900);

        aiAbort = () => {
            clearTimeout(timer);
            aiAbort = null;
            reject(new Error('Generation stopped.'));
        };
    });
}

/* ── Percakapan ────────────────────────────────────────────────────────── */

function aiNewChat() {
    if (aiBusy) aiStop();

    document.getElementById('aiMessages').innerHTML = '';
    document.getElementById('aiMessages').classList.add('hidden');
    document.getElementById('aiEmptyState').classList.remove('hidden');

    aiFiles = [];
    aiRenderAttachments();

    const input = document.getElementById('aiInput');
    input.value = '';
    aiAutoGrow(input);
    input.focus();
}

function aiFilterHistory(term) {
    const q = term.trim().toLowerCase();

    document.querySelectorAll('#aiHistoryList .ai-history-item').forEach(item => {
        item.classList.toggle('hidden', q !== '' && !item.textContent.toLowerCase().includes(q));
    });

    // Sembunyikan label grup saat tidak ada isi yang cocok.
    const anyVisible = !!document.querySelector('#aiHistoryList .ai-history-item:not(.hidden)');
    document.querySelectorAll('#aiHistoryList .ai-history-label').forEach(el => {
        el.classList.toggle('hidden', !anyVisible);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('aiInput');
    aiAutoGrow(input);
    input.focus();
});
</script>
@endpush
