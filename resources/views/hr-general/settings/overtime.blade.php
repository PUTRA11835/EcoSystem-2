@extends('dashboard')

@section('title', 'Overtime — Settings')
@section('page-title', 'Overtime')
@section('page-subtitle', 'Overtime rules — the approval workflow moved to its own page')

@section('content')
@php
    use App\Models\Overtime\OvertimeSetting;
@endphp

@include('hr-general.overtime.hub-tabs-overtime')

{{-- Lebar penuh (D152/D176) — sebelumnya max-w-6xl, satu-satunya tab di hub
     ini yang masih sempit dibanding Branches/Shifts/Cash Advance Settings.
     Grid-grid di dalamnya sudah responsif (3/4/3/2 kolom per bagian). --}}
<div class="w-full space-y-5">

    {{-- 🔴 D180 — Approval Workflow PINDAH ke halaman tersendiri, dengan hak
         akses TERPISAH dari halaman ini (izinnya sekarang berbeda slug, bukan
         lagi ikut slug Settings). Diberitahukan di sini, bukan didiamkan —
         orang yang terbiasa mencari alur di sini akan mengira fiturnya hilang
         tanpa pengingat ini. --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">Looking for the approval workflow?</span>
            It moved to its own page —
            <a href="{{ route('general.approval-workflow.overtime') }}" class="underline font-semibold hover:text-blue-700">Approval Workflow</a>.
        </p>
    </div>

    {{-- =============================================================
         BAGIAN 2 — ATURAN LEMBUR
         ============================================================= --}}
    <form method="POST" action="{{ route('general.overtime.settings.update') }}" class="bg-white rounded-xl p-6 shadow-sm space-y-6">
        @csrf

        <div class="pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Overtime Rules</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Every value below can block or flag a request. Set <strong>0</strong> where you want no limit at all.
            </p>
        </div>

        {{-- Aturan tanggal --}}
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Dates</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2 md:col-span-1">
                    <input type="checkbox" name="allow_future_date" value="1" @checked($settings->allow_future_date)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow future dates</span>
                        <span class="block text-xs text-gray-500">
                            Attendance comparison is skipped for dates that have not happened yet.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Backdate limit (days)</label>
                    <input type="number" name="max_backdate_days" min="0" max="3650" required
                           value="{{ old('max_backdate_days', $settings->max_backdate_days) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no limit.</p>
                    @error('max_backdate_days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Closed reporting period</label>
                    <select name="locked_period_policy"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        @foreach([
                            OvertimeSetting::LOCK_OFF            => 'Ignore — allow everything',
                            OvertimeSetting::LOCK_BLOCK_EMPLOYEE => 'Block employees only',
                            OvertimeSetting::LOCK_BLOCK_ALL      => 'Block everyone',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($settings->locked_period_policy === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        "Block employees only" still lets Overtime — Manage holders act.
                    </p>
                </div>
            </div>
        </div>

        {{-- Aturan durasi --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Duration</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_crosses_midnight" value="1" @checked($settings->allow_crosses_midnight)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow past midnight</span>
                        <span class="block text-xs text-gray-500">Overtime may end on the next day.</span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum (minutes)</label>
                    <input type="number" name="min_duration_minutes" min="0" max="1440" required
                           value="{{ old('min_duration_minutes', $settings->min_duration_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no minimum. Flagged, not blocked.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Daily cap (minutes)</label>
                    <input type="number" name="max_daily_minutes" min="0" max="1440" required
                           value="{{ old('max_daily_minutes', $settings->max_daily_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no cap. Regulation reference: 240.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Weekly cap (minutes)</label>
                    <input type="number" name="max_weekly_minutes" min="0" max="10080" required
                           value="{{ old('max_weekly_minutes', $settings->max_weekly_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">0 = no cap. Regulation reference: 1080.</p>
                </div>
            </div>
        </div>

        {{-- Persetujuan --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Approval</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_self_approval" value="1" @checked($settings->allow_self_approval)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Allow self-approval</span>
                        <span class="block text-xs text-gray-500">
                            Requesters who hold an approver role may approve their own request.
                            It is always recorded and flagged.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="allow_approver_adjust_time" value="1" @checked($settings->allow_approver_adjust_time)
                           class="mt-0.5 w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <span class="text-sm">
                        <span class="font-semibold text-gray-800">Approvers may adjust times</span>
                        <span class="block text-xs text-gray-500">
                            The originally claimed times are always kept on record.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Fallback approver role</label>
                    <select name="self_approval_fallback_role_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                        <option value="">— none —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected($settings->self_approval_fallback_role_id === $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Suggested to whoever is blocked when self-approval is off.
                    </p>
                </div>
            </div>

            @if($settings->allow_self_approval)
            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <p class="text-sm text-amber-900">
                    <span class="font-semibold">Self-approval is currently enabled.</span>
                    With only {{ $steps->where('is_active', true)->count() }} active step(s), a requester who holds
                    the approver role can take their own request all the way to approved without anyone else seeing it.
                    Every such case is flagged as <em>Self-approved</em> in the review list. Adding a second step
                    removes this exposure.
                </p>
            </div>
            @endif
        </div>

        {{-- Perbandingan & alasan --}}
        <div class="pt-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Comparison &amp; Reason</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Attendance mismatch tolerance (minutes)</label>
                    <input type="number" name="mismatch_tolerance_minutes" min="0" max="1440" required
                           value="{{ old('mismatch_tolerance_minutes', $settings->mismatch_tolerance_minutes) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">
                        A claim differing from the attendance record by more than this is flagged for the reviewer — never rejected.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum reason length</label>
                    <input type="number" name="require_reason_min_chars" min="0" max="255" required
                           value="{{ old('require_reason_min_chars', $settings->require_reason_min_chars) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-gray-400 mt-1">Characters required in the reason field.</p>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-gray-100">
            <button type="submit"
                    class="px-5 py-2.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection
