{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- IZIN PER SECTION (Menu Access) — dipakai Delivery Project & Delivery Support --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--
    Konvensi slug: <modul>.<section>.<aksi>

      .view    → section tampil. Section tanpa izin ini TIDAK dirender sama
                 sekali (dibungkus @if di view-nya masing-masing).
      .edit    → boleh mengubah data yang sudah ada.
      .manage  → boleh menambah & menghapus data.

    Beberapa section memecah hapus jadi aksi sendiri karena role yang boleh
    menghapus tidak sama dengan yang boleh menambah:

      .manage  → boleh menambah (label menu jadi "Create")
      .delete  → boleh menghapus  (mis. `delivery-project.team.delete`)

    Section seperti itu menulis `data-perm-delete` juga. Skrip di bawah TIDAK
    memakainya (tombol hapusnya ada di toolbar mengambang, di luar <section>) —
    atributnya penanda supaya izinnya terbaca dari markup; pemagarannya ada di
    view masing-masing lewat @if($can(...)) / flag JS.

    Tiap <section> menandai izinnya lewat atribut:

      <section id="team"
               data-perm-edit="{{ '{{' }} $can('delivery-project.team.edit') ? '1' : '0' }}"
               data-perm-manage="{{ '{{' }} $can('delivery-project.team.manage') ? '1' : '0' }}">

    `data-perm-manage` boleh dihilangkan untuk section yang memang tidak punya
    aksi tambah/hapus (General, Location, Approval, ...) — nilainya ikut
    `data-perm-edit`.

    Ini murni lapisan UI. Penegakan sebenarnya ada di middleware `menu:` pada
    route tulis masing-masing section (routes/web.php & routes/delivery-support.php).
--}}
@push('styles')
<style>
.perm-readonly input:not([type="hidden"]),
.perm-readonly select,
.perm-readonly textarea {
    background-color: #f9fafb !important;
    color: #6b7280 !important;
    cursor: not-allowed !important;
    pointer-events: none !important;
}
.perm-hidden { display: none !important; }
.perm-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.15rem 0.55rem; margin-left: 0.6rem;
    border-radius: 9999px; background: #f3f4f6; color: #6b7280;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
    vertical-align: middle;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    // Tombol dipilah jadi dua kelompok supaya izin `.edit` dan `.manage` bisa
    // dicabut terpisah. Tombol di luar keduanya (collapse, tab, export, pindah
    // view, refresh) sengaja dibiarkan agar section tetap bisa dibaca penuh.
    const MANAGE_LABEL = /\b(add|new|create|delete|remove|upload|import|generate|assign|unassign)\b/i;
    const EDIT_LABEL   = /\b(save|update|edit|change|submit|confirm|apply|sync)\b/i;

    function labelOf(el) {
        return (el.textContent || '') + ' ' +
               (el.getAttribute('title') || '') + ' ' +
               (el.getAttribute('aria-label') || '');
    }

    /** 'manage' | 'edit' | null — kategori aksi tulis sebuah tombol. */
    function actionKind(el) {
        if (el.hasAttribute('data-perm-keep')) return null;   // opt-out manual
        const forced = el.getAttribute('data-perm-action');   // opt-in manual
        if (forced === 'manage' || forced === 'edit') return forced;

        const label = labelOf(el);
        if (MANAGE_LABEL.test(label)) return 'manage';
        if (EDIT_LABEL.test(label))   return 'edit';
        if (el.type === 'submit')     return 'edit';
        return null;
    }

    function lockSection(section, canEdit, canManage) {
        if (!canEdit) {
            section.querySelectorAll('input, select, textarea').forEach(function (f) {
                if (f.type === 'hidden') return;
                f.disabled = true;
            });
        }

        section.querySelectorAll('button, a[role="button"], input[type="submit"]').forEach(function (b) {
            const kind = actionKind(b);
            if (!kind) return;
            if (kind === 'manage' ? canManage : canEdit) return;
            b.classList.add('perm-hidden');
            b.disabled = true;
        });

        // Form hanya diblokir kalau BENAR-BENAR tidak boleh menulis apa pun —
        // section yang boleh manage tapi tidak boleh edit tetap perlu submit.
        if (!canEdit && !canManage) {
            section.querySelectorAll('form').forEach(function (f) {
                if (f.dataset.permBlocked) return;   // lockSection dipanggil ulang oleh observer
                f.dataset.permBlocked = '1';
                f.addEventListener('submit', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }, true);
            });
        }
    }

    function badge(section, canEdit, canManage) {
        const heading = section.querySelector('h2');
        if (!heading || heading.querySelector('.perm-badge')) return;

        let text;
        if (!canEdit && !canManage)      text = 'Read only';
        else if (!canEdit)               text = 'Add / delete only';
        else if (!canManage)             text = 'Edit only';
        else return;

        const span = document.createElement('span');
        span.className = 'perm-badge';
        span.title = 'Your role has limited permission on this section';
        span.textContent = text;
        heading.appendChild(span);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('section[data-perm-edit]').forEach(function (section) {
            const canEdit = section.dataset.permEdit === '1';
            // Section tanpa aksi tambah/hapus tidak menulis data-perm-manage —
            // izinnya ikut .edit.
            const canManage = section.dataset.permManage === undefined
                ? canEdit
                : section.dataset.permManage === '1';

            if (canEdit && canManage) return;   // izin penuh, tidak perlu dikunci

            if (!canEdit) section.classList.add('perm-readonly');
            badge(section, canEdit, canManage);
            lockSection(section, canEdit, canManage);

            // Sebagian besar tabel section ini dirender ulang oleh JS setelah data
            // di-fetch, jadi penguncian harus diulang tiap kali DOM-nya berubah.
            new MutationObserver(function () { lockSection(section, canEdit, canManage); })
                .observe(section, { childList: true, subtree: true });
        });
    });
})();
</script>
@endpush
