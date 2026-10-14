@extends('dashboard')

@section('title', 'Reimbursement — Management')
@section('page-title', 'Reimbursement')
@section('page-subtitle', 'Manage and process employee reimbursement requests')

@php
    $filterForm = 'reimbursementFilters';
    $filter     = 'hr-general.recruitment.components.header-filter';
    $action     = 'hr-general.recruitment.components.icon-action';

    // "Open" is the default view, so it is the "no filter" row of the status funnel.
    $statusOptions = ['approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
    if ($canManage) {
        $statusOptions['deleted'] = 'Deleted';
    }

    $cards = [
        ['label' => 'Waiting for You', 'value' => $counts['mine'],     'class' => 'text-red-800',   'icon' => 'fa-hourglass-half', 'href' => route('general.reimbursement.index', ['scope' => 'mine'])],
        ['label' => 'Pending Total',   'value' => $counts['pending'],  'class' => 'text-amber-600', 'icon' => 'fa-clock'],
        ['label' => 'Approved',        'value' => $counts['approved'], 'class' => 'text-green-600', 'icon' => 'fa-circle-check'],
        ['label' => 'Rejected',        'value' => $counts['rejected'], 'class' => 'text-red-600',   'icon' => 'fa-circle-xmark'],
    ];
@endphp

@section('content')
@php use App\Models\Reimbursement\ReimbursementRequest; @endphp

@include('hr-general.reimbursement.components.hub-tabs')
@include('hr-general.reimbursement.components.date-picker')

<div class="space-y-5">

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($cards as $card)
            @php $isActive = !empty($card['href']) && $filters['scope'] === 'mine'; @endphp
            @if(!empty($card['href']))
            <a href="{{ $isActive ? route('general.reimbursement.index') : $card['href'] }}"
               title="{{ $isActive ? 'Show everyone again' : 'Show only documents waiting for you' }}"
               class="block bg-white rounded-xl p-5 shadow-sm border transition-all hover:shadow-md {{ $isActive ? 'border-red-800 ring-1 ring-red-800' : 'border-transparent' }}">
            @else
            <div class="bg-white rounded-xl p-5 shadow-sm border border-transparent">
            @endif
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
                    <i class="fas {{ $card['icon'] }} text-sm {{ $card['class'] }} opacity-60"></i>
                </div>
                <p class="text-2xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
            @if(!empty($card['href']))
            </a>
            @else
            </div>
            @endif
        @endforeach
    </div>

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.reimbursement.index') }}" data-filter-form class="hidden">
        @if($filters['scope'] === 'mine')
            <input type="hidden" name="scope" value="mine">
        @endif
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Reimbursement Requests</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">
                    Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.
                    Open a document to approve or reject it. Highlighted rows need attention.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2" data-live-region="toolbar">
                {{-- Month filter: a dropdown in the table header, not in the Date column. --}}
                <div class="flex items-center gap-1.5">
                    <label for="rbMonth" class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Month</label>
                    <div class="w-40">
                        <select name="month" id="rbMonth" form="{{ $filterForm }}" onchange="this.form.requestSubmit()">
                            <option value="">All months</option>
                            @foreach($monthOptions as $ym => $monthLabel)
                                <option value="{{ $ym }}" @selected($filters['month'] === $ym)>{{ $monthLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($hasFilters)
                    <a href="{{ route('general.reimbursement.index') }}"
                       class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif

                @if($can('general.reimbursement.export'))
                <a href="{{ route('general.reimbursement.export', request()->query()) }}"
                   title="Exports the filtered documents; without a date range, the current month"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-white text-green-700 text-xs font-semibold rounded-lg border border-green-300 hover:bg-green-50 transition-all whitespace-nowrap">
                    <i class="fas fa-file-excel text-xs"></i> Monthly Export
                </a>
                @endif

                @if($can('general.reimbursement.import'))
                <a href="{{ route('general.reimbursement.import.form') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-white text-blue-700 text-xs font-semibold rounded-lg border border-blue-300 hover:bg-blue-50 transition-all whitespace-nowrap">
                    <i class="fas fa-file-import text-xs"></i> Import RB
                </a>
                @endif

                @if($can('general.reimbursement.create'))
                <a href="{{ route('general.reimbursement.create') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm whitespace-nowrap">
                    <i class="fas fa-plus text-xs"></i> New RB
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
                            'form' => $filterForm, 'name' => 'number', 'label' => 'Document No.', 'type' => 'search',
                            'value' => $filters['number'], 'placeholder' => 'e.g. RB/2026/10…', 'thClass' => 'min-w-40',
                        ])
                        <th class="px-4 py-3 min-w-28">Date</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'title', 'label' => 'Description', 'type' => 'search',
                            'value' => $filters['title'], 'placeholder' => 'Title or item description…',
                            'note' => 'Searches the title and every item line.', 'thClass' => 'min-w-48',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'requester', 'label' => 'Requester', 'type' => 'search',
                            'value' => $filters['requester'], 'placeholder' => 'Name or employee no…', 'thClass' => 'min-w-40',
                        ])
                        <th class="px-4 py-3 min-w-32">Approver</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'amount', 'label' => 'Amount', 'type' => 'range', 'align' => 'right',
                            'value' => ['min' => $filters['amount_min'], 'max' => $filters['amount_max']],
                            'note' => 'Document total, whole numbers.', 'thClass' => 'min-w-32',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statusOptions,
                            'default' => 'open', 'allLabel' => 'Open', 'thClass' => 'min-w-44',
                        ])
                        <th class="px-4 py-3 min-w-36">Log Date</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($requests as $index => $request)
                    @php
                        $attention = $request->attentionFlags();
                        $isMine    = in_array($request->id, $mineIds, true);
                        $isDeleted = $request->trashed();
                        $statusTone = $request->status === 'approved' ? 'text-green-700'
                            : ($request->status === 'rejected' ? 'text-red-700' : 'text-blue-700');
                    @endphp
                    <tr class="align-top hover:bg-gray-50 transition-colors {{ $isDeleted ? 'opacity-60' : ($attention ? 'bg-amber-50/60' : '') }}">
                        <td class="px-4 py-3 text-gray-400">{{ $requests->firstItem() + $index }}</td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('general.reimbursement.show', $request) }}"
                               class="font-mono text-xs text-red-800 font-semibold hover:underline">
                                {{ $request->request_no }}
                            </a>
                            @if($isMine)
                                <span class="block mt-1 px-1.5 py-0.5 bg-red-800 text-white text-[10px] font-bold rounded uppercase tracking-wide text-center">Your turn</span>
                            @endif
                            @if($isDeleted)
                                <span class="block mt-1 text-[10px] text-gray-500">deleted</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap text-gray-900">
                            {{ $request->request_date->format('d M Y') }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-gray-900">{{ $request->title }}</div>
                            <div class="text-[10px] text-gray-400">{{ $request->item_count }} item(s)</div>
                            @if($attention)
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                @foreach($attention as $flag)
                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-semibold rounded">
                                        {{ ReimbursementRequest::FLAG_LABELS[$flag] ?? $flag }}
                                    </span>
                                @endforeach
                            </div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $request->employee?->fullNameLabel() ?? '—' }}</div>
                            <div class="text-[10px] text-gray-400">{{ $request->employee?->eci }}</div>
                        </td>

                        <td class="px-4 py-3 text-gray-700">{{ $request->approverLabel() }}</td>

                        <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">
                            {{ $request->totalLabel() }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-semibold {{ $statusTone }}">{{ $request->statusLabel('Pending') }}</div>
                            <div class="text-[10px] text-gray-400 mt-0.5">
                                @if($request->isOpen())
                                    Current step: {{ $request->currentStepName() ?? '—' }}
                                @elseif($request->completed_at)
                                    {{ ucfirst($request->status) }}: {{ $request->completed_at->format('d M Y') }}
                                @endif
                            </div>
                            @if($isDeleted && $request->delete_reason)
                                <div class="text-[10px] text-gray-500 italic mt-1">"{{ $request->delete_reason }}"</div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-[10px] text-gray-500 whitespace-nowrap">
                            <div>Created : {{ $request->created_at?->format('d M Y') ?? '—' }}</div>
                            <div>Update&nbsp; : {{ $request->updated_at?->format('d M Y') ?? '—' }}</div>
                        </td>

                        {{-- Approving and rejecting happen on the detail page only: a decision is made
                             with the items, the trail and the notes in front of you. --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                @include($action, [
                                    'icon' => 'eye',
                                    'tone' => $isMine && $can('general.reimbursement.approve') ? 'green' : 'gray',
                                    'label' => $isMine && $can('general.reimbursement.approve')
                                        ? 'Open to approve & sign, or reject' : 'View details',
                                    'href' => route('general.reimbursement.show', $request),
                                ])

                                @if(in_array($request->id, $editableIds, true))
                                    @include($action, [
                                        'icon' => 'pen', 'tone' => 'blue', 'label' => 'Edit',
                                        'href' => route('general.reimbursement.edit', $request),
                                    ])
                                @endif

                                @include($action, [
                                    'icon' => 'print', 'tone' => 'gray', 'label' => 'Print', 'newTab' => true,
                                    'href' => route('general.reimbursement.print', $request),
                                ])

                                @if($can('general.reimbursement.export'))
                                    @include($action, [
                                        'icon' => 'file-excel', 'tone' => 'green', 'label' => 'Export Excel',
                                        'href' => route('general.reimbursement.export.single', $request),
                                    ])
                                @endif

                                @if($request->supporting_url)
                                    @include($action, [
                                        'icon' => 'up-right-from-square', 'tone' => 'indigo', 'label' => 'Open supporting file',
                                        'href' => $request->supporting_url, 'newTab' => true,
                                    ])
                                @endif

                                @if($canManage && !$isDeleted)
                                    @include($action, [
                                        'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete — opens the document',
                                        'href' => route('general.reimbursement.show', $request),
                                    ])
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            {{ $hasFilters ? 'No reimbursement matches these filters.' : 'No reimbursement yet.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $requests, 'form' => $filterForm])
        </div>
    </div>
</div>
@endsection
