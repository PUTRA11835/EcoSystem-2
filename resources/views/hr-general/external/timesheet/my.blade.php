@extends('dashboard')
@section('title', 'My Timesheet & Invoice')
@section('page-title', 'My Timesheet & Invoice')
@section('page-subtitle', 'Log your hours per month, submit them for approval, then upload your invoice.')

@php
    use App\Support\External\ExternalTimesheetRules as R;
    $canEdit = true; $canApprove = false; $canVerify = false; $canUploadFor = false;
    $u = $sheet ? [
        'save' => $saveUrl, 'submit' => $submitUrl, 'print' => route('general.external.my-timesheet.print', $sheet->id),
        'invoiceFile' => route('general.external.my-timesheet.invoice.file', $sheet->id), 'invoiceUpload' => route('general.external.my-timesheet.invoice.upload', $sheet->id),
    ] : [];
    $steps = [
        ['pen-to-square', 'Log hours', 'Add a row for each day you worked, then Submit.'],
        ['user-check', 'Approval', 'The Head of RPMO approves, or returns it with a reason.'],
        ['file-invoice-dollar', 'Invoice', 'After approval, upload your invoice (PDF).'],
        ['money-check-dollar', 'Payment', 'Finance verifies the invoice and pays within the payment term.'],
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')
    @include('hr-general.contracts.components.confirm-forms')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif

    {{-- How it works --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($steps as $i => [$icon, $title, $text])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-start gap-3">
                <span class="w-9 h-9 rounded-lg tone-primary flex items-center justify-center shrink-0"><i class="fas fa-{{ $icon }}"></i></span>
                <div><p class="text-xs font-bold text-gray-800">{{ $i + 1 }}. {{ $title }}</p><p class="text-[11px] text-gray-500 mt-0.5">{{ $text }}</p></div>
            </div>
        @endforeach
    </div>

    @if(!$periods)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-12 text-center">
            <span class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 inline-flex items-center justify-center text-lg mb-3"><i class="fas fa-file-contract"></i></span>
            <h2 class="text-base font-bold text-gray-800">No active contract yet</h2>
            <p class="text-sm text-gray-500 mt-1 max-w-lg mx-auto">Your timesheet is tied to your external contract. When HR activates your contract, its months appear here.</p>
        </div>
    @else
        {{-- Months --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Months of your contract</p>
            <div class="flex flex-wrap gap-2">
                @foreach($periods as $p)
                    @php $active = $sheet && (int) $p['sheet_id'] === (int) $sheet->id; @endphp
                    @if($p['sheet_id'])
                        <a href="{{ route('general.external.my-timesheet.index', ['sheet' => $p['sheet_id']]) }}"
                           class="px-3 py-2 rounded-lg border text-xs {{ $active ? 'primary-gradient text-white border-transparent shadow-sm' : 'border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                            <span class="font-semibold">{{ $p['label'] }}</span>
                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $active ? 'bg-white bg-opacity-25 text-white' : R::STATUS_BADGES[$p['status']] }}">{{ R::STATUS_LABELS[$p['status']] }}</span>
                        </a>
                    @else
                        <form method="POST" action="{{ route('general.external.my-timesheet.open') }}" data-unsaved-ignore>@csrf
                            <input type="hidden" name="contract_id" value="{{ $p['contract_id'] }}"><input type="hidden" name="year" value="{{ $p['year'] }}"><input type="hidden" name="month" value="{{ $p['month'] }}">
                            <button class="px-3 py-2 rounded-lg border border-dashed border-gray-300 text-xs text-gray-600 hover:bg-gray-50" title="Start the timesheet of this month"><i class="fas fa-plus mr-1"></i> {{ $p['label'] }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>

        @if($sheet)
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">{{ $sheet->period_label }}
                        <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold align-middle {{ R::STATUS_BADGES[$sheet->status] }}">{{ R::STATUS_LABELS[$sheet->status] }}</span></h2>
                    <p class="text-xs text-gray-500">{{ $sheet->contract_number }} · {{ $sheet->sheet_no }}</p>
                </div>
                <a href="{{ $u['print'] }}" target="_blank" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-print mr-1"></i> Print</a>
            </div>
            @include('hr-general.external.timesheet.components.sheet')
        @else
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-10 text-center text-sm text-gray-500">Choose a month above, or start a new one with <i class="fas fa-plus mx-1"></i>.</div>
        @endif
    @endif
</div>
@endsection
