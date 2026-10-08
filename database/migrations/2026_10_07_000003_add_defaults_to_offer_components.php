<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A default for every compensation component, so HR does not retype the same figure on each letter
 * (base salary at the regional minimum wage, a BPJS allowance as a share of the base salary, ...).
 *
 *   default_type   amount  a fixed rupiah amount
 *                  percent a percentage of the letter's base salary
 *   default_value  the rupiah amount or the percentage; empty = no default
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_offer_components', function (Blueprint $table) {
            $table->string('default_type', 10)->default('amount')->after('kind');
            $table->decimal('default_value', 15, 2)->nullable()->after('default_type');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_offer_components', function (Blueprint $table) {
            $table->dropColumn(['default_type', 'default_value']);
        });
    }
};
