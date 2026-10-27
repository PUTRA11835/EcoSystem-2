@extends('dashboard')
@section('title', $panel['title'] . ' — Settings')
@section('page-title', $panel['title'])
@section('page-subtitle', $panel['about'])

{{--
    One tab of Management → Employee: the dropdown lists and rules of that section of the employee profile (see components/settings-panel),
    with the mini sidebar beside it. The employee's own records are on Master → Employee.
    The Contract tab is different: it holds what an employee sees on their own Contract tab; contracts themselves are HR & General → Contract.
--}}
@section('content')
<div class="w-full px-1 lg:px-2 lg:flex lg:gap-6 items-start space-y-4 lg:space-y-0">
    @include('management.employee.components.mini-sidebar')

    <div class="flex-1 min-w-0 space-y-5">
        @include('hr-general.recruitment.components.form-errors')

        @if($panel['section'] === 'contract')
            @php
                $rule = fn (string $key) => \App\Services\Management\EmployeeMasterData::rule('contract', $key);
                $columns = [
                    ['Contract', true], ['Type', true], ['Position', true], ['Period', true],
                    ['Salary', $rule('show_salary')], ['Status', true], ['Signing', true],
                ];
            @endphp
            {{-- What the employee's table looks like with the saved settings --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-gray-900"><i class="fas fa-eye text-gray-400 mr-1.5"></i> Employee's view now</h3>
                    <span class="text-xs text-gray-500">
                        {{ $rule('show_past_contracts') ? 'All contracts' : 'Current & upcoming only' }}
                        · number {{ $rule('show_contract_number') ? 'shown' : 'hidden' }}
                        · “ending soon” within {{ $rule('ending_soon_days') }} days
                    </span>
                </div>
                <div class="flex flex-wrap gap-1.5 mt-3">
                    @foreach($columns as [$name, $shown])
                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $shown ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400 line-through' }}">{{ $name }}</span>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-2">Signing always stays: it is where an employee signs a contract waiting for them.</p>
            </div>
        @endif

        @include('management.employee.components.settings-panel')

        @if($panel['section'] === 'contract')
            {{-- Everything else about contracts is HR & General → Contract: this page does not repeat it. --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900">Managed in HR &amp; General → Contract</h3>
                <p class="text-xs text-gray-500 mt-0.5">Not repeated here.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-3">
                    @foreach([
                        ['general.contracts.list', 'general.contracts.list', 'fa-file-signature', 'Dashboard & all contracts', 'Who is ending soon, create, renew, sign'],
                        ['general.contracts.settings', 'general.contracts.settings.index', 'fa-list-check', 'Types & numbering', 'Contract types, position types'],
                        ['general.contracts.templates', 'general.contracts.templates.index', 'fa-file-contract', 'Templates', 'Wording of each contract'],
                    ] as [$slug, $route, $icon, $label, $hint])
                        @if($can($slug))
                            <a href="{{ route($route) }}" class="flex items-start gap-3 rounded-lg border border-gray-200 px-3.5 py-3 hover:bg-gray-50 hover:border-gray-300">
                                <i class="fas {{ $icon }} mt-0.5 text-gray-400"></i>
                                <span><span class="block text-sm font-semibold text-gray-800">{{ $label }}</span><span class="block text-xs text-gray-500">{{ $hint }}</span></span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@include('hr-general.recruitment.components.confirm-forms')
@endsection
