{{--
    Makes the "brand red" of the Master → Employee pages follow the user's Accent colour (Settings → Appearance), like the sidebar.

    These pages (employee list, employee detail, My Profile — and every section tab inside them) were written with fixed Tailwind
    `red-800` for their active tab, primary buttons (Save Changes, Add…), focus rings, checkboxes and avatar, so after the Accent colour
    was changed they stayed red next to a teal (or any other) sidebar. Instead of rewriting ~250 class names across 14 files (which would
    collide with the colleagues' edits to these same files), the BRAND shades are remapped here to the theme variables `--primary-rgb`
    / `--primary-dark-rgb` (see docs/updated-file/07-KONVENSI-UI.md).

    Deliberately NOT remapped, because they mean something: red-600 / red-700 (errors, required marks, Delete buttons), red-300,
    red-100 badges (Expired / Failed) and `hover:bg-red-50` on outline-danger buttons keep their red.

    Scoped to `body.emp-accent`, which this partial sets, so no other page is affected. Include once per page.
--}}
@once
@push('styles')
<style>
    /* solid brand surfaces: primary buttons, active-tab underline, hover of the close button */
    body.emp-accent .bg-red-800,
    body.emp-accent .hover\:bg-red-800:hover { background-color: rgb(var(--primary-rgb)) !important; }
    body.emp-accent .hover\:bg-red-900:hover { background-color: rgb(var(--primary-dark-rgb)) !important; }
    /* soft tint behind the active tab */
    body.emp-accent .bg-red-50\/60 { background-color: rgba(var(--primary-rgb), .08) !important; }
    /* text: active tab, links, labels, checkboxes (forms plugin colours the check with currentColor); danger badges `bg-red-100 text-red-800` excluded */
    body.emp-accent .text-red-800:not(.bg-red-100),
    body.emp-accent .hover\:text-red-800:hover { color: rgb(var(--primary-rgb)) !important; }
    body.emp-accent .hover\:text-red-900:hover { color: rgb(var(--primary-dark-rgb)) !important; }
    /* borders */
    body.emp-accent .border-red-800,
    body.emp-accent .hover\:border-red-800:hover { border-color: rgb(var(--primary-rgb)) !important; }
    /* focus ring of inputs / selects */
    body.emp-accent .focus\:ring-red-800:focus { --tw-ring-color: rgb(var(--primary-rgb)) !important; }
    /* initials avatar: same surface as the sidebar (gradient or solid, per Settings → Sidebar style) */
    body.emp-accent .emp-avatar { background: var(--primary-surface) !important; }
</style>
@endpush
<script>document.body.classList.add('emp-accent');</script>
@endonce
