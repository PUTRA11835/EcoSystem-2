@extends('dashboard')

@section('title', 'KPI Evaluation — Lead & Project')
@section('page-title', 'KPI Evaluation')

@section('content')
@php
    $user = session('user');
    $hasFilters = !empty($search) || !empty($positionFilter) || (($projectFilter ?? '') !== '') || (($leadFilter ?? '') !== '');
@endphp

<div class="space-y-5">

    {{-- ── Page Tab Strip ───────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-1.5 shadow-sm border border-gray-100 flex items-center gap-1.5 w-full sm:w-auto">
        <a href="{{ route('general.kpi-evaluation.index') }}"
           class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-chart-bar mr-1.5"></i> Dashboard
        </a>
        @if(($can ?? fn($p) => true)('general.kpi-evaluation.templates'))
        <a href="{{ route('general.kpi-evaluation.templates.index') }}"
           class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-layer-group mr-1.5"></i> Assessment Templates
        </a>
        @endif
        <span class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold primary-gradient text-white shadow">
            <i class="fas fa-sitemap mr-1.5"></i> Lead &amp; Project
        </span>
    </div>

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
            <span class="w-9 h-9 rounded-xl primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                <i class="fas fa-sitemap"></i>
            </span>
            Lead &amp; Project
        </h1>
        <p class="text-xs text-gray-500 mt-1 max-w-2xl">
            Each employee's leader is filled in automatically: if they're on a <strong>project</strong>, the leader is that
            project's manager; otherwise it's the <strong>“Report to”</strong> on their employee record. To change a leader,
            edit the employee in <strong>Master Data → Employee</strong> — it can't be set here.
        </p>
    </div>

    {{-- ── Summary tiles ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium">Active employees</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div class="text-3xl font-bold text-emerald-600">{{ $stats['withLead'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium">Have a leader</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div class="text-3xl font-bold {{ $stats['noLead'] > 0 ? 'text-red-500' : 'text-gray-900' }}">{{ $stats['noLead'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium">No leader set</div>
        </div>
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-wrap items-center gap-2">
            <i class="fas fa-users text-gray-400"></i>
            <h3 class="text-sm font-bold text-gray-800">Reporting lines</h3>
            <span class="text-xs text-gray-400">Read-only — use the <i class="fas fa-filter text-[10px]"></i> icons to filter.</span>
            <button type="button" id="teamResetBtn" onclick="resetTeams()"
                class="{{ $hasFilters ? '' : 'hidden' }} ml-1 inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all">
                <i class="fas fa-rotate-left text-[10px]"></i> Reset filters
            </button>
        </div>

        <form method="GET" action="{{ route('general.kpi-evaluation.teams') }}" id="teamFilterForm" class="hidden">
            <input type="hidden" name="search"   id="fSearch"   value="{{ $search ?? '' }}">
            <input type="hidden" name="position" id="fPosition" value="{{ $positionFilter ?? '' }}">
            <input type="hidden" name="project"  id="fProject"  value="{{ $projectFilter ?? '' }}">
            <input type="hidden" name="lead"     id="fLead"     value="{{ $leadFilter ?? '' }}">
            <input type="hidden" name="per_page" id="fPerPage"  value="{{ $perPage ?? 15 }}">
        </form>

        {{-- Table view — per-column filter icons open a popover that floats
             (position:fixed, JS-positioned) so it is never clipped by this
             table's horizontal scroll and always renders above the table. --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/90 border-b border-gray-100 select-none">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-10">No</th>

                        {{-- Employee --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider min-w-[210px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Employee</span>
                                <button type="button" data-hf-btn onclick="hf(event,'hfEmp')"
                                    class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($search) ? 'text-(--primary-color)' : 'text-gray-400 hover:text-gray-600' }}">
                                    <i class="fas fa-filter text-[10px]"></i>
                                    @if(!empty($search))<span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>@endif
                                </button>
                            </div>
                            <div id="hfEmp" class="header-filter-popover hidden w-64 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case" onclick="event.stopPropagation()">
                                <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Employee</span>
                                    @if(!empty($search))
                                    <button type="button" onclick="document.getElementById('hfEmpSearch').value='';setHF('fSearch','');" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                    @endif
                                </div>
                                <div class="p-2.5">
                                    <div class="relative">
                                        <input type="text" id="hfEmpSearch" value="{{ $search ?? '' }}" placeholder="Type a name or ECI…" autocomplete="off"
                                            oninput="fSearchType(this.value)"
                                            onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
                                            class="w-full bg-gray-50 border border-gray-200 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-(--primary-color)/25 focus:border-(--primary-color) transition-all font-normal">
                                        <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1.5">Results update as you type.</p>
                                </div>
                            </div>
                        </th>

                        {{-- Position --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider min-w-[130px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Position</span>
                                <button type="button" data-hf-btn onclick="hf(event,'hfPos')"
                                    class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($positionFilter) ? 'text-(--primary-color)' : 'text-gray-400 hover:text-gray-600' }}">
                                    <i class="fas fa-filter text-[10px]"></i>
                                    @if(!empty($positionFilter))<span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>@endif
                                </button>
                            </div>
                            <div id="hfPos" class="header-filter-popover hidden w-56 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Position</span>
                                    @if(!empty($positionFilter))
                                    <button type="button" onclick="setHF('fPosition','')" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                    @endif
                                </div>
                                <div class="py-1 max-h-64 overflow-y-auto">
                                    <button type="button" onclick="setHF('fPosition','')"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ empty($positionFilter) ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">All positions</span>
                                        @if(empty($positionFilter))<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    @foreach($positions as $pos)
                                    <button type="button" onclick="setHF('fPosition',{{ Js::from($pos) }})"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ ($positionFilter ?? '') === $pos ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">{{ $pos }}</span>
                                        @if(($positionFilter ?? '') === $pos)<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                        </th>

                        {{-- Project --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider min-w-[170px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Project</span>
                                <button type="button" data-hf-btn onclick="hf(event,'hfProj')"
                                    class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all {{ ($projectFilter ?? '') !== '' ? 'text-(--primary-color)' : 'text-gray-400 hover:text-gray-600' }}">
                                    <i class="fas fa-filter text-[10px]"></i>
                                    @if(($projectFilter ?? '') !== '')<span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>@endif
                                </button>
                            </div>
                            <div id="hfProj" class="header-filter-popover hidden w-64 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                <div class="px-3 pt-2.5 pb-2 border-b border-gray-100">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Project</span>
                                        @if(($projectFilter ?? '') !== '')
                                        <button type="button" onclick="document.getElementById('hfProjSearch').value='';projFilterList('');setHF('fProject','');" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                        @endif
                                    </div>
                                    <div class="relative">
                                        <input type="text" id="hfProjSearch" placeholder="Search project…" autocomplete="off"
                                            oninput="projFilterList(this.value)"
                                            class="w-full bg-gray-50 border border-gray-200 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-(--primary-color)/25 focus:border-(--primary-color) transition-all font-normal">
                                        <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    </div>
                                </div>
                                <div class="py-1 max-h-64 overflow-y-auto" id="hfProjList">
                                    <button type="button" data-name="" onclick="setHF('fProject','')" class="proj-opt w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ ($projectFilter ?? '') === '' ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">All projects</span>
                                        @if(($projectFilter ?? '') === '')<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    <button type="button" data-name="" onclick="setHF('fProject','none')" class="proj-opt w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ ($projectFilter ?? '') === 'none' ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">— No project —</span>
                                        @if(($projectFilter ?? '') === 'none')<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    @foreach($projects as $pr)
                                    <button type="button" data-name="{{ \Illuminate\Support\Str::lower($pr->name) }}" onclick="setHF('fProject','{{ $pr->id }}')" class="proj-opt w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ (string)($projectFilter ?? '') === (string)$pr->id ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">{{ $pr->name }}</span>
                                        @if((string)($projectFilter ?? '') === (string)$pr->id)<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    @endforeach
                                    <p id="hfProjEmpty" class="hidden px-3 py-3 text-[11px] text-gray-400 text-center">No matching projects</p>
                                </div>
                            </div>
                        </th>

                        {{-- Leader --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider min-w-[230px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Leader</span>
                                <button type="button" data-hf-btn onclick="hf(event,'hfLead')"
                                    class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all {{ ($leadFilter ?? '') !== '' ? 'text-(--primary-color)' : 'text-gray-400 hover:text-gray-600' }}">
                                    <i class="fas fa-filter text-[10px]"></i>
                                    @if(($leadFilter ?? '') !== '')<span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-(--primary-color) ring-2 ring-white"></span>@endif
                                </button>
                            </div>
                            <div id="hfLead" class="header-filter-popover hidden w-64 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden normal-case font-normal" onclick="event.stopPropagation()">
                                <div class="px-3 pt-2.5 pb-2 border-b border-gray-100">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · Leader</span>
                                        @if(($leadFilter ?? '') !== '')
                                        <button type="button" onclick="document.getElementById('hfLeadSearch').value='';leadFilterList('');setHF('fLead','');" class="text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
                                        @endif
                                    </div>
                                    <div class="relative">
                                        <input type="text" id="hfLeadSearch" placeholder="Search leader…" autocomplete="off"
                                            oninput="leadFilterList(this.value)"
                                            class="w-full bg-gray-50 border border-gray-200 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-(--primary-color)/25 focus:border-(--primary-color) transition-all font-normal">
                                        <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    </div>
                                </div>
                                <div class="py-1 max-h-64 overflow-y-auto" id="hfLeadList">
                                    <button type="button" data-name="" onclick="setHF('fLead','')" class="lead-opt w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ ($leadFilter ?? '') === '' ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">All leaders</span>
                                        @if(($leadFilter ?? '') === '')<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    <button type="button" data-name="" onclick="setHF('fLead','none')" class="lead-opt w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ ($leadFilter ?? '') === 'none' ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">— No leader —</span>
                                        @if(($leadFilter ?? '') === 'none')<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    @foreach($leadOptions as $lo)
                                    <button type="button" data-name="{{ \Illuminate\Support\Str::lower($lo['name'].' '.$lo['meta']) }}" onclick="setHF('fLead','{{ $lo['id'] }}')" class="lead-opt w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors {{ (string)($leadFilter ?? '') === (string)$lo['id'] ? 'text-(--primary-color) font-semibold bg-(--primary-color)/5' : 'text-gray-700' }}">
                                        <span class="truncate">{{ $lo['name'] }}<span class="text-gray-400 font-normal">{{ $lo['meta'] ? ' · '.$lo['meta'] : '' }}</span></span>
                                        @if((string)($leadFilter ?? '') === (string)$lo['id'])<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                                    </button>
                                    @endforeach
                                    <p id="hfLeadEmpty" class="hidden px-3 py-3 text-[11px] text-gray-400 text-center">No matching leaders</p>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody id="teamRows" class="divide-y divide-gray-50">
                    @include('hr-general.kpi.partials._lead-project-rows')
                </tbody>
            </table>
        </div>

        <div id="teamPager">
            @include('hr-general.kpi.partials._lead-project-pager')
        </div>
        <div id="teamLoading" class="hidden px-5 py-3 text-center text-xs text-gray-400"><i class="fas fa-circle-notch fa-spin mr-1"></i> Loading…</div>
    </div>
</div>

<script>
const T_URL = "{{ url('/general/kpi-evaluation/teams') }}";

// ── Per-column header filter popovers — float at position:fixed so the
//    table's horizontal overflow (.overflow-x-auto) can never clip them and
//    they always render above the table (z-index 9999). ────────────────────
let _hfOpen = null; // { btn, pop }
function hf(e, id) {
    e.stopPropagation();
    const btn = e.currentTarget;
    const pop = document.getElementById(id);
    if (!pop) return;
    const wasHidden = pop.classList.contains('hidden');
    closeAllHF();
    if (wasHidden) {
        pop.classList.remove('hidden');
        floatHF(btn, pop);
        _hfOpen = { btn, pop };
        pop.querySelector('input')?.focus();
    }
}
function floatHF(btn, pop) {
    pop.style.position = 'fixed';
    pop.style.margin   = '0';
    pop.style.zIndex   = '9999';
    pop.style.top = '-9999px'; pop.style.left = '-9999px';
    const pw = pop.offsetWidth || 220, ph = pop.offsetHeight || 200;
    const r  = btn.getBoundingClientRect();
    const vw = document.documentElement.clientWidth, vh = window.innerHeight;
    let left = Math.min(Math.max(8, r.right - pw), vw - pw - 8);
    let top  = r.bottom + 4;
    if (top + ph > vh - 8 && r.top - ph - 4 > 8) top = r.top - ph - 4; // flip up
    top = Math.max(8, Math.min(top, vh - ph - 8));
    pop.style.left = left + 'px';
    pop.style.top  = top + 'px';
}
function closeAllHF() {
    document.querySelectorAll('.header-filter-popover').forEach(p => {
        p.classList.add('hidden');
        p.style.position = p.style.top = p.style.left = p.style.zIndex = p.style.margin = '';
    });
    _hfOpen = null;
}
document.addEventListener('click', e => {
    if (!e.target.closest('.header-filter-popover') && !e.target.closest('[data-hf-btn]')) closeAllHF();
});
window.addEventListener('scroll', e => {
    // Ignore scroll events bubbling up (capture phase) from inside the open
    // popover itself — e.g. scrolling its own option list — only close on a
    // scroll of the page/table behind it.
    if (_hfOpen && !(e.target.closest && e.target.closest('.header-filter-popover'))) closeAllHF();
}, true);
window.addEventListener('resize', () => { if (_hfOpen) floatHF(_hfOpen.btn, _hfOpen.pop); });

let _tPage = 1;
function setHF(field, val) {
    document.getElementById(field).value = val;
    _tPage = 1;
    if (field !== 'fSearch') closeAllHF();
    reloadTeams();
}
let _searchT = null;
function fSearchType(val) {
    document.getElementById('fSearch').value = val;
    _tPage = 1;
    clearTimeout(_searchT);
    _searchT = setTimeout(reloadTeams, 250);
}
function resetTeams() {
    ['fSearch', 'fPosition', 'fProject', 'fLead'].forEach(f => document.getElementById(f).value = '');
    const si = document.getElementById('hfEmpSearch'); if (si) si.value = '';
    const pi = document.getElementById('hfProjSearch'); if (pi) { pi.value = ''; projFilterList(''); }
    const li = document.getElementById('hfLeadSearch'); if (li) { li.value = ''; leadFilterList(''); }
    closeAllHF();
    _tPage = 1;
    reloadTeams();
}
// client-side filter of the searchable dropdown lists (Project / Leader),
// with a "no matches" placeholder shown when a query hides every real option.
function optListFilter(listId, optClass, emptyId, v) {
    const q = (v || '').trim().toLowerCase();
    let anyReal = false;
    document.querySelectorAll(`#${listId} .${optClass}`).forEach(b => {
        const n = b.dataset.name;
        if (!n) return; // the static "All / No X" rows always stay visible
        const hide = q !== '' && !n.includes(q);
        b.classList.toggle('hidden', hide);
        if (!hide) anyReal = true;
    });
    document.getElementById(emptyId)?.classList.toggle('hidden', q === '' || anyReal);
}
function projFilterList(v) { optListFilter('hfProjList', 'proj-opt', 'hfProjEmpty', v); }
function leadFilterList(v) { optListFilter('hfLeadList', 'lead-opt', 'hfLeadEmpty', v); }
function changeTeamPerPage(val) {
    document.getElementById('fPerPage').value = val;
    _tPage = 1;
    reloadTeams();
}
let _tReqSeq = 0;
async function reloadTeams() {
    const qs = new URLSearchParams({
        partial: 1, page: _tPage,
        search:   document.getElementById('fSearch').value || '',
        position: document.getElementById('fPosition').value || '',
        project:  document.getElementById('fProject').value || '',
        lead:     document.getElementById('fLead').value || '',
        per_page: document.getElementById('fPerPage').value || 15,
    });
    const seq = ++_tReqSeq;
    document.getElementById('teamLoading')?.classList.remove('hidden');
    try {
        const res = await fetch(`${T_URL}?${qs}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (seq !== _tReqSeq) return; // a newer keystroke already fired
        document.getElementById('teamRows').innerHTML = data.rows;
        document.getElementById('teamPager').innerHTML = data.pager;
        paintFilterIcons();
        try {
            const clean = new URLSearchParams(qs); clean.delete('partial'); clean.delete('page');
            history.replaceState(null, '', location.pathname + (clean.toString() ? '?' + clean : ''));
        } catch (e) {}
    } catch (e) {
        showToast('Could not load results.', 'error');
    } finally {
        document.getElementById('teamLoading')?.classList.add('hidden');
    }
}
function paintFilterIcons() {
    const map = { hfEmp: 'fSearch', hfPos: 'fPosition', hfProj: 'fProject', hfLead: 'fLead' };
    let any = false;
    Object.entries(map).forEach(([pop, field]) => {
        const active = !!(document.getElementById(field)?.value);
        if (active) any = true;
        document.querySelector(`[onclick*="hf(event,'${pop}')"]`)
            ?.classList.toggle('text-(--primary-color)', active);
    });
    document.getElementById('teamResetBtn')?.classList.toggle('hidden', !any);
}
// pager buttons live inside the swapped-in partial
document.addEventListener('click', e => {
    const pg = e.target.closest('.tpg');
    if (pg && pg.dataset.page) { _tPage = parseInt(pg.dataset.page, 10) || 1; reloadTeams(); }
});
</script>
@endsection
