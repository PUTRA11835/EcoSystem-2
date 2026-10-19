<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access for three things that arrived together (standing rule of MenuRegistrar: a new slug starts for EC Administrator only; the
 * rest are granted explicitly below so nobody loses what they could already do):
 *
 * 1. MY APPROVALS — the extra powers an approver can be given while reviewing a document. Being NAMED on an approval workflow already
 *    lets a person open My Approvals and decide what waits on them; that authority comes from the workflow, not a menu.
 *      general.approvals          E adjust the document (still needs the module's "approvers may adjust" setting and the approver's turn)
 *                                 D delete the document
 *      general.approvals.export   V export the document
 *
 * 2. DOCUMENT IDENTITY — letterheads and signers, split out of `general.letter-templates` so Legal can manage what contracts print on and
 *    are signed with, without getting numbering codes, letter types or request types (those stay HR's).
 *      general.letters.settings.letterheads   V · E add / change / delete a letterhead
 *      general.letters.settings.signers       V · E add / change / remove a signer
 *    Granted to every position that could open Letter Templates settings (View, plus Edit if it had Edit / Create / Delete there), and to the
 *    Legal positions (HO Legal Administrator / Head: View + Edit · HO Legal User: View).
 *
 * 3. LEGAL DASHBOARD — the Legal page is now a real dashboard (counts of contracts, signing and document identity), so the Legal positions
 *    get View on it. What its cards show still follows each position's own Contract / External Contract / Document Identity access.
 */
return new class extends Migration
{
    private const APPROVAL_PAGES = [
        'general.approvals'        => 'My Approvals',
        'general.approvals.export' => 'My Approvals · Export',
    ];

    private const IDENTITY_PAGES = [
        'general.letters.settings.letterheads' => 'Document Identity · Letterheads',
        'general.letters.settings.signers'     => 'Document Identity · Signers',
    ];

    /** position name => may edit */
    private const LEGAL = ['HO Legal Administrator' => true, 'HO Legal Head' => true, 'HO Legal User' => false];

    public function up(): void
    {
        $this->approvals();
        $this->documentIdentity();
        $this->legalDashboard();

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove([...array_keys(self::APPROVAL_PAGES), ...array_keys(self::IDENTITY_PAGES)]);
        // The Legal dashboard grant is left in place: removing it could take the page away from someone an administrator later gave it to.
        $this->flushPermCache();
    }

    private function approvals(): void
    {
        if (MenuRegistrar::register('general', self::APPROVAL_PAGES, 150, 'page')) {
            MenuRegistrar::grantToAdminAndRoles(['general.approvals'], [], ['edit', 'delete']);
        }
    }

    private function documentIdentity(): void
    {
        if (!MenuRegistrar::register('general.letters', self::IDENTITY_PAGES, 10, 'page')) {
            return;
        }

        $menuIds = DB::table('menu')->whereIn('slug', array_keys(self::IDENTITY_PAGES))->pluck('id');
        $old = DB::table('menu')->where('slug', 'general.letter-templates')->value('id');

        // role_id => may edit
        $grants = [MenuRegistrar::adminRoleId() => true];
        if ($old) {
            foreach (DB::table('role_menu')->where('menu_id', $old)->where('can_view', true)->get() as $row) {
                $grants[(int) $row->role_id] = ($grants[(int) $row->role_id] ?? false) || $row->can_edit || $row->can_create || $row->can_delete;
            }
        }
        foreach (self::LEGAL as $name => $edit) {
            if ($id = DB::table('employee_role')->where('name', $name)->value('id')) {
                $grants[(int) $id] = ($grants[(int) $id] ?? false) || $edit;
            }
        }

        $now = now();
        foreach ($menuIds as $menuId) {
            foreach ($grants as $roleId => $edit) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => (bool) $edit, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    private function legalDashboard(): void
    {
        $menu = DB::table('menu')->where('slug', 'legal')->value('id');
        if (!$menu) {
            return;
        }

        $now = now();
        foreach (DB::table('employee_role')->whereIn('name', array_keys(self::LEGAL))->pluck('id') as $roleId) {
            if (!DB::table('role_menu')->where('role_id', $roleId)->where('menu_id', $menu)->exists()) {
                DB::table('role_menu')->insert([
                    'role_id' => $roleId, 'menu_id' => $menu, 'can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
