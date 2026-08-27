<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Master sumber presensi.
 *
 * Dipisah dari AttendanceSettingController karena sifatnya berbeda: yang satu
 * mengubah satu baris pengaturan, yang ini mengelola daftar yang dapat
 * bertambah. Keduanya tampil di halaman Attendance Settings.
 */
class AttendanceSourceController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        // Kode diturunkan dari nama dan dikunci setelah dibuat, karena nilainya
        // ikut tersimpan di attendance_records.source. Kode yang berubah akan
        // membuat riwayat presensi lama menunjuk ke sumber yang tidak ada.
        $data['code']       = $this->uniqueCode($data['name']);
        $data['is_builtin'] = false;

        $this->persist($data, new AttendanceSource());

        return back()->with('success', "Attendance source \"{$data['name']}\" has been added.");
    }

    public function update(Request $request, AttendanceSource $source)
    {
        $data = $this->validatePayload($request, $source);

        $this->persist($data, $source);

        return back()->with('success', "Attendance source \"{$source->name}\" has been updated.");
    }

    public function destroy(AttendanceSource $source)
    {
        if ($source->is_builtin) {
            return back()->with('error',
                "\"{$source->name}\" is a built-in source and cannot be deleted. Deactivate it instead.");
        }

        // Sumber yang sudah pernah dipakai TIDAK boleh hilang, kalau tidak
        // baris presensi lama akan menampilkan kode mentah tanpa nama.
        $used = DB::table('attendance_records')->where('source', $source->code)->exists();

        if ($used) {
            return back()->with('error',
                "\"{$source->name}\" is already referenced by existing attendance records and cannot be deleted. "
                . 'Deactivate it instead so it stays available for historical data.');
        }

        $name = $source->name;
        $source->delete();

        return back()->with('success', "Attendance source \"{$name}\" has been deleted.");
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function persist(array $data, AttendanceSource $source): void
    {
        DB::transaction(function () use ($data, $source) {
            // Hanya SATU sumber yang boleh menjadi jalur presensi web.
            // Ditegakkan di sini karena MariaDB tidak mendukung unique parsial.
            if (!empty($data['is_web_checkin'])) {
                AttendanceSource::where('is_web_checkin', true)
                    ->when($source->exists, fn ($q) => $q->where('id', '!=', $source->id))
                    ->update(['is_web_checkin' => false]);

                // Jalur presensi web mustahil dipakai bila sumbernya nonaktif.
                $data['is_active'] = true;
            }

            $source->fill($data)->save();
        });
    }

    private function validatePayload(Request $request, ?AttendanceSource $existing = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('attendance_sources', 'name')->ignore($existing?->id),
            ],
            'description'    => ['nullable', 'string', 'max:255'],
            'sort_order'     => ['nullable', 'integer', 'between:1,999'],
            'is_active'      => ['nullable', 'boolean'],
            'is_web_checkin' => ['nullable', 'boolean'],
        ]);

        $validated['is_active']      = $request->boolean('is_active');
        $validated['is_web_checkin'] = $request->boolean('is_web_checkin');
        $validated['sort_order']     = $validated['sort_order'] ?? 50;

        // Nama boleh diubah; kode bawaan tidak pernah ikut berubah.
        if ($existing) {
            unset($validated['code']);
        }

        return $validated;
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'source';
        $code = Str::limit($base, 30, '');
        $n    = 1;

        while (AttendanceSource::where('code', $code)->exists()) {
            $code = Str::limit($base, 27, '') . '_' . (++$n);
        }

        return $code;
    }
}
