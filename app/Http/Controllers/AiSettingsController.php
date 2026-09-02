<?php

namespace App\Http\Controllers;

use App\Support\AiModelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Control Center → AI Settings.
 *
 * Satu-satunya tempat model AI ditentukan. Halaman ini sengaja TIDAK memakai
 * hardcode role id: aksesnya lewat slug `control-center.ai-settings` yang
 * (sesuai aturan slug baru) lahir aktif hanya untuk EC Administrator, dan
 * pemilik sistem bisa memindahkannya lewat Menu Access tanpa ubah kode.
 *
 * Validasi di sini sengaja LONGGAR — cukup memastikan bentuknya array —
 * karena penegakan yang sebenarnya (model dikenal, model mendukung server
 * tool, plafon token milik model, effort yang ditolak Haiku) ada di
 * AiModelSettings::sanitize(). Menduplikasinya di sini hanya membuat dua
 * tempat yang bisa berbeda pendapat.
 */
class AiSettingsController extends Controller
{
    public function index()
    {
        $assistants = AiModelSettings::assistants();

        return view('admin.ai-settings', [
            'settings' => AiModelSettings::all(),
            'assistants' => $assistants,
            'catalog' => AiModelSettings::catalog(),

            // Daftar model yang boleh dipilih DIHITUNG PER ASISTEN di sini,
            // bukan disaring ulang di Blade. Sebelumnya Blade hanya menyaring
            // baris AI Research, padahal AiModelSettings::sanitize() menolak
            // model tanpa server tool untuk Ticket Analyzer, AI Summarize, dan
            // kedua fase Word Report juga — jadi form menawarkan pilihan yang
            // diam-diam dikembalikan ke bawaan begitu disimpan.
            'allowedByAssistant' => array_map(
                static fn ($key) => AiModelSettings::catalogFor($key),
                array_combine(array_keys($assistants), array_keys($assistants))
            ),
            'requiresWebByAssistant' => array_map(
                static fn ($key) => AiModelSettings::requiresServerTools($key),
                array_combine(array_keys($assistants), array_keys($assistants))
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'assistants' => 'required|array',
        ]);

        AiModelSettings::save($request->input('assistants', []));

        $applied = AiModelSettings::all();

        // Dicatat karena ini keputusan yang mempengaruhi tagihan: kalau biaya
        // melonjak bulan depan, jejak siapa mengubah apa ada di sini.
        // Seluruh $applied dicatat, bukan cuma research+internal seperti dulu:
        // setiap asisten yang ditambahkan ke AiModelSettings ikut menagih, dan
        // daftar tetap di sini membuat asisten baru (mis. AI Summarize) hilang
        // dari jejak audit tanpa ada yang sadar.
        Log::info('AI model settings updated', [
            'by' => session('user.name'),
            'settings' => $applied,
        ]);

        return redirect()
            ->route('admin.ai-settings')
            ->with('success', 'AI model settings saved. New chats use them immediately.');
    }
}
