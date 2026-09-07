@extends('dashboard')

@section('title', 'KPI Evaluation — Team & Leads')
@section('page-title', 'KPI Evaluation')

@section('content')
@php
    $user = session('user');
    $canEdit = $canEdit ?? false;
    $hasFilters = !empty($search) || !empty($positionFilter) || ($leadFilter ?? '') !== '' || ($teamFilter ?? '') !== '';
    $srcBadge = [
        'manual'  => ['Manual',  'bg-gray-100 text-gray-600'],
        'team'    => ['Team',    'bg-indigo-50 text-indigo-600'],
        'project' => ['Project', 'bg-amber-50 text-amber-700'],
    ];
    $teamsJs = $teams->map(function ($t) {
        return [
            'id'        => (string) $t->id,
            'name'      => $t->name,
            'lead_id'   => $t->lead_employee_id ? (string) $t->lead_employee_id : '',
            'lead_name' => $t->lead?->basicData?->full_name ?? ($t->lead_employee_id ? ('#' . $t->lead_employee_id) : null),
        ];
    })->values();
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
            <i class="fas fa-sitemap mr-1.5"></i> Team &amp; Leads
        </span>
    </div>

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                    <i class="fas fa-sitemap"></i>
                </span>
                Team &amp; Leads
            </h1>
            <p class="text-xs text-gray-500 mt-1 max-w-2xl">
                Put people on a <strong>team</strong> or pull the lead from their <strong>project</strong> — the leader is filled
                in automatically. You can still override any single person. The leader drives the lead-assessment, the
                “My Team” tab, and the supervisor set when a template is assigned.
            </p>
        </div>
        @if($canEdit)
        <button type="button" onclick="openTeamMgr()"
            class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 bg-gray-900 text-white text-xs font-bold rounded-xl hover:bg-gray-800 transition-all">
            <i class="fas fa-users-gear text-xs"></i> Manage Teams
        </button>
        @endif
    </div>

    {{-- ── Summary tiles ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
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
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div class="text-3xl font-bold text-indigo-600">{{ $stats['teams'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium">KPI teams</div>
        </div>
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-wrap items-center gap-2">
            <i class="fas fa-users text-gray-400"></i>
            <h3 class="text-sm font-bold text-gray-800">Reporting lines</h3>
            <span class="text-xs text-gray-400">Use the <i class="fas fa-filter text-[10px]"></i> icons to filter.</span>
            <button type="button" id="teamResetBtn" onclick="resetTeams()"
                class="{{ $hasFilters ? '' : 'hidden' }} ml-1 inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all">
                <i class="fas fa-rotate-left text-[10px]"></i> Reset filters
            </button>
        </div>

        <form method="GET" action="{{ route('general.kpi-evaluation.teams') }}" id="teamFilterForm" class="hidden">
            <input type="hidden" name="search"   id="fSearch"   value="{{ $search ?? '' }}">
            <input type="hidden" name="position" id="fPosition" value="{{ $positionFilter ?? '' }}">
            <input type="hidden" name="lead"     id="fLead"     value="{{ $leadFilter ?? '' }}">
            <input type="hidden" name="team"     id="fTeam"     value="{{ $teamFilter ?? '' }}">
            <input type="hidden" name="per_page" id="fPerPage"  value="{{ $perPage ?? 15 }}">
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/90 border-b border-gray-100 select-none">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-10">No</th>

                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[210px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Employee</span>
                                <button type="button" onclick="hf(event,'hfEmp')" class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($search) ? 'text-[var(--primary-color)]' : 'text-gray-400 hover:text-gray-600' }}"><i class="fas fa-filter text-[10px]"></i></button>
                            </div>
                            <div id="hfEmp" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 p-2.5 z-50 min-w-[230px] normal-case" onclick="event.stopPropagation()">
                                <div class="relative">
                                    <input type="text" id="hfEmpSearch" value="{{ $search ?? '' }}" placeholder="Type a name or ECI…" autocomplete="off"
                                        oninput="fSearchType(this.value)"
                                        onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
                                        class="w-full bg-gray-50 border border-gray-200 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:ring-1 focus:ring-[var(--primary-color)] font-normal">
                                    <i class="fas fa-search text-[10px] absolute left-2 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1.5 px-0.5">Results update as you type.</p>
                            </div>
                        </th>

                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[130px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Position</span>
                                <button type="button" onclick="hf(event,'hfPos')" class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ !empty($positionFilter) ? 'text-[var(--primary-color)]' : 'text-gray-400 hover:text-gray-600' }}"><i class="fas fa-filter text-[10px]"></i></button>
                            </div>
                            <div id="hfPos" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[190px] max-h-[260px] overflow-y-auto normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="setHF('fPosition','')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ empty($positionFilter) ? 'font-bold text-[var(--primary-color)]' : '' }}">All positions</button>
                                @foreach($positions as $pos)
                                <button type="button" onclick="setHF('fPosition',{{ Js::from($pos) }})" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($positionFilter ?? '') === $pos ? 'font-bold text-[var(--primary-color)]' : '' }}">{{ $pos }}</button>
                                @endforeach
                            </div>
                        </th>

                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider min-w-[170px]">Project</th>

                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[200px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Team</span>
                                <button type="button" onclick="hf(event,'hfTeam')" class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ ($teamFilter ?? '') !== '' ? 'text-[var(--primary-color)]' : 'text-gray-400 hover:text-gray-600' }}"><i class="fas fa-filter text-[10px]"></i></button>
                            </div>
                            <div id="hfTeam" class="header-filter-popover hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[200px] max-h-[280px] overflow-y-auto normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="setHF('fTeam','')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($teamFilter ?? '') === '' ? 'font-bold text-[var(--primary-color)]' : '' }}">All</button>
                                <button type="button" onclick="setHF('fTeam','none')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($teamFilter ?? '') === 'none' ? 'font-bold text-[var(--primary-color)]' : '' }}">— No team —</button>
                                @foreach($teams as $t)
                                <button type="button" onclick="setHF('fTeam','{{ $t->id }}')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ (string)($teamFilter ?? '') === (string)$t->id ? 'font-bold text-[var(--primary-color)]' : '' }}">{{ $t->name }}</button>
                                @endforeach
                            </div>
                        </th>

                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[230px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Leader</span>
                                <button type="button" onclick="hf(event,'hfLead')" class="p-1 rounded-md hover:bg-gray-200/70 transition-all {{ ($leadFilter ?? '') !== '' ? 'text-[var(--primary-color)]' : 'text-gray-400 hover:text-gray-600' }}"><i class="fas fa-filter text-[10px]"></i></button>
                            </div>
                            <div id="hfLead" class="header-filter-popover hidden absolute top-full right-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[220px] max-h-[280px] overflow-y-auto normal-case font-normal" onclick="event.stopPropagation()">
                                <button type="button" onclick="setHF('fLead','')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($leadFilter ?? '') === '' ? 'font-bold text-[var(--primary-color)]' : '' }}">All</button>
                                <button type="button" onclick="setHF('fLead','none')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ ($leadFilter ?? '') === 'none' ? 'font-bold text-[var(--primary-color)]' : '' }}">— No leader —</button>
                                @foreach($leadOptions->where('is_lead', true) as $o)
                                <button type="button" onclick="setHF('fLead','{{ $o['id'] }}')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ (string)($leadFilter ?? '') === $o['id'] ? 'font-bold text-[var(--primary-color)]' : '' }}">{{ $o['name'] }}</button>
                                @endforeach
                            </div>
                        </th>

                        <th class="px-4 py-3 w-6"></th>
                    </tr>
                </thead>
                <tbody id="teamRows" class="divide-y divide-gray-50">
                    @include('hr-general.kpi.partials._teams-rows')
                </tbody>
            </table>
        </div>

        <div id="teamPager">
            @include('hr-general.kpi.partials._teams-pager')
        </div>
        <div id="teamLoading" class="hidden px-5 py-3 text-center text-xs text-gray-400"><i class="fas fa-circle-notch fa-spin mr-1"></i> Loading…</div>
    </div>
</div>

{{-- ── Manage Teams modal ──────────────────────────────────────────────────── --}}
@if($canEdit)
<div id="teamMgrModal" class="fixed inset-0 bg-black/50 z-[60] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900"><i class="fas fa-users-gear text-gray-500 mr-1.5"></i> Manage KPI Teams</h3>
            <button type="button" onclick="closeTeamMgr()" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 overflow-y-auto space-y-2" id="teamMgrList">
            @foreach($teams as $t)
            <div class="tm-row flex flex-wrap items-center gap-2 p-2.5 rounded-xl border border-gray-200 bg-gray-50/50" data-id="{{ $t->id }}">
                <input type="text" class="tm-name flex-1 min-w-[140px] px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-indigo-400" value="{{ $t->name }}">
                <div class="tmlead-cell relative">
                    <button type="button" onclick="tmLeadOpen(this)" class="tm-lead-btn flex items-center gap-2 px-3 py-2 text-xs border border-gray-200 rounded-lg bg-white hover:border-indigo-300 min-w-[170px]">
                        <span class="tm-lead-label truncate {{ $t->lead ? 'text-gray-800' : 'text-gray-400 italic' }}">{{ $t->lead?->basicData?->full_name ?? 'No lead' }}</span>
                        <i class="fas fa-chevron-down text-[9px] text-gray-400 ml-auto"></i>
                    </button>
                    <input type="hidden" class="tm-lead-id" value="{{ $t->lead_employee_id ?? '' }}">
                    <div class="tm-lead-menu hidden absolute z-[70] left-0 mt-1 w-[240px] bg-white border border-gray-200 rounded-xl shadow-xl">
                        <div class="p-2 border-b border-gray-100"><input type="text" class="tm-lead-search w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg" placeholder="Search…" oninput="tmLeadFilter(this)"></div>
                        <div class="tm-lead-list max-h-52 overflow-y-auto py-1"></div>
                    </div>
                </div>
                <span class="text-[11px] text-gray-400 px-1">{{ $t->member_basic_data_count }} member{{ $t->member_basic_data_count == 1 ? '' : 's' }}</span>
                <button type="button" onclick="saveTeamRow(this)" class="px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">Save</button>
                <button type="button" onclick="deleteTeamRow(this)" class="px-2.5 py-2 bg-red-50 text-red-500 text-xs rounded-lg hover:bg-red-100 border border-red-200"><i class="fas fa-trash text-[10px]"></i></button>
            </div>
            @endforeach
            <p id="teamMgrEmpty" class="text-xs text-gray-400 py-2 {{ $teams->count() ? 'hidden' : '' }}">No teams yet — add one below.</p>
        </div>
        <div class="p-5 border-t border-gray-100 bg-gray-50/60 flex flex-wrap items-center gap-2">
            <input type="text" id="newTeamName" placeholder="New team name…" class="flex-1 min-w-[160px] px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
            <div class="tmlead-cell relative" id="newTeamLeadCell">
                <button type="button" onclick="tmLeadOpen(this)" class="tm-lead-btn flex items-center gap-2 px-3 py-2 text-xs border border-gray-200 rounded-lg bg-white hover:border-indigo-300 min-w-[170px]">
                    <span class="tm-lead-label text-gray-400 italic truncate">No lead</span>
                    <i class="fas fa-chevron-down text-[9px] text-gray-400 ml-auto"></i>
                </button>
                <input type="hidden" class="tm-lead-id" value="">
                <div class="tm-lead-menu hidden absolute z-[70] left-0 bottom-full mb-1 w-[240px] bg-white border border-gray-200 rounded-xl shadow-xl">
                    <div class="p-2 border-b border-gray-100"><input type="text" class="tm-lead-search w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg" placeholder="Search…" oninput="tmLeadFilter(this)"></div>
                    <div class="tm-lead-list max-h-48 overflow-y-auto py-1"></div>
                </div>
            </div>
            <button type="button" onclick="createTeam()" class="px-4 py-2 bg-gray-900 text-white text-xs font-bold rounded-lg hover:bg-gray-800">Add team</button>
        </div>
    </div>
</div>
@endif

<script>
const LEAD_OPTS = @json($leadOptions);
const TEAMS     = @json($teamsJs);
const PROJ_LEAD = @json($projectLeadMap);
const T_URL     = "{{ url('/general/kpi-evaluation/teams') }}";
const CSRF      = '{{ csrf_token() }}';
const SRC_BADGE = { manual: ['Manual', 'bg-gray-100 text-gray-600'], team: ['Team', 'bg-indigo-50 text-indigo-600'], project: ['Project', 'bg-amber-50 text-amber-700'] };

// ── header filters — realtime, no page reload ─────────────────────────────
let _tPage = 1;
function hf(e, id) {
    e.stopPropagation();
    const el = document.getElementById(id);
    const wasHidden = el.classList.contains('hidden');
    document.querySelectorAll('.header-filter-popover').forEach(p => p.classList.add('hidden'));
    if (wasHidden) { el.classList.remove('hidden'); el.querySelector('input')?.focus(); }
}
function setHF(field, val) {
    document.getElementById(field).value = val;
    _tPage = 1;
    // dropdown filters can close the popover; the search box stays open
    if (field !== 'fSearch') document.querySelectorAll('.header-filter-popover').forEach(p => p.classList.add('hidden'));
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
    ['fSearch', 'fPosition', 'fLead', 'fTeam'].forEach(f => document.getElementById(f).value = '');
    const si = document.getElementById('hfEmpSearch'); if (si) si.value = '';
    document.querySelectorAll('.header-filter-popover').forEach(p => p.classList.add('hidden'));
    _tPage = 1;
    reloadTeams();
}
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
        lead:     document.getElementById('fLead').value || '',
        team:     document.getElementById('fTeam').value || '',
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
    const map = { hfEmp: 'fSearch', hfPos: 'fPosition', hfTeam: 'fTeam', hfLead: 'fLead' };
    let any = false;
    Object.entries(map).forEach(([pop, field]) => {
        const active = !!(document.getElementById(field)?.value);
        if (active) any = true;
        document.querySelector(`[onclick="hf(event,'${pop}')"]`)
            ?.classList.toggle('text-[var(--primary-color)]', active);
    });
    document.getElementById('teamResetBtn')?.classList.toggle('hidden', !any);
}
// pager buttons live inside the swapped-in partial
document.addEventListener('click', e => {
    const pg = e.target.closest('.tpg');
    if (pg && pg.dataset.page) { _tPage = parseInt(pg.dataset.page, 10) || 1; reloadTeams(); }
});
document.addEventListener('click', e => {
    if (!e.target.closest('.header-filter-popover')) document.querySelectorAll('.header-filter-popover').forEach(p => p.classList.add('hidden'));
    if (!e.target.closest('.lead-cell')   && !e.target.closest('.lead-menu'))   document.querySelectorAll('.lead-menu').forEach(m => m.classList.add('hidden'));
    if (!e.target.closest('.team-cell')   && !e.target.closest('.team-menu'))   document.querySelectorAll('.team-menu').forEach(m => m.classList.add('hidden'));
    if (!e.target.closest('.proj-cell')   && !e.target.closest('.proj-menu'))   document.querySelectorAll('.proj-menu').forEach(m => m.classList.add('hidden'));
    if (!e.target.closest('.tmlead-cell') && !e.target.closest('.tm-lead-menu')) document.querySelectorAll('.tm-lead-menu').forEach(m => m.classList.add('hidden'));
    if (_openMenu && _openMenu.menu.classList.contains('hidden')) clearFloatMenu();
});
const esc = s => (s || '').toString().replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

