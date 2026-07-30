{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- KUNCI READ-ONLY UNTUK PROJECT YANG SUDAH DI-CLOSE                          --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--
    Penegakan sebenarnya ada di middleware `project.editable`
    (App\Http\Middleware\EnsureProjectNotClosed) pada semua route tulis project.
    Partial ini adalah lapisan UI + pagar terakhir di browser.

    KENAPA PERLU:
    CSS lama hanya mengunci `body.project-closed section.section-animate`, padahal
    SEMUA modal (Plan Cost, Actual Expense Details, Team, Documents, Issue, Risk, …)
    dirender di LUAR <section> — jadi form tambah/edit/hapus di dalam modal tetap
    aktif sepenuhnya. Ini pitfall yang sama dengan izin section:
    lihat delivery/partials/section-permissions.blade.php.

    YANG DIKUNCI:
      1. Kontrol tulis di semua <section> DAN semua modal (kecuali yang
         di-exempt), termasuk yang dirender ulang oleh JS.
      2. Semua request non-GET ke endpoint bercakupan project (fetch, XHR, form
         submit). Ini choke point-nya: sekalipun ada tombol yang lolos, request
         tetap tidak pernah berangkat.

    YANG TETAP JALAN: baca data, buka/scroll section, membuka modal detail,
    membuka & mengunduh file (link OneDrive), serta tombol Reopen/Close/Delete
    project itu sendiri.

    Pemakaian: @include('delivery.partials.project-closed-lock', ['project' => $project])
--}}
@if($project->is_closed ?? false)
@push('styles')
<style>
    /* Kontrol tulis disembunyikan, bukan sekadar diredupkan, supaya tidak ada
       yang mengira masih bisa dipakai. */
    .closed-lock-hidden { display: none !important; }

    /* Field yang tersisa (mis. filter di dalam modal) tampil jelas non-aktif. */
    body.project-closed :is(input, select, textarea)[disabled] {
        background-color: #f9fafb !important;
        color: #6b7280 !important;
        cursor: not-allowed !important;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const PROJECT_ID = @json($project->id);
    const MESSAGE    = 'This project is closed and is read-only. Reopen it first to make changes.';

    // Aksi yang tetap boleh berjalan meski project closed — harus cocok PERSIS
    // (jangan pakai "mengandung /delete", karena hapus expense pun berakhiran itu).
    const ALLOWED_PATHS = [
        '/projects/' + PROJECT_ID + '/close',
        '/projects/' + PROJECT_ID + '/reopen',
        '/projects/' + PROJECT_ID + '/delete',
    ];

    // Hanya endpoint bercakupan project yang diblokir; request global
    // (logout, notifikasi, preferensi tema, …) tidak boleh ikut mati.
    const PROJECT_SCOPED = /^\/(projects|project|project-updates|planning)(\/|$)/;

    // ── 1. Pagar request ────────────────────────────────────────────────────
    function isBlocked(method, url) {
        const m = String(method || 'GET').toUpperCase();
        if (m === 'GET' || m === 'HEAD' || m === 'OPTIONS') return false;

        let parsed;
        try { parsed = new URL(url, window.location.origin); } catch (e) { return false; }
        if (parsed.origin !== window.location.origin) return false;

        const path = parsed.pathname.replace(/\/+$/, '');
        if (ALLOWED_PATHS.indexOf(path) !== -1) return false;

        return PROJECT_SCOPED.test(path);
    }

    let toastTimer = null;
    function notify() {
        // Toast mandiri — partial ini tidak boleh bergantung pada helper toast
        // milik section mana pun (bisa belum ter-load / berbeda per halaman).
        let el = document.getElementById('projectClosedToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'projectClosedToast';
            el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;max-width:22rem;'
                + 'background:#7f1d1d;color:#fff;padding:12px 16px;border-radius:10px;'
                + 'box-shadow:0 10px 25px rgba(0,0,0,.25);font-size:13px;line-height:1.4;display:none;';
            document.body.appendChild(el);
        }
        el.textContent = MESSAGE;
        el.style.display = 'block';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    const originalFetch = window.fetch;
    window.fetch = function (input, init) {
        const url    = (input && typeof input === 'object' && 'url' in input) ? input.url : String(input);
        const method = (init && init.method)
            || (input && typeof input === 'object' && input.method)
            || 'GET';

        if (isBlocked(method, url)) {
            notify();
            // Balas seperti middleware-nya (423 Locked) supaya cabang error di
            // pemanggil tetap jalan dan pesannya sama dengan yang dari server.
            return Promise.resolve(new Response(
                JSON.stringify({ success: false, message: MESSAGE }),
                { status: 423, headers: { 'Content-Type': 'application/json' } }
            ));
        }
        return originalFetch.apply(this, arguments);
    };

    const originalOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
        this.__projectLocked = isBlocked(method, url);
        return originalOpen.apply(this, arguments);
    };

    const originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function () {
        if (this.__projectLocked) {
            notify();
            this.dispatchEvent(new Event('error'));
            return;
        }
        return originalSend.apply(this, arguments);
    };

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (!isBlocked(method, form.getAttribute('action') || window.location.href)) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        notify();
    }, true);

    // ── 2. Kunci tampilan ───────────────────────────────────────────────────
    // Pola label sama dengan section-permissions.blade.php supaya perilakunya
    // konsisten: tombol baca/navigasi (Close, Cancel, View, Export) tidak ikut.
    const WRITE_LABEL = /\b(add|new|create|delete|remove|upload|import|generate|assign|unassign|save|update|edit|change|submit|confirm|apply|sync|reorder)\b/i;

    function labelOf(el) {
        return (el.textContent || '') + ' ' +
               (el.getAttribute('title') || '') + ' ' +
               (el.getAttribute('aria-label') || '');
    }

    function lockContainer(root) {
        root.querySelectorAll('[data-closed-hide]').forEach(function (el) {
            el.classList.add('closed-lock-hidden');
        });

        root.querySelectorAll('input, select, textarea').forEach(function (f) {
            if (f.type === 'hidden' || f.closest('[data-lock-exempt]')) return;
            f.disabled = true;
        });

        root.querySelectorAll('button, a[role="button"], input[type="submit"]').forEach(function (b) {
            if (b.closest('[data-lock-exempt]') || b.hasAttribute('data-lock-exempt')) return;
            if (!WRITE_LABEL.test(labelOf(b)) && b.type !== 'submit') return;
            b.classList.add('closed-lock-hidden');
            b.disabled = true;
        });
    }

    // Section + SEMUA root modal (div fixed inset-0 ber-id) + wrapper halaman
    // yang opt-in lewat data-closed-lock-root (mis. Phase Management yang tidak
    // memakai <section>). Modal state project (Close/Reopen/Delete) di-exempt
    // lewat atribut data-lock-exempt di view.
    function containers() {
        return document.querySelectorAll(
            'section.section-animate, [data-closed-lock-root], div[id][class*="inset-0"]:not([data-lock-exempt])'
        );
    }

    /** Halaman ber-lock-root belum tentu punya banner sendiri → sisipkan. */
    function ensureBanner() {
        const root = document.querySelector('[data-closed-lock-root]');
        if (!root || document.getElementById('projectClosedBanner')) return;

        const bar = document.createElement('div');
        bar.id = 'projectClosedBanner';
        bar.className = 'mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800';
        bar.textContent = MESSAGE;
        root.prepend(bar);
    }

    function lockAll() {
        containers().forEach(lockContainer);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('project-closed');
        ensureBanner();
        lockAll();

        // Tabel & baris di section maupun modal dirender ulang oleh JS setelah
        // fetch, jadi penguncian harus diulang setiap DOM-nya berubah.
        let queued = false;
        new MutationObserver(function () {
            if (queued) return;
            queued = true;
            requestAnimationFrame(function () { queued = false; lockAll(); });
        }).observe(document.body, { childList: true, subtree: true });
    });
})();
</script>
@endpush
@endif
