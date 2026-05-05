{{-- RESPONSIVE TABLE VIEW FOR SUPPORT PLANNING --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <!-- Quick Action Buttons -->
    <div class="px-3 sm:px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2 text-xs sm:text-sm text-gray-600">
                <button onclick="expandAll()" class="hover:text-gray-900 px-2 py-1 rounded hover:bg-white transition">
                    <svg class="w-3 h-3 sm:w-4 sm:h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    <span class="hidden sm:inline">Expand All</span>
                    <span class="sm:hidden">Expand</span>
                </button>
                <button onclick="collapseAll()" class="hover:text-gray-900 px-2 py-1 rounded hover:bg-white transition">
                    <svg class="w-3 h-3 sm:w-4 sm:h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                    </svg>
                    <span class="hidden sm:inline">Collapse All</span>
                    <span class="sm:hidden">Collapse</span>
                </button>
                <button onclick="refreshTableData()" class="hover:text-blue-600 px-2 py-1 rounded hover:bg-white transition" title="Refresh data">
                    <svg class="w-3 h-3 sm:w-4 sm:h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span class="hidden sm:inline">Refresh</span>
                </button>
            </div>

            {{-- Mobile: Scroll Hint --}}
            <div class="lg:hidden flex items-center text-xs text-gray-500">
                <svg class="w-3 h-3 mr-1 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                </svg>
                <span>Scroll</span>
            </div>
        </div>
    </div>

    <!-- Table - Responsive with Horizontal Scroll -->
    <div class="overflow-x-auto touch-pan-x">
        <div class="min-w-[800px] lg:min-w-0">
            <table class="w-full divide-y divide-gray-200" id="activitiesTable">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-20 min-w-[250px] sm:min-w-[400px]">
                            Phase / Group / Stage / Activity
                        </th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20 sm:w-28">
                            Weight %
                        </th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24 sm:w-32">Description</th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20 sm:w-28">Object</th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50 w-24 sm:w-32">Start</th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50 w-24 sm:w-32">End</th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50 w-16 sm:w-24">Days</th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24 sm:w-32">Status</th>
                        <th class="px-2 sm:px-3 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32 sm:w-40">Progress</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="activitiesTableBody">
                    <tr>
                        <td colspan="9" class="text-center p-4 sm:p-8">
                            <div class="flex flex-col items-center justify-center">
                                <svg class="w-8 h-8 sm:w-12 sm:h-12 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="mt-2 text-xs sm:text-sm text-gray-500">Loading data...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    function getSupportId() {
        if (typeof window.supportId !== 'undefined' && window.supportId !== null) {
            return window.supportId;
        }

        const container = document.querySelector('[data-support-id]');
        if (container && container.dataset.supportId) {
            const id = parseInt(container.dataset.supportId);
            window.supportId = id;
            return id;
        }

        const urlMatch = window.location.pathname.match(/support\/(\d+)/);
        if (urlMatch) {
            const id = parseInt(urlMatch[1]);
            window.supportId = id;
            return id;
        }

        console.error('Cannot determine support ID!');
        return null;
    }

    // EXPOSE GLOBALLY
    window.loadAllPhasesData = function() {
        const supportId = getSupportId();
        if (!supportId) {
            console.error('Cannot load data: Support ID not found');
            return;
        }


        const tbody = document.getElementById('activitiesTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center p-4 sm:p-8">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="w-8 h-8 sm:w-12 sm:h-12 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-xs sm:text-sm text-gray-500">Loading data...</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        axios.get(`/delivery/support/${supportId}/data/table`)
            .then(function(response) {
                // API returns {success: true, data: [...]} so extract data array
                const phasesData = response.data.data || response.data;
                renderHierarchicalTable(phasesData);
            })
            .catch(function(error) {
                console.error('Error loading phases data:', error);
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-8 sm:py-12 text-red-600">
                                <div class="flex flex-col items-center">
                                    <svg class="w-8 h-8 sm:w-12 sm:h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <strong class="text-sm sm:text-base">Error loading data:</strong>
                                    <p class="text-xs sm:text-sm mt-1">${error.response?.data?.message || error.message}</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            });
    };

    function renderHierarchicalTable(phasesData) {

        const tbody = document.getElementById('activitiesTableBody');
        if (!tbody) {
            console.error('Table body not found');
            return;
        }

        tbody.innerHTML = '';

        if (!phasesData || phasesData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-8 sm:py-12">
                        <div class="text-gray-500">
                            <svg class="mx-auto h-8 w-8 sm:h-12 sm:w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            <h3 class="mt-2 text-xs sm:text-sm font-medium">No phases configured</h3>
                            <p class="mt-1 text-xs text-gray-500">Add phases in configuration.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        const fragment = document.createDocumentFragment();

        phasesData.forEach(function(phaseData) {
            const phaseRow = createPhaseRow(phaseData.phase);
            fragment.appendChild(phaseRow);

            if (phaseData.groups && phaseData.groups.length > 0) {
                phaseData.groups.forEach(function(group) {
                    createGroupRowRecursive(group, fragment, phaseData.phase.id, 1);
                });
            } else {
                const emptyRow = createEmptyPhaseRow(phaseData.phase);
                fragment.appendChild(emptyRow);
            }
        });

        tbody.appendChild(fragment);

        // Auto-expand all items
        setTimeout(function() {
            expandAll();
        }, 100);
    }

    function createPhaseRow(phase) {
        const row = document.createElement('tr');
        row.className = 'bg-gradient-to-r from-indigo-100 to-purple-100 font-bold border-l-4';
        row.style.borderLeftColor = phase.color;
        row.dataset.phaseId = phase.id;
        row.dataset.level = 0;

        const progress = parseFloat(phase.progress) || 0;

        row.innerHTML = `
            <td class="px-2 sm:px-3 py-3 sm:py-4 sticky left-0 bg-gradient-to-r from-indigo-100 to-purple-100 z-10 min-w-[250px] sm:min-w-[400px]">
                <div class="flex items-center justify-between">
                    <div class="flex items-center min-w-0 flex-1">
                        <button onclick="togglePhase(${phase.id}, this)" class="phase-toggle mr-1 sm:mr-2 p-1 rounded hover:bg-indigo-200 transition flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 transition-transform transform rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        <svg class="w-4 h-4 sm:w-6 sm:h-6 mr-1 sm:mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" style="color: ${phase.color}">
                            <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM2 12a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        <span class="text-xs sm:text-base uppercase tracking-wide truncate">${phase.name}</span>
                    </div>
                    <button onclick="quickAddGroupToPhase(${phase.id})"
                            class="hidden sm:inline-flex px-2 py-1 text-xs bg-purple-100 hover:bg-purple-200 text-purple-700 rounded transition ml-2 flex-shrink-0"
                            title="Add Group">
                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                        <span class="hidden md:inline">Add Group</span>
                    </button>
                </div>
            </td>
            <td class="px-2 sm:px-3 py-3 sm:py-4 text-center">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold" style="background-color: ${phase.color}20; color: ${phase.color}">
                    ${parseFloat(phase.weight).toFixed(1)}%
                </span>
            </td>
            <td colspan="2" class="px-2 sm:px-3 py-3 sm:py-4 text-xs text-gray-600 italic">Phase Level</td>
            <td class="px-2 sm:px-3 py-3 sm:py-4 text-xs text-center bg-blue-50 font-medium">${phase.start_date || '-'}</td>
            <td class="px-2 sm:px-3 py-3 sm:py-4 text-xs text-center bg-blue-50 font-medium">${phase.end_date || '-'}</td>
            <td class="px-2 sm:px-3 py-3 sm:py-4 text-xs text-center bg-blue-50 font-bold">${phase.duration_in_days || '-'}</td>
            <td class="px-2 sm:px-3 py-3 sm:py-4 text-center">
                <span class="px-2 py-1 text-xs font-bold rounded-full ${phase.status_badge || 'bg-gray-100 text-gray-800'}">
                    ${phase.status_text || 'Not Started'}
                </span>
            </td>
            <td class="px-2 sm:px-3 py-3 sm:py-4">
                <div class="flex items-center">
                    <div class="w-full bg-gray-200 rounded-full h-1.5 sm:h-2 mr-2">
                        <div class="h-1.5 sm:h-2 rounded-full transition-all" style="width: ${progress}%; background-color: ${phase.color}"></div>
                    </div>
                    <span class="text-xs font-bold flex-shrink-0" style="color: ${phase.color}">${Math.round(progress)}%</span>
                </div>
            </td>
        `;

        return row;
    }

    function createEmptyPhaseRow(phase) {
        const row = document.createElement('tr');
        row.className = 'phase-child-' + phase.id;
        row.style.display = 'table-row';

        row.innerHTML = `
            <td colspan="9" class="px-2 sm:px-3 py-2 sm:py-3 pl-8 sm:pl-16 text-xs sm:text-sm text-gray-400 italic">
                No groups in this phase yet.
            </td>
        `;

        return row;
    }

    function createGroupRowRecursive(group, parentElement, phaseId, indentation) {
        const row = document.createElement('tr');
        const level = group.level || 0;

        row.className = `bg-gradient-to-r from-purple-50 to-indigo-50 font-medium text-gray-800 group-row hover:from-purple-100 hover:to-indigo-100 transition phase-child-${phaseId}`;
        row.dataset.groupId = group.id;
        row.dataset.phaseId = phaseId;
        row.dataset.level = level + 1;
        row.style.display = 'table-row';

        if (group.parent_id) {
            row.classList.add(`group-child-${group.parent_id}`);
            row.style.display = 'none';
        }

        const weight = parseFloat(group.weight) || 0;
        const progress = parseFloat(group.progress_percentage) || 0;
        const hasChildren = (group.sub_groups && group.sub_groups.length > 0) || (group.stages && group.stages.length > 0);
        const paddingLeft = (indentation * 1.5) + 'rem';

        row.innerHTML = `
            <td class="px-2 sm:px-3 py-2 sm:py-3 sticky left-0 bg-gradient-to-r from-purple-50 to-indigo-50 z-10 min-w-[250px] sm:min-w-[400px]" style="padding-left: ${paddingLeft};">
                <div class="flex items-center justify-between group">
                    <div class="flex items-center flex-1 min-w-0">
                        ${hasChildren ? `
                        <button onclick="toggleGroup(${group.id}, this)" class="group-toggle mr-1 sm:mr-2 p-1 rounded hover:bg-purple-200 transition flex-shrink-0">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 transition-transform transform rotate-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        ` : '<span class="w-4 sm:w-6 mr-1 sm:mr-2"></span>'}
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600 mr-1 sm:mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-semibold truncate">${escapeHtml(group.name || 'Unnamed Group')}</span>
                        ${group.stages && group.stages.length > 0 ? `<span class="ml-1 sm:ml-2 text-xs text-purple-600 bg-purple-100 px-1.5 py-0.5 rounded-full hidden sm:inline">${group.stages.length}</span>` : ''}
                    </div>
                    <div class="hidden group-hover:flex items-center space-x-1 ml-2 flex-shrink-0">
                        <button onclick="openStageModal(${group.id}, '${escapeHtml(group.name || '').replace(/'/g, "\\'")}', ${phaseId})"
                                class="p-1 text-indigo-600 hover:bg-indigo-200 rounded transition"
                                title="Manage Stages">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </button>
                        <button onclick="editItem(${group.id})"
                                class="p-1 text-blue-600 hover:bg-blue-200 rounded transition"
                                title="Edit">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button class="delete-btn p-1 text-red-600 hover:bg-red-200 rounded transition"
                                data-item-id="${group.id}"
                                data-item-name="${escapeHtml(group.name || 'Unnamed Group')}"
                                data-is-group="true"
                                title="Delete">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </td>
            <td class="px-2 sm:px-3 py-2 sm:py-3 text-center">
                <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 sm:py-1 rounded text-xs font-bold bg-purple-200 text-purple-800">
                    ${weight.toFixed(1)}%
                </span>
            </td>
            <td colspan="2" class="px-2 sm:px-3 py-2 sm:py-3 text-xs text-gray-500 truncate">${group.notes || 'Group'}</td>
            <td class="px-2 sm:px-3 py-2 sm:py-3 text-xs text-center bg-blue-50 font-medium">${group.start_date || '-'}</td>
            <td class="px-2 sm:px-3 py-2 sm:py-3 text-xs text-center bg-blue-50 font-medium">${group.end_date || '-'}</td>
            <td class="px-2 sm:px-3 py-2 sm:py-3 text-xs text-center bg-blue-50 font-semibold">${group.duration_in_days || '-'}</td>
            <td class="px-2 sm:px-3 py-2 sm:py-3 text-center">
                <span class="px-1.5 sm:px-2 py-0.5 sm:py-1 text-xs font-semibold rounded-full ${group.status_badge || 'bg-gray-100 text-gray-800'}">
                    ${group.status_text || 'Not Started'}
                </span>
            </td>
            <td class="px-2 sm:px-3 py-2 sm:py-3">
                <div class="flex items-center">
                    <div class="w-full bg-purple-200 rounded-full h-1.5 mr-2">
                        <div class="bg-purple-600 h-1.5 rounded-full transition-all" style="width: ${progress}%"></div>
                    </div>
                    <span class="text-xs font-bold text-purple-800 flex-shrink-0">${Math.round(progress)}%</span>
                </div>
            </td>
        `;

        parentElement.appendChild(row);

        // Render sub-groups
        if (group.sub_groups && Array.isArray(group.sub_groups) && group.sub_groups.length > 0) {
            group.sub_groups.forEach(function(subGroup) {
                createGroupRowRecursive(subGroup, parentElement, phaseId, indentation + 1.5);
            });
        }

        // Render stages
        if (group.stages && Array.isArray(group.stages) && group.stages.length > 0) {
            group.stages.forEach(function(stage) {
                createStageRow(stage, group.id, indentation + 1.5, parentElement);

                // Render activities
                if (stage.activities && Array.isArray(stage.activities) && stage.activities.length > 0) {
                    stage.activities.forEach(function(activity) {
                        createActivityRow(activity, stage.id, indentation + 3, parentElement);
                    });
                }
            });
        }
    }

    function createStageRow(stage, groupId, indentation, parentElement) {
        const row = document.createElement('tr');
        row.className = `bg-yellow-50 border-l-4 stage-row group-child-${groupId}`;
        row.style.borderLeftColor = stage.color || '#F59E0B';
        row.style.display = 'none';
        row.dataset.stageId = stage.id;
        row.dataset.groupId = groupId;

        const weight = parseFloat(stage.weight) || 0;
        const progress = parseFloat(stage.progress) || 0;
        const escapedName = escapeHtml(stage.name || 'Unnamed Stage');
        const hasActivities = stage.activities && stage.activities.length > 0;
        const paddingLeft = (indentation * 1.5) + 'rem';

        row.innerHTML = `
            <td class="px-2 sm:px-3 py-2 sticky left-0 bg-yellow-50 z-10 min-w-[250px] sm:min-w-[400px]" style="padding-left: ${paddingLeft};">
                <div class="flex items-center justify-between group">
                    <div class="flex items-center flex-1 min-w-0">
                        ${hasActivities ? `
                        <button onclick="toggleStage(${stage.id}, this)" class="stage-toggle mr-1 sm:mr-2 p-1 rounded hover:bg-yellow-200 transition flex-shrink-0">
                            <svg class="w-3 h-3 transition-transform transform rotate-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        ` : '<span class="w-4 sm:w-5 mr-1 sm:mr-2"></span>'}
                        <svg class="w-3 h-3 sm:w-4 sm:h-4 text-yellow-600 mr-1 sm:mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1z"></path>
                        </svg>
                        <span class="text-xs font-medium text-gray-700 truncate">${escapedName}</span>
                        ${hasActivities ? `<span class="ml-1 sm:ml-2 text-xs text-yellow-700 bg-yellow-200 px-1.5 py-0.5 rounded-full hidden sm:inline">${stage.activities.length}</span>` : ''}
                    </div>
                    <div class="hidden group-hover:flex items-center space-x-1 ml-2 flex-shrink-0">
                        <button onclick="quickAddActivityToStage(${stage.id}, ${groupId})"
                                class="p-1 text-blue-600 hover:bg-blue-100 rounded transition"
                                title="Add Activity">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                        <button onclick="editStage(${stage.id}, '${escapedName.replace(/'/g, "\\'")}', ${groupId})"
                                class="p-1 text-indigo-600 hover:bg-indigo-100 rounded transition"
                                title="Edit Stage">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button class="delete-stage-btn p-1 text-red-600 hover:bg-red-100 rounded transition"
                                data-stage-id="${stage.id}"
                                data-stage-name="${escapedName}"
                                title="Delete Stage">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </td>
            <td class="px-2 sm:px-3 py-2 text-center">
                <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded text-xs font-medium bg-yellow-200 text-yellow-900">
                    ${weight.toFixed(1)}%
                </span>
            </td>
            <td class="px-2 sm:px-3 py-2 text-xs text-gray-500 truncate" colspan="2">${stage.description || 'Stage'}</td>
            <td class="px-2 sm:px-3 py-2 text-xs text-center bg-yellow-50 font-medium">${stage.start_date || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-xs text-center bg-yellow-50 font-medium">${stage.end_date || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-xs text-center bg-yellow-50 font-semibold">${stage.duration_in_days || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-center">
                <span class="px-1.5 sm:px-2 py-0.5 text-xs font-medium rounded-full ${stage.status_badge || 'bg-gray-100 text-gray-800'}">${stage.status_text || 'Not Started'}</span>
            </td>
            <td class="px-2 sm:px-3 py-2">
                <div class="flex items-center">
                    <div class="w-full bg-yellow-200 rounded-full h-1.5 mr-2">
                        <div class="bg-yellow-600 h-1.5 rounded-full transition-all" style="width: ${progress}%"></div>
                    </div>
                    <span class="text-xs font-medium flex-shrink-0">${Math.round(progress)}%</span>
                </div>
            </td>
        `;

        parentElement.appendChild(row);
    }

    function createActivityRow(activity, stageId, indentation, parentElement) {
        const row = document.createElement('tr');
        row.className = `hover:bg-blue-50 activity-row transition stage-child-${stageId}`;
        row.style.display = 'none';
        row.dataset.activityId = activity.id;
        row.dataset.stageId = stageId;

        const weight = parseFloat(activity.weight) || 0;
        const progress = parseFloat(activity.progress_percentage) || 0;
        const escapedName = escapeHtml(activity.name || 'Unnamed Activity');
        const paddingLeft = (indentation * 1.5) + 'rem';

        row.innerHTML = `
            <td class="px-2 sm:px-3 py-2 sticky left-0 bg-white z-10 min-w-[250px] sm:min-w-[400px]" style="padding-left: ${paddingLeft};">
                <div class="flex items-center justify-between group">
                    <div class="flex items-center flex-1 min-w-0">
                        <svg class="w-3 h-3 text-blue-500 mr-1 sm:mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-xs font-medium text-gray-900 truncate">${escapedName}</span>
                    </div>
                    <div class="hidden group-hover:flex items-center space-x-1 ml-2 flex-shrink-0">
                        <button onclick="editActivity(${activity.id}, ${stageId})"
                                class="p-1 text-blue-600 hover:bg-blue-100 rounded transition"
                                title="Edit Activity">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button class="delete-activity-btn p-1 text-red-600 hover:bg-red-100 rounded transition"
                                data-activity-id="${activity.id}"
                                data-activity-name="${escapedName}"
                                title="Delete Activity">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </td>
            <td class="px-2 sm:px-3 py-2 text-center">
                <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                    ${weight.toFixed(1)}%
                </span>
            </td>
            <td class="px-2 sm:px-3 py-2 text-xs truncate">${activity.module || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-xs text-center">${activity.object || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-xs text-center bg-blue-50">${activity.start_date || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-xs text-center bg-blue-50">${activity.end_date || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-xs text-center bg-blue-50 font-medium">${activity.duration_in_days || '-'}</td>
            <td class="px-2 sm:px-3 py-2 text-center">
                <span class="px-1.5 sm:px-2 py-0.5 text-xs font-semibold rounded-full ${activity.status_badge || 'bg-gray-100 text-gray-800'}">${activity.status_text || 'Not Started'}</span>
            </td>
            <td class="px-2 sm:px-3 py-2">
                <div class="flex items-center">
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mr-2">
                        <div class="bg-blue-600 h-1.5 rounded-full transition-all" style="width: ${progress}%"></div>
                    </div>
                    <span class="text-xs font-semibold flex-shrink-0">${Math.round(progress)}%</span>
                </div>
            </td>
        `;

        parentElement.appendChild(row);
    }

    // Toggle functions
    window.togglePhase = function(phaseId, buttonElement) {
        const childRows = document.querySelectorAll(`.phase-child-${phaseId}`);
        const icon = buttonElement.querySelector('svg');
        const isCollapsed = icon.classList.contains('rotate-0');

        childRows.forEach(function(row) {
            row.style.display = isCollapsed ? 'table-row' : 'none';
        });

        icon.classList.toggle('rotate-0');
        icon.classList.toggle('rotate-90');
    };

    window.toggleGroup = function(groupId, buttonElement) {
        const childRows = document.querySelectorAll(`.group-child-${groupId}`);
        const icon = buttonElement.querySelector('svg');
        const isCollapsed = icon.classList.contains('rotate-0');

        childRows.forEach(function(row) {
            row.style.display = isCollapsed ? 'table-row' : 'none';
        });

        icon.classList.toggle('rotate-0');
        icon.classList.toggle('rotate-90');
    };

    window.toggleStage = function(stageId, buttonElement) {
        const childRows = document.querySelectorAll(`.stage-child-${stageId}`);
        const icon = buttonElement.querySelector('svg');
        const isCollapsed = icon.classList.contains('rotate-0');

        childRows.forEach(function(row) {
            row.style.display = isCollapsed ? 'table-row' : 'none';
        });

        icon.classList.toggle('rotate-0');
        icon.classList.toggle('rotate-90');
    };

    window.expandAll = function() {
        document.querySelectorAll('.phase-toggle, .group-toggle, .stage-toggle').forEach(function(button) {
            const icon = button.querySelector('svg');
            if (icon && icon.classList.contains('rotate-0')) {
                button.click();
            }
        });
    };

    window.collapseAll = function() {
        document.querySelectorAll('.phase-toggle, .group-toggle, .stage-toggle').forEach(function(button) {
            const icon = button.querySelector('svg');
            if (icon && icon.classList.contains('rotate-90')) {
                button.click();
            }
        });
    };

    // Quick Add Functions
    window.quickAddGroupToPhase = function(phaseId) {
        if (typeof openQuickModal === 'function') {
            openQuickModal('group', phaseId, null);
        }
    };

    window.quickAddActivityToStage = function(stageId, groupId) {

        if (typeof window.openActivityModal === 'function') {
            window.openActivityModal(stageId, groupId);
        } else {
            console.error('openActivityModal not loaded!');
            showNotification('Activity modal not ready. Please refresh the page.', 'error');
        }
    };

    window.editActivity = function(activityId, stageId) {

        if (typeof window.openActivityModal === 'function') {
            window.openActivityModal(stageId, null, activityId);
        } else {
            console.error('openActivityModal not loaded!');
            showNotification('Activity modal not ready. Please refresh the page.', 'error');
        }
    };

    // Event delegation for delete buttons
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();

            const itemId = deleteBtn.dataset.itemId;
            const itemName = deleteBtn.dataset.itemName;
            const isGroup = deleteBtn.dataset.isGroup === 'true';

            if (typeof deleteItem === 'function') {
                deleteItem(itemId, isGroup, itemName);
            }
        }

        const deleteActivityBtn = e.target.closest('.delete-activity-btn');
        if (deleteActivityBtn) {
            e.preventDefault();
            e.stopPropagation();

            const activityId = deleteActivityBtn.dataset.activityId;
            const activityName = deleteActivityBtn.dataset.activityName;

            if (typeof deleteItem === 'function') {
                deleteItem(activityId, false, activityName);
            }
        }

        const deleteStageBtn = e.target.closest('.delete-stage-btn');
        if (deleteStageBtn) {
            e.preventDefault();
            e.stopPropagation();

            const stageId = deleteStageBtn.dataset.stageId;
            const stageName = deleteStageBtn.dataset.stageName;

            if (typeof deleteStage === 'function') {
                deleteStage(stageId, stageName);
            }
        }
    });

    // Helper: Safe HTML Escape
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Auto-load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(loadAllPhasesData, 300);
    });
})();
</script>

<style>
/* Mobile Touch Improvements */
@media (max-width: 1024px) {
    button, a {
        min-height: 44px;
        min-width: 44px;
        touch-action: manipulation;
    }

    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
    }

    th.sticky, td.sticky {
        box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    }
}

.phase-toggle, .group-toggle, .stage-toggle {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
