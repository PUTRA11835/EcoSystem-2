@extends('dashboard')

@section('title', 'My Approvals')
@section('page-title', 'My Approvals')
@section('page-subtitle', 'Everything that waits for your decision, across all modules')

@section('content')
<div class="space-y-5">

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        <a href="{{ route('general.approvals.index') }}"
           class="bg-white rounded-xl p-5 shadow-sm border-2 {{ $active === '' ? 'border-red-800' : 'border-transparent' }}">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Waiting for you</p>
            <p class="text-3xl font-bold mt-1 text-red-800">{{ $total }}</p>
        </a>
        @foreach($counts as $module => $count)
            <a href="{{ route('general.approvals.index', ['module' => $module]) }}"
               class="bg-white rounded-xl p-5 shadow-sm border-2 {{ $active === $module ? 'border-red-800' : 'border-transparent' }}">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $modules[$module] ?? $module }}</p>
                <p class="text-3xl font-bold mt-1 text-gray-900">{{ $count }}</p>
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <div class="mb-6 pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Waiting for your decision</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                You are named as an approver on these documents. Open one to approve or reject it; you only see the documents you take part in.
            </p>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-12">No</th>
                        <th class="px-4 py-3">Module</th>
                        <th class="px-4 py-3">Document No.</th>
                        <th class="px-4 py-3">Requester</th>
                        <th class="px-4 py-3">Your step</th>
                        <th class="px-4 py-3">Waiting since</th>
                        <th class="px-4 py-3 w-24 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $i => $row)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $row['label'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-red-800 whitespace-nowrap">{{ $row['number'] }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $row['requester'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row['step'] }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $row['since']?->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ $row['url'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-200 text-red-800 hover:bg-red-50">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400">Nothing is waiting for you. You are all caught up.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
