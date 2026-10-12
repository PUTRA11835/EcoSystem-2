<?php

namespace App\Http\Controllers;

use App\Models\AppConfig;
use App\Models\AuditLog;
use App\Support\GlobalSeasonalTheme;
use App\Support\SeasonalThemes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Control Center → Seasonal Theme.
 *
 * Menentukan tema musiman DEFAULT untuk seluruh user (dashboard) + halaman
 * login. BUKAN dipaksakan ke semua orang: user yang preferensinya sendiri
 * sudah eksplisit ('none' atau tema tertentu di Settings > Appearance) tidak
 * ikut berubah — lihat App\Support\SeasonalThemeResolver, satu-satunya
 * tempat aturan "default ikut admin, user boleh menimpa" ditegakkan.
 *
 * Sengaja TIDAK hardcode role id, sama seperti AiSettingsController: akses
 * lewat slug `control-center.seasonal-theme` yang lahir aktif hanya untuk
 * EC Administrator, dan bisa dipindah lewat Menu Access tanpa ubah kode.
 */
class SeasonalThemeSettingsController extends Controller
{
    public function index()
    {
        $settings = GlobalSeasonalTheme::all();

        return view('admin.seasonal-theme-settings', [
            'active' => $settings['theme'],
            'activeSound' => $settings['sound'],
            'activeVolume' => $settings['volume'],
            'catalog' => SeasonalThemes::catalog(),
            // Admin memilih dari file yang BENAR-BENAR ADA di public/sounds/,
            // bukan mengetik bebas — daftar ini juga yang jadi acuan whitelist
            // di GlobalSeasonalTheme::sanitizeSound(). Ini folder yang sama
            // dipakai fitur Notif Sounds (AdminNotificationSoundController) —
            // sengaja tidak dipisah folder supaya admin tidak perlu ingat 2
            // tempat upload berbeda untuk hal yang sama-sama "file suara".
            'availableSounds' => collect(glob(public_path('sounds/*.{mp3,wav,ogg,aac}'), GLOB_BRACE))
                ->map(fn ($path) => basename($path))
                ->sort()
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'theme' => 'required|string',
            'sound' => 'nullable|string',
            'volume' => 'nullable|numeric|min:0|max:100',
        ]);

        $before = GlobalSeasonalTheme::all();

        // Validasi sebenarnya (apakah key dikenal SeasonalThemes, apakah file
        // sound benar-benar ada di public/sounds/, volume dalam rentang wajar)
        // ada di GlobalSeasonalTheme::sanitize()/sanitizeSound()/sanitizeVolume(),
        // bukan diduplikasi di sini — supaya cuma ada satu tempat yang
        // menentukan apa yang "valid". SEMUA field DIKIRIM BERSAMA (lihat
        // catatan di GlobalSeasonalTheme::save()) supaya tidak ada yang
        // diam-diam ke-reset ke default.
        GlobalSeasonalTheme::save([
            'theme' => $request->input('theme'),
            'sound' => $request->input('sound'),
            'volume' => $request->input('volume'),
        ]);

        $applied = GlobalSeasonalTheme::all();

        Log::info('Global seasonal theme updated', [
            'by' => session('user.name'),
            'settings' => $applied,
        ]);

        $configId = AppConfig::where('key', GlobalSeasonalTheme::KEY)->value('id') ?? 0;

        AuditLog::recordAction(
            module: 'Seasonal Theme',
            auditableType: 'AppConfig',
            auditableId: $configId,
            event: 'updated',
            recordLabel: 'Global seasonal theme',
            description: 'updated global seasonal theme',
            old: $before,
            new: $applied,
        );

        return redirect()
            ->route('admin.seasonal-theme')
            ->with('success', 'Seasonal theme saved. Users on "Default" see it immediately; users who picked their own stay unaffected.');
    }

    /**
     * Upload lagu baru KHUSUS untuk musik latar musiman.
     *
     * Sengaja MENIRU validasi & folder tujuan AdminNotificationSoundController::store()
     * persis (sama-sama public/sounds/, sama-sama wav/mp3/ogg/aac maks 3MB) —
     * itu permintaan eksplisit user ("sesuaikan dengan notifikasi itu"), satu
     * folder upload untuk SEMUA file suara di app ini, bukan dua tempat
     * berbeda yang harus diingat admin. TAPI sengaja TIDAK membuat baris
     * NotificationSound: tabel itu murni untuk bunyi notifikasi bel/ticket,
     * lagu tema Natal bukan "notification sound" dan tidak semestinya muncul
     * di pemilih notifikasi. GlobalSeasonalTheme::sanitizeSound() cukup
     * mengecek file_exists() di folder ini, tidak butuh baris DB terpisah.
     */
    public function uploadSound(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:wav,mp3,ogg,aac|max:3072',
        ], [
            'file.mimes' => 'Only WAV, MP3, OGG, and AAC files are allowed.',
            'file.max'   => 'File size must not exceed 3 MB.',
        ]);

        $file     = $request->file('file');
        $slug     = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext      = $file->getClientOriginalExtension();
        $filename = ($slug !== '' ? $slug : 'track') . '_' . time() . '.' . $ext;

        $file->move(public_path('sounds'), $filename);

        Log::info('Seasonal music track uploaded', [
            'filename' => $filename,
            'by'       => session('user.name'),
        ]);

        return redirect()
            ->route('admin.seasonal-theme')
            ->with('success', "Uploaded \"{$filename}\" — select it below and save to make it active.");
    }
}
