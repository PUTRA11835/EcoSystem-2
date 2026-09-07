{{-- Pagination for the Team & Leads table — mirrors the KPI Evaluation dashboard
     coverage table, but the page links reload via AJAX (.tpg[data-page]). --}}
@if($employees->total() > 0)
<div class="px-5 py-4 border-t border-gray-100 bg-white flex flex-col sm:flex-row items-center justify-between gap-4">
    {{-- Left: Pagination Navigation & Results Counter --}}
    <div class="flex items-center gap-1.5 flex-wrap">
        {{-- Previous Page Button --}}
        @if($employees->onFirstPage())
            <span class="w-8 h-8 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 flex items-center justify-center text-xs cursor-not-allowed shadow-none">
                <i class="fas fa-chevron-left text-[10px]"></i>
            </span>
        @else
            <button type="button" data-page="{{ $employees->currentPage() - 1 }}"
                class="tpg w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 flex items-center justify-center text-xs shadow-sm transition-all">
                <i class="fas fa-chevron-left text-[10px]"></i>
            </button>
        @endif

        {{-- Page Numbers --}}
        @php
            $current = $employees->currentPage();
            $last    = $employees->lastPage();
            $start   = max(1, $current - 2);
            $end     = min($last, $current + 2);
            if ($end - $start < 4) {
                if ($start === 1) {
                    $end = min($last, $start + 4);
                } elseif ($end === $last) {
                    $start = max(1, $end - 4);
                }
            }
        @endphp

        @if($start > 1)
            <button type="button" data-page="1"
                class="tpg w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all">1</button>
            @if($start > 2)
                <span class="w-5 text-center text-gray-400 text-xs">...</span>
            @endif
        @endif

        @for($p = $start; $p <= $end; $p++)
            @if($p == $current)
                <span class="w-8 h-8 rounded-lg primary-surface text-white font-bold flex items-center justify-center text-xs shadow-sm"
                      style="background: var(--primary-surface, var(--primary-color)) !important;">{{ $p }}</span>
            @else
                <button type="button" data-page="{{ $p }}"
                    class="tpg w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all">{{ $p }}</button>
            @endif
        @endfor

        @if($end < $last)
            @if($end < $last - 1)
                <span class="w-5 text-center text-gray-400 text-xs">...</span>
            @endif
            <button type="button" data-page="{{ $last }}"
                class="tpg w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all">{{ $last }}</button>
        @endif

        {{-- Next Page Button --}}
        @if($employees->hasMorePages())
            <button type="button" data-page="{{ $employees->currentPage() + 1 }}"
                class="tpg w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 flex items-center justify-center text-xs shadow-sm transition-all">
                <i class="fas fa-chevron-right text-[10px]"></i>
            </button>
        @else
            <span class="w-8 h-8 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 flex items-center justify-center text-xs cursor-not-allowed shadow-none">
                <i class="fas fa-chevron-right text-[10px]"></i>
            </span>
        @endif

        {{-- Showing Results Counter --}}
        <span class="text-xs text-gray-500 ml-3 font-normal whitespace-nowrap">
            Showing {{ $employees->firstItem() ?? 0 }} to {{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }} results
        </span>
    </div>

    {{-- Right: Rows per page selector --}}
    <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500 font-normal">Rows per page:</span>
        <div class="relative">
            <select onchange="changeTeamPerPage(this.value)"
                class="appearance-none bg-white border border-gray-200 rounded-lg pl-3 pr-7 py-1.5 text-xs font-medium text-gray-700 hover:border-gray-300 focus:outline-none focus:ring-1 focus:ring-[var(--primary-color)] cursor-pointer shadow-sm transition-all">
                @foreach([10, 15, 25, 50] as $pp)
                <option value="{{ $pp }}" {{ (int) ($perPage ?? 15) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 text-[10px]">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>
</div>
@endif
