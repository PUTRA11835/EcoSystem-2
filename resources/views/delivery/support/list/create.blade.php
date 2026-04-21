@extends('dashboard')
@section('title', 'Create Support')
@section('page-title', 'Create New Support')
@section('page-subtitle', 'Add a new support delivery item')

@push('styles')
<style>
    .primary-link { color: var(--primary-color); }
    .primary-link:hover { opacity: 0.75; }
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Flash Notifications --}}
    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('success')),'success'));</script>
    @endif
    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json(session('error')),'error'));</script>
    @endif
    @if($errors->any())
        <script>document.addEventListener('DOMContentLoaded',()=>showNotification(@json($errors->first()),'error'));</script>
    @endif

    {{-- Breadcrumb --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('delivery.support.index') }}" class="text-gray-600 primary-link text-sm font-medium transition-colors">
                        <i class="fas fa-headset mr-2"></i>Support
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500">Create New</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <form action="{{ route('delivery.support.store') }}" method="POST" id="supportForm">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Form --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Basic Information --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Basic Information</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Support Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" required
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                                   placeholder="Enter support name">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Client <span class="text-red-500">*</span>
                            </label>
                            <select name="client_id" id="client_id" required
                                    class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                                <option value="">Select Client</option>
                                @foreach($clients ?? [] as $client)
                                    <option value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? 'N/A' }}</option>
                                @endforeach
                            </select>
                            @error('client_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Tickets can be assigned to this delivery support from the ticket page</p>
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">
                                Type <span class="text-red-500">*</span>
                            </label>
                            <select name="type" id="type" required
                                    class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                                <option value="">Select Type</option>
                                <option value="AMS">AMS</option>
                                <option value="MO">MO</option>
                                <option value="ATS">ATS</option>
                                <option value="Project">Project</option>
                                <option value="Internal">Internal</option>
                            </select>
                            @error('type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">The type determines the category of support delivery and associated tickets</p>
                        </div>

                        <div>
                            <label for="support_method" class="block text-sm font-medium text-gray-700 mb-1">
                                Support Method
                            </label>
                            <select name="support_method" id="support_method"
                                    class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                                <option value="">Select method</option>
                                <option value="Remote">Remote</option>
                                <option value="On-Site">On-Site</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Timeline --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Timeline</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Start Date
                                </label>
                                <input type="date" name="start_date" id="start_date"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date
                                </label>
                                <input type="date" name="end_date" id="end_date"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>

                            <div>
                                <label for="resolution_estimated" class="block text-sm font-medium text-gray-700 mb-1">
                                    Resolution Estimated
                                </label>
                                <input type="date" name="resolution_estimated" id="resolution_estimated"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Team Assignment --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Team Assignment</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="delivery_owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Delivery Owner
                                </label>
                                <select name="delivery_owner_id" id="delivery_owner_id"
                                        class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                                    <option value="">Select delivery owner</option>
                                    @foreach($employees ?? [] as $employee)
                                        <option value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="support_manager_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Support Manager
                                </label>
                                <select name="support_manager_id" id="support_manager_id"
                                        class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                                    <option value="">Select support manager</option>
                                    @foreach($employees ?? [] as $employee)
                                        <option value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Mandays --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Effort Estimation</h3>
                    </div>
                    <div class="p-6">
                        <div>
                            <label for="total_mandays" class="block text-sm font-medium text-gray-700 mb-1">
                                Total Mandays
                            </label>
                            <input type="number" name="total_mandays" id="total_mandays" min="0"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                                   placeholder="0">
                        </div>
                    </div>
                </div>

                {{-- Approval Info --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Approval</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="approval_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Approval Date
                            </label>
                            <input type="date" name="approval_date" id="approval_date"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5">
                        </div>
                        <div>
                            <label for="approval_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Approved By
                            </label>
                            <input type="text" name="approval_name" id="approval_name"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm primary-focus text-sm px-4 py-2.5"
                                   placeholder="Approver name">
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 space-y-3">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                            Create Delivery Support
                        </button>
                        <a href="{{ route('delivery.support.index') }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date validation
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const resolutionDate = document.getElementById('resolution_estimated');

    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
    });

    endDate.addEventListener('change', function() {
        if (this.value && startDate.value && this.value < startDate.value) {
            showNotification('End date cannot be before start date', 'warning');
            this.value = startDate.value;
        }
    });
});
</script>
@endsection
