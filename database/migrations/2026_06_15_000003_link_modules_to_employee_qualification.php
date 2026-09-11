<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add module_id FK to employee_qualification
        Schema::table('employee_qualification', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('employee_id');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('set null');
        });

        // 2. Migrate free text module → modules master + update module_id
        $rows = DB::table('employee_qualification')
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->select('qualification_id', 'module')
            ->get();

        foreach ($rows as $row) {
            $name = trim($row->module);
            if (empty($name)) continue;

            $moduleId = DB::table('modules')->where('name', $name)->value('id');
            if (!$moduleId) {
                $moduleId = DB::table('modules')->insertGetId([
                    'name'       => $name,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('employee_qualification')
                ->where('qualification_id', $row->qualification_id)
                ->update(['module_id' => $moduleId]);
        }

        // 3. Drop old free text column
        Schema::table('employee_qualification', function (Blueprint $table) {
            $table->dropColumn('module');
        });

        // 4. Drop employee_modules (redundant — modules now via employee_qualification)
        Schema::table('employee_modules', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['module_id']);
        });
        Schema::dropIfExists('employee_modules');
    }

    public function down(): void
    {
        // Recreate employee_modules
        Schema::create('employee_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();
            $table->unique(['employee_id', 'module_id']);
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        });

        // Restore module varchar column
        Schema::table('employee_qualification', function (Blueprint $table) {
            $table->string('module')->nullable()->after('employee_id');
        });

        // Restore free text from module_id
        $rows = DB::table('employee_qualification')
            ->whereNotNull('module_id')
            ->join('modules', 'modules.id', '=', 'employee_qualification.module_id')
            ->select('employee_qualification.qualification_id', 'modules.name')
            ->get();

        foreach ($rows as $row) {
            DB::table('employee_qualification')
                ->where('qualification_id', $row->qualification_id)
                ->update(['module' => $row->name]);
        }

        Schema::table('employee_qualification', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
            $table->dropColumn('module_id');
        });
    }
};
