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
@php
    $managerLabel = $deliverySupport->support_manager_name ?: '<span class="italic opacity-50">Unassigned</span>';
    $adminLabel   = $deliverySupport->support_admin_name   ?: '<span class="italic opacity-50">Unassigned</span>';
    $hasAny = $deliverySupport->support_manager_name || $deliverySupport->support_admin_name;
@endphp
<span class="inline-flex items-center gap-1 ml-1 px-2 py-0.5 rounded-md text-xs font-semibold align-middle {{ $hasAny ? 'bg-gray-100 text-gray-600' : 'bg-yellow-50 text-yellow-600 border border-yellow-200' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
    {!! $managerLabel !!} / {!! $adminLabel !!}
</span>
@endif
@endsection

@section('page-actions')
{{-- Folder ticket diturunkan dari folder Customer Deliverable milik delivery support yang di-assign.
     Link "Open Folder" muncul otomatis setelah deliverable pertama di-upload. --}}
@if($ticket->onedrive_folder_url)
<a id="ticketFolderBtn" href="{{ $ticket->onedrive_folder_url }}" target="_blank" rel="noopener"
   class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
    Open Folder
</a>
@endif
@endsection

{{-- Override sidebar with ticket inbox --}}
@section('sidebar-nav')
{{-- Resize handle — draggable right edge --}}
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
    @if($can('ui.ticket.sidebar-tabs'))
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
@php $customDdVer = file_exists(public_path('js/custom-dropdown.js')) ? filemtime(public_path('js/custom-dropdown.js')) : time(); @endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

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
                            'open'                    => 'bg-blue-100 text-blue-700',
                            'inprocess'               => 'bg-yellow-100 text-yellow-700',
                            'waiting_on_customer'     => 'bg-amber-100 text-amber-700',
                            'waiting_on_3rd_party'    => 'bg-indigo-100 text-indigo-700',
                            'waiting_to_confirmation' => 'bg-teal-100 text-teal-700',
                            'hold'                    => 'bg-orange-100 text-orange-700',
                            'cancelled'               => 'bg-gray-100 text-gray-500',
                            'closed'                  => 'bg-green-100 text-green-700',
                        ];
                        $statusLabels = [
                            'open'                    => 'Open',
                            'inprocess'               => 'Inprocess',
                            'waiting_on_customer'     => 'Waiting on Customer',
                            'waiting_on_3rd_party'    => 'Waiting on 3rd Party',
                            'waiting_to_confirmation' => 'Waiting to Confirmation',
                            'hold'                    => 'Hold',
                            'cancelled'               => 'Cancelled',
                            'closed'                  => 'Closed',
                        ];
                    @endphp
                    <span id="ticketStatusBadge" class="inline-block px-2.5 py-0.5 rounded-md text-xs font-semibold {{ $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$ticket->status] ?? ucfirst($ticket->status) }}
                    </span>
                    @if($ticket->ticket_type)
                    @php
                        $typeColors = [
                            'Incident'        => 'bg-red-100 text-red-700',
                            'Change Request'  => 'bg-amber-100 text-amber-700',
                            'Service Request' => 'bg-indigo-100 text-indigo-700',
                            'EWA'             => 'bg-orange-100 text-orange-700',
                            'RISE'            => 'bg-violet-100 text-violet-700',
                            'Consult'         => 'bg-teal-100 text-teal-700',
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
                    @if($ticket->ticketLead)
                        <span class="text-gray-300">|</span>
                        <span>Ticket Lead: {{ $ticket->ticketLead->basicData ? trim($ticket->ticketLead->basicData->first_name . ' ' . ($ticket->ticketLead->basicData->last_name ?? '')) : 'Assigned' }}</span>
                    @endif
                </div>
            </div>
            @php
                $canViewCredential = $can('ticket.view-credential')
                    || $ticket->ticket_lead_id == $user->id
                    || $ticket->members->contains('employee_id', $user->id);
            @endphp
            @if($canViewCredential && $ticket->customer_id)
            <button onclick="openCredentialModal()"
                title="Customer Credential"
                class="ml-4 flex-shrink-0 h-9 px-3 flex items-center justify-center rounded-lg border border-gray-300 text-gray-500 text-xs font-semibold hover:bg-gray-50 hover:text-gray-700 transition-all">
                Credential
            </button>
            @endif
            {{-- Toggle right panel --}}
            <button id="toggleRightPanelBtn" onclick="toggleRightPanel()" title="Toggle Properties Panel"
                class="ml-2 flex-shrink-0 w-9 h-9 hidden xl:flex items-center justify-center rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all">
                <svg id="rightPanelIconCollapse" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <svg id="rightPanelIconExpand" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
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

            {{-- Toggle strip: channel indicator + collapse button --}}
            <div class="flex items-center pr-3">
                <div class="flex-1">
                    @if($ticket->channel === 'email' || $ticket->email_thread_id)
                    <div class="px-4 pt-2 pb-0.5 flex items-center gap-1.5 text-xs text-blue-700">
                        <i class="fas fa-envelope text-[10px]"></i>
                        <span>Replies will be sent to customer via <strong>Email</strong></span>
                    </div>
                    @elseif($ticket->channel === 'imported')
                    {{-- Imported ticket: offer option to start email thread --}}
                    <div class="px-4 pt-2 pb-0.5 flex items-center gap-2">
                        <i class="fas fa-comment text-[10px] text-gray-400"></i>
                        <span class="text-xs text-gray-400 flex-1">Replies saved internally — no email will be sent to customer</span>
                        <button onclick="showEmailInitMode()" id="btnStartEmailThread"
                            class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium hover:underline transition-colors">
                            <i class="fas fa-envelope text-[10px]"></i> Start Email to Customer
                        </button>
                    </div>
                    @else
                    {{-- Ticket web biasa (bukan import): tidak ada opsi email --}}
                    <div class="px-4 pt-2 pb-0.5 flex items-center gap-1.5 text-xs text-gray-400">
                        <i class="fas fa-comment text-[10px]"></i>
                        <span>Replies saved internally — no email will be sent to customer</span>
                    </div>
                    @endif
                </div>
                <button onclick="toggleReplyBox()" title="Toggle reply box"
                    class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                    <svg id="replyToggleIconDown" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <svg id="replyToggleIconUp" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                </button>
            </div>

            {{-- Collapsible compose content --}}
            {{-- overflow:visible (default expanded) agar dropdown picker Quill ("Normal") tidak ter-clip.
                 Saat collapse via toggleReplyBox(), JS akan switch ke overflow:hidden sementara untuk
                 menyembunyikan konten yang ter-collapse. --}}
            <div id="replyComposeInner" style="max-height:600px;overflow:visible;opacity:1;transition:max-height .2s ease,opacity .2s ease;">

            {{-- To Row: selalu dirender; untuk non-email ticket dikontrol JS (showEmailInitMode/hideEmailInitMode) --}}
            <div class="px-4 pt-1.5" id="toRow" @if(!($ticket->channel === 'email' || $ticket->email_thread_id)) style="display:none" @endif>
                <div class="flex flex-wrap items-center gap-1 min-h-[30px] border border-gray-200 rounded-lg bg-gray-50 px-2 py-1 cursor-text" onclick="document.getElementById('toInput').focus()">
                    <span class="text-[11px] text-gray-500 font-semibold mr-0.5 flex-shrink-0">To</span>
                    <div id="toTagsContainer" class="flex flex-wrap gap-1 items-center"></div>
                    <input type="text" id="toInput"
                           placeholder="Add email and press Enter..."
                           class="text-xs border-none bg-transparent outline-none flex-1 min-w-[150px] placeholder-gray-300 py-0.5"
                           onkeydown="handleToKeydown(event)"
                           onblur="commitToInput()"
                           onpaste="handleToPaste(event)">
                </div>
            </div>

            {{-- CC Row: selalu dirender; untuk non-email ticket dikontrol JS --}}
            <div class="px-4 pt-1.5" id="ccRow" @if(!($ticket->channel === 'email' || $ticket->email_thread_id)) style="display:none" @endif>
                <div class="flex flex-wrap items-center gap-1 min-h-[30px] border border-gray-200 rounded-lg bg-gray-50 px-2 py-1 cursor-text" onclick="document.getElementById('ccInput').focus()">
                    <span class="text-[11px] text-gray-500 font-semibold mr-0.5 flex-shrink-0">CC</span>
                    <div id="ccTagsContainer" class="flex flex-wrap gap-1 items-center"></div>
                    <input type="text" id="ccInput"
                           placeholder="Add email and press Enter..."
                           class="text-xs border-none bg-transparent outline-none flex-1 min-w-[150px] placeholder-gray-300 py-0.5"
                           onkeydown="handleCcKeydown(event)"
                           onblur="commitCcInput()"
                           onpaste="handleCcPaste(event)">
                </div>
            </div>

            {{-- Reply-to context bar (WhatsApp-style) --}}
            <div id="replyContextBar" class="hidden px-4 pt-2">
                <div class="flex items-center gap-2 bg-amber-50 border-l-4 border-amber-400 rounded-r-lg px-3 py-2">
                    <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <span class="text-[10px] font-bold text-amber-700 block" id="replyContextName"></span>
                        <span class="text-xs text-gray-500 truncate block" id="replyContextText"></span>
                    </div>
                    <button onclick="cancelReply()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors" title="Cancel reply">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="px-4 pt-2 pb-2">
                {{-- Mention dropdown (positioned relative to editor wrapper) --}}
                {{-- NOTE: wrapper TIDAK pakai overflow-hidden — itu memotong dropdown picker
                     "Normal" Quill yang muncul ke bawah. Border-radius cukup pakai rounded-lg
                     tanpa overflow clip; konten editor sudah dibatasi oleh .ql-editor overflow. --}}

                <div class="relative">
                    <div class="bg-white border border-gray-300 rounded-lg">
                        <div id="quillEditor" style="min-height: 80px;"></div>
                    </div>
                    {{-- @mention autocomplete dropdown — fixed so it's never clipped by overflow parents --}}
                    <div id="mentionDropdown" class="hidden fixed z-[9999] bg-white border border-gray-200 rounded-xl shadow-xl overflow-y-auto" style="max-height:192px;">
                        <div id="mentionList" class="py-1"></div>
                    </div>
                </div>

                {{-- Attachment Preview Area (toggled via JS: style.display flex/none) --}}
                <div id="attachmentPreview" style="display:none" class="mt-2 flex-wrap gap-2"></div>

                {{-- Hidden file input (button injected into Quill toolbar via JS) --}}
                <input type="file" id="attachInput" multiple class="hidden"
                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,.csv">
                <div class="flex items-center mt-2 mb-1 gap-2">
                    {{-- Internal Note dipindah ke kiri agar tidak bersebelahan dengan Send (mencegah salah klik) --}}
                    <button onclick="sendReply('internal_note')" class="inline-flex items-center px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold rounded-lg hover:bg-amber-100 transition-all duration-200">
                        Internal Note
                    </button>
                    @if($can('ticket.meeting'))
                    <button id="meetingBtn" onclick="openMeetingPanel()"
                        {{ $inMeeting ? 'disabled title=\'Meeting sedang berjalan\'' : '' }}
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all duration-200 {{ $inMeeting ? 'bg-gray-50 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100' }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868v6.264a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        {{ $inMeeting ? 'Meeting Active' : 'Meeting' }}
                    </button>
                    @endif
                    <span id="attachCount" class="hidden text-xs text-blue-600 font-medium ml-2"></span>
                    {{-- Send button dipisah ke pojok kanan --}}
                    @if($ticket->channel === 'email')
                    <button onclick="sendReply('reply')" class="ml-auto inline-flex items-center px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all duration-200">
                        Send via Email
                    </button>
                    @elseif($ticket->email_thread_id)
                    <button onclick="sendReply('reply')" class="ml-auto inline-flex items-center px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all duration-200">
                        Send Reply
                    </button>
                    @elseif($ticket->channel === 'imported')
                    {{-- Imported ticket: Send First Email button (hidden, shown when email init mode is active) --}}
                    <button id="btnSendInitEmail" onclick="doInitiateEmail()"
                        class="ml-auto hidden inline-flex items-center gap-1.5 px-4 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
                        <i class="fas fa-paper-plane text-[10px]"></i> Send First Email
                    </button>
                    <button id="btnCancelInitEmail" onclick="hideEmailInitMode()"
                        class="hidden inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-200 transition-all duration-200">
                        Cancel
                    </button>
                    @else
                    {{-- Ticket web biasa: Send Reply normal --}}
                    <button onclick="sendReply('reply')" class="ml-auto inline-flex items-center px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all duration-200">
                        Send Reply
                    </button>
                    @endif
                </div>
            </div>
            </div>{{-- /replyComposeInner --}}
        </div>
    </div>

    {{-- Right Sidebar --}}
    @php
        $mandaysStatus   = $ticket->mandays_proposal_status   ?? 'none';
        $resolutionStatus  = $ticket->resolution_days_status    ?? 'none';
        $isPic           = $can('ticket.propose-mandays');
        $isHelpdesk      = $can('ticket.review-mandays');
        $isHead          = $can('ticket.head-mandays');
        // Head dan Helpdesk bisa aktif bersamaan (role berbeda boleh punya keduanya).
        // Yang di-override hanya PIC: jika user adalah Head atau Helpdesk, sembunyikan seksi PIC.
        if ($isHead || $isHelpdesk) { $isPic = false; }
        // Computed AFTER override
        $hdCanEditActivity    = $isHelpdesk && $can('ticket.review-mandays.edit-activity');
        $hdCanEditDesc        = $isHelpdesk && $can('ticket.review-mandays.edit-description');
        $hdCanEditNotes       = $isHelpdesk && $can('ticket.review-mandays.edit-proposal-notes');
        $hdCanSaveDraft       = $isHelpdesk && $can('ticket.review-mandays.save-draft');
        $hdCanSendToCustomer  = $isHelpdesk && $can('ticket.review-mandays.send-to-customer');
        $hdCanApprove         = $isHelpdesk && $can('ticket.review-mandays.approve');
        $hdCanCancel          = $isHelpdesk && $can('ticket.review-mandays.cancel');
        $mandaysBadge    = [
            'none'            => ['bg-gray-100 text-gray-500',   'None'],
            'pic_draft'       => ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_helpdesk'=> ['bg-blue-100 text-blue-700',   'Pending Review'],
            'sent_to_chat'    => ['bg-purple-100 text-purple-700','Sent to Chat'],
            'approved'        => ['bg-green-100 text-green-700', 'Approved'],
            'canceled'        => ['bg-red-100 text-red-700',     'Canceled'],
        ];
        $resolutionBadge   = [
            'none'         => ['bg-gray-100 text-gray-500',   'None'],
            'draft'        => ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_head' => ['bg-blue-100 text-blue-700',   'Pending Head'],
            'approved'     => ['bg-green-100 text-green-700', 'Approved'],
            'rejected'     => ['bg-red-100 text-red-700',     'Rejected'],
        ];
        [$mBadgeClass, $mBadgeLabel]  = $mandaysBadge[$mandaysStatus]  ?? ['bg-gray-100 text-gray-500', $mandaysStatus];
        [$iBadgeClass, $iBadgeLabel]   = $resolutionBadge[$resolutionStatus] ?? ['bg-gray-100 text-gray-500', $resolutionStatus];
        $picMandaysLabel = match($mandaysStatus) {
            'none'  => 'Propose Mandays',
            default => 'Update Proposal',
        };
        $picResolutionLabel = match($resolutionStatus) {
            'none'  => 'Propose Resolution Days',
            default => 'Update Resolution Days',
        };
        $ticketAssigned    = $ticket->ticket_lead_id !== null;
        $canTakeTicket     = $can('ticket.take') && !$ticketAssigned;
        $canAssignPic      = $can('ticket.assign-pic');
        $canAssignDelivery = $can('ticket.assign-delivery-support');
        // Mandays buttons only visible when ticket has a PIC
        $isPicMandays      = $isPic && $ticketAssigned;
        $isHelpdeskMandays = $isHelpdesk && $ticketAssigned;
        $isHeadMandays          = $isHead && $ticketAssigned && in_array($resolutionStatus, ['pending_head', 'approved', 'rejected', 'draft']);
        $isHeadCustomerMandays  = $isHead && $ticketAssigned && in_array($mandaysStatus, ['pic_draft', 'pending_helpdesk', 'sent_to_chat', 'approved', 'canceled']);
        $hasMandaysSection = $isPicMandays || $isHelpdeskMandays || $isHeadMandays || $isHeadCustomerMandays
                           || $canTakeTicket || $canAssignPic || $canAssignDelivery;
    @endphp

    <div id="rightSidePanel" class="hidden xl:flex xl:flex-col w-64 gap-3 flex-shrink-0 overflow-y-auto" style="transition: width 0.25s ease, opacity 0.25s ease;">

        {{-- â"€â"€ Mandays Panel â"€â"€ --}}
        @if($hasMandaysSection)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-shrink-0">
            <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                 onclick="toggleSidebarPanel('mandaysPanel', 'mandaysChevron')">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Mandays</h4>
                <i id="mandaysChevron" class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
            </div>
            <div id="mandaysPanel" class="px-4 pb-4 pt-3 space-y-4 border-t border-gray-100">
                {{-- PIC: Customer Mandays & Resolution Days --}}
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
                        <label class="text-xs font-semibold text-gray-500">Resolution Days</label>
                        <span id="resolutionBadge" class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $iBadgeClass }}">{{ $iBadgeLabel }}</span>
                    </div>
                    <button onclick="openResolutionDaysModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        {{ $picResolutionLabel }}
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
                {{-- Delivery Support Head: Customer Mandays (view only) --}}
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
                {{-- Delivery Support Head: Resolution Days --}}
                @if($isHeadMandays)
                <div {{ $isHeadCustomerMandays ? 'class="pt-1 border-t border-gray-100"' : '' }}>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Resolution Days</label>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $iBadgeClass }}">{{ $iBadgeLabel }}</span>
                    </div>
                    <button onclick="openHeadResolutionModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Review Resolution Days
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
                {{-- Assign / Change Ticket Lead (TICKET_MANAGER_GROUP) --}}
                @if($canAssignPic)
                <div>
                    <button onclick="openAssignTicketLeadModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        {{ $ticketAssigned ? 'Change Ticket Lead' : 'Assign Ticket Lead' }}
                    </button>
                </div>
                @endif
                {{-- Assign to Delivery Support --}}
                @if($canAssignDelivery)
                <div class="{{ ($isPicMandays || $isHelpdeskMandays || $isHeadMandays) ? 'pt-1 border-t border-gray-100' : '' }}">
                    <button onclick="openAssignSupportModal()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Assign to Delivery Support
                    </button>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- â"€â"€ Deliverable Panel â"€â"€ --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-shrink-0">
            <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                 onclick="toggleSidebarPanel('deliverablePanel', 'deliverableChevron')">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Deliverable</h4>
                <i id="deliverableChevron" class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
            </div>
            <div id="deliverablePanel" class="px-4 pb-4 pt-1 border-t border-gray-100">
                <button onclick="openDeliverableModal()"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    View Documents
                    <span id="delivBadgeCount" class="hidden ml-1 bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none"></span>
                </button>
            </div>
        </div>

        {{-- â"€â"€ Properties Panel â"€â"€ --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-shrink-0">
            <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                 onclick="toggleSidebarPanel('propertiesPanel', 'propertiesChevron')">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Properties</h4>
                <div class="flex items-center gap-2">
                    @if($can('ui.ticket.edit-fields'))
                    <button onclick="event.stopPropagation(); saveAllProperties()"
                            class="inline-flex items-center px-2.5 py-1 primary-gradient text-white text-[10px] font-semibold rounded-md hover:opacity-90 transition-all duration-200">
                        Save All
                    </button>
                    @endif
                    <i id="propertiesChevron" class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
                </div>
            </div>
            @php
                $canEditProps  = $can('ui.ticket.edit-fields');
                $ddBtnCls      = 'custom-dd-btn w-full flex items-center justify-between gap-1 px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white hover:border-gray-400 transition-all';
                $roValCls      = 'text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 w-full block';
                $statusLabels  = ['open'=>'Open','inprocess'=>'Inprocess','waiting_on_customer'=>'Waiting on Customer','waiting_on_3rd_party'=>'Waiting on 3rd Party','waiting_to_confirmation'=>'Waiting to Confirmation','hold'=>'Hold','cancelled'=>'Cancelled','closed'=>'Closed'];
            @endphp
            <div id="propertiesPanel" class="px-4 pb-4 pt-3 space-y-3 border-t border-gray-100">
                {{-- Status --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Status</label>
                    @if($canEditProps)
                    <div class="custom-dd relative w-full">
                        <button type="button" class="{{ $ddBtnCls }}">
                            <span class="custom-dd-label text-gray-700">{{ $statusLabels[$ticket->status] ?? ucfirst($ticket->status) }}</span>
                            <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="detailStatus" value="{{ $ticket->status }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:190px;">
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="open">Open</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="inprocess">Inprocess</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="waiting_on_customer">Waiting on Customer</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="waiting_on_3rd_party">Waiting on 3rd Party</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="waiting_to_confirmation">Waiting to Confirmation</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="hold">Hold</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="cancelled">Cancelled</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="closed">Closed</button>
                        </div>
                    </div>
                    @else
                    <input type="hidden" id="detailStatus" value="{{ $ticket->status }}">
                    <span class="{{ $roValCls }}">{{ $statusLabels[$ticket->status] ?? ucfirst($ticket->status) }}</span>
                    @endif
                </div>
                {{-- Priority --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Priority</label>
                    @if($canEditProps)
                    <div class="custom-dd relative w-full">
                        <button type="button" class="{{ $ddBtnCls }}">
                            <span class="custom-dd-label text-gray-700">{{ $ticket->ticket_priority ?? '—' }}</span>
                            <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="detailPriority" value="{{ $ticket->ticket_priority }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:200px;min-width:130px;">
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Very High">Very High</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="High">High</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Medium">Medium</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Low">Low</button>
                        </div>
                    </div>
                    @else
                    <input type="hidden" id="detailPriority" value="{{ $ticket->ticket_priority }}">
                    <span class="{{ $roValCls }}">{{ $ticket->ticket_priority ?? '—' }}</span>
                    @endif
                </div>
                {{-- Scale --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Scale</label>
                    @if($canEditProps)
                    <div class="custom-dd relative w-full">
                        <button type="button" class="{{ $ddBtnCls }}">
                            <span class="custom-dd-label text-gray-700">{{ $ticket->scale ?? 'Simple' }}</span>
                            <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="detailScale" value="{{ $ticket->scale ?? 'Simple' }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:200px;min-width:120px;">
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Simple">Simple</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Medium">Medium</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Complex">Complex</button>
                        </div>
                    </div>
                    @else
                    <input type="hidden" id="detailScale" value="{{ $ticket->scale ?? 'Simple' }}">
                    <span class="{{ $roValCls }}">{{ $ticket->scale ?? 'Simple' }}</span>
                    @endif
                </div>
                {{-- Ticket Type --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Ticket Type</label>
                    @if($canEditProps)
                    <div class="custom-dd relative w-full">
                        <button type="button" class="{{ $ddBtnCls }}">
                            <span class="custom-dd-label text-gray-700">{{ $ticket->ticket_type ?? 'Incident' }}</span>
                            <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="detailType" value="{{ $ticket->ticket_type ?? 'Incident' }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:200px;min-width:150px;">
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Incident">Incident</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Change Request">Change Request</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Service Request">Service Request</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="EWA">EWA</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="RISE">RISE</button>
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" data-value="Consult">Consult</button>
                        </div>
                    </div>
                    @else
                    <input type="hidden" id="detailType" value="{{ $ticket->ticket_type ?? 'Incident' }}">
                    <span class="{{ $roValCls }}">{{ $ticket->ticket_type ?? 'Incident' }}</span>
                    @endif
                </div>
                {{-- Ticket Lead --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Ticket Lead</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">
                        @if($ticket->ticketLead && $ticket->ticketLead->basicData)
                            {{ trim($ticket->ticketLead->basicData->first_name . ' ' . ($ticket->ticketLead->basicData->last_name ?? '')) }}
                        @else
                            <span class="text-gray-400 italic">— Unassigned —</span>
                        @endif
                    </p>
                </div>
                {{-- PIC (In Charge) --}}
                @php
                    $canEditPic = $can('ticket.assign-pic')
                        || $ticket->ticket_lead_id == $user->id
                        || $ticket->members->contains('employee_id', $user->id);
                    $picOptions = [];
                    if ($ticket->ticketLead && $ticket->ticketLead->basicData) {
                        $leadName = trim(($ticket->ticketLead->basicData->first_name ?? '') . ' ' . ($ticket->ticketLead->basicData->last_name ?? ''));
                        $picOptions[] = ['name' => $leadName, 'label' => $leadName . ' (Ticket Lead)'];
                    }
                    foreach ($ticket->allMembers as $m) {
                        if ($m->pivot->is_active && $m->basicData) {
                            $mName = trim(($m->basicData->first_name ?? '') . ' ' . ($m->basicData->last_name ?? ''));
                            $picOptions[] = ['name' => $mName, 'label' => $mName];
                        }
                    }
                @endphp
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">PIC (In Charge)</label>
                    @if($canEditPic && count($picOptions) > 0)
                    <div class="custom-dd relative w-full" data-onchange="onPicDropdownChange">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between gap-1 px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white hover:border-gray-400 transition-all">
                            <span class="custom-dd-label text-gray-700 truncate">{{ $ticket->pic ?? 'Helpdesk' }}</span>
                            <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="picSelectHidden" value="{{ $ticket->pic ?? '' }}">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:200px;">
                            @foreach($picOptions as $opt)
                            <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 {{ $ticket->pic === $opt['name'] ? 'bg-gray-50 font-medium text-gray-900' : '' }}" data-value="{{ $opt['name'] }}">{{ $opt['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->pic ?? 'Helpdesk' }}</p>
                    @endif
                </div>
                {{-- Team Members --}}
                @php
                    $canManageMembers = $can('ui.ticket.manage-members') && (
                            !$can('ticket.propose-mandays')               // non-DS-User roles: always OK
                            || $ticket->ticket_lead_id == $user->id       // DS User: only if they are the lead
                        );
                    $allMemberIds = $ticket->allMembers->pluck('employee_id')->toArray();
                @endphp
                <div class="pt-3 border-t border-gray-200">
                    <label class="text-xs font-semibold text-gray-500 mb-2 block">Team Members</label>
                    <div id="membersList" class="space-y-1 mb-2">
                        @forelse($ticket->allMembers as $member)
                            @php
                                $mName    = trim(($member->basicData->first_name ?? '') . ' ' . ($member->basicData->last_name ?? ''));
                                $mActive  = (bool) $member->pivot->is_active;
                            @endphp
                            <div class="member-chip flex items-center justify-between gap-1 px-2.5 py-1.5 rounded-lg {{ $mActive ? 'bg-blue-50' : 'bg-gray-100' }}"
                                 data-id="{{ $member->employee_id }}" data-active="{{ $mActive ? '1' : '0' }}">
                                <span class="text-xs font-medium truncate {{ $mActive ? 'text-blue-700' : 'text-gray-400 line-through' }}">{{ $mName }}</span>
                                @if($canManageMembers)
                                <button type="button"
                                        onclick="toggleMemberBtn({{ $member->employee_id }}, {{ $mActive ? 'true' : 'false' }})"
                                        class="flex-shrink-0 ml-1 transition-colors {{ $mActive ? 'text-blue-300 hover:text-red-500' : 'text-gray-400 hover:text-green-500' }}"
                                        title="{{ $mActive ? 'Nonaktifkan member' : 'Aktifkan kembali member' }}">
                                    <i class="fas {{ $mActive ? 'fa-eye-slash' : 'fa-eye' }} text-[9px]"></i>
                                </button>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic" id="noMembersText">No members assigned.</p>
                        @endforelse
                    </div>
                    @if($canManageMembers)
                    <div class="flex gap-1.5">
                        <div id="addMemberDd" class="custom-dd relative flex-1 min-w-0" data-fixed="true" data-searchable="true">
                            <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white primary-focus text-left">
                                <span class="custom-dd-label text-gray-500 truncate">-- Add member --</span>
                                <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-transform duration-200 flex-shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" id="addMemberSelect" value="">
                            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:320px;">
                                <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="">-- Add member --</button>
                                @foreach($employees as $emp)
                                    @php $empInAll = in_array($emp['employee_id'], $allMemberIds); @endphp
                                    @if(!$empInAll && $emp['employee_id'] != $ticket->ticket_lead_id)
                                        <button type="button" class="custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $emp['employee_id'] }}">{{ $emp['name'] }}</button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <button type="button" id="addMemberBtnEl" onclick="addMemberBtn()"
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
                @if($ticket->end_customer_id)
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">For End Customer</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-blue-50 rounded-lg border border-blue-200">
                        &#8627; {{ $ticket->endCustomer?->basicData?->name_1 ?? 'N/A' }}
                    </p>
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
                @if($can('ticket.delete'))
                <div class="pt-3 border-t border-gray-200">
                    <button onclick="deleteTicket()" class="w-full inline-flex items-center justify-center px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Delete Ticket
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- ── Additional Info Panel ── --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-shrink-0">
            <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                 onclick="toggleSidebarPanel('additionalInfoPanel', 'additionalInfoChevron')">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Additional Info</h4>
                <div class="flex items-center gap-2">
                    @if($canEditProps)
                    <button id="additionalInfoSaveBtn" onclick="event.stopPropagation(); saveAdditionalInfo()"
                            class="inline-flex items-center px-2.5 py-1 primary-gradient text-white text-[10px] font-semibold rounded-md hover:opacity-90 transition-all duration-200">
                        Save
                    </button>
                    @endif
                    <i id="additionalInfoChevron" class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
                </div>
            </div>
            <div id="additionalInfoPanel" class="px-4 pb-4 pt-3 space-y-3 border-t border-gray-100">
                {{-- Contact Name --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Contact Name</label>
                    @if($canEditProps)
                    <input id="additionalInfoName" type="text" value="{{ $ticket->name ?: $ticket->submitted_by_name }}"
                           placeholder="Enter contact name..."
                           class="w-full text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                    @else
                    <span class="{{ $roValCls }}">{{ $ticket->name ?: ($ticket->submitted_by_name ?? '—') }}</span>
                    @endif
                </div>
                {{-- Phone Number --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Phone Number</label>
                    @if($canEditProps)
                    <input id="additionalInfoNoHp" type="text" value="{{ $ticket->no_hp }}"
                           placeholder="Enter phone number..."
                           class="w-full text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                    @else
                    <span class="{{ $roValCls }}">{{ $ticket->no_hp ?? '—' }}</span>
                    @endif
                </div>
                @if($ticket->submitted_by_email)
                {{-- Contact Email (always read-only) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Contact Email</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->submitted_by_email }}</p>
                </div>
                @endif
                {{-- Module --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Module</label>
                    @if($canEditProps)
                    <input id="additionalInfoModule" type="text" value="{{ $ticket->module }}"
                           placeholder="Enter module name..."
                           class="w-full text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                    @else
                    <span class="{{ $roValCls }}">{{ $ticket->module ?? '—' }}</span>
                    @endif
                </div>
                {{-- Client --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Client</label>
                    @if($canEditProps)
                    <input id="additionalInfoClient" type="text" value="{{ $ticket->client }}"
                           placeholder="Enter client name..."
                           class="w-full text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                    @else
                    <span class="{{ $roValCls }}">{{ $ticket->client ?? '—' }}</span>
                    @endif
                </div>
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
/* !important diperlukan karena HTML email dari Outlook/Gmail sering menyertakan
   inline style="width: NNNpx" pada <img> yang akan override CSS biasa. */
.email-html-body img {
    max-width: min(100%, 420px) !important;
    max-height: 320px !important;
    width: auto !important;
    height: auto !important;
    object-fit: contain;
    border-radius: 6px;
    display: block;
    margin: 4px 0;
}
.email-html-body table { border-collapse: collapse; font-size: 12px; max-width: 100%; }
.email-html-body td, .email-html-body th { border: 1px solid #e5e7eb; padding: 4px 8px; }

/* Links di semua bubble (plain text, Quill HTML, internal note) */
.message-content a { color: #2563eb !important; text-decoration: underline !important; word-break: break-all; cursor: pointer; }
.message-content a:hover { color: #1d4ed8 !important; }
.email-html-body a  { color: #2563eb !important; text-decoration: underline !important; }
/* Links di Quill editor saat mengetik */
.ql-editor a { color: #2563eb !important; text-decoration: underline !important; cursor: pointer; }

/* Quill Editor Overrides */
.ql-toolbar.ql-snow {
    border: none !important;
    border-bottom: 1px solid #e5e7eb !important;
    padding: 4px 8px !important;
    background: #f9fafb;
    /* JANGAN override display ke flex — Quill default inline-block lebih kompatibel
       dengan picker SVG-nya. Hanya `display:flex` di .ql-formats yang mendistorsi
       polygon SVG chevron "Normal" jadi terlihat seperti dash "—". */
}
/* Rapatkan jarak antar grup format agar lebih banyak tombol muat dalam 1 baris.
   Default Quill ~15px → 6px. JANGAN ubah display .ql-formats — tetap inline-block
   bawaan Quill agar SVG chevron picker render dengan benar (sebagai panah ▼). */
.ql-toolbar.ql-snow .ql-formats {
    margin-right: 6px !important;
    margin-bottom: 0 !important;
}
.ql-toolbar.ql-snow .ql-formats:last-child { margin-right: 0 !important; }

/* Pastikan dropdown picker tetap tersembunyi sampai user benar-benar klik untuk
   expand — guard terhadap rule lain yang mungkin override display:none Quill. */
.ql-toolbar.ql-snow .ql-picker .ql-picker-options { display: none !important; }
.ql-toolbar.ql-snow .ql-picker.ql-expanded .ql-picker-options {
    display: block !important;
    position: absolute;
    top: 100%;
    z-index: 10;
    background: #fff;
    border: 1px solid #ccc;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Quill link tooltip ("Enter link" / "Edit") — pin ke posisi tetap di tepi kiri
   editor agar label "Link:" tidak terpotong saat Quill kalkulasi left berdasarkan
   cursor yang menghasilkan nilai negatif/kecil. max-width mencegah overflow kanan
   bila URL yang dimasukkan panjang. */
.ql-container .ql-tooltip {
    left: 8px !important;
    max-width: calc(100% - 16px);
    box-sizing: border-box;
    white-space: nowrap;
    z-index: 30;
}
.ql-container .ql-tooltip input[type="text"] {
    min-width: 200px;
}
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
/* Images inside message bubbles — cap both width & height so the bubble doesn't
   grow to fit huge inline screenshots. min(100%, 420px) keeps it responsive on
   narrow viewports while limiting to ~420px on wider screens.
   !important is needed because email HTML from Outlook/Gmail often inlines
   style="width:NNNpx" / "height:NNNpx" on <img> which overrides plain CSS. */
.message-bubble img {
    max-width: min(100%, 420px) !important;
    max-height: 320px !important;
    width: auto !important;
    height: auto !important;
    object-fit: contain;
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

/* Status delivery indicator (WhatsApp-style) — hanya untuk reply helpdesk */
.msg-status-row {
    display: flex; justify-content: flex-end; align-items: center; gap: 8px;
    margin-top: 6px; padding-top: 4px;
    border-top: 1px solid rgba(0,0,0,0.04);
}
.msg-status {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; color: #9ca3af; user-select: none; cursor: default;
    line-height: 1;
}
.msg-status.read { color: #2563eb; font-weight: 600; }
.msg-status .check-pair {
    display: inline-flex; align-items: center; flex-shrink: 0;
}
.msg-status .check-pair svg { width: 12px; height: 12px; }
.msg-status .check-pair svg + svg { margin-left: -7px; }

/* SLA button next to Read/status indicator */
.sla-open-btn {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 600;
    color: #9ca3af;
    background: #f9fafb;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    padding: 2px 6px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
    line-height: 1;
    flex-shrink: 0;
}
.sla-open-btn:hover { color: #6b7280; border-color: #9ca3af; background: #f3f4f6; }
.sla-open-btn.has-sla { color: #16a34a; border-color: #86efac; background: #f0fdf4; }
.sla-open-btn.has-sla:hover { color: #15803d; border-color: #4ade80; background: #dcfce7; }
.sla-open-btn svg { width: 10px; height: 10px; flex-shrink: 0; }

/* Message content */
.message-content p { margin-bottom: 0.25rem; }
.message-content p:last-child { margin-bottom: 0; }
.message-content ul, .message-content ol { padding-left: 1.5rem; margin-bottom: 0.5rem; }
.message-content blockquote { border-left: 3px solid #d1d5db; padding-left: 0.75rem; color: #6b7280; }

/* â"€â"€â"€ Sidebar resize handle hover glow â"€â"€â"€ */
#sidebarResizeHandle:hover,
#sidebarResizeHandle.resizing {
    background: rgba(255,255,255,0.35) !important;
    transition: background 0.15s;
}

/* â"€â"€â"€ Sidebar ticket items â"€â"€â"€ */
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

/* â"€â"€â"€ Ticket card badge pills (bottom row) â"€â"€â"€ */
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
/* Ticket status */
.sb-status-open                    { background:#dbeafe; color:#1d4ed8; }
.sb-status-inprocess               { background:#fef9c3; color:#92400e; }
.sb-status-waiting_on_customer     { background:#fef3c7; color:#b45309; }
.sb-status-waiting_on_3rd_party    { background:#e0e7ff; color:#4338ca; }
.sb-status-waiting_to_confirmation { background:#ccfbf1; color:#0f766e; }
.sb-status-hold                    { background:#ffedd5; color:#c2410c; }
.sb-status-cancelled               { background:#fee2e2; color:#b91c1c; }
.sb-status-closed                  { background:#dcfce7; color:#15803d; }
.sb-status-default                 { background:#f3f4f6; color:#6b7280; }

/* â"€â"€â"€ Internal note reply button (hidden until hover on group) â"€â"€â"€ */
.note-reply-btn {
    transition: opacity 0.15s;
}

/* â"€â"€â"€ Primary theme helpers (mandays modals) â"€â"€â"€ */
.primary-focus:focus {
    outline: none;
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important;
}
.primary-text { color: var(--primary-color) !important; }

/* Hide native calendar/clock icons so our custom SVGs show cleanly */
#meetingStartDate::-webkit-calendar-picker-indicator,
#meetingEndDate::-webkit-calendar-picker-indicator,
#meetingStartHour::-webkit-calendar-picker-indicator,
#meetingEndHour::-webkit-calendar-picker-indicator {
    opacity: 0;
    position: absolute;
    width: 100%;
    height: 100%;
    cursor: pointer;
}
</style>

{{-- Assign to Delivery Support Modal --}}
@if($canAssignDelivery)
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
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Delivery Support</label>
                <select id="deliverySupportSelect" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm primary-focus">
                    <option value="">Loading...</option>
                </select>
                <p id="assignSupportHint" class="mt-1 text-xs text-gray-500">Ticket will be added as an activity under this delivery support.</p>
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
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Customer Mandays Proposal</h3>
                <p class="text-xs text-gray-500 mt-0.5">Version: <span id="picMandaysVersion">—</span> &nbsp;|&nbsp; Status: <span id="picMandaysStatusLabel">—</span></p>
            </div>
            <button onclick="closePicMandaysModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            {{-- Status / cancellation / rejection info banner --}}
            <div id="picRejectionInfo" class="hidden mb-4 p-3 rounded-lg text-sm"></div>

            {{-- Description & Notes fields --}}
            <div id="picDescNotesWrap" class="mb-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Proposal Title <span class="text-red-400">*</span>
                    </label>
                    <input id="picMandaysDescription" type="text" maxlength="255"
                        placeholder="e.g. Propose Mandays 1 / Additional MD for New FM"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs primary-focus">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Notes <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea id="picMandaysNotes" rows="2" maxlength="2000"
                        placeholder="e.g. Approve by WA / Ada tambahan MD setelah meeting..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs primary-focus resize-none"></textarea>
                </div>
            </div>

            {{-- Matrix table --}}
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div id="picMandaysTableWrap" class="overflow-x-auto">
                    <div id="picMandaysLoading" class="py-10 text-center">
                        <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                        <p class="text-xs text-gray-400">Loading proposal data...</p>
                    </div>
                    <table id="picMandaysTable" class="hidden w-full text-xs border-collapse">
                        <thead id="picMandaysHead"></thead>
                        <tbody id="picMandaysBody"></tbody>
                        <tfoot id="picMandaysFoot"></tfoot>
                    </table>
                </div>
                {{-- Add activity row --}}
                <div id="picAddRowWrap" class="hidden px-3 py-2.5 bg-gray-50 border-t border-gray-200 flex gap-2 items-center">
                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    <input id="picNewActivity" type="text" placeholder="New activity name (e.g. Analysis, Development, UAT)" class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-xs primary-focus">
                    <button onclick="picAddActivityRow()" class="px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 whitespace-nowrap">+ Add Row</button>
                </div>
            </div>
        </div>
        <div id="picMandaysFooter" class="px-6 py-4 border-t border-gray-200 flex justify-between items-center flex-shrink-0 gap-3">
            <div class="flex items-center gap-3">
                <div class="text-xs text-gray-500">Total: <strong id="picTotalDisplay" class="primary-text">0</strong> MD</div>
                <button id="picBtnNewVersion" onclick="picStartNewVersion()" class="hidden px-4 py-2 bg-orange-500 text-white text-xs font-semibold rounded-lg hover:bg-orange-600 transition-all">
                    <svg class="w-3 h-3 inline mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    New Version
                </button>
            </div>
            <div class="flex gap-2">
                <button id="picBtnSaveDraft" onclick="picSaveDraft()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-xs font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Save Draft</button>
                <button id="picBtnSubmit" onclick="picSubmitDraft()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Submit to Helpdesk</button>
            </div>
        </div>
    </div>
</div>

{{-- PIC: Resolution Days Modal --}}
<div id="picResolutionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Resolution Days Proposal</h3>
            </div>
            <button onclick="closePicResolutionModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="resolutionRejectionInfo" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
            <div id="resolutionLoading" class="py-10 text-center">
                <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                <p class="text-xs text-gray-400">Loading resolution days data...</p>
            </div>
            <table id="resolutionTable" class="hidden w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Name</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16" title="Days — working days">Days</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16" title="Additional Days proposed by PIC">Add.</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Notes</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-20" title="Approved Additional — extra days approved by Head">Appr. Add.</th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-20" title="Total Days = Days + Approved Additional">Total Days</th>
                    </tr>
                </thead>
                <tbody id="resolutionBody"></tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td colspan="5" class="px-3 py-2 border border-gray-200 text-right text-xs">Total</td>
                        <td class="px-3 py-2 border border-gray-200 text-center" id="resolutionFooterTotal">0</td>
                    </tr>
                </tfoot>
            </table>
            <div class="mt-4">
                <label class="text-xs font-semibold text-gray-600">Notes for Delivery Support Head</label>
                <textarea id="resolutionNotes" rows="2" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-xs primary-focus" placeholder="Optional notes..."></textarea>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center flex-shrink-0 gap-3">
            <div class="text-xs text-gray-500">Total: <strong id="resolutionTotalDisplay">0</strong> days</div>
            <div class="flex gap-2">
                <button id="resolutionBtnSave" onclick="resolutionPicSaveDraft()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-xs font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Save</button>
                <button id="resolutionBtnSubmit" onclick="resolutionPicSubmit()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">Submit to Head</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Assign Ticket Lead Modal (Admin / Helpdesk / Delivery Support Head) --}}
@if($canAssignPic)
<div id="assignTicketLeadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-sm shadow-2xl flex flex-col">
        <div class="flex justify-between items-center px-5 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Assign Ticket Lead</h3>
            <button onclick="closeAssignTicketLeadModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-xs text-gray-500">Select a consultant to assign as Ticket Lead for this ticket.</p>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Consultant</label>
                <div class="relative">
                    <input id="assignTicketLeadSearch" type="text" placeholder="Search name..."
                        autocomplete="off"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs primary-focus"
                        oninput="filterAssignTicketLeadList()">
                    <div id="assignTicketLeadDropdown" class="hidden absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-xs"></div>
                </div>
                <input type="hidden" id="assignTicketLeadSelectedId">
                <div id="assignTicketLeadSelectedName" class="mt-1.5 text-xs primary-text font-semibold hidden"></div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-2">
            <button id="assignTicketLeadBtn" onclick="submitAssignTicketLead()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all">Assign</button>
        </div>
    </div>
</div>
@endif

{{-- ── Send Status Modal ───────────────────────────────────────────────── --}}
<div id="sendStatusModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-xs shadow-2xl flex flex-col">
        <div class="flex justify-between items-center px-5 py-3.5 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Send &amp; Set Status</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">Pilih status setelah reply dikirim</p>
            </div>
            <button onclick="closeSendStatusModal()" class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-red-700 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-4 py-4 flex flex-col gap-2">
            {{-- Inprocess --}}
            <button onclick="confirmSendWithStatus('inprocess')"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-yellow-50 border border-yellow-200 hover:bg-yellow-400 hover:border-yellow-400 hover:text-white group transition-all duration-150">
                <span class="w-8 h-8 rounded-lg bg-yellow-400 group-hover:bg-yellow-100 flex items-center justify-center transition-all shrink-0">
                    <svg class="w-4 h-4 text-white group-hover:text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                <div class="text-left">
                    <div class="text-xs font-bold text-yellow-800 group-hover:text-white">Inprocess</div>
                    <div class="text-[10px] text-yellow-600 group-hover:text-yellow-100">Helpdesk sedang mengerjakan</div>
                </div>
                <svg class="ml-auto w-4 h-4 text-yellow-300 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Waiting on Customer --}}
            <button onclick="confirmSendWithStatus('waiting_on_customer')"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 hover:bg-amber-500 hover:border-amber-500 hover:text-white group transition-all duration-150">
                <span class="w-8 h-8 rounded-lg bg-amber-400 group-hover:bg-amber-100 flex items-center justify-center transition-all shrink-0">
                    <svg class="w-4 h-4 text-white group-hover:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </span>
                <div class="text-left">
                    <div class="text-xs font-bold text-amber-800 group-hover:text-white">Waiting on Customer</div>
                    <div class="text-[10px] text-amber-600 group-hover:text-amber-100">Menunggu balasan customer</div>
                </div>
                <svg class="ml-auto w-4 h-4 text-amber-300 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Waiting to Confirmation --}}
            <button onclick="confirmSendWithStatus('waiting_to_confirmation')"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-teal-50 border border-teal-200 hover:bg-teal-500 hover:border-teal-500 hover:text-white group transition-all duration-150">
                <span class="w-8 h-8 rounded-lg bg-teal-400 group-hover:bg-teal-100 flex items-center justify-center transition-all shrink-0">
                    <svg class="w-4 h-4 text-white group-hover:text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div class="text-left">
                    <div class="text-xs font-bold text-teal-800 group-hover:text-white">Waiting to Confirmation</div>
                    <div class="text-[10px] text-teal-600 group-hover:text-teal-100">Menunggu konfirmasi customer</div>
                </div>
                <svg class="ml-auto w-4 h-4 text-teal-300 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Waiting on 3rd Party --}}
            <button onclick="confirmSendWithStatus('waiting_on_3rd_party')"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-indigo-50 border border-indigo-200 hover:bg-indigo-500 hover:border-indigo-500 hover:text-white group transition-all duration-150">
                <span class="w-8 h-8 rounded-lg bg-indigo-400 group-hover:bg-indigo-100 flex items-center justify-center transition-all shrink-0">
                    <svg class="w-4 h-4 text-white group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                </span>
                <div class="text-left">
                    <div class="text-xs font-bold text-indigo-800 group-hover:text-white">Waiting on 3rd Party</div>
                    <div class="text-[10px] text-indigo-600 group-hover:text-indigo-100">Diteruskan ke SAP / pihak ketiga</div>
                </div>
                <svg class="ml-auto w-4 h-4 text-indigo-300 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Hold --}}
            <button onclick="confirmSendWithStatus('hold')"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-orange-50 border border-orange-200 hover:bg-orange-500 hover:border-orange-500 hover:text-white group transition-all duration-150">
                <span class="w-8 h-8 rounded-lg bg-orange-400 group-hover:bg-orange-100 flex items-center justify-center transition-all shrink-0">
                    <svg class="w-4 h-4 text-white group-hover:text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div class="text-left">
                    <div class="text-xs font-bold text-orange-800 group-hover:text-white">Hold</div>
                    <div class="text-[10px] text-orange-600 group-hover:text-orange-100">Ticket ditahan sementara</div>
                </div>
                <svg class="ml-auto w-4 h-4 text-orange-300 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        <div class="px-4 pb-4">
            <button onclick="closeSendStatusModal()" class="w-full py-2 text-xs text-gray-400 hover:text-gray-600 transition-all">Cancel</button>
        </div>
    </div>
</div>

{{-- Helpdesk: Customer Mandays Review Modal --}}
@if(isset($isHelpdesk) && $isHelpdesk)
<div id="hdMandaysModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-3xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Review Mandays Proposal</h3>
                <p class="text-xs text-gray-500 mt-0.5">Status: <span id="hdMandaysStatusLabel">—</span></p>
            </div>
            <button onclick="closeHdMandaysModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="hdMandaysLoading" class="py-10 text-center">
                <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                <p class="text-xs text-gray-400">Loading proposal...</p>
            </div>
            {{-- Banner area: shown for approved / customer-rejected states --}}
            <div id="hdMandaysBanner" class="hidden mb-4 rounded-lg px-4 py-3 text-sm font-medium items-start gap-3"></div>
            <div id="hdMandaysContent" class="hidden">
                {{-- Rejection reason (shown when customer rejected) --}}
                <div id="hdRejectionReasonWrap" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-xs font-semibold text-red-700 mb-1">Customer Rejection Reason:</p>
                    <p id="hdRejectionReasonText" class="text-xs text-red-800"></p>
                </div>
                @if($hdCanEditDesc || $hdCanEditNotes)
                <div id="hdMetaFieldsWrap" class="hidden mb-4 space-y-3">
                    @if($hdCanEditDesc)
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Description</label>
                        <input type="text" id="hdDescriptionInput" maxlength="255"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-800"
                            placeholder="Proposal description...">
                    </div>
                    @endif
                    @if($hdCanEditNotes)
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Proposal Notes</label>
                        <textarea id="hdProposalNotesInput" rows="2" maxlength="2000"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-800 resize-none"
                            placeholder="Notes for this proposal..."></textarea>
                    </div>
                    @endif
                </div>
                @endif
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
                <button id="hdBtnSaveDraft"     onclick="hdSaveDraft()"         class="hidden inline-flex items-center px-4 py-2 bg-white text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 border border-gray-300 transition-all duration-200">Save Draft</button>
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
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-base font-bold text-gray-900">Mandays Proposal</h3>
                <p class="text-xs text-gray-500 mt-0.5">All proposal versions submitted to customer</p>
            </div>
            <button onclick="closeMandaysVersionList()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div id="mandaysVersionListLoading" class="py-10 text-center">
                <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                <p class="text-xs text-gray-400">Loading proposal history...</p>
            </div>
            <div id="mandaysVersionListEmpty" class="hidden py-12 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 mb-3">
                    <svg class="w-6 h-6 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                </div>
                <p class="text-sm font-semibold text-gray-600">No proposals yet</p>
                <p class="text-xs text-gray-400 mt-0.5">PIC has not submitted any mandays for this ticket.</p>
            </div>
            <div id="mandaysVersionListWrap" class="hidden overflow-x-auto">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-16">Ver.</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-500 border border-gray-200 whitespace-nowrap" style="min-width:180px;">Description</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-500 border border-gray-200 whitespace-nowrap" style="min-width:140px;">Notes</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-32">Status</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-20">Total MD</th>
                            <th class="px-3 py-2.5 text-center font-semibold text-gray-500 border border-gray-200 whitespace-nowrap w-36">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody id="mandaysVersionListBody"></tbody>
                </table>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between flex-shrink-0 gap-3">
            <p class="text-[10px] text-gray-400 flex items-center gap-1">
                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                Click a row to view version detail
            </p>
            @if($isPicMandays)
            <button id="mandaysVersionBtnNewPropose" onclick="mandaysVersionNewPropose()" class="hidden inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                New Proposal
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
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
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
            <div id="mvdLoading" class="py-10 text-center">
                <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                <p class="text-xs text-gray-400">Loading version detail...</p>
            </div>
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
                &larr; Back to List
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

{{-- Delivery Support Head: Resolution Days Modal --}}
@if(isset($isHead) && $isHead)
<div id="headResolutionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Review Resolution Days</h3>
                <p class="text-xs text-gray-500 mt-0.5">Status: <span id="headResolutionStatusLabel">—</span></p>
            </div>
            <button onclick="closeHeadResolutionModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="headresolutionLoading" class="py-10 text-center">
                <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                <p class="text-xs text-gray-400">Loading resolution days proposal...</p>
            </div>
            <div id="headResolutionStatusBanner" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
            <div id="headResolutionContent" class="hidden">
                <table class="w-full text-xs border-collapse mb-4">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Name</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-14" title="Days — working days">Days</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16" title="Additional Days proposed by PIC">Add.</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Notes</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-20" title="Enter approved additional for each employee">Approve Add.</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-20" title="Total Days = Days + Approved Additional">Total Days</th>
                        </tr>
                    </thead>
                    <tbody id="headresolutionBody"></tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-bold">
                            <td colspan="5" class="px-3 py-2 border border-gray-200 text-right text-xs">Total</td>
                            <td class="px-3 py-2 border border-gray-200 text-center" id="headResolutionTotal">0</td>
                        </tr>
                    </tfoot>
                </table>
                <div id="headProposedBy" class="text-xs text-gray-500 mb-1"></div>
                <div id="headResolutionNoteWrap" class="hidden p-3 bg-gray-50 rounded-lg text-xs text-gray-600 mb-3"></div>
            </div>
        </div>
        <div id="headResolutionFooter" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between flex-shrink-0">
            <p class="text-xs text-gray-400">Edit the "Approve Add." column then save to approve additional days.</p>
            <button id="headBtnApprove" onclick="headResolutionApprove()" class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                Save Approval
            </button>
        </div>
    </div>
</div>
@endif

{{-- Delivery Support Head: Customer Mandays View-Only Modal --}}
@if(isset($isHead) && $isHead)
<div id="headCustomerMandaysModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Customer Mandays Proposal</h3>
                <p class="text-xs text-gray-500 mt-0.5">View Only — Status: <span id="headCustMandaysStatus">—</span></p>
            </div>
            <button onclick="closeHeadCustomerMandaysModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="headCustMandaysLoading" class="py-10 text-center">
                <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                <p class="text-xs text-gray-400">Loading customer proposal...</p>
            </div>
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

{{-- ===== MEETING MODAL ===== --}}
@if($can('ticket.meeting'))
<div id="meetingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" onclick="if(event.target===this) closeMeetingPanel()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        {{-- Header --}}
        <div id="meetingModalHeader" class="flex items-center justify-between px-6 py-4 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div id="meetingModalIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868v6.264a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span id="meetingPanelTitle" class="text-base font-semibold"></span>
            </div>
            <button onclick="closeMeetingPanel()" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 pb-2 space-y-3">
            {{-- Link meeting — hanya tampil saat mulai meeting --}}
            <div id="meetingLinkWrap">
                {{-- Waktu --}}
                <label id="meetingTimesLabel" class="block text-sm font-medium text-gray-700 mb-2">
                    Waktu Meeting
                </label>

                {{-- Mulai: tanggal + jam --}}
                <div id="meetingStartRow" class="mb-2">
                    <p class="text-xs text-gray-400 mb-1.5 font-medium tracking-wide uppercase">Mulai</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative overflow-hidden flex items-center gap-2 px-3 py-2.5 border border-gray-300 rounded-xl bg-white focus-within:ring-2 focus-within:ring-purple-300 focus-within:border-purple-400 transition-all">
                            <svg class="w-4 h-4 text-purple-400 flex-shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <input id="meetingStartDate" type="date"
                                class="flex-1 text-sm bg-transparent focus:outline-none text-gray-700 min-w-0">
                        </div>
                        <div class="relative overflow-hidden flex items-center gap-2 px-3 py-2.5 border border-gray-300 rounded-xl bg-white focus-within:ring-2 focus-within:ring-purple-300 focus-within:border-purple-400 transition-all">
                            <svg class="w-4 h-4 text-purple-400 flex-shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <input id="meetingStartHour" type="time"
                                class="flex-1 text-sm bg-transparent focus:outline-none text-gray-700 min-w-0">
                        </div>
                    </div>
                </div>

                {{-- Selesai: tanggal + jam --}}
                <div class="mb-3">
                    <p class="text-xs text-gray-400 mb-1.5 font-medium tracking-wide uppercase">Selesai</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative overflow-hidden flex items-center gap-2 px-3 py-2.5 border border-gray-300 rounded-xl bg-white focus-within:ring-2 focus-within:ring-purple-300 focus-within:border-purple-400 transition-all">
                            <svg class="w-4 h-4 text-purple-400 flex-shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <input id="meetingEndDate" type="date"
                                class="flex-1 text-sm bg-transparent focus:outline-none text-gray-700 min-w-0">
                        </div>
                        <div class="relative overflow-hidden flex items-center gap-2 px-3 py-2.5 border border-gray-300 rounded-xl bg-white focus-within:ring-2 focus-within:ring-purple-300 focus-within:border-purple-400 transition-all">
                            <svg class="w-4 h-4 text-purple-400 flex-shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <input id="meetingEndHour" type="time"
                                class="flex-1 text-sm bg-transparent focus:outline-none text-gray-700 min-w-0">
                        </div>
                    </div>
                </div>

                {{-- Link --}}
                <div id="meetingLinkSection">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Link Meeting
                        <span class="text-gray-400 font-normal">(opsional)</span>
                    </label>
                    <div class="flex items-center gap-2 px-3 py-2.5 border border-gray-300 rounded-xl bg-white focus-within:ring-2 focus-within:ring-purple-300 transition-all">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        <input id="meetingLink" type="url"
                            class="flex-1 text-sm bg-transparent focus:outline-none"
                            placeholder="https://meet.google.com/… atau https://zoom.us/…">
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Waktu dan link akan dikirim via email ke customer</p>
                </div>
            </div>

            <div>
                <label id="meetingNotesLabel" class="block text-sm font-medium text-gray-700 mb-1.5"></label>
                <textarea id="meetingNotes" rows="2"
                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-offset-0 transition-all bg-white"
                    placeholder="(opsional)"></textarea>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-3 px-6 py-4">
            <button onclick="closeMeetingPanel()"
                class="px-4 py-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all font-medium">
                Batal
            </button>
            <button id="meetingConfirmBtn" onclick="confirmMeeting()"
                class="px-5 py-2 text-sm font-semibold text-white rounded-xl transition-all">
            </button>
        </div>
    </div>
</div>
@endif
{{-- ===== END MEETING MODAL ===== --}}

<script>
    const ticketId                    = {{ $ticket->ticket_id }};
    const userRoleIds                 = {!! json_encode($user->role_ids) !!};
    const userRole                    = userRoleIds[0] ?? 0;
    let inMeeting                     = {{ $inMeeting ? 'true' : 'false' }};
    const EC_ADMINISTRATOR_ROLE       = {{ \App\Enums\RoleId::EC_ADMINISTRATOR->value }};
    const DELIVERY_SUPPORT_USER_ROLE  = {{ \App\Enums\RoleId::DELIVERY_SUPPORT_USER->value }};
    const EC_USER_ROLE                = {{ \App\Enums\RoleId::EC_USER->value }};
    const DELIVERY_HELPDESK_ROLE      = {{ \App\Enums\RoleId::DELIVERY_HELPDESK->value }};
    const DELIVERY_RPMO_HEAD_ROLE     = {{ \App\Enums\RoleId::DELIVERY_RPMO_HEAD->value }};
    const ticketCustomerId            = {{ $ticket->customer_id ?? 'null' }};
    const currentUserId               = {{ $user->id ?? 'null' }};
    const DRAFT_KEY                   = `ticket_draft_${ticketId}_${currentUserId}`;
    const ticketChannel = @json($ticket->channel ?? 'web');
    let assignedDsId   = {{ isset($deliverySupport) && $deliverySupport ? $deliverySupport->id : 'null' }};
    const currentTicketLeadId   = {{ $ticket->ticket_lead_id ?? 'null' }};
    const currentTicketLeadName = @json($ticket->ticketLead?->basicData ? trim(($ticket->ticketLead->basicData->first_name ?? '') . ' ' . ($ticket->ticketLead->basicData->last_name ?? '')) : null);
    const assignedDsName = @json(isset($deliverySupport) && $deliverySupport ? $deliverySupport->name : null);
    const assignedDsType = @json(isset($deliverySupport) && $deliverySupport ? $deliverySupport->type : null);
    let quillEditor     = null;

    // â"€â"€ Reply-to state (WhatsApp-style internal note reply) â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
    let replyToId = null;

    function setReplyTo(msgId, senderName, msgText) {
        replyToId = msgId;
        document.getElementById('replyContextName').textContent = senderName;
        document.getElementById('replyContextText').textContent = msgText || '(no text)';
        document.getElementById('replyContextBar').classList.remove('hidden');
        quillEditor && quillEditor.focus();
    }

    function cancelReply() {
        replyToId = null;
        document.getElementById('replyContextBar').classList.add('hidden');
    }

    // ── Initiate Email Mode (untuk non-email tickets) ─────────────────────────

    let _emailInitMode = false;

    function showEmailInitMode() {
        _emailInitMode = true;
        const toRow     = document.getElementById('toRow');
        const ccRow     = document.getElementById('ccRow');
        const btnStart  = document.getElementById('btnStartEmailThread');
        const btnSend   = document.getElementById('btnSendInitEmail');
        const btnCancel = document.getElementById('btnCancelInitEmail');

        if (toRow)     toRow.style.display = '';
        if (ccRow)     ccRow.style.display = '';
        if (btnStart)  btnStart.style.display = 'none';
        if (btnSend)   btnSend.classList.remove('hidden');
        if (btnCancel) btnCancel.classList.remove('hidden');

        toEmails = [];
        renderToTags();
        quillEditor && quillEditor.focus();
    }

    function hideEmailInitMode() {
        _emailInitMode = false;
        const toRow     = document.getElementById('toRow');
        const ccRow     = document.getElementById('ccRow');
        const btnStart  = document.getElementById('btnStartEmailThread');
        const btnSend   = document.getElementById('btnSendInitEmail');
        const btnCancel = document.getElementById('btnCancelInitEmail');

        if (toRow)     toRow.style.display = 'none';
        if (ccRow)     ccRow.style.display = 'none';
        if (btnStart)  btnStart.style.display = '';
        if (btnSend)   btnSend.classList.add('hidden');
        if (btnCancel) btnCancel.classList.add('hidden');

        toEmails = [];
        renderToTags();
    }

    async function doInitiateEmail() {
        const to = toEmails[0] ?? '';
        if (!to) { showNotification('Please enter a recipient email address in the To field.', 'error'); return; }

        commitToInput();
        commitCcInput();

        const rawHtml  = quillEditor ? quillEditor.root.innerHTML : '';
        const bodyHtml = trimQuillHtml(rawHtml);

        const btn = document.getElementById('btnSendInitEmail');
        if (btn) { btn.disabled = true; btn.innerHTML = '<svg class="animate-spin h-3 w-3 inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Sending...'; }

        try {
            const ccStr = ccEmails.map(e => (typeof e === 'string' ? e : e.address)).join(', ');
            const res   = await fetch(`/api/tickets/${ticketId}/initiate-email`, {
                method:  'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ to, cc: ccStr, body: bodyHtml }),
            });

            const json = await res.json();
            if (json.success) {
                showNotification('Email sent successfully. The chat thread is now active.', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showNotification(json.message ?? 'Failed to send email.', 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane text-[10px]"></i> Send First Email'; }
            }
        } catch (err) {
            showNotification('Error: ' + err.message, 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane text-[10px]"></i> Send First Email'; }
        }
    }

    function scrollToMessage(msgId) {
        const el = document.querySelector(`[data-msg-id="${msgId}"]`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('ring-2', 'ring-amber-400', 'rounded-lg');
            setTimeout(() => el.classList.remove('ring-2', 'ring-amber-400', 'rounded-lg'), 2000);
        }
    }

    // â"€â"€ Right panel toggle â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
    function toggleRightPanel() {
        const panel        = document.getElementById('rightSidePanel');
        const iconCollapse = document.getElementById('rightPanelIconCollapse');
        const iconExpand   = document.getElementById('rightPanelIconExpand');
        if (!panel) return;
        const isExpanded = panel.style.width !== '0px';
        panel.style.width    = isExpanded ? '0px'   : '288px';
        panel.style.opacity  = isExpanded ? '0'     : '1';
        panel.style.overflow = isExpanded ? 'hidden' : '';
        if (iconCollapse) iconCollapse.classList.toggle('hidden', !isExpanded);
        if (iconExpand)   iconExpand.classList.toggle('hidden', isExpanded);
    }

    // â"€â"€ Compose area collapse toggle â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
    function toggleReplyBox() {
        const inner    = document.getElementById('replyComposeInner');
        const iconDown = document.getElementById('replyToggleIconDown');
        const iconUp   = document.getElementById('replyToggleIconUp');
        if (!inner) return;
        const isExpanded = inner.style.maxHeight !== '0px';
        inner.style.maxHeight = isExpanded ? '0px'   : '600px';
        inner.style.opacity   = isExpanded ? '0'     : '1';
        // overflow switch: hidden saat collapse (sembunyikan konten yang ter-collapse),
        // visible saat expand (agar dropdown picker Quill tidak ter-clip).
        // Saat expand, tunda set ke 'visible' sampai transisi max-height selesai (~200ms)
        // supaya konten tidak "lompat" keluar saat sedang ter-animate.
        if (isExpanded) {
            inner.style.overflow = 'hidden';
        } else {
            setTimeout(() => { inner.style.overflow = 'visible'; }, 220);
        }
        if (iconDown) iconDown.classList.toggle('hidden', isExpanded);
        if (iconUp)   iconUp.classList.toggle('hidden', !isExpanded);
    }

    // ── TO state ────────────────────────────────────────────────────────────
    // Default seeded dengan resolved customer email (sama dengan tampilan lama).
    // User dapat menambah/menghapus tag untuk mengirim ke multiple primary recipient.
    // Tidak dipersist ke DB (tiap reply mulai dari customer email lagi) — sesuai
    // permintaan minimal-MVP, persistensi bisa ditambah kemudian.
    let toEmails = @json(array_values(array_filter([$customerEmail ?? null])));

    function renderToTags() {
        const container = document.getElementById('toTagsContainer');
        if (!container) return;
        container.innerHTML = toEmails.map((email, i) =>
            `<span class="inline-flex items-center gap-1 bg-green-50 border border-green-200 text-green-700 text-[11px] rounded-full px-2 py-0.5 max-w-[220px]">
                <span class="truncate">${escHtmlCC(email)}</span>
                <button type="button" onclick="removeToTag(${i})" class="text-green-300 hover:text-red-500 transition-colors flex-shrink-0 leading-none ml-0.5">&times;</button>
            </span>`
        ).join('');
    }

    function removeToTag(index) {
        toEmails.splice(index, 1);
        renderToTags();
    }

    function handleToKeydown(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            commitToInput();
        } else if (e.key === 'Backspace' && e.target.value === '' && toEmails.length > 0) {
            toEmails.pop();
            renderToTags();
        }
    }

    function commitToInput() {
        const input = document.getElementById('toInput');
        if (!input) return;
        const parts = input.value.split(/[,;\s]+/).map(s => s.trim()).filter(Boolean);
        let added = false;
        const lowerExisting = new Set(toEmails.map(e => String(e).toLowerCase()));
        for (const email of parts) {
            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && !lowerExisting.has(email.toLowerCase())) {
                toEmails.push(email);
                lowerExisting.add(email.toLowerCase());
                added = true;
            }
        }
        if (added) renderToTags();
        input.value = '';
    }

    function handleToPaste(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        const input = document.getElementById('toInput');
        if (input) { input.value = text; commitToInput(); }
    }

    // â"€â"€ CC state â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
    let ccEmails = @json(
        collect($ticket->cc_emails ?? [])
            ->map(fn($c) => is_array($c) ? ($c['address'] ?? '') : (string)$c)
            ->filter()
            ->values()
    );

    function renderCcTags() {
        const container = document.getElementById('ccTagsContainer');
        if (!container) return;
        container.innerHTML = ccEmails.map((email, i) =>
            `<span class="inline-flex items-center gap-1 bg-blue-50 border border-blue-200 text-blue-700 text-[11px] rounded-full px-2 py-0.5 max-w-[200px]">
                <span class="truncate">${escHtmlCC(email)}</span>
                <button type="button" onclick="removeCcTag(${i})" class="text-blue-300 hover:text-red-500 transition-colors flex-shrink-0 leading-none ml-0.5">&times;</button>
            </span>`
        ).join('');
    }

    function removeCcTag(index) {
        ccEmails.splice(index, 1);
        renderCcTags();
    }

    function handleCcKeydown(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            commitCcInput();
        } else if (e.key === 'Backspace' && e.target.value === '' && ccEmails.length > 0) {
            ccEmails.pop();
            renderCcTags();
        }
    }

    function commitCcInput() {
        const input = document.getElementById('ccInput');
        if (!input) return;
        const parts = input.value.split(/[,;\s]+/).map(s => s.trim()).filter(Boolean);
        let added = false;
        for (const email of parts) {
            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && !ccEmails.includes(email)) {
                ccEmails.push(email);
                added = true;
            }
        }
        if (added) renderCcTags();
        input.value = '';
    }

    function handleCcPaste(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        const input = document.getElementById('ccInput');
        if (input) { input.value = text; commitCcInput(); }
    }

    function escHtmlCC(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Ekstrak alamat email dari raw CC value — dukung string, {address,name}, atau JSON string.
    function normalizeCcEntry(c) {
        if (!c) return '';
        if (typeof c === 'string') return c.trim().toLowerCase();
        if (typeof c === 'object' && c.address) return String(c.address).trim().toLowerCase();
        return '';
    }

    // Merge CC dari message baru customer ke state ccEmails (dedup, skip email customer
    // itu sendiri & helpdesk sender). Panggil setelah polling deteksi message baru.
    function mergeCustomerCcFromMessages(newMessages) {
        if (!Array.isArray(newMessages) || newMessages.length === 0) return;
        const customerEmail = @json(strtolower($ticket->customer?->email ?? ''));
        const senderSelf    = @json(strtolower(config('services.microsoft_graph.sender_email') ?? ''));
        const excludeSet    = new Set([customerEmail, senderSelf].filter(Boolean));
        const existingSet   = new Set(ccEmails.map(e => String(e).toLowerCase()));
        let changed = false;
        for (const msg of newMessages) {
            if (msg.sender_type !== 'customer') continue;
            let raw = msg.cc_emails;
            if (typeof raw === 'string' && raw) {
                try { raw = JSON.parse(raw); } catch (_) { raw = []; }
            }
            if (!Array.isArray(raw)) continue;
            for (const c of raw) {
                const addr = normalizeCcEntry(c);
                if (!addr) continue;
                if (excludeSet.has(addr)) continue;
                if (existingSet.has(addr)) continue;
                ccEmails.push(addr);
                existingSet.add(addr);
                changed = true;
            }
        }
        if (changed) renderCcTags();
    }

    // â"€â"€ @mention state â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
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
    // Digunakan agar polling tidak me-render ulang pesan lama &rarr; gambar tidak flicker.
    let renderedMessageIds = new Set();
    const messageCache = new Map();

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
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

        // Handle image paste (Ctrl+V) — compress & resize before inserting
        quillEditor.root.addEventListener('paste', function (e) {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            for (const item of items) {
                if (item.type.indexOf('image') === 0) {
                    e.preventDefault();
                    const file = item.getAsFile();
                    if (!file) continue;
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        const img = new Image();
                        img.onload = () => {
                            const MAX_W = 1024, MAX_H = 1024, QUALITY = 0.75;
                            let w = img.width, h = img.height;
                            if (w > MAX_W || h > MAX_H) {
                                const ratio = Math.min(MAX_W / w, MAX_H / h);
                                w = Math.round(w * ratio);
                                h = Math.round(h * ratio);
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = w; canvas.height = h;
                            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                            const dataUrl = canvas.toDataURL('image/jpeg', QUALITY);
                            const range = quillEditor.getSelection(true);
                            quillEditor.insertEmbed(range ? range.index : 0, 'image', dataUrl, 'user');
                            if (range) quillEditor.setSelection(range.index + 1, 0);
                        };
                        img.src = ev.target.result;
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

        // â"€â"€ @mention: detect @ in quill text-change â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
        quillEditor.on('text-change', function (delta, oldDelta, source) {
            // Only react to direct user input — ignore API-triggered changes (e.g. from insertMention)
            if (source !== 'user') return;

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

        // â"€â"€ Auto-link: detect URL saat user ketik spasi/enter setelah URL â"€â"€â"€â"€â"€â"€
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

        // ── Draft restore ────────────────────────────────────────────────────────
        const savedDraft = localStorage.getItem(DRAFT_KEY);
        if (savedDraft) {
            try { quillEditor.setContents(JSON.parse(savedDraft), 'api'); }
            catch { localStorage.removeItem(DRAFT_KEY); }
        }

        // ── Draft auto-save (debounce 1 s) ───────────────────────────────────
        let _draftTimer;
        quillEditor.on('text-change', function(delta, old, source) {
            if (source !== 'user') return;
            clearTimeout(_draftTimer);
            _draftTimer = setTimeout(function() {
                const hasText = quillEditor.getText().trim().length > 0;
                if (hasText) {
                    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(quillEditor.getContents())); } catch {}
                } else {
                    localStorage.removeItem(DRAFT_KEY);
                }
            }, 1000);
        });

        renderToTags();
        renderCcTags();
        loadMessages();
        switchSidebarView('my');
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

        // Position relative to the quill editor using fixed coords (avoids overflow clipping)
        const editorEl = document.getElementById('quillEditor');
        if (editorEl) {
            const rect = editorEl.getBoundingClientRect();
            dropdown.style.left  = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
            // Appear above the editor; clamp max-height so it never goes off screen
            const spaceAbove = rect.top - 8;
            dropdown.style.maxHeight = Math.min(192, spaceAbove) + 'px';
            dropdown.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
            dropdown.style.top = 'auto';
        }
        dropdown.classList.remove('hidden');
    }

    function insertMention(type, id, display) {
        // Capture to local vars before any API call can reset the globals via text-change
        const startIdx   = mentionStartIndex;
        const savedQuery = mentionQuery;

        if (startIdx < 0) return;

        const replaceLen = 1 + (savedQuery?.length ?? 0); // '@' + typed query

        // Delete the '@...' text
        quillEditor.deleteText(startIdx, replaceLen);

        // Insert a leading space if the character immediately before the '@' wasn't whitespace
        const textBefore      = quillEditor.getText(0, startIdx);
        const needsLeadSpace  = startIdx > 0 && !/\s$/.test(textBefore);
        if (needsLeadSpace) {
            quillEditor.insertText(startIdx, ' ', { color: false, bold: false });
        }

        const chipPos = needsLeadSpace ? startIdx + 1 : startIdx;
        const chip    = `@${display}`;

        // Insert chip with colour + bold, then trailing space with plain formatting
        quillEditor.insertText(chipPos, chip, {
            color: type === 'role' ? '#7c3aed' : '#1d4ed8',
            bold: true,
        });
        quillEditor.insertText(chipPos + chip.length, ' ', { color: false, bold: false });
        quillEditor.setSelection(chipPos + chip.length + 1);

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
            } else {
                // Poll berikutnya: hanya append pesan baru di bawah, pesan lama tidak disentuh
                newMessages.forEach(msg => {
                    thread.insertAdjacentHTML('beforeend', createMessageBubble(msg));
                    renderedMessageIds.add(msg.id);
                });

                // Bunyi + OS notif jika ada pesan baru dari orang lain (bukan diri sendiri)
                const incomingMessages = newMessages.filter(msg => msg.sender_id !== currentUserId);
                if (incomingMessages.length > 0) {
                    var _chatFn = window.playChatSound || window.playNotifSound;
                    if (typeof _chatFn === 'function') _chatFn();
                    // OS notification hanya saat tab background/minimize
                    if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                        const latest = incomingMessages[incomingMessages.length - 1];
                        const isNote = latest.message_type === 'internal_note';
                        const senderLabel = latest.sender_name || (isNote ? 'Someone' : 'Customer');
                        const title = isNote
                            ? senderLabel + ' added an internal note'
                            : senderLabel + ' replied to ticket ' + ({!! $ticket->ticket_number ? json_encode($ticket->ticket_number) : 'null' !!} || '');
                        const body  = latest.message_text
                            ? latest.message_text.substring(0, 100)
                            : (latest.subject || '');
                        const n = new Notification(title, {
                            body: body,
                            icon: '/images/logo_nobg.png',
                            tag:  'ticket-msg-' + latest.id,
                        });
                        n.onclick = function () { window.focus(); n.close(); };
                    }
                }
            }

            // Auto-populate CC input saat ada reply customer baru yang bawa CC —
            // helpdesk tidak perlu mengetik ulang. Helpdesk tetap bisa hapus tag.
            mergeCustomerCcFromMessages(newMessages);

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

    // â"€â"€ Render attachment list (gambar inline, file sebagai link download) â"€â"€â"€â"€â"€â"€
    // isEmailWithHtml: true jika pesan email sudah punya message_html &rarr;
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
                    ${icon}
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

    // SVG icons (not emoji) — production server kadang tidak set Content-Type charset=utf-8
    // sehingga emoji UTF-8 ter-render mojibake (e.g. "ÖŸ"). SVG aman dari masalah charset
    // dan font emoji OS yang berbeda-beda.
    function attachmentIcon(type, mime, sizeClass = 'w-5 h-5') {
        const cls = `${sizeClass} flex-shrink-0`;
        if (mime?.startsWith('image/')) return `<svg xmlns="http://www.w3.org/2000/svg" class="${cls} text-purple-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>`;
        if (type === 'pdf')             return `<svg xmlns="http://www.w3.org/2000/svg" class="${cls} text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>`;
        if (type === 'document')        return `<svg xmlns="http://www.w3.org/2000/svg" class="${cls} text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/><path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>`;
        if (type === 'spreadsheet')     return `<svg xmlns="http://www.w3.org/2000/svg" class="${cls} text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm10 1H7v3h6V5zm-6 4v3h2V9H7zm0 4v2h2v-2H7zm4 0v2h2v-2h-2zm0-1v-3h2v3h-2z" clip-rule="evenodd"/></svg>`;
        if (type === 'archive')         return `<svg xmlns="http://www.w3.org/2000/svg" class="${cls} text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm5 1a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1zm0 3a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1zm0 3a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1zm0 3a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>`;
        return `<svg xmlns="http://www.w3.org/2000/svg" class="${cls} text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd"/></svg>`;
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

    // â"€â"€ Linkify: buat URL plain text jadi <a> yang bisa diklik â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
    // Inline style dipakai langsung agar tidak kalah oleh CSS cascade (Tailwind, dsb).
    const _linkStyle = 'color:#2563eb;text-decoration:underline;word-break:break-all;';

    // linkifyHtml: aman untuk HTML yang sudah ada — tidak menyentuh attribute di
    // dalam tag (mis. src="http://..."). Pecah di SEMUA tag (bukan hanya <a>) agar
    // URL di dalam atribut tidak di-linkify (yang bikin HTML rusak, mis. <img src>
    // berubah jadi <img src="<a href=...>URL</a>"> dan di-render sebagai text).
    function linkifyHtml(html) {
        if (!html) return html;
        // Split by complete HTML tag (opening/closing/self-closing). Text nodes berada
        // di index genap, tag di index ganjil — hanya text node yang di-linkify.
        const parts = html.split(/(<[^>]*>)/g);
        return parts.map((part, i) => {
            if (i % 2 === 1) return part; // tag — jangan diubah
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

    // â"€â"€ Pilih konten pesan: HTML dari email atau plain text dari web â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
    function messageContent(msg) {
        // Email dengan HTML body &rarr; render HTML + linkify URL plain text yang tidak terbungkus <a>
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

        // Employee reply dengan message_html &rarr; render HTML + linkify URL plain text
        if (msg.sender_type === 'employee' && msg.message_html) {
            return `<div class="message-content text-sm text-gray-700 email-html-body">${linkifyHtml(sanitizeEmailHtml(msg.message_html))}</div>`;
        }

        // Web reply atau customer message &rarr; escape + linkify (XSS safe)
        if (!msg.message_body) return '';
        return `<div class="message-content text-sm text-gray-700 whitespace-pre-wrap">${linkifyText(msg.message_body)}</div>`;
    }

    // Status delivery icon: single check (sent) dan double check (delivered/read)
    const ICON_CHECK_SINGLE = `<span class="check-pair"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></span>`;
    const ICON_CHECK_DOUBLE = `<span class="check-pair"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></span>`;

    /**
     * Status indikator delivery untuk reply helpdesk &rarr; customer.
     * - Sent (&#10003; abu-abu)            : pesan tersimpan ke DB (default web)
     * - Sent via email (&#10003;&#10003; abu-abu) : email berhasil dikirim ke inbox customer
     * - Read (&#10003;&#10003; biru)              : customer sudah baca pesan di Jarvies
     *
     * Tidak ditampilkan untuk: pesan customer (sender_type='customer'),
     * internal note, atau system message — indikator hanya relevan saat
     * helpdesk perlu tahu apakah pesannya sampai dan dibaca customer.
     */
    function statusIndicator(msg) {
        if (msg.sender_type !== 'employee') return '';
        if (msg.message_type === 'internal_note') return '';

        // Format read_at sebagai tooltip "Read at 06 May 2026, 14:25 (WIB)"
        let readAtTip = '';
        if (msg.read_at) {
            try {
                const t = new Date(msg.read_at).toLocaleString('en-GB', {
                    timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', hour12: false
                }) + ' (WIB)';
                readAtTip = `Read at ${t}`;
            } catch (e) { readAtTip = 'Read by customer'; }
        } else {
            readAtTip = 'Read by customer';
        }

        if (msg.is_read_by_customer) {
            return `<div class="msg-status read" title="${readAtTip}">${ICON_CHECK_DOUBLE}<span>Read</span></div>`;
        }

        if (msg.channel === 'email' && msg.email_message_id) {
            return `<div class="msg-status" title="Delivered to customer email">${ICON_CHECK_DOUBLE}<span>Sent via email</span></div>`;
        }

        return `<div class="msg-status" title="Saved to ticket">${ICON_CHECK_SINGLE}<span>Sent</span></div>`;
    }

    const SLA_ICON = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>`;

    function slaMsgBtn(msg) {
        const hasSla = !!(msg.sla_message && msg.sla_message.trim());
        const tip    = hasSla ? escHtml(msg.sla_message) : 'Tambah pesan SLA';
        return `<button class="sla-open-btn${hasSla ? ' has-sla' : ''}"
                        title="${tip}"
                        onclick="openSlaModal(${msg.id}, this)"
                        data-sla-val="${escHtml(msg.sla_message || '')}">
                    ${SLA_ICON}SLA
                </button>`;
    }

    let _slaCurrentMsgId   = null;
    let _slaCurrentTrigger = null;

    function openSlaModal(messageId, triggerBtn) {
        _slaCurrentMsgId   = messageId;
        _slaCurrentTrigger = triggerBtn;
        const existing = triggerBtn.dataset.slaVal || '';
        document.getElementById('slaMsgTextarea').value = existing;
        document.getElementById('slaMsgModal').classList.remove('hidden');
        document.getElementById('slaMsgTextarea').focus();
    }

    function closeSlaModal() {
        document.getElementById('slaMsgModal').classList.add('hidden');
        _slaCurrentMsgId   = null;
        _slaCurrentTrigger = null;
    }

    async function submitSlaMessage() {
        if (!_slaCurrentMsgId) return;
        const val = document.getElementById('slaMsgTextarea').value.trim();
        const btn = document.getElementById('slaSaveBtn');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
        try {
            const res = await fetch(`/api/tickets/${ticketId}/messages/${_slaCurrentMsgId}/sla-message`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                },
                body: JSON.stringify({ sla_message: val })
            });
            if (res.ok && _slaCurrentTrigger) {
                _slaCurrentTrigger.dataset.slaVal = val;
                _slaCurrentTrigger.classList.toggle('has-sla', val.length > 0);
                _slaCurrentTrigger.title = val.length > 0 ? val : 'Tambah pesan SLA';
            }
            closeSlaModal();
        } catch (err) {
            console.error('Failed to save SLA message', err);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    }

    function createMessageBubble(msg) {
        messageCache.set(msg.id, msg);
        // Meeting events — kartu khusus di tengah chat
        if (msg.message_type === 'meeting_started' || msg.message_type === 'meeting_ended') {
            const isStart  = msg.message_type === 'meeting_started';
            const cardBg   = isStart ? 'bg-purple-50 border-purple-200' : 'bg-green-50 border-green-200';
            const iconClr  = isStart ? 'text-purple-500' : 'text-green-500';
            const titleClr = isStart ? 'text-purple-800' : 'text-green-800';
            const byClr    = isStart ? 'text-purple-600' : 'text-green-600';
            const title    = isStart ? 'Meeting Dimulai' : 'Meeting Selesai';
            const badge    = isStart
                ? `<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">SLA Dijeda</span>`
                : `<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-green-100 text-green-700">SLA Dilanjutkan</span>`;
            const date = new Date(msg.created_at).toLocaleString('en-GB', {
                timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: false
            }) + ' (WIB)';
            const byLine = msg.sender_name
                ? `<p class="text-[11px] ${byClr} mt-0.5">oleh ${escHtml(msg.sender_name)}</p>` : '';

            // Parse metadata dari message body
            let notesText = msg.message_body || '';
            let linkHtml  = '';
            let scheduleHtml = '';

            // Extract MeetingStart / MeetingEnd
            const fmtMeetingTime = (iso) => {
                try {
                    return new Date(iso).toLocaleString('id-ID', {
                        timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', hour12: false
                    }) + ' WIB';
                } catch { return iso; }
            };
            const startMatch = notesText.match(/(?:^|\n)MeetingStart:\s*(\S+)/i);
            const endMatch   = notesText.match(/(?:^|\n)MeetingEnd:\s*(\S+)/i);
            if (startMatch || endMatch) {
                const startStr = startMatch ? fmtMeetingTime(startMatch[1]) : '—';
                const endStr   = endMatch   ? fmtMeetingTime(endMatch[1])   : '—';
                scheduleHtml = `<div class="mt-2 text-xs text-gray-600 space-y-0.5">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><span class="text-gray-400">Mulai:</span> ${startStr}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><span class="text-gray-400">Selesai:</span> ${endStr}</span>
                    </div>
                </div>`;
                notesText = notesText
                    .replace(/(?:^|\n)MeetingStart:\s*\S+/i, '')
                    .replace(/(?:^|\n)MeetingEnd:\s*\S+/i, '')
                    .trim();
            }

            // Extract link
            const linkMatch = notesText.match(/(?:^|\n)Link:\s*(https?:\/\/\S+)/i);
            if (linkMatch) {
                const url = linkMatch[1];
                linkHtml  = `<a href="${url}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-700 text-white hover:bg-purple-800 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868v6.264a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Join Meeting
                </a>`;
                notesText = notesText.replace(/(?:^|\n)Link:\s*https?:\/\/\S+/i, '').trim();
            }
            const notesLine = notesText && notesText !== 'Meeting dimulai' && notesText !== 'Meeting selesai' && notesText !== 'Jadwal meeting dibuat'
                ? `<p class="text-xs text-gray-600 mt-1.5 whitespace-pre-wrap">${escHtml(notesText)}</p>` : '';
            return `<div class="flex justify-center my-3 px-4">
                <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl border ${cardBg} w-full max-w-md">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0 ${iconClr}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868v6.264a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-semibold ${titleClr}">${title}</span>
                                ${badge}
                            </div>
                            <span class="text-[10px] text-gray-400 flex-shrink-0">${date}</span>
                        </div>
                        ${byLine}
                        ${scheduleHtml}
                        ${notesLine}
                        ${linkHtml}
                    </div>
                </div>
            </div>`;
        }

        // System messages (status changes, audit log) &rarr; centered pill, no bubble.
        // Real system messages are never email-channel — they're web/null from server-side events.
        // CC email replies from unregistered senders get stored as sender_type='system' by
        // processInbox(), but they are real human messages and must render as chat bubbles.
        const isSystem = (msg.sender_type === 'system' && msg.channel !== 'email')
                      || /^Status change to "/i.test(msg.message_body || msg.message || '');
        if (isSystem) {
            const date = new Date(msg.created_at).toLocaleString('en-GB', {
                timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: false
            }) + ' (WIB)';
            return `<div class="flex justify-center my-2">
                <span class="text-xs text-gray-500 bg-gray-100 border border-gray-200 px-3 py-1.5 rounded-full" title="${date}">${escHtml(msg.message_body || msg.message || '')}</span>
            </div>`;
        }

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
        // Normalisasi: API mungkin kembalikan array atau JSON string (data lama) &rarr; selalu array
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
            const noteBadge = `<span class="inline-flex items-center gap-1 text-[10px] bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded font-semibold leading-none"><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg>Internal Note</span>`;

            // Soft-deleted placeholder
            if (msg.is_deleted) {
                const delText = isMine ? 'You deleted this note' : 'Internal note deleted';
                const delBubble = `<div class="message-bubble internal-note ${bubbleExtra} p-3 inline-block text-left italic text-gray-400 text-sm flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    ${delText}
                </div>`;
                if (isMine) {
                    return `<div class="flex gap-3 flex-row-reverse" data-msg-id="${msg.id}">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${avatarBgNote} ${avatarTextNote} text-xs font-bold">${initials}</div>
                        <div class="text-right">
                            <div class="flex items-center gap-2 justify-end mb-1">
                                ${noteBadge}<span class="text-sm font-semibold text-gray-900">${senderName}</span><span class="text-xs text-gray-400">${date}</span>
                            </div>${delBubble}
                        </div>
                    </div>`;
                } else {
                    return `<div class="flex gap-3" data-msg-id="${msg.id}">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${avatarBgNote} ${avatarTextNote} text-xs font-bold">${initials}</div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-semibold text-gray-900">${senderName}</span>${noteBadge}<span class="text-xs text-gray-400">${date}</span>
                            </div>${delBubble}
                        </div>
                    </div>`;
                }
            }

            // Edit window: 10 minutes from creation
            const withinEditWindow = isMine && (Date.now() - new Date(msg.created_at).getTime()) < 10 * 60 * 1000;
            const editBtns = withinEditWindow ? `
                <button onclick="openEditNoteModal(${msg.id})" class="note-reply-btn opacity-0 group-hover:opacity-100 transition-opacity text-amber-600 hover:text-amber-800 text-[10px] font-semibold flex items-center gap-0.5 px-1.5 py-0.5 rounded hover:bg-amber-100 flex-shrink-0" title="Edit note">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button onclick="confirmDeleteNote(${msg.id})" class="note-reply-btn opacity-0 group-hover:opacity-100 transition-opacity text-red-400 hover:text-red-600 text-[10px] font-semibold flex items-center gap-0.5 px-1.5 py-0.5 rounded hover:bg-red-50 flex-shrink-0" title="Delete note">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>` : '';
            const editedLabel = msg.edited_at ? `<span class="text-[10px] text-gray-400 italic">(edited)</span>` : '';

            // Quoted context if this is a reply to another note
            const replyQuote = msg.reply_to_preview
                ? `<div class="mb-2 border-l-[3px] border-amber-400 pl-2 py-0.5 bg-amber-50 rounded-r text-xs cursor-pointer" onclick="scrollToMessage(${msg.reply_to_preview.id})">
                    <span class="font-semibold text-amber-700 block truncate">${escHtml(msg.reply_to_preview.sender_name || '')}</span>
                    <span class="text-gray-500 block truncate">${escHtml(msg.reply_to_preview.text || '')}</span>
                  </div>`
                : '';

            const replyBtn = `<button onclick="setReplyTo(${msg.id}, '${escHtml(senderName).replace(/'/g,"\\'")}', '${escHtml((msg.message_body || '')).replace(/'/g, "\\'").substring(0, 80)}')"
                class="note-reply-btn opacity-0 group-hover:opacity-100 transition-opacity text-amber-600 hover:text-amber-800 text-[10px] font-semibold flex items-center gap-0.5 px-1.5 py-0.5 rounded hover:bg-amber-100 flex-shrink-0" title="Reply to this note">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Reply
            </button>`;

            if (isMine) {
                return `
                <div class="flex gap-3 flex-row-reverse group" data-msg-id="${msg.id}">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${avatarBgNote} ${avatarTextNote} text-xs font-bold">${initials}</div>
                    <div class="text-right">
                        <div class="flex items-center gap-2 justify-end mb-1">
                            ${editBtns}
                            ${replyBtn}
                            ${noteBadge}
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            <span class="text-xs text-gray-400">${date}</span>
                            ${editedLabel}
                        </div>
                        <div class="message-bubble internal-note ${bubbleExtra} p-3 inline-block text-left">
                            ${replyQuote}
                            ${messageContent(msg)}
                            ${attachmentsHtml}
                            <div class="msg-status-row">${slaMsgBtn(msg)}</div>
                        </div>
                    </div>
                </div>`;
            } else {
                return `
                <div class="flex gap-3 group" data-msg-id="${msg.id}">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${avatarBgNote} ${avatarTextNote} text-xs font-bold">${initials}</div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            ${noteBadge}
                            <span class="text-xs text-gray-400">${date}</span>
                            ${editedLabel}
                            ${replyBtn}
                        </div>
                        <div class="message-bubble internal-note ${bubbleExtra} p-3 inline-block text-left">
                            ${replyQuote}
                            ${messageContent(msg)}
                            ${attachmentsHtml}
                            <div class="msg-status-row">${slaMsgBtn(msg)}</div>
                        </div>
                    </div>
                </div>`;
            }
        }

        const avatarBg   = isEmployee ? 'bg-blue-500' : 'bg-gray-400';
        const bubbleClass = isEmployee ? 'employee' : 'customer';

        // Status delivery indicator (hanya untuk reply helpdesk &rarr; customer)
        const statusHtml    = statusIndicator(msg);
        const statusSection = `<div class="msg-status-row">${statusHtml}${slaMsgBtn(msg)}</div>`;

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
                        ${statusSection}
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
            const fileType = file.type.startsWith('image/')        ? 'image'
                           : file.type === 'application/pdf'       ? 'pdf'
                           : /\.(doc|docx)$/i.test(file.name)      ? 'document'
                           : /\.(xls|xlsx|csv)$/i.test(file.name)  ? 'spreadsheet'
                           : /\.(zip|rar)$/i.test(file.name)       ? 'archive' : 'generic';
            const icon = attachmentIcon(fileType, file.type, 'w-4 h-4');
            return `<div class="flex items-center gap-1.5 bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1.5" style="max-width:200px">
                ${icon}
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-700 truncate" title="${escHtml(file.name)}">${escHtml(file.name)}</p>
                    ${size ? `<p class="text-[10px] text-gray-400">${size}</p>` : ''}
                </div>
                <button type="button" onclick="removeAttachment(${idx})" title="Remove"
                        class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors text-xs leading-none">&#10005;</button>
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

    // ── Send Status Modal ─────────────────────────────────────────────────────
    let _pendingSendType = null;

    function openSendStatusModal(messageType) {
        _pendingSendType = messageType;
        // Reset ke default inprocess setiap kali modal dibuka
        const defaultRadio = document.querySelector('input[name="sendStatus"][value="inprocess"]');
        if (defaultRadio) defaultRadio.checked = true;
        document.getElementById('sendStatusModal').classList.remove('hidden');
    }

    function closeSendStatusModal() {
        document.getElementById('sendStatusModal').classList.add('hidden');
        _pendingSendType = null;
    }

    function confirmSendWithStatus(chosenStatus) {
        document.getElementById('sendStatusModal').classList.add('hidden');
        _doSendReply(_pendingSendType, chosenStatus || 'inprocess');
        _pendingSendType = null;
    }

    // Klik backdrop modal → tutup
    document.getElementById('sendStatusModal').addEventListener('click', function(e) {
        if (e.target === this) closeSendStatusModal();
    });

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

        // Untuk reply employee → tampilkan modal pilih status dulu
        // Kecuali tiket sudah closed/cancelled — langsung kirim tanpa mengubah status
        if (messageType === 'reply') {
            const currentStatus = document.getElementById('detailStatus')?.value;
            if (currentStatus === 'closed' || currentStatus === 'cancelled') {
                await _doSendReply(messageType, null);
                return;
            }
            openSendStatusModal(messageType);
            return;
        }

        // Internal note langsung kirim tanpa modal
        await _doSendReply(messageType, null);
    }

    function updateStatusUI(newStatus) {
        if (!newStatus) return;
        const statusLabels = {
            'open':                    'Open',
            'inprocess':               'Inprocess',
            'waiting_on_customer':     'Waiting on Customer',
            'waiting_on_3rd_party':    'Waiting on 3rd Party',
            'waiting_to_confirmation': 'Waiting to Confirmation',
            'hold':                    'Hold',
            'cancelled':               'Cancelled',
            'closed':                  'Closed',
        };
        const statusColors = {
            'open':                    ['bg-blue-100',  'text-blue-700'],
            'inprocess':               ['bg-yellow-100','text-yellow-700'],
            'waiting_on_customer':     ['bg-amber-100', 'text-amber-700'],
            'waiting_on_3rd_party':    ['bg-indigo-100','text-indigo-700'],
            'waiting_to_confirmation': ['bg-teal-100',  'text-teal-700'],
            'hold':                    ['bg-orange-100','text-orange-700'],
            'cancelled':               ['bg-gray-100',  'text-gray-500'],
            'closed':                  ['bg-green-100', 'text-green-700'],
        };
        const allColorClasses = ['bg-blue-100','text-blue-700','bg-yellow-100','text-yellow-700','bg-amber-100','text-amber-700','bg-indigo-100','text-indigo-700','bg-teal-100','text-teal-700','bg-orange-100','text-orange-700','bg-gray-100','text-gray-500','bg-green-100','text-green-700','bg-gray-100','text-gray-600'];
        const label = statusLabels[newStatus] || newStatus;

        // Right panel: hidden input + dropdown label
        const detailInput = document.getElementById('detailStatus');
        if (detailInput) detailInput.value = newStatus;
        const propertiesPanel = document.getElementById('propertiesPanel');
        if (propertiesPanel) {
            const ddLabel = propertiesPanel.querySelector('.custom-dd-label');
            if (ddLabel) ddLabel.textContent = label;
        }

        // Top header badge
        const topBadge = document.getElementById('ticketStatusBadge');
        if (topBadge) {
            topBadge.classList.remove(...allColorClasses);
            topBadge.classList.add(...(statusColors[newStatus] || ['bg-gray-100','text-gray-600']));
            topBadge.textContent = label;
        }

        // Sidebar: mutate in-memory array then re-render (no API call)
        const sidebarTicket = allSidebarTickets.find(t => t.ticket_id === ticketId);
        if (sidebarTicket) {
            sidebarTicket.status = newStatus;
            filterSidebarTickets();
        }
    }

    async function _doSendReply(messageType, chosenStatus) {
        // Disable tombol kirim selama proses agar tidak double-submit
        const sendBtn = document.querySelector('button[onclick="sendReply(\'reply\')"]');
        const noteBtn = document.querySelector('button[onclick="sendReply(\'internal_note\')"]');
        if (sendBtn) { sendBtn.disabled = true; sendBtn.classList.add('opacity-60'); }
        if (noteBtn) { noteBtn.disabled = true; noteBtn.classList.add('opacity-60'); }

        const rawHtml      = quillEditor.root.innerHTML;
        const htmlContent  = trimQuillHtml(rawHtml);
        const hasFiles     = selectedFiles.length > 0;

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

            // Pastikan input TO yang belum ter-commit (user mengetik tanpa Enter) ikut terkirim.
            commitToInput();
            commitCcInput();

            const hasInlineImages = /<img[^>]+src=["']data:/i.test(htmlContent);
            if (hasFiles || hasInlineImages) {
                const formData = new FormData();
                formData.append('message_body', htmlContent);
                formData.append('message_type', messageType);
                formData.append('to_emails', JSON.stringify(toEmails));
                formData.append('cc_emails', JSON.stringify(ccEmails));
                selectedFiles.forEach(file => formData.append('attachments[]', file));
                mentionedEmployeeIds.forEach(id => formData.append('mentioned_employee_ids[]', id));
                mentionedRoleIds.forEach(id => formData.append('mentioned_role_ids[]', id));
                if (replyToId && messageType === 'internal_note') formData.append('reply_to_id', replyToId);
                if (chosenStatus) formData.append('ticket_status', chosenStatus);
                requestBody = formData;
            } else {
                headers['Content-Type'] = 'application/json';
                requestBody = JSON.stringify({
                    message_body: htmlContent,
                    message_type: messageType,
                    to_emails: toEmails,
                    cc_emails: ccEmails,
                    mentioned_employee_ids: mentionedEmployeeIds,
                    mentioned_role_ids: mentionedRoleIds,
                    ...(replyToId && messageType === 'internal_note' ? { reply_to_id: replyToId } : {}),
                    ...(chosenStatus ? { ticket_status: chosenStatus } : {}),
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
                localStorage.removeItem(DRAFT_KEY);
                resetAttachments();
                pendingMentions = []; // reset mentions
                cancelReply();        // clear reply context
                if (chosenStatus) updateStatusUI(chosenStatus);
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
            if (userRole === EC_USER_ROLE) endpoint = '/api/tickets/my';
            else if ([EC_ADMINISTRATOR_ROLE, DELIVERY_SUPPORT_USER_ROLE, DELIVERY_HELPDESK_ROLE, DELIVERY_RPMO_HEAD_ROLE].includes(userRole) && sidebarView === 'my') endpoint = '/api/tickets/my';

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
            'open':                    ['Open',                    'sb-status-open'],
            'inprocess':               ['Inprocess',               'sb-status-inprocess'],
            'waiting_on_customer':     ['Waiting Customer',        'sb-status-waiting_on_customer'],
            'waiting_on_3rd_party':    ['Waiting 3rd Party',       'sb-status-waiting_on_3rd_party'],
            'waiting_to_confirmation': ['Waiting Confirmation',    'sb-status-waiting_to_confirmation'],
            'hold':                    ['Hold',                    'sb-status-hold'],
            'cancelled':               ['Cancelled',               'sb-status-cancelled'],
            'closed':                  ['Closed',                  'sb-status-closed'],
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

        // All member IDs (active + inactive) — excluded from "add" dropdown
        const allMemberIds = new Set(members.map(m => m.employee_id));
        if (members.length === 0) {
            list.innerHTML = '<p class="text-xs text-gray-400 italic" id="noMembersText">No members assigned.</p>';
        } else {
            list.innerHTML = members.map(m => {
                const isActive = m.is_active;
                const chipBg   = isActive ? 'bg-blue-50' : 'bg-gray-100';
                const nameCls  = isActive ? 'text-xs text-blue-700 font-medium truncate' : 'text-xs text-gray-400 font-medium truncate line-through';
                const toggleIcon   = isActive ? 'fa-eye-slash' : 'fa-eye';
                const toggleTitle  = isActive ? 'Deactivate member' : 'Reactivate member';
                const toggleColor  = isActive ? 'text-blue-300 hover:text-red-500' : 'text-gray-400 hover:text-green-500';
                const manageBtn = canManageMembers
                    ? `<button type="button" onclick="toggleMemberBtn(${m.employee_id}, ${isActive})"
                            title="${toggleTitle}"
                            class="${toggleColor} transition-colors flex-shrink-0 ml-1">
                            <i class="fas ${toggleIcon} text-[9px]"></i></button>`
                    : '';
                return `<div class="member-chip flex items-center justify-between gap-1 px-2.5 py-1.5 ${chipBg} rounded-lg" data-id="${m.employee_id}">
                    <span class="${nameCls}">${escHtmlMember(m.name)}</span>
                    ${manageBtn}
                </div>`;
            }).join('');
        }

        // Rebuild custom-dd panel items: exclude ALL member records (active + inactive).
        // Inactive members can be reactivated via toggle button directly.
        const ddPanel = document.querySelector('#addMemberDd .custom-dd-panel');
        const hidden  = document.getElementById('addMemberSelect');
        if (ddPanel) {
            const searchWrap = ddPanel.querySelector('.custom-dd-search-wrap');
            const emptyEl    = ddPanel.querySelector('.custom-dd-empty');

            const escAttr = (s) => String(s).replace(/"/g, '&quot;');
            const escTxt  = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            const itemCls = 'custom-dd-item w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors';
            let itemsHtml = `<button type="button" class="${itemCls}" data-value="">-- Add member --</button>`;
            allEmployees.forEach(emp => {
                if (!allMemberIds.has(emp.employee_id) && emp.employee_id != {{ $ticket->ticket_lead_id ?? 'null' }}) {
                    itemsHtml += `<button type="button" class="${itemCls}" data-value="${escAttr(emp.employee_id)}">${escTxt(emp.name)}</button>`;
                }
            });

            ddPanel.innerHTML = '';
            if (searchWrap) ddPanel.appendChild(searchWrap);
            ddPanel.insertAdjacentHTML('beforeend', itemsHtml);
            if (emptyEl) ddPanel.appendChild(emptyEl);

            if (hidden) hidden.value = '';
            const label = document.querySelector('#addMemberDd .custom-dd-label');
            if (label) {
                label.textContent = '-- Add member --';
                label.className   = 'custom-dd-label text-gray-500 truncate';
            }
        }
    }

    async function addMemberBtn() {
        const hidden = document.getElementById('addMemberSelect');
        const empId  = hidden?.value;
        if (!empId) { showNotification('Please select a member to add.', 'error'); return; }

        const btn = document.getElementById('addMemberBtnEl');
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

    async function toggleMemberBtn(employeeId, isActive) {
        const method = isActive ? 'DELETE' : 'POST';
        const url    = isActive
            ? `/api/tickets/${ticketId}/members/${employeeId}`
            : `/api/tickets/${ticketId}/members`;
        const body   = isActive ? null : JSON.stringify({ employee_id: employeeId });

        try {
            const res  = await fetch(url, {
                method,
                headers: getHeaders(),
                credentials: 'same-origin',
                ...(body ? { body } : {}),
            });
            let data;
            try { data = await res.json(); } catch { showNotification(`Server error (HTTP ${res.status})`, 'error'); return; }
            if (!data.success) { showNotification(data.message || 'Failed to update member.', 'error'); return; }
            renderMembers(data.data);
            showNotification(isActive ? 'Member deactivated.' : 'Member reactivated.', 'success');
        } catch (err) {
            showNotification('Network error: ' + (err?.message || 'unknown'), 'error');
        }
    }

    // ==================== MEETING ====================
    const MEETING_ICON_SVG = `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868v6.264a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>`;

    function openMeetingPanel() {
        const modal      = document.getElementById('meetingModal');
        const notesArea  = document.getElementById('meetingNotes');
        const linkInput  = document.getElementById('meetingLink');
        if (!modal) return;

        notesArea.value = '';
        if (linkInput) linkInput.value = '';

        // Always "Schedule Meeting" mode — no manual End Meeting needed
        const header     = document.getElementById('meetingModalHeader');
        const iconWrap   = document.getElementById('meetingModalIconWrap');
        const titleEl    = document.getElementById('meetingPanelTitle');
        const notesLbl   = document.getElementById('meetingNotesLabel');
        const confirmBtn = document.getElementById('meetingConfirmBtn');
        const linkWrap   = document.getElementById('meetingLinkWrap');
        const startRow   = document.getElementById('meetingStartRow');
        const linkSec    = document.getElementById('meetingLinkSection');
        const timesLbl   = document.getElementById('meetingTimesLabel');

        if (header)     { header.classList.add('bg-purple-50'); header.classList.remove('bg-red-50'); }
        if (iconWrap)   { iconWrap.classList.add('bg-purple-100', 'text-purple-600'); iconWrap.classList.remove('bg-red-100', 'text-red-600'); }
        if (titleEl)    titleEl.textContent = 'Jadwalkan Meeting';
        if (notesLbl)   notesLbl.textContent = 'Catatan (opsional)';
        if (confirmBtn) { confirmBtn.textContent = 'Jadwalkan Meeting'; confirmBtn.className = confirmBtn.className.replace(/bg-\S+/g, ''); confirmBtn.classList.add('px-5', 'py-2', 'text-sm', 'font-semibold', 'text-white', 'rounded-xl', 'transition-all', 'bg-purple-500', 'hover:bg-purple-600'); }
        if (linkWrap)   linkWrap.classList.remove('hidden');
        if (startRow)   startRow.classList.remove('hidden');
        if (linkSec)    linkSec.classList.remove('hidden');
        if (timesLbl)   timesLbl.textContent = 'Waktu Meeting';

        // Pre-fill: start = sekarang, end = +1 jam
        const pad = (n) => String(n).padStart(2, '0');
        const toDateStr = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
        const toTimeStr = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
        const now = new Date();
        const end = new Date(now.getTime() + 60 * 60 * 1000);
        const sdEl = document.getElementById('meetingStartDate');
        const shEl = document.getElementById('meetingStartHour');
        const edEl = document.getElementById('meetingEndDate');
        const ehEl = document.getElementById('meetingEndHour');
        if (sdEl) sdEl.value = toDateStr(now);
        if (shEl) shEl.value = toTimeStr(now);
        if (edEl) edEl.value = toDateStr(end);
        if (ehEl) ehEl.value = toTimeStr(end);

        modal.classList.remove('hidden');
        setTimeout(() => linkInput?.focus(), 50);
    }

    function closeMeetingPanel() {
        const modal = document.getElementById('meetingModal');
        if (modal) modal.classList.add('hidden');
    }

    async function confirmMeeting() {
        const btn    = document.getElementById('meetingConfirmBtn');
        const notes     = document.getElementById('meetingNotes')?.value?.trim() || null;
        const link      = document.getElementById('meetingLink')?.value?.trim() || null;
        const startDate = document.getElementById('meetingStartDate')?.value || null;
        const startH    = document.getElementById('meetingStartHour')?.value || null;
        const endDate   = document.getElementById('meetingEndDate')?.value || null;
        const endH      = document.getElementById('meetingEndHour')?.value || null;
        const startTime = startDate && startH ? `${startDate}T${startH}` : null;
        const endTime   = endDate   && endH   ? `${endDate}T${endH}`     : null;
        if (!btn) return;

        if (!startTime || !endTime) {
            showNotification('Waktu mulai dan selesai meeting wajib diisi', 'error');
            return;
        }
        if (new Date(endTime) <= new Date(startTime)) {
            showNotification('Waktu selesai meeting harus lebih besar dari waktu mulai', 'error');
            return;
        }

        btn.disabled = true;
        const origText = btn.textContent;
        btn.textContent = 'Memproses…';

        const endpoint = `/api/tickets/${ticketId}/sla/meeting/start`;
        const payload  = { notes, meeting_link: link, meeting_start_time: startTime, meeting_end_time: endTime };

        try {
            const res  = await fetch(endpoint, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                closeMeetingPanel();
                showNotification(data.message, 'success');
                btn.disabled = false;
                try { await loadMessages(); } catch (_) {}
            } else {
                showNotification(data.message || 'Gagal', 'error');
                btn.textContent = origText;
                btn.disabled = false;
            }
        } catch {
            showNotification('Terjadi kesalahan jaringan', 'error');
            btn.textContent = origText;
            btn.disabled = false;
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
        const status   = document.getElementById('detailStatus').value;
        const priority = document.getElementById('detailPriority').value;
        const scale    = document.getElementById('detailScale').value;
        const type     = document.getElementById('detailType').value;
        try {
            const [statusRes, updateRes] = await Promise.all([
                fetch(`/api/tickets/${ticketId}/update-status`, {
                    method: 'PUT',
                    headers: getHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({ status }),
                }),
                fetch(`/api/tickets/${ticketId}`, {
                    method: 'PUT',
                    headers: getHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        ticket_priority: priority,
                        scale: scale || null,
                        ticket_type: type || null,
                    }),
                }),
            ]);

            const result = await updateRes.json();
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
                body: JSON.stringify({ ticket_lead_id: {{ $user->id ?? 'null' }} }),
            });
            const result = await response.json();
            if (result.success) {
                showNotification('Ticket taken! You are now the Ticket Lead.', 'success');
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

    // ==================== PIC (IN CHARGE) ====================
    function onPicDropdownChange() {
        const val = document.getElementById('picSelectHidden')?.value;
        if (val) updatePic(val);
    }

    async function updatePic(picName) {
        try {
            const res = await fetch(`/api/tickets/${ticketId}/pic`, {
                method: 'PATCH',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ pic: picName }),
            });
            const result = await res.json();
            if (result.success) {
                showNotification('PIC updated', 'success');
            } else {
                showNotification(result.message || 'Failed to update PIC', 'error');
            }
        } catch (e) {
            showNotification('Error: ' + e.message, 'error');
        }
    }

    // ==================== ADDITIONAL INFO SAVE ====================
    async function saveAdditionalInfo() {
        const btn = document.getElementById('additionalInfoSaveBtn');
        btn.disabled = true;
        btn.textContent = 'Saving…';
        try {
            const res = await fetch(`/api/tickets/${ticketId}`, {
                method: 'PUT',
                headers: { ...getHeaders(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    name:   document.getElementById('additionalInfoName').value.trim()   || null,
                    no_hp:  document.getElementById('additionalInfoNoHp').value.trim()   || null,
                    module: document.getElementById('additionalInfoModule').value.trim() || null,
                    client: document.getElementById('additionalInfoClient').value.trim() || null,
                }),
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to save');
            showNotification('Additional info saved', 'success');
        } catch (e) {
            showNotification(e.message || 'Error saving additional info', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save';
        }
    }

    // ==================== ASSIGN TICKET LEAD MODAL ====================
    let assignTicketLeadList = [];

    async function openAssignTicketLeadModal() {
        document.getElementById('assignTicketLeadModal').classList.remove('hidden');
        document.getElementById('assignTicketLeadModal').classList.add('flex');
        document.getElementById('assignTicketLeadDropdown').classList.add('hidden');

        // Pre-fill dengan Ticket Lead saat ini
        if (currentTicketLeadId && currentTicketLeadName) {
            document.getElementById('assignTicketLeadSearch').value = currentTicketLeadName;
            document.getElementById('assignTicketLeadSelectedId').value = currentTicketLeadId;
            const label = document.getElementById('assignTicketLeadSelectedName');
            label.textContent = '✓ ' + currentTicketLeadName + ' selected';
            label.classList.remove('hidden');
        } else {
            document.getElementById('assignTicketLeadSearch').value = '';
            document.getElementById('assignTicketLeadSelectedId').value = '';
            document.getElementById('assignTicketLeadSelectedName').classList.add('hidden');
        }

        if (assignTicketLeadList.length === 0) {
            try {
                const res  = await fetch('/api/tickets/available-ticket-leads', { headers: getHeaders(), credentials: 'same-origin' });
                const data = await res.json();
                assignTicketLeadList = data.data || [];
            } catch (e) {
                showNotification('Failed to load consultant list', 'error');
            }
        }
        // Defer agar render terjadi setelah event bubble click selesai
        setTimeout(() => renderAssignTicketLeadDropdown(assignTicketLeadList), 0);
    }

    function closeAssignTicketLeadModal() {
        document.getElementById('assignTicketLeadModal').classList.add('hidden');
        document.getElementById('assignTicketLeadModal').classList.remove('flex');
    }

    function filterAssignTicketLeadList() {
        const q = document.getElementById('assignTicketLeadSearch').value.trim().toLowerCase();
        const filtered = q ? assignTicketLeadList.filter(p => p.name.toLowerCase().includes(q)) : assignTicketLeadList;
        renderAssignTicketLeadDropdown(filtered);
        document.getElementById('assignTicketLeadDropdown').classList.remove('hidden');
        document.getElementById('assignTicketLeadSelectedId').value = '';
        document.getElementById('assignTicketLeadSelectedName').classList.add('hidden');
    }

    function renderAssignTicketLeadDropdown(list) {
        const dd = document.getElementById('assignTicketLeadDropdown');
        if (!list.length) {
            dd.innerHTML = '<div class="px-3 py-2 text-gray-400 italic">No consultant found</div>';
        } else {
            dd.innerHTML = list.map(p =>
                `<div class="px-3 py-2 hover:bg-red-50 cursor-pointer text-gray-700" onclick="selectAssignTicketLead(${p.employee_id}, '${p.name.replace(/'/g, "\\'")}')">${p.name}</div>`
            ).join('');
        }
        dd.classList.remove('hidden');
    }

    function selectAssignTicketLead(id, name) {
        document.getElementById('assignTicketLeadSelectedId').value = id;
        document.getElementById('assignTicketLeadSearch').value = name;
        document.getElementById('assignTicketLeadDropdown').classList.add('hidden');
        const label = document.getElementById('assignTicketLeadSelectedName');
        label.textContent = '✓ ' + name + ' selected';
        label.classList.remove('hidden');
    }

    async function submitAssignTicketLead() {
        const empId = document.getElementById('assignTicketLeadSelectedId').value;
        if (!empId) { showNotification('Please select a consultant', 'warning'); return; }

        const btn = document.getElementById('assignTicketLeadBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Assigning...';

        try {
            const res    = await fetch(`/api/tickets/${ticketId}/assign-ticket-lead`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ ticket_lead_id: empId }),
            });
            const result = await res.json();
            if (result.success) {
                showNotification('Ticket Lead assigned successfully!', 'success');
                closeAssignTicketLeadModal();
                setTimeout(() => location.reload(), 800);
            } else {
                showNotification(result.message || 'Failed to assign Ticket Lead', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Assign';
            }
        } catch (e) {
            showNotification('Error: ' + e.message, 'error');
            btn.disabled = false;
            btn.innerHTML = 'Assign';
        }
    }

    // Close dropdown on outside click (kecuali klik di dalam modal atau di search field)
    document.addEventListener('click', function(e) {
        const dd    = document.getElementById('assignTicketLeadDropdown');
        const modal = document.getElementById('assignTicketLeadModal');
        if (!dd || dd.classList.contains('hidden')) return;
        if (dd.contains(e.target)) return;
        if (e.target.id === 'assignTicketLeadSearch') return;
        if (modal && modal.querySelector('.bg-white')?.contains(e.target)) return;
        dd.classList.add('hidden');
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

    function populateDeliverySupportSelect(select) {
        select.innerHTML = '<option value="">-- Select Delivery Support --</option>';
        deliverySupportList.forEach(support => {
            const option = document.createElement('option');
            option.value = support.id;
            option.textContent = `${support.name} (${support.client_name || 'Unknown Client'})${support.type ? ', ' + support.type : ''}`;
            select.appendChild(option);
        });
        if (assignedDsId) {
            const match = [...select.options].find(o => Number(o.value) === assignedDsId);
            if (match) select.value = match.value;
        }
    }

    async function loadDeliverySupports() {
        const select = document.getElementById('deliverySupportSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Loading...</option>';

        try {
            const url = ticketCustomerId
                ? `/api/delivery/support/search?client_id=${ticketCustomerId}`
                : '/api/delivery/support/search';

            const response = await fetch(url, {
                headers: getHeaders(),
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                deliverySupportList = data.data;
                if (data.data.length === 0) {
                    select.innerHTML = '<option value="">No delivery support found for this customer</option>';
                    return;
                }
                populateDeliverySupportSelect(select);
            } else {
                select.innerHTML = '<option value="">Failed to load</option>';
            }
        } catch (error) {
            select.innerHTML = '<option value="">Error loading data</option>';
        }
    }

    async function confirmAssignSupport() {
        await assignToExistingSupport();
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
                assignedDsId = Number(supportId);
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
    let resolutionPicData    = null;
    let resolutionPicPeople  = [];
    let resolutionPicReadOnly= false;

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
                : '<span class="text-gray-300">&mdash;</span>';
            const note = v.proposal_notes
                ? `<span class="text-gray-500" title="${escHtml(v.proposal_notes)}">${escHtml(v.proposal_notes.substring(0, 40))}${v.proposal_notes.length > 40 ? '&hellip;' : ''}</span>`
                : '<span class="text-gray-300">&mdash;</span>';
            const lastUpdate = v.last_update
                ? new Date(v.last_update).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', hour12: false })
                : '&mdash;';
            html += `<tr class="hover:bg-gray-50 cursor-pointer transition-colors" onclick="openMandaysVersionDetail(${v.id})">
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
            picMandaysModules = (modData.data || []).map(m => m.name ?? m);
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
                bodyHtml += `<td class="px-2 py-1.5 border border-gray-200 text-xs text-center bg-gray-50">${val !== '' ? val : '&mdash;'}</td>`;
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
            picMandaysModules = (modData.data || []).map(m => m.name ?? m);

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
                ? `<button onclick="picRemoveModuleCol('${mEsc}')" class="ml-1 text-red-300 hover:text-red-600 font-bold leading-none" title="Remove column">&times;</button>`
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
                No activities yet. Type an activity name then click <strong>Add Row</strong> below.
            </td></tr>`;
        }
        activities.forEach(act => {
            const actEsc = act.replace(/"/g, '&quot;');
            const removeRowBtn = !picReadOnly
                ? `<button onclick="picRemoveActivityRow('${actEsc}')" class="ml-1 text-red-300 hover:text-red-600 font-bold leading-none" title="Remove row">&times;</button>`
                : '';
            bodyHtml += `<tr data-activity="${act}">`;
            bodyHtml += `<td class="px-2 py-1.5 border border-gray-200 text-xs font-medium text-gray-700 whitespace-nowrap">${act}${removeRowBtn}</td>`;
            modules.forEach(m => {
                const val = valueMap[act]?.[m] || '';
                bodyHtml += `<td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="pic-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-gray-100 ${picReadOnly ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}"
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
        const payload = picGetPayload();
        const desc = (document.getElementById('picMandaysDescription')?.value || '').trim();
        if (!desc) {
            showNotification('Proposal Title wajib diisi.', 'warning');
            document.getElementById('picMandaysDescription')?.focus();
            return;
        }
        if (payload.details.length === 0) {
            showNotification('Isi minimal satu nilai mandays sebelum menyimpan.', 'warning');
            return;
        }
        const btn = document.getElementById('picBtnSaveDraft');
        btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const res = await fetch(MANDAYS_API('pic-draft'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(payload),
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
        // Snapshot data from cancelled/approved version to pre-fill
        const prevData = picDraftData;
        const valueMap = {};
        if (prevData?.details?.length) {
            prevData.details.forEach(d => {
                const act = d.activity || 'General';
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
        }
        picDraftData = null;
        picReadOnly  = false;
        picDirty     = false;
        document.getElementById('picMandaysVersion').textContent     = 'New';
        document.getElementById('picMandaysStatusLabel').textContent = 'Draft';
        const descInput  = document.getElementById('picMandaysDescription');
        const notesInput = document.getElementById('picMandaysNotes');
        // Pre-fill from previous version instead of clearing
        if (descInput)  { descInput.value  = prevData?.description    || '';  descInput.removeAttribute('readonly');  descInput.classList.remove('bg-gray-50','cursor-not-allowed'); }
        if (notesInput) { notesInput.value = prevData?.proposal_notes || ''; notesInput.removeAttribute('readonly'); notesInput.classList.remove('bg-gray-50','cursor-not-allowed'); }
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


    // ==================== PIC: RESOLUTION DAYS ====================
    async function openResolutionDaysModal() {
        document.getElementById('picResolutionModal').classList.remove('hidden');
        document.getElementById('picResolutionModal').classList.add('flex');
        await resolutionPicLoad();
    }
    function closePicResolutionModal() {
        document.getElementById('picResolutionModal').classList.add('hidden');
        document.getElementById('picResolutionModal').classList.remove('flex');
    }

    async function resolutionPicLoad() {
        document.getElementById('resolutionLoading').classList.remove('hidden');
        document.getElementById('resolutionTable').classList.add('hidden');
        document.getElementById('resolutionRejectionInfo').classList.add('hidden');

        try {
            const res    = await fetch(MANDAYS_API('resolution'), { headers: getHeaders(), credentials: 'same-origin' });
            const data   = await res.json();
            if (!data.success) {
                showNotification(data.message || 'Failed to load resolution days', 'error');
                return;
            }
            resolutionPicData    = data.data;
            resolutionPicPeople  = data.people || [];
            const status       = data.resolution_days_status || 'none';

            resolutionPicReadOnly = false; // consultant can always edit

            document.getElementById('resolutionNotes').value = resolutionPicData?.notes || '';
            document.getElementById('resolutionNotes').readOnly = false;
            document.getElementById('resolutionNotes').classList.remove('bg-gray-50');

            // Show info banner based on status
            const infoEl = document.getElementById('resolutionRejectionInfo');
            if (status === 'approved') {
                infoEl.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
                infoEl.innerHTML = '<p class="font-semibold mb-1">Proposal Approved by Delivery Support Head</p>'
                    + (resolutionPicData?.approved_by_head ? '<p>Approved by: ' + resolutionPicData.approved_by_head + '</p>' : '')
                    + '<p class="mt-1 text-green-600">You can still update the resolution days and re-submit to Delivery Support Head.</p>';
                infoEl.classList.remove('hidden');
            } else if (status === 'pending_head') {
                infoEl.className = 'mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-600';
                infoEl.innerHTML = '<p class="font-semibold">Submitted — awaiting Delivery Support Head review. You can still update and re-submit.</p>';
                infoEl.classList.remove('hidden');
            } else if (status === 'rejected' && resolutionPicData?.rejection_reason) {
                infoEl.className = 'mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
                infoEl.innerHTML = '<p class="font-semibold mb-1">Revision Required by Delivery Support Head</p>'
                    + '<p>' + resolutionPicData.rejection_reason + '</p>';
                infoEl.classList.remove('hidden');
            }

            // Build valueMap from existing details only — start from 0 if none
            const valueMap = {};
            (resolutionPicData?.details || []).forEach(d => {
                valueMap[d.employee_id] = {
                    mandays:             (valueMap[d.employee_id]?.mandays || 0) + d.mandays,
                    additional_mandays:  (valueMap[d.employee_id]?.additional_mandays || 0) + (d.additional_mandays || 0),
                    approved_additional: (valueMap[d.employee_id]?.approved_additional || 0) + (d.approved_additional || 0),
                    notes:               d.notes || valueMap[d.employee_id]?.notes || '',
                };
            });

            resolutionPicRenderRows(valueMap);
        } catch(e) {
            console.error(e);
            showNotification('Failed to load resolution days', 'error');
        } finally {
            document.getElementById('resolutionLoading').classList.add('hidden');
        }
    }

    function resolutionPicRenderRows(valueMap) {
        let html = '';
        resolutionPicPeople.forEach(person => {
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
                        class="internal-md-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-gray-100 bg-white"
                        data-employee="${person.employee_id}" value="${mdVal}"
                        oninput="resolutionUpdateRowTotal(this)">
                </td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="internal-add-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-gray-100 bg-white"
                        data-employee="${person.employee_id}" value="${addVal}"
                        oninput="resolutionUpdateRowTotal(this)">
                </td>
                <td class="border border-gray-200 p-0">
                    <input type="text"
                        class="internal-note-cell w-full px-2 py-1.5 text-xs focus:outline-none focus:bg-gray-100 bg-white"
                        data-employee="${person.employee_id}" value="${existing.notes || ''}"
                        placeholder="notes..."
                        oninput="internalClearNoteHighlight(this)">
                </td>
                <td class="px-2 py-1.5 border border-gray-200 text-xs text-center bg-gray-50 text-gray-500" data-emp-appr="${person.employee_id}">${apprAddDisplay}</td>
                <td class="px-2 py-1.5 border border-gray-200 text-xs text-center font-semibold bg-gray-50" data-emp-total="${person.employee_id}">${totalMd > 0 ? totalMd.toFixed(1) : '—'}</td>
            </tr>`;
        });
        document.getElementById('resolutionBody').innerHTML = html;
        document.getElementById('resolutionTable').classList.remove('hidden');
        internalUpdateTotal();
    }

    function resolutionUpdateRowTotal(inp) {
        const row = inp.closest('tr');
        const mdVal  = parseFloat(row.querySelector('.internal-md-cell')?.value)  || 0;
        const addVal = parseFloat(row.querySelector('.internal-add-cell')?.value) || 0;
        // For PIC view, approved_additional comes from existing data (not editable here)
        const empId = inp.dataset.employee;
        const existingApproved = (resolutionPicData?.details || []).find(d => d.employee_id == empId)?.approved_additional || 0;
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
        document.getElementById('resolutionTotalDisplay').textContent = total.toFixed(1);
        const footer = document.getElementById('resolutionFooterTotal');
        if (footer) footer.textContent = total.toFixed(1);
    }

    function resolutionPicGetPayload() {
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
        return { details, notes: document.getElementById('resolutionNotes').value };
    }

    // Hapus highlight merah pada notes cell saat user mulai mengetik
    function internalClearNoteHighlight(el) {
        if (el.value.trim()) {
            el.classList.remove('ring-2', 'ring-red-400', 'bg-red-50');
        }
    }

    // Validasi: jika Additional MD diisi maka Notes wajib diisi
    // Mengembalikan array nama employee yang melanggar aturan (kosong = valid)
    function resolutionPicValidate() {
        const errors = [];
        document.querySelectorAll('.internal-add-cell').forEach(inp => {
            const row    = inp.closest('tr');
            const add    = parseFloat(inp.value) || 0;
            const noteEl = row.querySelector('.internal-note-cell');
            const notes  = noteEl?.value?.trim() || '';

            if (add > 0 && !notes) {
                const name = row.querySelector('td:first-child')?.textContent?.trim() || 'Employee';
                errors.push(name);
                // Tandai field notes dengan warna merah
                noteEl?.classList.add('ring-2', 'ring-red-400', 'bg-red-50');
                noteEl?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                noteEl?.classList.remove('ring-2', 'ring-red-400', 'bg-red-50');
            }
        });
        return errors;
    }

    async function resolutionPicSaveDraft() {
        const validationErrors = resolutionPicValidate();
        if (validationErrors.length) {
            showNotification(
                'Notes are required if Additional Days is filled: ' + validationErrors.join(', '),
                'error', 6000
            );
            return;
        }
        const btn = document.getElementById('resolutionBtnSave');
        btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const res = await fetch(MANDAYS_API('resolution'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(resolutionPicGetPayload()),
            });
            const data = await res.json();
            if (data.success) {
                if (data.data_changed === false) {
                    showNotification('No changes detected — approval status unchanged.', 'info');
                } else {
                    showNotification('Draft saved. Submit to Head Support for approval.', 'success');
                }
                resolutionUpdateSidebarBadge(data.resolution_days_status);
                resolutionPicData = data.data;
            } else {
                showNotification(data.message || 'Failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Save'; }
    }

    async function resolutionPicSubmit() {
        // Validasi sebelum submit
        const validationErrors = resolutionPicValidate();
        if (validationErrors.length) {
            showNotification(
                    'Notes are required if Additional Days is filled: ' + validationErrors.join(', '),
                    'error', 6000
            );
            return;
        }
        // Save first then submit
        const btn = document.getElementById('resolutionBtnSubmit');
        btn.disabled = true; btn.textContent = 'Submitting...';
        try {
            // Save
            const saveRes = await fetch(MANDAYS_API('resolution'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(resolutionPicGetPayload()),
            });
            const saveData = await saveRes.json();
            if (!saveData.success) { showNotification(saveData.message || 'Save failed', 'error'); return; }

            // Submit
            const subRes = await fetch(MANDAYS_API('resolution/submit'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
            });
            const subData = await subRes.json();
            if (subData.success) {
                showNotification('Submitted to Delivery Support Head!', 'success');
                resolutionUpdateSidebarBadge(subData.resolution_days_status);
                closePicResolutionModal();
            } else {
                showNotification(subData.message || 'Submit failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Submit to Head'; }
    }

    function resolutionUpdateSidebarBadge(status) {
        const badges = {
            'none':        ['bg-gray-100 text-gray-500',   'None'],
            'draft':       ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_head':['bg-blue-100 text-blue-700',   'Pending Head'],
            'approved':    ['bg-green-100 text-green-700', 'Approved'],
            'rejected':    ['bg-red-100 text-red-700',     'Rejected'],
        };
        const el = document.getElementById('resolutionBadge');
        if (el && badges[status]) {
            el.className = `inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${badges[status][0]}`;
            el.textContent = badges[status][1];
        }
    }


    // ==================== HELPDESK: CUSTOMER MANDAYS REVIEW ====================
    const HD_CAN_EDIT_ACTIVITY   = {{ ($hdCanEditActivity   ?? false) ? 'true' : 'false' }};
    const HD_CAN_EDIT_DESC       = {{ ($hdCanEditDesc       ?? false) ? 'true' : 'false' }};
    const HD_CAN_EDIT_NOTES      = {{ ($hdCanEditNotes      ?? false) ? 'true' : 'false' }};
    const HD_CAN_SAVE_DRAFT      = {{ ($hdCanSaveDraft      ?? false) ? 'true' : 'false' }};
    const HD_CAN_SEND_TO_CUSTOMER= {{ ($hdCanSendToCustomer ?? false) ? 'true' : 'false' }};
    const HD_CAN_APPROVE         = {{ ($hdCanApprove        ?? false) ? 'true' : 'false' }};
    const HD_CAN_CANCEL          = {{ ($hdCanCancel         ?? false) ? 'true' : 'false' }};

    async function openHdMandaysModal() {
        const modal = document.getElementById('hdMandaysModal');
        if (!modal) { console.warn('[hdMandays] modal element not found'); return; }
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('hdMandaysLoading').classList.remove('hidden');
        document.getElementById('hdMandaysContent').classList.add('hidden');
        document.getElementById('hdMandaysBanner').classList.add('hidden');
        document.getElementById('hdCancelConfirmWrap')?.classList.add('hidden');

        try {
            const [modRes, draftRes] = await Promise.all([
                fetch(MANDAYS_API('modules'), { headers: getHeaders(), credentials: 'same-origin' }),
                fetch(MANDAYS_API('hd-draft'), { headers: getHeaders(), credentials: 'same-origin' }),
            ]);
            if (!modRes.ok || !draftRes.ok) {
                throw new Error(`API error: modules=${modRes.status} hd-draft=${draftRes.status}`);
            }
            const modData   = await modRes.json();
            const draftData = await draftRes.json();
            const modules   = (modData.data || []).map(m => m.name ?? m);
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
                let cancelHtml = `<span class="text-gray-600 text-base mt-0.5">&#10005;</span>
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
                banner.innerHTML = `<span class="text-green-700 text-base mt-0.5">&#10003;</span>
                    <div><p class="font-semibold text-green-800">Approved by Customer</p>
                    ${ts ? `<p class="text-xs font-normal text-green-700 mt-0.5">${ts}</p>` : ''}</div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-green-50', 'border', 'border-green-200', 'text-green-800');
            } else if (isCustomerRejected) {
                const ts = proposal.customer_response_at
                    ? new Date(proposal.customer_response_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12: false }) + ' WIB'
                    : '';
                banner.innerHTML = `<span class="text-red-700 text-base mt-0.5">&#10005;</span>
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

            // Populate description/notes edit fields if editable & permitted
            const metaWrap = document.getElementById('hdMetaFieldsWrap');
            if (metaWrap) {
                if (isEditable) {
                    metaWrap.classList.remove('hidden');
                    const descEl = document.getElementById('hdDescriptionInput');
                    if (descEl) descEl.value = proposal.description || '';
                    const notesEl = document.getElementById('hdProposalNotesInput');
                    if (notesEl) notesEl.value = proposal.proposal_notes || '';
                } else {
                    metaWrap.classList.add('hidden');
                }
            }

            const escAttr = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            let bodyHtml = '';
            activities.forEach(act => {
                const actCell = (isEditable && HD_CAN_EDIT_ACTIVITY)
                    ? `<input type="text" class="hd-activity-input w-full px-2 py-1.5 text-xs border-0 focus:outline-none focus:bg-gray-50 bg-white font-medium" value="${escAttr(act)}" placeholder="Activity..." maxlength="150">`
                    : `<span class="px-2 py-1.5 block text-xs font-medium">${escAttr(act)}</span>`;
                bodyHtml += `<tr><td class="border border-gray-200 p-0">${actCell}</td>`;
                mods.forEach(m => {
                    const val = valueMap[act]?.[m] || '';
                    bodyHtml += `<td class="border border-gray-200 p-0">
                        <input type="number" min="0" step="0.5" class="hd-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none ${isEditable?'focus:bg-gray-100 bg-white':'bg-gray-50 cursor-not-allowed'}"
                        data-activity="${escAttr(act)}" data-module="${escAttr(m)}" value="${val}" ${isEditable?'':'readonly'} oninput="hdUpdateTotal()">
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
            ['hdBtnSaveDraft','hdBtnSendToChat','hdBtnReviseResend','hdBtnApprove','hdBtnCancel','hdBtnNewProposal'].forEach(id => {
                document.getElementById(id)?.classList.add('hidden');
            });
            if (isPicSubmitted) {
                if (HD_CAN_SAVE_DRAFT)       document.getElementById('hdBtnSaveDraft')?.classList.remove('hidden');
                if (HD_CAN_SEND_TO_CUSTOMER) document.getElementById('hdBtnSendToChat')?.classList.remove('hidden');
                if (HD_CAN_CANCEL)           document.getElementById('hdBtnCancel')?.classList.remove('hidden');
                // Show info banner: must send to chat before approving
                banner.innerHTML = `<i class="fas fa-info-circle text-blue-500 text-sm mt-0.5 flex-shrink-0"></i>
                    <div><p class="font-semibold text-blue-800">Send to Customer First</p>
                    <p class="text-xs font-normal text-blue-700 mt-0.5">You must send this proposal to the customer chat before it can be approved.</p></div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-blue-50', 'border', 'border-blue-200', 'text-blue-800');
            } else if (isCustomerRejected) {
                if (HD_CAN_SAVE_DRAFT)       document.getElementById('hdBtnSaveDraft')?.classList.remove('hidden');
                if (HD_CAN_SEND_TO_CUSTOMER) document.getElementById('hdBtnReviseResend')?.classList.remove('hidden');
                if (HD_CAN_CANCEL)           document.getElementById('hdBtnCancel')?.classList.remove('hidden');
            } else if (isSentToChat) {
                if (HD_CAN_APPROVE) document.getElementById('hdBtnApprove')?.classList.remove('hidden');
                if (HD_CAN_CANCEL)  document.getElementById('hdBtnCancel')?.classList.remove('hidden');
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
            if (v > 0) {
                const row = inp.closest('tr');
                const actInput = row?.querySelector('.hd-activity-input');
                const act = actInput ? (actInput.value.trim() || inp.dataset.activity) : inp.dataset.activity;
                details.push({ activity: act, module: inp.dataset.module, mandays: v });
            }
        });
        if (details.length > 0) {
            const savePayload = { details };
            if (HD_CAN_EDIT_DESC) {
                const descEl = document.getElementById('hdDescriptionInput');
                if (descEl) savePayload.description = descEl.value.trim();
            }
            if (HD_CAN_EDIT_NOTES) {
                const notesEl = document.getElementById('hdProposalNotesInput');
                if (notesEl) savePayload.proposal_notes = notesEl.value.trim();
            }
            await fetch(MANDAYS_API('hd-draft'), {
                method: 'PUT', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(savePayload),
            });
        }
        const res = await fetch(MANDAYS_API(endpoint), {
            method, headers: getHeaders(), credentials: 'same-origin',
            body: JSON.stringify(extraBody),
        });
        return res.json();
    }

    async function hdSaveDraft() {
        const btn = document.getElementById('hdBtnSaveDraft');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
        try {
            const details = [];
            document.querySelectorAll('.hd-cell:not([readonly])').forEach(inp => {
                const v = parseFloat(inp.value) || 0;
                if (v > 0) {
                    const row = inp.closest('tr');
                    const actInput = row?.querySelector('.hd-activity-input');
                    const act = actInput ? (actInput.value.trim() || inp.dataset.activity) : inp.dataset.activity;
                    details.push({ activity: act, module: inp.dataset.module, mandays: v });
                }
            });
            if (details.length === 0) {
                showNotification('Please fill in at least one mandays value.', 'warning');
                return;
            }
            const payload = { details };
            if (HD_CAN_EDIT_DESC) {
                const descEl = document.getElementById('hdDescriptionInput');
                if (descEl) payload.description = descEl.value.trim();
            }
            if (HD_CAN_EDIT_NOTES) {
                const notesEl = document.getElementById('hdProposalNotesInput');
                if (notesEl) payload.proposal_notes = notesEl.value.trim();
            }
            const res = await fetch(MANDAYS_API('hd-draft'), {
                method: 'PUT', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Draft saved. Team members can now view the updated proposal.', 'success');
            } else {
                showNotification(data.message || 'Failed to save draft.', 'error');
            }
        } catch(e) {
            showNotification('Error: ' + e.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Save Draft'; }
        }
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
                setTimeout(() => location.reload(), 800);
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
    }
    function hdShowCancelConfirm() {
        // Hide action buttons and show cancel confirmation form
        ['hdBtnSaveDraft','hdBtnSendToChat','hdBtnReviseResend','hdBtnApprove','hdBtnCancel','hdBtnNewProposal'].forEach(id => {
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


    // ==================== HEAD OF SUPPORT: RESOLUTION DAYS ====================
    async function openHeadResolutionModal() {
        const modal = document.getElementById('headResolutionModal');
        if (!modal) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('headresolutionLoading').classList.remove('hidden');
        document.getElementById('headResolutionContent').classList.add('hidden');
        document.getElementById('headResolutionStatusBanner').classList.add('hidden');

        try {
            const res  = await fetch(MANDAYS_API('resolution'), { headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            const proposal = data.data;
            const status   = data.resolution_days_status || 'none';

            const headStatusLabels = {
                'none':         'None',
                'draft':        'Draft',
                'pending_head': 'Pending Review',
                'approved':     'Approved',
                'rejected':     'Needs Revision',
            };
            document.getElementById('headResolutionStatusLabel').textContent = headStatusLabels[status] || status;

            if (!proposal) {
                document.getElementById('headResolutionContent').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No proposal submitted yet.</p>';
                document.getElementById('headResolutionContent').classList.remove('hidden');
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
                            class="head-approve-add w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-gray-100 bg-white"
                            data-employee="${eid}" data-mandays="${emp.mandays}"
                            value="${currentApprAdd > 0 ? currentApprAdd : ''}"
                            oninput="headUpdateRowTotal(this)">
                    </td>
                    <td class="px-2 py-1.5 border border-gray-200 text-xs text-center font-semibold bg-gray-50" data-head-total="${eid}">${rowTotal > 0 ? rowTotal.toFixed(1) : '—'}</td>
                </tr>`;
            });
            document.getElementById('headresolutionBody').innerHTML = bodyHtml;
            document.getElementById('headResolutionTotal').textContent = grandTotal.toFixed(1);

            if (proposal.proposed_by) {
                document.getElementById('headProposedBy').textContent = 'Proposed by: ' + proposal.proposed_by;
            }
            if (proposal.notes) {
                const nw = document.getElementById('headResolutionNoteWrap');
                nw.textContent = 'Notes: ' + proposal.notes;
                nw.classList.remove('hidden');
            }

            // Status info banner
            const bannerEl = document.getElementById('headResolutionStatusBanner');
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

            document.getElementById('headResolutionContent').classList.remove('hidden');
        } catch(e) {
            console.error(e);
            showNotification('Failed to load internal proposal', 'error');
        } finally {
            document.getElementById('headresolutionLoading').classList.add('hidden');
        }
    }

    function closeHeadResolutionModal() {
        const modal = document.getElementById('headResolutionModal');
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
        document.getElementById('headResolutionTotal').textContent = grand.toFixed(1);
    }

    async function headResolutionApprove() {
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

            const res  = await fetch(MANDAYS_API('resolution/approve'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify({ approved_details: approvedDetails }),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Resolution days saved!', 'success');
                resolutionUpdateSidebarBadge?.(data.resolution_days_status);
                closeHeadResolutionModal();
                setTimeout(() => location.reload(), 800);
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

    // ==================== INTERNAL NOTE EDIT / DELETE ====================
    let editNoteQuill = null;
    let editNoteId    = null;
    let editNoteRemovedAttachmentIds = [];

    function openEditNoteModal(msgId) {
        const msg = messageCache.get(msgId);
        if (!msg) return;

        editNoteId = msgId;
        editNoteRemovedAttachmentIds = [];

        // Lazy-init Quill editor for the edit modal
        if (!editNoteQuill) {
            editNoteQuill = new Quill('#editNoteEditorContainer', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        ['blockquote'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['clean']
                    ]
                }
            });
        }

        // Pre-fill content
        if (msg.message_html) {
            editNoteQuill.clipboard.dangerouslyPasteHTML(msg.message_html);
        } else {
            editNoteQuill.setText(msg.message_body || '');
        }

        // Render existing attachments
        const attContainer = document.getElementById('editNoteExistingAtts');
        attContainer.innerHTML = '';
        (msg.attachments || []).forEach(att => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 text-xs text-gray-700 py-1';
            row.dataset.attId = att.id;
            row.innerHTML = `<svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                <span class="truncate max-w-xs">${escHtml(att.file_name)}</span>
                <button type="button" onclick="removeEditNoteAtt(${att.id}, this.closest('[data-att-id]'))" class="ml-auto text-red-400 hover:text-red-600 flex-shrink-0" title="Remove attachment">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>`;
            attContainer.appendChild(row);
        });

        // Clear new files input
        document.getElementById('editNoteNewFiles').value = '';

        document.getElementById('editNoteModal').classList.remove('hidden');
    }

    function removeEditNoteAtt(attId, rowEl) {
        editNoteRemovedAttachmentIds.push(attId);
        if (rowEl) {
            rowEl.classList.add('opacity-30', 'line-through', 'pointer-events-none');
            rowEl.querySelector('button')?.remove();
        }
    }

    function closeEditNoteModal() {
        document.getElementById('editNoteModal').classList.add('hidden');
        editNoteId = null;
        editNoteRemovedAttachmentIds = [];
    }

    async function saveEditNote() {
        if (!editNoteId || !editNoteQuill) return;

        const ticketId = {{ $ticket->ticket_id }};
        const msgHtml  = editNoteQuill.root.innerHTML;
        const formData = new FormData();
        formData.append('message_html', msgHtml);

        editNoteRemovedAttachmentIds.forEach(id => formData.append('remove_attachment_ids[]', id));

        const newFiles = document.getElementById('editNoteNewFiles').files;
        for (const file of newFiles) formData.append('attachments[]', file);

        const btn = document.getElementById('editNoteSaveBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';

        try {
            const res  = await fetch(`/api/tickets/${ticketId}/messages/${editNoteId}/internal-note`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                credentials: 'same-origin',
                body: formData,
            });
            let json;
            try { json = await res.json(); } catch { showNotification(`Server error (${res.status})`, 'error'); return; }
            if (!json.success) { showNotification(json.message || 'Failed to update note.', 'error'); return; }

            // Update cache + re-render element in place
            const updatedMsg = { ...messageCache.get(editNoteId), message_html: json.data.message_html, message_body: json.data.message_body, edited_at: json.data.edited_at, attachments: json.data.attachments };
            const el = document.querySelector(`[data-msg-id="${editNoteId}"]`);
            if (el) el.outerHTML = createMessageBubble(updatedMsg);

            showNotification('Note updated.', 'success');
            closeEditNoteModal();
        } catch (e) {
            showNotification('Failed to update note.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save';
        }
    }

    async function confirmDeleteNote(msgId) {
        const ok = await showConfirm('Delete this note? It will be replaced with a placeholder and cannot be restored.', 'Delete Internal Note', 'danger');
        if (!ok) return;
        deleteNote(msgId);
    }

    async function deleteNote(msgId) {
        const ticketId = {{ $ticket->ticket_id }};
        try {
            const res  = await fetch(`/api/tickets/${ticketId}/messages/${msgId}/internal-note`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            let json;
            try { json = await res.json(); } catch { showNotification(`Server error (${res.status})`, 'error'); return; }
            if (!json.success) { showNotification(json.message || 'Failed to delete note.', 'error'); return; }

            const updatedMsg = { ...messageCache.get(msgId), is_deleted: true };
            const el = document.querySelector(`[data-msg-id="${msgId}"]`);
            if (el) el.outerHTML = createMessageBubble(updatedMsg);

            showNotification('Note deleted.', 'success');
        } catch (e) {
            showNotification('Failed to delete note.', 'error');
        }
    }
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

{{-- OneDrive folder generation dihapus — folder ticket kini otomatis dibuat di bawah
     folder Customer Deliverable milik delivery support saat upload deliverable. --}}

{{-- ==================== REUSABLE CONFIRM MODAL ==================== --}}
<div id="confirmModal" class="hidden fixed inset-0 bg-black/50 z-[70] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4">
        <div class="px-6 pt-6 pb-3">
            <div class="flex items-start gap-3">
                <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5"></div>
                <div>
                    <h3 id="confirmTitle" class="text-sm font-bold text-gray-900 mb-1">Confirm</h3>
                    <p id="confirmMessage" class="text-sm text-gray-600 leading-relaxed"></p>
                </div>
            </div>
        </div>
        <div class="px-6 pb-5 pt-2 flex gap-2 justify-end">
            <button id="confirmCancelBtn"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition font-medium">
                Cancel
            </button>
            <button id="confirmOkBtn"
                class="px-4 py-2 text-sm font-semibold text-white rounded-lg transition">
                OK
            </button>
        </div>
    </div>
</div>

{{-- ==================== SLA MESSAGE MODAL ==================== --}}
<div id="slaMsgModal" class="hidden fixed inset-0 bg-black/50 z-[70] flex items-center justify-center p-4" onclick="if(event.target===this)closeSlaModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Pesan SLA</h3>
                    <p class="text-xs text-gray-400 leading-none mt-0.5">Pesan ini akan tampil di laporan SLA menggantikan pesan asli</p>
                </div>
            </div>
            <button onclick="closeSlaModal()" class="text-gray-400 hover:text-gray-600 transition p-1 rounded-lg hover:bg-gray-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-6 py-4">
            <textarea id="slaMsgTextarea"
                      rows="4"
                      placeholder="Tulis pesan SLA di sini..."
                      class="w-full text-sm text-gray-700 border border-gray-200 rounded-xl px-3 py-2.5 resize-none outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition font-inherit placeholder-gray-300"
                      onkeydown="if(event.key==='Enter'&&(event.ctrlKey||event.metaKey))submitSlaMessage()"></textarea>
            <p class="text-xs text-gray-400 mt-1.5">Ctrl+Enter untuk simpan</p>
        </div>
        <div class="flex gap-2 justify-end px-6 pb-5">
            <button onclick="closeSlaModal()"
                    class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition font-medium">
                Batal
            </button>
            <button id="slaSaveBtn" onclick="submitSlaMessage()"
                    class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                Simpan
            </button>
        </div>
    </div>
</div>

{{-- ==================== DELIVERABLE MODAL ==================== --}}
<div id="deliverableModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl mx-4 flex flex-col" style="max-height:90vh">
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="text-base font-bold text-gray-900">Deliverable Documents</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ $ticket->ticket_number }} — {{ Str::limit($ticket->description, 50) }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openNewDocModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Document
                </button>
                <button onclick="closeDeliverableModal()"
                    class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-auto flex-1 px-2">
            <table class="w-full text-xs border-collapse" id="deliverableTable">
                <thead class="sticky top-0 bg-white z-10">
                    <tr class="border-b border-gray-200">
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Upload Date</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Time</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap" style="min-width:90px">Doc Type</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide" style="min-width:200px">Body Text</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide" style="min-width:160px">File Name</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody id="deliverableBody">
                    <tr>
                        <td colspan="7" class="text-center py-10 text-gray-400">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-3 border-t border-gray-100 text-[10px] text-gray-400 shrink-0" id="deliverableFooter"></div>
    </div>
</div>

{{-- ==================== NEW DOCUMENT MODAL ==================== --}}
<div id="newDocModal" class="hidden fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-sm font-bold text-gray-900">New Document</h3>
            <button onclick="closeNewDocModal()" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-5 py-4 space-y-4">
            {{-- Doc Type --}}
            <div>
                <label class="text-xs font-semibold text-gray-600 mb-1 block">Doc Type <span class="text-red-500">*</span></label>
                <select id="ndDocType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-400 focus:outline-none">
                    <option value="">-- Select --</option>
                    @foreach(['IR','RCA','CR Form','FSD','TD','UAT','MOM','BAST','Other'] as $dt)
                    <option value="{{ $dt }}">{{ $dt }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Body Text --}}
            <div>
                <label class="text-xs font-semibold text-gray-600 mb-1 block">Body Text</label>
                <textarea id="ndBodyText" rows="3" placeholder="Short description..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:ring-2 focus:ring-red-400 focus:outline-none"></textarea>
            </div>
            {{-- File --}}
            <div>
                <label class="text-xs font-semibold text-gray-600 mb-1 block">File
                    <span id="ndNoSupportHint" class="ml-1 text-orange-500 font-normal {{ ($deliverySupport ?? null) ? 'hidden' : '' }}">(ticket belum dihubungkan ke delivery support)</span>
                    @if(($deliverySupport ?? null) && empty($deliverySupport->onedrive_deliverable_folder_id))
                    <span class="ml-1 text-orange-500 font-normal">(generate Customer Deliverable folder di halaman support dulu)</span>
                    @endif
                </label>
                <div class="flex items-center gap-2">
                    <label class="flex-1 cursor-pointer flex items-center gap-2 px-3 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <span id="ndFileName" class="text-xs text-gray-400 truncate">Choose file...</span>
                        <input type="file" id="ndFile" class="hidden" onchange="updateFileName()">
                    </label>
                    <button onclick="document.getElementById('ndFile').value=''; document.getElementById('ndFileName').textContent='Choose file...'"
                        class="px-2 py-2 text-gray-400 hover:text-red-500 transition" title="Clear">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            {{-- Error --}}
            <p id="ndError" class="hidden text-xs text-red-600 font-medium"></p>
        </div>
        <div class="px-5 pb-5 flex gap-2">
            <button onclick="submitNewDoc()" id="ndSubmitBtn"
                class="flex-1 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                Save Document
            </button>
            <button onclick="closeNewDocModal()"
                class="px-4 py-2.5 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Cancel
            </button>
        </div>
    </div>
</div>

{{-- ==================== EDIT DELIVERABLE MODAL ==================== --}}
<div id="editDelivModal" class="hidden fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-sm font-bold text-gray-900">Edit Body Text</h3>
            <button onclick="closeEditDelivModal()" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-5 py-4 space-y-4">
            <input type="hidden" id="edDelivId">
            <div>
                <label class="text-xs font-semibold text-gray-600 mb-1 block">Body Text</label>
                <textarea id="edBodyText" rows="5" placeholder="Short description..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:ring-2 focus:ring-red-400 focus:outline-none"></textarea>
            </div>
            <p id="edError" class="hidden text-xs text-red-600 font-medium"></p>
        </div>
        <div class="px-5 pb-5 flex gap-2">
            <button onclick="submitEditDeliv()" id="edSubmitBtn"
                class="flex-1 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                Save
            </button>
            <button onclick="closeEditDelivModal()"
                class="px-4 py-2.5 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Cancel
            </button>
        </div>
    </div>
</div>

{{-- ==================== EDIT INTERNAL NOTE MODAL ==================== --}}
<div id="editNoteModal" class="hidden fixed inset-0 bg-black/50 z-[70] flex items-center justify-center p-4" onclick="if(event.target===this)closeEditNoteModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 flex flex-col" style="max-height:85vh">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 flex-shrink-0">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 text-[10px] bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded font-semibold">
                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg>
                    Edit Internal Note
                </span>
            </div>
            <button onclick="closeEditNoteModal()" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-5 pt-4 pb-2 flex-1 overflow-y-auto">
            <div class="border border-gray-200 rounded-lg bg-white">
                <div id="editNoteEditorContainer" style="min-height:120px;max-height:260px;overflow-y:auto"></div>
            </div>
            {{-- Existing attachments --}}
            <div id="editNoteExistingAtts" class="mt-3 space-y-0.5"></div>
            {{-- New attachments --}}
            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Add attachments</label>
                <input type="file" id="editNoteNewFiles" multiple
                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,.csv"
                    class="block w-full text-xs text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 cursor-pointer">
            </div>
            <p class="mt-2 text-[11px] text-gray-400">Note: edit and delete are only available within <strong>10 minutes</strong> of posting.</p>
        </div>
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-200 flex-shrink-0">
            <button onclick="closeEditNoteModal()"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Cancel
            </button>
            <button id="editNoteSaveBtn" onclick="saveEditNote()"
                class="px-4 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                Save
            </button>
        </div>
    </div>
</div>

<script>
// ==================== REUSABLE CONFIRM HELPER ====================
/**
 * Tampilkan modal konfirmasi custom (mengganti browser confirm()).
 * @param {string} message   - Isi pesan konfirmasi
 * @param {string} title     - Judul modal (default: 'Confirm')
 * @param {string} variant   - 'danger' (tombol merah) | 'primary' (tombol biru) | default abu-abu
 * @returns {Promise<boolean>}
 */
function showConfirm(message, title = 'Confirm', variant = 'default') {
    return new Promise(resolve => {
        const modal     = document.getElementById('confirmModal');
        const titleEl   = document.getElementById('confirmTitle');
        const msgEl     = document.getElementById('confirmMessage');
        const okBtn     = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');
        const iconWrap  = document.getElementById('confirmIconWrap');

        titleEl.textContent = title;
        msgEl.textContent   = message;

        // Icon & warna tombol sesuai variant
        if (variant === 'danger') {
            iconWrap.className = 'w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5 bg-red-100';
            iconWrap.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>`;
            okBtn.className = 'px-4 py-2 text-sm font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg transition';
        } else if (variant === 'primary') {
            iconWrap.className = 'w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5 bg-blue-100';
            iconWrap.innerHTML = `<svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/></svg>`;
            okBtn.className = 'px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition';
        } else {
            iconWrap.className = 'w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5 bg-gray-100';
            iconWrap.innerHTML = `<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/></svg>`;
            okBtn.className = 'px-4 py-2 text-sm font-semibold text-white bg-gray-700 hover:bg-gray-800 rounded-lg transition';
        }

        modal.classList.remove('hidden');

        function cleanup() {
            modal.classList.add('hidden');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBackdrop);
        }
        function onOk()      { cleanup(); resolve(true); }
        function onCancel()  { cleanup(); resolve(false); }
        function onBackdrop(e) { if (e.target === modal) onCancel(); }

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
        modal.addEventListener('click', onBackdrop);
    });
}

// ==================== DELIVERABLE JS ====================
const DELIV_TICKET_ID = {{ $ticket->ticket_id }};
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '{{ csrf_token() }}';
let deliverableData = [];

const DOC_TYPE_ROWS = ['IR', 'RCA', 'CR Form', 'FSD', 'TD', 'UAT', 'MOM', 'BAST', 'Other'];

// Batas ukuran file deliverable (sinkron dengan validasi server: 20 MB).
const DELIV_MAX_FILE_BYTES = 20 * 1024 * 1024;

// Parse response API secara aman. Jika server membalas HTML (mis. halaman error
// 413/419/500 dari nginx/PHP saat file melebihi batas upload), `res.json()` akan
// melempar "Unexpected token '<'". Helper ini mengubahnya jadi pesan yang jelas.
async function delivParseJson(res) {
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (_) {
        let msg;
        if (res.status === 413) {
            msg = 'File terlalu besar untuk server. Kecilkan ukuran file atau hubungi admin untuk menaikkan batas upload.';
        } else if (res.status === 419) {
            msg = 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';
        } else if (res.status >= 500) {
            msg = `Server error (${res.status}). Coba lagi atau hubungi admin.`;
        } else {
            msg = `Respons server tidak valid (${res.status}).`;
        }
        throw new Error(msg);
    }
}

async function openDeliverableModal() {
    document.getElementById('deliverableModal').classList.remove('hidden');
    await loadDeliverables();
}

function closeDeliverableModal() {
    document.getElementById('deliverableModal').classList.add('hidden');
}

async function loadDeliverables() {
    document.getElementById('deliverableBody').innerHTML =
        `<tr><td colspan="7" class="text-center py-10 text-gray-400">Loading...</td></tr>`;
    document.getElementById('deliverableFooter').innerHTML = '';

    try {
        const res  = await fetch(`/api/tickets/${DELIV_TICKET_ID}/deliverables`, {
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        deliverableData = json.data ?? [];
        renderDeliverableTable(deliverableData);

        // Update badge
        const badge = document.getElementById('delivBadgeCount');
        if (deliverableData.length > 0) {
            badge.textContent = deliverableData.length;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }

        const footer = document.getElementById('deliverableFooter');
        if (!json.has_folder) {
            footer.innerHTML = '<span class="text-orange-500">' + (json.folder_message || 'Folder belum siap untuk upload file.') + '</span>';
        } else {
            footer.innerHTML = json.folder_url
                ? `<a href="${json.folder_url}" target="_blank" rel="noopener" class="text-blue-500 hover:underline">Open OneDrive Folder</a>`
                : '';
        }
    } catch (e) {
        document.getElementById('deliverableBody').innerHTML =
            `<tr><td colspan="7" class="text-center py-8 text-red-500 text-xs">Failed to load: ${e.message}</td></tr>`;
    }
}

function renderDeliverableTable(data) {
    if (!data || data.length === 0) {
        document.getElementById('deliverableBody').innerHTML =
            `<tr><td colspan="7" class="text-center py-14 text-gray-400">
                <svg class="w-9 h-9 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                No documents yet. Click <strong>+ New Document</strong> to add one.
            </td></tr>`;
        return;
    }

    const rows = data.map(d => {
        const statusCls = d.status === 'Sended'
            ? 'bg-green-100 text-green-700'
            : 'bg-orange-100 text-orange-700';

        const fileCell = d.file_name
            ? (d.file_url
                ? `<a href="${escHtmlD(d.file_url)}" target="_blank" rel="noopener" class="text-blue-600 hover:underline truncate max-w-[160px] block">${escHtmlD(d.file_name)}</a>`
                : `<span class="text-gray-600 truncate max-w-[160px] block">${escHtmlD(d.file_name)}</span>`)
            : '<span class="text-gray-300">—</span>';

        const editBtn = d.status !== 'Sended'
            ? `<button onclick="editDeliverable(${d.id})"
                class="text-[10px] text-amber-600 hover:text-amber-800 font-semibold border border-amber-200 px-1.5 py-0.5 rounded hover:bg-amber-50 transition">
                Edit</button>`
            : '';

        const sendBtn = d.status !== 'Sended'
            ? `<button onclick="sendDeliverable(${d.id})"
                class="text-[10px] text-blue-600 hover:text-blue-800 font-semibold border border-blue-200 px-1.5 py-0.5 rounded hover:bg-blue-50 transition ml-1">
                Send to Customer</button>`
            : '';

        // Dokumen yang sudah dikirim ke customer tidak boleh dihapus.
        const delBtn = d.status !== 'Sended'
            ? `<button onclick="deleteDeliverable(${d.id})"
                class="text-[10px] text-red-500 hover:text-red-700 font-semibold border border-red-200 px-1.5 py-0.5 rounded hover:bg-red-50 transition ml-1">
                Delete</button>`
            : '';

        return `<tr class="border-b border-gray-100 hover:bg-gray-50/60">
            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">${escHtmlD(d.upload_date ?? '—')}</td>
            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">${escHtmlD(d.upload_time ?? '—')}</td>
            <td class="px-3 py-2">
                <span class="inline-block bg-indigo-50 border border-indigo-100 text-indigo-700 text-[10px] font-semibold px-1.5 py-0.5 rounded">${escHtmlD(d.doc_type)}</span>
            </td>
            <td class="px-3 py-2 text-gray-700 max-w-[220px]">
                <span class="line-clamp-2">${d.body_text ? escHtmlD(d.body_text) : '<span class="text-gray-300">—</span>'}</span>
            </td>
            <td class="px-3 py-2">${fileCell}</td>
            <td class="px-3 py-2 whitespace-nowrap">
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded ${statusCls}">${escHtmlD(d.status)}</span>
            </td>
            <td class="px-3 py-2 whitespace-nowrap">${editBtn}${sendBtn}${delBtn}</td>
        </tr>`;
    });

    document.getElementById('deliverableBody').innerHTML = rows.join('');
}

function escHtmlD(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// â"€â"€ New Document modal â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
function openNewDocModal() {
    document.getElementById('ndDocType').value = '';
    document.getElementById('ndBodyText').value = '';
    document.getElementById('ndFile').value = '';
    document.getElementById('ndFileName').textContent = 'Choose file...';
    document.getElementById('ndError').classList.add('hidden');
    document.getElementById('ndSubmitBtn').disabled = false;
    // Sinkronkan hint "belum dihubungkan ke delivery support" dengan status assign terkini
    // (assignedDsId di-update saat assign tanpa reload halaman).
    const noSupportHint = document.getElementById('ndNoSupportHint');
    if (noSupportHint) noSupportHint.classList.toggle('hidden', !!assignedDsId);
    document.getElementById('newDocModal').classList.remove('hidden');
}

function closeNewDocModal() {
    document.getElementById('newDocModal').classList.add('hidden');
}

function updateFileName() {
    const f = document.getElementById('ndFile').files[0];
    document.getElementById('ndFileName').textContent = f ? f.name : 'Choose file...';
}

async function submitNewDoc() {
    const docType  = document.getElementById('ndDocType').value.trim();
    const bodyText = document.getElementById('ndBodyText').value.trim();
    const file     = document.getElementById('ndFile').files[0];
    const errEl    = document.getElementById('ndError');

    errEl.classList.add('hidden');

    if (!docType) { errEl.textContent = 'Please select a Doc Type.'; errEl.classList.remove('hidden'); return; }

    // Cegah upload melebihi batas sebelum request dikirim, agar tidak berakhir
    // dengan halaman error HTML dari server (penyebab "Unexpected token '<'").
    if (file && file.size > DELIV_MAX_FILE_BYTES) {
        const mb = (file.size / 1024 / 1024).toFixed(1);
        errEl.textContent = `File terlalu besar (${mb} MB). Maksimal 20 MB.`;
        errEl.classList.remove('hidden');
        return;
    }

    const submitBtn = document.getElementById('ndSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving…';

    try {
        const form = new FormData();
        form.append('doc_type',  docType);
        if (bodyText) form.append('body_text', bodyText);
        if (file)     form.append('file', file);

        const res  = await fetch(`/api/tickets/${DELIV_TICKET_ID}/deliverables`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: form,
        });
        const json = await delivParseJson(res);
        if (!json.success) throw new Error(json.message);

        closeNewDocModal();
        await loadDeliverables();
        showToast('Document saved successfully.', 'success');
    } catch (e) {
        errEl.textContent = 'Error: ' + e.message;
        errEl.classList.remove('hidden');
        showToast('Upload failed: ' + e.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Document';
    }
}

async function sendDeliverable(id) {
    if (!await showConfirm('Mark this document as "Sended to Customer"?', 'Send to Customer')) return;
    try {
        const res  = await fetch(`/api/tickets/${DELIV_TICKET_ID}/deliverables/${id}/send`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        const json = await delivParseJson(res);
        if (!json.success) throw new Error(json.message);
        await loadDeliverables();
        showToast('Document sent to customer.', 'success');
    } catch (e) {
        showDelivError(e.message);
        showToast('Failed to send: ' + e.message, 'error');
    }
}

async function deleteDeliverable(id) {
    if (!await showConfirm('Delete this deliverable document? This cannot be undone.', 'Delete Document', 'danger')) return;
    try {
        const res  = await fetch(`/api/tickets/${DELIV_TICKET_ID}/deliverables/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        const json = await delivParseJson(res);
        if (!json.success) throw new Error(json.message);
        await loadDeliverables();
        showToast('Document deleted.', 'success');
    } catch (e) {
        showDelivError(e.message);
        showToast('Failed to delete: ' + e.message, 'error');
    }
}

// ── Edit body text ─────────────────────────────────────────────────
function editDeliverable(id) {
    const d = deliverableData.find(x => x.id === id);
    if (!d) return;
    document.getElementById('edDelivId').value = id;
    document.getElementById('edBodyText').value = d.body_text ?? '';
    document.getElementById('edError').classList.add('hidden');
    const btn = document.getElementById('edSubmitBtn');
    btn.disabled = false;
    btn.textContent = 'Save';
    document.getElementById('editDelivModal').classList.remove('hidden');
}

function closeEditDelivModal() {
    document.getElementById('editDelivModal').classList.add('hidden');
}

async function submitEditDeliv() {
    const id        = parseInt(document.getElementById('edDelivId').value);
    const bodyText  = document.getElementById('edBodyText').value.trim();
    const errEl     = document.getElementById('edError');
    const submitBtn = document.getElementById('edSubmitBtn');

    errEl.classList.add('hidden');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving…';

    try {
        const res = await fetch(`/api/tickets/${DELIV_TICKET_ID}/deliverables/${id}`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ body_text: bodyText }),
        });
        const json = await delivParseJson(res);
        if (!json.success) throw new Error(json.message);

        const idx = deliverableData.findIndex(x => x.id === id);
        if (idx !== -1) deliverableData[idx] = json.data;
        renderDeliverableTable(deliverableData);

        closeEditDelivModal();
        showToast('Body text updated.', 'success');
    } catch (e) {
        errEl.textContent = 'Error: ' + e.message;
        errEl.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
    }
}

document.getElementById('editDelivModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditDelivModal();
});

// Tampilkan error di footer modal deliverable (non-blocking)
function showDelivError(msg) {
    const el = document.getElementById('deliverableFooter');
    if (!el) return;
    el.innerHTML = `<span class="text-red-600 font-medium">âš  ${msg}</span>`;
    setTimeout(() => { if (el.querySelector('.text-red-600')) el.innerHTML = ''; }, 5000);
}

// Close deliverable modal on backdrop click
document.getElementById('deliverableModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeliverableModal();
});
document.getElementById('newDocModal').addEventListener('click', function(e) {
    if (e.target === this) closeNewDocModal();
});

// Load badge on page load
(async () => {
    try {
        const res  = await fetch(`/api/tickets/${DELIV_TICKET_ID}/deliverables`, {
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success && (json.data ?? []).length > 0) {
            const badge = document.getElementById('delivBadgeCount');
            badge.textContent = json.data.length;
            badge.classList.remove('hidden');
        }
    } catch {}
})();
</script>

@endsection



