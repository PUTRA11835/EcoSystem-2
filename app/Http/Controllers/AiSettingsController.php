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
        return view('admin.ai-settings', [
            'settings' => AiModelSettings::all(),
            'assistants' => AiModelSettings::assistants(),
            'catalog' => AiModelSettings::catalog(),
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
        Log::info('AI model settings updated', [
            'by' => session('user.name'),
            'research' => $applied[AiModelSettings::RESEARCH],
            'internal' => $applied[AiModelSettings::INTERNAL],
        ]);

        return redirect()
            ->route('admin.ai-settings')
            ->with('success', 'AI model settings saved. New chats use them immediately.');
    }
}
