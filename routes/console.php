<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Proses email masuk setiap menit → buat tiket / tambah pesan ke tiket
Schedule::command('email:process-inbox')->everyMinute();

// Recompute delivery activity statuses tiap hari 00:05 (untuk transisi delayed berbasis tanggal)
Schedule::command('activities:recompute-status')->dailyAt('00:05');

// Reminder harian untuk Head of Project & Project Admin:
// - contract end date dalam 30 hari / overdue (pertimbangan adendum)
// - TOP invoice jatuh tempo (estimated_date) yang belum diisi Submit Invoice Date
Schedule::command('notifications:project-reminders')->dailyAt('07:00');

// Periksa & perbaiki share link OneDrive tiap hari 02:30 — link bisa mati sendiri
// (kebijakan expiry "Anyone links", scope diturunkan tenant, izin dicabut manual)
// dan tanpa ini kegagalannya baru ketahuan saat customer melapor tidak bisa akses.
Schedule::command('onedrive:audit-links --fix')
    ->dailyAt('02:30')
    ->withoutOverlapping();

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
    ->withoutOverlapping();
