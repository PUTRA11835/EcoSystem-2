@extends('dashboard')

@section('title', 'Employee Detail')

@section('content')
<div class="bg-white rounded-xl shadow-sm">
    <!-- Header with Employee Info -->
    <div class="border-b border-gray-200 p-6">
        <div class="flex justify-between items-start">
            <div class="flex gap-6">
                <!-- Avatar -->
                <div class="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                
                <!-- Employee Info -->
                <div>
                    <h1 class="text-2xl font-bold text-gray-900" id="employeeName">John Doe</h1>
                    <p class="text-sm text-gray-600 mt-1" id="employeeCode">S99001</p>
                    <div class="mt-3 space-y-1">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-600">Group:</span>
                            <span class="font-medium" id="employeeGroup">Internal Employee (1)</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-600">Division:</span>
                            <span class="font-medium" id="employeeDivision">Business Development</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-600">Position:</span>
                            <span class="font-medium" id="employeePosition">BD Executive</span>
                        </div>
                    </div>
                </div>

                <!-- Address & Communication -->
                <div class="ml-12">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Standard Address</h3>
                    <p class="text-sm text-gray-600" id="employeeAddress">
                        Jalan Raya Panjang Lebar No. 99<br>
                        Surabaya, Jawa Timur 60000
                    </p>
                    
                    <h3 class="text-sm font-semibold text-gray-700 mb-2 mt-4">Standard Communication</h3>
                    <p class="text-sm text-gray-600">
                        <span class="block">Phone Number: <span id="employeePhone">0876 5432 1000</span></span>
                        <span class="block">Email: <span id="employeeEmail">john.doe@eclectic.co.id</span></span>
                    </p>
                </div>

                <!-- Status -->
                <div class="ml-auto">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Status</h3>
                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800" id="employeeStatus">
                        Active
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button onclick="checkEmployee()" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all text-sm font-semibold">
                    Check
                </button>
                <button onclick="saveEmployee()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all text-sm font-semibold">
                    Save
                </button>
                <button onclick="discardChanges()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all text-sm font-semibold">
                    Discard
                </button>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="border-b border-gray-200 overflow-x-auto relative">
        <nav class="flex gap-1 px-6 relative">
            <!-- Sliding Indicator -->
            <div id="tabIndicator" class="absolute bottom-0 h-0.5 bg-red-800 transition-all duration-300 ease-in-out"></div>
            
            <button class="detail-tab active px-4 py-3 text-sm font-semibold text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="basic-data" onclick="switchDetailTab('basic-data', event)">
                Basic Data
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="address" onclick="switchDetailTab('address', event)">
                Address
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="identification" onclick="switchDetailTab('identification', event)">
                Identification
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="family" onclick="switchDetailTab('family', event)">
                Family Member
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="education" onclick="switchDetailTab('education', event)">
                Education
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="qualification" onclick="switchDetailTab('qualification', event)">
                Qualification
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="contract" onclick="switchDetailTab('contract', event)">
                Contract
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="bank" onclick="switchDetailTab('bank', event)">
                Bank Account
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="payment" onclick="switchDetailTab('payment', event)">
                Basic Payment
            </button>
            <button class="detail-tab px-4 py-3 text-sm font-semibold text-gray-600 hover:text-red-800 whitespace-nowrap transition-colors duration-200" data-tab="attachment" onclick="switchDetailTab('attachment', event)">
                Attachment
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="p-6">
        <!-- Basic Data Tab -->
        <div id="tab-basic-data" class="detail-tab-content">
            @include('master.basicdata')
        </div>

        <!-- Address Tab -->
        <div id="tab-address" class="detail-tab-content hidden">
            @include('master.address')
        </div>

        <!-- Identification Tab -->
        <div id="tab-identification" class="detail-tab-content hidden">
            @include('master.identification')
        </div>

        <!-- Family Member Tab -->
        <div id="tab-family" class="detail-tab-content hidden">
            @include('master.family')
        </div>

        <!-- Education Tab -->
        <div id="tab-education" class="detail-tab-content hidden">
            @include('master.education')
        </div>

        <!-- Qualification Tab -->
        <div id="tab-qualification" class="detail-tab-content hidden">
            @include('master.qualification')
        </div>

        <!-- Contract Tab -->
        <div id="tab-contract" class="detail-tab-content hidden">
            @include('master.contract')
        </div>

        <!-- Bank Account Tab -->
        <div id="tab-bank" class="detail-tab-content hidden">
            @include('master.bank')
        </div>

        <!-- Basic Payment Tab -->
        <div id="tab-payment" class="detail-tab-content hidden">
            @include('master.payment')
        </div>

        <!-- Attachment Tab -->
        <div id="tab-attachment" class="detail-tab-content hidden">
            @include('master.attachment')
        </div>
    </div>
</div>

@push('scripts')
<script>
    function switchDetailTab(tabName, event) {
        // Remove active from all tabs
        document.querySelectorAll('.detail-tab').forEach(tab => {
            tab.classList.remove('text-red-800');
            tab.classList.add('text-gray-600');
        });
        
        // Hide all tab contents with fade effect
        document.querySelectorAll('.detail-tab-content').forEach(content => {
            content.style.opacity = '0';
            setTimeout(() => {
                content.classList.add('hidden');
            }, 150);
        });
        
        // Activate selected tab
        const activeTab = event.target.closest('.detail-tab');
        if (activeTab) {
            activeTab.classList.add('text-red-800');
            activeTab.classList.remove('text-gray-600');
            
            // Move the indicator
            moveTabIndicator(activeTab);
        }
        
        // Show selected content with fade effect
        setTimeout(() => {
            const selectedContent = document.getElementById('tab-' + tabName);
            selectedContent.classList.remove('hidden');
            setTimeout(() => {
                selectedContent.style.opacity = '1';
            }, 10);
        }, 150);
    }

    function moveTabIndicator(activeTab) {
        const indicator = document.getElementById('tabIndicator');
        const tabRect = activeTab.getBoundingClientRect();
        const navRect = activeTab.parentElement.getBoundingClientRect();
        
        const left = tabRect.left - navRect.left;
        const width = tabRect.width;
        
        indicator.style.left = left + 'px';
        indicator.style.width = width + 'px';
    }

    function checkEmployee() {
        showNotification('Check employee data', 'info');
    }

    async function saveEmployee() {
        if (await showConfirm('Save changes to employee data?', 'Save Changes', 'primary')) {
            showNotification('Employee data saved successfully!', 'success');
            window.location.href = '{{ route("master") }}';
        }
    }

    async function discardChanges() {
        if (await showConfirm('Discard all changes?', 'Discard Changes', 'danger')) {
            window.location.href = '{{ route("master") }}';
        }
    }

    // Initialize indicator position and fade effect
    document.addEventListener('DOMContentLoaded', function() {
        const activeTab = document.querySelector('.detail-tab.active');
        if (activeTab) {
            moveTabIndicator(activeTab);
        }
        
        // Add transition to tab contents
        document.querySelectorAll('.detail-tab-content').forEach(content => {
            content.style.transition = 'opacity 0.15s ease-in-out';
            content.style.opacity = content.classList.contains('hidden') ? '0' : '1';
        });
        
        // Update indicator on window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const activeTab = document.querySelector('.detail-tab.active');
                if (activeTab) {
                    moveTabIndicator(activeTab);
                }
            }, 250);
        });
    });
</script>
@endpush
@endsection