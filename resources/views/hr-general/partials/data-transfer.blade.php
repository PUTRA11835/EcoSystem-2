{{--
    Excel export / import untuk satu atau beberapa tabel, sebagai SATU tombol
    "Excel" (menu: Export / Import / Template) yang ditaruh DI DALAM HALAMAN,
    di toolbar kartu tempat ia di-@include — sejajar tombol Tambah / Reset Filter,
    bukan di navbar layout. Kebiasaan yang sama dengan Reimbursement & Cash Advance.

    Variabel: $dataset — kunci di App\Support\DataTransfer\Registry, atau
              array kunci (menu dropdown diberi judul per tabel).
              $bare    — true bila pemanggil sudah menaruhnya di dalam baris toolbar
                         (default: dibungkus baris sendiri, rata kanan).
    Ekspor memakai query string halaman ini, jadi filter yang sedang aktif ikut.
    Letakkan di LUAR elemen `data-live-region` agar tidak ikut diganti saat filter.
--}}
@php
    $__defs = collect((array) $dataset)
        ->map(fn ($key) => \App\Support\DataTransfer\Registry::find($key))
        ->filter(fn ($def) => $def && (
            \App\Support\DataTransfer\Registry::canExport($def) || \App\Support\DataTransfer\Registry::canImport($def)
        ))
        ->values();
    $__many = $__defs->count() > 1;
    $__bare = !empty($bare);
@endphp

@if($__defs->isNotEmpty())
@unless($__bare)<div class="flex justify-end">@endunless
<div class="relative" data-excel-menu>
    <details class="group">
        <summary class="list-none cursor-pointer select-none inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition-colors whitespace-nowrap"
                 title="Export or import Excel">
            <i class="fas fa-file-excel text-green-600"></i>
            <span>Excel</span>
            <i class="fas fa-chevron-down text-[9px] transition-transform group-open:rotate-180"></i>
        </summary>

        {{-- `fixed` + positioned by script: the card around the toolbar clips an absolute menu. --}}
        <div data-excel-panel class="fixed w-72 max-h-[70vh] overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg z-50 py-1 text-sm">
            @foreach($__defs as $__def)
                @php
                    $__canExport = \App\Support\DataTransfer\Registry::canExport($__def);
                    $__canImport = \App\Support\DataTransfer\Registry::canImport($__def);
                    $__modalId   = 'dtModal-' . str_replace('.', '-', $__def->key);
                @endphp

                @if($__many)
                    <p class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-400 {{ $loop->first ? '' : 'border-t border-gray-100 mt-1' }}">
                        {{ $__def->title }}
                    </p>
                @endif

                @if($__canExport)
                <a href="{{ route('general.data.export', array_merge(request()->query(), ['dataset' => $__def->key])) }}"
                   class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-file-arrow-up w-4 text-green-600"></i> Export to Excel
                </a>
                @endif

                @if($__canImport)
                <button type="button" onclick="document.getElementById('{{ $__modalId }}').showModal()"
                        class="w-full flex items-center gap-3 px-4 py-2 text-left text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-file-import w-4 text-blue-600"></i> Import from Excel…
                </button>
                <a href="{{ route('general.data.template', $__def->key) }}"
                   class="flex items-center gap-3 px-4 py-2 text-gray-500 hover:bg-gray-50">
                    <i class="fas fa-file-arrow-down w-4"></i> Download import template
                </a>
                @endif
            @endforeach
        </div>
    </details>

    @foreach($__defs as $__def)
        @if(\App\Support\DataTransfer\Registry::canImport($__def))
        <dialog id="dtModal-{{ str_replace('.', '-', $__def->key) }}" class="rounded-xl p-0 w-full max-w-md backdrop:bg-black/40">
            <form method="POST" action="{{ route('general.data.import', $__def->key) }}" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Import {{ $__def->title }}</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Use the template, or a file exported from this page. A row whose key already exists is updated;
                        otherwise it is added. If any row has a problem, nothing is saved.
                    </p>
                </div>

                <input type="file" name="file" required accept=".xlsx,.xls,.csv"
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                <p class="text-xs text-gray-400">.xlsx, .xls or .csv — up to 5 MB and {{ \App\Support\DataTransfer\Importer::MAX_ROWS }} rows.</p>

                <div class="flex items-center justify-between gap-2 pt-2">
                    <a href="{{ route('general.data.template', $__def->key) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-800">
                        <i class="fas fa-file-arrow-down"></i> Download template
                    </a>
                    <div class="flex gap-2">
                        <button type="button" onclick="this.closest('dialog').close()"
                                class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90">
                            Import
                        </button>
                    </div>
                </div>
            </form>
        </dialog>
        @endif
    @endforeach

    @if(session('import_errors'))
    <dialog id="dtErrors" class="rounded-xl p-0 w-full max-w-lg backdrop:bg-black/40">
        <div class="p-6">
            <h3 class="text-lg font-bold text-red-800">Import problems — nothing was saved</h3>
            <p class="text-xs text-gray-500 mt-1">Fix these rows in your file and upload it again.</p>
            <ul class="mt-3 text-xs text-red-700 space-y-1 list-disc list-inside max-h-72 overflow-y-auto">
                @foreach(session('import_errors') as $__line)
                    <li>{{ $__line }}</li>
                @endforeach
            </ul>
            <div class="flex justify-end pt-4">
                <button type="button" onclick="this.closest('dialog').close()"
                        class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </dialog>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var dialog = document.getElementById('dtErrors');
            if (dialog && dialog.showModal) dialog.showModal();
        });
    </script>
    @endif

    <script>
        // One set of listeners for the page, however many menus it holds.
        if (!window.__dtMenuReady) {
            window.__dtMenuReady = true;
            const closeAll = (except) => document.querySelectorAll('[data-excel-menu] details[open]').forEach((d) => { if (d !== except) d.removeAttribute('open'); });
            document.addEventListener('toggle', (event) => {
                const details = event.target;
                if (!details.matches || !details.matches('[data-excel-menu] details') || !details.open) return;
                closeAll(details);
                const panel = details.querySelector('[data-excel-panel]');
                const box = details.querySelector('summary').getBoundingClientRect();
                panel.style.top = (box.bottom + 8) + 'px';
                panel.style.left = Math.max(8, box.right - panel.offsetWidth) + 'px';
            }, true);
            document.addEventListener('click', (event) => {
                document.querySelectorAll('[data-excel-menu] details[open]').forEach((d) => {
                    if (!d.contains(event.target) || event.target.closest('a, button')) d.removeAttribute('open');
                });
            });
            window.addEventListener('scroll', (event) => {
                if (event.target.closest && event.target.closest('[data-excel-panel]')) return;
                closeAll();
            }, true);
            window.addEventListener('resize', () => closeAll());
        }
    </script>
</div>
@unless($__bare)</div>@endunless
@endif
