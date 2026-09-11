<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allows a delivery_support record to have more than one Support Manager.
     * Backfills existing single-value support_manager_id assignments so no
     * data is lost when the old column is dropped in the next migration.
     */
    public function up(): void
    {
        Schema::create('delivery_support_managers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_support_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();

            $table->unique(['delivery_support_id', 'employee_id'], 'unique_support_delivery_manager');

            $table->foreign('delivery_support_id', 'fk_support_managers_delivery')
                ->references('id')
                ->on('delivery_support')
                ->onDelete('cascade');

            $table->foreign('employee_id', 'fk_support_managers_employee')
                ->references('employee_id')
                ->on('employee')
                ->onDelete('cascade');
        });

        $now = now();
        $rows = DB::table('delivery_support')
            ->whereNotNull('support_manager_id')
            ->pluck('support_manager_id', 'id');

        foreach ($rows as $deliverySupportId => $employeeId) {
            DB::table('delivery_support_managers')->insert([
                'delivery_support_id' => $deliverySupportId,
                'employee_id'         => $employeeId,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_support_managers');
    }
};
