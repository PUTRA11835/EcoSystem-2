<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_basic_data', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_basic_data', 'created_by')) {
                $table->string('created_by')->nullable()->after('deletion_flag');
            }
            if (!Schema::hasColumn('customer_basic_data', 'created_on')) {
                $table->timestamp('created_on')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('customer_basic_data', 'last_changed_by')) {
                $table->string('last_changed_by')->nullable()->after('created_on');
            }
            if (!Schema::hasColumn('customer_basic_data', 'last_changed_on')) {
                $table->timestamp('last_changed_on')->nullable()->after('last_changed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_basic_data', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'created_on', 'last_changed_by', 'last_changed_on']);
        });
    }
};
