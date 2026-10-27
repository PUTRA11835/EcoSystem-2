{{--
    Contract tab — READ-ONLY recap of the employee's contracts (history). There is no form here on either page:
    contracts are created and changed in HR → Contracts, which only roles with that menu can open.

      My Profile            recap only. The Salary column shows the total of the salary components in force today
                            (GET /api/profile/salary-summary) on the active contract, not contract.salary. The Signing column
                            carries the signing workflow (GET /api/profile/contract-signing): "Awaiting your signature" with a
                            Review & sign button, "Signed by you", and — once Legal releases it — a download.
      Master > Employee     recap + a "Manage in Contracts" button for roles that may open the Contracts menu.

    Receives from show.blade.php: $employeeId, $isOwnProfile (optional).
--}}
@php
    $ownView   = isset($isOwnProfile) && $isOwnProfile;
    $manageUrl = !$ownView && $canDo('general.contracts.list') ? route('general.contracts.show', $employeeId) : null;
    // Management → Employee → Contract: what an employee sees on their own profile (HR viewing someone else is unaffected).
    $showSalary = $ownView && \App\Services\Management\EmployeeMasterData::rule('contract', 'show_salary');
    $soonDays   = (int) \App\Services\Management\EmployeeMasterData::rule('contract', 'ending_soon_days');
    $cols       = 5 + ($ownView ? 1 : 0) + ($showSalary ? 1 : 0);
