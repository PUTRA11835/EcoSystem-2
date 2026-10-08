@extends('dashboard')
@section('title', 'Letter Requests')
@section('page-title', 'Letter Templates')
@section('page-subtitle', 'Letters employees asked for in My Letter Requests: generate, sign with the master data signature, then complete & send.')

@php
    use App\Models\Letters\Letter;
    use App\Models\Letters\LetterRequest;

    $filterForm = 'letterRequestFilters';
    $filter = 'hr-general.recruitment.components.header-filter';
    $action = 'hr-general.recruitment.components.icon-action';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.letters.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.letters.requests.index') }}" data-filter-form class="hidden"></form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Employee Letter Requests</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">
                    Opens on what is still waiting. Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to see the history.
                </p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.letters.requests.index') }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
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
                            'form' => $filterForm, 'name' => 'employee', 'label' => 'Employee', 'type' => 'search',
                            'value' => $filters['employee'], 'placeholder' => 'Name or employee ID…', 'thClass' => 'min-w-40',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'type', 'label' => 'Letter', 'type' => 'options',
                            'value' => $filters['type'], 'options' => $typeOptions, 'allLabel' => 'All letters', 'thClass' => 'min-w-44',
                        ])
                        <th class="px-4 py-3 min-w-28">Needed by</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statusOptions, 'default' => 'open', 'allLabel' => 'Pending + In Progress', 'thClass' => 'min-w-36',
                        ])
                        <th class="px-4 py-3 min-w-40">Letter</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($requests as $letterRequest)
                        @php $letter = $letterRequest->activeLetter(); @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $requests->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-800">{{ $letterRequest->employeeName() }}</span>
                                <span class="block text-[10px] text-gray-400">{{ $letterRequest->employee?->eci }} · asked {{ $letterRequest->created_at->format('d M Y') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-800">{{ $letterRequest->typeLabel() }}</span>
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[9px] font-bold">{{ strtoupper($letterRequest->language) }}</span>
                                @if($letterRequest->is_other)
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[9px] font-bold" title="Typed by the employee — answered with a custom letter">OTHER</span>
                                @endif
                                <span class="block text-[10px] text-gray-400">
                                    {{ $letterRequest->usesTemplate() ? 'Template: ' . \App\Support\Letters\LetterTemplates::label($letterRequest->template_key) : 'Custom letter' }}
                                </span>
                                <span class="block text-[10px] text-gray-500 mt-0.5">{{ $letterRequest->purpose }}</span>
                                @if($letterRequest->notes)
                                    <span class="block text-[10px] text-gray-400 italic">"{{ $letterRequest->notes }}"</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap {{ $letterRequest->isOpen() && $letterRequest->needed_by?->isPast() ? 'text-red-600 font-semibold' : '' }}">
                                {{ $letterRequest->needed_by?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ LetterRequest::STATUS_BADGES[$letterRequest->status] }}">
                                    {{ LetterRequest::STATUSES[$letterRequest->status] }}
                                </span>
                                @if($letterRequest->status === LetterRequest::REJECTED && $letterRequest->reject_reason)
                                    <span class="block text-[10px] text-red-600 mt-0.5">{{ $letterRequest->reject_reason }}</span>
                                @endif
                                @if($letterRequest->handler)
                                    <span class="block text-[10px] text-gray-400 mt-0.5">by {{ $letterRequest->handler->basicData?->full_name ?: $letterRequest->handler->eci }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($letter)
                                    <a href="{{ route('general.letters.pdf', $letter) }}" target="_blank" class="font-semibold hover:underline" style="color: var(--primary-color);">{{ $letter->letter_number }}</a>
                                    <span class="block text-[10px] text-gray-400">
                                        {{ Letter::STATUSES[$letter->status()] }}
                                        @if($letter->email_status === Letter::EMAIL_SENT) · emailed {{ $letter->sent_at?->format('d M Y H:i') }} @endif
                                    </span>
                                    @if($letter->email_status === Letter::EMAIL_FAILED)
                                        <span class="block text-[10px] text-red-600" title="{{ $letter->email_error }}"><i class="fas fa-triangle-exclamation"></i> Email failed</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($letter)
                                        @include($action, ['icon' => 'eye', 'tone' => 'gray', 'label' => 'View the letter', 'href' => route('general.letters.pdf', $letter), 'newTab' => true])
                                    @endif

                                    @if($canEdit && $letterRequest->isOpen())
                                        @if(!$letter && $canCompose)
                                            {{-- Opens Create Letter with the request filled in. --}}
                                            @include($action, [
                                                'icon' => 'pen-to-square', 'tone' => 'blue', 'label' => $letterRequest->usesTemplate() ? 'Process: generate the letter from its template' : 'Process: write it as a custom letter',
                                                'post' => route('general.letters.requests.process', $letterRequest),
                                            ])
                                        @endif
                                        @if($letter)
                                            @include($action, [
                                                'icon' => 'signature', 'tone' => 'green',
                                                'label' => $letter->isSigned() ? 'Sign again with the master data signature' : 'Sign with the master data signature',
                                                'post' => route('general.letters.requests.sign', $letterRequest),
                                                'confirm' => (int) $letter->signatory_employee_id === (int) session('user.id')
                                                    ? "Sign letter {$letter->letter_number} with your signature from the employee master data?"
                                                    : "This applies the signature of {$letter->signatory_name} from the employee master data to letter {$letter->letter_number}. "
                                                        . "Have you confirmed with {$letter->signatory_name} (outside the app) that they agree?",
                                                'confirmTitle' => 'Sign Letter', 'confirmOk' => (int) $letter->signatory_employee_id === (int) session('user.id') ? 'Sign' : 'Confirmed — sign',
                                            ])
                                        @endif
                                        @if($letter && $letter->isSigned())
                                            @include($action, [
                                                'icon' => 'paper-plane', 'tone' => 'indigo', 'label' => 'Complete & Send',
                                                'onclick' => 'openLetterSendModal(JSON.parse(this.dataset.payload))',
                                                'data' => [
                                                    'action' => route('general.letters.requests.complete', $letterRequest), 'pdf' => route('general.letters.pdf', $letter),
                                                    'title' => 'Complete & Send', 'button' => 'Complete & Send',
                                                    'number' => $letter->letter_number, 'name' => $letterRequest->employeeName(),
                                                    'email' => $letter->recipient_email ?: \App\Models\Letters\Letter::workEmailOf($letterRequest->employee_id),
                                                    ...($emails[$letter->id] ?? []),
                                                ],
                                            ])
                                        @endif
                                        @include($action, [
                                            'icon' => 'xmark', 'tone' => 'red', 'label' => 'Reject the request',
                                            'onclick' => 'openReasonModal(JSON.parse(this.dataset.payload))',
                                            'data' => [
                                                'action' => route('general.letters.requests.reject', $letterRequest), 'field' => 'reject_reason',
                                                'title' => 'Reject Request', 'label' => 'Reason (shown to the employee)', 'button' => 'Reject',
                                                'intro' => "Reject the request of {$letterRequest->employeeName()} for a {$letterRequest->typeLabel()}?"
                                                    . ($letter ? " Letter {$letter->letter_number} generated for it is voided." : ''),
                                            ],
                                        ])
                                    @endif

                                    @if($canEdit && $letterRequest->isDone() && $letter?->email_status === Letter::EMAIL_FAILED)
                                        {{-- Only a failed email is sent again; a delivered one never is. --}}
                                        @include($action, [
                                            'icon' => 'rotate-right', 'tone' => 'amber', 'label' => 'Resend the failed email',
                                            'onclick' => 'openLetterSendModal(JSON.parse(this.dataset.payload))',
                                            'data' => [
                                                'action' => route('general.letters.requests.resend', $letterRequest), 'pdf' => route('general.letters.pdf', $letter),
                                                'title' => 'Resend Letter', 'again' => true,
                                                'number' => $letter->letter_number, 'name' => $letterRequest->employeeName(), 'email' => $letter->recipient_email,
                                                ...($emails[$letter->id] ?? []),
                                            ],
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                                {{ $hasFilters ? 'No requests match these filters.' : 'No requests waiting — every request was handled.' }}
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

@include('hr-general.letters.components.modals')
@include('hr-general.recruitment.components.confirm-forms')
@endsection
