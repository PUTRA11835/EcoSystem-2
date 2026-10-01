@extends('dashboard')
@section('title', 'Recruitment - Job Openings')
@section('page-title', 'Job Openings')
@section('page-subtitle', 'One place for every job posting HR has published, on any platform.')

@php
    // Capabilities of the Job Openings tab, as ticked in Management → Roles.
    $canCreate = $canDo('general.recruitment.jobs', 'create');
    $canEdit   = $canDo('general.recruitment.jobs', 'edit');
    $canDelete = $canDo('general.recruitment.jobs', 'delete');

    $filterForm = 'jobFilters';
    $filter = 'hr-general.recruitment.components.header-filter';
    $action = 'hr-general.recruitment.components.icon-action';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.recruitment.components.hub-tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.recruitment.jobs.index') }}" data-filter-form class="hidden"></form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Job Postings</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.</p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.recruitment.jobs.index') }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
                @if($canCreate)
                    <a href="{{ route('general.recruitment.jobs.create') }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> New Job Opening
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                    <tr>
                        <th class="px-4 py-3 w-12">No.</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'title', 'label' => 'Job Title', 'type' => 'search',
                            'value' => $filters['title'], 'placeholder' => 'Title or request no.…', 'thClass' => 'min-w-48',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'platform_id', 'label' => 'Platform', 'type' => 'options',
                            'value' => $filters['platform_id'], 'options' => $platforms->pluck('name', 'id')->all(),
                            'allLabel' => 'All platforms', 'thClass' => 'min-w-36',
                        ])
                        <th class="px-4 py-3">Posting Period</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statuses, 'allLabel' => 'All statuses', 'thClass' => 'min-w-28',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'employment_type_id', 'label' => 'Employee Type', 'type' => 'options',
                            'value' => $filters['employment_type_id'], 'options' => $employmentTypes->pluck('name', 'id')->all(),
                            'allLabel' => 'All types', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'location', 'label' => 'Location', 'type' => 'search',
                            'value' => $filters['location'], 'placeholder' => 'Type a city…', 'thClass' => 'min-w-36',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'department_id', 'label' => 'Department / Position', 'type' => 'options',
                            'value' => $filters['department_id'], 'options' => $departments->pluck('name', 'id')->all(),
                            'allLabel' => 'All departments', 'thClass' => 'min-w-48',
                        ])
                        <th class="px-4 py-3 text-right">Needed</th>
                        <th class="px-4 py-3 text-right">Candidates</th>
                        {{-- Pinned, so the actions stay reachable while the wide table scrolls sideways. --}}
                        <th class="px-4 py-3 text-center sticky right-0 bg-gray-50 border-l border-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($jobs as $job)
                        <tr class="align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $jobs->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-800">{{ $job->position_title }}</span>
                                <span class="block text-[10px] text-gray-400 font-mono">{{ $job->request_number }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ $job->platform->name ?? '-' }}
                                @if($job->posting_url)
                                    <a href="{{ $job->posting_url }}" target="_blank" rel="noopener" class="block text-indigo-600 hover:underline">
                                        View posting <i class="fas fa-arrow-up-right-from-square text-[9px]"></i>
                                    </a>
                                @endif
                                @if($job->publish_to_website)
                                    <span class="block text-[10px] text-gray-400"><i class="fas fa-globe"></i> Marked for career website</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $job->opens_at?->format('d M Y, H:i') ?? '-' }}
                                <span class="block text-gray-400">to {{ $job->closes_at?->format('d M Y, H:i') ?? 'no closing date' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $job->statusBadge() }}">{{ $job->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $job->employmentType->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $job->location ?: '-' }}</td>
                            <td class="px-4 py-3">
                                {{ $job->department->name ?? '-' }}
                                <span class="block text-gray-400">{{ $job->position->name ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">{{ $job->quota }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($job->candidates_count && $can('general.recruitment.candidates'))
                                    <a href="{{ route('general.recruitment.candidates.index', ['job_opening_id' => $job->id]) }}" class="font-semibold text-indigo-600 hover:underline">{{ $job->candidates_count }}</a>
                                @else
                                    {{ $job->candidates_count }}
                                @endif
                            </td>
                            <td class="px-4 py-3 sticky right-0 bg-white border-l border-gray-100">
                                <div class="flex items-center justify-center gap-1.5">
                                    @include($action, $canEdit
                                        ? ['icon' => 'pen', 'tone' => 'blue', 'label' => 'Edit', 'href' => route('general.recruitment.jobs.edit', $job)]
                                        : ['icon' => 'eye', 'tone' => 'gray', 'label' => 'View', 'href' => route('general.recruitment.jobs.edit', $job)])
                                    @if($canEdit && $job->status() !== \App\Models\Recruitment\JobOpening::STATUS_CLOSED)
                                        @include($action, [
                                            'icon' => 'lock', 'tone' => 'amber', 'label' => 'Close now', 'post' => route('general.recruitment.jobs.close', $job),
                                            'confirm' => 'Close this job opening now? Its closing date will be set to the current time.',
                                            'confirmTitle' => 'Close Job Opening',
                                        ])
                                    @endif
                                    @if($canDelete)
                                        @include($action, [
                                            'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete', 'post' => route('general.recruitment.jobs.destroy', $job),
                                            'confirm' => 'Delete this job opening?' . ($job->candidates_count ? " Its {$job->candidates_count} candidate(s) are kept, without a job opening." : ''),
                                            'confirmTitle' => 'Delete Job Opening',
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-5 py-10 text-center text-gray-400">
                                {{ $hasFilters ? 'No job openings match these filters.' : 'No job openings yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $jobs, 'form' => $filterForm])
        </div>
    </div>
</div>

@include('hr-general.recruitment.components.confirm-forms')
@endsection
