{{--
    Daftar tab hub Attendance — SATU tempat untuk enam halaman (D175).

    Urutan tab TETAP di sini, tidak bergantung slug yang dipegang pengguna;
    yang berubah per pengguna hanya MANA yang dirender (lihat hub-tabs.blade.php).
    Ubah label/ikon/urutan di sini, otomatis berlaku di keenam halaman.
--}}
@php
    $hubTabs = [
        [
            'label' => 'Daily Recap',
            'icon'  => 'calendar-day',
            'route' => 'general.attendance.daily',
            'is'    => 'general/attendance',
            'gate'  => 'general.attendance',
        ],
        [
            'label' => 'Monthly Recap',
            'icon'  => 'calendar-days',
            'route' => 'general.attendance.monthly',
            'is'    => 'general/attendance/monthly',
            'gate'  => 'general.attendance.monthly',
        ],
        [
            'label' => 'Corrections',
            'icon'  => 'user-check',
            'route' => 'general.attendance.corrections.index',
            'is'    => 'general/attendance/corrections*',
            'gate'  => 'general.attendance.correction',
            // 🔴 D179 — HR-wide, bukan "milik saya": siapa pun yang boleh
            // membuka Corrections boleh meninjau baris apa pun (satu slug,
            // D77), jadi hitungannya sama untuk semua penerimanya.
            'badge' => fn () => \App\Models\Attendance\AttendanceCorrection::pending()->count(),
        ],
        [
            'label' => 'Branches',
            'icon'  => 'map-marker-alt',
            'route' => 'general.attendance.branches.index',
            'is'    => 'general/attendance/branches*',
            'gate'  => 'general.settings.branches',
        ],
        [
            'label' => 'Shifts',
            'icon'  => 'clock',
            'route' => 'general.attendance.shifts.index',
            'is'    => 'general/attendance/shifts*',
            'gate'  => 'general.settings.shifts',
        ],
        [
            'label' => 'Settings',
            'icon'  => 'sliders',
            'route' => 'general.attendance.settings.edit',
            'is'    => 'general/attendance/settings*',
            'gate'  => 'general.settings.attendance',
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
