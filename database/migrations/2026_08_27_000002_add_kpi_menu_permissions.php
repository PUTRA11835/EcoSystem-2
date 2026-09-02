<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * KPI Module — Menu Slug Registration
 *
 * All slugs live under the `general` parent (same as Attendance, Overtime,
 * Reimbursement) so the owner can manage them from a single branch in
 * Control Center → Menu Access.
 *
 * ESS slugs   → top-level sidebar items accessible to every active employee
 * HR slugs    → HR & General dropdown items (HR/Admin role only by default)
 * Setting slugs → Management → HR & General settings sub-menu
 *
 * Per MenuRegistrar convention: all slugs start ACTIVE for EC Administrator
 * only. The system owner grants access to other roles via the UI.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    /** Pages that render in the sidebar as navigation items */
    private const PAGES = [
        // ESS — visible to all active employees via ESS sidebar
        'general.my-kpi'                => 'My KPI — Employee Dashboard',

        // HR — visible inside HR & General dropdown
        'general.kpi-evaluation'        => 'KPI Evaluation — HR Dashboard',
        'general.kpi-evaluation.list'   => 'KPI Evaluation — Evaluation List',

        // Settings — inside Management → HR & General sub-menu
        'general.settings.kpi'          => 'Settings — KPI Templates',
    ];

    /** Function slugs: buttons/capabilities within a page (not sidebar items) */
    private const FUNCTIONS = [
        // ESS self-assessment
        'general.my-kpi.self-assessment'        => 'My KPI — Submit Self-Assessment',

        // HR evaluation management
        'general.kpi-evaluation.create'         => 'KPI Evaluation — Create Evaluation',
        'general.kpi-evaluation.review'         => 'KPI Evaluation — Review / Score',
        'general.kpi-evaluation.approve'        => 'KPI Evaluation — Approve / Reject',
        'general.kpi-evaluation.export'         => 'KPI Evaluation — Export Excel',

        // Template management
        'general.settings.kpi.manage'           => 'KPI Settings — Create / Edit / Delete Templates',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES,     60, 'page');
        MenuRegistrar::register(self::PARENT_SLUG, self::FUNCTIONS, 70, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::FUNCTIONS));
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