// ── Float a row/cell dropdown to the viewport so the table's overflow can't
//    clip it (no horizontal scrolling to see the options). ───────────────────
let _openMenu = null; // { btn, menu }
function floatMenu(btn, menu) {
    menu.style.position = 'fixed';
    menu.style.zIndex   = '9999';
    menu.style.margin   = '0';
    menu.style.top = '-9999px';
    menu.style.left = '-9999px';
    const mw = menu.offsetWidth || 260;
    const mh = menu.offsetHeight || 240;
    const r  = btn.getBoundingClientRect();
    const vw = document.documentElement.clientWidth;
    const vh = window.innerHeight;
    let left = Math.min(Math.max(8, r.left), vw - mw - 8);
    let top  = r.bottom + 4;
    if (top + mh > vh - 8 && r.top - mh - 4 > 8) top = r.top - mh - 4;      // flip up
    top = Math.max(8, Math.min(top, vh - mh - 8));
    menu.style.left = left + 'px';
    menu.style.top  = top + 'px';
    _openMenu = { btn, menu };
}
function clearFloatMenu() {
    if (!_openMenu) return;
    const m = _openMenu.menu;
    m.style.position = m.style.top = m.style.left = m.style.zIndex = m.style.margin = '';
    _openMenu = null;
}
window.addEventListener('scroll', () => {
    if (_openMenu && !_openMenu.menu.classList.contains('hidden')) floatMenu(_openMenu.btn, _openMenu.menu);
    else if (_openMenu) clearFloatMenu();
}, true);
window.addEventListener('resize', () => {
    if (_openMenu && !_openMenu.menu.classList.contains('hidden')) floatMenu(_openMenu.btn, _openMenu.menu);
});

