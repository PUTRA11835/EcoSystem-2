<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Proses email masuk setiap menit → buat tiket / tambah pesan ke tiket
Schedule::command('email:process-inbox')
    ->everyMinute()
    ->name('email-process-inbox');

// Reminder Microsoft Teams (lewat Power Automate) untuk tiket yang masih berstatus
// open. Jalan tiap menit karena jarak antar reminder memang dikonfigurasi dalam
// menit; command-nya sendiri yang memutuskan tiket mana yang sudah jatuh tempo
// (services.power_automate.reminder), dan langsung keluar tanpa efek kalau flow
// belum dikonfigurasi. withoutOverlapping supaya run yang lambat tidak menumpuk.
Schedule::command('tickets:open-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('tickets-open-reminders');

// Recompute delivery activity statuses tiap hari 00:05 (untuk transisi delayed berbasis tanggal)
Schedule::command('activities:recompute-status')
    ->dailyAt('00:05')
    ->name('activities-recompute-status');

// Reminder harian untuk Head of Project & Project Admin:
// - contract end date dalam 30 hari / overdue (pertimbangan adendum)
// - TOP invoice jatuh tempo (estimated_date) yang belum diisi Submit Invoice Date
Schedule::command('notifications:project-reminders')
    ->dailyAt('07:00')
    ->name('notifications-project-reminders');

// Periksa & perbaiki share link OneDrive tiap hari 02:30 — link bisa mati sendiri
// (kebijakan expiry "Anyone links", scope diturunkan tenant, izin dicabut manual)
// dan tanpa ini kegagalannya baru ketahuan saat customer melapor tidak bisa akses.
Schedule::command('onedrive:audit-links --fix')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->name('onedrive-audit-links');

// Buang percakapan AI yang kedaluwarsa. Cache file Laravel hanya menghapus entri
// kedaluwarsa saat kuncinya dibaca lagi, jadi percakapan yang ditinggalkan (tab
// ditutup, tidak pernah kembali) menetap di disk selamanya — termasuk lampiran
// gambarnya yang ikut tersimpan di dalam riwayat.
//
// DUA KALI SEHARI, bukan sekali (20 Agu 2026): TTL konteks naik dari 1 jam ke
// 12 jam, jadi berkasnya hidup 12× lebih lama DAN mati di jam yang tersebar
// sepanjang hari. Dengan jadwal harian, percakapan yang kedaluwarsa pukul 16:00
// baru dibersihkan pukul 03:00 esoknya — belasan jam memegang byte gambar yang
// sudah tidak ada gunanya. Pemangkasan arsip DB yang menumpang command ini
// berumur bulan, jadi ikut jalan dua kali sehari sama sekali tidak masalah.
Schedule::command('ai:prune-conversations --apply')
    ->twiceDailyAt(3, 15, 0)
    ->withoutOverlapping()
    ->name('ai-prune-conversations');

// Security Center: privilege escalation / mass export / anomalous login
// detection. Correlation-based, so it runs periodically rather than inline
// with the request that wrote the underlying audit_logs/login_activity row.
Schedule::command('security:detect-anomalies')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('security-detect-anomalies');

// Schedule Monitor's own staleness sweep (see ScheduleMonitorService::
// checkStaleness()). Deliberately NOT given ->name() - it is infrastructure
// for the monitor, not a task the monitor should watch/alert on itself.
Schedule::command('schedule-monitor:check-staleness')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Queue worker backlog sweep (see ScheduleMonitorService::
// checkQueueHealthAlerts()) - same "infrastructure, not a monitored task"
// reasoning as the staleness sweep above.
Schedule::command('schedule-monitor:check-queue-health')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
