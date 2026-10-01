{{--
    Numbered pagination footer — the same look as the KPI Evaluation table,
    as one component for every Recruitment list.

    Parameters:
      $paginator      — LengthAwarePaginator (->withQueryString() already applied)
      $form           — id of the page's GET filter form; changing "Rows per
                        page" submits it, which also returns to page 1
      $perPage        — current rows per page
      $perPageOptions — int[]
--}}
@if($paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last    = $paginator->lastPage();
        $start   = max(1, $current - 2);
        $end     = min($last, $current + 2);
        if ($end - $start < 4) {
            if ($start === 1) {
                $end = min($last, $start + 4);
            } elseif ($end === $last) {
                $start = max(1, $end - 4);
            }
        }

        $pageLink = 'w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-semibold flex items-center justify-center text-xs shadow-sm transition-all';
        $arrowLink = 'w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 flex items-center justify-center text-xs shadow-sm transition-all';
        $arrowOff = 'w-8 h-8 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 flex items-center justify-center text-xs cursor-not-allowed shadow-none';
    @endphp
    <div class="px-5 py-4 border-t border-gray-100 bg-white flex flex-col sm:flex-row items-center justify-between gap-4">
        <nav class="flex items-center gap-1.5 flex-wrap" aria-label="Pagination">
            @if($paginator->onFirstPage())
                <span class="{{ $arrowOff }}"><i class="fas fa-chevron-left text-[10px]"></i></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="{{ $arrowLink }}" aria-label="Previous page"><i class="fas fa-chevron-left text-[10px]"></i></a>
            @endif

            @if($start > 1)
                <a href="{{ $paginator->url(1) }}" class="{{ $pageLink }}">1</a>
                @if($start > 2)
                    <span class="w-5 text-center text-gray-400 text-xs">...</span>
                @endif
            @endif

            @for($p = $start; $p <= $end; $p++)
                @if($p === $current)
                    <span class="w-8 h-8 rounded-lg primary-surface text-white font-bold flex items-center justify-center text-xs shadow-sm" aria-current="page"
                        style="background: var(--primary-surface, var(--primary-color)) !important;">{{ $p }}</span>
                @else
                    <a href="{{ $paginator->url($p) }}" class="{{ $pageLink }}">{{ $p }}</a>
                @endif
            @endfor

            @if($end < $last)
                @if($end < $last - 1)
                    <span class="w-5 text-center text-gray-400 text-xs">...</span>
                @endif
                <a href="{{ $paginator->url($last) }}" class="{{ $pageLink }}">{{ $last }}</a>
            @endif

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="{{ $arrowLink }}" aria-label="Next page"><i class="fas fa-chevron-right text-[10px]"></i></a>
            @else
                <span class="{{ $arrowOff }}"><i class="fas fa-chevron-right text-[10px]"></i></span>
            @endif

            <span class="text-xs text-gray-500 ml-3 font-normal whitespace-nowrap">
                Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} results
            </span>
        </nav>

        <div class="flex items-center gap-2">
            <label for="{{ $form }}-per-page" class="text-xs text-gray-500 font-normal whitespace-nowrap">Rows per page:</label>
            {{-- A plain <select>: the global enhancer (select-enhance.js) turns it into the app's dropdown. --}}
            <div class="w-20">
                <select name="per_page" id="{{ $form }}-per-page" form="{{ $form }}" onchange="this.form.requestSubmit()">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
@endif
