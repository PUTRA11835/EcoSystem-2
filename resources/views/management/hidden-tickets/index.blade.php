@extends('dashboard')

@section('title', 'Hidden Tickets')
@section('page-title', 'Hidden Tickets')
@section('page-subtitle', 'Tiket yang disembunyikan dari daftar utama')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Hidden Tickets</h2>
            <p class="text-sm text-gray-500 mt-1">Tiket-tiket yang disembunyikan dan tidak tampil di daftar tiket utama. Anda dapat menampilkan kembali tiket dari halaman ini.</p>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
            <i class="fas fa-eye-slash text-orange-400"></i>
            <span id="hiddenCount" class="font-semibold text-orange-700">—</span>
            <span>tiket tersembunyi</span>
        </div>
    </div>

    {{-- Search --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input type="text" id="searchInput" placeholder="Cari nomor tiket atau deskripsi..."
            oninput="renderTable()"
            class="w-full sm:w-96 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Tiket #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" style="min-width:260px;">Deskripsi</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Ticket Lead</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Priority</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Tgl Dibuat</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody id="hiddenTicketsBody" class="divide-y divide-gray-100">
                <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Memuat data...
                </td></tr>
            </tbody>
        </table>
    </div>

    {{-- Empty State (hidden by default) --}}
    <div id="emptyState" class="hidden text-center py-16">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-eye text-gray-300 text-2xl"></i>
        </div>
        <h3 class="text-sm font-semibold text-gray-500 mb-1">Tidak ada tiket tersembunyi</h3>
        <p class="text-xs text-gray-400">Semua tiket sedang ditampilkan di daftar utama.</p>
    </div>
</div>

{{-- Confirm Unhide Modal --}}
<div id="unhideModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-eye text-green-500"></i>
                Tampilkan Kembali Tiket
            </h3>
            <button onclick="closeUnhideModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-1">Tiket berikut akan ditampilkan kembali di daftar utama:</p>
            <p id="unhideTicketInfo" class="text-sm font-semibold text-gray-800 bg-gray-50 rounded-lg px-3 py-2 mt-2 mb-4"></p>
            <p class="text-xs text-gray-400">Setelah ditampilkan, tiket akan muncul kembali untuk semua user yang berhak melihatnya.</p>
        </div>
        <div class="flex justify-end gap-3 px-6 pb-6">
            <button onclick="closeUnhideModal()"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                Batal
            </button>
            <button id="confirmUnhideBtn" onclick="confirmUnhide()"
                class="px-4 py-2 text-sm text-white bg-green-600 rounded-lg hover:bg-green-700 transition-all font-semibold">
                <i class="fas fa-eye mr-1"></i> Tampilkan Kembali
            </button>
        </div>
    </div>
</div>

<script>
    let allHiddenTickets = [];
    let pendingUnhideId  = null;

    const PRIORITY_COLORS = {
        'Very High': 'bg-red-100 text-red-700',
        'High':      'bg-orange-100 text-orange-700',
        'Medium':    'bg-yellow-100 text-yellow-700',
        'Low':       'bg-gray-100 text-gray-500',
    };

    const STATUS_COLORS = {
        'open':                    'bg-blue-50 text-blue-700',
        'inprocess':               'bg-yellow-50 text-yellow-700',
        'waiting_on_customer':     'bg-amber-50 text-amber-700',
        'waiting_on_3rd_party':    'bg-indigo-50 text-indigo-700',
        'waiting_to_confirmation': 'bg-teal-50 text-teal-700',
        'hold':                    'bg-gray-100 text-gray-600',
        'cancelled':               'bg-red-50 text-red-600',
        'closed':                  'bg-green-50 text-green-700',
    };

    const STATUS_LABELS = {
        'open':                    'Open',
        'inprocess':               'In Process',
        'waiting_on_customer':     'Wait Customer',
        'waiting_on_3rd_party':    'Wait 3rd Party',
        'waiting_to_confirmation': 'Wait Confirmation',
        'hold':                    'Hold',
        'cancelled':               'Cancelled',
        'closed':                  'Closed',
    };

    async function loadHiddenTickets() {
        try {
            const res  = await fetch('/api/tickets/hidden', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                allHiddenTickets = data.data || [];
                document.getElementById('hiddenCount').textContent = allHiddenTickets.length;
                renderTable();
            } else {
                showError('Gagal memuat tiket: ' + (data.message || 'Unknown error'));
            }
        } catch (e) {
            showError('Terjadi kesalahan saat memuat data.');
        }
    }

    function renderTable() {
        const keyword = (document.getElementById('searchInput').value || '').toLowerCase();
        const filtered = allHiddenTickets.filter(t => {
            if (!keyword) return true;
            return (t.ticket_number || '').toLowerCase().includes(keyword)
                || (t.description || '').toLowerCase().includes(keyword)
                || (t.customer?.customer_name || '').toLowerCase().includes(keyword);
        });

        const tbody = document.getElementById('hiddenTicketsBody');
        const empty = document.getElementById('emptyState');

        if (filtered.length === 0) {
            tbody.innerHTML = '';
            empty.classList.remove('hidden');
            return;
        }

        empty.classList.add('hidden');
        tbody.innerHTML = filtered.map(t => {
            const prioCls    = PRIORITY_COLORS[t.ticket_priority] || 'bg-gray-100 text-gray-500';
            const statusCls  = STATUS_COLORS[t.status]  || 'bg-gray-100 text-gray-500';
            const statusLbl  = STATUS_LABELS[t.status]  || t.status || '—';
            const custName   = t.customer?.customer_name || '—';
            const leadName   = t.employee?.employee_name || '<span class="text-gray-300 italic text-xs">Unassigned</span>';
            const createdAt  = t.created_at ? new Date(t.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
            const desc       = (t.description || '—').length > 80
                ? (t.description.substring(0, 80) + '…')
                : (t.description || '—');

            return `<tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 whitespace-nowrap">
                    <a href="/ticket/${t.ticket_id}" class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 font-mono text-xs font-bold text-gray-700 hover:bg-gray-200 transition-colors">
                        ${t.ticket_number || '—'}
                    </a>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700" title="${(t.description||'').replace(/"/g,'&quot;')}">
                    ${desc}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-800">${custName}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${leadName}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    ${t.ticket_priority
                        ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${prioCls}">${t.ticket_priority}</span>`
                        : '<span class="text-gray-300 text-xs">—</span>'}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${statusCls}">${statusLbl}</span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">${createdAt}</td>
                <td class="px-4 py-3 whitespace-nowrap text-center">
                    <button onclick="openUnhideModal(${t.ticket_id}, '${(t.ticket_number||'').replace(/'/g,"\\'")}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-50 border border-green-200 text-green-700 text-xs font-semibold rounded-lg hover:bg-green-100 hover:border-green-300 transition-all">
                        <i class="fas fa-eye text-xs"></i> Tampilkan
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    function showError(msg) {
        document.getElementById('hiddenTicketsBody').innerHTML =
            `<tr><td colspan="8" class="px-4 py-10 text-center text-red-400 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i>${msg}</td></tr>`;
    }

    function openUnhideModal(ticketId, ticketNumber) {
        pendingUnhideId = ticketId;
        document.getElementById('unhideTicketInfo').textContent = ticketNumber || `Tiket #${ticketId}`;
        document.getElementById('unhideModal').classList.remove('hidden');
    }

    function closeUnhideModal() {
        pendingUnhideId = null;
        document.getElementById('unhideModal').classList.add('hidden');
    }

    async function confirmUnhide() {
        if (!pendingUnhideId) return;

        const btn = document.getElementById('confirmUnhideBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...';

        try {
            const res = await fetch(`/api/tickets/${pendingUnhideId}/unhide`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.success) {
                allHiddenTickets = allHiddenTickets.filter(t => t.ticket_id !== pendingUnhideId);
                document.getElementById('hiddenCount').textContent = allHiddenTickets.length;
                closeUnhideModal();
                renderTable();
                showNotification('Tiket berhasil ditampilkan kembali.', 'success');
            } else {
                showNotification(data.message || 'Gagal menampilkan tiket.', 'error');
            }
        } catch (e) {
            showNotification('Terjadi kesalahan. Coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-eye mr-1"></i> Tampilkan Kembali';
        }
    }

    // Close modal on backdrop click
    document.getElementById('unhideModal').addEventListener('click', function(e) {
        if (e.target === this) closeUnhideModal();
    });

    // Load on page ready
    document.addEventListener('DOMContentLoaded', loadHiddenTickets);
</script>
@endsection
