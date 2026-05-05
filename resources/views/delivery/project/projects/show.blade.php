@extends('dashboard')
@section('title', 'Detail Proyek')
@section('page-title', 'Detail Proyek')
@section('page-subtitle', 'Lihat informasi lengkap proyek')
{{-- ✅ LOAD GANTT LIBRARIES --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.css">
<style>
    html {
        scroll-behavior: smooth;
    }

    #sectionNav {
        position: fixed;
        top: 90px;            /* tepat di bawah header */
        left: 256px;          /* w-64 sidebar */
        right: 0;
        z-index: 35;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(10px) saturate(180%);
        -webkit-backdrop-filter: blur(10px) saturate(180%);
        padding: 0 2rem;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 4px 8px -2px rgba(0, 0, 0, 0.06), 0 2px 4px -2px rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.2s ease, background 0.2s ease;
    }

    /* Spacer cukup untuk nav (~52px) + sedikit breathing room di atas konten */
    .nav-spacer {
        height: 64px;
    }

    .section-tab {
        position: relative;
        transition: color 0.18s ease, background-color 0.18s ease;
        padding: 0.875rem 1.25rem;
    }

    .section-tab:hover {
        background-color: #f9fafb;
        color: #1f2937;
    }

    .section-tab.active {
        font-weight: 600;
        color: #991b1b;
    }

    /* Indikator underline aktif — bar primary-color tebal sehingga mudah dilihat */
    .section-tab.active::after {
        content: '';
        position: absolute;
        left: 1rem;
        right: 1rem;
        bottom: -1px;          /* overlap border-bottom #sectionNav */
        height: 3px;
        background: linear-gradient(to right, #991b1b, #b91c1c);
        border-radius: 3px 3px 0 0;
    }

    /* Scrollbar untuk tab navigation */
    #sectionNav nav::-webkit-scrollbar {
        height: 4px;
    }

    #sectionNav nav::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    #sectionNav nav::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 2px;
    }

    /* Section scroll margin - account for header + sticky nav height */
    section[id] {
        scroll-margin-top: 130px; /* header 72px + section nav ~50px + padding */
    }

    /* Responsive fixed nav */
    @media (max-width: 768px) {
        #sectionNav {
            top: 65px;
            left: 0; /* No sidebar on mobile */
        }
        section[id] {
            scroll-margin-top: 125px;
        }
    }

    /* Ketika sidebar collapsed (w-20 = 80px) */
    body.sidebar-collapsed #sectionNav {
        left: 80px;
    }


    .section-animate {
        animation: fadeInUp 0.5s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    
    .scroll-indicator {
        position: fixed;
        top: 64px; /* Di bawah header */
        left: 0;
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
        z-index: 15;
        transition: width 0.1s ease;
    }

    .primary-tab-active { color: var(--primary-color) !important; border-color: var(--primary-color) !important; }
    .primary-text { color: var(--primary-color) !important; }
    .primary-link { color: var(--primary-color); }
    .primary-link:hover { opacity: 0.75; }
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }

    /* Selection Toolbar */
    .selection-toolbar {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 50;
        transition: all 0.3s ease;
        display: none;
    }
    
    .selection-toolbar.show {
        display: block;
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from {
            transform: translateX(-50%) translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    }

    /* Checkbox styling */
    .row-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .selected-row {
        background-color: #eff6ff !important;
    }

    /* Toast Notification */
    .toast-notification {
        animation: slideInRight 0.3s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Modal Styles */
    .modal-backdrop {
        backdrop-filter: blur(5px);
        animation: fadeIn 0.2s ease-out;
    }
    
    .modal-content {
        animation: modalSlideUp 0.3s ease-out;
        z-index: 9999;
        position: relative;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes modalSlideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .card-hover {
        transition: box-shadow 0.3s ease, transform 0.3s ease;
    }
    
    .card-hover:hover {
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .project-header {
        min-height: 80px;
        display: flex;
        align-items: center;
    }
    
    .project-title {
        word-break: break-word;
        line-height: 1.3;
    }

    .view-container {
        transition: opacity 0.3s ease;
    }
    
    .view-toggle {
        transition: all 0.2s ease;
    }
    
    .view-toggle:hover {
        transform: scale(1.05);
    }

    section {
        scroll-margin-top: 130px;
    }
</style>
@section('content')
{{-- ✅ SCROLL PROGRESS INDICATOR --}}
<div class="scroll-indicator" id="scrollIndicator"></div>
{{-- ✅ Sticky Navigation Tabs - PALING ATAS --}}
<div class="bg-white" id="sectionNav">
    <nav class="flex overflow-x-auto scrollbar-hide border-b border-gray-200">
        <button onclick="scrollToSection('general')" data-section="general" class="section-tab active text-sm font-medium text-gray-600 whitespace-nowrap">
            General
        </button>
        <button onclick="scrollToSection('delivery')" data-section="delivery" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Delivery Info
        </button>
        <button onclick="scrollToSection('team')" data-section="team" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Team
        </button>
        <button onclick="scrollToSection('documents')" data-section="documents" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Documents
        </button>
        <button onclick="scrollToSection('issues')" data-section="issues" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Issues
        </button>
        <button onclick="scrollToSection('location')" data-section="location" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Location
        </button>
        <button onclick="scrollToSection('planning')" data-section="planning" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Planning
        </button>
    </nav>
</div>

{{-- Spacer untuk fixed navigation --}}
<div class="nav-spacer"></div>

{{-- Back Button --}}
<div class="mb-4">
    <a href="{{ route('projects.index') }}"
        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back
    </a>
</div>

{{-- Project Title & Actions --}}
<div class="bg-white shadow-md rounded-lg mb-4">
    <div class="project-header p-4 sm:p-6 flex justify-between items-start flex-wrap gap-4">
        <div class="flex-1 min-w-0">
            <h1 class="project-title text-2xl font-bold text-gray-800 mb-1">{{ $project->name }}</h1>
            <p class="text-sm text-gray-600">
                {{ $project->client->basicData->name_1 ?? 'N/A' }} •
                <span class="font-semibold">{{ $project->project_type ?? 'N/A' }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            @if($project->onedrive_folder_url)
            <a id="headerFolderBtn" href="{{ $project->onedrive_folder_url }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Open Folder
            </a>
            @else
            <button type="button" id="headerFolderBtn" onclick="openOneDriveModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
                Create Folder
            </button>
            @endif
            <button type="button"
                    onclick="openDeleteModal('{{ $project->id }}', '{{ addslashes($project->name) }}')"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Delete Project
            </button>
        </div>
    </div>
</div>

{{-- General Information Section --}}
<section id="general" class="mb-6 card-hover section-animate">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">General Information</h2>
        </div>
        <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6 mt-3">
            <div class="p-6">
                <table class="min-w-full">
                    <tbody class="divide-y divide-gray-200">
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 w-1/3 text-sm font-medium text-gray-900">Customer</td>
                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $project->client->basicData->name_1 ?? 'N/A' }}</td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">PIC / Project Manager</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <form id="picUpdateForm" action="{{ route('projects.updateField', $project->id) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="field" value="pic">
                                    <div class="custom-dd relative" data-onchange="submitPicForm" data-fixed="true">
                                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                            <span class="custom-dd-label {{ $project->pic ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->pic ?: '-- Select Project Manager --' }}</span>
                                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <input type="hidden" name="value" id="picValue" value="{{ $project->pic }}">
                                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Project Manager --</button>
                                            @foreach($projectManagers as $pm)
                                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $pm->basicData->full_name ?? '-' }}">{{ $pm->basicData->full_name ?? '-' }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Project Type</td>
                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $project->project_type }}</td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Category</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($project->category == 'Open') bg-yellow-100 text-yellow-800
                                    @elseif($project->category == 'In Process') bg-blue-100 text-blue-800
                                    @elseif($project->category == 'Closed') bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ $project->category ?? 'N/A' }}
                                </span>
                                <p class="mt-1 text-xs text-amber-600">*Auto-filled from Project Planning</p>
                            </td>
                        </tr>
                        <tr class="hover:bg-blue-50" id="statusRow" style="{{ $project->category != 'In Process' ? 'display: none;' : '' }}">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Status</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <form id="statusUpdateForm" action="{{ route('projects.updateField', $project->id) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="field" value="status">
                                    <div class="custom-dd relative" data-onchange="submitStatusForm" data-fixed="true">
                                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                                            <span class="custom-dd-label text-gray-700">{{ $project->status ?: 'On Track' }}</span>
                                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <input type="hidden" name="value" id="statusValue" value="{{ $project->status ?: 'On Track' }}">
                                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                                            @foreach(['On Track', 'Monitoring', 'At Risk'] as $status)
                                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $status }}">{{ $status }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Phase</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $project->phase ?? 'N/A' }}
                                </span>
                                <p class="mt-1 text-xs text-amber-600">*Auto-filled from Project Planning</p>
                            </td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Description</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <form action="{{ route('projects.updateField', $project->id) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="field" value="description">
                                    <textarea name="value" rows="3"
                                        class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                        placeholder="Enter project description"
                                        onchange="this.form.submit()">{{ $project->description ?? '' }}</textarea>
                                    <p class="mt-1 text-xs text-gray-500">Press Tab or click outside to save changes</p>
                                </form>
                            </td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Start Date</td>
                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">
                                {{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d M Y') : 'N/A' }}
                                <p class="mt-1 text-xs text-amber-600">*Derived from earliest date in Project Planning</p>
                            </td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">End Date</td>
                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">
                                {{ $project->end_date ? \Carbon\Carbon::parse($project->end_date)->format('d M Y') : 'N/A' }}
                                <p class="mt-1 text-xs text-amber-600">*Derived from latest date in Project Planning</p>
                            </td>
                        </tr>
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Go Live Estimated</td>
                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">
                                {{ $project->go_live_estimated ? \Carbon\Carbon::parse($project->go_live_estimated)->format('d M Y') : 'N/A' }}
                                <p class="mt-1 text-xs text-amber-600">*Derived from the latest date in phase marked as 'Go-Live Phase'</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

{{-- Delivery Information Section --}}
<section id="delivery" class="mb-6 card-hover section-animate">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Delivery Information</h2>
        </div>
        <form action="{{ route('projects.updateDeliveryInfo', $project->id) }}" method="POST" class="p-6">
            @csrf @method('PATCH')
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Account Executive Type</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->ae_type ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->ae_type ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="ae_type" id="ae_type" value="{{ $project->ae_type }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Internal">Internal</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="External">External</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Account Executive</label>
                    <input type="text" name="ae_name" value="{{ $project->ae_name }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Delivery Owner</label>
                    @php
                        $doLabel = '';
                        foreach($employees as $e) { if ($project->delivery_owner_id == $e->employee_id) { $doLabel = $e->basicData->full_name ?? '-'; break; } }
                    @endphp
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $doLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $doLabel ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="delivery_owner_id" value="{{ $project->delivery_owner_id }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            @foreach($employees as $employee)
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Delivery Manager</label>
                    @php
                        $dmLabel = '';
                        foreach($employees as $e) { if ($project->delivery_manager_id == $e->employee_id) { $dmLabel = $e->basicData->full_name ?? '-'; break; } }
                    @endphp
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $dmLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $dmLabel ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="delivery_manager_id" value="{{ $project->delivery_manager_id }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            @foreach($employees as $employee)
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? '-' }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Delivery Method</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->delivery_method ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->delivery_method ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="delivery_method" value="{{ $project->delivery_method }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Onsite">Onsite</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Hybrid">Hybrid</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="WFH">WFH</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Total Mandays</label>
                    <input type="number" name="total_mandays" value="{{ $project->total_mandays }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
            </div>
            <div class="mt-6 text-right">
                <button type="submit" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    Update Information
                </button>
            </div>
        </form>
    </div>
</section>

{{-- Team Section WITH CHECKBOX SELECTION --}}
<section id="team" class="mb-6 card-hover section-animate">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Team Members</h2>
            <button onclick="openModal('teamModal')" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Add Team Member
            </button>
        </div>
        <div class="p-6">
            @if($project->teamMembers->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="teamMembersTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="selectAllTeam" class="row-checkbox" onchange="toggleSelectAll('team')">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Module</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assignment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($project->teamMembers as $member)
                                <tr class="hover:bg-gray-50 team-row" data-member-id="{{ $member->employee_id }}">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="row-checkbox team-checkbox"
                                               data-id="{{ $member->employee_id }}"
                                               data-name="{{ $member->basicData->full_name ?? '-' }}"
                                               data-position="{{ $member->basicData->position ?? '-' }}"
                                               data-module="{{ $member->pivot->module ?? '' }}"
                                               data-assignment="{{ $member->pivot->assignment ?? '' }}"
                                               data-start-date="{{ $member->pivot->start_date ?? '' }}"
                                               data-end-date="{{ $member->pivot->end_date ?? '' }}"
                                               onchange="handleRowSelection('team')">
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $member->basicData->full_name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $member->basicData->position ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $member->pivot->module ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $member->pivot->assignment }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $member->pivot->start_date ? \Carbon\Carbon::parse($member->pivot->start_date)->format('d M Y') : '-' }}
                                        -
                                        {{ $member->pivot->end_date ? \Carbon\Carbon::parse($member->pivot->end_date)->format('d M Y') : 'Present' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-8">No team members added yet.</p>
            @endif
        </div>
    </div>
</section>

{{-- Documents Section WITH CHECKBOX SELECTION --}}
<section id="documents" class="mb-6 card-hover section-animate">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Project Documents</h2>
            <button onclick="openModal('documentModal')" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Add Document
            </button>
        </div>
        <div class="p-6">
            @if($project->documents->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="documentsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="selectAllDocuments" class="row-checkbox" onchange="toggleSelectAll('document')">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Link</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($project->documents as $document)
                                <tr class="hover:bg-gray-50 document-row" data-document-id="{{ $document->id }}">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="row-checkbox document-checkbox" 
                                               data-id="{{ $document->id }}" 
                                               data-name="{{ $document->document_name }}"
                                               data-link="{{ $document->link_document }}"
                                               data-type="{{ $document->document_type }}"
                                               onchange="handleRowSelection('document')">
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $document->document_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <a href="{{ $document->link_document }}" target="_blank" class="primary-link">
                                            {{ Str::limit($document->link_document, 30) }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $document->document_type }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-8">No documents uploaded yet.</p>
            @endif
        </div>
    </div>
</section>

{{-- Issues Section WITH CHECKBOX SELECTION --}}
<section id="issues" class="mb-6 card-hover section-animate">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Project Issues</h2>
            <button onclick="openModal('issueModal')" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Add Issue
            </button>
        </div>
        <div class="p-6">
            @if($project->updates->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="issuesTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="selectAllIssues" class="row-checkbox" onchange="toggleSelectAll('issue')">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Complexity</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($project->updates as $update)
                                <tr class="hover:bg-gray-50 issue-row" data-issue-id="{{ $update->id }}">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="row-checkbox issue-checkbox" 
                                               data-id="{{ $update->id }}"
                                               data-issue="{{ $update->highlight_issue }}"
                                               data-action="{{ $update->action }}"
                                               data-due="{{ $update->due_date }}"
                                               data-status="{{ $update->status }}"
                                               data-complexity="{{ $update->complexity }}"
                                               onchange="handleRowSelection('issue')">
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $update->highlight_issue }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($update->due_date)->format('d M Y') }}
                                        @if(!in_array($update->status, ['Done', 'Closed']) && \Carbon\Carbon::parse($update->due_date)->isPast())
                                            <span class="block text-xs font-semibold text-red-600">Overdue</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            @if($update->status == 'Open') bg-yellow-100 text-yellow-800
                                            @elseif($update->status == 'Closed') bg-green-100 text-green-800
                                            @else bg-blue-100 text-blue-800 @endif">
                                            {{ $update->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $update->complexity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-8">No issues reported yet.</p>
            @endif
        </div>
    </div>
</section>

{{-- Selection Toolbar (Floating Action Bar) --}}
<div id="selectionToolbar" class="selection-toolbar">
    <div class="primary-gradient text-white rounded-lg shadow-2xl px-6 py-4 flex items-center space-x-4">
        <span id="selectionCount" class="font-semibold">0 selected</span>
        <div class="h-6 w-px bg-white opacity-40"></div>
        <button onclick="handleBulkEdit()" class="flex items-center space-x-2 px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-md transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span>Edit</span>
        </button>
        <button onclick="handleBulkDelete()" class="flex items-center space-x-2 px-4 py-2 bg-red-500 hover:bg-red-400 rounded-md transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            <span>Delete</span>
        </button>
        <button onclick="clearAllSelections()" class="flex items-center space-x-2 px-4 py-2 bg-gray-500 hover:bg-gray-400 rounded-md transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span>Clear</span>
        </button>
    </div>
</div>

{{-- Location Section --}}
<section id="location" class="mb-6 card-hover section-animate">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Location Information</h2>
        </div>
        <form action="{{ route('projects.updateLocationInfo', $project->id) }}" method="POST" class="p-6">
            @csrf @method('PATCH')
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Location Name</label>
                    <input type="text" name="location_name" value="{{ $project->location_name }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Type of Address</label>
                    <div class="custom-dd relative" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label {{ $project->location_type ? 'text-gray-700' : 'text-gray-500' }}">{{ $project->location_type ?: '-- Select --' }}</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" name="location_type" value="{{ $project->location_type }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Head Office">Head Office</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Plant">Plant</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Country</label>
                    <input type="text" value="Indonesia" readonly
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm bg-white text-gray-900">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Geographical</label>
                    <input type="text" name="location_geographical" value="{{ $project->location_geographical }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Region / Province</label>
                    <input type="text" name="location_region" value="{{ $project->location_region }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">City</label>
                    <input type="text" name="location_city" value="{{ $project->location_city }}"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Valid From</label>
                    <input type="text" value="{{ $project->location_valid_from ? \Carbon\Carbon::parse($project->location_valid_from)->format('d M Y') : 'N/A' }}" readonly
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm bg-white text-gray-900">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Valid To</label>
                    <input type="text" value="{{ $project->location_valid_to ? \Carbon\Carbon::parse($project->location_valid_to)->format('d M Y') : 'N/A' }}" readonly
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm bg-white text-gray-900">
                </div>
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Street Address</label>
                    <textarea name="location_street" rows="2"
                              class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">{{ $project->location_street }}</textarea>
                </div>
            </div>
            <div class="mt-6 text-right">
                <button type="submit" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    Update Location
                </button>
            </div>
        </form>
    </div>
</section>

{{-- ✅✅✅ PROJECT PLANNING SECTION (INTEGRATED) ✅✅✅ --}}
<section id="planning" class="mb-6 card-hover section-animate" data-project-id="{{ $project->id }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        Project Planning
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">Manage project phases, groups, stages, and activities</p>
                </div>
                
                <div class="flex items-center space-x-2">
                    <div class="inline-flex rounded-md shadow-sm" role="group">
                        <button type="button" 
                                data-view="table"
                                onclick="switchPlanningView('table')"
                                class="planning-view-toggle px-4 py-2 text-sm font-medium text-white primary-gradient border border-gray-200 rounded-l-lg hover:opacity-90 transition">
                            <svg class="inline-block w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            Table View
                        </button>
                        <button type="button"
                                data-view="gantt"
                                onclick="switchPlanningView('gantt')"
                                class="planning-view-toggle px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-r-lg hover:bg-gray-100 transition">
                            <svg class="inline-block w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                            </svg>
                            Gantt Chart
                        </button>
                    </div>
                    
                    <a href="{{ route('planning.phases.index', $project) }}"  
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                        <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Manage Full Planning
                    </a>
                </div>
            </div>
        </div>
        
        {{-- TABLE VIEW --}}
        <div id="planningTableView" class="planning-view-container">
            <div class="p-6">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <button onclick="expandAllPlanning()" class="hover:text-gray-900 px-3 py-1 bg-white rounded hover:shadow transition">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                                Expand All
                            </button>
                            <button onclick="collapseAllPlanning()" class="hover:text-gray-900 px-3 py-1 bg-white rounded hover:shadow transition">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                </svg>
                                Collapse All
                            </button>
                        </div>
                        <button onclick="refreshPlanningData()" class="px-3 py-1 bg-white primary-text rounded hover:shadow transition">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="planningTable">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-20 min-w-[400px]">
                                    Phase / Group / Stage / Activity
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Weight %</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Description</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Object</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50 w-32">Start</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50 w-32">End</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50 w-24">Days</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Status</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="planningTableBody">
                            <tr>
                                <td colspan="9" class="text-center p-8">
                                    <div class="text-gray-500">
                                        <svg class="animate-spin h-8 w-8 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Loading planning data...
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        {{-- GANTT VIEW --}}
        <div id="planningGanttView" class="planning-view-container hidden">
            <div class="p-6">
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">📊 Gantt Chart View</h3>
                        <div class="flex items-center space-x-2">
                            <button onclick="expandAllGanttPlanning()" class="px-3 py-1 bg-white text-gray-700 rounded hover:shadow transition text-sm">
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                                Expand All
                            </button>
                            <button onclick="collapseAllGanttPlanning()" class="px-3 py-1 bg-white text-gray-700 rounded hover:shadow transition text-sm">
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                </svg>
                                Collapse All
                            </button>
                            <button onclick="refreshGanttPlanning()" class="px-3 py-1 bg-white primary-text rounded hover:shadow transition text-sm">
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="ganttLoadingPlanning" class="flex items-center justify-center py-20">
                    <div class="text-center">
                        <svg class="animate-spin h-8 w-8 text-purple-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm text-gray-600">Loading Gantt Chart...</p>
                    </div>
                </div>
                
                <div id="ganttContentPlanning" class="hidden">
                    <div id="ganttChartPlanning"></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ALL MODALS --}}

{{-- Team Modal --}}
<div id="teamModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('teamModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Add Team Member</h3>
            </div>
            <form action="{{ route('projects.team.store', $project->id) }}" method="POST">
                @csrf
                <div class="modal-body p-6 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Consultant</label>
                            <select name="employee_id" id="employee_id" required
                                    class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                                <option value="">-- Select Employee --</option>
                                @foreach($consultants as $employee)
                                    <option value="{{ $employee->employee_id }}"
                                            data-department="{{ $employee->basicData->department ?? '' }}"
                                            data-whatsapp="{{ $employee->addresses->first()->cell_phone ?? '' }}"
                                            data-email="{{ $employee->addresses->first()->email_work ?? '' }}">
                                        {{ $employee->basicData->full_name ?? 'N/A' }} ({{ $employee->basicData->position ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Module</label>
                            <input type="text" name="module" id="modul"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                   placeholder="e.g. FI, CO, MM">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Assignment</label>
                            <select name="assignment" required
                                    class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                                <option value="">-- Select Role --</option>
                                <option value="Project Manager">Project Manager</option>
                                <option value="Co Project Manager">Co Project Manager</option>
                                <option value="FI Team Lead">FI Team Lead</option>
                                <option value="FI Team Member">FI Team Member</option>
                                <option value="CO Team Lead">CO Team Lead</option>
                                <option value="CO Team Member">CO Team Member</option>
                                <option value="MM Team Lead">MM Team Lead</option>
                                <option value="MM Team Member">MM Team Member</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">WhatsApp</label>
                            <input type="text" id="whatsapp_number" readonly
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm bg-white text-gray-900">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Start Date</label>
                            <input type="date" name="start_date"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">End Date</label>
                            <input type="date" name="end_date"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('teamModal')"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Team Member Modal --}}
<div id="editTeamModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('editTeamModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Edit Team Member</h3>
            </div>
            <form id="editTeamMemberForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-6 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Consultant</label>
                            <input type="text" id="edit_employee_name" readonly
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm bg-gray-100 text-gray-700">
                            <input type="hidden" name="employee_id" id="edit_employee_id">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Module</label>
                            <input type="text" name="module" id="edit_module"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                   placeholder="e.g. FI, CO, MM">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Assignment</label>
                            <select name="assignment" id="edit_assignment" required
                                    class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                                <option value="">-- Select Role --</option>
                                <option value="Project Manager">Project Manager</option>
                                <option value="Co Project Manager">Co Project Manager</option>
                                <option value="FI Team Lead">FI Team Lead</option>
                                <option value="FI Team Member">FI Team Member</option>
                                <option value="CO Team Lead">CO Team Lead</option>
                                <option value="CO Team Member">CO Team Member</option>
                                <option value="MM Team Lead">MM Team Lead</option>
                                <option value="MM Team Member">MM Team Member</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Position</label>
                            <input type="text" id="edit_position" readonly
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm bg-gray-100 text-gray-700">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">End Date</label>
                            <input type="date" name="end_date" id="edit_end_date"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editTeamModal')"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Update Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Document Modal --}}
<div id="documentModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('documentModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-lg w-full">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Add Document</h3>
            </div>
            <form action="{{ route('project.documents.store', $project->id) }}" method="POST">
                @csrf
                <div class="modal-body p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Document Name</label>
                            <input type="text" name="document_name" required
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm"
                                   placeholder="e.g., Contract Document">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Link Document</label>
                            <input type="url" name="link_document" required
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm"
                                   placeholder="https://...">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="document_type" required
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">-- Select Type --</option>
                                <option value="BAST/BAPP">BAST/BAPP</option>
                                <option value="Contract">Contract</option>
                                <option value="Justification">Justification</option>
                                <option value="PR/PO">PR/PO</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('documentModal')"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Add Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Issue Modal --}}
<div id="issueModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('issueModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Add Issue</h3>
            </div>
            <form action="{{ route('project.updates.store', $project->id) }}" method="POST">
                @csrf
                <div class="modal-body p-6 overflow-y-auto">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Highlight Issue</label>
                            <textarea name="highlight_issue" rows="3" required
                                      class="block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                            <textarea name="action" rows="3" required
                                      class="block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                                <input type="date" name="due_date" required
                                       class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" required
                                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="To Be Discussed">To Be Discussed</option>
                                    <option value="To Be Confirmed">To Be Confirmed</option>
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complexity</label>
                                <select name="complexity" required
                                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('issueModal')"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Add Issue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Document Modal --}}
<div id="editDocumentModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('editDocumentModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-lg w-full">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Edit Document</h3>
            </div>
            <form id="editDocumentForm">
                @csrf
                @method('PATCH')
                <input type="hidden" id="edit_document_id">
                <div class="modal-body p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Document Name</label>
                            <input type="text" id="edit_document_name" required
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Link Document</label>
                            <input type="url" id="edit_document_link" required
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="edit_document_type" required
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="BAST/BAPP">BAST/BAPP</option>
                                <option value="Contract">Contract</option>
                                <option value="Justification">Justification</option>
                                <option value="PR/PO">PR/PO</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editDocumentModal')"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Update Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Issue Modal --}}
<div id="editIssueModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('editIssueModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Edit Issue</h3>
            </div>
            <form id="editIssueForm">
                @csrf
                @method('PATCH')
                <input type="hidden" id="edit_issue_id">
                <div class="modal-body p-6 overflow-y-auto space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Highlight Issue</label>
                        <textarea id="edit_highlight_issue" rows="3" required
                                  class="block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                        <textarea id="edit_action" rows="3" required
                                  class="block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="date" id="edit_due_date" required
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="edit_status" required
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="To Be Discussed">To Be Discussed</option>
                                <option value="To Be Confirmed">To Be Confirmed</option>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Complexity</label>
                            <select id="edit_complexity" required
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editIssueModal')"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Update Issue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- OneDrive Folder Modal --}}
<div id="oneDriveModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeOneDriveModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full z-10 overflow-hidden">

            {{-- State 1: Generate form --}}
            <div id="odrStateGenerate">
                <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                        </svg>
                        <h3 class="text-base font-semibold text-gray-900">Create OneDrive Folder</h3>
                    </div>
                    <button onclick="closeOneDriveModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-gray-500">
                        The folder will be created in the OneDrive account <strong class="text-gray-700">{{ config('services.microsoft_graph.sender_email') }}</strong>
                        and accessible to anyone with the link (edit &amp; upload access).
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Folder Name <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <input type="text" id="odrFolderName"
                               value="{{ $project->name ?? 'Project-' . $project->id }}"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-400 mt-1">Name of the folder to be created in OneDrive.</p>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button onclick="generateProjectFolder()" id="odrGenerateBtn"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg id="odrGenerateIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                            </svg>
                            <svg id="odrGenerateSpinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span id="odrGenerateLabel">{{ $project->onedrive_folder_id ? 'Regenerate Link' : 'Generate Folder' }}</span>
                        </button>
                        <button type="button" id="odrDeleteBtnForm" onclick="deleteProjectFolder()"
                            class="{{ $project->onedrive_folder_id ? '' : 'hidden' }} px-4 py-2.5 border border-red-200 text-sm text-red-600 rounded-lg hover:bg-red-50 transition-all inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Folder
                        </button>
                        <button type="button" onclick="closeOneDriveModal()"
                            class="px-4 py-2.5 border border-gray-300 text-sm text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            {{-- State 2: Success --}}
            <div id="odrStateSuccess" class="hidden">
                <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-base font-semibold text-gray-900">OneDrive Folder Ready</h3>
                    </div>
                    <button onclick="closeOneDriveModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="flex justify-center">
                        <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 text-center">
                        Folder created successfully. Share the link below with anyone who needs access.
                    </p>
                    <div class="flex gap-2">
                        <input type="text" id="odrFolderUrl" readonly
                               class="flex-1 px-3 py-2 text-xs border border-gray-300 rounded-lg bg-gray-50 text-gray-700 focus:outline-none cursor-text select-all">
                        <button onclick="copyFolderLink()" id="odrCopyBtn" title="Copy link"
                            class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                            <svg id="odrCopyIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg id="odrCopiedIcon" class="hidden w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <a id="odrOpenLink" href="#" target="_blank" rel="noopener"
                           class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Open Folder
                        </a>
                        <button onclick="deleteProjectFolder()"
                            class="px-4 py-2.5 border border-red-200 text-sm text-red-600 rounded-lg hover:bg-red-50 transition-all inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Folder
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="deleteModal" class="fixed inset-0 z-[9998] hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 z-[9998]" onclick="closeModal('deleteModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 z-[9999]">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-md w-full relative">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Delete</h3>
                <p class="text-sm text-gray-600" id="deleteMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('deleteModal')"
                        class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    Cancel
                </button>
                <button type="button" id="confirmDeleteBtn"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ✅ LOAD SCRIPTS (IN CORRECT ORDER) --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
// ============================================
// SMOOTH SCROLL & STICKY NAV FUNCTIONALITY
// ============================================
const projectId = {{ $project->id }};
window.projectId = projectId;

// ✅ Scroll Progress Indicator
window.addEventListener('scroll', function() {
    const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
    const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    const scrolled = (winScroll / height) * 100;
    const indicator = document.getElementById('scrollIndicator');
    if (indicator) {
        indicator.style.width = scrolled + '%';
    }
});

// ✅ Smooth Scroll to Section
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;

    // Calculate proper offset (main navbar + sticky section nav + padding)
    const mainNavHeight = 73;  // Main navbar height
    const sectionNavHeight = 50; // Sticky section nav height
    const extraPadding = 15;   // Extra breathing room
    const totalOffset = mainNavHeight + sectionNavHeight + extraPadding;

    const targetPosition = section.offsetTop - totalOffset;

    window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
    });

    // Update active tab styling
    updateActiveTab(sectionId);
}

// ✅ Update Active Tab
function updateActiveTab(sectionId) {
    document.querySelectorAll('.section-tab').forEach(tab => {
        const tabSection = tab.getAttribute('data-section');
        if (tabSection === sectionId) {
            tab.classList.remove('text-gray-600', 'border-transparent');
            tab.classList.add('primary-tab-active', 'active');
        } else {
            tab.classList.remove('primary-tab-active', 'active');
            tab.classList.add('text-gray-600', 'border-transparent');
        }
    });
}


const observerOptions = {
    root: null,
    rootMargin: '-140px 0px -60% 0px', // Account for navbar + sticky section nav
    threshold: 0
};

const sectionObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const sectionId = entry.target.id;
            updateActiveTab(sectionId);
        }
    });
}, observerOptions);

