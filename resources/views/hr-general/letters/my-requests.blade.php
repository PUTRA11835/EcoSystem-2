@extends('dashboard')
@section('title', 'My Letter Requests')
@section('page-title', 'My Letter Requests')
@section('page-subtitle', 'Ask HR for a letter — an employment certificate, a reference, or any other letter you need — and download it here once it is ready. It is also emailed to you.')

@php
    use App\Models\Letters\LetterRequest;

    $canCreate = $canDo('general.my-letter-requests', 'create');
    $canEdit   = $canDo('general.my-letter-requests', 'edit');
    $canDelete = $canDo('general.my-letter-requests', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $action = 'hr-general.recruitment.components.icon-action';
    $other = \App\Http\Controllers\HR_General\MyLetterRequestController::OTHER;
    $canAsk = $types->isNotEmpty() || $allowOther;
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.recruitment.components.form-errors')
    @include('hr-general.recruitment.components.unsaved-guard')

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">My Requests</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">A request can be changed or cancelled until HR starts on it.</p>
            </div>
            @if($canCreate && $canAsk)
                <button type="button" onclick="openRequestModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm whitespace-nowrap">
                    <i class="fas fa-plus text-xs"></i> Request a Letter
                </button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3">Letter</th>
                        <th class="px-4 py-3">Purpose</th>
                        <th class="px-4 py-3">Asked on</th>
                        <th class="px-4 py-3">Needed by</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($requests as $letterRequest)
                        @php $letter = $letterRequest->activeLetter(); @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-800">{{ $letterRequest->typeLabel() }}</span>
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[9px] font-bold">{{ strtoupper($letterRequest->language) }}</span>
                                @if($letterRequest->is_other)
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[9px] font-bold">OTHER</span>
                                @endif
                                @if($letterRequest->isDone() && $letter)
                                    <span class="block text-[10px] text-gray-400">{{ $letter->letter_number }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $letterRequest->purpose }}
                                @if($letterRequest->notes)<span class="block text-[10px] text-gray-400 italic">"{{ $letterRequest->notes }}"</span>@endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $letterRequest->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $letterRequest->needed_by?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ LetterRequest::STATUS_BADGES[$letterRequest->status] }}">
                                    {{ LetterRequest::STATUSES[$letterRequest->status] }}
                                </span>
                                @if($letterRequest->status === LetterRequest::REJECTED && $letterRequest->reject_reason)
                                    <span class="block text-[10px] text-red-600 mt-0.5">{{ $letterRequest->reject_reason }}</span>
                                @endif
                                @if($letterRequest->isDone() && $letter?->email_status === \App\Models\Letters\Letter::EMAIL_SENT)
                                    <span class="block text-[10px] text-gray-400 mt-0.5"><i class="fas fa-envelope text-[9px]"></i> emailed {{ $letter->sent_at?->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($letterRequest->isDone() && $letter)
                                        @include($action, ['icon' => 'download', 'tone' => 'green', 'label' => 'Download the letter', 'href' => route('general.my-letter-requests.download', $letterRequest)])
                                    @endif
                                    @if($letterRequest->isPending() && $canEdit)
                                        @include($action, [
                                            'icon' => 'pen', 'tone' => 'blue', 'label' => 'Change the request',
                                            'onclick' => 'openRequestModal(JSON.parse(this.dataset.payload))',
                                            'data' => [
                                                'id' => $letterRequest->id, 'language' => $letterRequest->language,
                                                'request_type' => $letterRequest->is_other || !$letterRequest->request_type_id ? $other : (string) $letterRequest->request_type_id,
                                                'other_type' => $letterRequest->is_other ? $letterRequest->request_type_name : '',
                                                'purpose' => $letterRequest->purpose, 'needed_by' => $letterRequest->needed_by?->toDateString(), 'notes' => $letterRequest->notes,
                                            ],
                                        ])
                                    @endif
                                    @if($letterRequest->isPending() && $canDelete)
                                        @include($action, [
                                            'icon' => 'xmark', 'tone' => 'red', 'label' => 'Cancel the request',
                                            'post' => route('general.my-letter-requests.cancel', $letterRequest),
                                            'confirm' => "Cancel your request for a {$letterRequest->typeLabel()}?",
                                            'confirmTitle' => 'Cancel Request', 'confirmOk' => 'Cancel request',
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-gray-400">
                                You have not asked for a letter yet.
                                @if($canCreate && $canAsk) Use <strong>Request a Letter</strong> to ask HR for one. @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($myLetters->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">Letters About Me</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Letters HR issued about you and sent to you.</p>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($myLetters as $letter)
                    <div class="px-5 py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <span class="font-semibold text-gray-800">{{ $letter->subject }}</span>
                            <span class="block text-[10px] text-gray-400">{{ $letter->letter_number }} · {{ $letter->letter_date->format('d M Y') }}</span>
                        </div>
                        @include($action, ['icon' => 'download', 'tone' => 'green', 'label' => 'Download', 'href' => route('general.my-letter-requests.letters.download', $letter)])
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@if($canCreate || $canEdit)
    <div id="requestModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
            <form id="requestForm" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="request">
                <input type="hidden" name="_request_id" id="requestId">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800" id="requestTitle">Request a Letter</h3>
                    <button type="button" onclick="closeRequestModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <label for="requestType" class="{{ $label }}">Letter <span class="text-red-500">*</span></label>
                        {{-- The options HR keeps in Letter Templates → Settings, then "Other". --}}
                        <select name="request_type" id="requestType" required class="{{ $input }}">
                            @foreach($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                            @if($allowOther)
                                <option value="{{ $other }}">Other — a letter not on this list</option>
                            @endif
                        </select>
                    </div>
                    <div id="requestOtherBlock" class="hidden">
                        <label for="requestOther" class="{{ $label }}">Which letter do you need? <span class="text-red-500">*</span></label>
                        <input type="text" name="other_type" id="requestOther" maxlength="150" placeholder="e.g. Surat Keterangan Domisili" class="{{ $input }}">
                        <p class="text-[11px] text-gray-400 mt-1">HR writes it for you as a custom letter.</p>
                    </div>
                    <div>
                        <span class="{{ $label }}">Language <span class="text-red-500">*</span></span>
                        @include('hr-general.recruitment.components.language-switch', [
                            'switchName' => 'language', 'switchId' => 'requestLanguage', 'switchValue' => 'id', 'languages' => $languages,
                        ])
                    </div>
                    <div>
                        <label for="requestPurpose" class="{{ $label }}">Purpose <span class="text-red-500">*</span></label>
                        <input type="text" name="purpose" id="requestPurpose" required maxlength="255" placeholder="e.g. visa application, bank loan" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="requestNeededBy" class="{{ $label }}">Needed by</label>
                        <input type="date" name="needed_by" id="requestNeededBy" min="{{ now()->toDateString() }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="requestNotes" class="{{ $label }}">Notes for HR</label>
                        <textarea name="notes" id="requestNotes" rows="2" maxlength="2000" class="{{ $input }}"></textarea>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" onclick="closeRequestModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" id="requestSubmit" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Send Request</button>
                </div>
            </form>
        </div>
    </div>
@endif

@include('hr-general.recruitment.components.confirm-forms')
@endsection

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('requestModal');
        if (!modal) return;
        const byId = id => document.getElementById(id);
        const storeUrl = @json(route('general.my-letter-requests.store'));
        const updateUrl = {{ Js::from(route('general.my-letter-requests.update', ['letterRequest' => '__ID__'])) }};
        // The language each letter starts in (Letter Templates → Settings); it can be switched.
        const languageFor = {{ Js::from($languageFor) }};

        function setLanguage(code) {
            const radio = modal.querySelector(`input[name="language"][value="${code}"]`);
            if (radio) radio.checked = true;
        }

        function setSelect(select, value) {
            select.value = value ?? '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        window.openRequestModal = function (request) {
            request = request || {};
            const editing = !!request.id;
            byId('requestForm').action = editing ? updateUrl.replace('__ID__', request.id) : storeUrl;
            byId('requestId').value = request.id || '';
            byId('requestTitle').textContent = editing ? 'Change Request' : 'Request a Letter';
            byId('requestSubmit').textContent = editing ? 'Save' : 'Send Request';
            setSelect(byId('requestType'), request.request_type || byId('requestType').options[0]?.value);
            byId('requestOther').value = request.other_type || '';
            showOther();
            setLanguage(request.language || languageFor[byId('requestType').value] || 'id');
            byId('requestPurpose').value = request.purpose || '';
            byId('requestNeededBy').value = request.needed_by || '';
            byId('requestNotes').value = request.notes || '';
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        };

        window.closeRequestModal = function () {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        };

        // "Other": the employee types the letter they need.
        function showOther() {
            const isOther = byId('requestType').value === @json($other);
            byId('requestOtherBlock').classList.toggle('hidden', !isOther);
            byId('requestOther').required = isOther;
            byId('requestOther').disabled = !isOther;
        }

        byId('requestType').addEventListener('change', function () {
            showOther();
            if (!byId('requestId').value) setLanguage(languageFor[this.value] || 'id');
        });

        @if(old('_modal') === 'request')
            document.addEventListener('DOMContentLoaded', () => openRequestModal({{ Js::from([
                'id' => old('_request_id'), 'request_type' => old('request_type'), 'other_type' => old('other_type'), 'language' => old('language'),
                'purpose' => old('purpose'), 'needed_by' => old('needed_by'), 'notes' => old('notes'),
            ]) }}));
        @endif
    })();
</script>
@endpush
