@extends('dashboard')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-subtitle', 'Your mention notifications')

@section('content')
<div class="max-w-3xl mx-auto py-6 px-4">

    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-gray-800">All Notifications</h2>
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

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100" id="notifContainer">
        @forelse($notifications as $notif)
        <div class="flex gap-4 px-5 py-4 {{ !$notif->is_read ? 'bg-red-50' : '' }} hover:bg-gray-50 transition-colors group" id="notif-{{ $notif->id }}">
            <div class="w-9 h-9 rounded-full {{ !$notif->is_read ? 'bg-red-100' : 'bg-gray-100' }} flex items-center justify-center shrink-0 mt-0.5">
                <i class="fas fa-at {{ !$notif->is_read ? 'text-red-600' : 'text-gray-400' }} text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">
                            {{ $notif->from_name ?? 'Someone' }} mentioned you
                            @if($notif->ticket_id)
                                in <a href="/ticket/{{ $notif->ticket_id }}" class="text-red-700 hover:underline">Ticket</a>
                            @endif
                        </p>
                        @if($notif->preview)
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $notif->preview }}</p>
                        @endif
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[11px] text-gray-400">{{ $notif->created_at->diffForHumans() }}</p>
                        @if(!$notif->is_read)
                        <span class="inline-block w-2 h-2 bg-red-500 rounded-full mt-1 ml-auto"></span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-3 mt-2">
                    @if($notif->ticket_id)
                    <a href="/ticket/{{ $notif->ticket_id }}"
                       class="text-xs text-red-700 hover:underline font-medium">
                        View ticket →
                    </a>
                    @endif
                    @if(!$notif->is_read)
                    <button onclick="markRead({{ $notif->id }})" class="text-xs text-gray-400 hover:text-gray-600">
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
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function markRead(id) {
    fetch(`/api/notifications/${id}/read`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    }).then(() => {
        const el = document.getElementById('notif-' + id);
        if (el) {
            el.classList.remove('bg-red-50');
            el.querySelectorAll('.bg-red-100,.text-red-600,.bg-red-500').forEach(x => {
                x.classList.replace('bg-red-100', 'bg-gray-100');
                x.classList.replace('text-red-600', 'text-gray-400');
                x.classList.replace('bg-red-500', 'bg-transparent');
            });
        }
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
    if (!confirm('Delete all read notifications?')) return;
    fetch('/api/notifications/bulk-delete', {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    }).then(() => location.reload()).catch(() => {});
}
</script>
@endsection