function paintSource(cell, src) {
    const badge = cell.querySelector('.lead-src');
    if (!badge) return;
    if (src && SRC_BADGE[src]) {
        badge.textContent = SRC_BADGE[src][0];
        badge.className = 'lead-src inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold ' + SRC_BADGE[src][1];
    } else {
        badge.className = 'lead-src hidden';
    }
}
function paintLeader(empId, name, src) {
    document.querySelectorAll(`.lead-cell[data-emp="${empId}"]`).forEach(cell => {
        const lbl = cell.querySelector('.lead-label');
        cell.querySelector('.lead-id').value = '';
        lbl.textContent = name || 'No leader — set one';
        lbl.className = 'lead-label truncate ' + (name ? 'font-medium text-gray-800' : 'text-gray-400 italic');
        paintSource(cell, src);
    });
}

// ── generic list renderer ────────────────────────────────────────────────
function fillList(listEl, opts, currentId, onPick, extraTop) {
    let html = extraTop || '';
    html += opts.map(o => `
        <button type="button" class="opt block w-full text-left px-3 py-2 text-xs hover:bg-indigo-50 ${o.id === currentId ? 'bg-indigo-50 font-semibold' : ''}" data-id="${esc(o.id)}" data-label="${esc(o.name)}">
            <span class="font-medium text-gray-800">${esc(o.name)}</span>
            ${o.meta ? `<span class="text-gray-400 ml-1">${esc(o.meta)}</span>` : ''}
            ${o.is_lead ? '<span class="ml-1 text-[9px] text-indigo-500 font-bold">LEAD</span>' : ''}
        </button>`).join('');
    listEl.innerHTML = html || '<p class="px-3 py-3 text-[11px] text-gray-400">No match</p>';
    listEl.querySelectorAll('.opt').forEach(b => b.onclick = () => onPick(b.dataset.id, b.dataset.label || null));
}

