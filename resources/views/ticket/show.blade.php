@extends('dashboard')
@section('content-class', 'p-4')
@section('title', 'Ticket ' . $ticket->ticket_number)
@section('page-title', 'Support Ticket')
@section('page-subtitle')
#{{ $ticket->ticket_number }} - {{ Str::limit($ticket->description, 50) }}
@if(isset($deliverySupport) && $deliverySupport)
<span id="headbarTopDsBadge" class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-100 text-blue-700 align-middle">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>
    DS: {{ $deliverySupport->name }}@if($deliverySupport->type) <span class="opacity-70">({{ $deliverySupport->type }})</span>@endif
</span>
@endif
@endsection

{{-- Override sidebar with ticket inbox --}}
@section('sidebar-nav')
{{-- Resize handle – draggable right edge --}}
<div id="sidebarResizeHandle"
     style="position:absolute;top:0;right:0;width:5px;height:100%;cursor:col-resize;z-index:200;background:transparent;"
     onmousedown="sidebarResizeStart(event)"></div>
<div class="flex flex-col h-full">
    {{-- Back to Tickets --}}
    <div class="px-4 pt-4 pb-2">
        <a href="{{ route('ticket.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 transition-all text-sm">
            <i class="fas fa-arrow-left text-xs"></i>
            <span class="font-medium">Back to Tickets</span>
        </a>
    </div>

    {{-- Filter Tabs --}}
    @php $ticketManagerOrEmployee = array_merge(\App\Enums\RoleId::TICKET_MANAGER_GROUP, [\App\Enums\RoleId::EMPLOYEE->value]); @endphp
    @if(in_array($user->role->role_id, $ticketManagerOrEmployee, true))
    <div class="px-4 pb-3">
        <div class="flex bg-white bg-opacity-10 rounded-lg p-0.5 gap-0.5">
            <button id="sidebarTabAll" onclick="switchSidebarView('all')"
                class="flex-1 py-1.5 text-xs font-semibold rounded-md transition-all text-white" style="background:rgba(255,255,255,0.2)">
                All Ticket
            </button>
            <button id="sidebarTabMy" onclick="switchSidebarView('my')"
                class="flex-1 py-1.5 text-xs font-semibold rounded-md transition-all text-white opacity-60">
                My Ticket
            </button>
        </div>
    </div>
    @endif

    {{-- Search --}}
    <div class="px-4 pb-3">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-white text-opacity-40 text-xs"></i>
            </div>
            <input type="text" id="sidebarSearch" placeholder="Search tickets..."
                class="w-full pl-9 pr-3 py-2 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-lg text-sm text-white placeholder-white placeholder-opacity-50 focus:outline-none focus:bg-white focus:bg-opacity-15 transition-all"
                onkeyup="filterSidebarTickets()">
        </div>
    </div>

    {{-- Ticket List --}}
    <div id="sidebarTicketList" class="flex-1 overflow-y-auto px-2 pb-4 space-y-1.5">
        {{-- Loaded via JS --}}
    </div>

    {{-- Sidebar Loading --}}
    <div id="sidebarLoading" class="flex-1 flex items-center justify-center">
        <div class="text-center">
            <i class="fas fa-spinner fa-spin text-white text-opacity-50 text-lg mb-2"></i>
            <p class="text-white text-opacity-50 text-xs">Loading...</p>
        </div>
    </div>
</div>
@endsection

