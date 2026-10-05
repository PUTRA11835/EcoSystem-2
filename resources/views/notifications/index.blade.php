@extends('dashboard')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-subtitle', 'Approvals, document updates, and reminders for your account')

@section('content')
<div class="py-6 px-4">

    <div class="flex items-center justify-between mb-1">
        <h2 class="text-base font-semibold text-gray-800">
            {{ $pendingOnly ? 'Pending Approval' : 'All Notifications' }}
        </h2>
        <div class="flex gap-2">
            <button id="markAllReadBtn" onclick="markAllRead()"
                class="text-xs px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 hover:border-red-700 hover:text-red-700 transition-all font-medium">
                Mark all as read
            </button>
            <button id="clearReadBtn" onclick="clearRead()"
                class="text-xs px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-400 hover:border-red-700 hover:text-red-700 transition-all font-medium">
                Clear read
            </button>
        </div>
    </div>

    {{-- HC-D40 — konteks saat datang dari kotak "N Pending Approval" di Command
         Center: tegaskan ini daftar yang DIPERSEMPIT, dengan jalan keluar ke
         daftar lengkap. --}}
    @if($pendingOnly)
    <p class="text-xs text-gray-400 mb-4">
        Showing only documents waiting for your approval.
        <a href="{{ route('notifications.index', ['tab' => $tab]) }}" class="text-red-700 font-semibold hover:underline">View all notifications →</a>
    </p>
    @else
    <div class="mb-4"></div>
    @endif

    {{-- Kartu ringkasan (HC-D39, gaya ESH) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Unread</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($unreadCount) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Needs your attention or follow-up.</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Notifications</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalCount) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">History for your account.</p>
        </div>
    </div>

    {{-- Cari + tab Semua/Unread/Read (HC-D39). Satu mekanisme saja (tab), tanpa
         dropdown status terpisah — ESH punya keduanya tapi mengontrol hal yang
         sama; disederhanakan di sini supaya tidak ada dua kontrol yang saling
         tumpang tindih. --}}
    <form method="GET" action="{{ route('notifications.index') }}" class="mb-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if($pendingOnly)
        <input type="hidden" name="type" value="pending_approval">
        @endif
        <div class="flex flex-col sm:flex-row gap-2 mb-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search notifications..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-opacity-20 focus:border-red-700">
            </div>
            <button type="submit" class="text-xs px-4 py-2 bg-gray-900 text-white rounded-lg font-semibold hover:bg-black transition">Search</button>
            @if($search !== '')
            <a href="{{ route('notifications.index', array_filter(['tab' => $tab, 'type' => $pendingOnly ? 'pending_approval' : null])) }}" class="text-xs px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition text-center">Reset</a>
            @endif
        </div>
        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-semibold">
            @foreach(['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $key => $label)
            <a href="{{ route('notifications.index', array_filter(['tab' => $key, 'q' => $search ?: null, 'type' => $pendingOnly ? 'pending_approval' : null])) }}"
                class="px-3 py-1.5 rounded-md transition {{ $tab === $key ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
    </form>

    @php
        $lateExceptionTypes = [
            'late_exception_submitted'    => ['icon' => 'fa-user-clock',          'color' => 'yellow', 'title' => 'Late Access Request submitted'],
            'late_exception_pending_rpmo' => ['icon' => 'fa-user-clock',          'color' => 'blue',   'title' => 'Late Access Request needs RPMO review'],
            'late_exception_head_approved'=> ['icon' => 'fa-check-circle',        'color' => 'green',  'title' => 'Late Access Request approved by Head'],
            'late_exception_head_rejected'=> ['icon' => 'fa-times-circle',        'color' => 'red',    'title' => 'Late Access Request rejected by Head'],
            'late_exception_approved'     => ['icon' => 'fa-unlock',              'color' => 'green',  'title' => 'Late Access Request approved by RPMO'],
            'late_exception_rejected'     => ['icon' => 'fa-ban',                 'color' => 'red',    'title' => 'Late Access Request rejected by RPMO'],
            'customer_mandays_proposed'   => ['icon' => 'fa-file-invoice',        'color' => 'blue',   'title' => 'Customer Mandays Proposal — needs review'],
            'resolution_days_proposed'    => ['icon' => 'fa-users',               'color' => 'indigo', 'title' => 'Resolution Days Proposal — needs review'],
            'customer_mandays_canceled'   => ['icon' => 'fa-times-circle',        'color' => 'orange', 'title' => 'Customer Mandays Proposal canceled'],
            'contract_end_reminder'       => ['icon' => 'fa-file-contract',       'color' => 'yellow', 'title' => 'Contract deadline reminder'],
            'join_date_reminder'          => ['icon' => 'fa-calendar-check',      'color' => 'yellow', 'title' => 'HR needs your join date'],
            'top_invoice_reminder'        => ['icon' => 'fa-file-invoice-dollar', 'color' => 'blue',   'title' => 'Invoice submission due'],
            'customer_email_reply'        => ['icon' => 'fa-envelope',            'color' => 'green',  'title' => null], // title built dynamically from from_name
            'ticket_reply'                => ['icon' => 'fa-reply',               'color' => 'blue',   'title' => null],
            'ticket_internal_note'        => ['icon' => 'fa-sticky-note',         'color' => 'yellow', 'title' => null],
            'ticket_member_added'         => ['icon' => 'fa-user-plus',           'color' => 'green',  'title' => null],
            'ticket_member_removed'       => ['icon' => 'fa-user-minus',          'color' => 'red',    'title' => null],
            'ticket_member_reactivated'   => ['icon' => 'fa-user-check',          'color' => 'blue',   'title' => null],
            'leave_permit_submitted'      => ['icon' => 'fa-calendar-plus',       'color' => 'yellow', 'title' => null],
            'leave_permit_approved'       => ['icon' => 'fa-calendar-check',      'color' => 'green',  'title' => null],
            'leave_permit_rejected'       => ['icon' => 'fa-calendar-times',      'color' => 'red',    'title' => null],
            'leave_permit_revision'       => ['icon' => 'fa-calendar-alt',        'color' => 'blue',   'title' => null],

            // Lima modul alur kerja HR & General (Keputusan HC-D39) — judul statis
            // di sini, detail dokumennya (nomor, pesan) sudah lengkap di kolom
            // `preview` yang dibangun di masing-masing Service, jadi tidak perlu
            // logika dinamis tambahan di if/elseif bawah seperti tipe lama.
            'overtime_pending_approval'            => ['icon' => 'fa-business-time',        'color' => 'yellow', 'title' => 'Overtime — Needs Your Approval'],
            'overtime_approved'                    => ['icon' => 'fa-check-circle',         'color' => 'green',  'title' => 'Overtime — Approved'],
            'overtime_rejected'                    => ['icon' => 'fa-times-circle',         'color' => 'red',    'title' => 'Overtime — Rejected'],
            'overtime_progressed'                  => ['icon' => 'fa-forward',              'color' => 'blue',   'title' => 'Overtime — Progressed'],
            'reimbursement_pending_approval'       => ['icon' => 'fa-receipt',              'color' => 'yellow', 'title' => 'Reimbursement — Needs Your Approval'],
            'reimbursement_approved'               => ['icon' => 'fa-check-circle',         'color' => 'green',  'title' => 'Reimbursement — Approved'],
            'reimbursement_rejected'               => ['icon' => 'fa-times-circle',         'color' => 'red',    'title' => 'Reimbursement — Rejected'],
            'reimbursement_progressed'             => ['icon' => 'fa-forward',              'color' => 'blue',   'title' => 'Reimbursement — Progressed'],
            'purchase_request_pending_approval'    => ['icon' => 'fa-cart-shopping',        'color' => 'yellow', 'title' => 'Purchase Request — Needs Your Approval'],
            'purchase_request_approved'            => ['icon' => 'fa-check-circle',         'color' => 'green',  'title' => 'Purchase Request — Approved'],
            'purchase_request_rejected'            => ['icon' => 'fa-times-circle',         'color' => 'red',    'title' => 'Purchase Request — Rejected'],
            'purchase_request_progressed'          => ['icon' => 'fa-forward',              'color' => 'blue',   'title' => 'Purchase Request — Progressed'],
            'cash_advance_pending_approval'        => ['icon' => 'fa-hand-holding-dollar',  'color' => 'yellow', 'title' => 'Cash Advance — Needs Your Approval'],
            'cash_advance_approved'                => ['icon' => 'fa-check-circle',         'color' => 'green',  'title' => 'Cash Advance — Approved'],
            'cash_advance_rejected'                => ['icon' => 'fa-times-circle',         'color' => 'red',    'title' => 'Cash Advance — Rejected'],
            'cash_advance_progressed'              => ['icon' => 'fa-forward',              'color' => 'blue',   'title' => 'Cash Advance — Progressed'],
            'cash_advance_report_pending_approval' => ['icon' => 'fa-file-invoice-dollar',  'color' => 'yellow', 'title' => 'Cash Advance Report — Needs Your Approval'],
            'cash_advance_report_approved'         => ['icon' => 'fa-check-circle',         'color' => 'green',  'title' => 'Cash Advance Report — Approved'],
            'cash_advance_report_rejected'         => ['icon' => 'fa-times-circle',         'color' => 'red',    'title' => 'Cash Advance Report — Rejected'],
            'cash_advance_report_progressed'       => ['icon' => 'fa-forward',              'color' => 'blue',   'title' => 'Cash Advance Report — Progressed'],
        ];
        $colorMap = [
            'yellow' => ['bg' => 'bg-yellow-100', 'icon' => 'text-yellow-600'],
            'blue'   => ['bg' => 'bg-blue-100',   'icon' => 'text-blue-600'],
            'green'  => ['bg' => 'bg-green-100',  'icon' => 'text-green-600'],
            'red'    => ['bg' => 'bg-red-100',     'icon' => 'text-red-600'],
            'indigo' => ['bg' => 'bg-indigo-100',  'icon' => 'text-indigo-600'],
            'orange' => ['bg' => 'bg-orange-100',  'icon' => 'text-orange-600'],
        ];
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100" id="notifContainer">
        @forelse($notifications as $notif)
        @php
            $isLateEx   = isset($lateExceptionTypes[$notif->type]);
            $leInfo     = $isLateEx ? $lateExceptionTypes[$notif->type] : null;
            $leColor    = $leInfo ? $colorMap[$leInfo['color']] : null;
            $navLink    = $notif->link
                ?? ($notif->type === 'timesheet_submitted'
                    ? '/calendar/timesheets'
                    : ($notif->ticket_id ? '/ticket/' . $notif->ticket_id : null));
            if ($navLink && $notif->message_id && !str_contains($navLink, '#')) {
                $navLink .= '#msg-' . $notif->message_id;
            }
            $iconBg     = $notif->is_read ? 'bg-gray-100' : ($leColor ? $leColor['bg'] : 'bg-red-100');
            $iconColor  = $notif->is_read ? 'text-gray-400' : ($leColor ? $leColor['icon'] : 'text-red-600');
            $iconClass  = $leInfo ? $leInfo['icon'] : ($notif->type === 'timesheet_submitted' ? 'fa-file-alt' : 'fa-at');
        @endphp
        <div class="flex gap-4 px-5 py-4 {{ !$notif->is_read ? 'bg-red-50' : '' }} hover:bg-gray-50 transition-colors group" id="notif-{{ $notif->id }}" data-ticket-id="{{ $notif->ticket_id }}">
            <div class="w-9 h-9 rounded-full {{ $iconBg }} flex items-center justify-center shrink-0 mt-0.5">
                <i class="fas {{ $iconClass }} {{ $iconColor }} text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">
                            @if($isLateEx && $leInfo['title'])
                                {{ $leInfo['title'] }}
                                @if($notif->from_name)
                                    <span class="font-normal text-gray-500">· {{ $notif->from_name }}</span>
                                @endif
                            @elseif($notif->type === 'timesheet_submitted')
                                {{ $notif->from_name ?? 'Consultant' }} submitted a timesheet
                            @elseif($notif->type === 'customer_email_reply')
                                {{ $notif->from_name ?? 'Customer' }} replied via email
                            @elseif($notif->type === 'ticket_reply')
                                {{ $notif->from_name ?? 'Someone' }} replied to a ticket
                            @elseif($notif->type === 'ticket_internal_note')
                                {{ $notif->from_name ?? 'Someone' }} added an internal note
                            @elseif($notif->type === 'ticket_member_added')
                                {{ $notif->from_name ?? 'Someone' }} added you to a ticket
                            @elseif($notif->type === 'ticket_member_removed')
                                {{ $notif->from_name ?? 'Someone' }} removed a member from a ticket
                            @elseif($notif->type === 'ticket_member_reactivated')
                                {{ $notif->from_name ?? 'Someone' }} re-added a member to a ticket
                            @elseif($notif->type === 'leave_permit_submitted')
                                {{ $notif->from_name ?? 'An employee' }} submitted a Leave/Permit request for approval
                            @elseif($notif->type === 'leave_permit_approved')
                                Your Leave/Permit request was approved
                            @elseif($notif->type === 'leave_permit_rejected')
                                Your Leave/Permit request was rejected
                            @elseif($notif->type === 'leave_permit_revision')
                                Revision requested for your Leave/Permit request
                            @else
                                {{ $notif->from_name ?? 'Someone' }} mentioned you
                                @if($notif->ticket_id)
                                    in <a href="{{ $navLink ?? ('/ticket/' . $notif->ticket_id) }}" class="text-red-700 hover:underline">Ticket</a>
                                @endif
                            @endif
                        </p>
                        @if($notif->ticket?->ticket_number || $notif->ticket?->customer?->customer_code)
                        <p class="text-xs font-medium text-gray-700 mt-0.5">
                            {{ implode(' · ', array_filter([$notif->ticket?->ticket_number, $notif->ticket?->customer?->customer_code])) }}
                        </p>
                        @endif
                        @if($notif->preview)
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $notif->preview }}</p>
                        @endif
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[11px] text-gray-400">{{ $notif->created_at->diffForHumans() }}</p>
                        @if(!$notif->is_read)
                        <span class="js-unread-dot inline-block w-2 h-2 bg-red-500 rounded-full mt-1 ml-auto"></span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-3 mt-2">
                    @if($navLink)
                    <a href="{{ $navLink }}" onclick="markRead({{ $notif->id }})" class="text-xs text-red-700 hover:underline font-medium">
                        View &rarr;
                    </a>
                    @endif
                    @if(!$notif->is_read)
                    <button onclick="markRead({{ $notif->id }})" class="js-mark-read-btn text-xs text-gray-400 hover:text-gray-600">
                        Mark as read
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="px-6 py-12 text-center text-gray-400">
            <i class="fas fa-bell-slash text-3xl mb-3 block opacity-30"></i>
            <p class="text-sm">No notifications yet</p>
        </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

// Mark a notification as read (it STAYS on the page, just shown as read). The backend
// also marks every other unread notification for the same ticket read in one go, so
// mirror that here — update every row sharing this ticket_id, not just the one clicked.
function markReadRow(el) {
    if (!el) return;
    el.classList.remove('bg-red-50');
    const dot = el.querySelector('.js-unread-dot');
    if (dot) dot.remove();
    const btn = el.querySelector('.js-mark-read-btn');
    if (btn) btn.remove();
}

function markRead(id) {
    const el = document.getElementById('notif-' + id);
    markReadRow(el);

    const ticketId = el?.dataset.ticketId;
    if (ticketId) {
        document.querySelectorAll(`[data-ticket-id="${ticketId}"]`).forEach(markReadRow);
    }

    fetch(`/api/notifications/${id}/read`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    }).catch(() => {});
}

function markAllRead() {
    fetch('/api/notifications/read-all', {
        method: 'PUT',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    }).then(() => location.reload()).catch(() => {});
}

function clearRead() {
    fetch('/api/notifications/bulk-delete', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    }).then(() => location.reload()).catch(() => {});
}
</script>
@endsection
