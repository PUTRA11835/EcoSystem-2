{{-- Template preview with sample values — the real PDF, so the letterhead sits on every page. No employee data is read. --}}
@extends('dashboard')
@section('title', $template->name)
@section('page-title', 'Contract')
@section('page-subtitle', 'Preview of “' . $template->name . '” with sample values')

@push('styles')
<style>
    .contract-frame { width: 100%; height: calc(100vh - 230px); min-height: 640px; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; }
</style>
@endpush

@section('content')
<div class="w-full space-y-4 px-1 lg:px-2">
    @include('hr-general.contracts.components.tabs')

    <div class="flex justify-end gap-2">
        <a href="{{ route('general.contracts.templates.index') }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i> Back to Templates</a>
        @if($canDo('general.contracts.templates', 'edit'))<a href="{{ route('general.contracts.templates.edit', $template->id) }}" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90"><i class="far fa-pen-to-square mr-1"></i> Edit</a>@endif
    </div>
    @if($template->use_letterhead && !$letterhead)
        <div class="text-xs bg-sky-50 border border-sky-200 text-sky-900 rounded-lg px-4 py-3"><i class="fas fa-circle-info mr-1"></i> No letterhead is set for “Employment Contract” yet, so the sample prints without one.</div>
    @endif
    <iframe class="contract-frame" src="{{ $pdfUrl }}#view=FitH" title="{{ $template->name }}"></iframe>
</div>
@endsection
