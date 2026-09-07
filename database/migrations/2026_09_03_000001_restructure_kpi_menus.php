<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * KPI Module — Menu Restructure
 *
 * The standalone "KPI Templates" settings page is retired. Template management
 * now lives as a tab inside the HR "KPI Evaluation" page, so its slugs move
 * under the `general.kpi-evaluation` parent:
 *
 *   general.settings.kpi         → general.kpi-evaluation.templates
 *   general.settings.kpi.manage  → general.kpi-evaluation.templates.manage
 *
 * Role grants that already existed on the old slug are copied to the new one
 * so nobody silently loses access. Everything else follows the MenuRegistrar
 * convention (new slugs start active for EC Administrator only).
 */
return new class extends Migration
{
    private const OLD_PAGE     = 'general.settings.kpi';
    private const OLD_FUNC     = 'general.settings.kpi.manage';
    private const NEW_PARENT   = 'general.kpi-evaluation';
    private const NEW_PAGE     = 'general.kpi-evaluation.templates';
    private const NEW_FUNC     = 'general.kpi-evaluation.templates.manage';

    public function up(): void
    {
        MenuRegistrar::register(self::NEW_PARENT, [
            self::NEW_PAGE => 'KPI Evaluation — Assessment Templates',
        ], 75, 'page');

        MenuRegistrar::register(self::NEW_PARENT, [
            self::NEW_FUNC => 'KPI Templates — Create / Edit / Delete',
        ], 76, 'function');

        $this->copyGrants(self::OLD_PAGE, self::NEW_PAGE);
        $this->copyGrants(self::OLD_FUNC, self::NEW_FUNC);

        MenuRegistrar::remove([self::OLD_FUNC, self::OLD_PAGE]);
    }

    public function down(): void
    {
        MenuRegistrar::register('general', [
            self::OLD_PAGE => 'Settings — KPI Templates',
        ], 63, 'page');
        MenuRegistrar::register('general', [
            self::OLD_FUNC => 'KPI Settings — Create / Edit / Delete Templates',
        ], 70, 'function');

        $this->copyGrants(self::NEW_PAGE, self::OLD_PAGE);
        $this->copyGrants(self::NEW_FUNC, self::OLD_FUNC);

        MenuRegistrar::remove([self::NEW_FUNC, self::NEW_PAGE]);
    }

    /**
     * Mirror every role_menu row from $fromSlug onto $toSlug, preserving the
     * can_view / can_create / can_edit / can_delete flags.
     */
    private function copyGrants(string $fromSlug, string $toSlug): void
    {
        $from = DB::table('menu')->where('slug', $fromSlug)->first();
        $to   = DB::table('menu')->where('slug', $toSlug)->first();
        if (!$from || !$to) {
            return;
        }

        $rows = DB::table('role_menu')->where('menu_id', $from->id)->get();
        $now  = now();

        foreach ($rows as $row) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $row->role_id, 'menu_id' => $to->id],
                [
                    'can_view'   => $row->can_view,
                    'can_create' => $row->can_create,
                    'can_edit'   => $row->can_edit,
                    'can_delete' => $row->can_delete,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
};
