@extends('dashboard')

@section('title', isset($isOwnProfile) && $isOwnProfile ? 'My Profile' : 'Employee Detail')
@section('page-title', isset($isOwnProfile) && $isOwnProfile ? 'My Profile' : 'Employee Detail - ' . (($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '') ?: 'N/A'))

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-6">
    <!-- Header dengan tombol back -->
    <div class="flex items-center justify-between">
        @if(isset($isOwnProfile) && $isOwnProfile)
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to Dashboard
            </a>
        @else
            <a href="{{ route('master.employee.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to List
            </a>
        @endif
    </div>

    <!-- Employee Profile Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-start gap-6">
            <div class="w-32 h-32 rounded-full bg-gradient-to-br from-red-800 to-red-950 text-white flex items-center justify-center font-bold text-4xl flex-shrink-0">
                {{ strtoupper(substr(($employee->first_name ?? 'N'), 0, 1) . substr(($employee->last_name ?? 'A'), 0, 1)) }}
            </div>
            <div class="flex-1">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: 'N/A' }}</h1>
                        <p class="text-lg text-gray-600 mt-1">{{ $employee->position ?? 'N/A' }}</p>
                    </div>
                    @php
                        $statusClass = 'bg-gray-100 text-gray-800';
                        $statusLabel = 'Unknown';
                        
                        if (isset($employee->deletion_flag) && $employee->deletion_flag) {
                            $statusClass = 'bg-red-100 text-red-800';
                            $statusLabel = 'Flagged for Deletion';
                        } elseif (isset($employee->block) && $employee->block) {
                            $statusClass = 'bg-yellow-100 text-yellow-800';
                            $statusLabel = 'Blocked';
                        } elseif (isset($employee->is_active) && $employee->is_active) {
                            $statusClass = 'bg-green-100 text-green-800';
                            $statusLabel = 'Active';
                        } else {
                            $statusClass = 'bg-gray-100 text-gray-800';
                            $statusLabel = 'Inactive';
                        }
                    @endphp
                    <span class="inline-block px-4 py-2 text-sm font-semibold rounded-full {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Employee ID (ECI)</p>
                        <p class="font-semibold text-gray-900">{{ $employee->eci ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Email (Personal)</p>
                        <p class="font-semibold text-gray-900">{{ $employee->email_personal ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Phone</p>
                        <p class="font-semibold text-gray-900">{{ $employee->cell_phone ?? $employee->telephone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Department</p>
                        <p class="font-semibold text-gray-900">{{ $employee->department ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Division</p>
                        <p class="font-semibold text-gray-900">{{ $employee->division ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Since Date</p>
                        <p class="font-semibold text-gray-900">{{ $employee->since_date ? \Carbon\Carbon::parse($employee->since_date)->format('d M Y') : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px overflow-x-auto">
                <button onclick="switchSection('basic-data')" data-section="basic-data" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-red-800 text-red-800 whitespace-nowrap">
                    Basic Data
                </button>
                <button onclick="switchSection('address')" data-section="address" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Address
                </button>
                <button onclick="switchSection('identification')" data-section="identification" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Identification
                </button>
                <button onclick="switchSection('family')" data-section="family" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Family
                </button>
                <button onclick="switchSection('education')" data-section="education" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Education
                </button>
                <button onclick="switchSection('qualification')" data-section="qualification" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Qualification
                </button>
                <button onclick="switchSection('contract')" data-section="contract" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Contract
                </button>
                <button onclick="switchSection('bank')" data-section="bank" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Bank Account
                </button>
                <button onclick="switchSection('payment')" data-section="payment" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Basic Payment
                </button>
                <button onclick="switchSection('attachment')" data-section="attachment" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Attachment
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <div id="section-basic-data" class="section-content">
                @include('master.employee.sections.basicdata', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-address" class="section-content hidden">
                @include('master.employee.sections.address', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-identification" class="section-content hidden">
                @include('master.employee.sections.identification', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-family" class="section-content hidden">
                @include('master.employee.sections.family', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-education" class="section-content hidden">
                @include('master.employee.sections.education', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-qualification" class="section-content hidden">
                @include('master.employee.sections.qualification', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-contract" class="section-content hidden">
                @include('master.employee.sections.contract', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-bank" class="section-content hidden">
                @include('master.employee.sections.bank', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-payment" class="section-content hidden">
                @include('master.employee.sections.payment', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>
            <div id="section-attachment" class="section-content hidden">
                @include('master.employee.sections.attachment', ['employee' => $employee, 'employeeId' => $employee->id])
            </div>

        </div>
    </div>
</div>

<script>
    const employeeId = {{ $employee->id }};
    let currentSection = 'basic-data';

    // Switch between sections/tabs
    function switchSection(sectionName) {
        currentSection = sectionName;
        
        // Hide all sections
        document.querySelectorAll('.section-content').forEach(section => {
            section.classList.add('hidden');
        });
        
        // Remove active from all tabs
        document.querySelectorAll('.section-tab').forEach(tab => {
            tab.classList.remove('border-red-800', 'text-red-800');
            tab.classList.add('border-transparent', 'text-gray-600');
        });
        
        // Show selected section
        const selectedSection = document.getElementById('section-' + sectionName);
        if (selectedSection) {
            selectedSection.classList.remove('hidden');
        }
        
        // Add active to selected tab
        const selectedTab = document.querySelector(`[data-section="${sectionName}"]`);
        if (selectedTab) {
            selectedTab.classList.add('border-red-800', 'text-red-800');
            selectedTab.classList.remove('border-transparent', 'text-gray-600');
        }
        
        // Load data for specific sections
        loadSectionData(sectionName);
    }

    // Load data based on active section
    function loadSectionData(sectionName) {
        switch(sectionName) {
            case 'basic-data':
                if (typeof loadEmployeeBasicData === 'function') {
                    loadEmployeeBasicData(employeeId);
                }
                break;
            case 'address':
                if (typeof loadAddresses === 'function') {
                    loadAddresses(employeeId);
                }
                break;
        }
    }

    // Save current section
    function saveCurrentSection() {
        switch(currentSection) {
            case 'basic-data':
                saveEmployeeBasicData(employeeId);
                break;
            case 'address':
                if (typeof saveAddresses === 'function') {
                    saveAddresses();
                }
                break;
            default:
                showNotification('Save function not implemented for this section', 'info');
        }
    }

    // Load employee basic data
    async function loadEmployeeBasicData(employeeId) {
        try {
            const response = await fetch(`/api/employees/${employeeId}/basic-data`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });

            const result = await response.json();
            
            if (result.success && result.data) {
                const basicData = result.data;
                
                // General Information
                setValue('title', basicData.title);
                setValue('firstName', basicData.first_name);
                setValue('lastName', basicData.last_name);
                setValue('nickName', basicData.nick_name);
                setValue('gender', basicData.gender);
                setValue('religion', basicData.religion);
                setValue('searchTerm1', basicData.search_term_1);
                setValue('searchTerm2', basicData.search_term_2);
                setValue('maritalStatus', basicData.marital_status);
                setValue('birthDate', basicData.birth_date);
                setValue('birthPlace', basicData.birth_place);
                setValue('sinceDate', basicData.since_date);
                
                // Employee Information
                setValue('personnelArea', basicData.personnel_area);
                setValue('personnelSubarea', basicData.personnel_subarea);
                setValue('employeeGroup', basicData.employee_group);
                setValue('employeeSubgroup', basicData.employee_subgroup);
                setValue('position', basicData.position);
                setValue('division', basicData.division);
                setValue('department', basicData.department);
                setValue('directSupervision', basicData.direct_supervision);
                setValue('manager', basicData.manager);
                setValue('authorizationGroup', basicData.authorization_group);
                
                // Status
                setCheckbox('block', basicData.block);
                setCheckbox('deletionFlag', basicData.deletion_flag);
                
                // Audit Information
                setText('createdBy', basicData.created_by);
                setText('createdOn', formatDateTime(basicData.created_on));
                setText('lastChangedBy', basicData.last_changed_by);
                setText('lastChangedOn', formatDateTime(basicData.last_changed_on));
                
            } else {
            }
        } catch (error) {
            console.error('Error loading basic data:', error);
            showNotification('Error loading basic data', 'error');
        }
    }

    // Save employee basic data
    async function saveEmployeeBasicData(employeeId) {
        const firstName = getValue('firstName').trim();
        if (!firstName) {
            showNotification('First Name is required', 'error');
            document.getElementById('firstName')?.focus();
            return;
        }
        const v = id => getValue(id) || null;
        const basicData = {
            title: v('title'),
            first_name: getValue('firstName'),
            last_name: v('lastName'),
            nick_name: v('nickName'),
            gender: v('gender'),
            religion: v('religion'),
            search_term_1: v('searchTerm1'),
            search_term_2: v('searchTerm2'),
            marital_status: v('maritalStatus'),
            birth_date: v('birthDate'),
            birth_place: v('birthPlace'),
            since_date: v('sinceDate'),
            personnel_area: getValue('personnelArea'),
            personnel_subarea: getValue('personnelSubarea'),
            employee_group: getValue('employeeGroup'),
            employee_subgroup: getValue('employeeSubgroup'),
            position: getValue('position'),
            division: getValue('division'),
            department: getValue('department'),
            direct_supervision: getValue('directSupervision'),
            manager: getValue('manager'),
            authorization_group: getValue('authorizationGroup'),
            block: getCheckbox('block'),
            deletion_flag: getCheckbox('deletionFlag')
        };

        try {
            const response = await fetch(`/api/employees/${employeeId}/basic-data`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(basicData)
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification('Basic data saved successfully!', 'success');
                loadEmployeeBasicData(employeeId);
            } else {
                const fieldLabels = {
                    first_name: 'First Name', last_name: 'Last Name', nick_name: 'Nick Name',
                    gender: 'Gender', religion: 'Religion', marital_status: 'Marital Status',
                    birth_date: 'Birth Date', birth_place: 'Birth Place', since_date: 'Since Date',
                    title: 'Title',
                };
                if (data.errors && typeof data.errors === 'object') {
                    const msgs = Object.entries(data.errors).map(([field, errs]) => {
                        const label = fieldLabels[field] || field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                        return `${label}: ${Array.isArray(errs) ? errs[0] : errs}`;
                    });
                    showNotification(msgs.join('\n'), 'error');
                } else {
                    showNotification(data.message || 'Failed to save', 'error');
                }
            }
        } catch (error) {
            console.error('Error saving basic data:', error);
            showNotification('An error occurred while saving', 'error');
        }
    }

    // Helper functions
    function getValue(id) {
        const el = document.getElementById(id);
        return el ? el.value : '';
    }

    function setValue(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.type === 'hidden' && el.closest && el.closest('.custom-dd')) {
            if (typeof setCustomDropdownValue === 'function') {
                setCustomDropdownValue(id, value || '');
                return;
            }
        }
        el.value = value || '';
    }

    function getCheckbox(id) {
        const el = document.getElementById(id);
        return el ? el.checked : false;
    }

    function setCheckbox(id, value) {
        const el = document.getElementById(id);
        if (el) el.checked = value === true || value === 1;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '-';
    }

    function formatDateTime(dateString) {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleString('id-ID', {
            timeZone: 'Asia/Jakarta',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
    }

    // showNotification tersedia secara global dari dashboard.blade.php

    // Load data when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Guard untuk kasus custom-dropdown.js gagal di-load di production.
        if (typeof initCustomDropdowns === 'function') {
            initCustomDropdowns();
        }
        loadEmployeeBasicData(employeeId);
    });
</script>
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection