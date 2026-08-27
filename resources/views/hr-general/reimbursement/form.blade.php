@extends('dashboard')

@php
    $isEdit = $mode === 'edit';
@endphp

@section('title', $isEdit ? 'Edit Reimbursement' : 'New Reimbursement')
@section('page-title', $isEdit ? 'Edit Reimbursement' : 'New Reimbursement')
@section('page-subtitle', $isEdit
    ? 'Document ' . $request->request_no
    : 'Create a reimbursement on behalf of an employee')

{{--
    Satu form untuk "New RB" dan Edit. Keduanya mengisi dokumen yang sama dengan
    aturan yang sama; memisahkannya menjadi dua berkas berarti dua tempat yang
    harus dijaga tetap sinkron.

    Variabel: $mode · $request · $settings · $branches · $steps · $employees
              $action · $backRoute · $minDate · $maxDate
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
            Documents cannot be created until at least one approval step exists in Reimbursement Settings.
        </p>
    </div>
    @endif

    @if($isEdit)
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">Editing a document that is already under review.</span>
            Items are rewritten in full and the total is recalculated. If the total changes, the document is
            flagged <em>Amount adjusted</em> and the change is written to the application log.
        </p>
    </div>
    @endif

    <form method="POST" action="{{ $action }}" id="reimbursementForm"
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
                Reimbursement Title <span class="text-red-600">*</span>
            </label>
            <input type="text" name="title" maxlength="200" required
                   minlength="{{ $settings->require_title_min_chars }}"
                   value="{{ old('title', $isEdit ? $request->title : '') }}"
                   placeholder="e.g. Operational expenses for this month"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                Supporting Document @if($settings->require_supporting_url)<span class="text-red-600">*</span>@endif
            </label>
            <input type="url" name="supporting_url" maxlength="1000"
                   @required($settings->require_supporting_url)
                   value="{{ old('supporting_url', $isEdit ? $request->supporting_url : '') }}"
                   placeholder="https://drive.google.com/..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            @if($settings->allowedSupportingHosts())
                <p class="text-xs text-gray-400 mt-1">Suggested links: {{ implode(', ', $settings->allowedSupportingHosts()) }}</p>
            @endif
            @error('supporting_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @include('hr-general.reimbursement._item_rows', ['existingItems' => $isEdit ? $request->items : null])

        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit"
                    @disabled(!$isEdit && $steps->isEmpty())
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-floppy-disk"></i>
                {{ $isEdit ? 'Save Changes' : 'Create Reimbursement' }}
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
@include('hr-general.reimbursement._item_rows_script')
@endpush