// Observe all sections
document.querySelectorAll('section[id]').forEach(section => {
    sectionObserver.observe(section);
});

function syncNavWithSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sectionNav = document.getElementById('sectionNav');

    if (!sidebar || !sectionNav) return;

    // Check if sidebar is collapsed
    const isCollapsed = sidebar.classList.contains('w-20');

    if (isCollapsed) {
        sectionNav.style.left = '80px'; // w-20 = 5rem = 80px
    } else {
        sectionNav.style.left = '256px'; // w-64 = 16rem = 256px
    }
}

// Watch for sidebar changes
const sidebarObserver = new MutationObserver(syncNavWithSidebar);
const sidebar = document.getElementById('sidebar');
if (sidebar) {
    sidebarObserver.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
}

// Initial sync
syncNavWithSidebar();


let currentPlanningView = 'table';
let planningDataLoaded = false;
let ganttPlanningLoaded = false;

function switchPlanningView(view) {
    currentPlanningView = view;
    
    // Update button states
    document.querySelectorAll('.planning-view-toggle').forEach(btn => {
        if (btn.dataset.view === view) {
            btn.classList.remove('bg-white', 'text-gray-900');
            btn.classList.add('primary-gradient', 'text-white');
        } else {
            btn.classList.remove('primary-gradient', 'text-white');
            btn.classList.add('bg-white', 'text-gray-900');
        }
    });
    
    // Toggle containers
    const tableView = document.getElementById('planningTableView');
    const ganttView = document.getElementById('planningGanttView');
    
    if (view === 'table') {
        tableView.classList.remove('hidden');
        ganttView.classList.add('hidden');
        
        if (!planningDataLoaded) {
            loadPlanningTableData();
        }
    } else if (view === 'gantt') {
        tableView.classList.add('hidden');
        ganttView.classList.remove('hidden');
        
        if (!ganttPlanningLoaded) {
            loadPlanningGanttData();
        }
    }
}

