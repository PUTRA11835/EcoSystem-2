{{--
    My Profile → Contract → one contract, for the person who has to review and sign it.

    $role 'employee'  my own contract: review it, sign every page, or send it back to Legal for a change; download once released.
    $role 'approver'  a contract forwarded to me by Legal: review it, approve and sign every page, or send it back.

    The signature is the one in the master data (My Profile → HR information); nobody types it. Signing is confirmed in a modal
    that shows the signature that will be used and asks to confirm that every page was read.
--}}
@extends('dashboard')
@section('title', 'Contract ' . $contract->contract_number)
@section('page-title', $role === 'approver' ? 'Contract Approval' : 'My Contract')
@section('page-subtitle', $role === 'approver'
    ? 'Review the contract in detail, then approve and sign it, or send it back to Legal.'
    : 'Review the contract in detail, then sign it, or send it back to Legal for a change.')

@php
    $money = fn ($n) => \App\Support\Contracts\ContractRules::money((float) $n);
    $day = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '—';
    $eventLabels = [
        'sent' => 'Sent to the employee', 'employee_signed' => 'Signed by the employee', 'forwarded' => 'Forwarded to the approver',
        'approver_signed' => 'Approved and signed by the approver', 'released' => 'Released to the employee',
        'revision' => 'Change requested', 'cancelled' => 'Withdrawn from signing',
    ];
    $signAs = $role === 'approver' ? ($contract->approver_name ?: 'You') : $person;
    $action = $role === 'approver' ? 'Approve & sign' : 'Sign every page';
    $btn = 'inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all';
@endphp

@push('styles')
<style>
    .contract-frame { width: 100%; height: calc(100vh - 220px); min-height: 640px; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; }
</style>
@endpush

