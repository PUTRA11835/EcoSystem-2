@extends('dashboard')
@section('title', 'My Leave & Permit')
@section('page-title', 'My Leave & Permit')
@section('page-subtitle', 'Track your quota balances, apply for leave or permit, and view status history')

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl primary-gradient flex items-center justify-center shadow-sm text-white">
                <i class="fas fa-calendar-alt text-base"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">My Leave & Permit</h1>
                <p class="text-xs text-gray-400 mt-0.5">View your personal leave & permit quota balances and submit applications</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Year Selector Filter -->
            <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-1.5 shadow-sm text-xs">
                <i class="fas fa-calendar-alt text-gray-400"></i>
                <span class="font-semibold text-gray-600">Year:</span>
                @php $cYear = $currentYear ?? (int) date('Y'); @endphp
                <select id="selectGlobalYear" class="bg-transparent font-bold text-gray-800 focus:outline-none cursor-pointer" onchange="onGlobalYearChange()">
                    @for($y = $cYear + 1; $y >= $cYear - 2; $y--)
                        <option value="{{ $y }}" {{ $y === $cYear ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <!-- Apply Button -->
            <button onclick="openApplyModal()" class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                <i class="fas fa-plus text-xs"></i>
                <span>Apply Leave / Permit</span>
            </button>
        </div>
    </div>

    <!-- Stats Cards Overview -->
    <div id="quotaOverviewCards" class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow transition-all">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Quota Allocated</p>
            <p class="text-2xl font-bold text-gray-800 mt-1" id="statTotalQuota">0 <span class="text-xs font-normal text-gray-400">days</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow transition-all">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Quota Used (Approved)</p>
            <p class="text-2xl font-bold text-green-600 mt-1" id="statUsedQuota">0 <span class="text-xs font-normal text-gray-400">days</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow transition-all">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Pending Approvals</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1" id="statPendingQuota">0 <span class="text-xs font-normal text-gray-400">days</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow transition-all">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Remaining Quota</p>
            <p class="text-2xl font-bold primary-text mt-1" id="statRemainingQuota">0 <span class="text-xs font-normal text-gray-400">days</span></p>
        </div>
    </div>

    <!-- Quota Summary per Type Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700 flex items-center gap-2">
                <i class="fas fa-pie-chart text-red-600"></i> My Quota Breakdown (<span id="txtUserQuotaYear">{{ $cYear }}</span>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3">Leave / Permit Type</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3 text-center">Quota Rule</th>
                        <th class="px-4 py-3 text-right">Allocated</th>
                        <th class="px-4 py-3 text-right">Used</th>
                        <th class="px-4 py-3 text-right">Pending</th>
                        <th class="px-5 py-3 text-right font-bold">Remaining</th>
                    </tr>
                </thead>
                <tbody id="tblUserQuotaBody" class="divide-y divide-gray-100 text-gray-700">
                    <tr>
                        <td colspan="7" class="px-5 py-6 text-center text-gray-400">Loading quota information...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- History Log & Applications Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h3 class="font-bold text-xs uppercase tracking-wider text-gray-700 flex items-center gap-2">
                <i class="fas fa-history text-red-600"></i> My Application History Log
            </h3>
            <div class="flex items-center gap-2">
                <select id="filterMyStatus" class="text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-red-500" onchange="loadMyApplications()">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="revision">Ask for Edit (Revision)</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3">App No</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Dates</th>
                        <th class="px-4 py-3 text-center">Days</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="tblMyAppsBody" class="divide-y divide-gray-100 text-gray-700">
                    <tr>
                        <td colspan="7" class="px-5 py-6 text-center text-gray-400">Loading application history...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Partial Views -->
@include('hr-general.leave-permit.partials.modal-apply')
@include('hr-general.leave-permit.partials.modal-review')

<script>
    const isHR = false;
    let globalYear = parseInt(document.getElementById('selectGlobalYear').value);
    let cachedUserQuotas = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadUserQuotaAndSummary();
        loadMyApplications();
    });

    function onGlobalYearChange() {
        globalYear = parseInt(document.getElementById('selectGlobalYear').value);
        document.getElementById('txtUserQuotaYear').innerText = globalYear;
        loadUserQuotaAndSummary();
        loadMyApplications();
    }

    async function loadUserQuotaAndSummary() {
        try {
            const res = await fetch(`/api/hr-general/leave-permit/user-quotas?year=${globalYear}`, { credentials: 'same-origin' });
            const json = await res.json();

            if (json.success) {
                cachedUserQuotas = json.data;

                let totAlloc = 0, totUsed = 0, totPending = 0, totRem = 0;
                let html = '';

                json.data.forEach(item => {
                    if (!item.is_event_based) {
                        totAlloc += item.allocated_quota;
                        totUsed += item.used_quota;
                        totPending += item.pending_quota;
                        totRem += item.remaining_quota;
                    }

                    let ruleBadge = '';
                    if (item.is_monthly_reset) {
                        ruleBadge = `<span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded">Resets Monthly</span>`;
                    } else if (item.is_event_based) {
                        if (item.type_code === 'CTU' || !item.requires_attachment) {
                            ruleBadge = `<span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 rounded">Event-based</span>`;
                        } else {
                            ruleBadge = `<span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 rounded">Event-based (Doctor Note)</span>`;
                        }
                    } else {
                        ruleBadge = `<span class="bg-gray-100 text-gray-800 text-[10px] font-bold px-2 py-0.5 rounded">Annual Quota</span>`;
                    }

                    const remDisplay = item.is_event_based ? 'Uncapped' : item.remaining_quota;

                    html += `
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 font-semibold text-gray-900">${item.type_name} <span class="text-gray-400 font-normal">(${item.type_code})</span></td>
                            <td class="px-4 py-3 uppercase text-[10px] font-bold text-gray-500">${item.category}</td>
                            <td class="px-4 py-3 text-center">${ruleBadge}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-700">${item.is_event_based ? '-' : item.allocated_quota}</td>
                            <td class="px-4 py-3 text-right font-medium text-green-600">${item.used_quota}</td>
                            <td class="px-4 py-3 text-right font-medium text-yellow-600">${item.pending_quota}</td>
                            <td class="px-5 py-3 text-right font-bold ${remDisplay > 0 || item.is_event_based ? 'text-red-700' : 'text-gray-400'}">${remDisplay}</td>
                        </tr>
                    `;
                });

                document.getElementById('tblUserQuotaBody').innerHTML = html || `<tr><td colspan="7" class="px-5 py-6 text-center text-gray-400">No leave types available for your gender.</td></tr>`;

                document.getElementById('statTotalQuota').innerHTML = `${totAlloc} <span class="text-xs font-normal text-gray-400">days</span>`;
                document.getElementById('statUsedQuota').innerHTML = `${totUsed} <span class="text-xs font-normal text-gray-400">days</span>`;
                document.getElementById('statPendingQuota').innerHTML = `${totPending} <span class="text-xs font-normal text-gray-400">days</span>`;
                document.getElementById('statRemainingQuota').innerHTML = `${totRem} <span class="text-xs font-normal text-gray-400">days</span>`;

                populateTypeOptionsWithQuota(json.data);
            }
        } catch (err) {
            console.error(err);
        }
    }

    function populateTypeOptionsWithQuota(types) {
        const select = document.getElementById('applyTypeId');
        if (!select) return;
        let html = `<option value="" disabled selected>-- Select Leave / Permit Type --</option>`;

        types.forEach(t => {
            const isExhausted = !t.is_event_based && t.remaining_quota <= 0;
            const statusLabel = isExhausted ? ' [EXHAUSTED / NO QUOTA LEFT]' : (t.is_event_based ? ' [EVENT BASED]' : ` [Available: ${t.remaining_quota}d]`);

            html += `
                <option value="${t.type_id}" 
                        data-code="${t.type_code}"
                        data-requires-attachment="${t.requires_attachment ? '1' : '0'}"
                        ${isExhausted ? 'disabled' : ''}>
                    ${t.type_name} (${t.type_code}) ${statusLabel}
                </option>
            `;
        });

        select.innerHTML = html;
    }

    async function loadMyApplications() {
        const status = document.getElementById('filterMyStatus').value;
        try {
            const res = await fetch(`/api/hr-general/leave-permit/applications?my_only=1&year=${globalYear}&status=${status}`, { credentials: 'same-origin' });
            const json = await res.json();

            if (json.success) {
                let html = '';
                if (json.data.length === 0) {
                    html = `<tr><td colspan="7" class="px-5 py-6 text-center text-gray-400">No application records found for ${globalYear}.</td></tr>`;
                } else {
                    json.data.forEach(app => {
                        html += `
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 font-bold text-gray-900">${app.application_no}</td>
                                <td class="px-4 py-3 font-medium">${app.type_name}</td>
                                <td class="px-4 py-3 text-gray-600">${app.start_date} to ${app.end_date}</td>
                                <td class="px-4 py-3 text-center font-bold text-red-700">${app.total_days}</td>
                                <td class="px-4 py-3 text-gray-600 truncate max-w-xs">${app.reason}</td>
                                <td class="px-4 py-3 text-center">${renderStatusBadge(app.status)}</td>
                                <td class="px-5 py-3 text-right">
                                    <button onclick='openReviewModal(${JSON.stringify(app)})' class="text-red-700 hover:text-red-900 font-semibold text-xs">
                                        View Details
                                    </button>
                                    ${(app.status === 'revision' || app.status === 'pending') ? `
                                        <button onclick='openEditModal(${JSON.stringify(app)})' class="ml-2 text-yellow-700 hover:text-yellow-900 font-semibold text-xs">
                                            Edit
                                        </button>
                                    ` : ''}
                                </td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('tblMyAppsBody').innerHTML = html;
            }
        } catch (err) {
            console.error(err);
        }
    }

    function renderStatusBadge(status) {
        switch(status) {
            case 'approved':
                return `<span class="bg-green-100 text-green-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-check-circle"></i> Approved</span>`;
            case 'pending':
                return `<span class="bg-yellow-100 text-yellow-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-clock"></i> Pending</span>`;
            case 'revision':
                return `<span class="bg-blue-100 text-blue-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-edit"></i> Ask for Edit</span>`;
            case 'rejected':
                return `<span class="bg-red-100 text-red-800 font-bold px-2.5 py-0.5 rounded text-[10px] inline-flex items-center gap-1"><i class="fas fa-times-circle"></i> Rejected</span>`;
            default:
                return `<span class="bg-gray-100 text-gray-800 font-bold px-2.5 py-0.5 rounded text-[10px]">${status}</span>`;
        }
    }

    function openApplyModal() {
        document.getElementById('formApplyLeavePermit').reset();
        document.getElementById('applyAppId').value = '';
        document.getElementById('modalApplyTitle').innerText = 'Apply Leave / Permit';
        document.getElementById('daysCountBadge').classList.add('hidden');
        document.getElementById('quotaHintText').classList.add('hidden');
        const warnBanner = document.getElementById('applyQuotaWarningBanner');
        if (warnBanner) warnBanner.classList.add('hidden');

        const dayTypeSel = document.getElementById('applyDayType');
        if (dayTypeSel) dayTypeSel.value = 'full';
        const wfhWrap = document.getElementById('wfhPromptWrapper');
        if (wfhWrap) wfhWrap.classList.add('hidden');

        document.getElementById('modalApplyLeavePermit').classList.remove('hidden');
    }

    function closeApplyModal() {
        document.getElementById('modalApplyLeavePermit').classList.add('hidden');
    }

    function onDayTypeChange() {
        const dayType = document.getElementById('applyDayType') ? document.getElementById('applyDayType').value : 'full';
        const wfhWrapper = document.getElementById('wfhPromptWrapper');
        const startVal = document.getElementById('applyStartDate').value;
        const endInput = document.getElementById('applyEndDate');

        if (dayType === 'half') {
            if (wfhWrapper) wfhWrapper.classList.remove('hidden');
            if (startVal && endInput) endInput.value = startVal;
        } else {
            if (wfhWrapper) wfhWrapper.classList.add('hidden');
        }
        calculateDaysPreview();
    }

    function onTypeSelectChange() {
        const sel = document.getElementById('applyTypeId');
        if (!sel.value) return;
        const opt = sel.options[sel.selectedIndex];
        const reqAtt = opt.getAttribute('data-requires-attachment') === '1';
        const typeId = parseInt(sel.value);

        document.getElementById('attachmentRequiredAsterisk').classList.toggle('hidden', !reqAtt);

        const qItem = cachedUserQuotas.find(q => q.type_id === typeId);
        const hint = document.getElementById('quotaHintText');

        if (qItem) {
            hint.classList.remove('hidden');
            if (qItem.is_event_based) {
                if (qItem.type_code === 'CTU' || !qItem.requires_attachment) {
                    hint.innerText = 'ℹ️ Event-based leave: Submitted per occurrence (Unpaid).';
                } else {
                    hint.innerText = 'ℹ️ Event-based leave: Doctor note or supporting document is required.';
                }
                hint.className = 'text-xs text-purple-700 font-semibold mt-1 block';
            } else if (qItem.remaining_quota <= 0) {
                hint.innerText = '⚠️ Quota for this leave type has been exhausted.';
                hint.className = 'text-xs text-red-600 font-bold mt-1 block';
            } else {
                hint.innerText = `Available Remaining Quota: ${qItem.remaining_quota} day(s).`;
                hint.className = 'text-xs text-green-600 font-semibold mt-1 block';
            }
        }
        calculateDaysPreview();
    }

    function calculateDaysPreview() {
        const dayTypeSelect = document.getElementById('applyDayType');
        const dayType = dayTypeSelect ? dayTypeSelect.value : 'full';
        const start = document.getElementById('applyStartDate').value;
        const end = document.getElementById('applyEndDate').value;
        const badge = document.getElementById('daysCountBadge');
        const val = document.getElementById('daysCountValue');
        const warnBanner = document.getElementById('applyQuotaWarningBanner');
        const warnText = document.getElementById('applyQuotaWarningText');

        let requestedDays = 0;
        if (dayType === 'half') {
            requestedDays = 0.5;
            val.innerText = `0.5 day (Half-Day Leave)`;
            badge.classList.remove('hidden');
            badge.classList.add('flex');
        } else if (start && end) {
            const d1 = new Date(start);
            const d2 = new Date(end);
            if (d2 >= d1) {
                const diffTime = Math.abs(d2 - d1);
                requestedDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                val.innerText = `${requestedDays} day(s)`;
                badge.classList.remove('hidden');
                badge.classList.add('flex');
            } else {
                badge.classList.add('hidden');
            }
        } else {
            badge.classList.add('hidden');
        }

        // Live Quota Check Preview
        const typeId = parseInt(document.getElementById('applyTypeId').value || 0);
        const qItem = cachedUserQuotas.find(q => q.type_id === typeId);
        if (warnBanner && qItem && !qItem.is_event_based && requestedDays > 0) {
            if (requestedDays > qItem.remaining_quota) {
                if (warnText) warnText.innerText = `Requested duration (${requestedDays} day(s)) exceeds your available remaining quota balance (${qItem.remaining_quota} day(s)). Application cannot be submitted.`;
                warnBanner.classList.remove('hidden');
                warnBanner.classList.add('flex');
            } else {
                warnBanner.classList.add('hidden');
                warnBanner.classList.remove('flex');
            }
        } else if (warnBanner) {
            warnBanner.classList.add('hidden');
            warnBanner.classList.remove('flex');
        }
    }

    async function handleApplySubmit(e) {
        e.preventDefault();
        const typeId = parseInt(document.getElementById('applyTypeId').value || 0);
        const start = document.getElementById('applyStartDate').value;
        const end = document.getElementById('applyEndDate').value;
        const dayTypeSelect = document.getElementById('applyDayType');
        const dayType = dayTypeSelect ? dayTypeSelect.value : 'full';
        let reason = document.getElementById('applyReason').value.trim();

        // 1. Blank Reason Validation
        if (!reason) {
            if (typeof showToast === 'function') {
                showToast('Reason / Purpose is required and cannot be left blank.', 'warning');
            }
            document.getElementById('applyReason').focus();
            return;
        }

        // 2. Date Selection Validation
        if (!start || !end) {
            if (typeof showToast === 'function') {
                showToast('Please select valid Start Date and End Date.', 'warning');
            }
            return;
        }

        const d1 = new Date(start);
        const d2 = new Date(end);
        if (d2 < d1) {
            if (typeof showToast === 'function') {
                showToast('End Date cannot be earlier than Start Date.', 'warning');
            }
            return;
        }

        // 3. Half Day WFH Append & Quota Check
        let requestedDays = 1.0;
        if (dayType === 'half') {
            requestedDays = 0.5;
            const wfhVal = document.getElementById('applyWfhOption').value;
            const wfhText = wfhVal === 'wfh_continue' 
                ? 'Continue Working via WFH for remainder of day' 
                : 'No WFH / Off for remainder of day';
            reason += ` [Half-Day Leave: ${wfhText}]`;
        } else {
            const diffTime = Math.abs(d2 - d1);
            requestedDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        }

        const qItem = cachedUserQuotas.find(q => q.type_id === typeId);

        if (qItem && !qItem.is_event_based && requestedDays > qItem.remaining_quota) {
            if (typeof showToast === 'function') {
                showToast(`Cannot submit application: Requested duration (${requestedDays} day(s)) exceeds your remaining quota balance (${qItem.remaining_quota} day(s)).`, 'error');
            }
            const warnBanner = document.getElementById('applyQuotaWarningBanner');
            if (warnBanner) {
                warnBanner.classList.remove('hidden');
                warnBanner.classList.add('flex');
            }
            return;
        }

        const appId = document.getElementById('applyAppId').value;
        const form = document.getElementById('formApplyLeavePermit');
        const formData = new FormData(form);

        formData.append('leave_permit_type_id', typeId);
        formData.append('start_date', start);
        formData.append('end_date', dayType === 'half' ? start : end);
        formData.append('day_type', dayType);
        formData.append('total_days', requestedDays);
        formData.append('reason', reason);

        const fileInput = document.getElementById('applyAttachment');
        if (fileInput.files.length > 0) {
            formData.append('attachment', fileInput.files[0]);
        }

        const url = appId 
            ? `/api/hr-general/leave-permit/applications/${appId}/update`
            : `/api/hr-general/leave-permit/applications`;

        const btn = document.getElementById('btnSubmitApply');
        btn.disabled = true;
        btn.innerText = 'Submitting...';

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
                body: formData,
                credentials: 'same-origin'
            });

            const json = await res.json();
            if (json.success) {
                closeApplyModal();
                if (typeof showToast === 'function') {
                    showToast(json.message || 'Leave/permit application submitted successfully!', 'success');
                }
                loadUserQuotaAndSummary();
                loadMyApplications();
            } else {
                if (typeof showToast === 'function') {
                    showToast(json.message || 'Failed to submit application.', 'error');
                }
            }
        } catch (err) {
            if (typeof showToast === 'function') {
                showToast('An unexpected error occurred while submitting application.', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.innerText = 'Submit Application';
        }
    }

    function openEditModal(app) {
        openApplyModal();
        document.getElementById('modalApplyTitle').innerText = 'Edit Application (' + app.application_no + ')';
        document.getElementById('applyAppId').value = app.id;
        document.getElementById('applyTypeId').value = app.leave_permit_type_id;
        document.getElementById('applyStartDate').value = app.start_date;
        document.getElementById('applyEndDate').value = app.end_date;
        document.getElementById('applyReason').value = app.reason;
        onTypeSelectChange();
    }

    function openReviewModal(app) {
        document.getElementById('reviewAppId').value = app.id;
        document.getElementById('reviewAppNo').innerText = app.application_no;
        document.getElementById('reviewStatusBadge').innerHTML = renderStatusBadge(app.status);
        document.getElementById('reviewEmpName').innerText = app.employee_display_name || 'Self';
        document.getElementById('reviewTypeName').innerText = app.type_name;
        document.getElementById('reviewDates').innerText = `${app.start_date} ~ ${app.end_date}`;
        document.getElementById('reviewTotalDays').innerText = `${app.total_days} day(s)`;
        document.getElementById('reviewReason').innerText = app.reason;

        const attWrapper = document.getElementById('reviewAttachmentWrapper');
        if (app.attachment_path) {
            document.getElementById('reviewAttachmentLink').href = `/storage/${app.attachment_path}`;
            attWrapper.classList.remove('hidden');
        } else {
            attWrapper.classList.add('hidden');
        }

        let logHtml = '';
        if (app.logs && app.logs.length > 0) {
            app.logs.forEach(l => {
                const perfName = l.performer && l.performer.basic_data ? l.performer.basic_data.full_name : (l.performer ? l.performer.eci : 'System');
                logHtml += `
                    <div class="border-l-2 border-red-500 pl-2 py-0.5">
                        <span class="font-bold text-gray-800 uppercase">${l.action}</span> 
                        <span class="text-gray-400 text-[10px]">by ${perfName} at ${new Date(l.created_at).toLocaleString()}</span>
                        ${l.notes ? `<p class="text-gray-600 mt-0.5">${l.notes}</p>` : ''}
                    </div>
                `;
            });
        } else {
            logHtml = `<p class="text-gray-400">No activity history recorded.</p>`;
        }
        document.getElementById('reviewLogTimeline').innerHTML = logHtml;

        document.getElementById('modalReviewLeavePermit').classList.remove('hidden');
    }

    function closeReviewModal() {
        document.getElementById('modalReviewLeavePermit').classList.add('hidden');
    }
</script>
@endsection
