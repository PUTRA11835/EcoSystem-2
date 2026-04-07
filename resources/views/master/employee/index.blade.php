@extends('dashboard')

@section('title', 'Master Employee')
@section('page-title', 'Employee Management')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <h2 class="text-2xl font-bold text-gray-900">Employee Management</h2>
    </div>

    <!-- Filter Section -->
    <div class="bg-gray-50 rounded-lg p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                <select id="filterStatus" onchange="applyFilters()" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="blocked">Inactive</option>
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Employee</label>
                <input type="text" id="filterEmployee" placeholder="Search by ECI or name..." oninput="debouncedApplyFilters()" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Department</label>
                <input type="text" id="filterDepartment" placeholder="Search department..." oninput="debouncedApplyFilters()" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white">
            </div>
        </div>
        <div class="flex gap-3 justify-end">
            <button onclick="applyFilters()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                GO
            </button>
            <button onclick="resetFilters()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Reset
            </button>
        </div>
    </div>

    <!-- Table Section -->
    <div class="mt-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Employee List</h3>
            <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all shadow-sm hover:shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create Employee
        </button>
        </div>

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">ECI</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Full Name</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Position</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Division</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Department</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Since Date</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Status</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody id="employeeTableBody" class="bg-white divide-y divide-gray-100">
                    <!-- Dynamic rows will be inserted here by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Create/Edit Employee dengan 3 Sections -->
<div id="employeeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-6xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        <!-- Modal Header -->
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Create Employee</h3>
            <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body - 3 Columns Layout -->
        <div class="p-6">
            <form id="employeeForm">
                <input type="hidden" id="employeeId">
                <meta name="csrf-token" content="{{ csrf_token() }}">
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- SECTION 1: GENERAL DATA -->
                    <div class="space-y-4">
                        <h4 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b-2 border-gray-200">General Data</h4>
                        
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Employee <span class="text-red-600">*</span></label>
                            <input type="text" id="eci" placeholder="e.g., ECI001" required class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Password <span class="text-red-600" id="passwordRequired">*</span></label>
                            <div class="relative">
                                <input type="password" id="password" placeholder="Enter password" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-10">
                                <button type="button" onclick="togglePassword('password')" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <svg id="eyeIconPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </div>
                            <small class="text-xs text-gray-500 mt-1" id="passwordHint">Leave blank to keep current password</small>
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Confirm Password <span class="text-red-600" id="confirmPasswordRequired">*</span></label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" placeholder="Re-enter password" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800 pr-10">
                                <button type="button" onclick="togglePassword('confirmPassword')" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <svg id="eyeIconConfirmPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Title</label>
                            <select id="title" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                                <option value="">Select Title</option>
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">First Name <span class="text-red-600">*</span></label>
                            <input type="text" id="firstName" required class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Last Name</label>
                            <input type="text" id="lastName" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Nick Name</label>
                            <input type="text" id="nickName" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Gender</label>
                            <select id="gender" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Birth Date</label>
                            <input type="date" id="birthDate" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Birth Place</label>
                            <input type="text" id="birthPlace" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Since Date</label>
                            <input type="date" id="sinceDate" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>
                    </div>

                    <!-- SECTION 2: STANDARD ADDRESS -->
                    <div class="space-y-4">
                        <h4 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b-2 border-gray-200">Standard Address</h4>
                        
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Street</label>
                            <input type="text" id="street" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">House Number</label>
                            <input type="text" id="houseNumber" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Postal Code</label>
                            <input type="text" id="postalCode" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Country</label>
                            <input type="text" id="country" value="Indonesia" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Region/Province</label>
                            <input type="text" id="region" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">City</label>
                            <input type="text" id="city" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">District</label>
                            <input type="text" id="district" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Rural / Urban Villages</label>
                            <input type="text" id="village" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Language</label>
                            <select id="language" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                                <option value="">Select Language</option>
                                <option value="English">English</option>
                                <option value="Indonesian">Indonesian</option>
                                <option value="Javanese">Javanese</option>
                                <option value="Sundanese">Sundanese</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Work Email <span class="text-red-600" id="emailWorkRequired">*</span></label>
                            <input type="email" id="emailWork" placeholder="e.g., name@company.com" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                            <small class="text-xs text-gray-500 mt-1">Used for account activation link</small>
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Cell Phone</label>
                            <input type="text" id="cellPhone" placeholder="e.g., 08123456789" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>
                    </div>

                    <!-- SECTION 3: ORGANIZATIONAL DATA -->
                    <div class="space-y-4">
                        <h4 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b-2 border-gray-200">Organizational Data</h4>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Personnel Area</label>
                            <input type="text" id="personnelArea" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Position</label>
                            <input type="text" id="position" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Employee Group</label>
                            <input type="text" id="employeeGroup" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Employee Sub-Group</label>
                            <input type="text" id="employeeSubgroup" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>

                        <div class="flex flex-col">
                            <label class="text-xs font-semibold text-gray-600 mb-1">Division</label>
                            <input type="text" id="division" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button onclick="closeModal()" class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="saveEmployee()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                </svg>
                Save
            </button>
        </div>
    </div>
