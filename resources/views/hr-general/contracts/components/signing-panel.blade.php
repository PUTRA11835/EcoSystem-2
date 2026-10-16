{{--
    Signing panel of the contract document page (Legal / RPMO). The workflow, until the e-stamp is integrated:

      1 stamp   download the PDF, apply the e-stamp online, upload the stamped copy (the box above)
      2 send    to the employee, with a deadline — they review and sign every page from My Profile
      3 forward to the approver of the template — they review and sign every page
      4 print   the approved contract, then release it
      5 release the employee can now download it

    Needs $signing (ContractController::signingPanel), $contract, $doc. The buttons show only to holders of general.contracts.signing.
--}}
@php
    $steps = [
        ['key' => 'stamp',    'label' => 'E-stamped'],
        ['key' => 'employee', 'label' => 'Employee signs'],
        ['key' => 'approver', 'label' => 'Approver signs'],
        ['key' => 'print',    'label' => 'Print'],
        ['key' => 'release',  'label' => 'Released'],
    ];
    $stage = $signing['stage'];
    // How far the contract is: the index of the step it is waiting on.
    $at = match (true) {
        $stage === 'released'      => 5,
        $stage === 'approved'      => 3,
        $stage === 'with_approver' => 2,
        in_array($stage, ['with_employee', 'with_legal'], true) => $stage === 'with_legal' ? 2 : 1,
        $signing['hasStamped']     => 1,
        default                    => 0,
    };
    $evLabels = [
        'sent' => 'Sent to the employee', 'employee_signed' => 'Signed by the employee', 'forwarded' => 'Forwarded to the approver',
        'approver_signed' => 'Approved and signed by the approver', 'released' => 'Released to the employee',
        'revision' => 'Change requested', 'cancelled' => 'Withdrawn from signing',
    ];
    $field = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $lbl = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $can = $signing['can'];