// ── Leader (manual) ─────────────────────────────────────────────────────
function leadOpen(btn) {
    const cell = btn.closest('.lead-cell'), menu = cell.querySelector('.lead-menu');
    const open = menu.classList.contains('hidden');
    document.querySelectorAll('.lead-menu,.team-menu,.proj-menu').forEach(m => m.classList.add('hidden'));
    clearFloatMenu();
    if (!open) return;
    leadRender(cell, '');
    menu.classList.remove('hidden');
    floatMenu(btn, menu);
    const s = menu.querySelector('.lead-search'); s.value = ''; setTimeout(() => s.focus(), 30);
}
function leadFilter(input) { leadRender(input.closest('.lead-cell'), input.value.trim().toLowerCase()); }
function leadRender(cell, q) {
    const empId = cell.dataset.emp, cur = cell.querySelector('.lead-id').value;
    const opts = LEAD_OPTS.filter(o => o.id !== empId && (!q || o.name.toLowerCase().includes(q) || (o.meta || '').toLowerCase().includes(q))).slice(0, 60);
    fillList(cell.querySelector('.lead-list'), opts, cur,
        (id, label) => post(`${T_URL}/${empId}/lead`, { lead_id: id, sync_kpi: 1 }, d => { paintLeader(empId, d.lead_name, d.source); }),
        `<button type="button" class="opt block w-full text-left px-3 py-2 text-xs text-red-500 hover:bg-red-50" data-id="" data-label="">— No leader —</button>`);
}

