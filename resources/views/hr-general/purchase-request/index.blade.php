@extends('dashboard')

@section('title', 'Purchase Requests')
@section('page-title', 'Purchase Requests')
@section('page-subtitle', 'Review and process employee purchase requests')

@section('page-actions')
    <div class="flex flex-wrap items-center gap-2">
        @if($can('general.purchase-request.export'))
        <a href="{{ route('general.purchase-request.export', request()->query()) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-white text-green-700 text-sm font-semibold rounded-lg border border-green-300 hover:bg-green-50 transition-all">
            <i class="fas fa-file-excel"></i> Monthly Export
        </a>
        @endif

        @if($can('general.purchase-request.create'))
        <a href="{{ route('general.purchase-request.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <i class="fas fa-plus"></i> New PR
        </a>
        @endif
    </div>
@endsection

@section('content')
@php use App\Models\PurchaseRequest\PurchaseRequest; @endphp

<div class="space-y-5">

    {{-- Kartu ringkasan. `Cancelled` berdiri sendiri, tidak digabung ke
         `Rejected`: yang satu ditarik pemohon sebelum ditinjau, yang lain
         diputuskan penyetuju. Menggabungkannya membuat rekap tidak dapat
         menjawab "berapa yang benar-benar ditolak?". --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach([
            ['label' => 'Waiting for You', 'value' => $counts['mine'],      'class' => 'text-red-800'],
            ['label' => 'Pending Total',   'value' => $counts['pending'],   'class' => 'text-amber-600'],
            ['label' => 'Approved',        'value' => $counts['approved'],  'class' => 'text-green-600'],
            ['label' => 'Rejected',        'value' => $counts['rejected'],  'class' => 'text-red-600'],
            ['label' => 'Cancelled',       'value' => $counts['cancelled'], 'class' => 'text-gray-400'],
        ] as $card)
        <div class="bg-white rounded-xl p-5 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-2xl font-bold mt-1 {{ $card['class'] }}">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="mb-6 pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Purchase Requests</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                A request moves to the next step only after the current one is approved.
                Rows needing attention are highlighted.
            </p>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('general.purchase-request.index') }}"
              class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Month</label>
                    <input type="month" name="month" value="{{ $filters['month'] }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        @php
                            $statuses = [
                                'open'      => 'Open',
                                'approved'  => 'Approved',
                                'rejected'  => 'Rejected',
                                'cancelled' => 'Cancelled',
                                'all'       => 'All',
                            ];
                            if ($canManage) { $statuses['deleted'] = 'Deleted'; }
                        @endphp
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Scope</label>
                    <select name="scope" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="all"  @selected($filters['scope'] === 'all')>Everyone</option>
                        <option value="mine" @selected($filters['scope'] === 'mine')>Waiting for me</option>
                    </select>
                </div>

                <div class="md:col-span-5">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Document no, requester, summary..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <div class="md:col-span-12 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">Apply</button>
                    <a href="{{ route('general.purchase-request.index') }}"
                       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Reset</a>
                </div>
            </div>
        </form>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Document No.</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Requester</th>
                        <th class="px-4 py-3">Approver</th>
                        <th class="px-4 py-3 w-36">Qty</th>
                        <th class="px-4 py-3 w-52">Status</th>
                        <th class="px-4 py-3 w-40">Log Date</th>
                        <th class="px-4 py-3 w-36 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $index => $request)
                    @php
                        $attention = $request->attentionFlags();
                        $isMine    = in_array($request->id, $mineIds, true);
                        $isDeleted = $request->trashed();
                    @endphp
                    <tr class="align-top hover:bg-gray-50 transition-colors {{ $isDeleted ? 'opacity-60' : ($attention ? 'bg-amber-50/60' : '') }}">
                        <td class="px-4 py-3 text-gray-400">{{ $requests->firstItem() + $index }}</td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('general.purchase-request.show', $request) }}"
                               class="font-mono text-xs text-red-800 font-semibold hover:underline">
                                {{ $request->request_no }}
                            </a>
                            @if($isMine)
                                <span class="block mt-1 px-1.5 py-0.5 bg-red-800 text-white text-[10px] font-bold rounded uppercase tracking-wide text-center">Your turn</span>
                            @endif
                            @if($isDeleted)
                                <span class="block mt-1 text-[10px] text-gray-500">deleted</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap text-gray-900">
                            {{ $request->request_date->format('Y.m.d') }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-gray-900">{{ $request->title }}</div>
                            <div class="text-xs text-gray-400">
                                {{ $request->charged_to_label ?? '—' }} · {{ $request->itemCountLabel() }}
                            </div>
                            @if($attention)
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                @foreach($attention as $flag)
                                    <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-semibold rounded">
                                        {{ PurchaseRequest::FLAG_LABELS[$flag] ?? $flag }}
                                    </span>
                                @endforeach
                            </div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $request->employee?->basicData?->nick_name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $request->employee?->eci }}</div>
                        </td>

                        {{-- Untuk langkah yang penyetujunya dipilih pemohon, label
                             ini berbunyi "Nama (chosen by requester)" — sehingga
                             jelas kenapa penyetujunya orang itu (Keputusan D126). --}}
                        <td class="px-4 py-3 text-gray-700">{{ $request->approverLabel() }}</td>

                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $request->qtySummaryLabel() }}</td>

                        <td class="px-4 py-3">
                            {{-- "Pending" di rekap, "Waiting" di detail — mengikuti
                                 aplikasi acuan. Dua penulisan untuk SATU keadaan,
                                 bukan dua keadaan berbeda. --}}
                            <div class="font-semibold {{ match($request->status) {
                                'approved'  => 'text-green-700',
                                'rejected'  => 'text-red-700',
                                'cancelled' => 'text-gray-500',
                                default     => 'text-blue-700',
                            } }}">
                                {{ $request->statusLabel('Pending') }}
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                @if($request->isOpen())
                                    Current step: {{ $request->currentStepName() ?? '—' }}
                                @elseif($request->completed_at)
                                    {{ PurchaseRequest::STATUS_LABELS[$request->status] ?? ucfirst($request->status) }}:
                                    {{ $request->completed_at->format('d.m.Y') }}
                                @endif
                            </div>
                            @if($isDeleted && $request->delete_reason)
                                <div class="text-xs text-gray-500 italic mt-1">"{{ $request->delete_reason }}"</div>
                            @endif
                        </td>

                        {{-- Jejak waktu baris. `updated_at` berubah setiap dokumen
                             disetujui, dibatalkan, atau diedit — jadi ia menjawab
                             "terakhir disentuh kapan", bukan sekadar kapan dibuat. --}}
                        <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                            <div>Created : {{ $request->created_at?->format('d.m.Y') ?? '—' }}</div>
                            <div>Update&nbsp; : {{ $request->updated_at?->format('d.m.Y') ?? '—' }}</div>
                        </td>

                        <td class="px-4 py-3">
                            {{-- Urutan: baca -> ubah -> keluarkan -> putuskan -> buang.
                                 Delete sengaja di ujung: pada aplikasi acuan ia berada
                                 di antara ikon lain, dan tiga ikon berdempetan yang
                                 salah satunya menghapus dokumen mengundang salah klik. --}}
                            <div class="flex items-center justify-center gap-0.5 flex-wrap">
                                <a href="{{ route('general.purchase-request.show', $request) }}" title="View details"
                                   class="px-1.5 py-1.5 text-blue-700 hover:bg-blue-50 rounded transition-all">
                                    <i class="fas fa-file-lines text-xs"></i>
                                </a>

                                @if(in_array($request->id, $editableIds, true))
                                <a href="{{ route('general.purchase-request.edit', $request) }}" title="Edit"
                                   class="px-1.5 py-1.5 text-amber-600 hover:bg-amber-50 rounded transition-all">
                                    <i class="fas fa-pen-to-square text-xs"></i>
                                </a>
                                @endif

                                <a href="{{ route('general.purchase-request.print', $request) }}" target="_blank" title="Print"
                                   class="px-1.5 py-1.5 text-gray-600 hover:bg-gray-100 rounded transition-all">
                                    <i class="fas fa-print text-xs"></i>
                                </a>

                                @if($can('general.purchase-request.export'))
                                <a href="{{ route('general.purchase-request.export.single', $request) }}" title="Export Excel"
                                   class="px-1.5 py-1.5 text-green-700 hover:bg-green-50 rounded transition-all">
                                    <i class="fas fa-file-excel text-xs"></i>
                                </a>
                                @endif

                                @if($can('general.purchase-request.approve') && $request->isOpen() && $isMine)
                                <form method="POST" action="{{ route('general.purchase-request.approve', $request) }}"
                                      class="js-quick-approve" data-no="{{ $request->request_no }}">
                                    @csrf
                                    <button type="submit" title="Approve"
                                            class="px-1.5 py-1.5 text-green-700 hover:bg-green-50 rounded transition-all">
                                        <i class="fas fa-circle-check text-xs"></i>
                                    </button>
                                </form>

                                {{-- Penolakan TIDAK disediakan di daftar: ia menuntut
                                     alasan tertulis, dan itu milik halaman detail. --}}
                                <a href="{{ route('general.purchase-request.show', $request) }}" title="Reject — opens the document"
                                   class="px-1.5 py-1.5 text-red-700 hover:bg-red-50 rounded transition-all">
                                    <i class="fas fa-circle-xmark text-xs"></i>
                                </a>
                                @endif

                                {{-- Menghapus menuntut alasan tertulis, jadi ia
                                     membuka dokumennya — bukan menghapus dari
                                     daftar dengan satu klik. --}}
                                @if($canManage && !$isDeleted)
                                <a href="{{ route('general.purchase-request.show', $request) }}" title="Delete — opens the document"
                                   class="px-1.5 py-1.5 text-red-600 hover:bg-red-50 rounded transition-all">
                                    <i class="fas fa-trash text-xs"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            No purchase request matches these filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Persetujuan cepat dari daftar. Penolakan sengaja TIDAK disediakan di sini:
    // ia menuntut alasan tertulis, dan itu milik halaman detail.
    document.querySelectorAll('.js-quick-approve').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm(
                `Approve ${form.dataset.no}?`,
                'Approve Purchase Request',
                'warning',
                { okText: 'Approve', cancelText: 'Cancel' }
            );

            if (!ok) return;
            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });
</script>
@endpush
