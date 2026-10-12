@extends('dashboard')
@section('title', 'Payroll')
@section('page-title', 'Payroll')
@section('page-subtitle', 'Manage payroll periods, track calculation progress, and open monthly payroll details.')

@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $badge = ['open' => 'bg-blue-100 text-blue-700', 'locked' => 'bg-gray-800 text-white'];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.payroll.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Summary --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach([['Total periods', $summary['total'], 'text-gray-800'], ['Open', $summary['open'], 'text-blue-600'], ['Locked', $summary['locked'], 'text-green-600']] as [$label, $n, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="text-3xl font-bold mt-1 {{ $tone }}">{{ $n }}</p>
            </div>
        @endforeach
    </div>

    {{-- Create period --}}
    @if($caps['create'])
        <form method="POST" action="{{ route('finance.payroll.periods.store') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            @csrf
            <h3 class="text-sm font-bold text-gray-900 mb-3">Create period</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                <div class="md:col-span-2">
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Name</label>
                    <input type="text" name="name" maxlength="100" value="{{ old('name', $defaults['name']) }}" class="{{ $input }}">
                </div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Start</label>
                    <input type="date" name="period_start" value="{{ old('period_start', $defaults['start']) }}" class="{{ $input }}"></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">End</label>
                    <input type="date" name="period_end" value="{{ old('period_end', $defaults['end']) }}" class="{{ $input }}"></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Pay date</label>
                    <input type="date" name="pay_date" value="{{ old('pay_date', $defaults['pay']) }}" class="{{ $input }}"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end mt-3">
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Attendance period start</label>
                    <input type="date" name="attendance_start" value="{{ old('attendance_start', $defaults['start']) }}" class="{{ $input }}"></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Attendance period end</label>
                    <input type="date" name="attendance_end" value="{{ old('attendance_end', $defaults['end']) }}" class="{{ $input }}"></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Cut-off date (optional)</label>
                    <input type="date" name="cutoff_date" value="{{ old('cutoff_date') }}" class="{{ $input }}"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Attendance, leave and overtime are read from the attendance period. Workdays after the cut-off date are treated as fully present and corrected in the next payroll.</p>
            <div class="mt-3"><button type="submit" @disabled(!$enabled) class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90 disabled:opacity-50"><i class="fas fa-circle-plus mr-2"></i>Create period</button></div>
        </form>
    @endif

    {{-- Periods --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                        <th class="px-5 py-3 w-12">No</th><th class="px-3 py-3">Period</th><th class="px-3 py-3">Date</th><th class="px-3 py-3">Payroll run</th>
                        <th class="px-3 py-3">Amount</th><th class="px-3 py-3">Status</th><th class="px-5 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($periods as $i => $p)
                        <tr>
                            <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-3"><p class="font-semibold text-gray-900">{{ $p->name }}</p><p class="text-xs text-gray-500">#{{ $p->id }}</p></td>
                            <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($p->period_start)->format('d M Y') }} – {{ \Carbon\Carbon::parse($p->period_end)->format('d M Y') }}<p class="text-xs text-gray-500">Pay date: {{ \Carbon\Carbon::parse($p->pay_date)->format('d M Y') }}</p></td>
                            <td class="px-3 py-3 text-gray-600">
                                @if($p->calculated_at)
                                    {{ $p->employee_count }} employees calculated
                                    <p class="text-xs text-gray-500">{{ $p->status === 'locked' ? 'Locked ' . \Carbon\Carbon::parse($p->locked_at)->format('d M Y') : ($p->slips_generated_at ? 'Payslips generated' : 'Payslips not generated') }}</p>
                                @else
                                    <span class="text-gray-400">Not calculated yet</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-600 tabular-nums">
                                @if($p->calculated_at)
                                    <p>Take-home <span class="font-bold text-gray-900">Rp {{ $m($p->total_take_home) }}</span></p>
                                    <p>Gross Rp {{ $m($p->total_gross) }} · Deductions Rp {{ $m($p->total_deductions) }}</p>
                                @else — @endif
                            </td>
                            <td class="px-3 py-3"><span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full {{ $badge[$p->status] ?? '' }}">{{ \App\Support\Payroll\PayrollPeriodRules::STATUSES[$p->status] ?? $p->status }}</span></td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <a href="{{ route('finance.payroll.periods.show', $p->id) }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50">Open detail</a>
                                @if($p->status === 'open' && $caps['edit'])
                                    <form method="POST" action="{{ route('finance.payroll.periods.calculate', $p->id) }}" class="inline">@csrf
                                        <button type="submit" @disabled(!$enabled) class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-green-300 text-green-700 hover:bg-green-50 disabled:opacity-50">{{ $p->calculated_at ? 'Recalculate' : 'Calculate' }}</button></form>
                                @endif
                                @if($p->status === 'open' && $caps['delete'])
                                    <form method="POST" action="{{ route('finance.payroll.periods.destroy', $p->id) }}" class="inline" data-confirm="Delete {{ $p->name }} with all its payslips and corrections? This cannot be undone." data-confirm-title="Delete payroll period" data-confirm-ok="Delete" data-confirm-variant="danger">@csrf
                                        <button type="submit" @disabled(!$enabled) class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50">Delete</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">No payroll period yet. Create the first one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
