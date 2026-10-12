<?php

namespace App\Http\Controllers;

use App\Models\AppConfig;
use App\Models\AuditLog;
use App\Support\TwoFactorEnforcementSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Control Center → Two-Factor Enforcement.
 *
 * Menentukan role mana yang WAJIB mengaktifkan 2FA sebelum bisa memakai
 * aplikasi (dibaca App\Http\Middleware\EnforceTwoFactorForAdmins di setiap
 * request). Sengaja TIDAK hardcode role id — sama seperti
 * AiSettingsController/SeasonalThemeSettingsController — akses lewat slug
 * `control-center.two-factor-enforcement` yang lahir aktif hanya untuk EC
 * Administrator, dan bisa dipindah lewat Menu Access tanpa ubah kode.
 */
class TwoFactorEnforcementSettingsController extends Controller
{
    public function index()
    {
        return view('admin.two-factor-enforcement-settings', [
            'enforcedRoleIds' => TwoFactorEnforcementSettings::roleIds(),
            'roles'           => DB::table('employee_role')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:employee_role,id'],
        ]);

        $before = TwoFactorEnforcementSettings::roleIds();

        // role_ids sengaja boleh kosong/tidak dikirim sama sekali — itu berarti
        // admin uncheck semua role, alias "2FA tidak wajib untuk siapapun".
        TwoFactorEnforcementSettings::save($request->input('role_ids', []));

        $applied = TwoFactorEnforcementSettings::roleIds();

        Log::info('Two-factor enforcement setting updated', [
            'by'       => session('user.name'),
            'role_ids' => $applied,
        ]);

        $configId = AppConfig::where('key', TwoFactorEnforcementSettings::KEY)->value('id') ?? 0;

        AuditLog::recordAction(
            module: 'Security',
            auditableType: 'AppConfig',
            auditableId: $configId,
            event: 'updated',
            recordLabel: 'Two-factor enforcement',
            description: 'updated two-factor enforcement roles',
            old: ['role_ids' => $before],
            new: ['role_ids' => $applied],
        );

        return redirect()
            ->route('admin.two-factor-enforcement')
            ->with('success', empty($applied)
                ? 'Two-factor authentication is no longer mandatory for any role.'
                : 'Two-factor authentication is now mandatory for the selected role(s).');
    }
}
