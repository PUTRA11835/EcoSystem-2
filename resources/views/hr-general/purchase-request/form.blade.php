@extends('dashboard')

@php
    $isEdit = $mode === 'edit';
@endphp

@section('title', $isEdit ? 'Edit Purchase Request' : 'New Purchase Request')
@section('page-title', $isEdit ? 'Edit Purchase Request' : 'New Purchase Request')
@section('page-subtitle', $isEdit
    ? 'Document ' . $request->request_no
    : 'Create a purchase request on behalf of an employee')

{{--
    Satu form untuk "New PR" dan Edit. Keduanya mengisi dokumen yang sama dengan
    aturan yang sama; memisahkannya menjadi dua berkas berarti dua tempat yang
    harus dijaga tetap sinkron.

    Variabel: $mode · $request · $settings · $costCenters · $steps · $employees
              $action · $backRoute · $minDate · $maxDate
              $firstStep · $chooseApprover · $approverCandidates
--}}

@section('page-actions')
    <a href="{{ $backRoute }}"
       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
        Back
    </a>
@endsection

@section('content')
<div class="max-w-6xl space-y-5">

    @if(!$isEdit && $steps->isEmpty())
    <div class="bg-red-50 border border-red-200 rounded-xl px-5 py-4">
        <p class="text-sm text-red-800 font-semibold">No approval step is configured.</p>
        <p class="text-sm text-red-700 mt-1">
            Requests cannot be created until at least one approval step exists in
            Purchase Request Settings.
        </p>
    </div>
    @endif

    @if($isEdit)
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">Editing a request that is already under review.</span>
            Items are rewritten in full and the summary is recalculated. If the item count or the
            quantity summary changes, the request is flagged <em>Items adjusted</em> and the change is
            written to the application log.
        </p>
        <p class="text-xs text-blue-800 mt-1">
            The approval workflow is <strong>not</strong> restarted — approvals that already happened
            stay, and the approver waiting now stays the same.
        </p>
    </div>
    @endif

    <form method="POST" action="{{ $action }}" id="purchaseRequestForm"
          class="bg-white rounded-xl p-6 shadow-sm space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @if($isEdit)
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Document No.</label>
                <input type="text" value="{{ $request->request_no }}" disabled
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-500 font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Requester</label>
                <input type="text" disabled
                       value="{{ $request->employee?->basicData?->nick_name ?? $request->employee?->eci ?? '—' }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-400 mt-1">The requester cannot be changed after submission.</p>
            </div>
            @else
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                    Employee <span class="text-red-600">*</span>
                </label>
                <select name="employee_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <option value="">— choose an employee —</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected((string) old('employee_id') === (string) $employee->employee_id)>
                            {{ $employee->basicData?->nick_name ?? $employee->eci }} — {{ $employee->eci }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Your name is recorded as the creator of this document.</p>
                @error('employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                    Request Date <span class="text-red-600">*</span>
                </label>
                <input type="date" name="request_date" required
                       value="{{ old('request_date', $isEdit ? $request->request_date->toDateString() : now()->toDateString()) }}"
                       @if($minDate) min="{{ $minDate }}" @endif
                       @if($maxDate) max="{{ $maxDate }}" @endif
                       @disabled($isEdit)
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 @if($isEdit) bg-gray-50 text-gray-500 @endif">
                @if($isEdit)
                    {{-- Tanggal dokumen ikut menentukan nomor dan periodenya;
                         mengubahnya setelah nomor terbit akan membuat nomor dan
                         tanggal saling bertentangan. --}}
                    <input type="hidden" name="request_date" value="{{ $request->request_date->toDateString() }}">
                    <p class="text-xs text-gray-400 mt-1">Locked — the document number is derived from this date.</p>
                @endif
                @error('request_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                Request Summary <span class="text-red-600">*</span>
            </label>
            <input type="text" name="title" maxlength="200" required
                   minlength="{{ $settings->require_title_min_chars }}"
                   value="{{ old('title', $isEdit ? $request->title : '') }}"
                   placeholder="e.g. Equipment for the Yogyakarta team"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Notes</label>
            <textarea name="notes" rows="2" maxlength="2000"
                      placeholder="Anything the approver should know — optional"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">{{ old('notes', $isEdit ? $request->notes : '') }}</textarea>
            @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @include('hr-general.purchase-request._item_rows', ['existingItems' => $isEdit ? $request->items : null])

        {{-- Approver hanya ditanyakan saat MEMBUAT. Pada Edit, alur persetujuan
             dokumen sudah dibekukan sejak ia dibuat — menanyakannya lagi akan
             menyiratkan bahwa penyetujunya masih bisa diganti, padahal tidak
             (Keputusan D126). --}}
        @if(!$isEdit && $chooseApprover)
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Approver</h3>
            <div class="md:w-1/2 border border-gray-200 rounded-lg px-3 py-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    {{ $firstStep->name }} <span class="text-red-600">*</span>
                </p>

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

                {{-- Select yang disabled TIDAK dikirim browser. Untuk kandidat
                     tunggal, nilainya dititipkan di sini. --}}
                @if(count($approverCandidates) === 1)
                    <input type="hidden" name="approver_ids[{{ $firstStep->order_seq }}]"
                           value="{{ $approverCandidates[0]['id'] }}">
                    <p class="text-xs text-gray-400 mt-1">Only one approver is configured for this step.</p>
                @endif

                @error('approver_ids.' . $firstStep->order_seq)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        @endif

        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit"
                    @disabled(!$isEdit && $steps->isEmpty())
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-floppy-disk"></i>
                {{ $isEdit ? 'Save Changes' : 'Create Purchase Request' }}
            </button>
            <a href="{{ $backRoute }}"
               class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@include('hr-general.purchase-request._item_rows_script')
@endpush
