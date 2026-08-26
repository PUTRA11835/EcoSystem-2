@extends('dashboard')

@section('title', 'Submit Overtime')
@section('page-title', 'Submit Overtime')
@section('page-subtitle', 'Fill in the overtime details you want to submit')

@section('page-actions')
    <a href="{{ route('general.my-overtime.index') }}"
       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
        Back
    </a>
@endsection

@section('content')
<div class="max-w-5xl space-y-5">

    @if($steps->isEmpty())
    <div class="bg-red-50 border border-red-200 rounded-xl px-5 py-4">
        <p class="text-sm text-red-800 font-semibold">No approval step is configured.</p>
        <p class="text-sm text-red-700 mt-1">
            Requests cannot be submitted until HR sets up at least one approval step in Overtime Settings.
        </p>
    </div>
    @endif

    <form method="POST" action="{{ route('general.my-overtime.store') }}" id="overtimeForm"
          class="bg-white rounded-xl p-6 shadow-sm space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                    Overtime Date <span class="text-red-600">*</span>
                </label>
                <input type="date" name="overtime_date" id="overtimeDate" required
                       value="{{ old('overtime_date', now()->toDateString()) }}"
                       @if($minDate) min="{{ $minDate }}" @endif
                       @if($maxDate) max="{{ $maxDate }}" @endif
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                @error('overtime_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                    Start Time <span class="text-red-600">*</span>
                </label>
                <input type="time" name="start_time" id="startTime" required
                       value="{{ old('start_time') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                @error('start_time')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                    End Time <span class="text-red-600">*</span>
                </label>
                <input type="time" name="end_time" id="endTime" required
                       value="{{ old('end_time') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                @error('end_time')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Durasi dihitung langsung di layar. Pengguna melihat apa yang akan
             tersimpan SEBELUM menekan kirim, bukan setelah ditolak. --}}
        <div id="durationBox" class="hidden bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <span class="text-sm text-gray-600">
                    Calculated duration:
                    <strong id="durationLabel" class="text-gray-900">—</strong>
                </span>
                <span id="overnightNote" class="hidden text-sm text-amber-700">
                    This ends on the next day.
                </span>
            </div>
        </div>

        {{-- Pembanding presensi. Ditampilkan sebelum kirim supaya klaim yang
             keliru ketahuan lebih awal — bukan setelah ditolak penyetuju. --}}
        <div id="attendanceBox" class="hidden border rounded-lg px-4 py-3 text-sm"></div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                Reason <span class="text-red-600">*</span>
            </label>
            <textarea name="reason" rows="4" required
                      minlength="{{ $settings->require_reason_min_chars }}"
                      placeholder="Describe what you worked on, at least {{ $settings->require_reason_min_chars }} characters..."
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">{{ old('reason') }}</textarea>
            @error('reason')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Aturan yang sedang berlaku. Ditampilkan apa adanya supaya pengguna
             tidak menebak-nebak batas yang tak terlihat. --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Current rules</p>
            <ul class="text-xs text-gray-600 space-y-1">
                <li>• Future dates: <strong>{{ $settings->allow_future_date ? 'allowed' : 'not allowed' }}</strong></li>
                <li>• Backdating: <strong>{{ $settings->hasBackdateLimit() ? 'up to ' . $settings->max_backdate_days . ' days' : 'no limit' }}</strong></li>
                <li>• Passing midnight: <strong>{{ $settings->allow_crosses_midnight ? 'allowed' : 'not allowed' }}</strong></li>
                @if($settings->hasDailyLimit())
                <li>• Daily cap: <strong>{{ \App\Models\Overtime\OvertimeRequest::formatMinutes($settings->max_daily_minutes) }}</strong> — going over is flagged for review, not blocked</li>
                @endif
                <li>• Multiple requests on the same day are allowed as long as the times do not overlap</li>
            </ul>
        </div>

        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit" id="submitBtn"
                    @disabled($steps->isEmpty())
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                Submit Request
            </button>
            <a href="{{ route('general.my-overtime.index') }}"
               class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // URL relatif: route() memakai APP_URL, dan URL absolut menunjuk host yang
    // salah saat aplikasi dibuka lewat port atau host lain (Keputusan D27).
    const HINT_URL = @json(route('general.my-overtime.attendance-hint', [], false));
    const ALLOW_OVERNIGHT = @json((bool) $settings->allow_crosses_midnight);

    const dateInput   = document.getElementById('overtimeDate');
    const startInput  = document.getElementById('startTime');
    const endInput    = document.getElementById('endTime');
    const durationBox = document.getElementById('durationBox');
    const durationLbl = document.getElementById('durationLabel');
    const overnight   = document.getElementById('overnightNote');
    const attendance  = document.getElementById('attendanceBox');
    const submitBtn   = document.getElementById('submitBtn');

    function formatMinutes(total) {
        const h = Math.floor(total / 60);
        const m = total % 60;
        if (h === 0) return m + ' m';
        return m === 0 ? h + ' h' : h + ' h ' + m + ' m';
    }

    // Perhitungan ini SENGAJA menyalin aturan OvertimeService: jam selesai yang
    // lebih awal dianggap hari berikutnya. Yang ditampilkan di sini harus sama
    // persis dengan yang nanti disimpan.
    function recalcDuration() {
        if (!startInput.value || !endInput.value) {
            durationBox.classList.add('hidden');
            return;
        }

        const [sh, sm] = startInput.value.split(':').map(Number);
        const [eh, em] = endInput.value.split(':').map(Number);

        let minutes = (eh * 60 + em) - (sh * 60 + sm);
        const crosses = minutes <= 0;
        if (crosses) minutes += 24 * 60;

        durationBox.classList.remove('hidden');
        durationLbl.textContent = minutes > 0 ? formatMinutes(minutes) : '—';
        overnight.classList.toggle('hidden', !crosses);

        // Menahan tombol saat aturannya jelas dilanggar lebih ramah daripada
        // membiarkan pengguna mengirim lalu menerima penolakan.
        const blocked = crosses && !ALLOW_OVERNIGHT;
        submitBtn.disabled = blocked;
        overnight.classList.toggle('text-red-700', blocked);
        if (blocked) {
            overnight.textContent = 'Overtime cannot pass midnight. Ask HR to enable it in Overtime Settings.';
        } else {
            overnight.textContent = 'This ends on the next day.';
        }
    }

    async function loadAttendanceHint() {
        if (!dateInput.value) return;

        try {
            const res = await fetch(HINT_URL + '?date=' + encodeURIComponent(dateInput.value), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });

            if (!res.ok) throw new Error('Request failed with status ' + res.status);

            const body = res.json ? await res.json() : null;
            const data = body?.data ?? {};

            attendance.classList.remove('hidden', 'bg-green-50', 'border-green-200',
                'bg-amber-50', 'border-amber-200', 'bg-gray-50', 'border-gray-200');

            if (data.state === 'found') {
                attendance.classList.add('bg-green-50', 'border-green-200');
                attendance.innerHTML =
                    '<span class="font-semibold text-green-900">Attendance on this date:</span> ' +
                    '<span class="text-green-800">check-in ' + (data.check_in || '–') +
                    ', check-out ' + (data.check_out || '–') +
                    ' · beyond schedule: ' + data.overtime_label + '</span>' +
                    '<span class="block text-xs text-green-700 mt-1">' +
                    'This is shown for comparison only. Your claim is what gets reviewed.</span>';
            } else if (data.state === 'missing') {
                attendance.classList.add('bg-amber-50', 'border-amber-200');
                attendance.innerHTML =
                    '<span class="font-semibold text-amber-900">No attendance record on this date.</span>' +
                    '<span class="block text-xs text-amber-800 mt-1">' +
                    'You can still submit — the request will simply be flagged for the reviewer.</span>';
            } else {
                attendance.classList.add('bg-gray-50', 'border-gray-200');
                attendance.innerHTML =
                    '<span class="text-gray-700">This date has not happened yet, so there is no attendance to compare against.</span>';
            }
        } catch (error) {
            // Pembanding hanyalah bantuan. Kegagalannya tidak boleh menghalangi
            // pengajuan — kotaknya disembunyikan dan formnya tetap dapat dikirim.
            console.warn('Attendance hint unavailable', error);
            attendance.classList.add('hidden');
        }
    }

    startInput.addEventListener('change', recalcDuration);
    endInput.addEventListener('change', recalcDuration);
    dateInput.addEventListener('change', loadAttendanceHint);

    recalcDuration();
    loadAttendanceHint();

    // Konfirmasi berisi ringkasan yang akan tersimpan, mengikuti pola Branches.
    document.getElementById('overtimeForm').addEventListener('submit', async function (event) {
        const form = event.target;
        if (form.dataset.confirmed === 'yes') return;

        event.preventDefault();

        const ok = await showConfirm(
            'Submit overtime on ' + dateInput.value + ', ' + startInput.value + ' to ' + endInput.value +
            ' (' + durationLbl.textContent + ')? It will be sent for review and cannot be edited afterwards.',
            'Submit Overtime Request',
            'primary',
            { okText: 'Submit', cancelText: 'Review Again' }
        );

        if (!ok) return;

        form.dataset.confirmed = 'yes';
        submitBtn.disabled = true;
        form.submit();
    });
</script>
@endpush
