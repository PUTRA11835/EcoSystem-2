@extends('dashboard')
@section('title', 'Create Support')
@section('page-title', 'Create New Support')
@section('page-subtitle', 'Add a new support delivery item')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Flash Notifications --}}
    @if(session('error'))
        <div id="error-alert" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
            <button onclick="document.getElementById('error-alert').remove()" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-5 w-5 text-red-700" role="button" viewBox="0 0 20 20">
                    <path d="M14.348 5.652a.5.5 0 010 .707L10.707 10l3.641 3.641a.5.5 0 01-.707.707L10 10.707l-3.641 3.641a.5.5 0 01-.707-.707L9.293 10 5.652 6.359a.5.5 0 01.707-.707L10 9.293l3.641-3.641a.5.5 0 01.707 0z"/>
                </svg>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div id="validation-alert" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
            <div class="flex items-start">
                <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <span class="font-medium">Please fix the following errors:</span>
                    <ul class="mt-1 list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button onclick="document.getElementById('validation-alert').remove()" class="absolute top-0 right-0 px-4 py-3">
                <svg class="fill-current h-5 w-5 text-red-700" role="button" viewBox="0 0 20 20">
                    <path d="M14.348 5.652a.5.5 0 010 .707L10.707 10l3.641 3.641a.5.5 0 01-.707.707L10 10.707l-3.641 3.641a.5.5 0 01-.707-.707L9.293 10 5.652 6.359a.5.5 0 01.707-.707L10 9.293l3.641-3.641a.5.5 0 01.707 0z"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- Breadcrumb --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('delivery.support.index') }}" class="text-gray-600 hover:text-blue-600 text-sm font-medium">
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
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5"
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
                                    class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
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
                                    class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
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
                                    class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
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
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date
                                </label>
                                <input type="date" name="end_date" id="end_date"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
                            </div>

                            <div>
                                <label for="resolution_estimated" class="block text-sm font-medium text-gray-700 mb-1">
                                    Resolution Estimated
                                </label>
                                <input type="date" name="resolution_estimated" id="resolution_estimated"
                                       class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
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
                                        class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
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
                                        class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
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
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5"
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
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5">
                        </div>
                        <div>
                            <label for="approval_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Approved By
                            </label>
                            <input type="text" name="approval_name" id="approval_name"
                                   class="block w-full border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-4 py-2.5"
                                   placeholder="Approver name">
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 space-y-3">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center px-4 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i>
                            Create  Delivery Support
                        </button>
                        <a href="{{ route('delivery.support.index') }}"
                           class="w-full inline-flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-times mr-2"></i>
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
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('[id$="-alert"]');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);

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
            alert('End date cannot be before start date');
            this.value = startDate.value;
        }
    });
});
</script>
@endsection
