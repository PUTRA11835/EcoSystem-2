{{--
    Command Center (HC-D36 — lanjutan HC-D25 "Sidebar Favorit").

    Dua bagian:
    1. Tab "Favorite" / "All Menu" — pintasan klik ke menu, TERBUKA untuk
       siapa pun yang login. Daftar menunya TIDAK dikirim dari server; kartu
       dibangun di klien dari tautan yang sudah ada di sidebar (DOM `a.nav-link`)
       + `window.SidebarFavorites` (API yang diekspos sidebar.blade.php), persis
       prinsip HC-D25: "izin tetap dari server" — menu yang tak berizin memang
       tak pernah ada di DOM, jadi tak pernah bisa jadi kartu.
    2. Kotak "N Pending Approval" — jumlah dokumen yang menunggu persetujuan
       ORANG INI, dihitung lewat `pendingIdsFor()` yang sudah ada di 5 service
       alur kerja (Overtime/Reimbursement/Purchase Request/Cash
       Advance/Cash Advance Report). Hanya dihitung & ditampilkan bila orang
       itu diberi slug `general.command-center.pending-approval` lewat Menu
       Access — tidak semua karyawan adalah penyetuju. Klik mengarah LANGSUNG
       ke halaman `/notifications?type=pending_approval` (HC-D40, gaya ESH),
       bukan dropdown ringkasan — itu daftar individual per dokumennya.
--}}
@php
    $ccPendingTotal = 0;

    // ── "Needs attention" kelengkapan data (HC-D62) ────────────────────────────────────────────────
    // PEGAWAI: hanya butir yang BISA ia isi sendiri (bukan butir "HR only", bukan seksi yang tak boleh ia ubah,
    // bukan seksi yang dikunci HR) — penanda tak boleh menyuruh orang melakukan hal yang tak bisa ia lakukan.
    // Hilang otomatis saat tak ada lagi yang perlu diisi. Bukan bel/email: satu pintu masuk yang tak mengganggu.
    $ccProfileTodo = null;
    if ((session('user.type') ?? null) === 'employee') {
        try {
            $ccSelfId = (int) session('user.id');
            $ccProgress = app(\App\Services\Onboarding\OnboardingProgressService::class)->forEmployee($ccSelfId);
            if ($ccProgress && $ccProgress['status'] !== 'complete') {
                $ccLocked = app(\App\Services\HrProfile\ProfileLockService::class)->isLocked($ccSelfId);
                $ccTodo = array_values(array_filter($ccProgress['items'], function ($i) use ($can, $ccLocked) {
                    if ($i['done'] || !empty($i['hr_only'])) {
                        return false;
                    }
                    $sec = str_replace('-', '_', (string) $i['section']);

                    return $can("my-profile.section.{$sec}.update")
                        && !\App\Services\HrProfile\ProfileLockPolicy::blocksOwnerUpdate($ccLocked, $sec);
                }));
                if ($ccTodo) {
                    $ccProfileTodo = ['count' => count($ccTodo), 'percent' => $ccProgress['percent'], 'first' => $ccTodo[0]];
                }
            }
        } catch (\Throwable $e) {
            $ccProfileTodo = null; // penanda opsional: jangan pernah merusak Dashboard
        }
    }

    // HR (pemegang izin Onboarding): berapa karyawan yang datanya belum lengkap dan berapa yang belum punya join date.
    // Dihitung dari layanan progres yang sama dengan halaman Onboarding; di-cache singkat agar Dashboard tetap ringan.
    $ccHr = ['incomplete' => 0, 'no_join_date' => 0];
    if ($can('general.onboarding')) {
        try {
            $ccHr = \Illuminate\Support\Facades\Cache::remember('cc_onboarding_attention', 120, function () {
                $all = app(\App\Services\Onboarding\OnboardingProgressService::class)->all()['employees'];
                $noJoin = 0;
                foreach ($all as $e) {
                    foreach ($e['items'] as $item) {
                        if ($item['key'] === 'join_date' && !$item['done']) {
                            $noJoin++;
                            break;
                        }
                    }
                }

                return [
                    'incomplete'   => count(array_filter($all, fn ($e) => $e['status'] !== 'complete')),
                    'no_join_date' => $noJoin,
                ];
            });
        } catch (\Throwable $e) {
            $ccHr = ['incomplete' => 0, 'no_join_date' => 0];
        }
    }

    if ($can('general.command-center.pending-approval')) {
        $ccActorId = (int) session('user.id');
        $ccServices = [
            \App\Services\Overtime\OvertimeService::class,
            \App\Services\Reimbursement\ReimbursementService::class,
            \App\Services\PurchaseRequest\PurchaseRequestService::class,
            \App\Services\CashAdvance\CashAdvanceService::class,
            \App\Services\CashAdvance\CashAdvanceReportService::class,
        ];

        foreach ($ccServices as $serviceClass) {
            $ccPendingTotal += count(app($serviceClass)->pendingIdsFor($ccActorId));
        }
    }
