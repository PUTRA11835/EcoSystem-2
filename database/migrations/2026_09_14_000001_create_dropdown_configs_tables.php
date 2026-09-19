<?php

use App\Enums\Division;
use App\Enums\EmployeeGroup;
use App\Enums\EmployeeSubgroup;
use App\Enums\PersonnelArea;
use App\Enums\PersonnelSubarea;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generic dropdown/lookup master data — single source of truth for every
 * admin-editable Employee Information dropdown (Position, Department,
 * Division, Personnel Area, Personnel Subarea, Employee Group, Employee
 * Subgroup). Two tables instead of one-per-field:
 *
 *   dropdown_configs        — one row per dropdown TYPE (code + label)
 *   dropdown_config_values  — the values belonging to a config
 *
 * A new dropdown type can be added later purely as data (insert a
 * `dropdown_configs` row via the Dropdown Settings admin page) — no
 * migration or deploy needed. `employee_basic_data` keeps storing the
 * plain string value as it does today; this table only governs which
 * values are valid/offered in the UI, it is not a hard FK.
 *
 * Existing values are seeded here from wherever they currently live —
 * `positions`/`departments` tables and the Division/PersonnelArea/
 * PersonnelSubarea/EmployeeGroup/EmployeeSubgroup enums — so nothing in
 * the UI changes on deploy; only where the list is edited afterwards does.
 * Those tables/enums are left in place (not dropped) since nothing else
 * references them once AppServiceProvider stops reading from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropdown_configs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dropdown_config_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dropdown_config_id')->constrained('dropdown_configs')->cascadeOnDelete();
            $table->string('value', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dropdown_config_id', 'value'], 'idx_ddcv_config_value');
        });

        $now = now();

        $configs = [
            ['code' => 'position',           'name' => 'Position',           'description' => 'Employee job position / title.'],
            ['code' => 'department',         'name' => 'Department',         'description' => 'Employee department.'],
            ['code' => 'division',           'name' => 'Division',           'description' => 'Employee division.'],
            ['code' => 'personnel_area',      'name' => 'Personnel Area',     'description' => 'Employee personnel area.'],
            ['code' => 'personnel_subarea',   'name' => 'Personnel Subarea',  'description' => 'Employee personnel subarea.'],
            ['code' => 'employee_group',      'name' => 'Employee Group',     'description' => 'Employee group classification.'],
            ['code' => 'employee_subgroup',   'name' => 'Employee Subgroup',  'description' => 'Employee subgroup classification.'],
        ];

        $configIds = [];
        foreach ($configs as $c) {
            $configIds[$c['code']] = DB::table('dropdown_configs')->insertGetId([
                'code'        => $c['code'],
                'name'        => $c['name'],
                'description' => $c['description'],
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $valueSets = [
            'position'          => Schema::hasTable('positions')
                ? DB::table('positions')->orderBy('sort_order')->orderBy('name')->pluck('name')->all()
                : [],
            'department'        => Schema::hasTable('departments')
                ? DB::table('departments')->orderBy('sort_order')->orderBy('name')->pluck('name')->all()
                : [],
            'division'          => Division::options(),
            'personnel_area'    => PersonnelArea::options(),
            'personnel_subarea' => PersonnelSubarea::options(),
            'employee_group'    => EmployeeGroup::options(),
            'employee_subgroup' => EmployeeSubgroup::options(),
        ];

        foreach ($valueSets as $code => $values) {
            $rows = [];
            foreach (array_values($values) as $i => $value) {
                $rows[] = [
                    'dropdown_config_id' => $configIds[$code],
                    'value'              => $value,
                    'sort_order'         => $i + 1,
                    'is_active'          => true,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
            if ($rows) {
                DB::table('dropdown_config_values')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dropdown_config_values');
        Schema::dropIfExists('dropdown_configs');
    }
};
