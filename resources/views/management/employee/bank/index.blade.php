@extends('dashboard')
@section('title', 'Employee Bank Account')
@section('page-title', 'Employee Bank Account')
@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div><h2 class="text-2xl font-bold text-gray-900">Employee Bank Account</h2><p class="text-sm text-gray-500 mt-1">Daftar rekening bank seluruh employee</p></div>
        <input type="text" id="searchInput" placeholder="Cari..." oninput="filterRows()" class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
    </div>
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">No</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ECI</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Bank</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">No. Rekening</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Atas Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase"></th>
                </tr>
            </thead>
            <tbody id="tableBody"><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Memuat data...</td></tr></tbody>
        </table>
    </div>
    <div class="mt-2 text-xs text-gray-400" id="rowCount"></div>
</div>
<script>
    let allRows = [];
    async function loadData() {
        const empRes = await fetch('/api/employees?per_page=999', { credentials: 'same-origin' });
        const empData = await empRes.json();
        const employees = empData.data ?? empData ?? [];
        const rows = [];
        await Promise.all(employees.map(async e => {
            const id = e.employee_id ?? e.id;
            try {
                const res = await fetch(`/api/employees/${id}/bank`, { credentials: 'same-origin' });
                const d = await res.json();
                (d.data ?? []).forEach(a => rows.push({ ...a, _emp: e }));
            } catch(err) {}
        }));
        allRows = rows;
        renderTable(allRows);
    }
    function renderTable(rows) {
        document.getElementById('rowCount').textContent = rows.length + ' record';
        const tbody = document.getElementById('tableBody');
        if (!rows.length) { tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Tidak ada data</td></tr>`; return; }
        tbody.innerHTML = rows.map((r, i) => `
            <tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                <td class="px-4 py-3 text-gray-400 text-xs">${i+1}</td>
                <td class="px-4 py-3 text-gray-600 text-sm">${r._emp?.eci ?? '-'}</td>
                <td class="px-4 py-3 text-gray-600 text-sm">${r._emp?.full_name ?? '-'}</td>
                <td class="px-4 py-3 text-gray-600 text-sm">${r.bank_name ?? '-'}</td>
                <td class="px-4 py-3 text-gray-600 text-sm">${r.account_number ?? '-'}</td>
                <td class="px-4 py-3 text-gray-600 text-sm">${r.account_name ?? '-'}</td>
                <td class="px-4 py-3 text-center"><a href="/master/employee/${r._emp?.employee_id ?? r._emp?.id}" class="text-xs text-red-700 hover:underline">Detail →</a></td>
            </tr>`).join('');
    }
    function filterRows() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        renderTable(q ? allRows.filter(r => JSON.stringify(r).toLowerCase().includes(q)) : allRows);
    }
    document.addEventListener('DOMContentLoaded', loadData);
</script>
@endsection