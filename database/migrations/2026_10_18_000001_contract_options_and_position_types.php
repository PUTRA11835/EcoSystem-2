<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract — internal / external split, position types, configurable dropdowns and an editable generated document.
 *
 *   contract_options            dropdown data of the contract forms, per menu (scope internal | external):
 *                                 kind contract_type  engine types (PKWT, PKWTT, INTERNSHIP, ADDENDUM / the external schemes):
 *                                                     seeded, HR renames them and switches them on / off in the Settings tab
 *                                 kind position_type  free list HR keeps in the Settings tab (Sales, Internship, Management Trainee …)
 *   contract_templates.position_type   a template can follow a position type as well as a contract type (empty = every position type)
 *   employee_contract.position_type    the position type chosen on the contract
 *   employee_contract.body_customized  the text of body_html was generated and then edited by hand (Legal / HR / RPMO):
 *                                      it is kept as it is instead of following the template
 *
 * Two default templates are added for the new types (copies of the PKWT default; the text is for HR to edit).
 * Everything is additive: no existing row changes meaning.
 */
return new class extends Migration
{
    private const OPTIONS = [
        ['internal', 'contract_type', 'PKWT',       'Fixed-term (PKWT)',        10],
        ['internal', 'contract_type', 'PKWTT',      'Indefinite-term (PKWTT)',  20],
        ['internal', 'contract_type', 'INTERNSHIP', 'Internship',               30],
        ['internal', 'contract_type', 'ADDENDUM',   'Addendum',                 40],
        ['internal', 'position_type', 'STAFF',      'Staff',                    10],
        ['internal', 'position_type', 'SALES',      'Sales',                    20],
        ['internal', 'position_type', 'INTERNSHIP', 'Internship',               30],
        ['external', 'contract_type', 'mandays',     'Mandays',                 10],
        ['external', 'contract_type', 'monthly',     'Monthly retainer',        20],
        ['external', 'contract_type', 'fixed_price', 'Fixed price',             30],
        ['external', 'contract_type', 'hourly',      'Hourly',                  40],
        ['external', 'position_type', 'CONSULTANT',  'Consultant',              10],
        ['external', 'position_type', 'MANAGEMENT_TRAINEE', 'Management Trainee', 20],
    ];

    private const PAGES = [
        'general.contracts.settings'          => ['general.contracts', 'Settings', 3],
        'general.external.contracts.settings' => ['general.external', 'External Contract Settings', 7],
    ];

    public function up(): void
    {
        Schema::create('contract_options', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 10);
            $table->string('kind', 20);
            $table->string('code', 60);
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['scope', 'kind', 'code'], 'contract_options_unique');
        });

        $now = now();
        foreach (self::OPTIONS as [$scope, $kind, $code, $label, $order]) {
            DB::table('contract_options')->insert([
                'scope' => $scope, 'kind' => $kind, 'code' => $code, 'label' => $label, 'sort_order' => $order,
                'is_active' => true, 'is_system' => $kind === 'contract_type', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->string('position_type', 100)->nullable()->after('position');
        });
        Schema::table('employee_contract', function (Blueprint $table) {
            $table->string('position_type', 100)->nullable()->after('position');
            $table->boolean('body_customized')->default(false)->after('body_html');
        });

        // Default templates of the two new types, copied from the PKWT default so they work from the first day.
        $pkwt = DB::table('contract_templates')->where('contract_type', 'PKWT')->where('is_system_default', 1)->first();
        if ($pkwt) {
            foreach ([
                ['INTERNSHIP', 'Kontrak Default Magang', 'Perjanjian magang (internship). Disalin dari kontrak PKWT; sesuaikan pasalnya.'],
                ['ADDENDUM', 'Addendum Default', 'Addendum perjanjian kerja. Disalin dari kontrak PKWT; sesuaikan pasalnya.'],
            ] as [$type, $name, $description]) {
                if (DB::table('contract_templates')->where('contract_type', $type)->where('is_system_default', 1)->exists()) {
                    continue;
                }
                DB::table('contract_templates')->insert([
                    'name' => $name, 'contract_type' => $type, 'description' => $description, 'body_html' => $pkwt->body_html,
                    'use_letterhead' => $pkwt->use_letterhead, 'page_size' => $pkwt->page_size ?? null, 'page_orientation' => $pkwt->page_orientation ?? null,
                    'margin_top' => $pkwt->margin_top ?? null, 'margin_right' => $pkwt->margin_right ?? null,
                    'margin_bottom' => $pkwt->margin_bottom ?? null, 'margin_left' => $pkwt->margin_left ?? null,
                    'is_system_default' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (self::PAGES as $slug => [$parent, $name, $seq]) {
            MenuRegistrar::register($parent, [$slug => $name], $seq, 'page');
        }
        MenuRegistrar::grantToAdminAndRoles(array_keys(self::PAGES), [], ['create', 'edit', 'delete']);

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::PAGES));

        DB::table('contract_templates')->whereIn('contract_type', ['INTERNSHIP', 'ADDENDUM'])->where('is_system_default', 1)->delete();
        Schema::table('employee_contract', function (Blueprint $table) {
            $table->dropColumn(['position_type', 'body_customized']);
        });
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn('position_type');
        });
        Schema::dropIfExists('contract_options');

        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
