{{--
    My Profile → Contract approvals: the contracts Legal forwarded to me to review and sign, and the ones I already signed.
    No menu slug — a contract appears here only when it names me as its approver (MyContractController::approvals).
--}}
@extends('dashboard')
@section('title', 'Contract Approvals')
@section('page-title', 'Contract Approvals')
@section('page-subtitle', 'Contracts forwarded to you to review in detail and sign.')

@section('content')
<div class="w-full space-y-5 px-1 lg:px-2">
    @include('hr-general.recruitment.components.form-errors')

    <div class="grid grid-cols-2 gap-4 max-w-md">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-2xl font-bold {{ $pending ? 'text-amber-600' : 'text-gray-800' }} leading-none">{{ $pending }}</p>
            <p class="text-[11px] text-gray-500 mt-1">Waiting for you</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-2xl font-bold text-gray-800 leading-none">{{ $rows->count() - $pending }}</p>
            <p class="text-[11px] text-gray-500 mt-1">Already signed</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Contract</th><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Position</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($rows as $c)
                        @php $waiting = $c->sign_stage === 'with_approver'; @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3"><span class="font-semibold text-gray-800">{{ $c->contract_number }}</span><span class="block text-[11px] text-gray-400">{{ $c->contract_name }}</span></td>
                            <td class="px-4 py-3">{{ $c->person }}</td>
                            <td class="px-4 py-3">{{ $c->position ?: '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $c->start_date?->format('d M Y') }}<span class="block text-[11px] text-gray-400">until {{ $c->end_date?->format('d M Y') ?? 'permanent' }}</span></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $waiting ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">{{ $waiting ? 'Waiting for your signature' : 'Signed by you' }}</span>
                                @if($waiting && $c->sign_deadline)<span class="block text-[11px] text-gray-400 mt-0.5">Due {{ $c->sign_deadline->format('d M Y') }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('profile.contracts.show', $c->contract_id) }}" class="inline-flex px-3 py-1.5 rounded-lg text-[11px] font-semibold {{ $waiting ? 'primary-gradient text-white hover:opacity-90' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                                    {{ $waiting ? 'Review & sign' : 'View' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400"><i class="fas fa-inbox text-lg mb-2 block"></i>No contract has been forwarded to you.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
