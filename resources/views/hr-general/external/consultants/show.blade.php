@extends('dashboard')
@section('title', $c->name . ' — External Consultant')
@section('page-title', 'External Consultants')
@section('page-subtitle', 'Engagement and profile of one external consultant, read from the employee master data.')

@php
    $b = $profile['basic'];
    $ident = $profile['identification'];
    $addr = $profile['address'];
    $bank = $profile['bank'];
    $files = $profile['attachments'];
    $periodTone = ['in_period' => 'bg-green-100 text-green-700', 'upcoming' => 'bg-blue-100 text-blue-700', 'ended' => 'bg-red-100 text-red-700', 'none' => 'bg-gray-100 text-gray-500'];
    $dash = fn ($v) => trim((string) $v) !== '' ? $v : '—';
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y') : null;
    $field = fn (string $label, $value) => '<div><p class="text-[11px] text-gray-400">' . e($label) . '</p><p class="text-sm font-medium text-gray-800 break-words">' . e($dash($value)) . '</p></div>';
    $locked = fn (string $what) => '<p class="text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> ' . e($what) . ' is visible to holders of the matching permission in Employee Data only.</p>';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-full tone-primary flex items-center justify-center text-sm font-bold shrink-0">{{ $c->initials }}</span>
            <div>
                <h2 class="text-lg font-bold text-gray-800 leading-tight">{{ $c->name }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ $c->eci }} • {{ $c->position ?: '-' }}@if($c->managed_by) • Managed by {{ $c->managed_by }}@endif
                    <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $c->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('general.external.consultants.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i> Consultant List</a>
            @if($canDo('external.section.basic_data.view'))<a href="{{ route('general.external.consultants.edit', $c->employee_id) }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">Edit consultant</a>@endif
            @if($canDo('general.external.contracts'))<a href="{{ route('general.external.contracts.manage', $c->employee_id) }}" class="px-3 py-1.5 rounded-full primary-gradient text-white text-xs font-semibold hover:opacity-90">Manage External Contract</a>@endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Engagement --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Consultant Engagement</h3></div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                {!! $field('Vendor / partner', $c->vendor_partner) !!}
                {!! $field('Client / principal company', $c->client_company) !!}
                {!! $field('Assignment', $c->assignment_role) !!}
                {!! $field('Scheme', $c->scheme_label) !!}
                @if($canRate)
                    {!! $field('Rate', $c->rate_text ? $c->rate_text . ' ' . $c->rate_unit : null) !!}
                @endif
                {!! $field('Department', $c->department) !!}
                {!! $field('Position', $c->position) !!}
                {!! $field('Managed by', $c->managed_by) !!}
                <div class="sm:col-span-2">
                    <p class="text-[11px] text-gray-400">Engagement period</p>
                    <p class="text-sm font-medium text-gray-800">
                        @if($c->engagement_start || $c->engagement_end)
                            {{ $dt($c->engagement_start) ?? '—' }} until {{ $dt($c->engagement_end) ?? '—' }}
                            <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $periodTone[$c->period_state] }}">{{ $periodLabels[$c->period_state] }}</span>
                        @else — @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Quick Actions</h3></div>
            <div class="p-5 space-y-2">
                @foreach(\App\Support\External\ExternalRules::DOCUMENTS as $doc)
                    @if($canDo($doc['slug']))
                        <a href="{{ route($doc['route'], ['consultant' => $c->employee_id]) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-{{ $doc['icon'] }} w-4 text-center primary-text"></i> New {{ $doc['label'] }}
                        </a>
                    @endif
                @endforeach
                @if($canDo('general.external.contracts'))
                    <a href="{{ route('general.external.contracts.manage', $c->employee_id) }}#new-contract" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-file-contract w-4 text-center primary-text"></i> New External Contract
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Profile blocks: each follows the section permission it has in Employee Data --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Identity &amp; Biodata</h3></div>
            <div class="p-5 grid grid-cols-2 gap-x-6 gap-y-4">
                {!! $field('Consultant ID', $c->eci) !!}
                {!! $field('Gender', $b->gender ?? null) !!}
                {!! $field('Marital status', $b->marital_status ?? null) !!}
                {!! $field('Place of birth', $b->birth_place ?? null) !!}
                {!! $field('Date of birth', $dt($b->birth_date ?? null)) !!}
                {!! $field('Religion', $b->religion ?? null) !!}
                @if($ident !== null)
                    {!! $field('National ID (NIK)', $ident['nik']) !!}
                    {!! $field('Tax ID (NPWP)', $ident['npwp']) !!}
                @else
                    <div class="col-span-2">{!! $locked('Identification (NIK, NPWP)') !!}</div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Contact &amp; Address</h3></div>
            <div class="p-5 grid grid-cols-1 gap-y-4">
                @if($addr !== null)
                    {!! $field('Work email', $addr['email_work'] ?? null) !!}
                    {!! $field('Personal email', $addr['email_personal'] ?? null) !!}
                    {!! $field('Phone', $addr['phone'] ?? null) !!}
                    {!! $field('Address', $addr['line'] ?? null) !!}
                    {!! $field('City / province / postal code', $addr['city'] ?? null) !!}
                @else
                    {!! $locked('Contact and address') !!}
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Payment Account</h3></div>
            <div class="p-5 grid grid-cols-1 gap-y-4">
                @if($bank !== null)
                    {!! $field('Bank', $bank['bank'] ?? null) !!}
                    {!! $field('Account number', $bank['number'] ?? null) !!}
                    {!! $field('Account holder', $bank['holder'] ?? null) !!}
                @else
                    {!! $locked('The payment account') !!}
                @endif
            </div>
        </div>
    </div>

    {{-- Documents --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Documents &amp; Attachments</h3>
            <p class="text-[11px] text-gray-500 mt-0.5">Files are kept in the employee master data{{ $files !== null ? ' — ' . $files->count() . ' file' . ($files->count() === 1 ? '' : 's') : '' }}.</p>
        </div>
        @if($files === null)
            <div class="px-5 py-5">{!! $locked('Attachments') !!}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Document</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">File</th><th class="px-4 py-3">Uploaded</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse($files as $f)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-2.5 font-semibold text-gray-800">{{ $f->document_title ?: $f->document_type }}</td>
                                <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold">{{ $f->document_type ?: '-' }}</span></td>
                                <td class="px-4 py-2.5">{{ $f->file_name }}@if($f->file_size)<span class="block text-[11px] text-gray-400">{{ number_format($f->file_size / 1024, 1) }} KB</span>@endif</td>
                                <td class="px-4 py-2.5 whitespace-nowrap">{{ $f->created_at ? \Illuminate\Support\Carbon::parse($f->created_at)->format('d M Y H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No attachments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
