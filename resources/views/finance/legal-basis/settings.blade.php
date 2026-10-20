@extends('dashboard')
@section('title', 'Legal Basis — Settings')
@section('page-title', 'Legal Basis')
@section('page-subtitle', 'How each module uses the legal-basis register: show information, run in Draft mode, or enforce review.')

@php
    $canEdit = $canDo('finance.legal-basis.settings', 'edit');
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')
    @include('finance.legal-basis.components.tabs')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif

    {{-- Saklar penerapan per modul --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-start gap-3">
            <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="fas fa-sliders"></i></span>
            <div><h3 class="text-sm font-bold text-gray-800">Where the legal basis applies</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Switch how each module uses this register. <strong>Show panel</strong> and <strong>Warn</strong> are informational. <strong>Mode</strong>: <em>Draft mode</em> lets the module run now while its rules are not reviewed — every result is flagged <strong>DRAFT</strong>; <em>Enforced</em> makes the module refuse to run (Payroll: switch on / calculate a period; Offboarding: the settlement calculator) while any of its required rules is not marked Reviewed. Leave Enforce off to keep a module behaving exactly as before.</p></div>
        </div>
        <form method="POST" action="{{ route('finance.legal-basis.switches') }}">
            @csrf
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-start gap-3 bg-gray-50/60">
                <input type="hidden" name="legal_display" value="0">
                <label class="flex items-start gap-3 text-sm text-gray-700"><input type="checkbox" name="legal_display" value="1" class="w-4 h-4 mt-0.5 rounded border-gray-300" {{ app(\App\Services\Legal\LegalBasisService::class)->displayEnabled() ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                    <span><span class="font-semibold text-gray-900">Show legal basis information across the app</span>
                        <span class="block text-xs text-gray-500 mt-0.5">When off, the “Legal basis” panels, the amber “not reviewed” warnings and the DRAFT labels are hidden everywhere. The register itself stays here, and a module set to <em>Enforced</em> still refuses to run until its rules are Reviewed.</span></span></label>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500"><tr><th class="px-4 py-3">Module</th><th class="px-4 py-3">Affects</th><th class="px-4 py-3 text-center">Show panel</th><th class="px-4 py-3 text-center">Warn</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Rules not reviewed</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @php $affects = ['pph21' => 'Payroll → PPh 21 settings, switching Payroll on, calculating a period', 'bpjs' => 'Payroll → BPJS settings, switching Payroll on, calculating a period', 'overtime' => 'Payroll → overtime rates, switching Payroll on, calculating a period', 'contract' => 'Contract (PKWT term) — information only', 'offboarding' => 'Offboarding → Final Settlement calculator']; @endphp
                        @foreach($switches as $m => $sw)
                            <tr class="align-top">
                                <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">{{ $modules[$m] ?? $m }}</td>
                                <td class="px-4 py-3 text-[11px] text-gray-500 max-w-xs">{{ $affects[$m] ?? '' }}</td>
                                @foreach(['show_panel', 'warn_banner'] as $f)
                                    <td class="px-4 py-3 text-center"><input type="checkbox" name="sw[{{ $m }}][{{ $f }}]" value="1" class="w-4 h-4 rounded border-gray-300" {{ $sw->$f ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}></td>
                                @endforeach
                                <td class="px-4 py-3">
                                    <select name="sw[{{ $m }}][enforce]" {{ $canEdit && $m !== 'contract' ? '' : 'disabled' }} class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white" @if($m === 'contract') title="Contract has no feature to enforce yet" @endif>
                                        <option value="" @selected(!$sw->enforce)>Draft mode — runs now, flagged DRAFT</option>
                                        <option value="1" @selected($sw->enforce)>Enforced — blocked until Reviewed</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-[11px] {{ count($pending[$m] ?? []) ? 'text-amber-700' : 'text-green-700' }}">
                                    {{ count($pending[$m] ?? []) ? implode(', ', $pending[$m]) : 'All reviewed' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($canEdit)<div class="px-5 py-3 border-t border-gray-100 flex justify-end"><button class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm">Save switches</button></div>@endif
        </form>
    </div>

    <p class="text-xs text-gray-500">To accept the review of a module’s rules, open the <a href="{{ route('finance.legal-basis.index') }}" class="underline font-semibold">Register</a> tab and use “Accept review (all)” on that module (needs the Register “Edit” permission).</p>
</div>
@endsection
