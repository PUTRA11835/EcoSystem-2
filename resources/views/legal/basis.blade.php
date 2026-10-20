@extends('dashboard')
@section('title', 'Legal Basis')
@section('page-title', 'Legal')
@section('page-subtitle', 'The laws and regulations behind payroll, BPJS, overtime, contract and offboarding rules — with the date each was last amended.')

@section('content')
@php
    $navItems = [
        ['id' => 'basis-note', 'label' => 'Before you rely on a rule', 'icon' => 'fa-triangle-exclamation'],
        ['id' => 'basis-summary', 'label' => 'Review status', 'icon' => 'fa-chart-simple'],
        ['id' => 'basis-switches', 'label' => 'Where it applies', 'icon' => 'fa-sliders'],
        ['id' => 'basis-register', 'label' => 'Register', 'icon' => 'fa-book'],
    ];
    foreach ($basis['rows'] as $mod => $list) {
        $navItems[] = ['id' => 'reg-' . $mod, 'label' => $basis['modules'][$mod] ?? $mod, 'icon' => 'fa-scale-balanced'];
    }
@endphp

<div class="w-full px-1 lg:px-2">
    @include('legal.components.tabs')

    <div class="lg:grid lg:grid-cols-[14.5rem_minmax(0,1fr)] lg:gap-6 items-start space-y-4 lg:space-y-0">
        @include('partials.page-nav', ['navLabel' => 'Legal Basis', 'navItems' => $navItems])

        <div class="space-y-6 min-w-0">
            @include('legal.components.legal-basis', ['basis' => $basis])
        </div>
    </div>
</div>
@endsection
