{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- RECONS — scripts section (tab Recons di halaman Support Details)       --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<script>
window.SupportRecons = (function () {
    'use strict';

    const SUPPORT_ID = {{ $support->id }};
    // URL relatif (bukan url()/route() absolut) — konsisten dengan section
    // Plan Cost/Payment Terms, dan aman saat aplikasi diakses lewat HTTPS di
    // belakang proxy tanpa bergantung pada skema yang di-generate server.
    const BASE_URL   = `/delivery/support/${SUPPORT_ID}/recons`;
    const CAN_EDIT   = @json($can('delivery-support.recons.edit'));
    const CAN_MANAGE = @json($can('delivery-support.recons.manage'));

    let ticketRows = [];
    let batchRows  = [];

    // ── utilities ────────────────────────────────────────────────────────
    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // Deskripsi tiket berasal dari email customer — WAJIB di-escape sebelum
    // dimasukkan ke innerHTML supaya tidak jadi celah XSS.
    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Pola toast identik dengan showPlanCostToast() di section Plan Cost.
    function notify(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
        else if (typeof window.showNotification === 'function') window.showNotification(msg, type);
        else alert(msg);
    }

    function fmtMd(value) {
        if (value === null || value === undefined || value === '') return '-';
        return Number(value).toFixed(2);
    }

    function statusBadge(status, label) {
        if (!status) return '<span class="recons-badge recons-badge-none">Not reconciled</span>';
        const cls = status === 'submitted' ? 'recons-badge-submitted' : 'recons-badge-draft';
        return `<span class="recons-badge ${cls}">${esc(label || (status === 'submitted' ? 'Submitted' : 'Draft'))}</span>`;
    }

    function emptyRow(colspan, message) {
        return `<tr><td colspan="${colspan}" class="text-center py-10 text-gray-500 text-xs">${esc(message)}</td></tr>`;
    }

    // ── view toggle ──────────────────────────────────────────────────────
    function switchView(view) {
        const isTickets = view === 'tickets';
        document.getElementById('reconsTicketsView').classList.toggle('hidden', !isTickets);
        document.getElementById('reconsBatchesView').classList.toggle('hidden', isTickets);
        document.getElementById('reconsViewTicketsBtn').classList.toggle('active', isTickets);
        document.getElementById('reconsViewBatchesBtn').classList.toggle('active', !isTickets);

        if (!isTickets && batchRows.length === 0) loadBatches();
    }

    // ── daftar tiket ─────────────────────────────────────────────────────
    async function loadTickets() {
        try {
            const res  = await fetch(`${BASE_URL}/tickets`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to load tickets.');

            ticketRows = data.tickets || [];
            renderTickets();
        } catch (e) {
            document.getElementById('reconsTicketsBody').innerHTML = emptyRow(10, e.message || 'Failed to load tickets.');
        }
    }

    function renderTickets() {
        const body    = document.getElementById('reconsTicketsBody');
        const counter = document.getElementById('reconsTicketsCount');
        const term    = (document.getElementById('reconsTicketSearch')?.value || '').toLowerCase().trim();

        const rows = term
            ? ticketRows.filter(r =>
                (r.ticket_number || '').toLowerCase().includes(term) ||
                (r.description || '').toLowerCase().includes(term) ||
                (r.recons_number || '').toLowerCase().includes(term))
            : ticketRows;

        counter.textContent = `${rows.length} of ${ticketRows.length} ticket(s)`;

        if (rows.length === 0) {
            body.innerHTML = emptyRow(10, ticketRows.length === 0
                ? 'No ticket is linked to this delivery support yet.'
                : 'No ticket matches your search.');
            return;
        }

        body.innerHTML = rows.map(r => `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">${esc(r.ticket_number || ('#' + r.ticket_id))}</td>
                <td class="px-4 py-3 text-gray-700">${esc(r.description || '-')}</td>
                <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(r.start_date_label)}</td>
                <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(r.close_date_label)}</td>
                <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(r.status_label)}</td>
                <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(r.type || '-')}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">${fmtMd(r.man_days)}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    ${r.recons_number
                        ? `<a href="${BASE_URL}/${r.recons_id}" class="primary-link font-medium">${esc(r.recons_number)}</a>`
                        : '<span class="text-gray-400">-</span>'}
                </td>
                <td class="px-4 py-3 text-gray-700">${esc(r.recons_description || '-')}</td>
                <td class="px-4 py-3 text-center whitespace-nowrap">${statusBadge(r.recons_status)}</td>
            </tr>
        `).join('');
    }

    // ── daftar batch recons ──────────────────────────────────────────────
    async function loadBatches() {
        try {
            const res  = await fetch(`${BASE_URL}/batches`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to load recons.');

            batchRows = data.recons || [];
            renderBatches();
        } catch (e) {
            document.getElementById('reconsBatchesBody').innerHTML = emptyRow(8, e.message || 'Failed to load recons.');
        }
    }

    function renderBatches() {
        const body = document.getElementById('reconsBatchesBody');

        if (batchRows.length === 0) {
            body.innerHTML = emptyRow(8, 'No recons has been created for this delivery support yet.');
            return;
        }

        body.innerHTML = batchRows.map(r => {
            const isDraft = r.status !== 'submitted';
            const safeNumber = esc(r.recons_number).replace(/'/g, "\\'");
            const actions = [
                `<a href="${BASE_URL}/${r.id}" class="text-gray-600 hover:text-gray-900" title="View detail">View</a>`,
                `<a href="${BASE_URL}/${r.id}/export" class="text-emerald-700 hover:text-emerald-900" title="Export ticket detail to Excel">Export</a>`,
            ];
            if (isDraft && CAN_EDIT)   actions.push(`<a href="${BASE_URL}/${r.id}/edit" class="primary-link" title="Edit draft">Edit</a>`);
            // Batch yang sudah submit masih bisa dibatalkan → kembali jadi draft.
            if (!isDraft && CAN_EDIT)  actions.push(`<button type="button" onclick="SupportRecons.askCancel(${r.id}, '${safeNumber}')" class="text-amber-700 hover:text-amber-900" title="Revert this recons to draft">Cancel</button>`);
            if (isDraft && CAN_MANAGE) actions.push(`<button type="button" onclick="SupportRecons.askDelete(${r.id}, '${safeNumber}')" class="text-red-600 hover:text-red-800">Delete</button>`);

            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">
                        <a href="${BASE_URL}/${r.id}" class="primary-link">${esc(r.recons_number)}</a>
                    </td>
                    <td class="px-4 py-3 text-gray-700">${esc(r.description || '-')}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap text-gray-600">${esc(r.recons_date_label || '-')}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">${statusBadge(r.status, r.status_label)}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">${r.ticket_count}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">${fmtMd(r.total_md)}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">${esc(r.created_by || '-')}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <div class="flex items-center justify-center gap-3 text-xs font-semibold">${actions.join('')}</div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // ── batalkan submit (kembali ke draft) ───────────────────────────────
    async function askCancel(id, number) {
        const ok = await showConfirm(
            `Cancel recons ${number}? Its status returns to Draft so the header and ticket list can be edited again.\n\n`
            + 'Its tickets stay reserved for this recons and cannot be picked by another one.',
            'Cancel Recons',
            'danger',
            { okText: 'Return to Draft', cancelText: 'Keep Submitted' },
        );

        if (!ok) return;

        try {
            const res = await fetch(`${BASE_URL}/${id}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to cancel recons.');

            notify(data.message, 'success');
            // Status batch & kolom Recons Status di tabel tiket ikut berubah.
            await Promise.all([loadBatches(), loadTickets()]);
        } catch (e) {
            notify(e.message || 'Failed to cancel recons.', 'error');
        }
    }

    // ── hapus draft ──────────────────────────────────────────────────────
    // Memakai showConfirm() global (partials/confirm-modal) — bukan confirm()
    // bawaan browser — supaya konsisten dengan seluruh aksi destruktif lain.
    async function askDelete(id, number) {
        const ok = await showConfirm(
            `Delete draft ${number}? Its tickets will become available for a new recons again. This cannot be undone.`,
            'Delete Recons Draft',
            'danger',
            { okText: 'Delete Draft' },
        );

        if (!ok) return;

        try {
            const res = await fetch(`${BASE_URL}/${id}/delete`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to delete draft.');

            notify(data.message, 'success');
            // Tiket yang dilepas kembali eligible → kedua tabel perlu disegarkan.
            await Promise.all([loadBatches(), loadTickets()]);
        } catch (e) {
            notify(e.message || 'Failed to delete draft.', 'error');
        }
    }

    // ── init ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('reconsTicketsBody')) return;
        switchView('tickets');
        loadTickets();
    });

    return { switchView, renderTickets, loadTickets, loadBatches, askDelete, askCancel };
})();
</script>
