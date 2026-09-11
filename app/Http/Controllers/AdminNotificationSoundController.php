<?php

namespace App\Http\Controllers;

use App\Models\NotificationSound;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminNotificationSoundController extends Controller
{
    private function assertAdmin(): void
    {
        if ((int) session('user.role.id') !== 1) {
            abort(403, 'Superadmin only.');
        }
    }

    /**
     * GET /admin/sounds  — management page
     */
    public function index()
    {
        $this->assertAdmin();
        $sounds = NotificationSound::orderBy('is_default', 'desc')->orderBy('name')->get();
        return view('admin.sounds', compact('sounds'));
    }

    /**
     * POST /admin/sounds  — upload new sound
     */
    public function store(Request $request)
    {
        $this->assertAdmin();

        $request->validate([
            'name' => 'required|string|max:60',
            'file' => 'required|file|mimes:wav,mp3,ogg,aac|max:3072',
        ], [
            'file.mimes' => 'Only WAV, MP3, OGG, and AAC files are allowed.',
            'file.max'   => 'File size must not exceed 3 MB.',
        ]);

        $file     = $request->file('file');
        $slug     = Str::slug($request->name);
        $ext      = $file->getClientOriginalExtension();
        $filename = $slug . '_' . time() . '.' . $ext;

        // Move to public/sounds/
        $file->move(public_path('sounds'), $filename);

        $sound = NotificationSound::create([
            'name'       => trim($request->name),
            'filename'   => $filename,
            'is_default' => false,
        ]);

        Log::info('Notification sound uploaded', [
            'id'       => $sound->id,
            'name'     => $sound->name,
            'filename' => $sound->filename,
            'by'       => session('user.name'),
        ]);

        return redirect()->route('admin.sounds')->with('success', "Sound \"{$sound->name}\" uploaded successfully.");
    }

    /**
     * DELETE /admin/sounds/{id}  — remove sound
     */
    public function destroy($id)
    {
        $this->assertAdmin();

        $sound = NotificationSound::findOrFail($id);

        if ($sound->is_default) {
            return redirect()->route('admin.sounds')->with('error', 'The default sound cannot be deleted.');
        }

        // Delete physical file
        $path = public_path('sounds/' . $sound->filename);
        if (file_exists($path)) {
            unlink($path);
        }

        Log::info('Notification sound deleted', [
            'id'       => $sound->id,
            'name'     => $sound->name,
            'filename' => $sound->filename,
            'by'       => session('user.name'),
        ]);

        $sound->delete();

        return redirect()->route('admin.sounds')->with('success', "Sound \"{$sound->name}\" deleted.");
    }

    /**
     * GET /api/notification-sounds  — JSON list for settings page
     */
    public function list()
    {
        $sounds = NotificationSound::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'filename'   => $s->filename,
                'url'        => '/sounds/' . $s->filename,
                'is_default' => $s->is_default,
            ]);

        return response()->json(['success' => true, 'data' => $sounds]);
    }
}
