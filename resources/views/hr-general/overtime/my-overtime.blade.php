@extends('dashboard')

@section('title', 'Overtime')
@section('page-title', 'Overtime')
@section('page-subtitle', 'Submit and track your own overtime requests')

@section('page-actions')
    <a href="{{ route('general.my-overtime.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Submit Overtime
    </a>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Dasar hukum. Ditampilkan tetap, bukan di halaman bantuan terpisah:
         karyawan perlu tahu atas dasar apa lemburnya dihitung, tepat di tempat
         ia mengajukannya. --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">Legal basis:</span>
            Article 78 of the Manpower Law and Government Regulation No. 35 of 2021 on working time,
            rest time, and overtime.
        </p>
    </div>

    {{-- Kartu ringkasan bulan berjalan --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Submitted',      'value' => $summary['submitted'], 'class' => 'text-gray-900'],
            ['label' => 'Approved',       'value' => $summary['approved'],  'class' => 'text-green-600'],
            ['label' => 'Waiting Review', 'value' => $summary['pending'],   'class' => 'text-amber-600'],
            ['label' => 'Approved Hours', 'value' => \App\Models\Overtime\OvertimeRequest::formatMinutes($summary['approved_minutes']), 'class' => 'text-red-800'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $month }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Overtime Requests</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    A request can only be cancelled while it is still waiting for the first reviewer.
                </p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Overtime No.</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Duration</th>
                        <th class="px-4 py-3">Approval</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 w-24 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $index => $request)
                    <tr class="hover:bg-gray-50 transition-colors align-top">
                        <td class="px-4 py-3 text-gray-400">{{ $index + 1 }}</td>

                        <td class="px-4 py-3">
                            <span class="font-mono text-xs text-red-800 font-semibold">{{ $request->request_no }}</span>
                            @if($request->hasFlag(\App\Models\Overtime\OvertimeRequest::FLAG_TIME_ADJUSTED))
                                <span class="block mt-1 text-xs text-purple-600">
                                    Adjusted from
                                    {{ substr($request->original_start_time, 0, 5) }}–{{ substr($request->original_end_time, 0, 5) }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-gray-900">{{ $request->overtime_date->format('d M Y') }}</div>
                            <div class="text-xs text-gray-400">{{ $request->dayTypeLabel() }}</div>
                        </td>

                        <td class="px-4 py-3 font-mono text-xs text-gray-700 whitespace-nowrap">
                            {{ substr($request->start_time, 0, 5) }} – {{ substr($request->end_time, 0, 5) }}
                            @if($request->crosses_midnight)
                                <span class="block text-gray-400">next day</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-gray-900 font-medium whitespace-nowrap">
                            {{ $request->durationLabel() }}
                        </td>

                        {{-- Jejak persetujuan ditampilkan di sini supaya karyawan
                             tahu pengajuannya sedang menunggu siapa, tanpa harus
                             bertanya ke HR. --}}
                        <td class="px-4 py-3 text-xs">
                            @foreach($request->approvals as $approval)
                                <div class="flex items-center gap-1.5 {{ !$loop->last ? 'mb-1' : '' }}">
                                    @php
                                        $dot = match($approval->status) {
                                            'approved' => 'bg-green-500',
                                            'rejected' => 'bg-red-500',
                                            'skipped'  => 'bg-gray-300',
                                            default    => 'bg-amber-400',
                                        };
                                    @endphp
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dot }} shrink-0"></span>
                                    <span class="text-gray-600">{{ $approval->step_name }}</span>
                                    @if($approval->acted_at)
                                        <span class="text-gray-400">· {{ $approval->acted_at->format('d M') }}</span>
                                    @endif
                                </div>
                                @if($approval->notes)
                                    <div class="ml-3 mb-1 text-gray-400 italic">"{{ $approval->notes }}"</div>
                                @endif
                            @endforeach
                        </td>

                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @php
                                $badge = match($request->status) {
                                    'approved'  => 'bg-green-100 text-green-700',
                                    'rejected'  => 'bg-red-100 text-red-700',
                                    'cancelled' => 'bg-gray-100 text-gray-500',
                                    'in_review' => 'bg-blue-100 text-blue-700',
                                    default     => 'bg-amber-100 text-amber-700',
                                };
                            @endphp
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge }}">
                                {{ $request->statusLabel() }}
                            </span>

                            @if($request->hasFlag(\App\Models\Overtime\OvertimeRequest::FLAG_FUTURE_CLAIM))
                                <span class="block mt-1 text-xs text-gray-400">future date</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-center">
                            @if($request->isCancellable())
                            <form method="POST" action="{{ route('general.my-overtime.cancel', $request) }}"
                                  class="js-cancel-form" data-no="{{ $request->request_no }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 bg-white text-red-700 text-xs font-semibold rounded-lg border border-red-200 hover:bg-red-50 transition-all">
                                    Cancel
                                </button>
                            </form>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            No overtime requests yet.
                            <a href="{{ route('general.my-overtime.create') }}" class="text-red-800 font-semibold hover:underline">Submit your first one.</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Konfirmasi sebelum membatalkan. Memakai showConfirm() global, bukan
    // confirm() bawaan browser, mengikuti pola seluruh aplikasi.
    document.querySelectorAll('.js-cancel-form').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') {
                return;
            }

            event.preventDefault();

            const ok = await showConfirm(
                `Request ${form.dataset.no} will be cancelled and can no longer be reviewed. Continue?`,
                'Cancel Overtime Request',
                'danger',
                { okText: 'Cancel Request', cancelText: 'Keep It' }
            );

            if (!ok) return;

            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });
</script>
@endpush
