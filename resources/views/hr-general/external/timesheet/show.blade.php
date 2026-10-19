@extends('dashboard')
@section('title', $sheet->name . ' — ' . $sheet->period_label)
@section('page-title', 'Timesheet & Payment')
@section('page-subtitle', 'Review the hours of one external consultant for one month.')

@php
    use App\Support\External\ExternalTimesheetRules as R;
    $u = [
        'save' => $saveUrl, 'submit' => $submitUrl,
        'print' => route('general.external.timesheet.print', $sheet->id), 'invoiceFile' => route('general.external.timesheet.invoice.file', $sheet->id),
        'invoiceUpload' => route('general.external.timesheet.invoice.upload', $sheet->id),
        'approve' => route('general.external.timesheet.approve', $sheet->id), 'return' => route('general.external.timesheet.return', $sheet->id),
        'verify' => route('general.external.timesheet.invoice.verify', $sheet->id), 'reject' => route('general.external.timesheet.invoice.reject', $sheet->id),
        'paid' => route('general.external.timesheet.invoice.paid', $sheet->id),
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.external.timesheet.components.tabs')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
            <p class="text-[11px] text-gray-400"><a href="{{ $tabTimesheets ? route('general.external.timesheet.index') : route('general.external.timesheet.invoices') }}" class="hover:underline">← Timesheet &amp; Payment</a></p>
            <h2 class="text-lg font-bold text-gray-800 leading-tight">{{ $sheet->name }} · {{ $sheet->period_label }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $sheet->eci }} · {{ $sheet->contract_number }} · {{ $sheet->sheet_no }}
                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ R::STATUS_BADGES[$sheet->status] }}">{{ R::STATUS_LABELS[$sheet->status] }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $u['print'] }}" target="_blank" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-print mr-1"></i> Print</a>
            @if($sheet->status === R::DRAFT && $sheet->submitted_at === null && $canEdit)
                <form method="POST" action="{{ route('general.external.timesheet.destroy', $sheet->id) }}" data-confirm="Delete this draft? This cannot be undone." data-confirm-title="Delete Draft" data-confirm-ok="Delete" data-unsaved-ignore>@csrf
                    <button class="px-3 py-1.5 rounded-full border border-red-300 text-red-600 text-xs font-semibold hover:bg-red-50">Delete draft</button></form>
            @endif
        </div>
    </div>

    @include('hr-general.external.timesheet.components.sheet')
</div>
@endsection