// ✅ Load Planning Table Data
function loadPlanningTableData() {
    
    const tbody = document.getElementById('planningTableBody');
    if (!tbody) return;
    
    axios.get(`/planning/${projectId}/data/table`)
        .then(response => {
            renderPlanningTable(response.data);
            planningDataLoaded = true;
        })
        .catch(error => {
            console.error('❌ Error loading planning data:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-12 text-red-600">
                        <strong>Error loading planning data:</strong><br>
                        ${error.response?.data?.message || error.message}
                    </td>
                </tr>
            `;
        });
}

// ✅ Render Planning Table (REUSE table-view logic)
function renderPlanningTable(phasesData) {
    const tbody = document.getElementById('planningTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (phasesData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-12">
                    <div class="text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium">No phases configured</h3>
                        <p class="mt-1 text-sm">Visit the full planning page to configure phases.</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Render phases (simplified version from table-view)
    phasesData.forEach(phaseData => {
        const phase = phaseData.phase;
        const groups = phaseData.groups || [];
        
        // Phase row
        const phaseRow = document.createElement('tr');
        phaseRow.className = 'bg-gradient-to-r from-indigo-100 to-purple-100 font-bold border-l-4';
        phaseRow.style.borderLeftColor = phase.color;
        phaseRow.dataset.phaseId = phase.id;
        
        phaseRow.innerHTML = `
            <td class="px-3 py-4 sticky left-0 bg-gradient-to-r from-indigo-100 to-purple-100 z-10 min-w-[400px]">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20" style="color: ${phase.color}">
                        <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM2 12a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    <span class="text-base uppercase tracking-wide">${phase.name}</span>
                </div>
            </td>
            <td class="px-3 py-4 text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold" style="background-color: ${phase.color}20; color: ${phase.color}">
                    ${parseFloat(phase.weight).toFixed(1)}%
                </span>
            </td>
            <td colspan="2" class="px-3 py-4 text-xs text-gray-600 italic">Phase Level</td>
            <td class="px-3 py-4 text-xs text-center bg-blue-50 font-medium">${phase.start_date || '-'}</td>
            <td class="px-3 py-4 text-xs text-center bg-blue-50 font-medium">${phase.end_date || '-'}</td>
            <td class="px-3 py-4 text-xs text-center bg-blue-50 font-bold">${phase.duration_in_days || '-'}</td>
            <td class="px-3 py-4 text-center">
                <span class="px-3 py-1 text-xs font-bold rounded-full ${phase.status_badge || 'bg-gray-100 text-gray-800'}">
                    ${phase.status_text || 'Not Started'}
                </span>
            </td>
            <td class="px-3 py-4">
                <div class="flex items-center">
                    <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                        <div class="h-2 rounded-full transition-all" style="width: ${phase.progress}%; background-color: ${phase.color}"></div>
                    </div>
                    <span class="text-sm font-bold" style="color: ${phase.color}">${Math.round(phase.progress)}%</span>
                </div>
            </td>
        `;
        tbody.appendChild(phaseRow);
        
        // Groups (simplified - just show count)
        if (groups.length > 0) {
            const groupRow = document.createElement('tr');
            groupRow.innerHTML = `
                <td colspan="9" class="px-3 py-2 pl-16 text-sm text-gray-600 bg-gray-50">
                    📁 ${groups.length} Group(s) • <a href="{{ route('planning.phases.index', $project) }}" class="primary-link">View full details →</a>
                </td>
            `;
            tbody.appendChild(groupRow);
        }
    });
}

// ✅ Load Planning Gantt Data
function loadPlanningGanttData() {
    
    const loading = document.getElementById('ganttLoadingPlanning');
    const content = document.getElementById('ganttContentPlanning');
    
    axios.get(`/planning/${projectId}/data/gantt`)
        .then(response => {

            loading.classList.add('hidden');
            content.classList.remove('hidden');
            
            // Render simplified gantt or show link to full view
            content.innerHTML = `
                <div class="text-center py-12">
                    <svg class="mx-auto h-16 w-16 primary-text mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Full Gantt Chart Available</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        The complete interactive Gantt chart with all features is available on the dedicated planning page.
                    </p>
                    <a href="{{ route('planning.phases.index', $project) }}" 
                       class="inline-flex items-center px-6 py-3 primary-gradient text-white font-medium rounded-lg hover:opacity-90 transition shadow-lg hover:shadow-xl">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Open Full Planning Page
                    </a>
                </div>
            `;
            
            ganttPlanningLoaded = true;
        })
        .catch(error => {
            console.error('❌ Error loading gantt data:', error);
            loading.innerHTML = `
                <div class="text-center py-12 text-red-600">
                    <strong>Error loading Gantt Chart</strong><br>
                    <p class="text-sm mt-2">${error.response?.data?.message || error.message}</p>
                </div>
            `;
        });
}

// ✅ Refresh Functions
function refreshPlanningData() {
    planningDataLoaded = false;
    loadPlanningTableData();
}

function refreshGanttPlanning() {
    ganttPlanningLoaded = false;
    loadPlanningGanttData();
}

// Expand/Collapse Functions (placeholders)
function expandAllPlanning() {
    showNotification('Expand all: Visit full planning page for complete functionality', 'info');
}

function collapseAllPlanning() {
    showNotification('Collapse all: Visit full planning page for complete functionality', 'info');
}

function expandAllGanttPlanning() {
    showNotification('Expand all: Visit full planning page for complete Gantt functionality', 'info');
}

function collapseAllGanttPlanning() {
    showNotification('Collapse all: Visit full planning page for complete Gantt functionality', 'info');
}

// ✅ Auto-load planning on scroll to section
const planningObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !planningDataLoaded) {
            loadPlanningTableData();
        }
    });
}, { threshold: 0.1 });

const planningSection = document.getElementById('planning');
if (planningSection) {
    planningObserver.observe(planningSection);
}

// ============================================
// CHECKBOX SELECTION FUNCTIONALITY
// ============================================
let selectedItems = {
    team: new Set(),
    document: new Set(),
    issue: new Set()
};

let currentType = null;

function toggleSelectAll(type) {
    const selectAllCheckbox = document.getElementById(`selectAll${type.charAt(0).toUpperCase() + type.slice(1)}s`);
    const checkboxes = document.querySelectorAll(`.${type}-checkbox`);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
        const row = checkbox.closest('tr');
        if (selectAllCheckbox.checked) {
            row.classList.add('selected-row');
            selectedItems[type].add(checkbox.dataset.id);
        } else {
            row.classList.remove('selected-row');
            selectedItems[type].clear();
        }
    });
    
    updateSelectionToolbar(type);
}

function handleRowSelection(type) {
    const checkboxes = document.querySelectorAll(`.${type}-checkbox:checked`);
    const selectAllCheckbox = document.getElementById(`selectAll${type.charAt(0).toUpperCase() + type.slice(1)}s`);
    const allCheckboxes = document.querySelectorAll(`.${type}-checkbox`);
    
    selectedItems[type].clear();
    
    checkboxes.forEach(checkbox => {
        selectedItems[type].add(checkbox.dataset.id);
        checkbox.closest('tr').classList.add('selected-row');
    });
    
    allCheckboxes.forEach(checkbox => {
        if (!checkbox.checked) {
            checkbox.closest('tr').classList.remove('selected-row');
        }
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = checkboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
    }
    
    currentType = type;
    updateSelectionToolbar(type);
}

function updateSelectionToolbar(type) {
    const toolbar = document.getElementById('selectionToolbar');
    const count = selectedItems[type].size;
    const countSpan = document.getElementById('selectionCount');
    
    if (count > 0) {
        toolbar.classList.add('show');
        countSpan.textContent = `${count} item${count > 1 ? 's' : ''} selected`;
        currentType = type;
    } else {
        toolbar.classList.remove('show');
        currentType = null;
    }
}

function clearAllSelections() {
    if (currentType) {
        selectedItems[currentType].clear();
        document.querySelectorAll(`.${currentType}-checkbox`).forEach(checkbox => {
            checkbox.checked = false;
            checkbox.closest('tr').classList.remove('selected-row');
        });
        const selectAllCheckbox = document.getElementById(`selectAll${currentType.charAt(0).toUpperCase() + currentType.slice(1)}s`);
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }
    }
    document.getElementById('selectionToolbar').classList.remove('show');
    currentType = null;
}

// ============================================
// BULK ACTIONS
// ============================================
function handleBulkEdit() {
    if (!currentType || selectedItems[currentType].size === 0) {
        showNotification('Please select at least one item', 'error');
        return;
    }
    
    if (selectedItems[currentType].size > 1) {
        showNotification('Please select only one item to edit', 'error');
        return;
    }
    
    const selectedId = Array.from(selectedItems[currentType])[0];
    const checkbox = document.querySelector(`.${currentType}-checkbox[data-id="${selectedId}"]`);
    
    if (currentType === 'document') {
        openEditDocumentModal(checkbox);
    } else if (currentType === 'issue') {
        openEditIssueModal(checkbox);
    } else if (currentType === 'team') {
        openEditTeamMemberModal(checkbox);
    }
}

function handleBulkDelete() {
    if (!currentType || selectedItems[currentType].size === 0) {
        showNotification('Please select at least one item', 'error');
        return;
    }
    
    const count = selectedItems[currentType].size;
    const itemType = currentType.charAt(0).toUpperCase() + currentType.slice(1);
    
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to delete ${count} ${itemType}${count > 1 ? 's' : ''}? This action cannot be undone.`;
    
    openModal('deleteModal');
    
    document.getElementById('confirmDeleteBtn').onclick = function() {
        executeBulkDelete();
    };
}

async function executeBulkDelete() {
    const selectedIds = Array.from(selectedItems[currentType]);
    
    for (const id of selectedIds) {
        try {
            let url;
            if (currentType === 'document') {
                url = `/project/documents/${id}`;
            } else if (currentType === 'issue') {
                url = `/project-updates/${id}`;
            } else if (currentType === 'team') {
                url = `/projects/{{ $project->id }}/team-members/${id}`;
            }
            
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            });
            
            if (response.ok) {
                const row = document.querySelector(`[data-${currentType}-id="${id}"]`);
                if (row) {
                    row.remove();
                }
            }
        } catch (error) {
            console.error('Delete error:', error);
        }
    }
    
    showNotification(`Successfully deleted ${selectedIds.length} item(s)`, 'success');
    clearAllSelections();
    closeModal('deleteModal');
}

// ============================================
// EDIT MODAL FUNCTIONS
// ============================================
function openEditDocumentModal(checkbox) {
    document.getElementById('edit_document_id').value = checkbox.dataset.id;
    document.getElementById('edit_document_name').value = checkbox.dataset.name;
    document.getElementById('edit_document_link').value = checkbox.dataset.link;
    document.getElementById('edit_document_type').value = checkbox.dataset.type;
    openModal('editDocumentModal');
}

function openEditIssueModal(checkbox) {
    document.getElementById('edit_issue_id').value = checkbox.dataset.id;
    document.getElementById('edit_highlight_issue').value = checkbox.dataset.issue;
    document.getElementById('edit_action').value = checkbox.dataset.action;
    document.getElementById('edit_due_date').value = checkbox.dataset.due;
    document.getElementById('edit_status').value = checkbox.dataset.status;
    document.getElementById('edit_complexity').value = checkbox.dataset.complexity;
    openModal('editIssueModal');
}

function openEditTeamMemberModal(checkbox) {
    const employeeId = checkbox.dataset.id;
    const employeeName = checkbox.dataset.name;
    const position = checkbox.dataset.position;
    const module = checkbox.dataset.module;
    const assignment = checkbox.dataset.assignment;
    const startDate = checkbox.dataset.startDate;
    const endDate = checkbox.dataset.endDate;

    // Set form action URL
    document.getElementById('editTeamMemberForm').action = `/projects/{{ $project->id }}/team-members/${employeeId}`;

    // Populate form fields
    document.getElementById('edit_employee_id').value = employeeId;
    document.getElementById('edit_employee_name').value = employeeName;
    document.getElementById('edit_position').value = position;
    document.getElementById('edit_module').value = module || '';
    document.getElementById('edit_assignment').value = assignment || '';
    document.getElementById('edit_start_date').value = startDate || '';
    document.getElementById('edit_end_date').value = endDate || '';

    openModal('editTeamModal');
}

// Edit Team Member Form Submit
document.getElementById('editTeamMemberForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                _method: 'PUT',
                module: formData.get('module'),
                assignment: formData.get('assignment'),
                start_date: formData.get('start_date'),
                end_date: formData.get('end_date')
            })
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('editTeamModal');
            clearAllSelections();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to update team member', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred while updating team member', 'error');
    }
});

