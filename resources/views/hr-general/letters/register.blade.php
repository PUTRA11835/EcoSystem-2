@extends('dashboard')
@section('title', 'Letter Register')
@section('page-title', 'Letter Templates')
@section('page-subtitle', 'Every letter in and out in one register: generated here, offering letters, and letters logged by hand.')

@php
    use App\Models\Letters\Letter;

    $canCreate = $canDo('general.letters.register', 'create');
    $canEdit   = $canDo('general.letters.register', 'edit');
    $canDelete = $canDo('general.letters.register', 'delete');
    $filterForm = 'letterRegisterFilters';
    $filter = 'hr-general.recruitment.components.header-filter';
    $action = 'hr-general.recruitment.components.icon-action';
    $directionTabs = ['' => 'All', Letter::DIRECTION_OUTGOING => 'Outgoing', Letter::DIRECTION_INCOMING => 'Incoming'];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.letters.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.letters.register.index') }}" data-filter-form class="hidden">
        <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
        @if($filters['employee'] !== '')<input type="hidden" name="employee" value="{{ $filters['employee'] }}">@endif
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                {{-- One register, three views of it. --}}
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5" role="tablist">
                    @foreach($directionTabs as $value => $tabLabel)
                        <a href="{{ route('general.letters.register.index', array_filter([...request()->except(['direction', 'page']), 'direction' => $value])) }}"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold transition-colors {{ $filters['direction'] === $value ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}">
                            {{ $tabLabel }} <span class="ml-0.5 text-[10px] text-gray-400">{{ $counts[$value] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>
                <p class="text-[10px] text-gray-400">
                    Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.
                    @if($employeeName)
                        <span class="ml-1 px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-semibold">Letters about {{ $employeeName }}</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.letters.register.index', array_filter(['direction' => $filters['direction']])) }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
                @if($canCreate)
                    <a href="{{ route('general.letters.register.create', ['direction' => 'incoming']) }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 shadow-sm whitespace-nowrap">
                        <i class="fas fa-inbox text-xs"></i> Incoming Letter
                    </a>
                    <a href="{{ route('general.letters.register.create', ['direction' => 'outgoing']) }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> Outgoing Letter
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
                            'form' => $filterForm, 'name' => 'search', 'label' => 'Number', 'type' => 'search',
                            'value' => $filters['search'], 'placeholder' => 'Number, subject, to / from…', 'thClass' => 'min-w-48',
                        ])
                        <th class="px-4 py-3 min-w-24">Date</th>
                        <th class="px-4 py-3 min-w-40">To / From</th>
                        <th class="px-4 py-3 min-w-48">Subject</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'source', 'label' => 'Source', 'type' => 'options',
                            'value' => $filters['source'], 'options' => Letter::SOURCES, 'allLabel' => 'All sources', 'thClass' => 'min-w-28',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'code', 'label' => 'Code', 'type' => 'options',
                            'value' => $filters['code'], 'options' => $codeOptions, 'allLabel' => 'All codes', 'thClass' => 'min-w-20',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => Letter::STATUSES, 'allLabel' => 'All statuses', 'thClass' => 'min-w-28',
                        ])
                        <th class="px-4 py-3 min-w-36">Delivery</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($letters as $letter)
                        @php $status = $letter->status(); @endphp
                        <tr class="hover:bg-gray-50 align-top {{ $letter->isVoid() ? 'opacity-60' : '' }}">
                            <td class="px-4 py-3 text-gray-400">{{ $letters->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold {{ $letter->isOutgoing() ? 'bg-blue-50 text-blue-700' : 'bg-green-50 text-green-700' }}">{{ $letter->isOutgoing() ? 'OUT' : 'IN' }}</span>
                                @if($letter->isOutgoing())
                                    <span class="font-semibold text-gray-800 ml-1 {{ $letter->isVoid() ? 'line-through' : '' }}">{{ $letter->letter_number ?: '-' }}</span>
                                @else
                                    <span class="font-semibold text-gray-800 ml-1">{{ $letter->agenda_number }}</span>
                                    @if($letter->letter_number)<span class="block text-[10px] text-gray-400 mt-0.5">Sender's no.: {{ $letter->letter_number }}</span>@endif
                                @endif
                                @if($letter->language === 'en')
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[9px] font-bold">EN</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $letter->letter_date->format('d M Y') }}
                                @if($letter->received_date)<span class="block text-[10px] text-gray-400">received {{ $letter->received_date->format('d M Y') }}</span>@endif
                            </td>
                            <td class="px-4 py-3">{{ $letter->counterparty ?: '-' }}</td>
                            <td class="px-4 py-3">
                                {{ $letter->subject }}
                                @if($letter->isVoid() && $letter->void_reason)
                                    <span class="block text-[10px] text-red-600 mt-0.5">Void: {{ $letter->void_reason }}</span>
                                @elseif($letter->notes)
                                    <span class="block text-[10px] text-gray-400 mt-0.5">{{ \Illuminate\Support\Str::limit($letter->notes, 80) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $letter->typeLabel() }}</td>
                            <td class="px-4 py-3">{{ $letter->code?->code ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ Letter::STATUS_BADGES[$status] }}">{{ Letter::STATUSES[$status] }}</span>
                            </td>
                            <td class="px-4 py-3 text-[11px]">
                                @if($letter->delivered_via)<span class="block">{{ $letter->delivered_via }}</span>@endif
                                @if($letter->receipt_number)<span class="block text-gray-400">Receipt: {{ $letter->receipt_number }}</span>@endif
                                @if($letter->sent_at)<span class="block text-gray-400"><i class="fas fa-paper-plane text-[9px]"></i> {{ $letter->sent_at->format('d M Y') }}</span>@endif
                                @if(!$letter->delivered_via && !$letter->receipt_number && !$letter->sent_at)<span class="text-gray-400">-</span>@endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($letter->isGenerated() || ($letter->isOffering() && $can('general.recruitment.offers')))
                                        @include($action, ['icon' => 'file-pdf', 'tone' => 'gray', 'label' => 'Open the letter (PDF)', 'href' => route('general.letters.pdf', $letter), 'newTab' => true])
                                    @endif
                                    @if($letter->finalPath())
                                        @include($action, ['icon' => 'paperclip', 'tone' => 'gray', 'label' => 'Download ' . ($letter->isOutgoing() ? 'the final (signed / stamped) file' : 'the scanned letter'), 'href' => route('general.letters.file', $letter), 'newTab' => true])
                                    @endif
                                    @if($canEdit && !$letter->isVoid())
                                        @include($action, [
                                            'icon' => 'pen', 'tone' => 'blue',
                                            'label' => $letter->isManual() ? 'Edit' : 'Delivery details & final file',
                                            'href' => route('general.letters.register.edit', $letter),
                                        ])
                                    @endif
                                    @if($letter->isOffering() && $can('general.recruitment.offers'))
                                        @include($action, ['icon' => 'up-right-from-square', 'tone' => 'indigo', 'label' => 'Open in Offering Letter', 'href' => route('general.recruitment.offers.index', ['number' => $letter->letter_number])])
                                    @endif
                                    @if($canDelete && $letter->isOutgoing() && !$letter->isVoid() && !$letter->isOffering())
                                        @include($action, [
                                            'icon' => 'ban', 'tone' => 'red', 'label' => 'Void',
                                            'onclick' => 'openReasonModal(JSON.parse(this.dataset.payload))',
                                            'data' => [
                                                'action' => route('general.letters.register.void', $letter), 'field' => 'void_reason',
                                                'title' => 'Void Letter', 'button' => 'Void',
                                                'intro' => "Void letter {$letter->letter_number}? It stays in the register marked void, and its number is not given out again.",
                                            ],
                                        ])
                                    @endif
                                    @if($canDelete && $letter->isManual() && !$letter->isOutgoing())
                                        @include($action, [
                                            'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete',
                                            'post' => route('general.letters.register.destroy', $letter),
                                            'confirm' => "Delete incoming letter {$letter->agenda_number} from {$letter->counterparty}?",
                                            'confirmTitle' => 'Delete Incoming Letter', 'confirmOk' => 'Delete',
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-10 text-center text-gray-400">
                                {{ $hasFilters ? 'No letters match these filters.' : 'No letters in the register yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $letters, 'form' => $filterForm])
        </div>
    </div>
</div>

@include('hr-general.letters.components.modals')
@include('hr-general.recruitment.components.confirm-forms')
@endsection