</div>

<!-- Modal Change Password -->
<div id="changePasswordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Change Password</h3>
                <p id="cpEmployeeName" class="text-sm text-gray-500 mt-0.5"></p>
            </div>
            <button onclick="closeChangePasswordModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="cpEmployeeId">
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1">New Password <span class="text-red-600">*</span></label>
                <div class="relative">
                    <input type="password" id="cpNewPassword" placeholder="Enter new password" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10">
                    <button type="button" onclick="toggleCpField('cpNewPassword', 'cpEye1')" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg id="cpEye1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
                <small class="text-xs text-gray-400 mt-1">Minimum 6 characters</small>
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1">Confirm Password <span class="text-red-600">*</span></label>
                <div class="relative">
                    <input type="password" id="cpConfirmPassword" placeholder="Re-enter new password" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10">
                    <button type="button" onclick="toggleCpField('cpConfirmPassword', 'cpEye2')" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg id="cpEye2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button onclick="closeChangePasswordModal()" class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="submitChangePassword()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z" />
                </svg>
                Change Password
            </button>
        </div>
    </div>
</div>

<!-- Modal Change Role (multi-select via checkboxes) -->
<div id="changeRoleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Assign Roles</h3>
                <p id="crEmployeeName" class="text-sm text-gray-500 mt-0.5"></p>
            </div>
            <button onclick="closeChangeRoleModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="crEmployeeId">
            <p class="text-xs font-semibold text-gray-600 mb-3">Select Roles <span class="text-red-600">*</span> <span class="font-normal text-gray-400">(pilih satu atau lebih)</span></p>
            <div id="crRoleCheckboxes" class="space-y-2 max-h-60 overflow-y-auto pr-1">
                <p class="text-sm text-gray-400">Loading roles...</p>
            </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button onclick="closeChangeRoleModal()" class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="submitChangeRole()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-purple-600 text-white text-sm font-semibold rounded-lg hover:bg-purple-700 transition-all shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                Save Roles
            </button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Delete -->
