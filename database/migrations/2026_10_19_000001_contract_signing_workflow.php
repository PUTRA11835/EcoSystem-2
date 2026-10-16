<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract signing workflow (until the e-stamp is integrated):
 *
 *   Legal / RPMO prepares the contract → downloads it and applies the e-stamp online → uploads the stamped PDF →
 *   sends it to the employee (My Profile → Contract) → the employee reviews and signs every page → it returns to Legal →
 *   Legal forwards it to the approver of that template → the approver reviews and signs every page → back to Legal →
 *   Legal releases it → the employee can download the approved contract.
 *
 *   employee_contract.sign_stage      null | with_employee | with_legal | with_approver | approved | released | revision
 *   employee_contract.signed_pdf_path the stamped PDF with the signatures drawn on every page (when the PDF could be read)
 *   contract_templates.approver_employee_id   the approver of contracts made from the template (Legal may pick another)
 *   contract_sign_events              the audit trail: who did what, when, with which file (sha-256)
 *
 * Slug general.contracts.signing (function) = Legal's right to send, forward, release and withdraw. The employee and the approver
 * need no slug: what they may open is decided by the contract itself (its employee / its approver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contract', function (Blueprint $table) {
            $table->string('sign_stage', 20)->nullable()->after('stamp_method');
            $table->date('sign_deadline')->nullable()->after('sign_stage');
            $table->dateTime('sign_sent_at')->nullable()->after('sign_deadline');
            $table->unsignedBigInteger('sign_sent_by')->nullable()->after('sign_sent_at');
            $table->char('sign_file_hash', 64)->nullable()->after('sign_sent_by');
            $table->dateTime('employee_signed_at')->nullable()->after('sign_file_hash');
            $table->unsignedBigInteger('approver_employee_id')->nullable()->after('employee_signed_at');
            $table->string('approver_name', 150)->nullable()->after('approver_employee_id');
            $table->string('approver_title', 150)->nullable()->after('approver_name');
            $table->dateTime('approver_signed_at')->nullable()->after('approver_title');
            $table->dateTime('released_at')->nullable()->after('approver_signed_at');
            $table->unsignedBigInteger('released_by')->nullable()->after('released_at');
            $table->string('signed_pdf_path', 255)->nullable()->after('released_by');

            $table->index('sign_stage', 'employee_contract_sign_stage_index');
            $table->index('approver_employee_id', 'employee_contract_approver_index');
        });

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('approver_employee_id')->nullable()->after('signatory_employee_id');
        });

        Schema::create('contract_sign_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('event', 30);
            $table->unsignedBigInteger('actor_employee_id')->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('note', 1000)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['contract_id', 'id'], 'contract_sign_events_contract_index');
        });

        MenuRegistrar::register('general.contracts', ['general.contracts.signing' => 'Contract Signing'], 11, 'function');
        MenuRegistrar::grantToAdminAndRoles(['general.contracts.signing'], [], []);

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['general.contracts.signing']);

        Schema::dropIfExists('contract_sign_events');
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn('approver_employee_id');
        });
        Schema::table('employee_contract', function (Blueprint $table) {
            $table->dropIndex('employee_contract_sign_stage_index');
            $table->dropIndex('employee_contract_approver_index');
            $table->dropColumn([
                'sign_stage', 'sign_deadline', 'sign_sent_at', 'sign_sent_by', 'sign_file_hash', 'employee_signed_at',
                'approver_employee_id', 'approver_name', 'approver_title', 'approver_signed_at', 'released_at', 'released_by', 'signed_pdf_path',
            ]);
        });

        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
