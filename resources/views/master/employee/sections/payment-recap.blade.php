{{--
    Basic Payment tab on MY PROFILE — read-only recap of the latest published paystubs (GET /api/profile/paystub-summary,
    self only). It is a summary on purpose: the PDF downloads and the full history stay on the Paystub page, so nothing
    is duplicated here. Master > Employee keeps sections/payment.blade.php.
--}}
<div id="paymentRecap" class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-2 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <h3 class="text-base font-semibold text-gray-900">Recent Paystubs</h3>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium"><i class="fas fa-lock text-[10px]"></i> View Only</span>
        </div>
        @if($can('general.my-paystub'))
            <a href="{{ route('general.my-paystub.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 border border-gray-300 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                <i class="fas fa-receipt text-xs"></i> View all paystubs
            </a>
        @endif
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Period</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Slip No.</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Earnings</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Deductions</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Take-home Pay</th>
                </tr>
            </thead>
            <tbody id="paystubRecapRows" class="bg-white divide-y divide-gray-100">
                <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">Loading paystubs…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const rp  = n => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
    const day = d => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '–';

    async function load() {
        const rows = document.getElementById('paystubRecapRows');
        const message = (text, cls) => { rows.innerHTML = `<tr><td colspan="5" class="px-4 py-12 text-center text-sm ${cls}">${esc(text)}</td></tr>`; };
        try {
            const res = await fetch('/api/profile/paystub-summary', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            // 403 = the role has no Paystub menu: the tab then simply has nothing to show.
            if (res.status === 403) return message('Paystubs are not available for your role.', 'text-gray-500');
            const json = await res.json();
            if (!json.success) throw new Error();
            if (!json.data.length) return message('No payslip has been published for you yet.', 'text-gray-500');

            rows.innerHTML = json.data.map(s => `<tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm"><span class="font-semibold text-gray-900">${esc(s.period)}</span>
                    <span class="block text-xs text-gray-500">${esc(day(s.period_start))} – ${esc(day(s.period_end))}</span></td>
                <td class="px-4 py-3 text-sm text-gray-600 tabular-nums">#${esc(s.slip_no)}</td>
                <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-700">${rp(s.gross)}</td>
                <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-700">${rp(s.deductions)}</td>
                <td class="px-4 py-3 text-sm text-right tabular-nums font-bold text-gray-900">${rp(s.take_home)}</td></tr>`).join('');
        } catch (e) {
            message('Your paystubs could not be loaded. Please try again.', 'text-red-500');
        }
    }

    (window.onSectionFirstShow || function (t, fn) { document.addEventListener('DOMContentLoaded', fn); })('payment', load);
})();
</script>
