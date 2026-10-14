<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Services\HrProfile\ProfileLockPolicy;
use App\Services\HrProfile\ProfileLockService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate untuk endpoint section employee (basic-data, address, family, dst).
 *
 * Endpoint /api/employees/{id}/<section> dipakai oleh DUA halaman yang
 * merender view yang sama (master.employee.show):
 *
 *   - Master > Employee > detail  -> mengubah data ORANG LAIN
 *   - My Profile (/profile)       -> mengubah data DIRI SENDIRI
 *
 * Karena itu gate-nya tidak boleh satu slug saja: memakai
 * master.employee.* akan mencabut kemampuan orang mengedit profilnya
 * sendiri, memakai my-profile.* akan membocorkan data orang lain.
 * Target request yang menentukan slug mana yang dipakai:
 *
 *   target == diri sendiri -> my-profile.section.{key}.{view|update}
 *   target == orang lain   -> employee.section.{key}.{view|update}
 *
 * Pemakaian di route:
 *
 *     ->middleware('employee.section:family,update')
 *
 * Argumen kedua opsional, default 'update' (semua endpoint tulis). Tab berisi daftar rekaman memakai tiga
 * kemampuan, sesuai kotak di Menu Access:
 *
 *     'create'  tambah rekaman  (flag Create pada baris employee.section.{key}.view)
 *     'update'  ubah rekaman    (slug employee.section.{key}.update)
 *     'delete'  hapus rekaman   (flag Delete pada baris employee.section.{key}.view)
 *
 * Untuk profil SENDIRI ketiganya tetap mengikuti satu slug my-profile.section.{key}.update.
 *
 * JENIS TARGET (13 Okt 2026): untuk orang lain, keluarga slug dipilih menurut jenis karyawan targetnya —
 *   target Internal -> employee.section.{key}.*   (HR, Master Employee)
 *   target External -> external.section.{key}.*   (RPMO, External → Consultants)
 * sehingga siapa pun tidak dapat menjangkau jenis lain lewat ID di URL/API, dan Admin mengatur keduanya terpisah di Menu Access.
 * Tab yang tidak berlaku bagi External (family, contract, payment, salary, compensation) ditolak untuk target External.
 */
class CheckEmployeeSectionAccess
{
    public function handle(Request $request, Closure $next, string $sectionKey, string $ability = 'update'): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return $this->deny($request, 'Unauthenticated.', 401);
        }

        $selfId   = (int) ($user['id'] ?? 0);
        $targetId = (int) ($request->route('employeeId') ?? $request->route('id') ?? 0);

        $isSelf = $targetId === $selfId && $targetId !== 0;
        $writes = in_array($ability, ['update', 'create', 'delete'], true);

        if ($isSelf) {
            $allowed = $this->allows($selfId, "my-profile.section.{$sectionKey}." . ($writes ? 'update' : $ability));
        } else {
            $prefix = self::scopePrefix($targetId, $sectionKey);
            if ($prefix === null) {
                return $this->deny($request, 'Data ini tidak berlaku untuk konsultan External.', 403);
            }
            if (in_array($ability, ['create', 'delete'], true)) {
                $allowed = $this->allowsFlag($selfId, "{$prefix}.{$sectionKey}.view", $ability);
            } else {
                $allowed = $this->allows($selfId, "{$prefix}.{$sectionKey}.{$ability}");
            }
        }

        if (!$allowed) {
            $what = ['create' => 'menambah', 'delete' => 'menghapus'][$ability] ?? 'mengubah';

            return $this->deny(
                $request,
                "Akses ditolak. Akun Anda tidak memiliki izin untuk {$what} data ini. Hubungi administrator.",
                403
            );
        }

        // H3.11 (HC-D29): profil yang sudah di-"Verify & Lock" HR tidak dapat diubah PEMILIKNYA pada seksi yang
        // dinilai Onboarding. Hanya target == diri sendiri; HR/admin yang mengubah orang lain tidak tertahan.
        // isLocked() tidak pernah melempar galat (tabel belum ada → dianggap tidak terkunci).
        if ($writes && $isSelf
            && ProfileLockPolicy::blocksOwnerUpdate(app(ProfileLockService::class)->isLocked($selfId), $sectionKey)) {
            return $this->deny(
                $request,
                'Your profile has been verified and locked by HR. Please contact HR if you need to change this data.',
                403
            );
        }

        // HC-D62: Since Date (join date) HANYA diisi HR — otomatis dari offering letter saat Accept, kelak dari kontrak.
        // Pemilik (target == diri sendiri) mengirim since_date dari form Basic Data; nilainya DIBUANG dari permintaan agar
        // kolom tersimpan tidak tertimpa (store() hanya memperbarui kolom yang dikirim). HR yang mengubah orang lain tak terpengaruh.
        if ($sectionKey === 'basic_data' && $ability === 'update' && $targetId === $selfId && $targetId !== 0) {
            $request->request->remove('since_date');
            if ($request->isJson()) {
                $request->json()->remove('since_date');
            }
        }

        return $next($request);
    }

    /** Tab yang berlaku bagi konsultan External (sama dengan migrasi external_section_permissions). */
    public const EXTERNAL_TABS = ['basic_data', 'address', 'identification', 'education', 'qualification', 'bank', 'attachment', 'hr_profile', 'engagement', 'engagement_rate'];

    /**
     * Awalan slug untuk target orang lain: `employee.section` (Internal), `external.section` (External), atau null bila tab
     * itu tidak berlaku bagi External. Jenis dibaca dari employee_basic_data (kosong = Internal).
     */
    public static function scopePrefix(int $targetId, string $sectionKey): ?string
    {
        $type = \Illuminate\Support\Facades\DB::table('employee_basic_data')->where('employee_id', $targetId)->value('employee_type');
        if ($type !== 'External') {
            return 'employee.section';
        }

        return in_array($sectionKey, self::EXTERNAL_TABS, true) ? 'external.section' : null;
    }

    /**
     * Pakai daftar slug yang sudah di-cache ShareMenuPermissions supaya
     * satu request tidak memicu query menu berulang kali.
     */
    private function allows(int $employeeId, string $slug): bool
    {
        $slugs = Cache::get("perm_slugs_{$employeeId}");

        if ($slugs === null) {
            $employee = Employee::find($employeeId);
            $slugs    = $employee ? $employee->allPermissionSlugs() : [];
        }

        return in_array($slug, $slugs, true);
    }

    /** Flag Create / Delete pada baris menu `$slug` (matriks izin yang sama dengan `canDo()` di view). */
    private function allowsFlag(int $employeeId, string $slug, string $ability): bool
    {
        $matrix = Cache::get("perm_matrix_{$employeeId}");

        if ($matrix === null) {
            $employee = Employee::find($employeeId);
            $matrix   = $employee ? $employee->allPermissionMatrix() : [];
        }

        return (bool) ($matrix[$slug][$ability] ?? false);
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return redirect()->route('dashboard')->with('warning', $message);
    }
}
