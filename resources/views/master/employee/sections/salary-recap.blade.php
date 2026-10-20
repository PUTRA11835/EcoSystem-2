{{--
    Salary recap — My Profile only, read-only, beneath the Contract history.

    Fed by GET /api/profile/salary-summary (self only, active components). It shows no controls: HR changes the
    components in Master > Employee > Contract, and the last saved values are what Payroll reads.
--}}
<div id="salaryRecap" class="border border-gray-200 rounded-xl bg-white overflow-hidden">
    <div class="px-5 py-4 flex items-center justify-between gap-4 border-b border-gray-200">
        <span class="flex items-center gap-3 min-w-0">
            <span class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0"><i class="fas fa-wallet text-red-800"></i></span>
            <span class="min-w-0">
                <span class="block text-base font-semibold text-gray-900">Salary</span>
                <span class="block text-xs text-gray-500">Components in force today. Changes are made by HR.</span>
            </span>
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium flex-shrink-0"><i class="fas fa-lock text-[10px]"></i> View Only</span>
    </div>

    <div class="px-5 py-5 space-y-5">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach(['base' => 'Base Salary', 'fixed' => 'Fixed Allowance', 'variable' => 'Other Income', 'total' => 'Total Compensation'] as $k => $label)
                <div class="border border-gray-200 rounded-lg px-4 py-3">
                    <span class="block text-xs text-gray-500">{{ $label }}</span>
                    <span id="salaryRecap-{{ $k }}" class="block text-sm font-bold {{ $k === 'total' ? 'text-blue-600' : 'text-gray-900' }}">–</span>
                </div>
            @endforeach
        </div>

        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 text-[11px] font-bold uppercase tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 w-14">No</th>
                            <th class="px-4 py-3">Component</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Effective From</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="salaryRecapRows" class="divide-y divide-gray-100">
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const $    = id => document.getElementById(id);
    const esc  = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const rp   = n => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
    const day  = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '–';

    async function load() {
        const rows = $('salaryRecapRows');
        try {
            const res  = await fetch('/api/profile/salary-summary', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) throw new Error();

            ['base', 'fixed', 'variable', 'total'].forEach(k => { $('salaryRecap-' + k).textContent = rp(json.summary[k]); });

            rows.innerHTML = json.data.length
                ? json.data.map((r, i) => `<tr>
                    <td class="px-4 py-3 text-blue-600">${i + 1}</td>
                    <td class="px-4 py-3 font-semibold text-gray-900">${esc(r.name)}</td>
                    <td class="px-4 py-3 text-gray-700">${esc(r.category)}</td>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">${esc(day(r.effective_from))}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">${rp(r.amount)}</td></tr>`).join('')
                : '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No salary components are recorded for you yet.</td></tr>';
        } catch (e) {
            rows.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-red-500">Your salary could not be loaded. Please try again.</td></tr>';
        }
    }

    (window.onSectionFirstShow || function (t, fn) { document.addEventListener('DOMContentLoaded', fn); })('contract', load);
})();
</script>
