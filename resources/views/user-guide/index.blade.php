@extends('dashboard')
@section('title', 'User Guide')
@section('page-title', 'User Guide')
@section('page-subtitle', $ui['subtitle'])

@php
    $pdfUrl = route('user-guide.pdf') . '?' . http_build_query(['lang' => $locale]);
    $updatedHuman = $updated ? \Carbon\Carbon::parse($updated)->locale($locale === 'id' ? 'id' : 'en')->translatedFormat('d M Y') : null;
    $iconFor = fn (string $type) => ['note' => 'fa-circle-info', 'tip' => 'fa-lightbulb', 'important' => 'fa-circle-exclamation', 'warning' => 'fa-triangle-exclamation'][$type] ?? 'fa-circle-info';
@endphp

@push('styles')
<style>
    /* ── User Guide ─────────────────────────────────────────────────────────── */
    .ug { --ug-accent: var(--primary-color, #991b1b); }
    .ug-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .875rem; }
    /* Tata letak dua kolom (daftar isi di samping artikel). Ditulis sebagai CSS biasa karena build Tailwind aplikasi (public/build) bisa belum memuat
       kelas lg:grid-cols-12 / lg:col-span-* / lg:sticky yang dipakai di sini. Nilainya sama dengan kelas Tailwind itu, jadi aman bila build memuatnya. */
    .ug-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
    .ug-aside, .ug-main { min-width: 0; grid-column: auto !important; }
    @media (min-width: 1024px) {
        .ug-layout { grid-template-columns: minmax(0, 4fr) minmax(0, 8fr); }
        .ug-aside { position: sticky; top: 6rem; }
        .ug-toc-scroll { max-height: calc(100vh - 8rem); overflow-y: auto; }
        #ugTocBox > summary { display: none; }
    }
    @media (min-width: 1280px) { .ug-layout { grid-template-columns: minmax(0, 3fr) minmax(0, 9fr); } }
    #ugSearch:focus { outline: none; box-shadow: 0 0 0 3px #c7d2fe; }
    /* Pembungkus konten di layout dashboard memakai overflow-x-hidden, yang menjadikannya kontainer gulir dan mematikan position: sticky
       (daftar isi tidak menempel). Hanya pada halaman ini, `clip` tetap memotong luapan mendatar tanpa menjadi kontainer gulir. */
    .overflow-x-hidden:has(.ug-layout) { overflow-x: clip; overflow-y: visible; }
    /* Daftar isi: bab = kartu bernomor; topik = baris bernomor; sub-judul topik aktif tampil di bawahnya */
    .ug-toc .ug-chap { border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; margin-bottom: .5rem; overflow: hidden; }
    .ug-chap-btn { display: flex; width: 100%; align-items: center; gap: .6rem; padding: .55rem .65rem; text-align: left; background: #f9fafb; border: 0; cursor: pointer; }
    .ug-chap-btn:hover { background: #f3f4f6; }
    .ug-chap-no { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 1.6rem; height: 1.6rem; border-radius: 9999px; background: var(--ug-accent); color: #fff; font-size: .7rem; font-weight: 700; }
    .ug-chap-title { flex: 1; min-width: 0; font-size: .8125rem; font-weight: 700; color: #111827; line-height: 1.2; }
    .ug-chap-count { flex: none; font-size: .6875rem; color: #9ca3af; font-weight: 600; }
    .ug-chap-btn i { transition: transform .15s; color: #9ca3af; font-size: .625rem; }
    .ug-chap.is-closed .ug-chap-btn i { transform: rotate(-90deg); }
    .ug-chap.is-closed .ug-chap-list { display: none; }
    .ug-chap-list { padding: .3rem; display: flex; flex-direction: column; gap: .1rem; border-top: 1px solid #f3f4f6; }
    .ug-toc a.ug-t { display: flex; align-items: baseline; gap: .5rem; border-radius: .5rem; padding: .38rem .5rem; font-size: .8125rem; color: #374151; line-height: 1.25; border-left: 3px solid transparent; }
    .ug-toc a.ug-t:hover { background: #f3f4f6; }
    .ug-toc a.ug-t.is-active { background: color-mix(in srgb, var(--ug-accent) 9%, transparent); color: var(--ug-accent); font-weight: 600; border-left-color: var(--ug-accent); }
    .ug-t-no { flex: none; min-width: 1.9rem; font-size: .6875rem; font-weight: 700; color: #9ca3af; font-variant-numeric: tabular-nums; }
    .ug-t.is-active .ug-t-no { color: var(--ug-accent); }
    .ug-toc .ug-sub { margin: .1rem 0 .3rem 2.55rem; border-left: 2px solid #e5e7eb; }
    .ug-toc .ug-sub a { display: block; font-size: .75rem; padding: .22rem .5rem; color: #6b7280; line-height: 1.25; border-radius: 0 .375rem .375rem 0; }
    .ug-toc .ug-sub a:hover { background: #f3f4f6; color: #111827; }
    .ug-toc .ug-sub a.is-active { color: var(--ug-accent); font-weight: 600; }
    .ug-tools { display: flex; gap: .4rem; margin: 0 0 .5rem; }
    .ug-tools button { font-size: .6875rem; font-weight: 600; color: #4b5563; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .2rem .55rem; background: #fff; }
    .ug-tools button:hover { background: #f3f4f6; }
    .ug-seg { display: inline-flex; border: 1px solid #d1d5db; border-radius: .625rem; padding: 2px; background: #f9fafb; }
    .ug-seg a { padding: .3rem .7rem; font-size: .75rem; font-weight: 600; border-radius: .45rem; color: #4b5563; white-space: nowrap; }
    .ug-seg a.is-on { background: var(--ug-accent); color: #fff; }

    .ug-chapter { scroll-margin-top: 6rem; }
    .ug-no { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 .5rem; margin-right: .5rem; border-radius: .6rem; background: var(--ug-accent); color: #fff; font-size: .95rem; font-weight: 800; vertical-align: .1rem; }
    .ug-topic-no { color: var(--ug-accent); font-weight: 800; margin-right: .35rem; font-variant-numeric: tabular-nums; }
    .ug-chapter-title { font-size: 1.375rem; font-weight: 800; color: #111827; padding-bottom: .5rem; border-bottom: 2px solid var(--ug-accent); margin-bottom: 1rem; }
    .ug-topic { scroll-margin-top: 6rem; padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .ug-topic-title { font-size: 1.125rem; font-weight: 700; color: #111827; }
    .ug-summary { color: #6b7280; font-size: .875rem; margin-top: .25rem; }
    .ug-meta { margin-top: 1rem; padding-top: .6rem; border-top: 1px dashed #e5e7eb; display: flex; justify-content: space-between; gap: .5rem; flex-wrap: wrap; font-size: .6875rem; color: #9ca3af; }
    .ug-meta a { color: var(--ug-accent); font-weight: 600; }

    .guide-body { font-size: .9rem; line-height: 1.65; color: #1f2937; margin-top: .75rem; overflow-wrap: anywhere; }
    .guide-body h2 { scroll-margin-top: 6rem; font-size: 1rem; font-weight: 700; margin: 1.25rem 0 .4rem; color: #111827; }
    .guide-body h3 { scroll-margin-top: 6rem; font-size: .9rem; font-weight: 700; margin: 1rem 0 .3rem; color: #374151; }
    .guide-body p { margin: .5rem 0; }
    .guide-body ul, .guide-body ol { margin: .5rem 0 .5rem 1.4rem; }
    .guide-body ul { list-style: disc; } .guide-body ol { list-style: decimal; }
    .guide-body li { margin: .2rem 0; padding-left: .15rem; }
    .guide-body li::marker { color: var(--ug-accent); font-weight: 700; }
    .guide-body a { color: var(--ug-accent); text-decoration: underline; text-underline-offset: 2px; }
    /* Nama menu / tombol / kolom di layar dibuat menonjol (ditulis dengan `kode` di Markdown) */
    .guide-body code { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: .375rem; padding: .05rem .35rem; font-size: .8125rem; font-weight: 600; color: #111827; font-family: ui-sans-serif, system-ui, sans-serif; white-space: nowrap; }
    .guide-body strong { font-weight: 700; color: #111827; }
    .guide-body hr { margin: 1rem 0; border-color: #e5e7eb; }
    .guide-body img { display: block; max-width: 100%; height: auto; margin: .75rem 0; border: 1px solid #e5e7eb; border-radius: .625rem; }
    .guide-body .g-table { overflow-x: auto; margin: .75rem 0; border: 1px solid #e5e7eb; border-radius: .625rem; }
    .guide-body table { width: 100%; border-collapse: collapse; font-size: .8125rem; min-width: 28rem; }
    .guide-body th { background: #f9fafb; text-align: left; font-weight: 700; padding: .5rem .75rem; border-bottom: 1px solid #e5e7eb; }
    .guide-body td { padding: .5rem .75rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    .guide-body tr:last-child td { border-bottom: 0; }
    .callout { border-left: 4px solid; border-radius: .5rem; padding: .6rem .9rem; margin: .8rem 0; font-size: .875rem; }
    .callout p { margin: .2rem 0; }
    .callout-title { font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; }
    .callout-note { background: #eff6ff; border-color: #3b82f6; color: #1e3a8a; }
    .callout-tip { background: #ecfdf5; border-color: #10b981; color: #064e3b; }
    .callout-important { background: #fffbeb; border-color: #f59e0b; color: #78350f; }
    .callout-warning { background: #fef2f2; border-color: #ef4444; color: #7f1d1d; }
    .callout code { background: rgba(255,255,255,.7); }
    @media (max-width: 640px) { .guide-body code { white-space: normal; overflow-wrap: anywhere; } }   /* jalur menu panjang boleh turun baris di ponsel */
    mark.ug-hit { background: #fde68a; color: inherit; border-radius: .2rem; padding: 0 .1rem; }
    .ug-flash { animation: ugflash 1.6s ease-out; }
    @keyframes ugflash { 0% { background: #fef3c7; } 100% { background: transparent; } }
    .ug-res { display: block; padding: .5rem .6rem; border-radius: .5rem; font-size: .8125rem; }
    .ug-res:hover, .ug-res.is-focus { background: #f3f4f6; }
    .ug-res small { display: block; color: #6b7280; font-size: .6875rem; margin-top: .1rem; }
    @media (prefers-reduced-motion: reduce) { .ug-flash { animation: none; } html { scroll-behavior: auto; } }
</style>
@endpush

@section('content')
<div class="ug w-full space-y-4">

    {{-- Barang atas: bahasa, PDF, tanggal --}}
    <div class="ug-card px-5 py-4 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-bold text-gray-900">{{ $ui['title'] }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                @if($visibleCount > 0)<i class="fas fa-user-check mr-1"></i>{{ $ui['tailored'] }} · {{ $visibleCount }} {{ $ui['topics_of'] }} @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="ug-seg" role="group" aria-label="{{ $ui['language'] }}">
                @foreach($locales as $code => $name)
                    <a href="?{{ http_build_query(['lang' => $code]) }}" data-lang="{{ $code }}" class="{{ $code === $locale ? 'is-on' : '' }}" @if($code === $locale) aria-current="true" @endif hreflang="{{ $code }}">{{ $code === 'en' ? 'English' : 'Indonesia' }}</a>
                @endforeach
            </div>
            @if($visibleCount > 0)
                <a href="{{ $pdfUrl }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-file-pdf"></i> {{ $ui['download_pdf'] }}</a>
            @endif
            @if($updatedHuman)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-gray-200 text-gray-600 text-xs"><i class="far fa-clock"></i> {{ $ui['updated'] }} {{ $updatedHuman }}</span>
            @endif
        </div>
    </div>

    @if($visibleCount === 0)
        <div class="ug-card px-5 py-12 text-center text-sm text-gray-500"><i class="far fa-folder-open text-2xl text-gray-300 block mb-2"></i>{{ $ui['empty'] }}</div>
    @else
    <div class="ug-layout grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

        {{-- Daftar isi + pencarian --}}
        <aside class="ug-aside lg:col-span-4 xl:col-span-3 min-w-0 lg:sticky lg:top-24" aria-label="{{ $ui['toc'] }}">
            <details class="ug-card lg:!block" id="ugTocBox" open>
                <summary class="lg:hidden px-4 py-3 text-sm font-bold text-gray-900 cursor-pointer flex items-center justify-between">{{ $ui['toc'] }} <i class="fas fa-chevron-down text-xs text-gray-400"></i></summary>
                <div class="ug-toc-scroll p-3 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
                    <p class="hidden lg:block px-1 text-sm font-bold text-gray-900">{{ $ui['toc'] }}</p>
                    <p class="hidden lg:block px-1 text-[11px] text-gray-500 mb-2">{{ $ui['toc_hint'] }}</p>
                    <div class="relative mb-2">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input id="ugSearch" type="search" autocomplete="off" spellcheck="false" placeholder="{{ $ui['search_toc'] }}" aria-label="{{ $ui['search_toc'] }}"
                               class="w-full pl-8 pr-8 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white">
                        <button type="button" id="ugSearchClear" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-xs" aria-label="Clear"><i class="fas fa-times"></i></button>
                    </div>
                    <div id="ugResults" class="hidden" role="listbox" aria-live="polite"></div>
                    <div class="ug-tools hidden" id="ugTools">
                        <button type="button" id="ugExpandAll"><i class="fas fa-angles-down mr-1"></i>{{ $ui['expand_all'] }}</button>
                        <button type="button" id="ugCollapseAll"><i class="fas fa-angles-up mr-1"></i>{{ $ui['collapse_all'] }}</button>
                    </div>
                    <nav id="ugToc" class="ug-toc" aria-label="{{ $ui['toc'] }}">
                        @foreach($chapters as $chapter)
                            <div class="ug-chap" data-chapter="{{ $chapter['id'] }}">
                                <button type="button" class="ug-chap-btn" aria-expanded="true" title="{{ $ui['chapter'] }} {{ $chapter['no'] }}">
                                    <span class="ug-chap-no">{{ $chapter['no'] }}</span>
                                    <span class="ug-chap-title">{{ $chapter['title'] }}</span>
                                    <span class="ug-chap-count">{{ count($chapter['topics']) }}</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <div class="ug-chap-list">
                                    @foreach($chapter['topics'] as $key => $topic)
                                        <a href="#topic-{{ $key }}" class="ug-t" data-topic="{{ $key }}"><span class="ug-t-no">{{ $topic['no'] }}</span><span>{{ $topic['title'] }}</span></a>
                                        <div class="ug-sub hidden" data-sub="{{ $key }}">
                                            @foreach($topic['headings'] as $h)@if($h['level'] === 2)<a href="#{{ $h['id'] }}" data-h="{{ $h['id'] }}">{{ $h['text'] }}</a>@endif @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </nav>
                </div>
            </details>
        </aside>

        {{-- Isi panduan --}}
        <main class="ug-main lg:col-span-8 xl:col-span-9 min-w-0" id="ugContent">
            @foreach($chapters as $chapter)
                <section class="ug-chapter mb-6" id="chapter-{{ $chapter['id'] }}">
                    <h2 class="ug-chapter-title"><span class="ug-no">{{ $chapter['no'] }}</span>{{ $chapter['title'] }}</h2>
                    @foreach($chapter['topics'] as $key => $topic)
                        <article class="ug-card ug-topic" id="topic-{{ $key }}" data-topic="{{ $key }}">
                            <h3 class="ug-topic-title"><span class="ug-topic-no">{{ $topic['no'] }}</span>{{ $topic['title'] }}</h3>
                            @if($topic['summary'])<p class="ug-summary">{{ $topic['summary'] }}</p>@endif
                            @if(!empty($topic['fallback']))<div class="callout callout-note"><p class="callout-title"><i class="fas fa-language mr-1"></i>{{ $ui['note'] }}</p><p>{{ $ui['untranslated'] }}</p></div>@endif
                            <div class="guide-body">{!! $topic['html'] !!}</div>
                            <div class="ug-meta">
                                <span>@if($topic['updated']){{ $ui['topic_updated'] }} {{ \Carbon\Carbon::parse($topic['updated'])->locale($locale === 'id' ? 'id' : 'en')->translatedFormat('d M Y') }}@endif</span>
                                <a href="#ugContent" data-top><i class="fas fa-arrow-up mr-1"></i>{{ $ui['back_top'] }}</a>
                            </div>
                        </article>
                    @endforeach
                </section>
            @endforeach
        </main>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var LANG = @json($locale), LANG_KEY = 'ecosystem:guide:lang', INDEX = @json($index), NO_RES = @json($ui['no_results']), RES_WORD = @json($ui['results']);
    var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    // ── Bahasa: ingat pilihan di peramban ini (bila tak ada ?lang= dan pilihan tersimpan berbeda, terapkan) ──
    try {
        var url = new URL(location.href), stored = localStorage.getItem(LANG_KEY);
        if (!url.searchParams.has('lang') && stored && stored !== LANG && /^(en|id)$/.test(stored)) { url.searchParams.set('lang', stored); location.replace(url.toString()); return; }
        $$('.ug-seg a').forEach(function (a) { a.addEventListener('click', function () { try { localStorage.setItem(LANG_KEY, a.getAttribute('data-lang')); } catch (e) {} }); });
    } catch (e) { /* penyimpanan dinonaktifkan: pilihan tidak diingat */ }

    // Di layar kecil daftar isi tertutup dulu (isi panduan langsung terlihat); di desktop selalu terbuka.
    (function () { var box = $('#ugTocBox'); if (box && matchMedia('(max-width: 1023px)').matches && !location.hash) box.open = false; })();

    // ── Daftar isi: bab dapat dilipat; awalnya hanya bab yang sedang dibaca yang terbuka ──
    function chapState(c, open) { c.classList.toggle('is-closed', !open); var b = $('.ug-chap-btn', c); if (b) b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    $$('.ug-chap-btn').forEach(function (b) { b.addEventListener('click', function () { var c = b.closest('.ug-chap'); chapState(c, c.classList.contains('is-closed')); }); });
    (function () {
        var chaps = $$('.ug-chap'); if (chaps.length < 2) return;
        var hash = location.hash ? decodeURIComponent(location.hash.slice(1)) : '', el = hash ? document.getElementById(hash) : null, tk = el && el.closest('.ug-topic') ? el.closest('.ug-topic').getAttribute('data-topic') : null;
        var first = tk ? $('#ugToc a[data-topic="' + tk + '"]') : null, keep = first ? first.closest('.ug-chap') : chaps[0];
        chaps.forEach(function (c) { chapState(c, c === keep); });
        var tools = $('#ugTools'); if (tools) tools.classList.remove('hidden');
        $('#ugExpandAll').addEventListener('click', function () { chaps.forEach(function (c) { chapState(c, true); }); });
        $('#ugCollapseAll').addEventListener('click', function () { chaps.forEach(function (c) { chapState(c, false); }); });
    })();

    // ── Klik daftar isi: gulir halus + sorot; di layar kecil tutup kotak daftar isi ──
    function go(id, push) {
        var el = document.getElementById(id); if (!el) return;
        el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        el.classList.remove('ug-flash'); void el.offsetWidth; el.classList.add('ug-flash');
        if (push !== false) history.replaceState(null, '', '#' + id);
        var tk = id.indexOf('topic-') === 0 ? id.slice(6) : (el.closest && el.closest('.ug-topic') ? el.closest('.ug-topic').getAttribute('data-topic') : null);
        if (tk) { lockUntil = Date.now() + 1500; setActive(tk); }   // yang baru diklik langsung aktif (tak ditimpa pengamat gulir)
        if (matchMedia('(max-width: 1023px)').matches) { var box = $('#ugTocBox'); if (box) box.open = false; }
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href^="#"]'); if (!a || !a.closest('.ug')) return;
        var id = decodeURIComponent(a.getAttribute('href').slice(1)); if (!id || !document.getElementById(id)) return;
        e.preventDefault(); go(id);
    });
    if (location.hash) { setTimeout(function () { go(decodeURIComponent(location.hash.slice(1)), false); }, 250); }

    // ── Scroll-spy: topik aktif disorot dan anak judulnya dibuka ──
    var topics = $$('.ug-topic'), active = null, lockUntil = 0;
    function setActive(key) {
        var act = $('#ugToc a[data-topic="' + key + '"]'), ch = act ? act.closest('.ug-chap') : null; if (ch && ch.classList.contains('is-closed')) chapState(ch, true);   // bab yang sedang dibaca otomatis terbuka
        if (key === active) return; active = key;
        $$('#ugToc a[data-topic]').forEach(function (a) { a.classList.toggle('is-active', a.getAttribute('data-topic') === key); });
        $$('#ugToc .ug-sub').forEach(function (s) { s.classList.toggle('hidden', s.getAttribute('data-sub') !== key); });
        var cur = $('#ugToc a.ug-t.is-active'); if (cur) { var box = cur.closest('.lg\\:overflow-y-auto') || cur.closest('div[class*="overflow-y-auto"]'); if (box && matchMedia('(min-width: 1024px)').matches) { var r = cur.getBoundingClientRect(), b = box.getBoundingClientRect(); if (r.top < b.top || r.bottom > b.bottom) box.scrollTop += (r.top - b.top) - box.clientHeight / 3; } }
    }
    if ('IntersectionObserver' in window && topics.length) {
        var seen = {};
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { seen[en.target.getAttribute('data-topic')] = en.isIntersecting ? en.boundingClientRect.top : null; });
            if (Date.now() < lockUntil) return;
            var best = null, bt = Infinity; Object.keys(seen).forEach(function (k) { if (seen[k] !== null && Math.abs(seen[k]) < bt) { bt = Math.abs(seen[k]); best = k; } });
            // Di dasar halaman topik terakhir tak bisa mencapai tepi atas: anggap itu yang dibaca.
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) best = topics[topics.length - 1].getAttribute('data-topic');
            if (best) setActive(best);
        }, { rootMargin: '-90px 0px -55% 0px', threshold: [0, 1] });
        topics.forEach(function (t) { io.observe(t); });
        setActive(topics[0].getAttribute('data-topic'));
    }

    // ── Pencarian teks penuh (hanya topik yang boleh dilihat pengguna) ──
    var input = $('#ugSearch'), clearBtn = $('#ugSearchClear'), results = $('#ugResults'), toc = $('#ugToc'), timer = null;
    function norm(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    INDEX.forEach(function (r) { r._h = norm(r.h); r._t = norm(r.t); r._x = norm(r.x); });
    function esc(s) { return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function snippet(text, terms) {
        var n = norm(text), pos = -1; for (var i = 0; i < terms.length; i++) { var p = n.indexOf(terms[i]); if (p >= 0 && (pos < 0 || p < pos)) pos = p; }
        if (pos < 0) return esc(text.slice(0, 110));
        var s = Math.max(0, pos - 40), e = Math.min(text.length, pos + 90), out = esc(text.slice(s, e));
        return (s > 0 ? '…' : '') + out + (e < text.length ? '…' : '');
    }
    function search(q) {
        var terms = norm(q).split(/\s+/).filter(function (t) { return t.length > 0; });
        if (!terms.length) return null;
        var hits = [];
        INDEX.forEach(function (r) {
            var score = 0, ok = true;
            for (var i = 0; i < terms.length; i++) {
                var t = terms[i], inT = r._t.indexOf(t) >= 0, inH = r._h.indexOf(t) >= 0, inX = r._x.indexOf(t) >= 0;
                if (!inT && !inH && !inX) { ok = false; break; }
                score += (inT ? 6 : 0) + (inH ? 4 : 0) + (inX ? 1 : 0);
            }
            if (ok) hits.push({ r: r, s: score });
        });
        hits.sort(function (a, b) { return b.s - a.s; });
        return { terms: terms, hits: hits.slice(0, 40), total: hits.length };
    }
    function clearMarks() { $$('mark.ug-hit').forEach(function (m) { var p = m.parentNode; p.replaceChild(document.createTextNode(m.textContent), m); p.normalize(); }); }
    function markIn(root, terms) {
        clearMarks(); if (!root) return;
        var re = new RegExp('(' + terms.filter(function (t) { return t.length > 1; }).map(function (t) { return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')', 'gi');
        if (!terms.length) return;
        var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null), nodes = [], n; while ((n = w.nextNode())) { if (n.nodeValue.trim() && !n.parentNode.closest('mark, script, style')) nodes.push(n); }
        nodes.slice(0, 600).forEach(function (node) {
            if (!re.test(node.nodeValue)) { re.lastIndex = 0; return; } re.lastIndex = 0;
            var frag = document.createDocumentFragment(), last = 0, m; node.nodeValue.replace(re, function (match, g, off) { frag.appendChild(document.createTextNode(node.nodeValue.slice(last, off))); var k = document.createElement('mark'); k.className = 'ug-hit'; k.textContent = match; frag.appendChild(k); last = off + match.length; return match; });
            frag.appendChild(document.createTextNode(node.nodeValue.slice(last))); node.parentNode.replaceChild(frag, node);
        });
    }
    function render() {
        var q = input.value.trim(); clearBtn.classList.toggle('hidden', !q);
        var res = search(q);
        if (!res) { results.classList.add('hidden'); results.innerHTML = ''; toc.classList.remove('hidden'); clearMarks(); return; }
        toc.classList.add('hidden'); results.classList.remove('hidden');
        if (!res.hits.length) { results.innerHTML = '<p class="px-2 py-3 text-xs text-gray-500">' + esc(NO_RES) + '</p>'; return; }
        results.innerHTML = '<p class="px-2 pb-1 text-[11px] text-gray-500">' + res.total + ' ' + esc(RES_WORD) + '</p>' + res.hits.map(function (h, i) {
            var r = h.r, title = esc(r.t) + (r.h ? ' › ' + esc(r.h) : '');
            return '<a href="#' + esc(r.a) + '" class="ug-res" role="option" data-i="' + i + '" data-topic="' + esc(r.k) + '"><span class="font-semibold text-gray-900">' + title + '</span><small>' + esc(r.c) + ' · ' + snippet(r.x, res.terms) + '</small></a>';
        }).join('');
        results._terms = res.terms;
    }
    if (input) {
        input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(render, 120); });
        clearBtn.addEventListener('click', function () { input.value = ''; render(); input.focus(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { input.value = ''; render(); }
            if (e.key === 'ArrowDown') { var f = $('.ug-res', results); if (f) { e.preventDefault(); f.focus(); } }
        });
        results.addEventListener('click', function (e) {
            var a = e.target.closest('a.ug-res'); if (!a) return;
            e.preventDefault(); var topic = document.getElementById('topic-' + a.getAttribute('data-topic'));
            markIn(topic, results._terms || []); go(a.getAttribute('href').slice(1));
        });
        results.addEventListener('keydown', function (e) {
            var items = $$('.ug-res', results), i = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown' && i < items.length - 1) { e.preventDefault(); items[i + 1].focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); if (i > 0) items[i - 1].focus(); else input.focus(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === '/' && !/^(input|textarea|select)$/i.test((e.target.tagName || '')) && !e.target.isContentEditable) { e.preventDefault(); input.focus(); input.select(); } });
    }
})();
</script>
@endpush
