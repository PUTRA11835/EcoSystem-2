@extends('dashboard')

@section('title', 'Shifts')
@section('page-title', 'Shifts')
@section('page-subtitle', 'Working hour patterns used to calculate lateness and working time')

@section('content')
@php
    $dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
@endphp
<div class="bg-white rounded-xl p-6 shadow-sm">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Shifts</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Employees without an explicit assignment follow the shift marked as default.
            </p>
        </div>
        @if($can('general.settings.shifts.manage'))
        <a href="{{ route('general.settings.shifts.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <i class="fas fa-plus"></i> Add Shift
        </a>
        @endif
    </div>

    <form method="GET" action="{{ route('general.settings.shifts.index') }}"
          class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-7">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Shift name..."
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
                <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all">Apply</button>
                <a href="{{ route('general.settings.shifts.index') }}"
                   class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Reset</a>
            </div>
        </div>
    </form>

    <div class="border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    <th class="px-4 py-3 w-12">No</th>
                    <th class="px-4 py-3">Shift Name</th>
                    <th class="px-4 py-3">Working Hours</th>
                    <th class="px-4 py-3 text-right">Late Tolerance</th>
                    <th class="px-4 py-3 text-right">Break</th>
                    <th class="px-4 py-3">Working Days</th>
                    <th class="px-4 py-3 text-center">Employees</th>
                    <th class="px-4 py-3 text-center">Default</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center w-32">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($shifts as $index => $shift)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-400">{{ $shifts->firstItem() + $index }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">
                        {{ $shift->name }}
                        @if($shift->crosses_midnight)
                            <span class="ml-1 inline-block px-1.5 py-0.5 text-[10px] font-semibold rounded bg-indigo-100 text-indigo-700">Overnight</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $shift->time_range }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">{{ $shift->late_tolerance_minutes }} min</td>
                    <td class="px-4 py-3 text-right text-gray-700">{{ $shift->break_minutes }} min</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        {{ collect($shift->workDayNumbers())->map(fn ($d) => $dayLabels[$d] ?? $d)->implode(', ') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($can('general.settings.shifts.manage'))
                        <a href="{{ route('general.settings.shifts.assign', $shift) }}"
                           class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $shift->active_employees_count > 0 ? 'bg-blue-100 text-blue-700 hover:bg-blue-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }} transition-colors">
                            {{ $shift->active_employees_count }}
                        </a>
                        @else
                        <span class="text-gray-600">{{ $shift->active_employees_count }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($shift->is_default)
                            <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-700">Default</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $shift->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $shift->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($can('general.settings.shifts.manage'))
                        <div class="flex items-center justify-center gap-1.5">
                            <a href="{{ route('general.settings.shifts.assign', $shift) }}" title="Assign employees"
                               class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-all">
                                <i class="fas fa-user-plus text-xs"></i>
                            </a>
                            <a href="{{ route('general.settings.shifts.edit', $shift) }}" title="Edit"
                               class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 transition-all">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <button type="button" title="Delete"
                                    onclick="deleteShift({{ $shift->id }}, @js($shift->name), {{ $shift->active_employees_count }})"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-all">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-gray-400">
                            <i class="fas fa-clock text-3xl"></i>
                            <p class="text-sm font-medium">No shifts found.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($shifts->hasPages())
    <div class="mt-4">{{ $shifts->links() }}</div>
    @endif

    <div class="mt-5 flex items-start gap-2 text-xs text-gray-500 bg-blue-50 border border-blue-100 rounded-lg p-3">
        <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
        <div>
            Lateness is measured against the assigned shift's check-in time plus its tolerance.
            Employees with no assignment fall back to the <strong class="text-gray-700">default</strong> shift,
            so every employee always has a reference — no one needs to be assigned individually
            for attendance to work.
        </div>
    </div>
</div>

<form id="deleteShiftForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
async function deleteShift(id, name, assignedCount) {
    const warning = assignedCount > 0
        ? ` This shift still has ${assignedCount} assigned employee(s) and cannot be deleted until they are released.`
        : '';

    const ok = await showConfirm(
        `Delete the shift "${name}"?${warning}`,
        'Delete Shift',
        'danger',
        { okText: 'Delete', cancelText: 'Cancel' }
    );

    if (!ok) return;

    const form = document.getElementById('deleteShiftForm');
    form.action = `/general/settings/shifts/${id}`;
    form.submit();
}
</script>
@endpush
