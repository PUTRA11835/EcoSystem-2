@extends('dashboard')

@section('title', 'Submit Reimbursement')
@section('page-title', 'Submit Reimbursement')
@section('page-subtitle', 'Claim expenses you already paid for. The request is verified before it is paid.')

@section('page-actions')
    <a href="{{ route('general.my-reimbursement.index') }}"
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
            Reimbursements cannot be submitted until HR sets up at least one approval step in
            Reimbursement Settings.
        </p>
    </div>
    @endif

    {{-- 🔴 Karyawan TIDAK dapat membatalkan dokumennya sendiri (Keputusan D111).
         Peringatan ini adalah satu-satunya pengaman yang tersisa di sisi
         karyawan, jadi ia ditampilkan sebelum form, bukan sesudahnya. --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-3">
        <p class="text-sm text-amber-900">
            <span class="font-semibold">Check the amounts before you submit.</span>
            A submitted reimbursement cannot be cancelled by you — only HR can edit or remove it.
        </p>
    </div>

    <form method="POST" action="{{ route('general.my-reimbursement.store') }}" id="reimbursementForm"
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
                <input type="text" value="Follows the branch chosen on the items" disabled
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-400">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                Reimbursement Title <span class="text-red-600">*</span>
            </label>
            <input type="text" name="title" maxlength="200" required
                   minlength="{{ $settings->require_title_min_chars }}"
                   value="{{ old('title') }}"
                   placeholder="e.g. Operational expenses for this month"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <p class="text-xs text-gray-400 mt-1">One line summarising the whole document. It appears on the recap and the printed form.</p>
            @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                Supporting Document @if($settings->require_supporting_url)<span class="text-red-600">*</span>@endif
            </label>
            <input type="url" name="supporting_url" maxlength="1000"
                   @required($settings->require_supporting_url)
                   value="{{ old('supporting_url') }}"
                   placeholder="https://drive.google.com/... "
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <p class="text-xs text-amber-700 mt-1">
                Make sure the link is readable by whoever needs to verify this reimbursement.
                The system cannot check that for you.
            </p>
            @if($settings->allowedSupportingHosts())
                <p class="text-xs text-gray-400 mt-1">
                    Accepted links: {{ implode(', ', $settings->allowedSupportingHosts()) }}
                </p>
            @endif
            @error('supporting_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @include('hr-general.reimbursement._item_rows', ['existingItems' => null])

        {{-- Aturan yang sedang berlaku. Ditampilkan apa adanya supaya pengguna
             tidak menebak-nebak batas yang tak terlihat. --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Current rules</p>
            <ul class="text-xs text-gray-600 space-y-1">
                <li>• Future dates: <strong>{{ $settings->allow_future_date ? 'allowed' : 'not allowed' }}</strong></li>
                <li>• Backdating: <strong>{{ $settings->hasBackdateLimit() ? 'up to ' . $settings->max_backdate_days . ' days' : 'no limit' }}</strong></li>
                <li>• Items per document: <strong>{{ $settings->hasItemLimit() ? 'up to ' . $settings->max_items_per_request : 'no limit' }}</strong></li>
                @if($settings->hasAmountLimit())
                <li>• Maximum total:
                    <strong>{{ \App\Models\Reimbursement\ReimbursementRequest::formatRupiah($settings->max_request_amount) }}</strong>
                    — {{ $settings->blocksOverLimit() ? 'submissions above this are refused' : 'going over is flagged for the reviewer, not blocked' }}
                </li>
                @endif
                <li>• Receipt number: <strong>{{ $settings->require_receipt_no ? 'required on every item' : 'optional — missing ones are flagged' }}</strong></li>
            </ul>
        </div>

        {{-- Blok tanda tangan. Teks mati: nilainya mengikuti Reimbursement
             Settings dan tidak dapat diubah dari form ini. --}}
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Signatures</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @foreach([
                    'Requester'   => $signatoryRequester ?? (session('user.name') ?? 'You'),
                    'Accounting'  => $settings->accountingSigner?->basicData?->nick_name ?? '—',
                    'Cashier'     => $settings->cashierSigner?->basicData?->nick_name ?? '—',
                    'Approved by' => $settings->approverSigner?->basicData?->nick_name ?? 'Last approver in the workflow',
                ] as $label => $name)
                <div class="border border-gray-200 rounded-lg px-3 py-2 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                    <p class="text-sm text-gray-800 mt-1">{{ $name }}</p>
                </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-2">Follows Reimbursement Settings — cannot be changed from this form.</p>
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
            <a href="{{ route('general.my-reimbursement.index') }}"
               class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@include('hr-general.reimbursement._item_rows_script')
<script>
    // Konfirmasi sebelum kirim. Karena karyawan tidak dapat membatalkan
    // dokumennya (Keputusan D111), inilah kesempatan terakhir memeriksa nominal.
    document.getElementById('reimbursementForm').addEventListener('submit', async function (event) {
        const form = event.currentTarget;
        if (form.dataset.confirmed === 'yes') return;

        // Biarkan validasi bawaan peramban berjalan lebih dulu.
        if (!form.checkValidity()) return;

        event.preventDefault();

        const total = document.getElementById('itemsTotal').textContent;
        const count = document.querySelectorAll('#itemRows .js-item-row').length;

        const ok = await showConfirm(
            `Submit ${count} item(s) totalling ${total}? You will not be able to cancel or edit it yourself afterwards.`,
            'Submit Reimbursement',
            'warning',
            { okText: 'Submit', cancelText: 'Check again' }
        );

        if (!ok) return;

        form.dataset.confirmed = 'yes';
        form.submit();
    });
</script>
@endpush
