<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `domain` column to `customer` table.
 *
 * Purpose: filter incoming emails for ticket validation by company email
 * domain. Customer contact persons may include hundreds of employees with
 * different addresses — registering each as a contact is impractical.
 * Storing the company domain (e.g., "@apta.co.id") allows the inbox
 * processor to recognize ANY sender under that domain as belonging to
 * the customer, routing the email into the staging-ticket validation flow.
 *
 * Stored normalized: lowercase, trimmed, always prefixed with "@".
 * Nullable + NOT unique by design — subsidiaries may share the parent's
 * domain (e.g., parent "AptaWorks" + subsidiary "Apta Indonesia" both
 * @apta.co.id). Match logic picks the first active customer in order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->string('domain', 255)->nullable()->after('email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropIndex(['domain']);
            $table->dropColumn('domain');
        });
    }
};