<div id="confirmDeleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Employee</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Are you sure you want to delete this employee?</p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDelete()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDelete()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Hover effect untuk baris tabel yang bisa diklik */
    .employee-row {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .employee-row:hover {
        background-color: #fef2f2 !important;
        transform: scale(1.002);
    }
    
    /* Mencegah text selection saat double click */
    .employee-row {
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
</style>

<script>
    let employees = [];
    let currentEmployeeId = null;
    let deleteEmployeeId = null;

    /**
     * Tampilkan semua error validasi dari response API sebagai toast.
     * Jika ada data.errors (Laravel 422), tiap pesan ditampilkan.
     * Jika tidak, tampilkan data.message atau fallbackMsg.
     */
    function showApiErrors(data, fallbackMsg = 'Terjadi kesalahan, coba lagi') {
        if (data && data.errors && typeof data.errors === 'object') {
            const msgs = Object.values(data.errors).flat().filter(Boolean);
            if (msgs.length > 0) {
                const html = msgs.map(m => '• ' + m).join('<br>');
                showToast(html, 'error', 7000);
                return;
            }
        }
        showNotification(data?.message || fallbackMsg, 'error');
    }

    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById('eyeIcon' + fieldId.charAt(0).toUpperCase() + fieldId.slice(1));
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />`;
        } else {
            field.type = 'password';
            icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;
        }
    }

    async function fetchEmployees(filters = {}) {
        try {
            const params = new URLSearchParams(filters);
            const response = await fetch(`/api/employees?${params}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            
            if (data.success) {
                employees = data.data;
                renderTable(employees);
            } else {
                showNotification(data.message || 'Failed to fetch employees', 'error');
            }
        } catch (error) {
            showNotification('An error occurred while fetching employees', 'error');
        }
    }

    // Fungsi untuk navigasi ke halaman detail saat baris diklik
    function navigateToDetail(employeeId, event) {
        // Cek apakah yang diklik adalah tombol action
        if (event.target.closest('.action-buttons')) {
            return; // Jangan navigate jika klik tombol action
        }
        
        // Navigate ke halaman detail
        window.location.href = `/master/employee/${employeeId}`;
    }

    function renderTable(data = employees) {
        const tbody = document.getElementById('employeeTableBody');
        
        if (data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-16 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <p class="text-base font-medium text-gray-900 mb-2">No employees found</p>
                        <small class="text-sm text-gray-500">Click "Create Employee" to add a new employee</small>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = data.map(emp => {
            const statusInfo = getStatusInfo(emp);
            const fullName = [emp.first_name, emp.last_name].filter(n => n).join(' ') || '-';
            
            return `
            <tr class="employee-row" onclick="navigateToDetail(${emp.id}, event)">
                <td class="px-4 py-3.5 text-sm"><strong class="font-semibold text-gray-900">${emp.eci || '-'}</strong></td>
                <td class="px-4 py-3.5 text-sm text-gray-600">${fullName}</td>
                <td class="px-4 py-3.5 text-sm text-gray-600">${emp.position || '-'}</td>
                <td class="px-4 py-3.5 text-sm text-gray-600">${emp.division || '-'}</td>
                <td class="px-4 py-3.5 text-sm text-gray-600">${emp.employee_subgroup || '-'}</td>
                <td class="px-4 py-3.5 text-sm text-gray-600">${emp.since_date || '-'}</td>
                <td class="px-4 py-3.5 text-sm">
                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full ${statusInfo.class}">
                        ${statusInfo.label}
                    </span>
                </td>
                <td class="px-4 py-3.5 text-sm">
                    <div class="action-buttons" onclick="event.stopPropagation()">
                        <button onclick="openEmpMenu(event, ${emp.id}, '${fullName.replace(/'/g,"\\'")}', ${JSON.stringify(emp.roles ? emp.roles.map(r=>r.id) : [])})" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        }).join('');
    }

    function getStatusInfo(emp) {
        const status = emp.status?.toLowerCase() || 'active';
        const statusMap = {
            'active': { label: 'Active', class: 'bg-green-100 text-green-800' },
            'blocked': { label: 'Blocked', class: 'bg-yellow-100 text-yellow-800' },
            'deleted': { label: 'Flagged for Deletion', class: 'bg-red-100 text-red-800' }
        };
        return statusMap[status] || statusMap['active'];
    }

    function openCreateModal() {
        currentEmployeeId = null;
        document.getElementById('modalTitle').textContent = 'Create Employee';
        document.getElementById('employeeForm').reset();
        document.getElementById('employeeId').value = '';
        
        // Set default value for Country
        document.getElementById('country').value = 'Indonesia';
        
        // Set password as required for create
        document.getElementById('password').required = true;
        document.getElementById('confirmPassword').required = true;
        document.getElementById('passwordRequired').classList.remove('hidden');
        document.getElementById('confirmPasswordRequired').classList.remove('hidden');
        document.getElementById('passwordHint').classList.add('hidden');

        // Set email_work as required for create
        document.getElementById('emailWork').required = true;
        document.getElementById('emailWorkRequired').classList.remove('hidden');
        
        document.getElementById('employeeModal').classList.remove('hidden');
        document.getElementById('employeeModal').classList.add('flex');
    }

    function closeModal() {
        document.getElementById('employeeModal').classList.add('hidden');
        document.getElementById('employeeModal').classList.remove('flex');
        currentEmployeeId = null;
    }

    async function editEmployee(id) {
        try {
            const response = await fetch(`/api/employees/${id}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            
            if (data.success) {
                const emp = data.data;
                currentEmployeeId = id;
                
                // SECTION 1: GENERAL DATA
                document.getElementById('eci').value = emp.eci || '';
                document.getElementById('title').value = emp.title || '';
                document.getElementById('firstName').value = emp.first_name || '';
                document.getElementById('lastName').value = emp.last_name || '';
                document.getElementById('nickName').value = emp.nick_name || '';
                document.getElementById('gender').value = emp.gender || '';
                document.getElementById('birthDate').value = emp.birth_date || '';
                document.getElementById('birthPlace').value = emp.birth_place || '';
                document.getElementById('sinceDate').value = emp.since_date || '';
                
                // Clear password fields
                document.getElementById('password').value = '';
                document.getElementById('confirmPassword').value = '';
                
                // Set password as optional for edit
                document.getElementById('password').required = false;
                document.getElementById('confirmPassword').required = false;
                document.getElementById('passwordRequired').classList.add('hidden');
                document.getElementById('confirmPasswordRequired').classList.add('hidden');
                document.getElementById('passwordHint').classList.remove('hidden');

                // Set email_work as optional for edit
                document.getElementById('emailWork').required = false;
                document.getElementById('emailWorkRequired').classList.add('hidden');
                
                // SECTION 2: STANDARD ADDRESS
                document.getElementById('street').value = emp.street || '';
                document.getElementById('houseNumber').value = emp.house_number || '';
                document.getElementById('postalCode').value = emp.postal_code || '';
                document.getElementById('country').value = emp.country || 'Indonesia';
                document.getElementById('region').value = emp.region || '';
                document.getElementById('city').value = emp.city || '';
                document.getElementById('district').value = emp.district || '';
                document.getElementById('village').value = emp.rural_urban_village || '';
                document.getElementById('language').value = emp.language || '';
                document.getElementById('emailWork').value = emp.email_work || '';
                document.getElementById('cellPhone').value = emp.cell_phone || '';
                
                // SECTION 3: ORGANIZATIONAL DATA
                document.getElementById('personnelArea').value = emp.personnel_area || '';
                document.getElementById('position').value = emp.position || '';
                document.getElementById('employeeGroup').value = emp.employee_group || '';
                document.getElementById('employeeSubgroup').value = emp.employee_subgroup || '';
                document.getElementById('division').value = emp.division || '';
                
                document.getElementById('modalTitle').textContent = 'Edit Employee';
                document.getElementById('employeeModal').classList.remove('hidden');
                document.getElementById('employeeModal').classList.add('flex');
            } else {
                showNotification('Failed to load employee data', 'error');
            }
        } catch (error) {
            showNotification('An error occurred while loading employee data', 'error');
        }
    }

    async function saveEmployee() {
        const form = document.getElementById('employeeForm');
        
        // Validate password match
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (password || confirmPassword) {
            if (password !== confirmPassword) {
                showNotification('Passwords do not match!', 'error');
                return;
            }
            if (password.length < 6) {
                showNotification('Password must be at least 6 characters long!', 'error');
                return;
            }
        }
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const employeeData = {
            // SECTION 1: GENERAL DATA
            eci: document.getElementById('eci').value,
            title: document.getElementById('title').value,
            first_name: document.getElementById('firstName').value,
            last_name: document.getElementById('lastName').value,
            nick_name: document.getElementById('nickName').value,
            gender: document.getElementById('gender').value,
            birth_date: document.getElementById('birthDate').value,
            birth_place: document.getElementById('birthPlace').value,
            since_date: document.getElementById('sinceDate').value,
            
            // SECTION 2: STANDARD ADDRESS
            street: document.getElementById('street').value,
            house_number: document.getElementById('houseNumber').value,
            postal_code: document.getElementById('postalCode').value,
            country: document.getElementById('country').value,
            region: document.getElementById('region').value,
            city: document.getElementById('city').value,
            district: document.getElementById('district').value,
            rural_urban_village: document.getElementById('village').value,
            language: document.getElementById('language').value,
            email_work: document.getElementById('emailWork').value,
            cell_phone: document.getElementById('cellPhone').value,
            
            // SECTION 3: ORGANIZATIONAL DATA
            personnel_area: document.getElementById('personnelArea').value,
            position: document.getElementById('position').value,
            employee_group: document.getElementById('employeeGroup').value,
            employee_subgroup: document.getElementById('employeeSubgroup').value,
            division: document.getElementById('division').value,
        };

        // Add password only if it's provided
        if (password) {
            employeeData.password = password;
            employeeData.password_confirmation = confirmPassword;
        }

        if (!currentEmployeeId) {
            employeeData.role_id = 2;
        }

        try {
            let url = '/api/employees';
            let method = 'POST';
            
            if (currentEmployeeId) {
                url = `/api/employees/${currentEmployeeId}`;
                method = 'PUT';
            }

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify(employeeData)
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification(currentEmployeeId ? 'Employee updated successfully!' : 'Employee created successfully!', 'success');
                closeModal();
                fetchEmployees();
            } else {
                showApiErrors(data, 'Gagal menyimpan employee');
            }
        } catch (error) {
            showNotification('An error occurred while saving employee', 'error');
        }
    }

    function deleteEmployee(id) {
        deleteEmployeeId = id;
        document.getElementById('confirmDeleteModal').classList.remove('hidden');
        document.getElementById('confirmDeleteModal').classList.add('flex');
    }

    function closeConfirmDelete() {
        document.getElementById('confirmDeleteModal').classList.add('hidden');
        document.getElementById('confirmDeleteModal').classList.remove('flex');
        deleteEmployeeId = null;
    }

    async function confirmDelete() {
        if (!deleteEmployeeId) return;

        try {
            const response = await fetch(`/api/employees/${deleteEmployeeId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification('Employee deleted successfully!', 'success');
                closeConfirmDelete();
                fetchEmployees();
            } else {
                showNotification('Failed to delete employee: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            showNotification('An error occurred while deleting employee', 'error');
        }
    }

    function applyFilters() {
        const filters = {
            status: document.getElementById('filterStatus').value,
            employee: document.getElementById('filterEmployee').value,
            department: document.getElementById('filterDepartment').value,
        };
        fetchEmployees(filters);
    }

    let _employeeSearchTimer;
    function debouncedApplyFilters() {
        clearTimeout(_employeeSearchTimer);
        _employeeSearchTimer = setTimeout(applyFilters, 400);
    }

    function resetFilters() {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterEmployee').value = '';
        document.getElementById('filterDepartment').value = '';
        fetchEmployees();
    }



    // ── Change Password ────────────────────────────────────────────────────────
    function toggleCpField(fieldId, iconId) {
        const field = document.getElementById(fieldId);
        const isHidden = field.type === 'password';
        field.type = isHidden ? 'text' : 'password';
        const icon = document.getElementById(iconId);
        icon.innerHTML = isHidden
            ? `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />`
            : `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;
    }

    function openChangePasswordModal(employeeId, employeeName) {
        document.getElementById('cpEmployeeId').value = employeeId;
        document.getElementById('cpEmployeeName').textContent = employeeName;
        document.getElementById('cpNewPassword').value = '';
        document.getElementById('cpConfirmPassword').value = '';
        document.getElementById('changePasswordModal').classList.remove('hidden');
        document.getElementById('changePasswordModal').classList.add('flex');
        document.getElementById('cpNewPassword').focus();
    }

    function closeChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.add('hidden');
        document.getElementById('changePasswordModal').classList.remove('flex');
    }

    async function submitChangePassword() {
        const employeeId = document.getElementById('cpEmployeeId').value;
        const password = document.getElementById('cpNewPassword').value.trim();
        const confirmation = document.getElementById('cpConfirmPassword').value.trim();

        if (!password) {
            showNotification('Password baru wajib diisi', 'error');
            return;
        }
        if (password.length < 6) {
            showNotification('Password must be at least 6 characters', 'error');
            return;
        }
        if (password !== confirmation) {
            showNotification('Password confirmation does not match', 'error');
            return;
        }

        try {
            const response = await fetch(`/api/employees/${employeeId}/change-password`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ password, password_confirmation: confirmation }),
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Password updated successfully!', 'success');
                closeChangePasswordModal();
            } else {
                showApiErrors(data, 'Failed to update password');
            }
        } catch (error) {
            showNotification('An error occurred, please try again', 'error');
        }
    }

    // ── Change Role (multi) ────────────────────────────────────────────────────
    let allRoles = [];

    async function loadRoles() {
        if (allRoles.length > 0) return;
        try {
            const response = await fetch('/api/employees/roles', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data.success) allRoles = data.data;
        } catch (e) {
            showNotification('Failed to load roles', 'error');
        }
    }

    async function openChangeRoleModal(employeeId, employeeName, currentRoleIds) {
        await loadRoles();

        document.getElementById('crEmployeeId').value = employeeId;
        document.getElementById('crEmployeeName').textContent = employeeName;

        const container = document.getElementById('crRoleCheckboxes');

        if (!allRoles.length) {
            container.innerHTML = '<p class="text-sm text-gray-400">No roles available</p>';
        } else {
            container.innerHTML = allRoles.map(r => `
                <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-purple-50 hover:border-purple-300 transition-all">
                    <input type="checkbox" value="${r.id}"
                        class="cr-role-checkbox mt-0.5 w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500 cursor-pointer"
                        ${currentRoleIds.includes(r.id) ? 'checked' : ''}>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">${r.name}</p>
                        ${r.description ? `<p class="text-xs text-gray-500 mt-0.5">${r.description}</p>` : ''}
                    </div>
                </label>
            `).join('');
        }

        document.getElementById('changeRoleModal').classList.remove('hidden');
        document.getElementById('changeRoleModal').classList.add('flex');
    }

    function closeChangeRoleModal() {
        document.getElementById('changeRoleModal').classList.add('hidden');
        document.getElementById('changeRoleModal').classList.remove('flex');
    }

    async function submitChangeRole() {
        const employeeId = document.getElementById('crEmployeeId').value;
        const checked = [...document.querySelectorAll('.cr-role-checkbox:checked')].map(cb => parseInt(cb.value));

        if (!checked.length) {
            showNotification('Please select at least one role', 'error');
            return;
        }

        try {
            const response = await fetch(`/api/employees/${employeeId}/change-role`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ role_ids: checked }),
            });

            const data = await response.json();

            if (data.success) {
                const names = data.data.roles.map(r => r.name).join(', ');
                showNotification(`Role updated successfully: ${names}`, 'success');
                closeChangeRoleModal();
                fetchEmployees();
            } else {
                showApiErrors(data, 'Failed to update role');
            }
        } catch (error) {
            showNotification('An error occurred, please try again', 'error');
        }
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('employeeModal').classList.contains('hidden')) {
                closeModal();
            }
            if (!document.getElementById('confirmDeleteModal').classList.contains('hidden')) {
                closeConfirmDelete();
            }
            if (!document.getElementById('changePasswordModal').classList.contains('hidden')) {
                closeChangePasswordModal();
            }
            if (!document.getElementById('changeRoleModal').classList.contains('hidden')) {
                closeChangeRoleModal();
            }
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        fetchEmployees();
    });

    let _empMenuId = null, _empMenuName = null, _empMenuRoles = null;

    function openEmpMenu(event, id, name, roles) {
        event.stopPropagation();
        _empMenuId    = id;
        _empMenuName  = name;
        _empMenuRoles = roles;
        const menu = document.getElementById('floatingEmpMenu');
        const btn  = event.currentTarget;
        const rect = btn.getBoundingClientRect();
        menu.classList.remove('hidden');
        const mw = menu.offsetWidth;
        menu.style.top  = (rect.bottom + 4) + 'px';
        menu.style.left = (rect.right - mw) + 'px';
    }

    function closeEmpMenu() {
        document.getElementById('floatingEmpMenu').classList.add('hidden');
    }

    function empMenuChangePassword() {
        closeEmpMenu();
        openChangePasswordModal(_empMenuId, _empMenuName);
    }
    function empMenuChangeRole() {
        closeEmpMenu();
        openChangeRoleModal(_empMenuId, _empMenuName, _empMenuRoles);
    }
    function empMenuDelete() {
        closeEmpMenu();
        deleteEmployee(_empMenuId);
    }

    document.addEventListener('click', closeEmpMenu);
</script>

{{-- Floating action menu (fixed position to avoid table stacking context) --}}
<div id="floatingEmpMenu" class="hidden fixed z-[9999] w-44 bg-white border border-gray-200 rounded-lg shadow-xl py-1" onclick="event.stopPropagation()">
    <button onclick="empMenuChangePassword()" class="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-blue-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
        </svg>
        Change Password
    </button>
    <button onclick="empMenuChangeRole()" class="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-purple-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
        </svg>
        Change Role
    </button>
    <div class="border-t border-gray-100 my-1"></div>
    <button onclick="empMenuDelete()" class="w-full flex items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50 transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
        </svg>
        Delete
    </button>
</div>

@endsection