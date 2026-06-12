<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->decimal('progress_percentage', 5, 2)->default(0)->after('notes');
            $table->text('progress_note')->nullable()->after('progress_percentage');
            $table->timestamp('progress_updated_at')->nullable()->after('progress_note');
        });
    }

    public function down(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->dropColumn(['progress_percentage', 'progress_note', 'progress_updated_at']);
        });
    }
};
