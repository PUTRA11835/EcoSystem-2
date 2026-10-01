@extends('dashboard')
@section('title', 'Offering Letter')
@section('page-title', 'Offering Letter')
@section('page-subtitle', 'Write, print and send offering letters, and record what the candidate decided.')

@php
    // Capabilities of this tab, as ticked in Management → Roles.
    $canCreate = $canDo('general.recruitment.offers', 'create');
    $canEdit   = $canDo('general.recruitment.offers', 'edit');
    $filterForm = 'offerFilters';
    $filter = 'hr-general.recruitment.components.header-filter';
    $action = 'hr-general.recruitment.components.icon-action';
    $rupiah = fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.offering.components.hub-tabs')
    @include('hr-general.recruitment.components.form-errors')

    @if($canCreate && $awaitingLetter->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3">
            <span class="font-semibold"><i class="fas fa-file-signature mr-1"></i> At the Offer stage, no letter yet:</span>
            @foreach($awaitingLetter as $waiting)
                <button type="button" onclick="openOfferModal({ candidate_id: {{ $waiting->id }} })"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white border border-amber-300 font-semibold hover:bg-amber-100">
                    <i class="fas fa-plus text-[9px]"></i> {{ $waiting->name }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Every header filter and the rows-per-page choice belong to this form through their `form` attribute. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.recruitment.offers.index') }}" data-filter-form class="hidden"></form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Offering Letters</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.</p>
            </div>
            <div class="flex items-center gap-2" data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.recruitment.offers.index') }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
                @if($canCreate)
                    <button type="button" onclick="openOfferModal()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> Add Offering Letter
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
                            'form' => $filterForm, 'name' => 'number', 'label' => 'Letter No.', 'type' => 'search',
                            'value' => $filters['number'], 'placeholder' => 'Type a letter number…', 'thClass' => 'min-w-44',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'candidate', 'label' => 'Candidate', 'type' => 'search',
                            'value' => $filters['candidate'], 'placeholder' => 'Name or email…', 'thClass' => 'min-w-40',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'position', 'label' => 'Position', 'type' => 'search',
                            'value' => $filters['position'], 'placeholder' => 'Type a position…', 'thClass' => 'min-w-40',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statuses,
                            'allLabel' => 'All statuses', 'thClass' => 'min-w-28',
                        ])
                        <th class="px-4 py-3 min-w-32">Total</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($offers as $offer)
                        @php $status = $offer->status(); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-400">{{ $offers->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-800">{{ $offer->letter_number }}</span>
                                <span class="block text-[10px] text-gray-400">{{ $offer->offer_date->format('d M Y') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-800">{{ $offer->candidate_name }}</span>
                                @if($offer->candidate_email)
                                    <span class="block text-[10px] text-gray-400">{{ $offer->candidate_email }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $offer->position_title }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ \App\Models\Recruitment\Offer::STATUS_BADGES[$status] }}">{{ $statuses[$status] }}</span>
                                @if($offer->sent_at)
                                    <span class="block text-[10px] text-gray-400 mt-0.5" title="Last emailed to the candidate">
                                        <i class="fas fa-paper-plane text-[9px]"></i> {{ $offer->sent_at->format('d M Y, H:i') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-semibold text-gray-800">{{ $rupiah($offer->total_compensation) }}</span>
                                <span class="block text-[10px] text-gray-400">{{ \App\Models\Recruitment\Offer::SALARY_TYPES[$offer->salary_type] ?? $offer->salary_type }} salary</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($canEdit && $offer->isPending())
                                        @include($action, [
                                            'icon' => 'pen', 'tone' => 'blue', 'label' => 'Edit letter',
                                            'onclick' => 'openOfferModal(JSON.parse(this.dataset.payload))',
                                            'data' => [
                                                'id' => $offer->id,
                                                ...$offer->only([
                                                    'letter_number', 'candidate_id', 'candidate_name', 'candidate_email', 'candidate_phone',
                                                    'position_title', 'job_description', 'benefits', 'has_probation', 'salary_type',
                                                    'notes', 'signatory_name', 'signatory_title',
                                                ]),
                                                'offer_date'   => $offer->offer_date->toDateString(),
                                                'joining_date' => $offer->joining_date?->toDateString(),
                                                'amounts'      => $offer->lines()->pluck('amount', 'component_id'),
                                            ],
                                        ])
                                    @endif
                                    @include($action, [
                                        'icon' => 'print', 'tone' => 'gray', 'label' => 'Print PDF', 'newTab' => true,
                                        'href' => route('general.recruitment.offers.print', $offer),
                                    ])
                                    @if($canEdit && $offer->isPending())
                                        @include($action, [
                                            'icon' => 'envelope', 'tone' => 'indigo',
                                            'label' => $offer->sent_at ? 'Send to candidate again' : 'Send to candidate',
                                            'post' => route('general.recruitment.offers.send', $offer),
                                            'confirm' => 'Email offering letter ' . $offer->letter_number . ' as a PDF to ' . ($offer->candidate_email ?: '(no email on the letter)') . '?',
                                            'confirmTitle' => 'Send Offering Letter', 'confirmOk' => 'Send',
                                        ])
                                        @include($action, [
                                            'icon' => 'check', 'tone' => 'green', 'label' => 'Candidate accepted',
                                            'onclick' => 'openAcceptModal(JSON.parse(this.dataset.payload))',
                                            'data' => ['id' => $offer->id, 'name' => $offer->candidate_name, 'email' => $offer->candidate_email],
                                        ])
                                        @include($action, [
                                            'icon' => 'xmark', 'tone' => 'red', 'label' => 'Candidate rejected',
                                            'post' => route('general.recruitment.offers.reject', $offer),
                                            'confirm' => 'Mark the offer to ' . $offer->candidate_name . ' as rejected by the candidate?'
                                                . ($offer->candidate_id ? ' Their status in the selection process becomes Rejected.' : ''),
                                            'confirmTitle' => 'Offer Rejected', 'confirmOk' => 'Mark as rejected',
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                                {{ $hasFilters ? 'No offering letters match these filters.' : 'No offering letters yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $offers, 'form' => $filterForm])
        </div>
    </div>
</div>

@if($canCreate || $canEdit)
    @include('hr-general.offering.components.offer-modal')
@endif

@if($canEdit)
    <!-- Modal: the candidate accepted — creates their employee account -->
    <div id="acceptModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <form id="acceptForm" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="accept">
                <input type="hidden" name="_offer_id" id="acceptOfferId">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800">Offer Accepted — Create Employee</h3>
                    <button type="button" onclick="document.getElementById('acceptModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <p class="text-xs text-gray-500">
                        This creates the employee record and login account of <strong id="acceptCandidateName"></strong> and emails them a link to set their password,
                        so they can sign in and start onboarding straight away.
                    </p>
                    <div>
                        <label for="acceptEci" class="block text-xs font-semibold text-gray-600 mb-1">Employee ID (ECI) <span class="text-red-500">*</span></label>
                        <input type="text" name="eci" id="acceptEci" required maxlength="50" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <p class="text-[11px] text-gray-400 mt-1">Also the username they sign in with.</p>
                    </div>
                    <div>
                        <label for="acceptNickName" class="block text-xs font-semibold text-gray-600 mb-1">Nick Name <span class="text-red-500">*</span></label>
                        <input type="text" name="nick_name" id="acceptNickName" required maxlength="100" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="acceptEmail" class="block text-xs font-semibold text-gray-600 mb-1">Login Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="acceptEmail" required maxlength="150" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('acceptModal').classList.add('hidden')"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-green-600 rounded-lg hover:opacity-90">Accept &amp; Create Employee</button>
                </div>
            </form>
        </div>
    </div>
@endif

@include('hr-general.recruitment.components.confirm-forms')
@endsection

@if($canEdit)
    @push('scripts')
    <script>
        const acceptUrlTemplate = {{ Js::from(route('general.recruitment.offers.accept', ['offer' => '__ID__'])) }};

        // `keep` = reopened after a failed save: what was typed stays as it was.
        function openAcceptModal(offer, keep) {
            document.getElementById('acceptForm').action = acceptUrlTemplate.replace('__ID__', offer.id);
            document.getElementById('acceptOfferId').value = offer.id;
            document.getElementById('acceptCandidateName').textContent = offer.name ?? '';
            document.getElementById('acceptEci').value = keep ? offer.eci ?? '' : '';
            document.getElementById('acceptNickName').value = keep ? offer.nick_name ?? '' : (offer.name ?? '').split(' ')[0];
            document.getElementById('acceptEmail').value = offer.email ?? '';
            document.getElementById('acceptModal').classList.remove('hidden');
        }

        @if(old('_modal') === 'accept')
            document.addEventListener('DOMContentLoaded', () => openAcceptModal({{ Js::from([
                'id' => old('_offer_id'), 'name' => $offers->firstWhere('id', (int) old('_offer_id'))?->candidate_name,
                'eci' => old('eci'), 'nick_name' => old('nick_name'), 'email' => old('email'),
            ]) }}, true));
        @endif
    </script>
    @endpush
@endif
