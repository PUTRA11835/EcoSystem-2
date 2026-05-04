<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->decimal('progress_percentage', 5, 2)->default(0)->after('man_days');
            $table->text('progress_note')->nullable()->after('progress_percentage');
            $table->timestamp('last_progress_at')->nullable()->after('progress_note');
            $table->unsignedBigInteger('progress_updated_by')->nullable()->after('last_progress_at');

            $table->foreign('progress_updated_by')
                  ->references('employee_id')
                  ->on('employee')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropForeign(['progress_updated_by']);
            $table->dropColumn([
                'progress_percentage',
                'progress_note',
                'last_progress_at',
                'progress_updated_by',
            ]);
        });
    }
};
