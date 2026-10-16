<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract Settings tab — full management of the dropdown options.
 *
 *   contract_options.base_type   the built-in type a custom contract type behaves like (numbering, date rules, templates follow
 *                                it). Built-in rows carry their own code. A custom type is an alias: "Probation" based on PKWT.
 *   contract_options.is_default  the option the contract form preselects (one per menu and kind)
 *   contract_options.description a hint for the people who pick it
 *   employee_contract.contract_kind  the custom type chosen on a contract (null = the built-in one in contract_type)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_options', function (Blueprint $table) {
            $table->string('base_type', 30)->nullable()->after('code');
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->string('description', 255)->nullable()->after('label');
        });

        DB::table('contract_options')->where('kind', 'contract_type')->update(['base_type' => DB::raw('code')]);
        DB::table('contract_options')->where('scope', 'internal')->where('kind', 'contract_type')->where('code', 'PKWT')->update(['is_default' => true]);
        DB::table('contract_options')->where('scope', 'external')->where('kind', 'contract_type')->where('code', 'mandays')->update(['is_default' => true]);

        Schema::table('employee_contract', function (Blueprint $table) {
            $table->string('contract_kind', 60)->nullable()->after('contract_type');
            $table->index('contract_kind', 'employee_contract_kind_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contract', function (Blueprint $table) {
            $table->dropIndex('employee_contract_kind_index');
            $table->dropColumn('contract_kind');
        });
        Schema::table('contract_options', function (Blueprint $table) {
            $table->dropColumn(['base_type', 'is_default', 'description']);
        });
    }
};
