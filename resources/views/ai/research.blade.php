@extends('dashboard')

@section('title', 'AI Research')
@section('page-title', 'AI Research')
@section('page-subtitle', 'Look things up outside EcoSystem')

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

    /* Pratinjau tempelan besar: monospace + gulir sendiri, jangan mendorong
       lebar modal (baris kode panjang tidak boleh melebarkan halaman). */
    .air-paste-preview {
        white-space: pre;
        overflow: auto;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 11.5px;
        line-height: 1.55;
        tab-size: 4;
    }

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
                {{-- Pemilih model DIHAPUS: modelnya ditentukan super admin di
                     Control Center → AI Settings, sama untuk semua orang. --}}
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
                    Ask anything — work related or not. Paste a screenshot (Ctrl + V) or attach an image too.
                    Answers are researched on the web and come with source links.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-6 w-full max-w-2xl text-left">
                    @foreach ([
                        ['fa-image',                'Identify a screenshot', 'Which SAP screen is this, and what is its TCODE?'],
                        ['fa-triangle-exclamation', 'Investigate an error',  'What causes the SAP dump "TSV_TNEW_PAGE_ALLOC_FAILED"?'],
                        ['fa-globe',                'Look anything up',      'Kampus terbaik di Yogyakarta beserta jurusan unggulannya'],
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

            {{-- Chip lampiran + pemakaian jatah unggahan pesan ini --}}
            <p id="airUploadBudget" class="hidden text-[10px] text-gray-400 mb-1.5"></p>
            <div id="airAttachments" class="hidden flex-wrap gap-2 mb-2.5"></div>

            <div id="airDropzone"
                 class="air-dropzone rounded-2xl border border-gray-200 bg-white focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                <textarea id="airInput" rows="1" placeholder="Ask anything…  (Enter to send, Shift + Enter for a new line — long pastes and screenshots become attachments)"
                          oninput="airAutoGrow(this)" onkeydown="airOnKeydown(event)"
                          class="w-full resize-none bg-transparent px-4 pt-3 pb-1 text-sm text-gray-800 placeholder-gray-400 focus:outline-none air-scroll"></textarea>

                <div class="flex items-center gap-1.5 px-2.5 pb-2.5 pt-1">
                    <input type="file" id="airFile" class="hidden" multiple accept="image/*,application/pdf" onchange="airOnFilesPicked(this)">

                    {{-- Tooltip memakai angka dari controller. Yang tertulis di
                         sini sebelumnya ("max 2 files, 5 MB each") sudah lama
                         tidak benar. --}}
                    <button type="button" onclick="document.getElementById('airFile').click()"
                            title="Attach images or PDFs — up to {{ $limits['file_mb'] }} MB per file and {{ $limits['message_mb'] }} MB per message. No limit on how many."
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-paperclip text-sm"></i>
                    </button>

                    <button type="button" onclick="airOpenLimits()" title="Limits and behaviour of this assistant"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fas fa-circle-info text-sm"></i>
                    </button>

                    <span class="flex-1"></span>

                    {{-- Terlihat di semua ukuran layar: batas panjang pesan tidak
                         berhenti berlaku hanya karena layarnya sempit. --}}
                    <span id="airCounter" class="text-[10px] text-gray-400 tabular-nums mr-1">0 / {{ $limits['chars'] }}</span>

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
                <button type="button" onclick="airOpenLimits()" class="underline hover:text-gray-600">Limits &amp; behaviour</button>
            </p>
        </div>
    </section>
</div>

{{-- ── Limits & behaviour ───────────────────────────────────────────────────
     Setiap batas yang bisa ditemui user disebut di SATU tempat, dengan angka
     yang datang dari controller. Sebelum ini batas-batas ini hanya muncul
     sebagai pesan error setelah user terlanjur menabraknya. --}}
<div id="airLimitsModal" class="hidden fixed inset-0 z-[65] items-center justify-center p-4 bg-gray-900/50" onclick="airCloseLimits(event)">
    <div class="w-full max-w-lg max-h-[85vh] overflow-y-auto air-scroll bg-white rounded-2xl border border-gray-200 shadow-xl" onclick="event.stopPropagation()">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100">
            <h3 class="flex-1 text-sm font-bold text-gray-900">Limits &amp; behaviour</h3>
            <button type="button" onclick="airCloseLimits()" title="Close"
                    class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100">
                <i class="fas fa-xmark text-xs"></i>
            </button>
        </div>

        <dl class="px-5 py-4 space-y-3.5 text-[11px] leading-relaxed">
            <div>
                <dt class="text-xs font-semibold text-gray-800">Message length</dt>
                <dd class="text-gray-500 mt-0.5">
                    Up to {{ number_format($limits['chars']) }} characters per message. The counter next to Send turns
                    amber as you approach it and red once you pass it; Send stays disabled until the message fits.
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-800">Attachments per message</dt>
                <dd class="text-gray-500 mt-0.5">
                    Any number of files, up to {{ $limits['file_mb'] }} MB each and {{ $limits['message_mb'] }} MB in
                    total per message — and only if the conversation still has room for them (see below). Only PDF and
                    images (PNG, JPEG, GIF, WEBP) can be read; other file types are skipped, and the reply says which
                    ones.
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-800">Attachments per conversation</dt>
                <dd class="text-gray-500 mt-0.5">
                    About {{ $limits['chat_attachment_mb'] }} MB of attachments per chat, counted
                    <span class="font-semibold text-gray-700">across every message in it</span> — not per message.
                    Files you send stay in the conversation and are re-sent with every follow-up question, which is
                    what makes the assistant able to look at an earlier screenshot again, and also why the budget is
                    shared. This is the tightest of the three limits, so it applies even to your first message. When it
                    is full, new attachments are refused — questions without attachments still work, and starting a new
                    chat resets the budget.
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-800">Answers that get cut off</dt>
                <dd class="text-gray-500 mt-0.5">
                    Each reply has a token ceiling set by your administrator. When a reply reaches it, the assistant
                    resumes itself automatically a few times; if it is still unfinished, the answer stops and a
                    <span class="font-semibold text-gray-700">Continue</span> button appears under it. Continue picks up
                    exactly where the text stopped — it does not repeat what was already written.
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-800">Working memory</dt>
                <dd class="text-gray-500 mt-0.5">
                    Within a chat the assistant reads the whole conversation — including images you attached earlier,
                    which it can look at again — so follow-up questions like "and what about the second one?" work as
                    you would expect. That full context is held for {{ $limits['context_age'] }} after your last
                    message. Once it expires, reopening the chat restores the last {{ $limits['context_messages'] }} messages
                    (about {{ intdiv($limits['context_messages'], 2) }} exchanges) as text only — a line in the
                    transcript marks where the assistant's memory starts, and images are not restored, so re-upload
                    them if a follow-up depends on what they showed.
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-800">What it can and cannot see</dt>
                <dd class="text-gray-500 mt-0.5">
                    This assistant searches the public web only. It has no access to EcoSystem data — tickets, SLA
                    figures, delivery projects, customers or employees. Use the AI Assistant page for those. The model
                    itself is chosen by your administrator in Control Center → AI Settings.
                </dd>
            </div>
        </dl>

        <div class="px-5 py-3 border-t border-gray-100 text-right">
            <button type="button" onclick="airCloseLimits()"
                    class="px-3.5 py-1.5 rounded-xl bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition-all">
                Got it
            </button>
        </div>
    </div>
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

{{-- Pratinjau tempelan besar. Isinya tidak pernah dirender inline di thread:
     8.000 baris di dalam bubble membuat halaman tidak bisa dipakai. --}}
<div id="airPasteModal" onclick="airClosePaste(event)"
     class="hidden fixed inset-0 z-[70] bg-black/60 items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-gray-200 w-full max-w-3xl max-h-[80vh] flex flex-col overflow-hidden">
        <header class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
            <i class="fas fa-align-left text-xs text-gray-400"></i>
            <div class="min-w-0 flex-1">
                <p id="airPasteTitle" class="text-sm font-bold text-gray-900 truncate">Pasted text</p>
                <p id="airPasteMeta" class="text-[11px] text-gray-400"></p>
            </div>
            <button type="button" onclick="airClosePaste()" title="Close"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <i class="fas fa-xmark text-xs"></i>
            </button>
        </header>
        <pre id="airPasteBody" class="air-paste-preview air-scroll flex-1 m-0 px-4 py-3 text-gray-700 bg-gray-50"></pre>
    </div>
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

/* Semua angka batas datang dari controller — tidak ada yang ditulis ulang di
   sini. Duplikatnya dulu yang membuat tooltip lampiran menyebut angka yang
   sudah tidak berlaku selama berbulan-bulan. */
const AIR_MAX_CHARS = @json($limits['chars']);

/* JUMLAH berkas per pesan tidak dibatasi — lihat catatan di
   AiResearchController. Dua angka di bawah dicek di sini hanya supaya user
   dapat pesan yang jelas; server tetap yang menegakkannya.

   AIR_MAX_FILE_MB  : harus sama dengan MAX_ATTACHMENT_KB di controller.
   AIR_MAX_TOTAL_MB : total satu pesan, ditahan di bawah `post_max_size`
                      PHP (30M di produksi). Melewatinya membuat PHP
                      membuang SELURUH body, bukan cuma berkasnya — server
                      punya pagar untuk itu, tapi jauh lebih baik dicegah
                      di sini sebelum 30 MB terlanjur terkirim. */
const AIR_MAX_FILE_MB = @json($limits['file_mb']);
const AIR_MAX_TOTAL_MB = @json($limits['message_mb']);
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

/* ── Tempelan besar ─────────────────────────────────────────────────────────
   Di atas salah satu ambang ini, teks yang di-paste TIDAK masuk ke textarea
   melainkan jadi lampiran .txt — sama seperti composer claude.ai. Alasannya
   bukan kosmetik: `message` dibatasi AIR_MAX_CHARS di server, jadi menempel
   ribuan baris kode ke textarea berakhir sebagai penolakan validasi. */
const AIR_PASTE_MIN_CHARS = @json(\App\Support\AiTextAttachment::PASTE_THRESHOLD_CHARS);
const AIR_PASTE_MIN_LINES = @json(\App\Support\AiTextAttachment::PASTE_THRESHOLD_LINES);

/* Sinkron dengan AiTextAttachment::MAX_CHARS — di atas ini server memotong,
   dan user berhak tahu SEBELUM mengirim, bukan sesudah. */
const AIR_TEXT_MAX_CHARS = @json(\App\Support\AiTextAttachment::MAX_CHARS);

const airPasteMeta = new WeakMap();  // File → {id, lines}
const airPasteById = {};             // id → {lines, chars, text} untuk modal
let airPasteSeq = 0;

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

/**
 * Tinggi textarea + penghitung karakter.
 *
 * Penghitungnya BERWARNA, dan Send ikut mati begitu batas terlampaui: dulu
 * angkanya abu-abu sampai batas berapa pun, lalu pesan panjang baru ditolak
 * lewat toast SETELAH user menekan Send — sudah terlambat untuk berguna.
 */
function airAutoGrow(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';

    const len = el.value.length;
    const over = len > AIR_MAX_CHARS;
    const near = !over && len >= AIR_MAX_CHARS * 0.9;

    const counter = document.getElementById('airCounter');
    counter.textContent = len + ' / ' + AIR_MAX_CHARS + (over ? ' — too long' : '');
    counter.classList.toggle('text-red-600', over);
    counter.classList.toggle('font-semibold', over || near);
    counter.classList.toggle('text-amber-600', near);
    counter.classList.toggle('text-gray-400', !over && !near);

    document.getElementById('airSendBtn').disabled =
        airBusy || over || (len === 0 && airFiles.length === 0);
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

    // Berapa pun jumlahnya boleh, asal totalnya masih muat dalam satu request.
    // Ditambahkan satu per satu supaya yang lewat batas saja yang ditolak,
    // bukan seluruh pilihan user.
    const budget = AIR_MAX_TOTAL_MB * 1024 * 1024;
    let used = airFiles.reduce((sum, f) => sum + f.size, 0);
    const accepted = [];
    const skipped = [];

    incoming.forEach(file => {
        if (used + file.size > budget) {
            skipped.push(file.name);
            return;
        }
        used += file.size;
        accepted.push(file);
    });

    if (skipped.length > 0) {
        showToast(
            skipped.join(', ') + ' — this message would exceed ' + AIR_MAX_TOTAL_MB
                + ' MB in total. Send them in a separate message.',
            'warning',
        );
    }
    if (accepted.length === 0) return;

    airFiles = airFiles.concat(accepted);
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

    airRenderUploadBudget();

    box.innerHTML = airFiles.map((file, i) => {
        const paste = airPasteMeta.get(file);

        // Tempelan besar tampil sebagai chip PASTED yang bisa diklik untuk
        // dilihat isinya — nama berkas buatan ("pasted-1.php") tidak
        // memberi tahu apa pun tentang apa yang barusan ditempel.
        if (paste) {
            return `
            <span class="inline-flex items-center gap-2 pl-2 pr-1.5 py-1.5 rounded-xl border border-gray-200 bg-gray-50 max-w-[240px]">
                <button type="button" onclick="airOpenPaste('${paste.id}')" title="View pasted text"
                        class="flex items-center gap-2 min-w-0 text-left">
                    <span class="px-1.5 py-0.5 rounded-md bg-gray-200/70 text-[9px] font-bold tracking-wide text-gray-700">PASTED</span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold text-gray-700 truncate">${airEsc(file.name)}</span>
                        <span class="block text-[10px] text-gray-400">${paste.lines.toLocaleString()} lines · ${airFileSize(file.size)}</span>
                    </span>
                </button>
                <button type="button" onclick="airRemoveFile(${i})" title="Remove"
                        class="w-5 h-5 shrink-0 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-all">
                    <i class="fas fa-xmark text-[10px]"></i>
                </button>
            </span>`;
        }

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

/**
 * Berapa MB dari jatah satu pesan yang sudah terpakai.
 *
 * Ditampilkan SELAGI memilih berkas, bukan hanya sebagai penolakan setelah
 * berkas ke-sekian ditolak: user berhak tahu ia sedang mendekati batas ketika
 * masih bisa berbuat sesuatu tentang itu.
 */
function airRenderUploadBudget() {
    const line = document.getElementById('airUploadBudget');
    if (airFiles.length === 0) {
        line.classList.add('hidden');
        return;
    }

    const used = airFiles.reduce((sum, f) => sum + f.size, 0) / 1024 / 1024;
    const share = used / AIR_MAX_TOTAL_MB;

    line.textContent = airFiles.length + (airFiles.length > 1 ? ' files · ' : ' file · ')
        + used.toFixed(1) + ' / ' + AIR_MAX_TOTAL_MB + ' MB in this message';
    line.className = 'text-[10px] mb-1.5 ' + (share >= 0.9 ? 'text-amber-600 font-semibold' : 'text-gray-400');
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
    if (!e.clipboardData) return;

    const items = Array.from(e.clipboardData.items || []);
    const images = items
        .filter(item => item.kind === 'file' && item.type.startsWith('image/'))
        .map(item => item.getAsFile())
        .filter(Boolean)
        .map(file => file.name
            ? file
            : new File([file], 'screenshot-' + Date.now() + '.' + (file.type.split('/')[1] || 'png'), { type: file.type }));

    if (images.length > 0) {
        e.preventDefault();
        airAddFiles(images);
        return;
    }

    // Tempelan teks hanya diubah jadi lampiran kalau memang ditujukan ke
    // composer. Listener ini dipasang di document (supaya screenshot bisa
    // ditempel dari mana saja), dan membajak paste ke kolom pencarian atau
    // kolom lain akan terasa seperti kerusakan, bukan seperti fitur.
    if (e.target !== document.getElementById('airInput')) return;

    const text = e.clipboardData.getData('text/plain') || '';
    if (!airIsLargePaste(text)) return;   // tempelan wajar: biarkan default

    e.preventDefault();
    airAddPastedText(text);
}

function airIsLargePaste(text) {
    if (!text) return false;

    return text.length > AIR_PASTE_MIN_CHARS
        || (text.match(/\n/g) || []).length + 1 > AIR_PASTE_MIN_LINES;
}

/**
 * Teks tempelan → berkas .txt biasa.
 *
 * Sengaja lewat jalur lampiran yang sudah ada, bukan lewat field request
 * baru: di server, berkas teks dan tempelan ditangani kode yang sama
 * (App\Support\AiTextAttachment), jadi tidak ada jalur kedua yang bisa
 * menyimpang diam-diam.
 */
function airAddPastedText(text) {
    const lines = (text.match(/\n/g) || []).length + 1;
    const id = 'paste' + (++airPasteSeq) + '-' + Date.now();

    if (text.length > AIR_TEXT_MAX_CHARS) {
        showToast('That paste is very large — only the first '
            + AIR_TEXT_MAX_CHARS.toLocaleString() + ' characters will be sent.', 'warning');
    }

    const file = new File([text], airPasteFileName(text, airPasteSeq), { type: 'text/plain' });

    airPasteMeta.set(file, { id: id, lines: lines });
    airPasteById[id] = { lines: lines, chars: text.length, text: text };

    airAddFiles([file]);
}

/**
 * Ekstensi ditebak dari isinya supaya nama lampiran memberi petunjuk bahasa —
 * model membaca nama berkas, dan "pasted-1.txt" untuk file Blade membuang
 * konteks yang sebenarnya gratis.
 */
function airPasteFileName(text, seq) {
    const head = text.slice(0, 2000);
    let ext = 'txt';

    if (/^\s*[{[]/.test(head))                                      ext = 'json';
    // Escape heksadesimal DIPAKAI DENGAN SENGAJA di baris berikut (\x40 =
    // karakter at, \x7b/\x7d = kurung kurawal): file ini Blade, dan penanda
    // direktif Blade yang ditulis literal di dalam <script> tetap ikut
    // dikompilasi — hasilnya view gagal dirender (500), bukan sekadar regex
    // yang salah.
    else if (/\x40extends|\x40section|\x40php|\x7b\x7b.*\x7d\x7d/.test(head))  ext = 'blade.php';
    else if (/<\?php|namespace\s+\w+|public function /.test(head))   ext = 'php';
    else if (/<\/?(div|html|body|span|table)\b/i.test(head))         ext = 'html';
    else if (/\b(function|const|let|=>|document\.)\b/.test(head))    ext = 'js';
    else if (/\b(SELECT|INSERT|UPDATE|CREATE TABLE)\b/i.test(head))  ext = 'sql';

    return 'pasted-' + seq + '.' + ext;
}

function airOpenPaste(id) {
    const paste = airPasteById[id];
    if (!paste) return;

    document.getElementById('airPasteMeta').textContent =
        paste.lines.toLocaleString() + ' lines · ' + paste.chars.toLocaleString() + ' characters'
        + (paste.chars > AIR_TEXT_MAX_CHARS
            ? ' · only the first ' + AIR_TEXT_MAX_CHARS.toLocaleString() + ' are sent'
            : '');
    document.getElementById('airPasteBody').textContent = paste.text;

    const box = document.getElementById('airPasteModal');
    box.classList.remove('hidden');
    box.classList.add('flex');
}

/** Klik latar (bukan isi panel) menutup — sama seperti lightbox. */
function airClosePaste(e) {
    if (e && e.target !== e.currentTarget) return;

    const box = document.getElementById('airPasteModal');
    box.classList.add('hidden');
    box.classList.remove('flex');
    document.getElementById('airPasteBody').textContent = '';
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

/* ── Limits & behaviour ────────────────────────────────────────────────── */

function airOpenLimits() {
    const box = document.getElementById('airLimitsModal');
    box.classList.remove('hidden');
    box.classList.add('flex');
}

/** Klik latar (bukan isi panel) menutup — sama seperti lightbox. */
function airCloseLimits(e) {
    if (e && e.target !== e.currentTarget) return;

    const box = document.getElementById('airLimitsModal');
    box.classList.add('hidden');
    box.classList.remove('flex');
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
                {{-- Panel batas: jawaban terpotong, dihentikan user, atau gagal.
                     Terpisah dari .air-body supaya tidak pernah tercampur ke
                     dalam teks jawaban (dan tidak ikut tersalin oleh Copy). --}}
                <div class="air-notice hidden mt-2"></div>
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

/* ── Panel batas (terpotong / dihentikan / gagal) ───────────────────────────
   Kenapa panel, bukan teks miring di ujung jawaban seperti sebelumnya:
     - user harus bisa membedakan "ini kata model" dari "ini kata sistem";
     - keadaan ini SELALU punya jalan keluar, dan jalan keluarnya harus berupa
       tombol, bukan kalimat yang menyuruh user melakukan sesuatu sendiri;
     - teks yang menempel di jawaban ikut tersalin tombol Copy.
   ────────────────────────────────────────────────────────────────────────── */

const AIR_NOTICE_TONES = {
    warn:  { box: 'border-amber-200 bg-amber-50',   title: 'text-amber-900', text: 'text-amber-800', icon: 'fa-circle-exclamation text-amber-500' },
    error: { box: 'border-red-200 bg-red-50',       title: 'text-red-900',   text: 'text-red-800',   icon: 'fa-triangle-exclamation text-red-500' },
    info:  { box: 'border-gray-200 bg-gray-50',     title: 'text-gray-800',  text: 'text-gray-500',  icon: 'fa-circle-info text-gray-400' },
};

/**
 * @param {object} n  { title, text, can_continue, tone }
 */
function airRenderNotice(id, n) {
    const wrap = document.getElementById(id);
    if (!wrap) return;

    const box = wrap.querySelector('.air-notice');
    const tone = AIR_NOTICE_TONES[n.tone || 'warn'];

    const continueBtn = n.can_continue ? `
        <button type="button" onclick="airContinue('${id}')"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-[10px] font-semibold hover:bg-indigo-700 transition-all">
            <i class="fas fa-forward text-[9px]"></i> Continue
        </button>` : '';

    box.innerHTML = `
        <div class="rounded-xl border ${tone.box} px-3 py-2.5">
            <div class="flex gap-2">
                <i class="fas ${tone.icon} text-[11px] mt-0.5"></i>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold ${tone.title}">${airEsc(n.title || '')}</p>
                    <p class="text-[11px] ${tone.text} mt-0.5 leading-relaxed">${airEsc(n.text || '')}</p>
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        ${continueBtn}
                        <button type="button" onclick="airNewChat()"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-gray-300 bg-white text-gray-600 text-[10px] font-semibold hover:bg-gray-50 transition-all">
                            <i class="fas fa-plus text-[9px]"></i> Start a new chat
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    box.classList.remove('hidden');

    // Panel bisa muncul SELAGI stream masih jalan (notice datang sebelum 'done'),
    // jadi keadaan tombolnya harus disamakan dengan keadaan sekarang.
    if (airBusy) {
        box.querySelectorAll('button').forEach(btn => {
            btn.disabled = true;
            btn.classList.add('opacity-40', 'cursor-not-allowed');
        });
    }

    airScrollToBottom();
}

function airClearNotice(id) {
    const box = document.querySelector('#' + id + ' .air-notice');
    if (!box) return;
    box.innerHTML = '';
    box.classList.add('hidden');
}

/**
 * Lanjutkan jawaban yang berhenti karena batas — hasilnya di-stream ke bubble
 * YANG SAMA, bukan bubble baru: bagi user ini satu jawaban yang tersambung,
 * dan menyambungnya di tempat lain justru membuat kalimat terpotongnya makin
 * sulit dibaca. Server yang menyusun instruksi lanjutannya (resume=1), jadi
 * tidak ada perintah palsu atas nama user di dalam riwayat.
 */
async function airContinue(id) {
    if (airBusy) return;

    const wrap = document.getElementById(id);
    if (!wrap) return;

    airClearNotice(id);
    airSetBusy(true);
    airSetStatus(id, 'Continuing the answer…');

    try {
        await airSendToBackend('', [], 'default', id, true);
    } catch (err) {
        if (err.name === 'AbortError') {
            airRenderNotice(id, airStoppedNotice(id));
        } else {
            airRenderNotice(id, {
                tone: 'error',
                title: 'Could not continue this answer',
                text: err.message || 'Something went wrong. Try again, or ask the remaining part as a new question.',
                can_continue: !err.airRejected,
            });
        }
    } finally {
        airClearStatus(id);
        airSetBusy(false);
        airLoadHistory();
    }
}

/** Isi panel saat user menekan Stop — beda pesan kalau belum ada teks apa pun. */
function airStoppedNotice(id) {
    const body = document.querySelector('#' + id + ' .air-body');
    const hasText = Boolean(body && (body.dataset.text || '').trim());

    return hasText
        ? {
            tone: 'info',
            title: 'You stopped this answer',
            text: 'What you see above is only the part that had arrived. Continue to resume it from where it stopped, '
                + 'or just ask your next question.',
            can_continue: true,
        }
        : {
            tone: 'info',
            title: 'You stopped this answer',
            text: 'Nothing had been written yet, so there is nothing to resume. Ask the question again when you are ready.',
            can_continue: false,
        };
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
    // Tier dikirim hanya demi kompatibilitas payload; server memakai model
    // yang ditetapkan admin dan mengabaikan nilai ini.
    const model = 'default';

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

            if (stopped) {
                // Jawaban yang dihentikan sendiri oleh user tetap harus jelas
                // statusnya: tanpa ini bubble-nya cuma berhenti begitu saja dan
                // terlihat seperti jawaban yang memang sudah selesai.
                airRenderNotice(pendingId, airStoppedNotice(pendingId));
            } else {
                airRenderNotice(pendingId, {
                    tone: 'error',
                    title: err.airRejected ? 'This message was not sent' : 'The assistant could not finish',
                    text: err.airRejected
                        ? 'Your text and files are still in the composer, so nothing was lost.'
                        : 'Nothing was saved for this turn. Try again, or start a new chat if it keeps failing.',
                    can_continue: false,
                });
            }

            // Pesan DITOLAK sebelum sempat diproses (lampiran penuh, unggahan
            // kebesaran): kembalikan pilihan berkas dan teksnya. Memilih ulang
            // belasan berkas hanya karena kena batas adalah hukuman yang tidak
            // ada gunanya — tidak ada yang terkirim, jadi tidak ada yang hilang.
            if (err.airRejected) {
                airFiles = files;
                airRenderAttachments();
                if (!input.value.trim()) {
                    input.value = text;
                    airAutoGrow(input);
                }
            }
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

    // Tombol di panel batas ikut mati selagi ada giliran berjalan — kalau tidak,
    // Continue terlihat bisa ditekan padahal airContinue() akan mengabaikannya.
    document.querySelectorAll('.air-notice button').forEach(btn => {
        btn.disabled = busy;
        btn.classList.toggle('opacity-40', busy);
        btn.classList.toggle('cursor-not-allowed', busy);
    });

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
 *   notice  → giliran berhenti karena BATAS, bukan karena selesai
 *   done / error → penutup stream (backend selalu mengirim salah satunya)
 *
 * resume = true: giliran "Continue". Tidak ada teks maupun berkas yang dikirim
 * — instruksi lanjutannya disusun server supaya identik dengan penyambung
 * otomatisnya.
 */
async function airSendToBackend(text, files, model, pendingId, resume = false) {
    const controller = new AbortController();
    airAbort = controller;

    const form = new FormData();
    form.append('conversation_id', airEnsureConversationId());
    form.append('message', text);
    form.append('model', model);
    if (resume) form.append('resume', '1');
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
        // Penolakan yang disengaja (lampiran penuh, unggahan kebesaran) datang
        // sebagai JSON dengan alasan yang bisa ditindaklanjuti. Menampilkan
        // 'HTTP 422' saja membuat user mengira sistemnya rusak.
        let reason = null;
        try { reason = (await response.json()).message; } catch { /* bukan JSON */ }
        const err = new Error(reason || 'Could not reach the assistant (HTTP ' + response.status + ').');
        err.airRejected = Boolean(reason);   // ditolak dengan alasan, bukan gagal jaringan
        throw err;
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
                } else if (eventName === 'notice') {
                    airClearStatus(pendingId);
                    airRenderNotice(pendingId, {
                        tone: 'warn',
                        title: payload.title,
                        text: payload.text,
                        can_continue: Boolean(payload.can_continue),
                    });
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
   cache masih berlaku (12 jam). Karena itu percakapan lama tetap bisa dibaca,
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

        const messages = data.messages || [];

        if (messages.length > 0) {
            airShowThread();

            // Batas ingatan model. Saat konteks kerjanya sudah kedaluwarsa,
            // yang disemai ulang ke model hanya `window` pesan TERAKHIR — layar
            // menampilkan lebih banyak daripada yang diingat model, dan itu
            // harus dikatakan, bukan dibiarkan ditebak dari jawaban yang pelupa.
            const ctx = data.context || {};
            const cut = (!ctx.warm && ctx.window && messages.length > ctx.window)
                ? messages.length - ctx.window
                : -1;

            messages.forEach((m, i) => {
                if (i === cut) airAppendMemoryDivider();

                m.role === 'user'
                    ? airAppendUserStored(m.content, m.attachments, m.at)
                    : airAppendAssistantStored(m.content, m.sources, m.at);
            });
        }

        airMarkActive(data.id);
        airCloseHistoryDrawer();
        document.getElementById('airInput').focus();
    } catch {
        if (!silent) showToast('Could not open that conversation.', 'error');
    }
}

async function airDeleteConversation(id, title) {
    if (!await showConfirm('Delete "' + title + '"? This cannot be undone.', 'Delete Conversation', 'danger')) return;

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

/**
 * Garis "dari sini yang diingat model".
 *
 * Ditempatkan DI DALAM transkrip, bukan sebagai banner di atas halaman: yang
 * perlu diketahui user bukan "ada batas", melainkan batasnya ada DI MANA.
 */
function airAppendMemoryDivider() {
    document.getElementById('airMessages').insertAdjacentHTML('beforeend', `
        <div class="flex items-center gap-2 py-1" title="Older messages are still readable here — the assistant just no longer has them in context.">
            <span class="flex-1 h-px bg-gray-200"></span>
            <span class="text-[10px] text-gray-400 px-1 text-center leading-snug">
                The assistant remembers the conversation from here on — earlier messages and any images are no longer in its context
            </span>
            <span class="flex-1 h-px bg-gray-200"></span>
        </div>
    `);
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
        if (e.key !== 'Escape') return;
        airCloseLightbox();
        airCloseLimits();
    });

    const zone = document.getElementById('airDropzone');
    zone.addEventListener('dragover', airOnDragOver);
    zone.addEventListener('dragleave', airOnDragLeave);
    zone.addEventListener('drop', airOnDrop);
});
</script>
@endpush
