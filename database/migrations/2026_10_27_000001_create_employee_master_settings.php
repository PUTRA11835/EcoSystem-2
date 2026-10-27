<?php

use App\Support\Management\EmployeeMasterSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Management → Employee as settings: the dropdown lists of the employee forms and the rules their data is saved under, kept as data
 * so HR can change them. The lists are seeded with exactly what the forms offered before, so nothing changes until someone edits one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_field_options', function (Blueprint $table) {
            $table->id();
            $table->string('section', 30);
            $table->string('field', 40);
            $table->string('value', 150);                       // what is saved on the employee's record — fixed once created
            $table->string('label', 150);                       // what the dropdown shows — can be renamed
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);        // off = not offered on the form; records that hold it keep it
            $table->boolean('internal_only')->default(false);   // not offered for External consultants
            $table->timestamps();

            $table->unique(['section', 'field', 'value']);
            $table->index(['section', 'field', 'sort_order']);
        });

        Schema::create('employee_section_settings', function (Blueprint $table) {
            $table->id();
            $table->string('section', 30);
            $table->string('key', 60);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['section', 'key']);
        });

        $now = now();
        $internalOnly = ['BPJS_KESEHATAN', 'BPJS_KETENAGAKERJAAN'];

        foreach (array_keys(EmployeeMasterSettings::SLUGS) as $section) {
            foreach (EmployeeMasterSettings::get($section)['lists'] as $field => $list) {
                $order = 0;
                foreach ($list['defaults'] as $value => $label) {
                    DB::table('employee_field_options')->insert([
                        'section' => $section, 'field' => $field, 'value' => (string) $value, 'label' => $label, 'sort_order' => ++$order,
                        'is_active' => true, 'internal_only' => $section === 'identification' && in_array($value, $internalOnly, true),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                // Lists the API already refused anything else for stay restricted.
                if ($list['restrict']) {
                    DB::table('employee_section_settings')->insert(['section' => $section, 'key' => "restrict.{$field}", 'value' => '1', 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_section_settings');
        Schema::dropIfExists('employee_field_options');
    }
};