@endphp
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden" id="commandCenter">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 gap-3 flex-wrap">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center">
                <i class="fas fa-bolt text-indigo-600 text-xs"></i>
            </div>
            <p class="text-sm font-semibold text-gray-800">Command Center</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($ccProfileTodo)
            {{-- HC-D62 — penanda untuk PEGAWAI: klik membawa ke My Profile dan langsung menyorot butir pertama yang bisa diisi. --}}
            <a href="{{ route('profile.my', ['section' => $ccProfileTodo['first']['section'], 'field' => $ccProfileTodo['first']['key']]) }}"
                title="Your profile is {{ $ccProfileTodo['percent'] }}% complete. Select to fill in what is missing."
                class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-amber-100 transition">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                Needs attention · {{ $ccProfileTodo['count'] }} profile {{ $ccProfileTodo['count'] === 1 ? 'item' : 'items' }}
                <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
            @endif
            @if($ccHr['incomplete'] > 0)
            <a href="{{ route('general.onboarding.index') }}"
                title="Employees whose required profile data is not complete yet"
                class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-amber-100 transition">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                {{ $ccHr['incomplete'] }} {{ $ccHr['incomplete'] === 1 ? 'employee' : 'employees' }} incomplete
                <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
            @endif
            @if($ccHr['no_join_date'] > 0)
            <a href="{{ route('general.onboarding.index', ['status' => 'all', 'missing' => 'join_date']) }}"
                title="Internal employees without a join date. HR sets it in Master › Employee › Basic Data."
                class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-amber-100 transition">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                {{ $ccHr['no_join_date'] }} without join date
                <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
            @endif
            @if($ccPendingTotal > 0)
            {{-- HC-D40 — tautan LANGSUNG ke halaman Pending Approval (bergaya
                 ESH: kartu ringkasan, cari, tab), bukan dropdown ringkasan.
                 Keputusan pemilik: "Command Center -> Pending Approval" harus
                 satu klik langsung ke halaman itu, bukan dropdown perantara. --}}
            <a href="{{ route('notifications.index', ['type' => 'pending_approval', 'tab' => 'unread']) }}"
                class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-amber-100 transition">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                {{ $ccPendingTotal }} Pending Approval
                <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
            @endif

            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-semibold" role="tablist">
                <button type="button" data-cc-tab="pinned" class="cc-tab px-3 py-1.5 rounded-md transition">Favorite</button>
                <button type="button" data-cc-tab="all" class="cc-tab px-3 py-1.5 rounded-md transition">All Menu</button>
            </div>
        </div>
    </div>

    <div class="p-5">
        <div id="ccPanelPinned" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
        <div id="ccPanelAll" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
        <p id="ccEmptyPinned" class="hidden text-center text-sm text-gray-400 py-6">
            No pinned menu yet — hover a menu in the sidebar and click the pin icon to add one.
        </p>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var root = document.getElementById('commandCenter');
    if (!root) { return; }

    var scroll       = document.getElementById('sidebarScroll');
    var panelPinned  = document.getElementById('ccPanelPinned');
    var panelAll     = document.getElementById('ccPanelAll');
    var emptyPinned  = document.getElementById('ccEmptyPinned');
    var tabs         = Array.prototype.slice.call(root.querySelectorAll('.cc-tab'));

    var FORBIDDEN = /^\/(api|auth|sidebar)(\/|$)|^\/logout(\/|$)/;

    function pathOf(a) {
        var href = a.getAttribute('href');
        if (!href || href === '#' || href.indexOf('javascript:') === 0) { return null; }
        try {
            var u = new URL(href, window.location.origin);
            if (u.origin !== window.location.origin || FORBIDDEN.test(u.pathname)) { return null; }
            return u.pathname.length > 1 ? u.pathname.replace(/\/+$/, '') : u.pathname;
        } catch (e) { return null; }
    }

    // Judul bersih dari <a class="nav-link"> — teks span.nav-text PERTAMA
    // yang bukan lencana angka (badge jumlah menunggu, mis. CAR/Offering Letter).
    function labelOf(a) {
        var spans = a.querySelectorAll('.nav-text');
        for (var i = 0; i < spans.length; i++) {
            var t = spans[i].textContent.trim();
            if (t && !/^\d+\+?$/.test(t)) { return t; }
        }
        return a.textContent.trim();
    }

    // Ikon sidebar kini SVG sprite (<use href="#ti-…">) untuk menu yang dikenal, atau <i> Font Awesome
    // untuk yang tidak dikenal — kembalikan keduanya agar kartu memakai ikon yang sama dengan sidebar.
    function iconOf(a) {
        var use = a.querySelector('.nav-icon svg use');
        if (use) { return { svg: use.getAttribute('href') }; }
        var i = a.querySelector('.nav-icon i');
        return { cls: i ? i.className : 'fas fa-circle' };
    }

    // Breadcrumb = label dropdown pembungkus terdekat (button.nav-link),
    // ditelusuri lewat parentElement — tanpa kode singkatan, sesuai permintaan.
    function breadcrumbOf(a) {
        var el = a.parentElement;
        while (el && el !== scroll) {
            var btn = el.previousElementSibling;
            if (btn && btn.tagName === 'BUTTON' && btn.classList.contains('nav-link')) {
                var span = btn.querySelector('.nav-text');
                if (span) { return span.textContent.trim(); }
            }
            el = el.parentElement;
        }
        return null;
    }

    function collectMenu() {
        if (!scroll) { return []; }
        var seen = {};
        var out = [];
        Array.prototype.slice.call(scroll.querySelectorAll('a.nav-link')).forEach(function (a) {
            var path = pathOf(a);
            if (!path || seen[path]) { return; }
            seen[path] = true;
            out.push({ path: path, label: labelOf(a), icon: iconOf(a), breadcrumb: breadcrumbOf(a) });
        });
        return out;
    }

    var allMenu = collectMenu();

    function pinnedPaths() {
        if (window.SidebarFavorites && typeof window.SidebarFavorites.list === 'function') {
            return window.SidebarFavorites.list();
        }
        return Array.isArray(window.__sidebarFavorites) ? window.__sidebarFavorites.slice() : [];
    }

    function isPinned(path) { return pinnedPaths().indexOf(path) !== -1; }

    function card(item) {
        var a = document.createElement('a');
        a.href = item.path;
        a.className = 'relative flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-sm transition-all group bg-white';

        var iconWrap = document.createElement('div');
        iconWrap.className = 'w-9 h-9 rounded-lg bg-indigo-50 group-hover:bg-indigo-100 flex items-center justify-center shrink-0 transition';
        var icon;
        if (item.icon.svg) {
            var NS = 'http://www.w3.org/2000/svg';
            icon = document.createElementNS(NS, 'svg');
            icon.setAttribute('class', 'sb-ico text-indigo-600 w-[18px] h-[18px]');
            icon.setAttribute('aria-hidden', 'true');
            var useEl = document.createElementNS(NS, 'use');
            useEl.setAttribute('href', item.icon.svg);
            icon.appendChild(useEl);
        } else {
            icon = document.createElement('i');
            icon.className = item.icon.cls + ' text-indigo-600 text-sm';
        }
        iconWrap.appendChild(icon);
        a.appendChild(iconWrap);

        var text = document.createElement('div');
        text.className = 'min-w-0 flex-1';
        var title = document.createElement('p');
        title.className = 'text-sm font-semibold text-gray-800 truncate';
        title.textContent = item.label;
        text.appendChild(title);
        if (item.breadcrumb) {
            var sub = document.createElement('p');
            sub.className = 'text-[11px] text-gray-400 truncate mt-0.5';
            sub.textContent = item.breadcrumb;
            text.appendChild(sub);
        }
        a.appendChild(text);

        if (window.__sidebarFavoritesEnabled) {
            var pinned = isPinned(item.path);
            var pin = document.createElement('button');
            pin.type = 'button';
            pin.setAttribute('aria-label', pinned ? 'Unpin from sidebar' : 'Pin to sidebar');
            pin.title = pinned ? 'Unpin from sidebar' : 'Pin to sidebar';
            pin.className = 'absolute top-2 right-2 w-6 h-6 rounded-md flex items-center justify-center text-xs transition ' +
                (pinned ? 'text-amber-500' : 'text-gray-300 opacity-0 group-hover:opacity-100 hover:text-gray-500');
            var pinIcon = document.createElement('i');
            pinIcon.className = 'fas fa-thumbtack';
            pin.appendChild(pinIcon);
            pin.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (window.SidebarFavorites && typeof window.SidebarFavorites.toggle === 'function') {
                    window.SidebarFavorites.toggle(item.path);
                }
            });
            a.appendChild(pin);
        }

        return a;
    }

    function renderAll() {
        panelAll.textContent = '';
        allMenu.forEach(function (item) { panelAll.appendChild(card(item)); });
    }

    function renderPinned() {
        panelPinned.textContent = '';
        var paths = pinnedPaths();
        var byPath = {};
        allMenu.forEach(function (item) { byPath[item.path] = item; });
        var shown = 0;
        paths.forEach(function (path) {
            var item = byPath[path];
            if (!item) { return; }
            panelPinned.appendChild(card(item));
            shown++;
        });
        emptyPinned.classList.toggle('hidden', shown > 0);
    }

    function renderAllPinIcons() {
        // Perbarui ikon pin pada kartu "All Menu" yang sedang tampil tanpa membangun ulang semuanya.
        Array.prototype.slice.call(panelAll.querySelectorAll('a')).forEach(function (a, idx) {
            var item = allMenu[idx];
            if (!item) { return; }
            var pinIcon = a.querySelector('button i');
            var pin = pinIcon ? pinIcon.parentElement : null;
            if (!pin) { return; }
            var pinned = isPinned(item.path);
            pin.setAttribute('aria-label', pinned ? 'Unpin from sidebar' : 'Pin to sidebar');
            pin.title = pin.getAttribute('aria-label');
            pin.className = 'absolute top-2 right-2 w-6 h-6 rounded-md flex items-center justify-center text-xs transition ' +
                (pinned ? 'text-amber-500' : 'text-gray-300 opacity-0 group-hover:opacity-100 hover:text-gray-500');
        });
    }

    function setActiveTab(name) {
        tabs.forEach(function (btn) {
            var active = btn.getAttribute('data-cc-tab') === name;
            btn.classList.toggle('bg-white', active);
            btn.classList.toggle('shadow-sm', active);
            btn.classList.toggle('text-gray-800', active);
            btn.classList.toggle('text-gray-500', !active);
        });
        panelPinned.classList.toggle('hidden', name !== 'pinned');
        panelAll.classList.toggle('hidden', name !== 'all');
        emptyPinned.classList.toggle('hidden', name !== 'pinned' || panelPinned.children.length > 0);
    }

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () { setActiveTab(btn.getAttribute('data-cc-tab')); });
    });

    window.addEventListener('sidebar:favorites-changed', function () {
        renderPinned();
        renderAllPinIcons();
    });

    renderAll();
    renderPinned();
    setActiveTab('pinned');
})();
</script>
@endpush
