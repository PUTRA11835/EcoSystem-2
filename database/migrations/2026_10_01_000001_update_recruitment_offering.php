<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment & Offering Letter, second round (the tables of
 * 2026_09_29_000001_create_recruitment_tables):
 *
 * 1. Offering Settings — the one-line "Legal Basis" becomes the formatted note
 *    under the base salary percentage of the letter form (it starts from the
 *    legal basis that was set), and "Default Benefits" goes: benefits are
 *    typed per letter.
 *
 * 2. Recruitment document types — which documents are asked for is decided per
 *    job opening only, so the "new openings ask for it as" default of a
 *    document type goes. What existing job openings ask for is untouched.
 *
 * 3. Letters in English or Indonesian —
 *      recruitment_offers.language            the language a letter is written
 *                                             and printed in (existing: Indonesian)
 *      recruitment_offer_components.name_en   the English name a compensation
 *                                             line prints under; empty = its own
 *
 * 4. Signing — after a letter is generated, HR signs it with the signature of
 *    its signatory from the employee master data (employee_hr_profile). The
 *    image is COPIED onto the letter, so it stays as signed whatever later
 *    happens to the master data; only a signed letter is emailed, and changing
 *    a signed letter removes the signature.
 *      signatory_employee_id · signature_path · signed_at · signed_by_employee_id
 *
 * 5. Letter number — the language segment becomes the {lang} token (IN / EN),
 *    on one running number for both languages.
 */
return new class extends Migration
{
    private const RATIO_NOTE = 'Legal basis: base salary of at least <b>{min_percent}%</b> of base salary + fixed allowances.<br>'
        . 'Fixed allowances counted: {fixed_allowances}. Variable components ({variable_components}) are not part of this ratio.<br>'
        . '<i>{legal_basis}</i>';

    /** English names of the components seeded with the module, set where HR has not renamed them. */
    private const ENGLISH_NAMES = [
        'Gaji Pokok'                  => 'Basic Salary',
        'Uang Makan'                  => 'Meal Allowance',
        'Akomodasi'                   => 'Accommodation Allowance',
        'Tunjangan Transport'         => 'Transportation Allowance',
        'Tunjangan Jabatan'           => 'Position Allowance',
        'Tunjangan Profesi'           => 'Professional Allowance',
        'Tunjangan Timesheet Project' => 'Project Timesheet Allowance',
        'Tunjangan Project'           => 'Project Allowance',
        'Tunjangan Kesehatan'         => 'Health Allowance',
        'Tunjangan Lain-lain'         => 'Other Allowances',
    ];

    public function up(): void
    {
        $this->replaceOfferSettings();
        $this->dropDocumentTypeDefaults();

        // 3. Languages
        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->string('language', 2)->default('id')->after('letter_number');
        });
        Schema::table('recruitment_offer_components', function (Blueprint $table) {
            $table->string('name_en', 100)->nullable()->after('name');
        });
        foreach (self::ENGLISH_NAMES as $name => $english) {
            DB::table('recruitment_offer_components')->where('name', $name)->whereNull('name_en')->update(['name_en' => $english]);
        }

        // 4. Signing
        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('signatory_employee_id')->nullable()->after('signatory_title');
            $table->string('signature_path')->nullable()->after('signatory_employee_id');
            $table->dateTime('signed_at')->nullable()->after('signature_path');
            $table->unsignedBigInteger('signed_by_employee_id')->nullable()->after('signed_at');

            $table->foreign('signatory_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('signed_by_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
        });

        // 5. {lang}
        DB::table('recruitment_settings')->where('offer_number_format', 'like', '%/IN/%')
            ->update(['offer_number_format' => DB::raw("REPLACE(offer_number_format, '/IN/', '/{lang}/')")]);
    }

    public function down(): void
    {
        DB::table('recruitment_settings')->where('offer_number_format', 'like', '%{lang}%')
            ->update(['offer_number_format' => DB::raw("REPLACE(offer_number_format, '{lang}', 'IN')")]);

        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->dropForeign(['signatory_employee_id']);
            $table->dropForeign(['signed_by_employee_id']);
            $table->dropColumn(['signatory_employee_id', 'signature_path', 'signed_at', 'signed_by_employee_id', 'language']);
        });
        Schema::table('recruitment_offer_components', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        $this->restoreDocumentTypeDefaults();
        $this->restoreOfferSettings();
    }

    // ── 1. Offering Settings ─────────────────────────────────────────────────

    private function replaceOfferSettings(): void
    {
        if (!Schema::hasColumn('recruitment_settings', 'offer_legal_basis')) {
            return;
        }

        if (!Schema::hasColumn('recruitment_settings', 'offer_ratio_note')) {
            Schema::table('recruitment_settings', function (Blueprint $table) {
                // HTML with <b> <i> <u> and line breaks only (RecruitmentSetting::cleanNote).
                $table->text('offer_ratio_note')->nullable()->after('offer_base_salary_min_percent');
            });
        }

        foreach (DB::table('recruitment_settings')->get(['id', 'offer_legal_basis']) as $row) {
            DB::table('recruitment_settings')->where('id', $row->id)->update([
                'offer_ratio_note' => strtr(self::RATIO_NOTE, ['{legal_basis}' => e($row->offer_legal_basis)]),
            ]);
        }

        Schema::table('recruitment_settings', function (Blueprint $table) {
            $table->dropColumn(['offer_legal_basis', 'offer_default_benefits']);
        });
    }

    private function restoreOfferSettings(): void
    {
        if (!Schema::hasColumn('recruitment_settings', 'offer_ratio_note')) {
            return;
        }

        Schema::table('recruitment_settings', function (Blueprint $table) {
            $table->string('offer_legal_basis')->default('PP No. 36 Tahun 2021 tentang Pengupahan, Pasal 41')->after('offer_base_salary_min_percent');
            $table->string('offer_default_benefits', 500)->default('BPJS Kesehatan & BPJS Ketenagakerjaan')->after('offer_legal_basis');
        });

        Schema::table('recruitment_settings', function (Blueprint $table) {
            $table->dropColumn('offer_ratio_note');
        });
    }

    // ── 2. Document types ────────────────────────────────────────────────────

    private function dropDocumentTypeDefaults(): void
    {
        if (Schema::hasColumn('recruitment_options', 'requirement')) {
            Schema::table('recruitment_options', function (Blueprint $table) {
                $table->dropColumn('requirement');
            });
        }
    }

    private function restoreDocumentTypeDefaults(): void
    {
        if (!Schema::hasColumn('recruitment_options', 'requirement')) {
            Schema::table('recruitment_options', function (Blueprint $table) {
                $table->string('requirement', 10)->nullable()->after('is_active');
            });
        }
    }
};
