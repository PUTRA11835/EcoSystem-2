{{--
    "Add / edit offering letter" modal of the Offering Letter table.

    Open it with openOfferModal(letter):
      nothing / { candidate_id }  — a new letter, optionally for that candidate
      a letter's saved fields     — edit it (needs `id`)

    Expects from the controller: $components, $candidates, $settings,
    $nextNumber, $defaults.
--}}
@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $optional = '<span class="font-normal text-gray-400">(optional)</span>';
    $minPercent = rtrim(rtrim(number_format($settings->offer_base_salary_min_percent, 2, '.', ''), '0'), '.');
    $namesOf = fn (string $kind) => $components->where('kind', $kind)->where('is_active', true)->pluck('name')->implode(', ');
@endphp

<div id="offerModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[92vh] flex flex-col">
        <form id="offerModalForm" method="POST" class="flex flex-col min-h-0" autocomplete="off">
            @csrf
            <input type="hidden" name="_modal" value="offer">
            <input type="hidden" name="_offer_id" id="olId">

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="offerModalTitle" class="text-sm font-bold text-gray-800">Add Offering Letter</h3>
                <button type="button" onclick="closeOfferModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="px-5 py-4 space-y-4 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="olNumber" class="{{ $label }}">Letter No.</label>
                        <input type="text" name="letter_number" id="olNumber" maxlength="60" class="{{ $input }}">
                        <p class="text-[11px] text-gray-400 mt-1" id="olNumberHint">
                            Leave empty to generate it from the format in Offering Settings — for a letter dated today: {{ $nextNumber }}.
                        </p>
                    </div>
                    <div>
                        <label for="olDate" class="{{ $label }}">Offer Date <span class="text-red-500">*</span></label>
                        <input type="date" name="offer_date" id="olDate" required class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="olCandidate" class="{{ $label }}">Candidate Data {!! $optional !!}</label>
                    <select name="candidate_id" id="olCandidate" data-searchable="true" data-search-placeholder="Search candidate…" class="{{ $input }}">
                        <option value="">-- Enter by hand --</option>
                        @foreach($candidates as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->name }} — {{ $candidate->positionLabel() }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Candidates at the Offer stage of the selection process. Picking one fills in the name, email, phone and position below.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="olName" class="{{ $label }}">Candidate Name <span class="text-red-500">*</span></label>
                        <input type="text" name="candidate_name" id="olName" required maxlength="150" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="olPosition" class="{{ $label }}">Position <span class="text-red-500">*</span></label>
                        <input type="text" name="position_title" id="olPosition" required maxlength="150" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="olEmail" class="{{ $label }}">Email</label>
                        <input type="email" name="candidate_email" id="olEmail" maxlength="150" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="olPhone" class="{{ $label }}">Phone Number</label>
                        <input type="text" name="candidate_phone" id="olPhone" maxlength="30" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="olJobDescription" class="{{ $label }}">Job Description</label>
                    <textarea name="job_description" id="olJobDescription" rows="2" placeholder="What the candidate will be doing in this position" class="{{ $input }}"></textarea>
                </div>

                <div>
                    <label for="olBenefits" class="{{ $label }}">Benefits</label>
                    <textarea name="benefits" id="olBenefits" rows="2" class="{{ $input }}"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-start">
                    <div>
                        <label for="olJoining" class="{{ $label }}">Start Date <span class="text-red-500">*</span></label>
                        <input type="date" name="joining_date" id="olJoining" required class="{{ $input }}">
                        <p class="text-[11px] text-gray-400 mt-1">Becomes the employee's join date when the offer is accepted.</p>
                    </div>
                    <div class="sm:pt-6">
                        <label class="inline-flex items-center gap-2.5 cursor-pointer text-sm text-gray-700">
                            <input type="checkbox" name="has_probation" id="olProbation" value="1" class="sr-only peer">
                            <span class="relative w-9 h-5 shrink-0 rounded-full bg-gray-200 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-200
                                after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-4"></span>
                            3-month probation period
                        </label>
                        <p class="text-[11px] text-gray-400 mt-1">Prints the 3-month probation clause on the letter.</p>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-3">Compensation</h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="olSalaryType" class="{{ $label }}">Salary Type <span class="text-red-500">*</span></label>
                            <select name="salary_type" id="olSalaryType" required class="{{ $input }}">
                                @foreach(\App\Models\Recruitment\Offer::SALARY_TYPES as $value => $typeLabel)
                                    <option value="{{ $value }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Whether the amounts offered are gross or nett.</p>
                        </div>
                    </div>

                    {{-- One amount per component of Offering Settings. An inactive one is shown only for a letter that already carries it. --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                        @foreach($components as $component)
                            <div data-component-row @if(!$component->is_active) data-inactive class="hidden" @endif>
                                <label for="olAmount{{ $component->id }}" class="{{ $label }}">
                                    {{ $component->name }}
                                    @if($component->isBase())<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                                    <input type="text" inputmode="numeric" name="amounts[{{ $component->id }}]" id="olAmount{{ $component->id }}"
                                        data-amount data-kind="{{ $component->kind }}" placeholder="0" @required($component->isBase())
                                        class="{{ $input }} pl-9 text-right">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="olSignatoryName" class="{{ $label }}">Signatory Name <span class="text-red-500">*</span></label>
                        <input type="text" name="signatory_name" id="olSignatoryName" required maxlength="150" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="olSignatoryTitle" class="{{ $label }}">Signatory Position <span class="text-red-500">*</span></label>
                        <input type="text" name="signatory_title" id="olSignatoryTitle" required maxlength="150" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="olNotes" class="{{ $label }}">Notes</label>
                    <textarea name="notes" id="olNotes" rows="2" placeholder="Printed on the letter, under the compensation" class="{{ $input }}"></textarea>
                </div>

                <div class="flex items-center justify-between rounded-lg bg-blue-50 border border-blue-100 px-4 py-3">
                    <span class="text-xs font-semibold text-blue-700">Total Compensation</span>
                    <span class="text-lg font-bold text-blue-700" id="olTotal">Rp 0</span>
                </div>

                <div id="olRatio" class="rounded-lg border px-4 py-3 text-xs" aria-live="polite">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold text-gray-800">Base Salary Percentage</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">Legal basis: base salary of at least {{ $minPercent }}% of base salary + fixed allowances.</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xl font-bold" id="olRatioValue">0%</p>
                            <p class="text-[11px]" id="olRatioVerdict"></p>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2">
                        Fixed allowances counted: {{ $namesOf('fixed') ?: 'none' }}.
                        @if($namesOf('variable'))
                            Variable components ({{ $namesOf('variable') }}) are not part of this ratio.
                        @endif
                    </p>
                    <p class="text-[11px] text-gray-500 mt-1">{{ $settings->offer_legal_basis }}</p>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeOfferModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" id="offerModalSubmit" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('offerModal');
        const form = document.getElementById('offerModalForm');
        const byId = id => document.getElementById(id);

        const storeUrl = @json(route('general.recruitment.offers.store'));
        const updateUrl = {{ Js::from(route('general.recruitment.offers.update', ['offer' => '__ID__'])) }};
        const minPercent = @json($settings->offer_base_salary_min_percent);
        const defaults = {{ Js::from([...$defaults, 'benefits' => $settings->offer_default_benefits, 'has_probation' => true, 'salary_type' => 'gross']) }};
        const candidates = {{ Js::from($candidates->mapWithKeys(fn ($candidate) => [$candidate->id => [
            'candidate_name'  => $candidate->name,
            'candidate_email' => $candidate->email,
            'candidate_phone' => $candidate->phone,
            'position_title'  => $candidate->positionLabel() === '-' ? '' : $candidate->positionLabel(),
        ]])) }};

        // Field name => input id, for everything that is a plain value.
        const fields = {
            letter_number: 'olNumber', offer_date: 'olDate', candidate_name: 'olName', candidate_email: 'olEmail',
            candidate_phone: 'olPhone', position_title: 'olPosition', job_description: 'olJobDescription',
            benefits: 'olBenefits', joining_date: 'olJoining', notes: 'olNotes',
            signatory_name: 'olSignatoryName', signatory_title: 'olSignatoryTitle',
        };

        const amountInputs = () => Array.from(form.querySelectorAll('[data-amount]'));
        const digits = value => String(value ?? '').replace(/,\d{1,2}$/, '').replace(/\D/g, '');
        const money = value => { const d = digits(value); return d === '' ? '' : Number(d).toLocaleString('id-ID'); };

        function setSelect(id, value) {
            const select = byId(id);
            select.value = value == null ? '' : String(value);
            // Tells the enhanced dropdown (select-enhance.js) to show the new value.
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function recalculate() {
            let total = 0, base = 0, fixed = 0;
            amountInputs().forEach(input => {
                const amount = Number(digits(input.value) || 0);
                total += amount;
                if (input.dataset.kind === 'base') base += amount;
                if (input.dataset.kind === 'fixed') fixed += amount;
            });

            byId('olTotal').textContent = 'Rp ' + total.toLocaleString('id-ID');

            const panel = byId('olRatio'), value = byId('olRatioValue'), verdict = byId('olRatioVerdict');
            const percent = base > 0 ? base / (base + fixed) * 100 : null;
            const state = percent === null ? 'empty' : (percent >= minPercent ? 'ok' : 'low');
            const looks = {
                empty: ['border-gray-200 bg-gray-50', 'text-gray-400', 'text-gray-500', 'Enter the compensation components to calculate the percentage.'],
                ok:    ['border-green-200 bg-green-50', 'text-green-700', 'text-green-700', 'Meets the minimum of ' + minPercent + '%.'],
                low:   ['border-red-200 bg-red-50', 'text-red-600', 'text-red-600', 'Below the minimum of ' + minPercent + '% — raise the base salary or lower the fixed allowances.'],
            }[state];

            panel.className = 'rounded-lg border px-4 py-3 text-xs ' + looks[0];
            value.className = 'text-xl font-bold ' + looks[1];
            value.textContent = (percent === null ? 0 : percent.toLocaleString('id-ID', { maximumFractionDigits: 2 })) + '%';
            verdict.className = 'text-[11px] ' + looks[2];
            verdict.textContent = looks[3];
        }

        function fill(letter) {
            Object.entries(fields).forEach(([name, id]) => { byId(id).value = letter[name] ?? ''; });
            byId('olProbation').checked = !!Number(letter.has_probation);
            setSelect('olSalaryType', letter.salary_type || 'gross');

            const amounts = letter.amounts || {};
            amountInputs().forEach(input => {
                const componentId = input.name.match(/\d+/)[0];
                input.value = money(amounts[componentId]);

                const row = input.closest('[data-component-row]');
                if (row.hasAttribute('data-inactive')) row.classList.toggle('hidden', input.value === '');
            });

            recalculate();
        }

        window.openOfferModal = function (letter) {
            letter = letter || {};
            const editing = !!letter.id;

            form.action = editing ? updateUrl.replace('__ID__', letter.id) : storeUrl;
            byId('olId').value = letter.id || '';
            byId('offerModalTitle').textContent = editing ? 'Edit Offering Letter' : 'Add Offering Letter';
            byId('offerModalSubmit').textContent = editing ? 'Update' : 'Save';
            byId('olNumberHint').classList.toggle('hidden', editing);

            // The dropdown first: choosing a candidate fills name, contact and position, which the letter's own values then replace.
            setSelect('olCandidate', letter.candidate_id);
            fill(editing || letter._keep ? letter : { ...defaults, ...(candidates[letter.candidate_id] || {}) });

            modal.classList.remove('hidden');
        };

        window.closeOfferModal = function () {
            modal.classList.add('hidden');
        };

        byId('olCandidate').addEventListener('change', function () {
            const candidate = candidates[this.value];
            if (!candidate) return;
            Object.entries(candidate).forEach(([name, value]) => { byId(fields[name]).value = value ?? ''; });
        });

        form.addEventListener('input', event => {
            if (!event.target.matches('[data-amount]')) return;
            event.target.value = money(event.target.value);
            recalculate();
        });

        document.addEventListener('DOMContentLoaded', function () {
            @if(old('_modal') === 'offer')
                // Reopened after a failed save, with everything as it was typed.
                openOfferModal({ ...{{ Js::from(collect(old())->except('_token')) }}, id: @json(old('_offer_id')), has_probation: @json((bool) old('has_probation')), _keep: true });
            @elseif($canCreate && $prefillCandidateId && $awaitingLetter->contains('id', $prefillCandidateId))
                openOfferModal({ candidate_id: @json($prefillCandidateId) });
            @endif
        });
    })();
</script>
@endpush
