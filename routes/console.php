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
