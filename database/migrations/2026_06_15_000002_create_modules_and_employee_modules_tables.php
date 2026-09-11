<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();

            $table->unique(['employee_id', 'module_id']);
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        });

        // Migrate existing comma-separated modules string from employees table
        $employees = DB::table('employee')->whereNotNull('modules')->where('modules', '!=', '')->get(['employee_id', 'modules']);

        foreach ($employees as $emp) {
            $moduleNames = array_filter(array_map('trim', explode(',', $emp->modules)));
            foreach ($moduleNames as $name) {
                if (empty($name)) continue;
                $moduleId = DB::table('modules')->where('name', strtoupper($name))->value('id');
                if (!$moduleId) {
                    $moduleId = DB::table('modules')->insertGetId([
                        'name'       => strtoupper($name),
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('employee_modules')->insertOrIgnore([
                    'employee_id' => $emp->employee_id,
                    'module_id'   => $moduleId,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        Schema::table('employee', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }

    public function down(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->string('modules', 255)->nullable()->after('eci');
        });

        Schema::dropIfExists('employee_modules');
        Schema::dropIfExists('modules');
    }
};