@section('content')
{{-- Quill.js CDN --}}
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<div class="flex gap-4" style="height: calc(100vh - 106px); min-height: 500px;">
    {{-- Main Content: Conversation Thread --}}
    <div class="flex-1 flex flex-col bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Ticket Header --}}
        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0 sticky top-0 bg-white z-10">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-1 flex-wrap">
                    <h2 class="text-lg font-bold text-gray-900">{{ $ticket->description ?: 'No description' }}</h2>
                    <span class="text-sm text-gray-400 font-mono">{{ $ticket->ticket_number }}</span>
                    @php
                        $statusColors = [
                            'open' => 'bg-blue-100 text-blue-700',
                            'in_progress' => 'bg-yellow-100 text-yellow-700',
                            'hold' => 'bg-orange-100 text-orange-700',
                            'cancel' => 'bg-gray-100 text-gray-500',
                            'closed' => 'bg-green-100 text-green-700',
                            'reply' => 'bg-purple-100 text-purple-700',
                            'wait_to_close' => 'bg-teal-100 text-teal-700',
                        ];
                        $statusLabels = [
                            'open' => 'Open', 'in_progress' => 'In Progress', 'hold' => 'Hold',
                            'cancel' => 'Cancel', 'closed' => 'Closed', 'reply' => 'Reply',
                            'wait_to_close' => 'Wait to Close',
                        ];
                    @endphp
                    <span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-semibold {{ $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$ticket->status] ?? 'Open' }}
                    </span>
                    @if($ticket->ticket_type)
                    @php
                        $typeColors = [
                            'Incident' => 'bg-red-100 text-red-700',
                            'Service Request' => 'bg-indigo-100 text-indigo-700',
                            'Change Request' => 'bg-amber-100 text-amber-700',
                            'Consult' => 'bg-teal-100 text-teal-700',
                        ];
                    @endphp
                    <span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-semibold {{ $typeColors[$ticket->ticket_type] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $ticket->ticket_type }}
                    </span>
                    @endif
                </div>
                <div id="ticketHeadbarMeta" class="flex items-center gap-3 text-sm text-gray-500 flex-wrap">
                    <span>{{ $ticket->customer?->basicData?->name_1 ?? 'Unknown Customer' }}</span>
                    <span class="text-gray-300">|</span>
                    <span>{{ $ticket->created_at->format('d M Y H:i') }} WIB</span>
                    @if($ticket->employee)
                        <span class="text-gray-300">|</span>
                        <span>PIC: {{ $ticket->employee->basicData ? trim($ticket->employee->basicData->first_name . ' ' . ($ticket->employee->basicData->last_name ?? '')) : 'Assigned' }}</span>
                    @endif
                </div>
            </div>
            @php
                $canViewCredential =
                    in_array($user->role->role_id, array_merge(\App\Enums\RoleId::TICKET_MANAGER_GROUP, [\App\Enums\RoleId::HEAD_OF_SUPPORT->value]), true)
                    || $ticket->employee_id == $user->id
                    || $ticket->members->contains('employee_id', $user->id);
            @endphp
            @if($canViewCredential && $ticket->customer_id)
            <button onclick="openCredentialModal()"
                title="Customer Credential"
                class="ml-4 flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </button>
            @endif
        </div>

        {{-- Messages Thread --}}
        <div id="messagesThread" class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
            <div id="messagesLoading" class="flex items-center justify-center py-8">
                <svg class="animate-spin h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        {{-- Compose Area with Quill Editor --}}
        <div class="border-t border-gray-200 flex-shrink-0">
            {{-- Channel mode indicator --}}
            @if($ticket->channel === 'email' || $ticket->email_thread_id)
            <div class="px-4 pt-2 flex items-center gap-1.5 text-xs text-blue-700">
                <i class="fas fa-envelope text-[10px]"></i>
                <span>Replies will be sent to customer via <strong>Email</strong></span>
            </div>
            @else
            <div class="px-4 pt-2 flex items-center gap-1.5 text-xs text-gray-400">
                <i class="fas fa-comment text-[10px]"></i>
                <span>Replies only visible in <strong>Jarvies</strong> — no email will be sent</span>
            </div>
            @endif

            <div class="px-4 pt-2 pb-2">
                {{-- Mention dropdown (positioned relative to editor wrapper) --}}
                <div class="relative">
                    <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                        <div id="quillEditor" style="min-height: 80px;"></div>
                    </div>
                    {{-- @mention autocomplete dropdown --}}
                    <div id="mentionDropdown" class="hidden absolute z-50 bg-white border border-gray-200 rounded-xl shadow-xl w-72 max-h-48 overflow-y-auto" style="bottom: calc(100% + 4px); left: 0;">
                        <div id="mentionList" class="py-1"></div>
                    </div>
                </div>

                {{-- Attachment Preview Area (toggled via JS: style.display flex/none) --}}
                <div id="attachmentPreview" style="display:none" class="mt-2 flex-wrap gap-2"></div>

                {{-- Hidden file input (button injected into Quill toolbar via JS) --}}
                <input type="file" id="attachInput" multiple class="hidden"
                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,.csv">
                <div class="flex items-center justify-end mt-2 mb-1 gap-2">
                    <span id="attachCount" class="hidden text-xs text-blue-600 font-medium mr-auto"></span>
                    {{-- Send buttons --}}
                    <button onclick="sendReply('internal_note')" class="inline-flex items-center px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold rounded-lg hover:bg-amber-100 transition-all duration-200">
                        Internal Note
                    </button>
                    @if($ticket->channel === 'email')
                    <button onclick="sendReply('reply')" class="inline-flex items-center px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all duration-200">
                        Send via Email
                    </button>
                    @else
                    <button onclick="sendReply('reply')" class="inline-flex items-center px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all duration-200">
                        Send Reply
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Right Sidebar --}}
    @php
        $mandaysStatus   = $ticket->mandays_proposal_status   ?? 'none';
        $internalStatus  = $ticket->internal_mandays_status    ?? 'none';
        $isPic           = $user->role->role_id === \App\Enums\RoleId::EMPLOYEE->value;
        $isHelpdesk      = in_array($user->role->role_id, \App\Enums\RoleId::HELPDESK_GROUP, true);
        $isHead          = $user->role->role_id === \App\Enums\RoleId::HEAD_OF_SUPPORT->value;
        $mandaysBadge    = [
            'none'            => ['bg-gray-100 text-gray-500',   'None'],
            'pic_draft'       => ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_helpdesk'=> ['bg-blue-100 text-blue-700',   'Pending Review'],
            'sent_to_chat'    => ['bg-purple-100 text-purple-700','Sent to Chat'],
            'approved'        => ['bg-green-100 text-green-700', 'Approved'],
            'canceled'        => ['bg-red-100 text-red-700',     'Canceled'],
        ];
        $internalBadge   = [
            'none'         => ['bg-gray-100 text-gray-500',   'None'],
            'draft'        => ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_head' => ['bg-blue-100 text-blue-700',   'Pending Head'],
            'approved'     => ['bg-green-100 text-green-700', 'Approved'],
            'rejected'     => ['bg-red-100 text-red-700',     'Rejected'],
        ];
        [$mBadgeClass, $mBadgeLabel]  = $mandaysBadge[$mandaysStatus]  ?? ['bg-gray-100 text-gray-500', $mandaysStatus];
        [$iBadgeClass, $iBadgeLabel]   = $internalBadge[$internalStatus] ?? ['bg-gray-100 text-gray-500', $internalStatus];
        $picMandaysLabel = match($mandaysStatus) {
            'none'  => 'Propose Mandays',
            default => 'Update Proposal',
        };
        $picInternalLabel = match($internalStatus) {
            'none'  => 'Propose Internal Mandays',
            default => 'Update Internal Mandays',
        };
        $ticketAssigned    = $ticket->employee_id !== null;
        $canTakeTicket     = $user->role->role_id === \App\Enums\RoleId::EMPLOYEE->value
                             && !$ticketAssigned;
        $canAssignPic      = !$ticketAssigned && in_array($user->role->role_id, array_merge(
                                 [\App\Enums\RoleId::ADMIN->value, \App\Enums\RoleId::HEAD_OF_SUPPORT->value],
                                 \App\Enums\RoleId::HELPDESK_GROUP
                             ), true);
        // Mandays buttons only visible when ticket has a PIC
        $isPicMandays      = $isPic && $ticketAssigned;
        $isHelpdeskMandays = $isHelpdesk && $ticketAssigned;
        $isHeadMandays          = $isHead && $ticketAssigned && in_array($internalStatus, ['pending_head', 'approved', 'rejected', 'draft']);
        $isHeadCustomerMandays  = $isHead && $ticketAssigned && in_array($mandaysStatus, ['pic_draft', 'pending_helpdesk', 'sent_to_chat', 'approved', 'canceled']);
        $hasMandaysSection = $isPicMandays || $isHelpdeskMandays || $isHeadMandays || $isHeadCustomerMandays
                           || $canTakeTicket || $canAssignPic || in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true);
    @endphp

    <div class="hidden xl:flex xl:flex-col w-72 gap-3 flex-shrink-0 overflow-y-auto">

        {{-- ── Mandays Panel ── --}}
        @if($hasMandaysSection)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-shrink-0">
            <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                 onclick="toggleSidebarPanel('mandaysPanel', 'mandaysChevron')">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Mandays</h4>
                <i id="mandaysChevron" class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
            </div>
            <div id="mandaysPanel" class="px-4 pb-4 pt-3 space-y-4 border-t border-gray-100">
                {{-- PIC: Customer Mandays & Internal Mandays --}}
                @if($isPicMandays)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Customer Mandays</label>
                        <span id="mandaysBadge" class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $mBadgeClass }}">{{ $mBadgeLabel }}</span>
                    </div>
                    <button onclick="openMandaysVersionList('pic')" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        {{ $picMandaysLabel }}
                    </button>
                </div>
                <div class="pt-1 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Internal Mandays</label>
                        <span id="internalBadge" class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $iBadgeClass }}">{{ $iBadgeLabel }}</span>
                    </div>
                    <button onclick="openInternalMandaysModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        {{ $picInternalLabel }}
                    </button>
                </div>
                @endif
                {{-- Helpdesk: Customer Mandays Review --}}
                @if($isHelpdeskMandays)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Mandays Review</label>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $mBadgeClass }}">{{ $mBadgeLabel }}</span>
                    </div>
                    @if(in_array($mandaysStatus, ['pic_draft', 'pending_helpdesk', 'sent_to_chat', 'approved', 'canceled']))
                    <button onclick="openMandaysVersionList('hd')" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Review Mandays Proposal
                    </button>
                    @else
                    <p class="text-[11px] text-gray-400 italic text-center py-1">
                        {{ $mandaysStatus === 'none' ? 'Waiting for PIC proposal' : 'PIC is drafting proposal...' }}
                    </p>
                    @endif
                </div>
                @endif
                {{-- Head of Support: Customer Mandays (view only) --}}
                @if($isHeadCustomerMandays)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Customer Mandays</label>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $mBadgeClass }}">{{ $mBadgeLabel }}</span>
                    </div>
                    <button onclick="openMandaysVersionList('head')" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        View Mandays Proposal
                    </button>
                </div>
                @endif
                {{-- Head of Support: Internal Mandays --}}
                @if($isHeadMandays)
                <div {{ $isHeadCustomerMandays ? 'class="pt-1 border-t border-gray-100"' : '' }}>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Internal Mandays</label>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $iBadgeClass }}">{{ $iBadgeLabel }}</span>
                    </div>
                    <button onclick="openHeadInternalModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Review Internal Proposal
                    </button>
                </div>
                @endif
                {{-- Take Ticket (consultant self-assign, unassigned only) --}}
                @if($canTakeTicket)
                <div>
                    <button onclick="takeTicket()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Take This Ticket
                    </button>
                </div>
                @endif
                {{-- Assign PIC (Admin / Helpdesk / Head of Support) --}}
                @if($canAssignPic)
                <div>
                    <button onclick="openAssignPicModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Assign PIC
                    </button>
                </div>
                @endif
                {{-- Assign to Delivery Support (Admin/Helpdesk only) --}}
                @if(in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true))
                <div class="{{ ($isPicMandays || $isHelpdeskMandays || $isHeadMandays) ? 'pt-1 border-t border-gray-100' : '' }}">
                    <button onclick="openAssignSupportModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Assign to Delivery Support
                    </button>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Properties Panel ── --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-shrink-0">
            <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                 onclick="toggleSidebarPanel('propertiesPanel', 'propertiesChevron')">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Properties</h4>
                <div class="flex items-center gap-2">
                    @if(in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true))
                    <button onclick="event.stopPropagation(); saveAllProperties()"
                            class="inline-flex items-center px-2.5 py-1 primary-gradient text-white text-[10px] font-semibold rounded-md hover:opacity-90 transition-all duration-200">
                        Save All
                    </button>
                    @endif
                    <i id="propertiesChevron" class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
                </div>
            </div>
            <div id="propertiesPanel" class="px-4 pb-4 pt-3 space-y-3 border-t border-gray-100">
                {{-- Status --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Status</label>
                    <div class="relative">
                        <select id="detailStatus" {{ in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 pr-7 border border-gray-300 rounded-lg text-xs bg-white appearance-none">
                            <option value="open" {{ $ticket->status == 'open' ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ $ticket->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="hold" {{ $ticket->status == 'hold' ? 'selected' : '' }}>Hold</option>
                            <option value="wait_to_close" {{ $ticket->status == 'wait_to_close' ? 'selected' : '' }}>Wait to Close</option>
                            <option value="cancel" {{ $ticket->status == 'cancel' ? 'selected' : '' }}>Cancel</option>
                            <option value="closed" {{ $ticket->status == 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="reply" {{ $ticket->status == 'reply' ? 'selected' : '' }}>Reply</option>
                        </select>
                        <i class="fas fa-bars absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                {{-- Jarvies Status --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Jarvies Status</label>
                    <div class="relative">
                        <select id="detailJarviesStatus" {{ in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 pr-7 border border-gray-300 rounded-lg text-xs bg-white appearance-none">
                            <option value="in process" {{ $ticket->jarvies_status == 'in process' ? 'selected' : '' }}>In Process</option>
                            <option value="author action" {{ $ticket->jarvies_status == 'author action' ? 'selected' : '' }}>Author Action</option>
                            <option value="proposed solution" {{ $ticket->jarvies_status == 'proposed solution' ? 'selected' : '' }}>Proposed Solution</option>
                            <option value="sent in to SAP" {{ $ticket->jarvies_status == 'sent in to SAP' ? 'selected' : '' }}>Sent in to SAP</option>
                            <option value="sent it to support" {{ $ticket->jarvies_status == 'sent it to support' ? 'selected' : '' }}>Sent it to Support</option>
                            <option value="closed" {{ $ticket->jarvies_status == 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                        <i class="fas fa-bars absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                {{-- Priority --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Priority</label>
                    <div class="relative">
                        <select id="detailPriority" {{ in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 pr-7 border border-gray-300 rounded-lg text-xs bg-white appearance-none">
                            <option value="Very High" {{ $ticket->ticket_priority == 'Very High' ? 'selected' : '' }}>Very High</option>
                            <option value="High" {{ $ticket->ticket_priority == 'High' ? 'selected' : '' }}>High</option>
                            <option value="Medium" {{ $ticket->ticket_priority == 'Medium' ? 'selected' : '' }}>Medium</option>
                            <option value="Low" {{ $ticket->ticket_priority == 'Low' ? 'selected' : '' }}>Low</option>
                        </select>
                        <i class="fas fa-bars absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                {{-- Ticket Type --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Ticket Type</label>
                    <div class="relative">
                        <select id="detailType" {{ in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 pr-7 border border-gray-300 rounded-lg text-xs bg-white appearance-none">
                            <option value="" {{ !$ticket->ticket_type ? 'selected' : '' }}>-- Select Type --</option>
                            <option value="Incident" {{ $ticket->ticket_type == 'Incident' ? 'selected' : '' }}>Incident</option>
                            <option value="Service Request" {{ $ticket->ticket_type == 'Service Request' ? 'selected' : '' }}>Service Request</option>
                            <option value="Change Request" {{ $ticket->ticket_type == 'Change Request' ? 'selected' : '' }}>Change Request</option>
                            <option value="Consult" {{ $ticket->ticket_type == 'Consult' ? 'selected' : '' }}>Consult</option>
                        </select>
                        <i class="fas fa-bars absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                {{-- Agent (PIC) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Agent (PIC)</label>
                    <div class="relative">
                        <select id="detailPIC" {{ in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 pr-7 border border-gray-300 rounded-lg text-xs bg-white appearance-none">
                            <option value="" {{ !$ticket->employee_id ? 'selected' : '' }}>-- Unassigned --</option>
                            @foreach($consultants as $consultant)
                                <option value="{{ $consultant['employee_id'] }}" {{ $ticket->employee_id == $consultant['employee_id'] ? 'selected' : '' }}>
                                    {{ $consultant['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fas fa-bars absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                {{-- Team Members --}}
                @php
                    $canManageMembers = in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true)
                        || ($user->role->role_id === \App\Enums\RoleId::EMPLOYEE->value && $ticket->employee_id == $user->id);
                    $currentMemberIds = $ticket->members->pluck('employee_id')->toArray();
                @endphp
                <div class="pt-3 border-t border-gray-200">
                    <label class="text-xs font-semibold text-gray-500 mb-2 block">Team Members</label>
                    <div id="membersList" class="space-y-1 mb-2">
                        @forelse($ticket->members as $member)
                            @php $mName = trim(($member->basicData->first_name ?? '') . ' ' . ($member->basicData->last_name ?? '')); @endphp
                            <div class="member-chip flex items-center justify-between gap-1 px-2.5 py-1.5 bg-blue-50 rounded-lg" data-id="{{ $member->employee_id }}">
                                <span class="text-xs text-blue-700 font-medium truncate">{{ $mName }}</span>
                                @if($canManageMembers)
                                <button type="button" onclick="removeMemberBtn({{ $member->employee_id }})"
                                        class="text-blue-300 hover:text-red-500 transition-colors flex-shrink-0 ml-1">
                                    <i class="fas fa-times text-[9px]"></i>
                                </button>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic" id="noMembersText">No members assigned.</p>
                        @endforelse
                    </div>
                    @if($canManageMembers)
                    <div class="flex gap-1.5">
                        <div class="relative flex-1 min-w-0">
                            <select id="addMemberSelect"
                                    class="w-full px-2.5 py-1.5 pr-7 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                                <option value="">-- Add member --</option>
                                @foreach($employees as $emp)
                                    @if(!in_array($emp['employee_id'], $currentMemberIds) && $emp['employee_id'] != $ticket->employee_id)
                                        <option value="{{ $emp['employee_id'] }}">{{ $emp['name'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <i class="fas fa-bars absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        </div>
                        <button type="button" onclick="addMemberBtn()"
                                class="px-2.5 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-all flex-shrink-0"
                                title="Add member">
                            <i class="fas fa-user-plus text-[10px]"></i>
                        </button>
                    </div>
                    @endif
                </div>
                {{-- Customer --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Customer</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->customer?->basicData?->name_1 ?? 'N/A' }}</p>
                </div>
                {{-- Additional Info --}}
                @if($ticket->name || $ticket->no_hp || $ticket->module || $ticket->client)
                <div class="pt-3 border-t border-gray-200">
                    <label class="text-xs font-bold text-gray-500 mb-2 block uppercase tracking-wide">Additional Info</label>
                    <div class="space-y-1.5">
                        @if($ticket->name)
                        <div>
                            <span class="text-[10px] text-gray-400 font-semibold uppercase">Name</span>
                            <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 mt-0.5">{{ $ticket->name }}</p>
                        </div>
                        @endif
                        @if($ticket->no_hp)
                        <div>
                            <span class="text-[10px] text-gray-400 font-semibold uppercase">No HP</span>
                            <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 mt-0.5">{{ $ticket->no_hp }}</p>
                        </div>
                        @endif
                        @if($ticket->module)
                        <div>
                            <span class="text-[10px] text-gray-400 font-semibold uppercase">Module</span>
                            <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 mt-0.5">{{ $ticket->module }}</p>
                        </div>
                        @endif
                        @if($ticket->client)
                        <div>
                            <span class="text-[10px] text-gray-400 font-semibold uppercase">Client</span>
                            <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 mt-0.5">{{ $ticket->client }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                {{-- Man Days (from approved customer mandays proposal) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Man Days</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">
                        {{ $approvedMandays !== null ? number_format((float)$approvedMandays, 1) : '—' }}
                    </p>
                </div>
                {{-- Start Date --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Start Date</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->start_date ? \Carbon\Carbon::parse($ticket->start_date)->format('M d, Y') : 'Not started' }}</p>
                </div>
                {{-- Due Date --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Due Date</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->end_date ? \Carbon\Carbon::parse($ticket->end_date)->format('M d, Y') : 'No due date' }}</p>
                </div>
                {{-- Delivery Support --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Delivery Support</label>
                    @if(isset($deliverySupport) && $deliverySupport)
                        <a href="/delivery/support/{{ $deliverySupport->id }}"
                           class="flex items-center gap-1.5 px-2.5 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-xs font-medium text-blue-700 hover:bg-blue-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                            </svg>
                            <span class="truncate">{{ $deliverySupport->name }}</span>
                            @if($deliverySupport->type)
                                <span class="flex-shrink-0 px-1.5 py-0.5 bg-blue-200 text-blue-800 rounded text-[10px] font-bold">{{ $deliverySupport->type }}</span>
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 flex-shrink-0 ml-auto opacity-50">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    @else
                        <p id="propertiesDsBadge" class="text-xs text-gray-400 italic px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">Not assigned</p>
                    @endif
                </div>
                {{-- Created --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Created</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->created_at->format('d M Y H:i') }} WIB</p>
                </div>
                {{-- Admin only: Delete Ticket --}}
                @if($user->role->role_id === \App\Enums\RoleId::ADMIN->value)
                <div class="pt-3 border-t border-gray-200">
                    <button onclick="deleteTicket()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Delete Ticket
                    </button>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

<style>
/* Message Bubbles */
.message-bubble { max-width: 85%; }
.message-bubble.customer  { background: #eff6ff; border-radius: 12px 12px 4px 12px; }
.message-bubble.employee  { background: #f9fafb; border-radius: 12px 12px 12px 4px; }
.message-bubble.internal-note       { background: #fef9c3; border: 1px dashed #f59e0b; border-radius: 4px 12px 12px 12px; }
.message-bubble.internal-note.mine  { background: #fef3c7; border: 1px dashed #d97706; border-radius: 12px 4px 12px 12px; }

/* Email HTML body rendering — scoped agar tidak bocor ke luar bubble */
.email-html-body { word-break: break-word; }
.email-html-body p  { margin-bottom: 0.3rem; }
.email-html-body a  { color: #2563eb; text-decoration: underline; }
.email-html-body ul, .email-html-body ol { padding-left: 1.25rem; margin-bottom: 0.4rem; }
.email-html-body blockquote { border-left: 3px solid #d1d5db; padding-left: 0.75rem; color: #6b7280; margin: 0.25rem 0; }
.email-html-body img { max-width: 100%; height: auto; border-radius: 6px; }
.email-html-body table { border-collapse: collapse; font-size: 12px; max-width: 100%; }
.email-html-body td, .email-html-body th { border: 1px solid #e5e7eb; padding: 4px 8px; }

/* Links di semua bubble (plain text, Quill HTML, internal note) */
.message-content a { color: #2563eb !important; text-decoration: underline !important; word-break: break-all; cursor: pointer; }
.message-content a:hover { color: #1d4ed8 !important; }
.email-html-body a  { color: #2563eb !important; text-decoration: underline !important; }
/* Links di Quill editor saat mengetik */
.ql-editor a { color: #2563eb !important; text-decoration: underline !important; cursor: pointer; }

/* Quill Editor Overrides */
.ql-toolbar.ql-snow { border: none !important; border-bottom: 1px solid #e5e7eb !important; padding: 4px 8px !important; background: #f9fafb; }
.ql-container.ql-snow { border: none !important; font-size: 13px; }
.ql-editor { min-height: 80px; max-height: 180px; overflow-y: auto; overflow-x: hidden; padding: 8px 12px; }
.ql-editor.ql-blank::before { font-style: normal; color: #9ca3af; font-size: 13px; }

/* Images inside editor — fit width, cap height */
.ql-editor img {
    max-width: 100%;
    max-height: 160px;
    width: auto;
    height: auto;
    object-fit: contain;
    display: inline-block;
    vertical-align: bottom;
    border-radius: 4px;
    margin: 2px 0;
    cursor: default;
}
/* Images inside message bubbles */
.message-bubble img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    display: block;
    margin: 4px 0;
}

/* Quill Toolbar Tooltips */
.ql-toolbar button, .ql-toolbar .ql-picker { position: relative; }
.ql-toolbar button[title]:hover::after,
.ql-toolbar .ql-picker[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: calc(100% + 5px);
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 5px;
    white-space: nowrap;
    z-index: 9999;
    pointer-events: none;
    font-family: inherit;
}

/* Channel badge pada pesan */
.msg-channel-badge {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 600; padding: 1px 6px;
    border-radius: 4px; vertical-align: middle;
}
.msg-channel-email { background: #dbeafe; color: #1d4ed8; }
.msg-channel-web   { background: #f0fdf4; color: #15803d; }

/* Message content */
.message-content p { margin-bottom: 0.25rem; }
.message-content p:last-child { margin-bottom: 0; }
.message-content ul, .message-content ol { padding-left: 1.5rem; margin-bottom: 0.5rem; }
.message-content blockquote { border-left: 3px solid #d1d5db; padding-left: 0.75rem; color: #6b7280; }

/* ─── Sidebar resize handle hover glow ─── */
#sidebarResizeHandle:hover,
#sidebarResizeHandle.resizing {
    background: rgba(255,255,255,0.35) !important;
    transition: background 0.15s;
}

/* ─── Sidebar ticket items ─── */
.sidebar-ticket-item {
    display: block;
    padding: 8px 10px 8px 12px;
    border-radius: 7px;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    text-decoration: none;
    background: rgba(255,255,255,0.92);
    border: 1px solid rgba(255,255,255,0.5);
    border-left: 3px solid transparent;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    overflow: hidden;
}
.sidebar-ticket-item:hover {
    background: rgba(255,255,255,1);
    border-color: rgba(255,255,255,0.8);
    border-left-color: #b91c1c;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.sidebar-ticket-item.active {
    background: rgba(255,255,255,1);
    border-color: rgba(255,255,255,0.9);
    border-left-color: #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* ─── Ticket card badge pills (bottom row) ─── */
.sb-badge {
    display: inline-flex; align-items: center;
    font-size: 9px; font-weight: 700; line-height: 1;
    padding: 2px 5px; border-radius: 4px;
    white-space: nowrap; flex-shrink: 0;
}
/* Priority */
.sb-prio-very-high { background:#ede9fe; color:#6d28d9; }
.sb-prio-high      { background:#fee2e2; color:#b91c1c; }
.sb-prio-medium    { background:#dbeafe; color:#1d4ed8; }
.sb-prio-low       { background:#dcfce7; color:#15803d; }
.sb-prio-default   { background:#f3f4f6; color:#4b5563; }
/* Jarvies status */
.sb-jarvies        { background:#fef9c3; color:#a16207; }
/* Ticket status */
.sb-status-open         { background:#dbeafe; color:#1d4ed8; }
.sb-status-in_progress  { background:#ede9fe; color:#6d28d9; }
.sb-status-closed       { background:#dcfce7; color:#15803d; }
.sb-status-wait_to_close{ background:#ffedd5; color:#c2410c; }
.sb-status-hold         { background:#f3f4f6; color:#4b5563; }
.sb-status-reply        { background:#fef9c3; color:#a16207; }
.sb-status-cancel       { background:#fee2e2; color:#b91c1c; }
.sb-status-default      { background:#f3f4f6; color:#6b7280; }
</style>

{{-- Assign to Delivery Support Modal --}}
@if(in_array($user->role->role_id, \App\Enums\RoleId::TICKET_MANAGER_GROUP, true))
<div id="assignSupportModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Assign to Delivery Support</h3>
                <button onclick="closeAssignSupportModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6 space-y-4">
            {{-- Option: New or Existing --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Assign to:</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="assignType" value="existing" checked onchange="toggleAssignType()" class="w-4 h-4 text-blue-600">
                        <span class="text-sm text-gray-700">Existing Delivery Support</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="assignType" value="new" onchange="toggleAssignType()" class="w-4 h-4 text-blue-600">
                        <span class="text-sm text-gray-700">Create New</span>
                    </label>
                </div>
            </div>

            {{-- Existing Delivery Support Selection --}}
            <div id="existingDeliverySupport">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Delivery Support</label>
<select id="deliverySupportSelect" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Loading...</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Ticket will be added as an activity under this delivery support</p>
            </div>

            {{-- New Delivery Support Form (hidden by default) --}}
            <div id="newDeliverySupport" class="hidden space-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Support Name <span class="text-red-500">*</span></label>
                    <input type="text" id="newSupportName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., Support - Customer Name">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Support Type <span class="text-red-500">*</span></label>
                    <select id="newSupportType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Type</option>
                        <option value="AMS">AMS</option>
                        <option value="MO">MO</option>
                        <option value="ATS">ATS</option>
                        <option value="Project">Project</option>
                        <option value="Internal">Internal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Support Method</label>
                    <select id="newSupportMethod" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Method</option>
                        <option value="remote">Remote</option>
                        <option value="onsite">On-site</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <button onclick="closeAssignSupportModal()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Cancel
            </button>
            <button onclick="confirmAssignSupport()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Assign
            </button>
        </div>
    </div>
</div>

{{-- Success Confirmation Modal --}}
<div id="assignSuccessModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-sm w-full shadow-2xl">
        <div class="p-6 text-center">
            <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7 text-green-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Ticket Assigned!</h3>
            <p class="text-sm text-gray-600 mb-6">Ticket has been successfully assigned to delivery support. Do you want to view it?</p>
            <div class="flex gap-3">
                <button onclick="closeAssignSuccessModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    Stay Here
                </button>
                <button id="btnViewDeliverySupport" onclick="goToDeliverySupport()" class="flex-1 inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    View Support
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ===== MANDAYS MODALS ===== --}}

{{-- PIC: Customer Mandays Modal --}}
@if(isset($isPic) && $isPic)
<div id="picMandaysModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-3xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Customer Mandays Proposal</h3>
                <p class="text-xs text-gray-500 mt-0.5">Version: <span id="picMandaysVersion">—</span> &nbsp;|&nbsp; Status: <span id="picMandaysStatusLabel">—</span></p>
            </div>
            <button onclick="closePicMandaysModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            {{-- Rejection / cancellation info --}}
            <div id="picRejectionInfo" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
            {{-- Description & Notes fields --}}
            <div id="picDescNotesWrap" class="mb-4 grid grid-cols-1 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Description <span class="text-gray-400 font-normal">(judul propose)</span></label>
                    <input id="picMandaysDescription" type="text" maxlength="255" placeholder="e.g. Propose Mandays 1 / Additional MD for New FM"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Notes <span class="text-gray-400 font-normal">(alasan / konteks)</span></label>
                    <textarea id="picMandaysNotes" rows="2" maxlength="2000" placeholder="e.g. Approve by WA / Ada tambahan MD setelah meeting tanggal ..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"></textarea>
                </div>
            </div>
            {{-- Activity rows management --}}
            <div id="picMandaysTableWrap" class="overflow-x-auto">
                <div id="picMandaysLoading" class="py-8 text-center text-gray-400 text-sm">Loading...</div>
                <table id="picMandaysTable" class="hidden w-full text-xs border-collapse">
                    <thead id="picMandaysHead"></thead>
                    <tbody id="picMandaysBody"></tbody>
                    <tfoot id="picMandaysFoot"></tfoot>
                </table>
            </div>
            {{-- Add activity row --}}
            <div id="picAddRowWrap" class="hidden mt-3 flex gap-2">
                <input id="picNewActivity" type="text" placeholder="Activity name (e.g. Analisa, Development, UAT)" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button onclick="picAddActivityRow()" class="px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 whitespace-nowrap">Add Row</button>
            </div>
        </div>
        <div id="picMandaysFooter" class="px-6 py-4 border-t border-gray-200 flex justify-between items-center flex-shrink-0 gap-3">
            <div class="flex items-center gap-3">
                <div class="text-xs text-gray-500">Total: <strong id="picTotalDisplay">0</strong> mandays</div>
                <button id="picBtnNewVersion" onclick="picStartNewVersion()" class="hidden px-4 py-2 bg-orange-500 text-white text-xs font-semibold rounded-lg hover:bg-orange-600 transition-all">New Version</button>
            </div>
            <div class="flex gap-2">
                <button id="picBtnSaveDraft" onclick="picSaveDraft()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-xs font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Save Draft</button>
                <button id="picBtnSubmit" onclick="picSubmitDraft()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Submit to Helpdesk</button>
            </div>
        </div>
    </div>
</div>

{{-- PIC: Internal Mandays Modal --}}
<div id="picInternalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Internal Mandays Proposal</h3>
            </div>
            <button onclick="closePicInternalModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="internalRejectionInfo" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
            <div id="internalLoading" class="py-8 text-center text-gray-400 text-sm">Loading...</div>
            <table id="internalTable" class="hidden w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Name</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16">MD</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16">Add</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Notes</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16">Appr Add</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16">Total MD</th>
                    </tr>
                </thead>
                <tbody id="internalBody"></tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td colspan="5" class="px-3 py-2 border border-gray-200 text-right text-xs">Total</td>
                        <td class="px-3 py-2 border border-gray-200 text-center" id="internalFooterTotal">0</td>
                    </tr>
                </tfoot>
            </table>
            <div class="mt-4">
                <label class="text-xs font-semibold text-gray-600">Notes for Head of Support</label>
                <textarea id="internalNotes" rows="2" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Optional notes..."></textarea>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center flex-shrink-0 gap-3">
            <div class="text-xs text-gray-500">Total: <strong id="internalTotalDisplay">0</strong> mandays</div>
            <div class="flex gap-2">
                <button id="internalBtnSave" onclick="internalPicSaveDraft()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-xs font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Save</button>
                <button id="internalBtnSubmit" onclick="internalPicSubmit()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Submit to Head</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Assign PIC Modal (Admin / Helpdesk / Head of Support) --}}
@if($canAssignPic)
<div id="assignPicModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-sm shadow-2xl flex flex-col">
        <div class="flex justify-between items-center px-5 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Assign PIC</h3>
            <button onclick="closeAssignPicModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-xs text-gray-500">Select a consultant to assign as PIC for this ticket.</p>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Consultant</label>
                <div class="relative">
                    <input id="assignPicSearch" type="text" placeholder="Search name..."
                        autocomplete="off"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-400"
                        oninput="filterAssignPicList()">
                    <div id="assignPicDropdown" class="hidden absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-xs"></div>
                </div>
                <input type="hidden" id="assignPicSelectedId">
                <div id="assignPicSelectedName" class="mt-1.5 text-xs text-teal-700 font-semibold hidden"></div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-2">
            <button id="assignPicBtn" onclick="submitAssignPic()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all">Assign</button>
        </div>
    </div>
</div>
@endif

{{-- Helpdesk: Customer Mandays Review Modal --}}
@if(isset($isHelpdesk) && $isHelpdesk)
<div id="hdMandaysModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-3xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Review Mandays Proposal</h3>
                <p class="text-xs text-gray-500 mt-0.5">Status: <span id="hdMandaysStatusLabel">—</span></p>
            </div>
            <button onclick="closeHdMandaysModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="hdMandaysLoading" class="py-8 text-center text-gray-400 text-sm">Loading...</div>
            {{-- Banner area: shown for approved / customer-rejected states --}}
            <div id="hdMandaysBanner" class="hidden mb-4 rounded-lg px-4 py-3 text-sm font-medium items-start gap-3"></div>
            <div id="hdMandaysContent" class="hidden">
                {{-- Rejection reason (shown when customer rejected) --}}
                <div id="hdRejectionReasonWrap" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-xs font-semibold text-red-700 mb-1">Customer Rejection Reason:</p>
                    <p id="hdRejectionReasonText" class="text-xs text-red-800"></p>
                </div>
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-xs border-collapse">
                        <thead id="hdMandaysHead"></thead>
                        <tbody id="hdMandaysBody"></tbody>
                        <tfoot id="hdMandaysFoot"></tfoot>
                    </table>
                </div>
                {{-- Cancel confirm section (shown when clicking "Cancel Proposal") --}}
                <div id="hdCancelConfirmWrap" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-xs font-semibold text-red-700 mb-2">Cancel Proposal — Confirmation</p>
                    <label class="text-xs font-medium text-red-700">Reason / Notes for PIC <span class="text-gray-500 font-normal">(optional)</span></label>
                    <textarea id="hdCancelNotes" rows="2" class="mt-1 w-full px-3 py-2 border border-red-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Explain why you are canceling this proposal..."></textarea>
                    <div class="flex gap-2 mt-2 justify-end">
                        <button type="button" onclick="hdCancelAbort()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Back</button>
                        <button type="button" onclick="hdCancelConfirm()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Confirm Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="hdMandaysFooter" class="px-6 py-4 border-t border-gray-200 flex flex-wrap gap-2 justify-between items-center flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="text-xs text-gray-500">Total: <strong id="hdTotalDisplay">0</strong> mandays</div>
                <button id="hdBtnCancel" onclick="hdShowCancelConfirm()" class="hidden px-4 py-2 bg-red-100 text-red-700 text-xs font-semibold rounded-lg hover:bg-red-200 border border-red-300">Cancel Proposal</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="hdBtnSendToChat"    onclick="hdSubmitToChat()"      class="hidden inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Send to Customer</button>
                <button id="hdBtnReviseResend"  onclick="hdReviseResend()"      class="hidden inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Revise &amp; Resend</button>
                <button id="hdBtnApprove"       onclick="hdApprove()"           class="hidden inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Approve</button>
                <button id="hdBtnNewProposal"   onclick="hdCreateNewProposal()" class="hidden inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Create New Proposal</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- Mandays: Version List Modal (shared: PIC / Helpdesk / Head)  --}}
{{-- ============================================================ --}}
<div id="mandaysVersionListModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-5xl shadow-2xl flex flex-col" style="max-height:85vh;">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-base font-bold text-gray-900">Mandays Proposal</h3>
                <p class="text-xs text-gray-400 mt-0.5">Riwayat semua versi propose mandays ke customer</p>
            </div>
            <button onclick="closeMandaysVersionList()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div id="mandaysVersionListLoading" class="py-10 text-center text-gray-400 text-sm">Loading...</div>
            <div id="mandaysVersionListEmpty" class="hidden py-10 text-center text-gray-400 text-sm italic">Belum ada propose mandays untuk tiket ini.</div>
            <div id="mandaysVersionListWrap" class="hidden overflow-x-auto">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-16">Version</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-500 border border-gray-200 whitespace-nowrap" style="min-width:180px;">Description</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-500 border border-gray-200 whitespace-nowrap" style="min-width:160px;">Note</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-32">Status</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-20">Total MD</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-36">Last Update</th>
                        </tr>
                    </thead>
                    <tbody id="mandaysVersionListBody"></tbody>
                </table>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end items-center flex-shrink-0 gap-3">
            @if($isPicMandays)
            <button id="mandaysVersionBtnNewPropose" onclick="mandaysVersionNewPropose()" class="hidden inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                New Propose
            </button>
            @endif
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- Mandays: Version Detail Modal (read-only view per version)   --}}
{{-- ============================================================ --}}
<div id="mandaysVersionDetailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-3xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Mandays Proposal — <span id="mvdVersionLabel">Version —</span></h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Status: <span id="mvdStatusLabel" class="font-semibold">—</span>
                    &nbsp;|&nbsp; Proposed by: <span id="mvdProposedBy">—</span>
                </p>
            </div>
            <button onclick="closeMandaysVersionDetail()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="mvdLoading" class="py-10 text-center text-gray-400 text-sm">Loading...</div>
            <div id="mvdContent" class="hidden">
                {{-- Description & Notes --}}
                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-gray-50 rounded-lg px-4 py-3">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Description</p>
                        <p id="mvdDescription" class="text-xs text-gray-800">—</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg px-4 py-3">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</p>
                        <p id="mvdNotes" class="text-xs text-gray-800 whitespace-pre-line">—</p>
                    </div>
                </div>
                {{-- Cancel/rejection info if any --}}
                <div id="mvdInfoBanner" class="hidden mb-4 p-3 rounded-lg text-xs"></div>
                {{-- Matrix table (read-only) --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead id="mvdTableHead"></thead>
                        <tbody id="mvdTableBody"></tbody>
                        <tfoot id="mvdTableFoot"></tfoot>
                    </table>
                </div>
                <div class="mt-3 text-xs text-gray-500 text-right">Total: <strong id="mvdTotal">0</strong> mandays</div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center flex-shrink-0 gap-3">
            <button onclick="closeMandaysVersionDetail()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-xs font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                ← Back to List
            </button>
            {{-- Only PIC: button to open edit modal if this version is still a draft --}}
            @if($isPicMandays)
            <button id="mvdBtnEditDraft" onclick="mvdOpenEditDraft()" class="hidden inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Edit Draft
            </button>
            @endif
            {{-- Only Helpdesk: button to open review modal if this is the pending version --}}
            @if($isHelpdeskMandays)
            <button id="mvdBtnHdReview" onclick="mvdOpenHdReview()" class="hidden inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Review This Version
            </button>
            @endif
        </div>
    </div>
</div>

{{-- Head of Support: Internal Mandays Modal --}}
@if(isset($isHead) && $isHead)
<div id="headInternalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Review Internal Mandays</h3>
                <p class="text-xs text-gray-500 mt-0.5">Status: <span id="headInternalStatusLabel">—</span></p>
            </div>
            <button onclick="closeHeadInternalModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="headInternalLoading" class="py-8 text-center text-gray-400 text-sm">Loading...</div>
            <div id="headInternalStatusBanner" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
            <div id="headInternalContent" class="hidden">
                <table class="w-full text-xs border-collapse mb-4">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Name</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-14">MD</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-14">Add</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Notes</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16">Appr Add</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16">Total MD</th>
                        </tr>
                    </thead>
                    <tbody id="headInternalBody"></tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-bold">
                            <td colspan="5" class="px-3 py-2 border border-gray-200 text-right text-xs">Total</td>
                            <td class="px-3 py-2 border border-gray-200 text-center" id="headInternalTotal">0</td>
                        </tr>
                    </tfoot>
                </table>
                <div id="headProposedBy" class="text-xs text-gray-500 mb-1"></div>
                <div id="headInternalNoteWrap" class="hidden p-3 bg-gray-50 rounded-lg text-xs text-gray-600 mb-3"></div>
            </div>
        </div>
        <div id="headInternalFooter" class="px-6 py-4 border-t border-gray-200 flex gap-2 justify-end flex-shrink-0">
            <button id="headBtnApprove" onclick="headInternalApprove()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Save</button>
        </div>
    </div>
</div>
@endif

{{-- Head of Support: Customer Mandays View-Only Modal --}}
@if(isset($isHead) && $isHead)
<div id="headCustomerMandaysModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Customer Mandays Proposal</h3>
                <p class="text-xs text-gray-500 mt-0.5">View Only — Status: <span id="headCustMandaysStatus">—</span></p>
            </div>
            <button onclick="closeHeadCustomerMandaysModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="headCustMandaysLoading" class="py-8 text-center text-gray-400 text-sm">Loading...</div>
            <div id="headCustMandaysContent" class="hidden space-y-4">
                <div id="headCustMandaysEmpty" class="hidden text-center text-sm text-gray-400 py-4">No proposal submitted yet.</div>
                <div id="headCustMandaysTable" class="hidden overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Activity</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Module</th>
                                <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16">Mandays</th>
                            </tr>
                        </thead>
                        <tbody id="headCustMandaysBody"></tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-bold">
                                <td colspan="2" class="px-3 py-2 border border-gray-200 text-right text-xs">Total</td>
                                <td class="px-3 py-2 border border-gray-200 text-center" id="headCustMandaysTotal">0</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div id="headCustMandaysNotes" class="hidden mt-3 p-3 bg-gray-50 rounded-lg text-xs text-gray-600"></div>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end flex-shrink-0">
        </div>
    </div>
</div>
@endif
{{-- ===== END MANDAYS MODALS ===== --}}

<script>
    const ticketId         = {{ $ticket->ticket_id }};
    const userRole         = {{ $user->role->role_id ?? 0 }};
    const ticketCustomerId = {{ $ticket->customer_id ?? 'null' }};
    const currentUserId    = {{ $user->id ?? 'null' }};
    const ticketChannel = @json($ticket->channel ?? 'web');
    const assignedDsId   = {{ isset($deliverySupport) && $deliverySupport ? $deliverySupport->id : 'null' }};
    const assignedDsName = @json(isset($deliverySupport) && $deliverySupport ? $deliverySupport->name : null);
    const assignedDsType = @json(isset($deliverySupport) && $deliverySupport ? $deliverySupport->type : null);
    let quillEditor     = null;

    // ── @mention state ───────────────────────────────────────────────────────
    let pendingMentions   = [];   // [{ type:'employee'|'role', id, display }]
    let mentionQuery      = null; // null = not in mention mode
    let mentionStartIndex = -1;   // character index where '@' was typed
    let mentionFetchTimer = null;

    function toggleSidebarPanel(panelId, chevronId) {
        const panel   = document.getElementById(panelId);
        const chevron = document.getElementById(chevronId);
        const hidden  = panel.classList.toggle('hidden');
        if (chevron) chevron.style.transform = hidden ? 'rotate(180deg)' : '';
    }
    let allSidebarTickets  = [];
    let sidebarView        = 'all';
    let deliverySupportList = [];
    // Set berisi ID pesan yang sudah dirender ke DOM.
    // Digunakan agar polling tidak me-render ulang pesan lama → gambar tidak flicker.
    let renderedMessageIds = new Set();

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Quill
        quillEditor = new Quill('#quillEditor', {
            theme: 'snow',
            placeholder: 'Type your reply here...',
            modules: {
                toolbar: {
                    container: [
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'header': [1, 2, 3, false] }],
                        ['link', 'image'],
                        ['clean']
                    ],
                    handlers: {
                        image: function () {
                            const input = document.createElement('input');
                            input.setAttribute('type', 'file');
                            input.setAttribute('accept', 'image/*');
                            input.click();
                            input.onchange = () => {
                                const file = input.files[0];
                                if (!file) return;
                                if (file.size > 10 * 1024 * 1024) {
                                    showNotification('Image too large (max 10 MB)', 'error');
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    const range = quillEditor.getSelection(true);
                                    quillEditor.insertEmbed(range.index, 'image', e.target.result, 'user');
                                    quillEditor.setSelection(range.index + 1, 0);
                                };
                                reader.readAsDataURL(file);
                            };
                        }
                    }
                }
            }
        });

        // Handle image paste (Ctrl+V) — resize oversized pastes
        quillEditor.root.addEventListener('paste', function (e) {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            for (const item of items) {
                if (item.type.indexOf('image') === 0) {
                    e.preventDefault();
                    const file = item.getAsFile();
                    if (!file) continue;
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        const range = quillEditor.getSelection(true);
                        quillEditor.insertEmbed(range ? range.index : 0, 'image', ev.target.result, 'user');
                        if (range) quillEditor.setSelection(range.index + 1, 0);
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        // Tambah tooltip title pada tombol toolbar Quill
        const toolbar = document.querySelector('.ql-toolbar');
        if (toolbar) {
            const map = {
                'ql-bold': 'Bold', 'ql-italic': 'Italic',
                'ql-underline': 'Underline', 'ql-strike': 'Strikethrough',
                'ql-blockquote': 'Blockquote', 'ql-link': 'Link',
                'ql-clean': 'Clear Formatting',
            };
            Object.entries(map).forEach(([cls, label]) => {
                const btn = toolbar.querySelector('.' + cls);
                if (btn) btn.setAttribute('title', label);
            });
            toolbar.querySelectorAll('.ql-list').forEach(btn => {
                btn.setAttribute('title', btn.value === 'ordered' ? 'Numbered List' : 'Bullet List');
            });
            const header = toolbar.querySelector('.ql-header');
            if (header) header.setAttribute('title', 'Heading');

            // Inject attachment button into toolbar
            const attachGroup = document.createElement('span');
            attachGroup.className = 'ql-formats';
            attachGroup.innerHTML = `
                <button type="button" id="attachBtn" title="Attach File"
                        onclick="document.getElementById('attachInput').click()"
                        style="width:auto;padding:2px 7px;display:inline-flex;align-items:center;gap:4px;border-radius:3px;">
                    <i class="fas fa-paperclip" style="font-size:12px;color:#555"></i>
                    <span style="font-size:11px;font-weight:500;color:#444;line-height:1.5">Attachment</span>
                </button>`;
            toolbar.appendChild(attachGroup);
        }

        // ── @mention: detect @ in quill text-change ──────────────────────────
        quillEditor.on('text-change', function () {
            const selection = quillEditor.getSelection();
            if (!selection) return;

            const cursorPos = selection.index;
            const text      = quillEditor.getText(0, cursorPos);

            // Find last '@' in text before cursor
            const atIdx = text.lastIndexOf('@');
            if (atIdx === -1) { closeMentionDropdown(); return; }

            const query = text.slice(atIdx + 1);

            // If there's a space after @, close dropdown
            if (query.includes(' ')) { closeMentionDropdown(); return; }

            mentionQuery      = query;
            mentionStartIndex = atIdx;

            // Debounce fetch
            clearTimeout(mentionFetchTimer);
            mentionFetchTimer = setTimeout(() => fetchMentionables(query), 200);
        });

        // ── Auto-link: detect URL saat user ketik spasi/enter setelah URL ──────
        // Ketika user mengetik spasi atau Enter setelah URL, format teks sebagai hyperlink biru.
        // Gunakan posisi dari delta.ops (bukan getSelection) agar lebih reliable.
        // setTimeout untuk menghindari masalah re-entrancy Quill.
        quillEditor.on('text-change', function(delta, _old, source) {
            // Hanya proses input dari user (bukan format API call)
            if (source !== 'user' || !delta || !delta.ops) return;

            // Cek apakah karakter terakhir yang diinsert adalah spasi atau enter
            const lastOp = delta.ops[delta.ops.length - 1];
            if (!lastOp || typeof lastOp.insert !== 'string') return;
            const inserted = lastOp.insert;
            if (inserted !== ' ' && inserted !== '\n') return;

            // Hitung posisi insert dari delta.ops (lebih reliable dari getSelection di text-change)
            let insertPos = 0;
            for (const op of delta.ops) {
                if (typeof op.retain === 'number') { insertPos = op.retain; break; }
            }

            // Teks sebelum karakter yang baru diinsert
            const textBefore = quillEditor.getText(0, insertPos);
            // Cari awal kata terakhir (pemisah: spasi atau newline)
            const lastBreak = Math.max(textBefore.lastIndexOf(' '), textBefore.lastIndexOf('\n'));
            const lastWord  = textBefore.slice(lastBreak + 1);

            if (!lastWord || !/^https?:\/\/\S{4,}$/.test(lastWord)) return;

            const urlStart = lastBreak + 1;

            // Defer agar tidak konflik dengan cycle update Quill saat ini
            setTimeout(function() {
                const fmt = quillEditor.getFormat(urlStart, lastWord.length);
                if (fmt.link) return; // sudah ada link — skip
                quillEditor.formatText(urlStart, lastWord.length, { link: lastWord }, 'api');
                // Lepas format link dari spasi/enter yang menjadi pemisah
                quillEditor.formatText(urlStart + lastWord.length, inserted.length, { link: false }, 'api');
            }, 0);
        });

        loadMessages();
        loadSidebarTickets();
        markMessagesRead();
        startMessagePolling();
    });

    // ==================== @MENTION AUTOCOMPLETE ====================
    function fetchMentionables(q) {
        fetch(`/api/employees/mentionable?q=${encodeURIComponent(q)}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const items = [];
            (data.employees || []).forEach(e => items.push({ type: 'employee', id: e.id, display: e.display_name, sub: e.role_name }));
            (data.roles     || []).forEach(r => items.push({ type: 'role',     id: r.id, display: '@' + r.name, sub: 'All in role' }));
            renderMentionDropdown(items);
        })
        .catch(() => closeMentionDropdown());
    }

    function renderMentionDropdown(items) {
        const list     = document.getElementById('mentionList');
        const dropdown = document.getElementById('mentionDropdown');
        if (!list || !dropdown) return;

        if (!items.length) { closeMentionDropdown(); return; }

        list.innerHTML = items.map((item, idx) => `
            <div class="mention-item flex items-center gap-2 px-3 py-2 hover:bg-red-50 cursor-pointer transition-colors"
                 data-idx="${idx}" data-type="${item.type}" data-id="${item.id}" data-display="${escHtml(item.display)}">
                <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-[10px] font-bold
                    ${item.type === 'role' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}">
                    ${item.type === 'role' ? '<i class="fas fa-users" style="font-size:9px"></i>' : item.display.charAt(0).toUpperCase()}
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-gray-800 truncate">${escHtml(item.display)}</div>
                    <div class="text-[10px] text-gray-400 truncate">${escHtml(item.sub)}</div>
                </div>
            </div>`
        ).join('');

        // mousedown (not click) to avoid quill losing focus before we handle it
        list.querySelectorAll('.mention-item').forEach(el => {
            el.addEventListener('mousedown', function (e) {
                e.preventDefault();
                insertMention(this.dataset.type, parseInt(this.dataset.id), this.dataset.display);
            });
        });

        dropdown.classList.remove('hidden');
    }

    function insertMention(type, id, display) {
        if (mentionStartIndex < 0) return;

        const cursorPos = quillEditor.getSelection()?.index ?? mentionStartIndex + 1 + (mentionQuery?.length ?? 0);
        const replaceLen = 1 + (mentionQuery?.length ?? 0); // '@' + typed query

        // Delete the '@...' text
        quillEditor.deleteText(mentionStartIndex, replaceLen);

        // Insert styled mention chip
        const chip = `@${display}`;
        quillEditor.insertText(mentionStartIndex, chip + ' ', {
            color: type === 'role' ? '#7c3aed' : '#1d4ed8',
            bold: true,
        });
        // Reset formatting after chip
        quillEditor.formatText(mentionStartIndex, chip.length + 1, { color: false, bold: false });
        quillEditor.setSelection(mentionStartIndex + chip.length + 1);

        // Track for payload
        const already = pendingMentions.find(m => m.type === type && m.id === id);
        if (!already) pendingMentions.push({ type, id, display });

        closeMentionDropdown();
    }

    function closeMentionDropdown() {
        document.getElementById('mentionDropdown')?.classList.add('hidden');
        mentionQuery = null;
        mentionStartIndex = -1;
    }

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMentionDropdown();
    });

    // ==================== AUTO POLLING: reload pesan & cek email baru ====================
    function startMessagePolling() {
        setInterval(async function () {
            // Jika tiket dari email, proses inbox dulu
            if (ticketChannel === 'email') {
                try {
                    await fetch('/api/email/process-inbox', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
                        },
                        credentials: 'same-origin'
                    });
                } catch (_) {}
            }
            // Selalu reload pesan (bisa ada balasan dari agent lain juga)
            await loadMessages();
        }, 15000); // setiap 15 detik
    }

    // ==================== MESSAGES ====================
    async function loadMessages() {
        const thread  = document.getElementById('messagesThread');
        const loading = document.getElementById('messagesLoading');

        if (!thread) {
            console.error('[loadMessages] ERROR: #messagesThread tidak ditemukan di DOM');
            return;
        }

        try {
            const response = await fetch(`/api/tickets/${ticketId}/messages`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                console.error('[loadMessages] Response tidak OK:', response.status);
                if (loading) loading.classList.add('hidden');
                return;
            }

            const data = await response.json();
            if (loading) loading.classList.add('hidden');

            // Tidak ada pesan dari server
            if (!data.success || !data.data || data.data.length === 0) {
                // Hanya tampilkan fallback jika memang belum ada apapun di thread
                if (renderedMessageIds.size === 0) {
                    thread.innerHTML = createFallbackMessage();
                }
                return;
            }

            const messages    = data.data;
            const isFirstLoad = renderedMessageIds.size === 0;

            // Filter hanya pesan yang belum pernah dirender
            const newMessages = messages.filter(msg => !renderedMessageIds.has(msg.id));

            if (newMessages.length === 0) {
                // Tidak ada pesan baru — DOM tidak disentuh, gambar tidak hilang
                return;
            }

            if (isFirstLoad) {
                // Load pertama: render semua sekaligus (innerHTML sekali, bukan per-pesan)
                thread.innerHTML = messages.map(msg => createMessageBubble(msg)).join('');
                messages.forEach(msg => renderedMessageIds.add(msg.id));
                console.log('[loadMessages] Initial render:', messages.length, 'pesan');
            } else {
                // Poll berikutnya: hanya append pesan baru di bawah, pesan lama tidak disentuh
                newMessages.forEach(msg => {
                    thread.insertAdjacentHTML('beforeend', createMessageBubble(msg));
                    renderedMessageIds.add(msg.id);
                });
                console.log('[loadMessages] Appended', newMessages.length, 'pesan baru');
            }

            thread.scrollTop = thread.scrollHeight;

        } catch (error) {
            console.error('[loadMessages] EXCEPTION:', error.name, error.message);
            if (loading) loading.classList.add('hidden');
            // Hanya tampilkan fallback jika thread masih kosong
            if (renderedMessageIds.size === 0) {
                thread.innerHTML = createFallbackMessage();
            }
        }
    }

    // ── Render attachment list (gambar inline, file sebagai link download) ──────
    // isEmailWithHtml: true jika pesan email sudah punya message_html →
    //   inline images sudah ditampilkan di dalam HTML body, jadi tidak perlu ditampilkan ulang sebagai thumbnail
    function renderAttachments(attachments, isEmailWithHtml = false) {
        if (!attachments || attachments.length === 0) return '';

        // Pisahkan inline images dan file biasa
        // Jika email dengan HTML body: abaikan inline images (sudah ada di message_html setelah CID replacement)
        const inlineImgs = isEmailWithHtml
            ? []
            : attachments.filter(a => a.is_inline && a.mime_type?.startsWith('image/'));
        // Untuk email dengan HTML body: juga exclude is_inline=true dari files (sudah ada di HTML body)
        const files = isEmailWithHtml
            ? attachments.filter(a => !a.is_inline)
            : attachments.filter(a => !inlineImgs.includes(a));

        let html = '';

        if (inlineImgs.length > 0) {
            html += `<div class="mt-2 flex flex-wrap gap-2">`;
            inlineImgs.forEach(img => {
                html += `<a href="${img.url}" target="_blank" title="${escHtml(img.file_name)}">
                    <img src="${img.url}" alt="${escHtml(img.file_name)}"
                         class="max-h-48 max-w-xs rounded-lg border border-gray-200 cursor-zoom-in hover:opacity-90 transition-opacity"
                         onerror="this.style.display='none'">
                </a>`;
            });
            html += `</div>`;
        }

        if (files.length > 0) {
            html += `<div class="mt-2 space-y-1">`;
            files.forEach(file => {
                const icon  = attachmentIcon(file.attachment_type, file.mime_type);
                const size  = formatFileSize(file.file_size);
                const isImg = file.mime_type?.startsWith('image/');
                html += `<div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2 max-w-xs">
                    <span class="text-lg flex-shrink-0">${icon}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-700 truncate">${escHtml(file.file_name)}</p>
                        ${size ? `<p class="text-[10px] text-gray-400">${size}</p>` : ''}
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        ${isImg ? `<a href="${file.url}" target="_blank" class="text-xs text-blue-500 hover:underline">View</a>` : ''}
                        <a href="${file.url}" download="${escHtml(file.file_name)}"
                           class="text-xs text-blue-500 hover:underline">Download</a>
                    </div>
                </div>`;
            });
            html += `</div>`;
        }

        return html;
    }

    function attachmentIcon(type, mime) {
        if (mime?.startsWith('image/'))        return '🖼️';
        if (type === 'pdf')                    return '📄';
        if (type === 'document')               return '📝';
        if (type === 'spreadsheet')            return '📊';
        if (type === 'archive')                return '🗜️';
        return '📎';
    }

    function formatFileSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024)       return bytes + ' B';
        if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Linkify: buat URL plain text jadi <a> yang bisa diklik ─────────────────
    // Inline style dipakai langsung agar tidak kalah oleh CSS cascade (Tailwind, dsb).
    const _linkStyle = 'color:#2563eb;text-decoration:underline;word-break:break-all;';

    // linkifyHtml: aman untuk HTML yang sudah ada — tidak menyentuh <a> yang sudah ada.
    function linkifyHtml(html) {
        if (!html) return html;
        // Pecah di existing <a>...</a> lalu linkify hanya bagian di luar <a>
        const parts = html.split(/(<a[\s\S]*?<\/a>)/gi);
        return parts.map((part, i) => {
            if (i % 2 === 1) return part; // sudah <a> tag — skip
            return part.replace(
                /(https?:\/\/[^\s<>"'()[\]{}]+)/gi,
                `<a href="$1" target="_blank" rel="noopener noreferrer" style="${_linkStyle}">$1</a>`
            );
        }).join('');
    }

    // linkifyText: untuk plain text — escape HTML dulu (XSS safe) lalu linkify.
    function linkifyText(text) {
        if (!text) return '';
        const esc = text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        return esc.replace(
            /(https?:\/\/[^\s<>"]+)/gi,
            `<a href="$1" target="_blank" rel="noopener noreferrer" style="${_linkStyle}">$1</a>`
        );
    }

    // Ganti sisa referensi cid: yang tidak ter-replace backend dengan placeholder
    function sanitizeEmailHtml(html) {
        if (!html) return html;
        return html.replace(/<img\b([^>]*)\bsrc\s*=\s*["']cid:[^"']*["']([^>]*)>/gi,
            '<span class="inline-flex items-center gap-1.5 text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded px-2 py-1">' +
            '<i class="fas fa-image" style="font-size:10px"></i> Image unavailable</span>'
        );
    }

    // ── Pilih konten pesan: HTML dari email atau plain text dari web ────────────
    function messageContent(msg) {
        // Email dengan HTML body → render HTML + linkify URL plain text yang tidak terbungkus <a>
        if (msg.channel === 'email' && msg.message_html) {
            return `<div class="message-content text-sm text-gray-700 email-html-body">${linkifyHtml(sanitizeEmailHtml(msg.message_html))}</div>`;
        }

        // Internal note: render Quill HTML (contains @mention chips with color formatting)
        // Fall back to plain text with mention highlighting if no html
        if (msg.message_type === 'internal_note') {
            if (msg.message_html) {
                return `<div class="message-content text-sm text-gray-700">${linkifyHtml(msg.message_html)}</div>`;
            }
            if (!msg.message_body) return '';
            const highlighted = msg.message_body.replace(/@([\w.]+(?:\s[\w.]+)*)/g, (match) =>
                `<span class="inline-flex items-center px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-semibold">${escHtml(match)}</span>`
            );
            return `<div class="message-content text-sm text-gray-700">${linkifyText(highlighted)}</div>`;
        }

        // Employee reply dengan message_html → render HTML + linkify URL plain text
        if (msg.sender_type === 'employee' && msg.message_html) {
            return `<div class="message-content text-sm text-gray-700 email-html-body">${linkifyHtml(sanitizeEmailHtml(msg.message_html))}</div>`;
        }

        // Web reply atau customer message → escape + linkify (XSS safe)
        if (!msg.message_body) return '';
        return `<div class="message-content text-sm text-gray-700">${linkifyText(msg.message_body)}</div>`;
    }

    function createMessageBubble(msg) {
        const isEmployee     = msg.sender_type === 'employee';
        const isInternalNote = msg.message_type === 'internal_note';
        const senderName     = msg.sender_name || (isEmployee ? 'Employee' : 'Customer');
        const initials       = senderName.substring(0, 1).toUpperCase();
        const date           = new Date(msg.created_at).toLocaleString('en-GB', {
            timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: false
        }) + ' (WIB)';

        const channelBadge = msg.channel === 'email'
            ? `<span class="msg-channel-badge msg-channel-email"><svg style="width:9px;height:9px;display:inline" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg> Email</span>`
            : `<span class="msg-channel-badge msg-channel-web"><svg style="width:9px;height:9px;display:inline" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z" clip-rule="evenodd"/></svg> Web</span>`;

        // CC badge — hanya tampil kalau ada CC
        // Normalisasi: API mungkin kembalikan array atau JSON string (data lama) → selalu array
        const rawCc  = msg.cc_emails;
        const ccList = Array.isArray(rawCc) ? rawCc
                     : (typeof rawCc === 'string' && rawCc ? ((() => { try { return JSON.parse(rawCc); } catch(e) { return []; } })()) : []);
        const ccBadge  = ccList.length > 0
            ? `<span class="inline-flex items-center gap-1 text-[10px] text-gray-400 mt-0.5">
                <svg style="width:9px;height:9px;flex-shrink:0" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                <span class="font-medium text-gray-500">CC:</span>
                ${ccList.map(c => `<span title="${c.address || c}">${c.name || c.address || c}</span>`).join(', ')}
               </span>`
            : '';

        const isEmailWithHtml = msg.channel === 'email' && !!msg.message_html;
        const attachmentsHtml = renderAttachments(msg.attachments, isEmailWithHtml);

        if (isInternalNote) {
            const isMine = msg.sender_id && currentUserId && String(msg.sender_id) === String(currentUserId);
            const avatarBgNote = isMine ? 'bg-amber-400' : 'bg-amber-200';
            const avatarTextNote = isMine ? 'text-white' : 'text-amber-800';
            const bubbleExtra = isMine ? 'mine' : '';
            const noteBadge = `<span class="text-[10px] bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded font-semibold leading-none">📝 Internal Note</span>`;

            if (isMine) {
                return `
                <div class="flex gap-3 flex-row-reverse">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${avatarBgNote} ${avatarTextNote} text-xs font-bold">${initials}</div>
                    <div class="text-right">
                        <div class="flex items-center gap-2 justify-end mb-1">
                            ${noteBadge}
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        <div class="message-bubble internal-note ${bubbleExtra} p-3 inline-block text-left">
                            ${messageContent(msg)}
                            ${attachmentsHtml}
                        </div>
                    </div>
                </div>`;
            } else {
                return `
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${avatarBgNote} ${avatarTextNote} text-xs font-bold">${initials}</div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            ${noteBadge}
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        <div class="message-bubble internal-note ${bubbleExtra} p-3 inline-block text-left">
                            ${messageContent(msg)}
                            ${attachmentsHtml}
                        </div>
                    </div>
                </div>`;
            }
        }

        const avatarBg   = isEmployee ? 'bg-blue-500' : 'bg-gray-400';
        const bubbleClass = isEmployee ? 'employee' : 'customer';

        return `
            <div class="flex gap-3 ${isEmployee ? 'flex-row-reverse' : ''}">
                <div class="w-8 h-8 ${avatarBg} rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">${initials}</div>
                <div class="${isEmployee ? 'text-right' : ''}">
                    <div class="flex flex-col mb-1 ${isEmployee ? 'items-end' : ''}">
                        <div class="flex items-center gap-2 ${isEmployee ? 'justify-end' : ''}">
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            ${channelBadge}
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        ${ccBadge}
                    </div>
                    <div class="message-bubble ${bubbleClass} p-3 inline-block text-left">
                        ${messageContent(msg)}
                        ${attachmentsHtml}
                    </div>
                </div>
            </div>`;
    }

    function createFallbackMessage() {
        const customerName = @json($ticket->customer?->basicData?->name_1 ?? 'Customer');
        const description = @json($ticket->description ?? 'No description');
        const date = @json($ticket->created_at->format('d M Y H:i') . ' WIB');
        return `
            <div class="flex gap-3">
                <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">${customerName.substring(0, 1)}</div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900">${customerName}</span>
                        <span class="text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded font-semibold">Initial</span>
                        <span class="text-xs text-gray-400">${date}</span>
                    </div>
                    <div class="message-bubble customer p-3 inline-block">
                        <div class="message-content text-sm text-gray-700">${description}</div>
                    </div>
                </div>
            </div>`;
    }

    // ==================== ATTACHMENT HANDLING (COMPOSE) ====================
    let selectedFiles = []; // File[] yang dipilih user untuk dikirim bersama reply

    document.getElementById('attachInput').addEventListener('change', function () {
        const maxSize = 10 * 1024 * 1024; // 10 MB per file
        Array.from(this.files).forEach(file => {
            if (file.size > maxSize) {
                showNotification(`${file.name} is too large (max 10 MB)`, 'error');
                return;
            }
            // Hindari duplikat berdasarkan nama + ukuran
            if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                selectedFiles.push(file);
            }
        });
        // Reset value agar file yang sama bisa dipilih ulang setelah dihapus
        this.value = '';
        renderAttachmentPreview();
    });

    function renderAttachmentPreview() {
        const preview = document.getElementById('attachmentPreview');
        const countEl = document.getElementById('attachCount');

        if (selectedFiles.length === 0) {
            preview.style.display = 'none';
            countEl.classList.add('hidden');
            return;
        }

        preview.style.display = 'flex';
        countEl.classList.remove('hidden');
        countEl.textContent = selectedFiles.length + (selectedFiles.length === 1 ? ' file' : ' files');

        preview.innerHTML = selectedFiles.map((file, idx) => {
            const size = formatFileSize(file.size);
            const icon = file.type.startsWith('image/') ? '🖼️'
                       : file.type === 'application/pdf' ? '📄'
                       : /\.(doc|docx)$/i.test(file.name) ? '📝'
                       : /\.(xls|xlsx|csv)$/i.test(file.name) ? '📊'
                       : /\.(zip|rar)$/i.test(file.name) ? '🗜️'
                       : '📎';
            return `<div class="flex items-center gap-1.5 bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1.5" style="max-width:200px">
                <span class="text-sm flex-shrink-0">${icon}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-700 truncate" title="${escHtml(file.name)}">${escHtml(file.name)}</p>
                    ${size ? `<p class="text-[10px] text-gray-400">${size}</p>` : ''}
                </div>
                <button type="button" onclick="removeAttachment(${idx})" title="Remove"
                        class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors text-xs leading-none">✕</button>
            </div>`;
        }).join('');
    }

    function removeAttachment(idx) {
        selectedFiles.splice(idx, 1);
        renderAttachmentPreview();
    }

    function resetAttachments() {
        selectedFiles = [];
        document.getElementById('attachInput').value = '';
        renderAttachmentPreview();
    }

    // ==================== SEND REPLY ====================

    /**
     * Hapus <p><br></p> dan <p></p> yang kosong di awal dan akhir HTML Quill.
     * Quill menambahkan empty paragraphs saat user menekan Enter tanpa mengetik,
     * menyebabkan whitespace besar di atas/bawah pesan yang ditampilkan.
     */
    function trimQuillHtml(html) {
        // Ulangi penghapusan sampai tidak ada lagi empty paragraph di tepi
        let prev;
        do {
            prev = html;
            html = html
                .replace(/^(\s*<p[^>]*>\s*(<br\s*\/?>)?\s*<\/p>\s*)+/i, '')
                .replace(/(\s*<p[^>]*>\s*(<br\s*\/?>)?\s*<\/p>\s*)+$/i, '');
        } while (html !== prev);
        return html.trim();
    }

    async function sendReply(messageType) {
        const rawHtml      = quillEditor.root.innerHTML;
        const htmlContent  = trimQuillHtml(rawHtml);
        const plainContent = quillEditor.getText().trim();
        const hasFiles     = selectedFiles.length > 0;

        // Perlu minimal teks atau file lampiran
        if (!plainContent && !hasFiles) {
            showNotification('Type a message or attach a file', 'error');
            return;
        }

        // Disable tombol kirim selama proses agar tidak double-submit
        const sendBtn = document.querySelector('button[onclick="sendReply(\'reply\')"]');
        const noteBtn = document.querySelector('button[onclick="sendReply(\'internal_note\')"]');
        if (sendBtn) { sendBtn.disabled = true; sendBtn.classList.add('opacity-60'); }
        if (noteBtn) { noteBtn.disabled = true; noteBtn.classList.add('opacity-60'); }

        try {
            let requestBody;
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            };

            // Collect mention IDs for internal notes
            const mentionedEmployeeIds = messageType === 'internal_note'
                ? pendingMentions.filter(m => m.type === 'employee').map(m => m.id)
                : [];
            const mentionedRoleIds = messageType === 'internal_note'
                ? pendingMentions.filter(m => m.type === 'role').map(m => m.id)
                : [];

            if (hasFiles) {
                // Kirim sebagai multipart/form-data
                // Jangan set Content-Type manual — browser otomatis tambahkan boundary yang benar
                const formData = new FormData();
                formData.append('message_body', htmlContent);
                formData.append('message_type', messageType);
                selectedFiles.forEach(file => formData.append('attachments[]', file));
                mentionedEmployeeIds.forEach(id => formData.append('mentioned_employee_ids[]', id));
                mentionedRoleIds.forEach(id => formData.append('mentioned_role_ids[]', id));
                requestBody = formData;
            } else {
                headers['Content-Type'] = 'application/json';
                requestBody = JSON.stringify({
                    message_body: htmlContent,
                    message_type: messageType,
                    mentioned_employee_ids: mentionedEmployeeIds,
                    mentioned_role_ids: mentionedRoleIds,
                });
            }

            const response = await fetch(`/api/tickets/${ticketId}/messages`, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: requestBody
            });

            const data = await response.json();

            if (data.success) {
                quillEditor.setContents([]);
                resetAttachments();
                pendingMentions = []; // reset mentions
                await loadMessages();
                showNotification(messageType === 'internal_note' ? 'Internal note added' : 'Reply sent', 'success');
            } else {
                console.warn('[sendReply] API error:', data.message, data.errors);
                showNotification(data.message || 'Failed to send message', 'error');
            }
        } catch (error) {
            console.error('[sendReply] EXCEPTION:', error.name, error.message);
            showNotification('Error: ' + error.message, 'error');
        } finally {
            if (sendBtn) { sendBtn.disabled = false; sendBtn.classList.remove('opacity-60'); }
            if (noteBtn) { noteBtn.disabled = false; noteBtn.classList.remove('opacity-60'); }
        }
    }

    async function markMessagesRead() {
        try {
            await fetch(`/api/tickets/${ticketId}/messages/mark-all-read`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });
        } catch (e) {}
    }

    // ==================== SIDEBAR TICKETS ====================
    function switchSidebarView(view) {
        sidebarView = view;
        const tabAll = document.getElementById('sidebarTabAll');
        const tabMy  = document.getElementById('sidebarTabMy');
        if (tabAll && tabMy) {
            tabAll.style.background = view === 'all' ? 'rgba(255,255,255,0.2)' : '';
            tabAll.classList.toggle('opacity-60', view !== 'all');
            tabMy.style.background  = view === 'my'  ? 'rgba(255,255,255,0.2)' : '';
            tabMy.classList.toggle('opacity-60', view !== 'my');
        }
        loadSidebarTickets();
    }

    async function loadSidebarTickets() {
        try {
            let endpoint = '/api/tickets';
            if (userRole === 3) endpoint = '/api/tickets/my';
            else if ([1, 2, 6, 7].includes(userRole) && sidebarView === 'my') endpoint = '/api/tickets/my';

            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();

            document.getElementById('sidebarLoading').classList.add('hidden');

            if (data.success) {
                allSidebarTickets = data.data.sort((a, b) => new Date(b.last_message_at || b.created_at) - new Date(a.last_message_at || a.created_at));
                renderSidebarTickets(allSidebarTickets);
            }
        } catch (error) {
            document.getElementById('sidebarLoading').classList.add('hidden');
        }
    }

    function renderSidebarTickets(tickets) {
        const list = document.getElementById('sidebarTicketList');

        // Priority badge class mapping
        const prioBadge = {
            'Very High': 'sb-prio-very-high',
            'High':      'sb-prio-high',
            'Medium':    'sb-prio-medium',
            'Low':       'sb-prio-low',
        };
        // Status label + badge class mapping
        const statusMap = {
            'open':          ['Open',           'sb-status-open'],
            'in_progress':   ['In Progress',    'sb-status-in_progress'],
            'closed':        ['Closed',         'sb-status-closed'],
            'wait_to_close': ['Wait Close',     'sb-status-wait_to_close'],
            'hold':          ['Hold',           'sb-status-hold'],
            'reply':         ['Reply',          'sb-status-reply'],
            'cancel':        ['Canceled',       'sb-status-cancel'],
        };

        list.innerHTML = tickets.map(t => {
            const isActive   = t.ticket_id === ticketId;
            const custName   = t.customer?.customer_name || 'Unknown';
            const tickNum    = t.ticket_number || '';
            const desc       = t.description || 'No description';
            const lastActivity = new Date(t.last_message_at || t.created_at);
            const timeAgo    = formatTimeAgo(lastActivity);
            const timeTitle  = lastActivity.toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });

            // Line 1 heading: "26040024 - AptaWorks"
            const heading = tickNum ? `${tickNum} - ${custName}` : custName;

            // Priority badge
            const prioKey   = t.ticket_priority || 'Medium';
            const prioCls   = prioBadge[prioKey] || 'sb-prio-default';

            // Jarvies status badge (only if available)
            const jStatus   = t.jarvies_status;
            const jBadge    = jStatus
                ? `<span class="sb-badge sb-jarvies">JS: ${jStatus}</span>`
                : '';

            // Ticket status badge
            const sRaw    = (t.status || 'open').toLowerCase();
            const [sLabel, sCls] = statusMap[sRaw] || ['Unknown', 'sb-status-default'];

            return `
                <a href="/ticket/${t.ticket_id}" class="sidebar-ticket-item ${isActive ? 'active' : ''}" title="${heading}">
                    <div class="flex items-start justify-between gap-1 mb-0.5">
                        <span class="text-[11px] font-bold text-gray-800 truncate leading-tight">${heading}</span>
                        <span class="text-[9px] text-gray-400 flex-shrink-0 mt-0.5" title="${timeTitle}">${timeAgo}</span>
                    </div>
                    <p class="text-[10px] text-gray-500 truncate mb-1.5 leading-snug">${desc}</p>
                    <div class="flex items-center gap-1 flex-wrap">
                        <span class="sb-badge ${prioCls}">${prioKey}</span>
                        ${jBadge}
                        <span class="sb-badge ${sCls}">S: ${sLabel}</span>
                    </div>
                </a>`;
        }).join('');
    }

    function filterSidebarTickets() {
        const term = document.getElementById('sidebarSearch').value.toLowerCase();
        if (!term) {
            renderSidebarTickets(allSidebarTickets);
            return;
        }
        const filtered = allSidebarTickets.filter(t =>
            (t.ticket_number && t.ticket_number.toLowerCase().includes(term)) ||
            (t.description && t.description.toLowerCase().includes(term)) ||
            (t.customer?.customer_name && t.customer.customer_name.toLowerCase().includes(term))
        );
        renderSidebarTickets(filtered);
    }

    // ==================== SIDEBAR RESIZE ====================
    (function () {
        const STORAGE_KEY = 'sidebar_width';
        const MIN_W = 210;
        const MAX_W = 520;
        const DEFAULT_W = 256; // w-64 = 16rem = 256px

        const sidebar     = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        // Restore saved width on page load
        const savedW = parseInt(localStorage.getItem(STORAGE_KEY), 10);
        if (savedW && savedW >= MIN_W && savedW <= MAX_W) {
            applySidebarWidth(savedW);
        }

        function applySidebarWidth(w) {
            sidebar.style.width     = w + 'px';
            mainContent.style.marginLeft = w + 'px';
        }

        window.sidebarResizeStart = function (e) {
            e.preventDefault();
            const handle = document.getElementById('sidebarResizeHandle');
            handle.classList.add('resizing');
            // Prevent text selection while dragging
            document.body.style.userSelect = 'none';
            document.body.style.cursor     = 'col-resize';

            const startX    = e.clientX;
            const startW    = sidebar.offsetWidth;

            function onMove(ev) {
                const delta = ev.clientX - startX;
                const newW  = Math.min(MAX_W, Math.max(MIN_W, startW + delta));
                applySidebarWidth(newW);
            }

            function onUp() {
                handle.classList.remove('resizing');
                document.body.style.userSelect = '';
                document.body.style.cursor     = '';
                // Save final width
                localStorage.setItem(STORAGE_KEY, sidebar.offsetWidth);
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup',  onUp);
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup',  onUp);
        };

        // Patch toggleSidebar so it respects saved width on expand
        const _origToggle = window.toggleSidebar;
        window.toggleSidebar = function () {
            _origToggle && _origToggle();
            // After toggle, re-apply inline style so resize override stays consistent
            // isCollapsed is defined in dashboard.blade.php
            if (typeof isCollapsed !== 'undefined') {
                if (isCollapsed) {
                    sidebar.style.width          = '80px';
                    mainContent.style.marginLeft = '80px';
                } else {
                    const w = parseInt(localStorage.getItem(STORAGE_KEY), 10) || DEFAULT_W;
                    applySidebarWidth(Math.min(MAX_W, Math.max(MIN_W, w)));
                }
            }
        };
    })();

    // ==================== TEAM MEMBERS ====================
    const allEmployees  = @json($employees);
    const canManageMembers = {{ $canManageMembers ? 'true' : 'false' }};

    function escHtmlMember(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function renderMembers(members) {
        const list = document.getElementById('membersList');
        if (!list) return;

        const memberIds = new Set(members.map(m => m.employee_id));

        if (members.length === 0) {
            list.innerHTML = '<p class="text-xs text-gray-400 italic" id="noMembersText">No members assigned.</p>';
        } else {
            list.innerHTML = members.map(m => `
                <div class="member-chip flex items-center justify-between gap-1 px-2.5 py-1.5 bg-blue-50 rounded-lg" data-id="${m.employee_id}">
                    <span class="text-xs text-blue-700 font-medium truncate">${escHtmlMember(m.name)}</span>
                    ${canManageMembers ? `<button type="button" onclick="removeMemberBtn(${m.employee_id})"
                        class="text-blue-300 hover:text-red-500 transition-colors flex-shrink-0 ml-1">
                        <i class="fas fa-times text-[9px]"></i></button>` : ''}
                </div>`).join('');
        }

        // Rebuild dropdown: show only employees not already in members and not the PIC
        const sel = document.getElementById('addMemberSelect');
        if (sel) {
            sel.innerHTML = '<option value="">-- Add member --</option>';
            allEmployees.forEach(emp => {
                if (!memberIds.has(emp.employee_id) && emp.employee_id != {{ $ticket->employee_id ?? 'null' }}) {
                    const opt = document.createElement('option');
                    opt.value = emp.employee_id;
                    opt.textContent = emp.name;
                    sel.appendChild(opt);
                }
            });
        }
    }

    async function addMemberBtn() {
        const sel   = document.getElementById('addMemberSelect');
        const empId = sel?.value;
        if (!empId) { showNotification('Please select a member to add.', 'error'); return; }

        const btn = sel.nextElementSibling;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-[10px]"></i>'; }

        try {
            const res  = await fetch(`/api/tickets/${ticketId}/members`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ employee_id: parseInt(empId) }),
            });
            const data = await res.json();
            if (!data.success) { showNotification(data.message || 'Failed to add member.', 'error'); return; }
            renderMembers(data.data);
            showNotification('Member added successfully.', 'success');
        } catch {
            showNotification('Error adding member.', 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-user-plus text-[10px]"></i>'; }
        }
    }

    async function removeMemberBtn(employeeId) {
        try {
            const res  = await fetch(`/api/tickets/${ticketId}/members/${employeeId}`, {
                method: 'DELETE',
                headers: getHeaders(),
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!data.success) { showNotification(data.message || 'Failed to remove member.', 'error'); return; }
            renderMembers(data.data);
            showNotification('Member removed.', 'success');
        } catch {
            showNotification('Error removing member.', 'error');
        }
    }

    // ==================== ADMIN ACTIONS ====================
    function getHeaders() {
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        };
    }

    async function saveAllProperties() {
        const status = document.getElementById('detailStatus').value;
        const jarviesStatus = document.getElementById('detailJarviesStatus').value;
        const priority = document.getElementById('detailPriority').value;
        const type = document.getElementById('detailType').value;
        const pic = document.getElementById('detailPIC').value;
        try {
            // Update status via dedicated endpoint
            await fetch(`/api/tickets/${ticketId}/update-status`, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ status: status })
            });

            // Update all other properties via general update endpoint
            const updateData = {
                jarvies_status: jarviesStatus,
                ticket_priority: priority,
                ticket_type: type || null,
                employee_id: pic || null,
            };

            const response = await fetch(`/api/tickets/${ticketId}`, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(updateData)
            });

            const result = await response.json();

            if (result.success) {
                showNotification('All properties saved!', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showNotification(result.message || 'Failed to save', 'error');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        }
    }

    async function takeTicket() {
        const btn = document.querySelector('button[onclick="takeTicket()"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Taking...'; }
        try {
            const response = await fetch(`/api/tickets/${ticketId}`, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ employee_id: {{ $user->id ?? 'null' }} }),
            });
            const result = await response.json();
            if (result.success) {
                showNotification('Ticket taken! You are now the PIC.', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showNotification(result.message || 'Failed to take ticket.', 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-hand-paper text-xs"></i> Take This Ticket'; }
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-hand-paper text-xs"></i> Take This Ticket'; }
        }
    }

    // ==================== ASSIGN PIC MODAL ====================
    let assignPicList = [];

    async function openAssignPicModal() {
        document.getElementById('assignPicModal').classList.remove('hidden');
        document.getElementById('assignPicModal').classList.add('flex');
        document.getElementById('assignPicSearch').value = '';
        document.getElementById('assignPicSelectedId').value = '';
        document.getElementById('assignPicSelectedName').classList.add('hidden');
        document.getElementById('assignPicDropdown').classList.add('hidden');

        if (assignPicList.length === 0) {
            try {
                const res  = await fetch('/api/tickets/available-pics', { headers: getHeaders(), credentials: 'same-origin' });
                const data = await res.json();
                assignPicList = data.data || [];
            } catch (e) {
                showNotification('Failed to load consultant list', 'error');
            }
        }
        renderAssignPicDropdown(assignPicList);
    }

    function closeAssignPicModal() {
        document.getElementById('assignPicModal').classList.add('hidden');
        document.getElementById('assignPicModal').classList.remove('flex');
    }

    function filterAssignPicList() {
        const q = document.getElementById('assignPicSearch').value.trim().toLowerCase();
        const filtered = q ? assignPicList.filter(p => p.name.toLowerCase().includes(q)) : assignPicList;
        renderAssignPicDropdown(filtered);
        document.getElementById('assignPicDropdown').classList.remove('hidden');
        document.getElementById('assignPicSelectedId').value = '';
        document.getElementById('assignPicSelectedName').classList.add('hidden');
    }

    function renderAssignPicDropdown(list) {
        const dd = document.getElementById('assignPicDropdown');
        if (!list.length) {
            dd.innerHTML = '<div class="px-3 py-2 text-gray-400 italic">No consultant found</div>';
        } else {
            dd.innerHTML = list.map(p =>
                `<div class="px-3 py-2 hover:bg-red-50 cursor-pointer text-gray-700" onclick="selectAssignPic(${p.employee_id}, '${p.name.replace(/'/g, "\\'")}')">${p.name}</div>`
            ).join('');
        }
        dd.classList.remove('hidden');
    }

    function selectAssignPic(id, name) {
        document.getElementById('assignPicSelectedId').value = id;
        document.getElementById('assignPicSearch').value = name;
        document.getElementById('assignPicDropdown').classList.add('hidden');
        const label = document.getElementById('assignPicSelectedName');
        label.textContent = '✓ ' + name + ' selected';
        label.classList.remove('hidden');
    }

    async function submitAssignPic() {
        const empId = document.getElementById('assignPicSelectedId').value;
        if (!empId) { showNotification('Please select a consultant', 'warning'); return; }

        const btn = document.getElementById('assignPicBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Assigning...';

        try {
            const res    = await fetch(`/api/tickets/${ticketId}/assign-pic`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ employee_id: empId }),
            });
            const result = await res.json();
            if (result.success) {
                showNotification('PIC assigned successfully!', 'success');
                closeAssignPicModal();
                setTimeout(() => location.reload(), 800);
            } else {
                showNotification(result.message || 'Failed to assign PIC', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Assign';
            }
        } catch (e) {
            showNotification('Error: ' + e.message, 'error');
            btn.disabled = false;
            btn.innerHTML = 'Assign';
        }
    }

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        const dd = document.getElementById('assignPicDropdown');
        if (dd && !dd.contains(e.target) && e.target.id !== 'assignPicSearch') {
            dd.classList.add('hidden');
        }
    });

    async function deleteTicket() {
        if (!confirm('Are you sure you want to delete this ticket?')) return;
        try {
            const response = await fetch(`/api/tickets/${ticketId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (result.success) {
                showNotification('Ticket deleted!', 'success');
                setTimeout(() => window.location.href = '/ticket', 500);
            } else {
                showNotification(result.message || 'Failed to delete', 'error');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        }
    }

    // ==================== HELPERS ====================
    function formatTimeAgo(date) {
        const tz = 'Asia/Jakarta';
        const now = new Date();
        const toDay = (d) => new Date(d.toLocaleDateString('en-CA', { timeZone: tz }));
        const todayDate  = toDay(now);
        const targetDate = toDay(date);
        const diffDays = Math.round((todayDate - targetDate) / 86400000);

        if (diffDays === 0) {
            return date.toLocaleTimeString('id-ID', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false });
        }
        if (diffDays === 1) return 'Yest';
        if (diffDays < 7) {
            return date.toLocaleDateString('en-GB', { timeZone: tz, weekday: 'short' });
        }
        if (date.getFullYear() === now.getFullYear()) {
            return date.toLocaleDateString('en-GB', { timeZone: tz, day: '2-digit', month: 'short' });
        }
        return date.toLocaleDateString('en-GB', { timeZone: tz, day: '2-digit', month: 'short', year: 'numeric' });
    }

    // showNotification() is provided globally by the dashboard layout (toast system)

    // ==================== ASSIGN TO DELIVERY SUPPORT ====================
    async function openAssignSupportModal() {
        const modal = document.getElementById('assignSupportModal');
        if (modal) {
            modal.classList.remove('hidden');
            // Load existing delivery supports
            await loadDeliverySupports();
        }
    }

    function closeAssignSupportModal() {
        const modal = document.getElementById('assignSupportModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function toggleAssignType() {
        const assignType = document.querySelector('input[name="assignType"]:checked')?.value;
        const existingDiv = document.getElementById('existingDeliverySupport');
        const newDiv = document.getElementById('newDeliverySupport');

        if (assignType === 'new') {
            existingDiv.classList.add('hidden');
            newDiv.classList.remove('hidden');
        } else {
            existingDiv.classList.remove('hidden');
            newDiv.classList.add('hidden');
        }
    }

    async function loadDeliverySupports() {
        const select = document.getElementById('deliverySupportSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Loading...</option>';

        try {
            // Load delivery supports, optionally filtered by the same customer
            const response = await fetch('/api/delivery/support/search?client_id=' + (ticketCustomerId || ''), {
                headers: getHeaders(),
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                deliverySupportList = data.data;
                select.innerHTML = '<option value="">-- Select Delivery Support --</option>';

                if (data.data.length === 0) {
                    select.innerHTML = '<option value="">No delivery support found</option>';
                    return;
                }

                data.data.forEach(support => {
                    const option = document.createElement('option');
                    option.value = support.id;
                    option.textContent = `${support.name} (${support.client_name || 'Unknown Client'}), ${support.type}`;
                    select.appendChild(option);
                });

                // Auto-select currently assigned DS
                if (assignedDsId) {
                    const matchingOption = [...select.options].find(o => Number(o.value) === assignedDsId);
                    if (matchingOption) {
                        select.value = matchingOption.value;
                    }
                }
            } else {
                select.innerHTML = '<option value="">Failed to load</option>';
            }
        } catch (error) {
            select.innerHTML = '<option value="">Error loading data</option>';
        }
    }

    async function confirmAssignSupport() {
        const assignType = document.querySelector('input[name="assignType"]:checked')?.value;

        if (assignType === 'existing') {
            await assignToExistingSupport();
        } else {
            await createNewSupportAndAssign();
        }
    }

    async function assignToExistingSupport() {
        const supportId = document.getElementById('deliverySupportSelect').value;

        if (!supportId) {
            showNotification('Please select a delivery support', 'error');
            return;
        }

        try {
            const response = await fetch(`/api/tickets/${ticketId}/assign-to-support`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ support_id: supportId })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Ticket assigned to delivery support successfully!', 'success');
                closeAssignSupportModal();
                if (data.data?.support_name) updateDsBadges(data.data.support_id || supportId, data.data.support_name, data.data.support_type ?? null);
                showAssignSuccessModal(`/delivery/support/${supportId}`);
            } else {
                showNotification(data.message || 'Failed to assign ticket', 'error');
            }
        } catch (error) {
            console.error('Error assigning ticket:', error);
            showNotification('Error: ' + error.message, 'error');
        }
    }

    async function createNewSupportAndAssign() {
        const supportName   = document.getElementById('newSupportName').value.trim();
        const supportType   = document.getElementById('newSupportType').value;
        const supportMethod = document.getElementById('newSupportMethod').value;

        if (!supportName) {
            showNotification('Please enter a support name', 'error');
            return;
        }
        if (!supportType) {
            showNotification('Please select a support type', 'error');
            return;
        }

        try {
            const response = await fetch('/api/tickets/' + ticketId + '/create-delivery-support', {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: supportName,
                    type: supportType,
                    support_method: supportMethod
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Delivery support created and ticket assigned!', 'success');
                closeAssignSupportModal();
                if (data.data?.support_name) updateDsBadges(data.data.support_id, data.data.support_name, data.data.support_type ?? null);
                if (data.data?.support_id) {
                    showAssignSuccessModal(`/delivery/support/${data.data.support_id}`);
                }
            } else {
                showApiErrors(data, 'Failed to create delivery support');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        }
    }


    // ==================== ASSIGN SUCCESS MODAL ====================

    function updateDsBadges(dsId, dsName, dsType = null) {
        const typeHtml = dsType ? ` <span style="opacity:.7">(${dsType})</span>` : '';
        const svgPath = `M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z`;
        const svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="${svgPath}" /></svg>`;

        // 1. Top headbar badge
        const existing = document.getElementById('headbarTopDsBadge');
        if (existing) existing.remove();
        const subtitleEl = document.querySelector('.text-xs.text-gray-500');
        if (subtitleEl) {
            const badge = document.createElement('span');
            badge.id = 'headbarTopDsBadge';
            badge.className = 'inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-100 text-blue-700 align-middle';
            badge.innerHTML = `${svgIcon}DS: ${dsName}${typeHtml}`;
            subtitleEl.appendChild(badge);
        }

        // 2. Properties panel
        const propBadge = document.getElementById('propertiesDsBadge');
        if (propBadge && dsId) {
            const svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>`;
            const extIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 flex-shrink-0 ml-auto opacity-50"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>`;
            const link = document.createElement('a');
            link.href = `/delivery/support/${dsId}`;
            link.className = 'flex items-center gap-1.5 px-2.5 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-xs font-medium text-blue-700 hover:bg-blue-100 transition-colors';
            const typeBadge = dsType ? `<span class="flex-shrink-0 px-1.5 py-0.5 bg-blue-200 text-blue-800 rounded text-[10px] font-bold">${dsType}</span>` : '';
            link.innerHTML = `${svgIcon}<span class="truncate">${dsName}</span>${typeBadge}${extIcon}`;
            propBadge.replaceWith(link);
        }
    }

    let assignSuccessRedirectUrl = '';

    function showAssignSuccessModal(url) {
        assignSuccessRedirectUrl = url;
        document.getElementById('assignSuccessModal').classList.remove('hidden');
    }

    function closeAssignSuccessModal() {
        document.getElementById('assignSuccessModal').classList.add('hidden');
        assignSuccessRedirectUrl = '';
    }

    function goToDeliverySupport() {
        if (assignSuccessRedirectUrl) {
            window.location.href = assignSuccessRedirectUrl;
        }
    }


    // ==================== MANDAYS — SHARED ====================
    let picMandaysModules  = [];
    let picDraftData       = null;
    let picReadOnly        = false;
    let internalPicData    = null;
    let internalPicPeople  = [];
    let internalPicReadOnly= false;

    // Version list state
    let mandaysHistoryData      = [];
    let mandaysVersionListRole  = 'pic'; // 'pic' | 'hd' | 'head'
    let mandaysCurrentVersionId = null;  // for detail modal

    const MANDAYS_API = (path) => `/api/tickets/${ticketId}/mandays/${path}`;

    // ==================== MANDAYS VERSION LIST MODAL ====================

    async function openMandaysVersionList(role) {
        mandaysVersionListRole = role || 'pic';
        document.getElementById('mandaysVersionListModal').classList.remove('hidden');
        document.getElementById('mandaysVersionListModal').classList.add('flex');
        await loadMandaysVersionList();
    }

    function closeMandaysVersionList() {
        document.getElementById('mandaysVersionListModal').classList.add('hidden');
        document.getElementById('mandaysVersionListModal').classList.remove('flex');
    }

    async function loadMandaysVersionList() {
        document.getElementById('mandaysVersionListLoading').classList.remove('hidden');
        document.getElementById('mandaysVersionListEmpty').classList.add('hidden');
        document.getElementById('mandaysVersionListWrap').classList.add('hidden');

        try {
            const res  = await fetch(MANDAYS_API('history'), { headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            mandaysHistoryData = data.data || [];
            const ticketStatus = data.ticket_mandays_status || 'none';

            // Show "New Propose" button only for PIC when latest version is approved, canceled, or no version yet
            const btnNew = document.getElementById('mandaysVersionBtnNewPropose');
            if (btnNew) {
                const latestStatus = mandaysHistoryData.length > 0
                    ? mandaysHistoryData[mandaysHistoryData.length - 1].status
                    : null;
                const canNew = !latestStatus
                    || latestStatus === 'approved'
                    || latestStatus === 'canceled';
                btnNew.classList.toggle('hidden', !canNew);
            }

            if (mandaysHistoryData.length === 0) {
                document.getElementById('mandaysVersionListEmpty').classList.remove('hidden');
            } else {
                renderMandaysVersionList(mandaysHistoryData);
                document.getElementById('mandaysVersionListWrap').classList.remove('hidden');
            }
        } catch (e) {
            console.error(e);
            showNotification('Failed to load mandays history', 'error');
        } finally {
            document.getElementById('mandaysVersionListLoading').classList.add('hidden');
        }
    }

    const MANDAYS_STATUS_LABELS = {
        draft:            'Draft',
        pending_helpdesk: 'Pending Review',
        sent_to_chat:     'Waiting Customer',
        approved:         'Approved',
        canceled:         'Canceled',
    };
    const MANDAYS_STATUS_BADGE = {
        draft:            'bg-yellow-100 text-yellow-700',
        pending_helpdesk: 'bg-blue-100 text-blue-700',
        sent_to_chat:     'bg-purple-100 text-purple-700',
        approved:         'bg-green-100 text-green-700',
        canceled:         'bg-red-100 text-red-700',
    };

    function renderMandaysVersionList(versions) {
        const tbody = document.getElementById('mandaysVersionListBody');
        let html = '';
        versions.forEach(v => {
            const badgeClass  = MANDAYS_STATUS_BADGE[v.status]  || 'bg-gray-100 text-gray-600';
            const statusLabel = MANDAYS_STATUS_LABELS[v.status] || v.status;
            const desc = v.description
                ? escHtml(v.description)
                : '<span class="text-gray-300">—</span>';
            const note = v.proposal_notes
                ? `<span class="text-gray-500" title="${escHtml(v.proposal_notes)}">${escHtml(v.proposal_notes.substring(0, 40))}${v.proposal_notes.length > 40 ? '…' : ''}</span>`
                : '<span class="text-gray-300">—</span>';
            const lastUpdate = v.last_update
                ? new Date(v.last_update).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', hour12: false })
                : '—';
            html += `<tr class="hover:bg-blue-50 cursor-pointer transition-colors" onclick="openMandaysVersionDetail(${v.id})">
                <td class="px-3 py-2.5 border border-gray-100 text-center font-bold text-gray-700 whitespace-nowrap">v${v.version}</td>
                <td class="px-3 py-2.5 border border-gray-100 text-gray-800 whitespace-nowrap">${desc}</td>
                <td class="px-3 py-2.5 border border-gray-100 whitespace-nowrap">${note}</td>
                <td class="px-3 py-2.5 border border-gray-100 text-center whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold ${badgeClass}">${statusLabel}</span>
                </td>
                <td class="px-3 py-2.5 border border-gray-100 text-center font-semibold text-gray-700 whitespace-nowrap">${v.total_mandays}</td>
                <td class="px-3 py-2.5 border border-gray-100 text-center text-gray-500 whitespace-nowrap">${lastUpdate}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    async function mandaysVersionNewPropose() {
        closeMandaysVersionList();

        // Reset state untuk versi baru
        picDraftData = null;
        picReadOnly  = false;
        picDirty     = false;

        // Buka modal & tampilkan loading
        const modal = document.getElementById('picMandaysModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('picMandaysLoading').classList.remove('hidden');
        document.getElementById('picMandaysTable').classList.add('hidden');
        document.getElementById('picAddRowWrap').classList.add('hidden');

        // Reset header & fields
        document.getElementById('picMandaysVersion').textContent      = 'New';
        document.getElementById('picMandaysStatusLabel').textContent  = 'Draft';
        document.getElementById('picMandaysDescription').value        = '';
        document.getElementById('picMandaysNotes').value              = '';
        document.getElementById('picRejectionInfo').classList.add('hidden');
        document.getElementById('picBtnNewVersion').classList.add('hidden');
        document.getElementById('picBtnSaveDraft').classList.remove('hidden');
        document.getElementById('picBtnSubmit').classList.remove('hidden');
        const descWrap = document.getElementById('picDescNotesWrap');
        if (descWrap) descWrap.querySelectorAll('input,textarea').forEach(el => {
            el.removeAttribute('readonly');
            el.classList.remove('bg-gray-50', 'cursor-not-allowed');
        });

        try {
            // Load modules dari kualifikasi employee
            const modRes  = await fetch(MANDAYS_API('modules'), { headers: getHeaders(), credentials: 'same-origin' });
            const modData = await modRes.json();
            picMandaysModules = modData.data || [];
            picRenderMatrix({});
        } catch (e) {
            console.error(e);
            showNotification('Failed to load modules', 'error');
        } finally {
            document.getElementById('picMandaysLoading').classList.add('hidden');
        }
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ==================== MANDAYS VERSION DETAIL MODAL ====================

    async function openMandaysVersionDetail(mandaysId) {
        mandaysCurrentVersionId = mandaysId;
        document.getElementById('mandaysVersionDetailModal').classList.remove('hidden');
        document.getElementById('mandaysVersionDetailModal').classList.add('flex');
        document.getElementById('mvdLoading').classList.remove('hidden');
        document.getElementById('mvdContent').classList.add('hidden');

        try {
            const res  = await fetch(MANDAYS_API(`version/${mandaysId}`), { headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) { showNotification('Failed to load version detail', 'error'); return; }
            renderMandaysVersionDetail(data.data);
        } catch (e) {
            console.error(e);
            showNotification('Error loading version detail', 'error');
        } finally {
            document.getElementById('mvdLoading').classList.add('hidden');
        }
    }

    function closeMandaysVersionDetail() {
        document.getElementById('mandaysVersionDetailModal').classList.add('hidden');
        document.getElementById('mandaysVersionDetailModal').classList.remove('flex');
        mandaysCurrentVersionId = null;
    }

    function renderMandaysVersionDetail(p) {
        document.getElementById('mvdVersionLabel').textContent = 'Version ' + p.version;
        document.getElementById('mvdStatusLabel').textContent  = MANDAYS_STATUS_LABELS[p.status] || p.status;
        document.getElementById('mvdProposedBy').textContent   = p.proposed_by_name || '—';
        document.getElementById('mvdDescription').textContent  = p.description || '—';
        document.getElementById('mvdNotes').textContent        = p.proposal_notes || '—';

        // Info banner (cancel/rejection)
        const banner = document.getElementById('mvdInfoBanner');
        if (p.status === 'canceled' && p.cancel_notes) {
            banner.className = 'mb-4 p-3 rounded-lg text-xs bg-gray-100 border border-gray-300 text-gray-700';
            banner.innerHTML = '<strong>Canceled by Helpdesk:</strong> ' + escHtml(p.cancel_notes) + (p.canceled_by_name ? ' — ' + escHtml(p.canceled_by_name) : '');
            banner.classList.remove('hidden');
        } else if (p.rejection_reason) {
            banner.className = 'mb-4 p-3 rounded-lg text-xs bg-red-50 border border-red-200 text-red-700';
            banner.innerHTML = '<strong>Customer Rejection:</strong> ' + escHtml(p.rejection_reason);
            banner.classList.remove('hidden');
        } else {
            banner.classList.add('hidden');
        }

        // Build matrix
        const detailMap = {};
        const modules   = [];
        (p.details || []).forEach(d => {
            const act = d.activity || 'General';
            if (!detailMap[act]) detailMap[act] = {};
            detailMap[act][d.module] = d.mandays;
            if (!modules.includes(d.module)) modules.push(d.module);
        });

        let headHtml = '<tr class="bg-gray-50"><th class="px-2 py-2 text-left text-xs font-semibold text-gray-600 border border-gray-200">Activity</th>';
        modules.forEach(m => { headHtml += `<th class="px-2 py-2 text-center text-xs font-semibold text-gray-600 border border-gray-200 whitespace-nowrap">${escHtml(m)}</th>`; });
        headHtml += '</tr>';
        document.getElementById('mvdTableHead').innerHTML = headHtml;

        let bodyHtml = '';
        Object.entries(detailMap).forEach(([act, modMap]) => {
            bodyHtml += `<tr><td class="px-2 py-1.5 border border-gray-200 text-xs font-medium text-gray-700 whitespace-nowrap">${escHtml(act)}</td>`;
            modules.forEach(m => {
                const val = modMap[m] ?? '';
                bodyHtml += `<td class="px-2 py-1.5 border border-gray-200 text-xs text-center bg-gray-50">${val !== '' ? val : '—'}</td>`;
            });
            bodyHtml += '</tr>';
        });
        document.getElementById('mvdTableBody').innerHTML = bodyHtml;

        // Footer
        let footHtml = '<tr class="bg-gray-50 font-bold"><td class="px-2 py-1.5 border border-gray-200 text-xs">Total</td>';
        modules.forEach(m => {
            const colTotal = Object.values(detailMap).reduce((s, row) => s + (parseFloat(row[m]) || 0), 0);
            footHtml += `<td class="px-2 py-1.5 border border-gray-200 text-xs text-center">${colTotal > 0 ? colTotal.toFixed(1) : '—'}</td>`;
        });
        footHtml += '</tr>';
        document.getElementById('mvdTableFoot').innerHTML = footHtml;
        document.getElementById('mvdTotal').textContent  = p.total_mandays;

        // Action buttons
        const btnEdit = document.getElementById('mvdBtnEditDraft');
        if (btnEdit) btnEdit.classList.toggle('hidden', p.status !== 'draft');

        const btnHd = document.getElementById('mvdBtnHdReview');
        if (btnHd) btnHd.classList.toggle('hidden', !['pending_helpdesk', 'sent_to_chat'].includes(p.status));

        document.getElementById('mvdContent').classList.remove('hidden');
    }

    function mvdOpenEditDraft() {
        // Close detail, open PIC modal (loads current draft)
        closeMandaysVersionDetail();
        closeMandaysVersionList();
        document.getElementById('picMandaysModal').classList.remove('hidden');
        document.getElementById('picMandaysModal').classList.add('flex');
        picLoadDraft();
    }

    function mvdOpenHdReview() {
        closeMandaysVersionDetail();
        closeMandaysVersionList();
        openHdMandaysModal();
    }

    // ==================== PIC: CUSTOMER MANDAYS ====================
    async function openPicMandaysModal() {
        document.getElementById('picMandaysModal').classList.remove('hidden');
        document.getElementById('picMandaysModal').classList.add('flex');
        await picLoadDraft();
    }
    function closePicMandaysModal() {
        document.getElementById('picMandaysModal').classList.add('hidden');
        document.getElementById('picMandaysModal').classList.remove('flex');
    }

    async function picLoadDraft() {
        picDirty = false;
        document.getElementById('picMandaysLoading').classList.remove('hidden');
        document.getElementById('picMandaysTable').classList.add('hidden');
        document.getElementById('picAddRowWrap').classList.add('hidden');
        document.getElementById('picRejectionInfo').classList.add('hidden');

        try {
            // Load modules & draft in parallel
            const [modRes, draftRes] = await Promise.all([
                fetch(MANDAYS_API('modules'), { headers: getHeaders(), credentials: 'same-origin' }),
                fetch(MANDAYS_API('pic-draft'), { headers: getHeaders(), credentials: 'same-origin' }),
            ]);
            const modData   = await modRes.json();
            const draftData = await draftRes.json();

            picDraftData      = draftData.data;
            const status      = draftData.ticket_mandays_status || 'none';
            // Read-only when submitted/reviewed by helpdesk (PIC can't edit after submission unless canceled)
            picReadOnly = ['pending_helpdesk', 'sent_to_chat', 'approved', 'canceled'].includes(status);

            const picStatusLabels = {
                none: 'None', pic_draft: 'Draft', pending_helpdesk: 'Submitted to Helpdesk',
                sent_to_chat: 'Sent to Customer', approved: 'Approved', canceled: 'Canceled by Helpdesk'
            };
            document.getElementById('picMandaysVersion').textContent      = picDraftData?.version ?? 'New';
            document.getElementById('picMandaysStatusLabel').textContent  = picStatusLabels[status] || status;
            // Show "New Version" when proposal is canceled, approved, or sent back (customer rejected)
            const canStartNew = picDraftData && (status === 'canceled' || status === 'approved' || (status === 'pending_helpdesk' && picDraftData?.rejection_reason));
            document.getElementById('picBtnNewVersion').classList.toggle('hidden', !canStartNew);

            const picInfoEl = document.getElementById('picRejectionInfo');
            if (status === 'canceled') {
                picInfoEl.className = 'mb-4 p-3 rounded-lg text-sm bg-gray-100 border border-gray-300 text-gray-700';
                const cancelNotes = picDraftData?.cancel_notes;
                const canceledByName = picDraftData?.canceled_by_name;
                let cancelHtml = '<p class="font-semibold text-gray-800 mb-1">Proposal Canceled by Helpdesk</p>';
                if (cancelNotes) {
                    cancelHtml += '<p class="text-xs text-gray-600">' + cancelNotes.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>';
                } else {
                    cancelHtml += '<p class="text-xs text-gray-500 italic">No reason provided.</p>';
                }
                if (canceledByName) {
                    cancelHtml += '<p class="text-xs text-gray-500 mt-1">— ' + canceledByName.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>';
                }
                picInfoEl.innerHTML = cancelHtml;
                picInfoEl.classList.remove('hidden');
            } else if (picDraftData?.rejection_reason) {
                picInfoEl.className = 'mb-4 p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-700';
                picInfoEl.innerHTML = '<p class="font-semibold mb-1">Customer Rejection</p><p class="text-xs">' + picDraftData.rejection_reason.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>';
                picInfoEl.classList.remove('hidden');
            } else {
                picInfoEl.classList.add('hidden');
            }

            // Populate description & notes fields
            const descInput  = document.getElementById('picMandaysDescription');
            const notesInput = document.getElementById('picMandaysNotes');
            if (descInput)  { descInput.value  = picDraftData?.description    || ''; descInput.readOnly  = picReadOnly; }
            if (notesInput) { notesInput.value = picDraftData?.proposal_notes || ''; notesInput.readOnly = picReadOnly; }
            const descWrap = document.getElementById('picDescNotesWrap');
            if (descWrap) descWrap.querySelectorAll('input,textarea').forEach(el => {
                el.classList.toggle('bg-gray-50', picReadOnly);
                el.classList.toggle('cursor-not-allowed', picReadOnly);
            });

            // Build valueMap from existing details
            const valueMap = {};
            (picDraftData?.details || []).forEach(d => {
                const act = d.activity || 'General';
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
            // If no activities, start with empty table

            // Kolom hanya dari qualification member ticket
            // Jika belum diisi di master data, PIC tambah manual via "+ Column"
            picMandaysModules = modData.data || [];

            picRenderMatrix(valueMap);
        } catch (e) {
            console.error(e);
            showNotification('Failed to load mandays data', 'error');
        } finally {
            document.getElementById('picMandaysLoading').classList.add('hidden');
        }
    }

    function picRenderMatrix(valueMap) {
        const modules = picMandaysModules;
        const activities = Object.keys(valueMap);

        // Header
        let headHtml = '<tr class="bg-gray-50">';
        headHtml += '<th class="px-2 py-2 text-left text-xs font-semibold text-gray-600 border border-gray-200">Activity</th>';
        modules.forEach(m => {
            const mEsc = m.replace(/"/g, '&quot;');
            const removeBtn = !picReadOnly
                ? `<button onclick="picRemoveModuleCol('${mEsc}')" class="ml-1 text-red-300 hover:text-red-600 font-bold leading-none" title="Remove column">×</button>`
                : '';
            headHtml += `<th class="px-2 py-2 text-center text-xs font-semibold text-gray-600 border border-gray-200 whitespace-nowrap">${m}${removeBtn}</th>`;
        });
        headHtml += '</tr>';
        document.getElementById('picMandaysHead').innerHTML = headHtml;

        // Body
        let bodyHtml = '';
        if (activities.length === 0 && !picReadOnly) {
            const colspan = modules.length + 1;
            bodyHtml = `<tr><td colspan="${colspan}" class="px-3 py-4 text-center text-xs text-gray-400 italic border border-gray-200">
                Belum ada activity. Ketik nama activity lalu klik <strong>Add Row</strong> di bawah.
            </td></tr>`;
        }
        activities.forEach(act => {
            const actEsc = act.replace(/"/g, '&quot;');
            const removeRowBtn = !picReadOnly
                ? `<button onclick="picRemoveActivityRow('${actEsc}')" class="ml-1 text-red-300 hover:text-red-600 font-bold leading-none" title="Remove row">×</button>`
                : '';
            bodyHtml += `<tr data-activity="${act}">`;
            bodyHtml += `<td class="px-2 py-1.5 border border-gray-200 text-xs font-medium text-gray-700 whitespace-nowrap">${act}${removeRowBtn}</td>`;
            modules.forEach(m => {
                const val = valueMap[act]?.[m] || '';
                bodyHtml += `<td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="pic-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-indigo-50 ${picReadOnly ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}"
                        data-activity="${act}" data-module="${m}" value="${val}"
                        ${picReadOnly ? 'readonly' : ''} oninput="picDirty=true; picUpdateTotal()">
                    </td>`;
            });
            bodyHtml += '</tr>';
        });
        document.getElementById('picMandaysBody').innerHTML = bodyHtml;

        // Footer total
        let footHtml = '<tr class="bg-gray-50 font-bold"><td class="px-2 py-1.5 border border-gray-200 text-xs">Total</td>';
        modules.forEach(m => {
            footHtml += `<td id="picColTotal_${m}" class="px-2 py-1.5 border border-gray-200 text-xs text-center">0</td>`;
        });
        footHtml += '</tr>';
        document.getElementById('picMandaysFoot').innerHTML = footHtml;

        document.getElementById('picMandaysTable').classList.remove('hidden');
        // Show editing controls only when PIC can still edit (draft state)
        document.getElementById('picAddRowWrap').classList.toggle('hidden', picReadOnly);
        document.getElementById('picBtnSaveDraft').classList.toggle('hidden', picReadOnly);
        document.getElementById('picBtnSubmit').classList.toggle('hidden', picReadOnly);

        picUpdateTotal();
    }

    function picUpdateTotal() {
        let grand = 0;
        const colTotals = {};
        document.querySelectorAll('.pic-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            const m = inp.dataset.module;
            colTotals[m] = (colTotals[m] || 0) + v;
            grand += v;
        });
        document.getElementById('picTotalDisplay').textContent = grand.toFixed(1);
        Object.entries(colTotals).forEach(([m, t]) => {
            const el = document.getElementById(`picColTotal_${m}`);
            if (el) el.textContent = t.toFixed(1);
        });
    }

    function picAddActivityRow() {
        const name = document.getElementById('picNewActivity').value.trim();
        if (!name) { showNotification('Enter an activity name', 'warning'); return; }
        document.getElementById('picNewActivity').value = '';
        const currentMap = picGetCurrentValueMap();
        if (currentMap[name]) { showNotification('Activity already exists', 'warning'); return; }
        currentMap[name] = {};
        picDirty = true;
        picRenderMatrix(currentMap);
    }

    function picRemoveActivityRow(act) {
        const currentMap = picGetCurrentValueMap();
        delete currentMap[act];
        picDirty = true;
        picRenderMatrix(currentMap);
    }

    function picAddModuleCol() {
        const input = document.getElementById('picNewModule');
        const name = input.value.trim();
        if (!name) { showNotification('Enter a module name', 'warning'); return; }
        if (picMandaysModules.includes(name)) { showNotification('Module already exists', 'warning'); return; }
        input.value = '';
        picMandaysModules.push(name);
        picDirty = true;
        picRenderMatrix(picGetCurrentValueMap());
    }

    function picRemoveModuleCol(mod) {
        picMandaysModules = picMandaysModules.filter(m => m !== mod);
        if (picMandaysModules.length === 0) { showNotification('At least one module is required', 'warning'); picMandaysModules.push(mod); return; }
        picDirty = true;
        picRenderMatrix(picGetCurrentValueMap());
    }

    function picGetCurrentValueMap() {
        const map = {};
        document.querySelectorAll('.pic-cell').forEach(inp => {
            const act = inp.dataset.activity;
            const m   = inp.dataset.module;
            if (!map[act]) map[act] = {};
            map[act][m] = parseFloat(inp.value) || 0;
        });
        return map;
    }

    function picGetPayload() {
        const details = [];
        document.querySelectorAll('.pic-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            if (v > 0) {
                details.push({ activity: inp.dataset.activity, module: inp.dataset.module, mandays: v });
            }
        });
        return {
            details,
            description:    (document.getElementById('picMandaysDescription')?.value || '').trim() || null,
            proposal_notes: (document.getElementById('picMandaysNotes')?.value || '').trim() || null,
        };
    }

    async function picSaveDraft() {
        const btn = document.getElementById('picBtnSaveDraft');
        btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const res = await fetch(MANDAYS_API('pic-draft'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(picGetPayload()),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Draft saved!', 'success');
                picDirty = false;
                picMandaysUpdateSidebarBadge(data.ticket_mandays_status);
                picDraftData = data.data;
                document.getElementById('picMandaysVersion').textContent     = picDraftData?.version ?? '—';
                document.getElementById('picMandaysStatusLabel').textContent = 'Draft';
            } else {
                showNotification(data.message || 'Failed to save', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Save Draft'; }
    }

    function picStartNewVersion() {
        if (picDirty) { showNotification('Please save the draft before starting a new version', 'warning'); return; }
        const valueMap = {};
        if (picDraftData?.details?.length) {
            picDraftData.details.forEach(d => {
                const act = d.activity || '';
                if (!act) return;
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
        }
        picDraftData = null;
        picReadOnly = false;
        picDirty = false;
        document.getElementById('picMandaysVersion').textContent = 'New';
        document.getElementById('picMandaysStatusLabel').textContent = 'Draft';
        const descInput  = document.getElementById('picMandaysDescription');
        const notesInput = document.getElementById('picMandaysNotes');
        if (descInput)  { descInput.value = '';  descInput.removeAttribute('readonly');  descInput.classList.remove('bg-gray-50','cursor-not-allowed'); }
        if (notesInput) { notesInput.value = ''; notesInput.removeAttribute('readonly'); notesInput.classList.remove('bg-gray-50','cursor-not-allowed'); }
        document.getElementById('picRejectionInfo').classList.add('hidden');
        document.getElementById('picBtnNewVersion').classList.add('hidden');
        picRenderMatrix(valueMap);
    }

    async function picSubmitDraft() {
        if (picDirty) { showNotification('Please save draft before submitting', 'warning'); return; }
        if (!picDraftData) { showNotification('Save draft first', 'warning'); return; }
        const btn = document.getElementById('picBtnSubmit');
        btn.disabled = true; btn.textContent = 'Submitting...';
        try {
            const res = await fetch(MANDAYS_API('pic-draft/submit'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Proposal submitted to Helpdesk!', 'success');
                picMandaysUpdateSidebarBadge(data.ticket_mandays_status);
                closePicMandaysModal();
            } else {
                showNotification(data.message || 'Failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Submit to Helpdesk'; }
    }

    function picMandaysUpdateSidebarBadge(status) {
        const badges = {
            'none':             ['bg-gray-100 text-gray-500',   'None'],
            'pic_draft':        ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_helpdesk': ['bg-blue-100 text-blue-700',   'Pending Review'],
            'sent_to_chat':     ['bg-purple-100 text-purple-700','Sent to Chat'],
            'approved':         ['bg-green-100 text-green-700', 'Approved'],
            'canceled':         ['bg-red-100 text-red-700',     'Canceled'],
        };
        const el = document.getElementById('mandaysBadge');
        if (el && badges[status]) {
            el.className = `inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${badges[status][0]}`;
            el.textContent = badges[status][1];
        }
    }


    // ==================== PIC: INTERNAL MANDAYS ====================
    async function openInternalMandaysModal() {
        document.getElementById('picInternalModal').classList.remove('hidden');
        document.getElementById('picInternalModal').classList.add('flex');
        await internalPicLoad();
    }
    function closePicInternalModal() {
        document.getElementById('picInternalModal').classList.add('hidden');
        document.getElementById('picInternalModal').classList.remove('flex');
    }

    async function internalPicLoad() {
        document.getElementById('internalLoading').classList.remove('hidden');
        document.getElementById('internalTable').classList.add('hidden');
        document.getElementById('internalRejectionInfo').classList.add('hidden');

        try {
            const res    = await fetch(MANDAYS_API('internal'), { headers: getHeaders(), credentials: 'same-origin' });
            const data   = await res.json();
            internalPicData    = data.data;
            internalPicPeople  = data.people || [];
            const status       = data.internal_mandays_status || 'none';

            internalPicReadOnly = false; // consultant can always edit

            document.getElementById('internalNotes').value = internalPicData?.notes || '';
            document.getElementById('internalNotes').readOnly = false;
            document.getElementById('internalNotes').classList.remove('bg-gray-50');

            // Show info banner based on status
            const infoEl = document.getElementById('internalRejectionInfo');
            if (status === 'approved') {
                infoEl.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
                infoEl.innerHTML = '<p class="font-semibold mb-1">Proposal Approved by Head of Support</p>'
                    + (internalPicData?.approved_by_head ? '<p>Approved by: ' + internalPicData.approved_by_head + '</p>' : '')
                    + '<p class="mt-1 text-green-600">You can still update the mandays and re-submit to Head of Support.</p>';
                infoEl.classList.remove('hidden');
            } else if (status === 'pending_head') {
                infoEl.className = 'mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-600';
                infoEl.innerHTML = '<p class="font-semibold">Submitted — awaiting Head of Support review. You can still update and re-submit.</p>';
                infoEl.classList.remove('hidden');
            } else if (status === 'rejected' && internalPicData?.rejection_reason) {
                infoEl.className = 'mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
                infoEl.innerHTML = '<p class="font-semibold mb-1">Revision Required by Head of Support</p>'
                    + '<p>' + internalPicData.rejection_reason + '</p>';
                infoEl.classList.remove('hidden');
            }

            // Build valueMap from existing details only — start from 0 if none
            const valueMap = {};
            (internalPicData?.details || []).forEach(d => {
                valueMap[d.employee_id] = {
                    mandays:             (valueMap[d.employee_id]?.mandays || 0) + d.mandays,
                    additional_mandays:  (valueMap[d.employee_id]?.additional_mandays || 0) + (d.additional_mandays || 0),
                    approved_additional: (valueMap[d.employee_id]?.approved_additional || 0) + (d.approved_additional || 0),
                    notes:               d.notes || valueMap[d.employee_id]?.notes || '',
                };
            });

            internalPicRenderRows(valueMap);
        } catch(e) {
            console.error(e);
            showNotification('Failed to load internal mandays', 'error');
        } finally {
            document.getElementById('internalLoading').classList.add('hidden');
        }
    }

    function internalPicRenderRows(valueMap) {
        let html = '';
        internalPicPeople.forEach(person => {
            const existing = valueMap[person.employee_id] || {};
            const md  = existing.mandays || 0;
            const add = existing.additional_mandays || 0;
            const appAdd = existing.approved_additional || 0;
            const totalMd = md + appAdd;
            const mdVal  = md  > 0 ? md  : '';
            const addVal = add > 0 ? add : '';
            const apprAddDisplay = appAdd > 0 ? appAdd.toFixed(1) : '—';
            html += `<tr>
                <td class="px-3 py-2 border border-gray-200 font-medium text-gray-700">${person.name}</td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="internal-md-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-teal-50 bg-white"
                        data-employee="${person.employee_id}" value="${mdVal}"
                        oninput="internalUpdateRowTotal(this)">
                </td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="internal-add-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-teal-50 bg-white"
                        data-employee="${person.employee_id}" value="${addVal}"
                        oninput="internalUpdateRowTotal(this)">
                </td>
                <td class="border border-gray-200 p-0">
                    <input type="text"
                        class="internal-note-cell w-full px-2 py-1.5 text-xs focus:outline-none focus:bg-teal-50 bg-white"
                        data-employee="${person.employee_id}" value="${existing.notes || ''}"
                        placeholder="notes...">
                </td>
                <td class="px-2 py-1.5 border border-gray-200 text-xs text-center bg-gray-50 text-gray-500" data-emp-appr="${person.employee_id}">${apprAddDisplay}</td>
                <td class="px-2 py-1.5 border border-gray-200 text-xs text-center font-semibold bg-gray-50" data-emp-total="${person.employee_id}">${totalMd > 0 ? totalMd.toFixed(1) : '—'}</td>
            </tr>`;
        });
        document.getElementById('internalBody').innerHTML = html;
        document.getElementById('internalTable').classList.remove('hidden');
        internalUpdateTotal();
    }

    function internalUpdateRowTotal(inp) {
        const row = inp.closest('tr');
        const mdVal  = parseFloat(row.querySelector('.internal-md-cell')?.value)  || 0;
        const addVal = parseFloat(row.querySelector('.internal-add-cell')?.value) || 0;
        // For PIC view, approved_additional comes from existing data (not editable here)
        const empId = inp.dataset.employee;
        const existingApproved = (internalPicData?.details || []).find(d => d.employee_id == empId)?.approved_additional || 0;
        const totalMd = mdVal + existingApproved;
        const totalCell = row.querySelector(`[data-emp-total="${empId}"]`);
        if (totalCell) totalCell.textContent = totalMd > 0 ? totalMd.toFixed(1) : '—';
        internalUpdateTotal();
    }

    function internalUpdateTotal() {
        let total = 0;
        document.querySelectorAll('[data-emp-total]').forEach(cell => {
            const v = parseFloat(cell.textContent) || 0;
            total += v;
        });
        document.getElementById('internalTotalDisplay').textContent = total.toFixed(1);
        const footer = document.getElementById('internalFooterTotal');
        if (footer) footer.textContent = total.toFixed(1);
    }

    function internalPicGetPayload() {
        const details = [];
        document.querySelectorAll('.internal-md-cell').forEach(inp => {
            const row   = inp.closest('tr');
            const empId = parseInt(inp.dataset.employee);
            const md    = parseFloat(inp.value) || 0;
            const add   = parseFloat(row.querySelector('.internal-add-cell')?.value) || 0;
            const notes = row.querySelector('.internal-note-cell')?.value || '';
            if (md > 0 || add > 0) {
                details.push({ employee_id: empId, mandays: md, additional_mandays: add, notes });
            }
        });
        return { details, notes: document.getElementById('internalNotes').value };
    }

    async function internalPicSaveDraft() {
        const btn = document.getElementById('internalBtnSave');
        btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const res = await fetch(MANDAYS_API('internal'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(internalPicGetPayload()),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Internal draft saved!', 'success');
                internalUpdateSidebarBadge(data.internal_mandays_status);
                internalPicData = data.data;
            } else {
                showNotification(data.message || 'Failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Save Draft'; }
    }

    async function internalPicSubmit() {
        // Save first then submit
        const btn = document.getElementById('internalBtnSubmit');
        btn.disabled = true; btn.textContent = 'Submitting...';
        try {
            // Save
            const saveRes = await fetch(MANDAYS_API('internal'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(internalPicGetPayload()),
            });
            const saveData = await saveRes.json();
            if (!saveData.success) { showNotification(saveData.message || 'Save failed', 'error'); return; }

            // Submit
            const subRes = await fetch(MANDAYS_API('internal/submit'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
            });
            const subData = await subRes.json();
            if (subData.success) {
                showNotification('Submitted to Head of Support!', 'success');
                internalUpdateSidebarBadge(subData.internal_mandays_status);
                closePicInternalModal();
            } else {
                showNotification(subData.message || 'Submit failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Submit to Head'; }
    }

    function internalUpdateSidebarBadge(status) {
        const badges = {
            'none':        ['bg-gray-100 text-gray-500',   'None'],
            'draft':       ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_head':['bg-blue-100 text-blue-700',   'Pending Head'],
            'approved':    ['bg-green-100 text-green-700', 'Approved'],
            'rejected':    ['bg-red-100 text-red-700',     'Rejected'],
        };
        const el = document.getElementById('internalBadge');
        if (el && badges[status]) {
            el.className = `inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${badges[status][0]}`;
            el.textContent = badges[status][1];
        }
    }


    // ==================== HELPDESK: CUSTOMER MANDAYS REVIEW ====================
    async function openHdMandaysModal() {
        const modal = document.getElementById('hdMandaysModal');
        if (!modal) { console.warn('[hdMandays] modal element not found'); return; }
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('hdMandaysLoading').classList.remove('hidden');
        document.getElementById('hdMandaysContent').classList.add('hidden');
        document.getElementById('hdMandaysBanner').classList.add('hidden');
        document.getElementById('hdCancelConfirmWrap')?.classList.add('hidden');

        try {
            console.log('[hdMandays] fetching modules + hd-draft for ticket', ticketId);
            const [modRes, draftRes] = await Promise.all([
                fetch(MANDAYS_API('modules'), { headers: getHeaders(), credentials: 'same-origin' }),
                fetch(MANDAYS_API('hd-draft'), { headers: getHeaders(), credentials: 'same-origin' }),
            ]);
            console.log('[hdMandays] responses:', modRes.status, draftRes.status);
            if (!modRes.ok || !draftRes.ok) {
                throw new Error(`API error: modules=${modRes.status} hd-draft=${draftRes.status}`);
            }
            const modData   = await modRes.json();
            const draftData = await draftRes.json();
            console.log('[hdMandays] data loaded, status=', draftData.ticket_mandays_status);
            const modules   = modData.data || [];
            const proposal  = draftData.data;
            const status    = draftData.ticket_mandays_status || 'none';

            // Human-readable status label
            const statusLabels = {
                none: 'None', pic_draft: 'PIC Draft', pending_helpdesk: 'Pending Helpdesk',
                sent_to_chat: 'Awaiting Customer Response', approved: 'Approved', canceled: 'Canceled'
            };
            document.getElementById('hdMandaysStatusLabel').textContent = statusLabels[status] || status;

            if (!proposal) {
                document.getElementById('hdMandaysContent').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No proposal found.</p>';
                document.getElementById('hdMandaysContent').classList.remove('hidden');
                return; // finally will still run
            }

            // ---- State-specific banner & UI ----
            const banner = document.getElementById('hdMandaysBanner');
            const rejWrap = document.getElementById('hdRejectionReasonWrap');

            banner.className = 'hidden mb-4 rounded-lg px-4 py-3 text-sm font-medium items-start gap-3';
            rejWrap.classList.add('hidden');

            const isCustomerRejected = status === 'pending_helpdesk' && !!proposal.rejection_reason;
            const isPicSubmitted     = status === 'pending_helpdesk' && !proposal.rejection_reason;
            const isSentToChat       = status === 'sent_to_chat';
            const isApproved         = status === 'approved';
            const isCanceled         = status === 'canceled';

            if (isCanceled) {
                let cancelHtml = `<span class="text-gray-600 text-base mt-0.5">✕</span>
                    <div><p class="font-semibold text-gray-800">Proposal Canceled by Helpdesk</p>`;
                if (proposal.cancel_notes) {
                    cancelHtml += `<p class="text-xs font-normal text-gray-600 mt-0.5">${proposal.cancel_notes.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>`;
                }
                if (proposal.canceled_by_name) {
                    cancelHtml += `<p class="text-xs font-normal text-gray-500 mt-0.5">— ${proposal.canceled_by_name.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>`;
                }
                cancelHtml += '</div>';
                banner.innerHTML = cancelHtml;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-gray-100', 'border', 'border-gray-300', 'text-gray-700');
            } else if (isApproved) {
                const ts = proposal.customer_response_at
                    ? new Date(proposal.customer_response_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12: false }) + ' WIB'
                    : '';
                banner.innerHTML = `<span class="text-green-700 text-base mt-0.5">✓</span>
                    <div><p class="font-semibold text-green-800">Approved by Customer</p>
                    ${ts ? `<p class="text-xs font-normal text-green-700 mt-0.5">${ts}</p>` : ''}</div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-green-50', 'border', 'border-green-200', 'text-green-800');
            } else if (isCustomerRejected) {
                const ts = proposal.customer_response_at
                    ? new Date(proposal.customer_response_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12: false }) + ' WIB'
                    : '';
                banner.innerHTML = `<span class="text-red-700 text-base mt-0.5">✕</span>
                    <div><p class="font-semibold text-red-800">Rejected by Customer</p>
                    ${ts ? `<p class="text-xs font-normal text-red-700 mt-0.5">${ts}</p>` : ''}</div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-red-50', 'border', 'border-red-200', 'text-red-800');
                document.getElementById('hdRejectionReasonText').textContent = proposal.rejection_reason;
                rejWrap.classList.remove('hidden');
            }

            // Build table
            const valueMap = {};
            const activities = [];
            (proposal.details || []).forEach(d => {
                const act = d.activity || 'General';
                if (!activities.includes(act)) activities.push(act);
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
            const mods = modules.length > 0 ? modules : [...new Set((proposal.details||[]).map(d=>d.module))];

            let headHtml = '<tr class="bg-gray-50"><th class="px-2 py-2 text-left text-xs font-semibold border border-gray-200">Activity</th>';
            mods.forEach(m => headHtml += `<th class="px-2 py-2 text-center text-xs font-semibold border border-gray-200">${m}</th>`);
            headHtml += '</tr>';
            document.getElementById('hdMandaysHead').innerHTML = headHtml;

            // Table is editable only when Helpdesk can still make changes
            const isEditable = isPicSubmitted || isCustomerRejected;
            let bodyHtml = '';
            activities.forEach(act => {
                bodyHtml += `<tr><td class="px-2 py-1.5 border border-gray-200 text-xs font-medium">${act}</td>`;
                mods.forEach(m => {
                    const val = valueMap[act]?.[m] || '';
                    bodyHtml += `<td class="border border-gray-200 p-0">
                        <input type="number" min="0" step="0.5" class="hd-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none ${isEditable?'focus:bg-indigo-50 bg-white':'bg-gray-50 cursor-not-allowed'}"
                        data-activity="${act}" data-module="${m}" value="${val}" ${isEditable?'':'readonly'} oninput="hdUpdateTotal()">
                    </td>`;
                });
                bodyHtml += '</tr>';
            });
            document.getElementById('hdMandaysBody').innerHTML = bodyHtml;

            let footHtml = '<tr class="bg-gray-50 font-bold"><td class="px-2 py-1.5 border border-gray-200 text-xs text-right">Total</td>';
            mods.forEach(m => footHtml += `<td id="hdColTotal_${m}" class="px-2 py-1.5 border border-gray-200 text-xs text-center">0</td>`);
            footHtml += '</tr>';
            document.getElementById('hdMandaysFoot').innerHTML = footHtml;

            // Show/hide buttons per state
            ['hdBtnSendToChat','hdBtnReviseResend','hdBtnApprove','hdBtnCancel','hdBtnNewProposal'].forEach(id => {
                document.getElementById(id)?.classList.add('hidden');
            });
            if (isPicSubmitted) {
                document.getElementById('hdBtnSendToChat')?.classList.remove('hidden');
                document.getElementById('hdBtnApprove')?.classList.remove('hidden');
                document.getElementById('hdBtnCancel')?.classList.remove('hidden');
            } else if (isCustomerRejected) {
                document.getElementById('hdBtnReviseResend')?.classList.remove('hidden');
                document.getElementById('hdBtnCancel')?.classList.remove('hidden');
            } else if (isSentToChat) {
                // Helpdesk approve setelah baca chat dari customer
                document.getElementById('hdBtnApprove')?.classList.remove('hidden');
                document.getElementById('hdBtnCancel')?.classList.remove('hidden');
            } else if (isCanceled) {
                document.getElementById('hdBtnNewProposal')?.classList.remove('hidden');
            }
            // approved: no buttons

            document.getElementById('hdMandaysContent').classList.remove('hidden');
            hdUpdateTotal();
        } catch(e) {
            console.error('[hdMandays] error:', e);
            showNotification('Failed to load proposal: ' + e.message, 'error');
        } finally {
            console.log('[hdMandays] finally: hiding loading');
            const loadingEl = document.getElementById('hdMandaysLoading');
            if (loadingEl) loadingEl.classList.add('hidden');
        }
    }

    function closeHdMandaysModal() {
        const modal = document.getElementById('hdMandaysModal');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        // Reset cancel confirm section
        document.getElementById('hdCancelConfirmWrap')?.classList.add('hidden');
        const hdCancelNotes = document.getElementById('hdCancelNotes');
        if (hdCancelNotes) hdCancelNotes.value = '';
    }

    function hdUpdateTotal() {
        let grand = 0;
        const colTotals = {};
        document.querySelectorAll('.hd-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            colTotals[inp.dataset.module] = (colTotals[inp.dataset.module] || 0) + v;
            grand += v;
        });
        Object.entries(colTotals).forEach(([m, t]) => {
            const el = document.getElementById(`hdColTotal_${m}`);
            if (el) el.textContent = t.toFixed(1);
        });
        const totalEl = document.getElementById('hdTotalDisplay');
        if (totalEl) totalEl.textContent = grand.toFixed(1);
    }

    async function hdSaveAndAction(endpoint, method = 'POST', extraBody = {}) {
        // Save edits first (only if cells exist and are editable)
        const details = [];
        document.querySelectorAll('.hd-cell:not([readonly])').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            if (v > 0) details.push({ activity: inp.dataset.activity, module: inp.dataset.module, mandays: v });
        });
        if (details.length > 0) {
            await fetch(MANDAYS_API('hd-draft'), {
                method: 'PUT', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify({ details }),
            });
        }
        const res = await fetch(MANDAYS_API(endpoint), {
            method, headers: getHeaders(), credentials: 'same-origin',
            body: JSON.stringify(extraBody),
        });
        return res.json();
    }

    async function hdSubmitToChat() {
        const btn = document.getElementById('hdBtnSendToChat');
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
        try {
            const data = await hdSaveAndAction('hd-draft/submit-chat');
            if (data.success) {
                if (data.email_warning) {
                    showNotification('Status updated. Warning: ' + data.email_warning, 'warning');
                } else {
                    showNotification(data.message || 'Sent to customer via email!', 'success');
                }
                closeHdMandaysModal();
            } else {
                showNotification(data.message || 'Failed to send.', 'error');
            }
        } catch(e) {
            showNotification('Error: ' + e.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Send to Customer'; }
        }
    }
    async function hdReviseResend() {
        const btn = document.getElementById('hdBtnReviseResend');
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
        try {
            const data = await hdSaveAndAction('hd-draft/submit-chat');
            if (data.success) {
                if (data.email_warning) {
                    showNotification('Status updated. Warning: ' + data.email_warning, 'warning');
                } else {
                    showNotification(data.message || 'Revised proposal sent to customer!', 'success');
                }
                closeHdMandaysModal();
            } else {
                showNotification(data.message || 'Failed to send.', 'error');
            }
        } catch(e) {
            showNotification('Error: ' + e.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Revise & Resend'; }
        }
    }
    async function hdApprove() {
        try {
            const data = await hdSaveAndAction('hd-draft/approve');
            if (data.success) {
                showNotification('Customer mandays approved!', 'success');
                closeHdMandaysModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
    }
    function hdShowCancelConfirm() {
        // Hide action buttons and show cancel confirmation form
        ['hdBtnSendToChat','hdBtnReviseResend','hdBtnApprove','hdBtnCancel','hdBtnNewProposal'].forEach(id => {
            document.getElementById(id)?.classList.add('hidden');
        });
        document.getElementById('hdCancelConfirmWrap').classList.remove('hidden');
        document.getElementById('hdCancelNotes').value = '';
        document.getElementById('hdCancelNotes').focus();
    }
    function hdCancelAbort() {
        document.getElementById('hdCancelConfirmWrap').classList.add('hidden');
        // Restore buttons by re-running the open modal state (reload)
        openHdMandaysModal();
    }
    async function hdCancelConfirm() {
        const cancelNotes = document.getElementById('hdCancelNotes').value.trim();
        const confirmBtn  = document.querySelector('#hdCancelConfirmWrap button:last-child');
        if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Canceling...'; }
        try {
            const res = await fetch(MANDAYS_API('hd-draft/cancel'), {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ cancel_notes: cancelNotes }),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Proposal canceled.', 'success');
                closeHdMandaysModal();
            } else {
                showNotification(data.message || 'Failed to cancel.', 'error');
                if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Confirm Cancel'; }
            }
        } catch(e) {
            showNotification('Error: ' + e.message, 'error');
            if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Confirm Cancel'; }
        }
    }
    async function hdCreateNewProposal() {
        showNotification('Ask the PIC to submit a new draft.', 'info');
        closeHdMandaysModal();
    }


    // ==================== HEAD OF SUPPORT: INTERNAL MANDAYS ====================
    async function openHeadInternalModal() {
        const modal = document.getElementById('headInternalModal');
        if (!modal) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('headInternalLoading').classList.remove('hidden');
        document.getElementById('headInternalContent').classList.add('hidden');
        document.getElementById('headInternalStatusBanner').classList.add('hidden');

        try {
            const res  = await fetch(MANDAYS_API('internal'), { headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            const proposal = data.data;
            const status   = data.internal_mandays_status || 'none';

            const headStatusLabels = {
                'none':         'None',
                'draft':        'Draft',
                'pending_head': 'Pending Review',
                'approved':     'Approved',
                'rejected':     'Needs Revision',
            };
            document.getElementById('headInternalStatusLabel').textContent = headStatusLabels[status] || status;

            if (!proposal) {
                document.getElementById('headInternalContent').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No proposal submitted yet.</p>';
                document.getElementById('headInternalContent').classList.remove('hidden');
                document.getElementById('headBtnApprove').classList.add('hidden');
                return;
            }

            // Build per-employee map (sum across modules)
            const empMap = {};
            (proposal.details || []).forEach(d => {
                const eid = d.employee_id;
                if (!empMap[eid]) empMap[eid] = { name: d.employee_name || '—', mandays: 0, additional_mandays: 0, approved_additional: 0, notes: '' };
                empMap[eid].mandays            += parseFloat(d.mandays || 0);
                empMap[eid].additional_mandays += parseFloat(d.additional_mandays || 0);
                empMap[eid].approved_additional+= parseFloat(d.approved_additional || 0);
                if (d.notes) empMap[eid].notes = d.notes;
            });

            // Additional MD always editable by head of support
            let bodyHtml = '';
            let grandTotal = 0;
            Object.entries(empMap).forEach(([eid, emp]) => {
                const currentApprAdd = emp.approved_additional;
                const rowTotal = emp.mandays + currentApprAdd;
                grandTotal += rowTotal;
                bodyHtml += `<tr>
                    <td class="px-3 py-2 border border-gray-200 text-xs font-medium">${emp.name}</td>
                    <td class="px-3 py-2 border border-gray-200 text-xs text-center">${emp.mandays > 0 ? emp.mandays.toFixed(1) : '—'}</td>
                    <td class="px-3 py-2 border border-gray-200 text-xs text-center">${emp.additional_mandays > 0 ? emp.additional_mandays.toFixed(1) : '—'}</td>
                    <td class="px-3 py-2 border border-gray-200 text-xs text-gray-500">${emp.notes || ''}</td>
                    <td class="border border-gray-200 p-0">
                        <input type="number" min="0" step="0.5"
                            class="head-approve-add w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-teal-50 bg-white"
                            data-employee="${eid}" data-mandays="${emp.mandays}"
                            value="${currentApprAdd > 0 ? currentApprAdd : ''}"
                            oninput="headUpdateRowTotal(this)">
                    </td>
                    <td class="px-2 py-1.5 border border-gray-200 text-xs text-center font-semibold bg-gray-50" data-head-total="${eid}">${rowTotal > 0 ? rowTotal.toFixed(1) : '—'}</td>
                </tr>`;
            });
            document.getElementById('headInternalBody').innerHTML = bodyHtml;
            document.getElementById('headInternalTotal').textContent = grandTotal.toFixed(1);

            if (proposal.proposed_by) {
                document.getElementById('headProposedBy').textContent = 'Proposed by: ' + proposal.proposed_by;
            }
            if (proposal.notes) {
                const nw = document.getElementById('headInternalNoteWrap');
                nw.textContent = 'Notes: ' + proposal.notes;
                nw.classList.remove('hidden');
            }

            // Status info banner
            const bannerEl = document.getElementById('headInternalStatusBanner');
            if (status === 'approved') {
                bannerEl.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
                bannerEl.innerHTML = '<p class="font-semibold">Saved — Proposal Approved</p>'
                    + (proposal.approved_by_head ? '<p class="text-xs mt-0.5">Approved by: ' + proposal.approved_by_head + '</p>' : '');
                bannerEl.classList.remove('hidden');
            } else if (status === 'draft') {
                bannerEl.className = 'mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700';
                bannerEl.innerHTML = '<p class="font-semibold">Draft — PIC has not submitted yet.</p>';
                bannerEl.classList.remove('hidden');
            } else if (status === 'rejected') {
                bannerEl.className = 'mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg text-sm text-orange-700';
                bannerEl.innerHTML = '<p class="font-semibold">Needs Revision — PIC is revising.</p>';
                bannerEl.classList.remove('hidden');
            }

            // Always show Save button when proposal exists (editable at any status)
            document.getElementById('headBtnApprove').classList.remove('hidden');

            document.getElementById('headInternalContent').classList.remove('hidden');
        } catch(e) {
            console.error(e);
            showNotification('Failed to load internal proposal', 'error');
        } finally {
            document.getElementById('headInternalLoading').classList.add('hidden');
        }
    }

    function closeHeadInternalModal() {
        const modal = document.getElementById('headInternalModal');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    }

    function headUpdateRowTotal(inp) {
        const row      = inp.closest('tr');
        const md       = parseFloat(inp.dataset.mandays) || 0;
        const apprAdd  = parseFloat(inp.value) || 0;
        const total    = md + apprAdd;
        const empId    = inp.dataset.employee;
        const cell     = row.querySelector(`[data-head-total="${empId}"]`);
        if (cell) cell.textContent = total > 0 ? total.toFixed(1) : '—';
        // Update grand total
        let grand = 0;
        document.querySelectorAll('[data-head-total]').forEach(c => grand += parseFloat(c.textContent) || 0);
        document.getElementById('headInternalTotal').textContent = grand.toFixed(1);
    }

    async function headInternalApprove() {
        const btn = document.getElementById('headBtnApprove');
        btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const approvedDetails = [];
            document.querySelectorAll('.head-approve-add').forEach(inp => {
                approvedDetails.push({
                    employee_id:         parseInt(inp.dataset.employee),
                    approved_additional: parseFloat(inp.value) || 0,
                });
            });

            const res  = await fetch(MANDAYS_API('internal/approve'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify({ approved_details: approvedDetails }),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Internal mandays saved!', 'success');
                internalUpdateSidebarBadge?.(data.internal_mandays_status);
                closeHeadInternalModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
        finally { btn.disabled = false; btn.textContent = 'Save'; }
    }

    // ==================== HEAD OF SUPPORT: CUSTOMER MANDAYS VIEW ====================
    async function openHeadCustomerMandaysModal() {
        const modal = document.getElementById('headCustomerMandaysModal');
        if (!modal) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('headCustMandaysLoading').classList.remove('hidden');
        document.getElementById('headCustMandaysContent').classList.add('hidden');

        try {
            const res  = await fetch(MANDAYS_API('hd-draft'), { headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            const proposal = data.data;
            const status   = data.ticket_mandays_status || 'none';

            const statusLabels = {
                'none': 'None', 'pic_draft': 'Draft', 'pending_helpdesk': 'Pending Review',
                'sent_to_chat': 'Sent to Customer', 'approved': 'Approved', 'canceled': 'Canceled',
            };
            document.getElementById('headCustMandaysStatus').textContent = statusLabels[status] || status;

            if (!proposal || !proposal.details || proposal.details.length === 0) {
                document.getElementById('headCustMandaysEmpty').classList.remove('hidden');
                document.getElementById('headCustMandaysTable').classList.add('hidden');
            } else {
                let bodyHtml = '';
                let total = 0;
                (proposal.details || []).forEach(d => {
                    total += parseFloat(d.mandays || 0);
                    bodyHtml += `<tr>
                        <td class="px-3 py-2 border border-gray-200 text-xs">${d.activity || '—'}</td>
                        <td class="px-3 py-2 border border-gray-200 text-xs">${d.module || '—'}</td>
                        <td class="px-3 py-2 border border-gray-200 text-xs text-center font-semibold">${parseFloat(d.mandays || 0).toFixed(1)}</td>
                    </tr>`;
                });
                document.getElementById('headCustMandaysBody').innerHTML = bodyHtml;
                document.getElementById('headCustMandaysTotal').textContent = total.toFixed(1);

                if (proposal.notes) {
                    const nw = document.getElementById('headCustMandaysNotes');
                    nw.textContent = 'Notes: ' + proposal.notes;
                    nw.classList.remove('hidden');
                }
                document.getElementById('headCustMandaysTable').classList.remove('hidden');
            }
            document.getElementById('headCustMandaysContent').classList.remove('hidden');
        } catch(e) {
            console.error(e);
            showNotification('Failed to load customer mandays', 'error');
        } finally {
            document.getElementById('headCustMandaysLoading').classList.add('hidden');
        }
    }

    function closeHeadCustomerMandaysModal() {
        const modal = document.getElementById('headCustomerMandaysModal');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    }


    // ==================== CUSTOMER CREDENTIAL MODAL ====================
    @if($canViewCredential ?? false)
    const _credentialCustomerId = {{ $ticket->customer_id ?? 'null' }};

    async function openCredentialModal() {
        if (!_credentialCustomerId) return;

        document.getElementById('credentialModalContent').innerHTML =
            '<div class="flex items-center justify-center py-10">' +
            '<svg class="animate-spin h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
            '</svg></div>';

        document.getElementById('credentialModal').classList.remove('hidden');
        document.getElementById('credentialModal').classList.add('flex');

        try {
            const res  = await fetch(`/api/customers/${_credentialCustomerId}/credential`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();

            const area = document.getElementById('credentialModalContent');
            if (data.success && data.credential && data.credential.notes) {
                const escaped = data.credential.notes
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/(https?:\/\/[^\s]+)/g,
                        '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline break-all">$1</a>');
                area.innerHTML = `<div class="whitespace-pre-wrap text-sm text-gray-800 leading-relaxed">${escaped}</div>`;
                if (data.credential.updated_by) {
                    const d = data.credential.updated_at ? new Date(data.credential.updated_at).toLocaleString('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : '';
                    area.innerHTML += `<p class="mt-4 text-xs text-gray-400">Last saved by ${data.credential.updated_by}${d ? ' — ' + d : ''}</p>`;
                }
            } else {
                area.innerHTML = '<p class="text-sm text-gray-400 italic py-4 text-center">No credential recorded</p>';
            }
        } catch (err) {
            document.getElementById('credentialModalContent').innerHTML =
                '<p class="text-sm text-red-600 py-4 text-center">Failed to load credential</p>';
        }
    }

    function closeCredentialModal() {
        document.getElementById('credentialModal').classList.add('hidden');
        document.getElementById('credentialModal').classList.remove('flex');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('credentialModal');
            if (modal && !modal.classList.contains('hidden')) closeCredentialModal();
        }
    });
    @endif
</script>

@if($canViewCredential ?? false)
{{-- Customer Credential Modal --}}
<div id="credentialModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <h3 class="text-base font-bold text-gray-900">Customer Credential — {{ $ticket->customer?->basicData?->name_1 ?? 'Unknown Customer' }}</h3>
            </div>
            <button onclick="closeCredentialModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="credentialModalContent" class="px-6 py-5 max-h-96 overflow-y-auto"></div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
            <button onclick="closeCredentialModal()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                Close
            </button>
        </div>
    </div>
    {{-- Click outside to close --}}
    <div class="absolute inset-0 -z-10" onclick="closeCredentialModal()"></div>
</div>
@endif

@endsection