@endphp
<div id="contractRecap" class="space-y-4">
    {{-- My Profile: contracts forwarded to me to approve (filled in by the script when there are any). --}}
    @if($ownView)
        <div id="contractApprovals" class="hidden text-sm bg-purple-50 border border-purple-200 text-purple-900 rounded-lg px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <span><i class="fas fa-file-signature mr-1"></i> <strong id="contractApprovalsCount">0</strong> contract(s) are waiting for your approval.</span>
            <a id="contractApprovalsLink" href="#" class="px-3 py-1.5 rounded-lg bg-purple-700 text-white text-xs font-semibold hover:bg-purple-800">Review &amp; sign</a>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-2 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <h3 class="text-base font-semibold text-gray-900">Contract History</h3>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium"><i class="fas fa-lock text-[10px]"></i> View Only</span>
        </div>
        @if($manageUrl)
            <a href="{{ $manageUrl }}" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                <i class="fas fa-file-signature text-xs"></i> Manage in Contracts
            </a>
        @endif
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Contract</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Position</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Period</th>
                    @if($showSalary)
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Salary</th>
                    @endif
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                    @if($ownView)
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Signing</th>
                    @endif
                </tr>
            </thead>
            <tbody id="contractTableBody" class="bg-white divide-y divide-gray-100">
                <tr><td colspan="{{ $cols }}" class="px-4 py-10 text-center text-sm text-gray-400">Loading contracts…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const empId   = {{ (int) $employeeId }};
    const OWN     = @json($ownView);
    const SALARY  = @json($showSalary);
    const SOON    = {{ $soonDays }};
    const COLS    = {{ $cols }};
    const body    = document.getElementById('contractTableBody');
    const esc     = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const date    = d => d ? new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
    const rupiah  = n => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
    const badge   = (cls, text) => `<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full ${cls}">${text}</span>`;

    function status(c) {
        if (!c.is_active) return badge('bg-gray-100 text-gray-800', 'Inactive');
        if (!c.end_date) return badge('bg-blue-100 text-blue-800', 'Active (Permanent)');
        const days = Math.ceil((new Date(c.end_date) - new Date()) / 86400000);
        if (days < 0) return badge('bg-red-100 text-red-800', 'Expired');
        if (days <= SOON) return badge('bg-yellow-100 text-yellow-800', 'Expiring Soon');
        return badge('bg-green-100 text-green-800', 'Active');
    }

    function empty(message) {
        body.innerHTML = `<tr><td colspan="${COLS}" class="px-4 py-12 text-center text-sm text-gray-500">${esc(message)}</td></tr>`;
    }

    // The active components' total stands in for contract.salary on My Profile; only the active contract carries it.
    async function activeTotal() {
        if (!SALARY) return null;
        try {
            const res = await fetch('/api/profile/salary-summary', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const json = await res.json();
            return json.success ? Number(json.summary?.total) || 0 : null;
        } catch (e) { return null; }
    }

    // The signing state of my contracts and the approvals waiting for me (My Profile only).
    async function signingState() {
        if (!OWN) return { data: {}, approvals: 0 };
        try {
            const res = await fetch('/api/profile/contract-signing', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const json = await res.json();
            return json.success ? json : { data: {}, approvals: 0 };
        } catch (e) { return { data: {}, approvals: 0 }; }
    }

    function signingCell(c, state) {
        const s = state.data[c.contract_id];
        if (!s || !s.label) return '<span class="text-gray-300">-</span>';
        const due = s.action === 'sign' && s.deadline
            ? `<span class="block text-xs text-gray-500 mt-1">Due ${esc(s.deadline)}${s.days !== null && s.days !== undefined ? ' · ' + (s.days < 0 ? Math.abs(s.days) + ' day(s) overdue' : (s.days === 0 ? 'today' : s.days + ' day(s) left')) : ''}</span>` : '';
        const btn = s.action === 'sign'
            ? `<a href="${esc(s.url)}" class="inline-block mt-1.5 px-3 py-1.5 rounded-lg bg-red-800 text-white text-xs font-semibold hover:bg-red-900">Review &amp; sign</a>`
            : (s.action === 'download'
                ? `<a href="${esc(s.url)}" class="inline-block mt-1.5 px-3 py-1.5 rounded-lg bg-green-700 text-white text-xs font-semibold hover:bg-green-800">Download</a>`
                : (s.action === 'view' ? `<a href="${esc(s.url)}" class="inline-block mt-1.5 text-xs font-semibold text-gray-600 underline">View</a>` : ''));
        return badge(esc(s.tone), esc(s.label)) + due + (btn ? `<div>${btn}</div>` : '');
    }

    async function load() {
        try {
            const [res, total, state] = await Promise.all([
                fetch(`/api/employees/${empId}/contract`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }),
                activeTotal(),
                signingState(),
            ]);
            if (OWN && state.approvals > 0) {
                document.getElementById('contractApprovalsCount').textContent = state.approvals;
                document.getElementById('contractApprovalsLink').href = state.approvals_url;
                document.getElementById('contractApprovals').classList.remove('hidden');
            }
            const json = await res.json();
            const list = json.success && Array.isArray(json.data) ? json.data : [];
            if (!list.length) return empty('No contracts yet.');

            // The contract in force: active and not past its end date. Only that row shows the compensation total.
            const inForce = list.find(c => c.is_active && (!c.end_date || new Date(c.end_date) >= new Date()));

            body.innerHTML = list.map(c => `<tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm"><strong class="font-semibold text-gray-900">${esc(c.contract_name || '-')}</strong>
                    ${c.contract_number ? `<br><small class="text-xs text-gray-500">${esc(c.contract_number)}</small>` : ''}</td>
                <td class="px-4 py-3 text-sm">${badge('bg-blue-100 text-blue-800', esc(c.contract_type || '-'))}</td>
                <td class="px-4 py-3 text-sm text-gray-600">${esc(c.position || '-')}</td>
                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">${date(c.start_date)} – ${c.end_date ? date(c.end_date) : 'Permanent'}</td>
                ${SALARY ? `<td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">${inForce && c.contract_id === inForce.contract_id && total !== null ? `<span class="font-semibold text-gray-900">${rupiah(total)}</span>` : '-'}</td>` : ''}
                <td class="px-4 py-3 text-sm">${status(c)}</td>
                ${OWN ? `<td class="px-4 py-3 text-sm">${signingCell(c, state)}</td>` : ''}
            </tr>`).join('');
        } catch (e) {
            empty('The contracts could not be loaded. Please try again.');
        }
    }

    (window.onSectionFirstShow || function (t, fn) { document.addEventListener('DOMContentLoaded', fn); })('contract', load);
})();
</script>
