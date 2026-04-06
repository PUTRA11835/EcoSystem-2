<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make ticket_number nullable so that tickets created by Jarvies (customer side)
     * can exist without a number until EcoSystem assigns one via the staging approval flow.
     *
     * MySQL UNIQUE index allows multiple NULL values, so the uniqueness constraint
     * on non-null ticket numbers is preserved.
     */
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->string('ticket_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->string('ticket_number')->nullable(false)->change();
        });
    }
};
