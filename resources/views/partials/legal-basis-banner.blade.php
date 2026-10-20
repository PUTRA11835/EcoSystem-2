{{--
    Spanduk "Legal basis" untuk halaman KERJA sebuah modul (mis. periode Payroll, detail Offboarding): muncul bila saklar "Warn" modul menyala
    dan ada aturan wajib yang belum Reviewed. Parameter: $legalModule (pph21 | bpjs | overtime | contract | offboarding), boleh array modul.
--}}
@php
    $legalSvc = app(\App\Services\Legal\LegalBasisService::class);
    $legalPending = [];
    $legalEnforced = false;
    foreach ((array) $legalModule as $legalM) {
        $legalSw = $legalSvc->switches($legalM);
        if ($legalSw->warn_banner || $legalSw->enforce) {
            $legalPending = array_merge($legalPending, $legalSvc->pending($legalM));
            $legalEnforced = $legalEnforced || (bool) $legalSw->enforce;
        }
    }
@endphp
@if($legalPending)
    <div class="text-xs rounded-lg px-4 py-3 flex items-start gap-2 border {{ $legalEnforced ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
        <i class="fas fa-scale-balanced mt-0.5"></i>
        <div>
            <span class="font-semibold">{{ $legalEnforced ? 'Legal basis is enforced for this feature.' : 'Legal basis not fully reviewed.' }}</span>
            {{ count($legalPending) }} rule{{ count($legalPending) > 1 ? 's' : '' }} still need{{ count($legalPending) > 1 ? '' : 's' }} review:
            <span class="font-mono text-[11px]">{{ implode(', ', array_slice($legalPending, 0, 8)) }}{{ count($legalPending) > 8 ? ' …' : '' }}</span>.
            {{ $legalEnforced ? 'This feature will not run until they are marked Reviewed.' : 'Figures below follow the current settings; they have not been confirmed against the official regulation.' }}
            @if(($canDo ?? null) && $canDo('finance.legal-basis'))<a href="{{ route('finance.legal-basis.index') }}#basis-register" class="underline font-semibold ml-1">Open Legal Basis</a>@endif
        </div>
    </div>
@endif
