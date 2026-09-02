<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;

class EssSettingsController extends Controller
{
    /**
     * Master list of ESS menu items.
     */
    public const ESS_ITEMS = [
        'home' => [
            'name'  => 'Home',
            'route' => 'dashboard',
            'icon'  => 'fas fa-home',
        ],
        'my_profile' => [
            'name'  => 'My Profile',
            'route' => 'profile.my',
            'icon'  => 'fas fa-user-circle',
        ],
        'logout' => [
            'name'  => 'Logout',
            'route' => 'logout',
            'icon'  => 'fas fa-sign-out-alt',
        ],
        'my_attendance' => [
            'name'  => 'My Attendance',
            'route' => 'general.my-attendance.index',
            'icon'  => 'fas fa-user-clock',
        ],
        'my_leave_permit' => [
            'name'  => 'My Leave and Permit',
            'route' => 'my-leave-permit',
            'icon'  => 'fas fa-calendar-check',
        ],
        'overtime' => [
            'name'  => 'Overtime',
            'route' => 'general.my-overtime.index',
            'icon'  => 'fas fa-business-time',
        ],
        'paystub' => [
            'name'  => 'Paystub',
            'route' => null,
            'icon'  => 'fas fa-file-invoice-dollar',
        ],
        // Nama tampilan diselaraskan menjadi "Reimbursement" (26 Agu 2026) agar
        // sama dengan sebutan modulnya di seluruh aplikasi. KUNCI array tetap
        // `expense_reimbursement` karena kunci itulah yang tersimpan di JSON
        // `ess_menu_settings`; menggantinya membuat setelan tersimpan tidak lagi
        // cocok dan sakelarnya diam-diam kembali ke bawaan.
        'expense_reimbursement' => [
            'name'  => 'Reimbursement',
            'route' => 'general.my-reimbursement.index',
            'icon'  => 'fas fa-receipt',
        ],
        // Menunjuk halaman sungguhan sejak 2 Sep 2026 (sebelumnya null =
        // coming-soon). KUNCI array `purchase_request` SENGAJA tidak diubah:
        // kunci itulah yang tersimpan di JSON `ess_menu_settings` (tabel
        // app_config). Menggantinya membuat setelan yang sudah disimpan tidak
        // lagi cocok dan sakelarnya diam-diam kembali ke bawaan — pelajaran yang
        // sama dengan `expense_reimbursement`.
        'purchase_request' => [
            'name'  => 'Purchase Request',
            'route' => 'general.my-purchase-request.index',
            'icon'  => 'fas fa-shopping-cart',
        ],
        'advance_payment_ca' => [
            'name'  => 'Advance Payment (CA)',
            'route' => null,
            'icon'  => 'fas fa-hand-holding-usd',
        ],
        'advance_payment_car' => [
            'name'  => 'Advance Payment Report (CAR)',
            'route' => null,
            'icon'  => 'fas fa-file-contract',
        ],
        'loans' => [
            'name'  => 'Loans',
            'route' => null,
            'icon'  => 'fas fa-landmark',
        ],
        'my_kpis' => [
            'name'  => 'My KPI',
            'route' => 'general.my-kpi.index',
            'icon'  => 'fas fa-chart-line',
        ],
        'events_calendar' => [
            'name'  => 'Events Calendar',
            'route' => 'calendar.events',
            'icon'  => 'fas fa-calendar-alt',
        ],
        'my_timesheet' => [
            'name'  => 'My Timesheet',
            'route' => 'calendar.timesheets',
            'icon'  => 'fas fa-clock',
        ],
        'ai_assistant' => [
            'name'  => 'AI Assistant',
            'route' => 'ai-assistant',
            'icon'  => 'fas fa-robot',
        ],
        'ai_research' => [
            'name'  => 'AI Research',
            'route' => 'ai-research',
            'icon'  => 'fas fa-magnifying-glass-chart',
        ],
    ];

    /**
     * Display ESS Settings page in Menu Management folder.
     */
    public function index()
    {
        $currentSettings = static::getEssSettings();
        
        return view('management.ess-settings.index', [
            'items'    => static::ESS_ITEMS,
            'settings' => $currentSettings,
        ]);
    }

    /**
     * Save global ESS menu settings.
     */
    public function update(Request $request)
    {
        $enabledKeys = $request->input('enabled_items', []);

        $newSettings = [];
        foreach (static::ESS_ITEMS as $key => $item) {
            $newSettings[$key] = in_array($key, $enabledKeys);
        }

        AppConfig::setJson('ess_menu_settings', $newSettings, 'Global visibility settings for ESS menu items');

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'ESS Settings updated successfully.',
                'settings' => $newSettings,
            ]);
        }

        return redirect()->back()->with('success', 'ESS Settings updated successfully.');
    }

    /**
     * Retrieve array of ESS menu visibility settings [key => bool].
     */
    public static function getEssSettings(): array
    {
        $defaults = [];
        foreach (static::ESS_ITEMS as $key => $item) {
            $defaults[$key] = true;
        }

        $saved = AppConfig::getJson('ess_menu_settings', []);
        return array_merge($defaults, $saved);
    }
}
