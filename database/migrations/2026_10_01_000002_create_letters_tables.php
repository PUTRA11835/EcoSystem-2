<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * HR & General → Letter Templates hub (docs/planning/letters-hub-plan.md):
 *
 *   letterheads              one full-page A4 background image instead of a
 *                            header and a footer image (table of
 *                            2026_09_30_000001_create_letter_templates). A strip
 *                            cannot serve as a whole page, so old images are
 *                            deleted; such a letterhead prints on plain paper
 *                            until a background is uploaded.
 *   letter_settings          one row: company name, signing city, number formats,
 *                            the IN / EN language segment of a number, "Other"
 *   letter_codes             the letter codes printed in a number (OF, FI, SM, OL…)
 *   letter_number_sequences  the running-number counter per direction and year —
 *                            a number once given out is never given out again
 *   letter_type_settings     per letter type: the language a new letter starts
 *                            in, in use, default letter code
 *   letter_request_types     the letters employees can choose in My Letter
 *                            Requests (maintained in Settings), each answered
 *                            with a template or, without one, a custom letter
 *   letters                  the register: every letter in or out is one row —
 *                            template, custom, offering and manually logged letters
 *   letter_requests          letters employees ask HR for (My Letter Requests)
 *   letter_signatories       who may sign, role first: everyone holding a role
 *                            (employee_id empty) or one person of it; starts with
 *                            everyone holding "HO HR Administrator"
 *
 * Offering letters share the outgoing counter from here on, so the counter of
 * every year starts after the offering letters already numbered in it, and
 * those letters are written into the register. Runs after
 * 2026_10_01_000001_update_recruitment_offering (offer language and signing).
 */