// ── Team ───────────────────────────────────────────────────────────────
function tOpen(btn) {
    const cell = btn.closest('.team-cell'), menu = cell.querySelector('.team-menu');
    const open = menu.classList.contains('hidden');
    document.querySelectorAll('.lead-menu,.team-menu,.proj-menu').forEach(m => m.classList.add('hidden'));
    clearFloatMenu();
    if (!open) return;
    tRender(cell, '');
    menu.classList.remove('hidden');
    floatMenu(btn, menu);
    const s = menu.querySelector('.team-search'); s.value = ''; setTimeout(() => s.focus(), 30);
}
function tFilter(input) { tRender(input.closest('.team-cell'), input.value.trim().toLowerCase()); }
function tRender(cell, q) {
    const empId = cell.dataset.emp, cur = cell.querySelector('.team-id').value;
    const opts = TEAMS.filter(t => !q || t.name.toLowerCase().includes(q))
        .map(t => ({ id: t.id, name: t.name, meta: t.lead_name ? ('lead: ' + t.lead_name) : 'no lead' }));
    fillList(cell.querySelector('.team-list'), opts, cur, (id, label) => {
        post(`${T_URL}/${empId}/team`, { team_id: id }, d => {
            cell.querySelector('.team-id').value = id || '';
            const lbl = cell.querySelector('.team-label');
            lbl.textContent = d.team_name || 'No team';
            lbl.className = 'team-label truncate ' + (d.team_name ? 'font-medium text-gray-800' : 'text-gray-400 italic');
            if (d.lead_name) paintLeader(empId, d.lead_name, d.source);
        });
    }, `<button type="button" class="opt block w-full text-left px-3 py-2 text-xs text-red-500 hover:bg-red-50" data-id="" data-label="">— No team —</button>`);
}

