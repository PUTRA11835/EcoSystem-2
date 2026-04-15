<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_periods', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned(); // 1–12: period = day 21 of month → day 20 of next month
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable(); // employee_id
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_periods');
    }
};
