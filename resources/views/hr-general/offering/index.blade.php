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
                                @if($offer->isEnglish())
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[9px] font-bold align-middle" title="Written in English">EN</span>
                                @endif
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
                                @if($offer->isSigned())
                                    <span class="block text-[10px] text-gray-400 mt-0.5" title="Signed with the signature of {{ $offer->signatory_name }} from the employee master data">
                                        <i class="fas fa-signature text-[9px]"></i> {{ $offer->signatory_name }} · {{ $offer->signed_at->format('d M Y, H:i') }}
                                    </span>
                                @endif
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
                                                    'letter_number', 'language', 'candidate_id', 'candidate_name', 'candidate_email', 'candidate_phone',
                                                    'position_title', 'job_description', 'benefits', 'has_probation', 'salary_type',
                                                    'notes', 'signatory_name', 'signatory_title', 'signatory_employee_id',
                                                ]),
                                                'is_signed'    => $offer->isSigned(),
                                                'offer_date'   => $offer->offer_date->toDateString(),
                                                'joining_date' => $offer->joining_date?->toDateString(),
                                                'amounts'      => $offer->lines()->pluck('amount', 'component_id'),
                                            ],
                                        ])
                                    @endif
                                    @include($action, [
                                        'icon' => 'print', 'tone' => 'gray', 'newTab' => true,
                                        'label' => $offer->isSigned() ? 'Download signed PDF' : 'Print PDF (not signed yet)',
                                        'href' => route('general.recruitment.offers.print', $offer),
                                    ])
                                    @if($canEdit && $offer->isPending())
                                        {{-- Generated -> signed with the master-data signature -> sent. --}}
                                        @include($action, [
                                            'icon' => 'signature', 'tone' => 'green',
                                            'label' => $offer->isSigned() ? 'Sign again with the master data signature' : 'Sign with the master data signature',
                                            'post' => route('general.recruitment.offers.sign', $offer),
                                            'confirm' => 'Sign offering letter ' . $offer->letter_number . ' with the signature of '
                                                . ($offer->signatory_name ?: 'its signatory') . ' from the employee master data?',
                                            'confirmTitle' => 'Sign Offering Letter', 'confirmOk' => 'Sign',
                                        ])
                                        @if($offer->isSigned())
                                            @include($action, [
                                                'icon' => 'envelope', 'tone' => 'indigo',
                                                'label' => $offer->sent_at ? 'Send to candidate again' : 'Send to candidate',
                                                'onclick' => 'openSendModal(JSON.parse(this.dataset.payload))',
                                                'data' => [
                                                    'id' => $offer->id, 'number' => $offer->letter_number,
                                                    'name' => $offer->candidate_name, 'email' => $offer->candidate_email,
                                                    'again' => (bool) $offer->sent_at, ...$emails[$offer->id],
                                                ],
                                            ])
                                        @endif
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
                        This creates the employee record and login account of <strong id="acceptCandidateName"></strong> and emails them their username,
                        email and the default password below. When they first sign in with it, they are asked to set their own password, then they can start onboarding.
                    </p>
                    <p class="text-xs text-gray-500">
                        Their <strong>join date</strong> in the employee master data is set to <strong>today ({{ now()->format('d M Y') }})</strong>, the day the account is created,
                        and their position to the one on the letter. A new contract starts from that join date.
                    </p>
                    <div>
                        <label for="acceptEci" class="block text-xs font-semibold text-gray-600 mb-1">Employee ID (ECI) <span class="text-red-500">*</span></label>
                        <input type="text" name="eci" id="acceptEci" required maxlength="50" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <p class="text-[11px] text-gray-400 mt-1">Also the username they sign in with.</p>
                    </div>
                    <div>
                        <label for="acceptFullName" class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" id="acceptFullName" required maxlength="150" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <p class="text-[11px] text-gray-400 mt-1">As it should appear on the employee record. A nick name is made from it and can be changed in Master Employee.</p>
                    </div>
                    <div>
                        <label for="acceptEmail" class="block text-xs font-semibold text-gray-600 mb-1">Login Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="acceptEmail" required maxlength="150" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="acceptPassword" class="block text-xs font-semibold text-gray-600 mb-1">Default Password <span class="text-red-500">*</span></label>
                        <div class="flex gap-1.5">
                            <div class="relative flex-1 min-w-0">
                                <input type="password" name="default_password" id="acceptPassword" required minlength="8" maxlength="100" autocomplete="new-password"
                                    class="w-full border border-gray-200 rounded-lg pl-3 pr-9 py-2 text-sm font-mono">
                                <button type="button" onclick="toggleAcceptPassword()" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    title="Show / hide" aria-label="Show or hide the password">
                                    <i class="fas fa-eye text-xs" id="acceptPasswordEye"></i>
                                </button>
                            </div>
                            <button type="button" onclick="generateAcceptPassword()"
                                class="px-3 py-2 border border-gray-200 rounded-lg text-xs font-semibold text-gray-600 hover:bg-gray-50 whitespace-nowrap" title="Make up a random password">
                                <i class="fas fa-wand-magic-sparkles text-[10px]"></i> Generate
                            </button>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">At least 8 characters. Emailed to the candidate with their username; it only works until they set their own password.</p>
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