// ── Project lead shortcut ──────────────────────────────────────────────
function pOpen(btn) {
    const cell = btn.closest('.proj-cell'), menu = cell.querySelector('.proj-menu');
    const open = menu.classList.contains('hidden');
    document.querySelectorAll('.lead-menu,.team-menu,.proj-menu').forEach(m => m.classList.add('hidden'));
    clearFloatMenu();
    if (!open) return;
    const empId = cell.dataset.emp;
    const ids = (cell.dataset.projids || '').split(',').filter(Boolean);
    menu.innerHTML = ids.map(pid => {
        const p = PROJ_LEAD[pid];
        if (!p) return '';
        const usable = !!p.lead_name;
        return `<button type="button" ${usable ? '' : 'disabled'} class="block w-full text-left px-3 py-2 text-xs ${usable ? 'hover:bg-amber-50' : 'opacity-40 cursor-not-allowed'}" data-pid="${pid}">
            <span class="font-medium text-gray-800">${esc(p.name)}</span><br>
            <span class="text-[10px] text-gray-400">${usable ? 'lead: ' + esc(p.lead_name) : 'no project manager set'}</span>
        </button>`;
    }).join('') || '<p class="px-3 py-2 text-[11px] text-gray-400">No projects</p>';
    menu.querySelectorAll('button[data-pid]').forEach(b => b.onclick = () => {
        post(`${T_URL}/${empId}/project-lead`, { project_id: b.dataset.pid }, d => paintLeader(empId, d.lead_name, d.source));
        menu.classList.add('hidden');
        clearFloatMenu();
    });
    menu.classList.remove('hidden');
    floatMenu(btn, menu);
}