// Edit Document Form Submit
document.getElementById('editDocumentForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const documentId = document.getElementById('edit_document_id').value;
    
    const formData = {
        document_name: document.getElementById('edit_document_name').value,
        link_document: document.getElementById('edit_document_link').value,
        document_type: document.getElementById('edit_document_type').value,
    };
    
    try {
        const response = await fetch(`/project/documents/${documentId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        if (response.ok) {
            showNotification('Document updated successfully!', 'success');
            closeModal('editDocumentModal');
            clearAllSelections();
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error('Update failed');
        }
    } catch (error) {
        showNotification('Failed to update document', 'error');
    }
});

// Edit Issue Form Submit
document.getElementById('editIssueForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const issueId = document.getElementById('edit_issue_id').value;
    
    const formData = {
        highlight_issue: document.getElementById('edit_highlight_issue').value,
        action: document.getElementById('edit_action').value,
        due_date: document.getElementById('edit_due_date').value,
        status: document.getElementById('edit_status').value,
        complexity: document.getElementById('edit_complexity').value,
    };
    
    try {
        const response = await fetch(`/project-updates/${issueId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        if (response.ok) {
            showNotification('Issue updated successfully!', 'success');
            closeModal('editIssueModal');
            clearAllSelections();
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error('Update failed');
        }
    } catch (error) {
        showNotification('Failed to update issue', 'error');
    }
});

// ============================================
// MODAL FUNCTIONALITY
// ============================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// ============================================
// DELETE PROJECT FUNCTIONALITY
// ============================================
function openDeleteModal(projectId, projectName) {
    // Set message
    document.getElementById('deleteMessage').innerHTML = `
        Are you sure you want to delete project <strong>${projectName}</strong>?<br>
        <span class="text-red-600 font-semibold">This action cannot be undone and will delete all associated data!</span>
    `;
    
    // Show modal
    openModal('deleteModal');
    
    // Set up delete button onclick
    document.getElementById('confirmDeleteBtn').onclick = function() {
        executeProjectDelete(projectId);
    };
}

async function executeProjectDelete(projectId) {
    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.innerHTML;
    
    // Show loading
    btn.innerHTML = `
        <svg class="animate-spin h-4 w-4 inline-block mr-2" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Deleting...
    `;
    btn.disabled = true;
    
    try {
        const response = await fetch(`/projects/${projectId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            showNotification('Project deleted successfully!', 'success');
            closeModal('deleteModal');
            
            // Redirect to projects index after 1 second
            setTimeout(() => {
                window.location.href = '{{ route("projects.index") }}';
            }, 1000);
        } else {
            const data = await response.json();
            throw new Error(data.message || 'Failed to delete project');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Failed to delete project: ' + error.message, 'error');
        
        // Restore button
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = ['teamModal', 'documentModal', 'issueModal', 'deleteModal', 'editDocumentModal', 'editIssueModal'];
        modals.forEach(modalId => closeModal(modalId));
    }
});

// Show success message if present
@if(session('success'))
    showNotification('{{ session('success') }}', 'success');
@endif

@if(session('error'))
    showNotification('{{ session('error') }}', 'error');
@endif

// ============================================
// OTHER EXISTING FUNCTIONS
// ============================================
// Employee selection auto-fill
document.getElementById('employee_id')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    document.getElementById('modul').value = selectedOption.dataset.modul || '';
    document.getElementById('whatsapp_number').value = selectedOption.dataset.whatsapp || '';
});

// Initialize page on load
document.addEventListener('DOMContentLoaded', function() {
    // Add scroll animations to sections
    const sections = document.querySelectorAll('section');
    sections.forEach((section, index) => {
        section.style.animationDelay = (index * 0.1) + 's';
    });

    // Init custom-dd untuk semua dropdown PIC/Status/Delivery/Location.
    // Guard typeof biar halaman tidak crash kalau custom-dropdown.js gagal di-load.
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }
});

// Auto-submit handlers untuk PIC & Status — dipanggil custom-dd via
// data-onchange setiap kali user pilih opsi (custom-dd tidak fire event
// 'change' di hidden input).
function submitPicForm() {
    const f = document.getElementById('picUpdateForm');
    if (f) f.submit();
}
function submitStatusForm() {
    const f = document.getElementById('statusUpdateForm');
    if (f) f.submit();
}


// ── OneDrive Modal ────────────────────────────────────────────────────────
let _odrHasFolder = {{ $project->onedrive_folder_id ? 'true' : 'false' }};

function openOneDriveModal() {
    document.getElementById('oneDriveModal').classList.remove('hidden');
    _showOdrGenerate();
}

function closeOneDriveModal() {
    document.getElementById('oneDriveModal').classList.add('hidden');
}

function _showOdrGenerate() {
    document.getElementById('odrStateGenerate').classList.remove('hidden');
    document.getElementById('odrStateSuccess').classList.add('hidden');
    const del = document.getElementById('odrDeleteBtnForm');
    if (del) del.classList.toggle('hidden', !_odrHasFolder);
}

function _showOdrSuccess(url) {
    document.getElementById('odrStateGenerate').classList.add('hidden');
    document.getElementById('odrStateSuccess').classList.remove('hidden');
    document.getElementById('odrFolderUrl').value = url;
    document.getElementById('odrOpenLink').href   = url;
    document.getElementById('odrCopyIcon').classList.remove('hidden');
    document.getElementById('odrCopiedIcon').classList.add('hidden');
    _odrHasFolder = true;
    // Swap header button to "Open Folder"
    const btn = document.getElementById('headerFolderBtn');
    if (btn && btn.tagName === 'BUTTON') {
        const a = document.createElement('a');
        a.id = 'headerFolderBtn'; a.href = url; a.target = '_blank'; a.rel = 'noopener';
        a.className = btn.className;
        a.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg> Open Folder`;
        btn.replaceWith(a);
    }
}

async function deleteProjectFolder() {
    if (!confirm('Are you sure you want to delete this OneDrive folder? The folder and all its contents will be permanently deleted.')) return;
    try {
        const res  = await fetch('{{ route('projects.deleteFolder', $project->id) }}', {
            method:  'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        });
        const data = await res.json();
        if (data.success) {
            _odrHasFolder = false;
            closeOneDriveModal();
            // Revert header button to "Create Folder"
            const el = document.getElementById('headerFolderBtn');
            if (el) {
                const btn = document.createElement('button');
                btn.type = 'button'; btn.id = 'headerFolderBtn'; btn.onclick = openOneDriveModal;
                btn.className = el.className;
                btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg> Create Folder`;
                el.replaceWith(btn);
            }
            showToast('Folder deleted successfully.', 'success');
        } else {
            showToast(data.message || 'Failed to delete folder.', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function generateProjectFolder() {
    const btn     = document.getElementById('odrGenerateBtn');
    const icon    = document.getElementById('odrGenerateIcon');
    const spinner = document.getElementById('odrGenerateSpinner');
    const label   = document.getElementById('odrGenerateLabel');

    btn.disabled = true;
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    label.textContent = 'Creating folder…';

    try {
        const res  = await fetch('{{ route('projects.generateFolder', $project->id) }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ folder_name: document.getElementById('odrFolderName').value.trim() }),
        });
        const data = await res.json();

        if (data.success) {
            _showOdrSuccess(data.folder_url);
            showToast('OneDrive folder created successfully!', 'success');
        } else {
            showToast(data.message || 'Failed to create folder.', 'error');
            label.textContent = 'Generate Folder';
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
        label.textContent = 'Generate Folder';
    } finally {
        btn.disabled = false;
        icon.classList.remove('hidden');
        spinner.classList.add('hidden');
    }
}

function copyFolderLink() {
    const val = document.getElementById('odrFolderUrl').value;
    navigator.clipboard.writeText(val).then(() => {
        document.getElementById('odrCopyIcon').classList.add('hidden');
        document.getElementById('odrCopiedIcon').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('odrCopyIcon').classList.remove('hidden');
            document.getElementById('odrCopiedIcon').classList.add('hidden');
        }, 2000);
        showToast('Link copied to clipboard!', 'success');
    });
}

</script>
{{-- Load custom-dd component (sama dengan halaman admin lain). filemtime
     cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection
