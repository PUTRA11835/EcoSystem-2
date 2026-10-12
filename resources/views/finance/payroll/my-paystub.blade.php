@extends('dashboard')
@section('title', 'Paystub')
@section('page-title', 'Paystub')
@section('page-subtitle', 'Your payslips published by Finance. Download them as PDF in Indonesian or English.')

@php $m = fn ($n) => \App\Support\Payroll\Money::format($n); @endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                    <th class="px-5 py-3">Period</th><th class="px-3 py-3">Slip no.</th><th class="px-3 py-3 text-right">Earnings</th>
                    <th class="px-3 py-3 text-right">Deductions</th><th class="px-3 py-3 text-right">Take-home pay</th><th class="px-5 py-3">Download</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($slips as $s)
                        <tr>
                            <td class="px-5 py-3"><p class="font-semibold text-gray-900">{{ $s->name }}</p>
                                <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($s->period_start)->format('d M Y') }} – {{ \Carbon\Carbon::parse($s->period_end)->format('d M Y') }} · Published {{ \Carbon\Carbon::parse($s->published_at)->format('d M Y') }}</p></td>
                            <td class="px-3 py-3 text-gray-600 tabular-nums">#{{ str_pad($s->slip_no, 5, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $m($s->gross_earnings) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $m($s->total_deductions) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums font-bold text-gray-900">Rp {{ $m($s->take_home_pay) }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <a href="{{ route('general.my-paystub.pdf', [$s->id, 'lang' => 'id']) }}" class="px-2.5 py-1 text-xs font-semibold rounded border border-indigo-200 text-indigo-700 hover:bg-indigo-50"><i class="fas fa-download mr-1"></i>Indonesian</a>
                                <a href="{{ route('general.my-paystub.pdf', [$s->id, 'lang' => 'en']) }}" class="px-2.5 py-1 text-xs font-semibold rounded border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-download mr-1"></i>English</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">No payslip has been published for you yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