@section('content')
<div class="w-full space-y-5 px-1 lg:px-2">
    @include('hr-general.recruitment.components.form-errors')
    @error('contract')
        <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert"><i class="fas fa-circle-exclamation mr-1"></i> {{ $message }}</div>
    @enderror
    @error('confirm')
        <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert"><i class="fas fa-circle-exclamation mr-1"></i> {{ $message }}</div>
    @enderror

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ $back }}" class="text-xs font-semibold text-gray-500 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i> Back</a>
            <h2 class="text-lg font-bold text-gray-900 mt-1 truncate">{{ $contract->contract_name ?: 'Contract' }}</h2>
            <p class="text-xs text-gray-500">{{ $contract->contract_number }} · {{ $role === 'approver' ? 'Employee: ' . $person : $person }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($statusView['label'])
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $statusView['tone'] }}">{{ $statusView['label'] }}</span>
            @endif
            @if($canSign && $contract->sign_deadline)
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ ($daysLeft ?? 1) < 0 ? 'bg-red-100 text-red-800' : (($daysLeft ?? 9) <= 2 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                    <i class="far fa-clock mr-1"></i> Due {{ $day($contract->sign_deadline) }}
                    @if($daysLeft !== null) · {{ $daysLeft < 0 ? abs($daysLeft) . ' day' . (abs($daysLeft) === 1 ? '' : 's') . ' overdue' : ($daysLeft === 0 ? 'today' : $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left') }}@endif
                </span>
            @endif
        </div>
    </div>

    @if($stage === 'revision' && $role === 'employee')
        <div class="text-sm bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3"><i class="fas fa-rotate-left mr-1"></i>
            This contract was sent back to Legal for a change. You will be notified when it is sent to you again.</div>
    @elseif($stage === 'with_legal' || $stage === 'with_approver' || $stage === 'approved')
        @if($role === 'employee')
            <div class="text-sm bg-sky-50 border border-sky-200 text-sky-900 rounded-lg px-4 py-3"><i class="fas fa-signature mr-1"></i>
                <strong>Signed by you</strong> on {{ $day($contract->employee_signed_at) }}. Legal forwards it to the approver and releases it to you when it is approved.</div>
        @elseif($stage === 'approved')
            <div class="text-sm bg-green-50 border border-green-200 text-green-900 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i>
                You approved and signed this contract on {{ $day($contract->approver_signed_at) }}. It is with Legal to be printed.</div>
        @endif
    @endif

    {{-- The contract in detail --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Contract details</h3></div>
        <div class="p-5 grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 text-sm">
            @foreach([
                ['Contract no.', $contract->contract_number],
                ['Type', \App\Support\Contracts\ContractRules::TYPES[\App\Support\Contracts\ContractRules::normalizeType($contract->contract_type)] ?? $contract->contract_type],
                ['Employee', $person],
                ['Position', $contract->position ?: '—'],
                ['Position type', $contract->position_type ?: '—'],
                ['Department', $contract->department ?: '—'],
                ['Work location', $contract->work_location ?: '—'],
                ['Period', $day($contract->start_date) . ' – ' . ($contract->end_date ? $day($contract->end_date) : 'Permanent')],
                ['Signed by (company)', $contract->signatory_name ? $contract->signatory_name . ($contract->signatory_title ? ' — ' . $contract->signatory_title : '') : '—'],
                ['Approver', $contract->approver_name ? $contract->approver_name . ($contract->approver_title ? ' — ' . $contract->approver_title : '') : '—'],
                ['Sent by Legal', $contract->sign_sent_at ? $day($contract->sign_sent_at) : '—'],
                ['Pages', $pages ?? '—'],
            ] as [$k, $v])
                <div><p class="text-[11px] text-gray-400">{{ $k }}</p><p class="font-semibold text-gray-800 break-words">{{ $v }}</p></div>
            @endforeach
        </div>
        @if($contract->salary !== null || $components)
            <div class="px-5 pb-5">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Remuneration in this contract</p>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <tr><td class="px-4 py-2">Basic salary</td><td class="px-4 py-2 text-right font-semibold">{{ $money($contract->salary ?? 0) }}</td></tr>
                            @foreach($components as $row)
                                <tr><td class="px-4 py-2">{{ $row['name'] }}</td><td class="px-4 py-2 text-right font-semibold">{{ $money($row['amount']) }}</td></tr>
                            @endforeach
                            <tr class="bg-gray-50"><td class="px-4 py-2 font-bold">Total</td><td class="px-4 py-2 text-right font-bold text-gray-900">{{ $money($total) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        @if($contract->sign_stage && $events->first(fn ($e) => $e->event === 'sent' && $e->note))
            <div class="px-5 pb-5">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1">Note from Legal</p>
                <p class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 whitespace-pre-line">{{ $events->last(fn ($e) => $e->event === 'sent' && $e->note)->note ?? '' }}</p>
            </div>
        @endif
    </div>

    {{-- The document --}}
    @if($hasFile)
        <div class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold text-gray-800">The contract{{ $signedCopy ? ' — with the signatures on every page' : '' }}</h3>
                <a href="{{ route('profile.contracts.file', $contract->contract_id) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-gray-500 hover:text-gray-800"><i class="fas fa-arrow-up-right-from-square mr-1"></i> Open in a new tab</a>
            </div>
            <iframe class="contract-frame" src="{{ route('profile.contracts.file', $contract->contract_id) }}#view=FitH" title="Contract {{ $contract->contract_number }}"></iframe>
        </div>
    @else
        <div class="text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-file-circle-exclamation mr-1"></i> The contract file is not available. Please contact Legal.</div>
    @endif

    {{-- History --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-gray-800">Signing history</h3>
            <a href="{{ route('profile.contracts.record', $contract->contract_id) }}" target="_blank" class="text-xs font-semibold text-gray-500 hover:text-gray-800"><i class="fas fa-file-pdf mr-1"></i> Signing record (PDF)</a>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse($events as $e)
                <li class="px-5 py-3 flex items-start justify-between gap-4">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $eventLabels[$e->event] ?? $e->event }}</p>
                        <p class="text-xs text-gray-500">{{ $e->actor_name ?: '—' }}</p>
                        @if($e->note)<p class="text-xs text-gray-600 mt-1 whitespace-pre-line">{{ $e->note }}</p>@endif
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($e->created_at)->format('d M Y H:i') }}</span>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-gray-400">Nothing recorded yet.</li>
            @endforelse
        </ul>
    </div>

    {{-- Actions --}}
    @if($canSign || $canDownload)
        <div class="sticky bottom-3 z-10 bg-white/95 backdrop-blur border border-gray-200 shadow-lg rounded-xl px-5 py-3 flex flex-wrap items-center justify-between gap-3">
            @if($canSign)
                <p class="text-xs text-gray-600"><i class="fas fa-circle-info mr-1"></i> Read every page first. Your signature from the master data is placed on each page when you confirm.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="openRevision" class="{{ $btn }} bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-rotate-left text-xs"></i> Request a change</button>
                    <button type="button" id="openSign" class="{{ $btn }} primary-gradient text-white hover:opacity-90 shadow-sm" @disabled(!$hasFile || !$mySignature)><i class="fas fa-signature text-xs"></i> {{ $action }}</button>
                </div>
            @else
                <p class="text-xs text-gray-600"><i class="fas fa-circle-check mr-1 text-green-600"></i> Approved and released by Legal.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('profile.contracts.file', [$contract->contract_id, 'download' => 1]) }}" class="{{ $btn }} primary-gradient text-white hover:opacity-90 shadow-sm"><i class="fas fa-download text-xs"></i> Download the contract</a>
                </div>
            @endif
        </div>
        @if($canSign && !$mySignature)
            <div class="text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-pen-nib mr-1"></i>
                Your signature is not in the master data yet, so you cannot sign. Ask HR to add it under My Profile → HR information.</div>
        @endif
    @endif
</div>

@if($canSign)
    {{-- Confirm: sign --}}
    <div id="signModal" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true" aria-labelledby="signTitle">
        <form method="POST" action="{{ route('profile.contracts.sign', $contract->contract_id) }}" class="bg-white rounded-xl shadow-xl w-full max-w-lg" data-unsaved-ignore>
            @csrf
            <div class="px-5 py-4 border-b border-gray-100 flex items-start gap-3">
                <span class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center shrink-0"><i class="fas fa-signature"></i></span>
                <div>
                    <h3 id="signTitle" class="text-sm font-bold text-gray-900">{{ $role === 'approver' ? 'Approve and sign this contract?' : 'Sign this contract?' }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $contract->contract_number }} · {{ $person }}</p>
                </div>
            </div>
            <div class="px-5 py-4 space-y-4 text-sm text-gray-700">
                <p>
                    @if($role === 'approver')
                        Your signature is placed on <strong>every page</strong> and the contract goes back to Legal to be printed. This cannot be undone here.
                    @else
                        Your signature is placed on <strong>every page</strong> and the contract goes to Legal, who forwards it to the approver. This cannot be undone here.
                    @endif
                </p>
                <div class="border border-gray-200 rounded-lg p-3 flex items-center gap-4 bg-gray-50">
                    <div class="bg-white border border-dashed border-gray-300 rounded-md p-2 w-40 h-20 flex items-center justify-center">
                        @if($mySignature)<img src="{{ $mySignature }}" alt="Your signature from the master data" class="max-h-16 max-w-full object-contain">@endif
                    </div>
                    <div class="text-xs text-gray-500">Signature from the master data<br><strong class="text-gray-800 text-sm">{{ $signAs }}</strong></div>
                </div>
                <label class="flex items-start gap-2 cursor-pointer bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5">
                    <input type="checkbox" name="confirm" value="1" id="signConfirm" class="mt-0.5 rounded border-gray-300">
                    <span class="text-xs text-amber-900">I have read {{ $pages ? 'all ' . $pages . ' pages' : 'every page' }} of contract {{ $contract->contract_number }} and agree to sign it.</span>
                </label>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" data-close-modal class="px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" id="signSubmit" disabled class="px-5 py-2 text-sm font-semibold text-white primary-gradient rounded-lg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check mr-1"></i> {{ $role === 'approver' ? 'Approve & sign' : 'Confirm & sign' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Confirm: send back --}}
    <div id="revisionModal" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true" aria-labelledby="revisionTitle">
        <form method="POST" action="{{ route('profile.contracts.revision', $contract->contract_id) }}" class="bg-white rounded-xl shadow-xl w-full max-w-lg" data-unsaved-ignore>
            @csrf
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 id="revisionTitle" class="text-sm font-bold text-gray-900">Send this contract back to Legal?</h3>
                <p class="text-xs text-gray-500 mt-0.5">Nothing is signed. Legal changes it and sends it to you again.</p>
            </div>
            <div class="px-5 py-4">
                <label for="revisionNote" class="block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1">What has to be changed <span class="text-red-500">*</span></label>
                <textarea id="revisionNote" name="note" rows="4" maxlength="1000" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200" placeholder="e.g. Article 3 — the basic salary differs from the offering letter."></textarea>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" data-close-modal class="px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">Send back</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    (function () {
        const $ = id => document.getElementById(id);
        const open = el => { el.style.display = 'flex'; el.classList.remove('hidden'); };
        const close = el => { el.style.display = 'none'; el.classList.add('hidden'); };
        const sign = $('signModal'), rev = $('revisionModal');

        $('openSign')?.addEventListener('click', () => { $('signConfirm').checked = false; $('signSubmit').disabled = true; open(sign); });
        $('openRevision')?.addEventListener('click', () => { open(rev); $('revisionNote').focus(); });
        $('signConfirm')?.addEventListener('change', e => { $('signSubmit').disabled = !e.target.checked; });

        [sign, rev].forEach(m => {
            m.addEventListener('click', e => { if (e.target === m || e.target.closest('[data-close-modal]')) close(m); });
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { close(sign); close(rev); } });

        // One click only: a second submit must not sign twice.
        sign.querySelector('form').addEventListener('submit', e => {
            const b = $('signSubmit');
            if (b.dataset.sent) { e.preventDefault(); return; }
            b.dataset.sent = '1'; b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Signing…';
        });
    })();
    </script>
    @endpush
@endif
@endsection
