{{--
    Makes the indigo accents of a hub follow the user's Accent colour (Settings → Appearance), like the sidebar does.

    The Letter Templates and Contract pages were written with fixed Tailwind indigo for their active tab step, small badges,
    focus rings and links, so after the Accent colour was changed they stayed violet next to a teal (or any other) sidebar.
    Instead of rewriting every class, this remaps the indigo utilities to the theme variables `--primary-rgb` (declared in the
    layout's :root, see docs/updated-file/07-KONVENSI-UI.md). Green / red / amber keep their meaning (success / danger / warning).

    Include once per page (guarded with @once); it only has an effect on pages that include it.
--}}
@once
@push('styles')
<style>
    /* surfaces */
    .bg-indigo-50, .bg-indigo-50\/40 { background-color: rgba(var(--primary-rgb), .08) !important; }
    .bg-indigo-100 { background-color: rgba(var(--primary-rgb), .14) !important; }
    .bg-indigo-200 { background-color: rgba(var(--primary-rgb), .22) !important; }
    .hover\:bg-indigo-50:hover { background-color: rgba(var(--primary-rgb), .08) !important; }
    .hover\:bg-indigo-100:hover { background-color: rgba(var(--primary-rgb), .14) !important; }
    .hover\:bg-indigo-200:hover { background-color: rgba(var(--primary-rgb), .22) !important; }
    /* text */
    .text-indigo-500, .text-indigo-600, .text-indigo-700 { color: rgb(var(--primary-rgb)) !important; }
    .text-indigo-800, .text-indigo-900 { color: rgb(var(--primary-dark-rgb)) !important; }
    .hover\:text-indigo-700:hover, .hover\:text-indigo-800:hover { color: rgb(var(--primary-dark-rgb)) !important; }
    .peer:checked ~ .peer-checked\:text-indigo-700, .peer:checked ~ .peer-checked\:text-indigo-600 { color: rgb(var(--primary-rgb)) !important; }
    /* borders and rings */
    .border-indigo-100, .border-indigo-200, .border-indigo-300 { border-color: rgba(var(--primary-rgb), .30) !important; }
    .hover\:border-indigo-200:hover, .hover\:border-indigo-300:hover { border-color: rgba(var(--primary-rgb), .45) !important; }
    .ring-indigo-200, .ring-indigo-300 { --tw-ring-color: rgba(var(--primary-rgb), .35) !important; }
    .focus\:ring-indigo-200:focus, .focus-visible\:ring-indigo-200:focus-visible,
    .peer:focus-visible ~ .peer-focus-visible\:ring-indigo-200 { --tw-ring-color: rgba(var(--primary-rgb), .35) !important; }
    .focus\:border-indigo-300:focus, .focus\:border-indigo-400:focus { border-color: rgba(var(--primary-rgb), .60) !important; }
    /* tints of the Accent colour for small badges (PKWT / PKWTT) and segmented action buttons */
    .tone-primary { background-color: rgba(var(--primary-rgb), .12) !important; color: rgb(var(--primary-rgb)) !important; }
    .tone-primary-strong { background-color: rgba(var(--primary-rgb), .22) !important; color: rgb(var(--primary-dark-rgb)) !important; }
    .seg { display: inline-flex; border: 1px solid #d1d5db; border-radius: .5rem; overflow: hidden; background: #fff; }
    .seg > a, .seg > button { padding: .35rem .7rem; font-size: 11px; font-weight: 600; line-height: 1.2; border-left: 1px solid #d1d5db; transition: background-color .15s; white-space: nowrap; }
    .seg > :first-child { border-left: 0; }
    .seg .seg-view { color: #374151; } .seg .seg-view:hover { background: #f3f4f6; }
    .seg .seg-print { color: rgb(var(--primary-rgb)); } .seg .seg-print:hover { background: rgba(var(--primary-rgb), .08); }
    .seg .seg-edit { color: #b45309; } .seg .seg-edit:hover { background: #fffbeb; }
    .seg .seg-manage { color: #fff; background: var(--primary-surface); border-left-color: transparent; } .seg .seg-manage:hover { opacity: .9; }
    /* checkboxes and radios */
    input[type="checkbox"].text-indigo-600, input[type="radio"].text-indigo-600 { accent-color: rgb(var(--primary-rgb)); }
</style>
@endpush
@endonce
