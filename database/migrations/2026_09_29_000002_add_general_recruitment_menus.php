<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Menu Access slugs of HR & General → Recruitment, and their starting grants.
 *
 * One slug per page. What can be DONE on a page (create / edit / delete) has
 * no slug of its own — it is the C / E / D box of that page's slug in
 * Management → Roles, checked by the `menu.can:{slug},{action}` middleware.
 * What each box means per page is documented next to the routes
 * (routes/hr-general.php).
 *
 * The grants below are only the starting state; from here on access is
 * maintained in Management → Roles, and no code checks a role by name.
 *
 *   Dashboard, Selection Process, Schedule, Job Openings
 *       -> HR Administrator, HR Head, HR User: view, create, edit, delete
 *   Settings
 *       -> HR Administrator, HR Head: view, create, edit, delete
 *   Offering Letter — Letters, Settings (its own sidebar entry with two tabs,
 *   not tabs of the Recruitment hub)
 *       -> EC Administrator only
 *
 * EC Administrator always receives everything.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    private const CRUD = ['create', 'edit', 'delete'];

    /** slug => name in Management → Roles, in tab order. */
    private const PAGES = [
        'general.recruitment'            => 'Recruitment — Dashboard',
        'general.recruitment.candidates' => 'Recruitment — Selection Process',
        'general.recruitment.schedule'   => 'Recruitment — Schedule',
        'general.recruitment.jobs'       => 'Recruitment — Job Openings',
        'general.recruitment.settings'   => 'Recruitment — Settings',
        'general.recruitment.offers'     => 'Offering Letter — Letters',
        'general.recruitment.offers.settings' => 'Offering Letter — Settings',
    ];

    private const HR_TABS = [
        'general.recruitment',
        'general.recruitment.candidates',
        'general.recruitment.schedule',
        'general.recruitment.jobs',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES, 70, 'page');

        MenuRegistrar::grantToAdminAndRoles(self::HR_TABS, ['HO HR Administrator', 'HO HR Head', 'HO HR User'], self::CRUD);
        MenuRegistrar::grantToAdminAndRoles(['general.recruitment.settings'], ['HO HR Administrator', 'HO HR Head'], self::CRUD);
        MenuRegistrar::grantToAdminAndRoles(['general.recruitment.offers', 'general.recruitment.offers.settings'], [], self::CRUD);
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
