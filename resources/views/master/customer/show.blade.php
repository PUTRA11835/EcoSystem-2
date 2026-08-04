@extends('dashboard')

@section('title', 'Business Partner Detail')
@section('page-title', 'Business Partner Detail - ' . ($customer->basicData->name_1 ?? 'N/A'))

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-6">
    <!-- Header dengan tombol back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('master.customer.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to List
        </a>
    </div>

    <!-- Customer Profile Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 text-center sm:text-left">
            <div id="headerInitials" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-gradient-to-br from-red-800 to-red-950 text-white flex items-center justify-center font-bold text-3xl sm:text-4xl flex-shrink-0">
                @php
                    $name1 = $customer->basicData->name_1 ?? 'N';
                    $initials = strtoupper(substr($name1, 0, 1));
                    if (strlen($name1) > 1 && strpos($name1, ' ') !== false) {
                        $parts = explode(' ', $name1);
                        $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
                    }
                @endphp
                {{ $initials }}
            </div>
            <div class="flex-1 w-full min-w-0">
                <div class="flex flex-col sm:flex-row items-center sm:items-start sm:justify-between gap-2 mb-4">
                    <div>
                        <h1 id="headerName1" class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $customer->basicData->name_1 ?? 'N/A' }}</h1>
                        <p id="headerName2" class="text-lg text-gray-600 mt-1 {{ ($customer->basicData->name_2 ?? '') ? '' : 'hidden' }}">{{ $customer->basicData->name_2 ?? '' }}</p>
                    </div>
                    @php
                        $statusClass = 'bg-gray-100 text-gray-800';
                        $statusLabel = 'Unknown';

                        if (isset($customer->basicData->deletion_flag) && $customer->basicData->deletion_flag) {
                            $statusClass = 'bg-red-100 text-red-800';
                            $statusLabel = 'Flagged for Deletion';
                        } elseif (isset($customer->basicData->block) && $customer->basicData->block) {
                            $statusClass = 'bg-yellow-100 text-yellow-800';
                            $statusLabel = 'Blocked';
                        } elseif (isset($customer->is_active) && $customer->is_active) {
                            $statusClass = 'bg-green-100 text-green-800';
                            $statusLabel = 'Active';
                        } else {
                            $statusClass = 'bg-gray-100 text-gray-800';
                            $statusLabel = 'Inactive';
                        }
                    @endphp
                    <span id="headerStatusBadge" class="inline-block px-4 py-2 text-sm font-semibold rounded-full {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Customer Code</p>
                        <p id="headerCustomerCode" class="font-semibold text-gray-900">{{ $customer->customer_code ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Type</p>
                        <p id="headerType" class="font-semibold text-gray-900">{{ $customer->type ?? 'Customer' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Email</p>
                        <p id="headerEmail" class="font-semibold text-gray-900">{{ $customer->email ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Phone</p>
                        <p id="headerPhone" class="font-semibold text-gray-900">{{ $customer->basicData->telephone ?? $customer->basicData->cell_phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Customer Group</p>
                        <p id="headerCustomerGroup" class="font-semibold text-gray-900">{{ $customer->basicData->customer_group ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Customer Category</p>
                        <p id="headerCustomerCategory" class="font-semibold text-gray-900">{{ $customer->basicData->customer_category ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Industry Sector</p>
                        <p id="headerIndustrySector" class="font-semibold text-gray-900">{{ $customer->basicData->industry_sector ?? 'N/A' }}</p>
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
                <button onclick="switchSection('contact')" data-section="contact" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Contact Person
                </button>
                <button onclick="switchSection('identification')" data-section="identification" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Identification
                </button>
                <button onclick="switchSection('bank')" data-section="bank" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Bank Account
                </button>
                <button onclick="switchSection('credential')" data-section="credential" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        Credential
                    </span>
                </button>
                <button onclick="switchSection('attachment')" data-section="attachment" class="section-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-red-800 hover:border-gray-300 whitespace-nowrap">
                    Attachment
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <div id="section-basic-data" class="section-content">
                @include('master.customer.sections.basicdata', ['customer' => $customer, 'customerId' => $customer->customer_id])
            </div>
            <div id="section-address" class="section-content hidden">
                @include('master.customer.sections.address', ['customer' => $customer, 'customerId' => $customer->customer_id])
            </div>
            <div id="section-contact" class="section-content hidden">
                @include('master.customer.sections.contact', ['customer' => $customer, 'customerId' => $customer->customer_id])
            </div>
            <div id="section-identification" class="section-content hidden">
                @include('master.customer.sections.identification', ['customer' => $customer, 'customerId' => $customer->customer_id])
            </div>
            <div id="section-bank" class="section-content hidden">
                @include('master.customer.sections.bank', ['customer' => $customer, 'customerId' => $customer->customer_id])
            </div>
            <div id="section-credential" class="section-content hidden">
                @include('master.customer.sections.credential', ['customer' => $customer, 'customerId' => $customer->customer_id])
            </div>
            <div id="section-attachment" class="section-content hidden">
                @include('master.customer.sections.attachment', ['customer' => $customer, 'customerId' => $customer->customer_id])
            </div>
        </div>
    </div>
</div>

{{-- New customer group modal --}}
<div id="newGroupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-1">New Customer Group</h3>
            <p class="text-sm text-gray-600 mb-4">Enter a name for the new customer group.</p>
            <input type="text" id="newGroupName" placeholder="Customer group name"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();confirmNewGroup();}">
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeNewGroupModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="button" onclick="confirmNewGroup()" class="flex-1 px-4 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">Create</button>
            </div>
        </div>
    </div>
</div>

<script>
    const customerId = {{ $customer->customer_id }};
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
                if (typeof loadCustomerBasicData === 'function') {
                    loadCustomerBasicData(customerId);
                }
                break;
            case 'address':
                if (typeof loadAddresses === 'function') {
                    loadAddresses(customerId);
                }
                break;
            case 'contact':
                if (typeof loadContacts === 'function') {
                    loadContacts(customerId);
                }
                break;
            case 'identification':
                if (typeof loadIdentifications === 'function') {
                    loadIdentifications(customerId);
                }
                break;
            case 'bank':
                if (typeof loadBankAccounts === 'function') {
                    loadBankAccounts(customerId);
                }
                break;
        }
    }

    // Save current section
    function saveCurrentSection() {
        switch(currentSection) {
            case 'basic-data':
                saveCustomerBasicData(customerId);
                break;
            case 'address':
                if (typeof saveAddresses === 'function') {
                    saveAddresses();
                }
                break;
            case 'contact':
                if (typeof saveContacts === 'function') {
                    saveContacts();
                }
                break;
            default:
                showNotification('Save function not implemented for this section', 'info');
        }
    }

    // Refresh the header card from server without full page reload
    window.refreshHeader = async function(id) {
        try {
            const res = await fetch(`/api/customers/${id}/header`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const result = await res.json();
            if (!result.success) return;
            const d = result.data;
            document.getElementById('headerInitials').textContent     = d.initials || 'N';
            document.getElementById('headerName1').textContent        = d.name_1 || 'N/A';
            const name2El = document.getElementById('headerName2');
            name2El.textContent = d.name_2 || '';
            name2El.classList.toggle('hidden', !d.name_2);
            const badge = document.getElementById('headerStatusBadge');
            badge.textContent = d.status_label;
            badge.className   = 'inline-block px-4 py-2 text-sm font-semibold rounded-full ' + d.status_class;
            document.getElementById('headerCustomerCode').textContent     = d.customer_code     || 'N/A';
            document.getElementById('headerType').textContent             = d.type             || 'Customer';
            document.getElementById('headerEmail').textContent            = d.email             || 'N/A';
            document.getElementById('headerPhone').textContent            = d.phone             || 'N/A';
            document.getElementById('headerCustomerGroup').textContent    = d.customer_group    || 'N/A';
            document.getElementById('headerCustomerCategory').textContent = d.customer_category || 'N/A';
            document.getElementById('headerIndustrySector').textContent   = d.industry_sector   || 'N/A';
        } catch (e) {
            console.error('refreshHeader error', e);
        }
    };

    // Load customer basic data
    async function loadCustomerBasicData(customerId) {
        try {
            const response = await fetch(`/api/customers/${customerId}/basic-data`, {
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

                // Fields on `customer` table (not `customer_basic_data`) — merged into
                // basicData by the show() endpoint. setValue is no-op if id absent.
                setValue('customerDomain', basicData.domain);
                setValue('companyEmail', basicData.email);
                setSelect('partnerType', basicData.type || 'Customer');

                // General Information
                setValue('title', basicData.title);
                setValue('name1', basicData.name_1);
                setValue('name2', basicData.name_2);
                setValue('name3', basicData.name_3);
                setValue('name4', basicData.name_4);
                setValue('searchTerm1', basicData.search_term_1);
                setValue('searchTerm2', basicData.search_term_2);
                // NOTE: jangan set 'street','city','country','email', dst di sini.
                // Field-field itu TIDAK ada di section Basic Data — id yang sama hanya
                // dimiliki section Address. setValue() akan bocor mengisi form Address
                // (mis. Email Address ke-isi customer.email padahal belum pilih alamat).
                // Data Address dimuat sendiri oleh loadAddresses()/loadAddressToForm().

                // Customer Information
                setValue('externalNumber', basicData.external_number);
                // Customer Group & Parent — native <select> (select-enhance); set value + fire change
                setSelect('customerGroupId', basicData.customer_group_id ?? '');
                setSelect('customerParentId', basicData.parent_customer_id ?? '');
                setValue('customerCategory', basicData.customer_category);
                setValue('industrySector', basicData.industry_sector);
                setValue('creditLimitType', basicData.credit_limit_type);
                setValue('ecAccountExecutive', basicData.ec_account_executive);
                setValue('sapAccountExecutive', basicData.sap_account_executive);
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

    // Save customer basic data
    async function saveCustomerBasicData(customerId) {
        const basicData = {
            customer_code: getValue('customerCode').toUpperCase(),
            type: getValue('partnerType') || 'Customer',
            domain: getValue('customerDomain'),
            email: getValue('companyEmail'),
            title: getValue('title'),
            name_1: getValue('name1'),
            name_2: getValue('name2'),
            search_term_1: getValue('searchTerm1'),
            search_term_2: getValue('searchTerm2'),
            external_number: getValue('externalNumber'),
            customer_group_id: getValue('customerGroupId') || null,
            parent_customer_id: getValue('customerParentId') || null,
            customer_category: getValue('customerCategory'),
            credit_limit_type: getValue('creditLimitType'),
            industry_sector: getValue('industrySector'),
            ec_account_executive: getValue('ecAccountExecutive'),
            sap_account_executive: getValue('sapAccountExecutive'),
            authorization_group: getValue('authorizationGroup'),
            block: getCheckbox('block'),
            deletion_flag: getCheckbox('deletionFlag')
        };

        try {
            const response = await fetch(`/api/customers/${customerId}/basic-data`, {
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
                loadCustomerBasicData(customerId);
                refreshHeader(customerId);
            } else {
                showNotification('Failed to save: ' + (data.message || 'Unknown error'), 'error');
                if (data.errors) {
                    console.error('Validation errors:', data.errors);
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
        if (el) el.value = value || '';
    }

    // Set a native <select> value and notify select-enhance to re-render its label.
    function setSelect(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        el.value = (value === null || value === undefined) ? '' : String(value);
        el.dispatchEvent(new Event('change'));
    }

    let _newGroupResolver = null;

    function openNewGroupModal() {
        const modal = document.getElementById('newGroupModal');
        const input = document.getElementById('newGroupName');
        input.value = '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => input.focus(), 50);
        return new Promise((resolve) => { _newGroupResolver = resolve; });
    }

    function closeNewGroupModal() {
        const modal = document.getElementById('newGroupModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (_newGroupResolver) { _newGroupResolver(null); _newGroupResolver = null; }
    }

    function confirmNewGroup() {
        const name = (document.getElementById('newGroupName').value || '').trim();
        const modal = document.getElementById('newGroupModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (_newGroupResolver) { _newGroupResolver(name); _newGroupResolver = null; }
    }

    // Create a brand-new customer group during edit, then select it.
    async function createCustomerGroupInline() {
        const name = (await openNewGroupModal() || '').trim();
        if (!name) return;
        try {
            const res = await fetch('/api/customer-groups', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({ name })
            });
            const data = await res.json();
            if (data.success) {
                const sel = document.getElementById('customerGroupId');
                if (sel) {
                    const opt = document.createElement('option');
                    opt.value = data.data.id;
                    opt.textContent = data.data.name;
                    sel.appendChild(opt);
                    setSelect('customerGroupId', data.data.id);
                }
                showNotification('Customer group created. Remember to Save Changes.', 'success');
            } else {
                showNotification(data.message || 'Failed to create group', 'error');
            }
        } catch (e) {
            console.error('createCustomerGroupInline error', e);
            showNotification('An error occurred while creating group', 'error');
        }
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

    // Load data when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadCustomerBasicData(customerId);
    });
</script>
@endsection