@endphp

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden" id="signingPanel">
    <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg tone-primary flex items-center justify-center"><i class="fas fa-signature"></i></span>
            <div>
                <p class="text-sm font-bold text-gray-800">Signing</p>
                <p class="text-[11px] text-gray-500">
                    {{ $signing['label'] }}
                    @if($stage === 'with_employee' && $contract->sign_deadline)
                        · due {{ $contract->sign_deadline->format('d M Y') }}@if($signing['daysLeft'] !== null) ({{ $signing['daysLeft'] < 0 ? abs($signing['daysLeft']) . ' day(s) overdue' : $signing['daysLeft'] . ' day(s) left' }})@endif
                    @endif
                </p>
            </div>
            @if($stage)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $signing['badge'] }}">{{ $signing['label'] }}</span>@endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($stage)
                <a href="{{ route('general.contracts.signing.record', $contract->contract_id) }}" target="_blank" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-file-pdf mr-1"></i> Signing record</a>
            @endif
            @if($signing['hasSigned'] || ($stage && $signing['hasStamped']))
                <a href="{{ route('general.contracts.signing.file', $contract->contract_id) }}" target="_blank" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-eye mr-1"></i> {{ $signing['hasSigned'] ? 'Signed copy' : 'Stamped copy' }}</a>
                <a href="{{ route('general.contracts.signing.file', [$contract->contract_id, 'download' => 1]) }}" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-download mr-1"></i> Download</a>
            @endif
        </div>
    </div>

    {{-- Steps --}}
    <ol class="px-4 py-3 grid grid-cols-2 sm:grid-cols-5 gap-2 text-[11px]">
        @foreach($steps as $i => $step)
            @php $done = $i < $at; $now = $i === $at && $stage !== 'released'; @endphp
            <li class="flex items-center gap-2 rounded-lg border px-2.5 py-2 {{ $done ? 'border-green-200 bg-green-50 text-green-800' : ($now ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-gray-200 text-gray-400') }}">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 {{ $done ? 'bg-green-600 text-white' : ($now ? 'bg-amber-500 text-white' : 'bg-gray-200 text-gray-500') }}">
                    @if($done)<i class="fas fa-check"></i>@else{{ $i + 1 }}@endif
                </span>
                <span class="font-semibold">{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ol>

    @if($stage === 'revision')
        @php $why = $signing['events']->first(fn ($e) => $e->event === 'revision'); @endphp
        <div class="mx-4 mb-3 text-xs bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3">
            <p class="font-semibold"><i class="fas fa-rotate-left mr-1"></i> {{ $why->actor_name ?? 'The signer' }} asked for a change.</p>
            @if($why && $why->note)<p class="mt-1 whitespace-pre-line">{{ $why->note }}</p>@endif
            <p class="mt-1 text-red-700">Edit the contract, upload the new stamped copy, then send it again.</p>
        </div>
    @endif

    @if($can)
        <div class="px-4 pb-4 flex flex-wrap items-center gap-2">
            @if($signing['allow']['send'])
                <button type="button" data-open="sendModal" @disabled(!$signing['hasStamped'])
                    class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane mr-1"></i> {{ $stage === 'with_legal' ? 'Send to employee again' : 'Send to employee' }}
                </button>
                @unless($signing['hasStamped'])
                    <span class="text-[11px] text-gray-500"><i class="fas fa-stamp mr-1"></i> Upload the e-stamped PDF first.</span>
                @endunless
            @endif
            @if($signing['allow']['forward'])
                <button type="button" data-open="forwardModal" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-share mr-1"></i> Forward to approver</button>
            @endif
            @if($signing['allow']['release'])
                <form method="POST" action="{{ route('general.contracts.signing.release', $contract->contract_id) }}" data-unsaved-ignore
                      data-confirm="Release {{ $doc['number'] }}? The employee can then download the approved contract. Print it first if it has to be on paper." data-confirm-title="Release Contract" data-confirm-ok="Release">@csrf
                    <button type="submit" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-unlock mr-1"></i> Release to employee</button>
                </form>
            @endif
            @if($signing['allow']['cancel'] && $stage)
                <form method="POST" action="{{ route('general.contracts.signing.cancel', $contract->contract_id) }}" data-unsaved-ignore
                      data-confirm="Withdraw {{ $doc['number'] }} from signing? The signatures given so far are dropped and the stamped copy can be changed again." data-confirm-title="Withdraw from Signing" data-confirm-ok="Withdraw" data-confirm-variant="danger">@csrf
                    <button type="submit" class="px-3 py-2 bg-white border border-red-300 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50">Withdraw</button>
                </form>
            @endif
        </div>
    @elseif($stage === null)
        <p class="px-4 pb-4 text-[11px] text-gray-400"><i class="fas fa-lock mr-1"></i> Sending a contract for signing needs the “Contract Signing” permission.</p>
    @endif

    @if($signing['events']->isNotEmpty())
        <details class="border-t border-gray-100">
            <summary class="px-4 py-2.5 text-xs font-semibold text-gray-600 cursor-pointer">History ({{ $signing['events']->count() }})</summary>
            <ul class="divide-y divide-gray-100 text-xs">
                @foreach($signing['events'] as $e)
                    <li class="px-4 py-2 flex items-start justify-between gap-4">
                        <div>
                            <span class="font-semibold text-gray-800">{{ $evLabels[$e->event] ?? $e->event }}</span>
                            <span class="text-gray-500"> — {{ $e->actor_name ?: '—' }}</span>
                            @if($e->note)<p class="text-gray-600 mt-0.5 whitespace-pre-line">{{ $e->note }}</p>@endif
                            @if(isset($e->meta['overlay']) && $e->meta['overlay'] === false)
                                <p class="text-amber-700 mt-0.5"><i class="fas fa-triangle-exclamation mr-1"></i>Not drawn on the PDF — {{ $e->meta['reason'] ?? 'the PDF could not be read' }} It is kept in the signing record.</p>
                            @endif
                        </div>
                        <span class="text-gray-400 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($e->created_at)->format('d M Y H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>

@if($can)
    {{-- Send to the employee --}}
    <div id="sendModal" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true">
        <form method="POST" action="{{ route('general.contracts.signing.send', $contract->contract_id) }}" class="bg-white rounded-xl shadow-xl w-full max-w-lg" data-unsaved-ignore>@csrf
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900">Send to the employee for signature</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $doc['number'] }} — the e-stamped copy on file is what they review and sign.</p>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div><label class="{{ $lbl }}" for="sendDeadline">Sign before</label>
                    <input type="date" id="sendDeadline" name="sign_deadline" min="{{ now()->toDateString() }}" class="{{ $field }}">
                    <p class="text-[10px] text-gray-400 mt-1">The employee is notified now and reminded as the date comes close. Leave empty for no deadline.</p></div>
                <div><label class="{{ $lbl }}" for="sendApprover">Approver</label>
                    <select id="sendApprover" name="approver_employee_id" data-searchable="true" data-search-placeholder="Search approver…" class="{{ $field }}">
                        <option value="">Choose when forwarding</option>
                        @foreach($signing['approvers'] as $p)
                            <option value="{{ $p['id'] }}" @selected($signing['approverId'] === (int) $p['id'])>{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1">Preset from the template. Must have a signature in the master data.</p></div>
                <div><label class="{{ $lbl }}" for="sendNote">Note to the employee</label>
                    <textarea id="sendNote" name="note" rows="3" maxlength="1000" class="{{ $field }}" placeholder="Optional"></textarea></div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" data-close class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><i class="fas fa-paper-plane mr-1"></i> Send</button>
            </div>
        </form>
    </div>

    {{-- Forward to the approver --}}
    <div id="forwardModal" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true">
        <form method="POST" action="{{ route('general.contracts.signing.forward', $contract->contract_id) }}" class="bg-white rounded-xl shadow-xl w-full max-w-lg" data-unsaved-ignore>@csrf
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900">Forward to the approver</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $doc['number'] }} — signed by the employee on {{ $contract->employee_signed_at?->format('d M Y') }}.</p>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div><label class="{{ $lbl }}" for="fwApprover">Approver <span class="text-red-500">*</span></label>
                    <select id="fwApprover" name="approver_employee_id" required data-searchable="true" data-search-placeholder="Search approver…" class="{{ $field }}">
                        <option value="">Select an approver</option>
                        @foreach($signing['approvers'] as $p)
                            <option value="{{ $p['id'] }}" @selected($signing['approverId'] === (int) $p['id'])>{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1">The approver set on the template is preselected. They are notified and sign every page.</p></div>
                <div><label class="{{ $lbl }}" for="fwNote">Note</label>
                    <textarea id="fwNote" name="note" rows="3" maxlength="1000" class="{{ $field }}" placeholder="Optional"></textarea></div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" data-close class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><i class="fas fa-share mr-1"></i> Forward</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    (function () {
        const show = el => { el.style.display = 'flex'; el.classList.remove('hidden'); };
        const hide = el => { el.style.display = 'none'; el.classList.add('hidden'); };
        document.querySelectorAll('[data-open]').forEach(b => b.addEventListener('click', () => show(document.getElementById(b.dataset.open))));
        ['sendModal', 'forwardModal'].forEach(id => {
            const m = document.getElementById(id);
            m.addEventListener('click', e => { if (e.target === m || e.target.closest('[data-close]')) hide(m); });
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') ['sendModal', 'forwardModal'].forEach(id => hide(document.getElementById(id))); });
    })();
    </script>
    @endpush
@endif
