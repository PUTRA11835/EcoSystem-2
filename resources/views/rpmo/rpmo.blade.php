@extends('dashboard')
@section('title', 'RPMO')
@section('page-title', 'RPMO')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">RPMO</h2>
            <p class="text-sm text-gray-500 mt-0.5">Resource & Project Management Office</p>
        </div>
    </div>

    <div class="py-16 text-center">
        <i class="fas fa-cogs text-4xl text-gray-200 mb-4 block"></i>
        <p class="text-gray-500 font-semibold">RPMO module is under construction.</p>
        <p class="text-gray-400 text-sm mt-1">Available features are accessible via sub-menus.</p>
        <a href="{{ route('rpmo.periods.index') }}"
           class="mt-5 inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            <i class="fas fa-calendar-alt text-xs"></i>
            Go to Period Management
        </a>
    </div>

</div>
@endsection
