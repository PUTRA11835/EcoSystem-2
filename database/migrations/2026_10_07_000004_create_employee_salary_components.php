<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary components of an employee: the primary source for payroll, BPJS and contract references.
 *
 * A row is one active compensation line (base salary, an allowance, ...) taken from the Compensation
 * Components of Offering Letter → Settings. When an offering letter is accepted its lines are copied here,
 * starting on the join date; after that HR adjusts them in Master Employee → Contract → Salary Components.
 * The name and kind are copied onto the row, so it stays readable if the component is renamed later.
 *
 * Permission: employee.section.salary.view|update (sensitive). Only employee.section.* — an employee has no
 * my-profile.section.salary, so the box never shows on My Profile. New slugs start admin-only (MenuRegistrar).
 */
return new class extends Migration
{
    private const SLUGS = [
        'employee.section.salary',
        'employee.section.salary.view',
        'employee.section.salary.update',
    ];

    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('component_id')->nullable();
            $table->string('name', 100);
            $table->string('kind', 10);
            $table->decimal('amount', 15, 2);
            $table->date('effective_from');
            $table->string('source', 10)->default('manual');   // offer | manual
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'kind']);
            $table->foreign('employee_id')->references('employee_id')->on('employee')->cascadeOnDelete();
            $table->foreign('component_id')->references('id')->on('recruitment_offer_components')->nullOnDelete();
            $table->foreign('offer_id')->references('id')->on('recruitment_offers')->nullOnDelete();
        });

        MenuRegistrar::register('master.employee', ['employee.section.salary' => 'Salary Components'], 23, 'group');
        MenuRegistrar::register('employee.section.salary', [
            'employee.section.salary.view'   => 'View Salary Components',
            'employee.section.salary.update' => 'Update Salary Components',
        ], 1);
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_reverse(self::SLUGS));
        Schema::dropIfExists('employee_salary_components');
    }
};