@if($canEdit)
    <!-- Modal: email the signed letter to the candidate, with a message HR can adjust -->
    <div id="sendModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[92vh] flex flex-col">
            <form id="sendForm" method="POST" class="flex flex-col min-h-0">
                @csrf
                <input type="hidden" name="_modal" value="send">
                <input type="hidden" name="_offer_id" id="sendOfferId">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800">Send Offering Letter</h3>
                    <button type="button" onclick="document.getElementById('sendModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-5 py-4 space-y-3 overflow-y-auto">
                    <p id="sendAgainNote" class="hidden text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-3 py-2">
                        This letter was already emailed. Sending it again emails the candidate a second time.
                    </p>
                    <div>
                        <span class="block text-xs font-semibold text-gray-600 mb-1">To</span>
                        <p class="text-sm text-gray-800"><span id="sendName" class="font-semibold"></span> &lt;<span id="sendEmail"></span>&gt;</p>
                    </div>
                    <div>
                        <label for="sendSubject" class="block text-xs font-semibold text-gray-600 mb-1">Subject <span class="text-red-500">*</span></label>
                        <input type="text" name="subject" id="sendSubject" required maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="sendBody" class="block text-xs font-semibold text-gray-600 mb-1">Message <span class="text-red-500">*</span></label>
                        <textarea name="body" id="sendBody" required rows="10" maxlength="10000" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm leading-relaxed"></textarea>
                        <p class="text-[11px] text-gray-400 mt-1">Starts from the email text in the letter's language — adjust it as needed. A blank line starts a new paragraph.</p>
                    </div>
                    <p class="text-xs text-gray-500"><i class="fas fa-paperclip mr-1"></i> The signed letter <strong id="sendNumber"></strong> is attached as a PDF.</p>
                </div>
                <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('sendModal').classList.add('hidden')"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                        <i class="fas fa-paper-plane text-[10px]"></i> Send
                    </button>
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
        const sendUrlTemplate = {{ Js::from(route('general.recruitment.offers.send', ['offer' => '__ID__'])) }};

        function openSendModal(offer) {
            document.getElementById('sendForm').action = sendUrlTemplate.replace('__ID__', offer.id);
            document.getElementById('sendOfferId').value = offer.id;
            document.getElementById('sendName').textContent = offer.name ?? '';
            document.getElementById('sendEmail').textContent = offer.email || '(no email on the letter)';
            document.getElementById('sendNumber').textContent = offer.number ?? '';
            document.getElementById('sendSubject').value = offer.subject ?? '';
            document.getElementById('sendBody').value = offer.body ?? '';
            document.getElementById('sendAgainNote').classList.toggle('hidden', !offer.again);
            document.getElementById('sendModal').classList.remove('hidden');
        }

        // `keep` = reopened after a failed save: what was typed stays as it was.
        function openAcceptModal(offer, keep) {
            document.getElementById('acceptForm').action = acceptUrlTemplate.replace('__ID__', offer.id);
            document.getElementById('acceptOfferId').value = offer.id;
            document.getElementById('acceptCandidateName').textContent = offer.name ?? '';
            document.getElementById('acceptEci').value = keep ? offer.eci ?? '' : '';
            document.getElementById('acceptFullName').value = keep ? offer.full_name ?? '' : offer.name ?? '';
            document.getElementById('acceptEmail').value = offer.email ?? '';
            // A password is never sent back to the page after a failed save — it is typed or generated again.
            document.getElementById('acceptPassword').value = '';
            document.getElementById('acceptPassword').type = 'password';
            document.getElementById('acceptPasswordEye').className = 'fas fa-eye text-xs';
            document.getElementById('acceptModal').classList.remove('hidden');
        }

        function toggleAcceptPassword() {
            const input = document.getElementById('acceptPassword');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            document.getElementById('acceptPasswordEye').className = 'fas fa-eye' + (show ? '-slash' : '') + ' text-xs';
        }

        // 12 characters from letters and digits that cannot be mistaken for one another (no 0/O, 1/l/I).
        function generateAcceptPassword() {
            const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            const bytes = crypto.getRandomValues(new Uint32Array(12));
            const input = document.getElementById('acceptPassword');
            input.value = Array.from(bytes, n => alphabet[n % alphabet.length]).join('');
            input.type = 'text';
            document.getElementById('acceptPasswordEye').className = 'fas fa-eye-slash text-xs';
        }

        @if(old('_modal') === 'send')
            // Reopened after a failed check, with the subject and message as they were typed.
            document.addEventListener('DOMContentLoaded', () => {
                const offer = {{ Js::from($offers->firstWhere('id', (int) old('_offer_id'))?->only(['id', 'letter_number', 'candidate_name', 'candidate_email'])) }};
                if (offer) openSendModal({
                    id: offer.id, number: offer.letter_number, name: offer.candidate_name, email: offer.candidate_email,
                    subject: @json(old('subject')), body: @json(old('body')),
                });
            });
        @endif

        @if(old('_modal') === 'accept')
            document.addEventListener('DOMContentLoaded', () => openAcceptModal({{ Js::from([
                'id' => old('_offer_id'), 'name' => $offers->firstWhere('id', (int) old('_offer_id'))?->candidate_name,
                'eci' => old('eci'), 'full_name' => old('full_name'), 'email' => old('email'),
            ]) }}, true));
        @endif
    </script>
    @endpush
@endif
