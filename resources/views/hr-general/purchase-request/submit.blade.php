@extends('dashboard')

@section('title', 'Submit Purchase Request')
@section('page-title', 'Submit Purchase Request')
@section('page-subtitle', 'Request goods or services that have not been bought yet')

@section('page-actions')
    <a href="{{ route('general.my-purchase-request.index') }}"
       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
        Back
    </a>
@endsection

@section('content')
<div class="max-w-6xl space-y-5">

    @if($steps->isEmpty())
    <div class="bg-red-50 border border-red-200 rounded-xl px-5 py-4">
        <p class="text-sm text-red-800 font-semibold">No approval step is configured.</p>
        <p class="text-sm text-red-700 mt-1">
            Purchase requests cannot be submitted until HR sets up at least one approval step in
            Purchase Request Settings.
        </p>
    </div>
    @endif

    <form method="POST" action="{{ route('general.my-purchase-request.store') }}" id="purchaseRequestForm"
          class="bg-white rounded-xl p-6 shadow-sm space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Document No.</label>
                <input type="text" value="Generated on submit" disabled
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-400">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                    Request Date <span class="text-red-600">*</span>
                </label>
                <input type="date" name="request_date" required
                       value="{{ old('request_date', now()->toDateString()) }}"
                       @if($minDate) min="{{ $minDate }}" @endif
                       @if($maxDate) max="{{ $maxDate }}" @endif
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                @error('request_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Charged To</label>
                <input type="text" value="Follows the cost centers chosen on the items" disabled
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-400">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                Request Summary <span class="text-red-600">*</span>
            </label>
            <input type="text" name="title" maxlength="200" required
                   minlength="{{ $settings->require_title_min_chars }}"
                   value="{{ old('title') }}"
                   placeholder="e.g. Equipment for the Yogyakarta team"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <p class="text-xs text-gray-400 mt-1">
                One line summarising the whole request. It appears on the recap and the printed form —
                so write what the request is for, not just the first item.
            </p>
            @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Notes</label>
            <textarea name="notes" rows="2" maxlength="2000"
                      placeholder="Anything the approver should know — optional"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">{{ old('notes') }}</textarea>
            @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @include('hr-general.purchase-request._item_rows', ['existingItems' => null])

        {{-- Aturan yang sedang berlaku. Ditampilkan apa adanya supaya pengguna
             tidak menebak-nebak batas yang tak terlihat. --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Current rules</p>
            <ul class="text-xs text-gray-600 space-y-1">
                <li>• Future dates: <strong>{{ $settings->allow_future_date ? 'allowed' : 'not allowed' }}</strong></li>
                <li>• Backdating: <strong>{{ $settings->hasBackdateLimit() ? 'up to ' . $settings->max_backdate_days . ' days' : 'no limit' }}</strong></li>
                <li>• Items per request: <strong>{{ $settings->hasItemLimit() ? 'up to ' . $settings->max_items_per_request : 'no limit' }}</strong></li>
                @if($settings->hasQtyLimit())
                <li>• Maximum quantity per item:
                    <strong>{{ \App\Models\PurchaseRequest\PurchaseRequestItem::formatQty($settings->max_qty_per_item) }}</strong>
                    — items above this are <strong>refused</strong>
                </li>
                @endif
                <li>• Use date: <strong>{{ $settings->require_use_date ? 'required on every item' : 'optional — missing ones are flagged' }}</strong></li>
                <li>• Period: <strong>{{ $settings->require_period ? 'required on every item' : 'optional — missing ones are flagged' }}</strong></li>
                <li>• Cost center: <strong>{{ $settings->require_cost_center_per_item ? 'required on every item' : 'optional — missing ones are flagged' }}</strong></li>
                <li>• Cancelling your own request:
                    <strong>{{ $settings->allow_requester_cancel ? 'allowed until the first approver acts' : 'not allowed' }}</strong>
                </li>
            </ul>
        </div>

        {{-- Blok Requester / Approver.

             Dropdown hanya muncul bila langkah PERTAMA ditandai "Chosen by
             requester" DAN benar-benar punya kandidat (Keputusan D126). Bila
             kandidatnya tunggal, dropdown tetap dirender dalam keadaan terkunci —
             supaya pemohon melihat siapa yang akan menerima dokumennya, bukan
             menebak. Nilainya tetap dikirim lewat input tersembunyi. --}}
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Requester &amp; Approver</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-gray-200 rounded-lg px-3 py-2 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Requester</p>
                    <p class="text-sm text-gray-800 mt-1">{{ $requesterName }}</p>
                </div>

                <div class="border border-gray-200 rounded-lg px-3 py-2 {{ $chooseApprover ? 'bg-white' : 'bg-gray-50' }}">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        {{ $firstStep?->name ?? 'Approver' }}
                        @if($chooseApprover)<span class="text-red-600">*</span>@endif
                    </p>

                    @if($chooseApprover)
                        <select name="approver_ids[{{ $firstStep->order_seq }}]" required
                                @disabled(count($approverCandidates) === 1)
                                class="w-full mt-1 px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-red-800 disabled:bg-gray-50 disabled:text-gray-600">
                            @if(count($approverCandidates) > 1)
                                <option value="">— choose an approver —</option>
                            @endif
                            @foreach($approverCandidates as $candidate)
                                <option value="{{ $candidate['id'] }}"
                                    @selected((string) old('approver_ids.' . $firstStep->order_seq) === (string) $candidate['id'] || count($approverCandidates) === 1)>
                                    {{ $candidate['name'] }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Select yang disabled TIDAK dikirim browser. Untuk
                             kandidat tunggal, nilainya dititipkan di sini. --}}
                        @if(count($approverCandidates) === 1)
                            <input type="hidden" name="approver_ids[{{ $firstStep->order_seq }}]"
                                   value="{{ $approverCandidates[0]['id'] }}">
                            <p class="text-xs text-gray-400 mt-1">Only one approver is configured for this step.</p>
                        @endif

                        @error('approver_ids.' . $firstStep->order_seq)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @else
                        <p class="text-sm text-gray-800 mt-1">{{ $firstStep?->approverLabel() ?? '—' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Set by the approval workflow — not chosen here.</p>
                    @endif
                </div>
            </div>

            @if($steps->count() > 1)
            <p class="text-xs text-gray-400 mt-2">
                After that: {{ $steps->skip(1)->pluck('name')->implode(' → ') }}
            </p>
            @endif
        </div>

        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit" id="submitBtn"
                    @disabled($steps->isEmpty())
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                Submit Request
            </button>
            <a href="{{ route('general.my-purchase-request.index') }}"
               class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@include('hr-general.purchase-request._item_rows_script')
<script>
    // Konfirmasi sebelum kirim. Berbeda dari Reimbursement, di sini pemohon MASIH
    // dapat menarik kembali dokumennya selama belum ditinjau (Keputusan D131) —
    // jadi teksnya menyebutkan itu, bukan memperingatkan sebaliknya.
    document.getElementById('purchaseRequestForm').addEventListener('submit', async function (event) {
        const form = event.currentTarget;
        if (form.dataset.confirmed === 'yes') return;

        // Biarkan validasi bawaan peramban berjalan lebih dulu.
        if (!form.checkValidity()) return;

        event.preventDefault();

        const count = document.getElementById('itemsCount').textContent;
        const qty   = document.getElementById('itemsQty').textContent;

        const ok = await showConfirm(
            `Submit ${count} totalling ${qty}?` +
            @json($settings->allow_requester_cancel
                ? ' You can still cancel it yourself until the first approver acts.'
                : ' You will not be able to cancel it yourself afterwards.'),
            'Submit Purchase Request',
            'warning',
            { okText: 'Submit', cancelText: 'Check again' }
        );

        if (!ok) return;

        form.dataset.confirmed = 'yes';
        form.submit();
    });
</script>
@endpush
