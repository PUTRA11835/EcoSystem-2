@extends('dashboard')
@section('title', 'Recruitment - Selection Process')
@section('page-title', 'Selection Process')
@section('page-subtitle', 'Every candidate in the selection process. Click a row to open the candidate.')

@php
    $canCreate = $canDo('general.recruitment.candidates', 'create');
    $filterForm = 'candidateFilters';
    $filter = 'hr-general.recruitment.components.header-filter';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.recruitment.components.hub-tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.recruitment.candidates.index') }}" data-filter-form class="hidden"></form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Candidates</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.</p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.recruitment.candidates.index') }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
                @if($canCreate)
                    <button type="button" onclick="openCandidateModal({ mode: 'new' })"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> Add Candidate
                    </button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                    <tr>
                        <th class="px-4 py-3 w-12">No.</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'name', 'label' => 'Candidate Name', 'type' => 'search',
                            'value' => $filters['name'], 'placeholder' => 'Type a name…', 'thClass' => 'min-w-40',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'position_id', 'label' => 'Position', 'type' => 'options',
                            'value' => $filters['position_id'], 'options' => $positions->pluck('name', 'id')->all(),
                            'allLabel' => 'All positions', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'job_opening_id', 'label' => 'Job Opening', 'type' => 'options',
                            'value' => $filters['job_opening_id'], 'options' => $filterJobs,
                            'allLabel' => 'All job openings', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'source_id', 'label' => 'Source', 'type' => 'options',
                            'value' => $filters['source_id'], 'options' => $filterSources,
                            'allLabel' => 'All sources', 'thClass' => 'min-w-28',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'contact', 'label' => 'Contact', 'type' => 'search',
                            'value' => $filters['contact'], 'placeholder' => 'Email or phone…', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'documents', 'label' => 'Attached Documents', 'type' => 'checkboxes',
                            'value' => $filters['documents'], 'options' => $filterDocTypes + ['none' => 'No documents attached'],
                            'exclusive' => 'none', 'note' => 'Shows candidates who have every ticked document.', 'thClass' => 'min-w-44',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statuses,
                            'allLabel' => 'All statuses', 'thClass' => 'min-w-28',
                        ])
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($candidates as $candidate)
                        <tr class="hover:bg-gray-50 cursor-pointer" data-href="{{ route('general.recruitment.candidates.show', $candidate) }}">
                            <td class="px-4 py-3 text-gray-400">{{ $candidates->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-800">
                                <a href="{{ route('general.recruitment.candidates.show', $candidate) }}" class="hover:underline">{{ $candidate->name }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $candidate->positionLabel() }}</td>
                            <td class="px-4 py-3">
                                @if($candidate->jobOpening)
                                    {{ $candidate->jobOpening->position_title }}
                                    <span class="block text-[10px] text-gray-400 font-mono">{{ $candidate->jobOpening->request_number }}</span>
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{!! $candidate->source ? e($candidate->source->name) : '<span class="text-gray-300">-</span>' !!}</td>
                            <td class="px-4 py-3 text-gray-500">
                                @if($candidate->email || $candidate->phone)
                                    {{ $candidate->email }}
                                    @if($candidate->email && $candidate->phone)<br>@endif
                                    {{ $candidate->phone }}
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @forelse($candidate->documents as $document)
                                    <a href="{{ route('general.recruitment.candidates.documents.download', [$candidate, $document]) }}" target="_blank" rel="noopener"
                                        title="{{ $document->original_name }}"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 mr-1 mb-1 rounded-full bg-gray-100 text-gray-700 text-[10px] font-semibold hover:bg-gray-200">
                                        <i class="fas fa-{{ $document->isLink() ? 'link' : 'paperclip' }} text-[9px]"></i> {{ $document->typeLabel() }}
                                    </a>
                                @empty
                                    <span class="text-gray-300">-</span>
                                @endforelse
                                @php $missingDocuments = $candidate->missingRequiredDocuments(); @endphp
                                @if($missingDocuments->isNotEmpty())
                                    <span class="block text-[10px] font-semibold text-red-600" title="Required by the job opening and not attached yet">
                                        <i class="fas fa-circle-exclamation"></i> Missing: {{ $missingDocuments->pluck('name')->implode(', ') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ $candidate->statusBadge() }}">{{ $candidate->statusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-gray-400">
                                {{ $hasFilters ? 'No candidates match these filters.' : 'No candidates yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $candidates, 'form' => $filterForm])
        </div>
    </div>
</div>

@if($canCreate)
    @include('hr-general.recruitment.components.candidate-modal')
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Whole row opens the candidate, except clicks on links inside it.
        // Delegated: the rows are re-rendered whenever a filter is typed.
        document.addEventListener('click', event => {
            const row = event.target.closest('tr[data-href]');
            if (row && !event.target.closest('a, button')) window.location = row.dataset.href;
        });

        @if($canCreate && request()->boolean('add'))
            openCandidateModal({ mode: 'new' });
        @endif
    });
</script>
@endpush
