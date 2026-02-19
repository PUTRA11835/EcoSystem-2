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
    <div id="sidebarTicketList" class="flex-1 overflow-y-auto px-2 pb-4 space-y-0.5">
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
            <div class="px-4 pt-3 pb-2">
                <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                    <div id="quillEditor" style="min-height: 100px; max-height: 200px; overflow-y: auto;"></div>
                </div>
                <div class="flex items-center justify-between mt-2 mb-1">
                    <div class="text-xs text-gray-400">Rich text editor</div>
                    <div class="flex items-center gap-2">
                        @if($user->role->role_id == 1 || $user->role->role_id == 2)
                        <button onclick="sendReply('internal_note')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold rounded-lg hover:bg-amber-100 transition-all">
                            <i class="fas fa-lock text-[10px]"></i>
                            Internal Note
                        </button>
                        @endif
                        <button onclick="sendReply('reply')" class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800 transition-all shadow-sm">
                            <i class="fas fa-paper-plane text-[10px]"></i>
                            Send Reply
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Properties Sidebar --}}
    <div class="hidden xl:block w-72 bg-white rounded-xl border border-gray-200 shadow-sm overflow-y-auto flex-shrink-0">
        <div class="p-5">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wide">Properties</h4>
                @if($user->role->role_id == 1)
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
                    <select id="detailStatus" {{ $user->role->role_id == 1 ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
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
                    <select id="detailJarviesStatus" {{ $user->role->role_id == 1 ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
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
                    <select id="detailPriority" {{ $user->role->role_id == 1 ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="Low" {{ $ticket->ticket_priority == 'Low' ? 'selected' : '' }}>Low</option>
                        <option value="Medium" {{ $ticket->ticket_priority == 'Medium' ? 'selected' : '' }}>Medium</option>
                        <option value="High" {{ $ticket->ticket_priority == 'High' ? 'selected' : '' }}>High</option>
                    </select>
                </div>

                {{-- Type --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Type</label>
                    <select id="detailType" {{ $user->role->role_id == 1 ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="" {{ !$ticket->type ? 'selected' : '' }}>-- Select Type --</option>
                        <option value="AMS" {{ $ticket->type == 'AMS' ? 'selected' : '' }}>AMS</option>
                        <option value="MO" {{ $ticket->type == 'MO' ? 'selected' : '' }}>MO</option>
                        <option value="ATS" {{ $ticket->type == 'ATS' ? 'selected' : '' }}>ATS</option>
                        <option value="Project" {{ $ticket->type == 'Project' ? 'selected' : '' }}>Project</option>
                        <option value="Internal" {{ $ticket->type == 'Internal' ? 'selected' : '' }}>Internal</option>
                    </select>
                </div>

                {{-- Agent (PIC) - Consultant Dropdown --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Agent (PIC)</label>
                    <select id="detailPIC" {{ $user->role->role_id == 1 ? '' : 'disabled' }} class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                        <option value="" {{ !$ticket->employee_id ? 'selected' : '' }}>-- Unassigned --</option>
                        @foreach($consultants as $consultant)
                            <option value="{{ $consultant['employee_id'] }}" {{ $ticket->employee_id == $consultant['employee_id'] ? 'selected' : '' }}>
                                {{ $consultant['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Customer (read-only) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Customer</label>
                    <p class="text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200">{{ $ticket->customer?->basicData?->name_1 ?? 'N/A' }}</p>
                </div>

                {{-- Man Days --}}
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Man Days</label>
                    <input type="number" id="detailManDays" value="{{ $ticket->man_days ?? '' }}" step="0.5" min="0" max="9999.99"
                        {{ $user->role->role_id == 1 ? '' : 'disabled' }}
                        class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white" placeholder="0.0">
                </div>

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

/* Quill Editor Overrides */
.ql-toolbar.ql-snow { border: none !important; border-bottom: 1px solid #e5e7eb !important; padding: 4px 8px !important; background: #f9fafb; }
.ql-container.ql-snow { border: none !important; font-size: 13px; }
.ql-editor { min-height: 80px; max-height: 180px; overflow-y: auto; padding: 8px 12px; }
.ql-editor.ql-blank::before { font-style: normal; color: #9ca3af; font-size: 13px; }

/* Message content */
.message-content p { margin-bottom: 0.25rem; }
.message-content p:last-child { margin-bottom: 0; }
.message-content ul, .message-content ol { padding-left: 1.5rem; margin-bottom: 0.5rem; }
.message-content blockquote { border-left: 3px solid #d1d5db; padding-left: 0.75rem; color: #6b7280; }

/* Sidebar ticket items */
.sidebar-ticket-item {
    display: block;
    padding: 8px 12px;
    border-radius: 8px;
    transition: background 0.15s;
    text-decoration: none;
}
.sidebar-ticket-item:hover { background: rgba(255,255,255,0.1); }
.sidebar-ticket-item.active { background: rgba(255,255,255,0.2); }
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

<script>
    const ticketId = {{ $ticket->ticket_id }};
    const userRole = {{ $user->role->role_id ?? 0 }};
    const ticketCustomerId = {{ $ticket->customer_id ?? 'null' }};
    let quillEditor = null;
    let allSidebarTickets = [];
    let deliverySupportList = [];

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

        loadMessages();
        loadSidebarTickets();
        markMessagesRead();
    });

    // ==================== MESSAGES ====================
    async function loadMessages() {
        const thread = document.getElementById('messagesThread');
        const loading = document.getElementById('messagesLoading');

        try {
            const response = await fetch(`/api/tickets/${ticketId}/messages`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            loading.classList.add('hidden');

            if (data.success && data.data.length > 0) {
                thread.innerHTML = data.data.map(msg => createMessageBubble(msg)).join('');
            } else {
                thread.innerHTML = createFallbackMessage();
            }
            thread.scrollTop = thread.scrollHeight;
        } catch (error) {
            loading.classList.add('hidden');
            thread.innerHTML = createFallbackMessage();
        }
    }

    function createMessageBubble(msg) {
        const isEmployee = msg.sender_type === 'employee';
        const isInternalNote = msg.message_type === 'internal_note';
        const senderName = msg.sender_name || (isEmployee ? 'Employee' : 'Customer');
        const initials = senderName.substring(0, 1).toUpperCase();
        const date = new Date(msg.created_at).toLocaleDateString('en-US', {
            month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });

        if (isInternalNote) {
            return `
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-200 text-amber-800 text-xs font-bold">${initials}</div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            <span class="text-[10px] bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded font-semibold">Internal Note</span>
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        <div class="message-bubble internal-note p-3">
                            <div class="message-content text-sm text-gray-700">${msg.message_body}</div>
                        </div>
                    </div>
                </div>`;
        }

        const avatarBg = isEmployee ? 'bg-blue-500' : 'bg-gray-400';
        const bubbleClass = isEmployee ? 'employee' : 'customer';

        return `
            <div class="flex gap-3 ${isEmployee ? 'flex-row-reverse' : ''}">
                <div class="w-8 h-8 ${avatarBg} rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">${initials}</div>
                <div class="${isEmployee ? 'text-right' : ''}">
                    <div class="flex items-center gap-2 mb-1 ${isEmployee ? 'justify-end' : ''}">
                        <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                        <span class="text-xs text-gray-400">${date}</span>
                    </div>
                    <div class="message-bubble ${bubbleClass} p-3 inline-block text-left">
                        <div class="message-content text-sm text-gray-700">${msg.message_body}</div>
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

    // ==================== SEND REPLY ====================
    async function sendReply(messageType) {
        const htmlContent = quillEditor.root.innerHTML;
        const plainContent = quillEditor.getText().trim();

        if (!plainContent) {
            showNotification('Please type a message', 'error');
            return;
        }

        try {
            const response = await fetch(`/api/tickets/${ticketId}/messages`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message_body: htmlContent, message_type: messageType })
            });
            const data = await response.json();

            if (data.success) {
                quillEditor.setContents([]);
                await loadMessages();
                showNotification(messageType === 'internal_note' ? 'Internal note added' : 'Reply sent', 'success');
            } else {
                showNotification(data.message || 'Failed to send message', 'error');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
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

            const prioColors = { 'Low': 'bg-green-400', 'Medium': 'bg-blue-400', 'High': 'bg-red-400' };
            const prioDot = prioColors[t.ticket_priority] || 'bg-gray-400';

            return `
                <a href="/ticket/${t.ticket_id}" class="sidebar-ticket-item ${isActive ? 'active' : ''}">
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="text-xs font-semibold text-white truncate max-w-[140px]">${customerName}</span>
                        <span class="text-[10px] text-white text-opacity-50">${timeAgo}</span>
                    </div>
                    <p class="text-[11px] text-white text-opacity-70 truncate mb-1">#${t.ticket_id} - ${shortDesc}</p>
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full ${prioDot}"></div>
                        <span class="text-[10px] text-white text-opacity-50">${t.ticket_priority || 'Medium'}</span>
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
                type: type || null,
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
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
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
                    option.textContent = `${support.name} (${support.client_name || 'Unknown Client'})`;
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
</script>
@endsection
