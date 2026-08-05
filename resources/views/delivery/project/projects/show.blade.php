@extends('dashboard')
@section('title', 'Project Detail')
@section('page-title', 'Project Detail')
@section('page-subtitle', e($project->name))
@php
    // Project Owner project ini boleh CRUD Risk Register meski role-nya tidak
    // punya slug risk.*. Grant hanya berlaku di project ini (bukan lintas
    // project) — sisi server dijaga middleware `menu.owner:`.
    $isProjectOwner = $isProjectOwner ?? false;
    $canRiskView    = $can('delivery-project.risk.view')   || $isProjectOwner;
    $canRiskEdit    = $can('delivery-project.risk.edit')   || $isProjectOwner;
    $canRiskManage  = $can('delivery-project.risk.manage') || $isProjectOwner;
@endphp
{{-- ✅ LOAD GANTT LIBRARIES --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    /* Flatpickr: tanggal merah + libur Indonesia */
    .flatpickr-day.fp-weekend:not(.flatpickr-disabled) { color: #ef4444; }
    .flatpickr-day.fp-holiday { color: #dc2626 !important; font-weight: 600; }
    .flatpickr-day.flatpickr-disabled.fp-holiday { color: #fca5a5 !important; opacity: 0.6; }

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

    /* Scrollbar untuk risk register table */
    .risk-scroll::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .risk-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    .risk-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    .risk-scroll::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* overflow-x:auto membuat overflow-y ikut jadi 'auto' (spec CSS); underline
       tab aktif (::after bottom:-1px) meluber 1px → memunculkan scrollbar
       vertikal yang tak perlu. Kunci overflow-y agar hanya scroll horizontal. */
    #sectionNav nav {
        overflow-y: hidden;
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

    /* Nilai read-only pada section (editing pindah ke modal). Bentuknya sengaja
       menyerupai input agar tata letak section tidak berubah dari versi form. */
    .display-box {
        display: block; width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background: #f9fafb;
        font-size: 0.875rem;
        color: #111827;
        min-height: 2.625rem;
    }
    .edit-btn:hover { color: var(--primary-color) !important; background-color: rgba(var(--primary-rgb), 0.08) !important; }

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

    /* Read-only saat project di-close: nonaktifkan seluruh kontrol form &
       tombol di dalam section (termasuk yang dirender dinamis oleh JS).
       Link <a> & scroll tetap berfungsi agar konten masih bisa dibaca. */
    body.project-closed section.section-animate :is(input, select, textarea, button) {
        pointer-events: none !important;
        opacity: .55;
        cursor: not-allowed;
    }
</style>
@section('content')
{{-- ✅ SCROLL PROGRESS INDICATOR --}}
<div class="scroll-indicator" id="scrollIndicator"></div>
{{-- ✅ Sticky Navigation Tabs - PALING ATAS --}}
{{-- Tiap tab ikut izin `.view` section tujuannya, supaya tidak ada tab yang
     mengarah ke section yang tidak dirender untuk role tersebut. --}}
<div class="bg-white" id="sectionNav">
    <nav class="flex overflow-x-auto scrollbar-hide border-b border-gray-200">
        @if($can('delivery-project.general.view'))
        <button onclick="scrollToSection('general')" data-section="general" class="section-tab active text-sm font-medium text-gray-600 whitespace-nowrap">
            General
        </button>
        @endif
        {{-- Tab ini menaungi dua section: Delivery Data (di atas) + Delivery
             Information. Klik diarahkan ke section pertama yang boleh dilihat. --}}
        @if($can('delivery-project.delivery-data.view') || $can('delivery-project.delivery-info.view'))
        <button onclick="scrollToSection('{{ $can('delivery-project.delivery-data.view') ? 'delivery-data' : 'delivery' }}')" data-section="delivery" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Delivery Info
        </button>
        @endif
        @if($can('delivery-project.team.view'))
        <button onclick="scrollToSection('team')" data-section="team" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Team
        </button>
        @endif
        @if($can('delivery-project.documents.view'))
        <button onclick="scrollToSection('documents')" data-section="documents" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Documents
        </button>
        @endif
        @if($can('delivery-project.issue-log.view'))
        <button onclick="scrollToSection('issues')" data-section="issues" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Issues
        </button>
        @endif
        @if($canRiskView)
        <button onclick="scrollToSection('risks')" data-section="risks" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            Risks
        </button>
        @endif
        @if($can('delivery-project.location.view'))
        <button onclick="scrollToSection('location')" data-section="location" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Location
        </button>
        @endif
        @if($can('delivery-project.planning.view'))
        <button onclick="scrollToSection('planning')" data-section="planning" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Planning
        </button>
        @endif
        @if($can('delivery-project.wricef.view'))
        <button onclick="scrollToSection('wricef')" data-section="wricef" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
            </svg>
            WRICEF
        </button>
        @endif
        @if($can('delivery-project.plan-cost.view'))
        <button onclick="scrollToSection('plancost')" data-section="plancost" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Plan Cost
        </button>
        @endif
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
            {{-- Status share link OneDrive: siapa yang sebenarnya bisa membuka "Open Folder". --}}
            @if($project->onedrive_folder_url)
            <p id="odrLinkBadgeWrap" class="mt-1.5">
                <span id="odrLinkBadge"
                      class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium
                             {{ $project->onedrive_link_is_public ? 'bg-green-100 text-green-800' : ($project->onedrive_link_warning ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 11-5.656-5.656l1.5-1.5m4.5-4.5l1.5-1.5a4 4 0 115.656 5.656l-3 3a4 4 0 01-5.656 0"/>
                    </svg>
                    <span id="odrLinkBadgeText">Folder link: {{ $project->onedrive_link_scope_label }}</span>
                </span>
            </p>
            @endif
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
            @elseif($can('delivery-project.documents.manage'))
            <button type="button" id="headerFolderBtn" onclick="openOneDriveModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
                Create Folder
            </button>
            @endif
            @if($can('delivery-project.close-project'))
                @if($project->is_closed)
                <form id="reopenProjectForm" method="POST" action="{{ route('projects.reopen', $project->id) }}" class="contents">
                    @csrf
                    <button type="button" onclick="openProjectStateModal('reopen')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                        </svg>
                        Reopen Project
                    </button>
                </form>
                @else
                <form id="closeProjectForm" method="POST" action="{{ route('projects.close', $project->id) }}" class="contents">
                    @csrf
                    <button type="button" onclick="openProjectStateModal('close')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 text-white text-sm font-semibold rounded-lg hover:bg-gray-800 transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Close Project
                    </button>
                </form>
                @endif
            @endif
            @if($can('delivery-project.delete-project'))
            <button type="button"
                    onclick="openDeleteModal('{{ $project->id }}', '{{ addslashes($project->name) }}')"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Delete Project
            </button>
            @endif
        </div>
    </div>
</div>

{{-- Banner peringatan share link OneDrive: muncul saat link tidak benar-benar publik
     (scope internal, kedaluwarsa, atau yang tersimpan bukan share link) — inilah
     kondisi yang membuat penerima kena "Request access". --}}
<div id="odrLinkWarningBanner"
     class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 flex items-start gap-3 {{ $project->onedrive_link_warning ? '' : 'hidden' }}">
    <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
    </svg>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-800">Folder link may not be accessible</p>
        <p id="odrLinkWarningText" class="text-xs text-amber-700 mt-0.5">{{ $project->onedrive_link_warning }}</p>
    </div>
    @if($can('delivery-project.documents.manage'))
    <button type="button" id="odrRefreshLinkBtn" onclick="refreshProjectFolderLink()"
            class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 text-white text-xs font-semibold rounded-lg hover:bg-amber-700 transition-all">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Refresh Link
    </button>
    @endif
</div>

@if($project->is_closed)
{{-- Banner read-only project yang sudah di-close --}}
<div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 flex items-start gap-3">
    <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
    </svg>
    <div>
        <p class="text-sm font-semibold text-amber-800">This project is closed — read-only</p>
        <p class="text-xs text-amber-700 mt-0.5">
            Closed{{ $project->closed_at ? ' on ' . $project->closed_at->format('d M Y, H:i') : '' }}@if($project->closedBy?->basicData?->full_name) by {{ $project->closedBy->basicData->full_name }}@endif.
            @if($can('delivery-project.close-project')) Use <span class="font-semibold">Reopen Project</span> above to make changes. @endif
        </p>
    </div>
</div>
{{-- Kunci tampilan + pagar request: delivery/partials/project-closed-lock.blade.php
     (di-include di akhir file; ia yang memasang class body.project-closed). --}}
@endif

{{-- General Information Section --}}
@if($can('delivery-project.general.view'))
<section id="general" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.general.edit') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">General Information</h2>
            @if($can('delivery-project.general.edit'))
            <button type="button" onclick="openModal('generalInfoModal')" title="Edit General Information"
                    class="p-2 text-gray-400 edit-btn rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            @endif
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Customer</label>
                    <div class="display-box">{{ $project->client->basicData->name_1 ?? '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Project Name</label>
                    <div class="display-box font-semibold">{{ $project->name ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Project Owner</label>
                    <div class="display-box">{{ $project->project_owner ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Project Type</label>
                    <div class="display-box">{{ $project->project_type ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">High Level Risk</label>
                    <div class="display-box">
                        @if($project->high_level_risk)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($project->high_level_risk === 'Low') bg-green-100 text-green-800
                                @elseif($project->high_level_risk === 'Moderate') bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ $project->high_level_risk }}
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">IO/Number Order</label>
                    <div class="display-box">{{ $project->io_number ?: '—' }}</div>
                </div>
                {{-- Category (auto dari Project Planning) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Category</label>
                    <div class="display-box">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($project->category == 'Open') bg-yellow-100 text-yellow-800
                            @elseif($project->category == 'In Process') bg-blue-100 text-blue-800
                            @elseif($project->category == 'Closed') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ $project->category ?? 'N/A' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-amber-600">*Auto-filled from Project Planning</p>
                </div>
                {{-- Phase (auto dari Project Planning) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Phase</label>
                    <div class="display-box">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            {{ $project->phase ?? 'N/A' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-amber-600">*Auto-filled from Project Planning</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Contract Start Date</label>
                    <div class="display-box">{{ $project->contract_start_date ? \Carbon\Carbon::parse($project->contract_start_date)->format('d M Y') : '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Contract End Date</label>
                    <div class="display-box">{{ $project->contract_end_date ? \Carbon\Carbon::parse($project->contract_end_date)->format('d M Y') : '—' }}</div>
                </div>
                {{-- Go Live Estimated (auto dari activity Go-Live) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Go Live Estimated</label>
                    <div class="display-box">{{ $project->go_live_estimated ? \Carbon\Carbon::parse($project->go_live_estimated)->format('d M Y') : 'N/A' }}</div>
                    <p class="mt-1 text-xs text-amber-600">*Derived from the Planned Start Date of the activity marked as 'Go-Live'</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Description</label>
                    <div class="display-box whitespace-pre-line min-h-[5rem]">{{ $project->description ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Delivery Data — sengaja ditempatkan SEBELUM Delivery Information; keduanya
     berbagi tab "Delivery Info" dan izin `edit-delivery-info`. --}}
@if($can('delivery-project.delivery-data.view'))
<section id="delivery-data" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.delivery-data.edit') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-700">Delivery Data</h2>
                <p class="mt-1 text-sm text-gray-600">Delivery method, warranty, and mandays</p>
            </div>
            @if($can('delivery-project.delivery-data.edit'))
            <button type="button" onclick="openModal('deliveryDataModal')" title="Edit Delivery Data"
                    class="p-2 text-gray-400 edit-btn rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            @endif
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Warranty Period <span class="text-gray-400 font-normal">(months)</span></label>
                    <div class="display-box">{{ $project->warranty_period !== null && $project->warranty_period !== '' ? $project->warranty_period . ' months' : '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Delivery Method</label>
                    <div class="display-box">{{ $project->delivery_method ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Total Mandays</label>
                    <div class="display-box">{{ $project->total_mandays !== null && $project->total_mandays !== '' ? $project->total_mandays . ' days' : '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Delivery Information Section --}}
@if($can('delivery-project.delivery-info.view'))
<section id="delivery" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.delivery-info.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-project.delivery-info.manage') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-700">Delivery Information</h2>
                <p class="mt-1 text-sm text-gray-600">Delivery and sales information</p>
            </div>
            @if($can('delivery-project.delivery-info.edit'))
            <button type="button" onclick="openModal('deliveryInfoModal')" title="Edit Delivery Information"
                    class="p-2 text-gray-400 edit-btn rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            @endif
        </div>
        <div class="p-6">
            {{-- Sales Data (read-only; editing lewat modal Edit Delivery Information) --}}
            @php
                $dispRev    = (float) ($project->revenue ?? 0);
                $dispPc     = (float) ($project->plan_cost ?? 0);
                $dispGp     = (float) ($project->gross_profit ?? 0);
                $dispGpPct  = $project->gross_profit_percentage;
                $dispAc     = (float) ($actualCost ?? 0);
                $dispAgp    = $dispRev - $dispAc;
                $dispAgpPct = $dispRev > 0 ? ($dispAgp / $dispRev) * 100 : 0;
                $rp         = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
                $pct        = fn($v) => number_format((float) $v, 2, ',', '.') . '%';
            @endphp
            <h4 class="text-lg font-medium text-gray-900 mb-4">Sales Data</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Account Executive Type</label>
                    <div class="display-box">{{ $project->ae_type ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Account Executive Name</label>
                    <div class="display-box">{{ $project->ae_name ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">AE Phone</label>
                    <div class="display-box">{{ $project->ae_phone ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">AE Email</label>
                    <div class="display-box break-all">{{ $project->ae_email ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Revenue</label>
                    <div class="display-box text-right">{{ $rp($dispRev) }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Plan Cost</label>
                    <div class="display-box text-right">{{ $rp($dispPc) }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Gross Profit</label>
                    <div class="display-box text-right">{{ $rp($dispGp) }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">% Gross Profit</label>
                    <div class="display-box text-right">{{ $dispGpPct === null ? '—' : $pct($dispGpPct) }}</div>
                </div>
                {{-- Baris Actual: lg:col-start-2 agar sejajar di bawah Plan Cost, sama
                     seperti tata letak form lamanya. --}}
                <div class="lg:col-start-2">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Actual Cost</label>
                    <div class="display-box text-right">{{ $rp($dispAc) }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Actual Gross Profit</label>
                    <div class="display-box text-right">{{ $rp($dispAgp) }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">% Actual Gross Profit</label>
                    <div class="display-box text-right">{{ $pct($dispAgpPct) }}</div>
                </div>
            </div>

            {{-- ── Term Of Payment (TOP) Plan ─────────────────────────── --}}
            <div class="mt-8 pt-6 border-t border-gray-200" data-project-id="{{ $project->id }}">
                <div class="flex justify-between items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h4 class="text-lg font-medium text-gray-900">Term Of Payment Plan</h4>
                    </div>
                    <button type="button" onclick="PaymentTermPlan.openAdd()"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Payment Term
                    </button>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm border-collapse" id="paymentTermTable">
                        <thead>
                            <tr class="bg-gray-700 text-white">
                                <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[50px]">No</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[160px]">Payment Term</th>
                                <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[110px]">Payment %</th>
                                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap min-w-[150px]">Amount</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[220px]">Payment Requirements / Evidence</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Estimated Date</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[150px]">Submit Invoice Date</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Invoice No</th>
                                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Paid Date</th>
                                <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[100px]">Status</th>
                                <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[80px]">Action</th>
                            </tr>
                        </thead>
                        <tbody id="paymentTermBody" class="divide-y divide-gray-100 bg-white">
                            <tr>
                                <td colspan="11" class="text-center py-8">
                                    <svg class="animate-spin h-5 w-5 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <p class="text-gray-500 text-xs">Loading payment terms…</p>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                            <tr id="paymentTermFooter" class="font-semibold text-gray-700">
                                <td class="px-3 py-3 text-center" colspan="2">Total</td>
                                <td class="px-3 py-3 text-center" id="ptTotalPct">0%</td>
                                <td class="px-3 py-3 text-right" id="ptTotalAmount">Rp 0</td>
                                <td class="px-3 py-3" colspan="7"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Team Section WITH CHECKBOX SELECTION --}}
@if($can('delivery-project.team.view'))
<section id="team" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.team.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-project.team.manage') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Team Members</h2>
            @if($can('delivery-project.team.manage'))
            <button onclick="openModal('teamModal')" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Add Team Member
            </button>
            @endif
        </div>
        <div class="p-6">
            @php
                // Collect all pivot employee IDs so we know which FK-only fallbacks to show
                $pivotEmpIds = $teamPivotRows->pluck('employee_id')->unique()->toArray();

                // FK-fallback rows: show legacy PM/Co PM/PA FK columns only when the
                // employee has NO pivot entry at all (e.g. project created before pivot flow)
                $fkFallbacks = [];
                foreach ([
                    ['id' => $project->project_manager_id, 'role' => 'Project Manager'],
                    ['id' => $project->co_pm_id,            'role' => 'Co Project Manager'],
                    ['id' => $project->project_admin_id,    'role' => 'Project Admin'],
                ] as $fk) {
                    if ($fk['id'] && !in_array($fk['id'], $pivotEmpIds)) {
                        $fbEmp = $employees->firstWhere('employee_id', $fk['id']);
                        if ($fbEmp) {
                            $fkFallbacks[] = ['emp' => $fbEmp, 'role' => $fk['role']];
                        }
                    }
                }

                $hasAnyTeam = $teamPivotRows->isNotEmpty() || !empty($fkFallbacks);

                // People list shared by the Owner / Originator (Issue Log) and
                // Risk Owner (Risk Register) dropdowns. Mirrors exactly the names
                // shown in the Team Members table (FK-fallback PM/Co PM/Project
                // Admin + every pivot member/lead) plus the project delivery
                // owner & manager.
                $teamPeople = collect();
                if ($project->deliveryOwner && $project->deliveryOwner->basicData) {
                    $teamPeople->push($project->deliveryOwner->basicData->full_name);
                }
                if ($project->deliveryManager && $project->deliveryManager->basicData) {
                    $teamPeople->push($project->deliveryManager->basicData->full_name);
                }
                foreach ($fkFallbacks as $fb) {
                    $teamPeople->push($fb['emp']->basicData->full_name ?? null);
                }
                foreach ($teamPivotRows as $tpRow) {
                    $tpEmp = $employees->firstWhere('employee_id', $tpRow->employee_id);
                    $teamPeople->push($tpEmp?->basicData->full_name);
                }
                $teamPeople = $teamPeople->filter()->unique()->sort()->values();
            @endphp

            {{-- Team Members Table --}}
            <div>
            @if($hasAnyTeam)
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">

                            {{-- ── FK-fallback rows: legacy projects without pivot entries ── --}}
                            @foreach($fkFallbacks as $fb)
                            @php $fbKey = $fb['emp']->employee_id . '::' . $fb['role']; @endphp
                            <tr class="hover:bg-gray-50 team-row" data-member-id="{{ $fb['emp']->employee_id }}">
                                <td class="px-6 py-4">
                                    <input type="checkbox" class="row-checkbox team-checkbox"
                                           data-id="{{ $fbKey }}"
                                           data-employee-id="{{ $fb['emp']->employee_id }}"
                                           data-name="{{ $fb['emp']->basicData->full_name ?? '-' }}"
                                           data-position="{{ $fb['emp']->basicData->position ?? '-' }}"
                                           data-module=""
                                           data-role="{{ $fb['role'] }}"
                                           data-employee-type="Internal"
                                           data-vendor-name=""
                                           data-start-date=""
                                           data-end-date=""
                                           data-notes=""
                                           data-employee-name="{{ $fb['emp']->basicData->full_name ?? '-' }}"
                                           onchange="handleRowSelection('team')">
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $fb['emp']->basicData->full_name ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $fb['emp']->basicData->position ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">—</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $fb['role'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">—</td>
                                <td class="px-6 py-4 text-sm text-gray-500">—</td>
                                <td class="px-6 py-4 text-sm text-gray-500">—</td>
                            </tr>
                            @endforeach

                            {{-- ── All pivot rows (one table row per pivot entry) ── --}}
                            @foreach($teamPivotRows as $row)
                            @php
                                $rEmp   = $employees->firstWhere('employee_id', $row->employee_id);
                                $rowKey = $row->employee_id . '::' . $row->role;
                            @endphp
                            <tr class="hover:bg-gray-50 team-row" data-member-id="{{ $row->employee_id }}">
                                <td class="px-6 py-4">
                                    <input type="checkbox" class="row-checkbox team-checkbox"
                                           data-id="{{ $rowKey }}"
                                           data-employee-id="{{ $row->employee_id }}"
                                           data-name="{{ $rEmp?->basicData->full_name ?? '-' }}"
                                           data-position="{{ $rEmp?->basicData->position ?? '-' }}"
                                           data-module="{{ $row->module ?? '' }}"
                                           data-role="{{ $row->role ?? '' }}"
                                           data-employee-type="{{ $row->employee_type ?? 'Internal' }}"
                                           data-vendor-name="{{ $row->vendor_name ?? '' }}"
                                           data-start-date="{{ $row->start_date ?? '' }}"
                                           data-end-date="{{ $row->end_date ?? '' }}"
                                           data-notes="{{ $row->notes ?? '' }}"
                                           data-employee-name="{{ $rEmp?->basicData->full_name ?? '-' }}"
                                           onchange="handleRowSelection('team')">
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $rEmp?->basicData->full_name ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $rEmp?->basicData->position ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $row->module ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $row->role ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $row->employee_type ?? '—' }}
                                    @if(($row->employee_type ?? '') === 'Vendor' && $row->vendor_name)
                                        <span class="text-gray-400">({{ $row->vendor_name }})</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $row->start_date ? \Carbon\Carbon::parse($row->start_date)->format('d M Y') : '—' }}
                                    –
                                    {{ $row->end_date ? \Carbon\Carbon::parse($row->end_date)->format('d M Y') : 'Present' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs">
                                    @if($row->notes)
                                        <span class="block truncate" title="{{ $row->notes }}">{{ $row->notes }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-8">No team members added yet.</p>
            @endif
            </div>{{-- /Team Members Table --}}
        </div>
    </div>
</section>
@endif

{{-- Documents Section WITH CHECKBOX SELECTION --}}
@if($can('delivery-project.documents.view'))
<section id="documents" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.documents.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-project.documents.manage') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-700">Project Documents</h2>
                @if(!$project->onedrive_folder_id)
                    <p class="text-xs text-amber-600 mt-0.5">Please create an OneDrive folder before uploading documents.</p>
                @endif
            </div>
            @if($can('delivery-project.documents.manage'))
            <button onclick="openUploadDocumentModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload Document
            </button>
            @endif
        </div>
        <div class="p-6">
            {{-- Empty state: shown when no documents --}}
            <div class="text-center py-12 {{ $project->documents->isNotEmpty() ? 'hidden' : '' }}" id="documentsEmptyState">
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm text-gray-500">No documents have been uploaded yet.</p>
            </div>
            {{-- Table: always rendered, hidden when no documents --}}
            <div class="overflow-x-auto {{ $project->documents->isEmpty() ? 'hidden' : '' }}" id="documentsTableWrap">
                <table class="min-w-full divide-y divide-gray-200" id="documentsTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left">
                                <input type="checkbox" id="selectAllDocuments" class="row-checkbox" onchange="toggleSelectAll('document')">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="documentsTableBody">
                        @foreach($project->documents as $document)
                            <tr class="hover:bg-gray-50 document-row" data-document-id="{{ $document->id }}">
                                <td class="px-6 py-4">
                                    <input type="checkbox" class="row-checkbox document-checkbox"
                                           data-id="{{ $document->id }}"
                                           data-name="{{ $document->document_name }}"
                                           data-type="{{ $document->document_type }}"
                                           onchange="handleRowSelection('document')">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="text-sm font-medium text-gray-900">{{ $document->document_name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $document->document_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($document->link_document)
                                        <a href="{{ $document->link_document }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                            Open File
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Issues Section WITH CHECKBOX SELECTION --}}
@if($can('delivery-project.issue-log.view'))
<section id="issues" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.issue-log.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-project.issue-log.manage') ? '1' : '0' }}" data-project-id="{{ $project->id }}">
    <div class="bg-white shadow-md rounded-lg">

        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Project Issues
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">Project Issue Log</p>
                </div>
                @if($can('delivery-project.issue-log.manage'))
                <button type="button" onclick="IssueLog.openAdd()"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Issue
                </button>
                @endif
            </div>
        </div>

        {{-- ── Table ───────────────────────────────────────────────── --}}
        <div class="p-6">
            <div class="overflow-x-auto overflow-y-auto max-h-[520px] rounded-lg border border-gray-200 risk-scroll">
                <table class="min-w-full text-sm border-collapse" id="issueLogTable">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-700 text-white">
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[80px]">ID</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[220px]">Issue Description</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[90px]">Module</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">Date Identified</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Closed Date</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[90px]">Status</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[160px]">Risk To Project</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Project Risk ID</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[90px]">Priority</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">Originator</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">Owner</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Estimated Closed</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[100px]">Escalation</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[160px]">Impact of Issue</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[180px]">Tracking Comments</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[80px]">Action</th>
                        </tr>
                    </thead>
                    <tbody id="issueTableBody" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="16" class="text-center py-10">
                                <svg class="animate-spin h-6 w-6 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-gray-500 text-xs">Loading issues…</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 mt-2">Priority: H = High &nbsp;|&nbsp; M = Medium &nbsp;|&nbsp; L = Low &nbsp;&nbsp;·&nbsp;&nbsp; Escalation Needed: Y / N</p>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PROJECT RISK REGISTER SECTION                                 --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
@if($canRiskView)
<section id="risks" class="mb-6 card-hover section-animate" data-perm-edit="{{ $canRiskEdit ? '1' : '0' }}" data-perm-manage="{{ $canRiskManage ? '1' : '0' }}" data-project-id="{{ $project->id }}">
    <div class="bg-white shadow-md rounded-lg">

        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        Project Risk Register
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">Integrated Project Risk Management Register</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="RiskRegister.openDashboard()"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Risk Dashboard
                    </button>
                    @if($canRiskManage)
                    <button type="button" onclick="RiskRegister.openAdd()"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Risk
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Table ───────────────────────────────────────────────── --}}
        <div class="p-6">
            <div class="overflow-x-auto overflow-y-auto max-h-[520px] rounded-lg border border-gray-200 risk-scroll">
                <table class="min-w-full text-sm border-collapse" id="riskRegisterTable">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-700 text-white">
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[80px]">Risk ID</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[110px]">Risk Type</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">Risk Category</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[200px]">Risk Description</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[160px]">Cause (Trigger)</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[160px]">Project Impact</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[70px]">P (1-5)</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[70px]">I (1-5)</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[70px]">Score</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[90px]">Risk Level</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Response Strategy</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[180px]">Mitigation / Contingency Plan</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Risk Owner</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[100px]">Status</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Target Date</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">Actual End Date</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[140px]">Comments / Notes</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[80px]">Action</th>
                        </tr>
                    </thead>
                    <tbody id="riskTableBody" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="18" class="text-center py-10">
                                <svg class="animate-spin h-6 w-6 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-gray-500 text-xs">Loading risks…</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 mt-2">P = Probability &nbsp;|&nbsp; I = Impact &nbsp;|&nbsp; Score = P × I &nbsp;|&nbsp; High ≥ 12 &nbsp;|&nbsp; Medium 5–11 &nbsp;|&nbsp; Low ≤ 4</p>
        </div>
    </div>
</section>
@endif

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
        {{-- Delete: disembunyikan untuk Team Member (hanya tampil untuk document/issue) --}}
        <button id="toolbarDeleteBtn" onclick="handleBulkDelete()" class="flex items-center space-x-2 px-4 py-2 bg-red-500 hover:bg-red-400 rounded-md transition">
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
@if($can('delivery-project.location.view'))
<section id="location" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.location.edit') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Location Information</h2>
            @if($can('delivery-project.location.edit'))
            <button type="button" onclick="openModal('locationInfoModal')" title="Edit Location Information"
                    class="p-2 text-gray-400 edit-btn rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            @endif
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Location Name</label>
                    <div class="display-box">{{ $project->location_name ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Type of Address</label>
                    <div class="display-box">{{ $project->location_type ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Country</label>
                    <div class="display-box">{{ $project->location_country ?: 'Indonesia' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Geographical</label>
                    <div class="display-box">{{ $project->location_geographical ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Region / Province</label>
                    <div class="display-box">{{ $project->location_region ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">City</label>
                    <div class="display-box">{{ $project->location_city ?: '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Valid From</label>
                    <div class="display-box">{{ $project->location_valid_from ? \Carbon\Carbon::parse($project->location_valid_from)->format('d M Y') : '—' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Valid To</label>
                    <div class="display-box">{{ $project->location_valid_to ? \Carbon\Carbon::parse($project->location_valid_to)->format('d M Y') : '—' }}</div>
                </div>
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 mb-1">Street Address</label>
                    <div class="display-box whitespace-pre-line">{{ $project->location_street ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Location Information — cascading Geographical → Region → City dropdowns.
     Sumber data & pola identik dengan form create (projects/create.blade.php)
     supaya tampilan & perilaku seragam. --}}
<script>
(function () {
    const locRegions = {
        'Jawa': ['DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Banten'],
        'Sumatera': ['Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi', 'Sumatera Selatan', 'Bengkulu', 'Lampung', 'Kepulauan Bangka Belitung'],
        'Bali & N.Tenggara': ['Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur'],
        'Kalimantan': ['Kalimantan Barat', 'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara'],
        'Sulawesi': ['Sulawesi Utara', 'Sulawesi Tengah', 'Sulawesi Selatan', 'Sulawesi Tenggara', 'Gorontalo', 'Sulawesi Barat'],
        'Maluku': ['Maluku', 'Maluku Utara'],
        'Papua': ['Papua', 'Papua Barat', 'Papua Selatan', 'Papua Tengah', 'Papua Pegunungan', 'Papua Barat Daya']
    };

    const locCities = {
        'DKI Jakarta': ['Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur', 'Kepulauan Seribu'],
        'Banten': ['Serang', 'Tangerang', 'Tangerang Selatan', 'Cilegon', 'Pandeglang', 'Lebak'],
        'Jawa Barat': ['Bandung', 'Bekasi', 'Bogor', 'Cirebon', 'Depok', 'Sukabumi', 'Tasikmalaya', 'Banjar', 'Cimahi', 'Garut', 'Indramayu', 'Karawang', 'Kuningan', 'Majalengka', 'Purwakarta', 'Subang', 'Sumedang', 'Ciamis', 'Cianjur', 'Pangandaran'],
        'Jawa Tengah': ['Semarang', 'Solo', 'Magelang', 'Salatiga', 'Pekalongan', 'Tegal', 'Banyumas', 'Cilacap', 'Purbalingga', 'Banjarnegara', 'Kebumen', 'Purworejo', 'Wonosobo', 'Klaten', 'Boyolali', 'Sukoharjo', 'Wonogiri', 'Karanganyar', 'Sragen', 'Grobogan', 'Blora', 'Rembang', 'Pati', 'Kudus', 'Jepara', 'Demak', 'Kendal', 'Temanggung', 'Batang', 'Pemalang', 'Brebes'],
        'Jawa Timur': ['Surabaya', 'Malang', 'Sidoarjo', 'Gresik', 'Mojokerto', 'Kediri', 'Jember', 'Batu', 'Blitar', 'Madiun', 'Pasuruan', 'Probolinggo', 'Bangkalan', 'Banyuwangi', 'Bojonegoro', 'Bondowoso', 'Jombang', 'Lamongan', 'Lumajang', 'Magetan', 'Nganjuk', 'Ngawi', 'Pacitan', 'Pamekasan', 'Ponorogo', 'Sampang', 'Situbondo', 'Sumenep', 'Trenggalek', 'Tuban', 'Tulungagung'],
        'DI Yogyakarta': ['Yogyakarta', 'Bantul', 'Sleman', 'Gunungkidul', 'Kulon Progo'],
        'Aceh': ['Banda Aceh', 'Sabang', 'Langsa', 'Lhokseumawe', 'Subulussalam', 'Aceh Besar', 'Aceh Jaya', 'Aceh Selatan', 'Aceh Singkil', 'Aceh Tengah', 'Aceh Tenggara', 'Aceh Timur', 'Aceh Utara', 'Bener Meriah', 'Bireuen', 'Gayo Lues', 'Nagan Raya', 'Pidie', 'Pidie Jaya', 'Simeulue'],
        'Sumatera Utara': ['Medan', 'Binjai', 'Pematangsiantar', 'Tanjungbalai', 'Tebing Tinggi', 'Padang Sidempuan', 'Gunungsitoli', 'Sibolga', 'Asahan', 'Batubara', 'Dairi', 'Deli Serdang', 'Humbang Hasundutan', 'Karo', 'Labuhanbatu', 'Labuhanbatu Selatan', 'Labuhanbatu Utara', 'Langkat', 'Mandailing Natal', 'Nias', 'Nias Barat', 'Nias Selatan', 'Nias Utara', 'Padang Lawas', 'Padang Lawas Utara', 'Pakpak Bharat', 'Samosir', 'Serdang Bedagai', 'Simalungun', 'Tapanuli Selatan', 'Tapanuli Tengah', 'Tapanuli Utara', 'Toba Samosir'],
        'Sumatera Barat': ['Padang', 'Bukittinggi', 'Padang Panjang', 'Pariaman', 'Payakumbuh', 'Sawahlunto', 'Solok', 'Agam', 'Dharmasraya', 'Kepulauan Mentawai', 'Lima Puluh Kota', 'Padang Pariaman', 'Pasaman', 'Pasaman Barat', 'Pesisir Selatan', 'Sijunjung', 'Solok Selatan', 'Tanah Datar'],
        'Riau': ['Pekanbaru', 'Dumai', 'Bengkalis', 'Indragiri Hilir', 'Indragiri Hulu', 'Kampar', 'Kepulauan Meranti', 'Kuantan Singingi', 'Pelalawan', 'Rokan Hilir', 'Rokan Hulu', 'Siak'],
        'Kepulauan Riau': ['Batam', 'Tanjung Pinang', 'Bintan', 'Karimun', 'Kepulauan Anambas', 'Lingga', 'Natuna'],
        'Jambi': ['Jambi', 'Sungai Penuh', 'Batang Hari', 'Bungo', 'Kerinci', 'Merangin', 'Muaro Jambi', 'Sarolangun', 'Tanjung Jabung Barat', 'Tanjung Jabung Timur', 'Tebo'],
        'Sumatera Selatan': ['Palembang', 'Lubuklinggau', 'Pagar Alam', 'Prabumulih', 'Banyuasin', 'Empat Lawang', 'Lahat', 'Muara Enim', 'Musi Banyuasin', 'Musi Rawas', 'Musi Rawas Utara', 'Ogan Ilir', 'Ogan Komering Ilir', 'Ogan Komering Ulu', 'Ogan Komering Ulu Selatan', 'Ogan Komering Ulu Timur', 'Penukal Abab Lematang Ilir'],
        'Bengkulu': ['Bengkulu', 'Bengkulu Selatan', 'Bengkulu Tengah', 'Bengkulu Utara', 'Kaur', 'Kepahiang', 'Lebong', 'Mukomuko', 'Rejang Lebong', 'Seluma'],
        'Lampung': ['Bandar Lampung', 'Metro', 'Lampung Barat', 'Lampung Selatan', 'Lampung Tengah', 'Lampung Timur', 'Lampung Utara', 'Mesuji', 'Pesawaran', 'Pesisir Barat', 'Pringsewu', 'Tanggamus', 'Tulang Bawang', 'Tulang Bawang Barat', 'Way Kanan'],
        'Kepulauan Bangka Belitung': ['Pangkal Pinang', 'Bangka', 'Bangka Barat', 'Bangka Selatan', 'Bangka Tengah', 'Belitung', 'Belitung Timur'],
        'Bali': ['Denpasar', 'Badung', 'Bangli', 'Buleleng', 'Gianyar', 'Jembrana', 'Karangasem', 'Klungkung', 'Tabanan'],
        'Nusa Tenggara Barat': ['Mataram', 'Bima', 'Dompu', 'Lombok Barat', 'Lombok Tengah', 'Lombok Timur', 'Lombok Utara', 'Sumbawa', 'Sumbawa Barat'],
        'Nusa Tenggara Timur': ['Kupang', 'Alor', 'Belu', 'Ende', 'Flores Timur', 'Lembata', 'Manggarai', 'Manggarai Barat', 'Manggarai Timur', 'Nagekeo', 'Ngada', 'Rote Ndao', 'Sabu Raijua', 'Sikka', 'Sumba Barat', 'Sumba Barat Daya', 'Sumba Tengah', 'Sumba Timur', 'Timor Tengah Selatan', 'Timor Tengah Utara'],
        'Kalimantan Barat': ['Pontianak', 'Singkawang', 'Bengkayang', 'Kapuas Hulu', 'Kayong Utara', 'Ketapang', 'Kubu Raya', 'Landak', 'Melawi', 'Mempawah', 'Sambas', 'Sanggau', 'Sekadau', 'Sintang'],
        'Kalimantan Tengah': ['Palangka Raya', 'Barito Selatan', 'Barito Timur', 'Barito Utara', 'Gunung Mas', 'Kapuas', 'Katingan', 'Kotawaringin Barat', 'Kotawaringin Timur', 'Lamandau', 'Murung Raya', 'Pulang Pisau', 'Seruyan', 'Sukamara'],
        'Kalimantan Selatan': ['Banjarmasin', 'Banjarbaru', 'Balangan', 'Banjar', 'Barito Kuala', 'Hulu Sungai Selatan', 'Hulu Sungai Tengah', 'Hulu Sungai Utara', 'Kotabaru', 'Tabalong', 'Tanah Bumbu', 'Tanah Laut', 'Tapin'],
        'Kalimantan Timur': ['Balikpapan', 'Bontang', 'Samarinda', 'Berau', 'Kutai Barat', 'Kutai Kartanegara', 'Kutai Timur', 'Mahakam Ulu', 'Paser', 'Penajam Paser Utara'],
        'Kalimantan Utara': ['Tarakan', 'Bulungan', 'Malinau', 'Nunukan', 'Tana Tidung'],
        'Sulawesi Utara': ['Manado', 'Bitung', 'Kotamobagu', 'Tomohon', 'Bolaang Mongondow', 'Bolaang Mongondow Selatan', 'Bolaang Mongondow Timur', 'Bolaang Mongondow Utara', 'Kepulauan Sangihe', 'Kepulauan Siau Tagulandang Biaro', 'Kepulauan Talaud', 'Minahasa', 'Minahasa Selatan', 'Minahasa Tenggara', 'Minahasa Utara'],
        'Sulawesi Tengah': ['Palu', 'Banggai', 'Banggai Kepulauan', 'Banggai Laut', 'Buol', 'Donggala', 'Morowali', 'Morowali Utara', 'Parigi Moutong', 'Poso', 'Sigi', 'Tojo Una-Una', 'Toli-Toli'],
        'Sulawesi Selatan': ['Makassar', 'Palopo', 'Parepare', 'Bantaeng', 'Barru', 'Bone', 'Bulukumba', 'Enrekang', 'Gowa', 'Jeneponto', 'Kepulauan Selayar', 'Luwu', 'Luwu Timur', 'Luwu Utara', 'Maros', 'Pangkajene dan Kepulauan', 'Pinrang', 'Sidenreng Rappang', 'Sinjai', 'Soppeng', 'Takalar', 'Tana Toraja', 'Toraja Utara', 'Wajo'],
        'Sulawesi Tenggara': ['Kendari', 'Baubau', 'Bombana', 'Buton', 'Buton Selatan', 'Buton Tengah', 'Buton Utara', 'Kolaka', 'Kolaka Timur', 'Kolaka Utara', 'Konawe', 'Konawe Kepulauan', 'Konawe Selatan', 'Konawe Utara', 'Muna', 'Muna Barat', 'Wakatobi'],
        'Gorontalo': ['Gorontalo', 'Boalemo', 'Bone Bolango', 'Gorontalo Utara', 'Pohuwato'],
        'Sulawesi Barat': ['Mamuju', 'Majene', 'Mamasa', 'Mamuju Tengah', 'Mamuju Utara', 'Polewali Mandar'],
        'Maluku': ['Ambon', 'Tual', 'Buru', 'Buru Selatan', 'Kepulauan Aru', 'Maluku Barat Daya', 'Maluku Tengah', 'Maluku Tenggara', 'Maluku Tenggara Barat', 'Seram Bagian Barat', 'Seram Bagian Timur'],
        'Maluku Utara': ['Ternate', 'Tidore Kepulauan', 'Halmahera Barat', 'Halmahera Selatan', 'Halmahera Tengah', 'Halmahera Timur', 'Halmahera Utara', 'Kepulauan Sula', 'Pulau Morotai', 'Pulau Taliabu'],
        'Papua': ['Jayapura', 'Biak Numfor', 'Keerom', 'Kepulauan Yapen', 'Mamberamo Raya', 'Sarmi', 'Supiori', 'Waropen'],
        'Papua Barat': ['Manokwari', 'Fakfak', 'Kaimana', 'Manokwari Selatan', 'Pegunungan Arfak', 'Teluk Bintuni', 'Teluk Wondama'],
        'Papua Selatan': ['Merauke', 'Asmat', 'Boven Digoel', 'Mappi'],
        'Papua Tengah': ['Nabire', 'Mimika', 'Paniai', 'Puncak Jaya', 'Puncak', 'Dogiyai', 'Intan Jaya', 'Deiyai'],
        'Papua Pegunungan': ['Jayawijaya', 'Lanny Jaya', 'Tolikara', 'Mamberamo Tengah', 'Yalimo', 'Nduga', 'Pegunungan Bintang', 'Yahukimo'],
        'Papua Barat Daya': ['Sorong', 'Sorong Selatan', 'Raja Ampat', 'Maybrat', 'Tambrauw']
    };

    function geoEl()    { return document.getElementById('loc_geographical'); }
    function regionEl() { return document.getElementById('loc_region'); }
    function cityEl()   { return document.getElementById('loc_city'); }

    // Build region options from selected geographical. preserve = nilai region
    // tersimpan yang ingin dipertahankan (saat init), kosongkan saat user ganti geo.
    function buildRegions(preserve) {
        const region = regionEl();
        if (!region) return;
        const selected = preserve ?? '';
        region.innerHTML = '<option value="">-- Select Region --</option>';
        if (cityEl()) cityEl().innerHTML = '<option value="">-- Select City --</option>';

        const geo = geoEl() ? geoEl().value : '';
        if (geo && locRegions[geo]) {
            locRegions[geo].forEach(function (r) {
                const opt = document.createElement('option');
                opt.value = r;
                opt.textContent = r;
                if (selected === r) opt.selected = true;
                region.appendChild(opt);
            });
        }
    }

    function buildCities(preserve) {
        const city = cityEl();
        if (!city) return;
        const selected = preserve ?? '';
        city.innerHTML = '<option value="">-- Select City --</option>';

        const region = regionEl() ? regionEl().value : '';
        if (region && locCities[region]) {
            locCities[region].forEach(function (c) {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                if (selected === c) opt.selected = true;
                city.appendChild(opt);
            });
        }
    }

    // Dipanggil custom-dropdown.js via data-onchange saat Geographical berubah.
    window.locUpdateRegions = function () {
        buildRegions('');   // user ganti geo → reset region & city
    };

    // Dipanggil native <select> onchange saat Region berubah.
    window.locUpdateCities = function () {
        buildCities('');
    };

    // Init: populate dropdown dari nilai tersimpan project.
    document.addEventListener('DOMContentLoaded', function () {
        const savedRegion = regionEl() ? (regionEl().dataset.selected || '') : '';
        const savedCity   = cityEl()   ? (cityEl().dataset.selected   || '') : '';
        buildRegions(savedRegion);
        buildCities(savedCity);
    });
})();
</script>

{{-- ✅✅✅ PROJECT PLANNING SECTION (INTEGRATED) ✅✅✅ --}}
@if($can('delivery-project.planning.view'))
<section id="planning" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.planning.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-project.planning.manage') ? '1' : '0' }}" data-project-id="{{ $project->id }}">
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
@endif

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- WRICEF LOG SECTION                                            --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
@if($can('delivery-project.wricef.view'))
<section id="wricef" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.wricef.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-project.wricef.manage') ? '1' : '0' }}" data-project-id="{{ $project->id }}">
    <div class="bg-white shadow-md rounded-lg">

        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                        WRICEF Log
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">Workflow, Report, Interface, Conversion, Enhancement &amp; Form objects — FSD → Development → Testing</p>
                </div>
                @if($can('delivery-project.wricef.manage'))
                <button type="button" onclick="WricefLog.openAdd()"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add WRICEF
                </button>
                @endif
            </div>
        </div>

        {{-- ── Table ───────────────────────────────────────────────── --}}
        {{-- Header dua baris: baris pertama mengelompokkan tahapan
             (FSD / Development / Testing) persis seperti WRICEF Log sheet. --}}
        <div class="p-6">
            <div class="overflow-x-auto overflow-y-auto max-h-[560px] rounded-lg border border-gray-200 risk-scroll">
                <table class="min-w-full text-sm border-collapse" id="wricefTable">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-800 text-white">
                            <th colspan="14" class="px-3 py-2 text-left font-semibold whitespace-nowrap border-r border-gray-600">WRICEF Log</th>
                            <th colspan="5" class="px-3 py-2 text-center font-semibold whitespace-nowrap border-r border-gray-600">FSD</th>
                            <th colspan="5" class="px-3 py-2 text-center font-semibold whitespace-nowrap border-r border-gray-600">Development</th>
                            <th colspan="5" class="px-3 py-2 text-center font-semibold whitespace-nowrap border-r border-gray-600">Testing</th>
                            <th rowspan="2" class="px-3 py-2 text-center font-semibold whitespace-nowrap w-[80px] bg-gray-700">Action</th>
                        </tr>
                        <tr class="bg-gray-700 text-white">
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[140px]">Company</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[90px]">SAP Module</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Category</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[90px]">Obj ID</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[200px]">Obj Name</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[200px]">Capability</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[90px]">TCode</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[80px]">Priority</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">Requestor</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Request Date</th>
                            <th class="px-3 py-3 text-right font-semibold whitespace-nowrap min-w-[110px]">Effort (Mandays)</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[130px]">Approved By</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[110px]">Approved Date</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[110px] border-r border-gray-600">Status</th>

                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">PIC</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[100px]">Start</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[100px]">End</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[100px]">Status</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[150px] border-r border-gray-600">Remarks</th>

                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">PIC</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[100px]">Start</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[100px]">End</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[130px]">Status</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[150px] border-r border-gray-600">Remarks</th>

                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">PIC</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[100px]">Start</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[100px]">End</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap w-[100px]">Status</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap min-w-[150px] border-r border-gray-600">Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="wricefTableBody" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="30" class="text-center py-10">
                                <svg class="animate-spin h-6 w-6 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-gray-500 text-xs">Loading WRICEF log…</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 mt-2">Obj ID dibuat otomatis dari SAP Module + Category (contoh: MM + Report → MMR001). Company mengikuti customer project.</p>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST SECTION                                             --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
@if($can('delivery-project.plan-cost.view'))
<section id="plancost" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-project.plan-cost.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-project.plan-cost.manage') ? '1' : '0' }}" data-project-id="{{ $project->id }}">
    <div class="bg-white shadow-md rounded-lg">

        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Plan Cost
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">Project cost recapitulation: Indirect Cost &amp; Direct Cost</p>
                </div>
                @if($can('delivery-project.plan-cost.manage'))
                <button type="button" onclick="PlanCost.openAddParentModal()"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Cost Item
                </button>
                @endif
            </div>
        </div>

        {{-- ── Summary Cards ────────────────────────────────────────── --}}
        <div id="planCostSummaryCards" class="px-6 pt-5 pb-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            {{-- rendered by JS --}}
            <div class="col-span-full text-center py-4">
                <svg class="animate-spin h-6 w-6 primary-text mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>
        </div>

        {{-- ── Recapitulation Table ─────────────────────────────────── --}}
        <div class="px-6 pb-6">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-sm" id="planCostTable">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="px-4 py-3 text-left font-semibold w-16">Code</th>
                            <th class="px-4 py-3 text-left font-semibold">Item Name</th>
                            <th class="px-4 py-3 text-right font-semibold w-36">Budget</th>
                            <th class="px-4 py-3 text-right font-semibold w-36 bg-blue-700">Release</th>
                            <th class="px-4 py-3 text-right font-semibold w-36 bg-orange-600">Actual</th>
                            <th class="px-4 py-3 text-right font-semibold w-36 bg-green-700">Avail. Budget</th>
                            <th class="px-4 py-3 text-right font-semibold w-36 bg-teal-700">Avail. Release</th>
                            <th class="px-4 py-3 text-center font-semibold w-28">Action</th>
                        </tr>
                    </thead>
                    <tbody id="planCostTableBody" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="8" class="text-center py-10">
                                <svg class="animate-spin h-7 w-7 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-gray-500 text-xs">Loading cost data…</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                * Avail. Budget = Budget &minus; Release &nbsp;|&nbsp; Avail. Release = Release &minus; Actual
            </p>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST — MODALS                                            --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

{{-- Add / Edit Cost Modal --}}
<div id="costModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="PlanCost.closeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-lg">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900" id="costModalTitle">Add Cost Item</h3>
                <button type="button" onclick="PlanCost.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-4">

                {{-- Hidden fields --}}
                <input type="hidden" id="costModalMode"     value="create"> {{-- create | edit --}}
                <input type="hidden" id="costModalId"       value="">
                <input type="hidden" id="costModalParentId" value="">

                {{-- Type (hidden if child) --}}
                <div id="costTypeRow">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cost Type <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="costTypeRadio" id="costTypeIndirect" value="indirect"
                                   class="w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500"
                                   onchange="PlanCost.onTypeChange('indirect')">
                            <span class="text-sm text-gray-700 font-medium">Indirect Cost</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="costTypeRadio" id="costTypeDirect" value="direct" checked
                                   class="w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500"
                                   onchange="PlanCost.onTypeChange('direct')">
                            <span class="text-sm text-gray-700 font-medium">Direct Cost</span>
                        </label>
                    </div>
                </div>

                {{-- Code --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-xs text-gray-400">(optional, e.g. 210)</span></label>
                    <input type="text" id="costCodeInput" maxlength="20"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. 210, 220, 230 …">
                </div>

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Name <span class="text-red-500">*</span></label>
                    <input type="text" id="costNameInput" maxlength="200"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. PROJECT ALLOWANCE, TRAVELING …">
                </div>

                {{-- Banner: parent-with-children (amount tidak bisa diedit langsung) --}}
                <div id="costAggregateNotice" class="hidden rounded-lg bg-amber-50 border border-amber-200 p-3">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-xs text-amber-700 leading-relaxed">
                            <span class="font-semibold">Budget, Release, and Actual</span> for this item are automatically calculated from its sub-items.<br>
                            To change the values, edit each sub-item listed below.
                        </p>
                    </div>
                </div>

                {{-- Budget / Release grid (disembunyikan untuk parent-with-children) --}}
                {{-- Actual TIDAK diinput di sini — nilainya otomatis dari total expense detail --}}
                <div id="costAmountsSection">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Budget (Rp)</label>
                            <input type="text" id="costBudgetInput" inputmode="numeric"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus text-right"
                                   placeholder="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center gap-1">
                                Release (Rp)
                                <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                            </label>
                            <input type="text" id="costReleaseInput" inputmode="numeric"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus text-right"
                                   placeholder="0">
                        </div>
                    </div>

                    {{-- Info: Actual otomatis dari expense detail --}}
                    <div class="mt-3 flex items-start gap-2 text-xs text-gray-500">
                        <span class="inline-block w-3 h-3 rounded-full bg-orange-500 mt-0.5 flex-shrink-0"></span>
                        <span>
                            <span class="font-medium text-orange-700">Actual</span> is calculated automatically
                            from the total of the expense details. Click the
                            <span class="font-medium">Actual</span> column on the table to add or view expenses.
                        </span>
                    </div>

                    {{-- Live preview computed values --}}
                    <div class="mt-3 bg-gray-50 rounded-lg p-3 text-xs text-gray-600 grid grid-cols-2 gap-2">
                        <div>
                            <span class="font-medium text-green-700">Avail. Budget</span> (Budget − Release):
                            <span id="previewAvailBudget" class="font-semibold ml-1 text-green-700">—</span>
                        </div>
                        <div>
                            <span class="font-medium text-teal-700">Avail. Release</span> (Release − Actual):
                            <span id="previewAvailRelease" class="font-semibold ml-1 text-teal-700">—</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="PlanCost.closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="costModalSaveBtn" onclick="PlanCost.save()"
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Cost Confirm Modal --}}
<div id="costDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="PlanCost.closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Cost Item?</h3>
                <p class="text-sm text-gray-500 mb-1">Item "<span id="costDeleteName" class="font-medium text-gray-700"></span>" will be deleted.</p>
                <p class="text-xs text-red-500 mb-5">If this item has sub-items, all sub-items will also be deleted.</p>
                <input type="hidden" id="costDeleteId" value="">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="PlanCost.closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" onclick="PlanCost.confirmDelete()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Delete Expense Confirm Modal (pengganti native confirm()) --}}
<div id="expenseDeleteModal" class="fixed inset-0 z-[55] hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="PlanCost.closeExpenseDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Expense?</h3>
                <p class="text-sm text-gray-500 mb-1">Expense "<span id="expenseDeleteName" class="font-medium text-gray-700"></span>" will be deleted.</p>
                <p class="text-xs text-red-500 mb-5">The actual amount will be recalculated automatically.</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="PlanCost.closeExpenseDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="expenseDeleteConfirmBtn" onclick="PlanCost.confirmDeleteExpense()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Expense Modal --}}
<div id="expenseEditModal" class="fixed inset-0 z-[55] hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="PlanCost.closeExpenseEditModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col" style="max-height:90vh;">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900">Edit Expense</h3>
                <button type="button" onclick="PlanCost.closeExpenseEditModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="overflow-y-auto flex-1 p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expense Name <span class="text-red-500">*</span></label>
                    <input type="text" id="aeDescInput" maxlength="200"
                           class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                           placeholder="e.g. Transportation, Accommodation…">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rp) <span class="text-red-500">*</span></label>
                    <input type="text" id="aeAmountInput" inputmode="numeric"
                           class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                           placeholder="0">
                </div>

                {{-- Current document (shown only when one exists) --}}
                <div id="aeCurrentDocRow" class="hidden">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Current Document</label>
                    <div class="flex items-center justify-between gap-2 border border-gray-200 rounded-lg px-3 py-2 bg-gray-50">
                        <a id="aeCurrentDocLink" href="#" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline truncate">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            <span id="aeCurrentDocName" class="truncate">View</span>
                        </a>
                        <button type="button" onclick="PlanCost.removeEditDoc()"
                                class="text-xs text-red-500 hover:text-red-700 font-medium flex-shrink-0">Remove</button>
                    </div>
                </div>

                {{-- Replace / add document --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        <span id="aeDropTitle">Supporting Document</span>
                        <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <div id="aeDropZone"
                         class="border-2 border-dashed border-gray-300 rounded-lg py-4 px-4 text-center cursor-pointer hover:border-orange-300 hover:bg-orange-50/30 transition-all duration-200"
                         onclick="document.getElementById('aeFileInput').click()"
                         ondragover="event.preventDefault();this.classList.add('border-orange-400','bg-orange-50/40')"
                         ondragleave="this.classList.remove('border-orange-400','bg-orange-50/40')"
                         ondrop="PlanCost.handleEditDocDrop(event)">
                        <svg class="w-6 h-6 text-gray-300 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-xs text-gray-400" id="aeDropLabel">Click or drag &amp; drop proof document</p>
                        <input type="file" id="aeFileInput" class="hidden"
                               onchange="PlanCost.onEditFileSelected(this)">
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="PlanCost.closeExpenseEditModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="aeSaveBtn" onclick="PlanCost.saveEditExpense()"
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- CONTRACT WINDOW WARNING MODAL (pengganti native alert)        --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="contractWarningModal" class="fixed inset-0 z-[60] hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeContractWarningModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col" style="max-height:85vh;">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-3 flex-shrink-0">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Saved — please review planning</h3>
                    <p class="text-xs text-gray-500 mt-0.5">The contract dates were saved successfully.</p>
                </div>
            </div>
            <div class="overflow-y-auto flex-1 px-6 py-4">
                <div id="contractWarningBody" class="text-sm text-gray-700"></div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end flex-shrink-0">
                <button type="button" onclick="closeContractWarningModal()"
                        class="px-5 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition">
                    OK, I'll review
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST — ACTUAL DETAIL MODAL                               --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="actualDetailModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="PlanCost.closeActualDetailModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col" style="max-height:90vh;">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Actual Expense Details</h3>
                    <p class="text-xs text-gray-500 mt-0.5" id="actualDetailSubtitle"></p>
                </div>
                <button type="button" onclick="PlanCost.closeActualDetailModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Scrollable body --}}
            <div class="overflow-y-auto flex-1 p-6 space-y-5">

                {{-- Summary bar: Actual = total of all expense details --}}
                <div id="actualDetailSummary" class="flex justify-center">
                    <div class="bg-orange-50 border border-orange-200 rounded-lg px-6 py-4 text-center w-full max-w-xs">
                        <p class="text-xs text-orange-600 font-medium uppercase tracking-wide mb-1">Actual Amount</p>
                        <p class="text-lg font-bold text-orange-700 font-mono" id="adTotalItems">Rp 0</p>
                        <p class="text-xs text-orange-400 mt-0.5">Auto-calculated from the expense total below</p>
                    </div>
                </div>

                {{-- Table rincian pengeluaran --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Expense List</h4>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-medium">#</th>
                                    <th class="px-4 py-2.5 text-left font-medium">Expense Name</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Amount</th>
                                    <th class="px-4 py-2.5 text-center font-medium">Document</th>
                                    <th class="px-4 py-2.5 text-center font-medium w-24">Action</th>
                                </tr>
                            </thead>
                            <tbody id="actualDetailTableBody" class="divide-y divide-gray-100">
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-400 text-sm">
                                        <svg class="animate-spin h-5 w-5 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        Loading data…
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="actualDetailTableFoot" class="hidden bg-gray-50 border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="2" class="px-4 py-2.5 text-sm font-bold text-gray-700 text-right">Total:</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-blue-700 font-mono text-sm" id="adFooterTotal">Rp 0</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Form tambah pengeluaran.
                     data-closed-hide: seluruh blok disembunyikan saat project
                     closed (lihat delivery/partials/project-closed-lock). --}}
                <div data-closed-hide class="border border-dashed border-gray-300 rounded-xl p-4 bg-gray-50/60">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Expense
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Expense name --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Expense Name <span class="text-red-500">*</span></label>
                            <input type="text" id="adDescInput" maxlength="200"
                                   class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                                   placeholder="e.g. Transportation, Accommodation…">
                        </div>
                        {{-- Amount --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rp) <span class="text-red-500">*</span></label>
                            <input type="text" id="adAmountInput" inputmode="numeric"
                                   class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                                   placeholder="0">
                        </div>
                        {{-- Upload dokumen --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Supporting Document
                                <span class="font-normal text-gray-400">(optional)</span>
                            </label>
                            <div id="adDropZone"
                                 class="border-2 border-dashed border-gray-300 rounded-lg py-4 px-4 text-center cursor-pointer hover:border-orange-300 hover:bg-orange-50/30 transition-all duration-200"
                                 onclick="document.getElementById('adFileInput').click()"
                                 ondragover="event.preventDefault();this.classList.add('border-orange-400','bg-orange-50/40')"
                                 ondragleave="this.classList.remove('border-orange-400','bg-orange-50/40')"
                                 ondrop="PlanCost.handleDocDrop(event)">
                                <svg class="w-6 h-6 text-gray-300 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                <p class="text-xs text-gray-400" id="adDropLabel">Click or drag &amp; drop proof document</p>
                                <input type="file" id="adFileInput" class="hidden"
                                       onchange="PlanCost.onFileSelected(this)">
                            </div>
                        </div>
                    </div>
                    {{-- Add button --}}
                    <div class="mt-3 flex justify-end">
                        <button type="button" id="adAddBtn" onclick="PlanCost.addExpenseItem()"
                                class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add
                        </button>
                    </div>
                </div>

            </div>{{-- end scrollable body --}}

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end flex-shrink-0">
                <button type="button" onclick="PlanCost.closeActualDetailModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- RISK REGISTER — ADD / EDIT MODAL                              --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TERM OF PAYMENT (TOP) PLAN — ADD / EDIT MODAL                  --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="paymentTermModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="PaymentTermPlan.closeModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900" id="paymentTermModalTitle">Add Payment Term</h3>
                <button type="button" onclick="PaymentTermPlan.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto space-y-4">
                <input type="hidden" id="paymentTermModalMode" value="create">
                <input type="hidden" id="paymentTermModalId" value="">

                {{-- Payment Term --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Term <span class="text-red-500">*</span></label>
                    <input type="text" id="pt_payment_term" maxlength="255" autocomplete="off"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                           placeholder="e.g. Down Payment, Termin 1, Final Payment">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Payment % --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment % <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" id="pt_payment_percentage" min="0" max="100" step="0.01" autocomplete="off"
                                   oninput="PaymentTermPlan.recalcAmount()"
                                   class="w-full pr-9 pl-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus text-right"
                                   placeholder="0">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 pointer-events-none">%</span>
                        </div>
                    </div>

                    {{-- Amount (auto) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-gray-400 font-normal">(auto)</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                            <input type="text" id="pt_amount_disp" readonly tabindex="-1"
                                   class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg bg-gray-50 cursor-not-allowed text-sm text-gray-600 text-right"
                                   placeholder="0">
                        </div>
                    </div>
                </div>

                {{-- Payment Requirements / Evidence --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Requirements / Evidence</label>
                    <textarea id="pt_requirements" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus resize-none"
                              placeholder="e.g. Signed BAST, Invoice, PO number…"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Estimated Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Date</label>
                        <input type="text" id="pt_estimated_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                               placeholder="dd/mm/yyyy">
                    </div>

                    {{-- Submit Invoice Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Submit Invoice Date</label>
                        <input type="text" id="pt_submit_invoice_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                               placeholder="dd/mm/yyyy">
                    </div>
                </div>

                {{-- Invoice Number — wajib saat Submit Invoice Date terisi --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Invoice Number <span id="pt_invoice_number_req" class="text-red-500 hidden">*</span>
                    </label>
                    <input type="text" id="pt_invoice_number" maxlength="255" autocomplete="off"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                           placeholder="e.g. INV/2026/06/001">
                    <p id="pt_invoice_number_hint" class="mt-1 text-xs text-gray-400 hidden">Required because Submit Invoice Date is filled.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Paid Date — wajib saat Status = Paid --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Paid Date <span id="pt_paid_date_req" class="text-red-500 hidden">*</span>
                        </label>
                        <input type="text" id="pt_paid_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                               placeholder="dd/mm/yyyy">
                        <p id="pt_paid_date_hint" class="mt-1 text-xs text-gray-400 hidden">Required because Status is Paid.</p>
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="pt_status" onchange="PaymentTermPlan.togglePaidDateRequired()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus">
                            @foreach(['Open','Paid','Delay'] as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="PaymentTermPlan.closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="paymentTermSaveBtn" onclick="PaymentTermPlan.save()"
                        class="px-4 py-2 text-sm font-semibold text-white primary-gradient rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TERM OF PAYMENT (TOP) PLAN — DELETE CONFIRMATION MODAL         --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="paymentTermDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="PaymentTermPlan.closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Payment Term #<span id="ptDeleteNumber"></span>?</h3>
                <p class="text-sm text-gray-500 mb-5">This payment term will be permanently deleted.</p>
                <input type="hidden" id="ptDeleteId" value="">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="PaymentTermPlan.closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="ptDeleteConfirmBtn" onclick="PaymentTermPlan.confirmDelete()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- RISK REGISTER — DELETE CONFIRMATION MODAL                      --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="riskDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="RiskRegister.closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete <span id="riskDeleteLabel"></span>?</h3>
                <p class="text-sm text-gray-500 mb-5">This risk will be permanently deleted.</p>
                <input type="hidden" id="riskDeleteId" value="">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="RiskRegister.closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="riskDeleteConfirmBtn" onclick="RiskRegister.confirmDelete()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="riskModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="RiskRegister.closeModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900" id="riskModalTitle">Add Risk</h3>
                <button type="button" onclick="RiskRegister.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto space-y-4">
                <input type="hidden" id="riskModalMode" value="create">
                <input type="hidden" id="riskModalId" value="">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    {{-- Risk Type --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Risk Type <span class="text-red-500">*</span></label>
                        <select id="risk_type" onchange="RiskRegister.onRiskTypeChange()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">-- Select Type --</option>
                            <option value="Threat">Threat</option>
                            <option value="Opportunity">Opportunity</option>
                        </select>
                    </div>

                    {{-- Risk Category --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Risk Category <span class="text-red-500">*</span></label>
                        <select id="risk_category" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">-- Select Risk Category --</option>
                            @foreach(['Scope','Schedule','Cost/Budget','Resource','Technical/Quality','Procurement','Stakeholder','External'] as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="risk_status" onchange="RiskRegister.onStatusChange()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            @foreach(['Open','In Progress','Mitigated','Closed'] as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Probability --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Probability (1-5) <span class="text-red-500">*</span></label>
                        <select id="risk_probability" onchange="RiskRegister.refreshScore()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">--</option>
                            @for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                        </select>
                    </div>

                    {{-- Impact --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Impact (1-5) <span class="text-red-500">*</span></label>
                        <select id="risk_impact" onchange="RiskRegister.refreshScore()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">--</option>
                            @for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                        </select>
                    </div>

                    {{-- Score preview --}}
                    <div class="sm:col-span-2">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <span class="text-sm text-gray-600 font-medium">Risk Score (P × I):</span>
                            <span id="riskScorePreview" class="text-sm font-bold text-gray-400">—</span>
                            <span class="text-sm text-gray-400 mx-1">→</span>
                            <span id="riskLevelPreview" class="text-sm font-bold text-gray-400">—</span>
                        </div>
                    </div>

                    {{-- Response Strategy (options depend on Risk Type) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Response Strategy <span class="text-red-500">*</span></label>
                        <select id="risk_response_strategy" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">-- Select Risk Type first --</option>
                        </select>
                    </div>

                    {{-- Risk Owner --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Risk Owner <span class="text-red-500">*</span></label>
                        <select id="risk_owner" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">-- Select Risk Owner --</option>
                            @foreach($teamPeople as $person)
                                <option value="{{ $person }}">{{ $person }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Target Date <span class="text-red-500">*</span></label>
                        <input type="text" id="risk_target_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="dd/mm/yyyy">
                    </div>

                    {{-- Actual End Date (shown only when status = Closed) --}}
                    <div id="risk_actual_end_wrapper" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Actual End Date <span class="text-red-500">*</span></label>
                        <input type="text" id="risk_actual_end_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="dd/mm/yyyy">
                    </div>

                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Risk Description <span class="text-red-500">*</span></label>
                    <textarea id="risk_description" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="Describe the identified risk…"></textarea>
                </div>

                {{-- Cause / Trigger --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cause (Trigger) <span class="text-red-500">*</span></label>
                    <textarea id="risk_cause" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="What could trigger this risk…"></textarea>
                </div>

                {{-- Project Impact text --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Impact <span class="text-red-500">*</span></label>
                    <textarea id="risk_project_impact" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="Impact this risk would have on the project…"></textarea>
                </div>

                {{-- Mitigation Plan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mitigation / Contingency Plan <span class="text-red-500">*</span></label>
                    <textarea id="risk_mitigation_plan" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="Mitigation steps and contingency plan…"></textarea>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Comments / Notes <span class="text-red-500">*</span></label>
                    <textarea id="risk_notes" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="Additional notes…"></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="RiskRegister.closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="riskModalSaveBtn" onclick="RiskRegister.save()"
                        class="px-4 py-2 text-sm font-semibold text-white primary-gradient rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- RISK REGISTER — DASHBOARD MODAL                               --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="riskDashboardModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="RiskRegister.closeDashboard()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Risk Summary Dashboard
                </h3>
                <button type="button" onclick="RiskRegister.closeDashboard()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto space-y-6">

                {{-- Stat Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-500 mb-1">Total Risks</p>
                        <p id="dash_total" class="text-3xl font-bold text-gray-800">0</p>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-red-600 mb-1">High Risk</p>
                        <p id="dash_high" class="text-3xl font-bold text-red-700">0</p>
                    </div>
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-orange-600 mb-1">Medium Risk</p>
                        <p id="dash_medium" class="text-3xl font-bold text-orange-700">0</p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-green-600 mb-1">Low Risk</p>
                        <p id="dash_low" class="text-3xl font-bold text-green-700">0</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                    {{-- Status Breakdown --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Risk Status Breakdown</h4>
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-800 text-white">
                                        <th class="px-4 py-2.5 text-left font-semibold">Status</th>
                                        <th class="px-4 py-2.5 text-center font-semibold w-20">Count</th>
                                    </tr>
                                </thead>
                                <tbody id="dash_status_tbody" class="divide-y divide-gray-100 bg-white">
                                    <tr><td colspan="2" class="text-center py-4 text-gray-400 text-xs">No data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Risk Level Matrix Reference --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Risk Level Matrix Reference (P × I)</h4>
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <table class="min-w-full text-xs text-center border-collapse">
                                <thead>
                                    <tr class="bg-gray-700 text-white">
                                        <th class="px-2 py-2 font-semibold border border-gray-600">P \ I</th>
                                        @for($i=1;$i<=5;$i++)<th class="px-3 py-2 font-semibold border border-gray-600">{{ $i }}</th>@endfor
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    // Level = High jika P×I >= 12, Medium jika >= 5, Low jika < 5
                                    $matrix = [
                                        5 => [1=>'M',2=>'M',3=>'H',4=>'H',5=>'H'],
                                        4 => [1=>'L',2=>'M',3=>'H',4=>'H',5=>'H'],
                                        3 => [1=>'L',2=>'M',3=>'M',4=>'H',5=>'H'],
                                        2 => [1=>'L',2=>'L',3=>'M',4=>'M',5=>'M'],
                                        1 => [1=>'L',2=>'L',3=>'L',4=>'L',5=>'M'],
                                    ];
                                    $matrixColor = ['H'=>'bg-red-100 text-red-700 font-bold','M'=>'bg-orange-100 text-orange-700 font-semibold','L'=>'bg-green-50 text-green-700'];
                                    @endphp
                                    @foreach($matrix as $p => $row)
                                    <tr>
                                        <td class="px-2 py-2 font-semibold bg-gray-700 text-white border border-gray-600">{{ $p }}</td>
                                        @foreach($row as $i => $level)
                                        <td class="px-3 py-2 border border-gray-200 {{ $matrixColor[$level] }}">{{ $level }}</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">H = High &nbsp;|&nbsp; M = Medium &nbsp;|&nbsp; L = Low</p>
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end flex-shrink-0">
                <button type="button" onclick="RiskRegister.closeDashboard()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST — JAVASCRIPT                                        --}}
{{-- Axios harus di-load dulu sebelum script ini dieksekusi        --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
(function () {
    'use strict';

    const PROJECT_ID = {{ $project->id }};
    const BASE_URL   = `/projects/${PROJECT_ID}/costs`;
    // CSRF dibaca di sini untuk digunakan di dalam request (bukan di top-level)
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // ── Formatter ──────────────────────────────────────────────────
    function fmt(val) {
        if (val === null || val === undefined) return '—';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(val);
    }

    /** Tampil dengan prefix "Rp " — untuk sel tabel */
    function fmtRp(val) {
        if (val === null || val === undefined) return '—';
        return 'Rp ' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(val);
    }

    function parseNum(str) {
        if (!str && str !== 0) return null;
        const cleaned = String(str).replace(/\./g, '').replace(',', '.');
        const n = parseFloat(cleaned);
        return isNaN(n) ? null : n;
    }

    // Format currency input with thousand separator as user types
    function formatCurrencyInput(input) {
        input.addEventListener('input', function () {
            const raw   = this.value.replace(/\D/g, '');
            this.value  = raw ? new Intl.NumberFormat('id-ID').format(Number(raw)) : '';
            refreshPreview();
        });
    }

    // ── State ──────────────────────────────────────────────────────
    let _costs   = [];   // flat structure returned by API
    let _editId  = null;

    // Actual detail modal state
    let _adCostId      = null;  // current cost item id
    let _adTotal       = 0;     // sum of expense line-items (= the actual amount)
    let _adDirty       = false; // expenses changed → main cost table needs reload
    let _adDeleteId    = null;  // expense item id pending deletion (confirm modal)
    let _adDeleteRowEl = null;  // <tr> element pending removal on confirm
    let _adEditId      = null;  // expense item id being edited
    let _adEditRowEl   = null;  // <tr> element being edited
    let _adEditRemoveDoc = false; // user asked to remove the existing document

    // Cost form modal: current actual_amount (derived from expenses, read-only here)
    let _currentActual = 0;

    // ── Init ───────────────────────────────────────────────────────
    async function init() {
        await ensureInit();
        await load();
    }

    async function ensureInit() {
        try {
            await axios.post(`${BASE_URL}/init`);
        } catch (e) {
            // 200 "already initialised" is swallowed; anything else ignored
        }
    }

    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _costs = res.data.costs ?? [];
            renderTable(_costs);
            renderSummaryCards(res.data.summary ?? {});
        } catch (e) {
            console.error('PlanCost: load error', e);
            document.getElementById('planCostTableBody').innerHTML =
                `<tr><td colspan="8" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh the page.</td></tr>`;
        }
    }

    // ── Render Table ───────────────────────────────────────────────
    function renderTable(costs) {
        const tbody = document.getElementById('planCostTableBody');
        if (!costs.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-gray-400 text-sm">No cost items yet. Click "Add Cost Item" to get started.</td></tr>`;
            return;
        }

        let html = '';
        costs.forEach(c => {
            html += rowHtml(c, false);
            if (c.children && c.children.length) {
                c.children.forEach(ch => { html += rowHtml(ch, true); });
            }
            // Always show "+ Add sub-item" for every top-level parent
            html += addChildRowHtml(c);
        });
        tbody.innerHTML = html;
    }

    function rowHtml(item, isChild) {
        const isParent = item.has_children;

        // For parent: aggregate display; for child/leaf: own values
        const budget  = item.display_budget;
        const release = item.display_release;
        // Actual is derived from expense details → always numeric (0 = no expenses).
        const actual  = item.display_actual ?? 0;
        const avBudg  = item.avail_budget;
        const avRel   = item.avail_release;

        // Color helpers
        function availColor(val) {
            if (val === null) return 'text-gray-400';
            if (val < 0)      return 'text-red-600 font-semibold';
            if (val === 0)    return 'text-gray-500';
            return 'text-green-700';
        }

        const rowBg   = isChild  ? 'bg-white hover:bg-gray-50'
                      : isParent ? 'bg-gray-100 hover:bg-gray-200'
                      : 'bg-blue-50 hover:bg-blue-100';

        const nameClass = isParent ? 'font-bold text-gray-800 uppercase tracking-wide'
                        : isChild  ? 'pl-6 text-gray-700'
                        : 'font-semibold text-gray-800';

        const codeLabel = isChild
            ? `<span class="text-gray-400 text-xs">${item.code ?? ''}</span>`
            : `<span class="font-bold text-gray-600">${item.code ?? ''}</span>`;

        // Disable edit/delete for aggregate parent rows that have children
        const editBtn = `
            <button type="button" title="Edit"
                    onclick="PlanCost.openEditModal(${item.id})"
                    class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>`;

        const deleteBtn = `
            <button type="button" title="Delete"
                    onclick="PlanCost.openDeleteModal(${item.id}, '${(item.name ?? '').replace(/'/g, "\\'")}')"
                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>`;

        // Actual cell: clickable for leaf items, static for parent aggregates
        const actualCell = !isParent
            ? `<td class="px-4 py-3 text-right whitespace-nowrap bg-orange-50/40 cursor-pointer hover:bg-orange-100 transition-colors"
                   onclick="PlanCost.openActualDetailModal(${item.id}, '${(item.name ?? '').replace(/'/g, "\\'")}')"
                   title="Click to view / add expense details">
                   <span class="text-orange-700 font-mono text-xs">${fmtRp(actual)}</span>
               </td>`
            : `<td class="px-4 py-3 text-right whitespace-nowrap text-orange-700 font-mono text-xs bg-orange-50/40">${fmtRp(actual)}</td>`;

        return `
        <tr class="${rowBg} transition-colors">
            <td class="px-4 py-3 whitespace-nowrap">${codeLabel}</td>
            <td class="px-4 py-3 ${nameClass}">${item.name ?? ''}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap text-gray-800 font-mono text-xs">${fmtRp(budget)}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap text-blue-700 font-mono text-xs bg-blue-50/40">${fmtRp(release)}</td>
            ${actualCell}
            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-xs ${availColor(avBudg)} bg-green-50/40">${fmtRp(avBudg)}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-xs ${availColor(avRel)}  bg-teal-50/40">${fmtRp(avRel)}</td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                <div class="inline-flex items-center gap-0.5">
                    ${editBtn}
                    ${deleteBtn}
                </div>
            </td>
        </tr>`;
    }

    function addChildRowHtml(parent) {
        return `
        <tr class="bg-white border-t border-dashed border-gray-200">
            <td colspan="8" class="px-4 py-2">
                <button type="button"
                        onclick="PlanCost.openAddChildModal(${parent.id}, '${parent.cost_type}')"
                        class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded transition">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add sub-item to ${parent.name}
                </button>
            </td>
        </tr>`;
    }

    // ── Render Summary Cards ───────────────────────────────────────
    function renderSummaryCards(s) {
        const wrap = document.getElementById('planCostSummaryCards');
        if (!wrap) return;

        // Inline SVG (heroicons-outline) — jangan pakai emoji literal (mojibake via HTTP).
        const icon = (path) =>
            `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">`
            + `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${path}"/></svg>`;

        // Path ikon per kartu.
        const ICONS = {
            budget:  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            release: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
            actual:  'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
            check:   'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            chart:   'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        };

        function card(label, value, colorClass, iconPath, iconTint) {
            return `
            <div class="bg-white rounded-lg border border-gray-200 p-4 flex flex-col gap-1 shadow-sm">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${iconTint}">${icon(iconPath)}</span>
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">${label}</span>
                </div>
                <span class="text-base font-bold ${colorClass} font-mono">Rp ${fmt(value ?? 0)}</span>
            </div>`;
        }

        wrap.innerHTML =
            card('Total Budget',   s.total_budget,   'text-gray-800',   ICONS.budget,  'bg-gray-100 text-gray-600') +
            card('Total Release',  s.total_release,  'text-blue-700',   ICONS.release, 'bg-blue-100 text-blue-600') +
            card('Total Actual',   s.total_actual,   'text-orange-600', ICONS.actual,  'bg-orange-100 text-orange-600') +
            card('Avail. Budget',  s.total_avail_budget,  s.total_avail_budget  < 0 ? 'text-red-600' : 'text-green-700', ICONS.check, 'bg-green-100 text-green-600') +
            card('Avail. Release', s.total_avail_release, s.total_avail_release < 0 ? 'text-red-600' : 'text-teal-700',  ICONS.chart, 'bg-teal-100 text-teal-600');

        // Keep Delivery Information → Actual Cost / GP / % in sync with the
        // Plan Cost "Total Actual" (no page reload needed).
        if (window.sfinSetActualCost) window.sfinSetActualCost(s.total_actual ?? 0);
    }

    // ── Modal helpers ─────────────────────────────────────────────
    function showModal() { document.getElementById('costModal').classList.remove('hidden'); }
    function _close()    { document.getElementById('costModal').classList.add('hidden'); }

    function resetForm() {
        document.getElementById('costCodeInput').value     = '';
        document.getElementById('costNameInput').value     = '';
        document.getElementById('costBudgetInput').value   = '';
        document.getElementById('costReleaseInput').value  = '';
        _currentActual = 0;
        document.getElementById('costModalId').value       = '';
        document.getElementById('costModalParentId').value = '';
        document.getElementById('costModalMode').value     = 'create';
        document.getElementById('costTypeIndirect').checked = false;
        document.getElementById('costTypeDirect').checked   = true;
        document.getElementById('costTypeRow').style.display      = '';
        // Default: amounts section visible, notice hidden
        document.getElementById('costAmountsSection').style.display  = '';
        document.getElementById('costAggregateNotice').style.display = 'none';
        refreshPreview();
    }

    function refreshPreview() {
        const b  = parseNum(document.getElementById('costBudgetInput').value.replace(/\./g,''));
        const r  = parseNum(document.getElementById('costReleaseInput').value.replace(/\./g,''));
        // Actual is derived from expense detail (not an input on this form).
        const a  = _currentActual ?? 0;
        const ab = (b !== null || r !== null) ? (b ?? 0) - (r ?? 0) : null;
        const ar = (r !== null || a > 0) ? (r ?? 0) - a : null;

        const abEl = document.getElementById('previewAvailBudget');
        const arEl = document.getElementById('previewAvailRelease');
        abEl.textContent = ab !== null ? `Rp ${fmt(ab)}` : '—';
        arEl.textContent = ar !== null ? `Rp ${fmt(ar)}` : '—';
        abEl.className = `font-semibold ml-1 ${ab !== null && ab < 0 ? 'text-red-600' : 'text-green-700'}`;
        arEl.className = `font-semibold ml-1 ${ar !== null && ar < 0 ? 'text-red-600' : 'text-teal-700'}`;
    }

    // ── Find item by id (recursive) ───────────────────────────────
    function findItem(id, list) {
        for (const c of list) {
            if (c.id === id) return c;
            if (c.children) {
                const found = findItem(id, c.children);
                if (found) return found;
            }
        }
        return null;
    }

    // ── Public API ─────────────────────────────────────────────────
    const PlanCost = {

        closeModal() { _close(); },
        closeDeleteModal() { document.getElementById('costDeleteModal').classList.add('hidden'); },

        onTypeChange(type) {
            // Update modal title to show which parent it will be added to
            const parentItem = _costs.find(c => c.cost_type === type && c.parent_id === null);
            const parentName = parentItem ? parentItem.name : (type === 'indirect' ? 'Indirect Cost' : 'Direct Cost');
            document.getElementById('costModalTitle').textContent = `Add Item to ${parentName}`;
        },

        openAddParentModal() {
            resetForm();
            // Default title — updates dynamically when type is changed
            const defaultParent = _costs.find(c => c.cost_type === 'direct' && c.parent_id === null);
            const defaultName   = defaultParent ? defaultParent.name : 'Direct Cost';
            document.getElementById('costModalTitle').textContent = `Add Item to ${defaultName}`;
            document.getElementById('costModalMode').value        = 'create';
            document.getElementById('costModalParentId').value    = '';
            document.getElementById('costTypeRow').style.display  = '';
            showModal();
        },

        openAddChildModal(parentId, parentType) {
            resetForm();
            document.getElementById('costModalTitle').textContent   = 'Add Cost Sub-item';
            document.getElementById('costModalMode').value          = 'create';
            document.getElementById('costModalParentId').value      = parentId;
            document.getElementById('costTypeRow').style.display    = 'none';
            // inherit parent type
            document.getElementById('costTypeIndirect').checked = (parentType === 'indirect');
            document.getElementById('costTypeDirect').checked   = (parentType === 'direct');
            showModal();
        },

        openEditModal(id) {
            const item = findItem(id, _costs);
            if (!item) return;

            resetForm();
            document.getElementById('costModalTitle').textContent     = 'Edit Cost Item';
            document.getElementById('costModalMode').value            = 'edit';
            document.getElementById('costModalId').value              = id;
            document.getElementById('costModalParentId').value        = item.parent_id ?? '';

            document.getElementById('costCodeInput').value  = item.code ?? '';
            document.getElementById('costNameInput').value  = item.name ?? '';

            document.getElementById('costTypeIndirect').checked = (item.cost_type === 'indirect');
            document.getElementById('costTypeDirect').checked   = (item.cost_type === 'direct');

            // Hide type selector for child items
            document.getElementById('costTypeRow').style.display = item.parent_id ? 'none' : '';

            const amountsSection  = document.getElementById('costAmountsSection');
            const aggregateNotice = document.getElementById('costAggregateNotice');

            if (item.has_children) {
                // Parent-with-children: amounts are aggregated — hide inputs, show notice
                amountsSection.style.display  = 'none';
                aggregateNotice.style.display = '';
            } else {
                // Leaf item: show inputs normally
                amountsSection.style.display  = '';
                aggregateNotice.style.display = 'none';

                function setFmtVal(inputId, val) {
                    const el = document.getElementById(inputId);
                    el.value = (val !== null && val !== undefined)
                        ? new Intl.NumberFormat('id-ID').format(val)
                        : '';
                }
                setFmtVal('costBudgetInput',  item.budget);
                setFmtVal('costReleaseInput', item.release_amount);
                // Actual is derived from expenses → used for preview only, not editable here.
                _currentActual = item.actual_amount ?? 0;
                refreshPreview();
            }

            showModal();
        },

        openDeleteModal(id, name) {
            document.getElementById('costDeleteId').value      = id;
            document.getElementById('costDeleteName').textContent = name;
            document.getElementById('costDeleteModal').classList.remove('hidden');
        },

        async save() {
            const mode     = document.getElementById('costModalMode').value;
            const id       = document.getElementById('costModalId').value;
            let parentId = document.getElementById('costModalParentId').value;
            const costType = document.querySelector('input[name="costTypeRadio"]:checked')?.value ?? 'direct';
            const name     = document.getElementById('costNameInput').value.trim();

            if (!name) {
                alert('Item name is required.');
                return;
            }

            function getRawVal(inputId) {
                const v = document.getElementById(inputId).value.replace(/\./g, '').replace(',', '.');
                return v === '' ? null : parseFloat(v);
            }

            // ── Auto-route to parent based on cost_type ────────────────
            // When creating from the "Add Cost Item" button (parentId empty),
            // automatically find the top-level parent that matches cost_type
            // so the new item appears as a child of INDIRECT COST or DIRECT COST.
            if (mode === 'create' && !parentId) {
                const matchingParent = _costs.find(
                    c => c.cost_type === costType && c.parent_id === null
                );
                if (matchingParent) {
                    parentId = String(matchingParent.id);
                }
            }

            // If amounts-section is hidden (parent-with-children),
            // send null so the DB is not overwritten with empty values
            const amountsHidden = document.getElementById('costAmountsSection').style.display === 'none';

            const payload = {
                parent_id:      parentId || null,
                code:           document.getElementById('costCodeInput').value.trim() || null,
                name,
                cost_type:      costType,
                budget:         amountsHidden ? null : getRawVal('costBudgetInput'),
                release_amount: amountsHidden ? null : getRawVal('costReleaseInput'),
                // actual_amount is derived server-side from expense details — not sent here.
                _token:         getCsrf(),
            };

            const btn = document.getElementById('costModalSaveBtn');
            btn.disabled = true;

            try {
                if (mode === 'create') {
                    await axios.post(BASE_URL, payload);
                } else {
                    // POST + X-HTTP-Method-Override:PUT — verb PUT diblokir sebagian edge production.
                    await axios.post(`${BASE_URL}/${id}`, payload, { headers: { 'X-HTTP-Method-Override': 'PUT' } });
                }
                _close();
                await load();
                showPlanCostToast(mode === 'create' ? 'Cost item added successfully.' : 'Cost item updated successfully.', 'success');
            } catch (err) {
                const msg = err.response?.data?.message
                         ?? (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' ') : null)
                         ?? 'An error occurred.';
                showPlanCostToast(msg, 'error');
            } finally {
                btn.disabled = false;
            }
        },

        async confirmDelete() {
            const id = document.getElementById('costDeleteId').value;
            if (!id) return;

            try {
                await axios.post(`${BASE_URL}/${id}/delete`);
                PlanCost.closeDeleteModal();
                await load();
                showPlanCostToast('Cost item deleted successfully.', 'success');
            } catch (err) {
                showPlanCostToast('Failed to delete item.', 'error');
            }
        },

        // ── Actual Detail Modal ──────────────────────────────────────

        async openActualDetailModal(costId, costName) {
            _adCostId = costId;
            _adDirty  = false;

            // Set subtitle
            document.getElementById('actualDetailSubtitle').textContent = costName;

            // Reset form
            _adResetForm();

            // Show modal
            document.getElementById('actualDetailModal').classList.remove('hidden');

            // Load items from server
            await _adLoadItems();
        },

        async closeActualDetailModal() {
            document.getElementById('actualDetailModal').classList.add('hidden');
            _adCostId = null;
            _adTotal  = 0;

            // Expenses changed → actual_amount was updated server-side; refresh the table.
            if (_adDirty) {
                _adDirty = false;
                await load();
            }
        },

        async addExpenseItem() {
            const desc   = document.getElementById('adDescInput').value.trim();
            const rawAmt = document.getElementById('adAmountInput').value.replace(/\./g, '').replace(',', '.');
            const amount = parseFloat(rawAmt);
            const file   = document.getElementById('adFileInput').files[0];

            if (!desc) {
                showPlanCostToast('Expense name is required.', 'error');
                document.getElementById('adDescInput').focus();
                return;
            }
            if (!rawAmt || isNaN(amount) || amount <= 0) {
                showPlanCostToast('Amount must be greater than 0.', 'error');
                document.getElementById('adAmountInput').focus();
                return;
            }

            const btn = document.getElementById('adAddBtn');
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('description', desc);
                fd.append('amount', amount);
                fd.append('_token', getCsrf());
                if (file) fd.append('document', file);

                const res = await axios.post(`${BASE_URL}/${_adCostId}/items`, fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                _adTotal = res.data.total ?? 0;
                _adDirty = true;
                _adAppendRow(res.data.item, _adGetCurrentCount() + 1);
                _adUpdateSummary();
                _adResetForm();
                showPlanCostToast('Expense added successfully.', 'success');
            } catch (err) {
                const msg = err.response?.data?.message
                         ?? (err.response?.data?.errors
                             ? Object.values(err.response.data.errors).flat().join(' ')
                             : null)
                         ?? 'Failed to add expense.';
                showPlanCostToast(msg, 'error');
            } finally {
                btn.disabled = false;
            }
        },

        // Opens the confirm modal (view-consistent, replaces native confirm()).
        deleteExpenseItem(itemId, rowEl) {
            _adDeleteId    = itemId;
            _adDeleteRowEl = rowEl;
            const name = rowEl?.querySelector('td:nth-child(2)')?.textContent?.trim() || '';
            document.getElementById('expenseDeleteName').textContent = name;
            document.getElementById('expenseDeleteModal').classList.remove('hidden');
        },

        closeExpenseDeleteModal() {
            document.getElementById('expenseDeleteModal').classList.add('hidden');
            _adDeleteId    = null;
            _adDeleteRowEl = null;
        },

        async confirmDeleteExpense() {
            if (!_adDeleteId) return;
            const itemId = _adDeleteId;
            const rowEl  = _adDeleteRowEl;
            const btn    = document.getElementById('expenseDeleteConfirmBtn');
            btn.disabled = true;
            try {
                const res = await axios.post(`${BASE_URL}/${_adCostId}/items/${itemId}/delete`);
                _adTotal = res.data.total ?? 0;
                _adDirty = true;
                rowEl?.remove();
                _adRenumberRows();
                _adUpdateSummary();
                if (_adGetCurrentCount() === 0) _adShowEmpty();
                PlanCost.closeExpenseDeleteModal();
                showPlanCostToast('Expense deleted.', 'success');
            } catch (err) {
                showPlanCostToast('Failed to delete expense.', 'error');
            } finally {
                btn.disabled = false;
            }
        },

        handleDocDrop(event) {
            event.preventDefault();
            document.getElementById('adDropZone').classList.remove('border-orange-400', 'bg-orange-50/40');
            const file = event.dataTransfer.files[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('adFileInput').files = dt.files;
            PlanCost.onFileSelected(document.getElementById('adFileInput'));
        },

        onFileSelected(input) {
            const file = input.files[0];
            const label = document.getElementById('adDropLabel');
            if (file) {
                label.textContent = file.name;
                label.className   = 'text-xs text-orange-600 font-medium';
            } else {
                label.textContent = 'Click or drag & drop proof document';
                label.className   = 'text-xs text-gray-400';
            }
        },

        // ── Edit Expense Modal ───────────────────────────────────────

        openEditExpenseModal(itemId, rowEl) {
            _adEditId        = itemId;
            _adEditRowEl     = rowEl;
            _adEditRemoveDoc = false;

            const desc   = rowEl?.dataset.desc   ?? '';
            const amount = parseFloat(rowEl?.dataset.amount ?? '0') || 0;
            const docName = rowEl?.dataset.docName ?? '';
            const docUrl  = rowEl?.dataset.docUrl  ?? '';

            document.getElementById('aeDescInput').value   = desc;
            document.getElementById('aeAmountInput').value = amount
                ? new Intl.NumberFormat('id-ID').format(amount) : '';

            // Current document row (only when the item already has one)
            const curRow = document.getElementById('aeCurrentDocRow');
            if (docUrl) {
                document.getElementById('aeCurrentDocLink').href        = docUrl;
                document.getElementById('aeCurrentDocName').textContent = docName || 'View';
                curRow.classList.remove('hidden');
                document.getElementById('aeDropTitle').textContent = 'Replace Document';
            } else {
                curRow.classList.add('hidden');
                document.getElementById('aeDropTitle').textContent = 'Supporting Document';
            }

            // Reset "attach new file" input
            document.getElementById('aeFileInput').value = '';
            const label = document.getElementById('aeDropLabel');
            label.textContent = 'Click or drag & drop proof document';
            label.className   = 'text-xs text-gray-400';

            document.getElementById('expenseEditModal').classList.remove('hidden');
        },

        closeExpenseEditModal() {
            document.getElementById('expenseEditModal').classList.add('hidden');
            _adEditId        = null;
            _adEditRowEl     = null;
            _adEditRemoveDoc = false;
        },

        removeEditDoc() {
            _adEditRemoveDoc = true;
            document.getElementById('aeCurrentDocRow').classList.add('hidden');
            document.getElementById('aeDropTitle').textContent = 'Supporting Document';
        },

        onEditFileSelected(input) {
            const file = input.files[0];
            const label = document.getElementById('aeDropLabel');
            if (file) {
                // A freshly attached file supersedes any "remove existing" intent.
                _adEditRemoveDoc = false;
                label.textContent = file.name;
                label.className   = 'text-xs text-orange-600 font-medium';
            } else {
                label.textContent = 'Click or drag & drop proof document';
                label.className   = 'text-xs text-gray-400';
            }
        },

        handleEditDocDrop(event) {
            event.preventDefault();
            document.getElementById('aeDropZone').classList.remove('border-orange-400', 'bg-orange-50/40');
            const file = event.dataTransfer.files[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('aeFileInput').files = dt.files;
            PlanCost.onEditFileSelected(document.getElementById('aeFileInput'));
        },

        async saveEditExpense() {
            if (!_adEditId) return;
            const desc   = document.getElementById('aeDescInput').value.trim();
            const rawAmt = document.getElementById('aeAmountInput').value.replace(/\./g, '').replace(',', '.');
            const amount = parseFloat(rawAmt);
            const file   = document.getElementById('aeFileInput').files[0];

            if (!desc) {
                showPlanCostToast('Expense name is required.', 'error');
                document.getElementById('aeDescInput').focus();
                return;
            }
            if (!rawAmt || isNaN(amount) || amount <= 0) {
                showPlanCostToast('Amount must be greater than 0.', 'error');
                document.getElementById('aeAmountInput').focus();
                return;
            }

            const btn = document.getElementById('aeSaveBtn');
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('description', desc);
                fd.append('amount', amount);
                if (file) fd.append('document', file);
                if (_adEditRemoveDoc) fd.append('remove_document', '1');

                // POST + X-HTTP-Method-Override:PUT — verb PUT diblokir sebagian edge
                // production; Laravel tetap merutekan ke updateItem() (lihat confirmDeleteExpense).
                const res = await axios.post(
                    `${BASE_URL}/${_adCostId}/items/${_adEditId}`,
                    fd,
                    { headers: { 'Content-Type': 'multipart/form-data', 'X-HTTP-Method-Override': 'PUT' } }
                );

                _adTotal = res.data.total ?? 0;
                _adDirty = true;
                _adUpdateRow(_adEditRowEl, res.data.item);
                _adUpdateSummary();
                PlanCost.closeExpenseEditModal();
                showPlanCostToast('Expense updated successfully.', 'success');
            } catch (err) {
                const msg = err.response?.data?.message
                         ?? (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' ') : null)
                         ?? 'Failed to update expense.';
                showPlanCostToast(msg, 'error');
            } finally {
                btn.disabled = false;
            }
        },
    };

    // ── Simple toast (reuse existing showToast if available) ──────
    function showPlanCostToast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        } else {
            alert(msg);
        }
    }

    // ── Actual Detail Modal — private helpers ─────────────────────

    async function _adLoadItems() {
        const tbody = document.getElementById('actualDetailTableBody');
        const tfoot = document.getElementById('actualDetailTableFoot');
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm">
            <svg class="animate-spin h-5 w-5 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>Loading data…</td></tr>`;
        tfoot.classList.add('hidden');
        try {
            const res = await axios.get(`${BASE_URL}/${_adCostId}/items`);
            _adTotal = res.data.total ?? 0;
            const items = res.data.items ?? [];
            if (!items.length) {
                _adShowEmpty();
            } else {
                tbody.innerHTML = '';
                items.forEach((it, idx) => _adAppendRow(it, idx + 1));
                tfoot.classList.remove('hidden');
            }
            _adUpdateSummary();
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-6 text-red-500 text-sm">Failed to load data.</td></tr>`;
        }
    }

    function _adShowEmpty() {
        document.getElementById('actualDetailTableBody').innerHTML =
            `<tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm italic">No expense records found.</td></tr>`;
        document.getElementById('actualDetailTableFoot').classList.add('hidden');
    }

    function _adAppendRow(item, no) {
        const tbody = document.getElementById('actualDetailTableBody');
        const tfoot = document.getElementById('actualDetailTableFoot');

        // Remove empty-state row if present
        const emptyRow = tbody.querySelector('[data-empty]');
        if (emptyRow) emptyRow.remove();

        const docCell = _adDocCellHtml(item);

        const tr = document.createElement('tr');
        tr.className   = 'hover:bg-gray-50 transition-colors';
        tr.dataset.itemId  = item.id;
        // Raw values cached on the row so the edit modal can be populated
        // without another round-trip.
        tr.dataset.desc    = item.description ?? '';
        tr.dataset.amount  = item.amount ?? 0;
        tr.dataset.docName = item.document_name ?? '';
        tr.dataset.docUrl  = item.document_url ?? '';
        tr.innerHTML = `
            <td class="px-4 py-2.5 text-gray-400 text-xs">${no}</td>
            <td class="px-4 py-2.5 text-gray-700 text-sm">${_esc(item.description)}</td>
            <td class="px-4 py-2.5 text-right font-mono text-sm text-blue-700 font-medium whitespace-nowrap">${fmtRp(item.amount)}</td>
            <td class="px-4 py-2.5 text-center whitespace-nowrap">${docCell}</td>
            <td class="px-4 py-2.5 text-center whitespace-nowrap">
                <div class="inline-flex items-center gap-0.5">
                    <button type="button" title="Edit"
                            onclick="PlanCost.openEditExpenseModal(${item.id}, this.closest('tr'))"
                            class="p-1 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button type="button" title="Delete"
                            onclick="PlanCost.deleteExpenseItem(${item.id}, this.closest('tr'))"
                            class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </td>`;
        tbody.appendChild(tr);
        tfoot.classList.remove('hidden');
    }

    function _adDocCellHtml(item) {
        return item.document_url
            ? `<a href="${item.document_url}" target="_blank" rel="noopener"
                  class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                   <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                   </svg>
                   ${_esc(item.document_name ?? 'View')}
               </a>`
            : `<span class="text-gray-300 text-xs">—</span>`;
    }

    // Update an existing row (cells + cached dataset) after an edit.
    function _adUpdateRow(tr, item) {
        if (!tr || !item) return;
        tr.dataset.desc    = item.description ?? '';
        tr.dataset.amount  = item.amount ?? 0;
        tr.dataset.docName = item.document_name ?? '';
        tr.dataset.docUrl  = item.document_url ?? '';
        const tds = tr.querySelectorAll('td');
        if (tds[1]) tds[1].textContent = item.description ?? '';
        if (tds[2]) tds[2].textContent = fmtRp(item.amount);
        if (tds[3]) tds[3].innerHTML   = _adDocCellHtml(item);
    }

    function _adRenumberRows() {
        document.querySelectorAll('#actualDetailTableBody tr[data-item-id]').forEach((tr, idx) => {
            const firstTd = tr.querySelector('td:first-child');
            if (firstTd) firstTd.textContent = idx + 1;
        });
    }

    function _adGetCurrentCount() {
        return document.querySelectorAll('#actualDetailTableBody tr[data-item-id]').length;
    }

    function _adUpdateSummary() {
        // Actual amount = sum of all expense items. Update header card & footer.
        document.getElementById('adTotalItems').textContent  = fmtRp(_adTotal);
        document.getElementById('adFooterTotal').textContent = fmtRp(_adTotal);
    }

    function _adResetForm() {
        document.getElementById('adDescInput').value  = '';
        document.getElementById('adAmountInput').value = '';
        document.getElementById('adFileInput').value   = '';
        const label = document.getElementById('adDropLabel');
        label.textContent = 'Click or drag & drop proof document';
        label.className   = 'text-xs text-gray-400';
    }

    function _esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Attach currency formatters & init ─────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Setup axios CSRF header — dilakukan di sini agar axios sudah tersedia
        axios.defaults.headers.common['X-CSRF-TOKEN'] = getCsrf();

        ['costBudgetInput', 'costReleaseInput', 'adAmountInput', 'aeAmountInput'].forEach(id => {
            const el = document.getElementById(id);
            if (el) formatCurrencyInput(el);
        });
        init();
    });

    // Expose globally
    window.PlanCost = PlanCost;

})();
</script>

{{-- ALL MODALS --}}

{{-- ── Section edit modals ───────────────────────────────────────────────────
     Section General / Delivery Information / Delivery Data / Location kini
     read-only; formnya pindah ke modal ini (klik pensil → isi → Save →
     notifikasi), mengikuti pola Delivery Support.

     Modal hanya dirender bila role-nya berhak. Ini penting: lapisan read-only
     `data-perm-edit` hanya mengunci field DI DALAM <section>, sedangkan modal
     berada di luar section — tanpa @if di bawah, form-nya jadi tak terkunci
     sama sekali. --}}
@if($can('delivery-project.general.edit'))
    @include('delivery.project.projects.partials.modal-general-info')
@endif
@if($can('delivery-project.delivery-info.edit'))
    @include('delivery.project.projects.partials.modal-delivery-info')
@endif
@if($can('delivery-project.delivery-data.edit'))
    @include('delivery.project.projects.partials.modal-delivery-data')
@endif
@if($can('delivery-project.location.edit'))
    @include('delivery.project.projects.partials.modal-location-info')
@endif

{{-- Team Modal --}}
<div id="teamModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('teamModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Add Team Member</h3>
            </div>
            <form id="addTeamMemberForm" action="{{ route('projects.team.store', $project->id) }}" method="POST">
                @csrf
                <div class="modal-body p-6 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Consultant</label>
                            <select name="employee_id" id="employee_id" required data-searchable="true"
                                    class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                                <option value="">-- Select Employee --</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->employee_id }}"
                                            data-department="{{ $employee->basicData->department ?? '' }}"
                                            data-whatsapp="{{ $employee->addresses->first()->cell_phone ?? '' }}"
                                            data-email="{{ $employee->addresses->first()->email_work ?? '' }}">
                                        {{ $employee->basicData->full_name ?? 'N/A' }}
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
                            <label class="block text-sm font-medium text-gray-900 mb-1">Role <span class="text-red-500">*</span></label>
                            <select name="role" required
                                    class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                                <option value="">-- Select Role --</option>
                                <option value="Project Manager">Project Manager</option>
                                <option value="Co Project Manager">Co Project Manager</option>
                                <option value="Project Admin">Project Admin</option>
                                <option value="Lead">Lead</option>
                                <option value="Member">Member</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">WhatsApp</label>
                            <input type="text" id="whatsapp_number" readonly
                                   class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Employee Type <span class="text-red-500">*</span></label>
                            <select name="employee_type" id="employee_type" required
                                    class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                    onchange="toggleVendorName('vendor_name_wrap', this.value)">
                                <option value="Internal">Internal</option>
                                <option value="External">External</option>
                                <option value="Vendor">Vendor</option>
                            </select>
                        </div>
                        <div id="vendor_name_wrap" style="display:none;">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Vendor Name</label>
                            <input type="text" name="vendor_name" id="vendor_name"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                   placeholder="Vendor name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Start Date <span class="text-red-500">*</span></label>
                            <input type="text" name="start_date" id="add_start_date" required readonly
                                   placeholder="Select Start Date"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus cursor-pointer">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">End Date <span class="text-xs text-gray-400 font-normal">— optional</span></label>
                            <input type="text" name="end_date" id="add_end_date" readonly
                                   placeholder="Select End Date"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus cursor-pointer">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Notes <span class="text-xs text-gray-400 font-normal">— optional</span></label>
                            <textarea name="notes" id="add_notes" rows="2"
                                      class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                      placeholder="Additional notes for this assignment…"></textarea>
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
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Edit Team Member</h3>
                <button type="button" onclick="closeModal('editTeamModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="editTeamMemberForm" method="POST">
                @csrf
                @method('PUT')
                {{-- Hidden: menyimpan old_role untuk identifikasi baris pivot --}}
                <input type="hidden" id="edit_old_role" name="old_role">
                <div class="modal-body p-6 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Consultant (disabled) --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Consultant</label>
                            <input type="text" id="edit_employee_name_display" disabled
                                   class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        {{-- Module (disabled) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Module</label>
                            <input type="text" id="edit_module_display" disabled
                                   class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        {{-- Role (editable) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Role <span class="text-red-500">*</span></label>
                            <select name="role" id="edit_role" required
                                    class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                                <option value="">-- Select Role --</option>
                                <option value="Project Manager">Project Manager</option>
                                <option value="Co Project Manager">Co Project Manager</option>
                                <option value="Project Admin">Project Admin</option>
                                <option value="Lead">Lead</option>
                                <option value="Member">Member</option>
                            </select>
                        </div>
                        {{-- Employee Type (disabled) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Employee Type</label>
                            <input type="text" id="edit_emptype_display" disabled
                                   class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        {{-- Vendor Name (disabled, conditional) --}}
                        <div id="edit_vendor_wrap" style="display:none;">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Vendor Name</label>
                            <input type="text" id="edit_vendor_display" disabled
                                   class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        {{-- Start Date (disabled) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Start Date</label>
                            <input type="text" id="edit_startdate_display" disabled
                                   class="block w-full py-2.5 px-3 border border-gray-200 rounded-md shadow-sm text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        {{-- End Date (editable) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">
                                End Date <span class="text-xs text-gray-400 font-normal">— optional</span>
                            </label>
                            <input type="text" name="end_date" id="edit_end_date" readonly
                                   placeholder="Select End Date"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus cursor-pointer">
                        </div>
                        {{-- Notes (editable) --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-900 mb-1">
                                Notes <span class="text-xs text-gray-400 font-normal">— optional</span>
                            </label>
                            <textarea name="notes" id="edit_notes" rows="2"
                                      class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                                      placeholder="Change notes or additional information…"></textarea>
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

{{-- Role Edit Modal --}}
<div id="roleModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('roleModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900" id="roleModalTitle">Edit Role</h3>
            </div>
            <form id="roleModalForm" action="{{ route('projects.updateDeliveryInfo', $project->id) }}" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" id="roleFieldInput" name="">
                <div class="p-6">
                    <label class="block text-sm font-medium text-gray-900 mb-2">Employee</label>
                    <select id="roleEmployeeSelect"
                            class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus">
                        <option value="">-- Not assigned --</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? '-' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('roleModal')"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Upload Document Modal --}}
<div id="documentModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('documentModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl w-full max-w-lg">
            {{-- Header --}}
            <div class="primary-gradient px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">Upload Document</h3>
                    <button type="button" onclick="closeModal('documentModal')" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            {{-- Body --}}
            <div class="px-6 py-5 space-y-4">
                {{-- Destination breadcrumb --}}
                <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3">
                    <p class="text-xs font-semibold text-blue-500 uppercase tracking-wide mb-1.5">Tujuan Upload</p>
                    <div class="flex items-center gap-1.5 flex-wrap text-sm font-medium text-blue-800">
                        <span>DELIVERY PROJECT</span>
                        <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span>{{ strtoupper($project->client->basicData->name_1 ?? 'CUSTOMER') }}</span>
                        <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span>{{ $project->name }}</span>
                    </div>
                </div>
                {{-- File drop zone --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        File <span class="text-red-500">*</span>
                    </label>
                    <div id="docDropZone"
                         class="border-2 border-dashed border-gray-300 rounded-lg py-7 px-4 text-center cursor-pointer hover:border-red-300 hover:bg-red-50/30 transition-all duration-200"
                         onclick="document.getElementById('docFileInput').click()"
                         ondragover="event.preventDefault();this.classList.add('border-red-400','bg-red-50/40')"
                         ondragleave="this.classList.remove('border-red-400','bg-red-50/40')"
                         ondrop="handleDocFileDrop(event)">
                        <svg class="w-9 h-9 text-gray-300 mx-auto mb-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-sm font-medium text-gray-500" id="docDropLabel">Click or drag &amp; drop file here</p>
                        <input type="file" id="docFileInput" class="hidden" onchange="onDocFileSelected(this, 'docDropLabel')">
                    </div>
                </div>
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Document Name
                        <span class="text-xs font-normal text-gray-400 ml-1">(optional — default: file name)</span>
                    </label>
                    <input type="text" id="doc_name"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm primary-focus text-sm"
                           placeholder="Leave empty to use the file name">
                </div>
                {{-- Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Type <span class="text-red-500">*</span></label>
                    <div class="custom-dd relative" data-fixed="true" data-onchange="onDocTypeChange" data-searchable="true">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">-- Select Type --</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="doc_type" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:280px;">
                            <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search document type…" autocomplete="off" spellcheck="false">
                            </div>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Select Type --</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="TOR">TOR</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="KAK">KAK</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Surat Pengikatan">Surat Pengikatan</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="BA Negosiasi">BA Negosiasi</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Surat Penetapan">Surat Penetapan</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="SPK / SPMK">SPK / SPMK</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Kontrak">Kontrak</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="PO">PO</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Others">Others</button>
                            <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                        </div>
                    </div>
                    <div id="doc_others_wrap" class="hidden mt-2 pl-3 border-l-2 border-red-200">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Specify document type <span class="text-red-500">*</span></label>
                        <input type="text" id="doc_others_text"
                               class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm primary-focus text-sm"
                               placeholder="e.g. MOM, SOW, Timeline...">
                    </div>
                </div>
                {{-- Progress indicator --}}
                <div id="docUploadProgress" class="hidden">
                    <div class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg px-4 py-2.5">
                        <svg class="animate-spin w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span id="docUploadLabel">Uploading to OneDrive...</span>
                    </div>
                </div>
            </div>
            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeModal('documentModal')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                    Cancel
                </button>
                <button type="button" id="docUploadBtn" onclick="submitDocumentUpload()"
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Upload
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Issue Modal --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- ISSUE LOG — DELETE CONFIRMATION MODAL                          --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="issueDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="IssueLog.closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete <span id="issueDeleteLabel"></span>?</h3>
                <p class="text-sm text-gray-500 mb-5">This issue will be permanently deleted.</p>
                <input type="hidden" id="issueDeleteId" value="">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="IssueLog.closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="issueDeleteConfirmBtn" onclick="IssueLog.confirmDelete()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- ISSUE LOG — ADD / EDIT MODAL                                   --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="issueModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="IssueLog.closeModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900" id="issueModalTitle">Add Issue</h3>
                <button type="button" onclick="IssueLog.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto space-y-4">
                <input type="hidden" id="issueModalMode" value="create">
                <input type="hidden" id="issueModalId" value="">

                {{-- Issue Description --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issue Description <span class="text-red-500">*</span></label>
                    <textarea id="issue_description" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="Describe the issue found…"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    {{-- Module --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Module</label>
                        <input type="text" id="issue_module" maxlength="100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="e.g. FI, MM, SD">
                    </div>

                    {{-- Project Risk ID (optional) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project Risk ID</label>
                        <select id="issue_risk_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">— None —</option>
                        </select>
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="issue_status" onchange="IssueLog.onStatusChange()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    {{-- Priority --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority <span class="text-red-500">*</span></label>
                        <select id="issue_priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="High">High (H)</option>
                            <option value="Medium" selected>Medium (M)</option>
                            <option value="Low">Low (L)</option>
                        </select>
                    </div>

                    {{-- Originator --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Originator <span class="text-red-500">*</span></label>
                        <select id="issue_originator" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">-- Select Originator --</option>
                            @foreach($teamPeople as $person)
                                <option value="{{ $person }}">{{ $person }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Owner --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Owner <span class="text-red-500">*</span></label>
                        <select id="issue_owner" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">-- Select Owner --</option>
                            @foreach($teamPeople as $person)
                                <option value="{{ $person }}">{{ $person }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date Identified --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Identified <span class="text-red-500">*</span></label>
                        <input type="text" id="issue_date_identified" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="dd/mm/yyyy">
                    </div>

                    {{-- Estimated Closed --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Closed</label>
                        <input type="text" id="issue_estimated_closed" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="dd/mm/yyyy">
                    </div>

                    {{-- Escalation Needed --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Escalation Needed <span class="text-red-500">*</span></label>
                        <select id="issue_escalation_needed" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="0" selected>No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    {{-- Closed Date (shown only when status = Closed) --}}
                    <div id="issue_closed_date_wrapper" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Closed Date <span class="text-red-500">*</span></label>
                        <input type="text" id="issue_closed_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="dd/mm/yyyy">
                    </div>

                </div>

                {{-- Risk To Project --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Risk To Project</label>
                    <textarea id="issue_risk_to_project" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="What this issue puts at risk for the project…"></textarea>
                </div>

                {{-- Impact of Issue --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Impact of Issue</label>
                    <textarea id="issue_impact" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="Impact this issue has on the project…"></textarea>
                </div>

                {{-- Tracking Comments --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Comments</label>
                    <textarea id="issue_tracking_comments" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                              placeholder="Progress notes / tracking comments…"></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="IssueLog.closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="issueModalSaveBtn" onclick="IssueLog.save()"
                        class="px-4 py-2 text-sm font-semibold text-white primary-gradient rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

@if($can('delivery-project.wricef.view'))
{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- WRICEF LOG — DELETE CONFIRMATION MODAL                         --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="wricefDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="WricefLog.closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete <span id="wricefDeleteLabel"></span>?</h3>
                <p class="text-sm text-gray-500 mb-5">This WRICEF object will be permanently deleted.</p>
                <input type="hidden" id="wricefDeleteId" value="">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="WricefLog.closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="wricefDeleteConfirmBtn" onclick="WricefLog.confirmDelete()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- WRICEF LOG — ADD / EDIT MODAL                                  --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PITFALL: modal dirender DI LUAR <section>, jadi lapisan izin
     `data-perm-*` tidak menjangkaunya. Kontrol tulis di sini harus
     dipagari @if($can(...)) sendiri (lihat delivery/partials/section-permissions). --}}
@php
    // Team Members section hanya dirender bila punya izin `.view`, sedangkan
    // $teamPeople didefinisikan di dalamnya — jaga-jaga bila izin itu dicabut.
    $wricefPeople  = $teamPeople ?? collect();
    $wricefCompany = $project->client->basicData->name_1 ?? '—';
@endphp
<div id="wricefModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="WricefLog.closeModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900" id="wricefModalTitle">Add WRICEF</h3>
                <button type="button" onclick="WricefLog.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto space-y-5">
                <input type="hidden" id="wricefModalMode" value="create">
                <input type="hidden" id="wricefModalId" value="">

                {{-- ── Blok 1: identitas objek ─────────────────────────── --}}
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Object</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {{-- Company — mengikuti customer project, tidak disimpan per baris --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                            <input type="text" value="{{ $wricefCompany }}" readonly
                                   class="w-full px-3 py-2 border border-gray-200 bg-gray-50 text-gray-600 rounded-lg text-sm cursor-not-allowed">
                        </div>

                        {{-- SAP Module --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SAP Module <span class="text-red-500">*</span></label>
                            <select id="wricef_sap_module" onchange="WricefLog.onObjIdSourceChange()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Select Module --</option>
                                @foreach(\App\Models\DeliveryProjectWricef::SAP_MODULES as $mod)
                                    <option value="{{ $mod }}">{{ $mod }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Category --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                            <select id="wricef_category" onchange="WricefLog.onObjIdSourceChange()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Select Category --</option>
                                @foreach(array_keys(\App\Models\DeliveryProjectWricef::CATEGORY_LETTERS) as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Obj ID — dibuat otomatis oleh server --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Obj ID</label>
                            <input type="text" id="wricef_obj_id_preview" readonly
                                   class="w-full px-3 py-2 border border-gray-200 bg-gray-50 text-gray-600 rounded-lg text-sm font-mono cursor-not-allowed"
                                   placeholder="Auto">
                            <p class="text-[11px] text-gray-400 mt-1">Otomatis dari Module + Category.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Obj Name <span class="text-red-500">*</span></label>
                            <input type="text" id="wricef_obj_name" maxlength="255"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="e.g. Sinkronisasi data master ke aplikasi eksternal">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">TCode</label>
                            <input type="text" id="wricef_tcode" maxlength="100"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="e.g. ZXX001">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Capability</label>
                        <textarea id="wricef_capability" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                                  placeholder="Satu kemampuan per baris, mis.&#10;Otomatis membuat dokumen A&#10;Otomatis memperbarui status B"></textarea>
                    </div>
                </div>

                {{-- ── Blok 2: request & approval ──────────────────────── --}}
                <div class="pt-4 border-t border-gray-100">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Request &amp; Approval</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority <span class="text-red-500">*</span></label>
                            <select id="wricef_priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Requestor</label>
                            <input type="text" id="wricef_requestor" maxlength="150"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="e.g. Budi Santoso">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Request Date</label>
                            <input type="text" id="wricef_request_date" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Effort (Mandays)</label>
                            <input type="number" id="wricef_effort_mandays" min="0" step="0.5"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="e.g. 3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                            <select id="wricef_approved_by" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Select --</option>
                                @foreach($wricefPeople as $person)
                                    <option value="{{ $person }}">{{ $person }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Approved Date</label>
                            <input type="text" id="wricef_approved_date" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                            <select id="wricef_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="Open" selected>Open</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- ── Blok 3: FSD ─────────────────────────────────────── --}}
                <div class="pt-4 border-t border-gray-100">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">FSD</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PIC</label>
                            <select id="wricef_fsd_pic" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Select PIC --</option>
                                @foreach($wricefPeople as $person)
                                    <option value="{{ $person }}">{{ $person }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start</label>
                            <input type="text" id="wricef_fsd_start" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End</label>
                            <input type="text" id="wricef_fsd_end" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="wricef_fsd_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Not Started --</option>
                                @foreach(\App\Models\DeliveryProjectWricef::FSD_STATUSES as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea id="wricef_fsd_remarks" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                                  placeholder="Catatan tahap FSD…"></textarea>
                    </div>
                </div>

                {{-- ── Blok 4: Development ─────────────────────────────── --}}
                <div class="pt-4 border-t border-gray-100">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Development</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PIC</label>
                            <select id="wricef_dev_pic" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Select PIC --</option>
                                @foreach($wricefPeople as $person)
                                    <option value="{{ $person }}">{{ $person }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start</label>
                            <input type="text" id="wricef_dev_start" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End</label>
                            <input type="text" id="wricef_dev_end" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="wricef_dev_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Not Started --</option>
                                @foreach(\App\Models\DeliveryProjectWricef::DEV_STATUSES as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea id="wricef_dev_remarks" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                                  placeholder="Catatan tahap Development…"></textarea>
                    </div>
                </div>

                {{-- ── Blok 5: Testing ─────────────────────────────────── --}}
                <div class="pt-4 border-t border-gray-100">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Testing</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PIC</label>
                            <select id="wricef_test_pic" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Select PIC --</option>
                                @foreach($wricefPeople as $person)
                                    <option value="{{ $person }}">{{ $person }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start</label>
                            <input type="text" id="wricef_test_start" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End</label>
                            <input type="text" id="wricef_test_end" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                   placeholder="dd/mm/yyyy">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="wricef_test_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                <option value="">-- Not Started --</option>
                                @foreach(\App\Models\DeliveryProjectWricef::TEST_STATUSES as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea id="wricef_test_remarks" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                                  placeholder="Catatan tahap Testing…"></textarea>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="WricefLog.closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                @if($can('delivery-project.wricef.edit') || $can('delivery-project.wricef.manage'))
                <button type="button" id="wricefModalSaveBtn" onclick="WricefLog.save()"
                        class="px-4 py-2 text-sm font-semibold text-white primary-gradient rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    Save
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- Edit Document Modal --}}
<div id="editDocumentModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('editDocumentModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl w-full max-w-lg">
            {{-- Header --}}
            <div class="primary-gradient px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">Edit Dokumen</h3>
                    <button type="button" onclick="closeModal('editDocumentModal')" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <form id="editDocumentForm">
                <input type="hidden" id="edit_document_id">
                <div class="px-6 py-5 space-y-4">
                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Document Name <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_document_name" required
                               class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                    </div>
                    {{-- Type --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Document Type <span class="text-red-500">*</span></label>
                        <select id="edit_document_type" required
                                class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm primary-focus text-sm"
                                onchange="toggleOthersInput(this.value, 'edit_doc_others_wrap')">
                            <option value="">-- Select Type --</option>
                            <option value="TOR">TOR</option>
                            <option value="KAK">KAK</option>
                            <option value="Surat Pengikatan">Surat Pengikatan</option>
                            <option value="BA Negosiasi">BA Negosiasi</option>
                            <option value="Surat Penetapan">Surat Penetapan</option>
                            <option value="SPK / SPMK">SPK / SPMK</option>
                            <option value="Kontrak">Kontrak</option>
                            <option value="PO">PO</option>
                            <option value="Others">Others</option>
                        </select>
                        <div id="edit_doc_others_wrap" class="hidden mt-2 pl-3 border-l-2 border-red-200">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Specify document type <span class="text-red-500">*</span></label>
                            <input type="text" id="edit_doc_others_text"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm primary-focus text-sm"
                                   placeholder="e.g. MOM, SOW, Timeline...">
                        </div>
                    </div>
                    {{-- Replace file (optional) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Replace File
                            <span class="text-xs font-normal text-gray-400 ml-1">(optional — leave empty to keep the current file)</span>
                        </label>
                        <div id="editDocDropZone"
                             class="border-2 border-dashed border-gray-300 rounded-lg py-5 px-4 text-center cursor-pointer hover:border-red-300 hover:bg-red-50/30 transition-all duration-200"
                             onclick="document.getElementById('edit_doc_file').click()"
                             ondragover="event.preventDefault();this.classList.add('border-red-400','bg-red-50/40')"
                             ondragleave="this.classList.remove('border-red-400','bg-red-50/40')"
                             ondrop="handleEditDocFileDrop(event)">
                            <svg class="w-7 h-7 text-gray-300 mx-auto mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            <p class="text-sm text-gray-400" id="editDocDropLabel">Click or drag &amp; drop replacement file</p>
                            <input type="file" id="edit_doc_file" class="hidden" onchange="onDocFileSelected(this, 'editDocDropLabel')">
                        </div>
                    </div>
                    {{-- Save progress --}}
                    <div id="editDocSaveProgress" class="hidden">
                        <div class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg px-4 py-2.5">
                            <svg class="animate-spin w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span>Saving changes...</span>
                        </div>
                    </div>
                </div>
                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('editDocumentModal')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="editDocSaveBtn"
                            class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- No OneDrive Folder Warning Modal --}}
<div id="noFolderWarningModal" class="hidden fixed inset-0 z-[60] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeNoFolderWarning()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-sm w-full z-10 overflow-hidden">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 mb-1">OneDrive Folder Not Created Yet</h3>
                        <p class="text-sm text-gray-500">
                            Documents will be saved to OneDrive. Please create a project folder first to enable uploads.
                        </p>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-6 flex justify-end gap-2">
                <button onclick="closeNoFolderWarning()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                    Cancel
                </button>
                <button onclick="closeNoFolderWarning(); openOneDriveModal();"
                        class="px-4 py-2 text-sm font-semibold text-white primary-gradient rounded-lg hover:opacity-90 transition-all inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    Create Folder Now
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Folder Confirmation Modal --}}
<div id="deleteFolderConfirmModal" class="hidden fixed inset-0 z-[60] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeDeleteFolderModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-sm w-full z-10 overflow-hidden">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 mb-1">Delete OneDrive Folder?</h3>
                        <p class="text-sm text-gray-500">
                            The folder and <strong>all its contents</strong> will be permanently deleted from OneDrive and cannot be recovered.
                        </p>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-6 flex justify-end gap-2">
                <button onclick="closeDeleteFolderModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                    Cancel
                </button>
                <button id="confirmDeleteFolderBtn"
                        class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-all inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete Folder
                </button>
            </div>
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
                        with the following structure:
                    </p>
                    {{-- Path Preview --}}
                    <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm">
                        <p class="text-xs font-medium text-blue-500 uppercase mb-1">Folder Location</p>
                        <div class="flex items-center gap-1 flex-wrap text-blue-800 font-medium">
                            <span>DELIVERY PROJECT</span>
                            <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span>{{ strtoupper($project->client->basicData->name_1 ?? 'CUSTOMER') }}</span>
                            <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span id="odrPathFolderName" class="text-blue-900">{{ $project->name ?? 'Project-' . $project->id }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Project Folder Name <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <input type="text" id="odrFolderName"
                               value="{{ $project->name ?? 'Project-' . $project->id }}"
                               oninput="document.getElementById('odrPathFolderName').textContent = this.value || '{{ $project->name ?? 'Project-' . $project->id }}'"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-400 mt-1">Subfolder name to be created inside the customer folder.</p>
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

@if($can('delivery-project.close-project'))
{{-- Confirm modal untuk Close / Reopen Project (pengganti confirm() native) --}}
{{-- data-lock-exempt: satu-satunya modal yang harus tetap aktif saat project
     closed — dari sinilah project di-Reopen. --}}
<div id="projectStateModal" data-lock-exempt class="fixed inset-0 z-[9998] hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 z-[9998]" onclick="closeModal('projectStateModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 z-[9999]">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-md w-full relative">
            <div class="p-6">
                <h3 id="projectStateTitle" class="text-lg font-semibold text-gray-900 mb-2">Close Project?</h3>
                <p id="projectStateMessage" class="text-sm text-gray-600">This project will become read-only until it is reopened.</p>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('projectStateModal')"
                        class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    Cancel
                </button>
                <button type="button" id="projectStateConfirmBtn"
                        class="inline-flex items-center px-4 py-2 bg-gray-700 text-white text-sm font-semibold rounded-lg hover:bg-gray-800 transition-all duration-200">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    // Modal konfirmasi Close/Reopen — submit form tersembunyi saat dikonfirmasi.
    let _projectStateForm = null;
    function openProjectStateModal(mode) {
        const title = document.getElementById('projectStateTitle');
        const msg   = document.getElementById('projectStateMessage');
        const btn   = document.getElementById('projectStateConfirmBtn');
        if (mode === 'reopen') {
            _projectStateForm = document.getElementById('reopenProjectForm');
            title.textContent = 'Reopen Project?';
            msg.textContent   = 'This project will become editable again. Its status will be recalculated from planning progress.';
            btn.textContent   = 'Reopen Project';
            btn.className      = 'inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all duration-200';
        } else {
            _projectStateForm = document.getElementById('closeProjectForm');
            title.textContent = 'Close Project?';
            msg.textContent   = 'This project will become read-only until it is reopened.';
            btn.textContent   = 'Close Project';
            btn.className      = 'inline-flex items-center px-4 py-2 bg-gray-700 text-white text-sm font-semibold rounded-lg hover:bg-gray-800 transition-all duration-200';
        }
        document.getElementById('projectStateModal').classList.remove('hidden');
    }
    document.getElementById('projectStateConfirmBtn')?.addEventListener('click', function () {
        if (_projectStateForm) _projectStateForm.submit();
    });
</script>
@endif

{{-- ✅ LOAD SCRIPTS (IN CORRECT ORDER) --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

{{-- ===== Sales Financial: Thousand Separator + Auto-Calculation ===== --}}
<script>
(function () {
    function fmtRp(n) {
        const neg = n < 0;
        const abs = Math.abs(Math.round(n));
        return (neg ? '-' : '') + abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function parseNum(str) {
        if (str === '' || str === null || str === undefined) return 0;
        const s = String(str).trim();
        // Indonesian display format with comma decimal (e.g. "1.000.000,50")
        if (s.includes(',')) {
            return parseFloat(s.replace(/\./g, '').replace(',', '.')) || 0;
        }
        // Indonesian thousands-dot format with multiple dots (e.g. "1.000.000.000")
        const dotCount = (s.match(/\./g) || []).length;
        if (dotCount > 1) {
            return parseFloat(s.replace(/\./g, '')) || 0;
        }
        // Raw DB value: standard integer or decimal (e.g. "1000000000" or "1000000000.00")
        return parseFloat(s) || 0;
    }
    function sfinRecalc() {
        const rev  = parseNum(document.getElementById('sfin_rev_val')?.value);
        const pc   = parseNum(document.getElementById('sfin_pc_val')?.value);
        const gp   = rev - pc;
        const pct  = (rev !== 0) ? (gp / rev) * 100 : 0;

        const gpVal   = document.getElementById('sfin_gp_val');
        const pctVal  = document.getElementById('sfin_pct_val');
        const gpDisp  = document.getElementById('sfin_gp_disp');
        const pctDisp = document.getElementById('sfin_pct_disp');

        if (gpVal)   gpVal.value   = gp;
        if (pctVal)  pctVal.value  = pct.toFixed(2);
        if (gpDisp)  gpDisp.value  = fmtRp(gp);
        if (pctDisp) pctDisp.value = pct.toFixed(2).replace('.', ',');

        // Actual GP juga bergantung pada Revenue → ikut dihitung ulang.
        sfinRecalcActual();
    }
    // Actual Cost = Total Actual (dari expense detail Plan Cost).
    // Actual Gross Profit = Revenue − Actual Cost; % = Actual GP / Revenue × 100.
    function sfinRecalcActual() {
        const rev = parseNum(document.getElementById('sfin_rev_val')?.value);
        const ac  = parseNum(document.getElementById('sfin_ac_val')?.value);
        const agp = rev - ac;
        const apct = (rev !== 0) ? (agp / rev) * 100 : 0;

        const agpVal   = document.getElementById('sfin_agp_val');
        const apctVal  = document.getElementById('sfin_apct_val');
        const acDisp   = document.getElementById('sfin_ac_disp');
        const agpDisp  = document.getElementById('sfin_agp_disp');
        const apctDisp = document.getElementById('sfin_apct_disp');

        if (agpVal)   agpVal.value   = agp;
        if (apctVal)  apctVal.value  = apct.toFixed(2);
        if (acDisp)   acDisp.value   = fmtRp(ac);
        if (agpDisp)  agpDisp.value  = fmtRp(agp);
        if (apctDisp) apctDisp.value = apct.toFixed(2).replace('.', ',');
    }
    // Dipanggil oleh section Plan Cost saat "Total Actual" berubah agar
    // Delivery Information → Actual Cost / GP / % tetap sinkron tanpa reload.
    window.sfinSetActualCost = function (actualCost) {
        const acVal = document.getElementById('sfin_ac_val');
        if (!acVal) return;
        acVal.value = actualCost ?? 0;
        sfinRecalcActual();
    };
    function bindInput(dispId, valId) {
        const disp = document.getElementById(dispId);
        const val  = document.getElementById(valId);
        if (!disp || !val) return;
        disp.addEventListener('input', function () {
            const raw  = this.value.replace(/[^0-9]/g, '');
            this.value = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
            val.value  = raw || '';
            sfinRecalc();
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        const revVal  = document.getElementById('sfin_rev_val');
        const pcVal   = document.getElementById('sfin_pc_val');
        const revDisp = document.getElementById('sfin_rev_disp');
        const pcDisp  = document.getElementById('sfin_pc_disp');
        if (!revVal) return;

        // Inisialisasi display dari nilai existing project
        if (revVal.value)  revDisp.value = fmtRp(parseFloat(revVal.value) || 0);
        if (pcVal.value)   pcDisp.value  = fmtRp(parseFloat(pcVal.value)  || 0);
        sfinRecalc();

        bindInput('sfin_rev_disp', 'sfin_rev_val');
        bindInput('sfin_pc_disp',  'sfin_pc_val');
    });
})();
</script>

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
    // Delivery Data section tersendiri tapi berbagi tab "Delivery Info", jadi
    // dinormalkan di sini — berlaku baik saat dipanggil scrollToSection maupun
    // scroll-spy.
    if (sectionId === 'delivery-data') sectionId = 'delivery';
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
            let sectionId = entry.target.id;
            // Delivery Data is its own section but shares the "Delivery Info" tab.
            if (sectionId === 'delivery-data') sectionId = 'delivery';
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
    document: new Set()
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
    const deleteBtn = document.getElementById('toolbarDeleteBtn');

    if (count > 0) {
        toolbar.classList.add('show');
        countSpan.textContent = `${count} item${count > 1 ? 's' : ''} selected`;
        currentType = type;
        // Team members cannot be deleted — only edited (role/end_date/notes)
        if (deleteBtn) {
            deleteBtn.style.display = (type === 'team') ? 'none' : '';
        }
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
            let fetchOptions = {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            };

            if (currentType === 'document') {
                url = `/project/documents/${id}/delete`;
            } else {
                // Team members cannot be deleted — delete button is hidden for type 'team'
                continue;
            }

            const response = await fetch(url, fetchOptions);

            if (response.ok) {
                // Remove the specific row that was selected
                const cb = document.querySelector(`.${currentType}-checkbox[data-id="${id}"]`);
                if (cb) cb.closest('tr')?.remove();
            }
        } catch (error) {
            console.error('Delete error:', error);
        }
    }

    showNotification(`Successfully deleted ${selectedIds.length} item(s)`, 'success');
    clearAllSelections();
    closeModal('deleteModal');
    setTimeout(() => location.reload(), 800);
}

// ============================================
// EDIT MODAL FUNCTIONS
// ============================================
function openEditDocumentModal(checkbox) {
    document.getElementById('edit_document_id').value   = checkbox.dataset.id;
    document.getElementById('edit_document_name').value = checkbox.dataset.name;

    // Populate type — handle "Others: xxx" values stored in DB
    const rawType    = checkbox.dataset.type || '';
    const knownTypes = ['TOR', 'KAK', 'Surat Pengikatan', 'BA Negosiasi', 'Surat Penetapan', 'SPK / SPMK', 'Kontrak', 'PO', 'Others'];
    const selectEl   = document.getElementById('edit_document_type');
    if (rawType === '') {
        // No type set — show placeholder so user must choose
        selectEl.value = '';
        document.getElementById('edit_doc_others_text').value = '';
    } else if (knownTypes.includes(rawType)) {
        selectEl.value = rawType;
        document.getElementById('edit_doc_others_text').value = '';
    } else {
        // Custom / legacy type stored in DB — map to "Others" + show the text
        selectEl.value = 'Others';
        document.getElementById('edit_doc_others_text').value = rawType;
    }
    toggleOthersInput(selectEl.value, 'edit_doc_others_wrap');

    // Reset file input & drop zone label
    const fileInput = document.getElementById('edit_doc_file');
    if (fileInput) fileInput.value = '';
    const label = document.getElementById('editDocDropLabel');
    if (label) { label.textContent = 'Click or drag & drop replacement file'; label.className = 'text-sm text-gray-400'; }

    openModal('editDocumentModal');
}

function toggleOthersInput(value, wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    const show = value === 'Others';
    wrap.classList.toggle('hidden', !show);
    const input = wrap.querySelector('input');
    if (input) input.required = show;
}

function onDocTypeChange() {
    const val = document.getElementById('doc_type').value;
    toggleOthersInput(val, 'doc_others_wrap');
}

function toggleVendorName(wrapId, type) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    const show = type === 'Vendor';
    wrap.style.display = show ? '' : 'none';
    const input = wrap.querySelector('input[name="vendor_name"]');
    if (input) input.required = show;
}

// Auto-fill Position field when Consultant select changes in edit modal
function updateEditPosition(select) {
    const opt = select.options[select.selectedIndex];
    const posEl = document.getElementById('edit_position_display');
    if (posEl) posEl.textContent = opt?.dataset?.position || '—';
}

function openEditTeamMemberModal(checkbox) {
    // data-id is now a composite "employee_id::role" key used for row tracking.
    // For the URL we need the plain employee_id stored in data-employee-id.
    const employeeId   = checkbox.dataset.employeeId || checkbox.dataset.id;
    const employeeName = checkbox.dataset.employeeName  || '-';
    const position     = checkbox.dataset.position      || '';
    const module       = checkbox.dataset.module        || '';
    const role         = checkbox.dataset.role          || '';
    const employeeType = checkbox.dataset.employeeType  || 'Internal';
    const vendorName   = checkbox.dataset.vendorName    || '';
    const startDate    = checkbox.dataset.startDate     || '';
    const endDate      = checkbox.dataset.endDate       || '';
    const notes        = checkbox.dataset.notes         || '';

    // Set form action URL (employee ID di URL)
    document.getElementById('editTeamMemberForm').action = `/projects/{{ $project->id }}/team-members/${employeeId}`;

    // Simpan old_role untuk identifikasi baris pivot di server
    document.getElementById('edit_old_role').value = role;

    // Populate disabled fields
    document.getElementById('edit_employee_name_display').value = employeeName;
    document.getElementById('edit_module_display').value  = module || '—';
    document.getElementById('edit_emptype_display').value = employeeType;

    // Vendor name: tampilkan field hanya jika ada
    const vendorWrap = document.getElementById('edit_vendor_wrap');
    if (vendorName) {
        document.getElementById('edit_vendor_display').value = vendorName;
        vendorWrap.style.display = 'block';
    } else {
        vendorWrap.style.display = 'none';
    }

    // Format start date untuk display
    if (startDate) {
        try {
            const d = new Date(startDate + 'T00:00:00');
            document.getElementById('edit_startdate_display').value =
                d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        } catch(e) {
            document.getElementById('edit_startdate_display').value = startDate;
        }
    } else {
        document.getElementById('edit_startdate_display').value = '—';
    }

    // Set role langsung (native <select>, tidak butuh rAF)
    const roleEl = document.getElementById('edit_role');
    if (roleEl) roleEl.value = role;

    // Set end date (editable)
    if (window._fpEditEnd) window._fpEditEnd.setDate(endDate || '', false, 'Y-m-d');
    else document.getElementById('edit_end_date').value = endDate || '';

    // Set notes (editable)
    document.getElementById('edit_notes').value = notes;

    // Buka modal
    openModal('editTeamModal');
}

// Edit Team Member Form Submit
// Hanya mengirim: old_role (identifikasi), role (baru), end_date, notes
document.getElementById('editTeamMemberForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const role = formData.get('role');
    if (!role) {
        showNotification('Please select a role first.', 'error');
        return;
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                _method:  'PUT',
                old_role: formData.get('old_role'),
                role:     role,
                end_date: formData.get('end_date') || null,
                notes:    formData.get('notes')    || null,
            })
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('editTeamModal');
            clearAllSelections();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to update team member.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred while updating team member.', 'error');
    }
});

// ============================================
// ADD TEAM MEMBER (AJAX — form POST biasa tidak beri notifikasi instan)
// ============================================
document.getElementById('addTeamMemberForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form    = e.target;
    const btn     = form.querySelector('button[type="submit"]');
    const origTxt = btn ? btn.innerHTML : '';

    if (btn) {
        btn.disabled  = true;
        btn.innerHTML = `<svg class="animate-spin h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Adding…`;
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: new FormData(form),
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message || 'Team member added successfully!', 'success');
            closeModal('teamModal');
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification(data.message || 'Failed to add team member.', 'error');
        }
    } catch (err) {
        console.error('Error adding team member:', err);
        showNotification('An error occurred. Please try again.', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = origTxt; }
    }
});

// Edit Document Form Submit
document.getElementById('editDocumentForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const documentId = document.getElementById('edit_document_id').value;
    const saveBtn    = document.getElementById('editDocSaveBtn');
    const progress   = document.getElementById('editDocSaveProgress');
    const fileInput  = document.getElementById('edit_doc_file');

    saveBtn.disabled = true;
    progress.classList.remove('hidden');

    // Use FormData + POST with _method=PATCH so Laravel accepts file uploads
    const fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    fd.append('_method', 'PATCH');
    const _editTypeSelect = document.getElementById('edit_document_type');
    const _editOthersText = document.getElementById('edit_doc_others_text');
    const _editDocType    = _editTypeSelect.value === 'Others'
        ? (_editOthersText.value.trim() || 'Others')
        : _editTypeSelect.value;

    if (_editTypeSelect.value === 'Others' && !_editOthersText.value.trim()) {
        showNotification('Please specify the document type for Others.', 'error');
        _editOthersText.focus();
        saveBtn.disabled = false;
        progress.classList.add('hidden');
        return;
    }

    fd.append('document_name', document.getElementById('edit_document_name').value);
    fd.append('document_type', _editDocType);
    if (fileInput && fileInput.files[0]) {
        const maxSize = 100 * 1024 * 1024;
        if (fileInput.files[0].size > maxSize) {
            showNotification('File size must not exceed 100 MB.', 'error');
            saveBtn.disabled = false;
            progress.classList.add('hidden');
            return;
        }
        fd.append('file', fileInput.files[0]);
    }

    try {
        const response = await fetch(`/project/documents/${documentId}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd,
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message || 'Document updated successfully!', 'success');
            closeModal('editDocumentModal');
            clearAllSelections();

            // Update row in-place
            const row = document.querySelector(`[data-document-id="${documentId}"]`);
            if (row) {
                const cb = row.querySelector('.document-checkbox');
                if (cb) {
                    cb.dataset.name = data.document.document_name;
                    cb.dataset.type = data.document.document_type;
                }
                const nameEl = row.querySelector('td:nth-child(2) span');
                if (nameEl) nameEl.textContent = data.document.document_name;
                const typeEl = row.querySelector('td:nth-child(3) span');
                if (typeEl) typeEl.textContent = data.document.document_type;
                const actionTd = row.querySelector('td:nth-child(4)');
                if (actionTd && data.document.link_document) {
                    actionTd.innerHTML = `<a href="${data.document.link_document}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>Open File</a>`;
                }
            }
        } else {
            showNotification(data.message || 'Failed to update document.', 'error');
        }
    } catch (error) {
        showNotification('An error occurred. Please try again.', 'error');
    } finally {
        saveBtn.disabled = false;
        progress.classList.add('hidden');
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
// PROJECT ROLES (PM / CO PM / PROJECT ADMIN)
// ============================================
function openRoleModal(field, currentId, title) {
    document.getElementById('roleModalTitle').textContent = 'Edit ' + title;
    const input = document.getElementById('roleFieldInput');
    input.name = field;
    const select = document.getElementById('roleEmployeeSelect');
    select.value = currentId || '';
    input.value = select.value;
    openModal('roleModal');
}

document.getElementById('roleEmployeeSelect').addEventListener('change', function() {
    document.getElementById('roleFieldInput').value = this.value;
});

document.getElementById('roleModalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    // Sync hidden field with the select value before sending
    document.getElementById('roleFieldInput').value = document.getElementById('roleEmployeeSelect').value;

    const form = e.target;
    const btn  = form.querySelector('button[type="submit"]');
    const orig = btn?.innerHTML;
    const spin = `<svg class="animate-spin h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg> Saving…`;

    if (btn) { btn.disabled = true; btn.innerHTML = spin; }
    try {
        const res  = await fetch(form.action, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body:    new FormData(form),
        });
        const data = await res.json();
        if (res.ok && data.success) {
            showNotification(data.message || 'Role updated successfully.', 'success');
            closeModal('roleModal');
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification(data.message || 'Failed to update role.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        }
    } catch (err) {
        showNotification('An error occurred. Please try again.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
});

async function clearRole(field, roleName) {
    if (!confirm('Clear ' + roleName + ' assignment?')) return;
    try {
        const fd = new FormData();
        fd.append('_method', 'PATCH');
        fd.append(field, '');
        const res  = await fetch('/projects/{{ $project->id }}/delivery-info', {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body:    fd,
        });
        const data = await res.json();
        if (res.ok && data.success) {
            showNotification(data.message || roleName + ' cleared successfully.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification(data.message || 'Failed to clear ' + roleName + '.', 'error');
        }
    } catch (err) {
        showNotification('An error occurred. Please try again.', 'error');
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

let projectDeleteInProgress = false;
async function executeProjectDelete(projectId) {
    // Guard against double-submit: the confirm button is shared between flows and a
    // rapid double-click previously fired two DELETE requests — the first deleted the
    // project, the second hit an already-gone row and showed a "No query results" error.
    if (projectDeleteInProgress) return;
    projectDeleteInProgress = true;

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
        const response = await fetch(`/projects/${projectId}/delete`, {
            method: 'POST',
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

        // Restore button so the user can retry
        btn.innerHTML = originalText;
        btn.disabled = false;
        projectDeleteInProgress = false;
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = ['teamModal', 'editTeamModal', 'roleModal', 'documentModal', 'issueModal', 'issueDeleteModal', 'deleteModal', 'editDocumentModal', 'deleteFolderConfirmModal', 'noFolderWarningModal',
                        'generalInfoModal', 'deliveryInfoModal', 'deliveryDataModal', 'locationInfoModal',
                        'wricefModal', 'wricefDeleteModal'];
        modals.forEach(modalId => closeModal(modalId));
    }
});

// ============================================
// FLASH NOTIFICATIONS (DOMContentLoaded agar showNotification sudah terdefinisi)
//
// JANGAN tampilkan session('success')/('error')/('warning') di sini — layout
// dashboard.blade.php SUDAH memunculkannya untuk semua halaman. Menambahkannya
// lagi membuat toast dobel (mis. "Project closed successfully." muncul 2x).
// Yang tersisa di bawah adalah $errors (validasi), yang tidak ditangani layout.
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    @if($errors->has('error'))
    showNotification({!! json_encode($errors->first('error')) !!}, 'error');
    @endif
    @if($errors->any() && !$errors->has('error'))
    showNotification({!! json_encode($errors->first()) !!}, 'error');
    @endif
});

// ============================================
// SECTION FORM AJAX HANDLERS
// Generic helper: intercepts a plain POST/PATCH form, sends via fetch(),
// shows toast on success/error, then reloads the page on success.
// ============================================
(function() {
    const SPIN = `<svg class="animate-spin h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24">` +
        `<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>` +
        `<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z` +
        `m2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg> Saving…`;

    function attachSectionForm(formId, fallbackSuccess, fallbackError) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn  = form.querySelector('button[type="submit"]');
            const orig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = SPIN; }
            try {
                const res  = await fetch(form.getAttribute('action'), {
                    method:  'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body:    new FormData(form),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    // Form section kini hidup di dalam modal: tutup dulu supaya
                    // notifikasi & warning modal tidak tertimbun di belakangnya.
                    const host = form.closest('.fixed.inset-0');
                    if (host) { host.classList.add('hidden'); document.body.style.overflow = ''; }
                    showNotification(data.message || fallbackSuccess, 'success');
                    // A clear, acknowledged warning when the save left planning outside the
                    // contract window — shown in a styled modal (consistent with the rest of
                    // the page) instead of a native alert(). Reload happens when dismissed.
                    if (data.warning) {
                        showContractWarningModal(data.warning);
                    } else {
                        setTimeout(() => location.reload(), 800);
                    }
                } else {
                    // Show first validation error if available
                    let msg = data.message || fallbackError;
                    if (data.errors) {
                        const first = Object.values(data.errors)[0];
                        msg = Array.isArray(first) ? first[0] : String(first);
                    }
                    showNotification(msg, 'error');
                    if (btn) { btn.disabled = false; btn.innerHTML = orig; }
                }
            } catch (err) {
                showNotification('An error occurred. Please try again.', 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            }
        });
    }

    attachSectionForm('generalInfoForm',  'General information updated successfully.',  'Failed to update general information.');
    attachSectionForm('deliveryInfoForm', 'Delivery information updated successfully.', 'Failed to update delivery information.');
    attachSectionForm('deliveryDataForm', 'Delivery data updated successfully.', 'Failed to update delivery data.');
    attachSectionForm('locationInfoForm', 'Location information updated successfully.', 'Failed to update location information.');
})();

// ── Contract window warning modal (global; called from attachSectionForm + onclick) ──
// `warning` is the structured payload { count, window, items[] }. A plain string is
// also accepted as a graceful fallback (legacy / unexpected shape).
function showContractWarningModal(warning) {
    const modal = document.getElementById('contractWarningModal');
    const body  = document.getElementById('contractWarningBody');
    if (!modal || !body) {            // graceful fallback if markup missing
        alert(typeof warning === 'string' ? warning : 'Saved with warnings — please review planning.');
        setTimeout(() => location.reload(), 200);
        return;
    }

    body.innerHTML = '';

    if (typeof warning === 'string') {
        body.textContent = warning;
        body.style.whiteSpace = 'pre-line';
        modal.classList.remove('hidden');
        return;
    }

    const items = Array.isArray(warning.items) ? warning.items : [];
    const count = warning.count || items.length;
    const win   = warning.window || '';

    const head = document.createElement('p');
    head.className = 'mb-2';
    head.textContent = count + ' planning item(s) now fall OUTSIDE the contract window'
        + (win ? ' (' + win + ')' : '') + ':';
    body.appendChild(head);

    const ul = document.createElement('ul');
    ul.className = 'list-disc pl-5 space-y-1 text-gray-600';
    const PREVIEW = 5;
    items.forEach(function (it, i) {
        const li = document.createElement('li');
        li.textContent = it;
        if (i >= PREVIEW) { li.setAttribute('data-extra', '1'); li.style.display = 'none'; }
        ul.appendChild(li);
    });
    body.appendChild(ul);

    if (items.length > PREVIEW) {
        const hidden = items.length - PREVIEW;
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'mt-2 text-sm font-medium text-amber-700 hover:text-amber-800 hover:underline';
        toggle.textContent = 'Show ' + hidden + ' more';
        let expanded = false;
        toggle.addEventListener('click', function () {
            expanded = !expanded;
            ul.querySelectorAll('li[data-extra]').forEach(function (li) {
                li.style.display = expanded ? '' : 'none';
            });
            toggle.textContent = expanded ? 'Show less' : ('Show ' + hidden + ' more');
        });
        body.appendChild(toggle);
    }

    const note = document.createElement('p');
    note.className = 'text-xs text-gray-400 mt-3';
    note.textContent = 'Please review and adjust these planning items.';
    body.appendChild(note);

    modal.classList.remove('hidden');
}

function closeContractWarningModal() {
    const modal = document.getElementById('contractWarningModal');
    if (modal) modal.classList.add('hidden');
    // The data was already saved; reload so the page reflects the new contract dates.
    location.reload();
}

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

    // Sync AE Name field with the current AE Type (placeholder/dropdown/text).
    toggleAEFields();
});

// ── Account Executive (Sales Data) — konsisten dengan halaman create ──────────
// Contact map: full_name → { phone, email } untuk auto-fill saat AE Internal dipilih.
const aeEmployeeContacts = {
@foreach($employees as $employee)
@php
    $addr  = $employee->addresses->first();
    $phone = $addr?->cell_phone ?: ($addr?->telephone ?? '');
    $email = $addr?->email_work ?: ($addr?->email_personal ?? '');
    $name  = addslashes($employee->basicData->full_name ?? '-');
@endphp
    "{{ $name }}": { phone: "{{ $phone }}", email: "{{ $email }}" },
@endforeach
};

function fillAEContactInfo() {
    const name    = document.getElementById('ae_employee_hidden').value;
    const contact = aeEmployeeContacts[name] || {};
    const phoneEl = document.querySelector('input[name="ae_phone"]');
    const emailEl = document.querySelector('input[name="ae_email"]');
    if (contact.phone && phoneEl) phoneEl.value = contact.phone;
    if (contact.email && emailEl) emailEl.value = contact.email;
}

// Toggle AE Name control based on AE Type (empty / Internal / External).
function toggleAEFields() {
    const aeType      = document.getElementById('ae_type').value;
    const placeholder = document.getElementById('ae_name_placeholder');
    const ddWrapper   = document.getElementById('ae_employee_dd_wrapper');
    const aeHidden    = document.getElementById('ae_employee_hidden');
    const aeTextInput = document.getElementById('ae_name_input');
    if (!ddWrapper || !aeHidden || !aeTextInput) return;

    if (aeType === 'Internal') {
        if (placeholder) placeholder.style.display = 'none';
        ddWrapper.style.display   = 'block';
        aeHidden.name             = 'ae_name';
        aeTextInput.style.display = 'none';
        aeTextInput.name          = '';
        if (typeof initCustomDropdowns === 'function') {
            initCustomDropdowns(ddWrapper);
        }
    } else if (aeType === 'External') {
        if (placeholder) placeholder.style.display = 'none';
        ddWrapper.style.display   = 'none';
        aeHidden.name             = '';
        aeTextInput.style.display = 'block';
        aeTextInput.name          = 'ae_name';
    } else {
        // No type selected yet → AE Name disabled (placeholder only).
        if (placeholder) placeholder.style.display = 'block';
        ddWrapper.style.display   = 'none';
        aeHidden.name             = '';
        aeHidden.value            = '';
        aeTextInput.style.display = 'none';
        aeTextInput.name          = '';
        aeTextInput.value         = '';
    }
}

// ── Upload Document ───────────────────────────────────────────────────────
function closeNoFolderWarning() {
    document.getElementById('noFolderWarningModal').classList.add('hidden');
}

function openUploadDocumentModal() {
    if (!_odrHasFolder) {
        document.getElementById('noFolderWarningModal').classList.remove('hidden');
        return;
    }
    // Reset modal state
    const _dl = document.getElementById('docDropLabel');
    document.getElementById('docFileInput').value = '';
    if (_dl) { _dl.textContent = 'Click or drag & drop file here'; _dl.className = 'text-sm font-medium text-gray-500'; }
    document.getElementById('doc_name').value = '';
    setCustomDropdownValue('doc_type', '');
    document.getElementById('doc_others_text').value = '';
    document.getElementById('doc_others_wrap').classList.add('hidden');
    document.getElementById('docUploadProgress').classList.add('hidden');
    document.getElementById('docUploadBtn').disabled = false;
    openModal('documentModal');
}

function onDocFileSelected(input, labelId) {
    const label = document.getElementById(labelId || 'docDropLabel');
    if (input.files && input.files[0] && label) {
        label.textContent = input.files[0].name;
        label.classList.add('text-gray-700', 'font-medium');
        label.classList.remove('text-gray-400', 'text-gray-500');
    }
}

function _assignFileToDrop(file, inputId, labelId) {
    const input = document.getElementById(inputId);
    if (!input || !file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    onDocFileSelected(input, labelId);
}

function handleDocFileDrop(event) {
    event.preventDefault();
    document.getElementById('docDropZone').classList.remove('border-red-400', 'bg-red-50/40');
    _assignFileToDrop(event.dataTransfer.files[0], 'docFileInput', 'docDropLabel');
}

function handleEditDocFileDrop(event) {
    event.preventDefault();
    document.getElementById('editDocDropZone').classList.remove('border-red-400', 'bg-red-50/40');
    _assignFileToDrop(event.dataTransfer.files[0], 'edit_doc_file', 'editDocDropLabel');
}

async function _uploadFileChunked(uploadUrl, file, onProgress) {
    const CHUNK = 5 * 1024 * 1024; // 5 MB per chunk
    let start = 0;
    let itemId = null;

    while (start < file.size) {
        const end   = Math.min(start + CHUNK, file.size);
        const chunk = file.slice(start, end);

        const res = await fetch(uploadUrl, {
            method: 'PUT',
            headers: {
                'Content-Range': `bytes ${start}-${end - 1}/${file.size}`,
                'Content-Type': file.type || 'application/octet-stream',
            },
            body: chunk,
        });

        if (res.status === 202) {
            onProgress(Math.round(end / file.size * 95));
        } else if (res.status === 200 || res.status === 201) {
            const data = await res.json();
            itemId = data.id;
            onProgress(100);
        } else {
            const errText = await res.text();
            throw new Error(`OneDrive upload failed (${res.status}): ${errText}`);
        }

        start = end;
    }

    return itemId;
}

async function submitDocumentUpload() {
    const fileInput  = document.getElementById('docFileInput');
    const typeSelect = document.getElementById('doc_type');
    const othersText = document.getElementById('doc_others_text');
    const docType    = typeSelect.value === 'Others'
        ? (othersText.value.trim() || '')
        : typeSelect.value;

    if (!fileInput.files || !fileInput.files[0]) {
        showNotification('Please select a file first.', 'error');
        return;
    }
    if (!typeSelect.value) {
        showNotification('Please select a document type.', 'error');
        return;
    }
    if (typeSelect.value === 'Others' && !othersText.value.trim()) {
        showNotification('Please specify the document type for Others.', 'error');
        othersText.focus();
        return;
    }

    const file    = fileInput.files[0];
    const maxSize = 100 * 1024 * 1024;
    if (file.size > maxSize) {
        showNotification('File size must not exceed 100 MB.', 'error');
        return;
    }

    const btn      = document.getElementById('docUploadBtn');
    const progress = document.getElementById('docUploadProgress');
    const label    = document.getElementById('docUploadLabel');

    btn.disabled = true;
    progress.classList.remove('hidden');
    label.textContent = 'Preparing upload...';

    try {
        // Step 1: get upload session URL from server (no file data — tiny request)
        const sessionRes = await fetch('/projects/{{ $project->id }}/documents/create-upload-session', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                filename:      file.name,
                document_name: document.getElementById('doc_name').value.trim(),
                document_type: docType,
            }),
        });

        const sessionData = await sessionRes.json();
        if (!sessionData.success) {
            throw new Error(sessionData.message || 'Failed to create upload session.');
        }

        // Step 2: upload file directly to OneDrive in chunks (bypasses server limit)
        label.textContent = 'Uploading to OneDrive...';
        const itemId = await _uploadFileChunked(sessionData.upload_url, file, pct => {
            label.textContent = `Uploading... ${pct}%`;
        });

        if (!itemId) throw new Error('Upload completed but no item ID returned.');

        // Step 3: finalize — server creates share link and saves to DB
        label.textContent = 'Finalizing...';
        const finalRes = await fetch('/projects/{{ $project->id }}/documents/finalize-upload', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                onedrive_item_id: itemId,
                filename:         file.name,
                document_name:    document.getElementById('doc_name').value.trim(),
                document_type:    docType,
            }),
        });

        const data = await finalRes.json();

        if (data.success) {
            showNotification('Document uploaded successfully!', 'success');
            closeModal('documentModal');

            const emptyState = document.getElementById('documentsEmptyState');
            if (emptyState) emptyState.classList.add('hidden');
            const tableWrap = document.getElementById('documentsTableWrap');
            if (tableWrap) tableWrap.classList.remove('hidden');

            const tbody = document.getElementById('documentsTableBody');
            const doc   = data.document;
            const tr    = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 document-row';
            tr.setAttribute('data-document-id', doc.id);
            tr.innerHTML = `
                <td class="px-6 py-4">
                    <input type="checkbox" class="row-checkbox document-checkbox"
                           data-id="${doc.id}"
                           data-name="${doc.document_name}"
                           data-type="${doc.document_type}"
                           onchange="handleRowSelection('document')">
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="text-sm font-medium text-gray-900">${doc.document_name}</span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${doc.document_type}
                    </span>
                </td>
                <td class="px-6 py-4">
                    ${doc.link_document
                        ? `<a href="${doc.link_document}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Open File</a>`
                        : `<span class="text-xs text-gray-400">-</span>`
                    }
                </td>`;
            tbody.appendChild(tr);
        } else {
            throw new Error(data.message || 'Failed to finalize upload.');
        }
    } catch (err) {
        console.error('Upload error:', err);
        showNotification(err.message || 'An error occurred during upload.', 'error');
        btn.disabled = false;
    } finally {
        progress.classList.add('hidden');
    }
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

function openDeleteFolderModal() {
    document.getElementById('deleteFolderConfirmModal').classList.remove('hidden');
    document.getElementById('confirmDeleteFolderBtn').onclick = confirmDeleteFolder;
}

function closeDeleteFolderModal() {
    document.getElementById('deleteFolderConfirmModal').classList.add('hidden');
}

async function confirmDeleteFolder() {
    const btn = document.getElementById('confirmDeleteFolderBtn');
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Deleting...`;
    try {
        const res  = await fetch('/projects/{{ $project->id }}/folder/delete', {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
        });
        const data = await res.json();
        if (data.success) {
            _odrHasFolder = false;
            closeDeleteFolderModal();
            closeOneDriveModal();
            const el = document.getElementById('headerFolderBtn');
            if (el) {
                const newBtn = document.createElement('button');
                newBtn.type = 'button'; newBtn.id = 'headerFolderBtn'; newBtn.onclick = openOneDriveModal;
                newBtn.className = el.className;
                newBtn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg> Create Folder`;
                el.replaceWith(newBtn);
            }
            showNotification('Folder deleted successfully.', 'success');
        } else {
            showNotification(data.message || 'Failed to delete folder.', 'error');
        }
    } catch (err) {
        showNotification('Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg> Delete Folder`;
    }
}

function deleteProjectFolder() {
    openDeleteFolderModal();
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
        const res  = await fetch('/projects/{{ $project->id }}/generate-folder', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ folder_name: document.getElementById('odrFolderName').value.trim() }),
        });
        const data = await res.json();

        if (data.success) {
            _showOdrSuccess(data.folder_url);
            _applyOdrLinkStatus(data);
            if (data.link_warning) {
                showNotification(data.link_warning, 'error');
            } else {
                showNotification('OneDrive folder created successfully!', 'success');
            }
        } else {
            showNotification(data.message || 'Failed to create folder.', 'error');
            label.textContent = 'Generate Folder';
        }
    } catch (err) {
        showNotification('Error: ' + err.message, 'error');
        label.textContent = 'Generate Folder';
    } finally {
        btn.disabled = false;
        icon.classList.remove('hidden');
        spinner.classList.add('hidden');
    }
}

// Perbarui badge + banner status link setelah folder dibuat / link di-refresh.
function _applyOdrLinkStatus(data) {
    const badge   = document.getElementById('odrLinkBadge');
    const text    = document.getElementById('odrLinkBadgeText');
    const banner  = document.getElementById('odrLinkWarningBanner');
    const warnTxt = document.getElementById('odrLinkWarningText');

    if (badge && text) {
        text.textContent = 'Folder link: ' + (data.link_scope_label || 'Not verified');
        badge.className  = 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium '
            + (data.link_warning ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800');
    }

    if (banner && warnTxt) {
        warnTxt.textContent = data.link_warning || '';
        banner.classList.toggle('hidden', !data.link_warning);
    }
}

// Buat ulang share link folder (folder-nya sendiri tidak disentuh).
async function refreshProjectFolderLink() {
    const btn = document.getElementById('odrRefreshLinkBtn');
    if (btn) { btn.disabled = true; btn.classList.add('opacity-60'); }

    try {
        const res  = await fetch('/projects/{{ $project->id }}/generate-folder', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({}),
        });
        const data = await res.json();

        if (!data.success) {
            showNotification(data.message || 'Failed to refresh the folder link.', 'error');
            return;
        }

        _applyOdrLinkStatus(data);

        const headerBtn = document.getElementById('headerFolderBtn');
        if (headerBtn && headerBtn.tagName === 'A') headerBtn.href = data.folder_url;

        if (data.link_warning) {
            showNotification(data.link_warning, 'error');
        } else {
            showNotification('Folder link refreshed — anyone with the link can open it.', 'success');
        }
    } catch (err) {
        showNotification('Error: ' + err.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.classList.remove('opacity-60'); }
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
        showNotification('Link copied to clipboard!', 'success');
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

{{-- Flatpickr + HolidayCalendar untuk semua date picker di halaman ini --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
// Definisi HolidayCalendar (singleton — tidak re-define jika sudah ada dari halaman lain)
window.HolidayCalendar = window.HolidayCalendar || (function () {
    var _set  = new Set();
    var _meta = {};
    var _loaded = false, _promise = null;

    function isWeekend(d) { var day = d.getDay(); return day === 0 || day === 6; }
    function toISO(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }
    function isNonWorkingDay(d) { return isWeekend(d) || _set.has(toISO(d)); }
    function holidayInfo(d) { return _meta[toISO(d)] || null; }

    function load(from, to) {
        if (_loaded) return Promise.resolve();
        if (_promise) return _promise;
        var y = new Date().getFullYear();
        from = from || (y - 1); to = to || (y + 2);
        _promise = fetch('/api/holidays?from=' + from + '&to=' + to)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.holidays || []).forEach(function (h) {
                    _set.add(h.date);
                    _meta[h.date] = { name: h.name, type: h.type };
                });
                _loaded = true;
            })
            .catch(function () { _loaded = true; });
        return _promise;
    }

    function initPicker(input, options) {
        if (!input || typeof flatpickr === 'undefined') return null;
        var altClass = input.className; // inherit CSS class ke altInput
        var cfg = Object.assign({
            dateFormat  : 'Y-m-d',    // format disimpan ke value input (untuk server)
            altInput    : true,        // tampilkan input kedua yang user-friendly
            altFormat   : 'd M Y',    // format tampilan ke user (mis. 08 Jun 2026)
            altInputClass: altClass,
            allowInput  : false,
            disableMobile: true,
            monthSelectorType: 'static',
            appendTo    : document.body,
            disable     : [function (date) { return isNonWorkingDay(date); }],
            onDayCreate : function (_, __, ___, dayElem) {
                if (isWeekend(dayElem.dateObj)) dayElem.classList.add('fp-weekend');
                var info = holidayInfo(dayElem.dateObj);
                if (info) { dayElem.classList.add('fp-holiday'); dayElem.title = info.name; }
            }
        }, options || {});
        return flatpickr(input, cfg);
    }

    return { load: load, initPicker: initPicker, isNonWorkingDay: isNonWorkingDay, holidayInfo: holidayInfo };
})();

// Inisialisasi semua picker setelah holidays di-load
document.addEventListener('DOMContentLoaded', function () {
    HolidayCalendar.load().then(function () {
        // Contract dates (General Information) — start = lower bound of end, end = upper bound of start
        var _csEl = document.getElementById('contract_start_date');
        var _ceEl = document.getElementById('contract_end_date');
        if (_csEl && _ceEl) {
            window._fpContractStart = HolidayCalendar.initPicker(_csEl, {
                onChange: function (_, str) { if (window._fpContractEnd) window._fpContractEnd.set('minDate', str || null); }
            });
            window._fpContractEnd = HolidayCalendar.initPicker(_ceEl, {
                onChange: function (_, str) { if (window._fpContractStart) window._fpContractStart.set('maxDate', str || null); }
            });
            if (_csEl.value && window._fpContractEnd)   window._fpContractEnd.set('minDate', _csEl.value);
            if (_ceEl.value && window._fpContractStart) window._fpContractStart.set('maxDate', _ceEl.value);
        }

        // Add Team Member modal
        window._fpAddStart = HolidayCalendar.initPicker(document.getElementById('add_start_date'));
        window._fpAddEnd   = HolidayCalendar.initPicker(document.getElementById('add_end_date'));

        // Edit Team Member modal (hanya end_date yang editable)
        window._fpEditEnd   = HolidayCalendar.initPicker(document.getElementById('edit_end_date'));

        // Issue Log modal — Date Identified / Estimated Closed / Closed Date
        window._fpIssueIdentified = HolidayCalendar.initPicker(document.getElementById('issue_date_identified'));
        window._fpIssueEstClosed  = HolidayCalendar.initPicker(document.getElementById('issue_estimated_closed'));
        window._fpIssueClosed     = HolidayCalendar.initPicker(document.getElementById('issue_closed_date'));

        // WRICEF modal — Request/Approved + Start & End tiap tahap.
        // Disimpan dalam satu map supaya reset/isi ulang bisa di-loop.
        window._fpWricef = {};
        [
            'request_date', 'approved_date',
            'fsd_start', 'fsd_end',
            'dev_start', 'dev_end',
            'test_start', 'test_end',
        ].forEach(function (key) {
            const el = document.getElementById('wricef_' + key);
            if (el) window._fpWricef[key] = HolidayCalendar.initPicker(el);
        });

        // Location Information — Valid From / Valid To
        window._fpLocFrom = HolidayCalendar.initPicker(document.getElementById('loc_valid_from'));
        window._fpLocTo   = HolidayCalendar.initPicker(document.getElementById('loc_valid_to'));

        // Risk Register — Target Date & Actual End Date
        window._fpRiskTarget    = HolidayCalendar.initPicker(document.getElementById('risk_target_date'));
        window._fpRiskActualEnd = HolidayCalendar.initPicker(document.getElementById('risk_actual_end_date'));

        // Term Of Payment Plan — Estimated / Submit Invoice / Paid Date
        window._fpPtEstimated     = HolidayCalendar.initPicker(document.getElementById('pt_estimated_date'));
        window._fpPtSubmitInvoice = HolidayCalendar.initPicker(document.getElementById('pt_submit_invoice_date'), {
            onChange: function () {
                if (window.PaymentTermPlan && window.PaymentTermPlan.toggleInvoiceRequired) {
                    window.PaymentTermPlan.toggleInvoiceRequired();
                }
            }
        });
        window._fpPtPaid          = HolidayCalendar.initPicker(document.getElementById('pt_paid_date'));
    });
});
</script>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- RISK REGISTER — JAVASCRIPT                                    --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<script>
window.RiskRegister = (function () {
    'use strict';

    const PROJECT_ID = {{ $project->id }};
    const BASE_URL   = `/projects/${PROJECT_ID}/risks`;

    // Response strategies per risk type. Opportunity excludes "Exploit".
    const STRATEGIES = {
        'Threat':      ['Avoid', 'Mitigate', 'Transfer', 'Accept', 'Exploit', 'Enhance', 'Share'],
        'Opportunity': ['Avoid', 'Mitigate', 'Transfer', 'Accept', 'Enhance', 'Share'],
    };

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    let _risks  = [];
    let _editId = null;

    // ── Risk level computation ─────────────────────────────────────
    function riskLevel(prob, imp) {
        const score = prob * imp;
        if (score >= 12) return 'High';
        if (score >= 5)  return 'Medium';
        return 'Low';
    }

    function levelBadge(level) {
        if (level === 'High')   return '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">High</span>';
        if (level === 'Medium') return '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700">Medium</span>';
        return '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">Low</span>';
    }

    function statusBadge(status) {
        const map = {
            'Open':        'bg-yellow-100 text-yellow-800',
            'In Progress': 'bg-blue-100 text-blue-800',
            'Mitigated':   'bg-purple-100 text-purple-800',
            'Closed':      'bg-green-100 text-green-800',
        };
        const cls = map[status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${esc(status)}</span>`;
    }

    function typeBadge(type) {
        if (type === 'Opportunity')
            return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Opportunity</span>';
        if (type === 'Threat')
            return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700">Threat</span>';
        return '—';
    }

    // Repopulate Response Strategy <select> based on selected Risk Type,
    // preserving the current selection if it is still valid.
    function populateResponseStrategies(type, keepValue) {
        const sel = document.getElementById('risk_response_strategy');
        if (!sel) return;
        const list = STRATEGIES[type] || [];
        const prev = keepValue ?? sel.value;
        if (!list.length) {
            sel.innerHTML = '<option value="">-- Select Risk Type first --</option>';
            return;
        }
        sel.innerHTML = '<option value="">-- Select Strategy --</option>' +
            list.map(s => `<option value="${s}">${s}</option>`).join('');
        if (prev && list.includes(prev)) sel.value = prev;
    }

    function onRiskTypeChange() {
        const type = document.getElementById('risk_type').value;
        populateResponseStrategies(type);
    }

    // Show/hide the Actual End Date field depending on status.
    function onStatusChange() {
        const status  = document.getElementById('risk_status').value;
        const wrapper = document.getElementById('risk_actual_end_wrapper');
        if (!wrapper) return;
        if (status === 'Closed') {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
            document.getElementById('risk_actual_end_date').value = '';
            if (window._fpRiskActualEnd) window._fpRiskActualEnd.clear();
        }
    }

    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Set a <select> value, injecting the value as an option first if it is
    // not already present (e.g. legacy free-text owner no longer on the team).
    function setSelectValue(sel, val) {
        if (!sel) return;
        const v = val ?? '';
        if (v && !Array.from(sel.options).some(o => o.value === v)) {
            sel.add(new Option(v, v));
        }
        sel.value = v;
    }

    // ── Load & render ─────────────────────────────────────────────
    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _risks = res.data.risks ?? [];
            renderTable(_risks);
        } catch (e) {
            document.getElementById('riskTableBody').innerHTML =
                `<tr><td colspan="18" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh.</td></tr>`;
        }
    }

    function renderTable(risks) {
        const tbody = document.getElementById('riskTableBody');
        if (!risks.length) {
            tbody.innerHTML = `<tr><td colspan="18" class="text-center py-10 text-gray-400 text-sm">No risks yet. Click "Add Risk" to get started.</td></tr>`;
            return;
        }
        tbody.innerHTML = risks.map(r => rowHtml(r)).join('');
    }

    function rowHtml(r) {
        const score = r.risk_score;
        const level = r.risk_level;
        return `<tr class="hover:bg-gray-50 align-top">
            <td class="px-3 py-3 text-xs font-mono text-gray-600 whitespace-nowrap">${esc(r.risk_id_label)}</td>
            <td class="px-3 py-3 text-center whitespace-nowrap">${typeBadge(r.risk_type)}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(r.category)}</td>
            <td class="px-3 py-3 text-xs text-gray-800 max-w-[200px]"><div class="line-clamp-3">${esc(r.description)}</div></td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[160px]"><div class="line-clamp-3">${esc(r.cause) || '—'}</div></td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[160px]"><div class="line-clamp-3">${esc(r.project_impact) || '—'}</div></td>
            <td class="px-3 py-3 text-center text-sm font-semibold text-gray-700">${r.probability}</td>
            <td class="px-3 py-3 text-center text-sm font-semibold text-gray-700">${r.impact}</td>
            <td class="px-3 py-3 text-center text-sm font-bold text-gray-800">${score}</td>
            <td class="px-3 py-3 text-center">${levelBadge(level)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap">${esc(r.response_strategy) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[180px]"><div class="line-clamp-3">${esc(r.mitigation_plan) || '—'}</div></td>
            <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap">${esc(r.risk_owner) || '—'}</td>
            <td class="px-3 py-3 text-center">${statusBadge(r.status)}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(r.target_date_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(r.actual_end_date_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 max-w-[140px]"><div class="line-clamp-2">${esc(r.notes) || '—'}</div></td>
            <td class="px-3 py-3 text-center whitespace-nowrap">
                <button onclick="RiskRegister.openEdit(${r.id})"
                        class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button onclick="RiskRegister.openDeleteModal(${r.id}, '${esc(r.risk_id_label)}')"
                        class="inline-flex items-center p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        </tr>`;
    }

    // ── Modal helpers ─────────────────────────────────────────────
    function resetForm() {
        ['risk_type','risk_category','risk_probability','risk_impact'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('risk_status').value = 'Open';
        ['risk_description','risk_cause','risk_project_impact',
         'risk_mitigation_plan','risk_notes'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('risk_owner').value = '';
        document.getElementById('risk_target_date').value = '';
        if (window._fpRiskTarget) window._fpRiskTarget.clear();
        populateResponseStrategies('');         // reset strategy options (none until type chosen)
        onStatusChange();                        // hide & clear Actual End Date
        document.getElementById('riskScorePreview').textContent  = '—';
        document.getElementById('riskLevelPreview').textContent  = '—';
        document.getElementById('riskLevelPreview').className    = 'text-sm font-bold text-gray-400';
        document.getElementById('riskScorePreview').className    = 'text-sm font-bold text-gray-400';
    }

    function openAdd() {
        _editId = null;
        resetForm();
        document.getElementById('riskModalMode').value  = 'create';
        document.getElementById('riskModalId').value    = '';
        document.getElementById('riskModalTitle').textContent = 'Add Risk';
        document.getElementById('riskModal').classList.remove('hidden');
    }

    function openEdit(id) {
        const r = _risks.find(x => x.id === id);
        if (!r) return;
        _editId = id;
        resetForm();
        document.getElementById('riskModalMode').value  = 'edit';
        document.getElementById('riskModalId').value    = id;
        document.getElementById('riskModalTitle').textContent = `Edit Risk — ${r.risk_id_label}`;

        document.getElementById('risk_type').value               = r.risk_type ?? '';
        document.getElementById('risk_category').value           = r.category ?? '';
        document.getElementById('risk_status').value             = r.status ?? 'Open';
        document.getElementById('risk_probability').value        = r.probability ?? '';
        document.getElementById('risk_impact').value             = r.impact ?? '';
        populateResponseStrategies(r.risk_type ?? '', r.response_strategy ?? '');
        setSelectValue(document.getElementById('risk_owner'), r.risk_owner ?? '');
        document.getElementById('risk_description').value        = r.description ?? '';
        document.getElementById('risk_cause').value              = r.cause ?? '';
        document.getElementById('risk_project_impact').value     = r.project_impact ?? '';
        document.getElementById('risk_mitigation_plan').value    = r.mitigation_plan ?? '';
        document.getElementById('risk_notes').value              = r.notes ?? '';

        if (r.target_date && window._fpRiskTarget) {
            window._fpRiskTarget.setDate(r.target_date, false, 'Y-m-d');
        } else if (r.target_date) {
            document.getElementById('risk_target_date').value = r.target_date;
        }

        // Actual End Date (show field first if status = Closed)
        onStatusChange();
        if (r.actual_end_date && window._fpRiskActualEnd) {
            window._fpRiskActualEnd.setDate(r.actual_end_date, false, 'Y-m-d');
        } else if (r.actual_end_date) {
            document.getElementById('risk_actual_end_date').value = r.actual_end_date;
        }

        refreshScore();
        document.getElementById('riskModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('riskModal').classList.add('hidden');
    }

    // ── Score preview ─────────────────────────────────────────────
    function refreshScore() {
        const p = parseInt(document.getElementById('risk_probability').value, 10);
        const i = parseInt(document.getElementById('risk_impact').value, 10);
        const scoreEl = document.getElementById('riskScorePreview');
        const levelEl = document.getElementById('riskLevelPreview');

        if (!p || !i) {
            scoreEl.textContent = '—';
            scoreEl.className   = 'text-sm font-bold text-gray-400';
            levelEl.textContent = '—';
            levelEl.className   = 'text-sm font-bold text-gray-400';
            return;
        }

        const score = p * i;
        const level = riskLevel(p, i);
        const colorMap = { High: 'text-red-600', Medium: 'text-orange-600', Low: 'text-green-600' };

        scoreEl.textContent = score;
        scoreEl.className   = `text-sm font-bold ${colorMap[level]}`;
        levelEl.textContent = level;
        levelEl.className   = `text-sm font-bold ${colorMap[level]}`;
    }

    // ── Save (create / update) ────────────────────────────────────
    async function save() {
        const mode = document.getElementById('riskModalMode').value;

        const riskType  = document.getElementById('risk_type').value;
        const category  = document.getElementById('risk_category').value.trim();
        const desc      = document.getElementById('risk_description').value.trim();
        const prob      = document.getElementById('risk_probability').value;
        const imp       = document.getElementById('risk_impact').value;
        const strategy  = document.getElementById('risk_response_strategy').value;
        const owner     = document.getElementById('risk_owner').value.trim();
        const targetDt  = document.getElementById('risk_target_date').value;
        const cause     = document.getElementById('risk_cause').value.trim();
        const impactTxt = document.getElementById('risk_project_impact').value.trim();
        const mitigation= document.getElementById('risk_mitigation_plan').value.trim();
        const notes     = document.getElementById('risk_notes').value.trim();
        const status    = document.getElementById('risk_status').value;
        const actualEnd = document.getElementById('risk_actual_end_date').value;

        // All fields are mandatory.
        if (!riskType)   { showNotification('Risk Type is required.', 'error'); return; }
        if (!category)   { showNotification('Risk Category is required.', 'error'); return; }
        if (!prob)       { showNotification('Probability is required.', 'error'); return; }
        if (!imp)        { showNotification('Impact is required.', 'error'); return; }
        if (!strategy)   { showNotification('Response Strategy is required.', 'error'); return; }
        if (!owner)      { showNotification('Risk Owner is required.', 'error'); return; }
        if (!targetDt)   { showNotification('Target Date is required.', 'error'); return; }
        if (!desc)       { showNotification('Risk Description is required.', 'error'); return; }
        if (!cause)      { showNotification('Cause (Trigger) is required.', 'error'); return; }
        if (!impactTxt)  { showNotification('Project Impact is required.', 'error'); return; }
        if (!mitigation) { showNotification('Mitigation / Contingency Plan is required.', 'error'); return; }
        if (!notes)      { showNotification('Comments / Notes is required.', 'error'); return; }
        if (status === 'Closed' && !actualEnd) { showNotification('Actual End Date is required when status is Closed.', 'error'); return; }

        const btn = document.getElementById('riskModalSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';

        const payload = {
            risk_type:         riskType,
            category:          category,
            description:       desc,
            cause:             cause,
            project_impact:    impactTxt,
            probability:       parseInt(prob, 10),
            impact:            parseInt(imp, 10),
            response_strategy: strategy,
            mitigation_plan:   mitigation,
            risk_owner:        owner,
            status:            status,
            target_date:       targetDt,
            actual_end_date:   status === 'Closed' ? (actualEnd || null) : null,
            notes:             notes,
            _token:            getCsrf(),
        };

        try {
            let res;
            if (mode === 'create') {
                res = await axios.post(BASE_URL, payload);
            } else {
                const id = document.getElementById('riskModalId').value;
                res = await axios.put(`${BASE_URL}/${id}`, payload);
            }
            showNotification(res.data.message ?? 'Saved.', 'success');
            closeModal();
            await load();
        } catch (e) {
            let msg = 'Something went wrong. Please try again.';
            if (e.response?.data?.errors) {
                const first = Object.values(e.response.data.errors)[0];
                msg = Array.isArray(first) ? first[0] : String(first);
            } else if (e.response?.data?.message) {
                msg = e.response.data.message;
            }
            showNotification(msg, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Delete ────────────────────────────────────────────────────
    function openDeleteModal(id, label) {
        document.getElementById('riskDeleteId').value = id;
        document.getElementById('riskDeleteLabel').textContent = label ?? '';
        document.getElementById('riskDeleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('riskDeleteModal').classList.add('hidden');
    }

    async function confirmDelete() {
        const id = document.getElementById('riskDeleteId').value;
        if (!id) return;

        const btn  = document.getElementById('riskDeleteConfirmBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Deleting…';

        try {
            const res = await axios.post(`${BASE_URL}/${id}/delete`, {}, {
                headers: { 'X-CSRF-TOKEN': getCsrf() },
            });
            closeDeleteModal();
            showNotification(res.data.message ?? 'Deleted.', 'success');
            await load();
        } catch (e) {
            showNotification(e.response?.data?.message ?? 'Failed to delete.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Dashboard ─────────────────────────────────────────────────
    function openDashboard() {
        renderDashboard(_risks);
        document.getElementById('riskDashboardModal').classList.remove('hidden');
    }

    function closeDashboard() {
        document.getElementById('riskDashboardModal').classList.add('hidden');
    }

    function renderDashboard(risks) {
        const total  = risks.length;
        const high   = risks.filter(r => r.risk_level === 'High').length;
        const medium = risks.filter(r => r.risk_level === 'Medium').length;
        const low    = risks.filter(r => r.risk_level === 'Low').length;

        document.getElementById('dash_total').textContent  = total;
        document.getElementById('dash_high').textContent   = high;
        document.getElementById('dash_medium').textContent = medium;
        document.getElementById('dash_low').textContent    = low;

        const statusOrder = ['Open', 'In Progress', 'Mitigated', 'Closed'];
        const statusCount = {};
        statusOrder.forEach(s => { statusCount[s] = 0; });
        risks.forEach(r => {
            if (statusCount[r.status] !== undefined) statusCount[r.status]++;
            else statusCount[r.status] = (statusCount[r.status] || 0) + 1;
        });

        const tbody = document.getElementById('dash_status_tbody');
        const rows = statusOrder.map(s => {
            const cnt = statusCount[s] ?? 0;
            const statusCls = {
                'Open':        'bg-yellow-50 text-yellow-800',
                'In Progress': 'bg-blue-50 text-blue-800',
                'Mitigated':   'bg-purple-50 text-purple-800',
                'Closed':      'bg-green-50 text-green-800',
            };
            const cls = statusCls[s] ?? '';
            return `<tr class="${cls}">
                <td class="px-4 py-2.5 text-sm font-medium">${esc(s)}</td>
                <td class="px-4 py-2.5 text-center text-sm font-bold">${cnt}</td>
            </tr>`;
        });
        tbody.innerHTML = rows.join('') || `<tr><td colspan="2" class="text-center py-4 text-gray-400 text-xs">No data</td></tr>`;
    }

    // ── Auto-load on page ready ───────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () { load(); });

    return { openAdd, openEdit, closeModal, save, openDeleteModal, closeDeleteModal, confirmDelete, openDashboard, closeDashboard, refreshScore, onRiskTypeChange, onStatusChange };
})();
</script>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- ISSUE LOG — JAVASCRIPT                                         --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<script>
window.IssueLog = (function () {
    'use strict';

    const PROJECT_ID = {{ $project->id }};
    const BASE_URL   = `/projects/${PROJECT_ID}/issues`;
    const RISK_URL   = `/projects/${PROJECT_ID}/risks`;

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    let _issues = [];

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Set a <select> value, injecting the value as an option first if it is
    // not already present (e.g. a team member later removed from the project).
    function setSelectValue(sel, val) {
        if (!sel) return;
        const v = val ?? '';
        if (v && !Array.from(sel.options).some(o => o.value === v)) {
            sel.add(new Option(v, v));
        }
        sel.value = v;
    }

    // ── Badges ────────────────────────────────────────────────────
    function statusBadge(status) {
        const map = {
            'Open':   'bg-yellow-100 text-yellow-800',
            'Closed': 'bg-green-100 text-green-800',
        };
        const cls = map[status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${esc(status)}</span>`;
    }

    function priorityBadge(priority) {
        const map = {
            'High':   { letter: 'H', cls: 'bg-red-100 text-red-700' },
            'Medium': { letter: 'M', cls: 'bg-orange-100 text-orange-700' },
            'Low':    { letter: 'L', cls: 'bg-green-100 text-green-700' },
        };
        const p = map[priority];
        if (!p) return '—';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-bold ${p.cls}" title="${esc(priority)}">${p.letter}</span>`;
    }

    function escalationBadge(needed) {
        return needed
            ? '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">Y</span>'
            : '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">N</span>';
    }

    // ── Risk ID dropdown ──────────────────────────────────────────
    // Populate #issue_risk_id from this project's Risk Register, preserving
    // the given selection if it is still valid.
    async function loadRiskOptions(keepValue) {
        const sel = document.getElementById('issue_risk_id');
        if (!sel) return;
        let risks = [];
        try {
            const res = await axios.get(RISK_URL);
            risks = res.data.risks ?? [];
        } catch (e) {
            risks = [];
        }
        const prev = keepValue ?? sel.value;
        sel.innerHTML = '<option value="">— None —</option>' +
            risks.map(r => `<option value="${r.id}">${esc(r.risk_id_label)}</option>`).join('');
        if (prev) sel.value = String(prev);
    }

    // Show/hide Closed Date depending on status.
    function onStatusChange() {
        const status  = document.getElementById('issue_status').value;
        const wrapper = document.getElementById('issue_closed_date_wrapper');
        if (!wrapper) return;
        if (status === 'Closed') {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
            document.getElementById('issue_closed_date').value = '';
            if (window._fpIssueClosed) window._fpIssueClosed.clear();
        }
    }

    // ── Load & render ─────────────────────────────────────────────
    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _issues = res.data.issues ?? [];
            renderTable(_issues);
        } catch (e) {
            document.getElementById('issueTableBody').innerHTML =
                `<tr><td colspan="16" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh.</td></tr>`;
        }
    }

    function renderTable(issues) {
        const tbody = document.getElementById('issueTableBody');
        if (!issues.length) {
            tbody.innerHTML = `<tr><td colspan="16" class="text-center py-10 text-gray-400 text-sm">No issues reported yet. Click "Add Issue" to get started.</td></tr>`;
            return;
        }
        tbody.innerHTML = issues.map(i => rowHtml(i)).join('');
    }

    function rowHtml(i) {
        return `<tr class="hover:bg-gray-50 align-top">
            <td class="px-3 py-3 text-xs font-mono text-gray-600 whitespace-nowrap">${esc(i.issue_id_label)}</td>
            <td class="px-3 py-3 text-xs text-gray-800 max-w-[220px]"><div class="line-clamp-3">${esc(i.issue_description)}</div></td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(i.module) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(i.date_identified_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(i.closed_date_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${statusBadge(i.status)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[160px]"><div class="line-clamp-3">${esc(i.risk_to_project) || '—'}</div></td>
            <td class="px-3 py-3 text-xs font-mono text-gray-600 whitespace-nowrap">${esc(i.risk_id_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${priorityBadge(i.priority)}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(i.originator) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(i.owner) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(i.estimated_closed_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${escalationBadge(i.escalation_needed)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[160px]"><div class="line-clamp-3">${esc(i.impact_of_issue) || '—'}</div></td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[180px]"><div class="line-clamp-3">${esc(i.tracking_comments) || '—'}</div></td>
            <td class="px-3 py-3 text-center whitespace-nowrap">
                <button onclick="IssueLog.openEdit(${i.id})"
                        class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button onclick="IssueLog.openDeleteModal(${i.id}, '${esc(i.issue_id_label)}')"
                        class="inline-flex items-center p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        </tr>`;
    }

    // ── Modal helpers ─────────────────────────────────────────────
    function resetForm() {
        document.getElementById('issue_description').value       = '';
        document.getElementById('issue_module').value            = '';
        document.getElementById('issue_status').value            = 'Open';
        document.getElementById('issue_priority').value          = 'Medium';
        document.getElementById('issue_originator').value        = '';
        document.getElementById('issue_owner').value             = '';
        document.getElementById('issue_escalation_needed').value = '0';
        document.getElementById('issue_risk_to_project').value   = '';
        document.getElementById('issue_impact').value            = '';
        document.getElementById('issue_tracking_comments').value = '';

        document.getElementById('issue_date_identified').value   = '';
        document.getElementById('issue_estimated_closed').value  = '';
        document.getElementById('issue_closed_date').value       = '';
        if (window._fpIssueIdentified) window._fpIssueIdentified.clear();
        if (window._fpIssueEstClosed)  window._fpIssueEstClosed.clear();
        if (window._fpIssueClosed)     window._fpIssueClosed.clear();

        onStatusChange(); // hide & clear Closed Date
    }

    function openAdd() {
        resetForm();
        document.getElementById('issueModalMode').value  = 'create';
        document.getElementById('issueModalId').value    = '';
        document.getElementById('issueModalTitle').textContent = 'Add Issue';
        loadRiskOptions('');
        document.getElementById('issueModal').classList.remove('hidden');
    }

    async function openEdit(id) {
        const i = _issues.find(x => x.id === id);
        if (!i) return;
        resetForm();
        document.getElementById('issueModalMode').value  = 'edit';
        document.getElementById('issueModalId').value    = id;
        document.getElementById('issueModalTitle').textContent = `Edit Issue — ${i.issue_id_label}`;

        document.getElementById('issue_description').value       = i.issue_description ?? '';
        document.getElementById('issue_module').value            = i.module ?? '';
        document.getElementById('issue_status').value            = i.status ?? 'Open';
        document.getElementById('issue_priority').value          = i.priority ?? 'Medium';
        setSelectValue(document.getElementById('issue_originator'), i.originator ?? '');
        setSelectValue(document.getElementById('issue_owner'), i.owner ?? '');
        document.getElementById('issue_escalation_needed').value = i.escalation_needed ? '1' : '0';
        document.getElementById('issue_risk_to_project').value   = i.risk_to_project ?? '';
        document.getElementById('issue_impact').value            = i.impact_of_issue ?? '';
        document.getElementById('issue_tracking_comments').value = i.tracking_comments ?? '';

        await loadRiskOptions(i.delivery_project_risk_id ? String(i.delivery_project_risk_id) : '');

        if (i.date_identified && window._fpIssueIdentified) {
            window._fpIssueIdentified.setDate(i.date_identified, false, 'Y-m-d');
        }
        if (i.estimated_closed && window._fpIssueEstClosed) {
            window._fpIssueEstClosed.setDate(i.estimated_closed, false, 'Y-m-d');
        }

        onStatusChange(); // reveal Closed Date field if needed
        if (i.closed_date && window._fpIssueClosed) {
            window._fpIssueClosed.setDate(i.closed_date, false, 'Y-m-d');
        }

        document.getElementById('issueModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('issueModal').classList.add('hidden');
    }

    // ── Save (create / update) ────────────────────────────────────
    async function save() {
        const mode = document.getElementById('issueModalMode').value;

        const description = document.getElementById('issue_description').value.trim();
        const moduleVal   = document.getElementById('issue_module').value.trim();
        const status      = document.getElementById('issue_status').value;
        const priority    = document.getElementById('issue_priority').value;
        const originator  = document.getElementById('issue_originator').value;
        const owner       = document.getElementById('issue_owner').value;
        const escalation  = document.getElementById('issue_escalation_needed').value;
        const riskId      = document.getElementById('issue_risk_id').value;
        const riskToProj  = document.getElementById('issue_risk_to_project').value.trim();
        const impact      = document.getElementById('issue_impact').value.trim();
        const comments    = document.getElementById('issue_tracking_comments').value.trim();
        const dateIdent   = document.getElementById('issue_date_identified').value;
        const estClosed   = document.getElementById('issue_estimated_closed').value;
        const closedDate  = document.getElementById('issue_closed_date').value;

        if (!description) { showNotification('Issue Description is required.', 'error'); return; }
        if (!dateIdent)   { showNotification('Date Identified is required.', 'error'); return; }
        if (!priority)    { showNotification('Priority is required.', 'error'); return; }
        if (!originator)  { showNotification('Originator is required.', 'error'); return; }
        if (!owner)       { showNotification('Owner is required.', 'error'); return; }
        if (status === 'Closed' && !closedDate) { showNotification('Closed Date is required when status is Closed.', 'error'); return; }

        const btn  = document.getElementById('issueModalSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';

        const payload = {
            issue_description:        description,
            module:                   moduleVal || null,
            date_identified:          dateIdent,
            closed_date:              status === 'Closed' ? (closedDate || null) : null,
            status:                   status,
            risk_to_project:          riskToProj || null,
            priority:                 priority,
            originator:               originator,
            owner:                    owner,
            estimated_closed:         estClosed || null,
            escalation_needed:        escalation === '1',
            impact_of_issue:          impact || null,
            tracking_comments:        comments || null,
            delivery_project_risk_id: riskId || null,
            _token:                   getCsrf(),
        };

        try {
            let res;
            if (mode === 'create') {
                res = await axios.post(BASE_URL, payload);
            } else {
                const id = document.getElementById('issueModalId').value;
                res = await axios.put(`${BASE_URL}/${id}`, payload);
            }
            showNotification(res.data.message ?? 'Saved.', 'success');
            closeModal();
            await load();
        } catch (e) {
            let msg = 'Something went wrong. Please try again.';
            if (e.response?.data?.errors) {
                const first = Object.values(e.response.data.errors)[0];
                msg = Array.isArray(first) ? first[0] : String(first);
            } else if (e.response?.data?.message) {
                msg = e.response.data.message;
            }
            showNotification(msg, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Delete ────────────────────────────────────────────────────
    function openDeleteModal(id, label) {
        document.getElementById('issueDeleteId').value = id;
        document.getElementById('issueDeleteLabel').textContent = label ?? '';
        document.getElementById('issueDeleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('issueDeleteModal').classList.add('hidden');
    }

    async function confirmDelete() {
        const id = document.getElementById('issueDeleteId').value;
        if (!id) return;

        const btn  = document.getElementById('issueDeleteConfirmBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Deleting…';

        try {
            const res = await axios.post(`${BASE_URL}/${id}/delete`, {}, {
                headers: { 'X-CSRF-TOKEN': getCsrf() },
            });
            closeDeleteModal();
            showNotification(res.data.message ?? 'Deleted.', 'success');
            await load();
        } catch (e) {
            showNotification(e.response?.data?.message ?? 'Failed to delete.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Auto-load on page ready ───────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () { load(); });

    return { openAdd, openEdit, closeModal, save, openDeleteModal, closeDeleteModal, confirmDelete, onStatusChange };
})();
</script>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- WRICEF LOG — JAVASCRIPT                                        --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
@if($can('delivery-project.wricef.view'))
<script>
window.WricefLog = (function () {
    'use strict';

    const PROJECT_ID = {{ $project->id }};
    const BASE_URL   = `/projects/${PROJECT_ID}/wricefs`;

    // Huruf Obj_Id per kategori — harus sama dengan
    // App\Models\DeliveryProjectWricef::CATEGORY_LETTERS (server tetap yang
    // menentukan nilai final; ini cuma pratinjau prefix di modal).
    const CATEGORY_LETTERS = @json(\App\Models\DeliveryProjectWricef::CATEGORY_LETTERS);

    // Field teks/select sederhana: id elemen = 'wricef_' + key, key = nama kolom.
    const TEXT_FIELDS = [
        'sap_module', 'category', 'obj_name', 'capability', 'tcode',
        'priority', 'requestor', 'effort_mandays', 'approved_by', 'status',
        'fsd_pic', 'fsd_status', 'fsd_remarks',
        'dev_pic', 'dev_status', 'dev_remarks',
        'test_pic', 'test_status', 'test_remarks',
    ];

    const DATE_FIELDS = [
        'request_date', 'approved_date',
        'fsd_start', 'fsd_end',
        'dev_start', 'dev_end',
        'test_start', 'test_end',
    ];

    let _rows    = [];
    let _company = '';

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function el(key) {
        return document.getElementById('wricef_' + key);
    }

    // Set nilai <select>, menyisipkan opsi bila nilainya tidak ada lagi di
    // daftar (mis. PIC yang sudah dikeluarkan dari Project Team).
    function setSelectValue(sel, val) {
        if (!sel) return;
        const v = val ?? '';
        if (v && !Array.from(sel.options).some(o => o.value === v)) {
            sel.add(new Option(v, v));
        }
        sel.value = v;
    }

    // ── Badges ────────────────────────────────────────────────────
    function statusBadge(status) {
        const map = {
            'Open':        'bg-yellow-100 text-yellow-800',
            'In Progress': 'bg-blue-100 text-blue-800',
            'Closed':      'bg-green-100 text-green-800',
        };
        if (!status) return '—';
        const cls = map[status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${esc(status)}</span>`;
    }

    // Warna dipakai bersama tiga tahap; label "Done" selalu hijau, "Revisi"
    // selalu merah, sisanya netral/biru supaya mudah dipindai per kolom.
    function stageBadge(status) {
        const map = {
            'Done':             'bg-green-100 text-green-800',
            'Revisi':           'bg-red-100 text-red-700',
            'Review':           'bg-purple-100 text-purple-700',
            'Testing':          'bg-blue-100 text-blue-800',
            'Develop':          'bg-amber-100 text-amber-800',
            'Develop Scenario': 'bg-amber-100 text-amber-800',
        };
        if (!status) return '<span class="text-gray-300">—</span>';
        const cls = map[status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap ${cls}">${esc(status)}</span>`;
    }

    function priorityBadge(priority) {
        const map = {
            'High':   'bg-red-100 text-red-700',
            'Medium': 'bg-orange-100 text-orange-700',
            'Low':    'bg-green-100 text-green-700',
        };
        if (!priority) return '—';
        const cls = map[priority] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-bold ${cls}">${esc(priority)}</span>`;
    }

    function fmtMandays(v) {
        if (v === null || v === undefined || v === '') return '—';
        const num = Number(v);
        if (isNaN(num)) return '—';
        return Number.isInteger(num) ? String(num) : num.toFixed(2).replace('.', ',');
    }

    // Capability boleh multi-baris (lihat contoh CSV) — pertahankan barisnya.
    function multiline(text) {
        if (!text) return '—';
        return esc(text).replace(/\r?\n/g, '<br>');
    }

    // ── Load & render ─────────────────────────────────────────────
    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _rows    = res.data.wricefs ?? [];
            _company = res.data.company ?? '';
            renderTable();
        } catch (e) {
            const tbody = document.getElementById('wricefTableBody');
            if (tbody) tbody.innerHTML =
                `<tr><td colspan="30" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh.</td></tr>`;
        }
    }

    function renderTable() {
        const tbody = document.getElementById('wricefTableBody');
        if (!tbody) return;

        if (!_rows.length) {
            tbody.innerHTML = `<tr><td colspan="30" class="text-center py-10 text-gray-400 text-sm">No WRICEF objects yet. Click "Add WRICEF" to get started.</td></tr>`;
            return;
        }
        tbody.innerHTML = _rows.map(w => rowHtml(w)).join('');
    }

    function rowHtml(w) {
        return `<tr class="hover:bg-gray-50 align-top">
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.company) || '—'}</td>
            <td class="px-3 py-3 text-xs text-center font-semibold text-gray-700 whitespace-nowrap">${esc(w.sap_module)}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.category)}</td>
            <td class="px-3 py-3 text-xs font-mono text-gray-600 whitespace-nowrap">${esc(w.obj_id)}</td>
            <td class="px-3 py-3 text-xs text-gray-800 max-w-[240px]"><div class="line-clamp-3">${esc(w.obj_name)}</div></td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[240px]"><div class="line-clamp-4">${multiline(w.capability)}</div></td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.tcode) || '—'}</td>
            <td class="px-3 py-3 text-center">${priorityBadge(w.priority)}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.requestor) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.request_date_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-right text-gray-700 whitespace-nowrap">${fmtMandays(w.effort_mandays)}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.approved_by) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.approved_date_label) || '—'}</td>
            <td class="px-3 py-3 text-center border-r border-gray-200">${statusBadge(w.status)}</td>

            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.fsd_pic) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.fsd_start_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.fsd_end_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${stageBadge(w.fsd_status)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[180px] border-r border-gray-200"><div class="line-clamp-3">${esc(w.fsd_remarks) || '—'}</div></td>

            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.dev_pic) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.dev_start_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.dev_end_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${stageBadge(w.dev_status)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[180px] border-r border-gray-200"><div class="line-clamp-3">${esc(w.dev_remarks) || '—'}</div></td>

            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(w.test_pic) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.test_start_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(w.test_end_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${stageBadge(w.test_status)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[180px] border-r border-gray-200"><div class="line-clamp-3">${esc(w.test_remarks) || '—'}</div></td>

            <td class="px-3 py-3 text-center whitespace-nowrap">
                <button onclick="WricefLog.openEdit(${w.id})"
                        class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button onclick="WricefLog.openDeleteModal(${w.id}, '${esc(w.obj_id)}')"
                        class="inline-flex items-center p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        </tr>`;
    }

    // ── Obj ID preview ────────────────────────────────────────────
    // Nilai final tetap dari server. Saat menambah, nomor urutnya belum
    // diketahui sehingga hanya prefix yang ditampilkan; saat mengedit, Obj ID
    // lama dipertahankan selama modul & kategorinya tidak berubah.
    function onObjIdSourceChange() {
        const preview = document.getElementById('wricef_obj_id_preview');
        if (!preview) return;

        const mod = el('sap_module')?.value || '';
        const cat = el('category')?.value || '';
        const id  = document.getElementById('wricefModalId').value;

        if (id) {
            const current = _rows.find(x => String(x.id) === String(id));
            if (current && current.sap_module === mod && current.category === cat) {
                preview.value = current.obj_id;
                return;
            }
        }

        if (!mod || !cat) {
            preview.value = '';
            return;
        }
        preview.value = mod + (CATEGORY_LETTERS[cat] ?? cat.charAt(0).toUpperCase()) + '###';
    }

    // ── Modal helpers ─────────────────────────────────────────────
    function resetForm() {
        TEXT_FIELDS.forEach(function (key) {
            const node = el(key);
            if (node) node.value = '';
        });
        // Nilai default yang tidak boleh kosong.
        if (el('priority')) el('priority').value = 'Medium';
        if (el('status'))   el('status').value   = 'Open';

        DATE_FIELDS.forEach(function (key) {
            const node = el(key);
            if (node) node.value = '';
            if (window._fpWricef && window._fpWricef[key]) window._fpWricef[key].clear();
        });

        const preview = document.getElementById('wricef_obj_id_preview');
        if (preview) preview.value = '';
    }

    function openAdd() {
        document.getElementById('wricefModalMode').value = 'create';
        document.getElementById('wricefModalId').value   = '';
        resetForm();
        document.getElementById('wricefModalTitle').textContent = 'Add WRICEF';
        onObjIdSourceChange();
        document.getElementById('wricefModal').classList.remove('hidden');
    }

    function openEdit(id) {
        const w = _rows.find(x => x.id === id);
        if (!w) return;

        document.getElementById('wricefModalMode').value = 'edit';
        document.getElementById('wricefModalId').value   = id;
        resetForm();
        document.getElementById('wricefModalTitle').textContent = `Edit WRICEF — ${w.obj_id}`;

        TEXT_FIELDS.forEach(function (key) {
            const node = el(key);
            if (!node) return;
            const val = w[key] ?? '';
            if (node.tagName === 'SELECT') {
                setSelectValue(node, val === null ? '' : String(val));
            } else {
                node.value = val === null ? '' : val;
            }
        });

        DATE_FIELDS.forEach(function (key) {
            if (w[key] && window._fpWricef && window._fpWricef[key]) {
                window._fpWricef[key].setDate(w[key], false, 'Y-m-d');
            }
        });

        onObjIdSourceChange();
        document.getElementById('wricefModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('wricefModal').classList.add('hidden');
    }

    // ── Save (create / update) ────────────────────────────────────
    async function save() {
        const mode = document.getElementById('wricefModalMode').value;

        const val = key => (el(key)?.value ?? '').trim();

        const sapModule = val('sap_module');
        const category  = val('category');
        const objName   = val('obj_name');

        if (!sapModule) { showNotification('SAP Module is required.', 'error'); return; }
        if (!category)  { showNotification('Category is required.', 'error'); return; }
        if (!objName)   { showNotification('Obj Name is required.', 'error'); return; }

        const effort = val('effort_mandays');

        const payload = {
            sap_module:     sapModule,
            category:       category,
            obj_name:       objName,
            capability:     val('capability') || null,
            tcode:          val('tcode') || null,
            priority:       val('priority') || 'Medium',
            requestor:      val('requestor') || null,
            request_date:   val('request_date') || null,
            effort_mandays: effort === '' ? null : Number(effort),
            approved_by:    val('approved_by') || null,
            approved_date:  val('approved_date') || null,
            status:         val('status') || 'Open',

            fsd_pic:     val('fsd_pic') || null,
            fsd_start:   val('fsd_start') || null,
            fsd_end:     val('fsd_end') || null,
            fsd_status:  val('fsd_status') || null,
            fsd_remarks: val('fsd_remarks') || null,

            dev_pic:     val('dev_pic') || null,
            dev_start:   val('dev_start') || null,
            dev_end:     val('dev_end') || null,
            dev_status:  val('dev_status') || null,
            dev_remarks: val('dev_remarks') || null,

            test_pic:     val('test_pic') || null,
            test_start:   val('test_start') || null,
            test_end:     val('test_end') || null,
            test_status:  val('test_status') || null,
            test_remarks: val('test_remarks') || null,

            _token: getCsrf(),
        };

        const btn = document.getElementById('wricefModalSaveBtn');
        if (!btn) return;
        const orig = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';

        try {
            let res;
            if (mode === 'create') {
                res = await axios.post(BASE_URL, payload);
            } else {
                const id = document.getElementById('wricefModalId').value;
                res = await axios.put(`${BASE_URL}/${id}`, payload);
            }
            showNotification(res.data.message ?? 'Saved.', 'success');
            closeModal();
            await load();
        } catch (e) {
            let msg = 'Something went wrong. Please try again.';
            if (e.response?.data?.errors) {
                const first = Object.values(e.response.data.errors)[0];
                msg = Array.isArray(first) ? first[0] : String(first);
            } else if (e.response?.data?.message) {
                msg = e.response.data.message;
            }
            showNotification(msg, 'error');
        } finally {
            btn.disabled  = false;
            btn.innerHTML = orig;
        }
    }

    // ── Delete ────────────────────────────────────────────────────
    function openDeleteModal(id, label) {
        document.getElementById('wricefDeleteId').value        = id;
        document.getElementById('wricefDeleteLabel').textContent = label ?? '';
        document.getElementById('wricefDeleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('wricefDeleteModal').classList.add('hidden');
    }

    async function confirmDelete() {
        const id = document.getElementById('wricefDeleteId').value;
        if (!id) return;

        const btn  = document.getElementById('wricefDeleteConfirmBtn');
        const orig = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = 'Deleting…';

        try {
            // Lewat POST: verb DELETE diblokir edge/WAF di production.
            const res = await axios.post(`${BASE_URL}/${id}/delete`, {}, {
                headers: { 'X-CSRF-TOKEN': getCsrf() },
            });
            closeDeleteModal();
            showNotification(res.data.message ?? 'Deleted.', 'success');
            await load();
        } catch (e) {
            showNotification(e.response?.data?.message ?? 'Failed to delete.', 'error');
        } finally {
            btn.disabled  = false;
            btn.innerHTML = orig;
        }
    }

    // ── Auto-load on page ready ───────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () { load(); });

    return { openAdd, openEdit, closeModal, save, openDeleteModal, closeDeleteModal, confirmDelete, onObjIdSourceChange };
})();
</script>
@endif

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TERM OF PAYMENT (TOP) PLAN — JAVASCRIPT                        --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<script>
window.PaymentTermPlan = (function () {
    'use strict';

    const PROJECT_ID = {{ $project->id }};
    const BASE_URL   = `/projects/${PROJECT_ID}/payment-terms`;

    let _terms   = [];
    // Revenue acuan untuk hitung Amount = revenue × % / 100
    let _revenue = parseFloat('{{ $project->revenue ?? 0 }}') || 0;

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Currency formatting (Indonesian thousands-dot) ─────────────
    function fmtRp(n) {
        const num = Number(n) || 0;
        const neg = num < 0;
        const abs = Math.abs(Math.round(num));
        return 'Rp ' + (neg ? '-' : '') + abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Baca revenue terkini dari field Sales Data (jika user mengubah tanpa reload)
    function currentRevenue() {
        const el = document.getElementById('sfin_rev_val');
        if (el && el.value !== '') {
            const v = parseFloat(el.value);
            if (!isNaN(v)) return v;
        }
        return _revenue;
    }

    function statusBadge(status) {
        const map = {
            'Open':  'bg-yellow-100 text-yellow-800',
            'Paid':  'bg-green-100 text-green-800',
            'Delay': 'bg-red-100 text-red-700',
        };
        const cls = map[status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${esc(status)}</span>`;
    }

    function fmtPct(p) {
        const num = Number(p) || 0;
        // tampilkan tanpa desimal jika bulat, jika tidak 2 desimal
        return (Number.isInteger(num) ? num.toString() : num.toFixed(2).replace('.', ',')) + '%';
    }

    // ── Load & render ─────────────────────────────────────────────
    async function load() {
        try {
            const res = await axios.get(BASE_URL);
            _terms = res.data.payment_terms ?? [];
            if (res.data.project_revenue !== undefined && res.data.project_revenue !== null) {
                _revenue = parseFloat(res.data.project_revenue) || _revenue;
            }
            renderTable();
        } catch (e) {
            const tbody = document.getElementById('paymentTermBody');
            if (tbody) tbody.innerHTML =
                `<tr><td colspan="11" class="text-center py-8 text-red-500 text-sm">Failed to load data. Please refresh.</td></tr>`;
        }
    }

    function renderTable() {
        const tbody = document.getElementById('paymentTermBody');
        if (!tbody) return;

        if (!_terms.length) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-gray-400 text-sm">No payment terms yet. Click "Add Payment Term" to get started.</td></tr>`;
        } else {
            tbody.innerHTML = _terms.map(t => rowHtml(t)).join('');
        }

        // Footer totals
        const totalPct = _terms.reduce((s, t) => s + (Number(t.payment_percentage) || 0), 0);
        const totalAmt = _terms.reduce((s, t) => s + (Number(t.amount) || 0), 0);
        const pctEl = document.getElementById('ptTotalPct');
        const amtEl = document.getElementById('ptTotalAmount');
        if (pctEl) {
            pctEl.textContent = fmtPct(totalPct);
            pctEl.className = 'px-3 py-3 text-center ' + (totalPct > 100 ? 'text-red-600' : 'text-gray-700');
        }
        if (amtEl) amtEl.textContent = fmtRp(totalAmt);
    }

    function rowHtml(t) {
        return `<tr class="hover:bg-gray-50 align-top">
            <td class="px-3 py-3 text-center text-xs font-mono text-gray-600">${t.term_number}</td>
            <td class="px-3 py-3 text-xs text-gray-800"><div class="line-clamp-3">${esc(t.payment_term)}</div></td>
            <td class="px-3 py-3 text-center text-xs font-semibold text-gray-700">${fmtPct(t.payment_percentage)}</td>
            <td class="px-3 py-3 text-right text-xs font-semibold text-gray-800 whitespace-nowrap">${fmtRp(t.amount)}</td>
            <td class="px-3 py-3 text-xs text-gray-600 max-w-[260px]"><div class="line-clamp-3">${esc(t.requirements) || '—'}</div></td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(t.estimated_date_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(t.submit_invoice_date_label) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">${esc(t.invoice_number) || '—'}</td>
            <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">${esc(t.paid_date_label) || '—'}</td>
            <td class="px-3 py-3 text-center">${statusBadge(t.status)}</td>
            <td class="px-3 py-3 text-center whitespace-nowrap">
                <button type="button" onclick="PaymentTermPlan.openEdit(${t.id})"
                        class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button type="button" onclick="PaymentTermPlan.openDeleteModal(${t.id}, ${t.term_number})"
                        class="inline-flex items-center p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
        </tr>`;
    }

    // ── Amount auto-calc (preview di modal) ────────────────────────
    function recalcAmount() {
        const pct = parseFloat(document.getElementById('pt_payment_percentage').value);
        const amount = (isNaN(pct) ? 0 : currentRevenue() * pct / 100);
        const disp = document.getElementById('pt_amount_disp');
        if (disp) {
            const abs = Math.abs(Math.round(amount));
            disp.value = abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
    }

    // Toggle indikator "wajib" pada Invoice Number sesuai isi Submit Invoice Date
    function toggleInvoiceRequired() {
        const hasDate = !!document.getElementById('pt_submit_invoice_date').value;
        const req  = document.getElementById('pt_invoice_number_req');
        const hint = document.getElementById('pt_invoice_number_hint');
        if (req)  req.classList.toggle('hidden', !hasDate);
        if (hint) hint.classList.toggle('hidden', !hasDate);
    }

    // Toggle indikator "wajib" pada Paid Date sesuai nilai Status (Paid → wajib)
    function togglePaidDateRequired() {
        const isPaid = document.getElementById('pt_status').value === 'Paid';
        const req  = document.getElementById('pt_paid_date_req');
        const hint = document.getElementById('pt_paid_date_hint');
        if (req)  req.classList.toggle('hidden', !isPaid);
        if (hint) hint.classList.toggle('hidden', !isPaid);
    }

    // ── Modal helpers ──────────────────────────────────────────────
    function resetForm() {
        document.getElementById('pt_payment_term').value        = '';
        document.getElementById('pt_payment_percentage').value  = '';
        document.getElementById('pt_amount_disp').value         = '';
        document.getElementById('pt_requirements').value        = '';
        document.getElementById('pt_status').value              = 'Open';
        document.getElementById('pt_estimated_date').value      = '';
        document.getElementById('pt_submit_invoice_date').value = '';
        document.getElementById('pt_invoice_number').value      = '';
        document.getElementById('pt_paid_date').value           = '';
        if (window._fpPtEstimated)     window._fpPtEstimated.clear();
        if (window._fpPtSubmitInvoice) window._fpPtSubmitInvoice.clear();
        if (window._fpPtPaid)          window._fpPtPaid.clear();
        toggleInvoiceRequired();
        togglePaidDateRequired();
    }

    function openAdd() {
        resetForm();
        document.getElementById('paymentTermModalMode').value  = 'create';
        document.getElementById('paymentTermModalId').value    = '';
        document.getElementById('paymentTermModalTitle').textContent = 'Add Payment Term';
        document.getElementById('paymentTermModal').classList.remove('hidden');
    }

    function openEdit(id) {
        const t = _terms.find(x => x.id === id);
        if (!t) return;
        resetForm();
        document.getElementById('paymentTermModalMode').value  = 'edit';
        document.getElementById('paymentTermModalId').value    = id;
        document.getElementById('paymentTermModalTitle').textContent = `Edit Payment Term #${t.term_number}`;

        document.getElementById('pt_payment_term').value       = t.payment_term ?? '';
        document.getElementById('pt_payment_percentage').value = t.payment_percentage ?? '';
        document.getElementById('pt_requirements').value       = t.requirements ?? '';
        document.getElementById('pt_invoice_number').value     = t.invoice_number ?? '';
        document.getElementById('pt_status').value             = t.status ?? 'Open';

        if (t.estimated_date && window._fpPtEstimated) window._fpPtEstimated.setDate(t.estimated_date, false, 'Y-m-d');
        else if (t.estimated_date) document.getElementById('pt_estimated_date').value = t.estimated_date;

        if (t.submit_invoice_date && window._fpPtSubmitInvoice) window._fpPtSubmitInvoice.setDate(t.submit_invoice_date, false, 'Y-m-d');
        else if (t.submit_invoice_date) document.getElementById('pt_submit_invoice_date').value = t.submit_invoice_date;

        if (t.paid_date && window._fpPtPaid) window._fpPtPaid.setDate(t.paid_date, false, 'Y-m-d');
        else if (t.paid_date) document.getElementById('pt_paid_date').value = t.paid_date;

        toggleInvoiceRequired();
        togglePaidDateRequired();
        recalcAmount();
        document.getElementById('paymentTermModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('paymentTermModal').classList.add('hidden');
    }

    // ── Save (create / update) ─────────────────────────────────────
    async function save() {
        const mode = document.getElementById('paymentTermModalMode').value;
        const term = document.getElementById('pt_payment_term').value.trim();
        const pct  = document.getElementById('pt_payment_percentage').value;

        const submitInvoiceDate = document.getElementById('pt_submit_invoice_date').value || null;
        const invoiceNumber     = document.getElementById('pt_invoice_number').value.trim();
        const paidDate          = document.getElementById('pt_paid_date').value || null;
        const status            = document.getElementById('pt_status').value;

        if (!term) { showNotification('Payment Term is required.', 'error'); return; }
        if (pct === '' || isNaN(parseFloat(pct))) { showNotification('Payment % is required.', 'error'); return; }
        if (parseFloat(pct) < 0 || parseFloat(pct) > 100) { showNotification('Payment % must be between 0 and 100.', 'error'); return; }
        if (submitInvoiceDate && !invoiceNumber) { showNotification('Invoice Number is required when Submit Invoice Date is filled.', 'error'); return; }
        if (status === 'Paid' && !paidDate) { showNotification('Paid Date is required when Status is Paid.', 'error'); return; }

        // Guard: total payment terms tidak boleh melebihi 100% / revenue
        const editId    = mode === 'edit' ? parseInt(document.getElementById('paymentTermModalId').value, 10) : null;
        const otherPct  = _terms.reduce((s, t) => (t.id === editId ? s : s + (Number(t.payment_percentage) || 0)), 0);
        const totalPct  = otherPct + parseFloat(pct);
        if (totalPct > 100 + 0.001) {
            const rev      = currentRevenue();
            const totalAmt = rev * totalPct / 100;
            const pctLabel = (Number.isInteger(totalPct) ? totalPct.toString() : totalPct.toFixed(2).replace('.', ',')) + '%';
            showNotification(`Total payment terms (${pctLabel} = ${fmtRp(totalAmt)}) cannot exceed the project revenue (${fmtRp(rev)}).`, 'error');
            return;
        }

        const btn = document.getElementById('paymentTermSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';

        const payload = {
            payment_term:        term,
            payment_percentage:  parseFloat(pct),
            requirements:        document.getElementById('pt_requirements').value.trim() || null,
            estimated_date:      document.getElementById('pt_estimated_date').value || null,
            submit_invoice_date: submitInvoiceDate,
            invoice_number:      invoiceNumber || null,
            paid_date:           paidDate,
            status:              status,
            _token:              getCsrf(),
        };

        try {
            let res;
            if (mode === 'create') {
                res = await axios.post(BASE_URL, payload);
            } else {
                const id = document.getElementById('paymentTermModalId').value;
                res = await axios.put(`${BASE_URL}/${id}`, payload);
            }
            showNotification(res.data.message ?? 'Saved.', 'success');
            closeModal();
            await load();
        } catch (e) {
            let msg = 'Something went wrong. Please try again.';
            if (e.response?.data?.errors) {
                const first = Object.values(e.response.data.errors)[0];
                msg = Array.isArray(first) ? first[0] : String(first);
            } else if (e.response?.data?.message) {
                msg = e.response.data.message;
            }
            showNotification(msg, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Delete ─────────────────────────────────────────────────────
    function openDeleteModal(id, number) {
        document.getElementById('ptDeleteId').value = id;
        document.getElementById('ptDeleteNumber').textContent = number ?? '';
        document.getElementById('paymentTermDeleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('paymentTermDeleteModal').classList.add('hidden');
    }

    async function confirmDelete() {
        const id = document.getElementById('ptDeleteId').value;
        if (!id) return;

        const btn  = document.getElementById('ptDeleteConfirmBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Deleting…';

        try {
            const res = await axios.post(`${BASE_URL}/${id}/delete`, {}, {
                headers: { 'X-CSRF-TOKEN': getCsrf() },
            });
            closeDeleteModal();
            showNotification(res.data.message ?? 'Deleted.', 'success');
            await load();
        } catch (e) {
            showNotification(e.response?.data?.message ?? 'Failed to delete.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    document.addEventListener('DOMContentLoaded', function () { load(); });

    return { openAdd, openEdit, closeModal, save, openDeleteModal, closeDeleteModal, confirmDelete, recalcAmount, toggleInvoiceRequired, togglePaidDateRequired, reload: load };
})();
</script>
@endsection

@include('delivery.partials.section-permissions')
@include('delivery.partials.project-closed-lock', ['project' => $project])