return new class extends Migration
{
    private const DISK = 'local';

    /** Keys of App\Support\Letters\LetterTemplates, plus the custom letter. */
    private const LETTER_TYPES = [
        'employment_certificate', 'employment_reference', 'assignment_letter',
        'goods_receipt', 'payment_receipt', 'custom_letter',
    ];

    public function up(): void
    {
        $this->useSingleBackground();

        Schema::create('letter_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 150)->default('PT Eclectic Consulting');
            $table->string('signing_city', 100)->default('Jakarta');
            // Tokens: {seq} {code} {lang} {day} {month} {roman} {year} {yy}.
            $table->string('outgoing_number_format', 100)->default('EC/{month}/{code}/{lang}/{day}{seq}/{year}');
            $table->unsignedTinyInteger('outgoing_number_digits')->default(3);
            $table->string('incoming_agenda_format', 100)->default('AGD/{year}/{seq}');
            $table->unsignedTinyInteger('incoming_agenda_digits')->default(4);
            // {lang} per language code: {"id": "IN", "en": "EN"}.
            $table->json('language_codes')->nullable();
            // My Letter Requests offers "Other", where the employee types the letter they need.
            $table->boolean('allow_other_requests')->default(true);
            $table->timestamps();
        });

        DB::table('letter_settings')->insert([
            'language_codes' => json_encode(['id' => 'IN', 'en' => 'EN']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('letter_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100)->nullable(); // what the code stands for
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // What OF / FI / SM stand for is filled in by HR in Settings.
        $sort = 0;
        foreach (['OF' => null, 'FI' => null, 'SM' => null, 'OL' => 'Offering Letter'] as $code => $name) {
            DB::table('letter_codes')->insert([
                'code' => $code, 'name' => $name, 'is_active' => true, 'sort_order' => ++$sort,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('letter_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10); // outgoing | incoming
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['direction', 'year']);
        });

        // The outgoing counter of each year starts after the offering letters already numbered in it.
        $offerYears = DB::table('recruitment_offers')
            ->selectRaw('YEAR(offer_date) as offer_year, MAX(number_sequence) as max_sequence')
            ->groupByRaw('YEAR(offer_date)')
            ->get();
        foreach ($offerYears as $row) {
            DB::table('letter_number_sequences')->insert([
                'direction' => 'outgoing', 'year' => $row->offer_year, 'last_value' => $row->max_sequence,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('letter_type_settings', function (Blueprint $table) {
            $table->id();
            // A key of App\Models\Letterhead::letterTypes().
            $table->string('letter_type', 50)->unique();
            // A key of App\Models\LetterTypeSetting::LANGUAGES.
            $table->string('language', 2)->default('id');
            $table->boolean('is_active')->default(true);
            $table->foreignId('letter_code_id')->nullable()->constrained('letter_codes')->nullOnDelete();
            $table->timestamps();
        });

        $codeOf = DB::table('letter_codes')->where('code', 'OF')->value('id');
        $codeOl = DB::table('letter_codes')->where('code', 'OL')->value('id');

        foreach (['offering_letter' => $codeOl] + array_fill_keys(self::LETTER_TYPES, $codeOf) as $type => $codeId) {
            DB::table('letter_type_settings')->insert([
                'letter_type'    => $type,
                'language'       => 'id',
                'is_active'      => true,
                'letter_code_id' => $codeId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        Schema::create('letter_request_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // The template HR answers it with; empty = a custom letter titled with the name.
            $table->string('template_key', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // A starting list; HR adds, renames and switches them off in Settings.
        $sort = 0;
        foreach ([
            ['Surat Keterangan Kerja', 'employment_certificate'],
            ['Surat Referensi Kerja / Paklaring', 'employment_reference'],
            ['Surat Keterangan Penghasilan', null],
            ['Surat Pengantar Visa / Kedutaan', null],
        ] as [$name, $template]) {
            DB::table('letter_request_types')->insert([
                'name' => $name, 'template_key' => $template, 'is_active' => true, 'sort_order' => ++$sort,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10)->index();          // outgoing | incoming
            $table->string('source', 20)->index();             // manual | template | custom | offering_letter
            $table->string('template_key', 50)->nullable();    // App\Support\Letters\LetterTemplates key
            $table->unsignedBigInteger('source_id')->nullable(); // recruitment_offers.id for an offering letter

            // Outgoing: generated or typed. Incoming: the sender's own number.
            $table->string('letter_number', 100)->nullable()->index();
            $table->unsignedInteger('number_sequence')->nullable();
            $table->unsignedSmallInteger('number_year')->nullable();
            $table->string('agenda_number', 60)->nullable()->index(); // incoming only
            $table->foreignId('letter_code_id')->nullable()->constrained('letter_codes')->nullOnDelete();

            $table->date('letter_date')->index();
            $table->date('received_date')->nullable();          // incoming only
            $table->string('counterparty', 255)->nullable();    // outgoing: recipient · incoming: sender
            $table->string('recipient_email', 150)->nullable(); // where Send emails the letter
            $table->string('subject', 255);

            $table->unsignedBigInteger('employee_id')->nullable()->index(); // the letter is about this employee
            $table->string('language', 2)->default('id');
            $table->foreignId('letterhead_id')->nullable()->constrained('letterheads')->nullOnDelete();
            $table->boolean('use_letterhead')->default(true);
            // A template's field values with the employee and company data as they were when
            // generated, or a custom letter's body — a reprint looks the same later.
            $table->json('fields')->nullable();

            // Signed with the signatory's signature from the employee master data (as offering letters).
            $table->string('signatory_name', 150)->nullable();
            $table->string('signatory_title', 150)->nullable();
            $table->unsignedBigInteger('signatory_employee_id')->nullable();
            $table->string('signature_path')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->unsignedBigInteger('signed_by_employee_id')->nullable();

            $table->string('delivered_via', 150)->nullable();   // outgoing: "Diterima oleh" / courier
            $table->string('receipt_number', 150)->nullable();  // outgoing: "Resi"
            $table->string('final_path')->nullable();           // signed / stamped scan, or the received letter
            $table->string('final_name')->nullable();
            $table->text('notes')->nullable();

            $table->string('status', 10)->default('active');    // active | void
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->dateTime('voided_at')->nullable();

            $table->dateTime('sent_at')->nullable();
            $table->string('email_status', 10)->nullable();     // null | sent | failed
            $table->text('email_error')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source', 'source_id']);
            $table->foreign('employee_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('signatory_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('signed_by_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('voided_by')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('created_by')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('updated_by')->references('employee_id')->on('employee')->nullOnDelete();
        });

        Schema::create('letter_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            // What was asked for: one of the options of Settings, or "Other" with the employee's own words.
            $table->foreignId('request_type_id')->nullable()->constrained('letter_request_types')->nullOnDelete();
            $table->string('request_type_name', 150);   // as it was when asked, or what was typed for "Other"
            $table->boolean('is_other')->default(false);
            $table->string('template_key', 50)->nullable(); // the template that answers it; empty = custom letter
            $table->string('language', 2)->default('id');
            $table->string('purpose', 255);
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 15)->default('pending')->index(); // pending | in_progress | done | rejected | cancelled
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->dateTime('handled_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignId('letter_id')->nullable()->constrained('letters')->nullOnDelete();
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('employee')->cascadeOnDelete();
            $table->foreign('handled_by')->references('employee_id')->on('employee')->nullOnDelete();
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->foreignId('letter_request_id')->nullable()->after('notes')->constrained('letter_requests')->nullOnDelete();
        });

        Schema::create('letter_signatories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');                 // employee_role.id
            $table->unsignedBigInteger('employee_id')->nullable(); // empty = everyone holding the role
            $table->string('title', 150)->nullable();              // printed under the signature instead of the master-data position
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'employee_id']);
            $table->foreign('role_id')->references('id')->on('employee_role')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employee')->cascadeOnDelete();
            $table->foreign('created_by')->references('employee_id')->on('employee')->nullOnDelete();
        });

        if ($hrAdministrator = DB::table('employee_role')->where('name', 'HO HR Administrator')->value('id')) {
            DB::table('letter_signatories')->insert([
                'role_id' => $hrAdministrator, 'employee_id' => null, 'is_active' => true, 'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Offering letters already written go into the register, read-only (Recruitment owns them).
        foreach (DB::table('recruitment_offers')->get() as $offer) {
            DB::table('letters')->insert([
                'direction'       => 'outgoing',
                'source'          => 'offering_letter',
                'source_id'       => $offer->id,
                'letter_number'   => $offer->letter_number,
                'number_sequence' => $offer->number_sequence,
                'number_year'     => (int) substr($offer->offer_date, 0, 4),
                'letter_code_id'  => $codeOl,
                'letter_date'     => $offer->offer_date,
                'counterparty'    => $offer->candidate_name,
                'recipient_email' => $offer->candidate_email,
                'subject'         => 'Offering Letter - ' . $offer->position_title,
                'language'        => $offer->language ?? 'id',
                'signatory_name'  => $offer->signatory_name,
                'signatory_title' => $offer->signatory_title,
                'signed_at'       => $offer->signed_at,
                'status'          => 'active',
                'sent_at'         => $offer->sent_at,
                'email_status'    => $offer->sent_at ? 'sent' : null,
                'created_by'      => $offer->created_by_employee_id,
                'created_at'      => $offer->created_at,
                'updated_at'      => $offer->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_signatories');
        Schema::table('letters', function (Blueprint $table) {
            $table->dropForeign(['letter_request_id']);
        });
        Schema::dropIfExists('letter_requests');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_request_types');
        Schema::dropIfExists('letter_type_settings');
        Schema::dropIfExists('letter_number_sequences');
        Schema::dropIfExists('letter_codes');
        Schema::dropIfExists('letter_settings');

        $this->restoreHeaderAndFooter();
    }

    // ── Letterhead: one background image ─────────────────────────────────────

    private function useSingleBackground(): void
    {
        if (!Schema::hasColumn('letterheads', 'header_path')) {
            return;
        }

        $old = DB::table('letterheads')->get(['header_path', 'footer_path'])
            ->flatMap(fn ($row) => [$row->header_path, $row->footer_path])
            ->filter()
            ->all();
        Storage::disk(self::DISK)->delete($old);

        Schema::table('letterheads', function (Blueprint $table) {
            $table->dropColumn(['header_path', 'footer_path']);
        });

        Schema::table('letterheads', function (Blueprint $table) {
            // A4 page image on the private `local` disk, served through the controller.
            $table->string('background_path')->nullable()->after('name');
        });
    }

    private function restoreHeaderAndFooter(): void
    {
        if (!Schema::hasColumn('letterheads', 'background_path')) {
            return;
        }

        Storage::disk(self::DISK)->delete(DB::table('letterheads')->whereNotNull('background_path')->pluck('background_path')->all());

        Schema::table('letterheads', function (Blueprint $table) {
            $table->dropColumn('background_path');
        });

        Schema::table('letterheads', function (Blueprint $table) {
            $table->string('header_path')->nullable()->after('name');
            $table->string('footer_path')->nullable()->after('header_path');
        });
    }
};