// ── shared POST ────────────────────────────────────────────────────────
async function post(url, body, onOk) {
    const fd = new FormData();
    fd.append('_token', CSRF);
    Object.entries(body).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') fd.append(k, v); });
    try {
        const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Saved.' : 'Failed.'), data.success ? 'success' : 'error');
        if (data.success && onOk) onOk(data);
        document.querySelectorAll('.lead-menu,.team-menu,.proj-menu').forEach(m => m.classList.add('hidden'));
        clearFloatMenu();
        return data;
    } catch (e) { showToast('Network error.', 'error'); }
}

// ── Manage Teams modal ────────────────────────────────────────────────
function openTeamMgr()  { const m = document.getElementById('teamMgrModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeTeamMgr() { const m = document.getElementById('teamMgrModal'); m.classList.add('hidden'); m.classList.remove('flex'); }

function tmLeadOpen(btn) {
    const cell = btn.closest('.tmlead-cell'), menu = cell.querySelector('.tm-lead-menu');
    const open = menu.classList.contains('hidden');
    document.querySelectorAll('.tm-lead-menu').forEach(m => m.classList.add('hidden'));
    clearFloatMenu();
    if (!open) return;
    tmLeadRender(cell, '');
    menu.classList.remove('hidden');
    floatMenu(btn, menu);
    const s = menu.querySelector('.tm-lead-search'); s.value = ''; setTimeout(() => s.focus(), 30);
}
function tmLeadFilter(input) { tmLeadRender(input.closest('.tmlead-cell'), input.value.trim().toLowerCase()); }
function tmLeadRender(cell, q) {
    const cur = cell.querySelector('.tm-lead-id').value;
    const opts = LEAD_OPTS.filter(o => !q || o.name.toLowerCase().includes(q) || (o.meta || '').toLowerCase().includes(q)).slice(0, 60);
    fillList(cell.querySelector('.tm-lead-list'), opts, cur, (id, label) => {
        cell.querySelector('.tm-lead-id').value = id || '';
        const lbl = cell.querySelector('.tm-lead-label');
        lbl.textContent = label || 'No lead';
        lbl.className = 'tm-lead-label truncate ' + (label ? 'text-gray-800' : 'text-gray-400 italic');
        cell.querySelector('.tm-lead-menu').classList.add('hidden');
        clearFloatMenu();
    }, `<button type="button" class="opt block w-full text-left px-3 py-2 text-xs text-red-500 hover:bg-red-50" data-id="" data-label="">— No lead —</button>`);
}
async function saveTeamRow(btn) {
    const row = btn.closest('.tm-row');
    const d = await post(`${T_URL}/groups/save`, {
        id: row.dataset.id,
        name: row.querySelector('.tm-name').value.trim(),
        lead_employee_id: row.querySelector('.tm-lead-id').value,
    });
    if (d && d.success) setTimeout(() => location.reload(), 700);
}
async function deleteTeamRow(btn) {
    if (!await showConfirm('Delete this team? Members keep their current leader.', 'Delete Team', 'danger', { okText: 'Delete' })) return;
    const row = btn.closest('.tm-row');
    const d = await post(`${T_URL}/groups/${row.dataset.id}/delete`, {});
    if (d && d.success) setTimeout(() => location.reload(), 700);
}
async function createTeam() {
    const cell = document.getElementById('newTeamLeadCell');
    const d = await post(`${T_URL}/groups/save`, {
        name: document.getElementById('newTeamName').value.trim(),
        lead_employee_id: cell.querySelector('.tm-lead-id').value,
    });
    if (d && d.success) setTimeout(() => location.reload(), 700);
}
</script>
@endsection
