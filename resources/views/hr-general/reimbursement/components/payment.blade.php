{{--
    Payment state of an approved reimbursement: Awaiting payment → Paid. Shown on the detail page for both sides (employee: read only;
    Finance: record / reverse, when `$canPay` / `$canReversePayment` are set by the HR controller).

    Uses: $request (ReimbursementRequest), optional $canPay, $canReversePayment, $payRoute, $reversePaymentRoute.
--}}
@php $payment = $request->paymentStatus(); @endphp
@if($payment)
<div class="flex gap-3">
    <dt class="w-32 shrink-0 text-gray-500">Payment</dt>
    <dd class="min-w-0">
        @if($payment === 'paid')
            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><i class="fas fa-circle-check mr-1"></i>{{ $request->paymentLabel() }}</span>
            <p class="text-xs text-gray-500 mt-1">
                @if($request->payment_reference)Reference <span class="font-mono text-gray-700">{{ $request->payment_reference }}</span> · @endif
                Recorded by {{ $request->payer?->fullNameLabel() ?? '—' }}
            </p>
            @if($request->payment_note)<p class="text-xs text-gray-500 mt-0.5">{{ $request->payment_note }}</p>@endif
        @else
            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800"><i class="fas fa-hourglass-half mr-1"></i>Awaiting payment</span>
            @if($request->payment_note)<p class="text-xs text-gray-500 mt-1">{{ $request->payment_note }}</p>@endif
        @endif
    </dd>
</div>

@if(!empty($canPay))
@include('hr-general.reimbursement.components.date-picker')
<form method="POST" action="{{ $payRoute }}" class="mt-1 rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
    @csrf
    <p class="text-xs font-bold text-gray-700 uppercase tracking-wide">Record payment</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1" for="paidOn">Paid on</label>
            {{-- The app's calendar (data-date), never the native date box; it still submits Y-m-d. --}}
            <input type="text" data-date id="paidOn" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" data-max="{{ now()->toDateString() }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1" for="payRef">Transfer reference</label>
            <input type="text" id="payRef" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="Bank reference / voucher no."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
        </div>
    </div>
    <input type="text" name="note" maxlength="255" value="{{ old('note') }}" placeholder="Note (optional)"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
    <button type="submit" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90">
        <i class="fas fa-money-bill-transfer mr-1.5"></i> Mark as paid
    </button>
</form>
@endif

@if(!empty($canReversePayment))
<form method="POST" action="{{ $reversePaymentRoute }}" class="mt-1 flex flex-col sm:flex-row gap-2"
      data-confirm="The document goes back to “Awaiting payment” and the requester is told." data-confirm-title="Reverse this payment?" data-confirm-ok="Reverse" data-confirm-variant="danger">
    @csrf
    <input type="text" name="reason" required minlength="5" maxlength="200" placeholder="Why is the payment reversed?"
           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
    <button type="submit" class="px-4 py-2 border border-red-300 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-50 whitespace-nowrap">Reverse payment</button>
</form>
@endif
@endif
