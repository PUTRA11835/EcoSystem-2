{{--
    "Legal basis" panel for a Settings page: which regulation stands behind this module's rules, when each was last amended and whether a person
    has reviewed it. Read-only; the register is kept in Business & Legal → Legal Basis, where the "Show panel" switch hides this panel. Parameter: $legalModule (pph21 | bpjs | overtime | contract | offboarding).
--}}
@php
    $legalSummary = app(\App\Services\Legal\LegalBasisService::class)->summary($legalModule);
    $legalCan = $canDo('finance.legal-basis');
    $legalSw = app(\App\Services\Legal\LegalBasisService::class)->switches($legalModule);
@endphp
@if($legalSw->show_panel)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 {{ $legalSummary['rows']->isNotEmpty() ? 'border-b border-gray-100' : '' }}">
        <div class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="fas fa-scale-balanced"></i></span>
            <div><h3 class="text-sm font-bold text-gray-800">Legal basis</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">The regulations behind these rules, with the date each was last amended. {{ $legalSummary['total'] }} registered {{ "" }}@if($legalSummary['needs_review'] > 0) · <span class="text-amber-700 font-semibold">{{ $legalSummary['needs_review'] }} need review</span> @endif @if($legalSummary['stale'] > 0) · <span class="text-amber-700 font-semibold">{{ $legalSummary['stale'] }} last reviewed over 12 months ago</span> @endif.</p></div>
        </div>
        @if($legalCan)<a href="{{ route('finance.legal-basis.index', ['module' => $legalModule]) }}#basis-register" class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50">Manage legal basis</a>@endif
    </div>
    @if($legalSummary['needs_review'] > 0)
        <div class="px-5 py-2.5 text-[11px] bg-amber-50 border-b border-amber-100 text-amber-900"><i class="fas fa-triangle-exclamation mr-1"></i> Some entries were written from the preparer's knowledge and have not been checked against the official text yet. Treat them as a reference until a person marks them reviewed.</div>
    @endif
    @include('partials.legal-basis-table', ['rows' => $legalSummary['rows']])
</div>
@endif
