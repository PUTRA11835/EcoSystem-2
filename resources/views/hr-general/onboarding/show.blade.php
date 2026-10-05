@extends('dashboard')

@section('title', 'Onboarding — ' . $row['name'])
@section('page-title', $row['name'])
@section('page-subtitle', 'Data checklist' . ($row['join_date'] ? ' for employee joining ' . \Carbon\Carbon::parse($row['join_date'])->format('d M Y') : ''))

@section('page-actions')
    <a href="{{ route('general.onboarding.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-full border border-gray-300 hover:bg-gray-50 transition-all">
        <i class="fas fa-arrow-left text-xs"></i> Back
    </a>
@endsection

@section('content')
@php
    $canOpenMaster = $can('master.employee');
    $masterUrl = fn (?string $section) => route('master.employee.detail', $row['employee_id'])
        . ($section ? '?section=' . $section : '');
    $no = 0;
@endphp

{{-- Tata letak mengikuti halaman detail Onboarding aplikasi acuan (ESH): kartu progres
     di kiri, tabel checklist di kanan (No · Item · Status · Aksi). --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

    {{-- ── Kiri: progres ────────────────────────────────────────────────── --}}
    <div class="lg:col-span-4 xl:col-span-3 space-y-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-base font-bold text-gray-900">Progress</h2>
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">{{ $row['percent'] }}%</span>
            </div>
            <div class="mt-4 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-2 rounded-full primary-solid" style="width: {{ $row['percent'] }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ $row['done'] }} of {{ $row['total'] }} required items filled.</p>

            <div class="mt-4 rounded-lg bg-sky-50 border border-sky-100 px-3 py-2.5 text-xs text-sky-900 leading-relaxed">
                This checklist follows the employee master data: identity, payroll and BPJS numbers, and the employment contract.
                It updates as soon as the data is saved.
            </div>
            @if($row['type'] === 'External')
                <div class="mt-3 rounded-lg bg-blue-50 border border-blue-200 px-3 py-2.5 text-xs text-blue-900 leading-relaxed">
                    External consultants are assessed on contact, identity, tax ID and bank account items only.
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 text-sm">
            <dl class="space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">ECI</dt><dd class="font-mono text-gray-900">{{ $row['eci'] }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Position</dt><dd class="text-gray-900 text-right">{{ $row['position'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Department</dt><dd class="text-gray-900 text-right">{{ $row['department'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Type</dt>
                    <dd><span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold {{ $row['type'] === 'External' ? 'bg-sky-100 text-sky-800' : 'bg-green-100 text-green-800' }}">{{ $row['type'] }}</span></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Employment status</dt>
                    <dd class="text-gray-900">{{ \App\Services\HrProfile\EmploymentStatus::label($row['employment_status'] ?? null) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Join date</dt>
                    <dd class="text-gray-900">{{ $row['join_date'] ? \Carbon\Carbon::parse($row['join_date'])->format('d M Y') : '—' }}</dd></div>
            </dl>
        </div>

        {{-- Kunci profil (H3.11; HC-D29) --}}
        @php
            $lockedAt = $row['locked_at'] ?? null;
            $canLock = $can('general.onboarding.lock');
            $canUnlock = $can('general.onboarding.unlock');
        @endphp
        <div id="lockCard" class="bg-white rounded-xl shadow-sm p-5" data-employee="{{ $row['employee_id'] }}">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-base font-bold text-gray-900">Profile lock</h2>
                @if($lockedAt)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-gray-200 text-gray-700"><i class="fas fa-lock text-[10px]"></i> Locked</span>
                @elseif($row['status'] === 'complete')
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Ready to lock</span>
                @endif
            </div>
            <p class="text-xs text-gray-600 mt-2 leading-relaxed">
                @if($lockedAt)
                    Verified and locked on {{ \Carbon\Carbon::parse($lockedAt)->format('d M Y H:i') }}. The employee can no longer change Basic Data, Address, Identification, Bank Account or HR Profile; HR can.
                @elseif($row['status'] === 'complete')
                    All required data is filled in. After you have checked it, lock the profile so only HR can change it.
                @else
                    Locking becomes available once all required items are filled in.
                @endif
            </p>
            @if(!$lockedAt && $canLock)
                <button type="button" id="lockBtn" onclick="lockProfile()" @disabled($row['status'] !== 'complete')
                        class="mt-3 w-full px-3 py-2 text-xs font-semibold rounded-lg bg-red-800 text-white hover:bg-red-900 disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fas fa-lock mr-1"></i> Verify &amp; Lock
                </button>
            @endif
            @if($lockedAt && $canUnlock)
                <div id="unlockBox" class="mt-3 space-y-2">
                    <input type="text" id="unlockReason" maxlength="200" placeholder="Reason for unlocking (required)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-800">
                    <button type="button" id="unlockBtn" onclick="unlockProfile()" class="w-full px-3 py-2 text-xs font-semibold rounded-lg border border-red-300 text-red-700 hover:bg-red-50">
                        <i class="fas fa-lock-open mr-1"></i> Unlock profile
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Kanan: tabel checklist ───────────────────────────────────────── --}}
    <div class="lg:col-span-8 xl:col-span-9 bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-gray-900">Checklist</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Employees fill in their own data in <span class="font-semibold">My Profile</span>; HR can do the same from
                    <span class="font-semibold">Master › Employee</span>.
                </p>
            </div>
            @if($row['status'] === 'complete')
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 whitespace-nowrap">Complete</span>
            @else
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 whitespace-nowrap">In progress</span>
            @endif
        </div>

        <div class="border-t border-gray-100 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-100">
                        <th class="px-5 py-3 w-12">No</th>
                        <th class="px-3 py-3">Item</th>
                        <th class="px-3 py-3 w-32">Status</th>
                        <th class="px-5 py-3 w-32 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($row['groups'] as $key => $g)
                        @continue($g['total'] === 0)
                        {{-- Baris judul kelompok --}}
                        <tr class="bg-gray-50">
                            <td colspan="4" class="px-5 py-2">
                                <span class="text-xs font-bold text-gray-700 uppercase tracking-wide">{{ $g['label'] }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ $g['done'] }}/{{ $g['total'] }}</span>
                            </td>
                        </tr>
                        @foreach($row['items'] as $item)
                            @continue($item['group'] !== $key)
                            @php $no++; @endphp
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 text-gray-400">{{ $no }}</td>
                                <td class="px-3 py-3 {{ $item['done'] ? 'text-gray-700' : 'text-gray-900 font-medium' }}">{{ $item['label'] }}
                                @if(!$item['done'] && !empty($item['hint']))
                                    <div class="text-xs font-normal text-gray-500 mt-0.5">{{ $item['hint'] }}</div>
                                @endif
                            </td>
                                <td class="px-3 py-3">
                                    @if($item['done'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800"><i class="fas fa-check text-[10px]"></i> Complete</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800"><i class="fas fa-circle-exclamation text-[10px]"></i> Missing</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if(!$item['done'] && $canOpenMaster)
                                        <a href="{{ $masterUrl($item['section']) }}{{ str_contains($masterUrl($item['section']), '?') ? '&' : '?' }}field={{ $item['key'] }}"
                                           class="inline-block px-3 py-1.5 text-xs font-semibold rounded-full border border-blue-300 text-blue-700 hover:bg-blue-50">Fill in</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($can('general.onboarding.lock') || $can('general.onboarding.unlock'))
<script>
    // Kunci profil (H3.11): POST + header AJAX + token CSRF; setelah berhasil muat ulang agar status dan badge segar.
    (function () {
        const card = document.getElementById('lockCard');
        if (!card) { return; }
        const base = '{{ url('/general/onboarding') }}/' + card.dataset.employee;
        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const notify = (m, t) => (typeof showNotification === 'function' ? showNotification(m, t) : alert(m));

        async function post(path, body) {
            const res = await fetch(base + path, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(body || {})
            });
            let json = null;
            try { json = await res.json(); } catch (e) { /* bukan JSON (mis. dialihkan) */ }
            return { ok: res.ok, json };
        }

        window.lockProfile = async function () {
            const msg = 'Lock this profile? The employee will no longer be able to change Basic Data, Address, Identification, Bank Account or HR Profile.';
            const ok = typeof showConfirm === 'function' ? await showConfirm(msg, 'Verify & Lock', 'danger') : window.confirm(msg);
            if (!ok) { return; }
            const r = await post('/lock');
            if (r.ok && r.json && r.json.success) { notify(r.json.message, 'success'); setTimeout(() => location.reload(), 600); }
            else { notify((r.json && r.json.message) || 'Could not lock the profile.', 'error'); }
        };

        window.unlockProfile = async function () {
            const reason = (document.getElementById('unlockReason').value || '').trim();
            if (reason.length < 5) { notify('Please give a reason (at least 5 characters).', 'warning'); return; }
            const r = await post('/unlock', { reason: reason });
            if (r.ok && r.json && r.json.success) { notify(r.json.message, 'success'); setTimeout(() => location.reload(), 600); }
            else { notify((r.json && r.json.message) || 'Could not unlock the profile.', 'error'); }
        };
    })();
</script>
@endif
@endsection
