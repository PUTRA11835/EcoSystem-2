@extends('dashboard')

@section('title', 'Menu Access')
@section('page-title', 'Menu Access')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <h2 class="text-2xl font-bold text-gray-900">Menu List</h2>
        <div class="flex gap-2">
            <button onclick="expandAll()" class="px-3 py-2 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-plus-square mr-1"></i> Expand All
            </button>
            <button onclick="collapseAll()" class="px-3 py-2 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-minus-square mr-1"></i> Collapse All
            </button>
        </div>
    </div>

    <!-- Filter -->
    <div class="flex gap-3 mb-5">
        <input type="text" id="filterSearch" placeholder="Search menu name..."
            oninput="applyFilter()"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
        <button onclick="resetFilter()"
            class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
            Reset
        </button>
    </div>

    <!-- List -->
    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <div id="menuList" class="divide-y divide-gray-100">
            <div class="px-4 py-10 text-center text-gray-400 text-sm">Loading...</div>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-4 flex items-center gap-6 text-xs text-gray-400">
        <span class="flex items-center gap-1.5"><i class="fas fa-layer-group text-purple-400"></i> Group</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-file-alt text-blue-400"></i> Page</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-bolt text-orange-400"></i> Function / Button</span>
    </div>
</div>

<script>
let menusFlat  = [];
let menuTree   = [];
let expandedSet = new Set();

async function loadData() {
    const res = await fetch('/api/menus/all');
    menusFlat = (await res.json()).data || [];
    menuTree  = buildTree(menusFlat, null);
    menuTree.forEach(n => expandedSet.add(n.slug));
    renderList();
}

function buildTree(items, parentId) {
    return items
        .filter(m => m.parent_id == parentId)
        .sort((a, b) => (a.order_seq || 0) - (b.order_seq || 0))
        .map(m => ({ ...m, children: buildTree(items, m.id) }));
}

function renderList() {
    const search  = document.getElementById('filterSearch').value.toLowerCase().trim();
    const container = document.getElementById('menuList');
    container.innerHTML = '';

    let hasAny = false;
    menuTree.forEach(node => {
        hasAny = renderNode(node, 0, container, search) || hasAny;
    });

    if (!hasAny) {
        container.innerHTML = '<div class="px-4 py-10 text-center text-gray-400 text-sm">No menus found.</div>';
    }
}

function renderNode(node, depth, container, search) {
    if (search && !nodeOrDescendantMatches(node, search)) return false;

    const hasChildren = node.children && node.children.length > 0;
    const isExpanded  = expandedSet.has(node.slug);

    const typeStyle = {
        group:    { icon: 'fa-layer-group', iconCls: 'text-purple-400', textCls: 'font-semibold text-gray-900', bgCls: 'bg-white' },
        page:     { icon: 'fa-file-alt',    iconCls: 'text-blue-400',   textCls: 'font-medium text-gray-800',   bgCls: 'bg-white' },
        function: { icon: 'fa-bolt',        iconCls: 'text-orange-400', textCls: 'text-gray-700',               bgCls: 'bg-gray-50' },
    }[node.type] || { icon: 'fa-circle', iconCls: 'text-gray-400', textCls: 'text-gray-700', bgCls: 'bg-white' };

    const div = document.createElement('div');
    div.className = `flex items-center gap-2 px-4 py-2.5 hover:bg-blue-50/30 transition-colors ${typeStyle.bgCls} ${depth > 0 ? 'border-t border-gray-100' : ''}`;
    div.style.paddingLeft = `${16 + depth * 22}px`;

    div.innerHTML = `
        ${hasChildren
            ? `<button onclick="toggleNode('${node.slug}')"
                    class="w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-600 flex-shrink-0 focus:outline-none">
                   <i class="fas ${isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'} text-xs"></i>
               </button>`
            : `<span class="w-5 flex-shrink-0"></span>`
        }
        <i class="fas ${typeStyle.icon} ${typeStyle.iconCls} text-xs w-4 text-center flex-shrink-0"></i>
        <span class="text-sm ${typeStyle.textCls} leading-tight">${escHtml(node.name)}</span>
        <span class="ml-auto text-xs text-gray-300 font-mono">${escHtml(node.slug)}</span>
    `;

    container.appendChild(div);

    if (hasChildren && isExpanded) {
        node.children.forEach(child => renderNode(child, depth + 1, container, search));
    }

    return true;
}

function nodeOrDescendantMatches(node, search) {
    if (node.name.toLowerCase().includes(search) || node.slug.toLowerCase().includes(search)) return true;
    return (node.children || []).some(c => nodeOrDescendantMatches(c, search));
}

function toggleNode(slug) {
    expandedSet.has(slug) ? expandedSet.delete(slug) : expandedSet.add(slug);
    renderList();
}

function expandAll() {
    menusFlat.forEach(m => expandedSet.add(m.slug));
    renderList();
}

function collapseAll() {
    expandedSet.clear();
    renderList();
}

function applyFilter() {
    menusFlat.forEach(m => expandedSet.add(m.slug));
    renderList();
}

function resetFilter() {
    document.getElementById('filterSearch').value = '';
    expandedSet.clear();
    menuTree.forEach(n => expandedSet.add(n.slug));
    renderList();
}

function escHtml(s) {
    const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML;
}

loadData();
</script>
@endsection
