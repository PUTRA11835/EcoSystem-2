@extends('dashboard')

@php
    $isEdit = $recons !== null;
@endphp

@section('title', $isEdit ? 'Edit Recons' : 'New Recons')
@section('page-title', $isEdit ? 'Edit Recons' : 'New Recons')
@section('page-subtitle', $support->name ?? ('Support #' . $support->id))

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Back --}}
    <div class="mb-4">
        <a href="{{ route('delivery.support.show', $support->id) }}#recons"
           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Support Details
        </a>
    </div>

    {{-- ── Header batch ───────────────────────────────────────────────── --}}
    <div class="bg-white shadow-md rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $isEdit ? 'Edit Recons Draft' : 'New Recons' }}</h2>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $support->client->basicData->name_1 ?? 'N/A' }} • {{ $support->name ?? ('Support #' . $support->id) }}
                </p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold
                         {{ $isEdit && $recons->isSubmitted() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                Status: {{ $isEdit ? $recons->status_label : 'Draft' }}
            </span>
        </div>

        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-3 gap-5">
            {{-- Nomor selalu dibuat sistem — ditampilkan, tidak diisi manual. --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recons Number</label>
                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-md">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-800 font-mono">{{ $previewNumber }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    @if($isEdit)
                        Issued automatically and kept for the life of this recons.
                    @else
                        Generated automatically on save — format MDRC-[customer code]-[yymm]-[counter].
                    @endif
                </p>
            </div>

            <div>
                <label for="reconsDate" class="block text-sm font-medium text-gray-700 mb-1">
                    Recons Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="reconsDate" required aria-required="true"
                       oninput="ReconsForm.clearFieldError('reconsDate')"
                       onchange="ReconsForm.clearFieldError('reconsDate')"
                       value="{{ $isEdit ? ($recons->recons_date?->format('Y-m-d') ?? now()->format('Y-m-d')) : now()->format('Y-m-d') }}"
                       class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm primary-focus">
                <p id="reconsDateError" class="hidden mt-1 text-xs font-medium text-red-600"></p>
            </div>

            <div>
                <label for="reconsDescription" class="block text-sm font-medium text-gray-700 mb-1">
                    Description <span class="text-red-500">*</span>
                </label>
                <input type="text" id="reconsDescription" maxlength="2000" required aria-required="true"
                       oninput="ReconsForm.clearFieldError('reconsDescription')"
                       value="{{ $isEdit ? $recons->description : '' }}"
                       placeholder="e.g. Recons AMS September 2026"
                       class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm primary-focus">
                <p id="reconsDescriptionError" class="hidden mt-1 text-xs font-medium text-red-600"></p>
            </div>
        </div>
    </div>

    {{-- ── Pemilihan tiket ────────────────────────────────────────────── --}}
    <div class="bg-white shadow-md rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">Eligible Tickets</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Closed tickets that have Customer MD and have never been included in another recons.
                    </p>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Selected</p>
                        <p class="font-bold text-gray-900" id="selectedCount">0</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Total MD</p>
                        <p class="font-bold text-gray-900" id="selectedMd">0.00</p>
                    </div>
                </div>
            </div>

            {{-- Baris 1: pencarian + aksi (Cancel / Save Draft / Submit sejajar search) --}}
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative flex-1 min-w-[220px]">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                    </div>
                    {{-- Pencarian juga mencocokkan tanggal yang diketik bebas
                         (mis. "jun 2026", "26 jun", "2026-06") supaya user tidak
                         harus selalu memakai date picker. --}}
                    <input type="text" id="ticketSearch" placeholder="Search ticket number, description, or date (e.g. Jun 2026)…"
                           autocomplete="off" oninput="ReconsForm.render()"
                           class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus">
                </div>

                <button type="button" onclick="ReconsForm.selectAllVisible(true)"
                        class="px-3 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition whitespace-nowrap">
                    Select all shown
                </button>
                <button type="button" onclick="ReconsForm.selectAllVisible(false)"
                        class="px-3 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition whitespace-nowrap">
                    Clear selection
                </button>

                {{-- Pemisah visual antara aksi tabel dan aksi dokumen --}}
                <span class="hidden sm:block w-px h-7 bg-gray-200 mx-1"></span>

                <a href="{{ route('delivery.support.show', $support->id) }}#recons"
                   class="px-4 py-2 border border-gray-300 text-xs font-semibold text-gray-700 rounded-lg hover:bg-gray-50 transition whitespace-nowrap">
                    Cancel
                </a>
                <button type="button" id="btnSaveDraft" onclick="ReconsForm.save('draft')"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-800 text-xs font-semibold rounded-lg hover:bg-gray-50 transition disabled:opacity-60 whitespace-nowrap">
                    Save Draft
                </button>
                <button type="button" id="btnSubmit" onclick="ReconsForm.save('submit')"
                        class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-60 whitespace-nowrap">
                    Submit
                </button>
            </div>

            {{-- Baris 2: filter tanggal (bulan/tahun multi-pilih + rentang) --}}
            <div class="flex items-center gap-2 flex-wrap mt-3 pt-3 border-t border-gray-100">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide mr-1">Filter by date</span>

                {{-- Dasar tanggal: Close Date / Start Date --}}
                {{-- data-onchange WAJIB nama fungsi global polos — custom-dropdown.js
                     mencarinya lewat window[nama], yang tidak mengurai nama bertitik. --}}
                <div class="custom-dd relative" id="ddDateBasis" data-fixed="true" data-onchange="reconsChangeDateBasis" style="min-width:150px">
                    {{-- Default = Start Date karena kolom itu selalu terisi;
                         Close Date baru terisi untuk tiket yang ditutup setelah
                         pencatatan close date aktif, jadi kalau dijadikan default
                         hasil filter sering kosong dan membingungkan. --}}
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:border-gray-400 transition text-left">
                        <span class="custom-dd-label text-gray-700">Start Date</span>
                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="dateBasis" value="start_date">
                    <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] pt-1.5 overflow-y-auto" style="max-height:220px;min-width:150px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="start_date">Start Date</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="close_date">Close Date</button>
                    </div>
                </div>

                {{-- Bulan/tahun — bisa pilih lebih dari satu (opsi diisi dari data) --}}
                <div class="custom-dd relative" id="ddPeriod" data-multi="true" data-fixed="true"
                     data-placeholder="All months" data-searchable="true" data-search-placeholder="Search month…"
                     data-onchange="reconsApplyDateFilter" style="min-width:190px">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:border-gray-400 transition text-left">
                        <span class="custom-dd-label text-gray-500 truncate">All months</span>
                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="filterPeriods" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] pt-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All months</button>
                        {{-- item bulan disisipkan di sini oleh ReconsForm.buildPeriodOptions() --}}
                        <div id="periodClearFooter" class="sticky bottom-0 bg-white border-t border-gray-100 px-3 py-2 flex justify-end">
                            <button type="button" onclick="clearCustomDropdownMulti('filterPeriods'); ReconsForm.applyDateFilter();"
                                    class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                        </div>
                    </div>
                </div>

                {{-- Rentang tanggal --}}
                <div class="flex items-center gap-1.5">
                    <input type="date" id="filterFrom" onchange="ReconsForm.applyDateFilter()" title="From date"
                           class="px-2.5 py-2 border border-gray-300 rounded-lg text-xs primary-focus">
                    <span class="text-xs text-gray-400">to</span>
                    <input type="date" id="filterTo" onchange="ReconsForm.applyDateFilter()" title="To date"
                           class="px-2.5 py-2 border border-gray-300 rounded-lg text-xs primary-focus">
                </div>

                {{-- Filter sebenarnya sudah diterapkan otomatis saat pilihan berubah;
                     tombol ini memberi kendali eksplisit dan berguna untuk input
                     rentang tanggal yang tidak selalu memicu event 'change'. --}}
                <button type="button" onclick="ReconsForm.applyDateFilter()"
                        class="px-3 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 transition whitespace-nowrap">
                    Apply filter
                </button>
                <button type="button" onclick="ReconsForm.resetDateFilter()"
                        class="px-3 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition whitespace-nowrap">
                    Reset filter
                </button>

                <span id="dateFilterHint" class="text-xs text-gray-400"></span>
            </div>
        </div>

        <div class="px-6 py-5">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="px-4 py-3 text-center font-semibold w-12">
                                <input type="checkbox" id="checkAll" onclick="ReconsForm.toggleAll(this.checked)"
                                       class="w-4 h-4 rounded border-gray-300">
                            </th>
                            <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Ticket Number</th>
                            <th class="px-4 py-3 text-left font-semibold" style="min-width:260px;">Description</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Start Date</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Close Date</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Status</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Type</th>
                            <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Customer MD</th>
                        </tr>
                    </thead>
                    <tbody id="ticketBody" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="8" class="text-center py-10">
                                <svg class="animate-spin h-7 w-7 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-gray-500 text-xs">Loading eligible tickets…</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@php $customDdVer = file_exists(public_path('js/custom-dropdown.js')) ? filemtime(public_path('js/custom-dropdown.js')) : 1; @endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

<script>
window.ReconsForm = (function () {
    'use strict';

    // URL relatif — konsisten dengan section lain & aman di belakang proxy HTTPS.
    const BASE_URL   = `/delivery/support/{{ $support->id }}/recons`;
    const RECONS_ID  = @json($isEdit ? $recons->id : null);
    const SAVE_URL   = RECONS_ID ? `${BASE_URL}/${RECONS_ID}/save` : `${BASE_URL}/save`;

    const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    let tickets  = [];
    // Tiket yang sudah ada di draft ini ikut tercentang sejak awal.
    let selected = new Set(@json(array_map('intval', $selectedTicketIds)));

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // Deskripsi tiket berasal dari email customer — escape sebelum innerHTML.
    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Pola toast identik dengan section lain (Plan Cost / Financial).
    function notify(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
        else if (typeof window.showNotification === 'function') window.showNotification(msg, type);
        else alert(msg);
    }

    function fmtMd(v) {
        return (v === null || v === undefined || v === '') ? '-' : Number(v).toFixed(2);
    }

    // ── filter tanggal ───────────────────────────────────────────────────
    function basis() {
        return document.getElementById('dateBasis').value || 'close_date';
    }

    /** Tanggal (YYYY-MM-DD) sebuah tiket menurut dasar filter yang dipilih. */
    function dateOf(ticket) {
        return ticket[basis()] || null;
    }

    function selectedPeriods() {
        const raw = document.getElementById('filterPeriods').value;
        return raw ? raw.split(',').filter(Boolean) : [];
    }

    /** Opsi bulan dibangun dari data tiket yang benar-benar ada. */
    function buildPeriodOptions() {
        const dd = document.getElementById('ddPeriod');
        // Panel bisa sedang dilepas ke <body> (mode fixed) — pakai ref tersimpan.
        const panel  = dd?._ddPanel || dd?.querySelector('.custom-dd-panel');
        const footer = document.getElementById('periodClearFooter');
        if (!panel || !footer) return;

        // Bersihkan item lama (sisakan opsi "All months" dan footer Clear).
        panel.querySelectorAll('.custom-dd-item[data-value]:not([data-value=""])').forEach(el => el.remove());

        const months = [...new Set(tickets.map(t => (dateOf(t) || '').slice(0, 7)).filter(Boolean))]
            .sort().reverse();

        months.forEach(ym => {
            const [year, month] = ym.split('-');
            const item = document.createElement('button');
            item.type      = 'button';
            item.className = 'custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 text-left';
            item.dataset.value = ym;
            item.innerHTML = `<span class="custom-dd-item-text">${MONTH_NAMES[Number(month) - 1]} ${year}</span>`
                + '<svg class="custom-dd-check w-4 h-4 text-red-800 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';
            panel.insertBefore(item, footer);
        });

        // JANGAN panggil initCustomDropdowns() lagi di sini. custom-dropdown.js
        // menangani klik item lewat event delegation di level panel dan search-nya
        // membaca .custom-dd-item secara live, jadi item yang disisipkan belakangan
        // sudah otomatis berfungsi. Meng-init ulang justru memasang listener KEDUA
        // pada tombol: klik pertama membuka panel, handler kedua langsung
        // menutupnya lagi — inilah penyebab dropdown "tidak bisa ditekan".
        //
        // Yang perlu disegarkan hanyalah tanda centang + label, karena item lama
        // dibuang dan diganti sementara nilai pilihannya tersimpan di hidden input.
        if (dd && typeof _syncMultiVisualState === 'function') _syncMultiVisualState(dd);
    }

    function matchesDateFilter(ticket) {
        const periods = selectedPeriods();
        const from    = document.getElementById('filterFrom').value;
        const to      = document.getElementById('filterTo').value;

        if (!periods.length && !from && !to) return true;

        const date = dateOf(ticket);
        // Tiket tanpa tanggal pada dasar yang dipilih tidak bisa dicocokkan.
        if (!date) return false;

        if (periods.length && !periods.includes(date.slice(0, 7))) return false;
        if (from && date < from) return false;
        if (to   && date > to)   return false;

        return true;
    }

    function onBasisChange() {
        // Opsi bulan bergantung pada dasar tanggal → bangun ulang & reset pilihan.
        clearCustomDropdownMulti('filterPeriods');
        buildPeriodOptions();
        syncSearchPlaceholder();
        render();
    }

    /**
     * Terapkan filter tanggal. Dipanggil otomatis saat pilihan berubah, DAN
     * lewat tombol "Apply filter" — tombol itu berguna untuk input rentang
     * tanggal, yang tidak selalu memicu event 'change' selagi user mengetik.
     */
    function applyDateFilter() {
        const from = document.getElementById('filterFrom').value;
        const to   = document.getElementById('filterTo').value;

        // Rentang terbalik hampir pasti salah ketik — beri tahu, jangan diam
        // saja menampilkan tabel kosong.
        if (from && to && to < from) {
            notify('The "to" date is earlier than the "from" date. Please check the range.', 'error');
            return;
        }

        render();
    }

    function resetDateFilter() {
        clearCustomDropdownMulti('filterPeriods');
        document.getElementById('filterFrom').value = '';
        document.getElementById('filterTo').value   = '';
        render();
    }

    /**
     * Pencocokan kata kunci: nomor tiket, deskripsi, DAN tanggal.
     *
     * Tanggal yang dicocokkan HANYA yang sesuai dasar filter aktif (Start Date
     * atau Close Date) — bukan dua-duanya. Alasannya: kalau keduanya ikut
     * dicocokkan, mengetik "Jul" ikut menjaring tiket yang Start Date-nya Juni
     * hanya karena Close Date-nya Juli, sehingga daftarnya terlihat "tidak
     * tersaring". Dengan mengikuti dasar yang dipilih, perilakunya konsisten
     * dengan kontrol "Filter by date" tepat di bawah kotak pencarian ini.
     *
     * Bentuk yang dicocokkan: mentah (`2026-07-26`), label tampilan
     * (`26 Jul 2026`), dan format lokal (`26/07/2026`) — sehingga user bisa
     * mengetik "jul", "jul 2026", "26 jul", "2026-07", atau "26/07/2026".
     */
    function matchesTerm(ticket, term) {
        const date = dateOf(ticket);                       // ikut dasar filter aktif
        const label = basis() === 'close_date' ? ticket.close_date_label : ticket.start_date_label;

        const haystack = [
            ticket.ticket_number,
            ticket.description,
            date,
            label,
            (date || '').split('-').reverse().join('/'),   // dd/mm/yyyy
        ].filter(Boolean).join(' ').toLowerCase();

        return haystack.includes(term);
    }

    /** Placeholder pencarian ikut menyebut dasar tanggal yang sedang dipakai. */
    function syncSearchPlaceholder() {
        const input = document.getElementById('ticketSearch');
        if (!input) return;
        const label = basis() === 'close_date' ? 'close date' : 'start date';
        input.placeholder = `Search ticket number, description, or ${label} (e.g. Jul 2026)…`;
    }

    function visibleTickets() {
        const term = (document.getElementById('ticketSearch')?.value || '').toLowerCase().trim();

        return tickets.filter(t => {
            if (!matchesDateFilter(t)) return false;
            if (!term) return true;
            return matchesTerm(t, term);
        });
    }

    // ── data ─────────────────────────────────────────────────────────────
    async function load() {
        try {
            const url = RECONS_ID
                ? `${BASE_URL}/eligible-tickets?recons_id=${RECONS_ID}`
                : `${BASE_URL}/eligible-tickets`;

            const res  = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to load tickets.');

            tickets = data.tickets || [];

            // Jaring pengaman: buang pilihan yang tiketnya tidak ada di daftar.
            // Server sudah menyertakan seluruh baris batch ini (termasuk yang
            // sudah tidak eligible), jadi normalnya tak ada yang terbuang —
            // tapi tanpa ini sebuah id "hantu" bisa ikut terkirim dan membuat
            // penyimpanan gagal terus tanpa cara memperbaikinya dari layar.
            const available = new Set(tickets.map(t => t.ticket_id));
            selected = new Set([...selected].filter(id => available.has(id)));

            buildPeriodOptions();
            render();
        } catch (e) {
            document.getElementById('ticketBody').innerHTML =
                `<tr><td colspan="8" class="text-center py-10 text-gray-500 text-xs">${esc(e.message || 'Failed to load tickets.')}</td></tr>`;
        }
    }

    /**
     * Alasan tabel kosong, ditulis sespesifik mungkin. Kasus paling sering:
     * memfilter memakai Close Date padahal tiket-tiketnya belum punya tanggal
     * itu — tanpa penjelasan, filter terlihat seperti rusak.
     */
    function emptyReason() {
        const basisLabel = basis() === 'close_date' ? 'close date' : 'start date';
        const dateActive = selectedPeriods().length
            || document.getElementById('filterFrom').value
            || document.getElementById('filterTo').value;
        const noDate = tickets.filter(t => !dateOf(t)).length;

        if (dateActive && noDate === tickets.length) {
            return `None of the ${tickets.length} eligible tickets has a ${basisLabel}, `
                 + `so nothing can match this date filter. Switch “Filter by date” to `
                 + `${basis() === 'close_date' ? 'Start Date' : 'Close Date'}, or reset the filter.`;
        }

        return 'No ticket matches your search or date filter.';
    }

    function render() {
        const body = document.getElementById('ticketBody');
        const rows = visibleTickets();

        if (rows.length === 0) {
            body.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-gray-500 text-xs">${
                tickets.length === 0
                    ? 'No eligible ticket. A ticket must be closed, have Customer MD, and not belong to another recons.'
                    : esc(emptyReason())
            }</td></tr>`;
        } else {
            body.innerHTML = rows.map(t => `
                <tr class="hover:bg-gray-50 ${selected.has(t.ticket_id) ? 'bg-amber-50' : ''}">
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" class="w-4 h-4 rounded border-gray-300 ticket-check"
                               value="${t.ticket_id}" ${selected.has(t.ticket_id) ? 'checked' : ''}
                               onchange="ReconsForm.toggleOne(${t.ticket_id}, this.checked)">
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">${esc(t.ticket_number || ('#' + t.ticket_id))}</td>
                    <td class="px-4 py-3 text-gray-700">
                        ${esc(t.description || '-')}
                        ${t.in_recons && t.eligible_now === false
                            ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-100 text-amber-800" title="Already recorded in this recons, but it no longer meets the criteria (reopened, or its Customer MD was cleared). Uncheck it to take it out.">Already in this recons — no longer eligible</span>'
                            : ''}
                    </td>
                    <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(t.start_date_label)}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(t.close_date_label)}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(t.status_label)}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(t.type || '-')}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">${fmtMd(t.man_days)}</td>
                </tr>
            `).join('');
        }

        updateSummary(rows);
    }

    function updateSummary(rows) {
        const chosen  = tickets.filter(t => selected.has(t.ticket_id));
        const totalMd = chosen.reduce((sum, t) => sum + Number(t.man_days || 0), 0);

        document.getElementById('selectedCount').textContent = chosen.length;
        document.getElementById('selectedMd').textContent    = totalMd.toFixed(2);

        const visible  = rows || visibleTickets();
        const checkAll = document.getElementById('checkAll');
        checkAll.checked = visible.length > 0 && visible.every(t => selected.has(t.ticket_id));

        // Beri tahu kalau ada tiket yang tersaring habis karena tanggalnya kosong.
        const hint    = document.getElementById('dateFilterHint');
        const active  = selectedPeriods().length || document.getElementById('filterFrom').value || document.getElementById('filterTo').value;
        const noDate  = tickets.filter(t => !dateOf(t)).length;
        hint.textContent = active
            ? `${visible.length} of ${tickets.length} shown`
              + (noDate ? ` • ${noDate} ticket(s) have no ${basis() === 'close_date' ? 'close' : 'start'} date` : '')
            : '';
    }

    function toggleOne(id, checked) {
        checked ? selected.add(id) : selected.delete(id);
        render();
    }

    function toggleAll(checked) {
        selectAllVisible(checked);
    }

    /** Berlaku untuk baris yang SEDANG tampil (mengikuti pencarian & filter tanggal). */
    function selectAllVisible(checked) {
        visibleTickets().forEach(t => checked ? selected.add(t.ticket_id) : selected.delete(t.ticket_id));
        render();
    }

    // ── validasi field wajib ─────────────────────────────────────────────
    const INVALID_CLS = ['border-red-500', 'ring-1', 'ring-red-500'];

    function setFieldError(id, message) {
        const input = document.getElementById(id);
        const note  = document.getElementById(id + 'Error');
        if (input) input.classList.add(...INVALID_CLS);
        if (note) {
            note.textContent = message;
            note.classList.remove('hidden');
        }
    }

    /** Dipanggil dari oninput/onchange field — tanda merah hilang begitu diisi. */
    function clearFieldError(id) {
        const input = document.getElementById(id);
        const note  = document.getElementById(id + 'Error');
        if (input) input.classList.remove(...INVALID_CLS);
        if (note) note.classList.add('hidden');
    }

    /**
     * Recons Date & Description wajib diisi. Divalidasi di sini agar user dapat
     * umpan balik seketika; server tetap memvalidasi ulang sebagai penjaga
     * terakhir (request bisa datang dari luar form ini).
     *
     * @return {boolean} true bila semua terisi
     */
    function validateRequiredFields() {
        const tanggal   = document.getElementById('reconsDate');
        const deskripsi = document.getElementById('reconsDescription');
        const kosong    = [];

        clearFieldError('reconsDate');
        clearFieldError('reconsDescription');

        if (!tanggal.value) {
            setFieldError('reconsDate', 'Recons date is required.');
            kosong.push({ el: tanggal, label: 'Recons Date' });
        }
        if (!deskripsi.value.trim()) {
            setFieldError('reconsDescription', 'Description is required.');
            kosong.push({ el: deskripsi, label: 'Description' });
        }

        if (kosong.length === 0) return true;

        notify(kosong.map(f => f.label).join(' and ') + ' must be filled in.', 'error');
        kosong[0].el.focus();       // arahkan kursor ke field pertama yang kosong
        return false;
    }

    async function save(action) {
        // Header divalidasi lebih dulu: percuma memeriksa tiket kalau data
        // wajibnya belum lengkap.
        if (!validateRequiredFields()) return;

        if (selected.size === 0) {
            notify('Select at least one ticket for this recons.', 'error');
            return;
        }

        // Modal konfirmasi global (partials/confirm-modal), bukan confirm()
        // bawaan browser — konsisten dengan aksi penting lain di aplikasi.
        if (action === 'submit') {
            const chosen  = tickets.filter(t => selected.has(t.ticket_id));
            const totalMd = chosen.reduce((sum, t) => sum + Number(t.man_days || 0), 0);

            const ok = await showConfirm(
                `Submit this recons with ${chosen.length} ticket(s) and ${totalMd.toFixed(2)} MD?\n\n`
                + 'After submitting, its header and ticket list are locked. You can still cancel it later to return it to draft.',
                'Submit Recons',
                'primary',
                { okText: 'Submit' },
            );

            if (!ok) return;
        }

        const draftBtn  = document.getElementById('btnSaveDraft');
        const submitBtn = document.getElementById('btnSubmit');
        draftBtn.disabled = submitBtn.disabled = true;

        try {
            const res = await fetch(SAVE_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    description: document.getElementById('reconsDescription').value.trim(),
                    recons_date: document.getElementById('reconsDate').value,
                    ticket_ids:  Array.from(selected),
                    action:      action,
                }),
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                // Laravel validation error (422) mengirim `errors`, bukan `message` saja.
                const detail = data.errors ? Object.values(data.errors).flat().join(' ') : null;
                throw new Error(detail || data.message || 'Failed to save recons.');
            }

            notify(data.message, 'success');
            window.location.href = data.redirect_url;
        } catch (e) {
            notify(e.message || 'Failed to save recons.', 'error');
            draftBtn.disabled = submitBtn.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCustomDropdowns();
        syncSearchPlaceholder();
        load();
    });

    return {
        render, toggleOne, toggleAll, selectAllVisible, save,
        onBasisChange, resetDateFilter, applyDateFilter, clearFieldError,
    };
})();

// custom-dropdown.js memanggil callback-nya lewat `window[namaFungsi]` — sebuah
// lookup DATAR yang tidak bisa menguraikan nama bertitik. `data-onchange` karena
// itu WAJIB berisi nama fungsi global polos (pola yang dipakai seluruh halaman
// lain: applyFilters, applyColFilter, dst). Sebelumnya di sini tertulis
// "ReconsForm.render" sehingga yang dicari adalah window["ReconsForm.render"]
// → undefined, dan filter bulan/dasar tanggal tidak pernah menerapkan apa pun.
window.reconsApplyDateFilter  = function () { window.ReconsForm.applyDateFilter(); };
window.reconsChangeDateBasis  = function () { window.ReconsForm.onBasisChange(); };
</script>
@endsection
