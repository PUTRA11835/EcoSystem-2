{{-- Gaya isi panduan (.guide-body + kotak catatan). Dipakai bersama oleh halaman User Guide dan User Guide Editor, supaya tampilan sunting = tampilan baca. --}}
<style>
    .guide-body { --ug-accent: var(--primary-color, #991b1b); }
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
    /* Tangkapan layar: selebar kolom, tidak pernah melebihi layar */
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
</style>
