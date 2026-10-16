@extends('dashboard')
@section('title', 'Contract — Dashboard')
@section('page-title', 'Contract')
@section('page-subtitle', 'Whose contract ends soon, and where contracts are missing or have lapsed — internal employees.')

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.contracts.components.tabs')

    @include('hr-general.contracts.components.dashboard', [
        'dash' => $dash, 'dashUrl' => route('general.contracts.dashboard'), 'listUrl' => route('general.contracts.list'),
    ])
</div>
@endsection
