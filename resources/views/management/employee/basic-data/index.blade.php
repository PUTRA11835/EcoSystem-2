@extends('dashboard')
@section('title', 'Employee Basic Data')
@section('page-title', 'Employee Basic Data')
@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Employee Basic Data</h2>
            <p class="text-sm text-gray-500 mt-1">List of all employee basic data</p>
        </div>
        <input type="text" id="searchInput" placeholder="Search name / ECI / position..." oninput="filterRows()" class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
    </div>
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">No</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ECI</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Position</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Since Date</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase"></th>
                </tr>
            </thead>
            <tbody id="tableBody"><tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr></tbody>
        </table>
    </div>
    <div class="mt-2 text-xs text-gray-400" id="rowCount"></div>
</div>
<script>
    let allRows = [];
    async function loadData() {
        const res  = await fetch('/api/employees?per_page=999', { credentials: 'same-origin' });
        const data = await res.json();
        allRows = data.data ?? data ?? [];
        renderTable(allRows);
    }
    function renderTable(rows) {
        document.getElementById('rowCount').textContent = rows.length + ' employee(s)';
        const tbody = document.getElementById('tableBody');
        if (!rows.length) { tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No data</td></tr>`; return; }
        tbody.innerHTML = rows.map((r, i) => `
            <tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                <td class="px-4 py-3 text-gray-400 text-xs">${i+1}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-600">${r.eci ?? '-'}</td>
                <td class="px-4 py-3 font-semibold text-gray-900">${r.full_name ?? (r.first_name ?? '') + ' ' + (r.last_name ?? '')}</td>
                <td class="px-4 py-3 text-gray-600">${r.position ?? '-'}</td>
                <td class="px-4 py-3 text-gray-600">${r.department ?? '-'}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">${r.since_date ?? '-'}</td>
                <td class="px-4 py-3 text-center">${r.is_active ? '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>' : '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>'}</td>
                <td class="px-4 py-3 text-center"><a href="/master/employee/${r.employee_id ?? r.id}" class="text-xs text-red-700 hover:underline">Detail →</a></td>
            </tr>`).join('');
    }
    function filterRows() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        renderTable(q ? allRows.filter(r => JSON.stringify(r).toLowerCase().includes(q)) : allRows);
    }
    document.addEventListener('DOMContentLoaded', loadData);
</script>
@endsection
