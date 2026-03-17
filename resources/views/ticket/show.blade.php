@extends('dashboard')
@section('title', 'Ticket #' . $ticket->ticket_id)
@section('page-title', 'Support Ticket')
@section('page-subtitle', '#' . $ticket->ticket_number . ' - ' . Str::limit($ticket->description, 50))

{{-- Override sidebar with ticket inbox --}}
@section('sidebar-nav')
<div class="flex flex-col h-full">
    {{-- Back to Tickets --}}
    <div class="px-4 pt-4 pb-2">
        <a href="{{ route('ticket.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 transition-all text-sm">
            <i class="fas fa-arrow-left text-xs"></i>
            <span class="font-medium">Back to Tickets</span>
        </a>
    </div>

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

<div class="flex gap-6" style="height: calc(100vh - 140px); min-height: 500px;">
    {{-- Main Content: Conversation Thread --}}
    <div class="flex-1 flex flex-col bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Ticket Header --}}
        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-1 flex-wrap">
                    <h2 class="text-lg font-bold text-gray-900">{{ $ticket->description ?: 'No description' }}</h2>
                    <span class="text-sm text-gray-400 font-mono">#{{ $ticket->ticket_id }}</span>
                    @php
                        $statusColors = [
                            'open' => 'bg-blue-100 text-blue-700',
                            'in_progress' => 'bg-yellow-100 text-yellow-700',
                            'hold' => 'bg-orange-100 text-orange-700',
                            'cancel' => 'bg-gray-100 text-gray-500',
                            'closed' => 'bg-green-100 text-green-700',
                            'reply' => 'bg-purple-100 text-purple-700',
                        ];
                        $statusLabels = [
                            'open' => 'Open', 'in_progress' => 'In Progress', 'hold' => 'Hold',
                            'cancel' => 'Cancel', 'closed' => 'Closed', 'reply' => 'Reply',
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
                <div class="flex items-center gap-3 text-sm text-gray-500">
                    <span>{{ $ticket->customer?->basicData?->name_1 ?? 'Unknown Customer' }}</span>
                    <span class="text-gray-300">|</span>
                    <span>{{ $ticket->created_at->format('M d, Y h:i A') }}</span>
                    @if($ticket->employee)
                        <span class="text-gray-300">|</span>
                        <span>PIC: {{ $ticket->employee->basicData ? trim($ticket->employee->basicData->first_name . ' ' . ($ticket->employee->basicData->last_name ?? '')) : 'Assigned' }}</span>
                    @endif
                </div>
            </div>
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
                <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                    <div id="quillEditor" style="min-height: 100px; max-height: 200px; overflow-y: auto;"></div>
                </div>

                {{-- Attachment Preview Area (toggled via JS: style.display flex/none) --}}
                <div id="attachmentPreview" style="display:none" class="mt-2 flex-wrap gap-2"></div>

                {{-- Hidden file input (button injected into Quill toolbar via JS) --}}
                <input type="file" id="attachInput" multiple class="hidden"
                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,.csv">
                <div class="flex items-center justify-end mt-2 mb-1 gap-2">
                    <span id="attachCount" class="hidden text-xs text-blue-600 font-medium mr-auto"></span>
                    {{-- Send buttons --}}
                    @if($user->role->role_id == 1 || $user->role->role_id == 2)
                    <button onclick="sendReply('internal_note')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold rounded-lg hover:bg-amber-100 transition-all">
                        <i class="fas fa-lock text-[10px]"></i>
                        Internal Note
                    </button>
                    @endif
                    @if($ticket->channel === 'email')
                    <button onclick="sendReply('reply')" class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all shadow-sm">
                        <i class="fas fa-envelope text-[10px]"></i>
                        Send via Email
                    </button>
                    @else
                    <button onclick="sendReply('reply')" class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all shadow-sm">
                        <i class="fas fa-paper-plane text-[10px]"></i>
                        Send Reply
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Properties Sidebar --}}
    <div class="hidden xl:block w-72 bg-white rounded-xl border border-gray-200 shadow-sm overflow-y-auto flex-shrink-0">
        <div class="p-5">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Properties</h4>
                @if(in_array($user->role->role_id, [1, 6, 7]))
                <button onclick="saveAllProperties()" class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-700 text-white text-[10px] font-semibold rounded-md hover:bg-red-800 transition-all">
                    <i class="fas fa-save text-[9px]"></i>
                    Save All
                </button>
                @endif
            </div>
            <div class="space-y-3">
                {{-- Status --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Status</label>
                    <select id="detailStatus" {{ in_array($user->role->role_id, [1, 6, 7]) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="open" {{ $ticket->status == 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ $ticket->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="hold" {{ $ticket->status == 'hold' ? 'selected' : '' }}>Hold</option>
                        <option value="cancel" {{ $ticket->status == 'cancel' ? 'selected' : '' }}>Cancel</option>
                        <option value="closed" {{ $ticket->status == 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="reply" {{ $ticket->status == 'reply' ? 'selected' : '' }}>Reply</option>
                    </select>
                </div>

                {{-- Jarvies Status --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Jarvies Status</label>
                    <select id="detailJarviesStatus" {{ in_array($user->role->role_id, [1, 6, 7]) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="in process" {{ $ticket->jarvies_status == 'in process' ? 'selected' : '' }}>In Process</option>
                        <option value="author action" {{ $ticket->jarvies_status == 'author action' ? 'selected' : '' }}>Author Action</option>
                        <option value="proposed solution" {{ $ticket->jarvies_status == 'proposed solution' ? 'selected' : '' }}>Proposed Solution</option>
                        <option value="sent in to SAP" {{ $ticket->jarvies_status == 'sent in to SAP' ? 'selected' : '' }}>Sent in to SAP</option>
                        <option value="sent it to support" {{ $ticket->jarvies_status == 'sent it to support' ? 'selected' : '' }}>Sent it to Support</option>
                        <option value="closed" {{ $ticket->jarvies_status == 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>

                {{-- Priority --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Priority</label>
                    <select id="detailPriority" {{ in_array($user->role->role_id, [1, 6, 7]) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="Very High" {{ $ticket->ticket_priority == 'Very High' ? 'selected' : '' }}>Very High</option>
                        <option value="High" {{ $ticket->ticket_priority == 'High' ? 'selected' : '' }}>High</option>
                        <option value="Medium" {{ $ticket->ticket_priority == 'Medium' ? 'selected' : '' }}>Medium</option>
                        <option value="Low" {{ $ticket->ticket_priority == 'Low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                {{-- Ticket Type --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Ticket Type</label>
                    <select id="detailType" {{ in_array($user->role->role_id, [1, 6, 7]) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="" {{ !$ticket->ticket_type ? 'selected' : '' }}>-- Select Type --</option>
                        <option value="Incident" {{ $ticket->ticket_type == 'Incident' ? 'selected' : '' }}>Incident</option>
                        <option value="Service Request" {{ $ticket->ticket_type == 'Service Request' ? 'selected' : '' }}>Service Request</option>
                        <option value="Change Request" {{ $ticket->ticket_type == 'Change Request' ? 'selected' : '' }}>Change Request</option>
                        <option value="Consult" {{ $ticket->ticket_type == 'Consult' ? 'selected' : '' }}>Consult</option>
                    </select>
                </div>

                {{-- Agent (PIC) - Consultant Dropdown --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Agent (PIC)</label>
                    <select id="detailPIC" {{ in_array($user->role->role_id, [1, 6, 7]) ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="" {{ !$ticket->employee_id ? 'selected' : '' }}>-- Unassigned --</option>
                        @foreach($consultants as $consultant)
                            <option value="{{ $consultant['employee_id'] }}" {{ $ticket->employee_id == $consultant['employee_id'] ? 'selected' : '' }}>
                                {{ $consultant['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Team Members --}}
                @php
                    $canManageMembers = in_array($user->role->role_id, [1, 6, 7])
                        || ($user->role->role_id == 2 && $ticket->employee_id == $user->id);
                    $currentMemberIds = $ticket->members->pluck('employee_id')->toArray();
                @endphp
                <div class="pt-3 border-t border-gray-200">
                    <label class="text-xs font-semibold text-gray-500 mb-2 block">Team Members</label>

                    {{-- Members list --}}
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

                    {{-- Add member row (visible for Admin, Helpdesk, PIC) --}}
                    @if($canManageMembers)
                    <div class="flex gap-1.5">
                        <select id="addMemberSelect"
                                class="flex-1 min-w-0 px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Add member --</option>
                            @foreach($employees as $emp)
                                @if(!in_array($emp['employee_id'], $currentMemberIds) && $emp['employee_id'] != $ticket->employee_id)
                                    <option value="{{ $emp['employee_id'] }}">{{ $emp['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="button" onclick="addMemberBtn()"
                                class="px-2.5 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-all flex-shrink-0"
                                title="Add member">
                            <i class="fas fa-user-plus text-[10px]"></i>
                        </button>
                    </div>
                    @endif
                </div>

                {{-- Customer (read-only) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Customer</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->customer?->basicData?->name_1 ?? 'N/A' }}</p>
                </div>

                {{-- Info Tambahan (hanya tampil jika ada nilai) --}}
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

                {{-- Man Days --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Man Days</label>
                    <input type="number" id="detailManDays" value="{{ $ticket->man_days ?? '' }}" step="0.5" min="0" max="9999.99"
                        {{ in_array($user->role->role_id, [1, 6, 7]) ? '' : 'disabled' }}
                        class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white" placeholder="0.0">
                </div>

                {{-- ===== MANDAYS PROPOSAL SECTION ===== --}}
                @php
                    $mandaysStatus   = $ticket->mandays_proposal_status   ?? 'none';
                    $internalStatus  = $ticket->internal_mandays_status    ?? 'none';
                    $isPic           = $user->role->role_id == 2;
                    $isHelpdesk      = in_array($user->role->role_id, [6, 7]);
                    $isHead          = $user->role->role_id == 5;
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
                        'pic_draft'        => 'Update Draft',
                        'pending_helpdesk' => 'View Proposal',
                        'sent_to_chat'     => 'View Proposal',
                        'approved'         => 'Submit New Proposal',
                        default            => 'Propose Mandays',
                    };
                    $picInternalLabel = match($internalStatus) {
                        'draft'        => 'Update Internal Draft',
                        'pending_head' => 'View Internal Proposal',
                        'approved'     => 'Update Internal Mandays',
                        'rejected'     => 'Revise Internal Mandays',
                        default        => 'Propose Internal Mandays',
                    };
                @endphp

                {{-- PIC: Customer Mandays --}}
                @if($isPic)
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Customer Mandays</label>
                        <span id="mandaysBadge" class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $mBadgeClass }}">{{ $mBadgeLabel }}</span>
                    </div>
                    <button onclick="openPicMandaysModal()" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-all">
                        <i class="fas fa-calculator text-[10px]"></i>
                        {{ $picMandaysLabel }}
                    </button>
                </div>
                {{-- PIC: Internal Mandays --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Internal Mandays</label>
                        <span id="internalBadge" class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $iBadgeClass }}">{{ $iBadgeLabel }}</span>
                    </div>
                    <button onclick="openInternalMandaysModal()" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-teal-600 text-white text-xs font-semibold rounded-lg hover:bg-teal-700 transition-all">
                        <i class="fas fa-users text-[10px]"></i>
                        {{ $picInternalLabel }}
                    </button>
                </div>
                @endif

                {{-- Helpdesk: Customer Mandays Review --}}
                @if($isHelpdesk)
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Mandays Review</label>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $mBadgeClass }}">{{ $mBadgeLabel }}</span>
                    </div>
                    @if(in_array($mandaysStatus, ['pending_helpdesk', 'sent_to_chat', 'approved', 'canceled']))
                    <button onclick="openHdMandaysModal()" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-all">
                        <i class="fas fa-clipboard-check text-[10px]"></i>
                        Review Mandays Proposal
                    </button>
                    @else
                    <p class="text-[11px] text-gray-400 italic text-center py-1">
                        {{ $mandaysStatus === 'none' ? 'Waiting for PIC proposal' : 'PIC is drafting proposal...' }}
                    </p>
                    @endif
                </div>
                @endif

                {{-- Head of Support: Internal Mandays --}}
                @if($isHead && in_array($internalStatus, ['pending_head', 'approved', 'rejected']))
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-500">Internal Mandays</label>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $iBadgeClass }}">{{ $iBadgeLabel }}</span>
                    </div>
                    <button onclick="openHeadInternalModal()" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-teal-600 text-white text-xs font-semibold rounded-lg hover:bg-teal-700 transition-all">
                        <i class="fas fa-user-check text-[10px]"></i>
                        Review Internal Proposal
                    </button>
                </div>
                @endif
                {{-- ===== END MANDAYS SECTION ===== --}}

                {{-- Start Date (read-only) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Start Date</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->start_date ? \Carbon\Carbon::parse($ticket->start_date)->format('M d, Y') : 'Not started' }}</p>
                </div>

                {{-- Due Date (read-only) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Due Date</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->end_date ? \Carbon\Carbon::parse($ticket->end_date)->format('M d, Y') : 'No due date' }}</p>
                </div>

                {{-- Created (read-only) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Created</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->created_at->format('M d, Y h:i A') }}</p>
                </div>

                {{-- Admin/Helpdesk Actions --}}
                @if(in_array($user->role->role_id, [1, 6, 7]))
                <div class="pt-3 border-t border-gray-200 space-y-2">
                    <button onclick="openAssignSupportModal()" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-all">
                        <i class="fas fa-headset text-[10px]"></i>
                        Assign to Delivery Support
                    </button>
                    @if($user->role->role_id == 1)
                    <button onclick="deleteTicket()" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition-all">
                        <i class="fas fa-trash text-[10px]"></i>
                        Delete Ticket
                    </button>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
/* Message Bubbles */
.message-bubble { max-width: 85%; }
.message-bubble.customer { background: #f3f4f6; border-radius: 12px 12px 12px 4px; }
.message-bubble.employee { background: #eff6ff; border-radius: 12px 12px 4px 12px; }
.message-bubble.internal-note { background: #fef9c3; border: 1px dashed #f59e0b; border-radius: 8px; }

/* Email HTML body rendering — scoped agar tidak bocor ke luar bubble */
.email-html-body { word-break: break-word; }
.email-html-body p  { margin-bottom: 0.3rem; }
.email-html-body a  { color: #2563eb; text-decoration: underline; }
.email-html-body ul, .email-html-body ol { padding-left: 1.25rem; margin-bottom: 0.4rem; }
.email-html-body blockquote { border-left: 3px solid #d1d5db; padding-left: 0.75rem; color: #6b7280; margin: 0.25rem 0; }
.email-html-body img { max-width: 100%; height: auto; border-radius: 6px; }
.email-html-body table { border-collapse: collapse; font-size: 12px; max-width: 100%; }
.email-html-body td, .email-html-body th { border: 1px solid #e5e7eb; padding: 4px 8px; }

/* Quill Editor Overrides */
.ql-toolbar.ql-snow { border: none !important; border-bottom: 1px solid #e5e7eb !important; padding: 4px 8px !important; background: #f9fafb; }
.ql-container.ql-snow { border: none !important; font-size: 13px; }
.ql-editor { min-height: 80px; max-height: 180px; overflow-y: auto; padding: 8px 12px; }
.ql-editor.ql-blank::before { font-style: normal; color: #9ca3af; font-size: 13px; }

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

/* Sidebar ticket items */
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
</style>

{{-- Assign to Delivery Support Modal --}}
@if(in_array($user->role->role_id, [1, 6, 7]))
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
            <button onclick="closeAssignSupportModal()" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-300 transition-all">
                Cancel
            </button>
            <button onclick="confirmAssignSupport()" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-all">
                <i class="fas fa-check mr-1"></i> Assign
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
                <button onclick="closeAssignSuccessModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                    Stay Here
                </button>
                <button id="btnViewDeliverySupport" onclick="goToDeliverySupport()" class="flex-1 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
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
            {{-- Rejection info --}}
            <div id="picRejectionInfo" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
            {{-- Activity rows management --}}
            <div id="picMandaysTableWrap" class="overflow-x-auto">
                <div id="picMandaysLoading" class="py-8 text-center text-gray-400 text-sm">Loading...</div>
                <table id="picMandaysTable" class="hidden w-full text-xs border-collapse">
                    <thead id="picMandaysHead"></thead>
                    <tbody id="picMandaysBody"></tbody>
                    <tfoot id="picMandaysFoot"></tfoot>
                </table>
            </div>
            {{-- Add activity row + add module column --}}
            <div id="picAddRowWrap" class="hidden mt-3 flex flex-wrap gap-2">
                <div class="flex gap-2 flex-1 min-w-[200px]">
                    <input id="picNewActivity" type="text" placeholder="Activity (e.g. Analisa)" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button onclick="picAddActivityRow()" class="px-3 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 whitespace-nowrap">+ Row</button>
                </div>
            </div>
        </div>
        <div id="picMandaysFooter" class="px-6 py-4 border-t border-gray-200 flex justify-between items-center flex-shrink-0 gap-3">
            <div class="text-xs text-gray-500">Total: <strong id="picTotalDisplay">0</strong> mandays</div>
            <div class="flex gap-2">
                <button id="picBtnSaveDraft" onclick="picSaveDraft()" class="px-4 py-2 bg-gray-600 text-white text-xs font-semibold rounded-lg hover:bg-gray-700 transition-all">Save Draft</button>
                <button id="picBtnSubmit" onclick="picSubmitDraft()" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-all">Submit to Helpdesk</button>
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
                <p class="text-xs text-gray-500 mt-0.5">Status: <span id="internalPicStatusLabel">—</span></p>
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
                        <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200">Mandays</th>
                    </tr>
                </thead>
                <tbody id="internalBody"></tbody>
            </table>
            <div class="mt-4">
                <label class="text-xs font-semibold text-gray-600">Notes for Head of Support</label>
                <textarea id="internalNotes" rows="2" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Optional notes..."></textarea>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center flex-shrink-0 gap-3">
            <div class="text-xs text-gray-500">Total: <strong id="internalTotalDisplay">0</strong> mandays</div>
            <div class="flex gap-2">
                <button id="internalBtnSave" onclick="internalPicSaveDraft()" class="px-4 py-2 bg-gray-600 text-white text-xs font-semibold rounded-lg hover:bg-gray-700">Save Draft</button>
                <button id="internalBtnSubmit" onclick="internalPicSubmit()" class="px-4 py-2 bg-teal-600 text-white text-xs font-semibold rounded-lg hover:bg-teal-700">Submit to Head</button>
            </div>
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
                <div id="hdNotesWrap">
                    <label class="text-xs font-semibold text-gray-600">Helpdesk Notes</label>
                    <textarea id="hdNotes" rows="2" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Optional internal notes..."></textarea>
                </div>
            </div>
        </div>
        <div id="hdMandaysFooter" class="px-6 py-4 border-t border-gray-200 flex flex-wrap gap-2 justify-end flex-shrink-0">
            <button id="hdBtnSendToChat"    onclick="hdSubmitToChat()"    class="hidden px-4 py-2 bg-purple-600 text-white text-xs font-semibold rounded-lg hover:bg-purple-700">Send to Chat</button>
            <button id="hdBtnReviseResend"  onclick="hdReviseResend()"    class="hidden px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700">Revise &amp; Resend</button>
            <button id="hdBtnApprove"       onclick="hdApprove()"         class="hidden px-4 py-2 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700">Approve</button>
            <button id="hdBtnCancel"        onclick="hdCancel()"          class="hidden px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700">Cancel Proposal</button>
            <button id="hdBtnNewProposal"   onclick="hdCreateNewProposal()" class="hidden px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">Create New Proposal</button>
        </div>
    </div>
</div>
@endif

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
            <div id="headInternalContent" class="hidden">
                <table class="w-full text-xs border-collapse mb-4">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Name</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200">Mandays</th>
                        </tr>
                    </thead>
                    <tbody id="headInternalBody"></tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-bold">
                            <td class="px-3 py-2 border border-gray-200 text-right text-xs">Total</td>
                            <td class="px-3 py-2 border border-gray-200 text-center" id="headInternalTotal">0</td>
                        </tr>
                    </tfoot>
                </table>
                <div id="headProposedBy" class="text-xs text-gray-500 mb-1"></div>
                <div id="headInternalNoteWrap" class="hidden p-3 bg-gray-50 rounded-lg text-xs text-gray-600 mb-3"></div>
                {{-- Reject reason input --}}
                <div id="headRejectWrap" class="hidden mt-3">
                    <label class="text-xs font-semibold text-gray-600">Rejection Reason</label>
                    <textarea id="headRejectReason" rows="2" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Explain why you reject this proposal..."></textarea>
                </div>
            </div>
        </div>
        <div id="headInternalFooter" class="px-6 py-4 border-t border-gray-200 flex gap-2 justify-end flex-shrink-0">
            <button id="headBtnApprove" onclick="headInternalApprove()" class="px-4 py-2 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700">Approve</button>
            <button id="headBtnToggleReject" onclick="headToggleReject()" class="px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700">Reject</button>
            <button id="headBtnConfirmReject" onclick="headInternalReject()" class="hidden px-4 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900">Confirm Reject</button>
        </div>
    </div>
</div>
@endif
{{-- ===== END MANDAYS MODALS ===== --}}

<script>
    const ticketId      = {{ $ticket->ticket_id }};
    const userRole      = {{ $user->role->role_id ?? 0 }};
    const ticketCustomerId = {{ $ticket->customer_id ?? 'null' }};
    const ticketChannel = @json($ticket->channel ?? 'web');
    let quillEditor     = null;
    let allSidebarTickets  = [];
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
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, false] }],
                    ['link'],
                    ['clean']
                ]
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

        loadMessages();
        loadSidebarTickets();
        markMessagesRead();
        startMessagePolling();
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

    // ── Pilih konten pesan: HTML dari email atau plain text dari web ────────────
    function messageContent(msg) {
        // Email dengan HTML body → render HTML mentah (sudah disanitasi oleh extractReplyBody)
        if (msg.channel === 'email' && msg.message_html) {
            return `<div class="message-content text-sm text-gray-700 email-html-body">${msg.message_html}</div>`;
        }
        // Web reply (Quill HTML) atau plain text — guard null untuk reply tanpa teks (file only)
        if (!msg.message_body) return '';
        return `<div class="message-content text-sm text-gray-700">${msg.message_body}</div>`;
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
        const ccList   = msg.cc_emails || [];
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
            return `
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-200 text-amber-800 text-xs font-bold">${initials}</div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            <span class="text-[10px] bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded font-semibold">Internal Note</span>
                            ${channelBadge}
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        <div class="message-bubble internal-note p-3">
                            ${messageContent(msg)}
                            ${attachmentsHtml}
                        </div>
                    </div>
                </div>`;
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
        const date = @json($ticket->created_at->format('M d, Y h:i A'));
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
                showNotification(`${file.name} terlalu besar (maks 10 MB)`, 'error');
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
    async function sendReply(messageType) {
        const htmlContent  = quillEditor.root.innerHTML;
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

            if (hasFiles) {
                // Kirim sebagai multipart/form-data
                // Jangan set Content-Type manual — browser otomatis tambahkan boundary yang benar
                const formData = new FormData();
                formData.append('message_body', htmlContent);
                formData.append('message_type', messageType);
                selectedFiles.forEach(file => formData.append('attachments[]', file));
                requestBody = formData;
            } else {
                headers['Content-Type'] = 'application/json';
                requestBody = JSON.stringify({ message_body: htmlContent, message_type: messageType });
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
    async function loadSidebarTickets() {
        try {
            let endpoint = '/api/tickets';
            if (userRole === 3) endpoint = '/api/tickets/my';

            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();

            document.getElementById('sidebarLoading').classList.add('hidden');

            if (data.success) {
                allSidebarTickets = data.data.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                renderSidebarTickets(allSidebarTickets);
            }
        } catch (error) {
            document.getElementById('sidebarLoading').classList.add('hidden');
        }
    }

    function renderSidebarTickets(tickets) {
        const list = document.getElementById('sidebarTicketList');
        list.innerHTML = tickets.map(t => {
            const isActive = t.ticket_id === ticketId;
            const customerName = t.customer?.customer_name || 'Unknown';
            const desc = t.description || 'No description';
            const shortDesc = desc.length > 40 ? desc.substring(0, 40) + '...' : desc;
            const timeAgo = formatTimeAgo(new Date(t.created_at));

            const prioColors = { 'Very High': 'bg-purple-500', 'High': 'bg-red-400', 'Medium': 'bg-blue-400', 'Low': 'bg-green-400' };
            const prioDot = prioColors[t.ticket_priority] || 'bg-gray-400';

            return `
                <a href="/ticket/${t.ticket_id}" class="sidebar-ticket-item ${isActive ? 'active' : ''}">
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="text-xs font-semibold text-gray-800 truncate max-w-[140px]">${customerName}</span>
                        <span class="text-[10px] text-gray-400">${timeAgo}</span>
                    </div>
                    <p class="text-[11px] text-gray-500 truncate mb-1">#${t.ticket_id} - ${shortDesc}</p>
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full ${prioDot}"></div>
                        <span class="text-[10px] text-gray-400">${t.ticket_priority || 'Medium'}</span>
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
            (t.ticket_id && t.ticket_id.toString().includes(term)) ||
            (t.description && t.description.toLowerCase().includes(term)) ||
            (t.customer?.customer_name && t.customer.customer_name.toLowerCase().includes(term))
        );
        renderSidebarTickets(filtered);
    }

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
        const manDays = document.getElementById('detailManDays').value;

        try {
            // Update status via dedicated endpoint
            const statusResponse = await fetch(`/api/tickets/${ticketId}/update-status`, {
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
                man_days: manDays ? parseFloat(manDays) : null,
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
                // Refresh header info after a short delay
                setTimeout(() => location.reload(), 800);
            } else {
                showNotification(result.message || 'Failed to save', 'error');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        }
    }

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
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        if (diffMins < 1) return 'now';
        if (diffMins < 60) return diffMins + 'm';
        if (diffHours < 24) return diffHours + 'h';
        if (diffDays < 7) return diffDays + 'd';
        return date.toLocaleDateString('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric' });
    }

    function showNotification(message, type = 'info') {
        const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-xl shadow-xl z-[100] transition-opacity duration-300 font-medium text-sm`;
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

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
            } else {
                select.innerHTML = '<option value="">Failed to load</option>';
            }
        } catch (error) {
            console.error('Error loading delivery supports:', error);
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
        const supportName = document.getElementById('newSupportName').value.trim();
        const supportMethod = document.getElementById('newSupportMethod').value;

        if (!supportName) {
            showNotification('Please enter a support name', 'error');
            return;
        }

        try {
            const response = await fetch('/api/tickets/' + ticketId + '/create-delivery-support', {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: supportName,
                    support_method: supportMethod
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Delivery support created and ticket assigned!', 'success');
                closeAssignSupportModal();
                if (data.data?.support_id) {
                    showAssignSuccessModal(`/delivery/support/${data.data.support_id}`);
                }
            } else {
                showNotification(data.message || 'Failed to create delivery support', 'error');
            }
        } catch (error) {
            console.error('Error creating delivery support:', error);
            showNotification('Error: ' + error.message, 'error');
        }
    }

    // Close modal on outside click
    document.getElementById('assignSupportModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAssignSupportModal();
        }
    });

    // ==================== ASSIGN SUCCESS MODAL ====================
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

    document.getElementById('assignSuccessModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAssignSuccessModal();
        }
    });

    // ==================== MANDAYS — SHARED ====================
    let picMandaysModules  = [];
    let picDraftData       = null;
    let picReadOnly        = false;
    let internalPicData    = null;
    let internalPicPeople  = [];
    let internalPicReadOnly= false;

    const MANDAYS_API = (path) => `/api/tickets/${ticketId}/mandays/${path}`;

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
            picReadOnly       = ['pending_helpdesk', 'sent_to_chat'].includes(status);

            document.getElementById('picMandaysVersion').textContent      = picDraftData?.version ?? 'New';
            document.getElementById('picMandaysStatusLabel').textContent  = status;

            if (picDraftData?.rejection_reason) {
                const el = document.getElementById('picRejectionInfo');
                el.textContent = 'Rejection: ' + picDraftData.rejection_reason;
                el.classList.remove('hidden');
            }

            // Build valueMap from existing details
            const valueMap = {};
            (picDraftData?.details || []).forEach(d => {
                const act = d.activity || 'General';
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
            // If no activities, start with default
            if (Object.keys(valueMap).length === 0) valueMap['General'] = {};

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
                        ${picReadOnly ? 'readonly' : ''} oninput="picUpdateTotal()">
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
        if (!picReadOnly) document.getElementById('picAddRowWrap').classList.remove('hidden');

        // Buttons visibility
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
        picRenderMatrix(currentMap);
    }

    function picRemoveActivityRow(act) {
        const currentMap = picGetCurrentValueMap();
        delete currentMap[act];
        if (Object.keys(currentMap).length === 0) currentMap['General'] = {};
        picRenderMatrix(currentMap);
    }

    function picAddModuleCol() {
        const input = document.getElementById('picNewModule');
        const name = input.value.trim();
        if (!name) { showNotification('Enter a module name', 'warning'); return; }
        if (picMandaysModules.includes(name)) { showNotification('Module already exists', 'warning'); return; }
        input.value = '';
        picMandaysModules.push(name);
        picRenderMatrix(picGetCurrentValueMap());
    }

    function picRemoveModuleCol(mod) {
        picMandaysModules = picMandaysModules.filter(m => m !== mod);
        if (picMandaysModules.length === 0) { showNotification('At least one module is required', 'warning'); picMandaysModules.push(mod); return; }
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
        return { details };
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
                picMandaysUpdateSidebarBadge(data.ticket_mandays_status);
                picDraftData = data.data;
                document.getElementById('picMandaysVersion').textContent = picDraftData?.version ?? '—';
            } else {
                showNotification(data.message || 'Failed to save', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Save Draft'; }
    }

    async function picSubmitDraft() {
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

    // Close on backdrop click
    document.getElementById('picMandaysModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePicMandaysModal();
    });

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
            internalPicReadOnly = status === 'pending_head';

            document.getElementById('internalPicStatusLabel').textContent = status;
            document.getElementById('internalNotes').value = internalPicData?.notes || '';
            document.getElementById('internalNotes').readOnly = internalPicReadOnly;

            if (internalPicData?.rejection_reason) {
                const el = document.getElementById('internalRejectionInfo');
                el.textContent = 'Rejected: ' + internalPicData.rejection_reason;
                el.classList.remove('hidden');
            }

            // Build valueMap from existing details or prefill
            const valueMap = {};
            if (internalPicData?.details?.length) {
                internalPicData.details.forEach(d => {
                    if (!valueMap[d.employee_id]) valueMap[d.employee_id] = {};
                    valueMap[d.employee_id][d.module] = d.mandays;
                });
            } else if (data.prefill_data) {
                // Distribute prefill to first person who has each module
                Object.entries(data.prefill_data).forEach(([mod, total]) => {
                    const person = internalPicPeople.find(p => p.modules.includes(mod));
                    if (person) {
                        if (!valueMap[person.employee_id]) valueMap[person.employee_id] = {};
                        valueMap[person.employee_id][mod] = total;
                    }
                });
            }

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
            // Sum all module values for this person as prefill
            const existingMods = valueMap[person.employee_id] || {};
            const total = Object.values(existingMods).reduce((sum, v) => sum + (parseFloat(v) || 0), 0);
            const val = total > 0 ? total : '';
            html += `<tr>
                <td class="px-3 py-2 border border-gray-200 font-medium text-gray-700">${person.name}</td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="internal-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-teal-50 ${internalPicReadOnly ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}"
                        data-employee="${person.employee_id}" value="${val}"
                        ${internalPicReadOnly ? 'readonly' : ''} oninput="internalUpdateTotal()">
                </td>
            </tr>`;
        });
        document.getElementById('internalBody').innerHTML = html;
        document.getElementById('internalTable').classList.remove('hidden');

        document.getElementById('internalBtnSave').classList.toggle('hidden', internalPicReadOnly);
        document.getElementById('internalBtnSubmit').classList.toggle('hidden', internalPicReadOnly);
        internalUpdateTotal();
    }

    function internalUpdateTotal() {
        let total = 0;
        document.querySelectorAll('.internal-cell').forEach(inp => total += parseFloat(inp.value) || 0);
        document.getElementById('internalTotalDisplay').textContent = total.toFixed(1);
    }

    function internalPicGetPayload() {
        const details = [];
        document.querySelectorAll('.internal-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            if (v > 0) details.push({ employee_id: parseInt(inp.dataset.employee), mandays: v });
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

    document.getElementById('picInternalModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePicInternalModal();
    });

    // ==================== HELPDESK: CUSTOMER MANDAYS REVIEW ====================
    async function openHdMandaysModal() {
        const modal = document.getElementById('hdMandaysModal');
        if (!modal) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('hdMandaysLoading').classList.remove('hidden');
        document.getElementById('hdMandaysContent').classList.add('hidden');
        document.getElementById('hdMandaysBanner').classList.add('hidden');

        try {
            const [modRes, draftRes] = await Promise.all([
                fetch(MANDAYS_API('modules'), { headers: getHeaders(), credentials: 'same-origin' }),
                fetch(MANDAYS_API('hd-draft'), { headers: getHeaders(), credentials: 'same-origin' }),
            ]);
            const modData   = await modRes.json();
            const draftData = await draftRes.json();
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
                return;
            }

            // ---- State-specific banner & UI ----
            const banner = document.getElementById('hdMandaysBanner');
            const rejWrap = document.getElementById('hdRejectionReasonWrap');
            const notesWrap = document.getElementById('hdNotesWrap');

            banner.className = 'hidden mb-4 rounded-lg px-4 py-3 text-sm font-medium items-start gap-3';
            rejWrap.classList.add('hidden');

            const isCustomerRejected = status === 'pending_helpdesk' && !!proposal.rejection_reason;
            const isPicSubmitted     = status === 'pending_helpdesk' && !proposal.rejection_reason;
            const isSentToChat       = status === 'sent_to_chat';
            const isApproved         = status === 'approved';
            const isCanceled         = status === 'canceled';

            if (isApproved) {
                const ts = proposal.customer_response_at
                    ? new Date(proposal.customer_response_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) + ' WIB'
                    : '';
                banner.innerHTML = `<span class="text-green-700 text-base mt-0.5">✓</span>
                    <div><p class="font-semibold text-green-800">Approved by Customer</p>
                    ${ts ? `<p class="text-xs font-normal text-green-700 mt-0.5">${ts}</p>` : ''}</div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-green-50', 'border', 'border-green-200', 'text-green-800');
                notesWrap.classList.add('hidden');
            } else if (isCustomerRejected) {
                const ts = proposal.customer_response_at
                    ? new Date(proposal.customer_response_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) + ' WIB'
                    : '';
                banner.innerHTML = `<span class="text-red-700 text-base mt-0.5">✕</span>
                    <div><p class="font-semibold text-red-800">Rejected by Customer</p>
                    ${ts ? `<p class="text-xs font-normal text-red-700 mt-0.5">${ts}</p>` : ''}</div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-red-50', 'border', 'border-red-200', 'text-red-800');
                document.getElementById('hdRejectionReasonText').textContent = proposal.rejection_reason;
                rejWrap.classList.remove('hidden');
                notesWrap.classList.remove('hidden');
            } else if (isSentToChat) {
                notesWrap.classList.add('hidden');
            } else if (isCanceled) {
                notesWrap.classList.add('hidden');
            } else {
                notesWrap.classList.remove('hidden');
            }

            document.getElementById('hdNotes').value = proposal?.notes || '';

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
            console.error(e);
            showNotification('Failed to load proposal', 'error');
        } finally {
            document.getElementById('hdMandaysLoading').classList.add('hidden');
        }
    }

    function closeHdMandaysModal() {
        const modal = document.getElementById('hdMandaysModal');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    }

    function hdUpdateTotal() {
        const colTotals = {};
        document.querySelectorAll('.hd-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            colTotals[inp.dataset.module] = (colTotals[inp.dataset.module] || 0) + v;
        });
        Object.entries(colTotals).forEach(([m, t]) => {
            const el = document.getElementById(`hdColTotal_${m}`);
            if (el) el.textContent = t.toFixed(1);
        });
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
                body: JSON.stringify({ details, notes: document.getElementById('hdNotes').value }),
            });
        }
        const res = await fetch(MANDAYS_API(endpoint), {
            method, headers: getHeaders(), credentials: 'same-origin',
            body: JSON.stringify(extraBody),
        });
        return res.json();
    }

    async function hdSubmitToChat() {
        try {
            const data = await hdSaveAndAction('hd-draft/submit-chat');
            if (data.success) {
                showNotification('Sent to customer via email!', 'success');
                closeHdMandaysModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
    }
    async function hdReviseResend() {
        try {
            const data = await hdSaveAndAction('hd-draft/submit-chat');
            if (data.success) {
                showNotification('Revised proposal sent to customer!', 'success');
                closeHdMandaysModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
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
    async function hdCancel() {
        try {
            const data = await hdSaveAndAction('hd-draft/cancel');
            if (data.success) {
                showNotification('Proposal canceled.', 'success');
                closeHdMandaysModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
    }
    async function hdCreateNewProposal() {
        // Canceled → PIC needs to create new draft; just close and inform
        showNotification('Ask the PIC to submit a new draft.', 'info');
        closeHdMandaysModal();
    }

    document.getElementById('hdMandaysModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeHdMandaysModal();
    });

    // ==================== HEAD OF SUPPORT: INTERNAL MANDAYS ====================
    async function openHeadInternalModal() {
        const modal = document.getElementById('headInternalModal');
        if (!modal) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('headInternalLoading').classList.remove('hidden');
        document.getElementById('headInternalContent').classList.add('hidden');
        document.getElementById('headRejectWrap').classList.add('hidden');
        document.getElementById('headBtnConfirmReject').classList.add('hidden');

        try {
            const res  = await fetch(MANDAYS_API('internal'), { headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            const proposal = data.data;
            const status   = data.internal_mandays_status || 'none';

            document.getElementById('headInternalStatusLabel').textContent = status;

            if (!proposal) {
                document.getElementById('headInternalContent').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No proposal found.</p>';
                document.getElementById('headInternalContent').classList.remove('hidden');
                return;
            }

            // Aggregate details by employee (sum mandays across modules)
            const empMap = {};
            (proposal.details || []).forEach(d => {
                const name = d.employee_name || '—';
                empMap[name] = (empMap[name] || 0) + parseFloat(d.mandays || 0);
            });
            let bodyHtml = '';
            Object.entries(empMap).forEach(([name, total]) => {
                bodyHtml += `<tr>
                    <td class="px-3 py-2 border border-gray-200 text-xs">${name}</td>
                    <td class="px-3 py-2 border border-gray-200 text-xs text-center">${total.toFixed(1)}</td>
                </tr>`;
            });
            document.getElementById('headInternalBody').innerHTML = bodyHtml;
            document.getElementById('headInternalTotal').textContent = (proposal.total_mandays||0).toFixed(1);

            if (proposal.proposed_by) {
                document.getElementById('headProposedBy').textContent = 'Proposed by: ' + proposal.proposed_by;
            }
            if (proposal.notes) {
                const nw = document.getElementById('headInternalNoteWrap');
                nw.textContent = 'Notes: ' + proposal.notes;
                nw.classList.remove('hidden');
            }

            // Show approve/reject only if pending
            const isPending = status === 'pending_head';
            document.getElementById('headBtnApprove').classList.toggle('hidden', !isPending);
            document.getElementById('headBtnToggleReject').classList.toggle('hidden', !isPending);

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

    function headToggleReject() {
        const wrap = document.getElementById('headRejectWrap');
        const btn  = document.getElementById('headBtnConfirmReject');
        const isHidden = wrap.classList.contains('hidden');
        wrap.classList.toggle('hidden', !isHidden);
        btn.classList.toggle('hidden', !isHidden);
    }

    async function headInternalApprove() {
        const btn = document.getElementById('headBtnApprove');
        btn.disabled = true; btn.textContent = 'Approving...';
        try {
            const res  = await fetch(MANDAYS_API('internal/approve'), { method: 'POST', headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                showNotification('Internal proposal approved!', 'success');
                internalUpdateSidebarBadge?.(data.internal_mandays_status);
                closeHeadInternalModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
        finally { btn.disabled = false; btn.textContent = 'Approve'; }
    }

    async function headInternalReject() {
        const reason = document.getElementById('headRejectReason').value.trim();
        if (!reason) { showNotification('Please provide a rejection reason', 'warning'); return; }
        const btn = document.getElementById('headBtnConfirmReject');
        btn.disabled = true; btn.textContent = 'Rejecting...';
        try {
            const res  = await fetch(MANDAYS_API('internal/reject'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify({ rejection_reason: reason }),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Proposal rejected.', 'success');
                internalUpdateSidebarBadge?.(data.internal_mandays_status);
                closeHeadInternalModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
        finally { btn.disabled = false; btn.textContent = 'Confirm Reject'; }
    }

    document.getElementById('headInternalModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeHeadInternalModal();
    });
</script>
@endsection
