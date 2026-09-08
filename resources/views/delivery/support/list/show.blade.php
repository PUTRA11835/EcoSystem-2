@extends('dashboard')
@section('title', 'Support Details')
@section('page-title', 'Support Details')
@section('page-subtitle', $support->name ?? 'Support #' . $support->id)

@push('styles')
<style>
    .primary-link { color: var(--primary-color); }
    .primary-link:hover { opacity: 0.75; }
    .edit-btn:hover { color: var(--primary-color) !important; background-color: rgba(var(--primary-rgb), 0.08) !important; }
    .primary-focus:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important; outline: none !important; }
    .primary-tab-active { color: var(--primary-color) !important; border-color: var(--primary-color) !important; }
    .primary-text { color: var(--primary-color) !important; }

    html { scroll-behavior: smooth; }

    /* ── Sticky section nav — pola identik dengan Delivery Project detail ────── */
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
    .nav-spacer { height: 64px; }

    .section-tab {
        position: relative;
        transition: color 0.18s ease, background-color 0.18s ease;
        padding: 0.875rem 1.25rem;
    }
    .section-tab:hover { background-color: #f9fafb; color: #1f2937; }
    .section-tab.active { font-weight: 600; color: #991b1b; }

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

    /* overflow-x:auto membuat overflow-y ikut jadi 'auto' (spec CSS); underline
       tab aktif (::after bottom:-1px) meluber 1px → memunculkan scrollbar
       vertikal yang tak perlu. Kunci overflow-y agar hanya scroll horizontal. */
    #sectionNav nav { overflow-y: hidden; }
    #sectionNav nav::-webkit-scrollbar { height: 4px; }
    #sectionNav nav::-webkit-scrollbar-track { background: #f1f5f9; }
    #sectionNav nav::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }

    /* Section scroll margin - account for header + sticky nav height */
    section[id] { scroll-margin-top: 130px; }

    /* Responsive fixed nav */
    @media (max-width: 768px) {
        #sectionNav { top: 65px; left: 0; }  /* No sidebar on mobile */
        section[id] { scroll-margin-top: 125px; }
    }

    /* Ketika sidebar collapsed (w-20 = 80px) */
    body.sidebar-collapsed #sectionNav { left: 80px; }

    .section-animate { animation: fadeInUp 0.5s ease-out; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .scroll-indicator {
        position: fixed;
        top: 64px;            /* Di bawah header */
        left: 0;
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
        z-index: 15;
        transition: width 0.1s ease;
    }

    .card-hover { transition: box-shadow 0.3s ease, transform 0.3s ease; }
    .card-hover:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.1); transform: translateY(-2px); }

    .support-header { min-height: 80px; display: flex; align-items: center; }
    .support-title { word-break: break-word; line-height: 1.3; }

    /* Nilai read-only ditampilkan dalam kotak bergaya input agar sebangun dengan
       form inline milik Delivery Project (editing tetap lewat modal). */
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
</style>
@endpush

@section('content')
@php
    $progress      = $support->calculated_progress ?? 0;
    $progressColor = $progress >= 100 ? '#10b981' : ($progress > 50 ? '#3b82f6' : ($progress > 0 ? '#f59e0b' : '#9ca3af'));
    $progressLabel = $progress >= 100 ? 'Completed' : ($progress > 0 ? 'In Progress' : 'Not Started');
@endphp

{{-- SCROLL PROGRESS INDICATOR --}}
<div class="scroll-indicator" id="scrollIndicator"></div>

{{-- Sticky Navigation Tabs - PALING ATAS --}}
{{-- Tiap tab ikut izin `.view` section tujuannya, supaya tidak ada tab yang
     mengarah ke section yang tidak dirender untuk role tersebut. --}}
<div class="bg-white" id="sectionNav">
    <nav class="flex overflow-x-auto scrollbar-hide border-b border-gray-200">
        @if($can('delivery-support.general.view'))
        <button onclick="scrollToSection('general')" data-section="general" class="section-tab active text-sm font-medium text-gray-600 whitespace-nowrap">
            General
        </button>
        @endif
        @if($can('delivery-support.approval.view'))
        <button onclick="scrollToSection('approval')" data-section="approval" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Approval
        </button>
        @endif
        @if($can('delivery-support.financial.view'))
        <button onclick="scrollToSection('financial')" data-section="financial" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Financial
        </button>
        @endif
        @if($can('delivery-support.team.view'))
        <button onclick="scrollToSection('team')" data-section="team" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Team
        </button>
        @endif
        @if($can('delivery-support.customer-pic.view'))
        <button onclick="scrollToSection('customer-pic')" data-section="customer-pic" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap">
            Customer PIC
        </button>
        @endif
        @if($can('delivery-support.activities.view'))
        <button onclick="scrollToSection('activities')" data-section="activities" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            Activities
        </button>
        @endif
        @if($can('delivery-support.sla.view'))
        <button onclick="scrollToSection('sla')" data-section="sla" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            SLA
        </button>
        @endif
        @if($can('delivery-support.plan-cost.view'))
        <button onclick="scrollToSection('plancost')" data-section="plancost" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Plan Cost
        </button>
        @endif
        @if($can('delivery-support.recons.view'))
        <button onclick="scrollToSection('recons')" data-section="recons" class="section-tab text-sm font-medium text-gray-600 whitespace-nowrap flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            Recons
        </button>
        @endif
    </nav>
</div>

{{-- Spacer untuk fixed navigation --}}
<div class="nav-spacer"></div>

{{-- Back Button --}}
<div class="mb-4">
    <a href="{{ route('delivery.support.index') }}"
        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back
    </a>
</div>

{{-- Support Title & Actions --}}
<div class="bg-white shadow-md rounded-lg mb-4">
    <div class="support-header p-4 sm:p-6 flex justify-between items-start flex-wrap gap-4">
        <div class="flex-1 min-w-0">
            <h1 class="support-title text-2xl font-bold text-gray-800 mb-1">{{ $support->name ?? 'Support #' . $support->id }}</h1>
            <p class="text-sm text-gray-600">
                {{ $support->client->basicData->name_1 ?? 'N/A' }} •
                <span class="font-semibold">{{ $support->type ?? 'N/A' }}</span>
            </p>
            <p class="mt-1.5">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium"
                      style="background-color: {{ $progressColor }}1a; color: {{ $progressColor }};">
                    {{ $progressLabel }} • {{ number_format($progress, 1) }}%
                </span>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
            @if($can('delivery-support.general.edit'))
            <a href="{{ route('delivery.support.edit', $support->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Support
            </a>
            @endif
            @if($can('delivery-support.activities.view'))
            <a href="{{ route('delivery.support.planning.index', $support->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Open Planning
            </a>
            @endif

            {{-- Customer Deliverable Folder Dropdown --}}
            @if($can('delivery-support.documents.view'))
            <div class="relative" id="dlvDropdownContainer">
                <button type="button" id="dlvDropdownBtn" onclick="toggleDeliverableDropdown()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-all duration-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    Deliverable Folder
                    <svg id="dlvDropdownChevron" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Dropdown Panel --}}
                <div id="dlvDropdownMenu"
                     class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-200 z-40 overflow-hidden">

                    {{-- Loading --}}
                    <div id="dlvDdLoading" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-400">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Loading sub-folders…
                    </div>

                    {{-- Content --}}
                    <div id="dlvDdContent" class="hidden">
                        {{-- Header --}}
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Sub-folders</p>
                            <p id="dlvDdCustomerName" class="text-xs text-gray-400 mt-0.5 truncate"></p>
                        </div>

                        {{-- Sub-folder list --}}
                        <div id="dlvDdList" class="max-h-56 overflow-y-auto divide-y divide-gray-50">
                            {{-- filled by JS --}}
                        </div>

                        {{-- Empty state (shown when no sub-folders) --}}
                        <div id="dlvDdEmpty" class="hidden px-4 py-4 text-sm text-gray-400 text-center">
                            <svg class="w-8 h-8 mx-auto mb-1 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                            </svg>
                            No sub-folders yet
                        </div>

                        {{-- Create new --}}
                        @if($can('delivery-support.documents.manage'))
                        <div class="border-t border-gray-100">
                            <button type="button"
                                    onclick="closeDeliverableDropdown(); openDeliverableModal()"
                                    class="w-full flex items-center gap-2 px-4 py-3 text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create New Sub-folder
                            </button>
                        </div>
                        @endif
                    </div>

                    {{-- Error state --}}
                    <div id="dlvDdError" class="hidden px-4 py-3 text-sm text-red-500">
                        <span id="dlvDdErrorMsg">Failed to load sub-folders.</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Documents & Updates — handler-nya masih stub (lihat openDocumentsModal/openUpdatesModal) --}}
            @if($can('delivery-support.documents.view'))
            <button type="button" onclick="openDocumentsModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Documents
                <span class="text-xs text-gray-500">{{ $support->documents->count() ?? 0 }}</span>
            </button>
            @endif
            @if($can('delivery-support.activities.view'))
            <button type="button" onclick="openUpdatesModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Updates &amp; Notes
                <span class="text-xs text-gray-500">{{ $support->updates->count() ?? 0 }}</span>
            </button>
            @endif

            @if($can('delivery-support.remove-ticket'))
            <button type="button" onclick="openRemoveTicketModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-orange-100 text-orange-700 text-sm font-semibold rounded-lg hover:bg-orange-200 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                Remove Ticket
                @if($linkedTickets->count() > 0)
                <span class="text-xs bg-orange-200 text-orange-800 font-semibold px-1.5 py-0.5 rounded-full">{{ $linkedTickets->count() }}</span>
                @endif
            </button>
            @endif

            @if($can('delivery-support.delete-support'))
            <form id="deleteSupportForm" action="{{ route('delivery.support.destroy', $support->id, false) }}" method="POST" class="contents">
                @csrf
                @method('DELETE')
                <button type="button" onclick="confirmDeleteSupport()"
                        class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    Delete Support
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- GENERAL — Support Information + Overall Progress                           --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.general.view'))
<section id="general" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.general.edit') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Support Information</h2>
            @if($can('delivery-support.general.edit'))
            <button type="button" onclick="openEditModal('support-info')"
                    class="p-2 text-gray-400 edit-btn rounded-lg transition"
                    title="Edit Support Information">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            @endif
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Field grid --}}
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Support Name</label>
                            <div class="display-box font-semibold" id="display-name">{{ $support->name ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Client</label>
                            <div class="display-box" id="display-client">{{ $support->client->basicData->name_1 ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Type</label>
                            <div class="display-box">
                                @if($support->type)
                                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800" id="display-type">
                                        {{ $support->type }}
                                    </span>
                                @else
                                    <span class="text-gray-400" id="display-type">No type set</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Support Method</label>
                            <div class="display-box" id="display-support_method">{{ $support->support_method ?? 'N/A' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Modules</label>
                            <div class="display-box flex flex-wrap gap-1.5" id="display-modules">
                                @forelse($support->modules as $module)
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800">{{ $module->name }}</span>
                                @empty
                                    <span class="text-gray-400">N/A</span>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Start Date</label>
                            <div class="display-box" id="display-start_date">{{ $support->start_date ? $support->start_date->format('d F Y') : 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">End Date</label>
                            <div class="display-box" id="display-end_date">{{ $support->end_date ? $support->end_date->format('d F Y') : 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Resolution Estimated</label>
                            <div class="display-box" id="display-resolution_estimated">{{ $support->resolution_estimated ? $support->resolution_estimated->format('d F Y') : 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Total Mandays</label>
                            <div class="display-box" id="display-total_mandays">{{ $support->total_mandays ?? '0' }} days</div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Service Window</label>
                            <div class="display-box" id="display-service_window">
                                @if($support->service_window_start && $support->service_window_end)
                                    {{ \Illuminate\Support\Str::substr($support->service_window_start, 0, 5) }} – {{ \Illuminate\Support\Str::substr($support->service_window_end, 0, 5) }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Overall Progress --}}
                <div class="lg:col-span-1">
                    <div class="h-full border border-gray-200 rounded-lg p-6 flex flex-col items-center justify-center bg-gray-50">
                        <p class="text-sm font-medium text-gray-500 mb-4">Overall Progress</p>
                        <div class="relative w-32 h-32 mb-4">
                            <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" fill="transparent" stroke="#e5e7eb" stroke-width="8"/>
                                <circle cx="50" cy="50" r="45" fill="transparent"
                                        stroke="{{ $progressColor }}"
                                        stroke-width="8"
                                        stroke-dasharray="{{ 283 * $progress / 100 }} 283"
                                        stroke-linecap="round"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-3xl font-bold" style="color: {{ $progressColor }}">{{ number_format($progress, 0) }}%</span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500">{{ $progressLabel }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- APPROVAL INFORMATION                                                       --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.approval.view'))
<section id="approval" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.approval.edit') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Approval Information</h2>
            @if($can('delivery-support.approval.edit'))
            <button type="button" onclick="openEditModal('approval-info')"
                    class="p-2 text-gray-400 edit-btn rounded-lg transition"
                    title="Edit Approval Information">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            @endif
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Approval Date</label>
                    <div class="display-box" id="display-approval_date">{{ $support->approval_date ? $support->approval_date->format('d F Y') : 'Not approved yet' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Approved By</label>
                    <div class="display-box" id="display-approval_name">{{ $support->approval_name ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- FINANCIAL — Sales Data + IO Number + Term Of Payment Plan                  --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.financial.view'))
<section id="financial" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.financial.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-support.financial.manage') ? '1' : '0' }}">
    @include('delivery.support.list.partials.financial-info')
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- TEAM                                                                       --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.team.view'))
<section id="team" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.team.manage') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-support.team.manage') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Team</h2>
            @if($can('delivery-support.team.manage'))
            <button type="button" onclick="openEditModal('team-info')"
                    class="p-2 text-gray-400 edit-btn rounded-lg transition"
                    title="Edit Team">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            @endif
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <div class="flex items-center space-x-3 border border-gray-200 rounded-lg p-4" id="team-delivery-owner">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                        DO
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">Delivery Owner</p>
                        <p class="text-xs text-gray-500 break-words" id="display-delivery_owner">{{ $support->deliveryOwner->basicData->full_name ?? 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 border border-gray-200 rounded-lg p-4" id="team-support-manager">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                        SM
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">Support Manager</p>
                        <p class="text-xs text-gray-500 break-words" id="display-support_manager">{{ $support->supportManagers->pluck('basicData.full_name')->filter()->join(', ') ?: 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 border border-gray-200 rounded-lg p-4" id="team-co-pm">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                        CP
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">Co PM</p>
                        <p class="text-xs text-gray-500 break-words" id="display-co_pm">{{ $support->coPm->basicData->full_name ?? 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 border border-gray-200 rounded-lg p-4" id="team-support-admin">
                    <div class="w-10 h-10 bg-amber-500 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                        SA
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">Support Admin</p>
                        <p class="text-xs text-gray-500 break-words" id="display-support_admin">{{ $support->supportAdmin->basicData->full_name ?? 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 border border-gray-200 rounded-lg p-4" id="team-sales">
                    <div class="w-10 h-10 bg-pink-500 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                        SL
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">Sales</p>
                        <p class="text-xs text-gray-500 break-words" id="display-sales">{{ $support->sales->basicData->full_name ?? 'Not assigned' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- CUSTOMER PIC                                                               --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.customer-pic.view'))
<section id="customer-pic" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.customer-pic.edit') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700">Customer PIC</h2>
            @if($can('delivery-support.customer-pic.edit'))
            <button type="button" onclick="openCustomerPicModal()"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Manage Customer PIC
            </button>
            @endif
        </div>
        <div class="p-6" id="customerPicPanel">
            <div id="customerPicList" class="space-y-2">
                <p class="text-xs text-gray-400 italic">Loading...</p>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- ACTIVITIES                                                                 --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.activities.view'))
<section id="activities" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.activities.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-support.activities.manage') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Activities
            </h2>
            <a href="{{ route('delivery.support.planning.index', $support->id) }}" class="text-sm primary-link">
                View all <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="p-6">
            @if($support->activities && $support->activities->count() > 0)
                <div class="space-y-3">
                    @foreach($support->activities->take(5) as $activity)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $activity->status === 'completed' ? 'bg-green-500' : ($activity->status === 'in_progress' ? 'bg-blue-500' : 'bg-gray-300') }}"></div>
                                <span class="text-sm font-medium text-gray-900 truncate">{{ $activity->name }}</span>
                            </div>
                            <div class="flex items-center space-x-3 flex-shrink-0">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full bg-blue-500" style="width: {{ $activity->progress_percentage }}%;"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ number_format($activity->progress_percentage, 0) }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($support->activities->count() > 5)
                    <p class="mt-3 text-sm text-gray-500 text-center">
                        + {{ $support->activities->count() - 5 }} more activities
                    </p>
                @endif
            @else
                <div class="text-center py-6">
                    <p class="text-gray-500">No activities yet</p>
                    <a href="{{ route('delivery.support.planning.index', $support->id) }}" class="inline-flex items-center mt-2 text-sm primary-link">
                        <i class="fas fa-plus mr-1"></i>
                        Add activities
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- SLA CONFIGURATION                                                          --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.sla.view'))
<section id="sla" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.sla.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-support.sla.manage') ? '1' : '0' }}">
    <div class="bg-white shadow-md rounded-lg">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2 primary-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    SLA Configuration
                </h2>
                <p class="text-xs text-gray-500 mt-1">Response &amp; resolution targets untuk delivery ini</p>
            </div>
            @if($canManage && $can('delivery-support.sla.manage'))
            <button onclick="openSlaAddModal()"
                class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus text-xs"></i> Add Policy
            </button>
            @endif
        </div>
        <div class="px-6 pb-6 pt-5">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="text-left px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Scale</th>
                            <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Response (h)</th>
                            <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Resolution (h)</th>
                            <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Mode</th>
                            <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                            @if($canManage && $can('delivery-support.sla.manage'))
                            <th class="text-center px-4 py-2.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="slaPolicyTableBody">
                        <tr>
                            <td colspan="{{ ($canManage && $can('delivery-support.sla.manage')) ? 7 : 6 }}" class="py-10 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-300">
                                    <i class="fas fa-spinner fa-spin text-2xl"></i>
                                    <p class="text-xs">Loading policies...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST                                                                  --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.plan-cost.view'))
<section id="plancost" class="mb-6 card-hover section-animate" data-perm-edit="{{ $can('delivery-support.plan-cost.edit') ? '1' : '0' }}" data-perm-manage="{{ $can('delivery-support.plan-cost.manage') ? '1' : '0' }}">
    @include('delivery.support.list.partials.plancost')
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- RECONS                                                                     --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($can('delivery-support.recons.view'))
<section id="recons" class="mb-6 card-hover section-animate">
    @include('delivery.support.list.partials.recons')
</section>
@endif

{{-- ============================================================================ --}}
{{-- ONEDRIVE MODAL --}}
{{-- ============================================================================ --}}
{{-- CUSTOMER DELIVERABLE FOLDER MODAL --}}
{{-- ============================================================================ --}}
<div id="deliverableModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeDeliverableModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full z-10 overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    <h3 class="text-base font-semibold text-gray-900">Customer Deliverable Folder</h3>
                </div>
                <button onclick="closeDeliverableModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-4">
                {{-- Info path preview --}}
                <div class="bg-gray-50 rounded-lg px-4 py-3 text-xs text-gray-500 font-mono leading-relaxed">
                    <span class="text-gray-400">Delivery Support / Customer Deliverable /</span><br>
                    <span class="text-emerald-700 font-semibold" id="dlvCustomerFolderPreview">
                        {{ str_pad($support->client_id, 3, '0', STR_PAD_LEFT) }} {{ strtoupper($support->client->basicData->name_1 ?? 'CUSTOMER') }}
                    </span><span class="text-gray-400"> /</span><br>
                    <span class="text-amber-700 font-semibold" id="dlvSupportFolderPreview">
                        {{ $support->name }}
                    </span><span class="text-gray-400"> /</span><br>
                    <span class="text-blue-700 font-semibold" id="dlvSubfolderPreview">subfolder name...</span>
                </div>

                {{-- Subfolder name input --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Sub-Folder Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="dlvSubfolderName"
                           placeholder="e.g. CONTRACT#001 ATS 2026"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                           oninput="document.getElementById('dlvSubfolderPreview').textContent = this.value || 'subfolder name...'">
                    <p class="text-xs text-gray-400 mt-1">Cannot contain: \ / : * ? " &lt; &gt; |</p>
                </div>

                @if($support->onedrive_deliverable_folder_url)
                <div class="bg-emerald-50 rounded-lg p-3 text-xs text-emerald-700">
                    <span class="font-semibold">This support folder already exists.</span>
                    Each new sub-folder is added inside it (existing ones are kept).
                </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex gap-2 px-6 pb-5">
                <button onclick="generateDeliverableFolder()" id="dlvGenerateBtn"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 active:bg-emerald-800 transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg id="dlvGenerateIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    <svg id="dlvGenerateSpinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span id="dlvGenerateLabel">Generate Folder</span>
                </button>
                <button type="button" onclick="closeDeliverableModal()"
                    class="px-4 py-2.5 border border-gray-300 text-sm text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                    Cancel
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ============================================================================ --}}
{{-- EDIT MODALS --}}
{{-- ============================================================================ --}}

{{-- Support Information Modal --}}
<div id="modal-support-info" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal('support-info')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-2xl">
            <form id="form-support-info" onsubmit="saveSection(event, 'support-info')">
                <div class="primary-gradient px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Edit Support Information</h3>
                        <button type="button" onclick="closeEditModal('support-info')" class="text-white hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Support Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="edit-name" required
                               value="{{ $support->name }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                            @php
                                $currentClientLabel = '';
                                if ($support->client_id) {
                                    foreach($clients ?? [] as $_c) {
                                        if ($_c->customer_id == $support->client_id) {
                                            $currentClientLabel = $_c->basicData->name_1 ?? 'N/A';
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            <div class="custom-dd relative" data-fixed="true">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                    <span class="custom-dd-label {{ $currentClientLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentClientLabel ?: 'Select Client' }}</span>
                                    <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" name="client_id" id="edit-client_id" value="{{ $support->client_id }}" required>
                                <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Client</button>
                                    @foreach($clients ?? [] as $client)
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $client->customer_id }}">{{ $client->basicData->name_1 ?? 'N/A' }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Support Method</label>
                            <select name="support_method" id="edit-support_method"
                                    class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                                <option value="">Select method</option>
                                <option value="Remote" {{ $support->support_method == 'Remote' ? 'selected' : '' }}>Remote</option>
                                <option value="On-Site" {{ $support->support_method == 'On-Site' ? 'selected' : '' }}>On-Site</option>
                                <option value="Hybrid" {{ $support->support_method == 'Hybrid' ? 'selected' : '' }}>Hybrid</option>
                            </select>
                        </div>
                    </div>
                    @php
                        $currentModuleIds   = $support->modules->pluck('id')->implode(',');
                        $currentModuleLabel = $support->modules->pluck('name')->filter()->join(', ');
                    @endphp
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Modules</label>
                        <div class="custom-dd relative" data-fixed="true" data-multi="true" data-placeholder="Select module(s)">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $currentModuleLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentModuleLabel ?: 'Select module(s)' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="module_ids" id="edit-module_ids" value="{{ $currentModuleIds }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                    <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search module…" autocomplete="off" spellcheck="false">
                                </div>
                                @foreach($modules ?? [] as $module)
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $module->id }}">
                                        <span class="custom-dd-item-text">{{ $module->name }}</span>
                                        <svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endforeach
                                <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                            </div>
                        </div>
                    </div>
                    @php $roleId = session('user.role.id'); @endphp
                    @if(in_array($roleId, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" id="edit-type"
                                class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                            <option value="">No type set</option>
                            <option value="AMS" {{ $support->type == 'AMS' ? 'selected' : '' }}>AMS</option>
                            <option value="MO" {{ $support->type == 'MO' ? 'selected' : '' }}>MO</option>
                            <option value="ATS" {{ $support->type == 'ATS' ? 'selected' : '' }}>ATS</option>
                            <option value="CR" {{ $support->type == 'CR' ? 'selected' : '' }}>CR</option>
                            <option value="RISE" {{ $support->type == 'RISE' ? 'selected' : '' }}>RISE</option>
                            <option value="CLOUD" {{ $support->type == 'CLOUD' ? 'selected' : '' }}>CLOUD</option>
                            <option value="POSTPAID" {{ $support->type == 'POSTPAID' ? 'selected' : '' }}>POSTPAID</option>
                            <option value="Project" {{ $support->type == 'Project' ? 'selected' : '' }}>Project</option>
                            <option value="Internal" {{ $support->type == 'Internal' ? 'selected' : '' }}>Internal</option>
                        </select>
                    </div>
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="edit-start_date"
                                   value="{{ $support->start_date?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="edit-end_date"
                                   value="{{ $support->end_date?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Estimated</label>
                            <input type="date" name="resolution_estimated" id="edit-resolution_estimated"
                                   value="{{ $support->resolution_estimated?->format('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Mandays</label>
                        <input type="number" name="total_mandays" id="edit-total_mandays" min="0"
                               value="{{ $support->total_mandays }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Service Window
                            <span class="ml-1 text-xs font-normal text-gray-400">(operational hours per day)</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Start Time</label>
                                <input type="time" name="service_window_start" id="edit-service_window_start"
                                       value="{{ $support->service_window_start ? \Illuminate\Support\Str::substr($support->service_window_start, 0, 5) : '' }}"
                                       class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">End Time</label>
                                <input type="time" name="service_window_end" id="edit-service_window_end"
                                       value="{{ $support->service_window_end ? \Illuminate\Support\Str::substr($support->service_window_end, 0, 5) : '' }}"
                                       class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                            </div>
                        </div>
                        <p id="edit-sw-error" class="mt-1 text-xs text-red-500 hidden">End time must be after start time.</p>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('support-info')"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Approval Information Modal --}}
<div id="modal-approval-info" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal('approval-info')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-lg">
            <form id="form-approval-info" onsubmit="saveSection(event, 'approval-info')">
                <div class="primary-gradient px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Edit Approval Information</h3>
                        <button type="button" onclick="closeEditModal('approval-info')" class="text-white hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Approval Date</label>
                        <input type="date" name="approval_date" id="edit-approval_date"
                               value="{{ $support->approval_date?->format('Y-m-d') }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                        <input type="text" name="approval_name" id="edit-approval_name"
                               value="{{ $support->approval_name }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm"
                               placeholder="Enter approver name">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('approval-info')"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Team Information Modal --}}
<div id="modal-team-info" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal('team-info')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-lg">
            <form id="form-team-info" onsubmit="saveSection(event, 'team-info')">
                <div class="primary-gradient px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Edit Team Assignment</h3>
                        <button type="button" onclick="closeEditModal('team-info')" class="text-white hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                    @php
                        $currentLabels = [
                            'delivery_owner_id'  => '',
                            'co_pm_id'           => '',
                            'support_admin_id'   => '',
                            'sales_id'           => '',
                        ];
                        foreach($employees ?? [] as $_e) {
                            $name = $_e->basicData->full_name ?? 'N/A';
                            if ($support->delivery_owner_id  == $_e->employee_id) $currentLabels['delivery_owner_id']  = $name;
                            if ($support->co_pm_id           == $_e->employee_id) $currentLabels['co_pm_id']           = $name;
                            if ($support->support_admin_id   == $_e->employee_id) $currentLabels['support_admin_id']   = $name;
                            if ($support->sales_id           == $_e->employee_id) $currentLabels['sales_id']           = $name;
                        }

                        $teamFields = [
                            ['key' => 'delivery_owner_id',  'label' => 'Delivery Owner',  'value' => $support->delivery_owner_id],
                            ['key' => 'co_pm_id',           'label' => 'Co PM',           'value' => $support->co_pm_id],
                            ['key' => 'support_admin_id',   'label' => 'Support Admin',   'value' => $support->support_admin_id],
                            ['key' => 'sales_id',           'label' => 'Sales',           'value' => $support->sales_id],
                        ];

                        $currentSupportManagerIds    = $support->supportManagers->pluck('employee_id')->implode(',');
                        $currentSupportManagerLabel  = $support->supportManagers->pluck('basicData.full_name')->filter()->join(', ');
                    @endphp

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Support Manager</label>
                        <div class="custom-dd relative" data-fixed="true" data-multi="true" data-placeholder="Not assigned">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $currentSupportManagerLabel ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentSupportManagerLabel ?: 'Not assigned' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="support_manager_ids" id="edit-support_manager_ids" value="{{ $currentSupportManagerIds }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                    <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                </div>
                                @foreach($employees ?? [] as $employee)
                                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">
                                        <span class="custom-dd-item-text">{{ $employee->basicData->full_name ?? 'N/A' }}</span>
                                        <svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endforeach
                                <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                            </div>
                        </div>
                    </div>

                    @foreach($teamFields as $tf)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $tf['label'] }}</label>
                        <div class="custom-dd relative" data-fixed="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                                <span class="custom-dd-label {{ $currentLabels[$tf['key']] ? 'text-gray-700' : 'text-gray-500' }}">{{ $currentLabels[$tf['key']] ?: 'Not assigned' }}</span>
                                <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="{{ $tf['key'] }}" id="edit-{{ $tf['key'] }}" value="{{ $tf['value'] }}">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:400px;">
                                <div class="custom-dd-search-wrap sticky top-0 bg-white border-b border-gray-100 px-2 py-2" style="z-index:1">
                                    <input type="text" class="custom-dd-search w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400" placeholder="Search employee…" autocomplete="off" spellcheck="false">
                                </div>
                                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Not assigned</button>
                                @foreach($employees ?? [] as $employee)
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $employee->employee_id }}">{{ $employee->basicData->full_name ?? 'N/A' }}</button>
                                @endforeach
                                <div class="custom-dd-empty hidden px-4 py-3 text-sm text-gray-400 text-center">No results</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeEditModal('team-info')"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const supportId = {{ $support->id }};
const csrfToken = '{{ csrf_token() }}';

// Employee data for updating display
const employeesData = @json($employees ?? []);
const clientsData = @json($clients ?? []);
const modulesData = @json($modules ?? []);

// ============================================================================
// DELETE SUPPORT (uses global showConfirm modal — no browser confirm())
// ============================================================================
async function confirmDeleteSupport() {
    const ok = await showConfirm(
        'Are you sure you want to delete this support delivery? This cannot be undone.',
        'Delete Support Delivery',
        'danger',
        { okText: 'Delete' }
    );
    if (ok) document.getElementById('deleteSupportForm').submit();
}

// ============================================================================
// MODAL FUNCTIONS
// ============================================================================
function openEditModal(section) {
    document.getElementById(`modal-${section}`).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeEditModal(section) {
    document.getElementById(`modal-${section}`).classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ============================================================================
// SAVE SECTION DATA
// ============================================================================
function saveSection(event, section) {
    event.preventDefault();

    const form = document.getElementById(`form-${section}`);
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...';
    submitBtn.disabled = true;

    fetch(`/delivery/support/${supportId}/field`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ section: section, data: data })
    })
    .then(async response => {
        const json = await response.json();
        if (!response.ok) {
            throw json;
        }
        showNotification(json.message || 'Changes saved successfully', 'success');
        closeEditModal(section);
        updateDisplayValues(section, data);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    })
    .catch(error => {
        console.error('Error saving:', error);
        showNotification(error.message || 'Failed to save changes', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// ============================================================================
// UPDATE DISPLAY VALUES
// ============================================================================
function updateDisplayValues(section, data) {
    if (section === 'support-info') {
        if (data.name) document.getElementById('display-name').textContent = data.name;
        if (data.support_method) document.getElementById('display-support_method').textContent = data.support_method || 'N/A';
        if (data.total_mandays !== undefined) document.getElementById('display-total_mandays').textContent = (data.total_mandays || 0) + ' days';

        // Update dates
        if (data.start_date) {
            const startDate = new Date(data.start_date);
            document.getElementById('display-start_date').textContent = startDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        }
        if (data.end_date) {
            const endDate = new Date(data.end_date);
            document.getElementById('display-end_date').textContent = endDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        }
        if (data.resolution_estimated) {
            const resDate = new Date(data.resolution_estimated);
            document.getElementById('display-resolution_estimated').textContent = resDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        }

        // Update client
        if (data.client_id) {
            const client = clientsData.find(c => c.customer_id == data.client_id);
            if (client) {
                document.getElementById('display-client').textContent = client.basic_data?.name_1 || 'N/A';
            }
        }

        // Update type display
        const typeEl = document.getElementById('display-type');
        if (typeEl) {
            if (data.type) {
                typeEl.outerHTML = `<span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800" id="display-type">${data.type}</span>`;
            } else {
                typeEl.outerHTML = `<p class="text-gray-400" id="display-type">No type set</p>`;
            }
        }

        // Update modules display
        const modulesEl = document.getElementById('display-modules');
        if (modulesEl) {
            const ids = (data.module_ids || '').split(',').filter(Boolean);
            const badges = ids
                .map(id => modulesData.find(m => m.id == id)?.name)
                .filter(Boolean)
                .map(name => `<span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800">${name}</span>`);
            modulesEl.innerHTML = badges.length ? badges.join('') : '<span class="text-gray-400">N/A</span>';
        }

        // Update service window display
        const swEl = document.getElementById('display-service_window');
        if (swEl) {
            const swStart = data.service_window_start || '';
            const swEnd   = data.service_window_end   || '';
            swEl.textContent = (swStart && swEnd) ? `${swStart} – ${swEnd}` : 'N/A';
        }
    }
    else if (section === 'approval-info') {
        if (data.approval_date) {
            const approvalDate = new Date(data.approval_date);
            document.getElementById('display-approval_date').textContent = approvalDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        } else {
            document.getElementById('display-approval_date').textContent = 'Not approved yet';
        }
        document.getElementById('display-approval_name').textContent = data.approval_name || 'N/A';
    }
    else if (section === 'team-info') {
        const teamMap = {
            delivery_owner_id: 'display-delivery_owner',
            co_pm_id:          'display-co_pm',
            support_admin_id:  'display-support_admin',
            sales_id:          'display-sales',
        };
        Object.entries(teamMap).forEach(([field, displayId]) => {
            const el = document.getElementById(displayId);
            if (!el) return;
            const empId = data[field];
            if (empId) {
                const emp = employeesData.find(e => e.employee_id == empId);
                el.textContent = emp?.basic_data?.full_name || 'Not assigned';
            } else {
                el.textContent = 'Not assigned';
            }
        });

        const smEl = document.getElementById('display-support_manager');
        if (smEl) {
            const ids = (data.support_manager_ids || '').split(',').filter(Boolean);
            const names = ids
                .map(id => employeesData.find(e => e.employee_id == id)?.basic_data?.full_name)
                .filter(Boolean);
            smEl.textContent = names.length ? names.join(', ') : 'Not assigned';
        }
    }
}

// ============================================================================
// OTHER MODALS (TO BE IMPLEMENTED)
// ============================================================================
function openDocumentsModal() {
    showNotification('Documents modal will be implemented', 'info');
}

function openUpdatesModal() {
    showNotification('Updates modal will be implemented', 'info');
}

// ============================================================================
// INITIALIZATION
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    // Init custom-dd untuk semua dropdown (Client, Delivery Owner, Support Manager).
    // Auto-inject search input bila > 7 item (lihat custom-dropdown.js).
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }

    // Service window validation inside edit modal
    const editSwStart = document.getElementById('edit-service_window_start');
    const editSwEnd   = document.getElementById('edit-service_window_end');
    const editSwError = document.getElementById('edit-sw-error');

    function validateEditServiceWindow() {
        if (editSwStart.value && editSwEnd.value && editSwEnd.value < editSwStart.value) {
            editSwError.classList.remove('hidden');
            editSwEnd.setCustomValidity('End time must be after start time.');
        } else {
            editSwError.classList.add('hidden');
            editSwEnd.setCustomValidity('');
        }
    }
    editSwStart.addEventListener('change', validateEditServiceWindow);
    editSwEnd.addEventListener('change', validateEditServiceWindow);


    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[id^="modal-"]').forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    const section = modal.id.replace('modal-', '');
                    closeEditModal(section);
                }
            });
        }
    });
});

// =========================================================================
// CUSTOMER DELIVERABLE FOLDER — DROPDOWN
// =========================================================================

let _dlvDropdownOpen   = false;
let _dlvSubfoldersLoaded = false;

function toggleDeliverableDropdown() {
    if (_dlvDropdownOpen) {
        closeDeliverableDropdown();
    } else {
        openDeliverableDropdown();
    }
}

function openDeliverableDropdown() {
    _dlvDropdownOpen = true;
    document.getElementById('dlvDropdownMenu').classList.remove('hidden');
    document.getElementById('dlvDropdownChevron').style.transform = 'rotate(180deg)';
    if (!_dlvSubfoldersLoaded) {
        fetchDeliverableSubfolders();
    }
}

function closeDeliverableDropdown() {
    _dlvDropdownOpen = false;
    document.getElementById('dlvDropdownMenu').classList.add('hidden');
    document.getElementById('dlvDropdownChevron').style.transform = 'rotate(0deg)';
}

async function fetchDeliverableSubfolders() {
    // Show loading, hide others
    document.getElementById('dlvDdLoading').classList.remove('hidden');
    document.getElementById('dlvDdContent').classList.add('hidden');
    document.getElementById('dlvDdError').classList.add('hidden');

    try {
        const res  = await fetch('{{ route('delivery.support.deliverable-subfolders', $support->id, false) }}', {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (!res.ok || data.error) {
            throw new Error(data.error || 'Server error');
        }

        // Populate customer name
        document.getElementById('dlvDdCustomerName').textContent = data.customer_folder ?? '';

        // Populate list
        const list = document.getElementById('dlvDdList');
        const empty = document.getElementById('dlvDdEmpty');
        list.innerHTML = '';

        if (data.subfolders && data.subfolders.length > 0) {
            empty.classList.add('hidden');
            data.subfolders.forEach(folder => {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-1 px-3 py-2 hover:bg-emerald-50 group cursor-pointer';
                row.title = 'Open folder in OneDrive';
                row.onclick = () => openDeliverableLink(folder.id, row);
                row.innerHTML = `
                    <svg class="w-4 h-4 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    <span class="flex-1 min-w-0 text-sm text-gray-700 truncate px-1">${escapeHtml(folder.name)}</span>
                    <button type="button" class="dlv-copy-btn flex-shrink-0 p-1 rounded text-gray-400 hover:text-emerald-600 hover:bg-emerald-100 opacity-0 group-hover:opacity-100 transition" title="Copy shareable link (anyone with the link can open)">
                        <svg class="dlv-copy-icon w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5"/>
                        </svg>
                        <svg class="dlv-copy-spin hidden animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </button>
                    <svg class="dlv-open-icon w-3.5 h-3.5 flex-shrink-0 text-gray-400 group-hover:text-emerald-600 opacity-0 group-hover:opacity-100 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <svg class="dlv-spin-icon hidden animate-spin w-3.5 h-3.5 flex-shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>`;
                row.querySelector('.dlv-copy-btn').onclick = (e) => {
                    e.stopPropagation();
                    copyDeliverableLink(folder.id, row);
                };
                list.appendChild(row);
            });
        } else {
            empty.classList.remove('hidden');
        }

        _dlvSubfoldersLoaded = true;
        document.getElementById('dlvDdLoading').classList.add('hidden');
        document.getElementById('dlvDdContent').classList.remove('hidden');

    } catch (err) {
        document.getElementById('dlvDdLoading').classList.add('hidden');
        document.getElementById('dlvDdErrorMsg').textContent = 'Failed to load: ' + err.message;
        document.getElementById('dlvDdError').classList.remove('hidden');
    }
}

function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function openDeliverableLink(folderId, row) {
    const openIcon = row.querySelector('.dlv-open-icon');
    const spinIcon = row.querySelector('.dlv-spin-icon');

    // Buka tab kosong dulu di dalam gesture klik agar tidak diblok popup blocker,
    // lalu arahkan ke URL share setelah fetch selesai.
    const tab = window.open('', '_blank');

    openIcon.classList.add('hidden');
    spinIcon.classList.remove('hidden');

    try {
        const res  = await fetch('{{ route('delivery.support.deliverable-share-link', $support->id, false) }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ folder_id: folderId }),
        });
        const data = await res.json();

        if (data.success && data.url) {
            if (tab) { tab.location.href = data.url; }
            else { window.open(data.url, '_blank', 'noopener'); }
            // Link bisa saja diturunkan ke scope internal oleh kebijakan tenant —
            // beri tahu sekarang, jangan sampai baru ketahuan dari keluhan customer.
            if (data.link_warning) showToast(data.link_warning, 'error');
        } else {
            throw new Error(data.message || 'Failed to get link');
        }
    } catch (err) {
        if (tab) { tab.close(); }
        showToast('Failed to open folder: ' + err.message, 'error');
    } finally {
        spinIcon.classList.add('hidden');
        openIcon.classList.remove('hidden');
    }
}

// Salin anonymous share link (bukan URL address-bar SharePoint yang terikat izin).
// Link ini bisa dibuka siapa saja & aman disimpan sebagai credential company.
async function copyDeliverableLink(folderId, row) {
    const btn      = row.querySelector('.dlv-copy-btn');
    const copyIcon = row.querySelector('.dlv-copy-icon');
    const copySpin = row.querySelector('.dlv-copy-spin');

    copyIcon.classList.add('hidden');
    copySpin.classList.remove('hidden');
    if (btn) btn.disabled = true;

    try {
        const res  = await fetch('{{ route('delivery.support.deliverable-share-link', $support->id, false) }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ folder_id: folderId }),
        });
        const data = await res.json();

        if (!data.success || !data.url) {
            throw new Error(data.message || 'Failed to get link');
        }

        let copied = false;
        try {
            await navigator.clipboard.writeText(data.url);
            copied = true;
        } catch (_) {
            // Fallback untuk konteks non-secure / clipboard API diblok
            const ta = document.createElement('textarea');
            ta.value = data.url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            copied = document.execCommand('copy');
            document.body.removeChild(ta);
        }

        if (data.link_warning) {
            // Jangan klaim "anyone with this link" kalau scope-nya ternyata internal.
            if (!copied) window.prompt('Copy this link:', data.url);
            showToast(data.link_warning, 'error');
        } else if (copied) {
            showToast('Shareable link copied — anyone with this link can open the folder.', 'success');
        } else {
            window.prompt('Copy this shareable link:', data.url);
        }
    } catch (err) {
        showToast('Failed to copy link: ' + err.message, 'error');
    } finally {
        copySpin.classList.add('hidden');
        copyIcon.classList.remove('hidden');
        if (btn) btn.disabled = false;
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('dlvDropdownContainer');
    if (container && !container.contains(e.target)) {
        closeDeliverableDropdown();
    }
});

// ── Deliverable Modal ──────────────────────────────────────────────────────

function openDeliverableModal() {
    document.getElementById('deliverableModal').classList.remove('hidden');
    const input = document.getElementById('dlvSubfolderName');
    input.value = '';
    document.getElementById('dlvSubfolderPreview').textContent = 'subfolder name...';
    setTimeout(() => input.focus(), 100);
}

function closeDeliverableModal() {
    document.getElementById('deliverableModal').classList.add('hidden');
}

async function generateDeliverableFolder() {
    const name = document.getElementById('dlvSubfolderName').value.trim();
    if (!name) {
        showToast('Sub-folder name is required.', 'error');
        document.getElementById('dlvSubfolderName').focus();
        return;
    }

    const btn     = document.getElementById('dlvGenerateBtn');
    const icon    = document.getElementById('dlvGenerateIcon');
    const spinner = document.getElementById('dlvGenerateSpinner');
    const label   = document.getElementById('dlvGenerateLabel');

    btn.disabled = true;
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    label.textContent = 'Generating…';

    try {
        const res  = await fetch('{{ route('delivery.support.generate-deliverable-folder', $support->id, false) }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ subfolder_name: name }),
        });
        const data = await res.json();

        if (data.success) {
            closeDeliverableModal();
            showToast('Customer deliverable folder created!', 'success');
            // Force reload sub-folder list on next dropdown open
            _dlvSubfoldersLoaded = false;
        } else {
            showToast(data.message || 'Failed to generate deliverable folder.', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        icon.classList.remove('hidden');
        spinner.classList.add('hidden');
        label.textContent = 'Generate Folder';
    }
}

// ── SLA Policies (scoped to this delivery support) ───────────────────────────

const SLA_DS_ID    = {{ $support->id }};
const SLA_CAN_MGMT = {{ ($canManage && $can('delivery-support.sla.manage')) ? 'true' : 'false' }};
const SLA_COL      = SLA_CAN_MGMT ? 7 : 6;

const SLA_PRIO_NUM   = { 'Very High': 1, 'High': 2, 'Medium': 3, 'Low': 4 };
const SLA_PRIO_LABEL = { 'Very High': '1: Very High', 'High': '2: High', 'Medium': '3: Medium', 'Low': '4: Low' };
const SLA_PRIO_CFG   = {
    'Very High': { bg: 'bg-red-50',    text: 'text-red-700',    dot: 'bg-red-500'    },
    'High':      { bg: 'bg-orange-50', text: 'text-orange-700', dot: 'bg-orange-500' },
    'Medium':    { bg: 'bg-yellow-50', text: 'text-yellow-700', dot: 'bg-yellow-500' },
    'Low':       { bg: 'bg-blue-50',   text: 'text-blue-700',   dot: 'bg-blue-400'   },
};

let _slaPolicies = [];

async function loadSlaPolicies() {
    const tbody = document.getElementById('slaPolicyTableBody');
    tbody.innerHTML = `<tr><td colspan="${SLA_COL}" class="py-10 text-center">
        <div class="flex flex-col items-center gap-2 text-gray-300">
            <i class="fas fa-spinner fa-spin text-2xl"></i>
            <p class="text-xs">Loading...</p>
        </div></td></tr>`;
    try {
        const res  = await fetch(`/api/admin/sla/policies?delivery_support_id=${SLA_DS_ID}`, { credentials: 'include' });
        const json = await res.json();
        _slaPolicies = json.data || [];
        renderSlaPolicies(_slaPolicies);
    } catch {
        tbody.innerHTML = `<tr><td colspan="${SLA_COL}" class="py-8 text-center text-red-400 text-xs">
            <i class="fas fa-exclamation-triangle mr-1"></i>Failed to load SLA policies.</td></tr>`;
    }
}

function renderSlaPolicies(policies) {
    const tbody = document.getElementById('slaPolicyTableBody');
    if (!policies.length) {
        tbody.innerHTML = `<tr><td colspan="${SLA_COL}" class="py-10 text-center">
            <div class="flex flex-col items-center gap-2">
                <i class="fas fa-file-contract text-gray-300 text-2xl mb-1"></i>
                <p class="text-sm font-medium text-gray-400">No SLA policy yet</p>
                ${SLA_CAN_MGMT ? `<p class="text-xs text-gray-300">Click "Add Policy" to create the first policy</p>` : ''}
            </div></td></tr>`;
        return;
    }
    tbody.innerHTML = policies.map(p => {
        const pc  = SLA_PRIO_CFG[p.priority] || { bg:'bg-gray-100', text:'text-gray-600', dot:'bg-gray-400' };
        const breakLabel = (p.break_start_time && p.break_end_time) ? ` (break ${p.break_start_time}-${p.break_end_time})` : '';
        const workHoursLabel = (p.work_start_time && p.work_end_time) ? ` ${p.work_start_time}–${p.work_end_time}${breakLabel}` : '';
        const modeCell = p.is_24_hours
            ? `<span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full"><i class="fas fa-infinity text-[9px]"></i> 24/7</span>`
            : `<span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Business${workHoursLabel}</span>`;
        const statusCell = p.is_active
            ? `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700 bg-green-50 px-2.5 py-0.5 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active</span>`
            : `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400 bg-gray-100 px-2.5 py-0.5 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactive</span>`;
        const actions = SLA_CAN_MGMT
            ? `<div class="flex items-center justify-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <button onclick='openSlaEditModal(${JSON.stringify(p)})' title="Edit"
                    class="w-7 h-7 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 flex items-center justify-center text-gray-400 hover:text-blue-600 transition">
                    <i class="fas fa-edit text-xs"></i>
                </button>
                <button onclick="deleteSlaPolicy(${p.id})" title="Delete"
                    class="w-7 h-7 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 flex items-center justify-center text-gray-400 hover:text-red-500 transition">
                    <i class="fas fa-trash text-xs"></i>
                </button>
               </div>` : '';
        return `
        <tr class="bg-white border-b border-gray-100 hover:bg-red-50/20 transition-colors group">
            <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold ${pc.text} ${pc.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full ${pc.dot}"></span>${SLA_PRIO_LABEL[p.priority] || p.priority}
                </span>
            </td>
            <td class="px-4 py-3"><span class="text-xs font-medium text-gray-600 bg-gray-100 px-2 py-0.5 rounded">${p.scale}</span></td>
            <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-gray-800">${p.response_hours}</span></td>
            <td class="px-4 py-3 text-center"><span class="text-sm font-bold text-gray-800">${p.resolution_hours}</span></td>
            <td class="px-4 py-3 text-center">${modeCell}</td>
            <td class="px-4 py-3 text-center">${statusCell}</td>
            ${SLA_CAN_MGMT ? `<td class="px-4 py-3 text-center">${actions}</td>` : ''}
        </tr>`;
    }).join('');
}

// ── SLA Add Modal ─────────────────────────────────────────────────────────────
function openSlaAddModal() {
    document.getElementById('slaAddForm').reset();
    document.getElementById('slaAddError').classList.add('hidden');
    document.getElementById('slaAddWorkEnd').setCustomValidity('');
    slaUpdateAdd24h();
    document.getElementById('slaAddModal').classList.remove('hidden');
}
function closeSlaAddModal() {
    document.getElementById('slaAddModal').classList.add('hidden');
}
function slaUpdateAdd24h() {
    const prio   = document.getElementById('slaAddPriority').value;
    const el     = document.getElementById('slaAdd24h');
    const label  = document.getElementById('slaAdd24hLabel');
    const note   = document.getElementById('slaAdd24hNote');
    if (prio === 'Very High') {
        el.checked = true; el.disabled = true;
        label.classList.add('opacity-75','cursor-not-allowed');
        label.classList.remove('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Required 24/7 — Very High priority always uses full calendar hours.';
    } else {
        el.disabled = false;
        label.classList.remove('opacity-75','cursor-not-allowed');
        label.classList.add('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Count all hours; otherwise, only business hours per the window below.';
    }
}
function slaValidateAddWindow() {
    const start  = document.getElementById('slaAddWorkStart');
    const end    = document.getElementById('slaAddWorkEnd');
    const bStart = document.getElementById('slaAddBreakStart');
    const bEnd   = document.getElementById('slaAddBreakEnd');
    end.setCustomValidity((start.value && end.value && end.value <= start.value)
        ? 'End time must be after start time.' : '');
    bEnd.setCustomValidity((bStart.value && bEnd.value && bEnd.value <= bStart.value)
        ? 'Break end must be after break start.' : '');
}
async function submitSlaAddPolicy(e) {
    e.preventDefault();
    const form   = document.getElementById('slaAddForm');
    const btn    = document.getElementById('slaAddSubmitBtn');
    const errDiv = document.getElementById('slaAddError');
    const errTxt = document.getElementById('slaAddErrorText');
    errDiv.classList.add('hidden');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.set('delivery_support_id', SLA_DS_ID);
    const payload = Object.fromEntries(fd.entries());
    payload.is_24_hours = form.querySelector('[name=is_24_hours]').checked;
    payload.work_start_time  = payload.work_start_time || null;
    payload.work_end_time    = payload.work_end_time || null;
    payload.break_start_time = payload.break_start_time || null;
    payload.break_end_time   = payload.break_end_time || null;
    try {
        const res  = await fetch('/api/admin/sla/policies', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.success) {
            closeSlaAddModal();
            showToast('SLA policy added successfully!', 'success');
            loadSlaPolicies();
        } else {
            errTxt.textContent = json.message || 'Failed to save policy.';
            errDiv.classList.remove('hidden');
        }
    } catch {
        errTxt.textContent = 'An error occurred. Please try again.';
        errDiv.classList.remove('hidden');
    } finally {
        btn.disabled = false;
    }
}

// ── SLA Edit Modal ────────────────────────────────────────────────────────────
function openSlaEditModal(p) {
    document.getElementById('slaEditId').value         = p.id;
    document.getElementById('slaEditPriority').value   = p.priority;
    document.getElementById('slaEditScale').value      = p.scale;
    document.getElementById('slaEditResponse').value   = p.response_hours;
    document.getElementById('slaEditResolution').value = p.resolution_hours;
    document.getElementById('slaEdit24h').checked      = p.is_24_hours;
    document.getElementById('slaEditWorkStart').value  = p.work_start_time || '';
    document.getElementById('slaEditWorkEnd').value    = p.work_end_time || '';
    document.getElementById('slaEditBreakStart').value = p.break_start_time || '';
    document.getElementById('slaEditBreakEnd').value   = p.break_end_time || '';
    document.getElementById('slaEditActive').checked   = p.is_active;
    document.getElementById('slaEditError').classList.add('hidden');
    document.getElementById('slaEditWorkEnd').setCustomValidity('');
    document.getElementById('slaEditBreakEnd').setCustomValidity('');
    slaUpdateEdit24h();
    document.getElementById('slaEditModal').classList.remove('hidden');
}
function closeSlaEditModal() {
    document.getElementById('slaEditModal').classList.add('hidden');
}
function slaUpdateEdit24h() {
    const prio  = document.getElementById('slaEditPriority').value;
    const el    = document.getElementById('slaEdit24h');
    const label = document.getElementById('slaEdit24hLabel');
    const note  = document.getElementById('slaEdit24hNote');
    if (prio === 'Very High') {
        el.checked = true; el.disabled = true;
        label.classList.add('opacity-75','cursor-not-allowed');
        label.classList.remove('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Required 24/7 — Very High priority always uses full calendar hours.';
    } else {
        el.disabled = false;
        label.classList.remove('opacity-75','cursor-not-allowed');
        label.classList.add('cursor-pointer','hover:bg-gray-100');
        note.textContent = 'Count all hours; otherwise, only business hours per the window below.';
    }
}
function slaValidateEditWindow() {
    const start  = document.getElementById('slaEditWorkStart');
    const end    = document.getElementById('slaEditWorkEnd');
    const bStart = document.getElementById('slaEditBreakStart');
    const bEnd   = document.getElementById('slaEditBreakEnd');
    bEnd.setCustomValidity((bStart.value && bEnd.value && bEnd.value <= bStart.value)
        ? 'Break end must be after break start.' : '');
    if (start.value && end.value && end.value <= start.value) {
        end.setCustomValidity('End time must be after start time.');
    } else {
        end.setCustomValidity('');
    }
}
async function submitSlaEditPolicy(e) {
    e.preventDefault();
    const id     = document.getElementById('slaEditId').value;
    const btn    = document.getElementById('slaEditSubmitBtn');
    const errDiv = document.getElementById('slaEditError');
    const errTxt = document.getElementById('slaEditErrorText');
    errDiv.classList.add('hidden');
    btn.disabled = true;
    const payload = {
        priority:         document.getElementById('slaEditPriority').value,
        scale:            document.getElementById('slaEditScale').value,
        response_hours:   document.getElementById('slaEditResponse').value,
        resolution_hours: document.getElementById('slaEditResolution').value,
        is_24_hours:      document.getElementById('slaEdit24h').checked,
        work_start_time:  document.getElementById('slaEditWorkStart').value || null,
        work_end_time:    document.getElementById('slaEditWorkEnd').value || null,
        break_start_time: document.getElementById('slaEditBreakStart').value || null,
        break_end_time:   document.getElementById('slaEditBreakEnd').value || null,
        is_active:        document.getElementById('slaEditActive').checked,
        delivery_support_id: SLA_DS_ID,
    };
    try {
        const res  = await fetch(`/api/admin/sla/policies/${id}`, {
            method: 'PUT',
            credentials: 'include',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.success) {
            closeSlaEditModal();
            showToast('SLA policy updated successfully!', 'success');
            loadSlaPolicies();
        } else {
            errTxt.textContent = json.message || 'Failed to update policy.';
            errDiv.classList.remove('hidden');
        }
    } catch {
        errTxt.textContent = 'An error occurred. Please try again.';
        errDiv.classList.remove('hidden');
    } finally {
        btn.disabled = false;
    }
}

async function deleteSlaPolicy(id) {
    if (!await showConfirm('Delete this SLA policy?', 'Delete SLA Policy', 'danger')) return;
    try {
        const res  = await fetch(`/api/admin/sla/policies/${id}/delete`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '' },
        });
        const json = await res.json();
        if (json.success) {
            showToast('SLA policy deleted.', 'success');
            loadSlaPolicies();
        } else {
            showToast(json.message || 'Failed to delete policy.', 'error');
        }
    } catch {
        showToast('An error occurred.', 'error');
    }
}

// Auto-load on page ready
document.addEventListener('DOMContentLoaded', loadSlaPolicies);

</script>

{{-- ── SLA Add Modal ──────────────────────────────────────────────────────── --}}
<div id="slaAddModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeSlaAddModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                        <i class="fas fa-plus text-red-600 text-xs"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800">Add SLA Policy</h3>
                </div>
                <button onclick="closeSlaAddModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <form id="slaAddForm" onsubmit="submitSlaAddPolicy(event)" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Priority <span class="text-red-500">*</span></label>
                        <select name="priority" id="slaAddPriority" required onchange="slaUpdateAdd24h()"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="Very High">1: Very High</option>
                            <option value="High">2: High</option>
                            <option value="Medium" selected>3: Medium</option>
                            <option value="Low">4: Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Scale <span class="text-red-500">*</span></label>
                        <select name="scale" id="slaAddScale" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="Simple" selected>Simple</option>
                            <option value="Medium">Medium</option>
                            <option value="Complex">Complex</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Response (Hours) <span class="text-red-500">*</span></label>
                        <input type="number" name="response_hours" step="0.5" min="0.5" required placeholder="e.g. 4"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Resolution (Hours) <span class="text-red-500">*</span></label>
                        <input type="number" name="resolution_hours" step="0.5" min="0.5" required placeholder="e.g. 24"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                </div>
                <label id="slaAdd24hLabel" class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" name="is_24_hours" id="slaAdd24h" onchange="slaUpdateAdd24h()" class="mt-0.5 w-4 h-4 rounded accent-red-700">
                    <div>
                        <p class="text-sm font-medium text-gray-700">24/7 Calendar Hours</p>
                        <p class="text-xs text-gray-400 mt-0.5" id="slaAdd24hNote">Count all hours; otherwise, only business hours per the window below.</p>
                    </div>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Work Start</label>
                        <input type="time" name="work_start_time" id="slaAddWorkStart" oninput="slaValidateAddWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Work End</label>
                        <input type="time" name="work_end_time" id="slaAddWorkEnd" oninput="slaValidateAddWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Break Start</label>
                        <input type="time" name="break_start_time" id="slaAddBreakStart" oninput="slaValidateAddWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Break End</label>
                        <input type="time" name="break_end_time" id="slaAddBreakEnd" oninput="slaValidateAddWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                </div>
                <div id="slaAddError" class="hidden items-center gap-2 bg-red-50 rounded-xl px-4 py-3 border border-red-100">
                    <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0 text-xs"></i>
                    <span id="slaAddErrorText" class="text-xs text-red-600"></span>
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeSlaAddModal()"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" id="slaAddSubmitBtn"
                        class="flex-1 bg-red-700 hover:bg-red-800 text-white rounded-xl py-2.5 text-sm font-semibold transition">Save Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── SLA Edit Modal ─────────────────────────────────────────────────────── --}}
<div id="slaEditModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeSlaEditModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-edit text-blue-600 text-xs"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800">Edit SLA Policy</h3>
                </div>
                <button onclick="closeSlaEditModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <form id="slaEditForm" onsubmit="submitSlaEditPolicy(event)" class="p-6 space-y-4">
                <input type="hidden" id="slaEditId">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Priority</label>
                        <select id="slaEditPriority" required onchange="slaUpdateEdit24h()"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="Very High">1: Very High</option>
                            <option value="High">2: High</option>
                            <option value="Medium">3: Medium</option>
                            <option value="Low">4: Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Scale</label>
                        <select id="slaEditScale" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="Simple">Simple</option>
                            <option value="Medium">Medium</option>
                            <option value="Complex">Complex</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Response (Hours)</label>
                        <input type="number" id="slaEditResponse" step="0.5" min="0.5" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Resolution (Hours)</label>
                        <input type="number" id="slaEditResolution" step="0.5" min="0.5" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                </div>
                <label id="slaEdit24hLabel" class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" id="slaEdit24h" onchange="slaUpdateEdit24h()" class="mt-0.5 w-4 h-4 rounded accent-blue-600">
                    <div>
                        <p class="text-sm font-medium text-gray-700">24/7 Calendar Hours</p>
                        <p class="text-xs text-gray-400 mt-0.5" id="slaEdit24hNote">Count all hours; otherwise, only business hours per the window below.</p>
                    </div>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Work Start</label>
                        <input type="time" id="slaEditWorkStart" oninput="slaValidateEditWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Work End</label>
                        <input type="time" id="slaEditWorkEnd" oninput="slaValidateEditWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Break Start</label>
                        <input type="time" id="slaEditBreakStart" oninput="slaValidateEditWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Break End</label>
                        <input type="time" id="slaEditBreakEnd" oninput="slaValidateEditWindow()" lang="en-GB"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                </div>
                <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" id="slaEditActive" class="mt-0.5 w-4 h-4 rounded accent-green-600">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Active</p>
                        <p class="text-xs text-gray-400 mt-0.5">Active policies are used for ticket SLA calculation</p>
                    </div>
                </label>
                <div id="slaEditError" class="hidden items-center gap-2 bg-red-50 rounded-xl px-4 py-3 border border-red-100">
                    <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0 text-xs"></i>
                    <span id="slaEditErrorText" class="text-xs text-red-600"></span>
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeSlaEditModal()"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" id="slaEditSubmitBtn"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-2.5 text-sm font-semibold transition">Update Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Customer PIC Modal --}}
<div id="customerPicModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCustomerPicModal()"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">
            <div class="flex items-center justify-between px-6 py-4 bg-gray-800 rounded-t-xl">
                <h3 class="text-lg font-semibold text-white">Manage Customer PIC</h3>
                <button type="button" onclick="closeCustomerPicModal()" class="text-white hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-xs text-gray-500">Pilih customer contact yang menjadi PIC untuk delivery support ini. Customer tersebut hanya akan melihat ticket yang terhubung ke DS ini di JARVIES.</p>

                {{-- Search input --}}
                <div class="relative">
                    <input type="text" id="cpicSearchInput" placeholder="Cari nama atau email..."
                        class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-800"
                        oninput="filterCpicOptions()">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                {{-- Contact list with checkboxes --}}
                <div id="cpicContactList" class="border border-gray-200 rounded-lg max-h-64 overflow-y-auto divide-y divide-gray-100">
                    <p class="text-xs text-gray-400 px-4 py-3 italic">Loading contacts...</p>
                </div>

                {{-- Selected count --}}
                <p class="text-xs text-gray-500">Dipilih: <span id="cpicSelectedCount" class="font-semibold text-gray-800">0</span> contact</p>
            </div>
            <div class="flex gap-3 px-6 pb-6">
                <button type="button" onclick="closeCustomerPicModal()"
                    class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    Batal
                </button>
                <button type="button" id="cpicSaveBtn" onclick="saveCustomerPics()"
                    class="flex-1 bg-gray-800 hover:bg-gray-700 text-white rounded-xl py-2.5 text-sm font-semibold transition">
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== CUSTOMER PIC ====================
const SUPPORT_ID = {{ $support->id }};
const CPIC_CONTACTS_URL = '{{ route("delivery.support.client-contacts", $support->id) }}';
const CPIC_SYNC_URL     = '{{ route("delivery.support.customer-pics.sync", $support->id) }}';
const CPIC_INDEX_URL    = '{{ route("delivery.support.customer-pics.index", $support->id) }}';
const CSRF_TOKEN        = '{{ csrf_token() }}';

let cpicAllContacts  = [];  // semua contact milik client
let cpicSelectedIds  = new Set();  // contact_id yang dipilih

// ── Panel render ─────────────────────────────────────────────────────────────

async function loadCustomerPicPanel() {
    try {
        const res  = await fetch(CPIC_INDEX_URL);
        const json = await res.json();
        renderCustomerPicPanel(json.data ?? []);
    } catch (e) {
        document.getElementById('customerPicList').innerHTML =
            '<p class="text-xs text-red-400 italic">Gagal memuat data.</p>';
    }
}

function renderCustomerPicPanel(pics) {
    const el = document.getElementById('customerPicList');
    if (!pics.length) {
        el.innerHTML = '<p class="text-xs text-gray-400 italic">Belum ada Customer PIC yang ditentukan.</p>';
        return;
    }
    el.innerHTML = pics.map(p => `
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold flex-shrink-0">
                ${(p.full_name || '?').charAt(0).toUpperCase()}
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">${escHtml(p.full_name ?? '—')}</p>
                <p class="text-xs text-gray-400 truncate">${escHtml(p.email_work ?? '')}${p.position ? ' · ' + escHtml(p.position) : ''}</p>
            </div>
        </div>
    `).join('');
}

// ── Modal ────────────────────────────────────────────────────────────────────

async function openCustomerPicModal() {
    document.getElementById('customerPicModal').classList.remove('hidden');
    document.getElementById('cpicSearchInput').value = '';
    document.getElementById('cpicContactList').innerHTML =
        '<p class="text-xs text-gray-400 px-4 py-3 italic">Loading contacts...</p>';

    try {
        const [contactsRes, picsRes] = await Promise.all([
            fetch(CPIC_CONTACTS_URL),
            fetch(CPIC_INDEX_URL),
        ]);
        const contactsJson = await contactsRes.json();
        const picsJson     = await picsRes.json();

        cpicAllContacts = contactsJson.data ?? [];
        cpicSelectedIds = new Set((picsJson.data ?? []).map(p => p.contact_id));

        renderCpicList(cpicAllContacts);
        updateCpicCount();
    } catch (e) {
        document.getElementById('cpicContactList').innerHTML =
            '<p class="text-xs text-red-400 px-4 py-3 italic">Gagal memuat data contact.</p>';
    }
}

function closeCustomerPicModal() {
    document.getElementById('customerPicModal').classList.add('hidden');
}

function renderCpicList(contacts) {
    const el = document.getElementById('cpicContactList');
    if (!contacts.length) {
        el.innerHTML = '<p class="text-xs text-gray-400 px-4 py-3 italic">Tidak ada contact ditemukan.</p>';
        return;
    }
    el.innerHTML = contacts.map(c => {
        const checked = cpicSelectedIds.has(c.contact_id) ? 'checked' : '';
        return `
        <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" value="${c.contact_id}" ${checked}
                class="cpic-checkbox w-4 h-4 rounded border-gray-300 text-gray-800 focus:ring-gray-700"
                onchange="toggleCpic(${c.contact_id}, this.checked)">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">${escHtml(c.full_name ?? '—')}</p>
                <p class="text-xs text-gray-400">${escHtml(c.email_work ?? '')}${c.position ? ' · ' + escHtml(c.position) : ''}</p>
            </div>
        </label>`;
    }).join('');
}

function toggleCpic(contactId, checked) {
    if (checked) cpicSelectedIds.add(contactId);
    else         cpicSelectedIds.delete(contactId);
    updateCpicCount();
}

function filterCpicOptions() {
    const q = document.getElementById('cpicSearchInput').value.trim().toLowerCase();
    const filtered = q
        ? cpicAllContacts.filter(c =>
            (c.full_name ?? '').toLowerCase().includes(q) ||
            (c.email_work ?? '').toLowerCase().includes(q))
        : cpicAllContacts;
    renderCpicList(filtered);
}

function updateCpicCount() {
    document.getElementById('cpicSelectedCount').textContent = cpicSelectedIds.size;
}

async function saveCustomerPics() {
    const btn = document.getElementById('cpicSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    try {
        const res = await fetch(CPIC_SYNC_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
            body: JSON.stringify({ contact_ids: [...cpicSelectedIds] }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message ?? 'Gagal menyimpan');

        renderCustomerPicPanel(json.data ?? []);
        closeCustomerPicModal();
        showToast('Customer PIC berhasil disimpan.', 'success');
    } catch (e) {
        showToast(e.message || 'Gagal menyimpan Customer PIC.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Simpan';
    }
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// showToast() dipakai langsung dari definisi global di dashboard.blade.php.

// Auto-load panel saat halaman siap
document.addEventListener('DOMContentLoaded', () => loadCustomerPicPanel());
</script>

{{-- Load custom-dd script + cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

@if($can('delivery-support.remove-ticket'))
{{-- Remove Ticket from DS Modal --}}
<div id="removeTicketModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Remove Ticket from DS</h3>
            <button onclick="closeRemoveTicketModal()" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-6 py-4 space-y-2 max-h-72 overflow-y-auto">
            @forelse($linkedTickets as $lt)
            <div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50 transition">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $lt->ticket_number }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ Str::limit($lt->description, 60) }}</p>
                </div>
                <button onclick="confirmRemoveTicket({{ $lt->activity_id }}, '{{ $lt->ticket_number }}')"
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-orange-700 bg-orange-100 hover:bg-orange-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Remove
                </button>
            </div>
            @empty
            <p class="text-sm text-gray-400 italic text-center py-4">No tickets currently linked to this DS.</p>
            @endforelse
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
            <button onclick="closeRemoveTicketModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Close</button>
        </div>
    </div>
</div>

<script>
function openRemoveTicketModal() {
    const modal = document.getElementById('removeTicketModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRemoveTicketModal() {
    const modal = document.getElementById('removeTicketModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function confirmRemoveTicket(activityId, ticketNumber) {
    const ok = await showConfirm(
        `Remove ticket ${ticketNumber} from this delivery support? The activity will remain; only the ticket link will be cleared.`,
        'Remove Ticket from DS',
        'warning',
        { okText: 'Remove' }
    );
    if (!ok) return;

    const supportId = {{ $support->id }};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
        const res  = await fetch(`/delivery/support/${supportId}/activities/${activityId}/remove-ticket`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (data.success) {
            showNotification(data.message, 'success');
            closeRemoveTicketModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification(data.message || 'Failed to remove ticket.', 'error');
        }
    } catch (e) {
        showNotification('Network error. Please try again.', 'error');
    }
}

document.getElementById('removeTicketModal').addEventListener('click', function(e) {
    if (e.target === this) closeRemoveTicketModal();
});
</script>
@endif

{{-- Financial / TOP / Plan Cost — modals & scripts --}}
@include('delivery.support.list.partials.financial-plancost-modals')
@include('delivery.support.list.partials.financial-plancost-scripts')

{{-- Recons — scripts section (hanya dimuat kalau section-nya tampil) --}}
@if($can('delivery-support.recons.view'))
@include('delivery.support.list.partials.recons-scripts')
@endif

<script>
// ============================================================================
// SMOOTH SCROLL & STICKY SECTION NAV
// Pola identik dengan Delivery Project detail supaya perilaku tab konsisten.
// ============================================================================

// Scroll Progress Indicator
window.addEventListener('scroll', function () {
    const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
    const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    const scrolled = height > 0 ? (winScroll / height) * 100 : 0;
    const indicator = document.getElementById('scrollIndicator');
    if (indicator) indicator.style.width = scrolled + '%';
});

// Smooth Scroll to Section
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;

    // Offset = navbar utama + sticky section nav + sedikit ruang
    const mainNavHeight     = 73;
    const sectionNavHeight  = 50;
    const extraPadding      = 15;
    const totalOffset       = mainNavHeight + sectionNavHeight + extraPadding;

    window.scrollTo({ top: section.offsetTop - totalOffset, behavior: 'smooth' });
    updateActiveTab(sectionId);
}

// Update Active Tab
function updateActiveTab(sectionId) {
    document.querySelectorAll('.section-tab').forEach(tab => {
        if (tab.getAttribute('data-section') === sectionId) {
            tab.classList.remove('text-gray-600', 'border-transparent');
            tab.classList.add('primary-tab-active', 'active');
        } else {
            tab.classList.remove('primary-tab-active', 'active');
            tab.classList.add('text-gray-600', 'border-transparent');
        }
    });
}

const supSectionObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) updateActiveTab(entry.target.id);
    });
}, { root: null, rootMargin: '-140px 0px -60% 0px', threshold: 0 });

document.querySelectorAll('section[id]').forEach(section => supSectionObserver.observe(section));

// Sticky nav mengikuti lebar sidebar (docked / collapsed)
function syncNavWithSidebar() {
    const sidebar    = document.getElementById('sidebar');
    const sectionNav = document.getElementById('sectionNav');
    if (!sidebar || !sectionNav) return;
    sectionNav.style.left = sidebar.classList.contains('w-20') ? '80px' : '256px';
}

const supSidebarEl = document.getElementById('sidebar');
if (supSidebarEl) {
    new MutationObserver(syncNavWithSidebar).observe(supSidebarEl, { attributes: true, attributeFilter: ['class'] });
}
syncNavWithSidebar();
</script>

@endsection

@include('delivery.partials.section-permissions')
