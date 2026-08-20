@extends('dashboard')

@section('title', 'Branches')
@section('page-title', 'Branches')
@section('page-subtitle', 'Branch master data and the geofence points used to validate attendance')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Branches</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                The coordinates and radius defined here validate where employees are allowed to check in.
            </p>
        </div>
        @if($can('general.settings.branches.manage'))
        <a href="{{ route('general.settings.branches.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <i class="fas fa-plus"></i> Add Branch
        </a>
        @endif
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('general.settings.branches.index') }}"
          class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-7">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Search</label>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="Branch name, code, city, or province..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                <select name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                    <option value="all" @selected($status === 'all')>All</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">
                    Apply
                </button>
                <a href="{{ route('general.settings.branches.index') }}"
                   class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Reset
                </a>
            </div>
        </div>
    </form>

    {{-- Tabel --}}
    <div class="border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    <th class="px-4 py-3 w-12">No</th>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Branch</th>
                    <th class="px-4 py-3">City</th>
                    <th class="px-4 py-3">Province</th>
                    <th class="px-4 py-3 text-right">Latitude</th>
                    <th class="px-4 py-3 text-right">Longitude</th>
                    <th class="px-4 py-3 text-right">Radius (m)</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3 text-center">Head Office</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    @if($can('general.settings.branches.manage'))
                    <th class="px-4 py-3 text-center w-24">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($branches as $index => $branch)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-400">{{ $branches->firstItem() + $index }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $branch->code }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $branch->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $branch->city ?: '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $branch->province ?: '—' }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-700">{{ rtrim(rtrim(number_format((float) $branch->latitude, 7, '.', ''), '0'), '.') }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-700">{{ rtrim(rtrim(number_format((float) $branch->longitude, 7, '.', ''), '0'), '.') }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">{{ $branch->radius_meters }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $branch->phone ?: '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        @if($branch->is_head_office)
                            <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-700">Yes</span>
                        @else
                            <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 text-gray-500">No</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($branch->is_active)
                            <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-700">Active</span>
                        @else
                            <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 text-gray-500">Inactive</span>
                        @endif
                    </td>
                    @if($can('general.settings.branches.manage'))
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1.5">
                            <a href="{{ route('general.settings.branches.edit', $branch) }}"
                               title="Edit"
                               class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 transition-all">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <button type="button"
                                    title="Delete"
                                    onclick="deleteBranch({{ $branch->id }}, @js($branch->name))"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-all">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="px-4 py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-gray-400">
                            <i class="fas fa-map-marker-alt text-3xl"></i>
                            <p class="text-sm font-medium">No branches have been added yet.</p>
                            <p class="text-xs">
                                Attendance cannot validate any location until at least one branch exists.
                            </p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($branches->hasPages())
    <div class="mt-4">
        {{ $branches->links() }}
    </div>
    @endif

    {{-- Keterangan --}}
    <div class="mt-5 flex items-start gap-2 text-xs text-gray-500 bg-blue-50 border border-blue-100 rounded-lg p-3">
        <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
        <div>
            <strong class="text-gray-700">Radius</strong> is the distance from the branch centre point, in every
            direction, that still counts as being on site. A 100 m radius means a circle 200 m across.
            Indoor GPS accuracy typically drifts by 20–100 m, so verify the figure with a real check-in
            at the location — especially on upper floors.
        </div>
    </div>
</div>

{{-- Form penghapusan; dikirim setelah showConfirm() disetujui --}}
<form id="deleteBranchForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
async function deleteBranch(id, name) {
    const ok = await showConfirm(
        `Delete the branch "${name}"? Attendance records already saved will be kept.`,
        'Delete Branch',
        'danger',
        { okText: 'Delete', cancelText: 'Cancel' }
    );

    if (!ok) return;

    const form = document.getElementById('deleteBranchForm');
    form.action = `/general/settings/branches/${id}`;
    form.submit();
}
</script>
@endpush
