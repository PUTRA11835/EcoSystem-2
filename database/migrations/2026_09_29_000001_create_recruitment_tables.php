<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every table of HR & General → Recruitment.
 *
 *   recruitment_options                     dropdown lists HR maintains in Settings
 *   recruitment_job_openings                one posting of a vacancy on one platform
 *   recruitment_job_opening_documents       the documents an opening asks applicants for
 *   recruitment_candidates                  a person in the selection process
 *   recruitment_candidate_documents         their files and links
 *   recruitment_candidate_status_histories  every status change (the timeline)
 *   recruitment_interviews                  scheduled interviews — the calendar's only source
 *   recruitment_interview_interviewers      who conducts each interview
 *   recruitment_offer_components            compensation lines an offering letter can carry
 *   recruitment_offers                      offering letters and their outcome
 *   recruitment_settings                    one row of module settings
 */
return new class extends Migration
{
    /** type => [name => document rules], in display order. */
    private const OPTIONS = [
        'platform' => [
            'LinkedIn' => [], 'Jobstreet' => [], 'Glints' => [], 'Kalibrr' => [],
            'Indeed' => [], 'Career Website' => [], 'Referral' => [], 'Walk-in' => [],
        ],
        'employment_type' => [
            'Full-time' => [], 'Part-time' => [], 'Internship' => [], 'PKWT' => [], 'PKWTT' => [],
        ],
        'document_type' => [
            'CV'             => ['requirement' => 'required', 'submission' => 'file', 'file_formats' => ['pdf']],
            'Portfolio'      => ['requirement' => 'optional', 'submission' => 'both'],
            'Other Document' => ['requirement' => 'none', 'submission' => 'file'],
        ],
    ];

    /**
     * name => kind, in display order. The names are Indonesian because they
     * are printed on the letter as they are.
     */
    private const OFFER_COMPONENTS = [
        'Gaji Pokok'                 => 'base',
        'Uang Makan'                 => 'fixed',
        'Akomodasi'                  => 'fixed',
        'Tunjangan Transport'        => 'fixed',
        'Tunjangan Jabatan'          => 'fixed',
        'Tunjangan Profesi'          => 'fixed',
        'Tunjangan Timesheet Project' => 'variable',
        'Tunjangan Project'          => 'variable',
        'Tunjangan Kesehatan'        => 'fixed',
        'Tunjangan Lain-lain'        => 'variable',
    ];

    public function up(): void
    {
        // One table with a `type` discriminator rather than three identical
        // ones — the lists share the same shape and the same Settings screen.
        Schema::create('recruitment_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // platform | employment_type | document_type
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Document types only — the rules HR sets per document:
            $table->string('requirement', 10)->nullable(); // how a NEW job opening asks for it: required | optional | none
            $table->string('submission', 10)->nullable();  // how it is handed in: file | link | both
            $table->json('file_formats')->nullable();      // accepted file formats; null = any

            $table->timestamps();

            $table->unique(['type', 'name']);
        });

        $this->seedOptions();

        Schema::create('recruitment_job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->unique();
            $table->string('position_title', 150);
            $table->foreignId('platform_id')->nullable()->constrained('recruitment_options')->nullOnDelete();
            $table->string('posting_url', 500)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('recruitment_options')->nullOnDelete();
            $table->string('location', 150)->nullable();

            // Whether a posting is open is never stored — it is derived from
            // these two at read time (JobOpening::status()), so it cannot go stale.
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();

            $table->unsignedSmallInteger('quota')->default(1);
            $table->decimal('salary_min', 15, 2)->nullable();
            $table->decimal('salary_max', 15, 2)->nullable();
            $table->unsignedBigInteger('recruiter_employee_id')->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();

            // Stored now; takes effect once the career website is connected.
            $table->boolean('publish_to_website')->default(false);
            $table->dateTime('website_published_at')->nullable();

            $table->unsignedBigInteger('created_by_employee_id')->nullable();
            $table->timestamps();

            $table->foreign('recruiter_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('created_by_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->index(['opens_at', 'closes_at']);
        });

        Schema::create('recruitment_job_opening_documents', function (Blueprint $table) {
            $table->foreignId('job_opening_id')->constrained('recruitment_job_openings')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('recruitment_options')->cascadeOnDelete();
            $table->boolean('is_required')->default(true); // false = asked for, but optional

            $table->primary(['job_opening_id', 'document_type_id']);
        });

        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            // A candidate is tied to a POSITION; the job opening and the source
            // are optional (a referral can arrive without any posting).
            $table->foreignId('job_opening_id')->nullable()->constrained('recruitment_job_openings')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('recruitment_options')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();

            // interview_hr -> interview_user -> tes_teknis -> offering, with
            // ditolak (rejected) as the exit; diterima (hired) is set only by
            // accepting an offer.
            $table->string('status', 20)->default('interview_hr')->index();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('hired_employee_id')->nullable();
            $table->timestamps();

            $table->foreign('hired_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
        });

        Schema::create('recruitment_candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->cascadeOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained('recruitment_options')->nullOnDelete();
            $table->string('original_name');

            // An uploaded file (`disk` + `path`) or a link (`url`), whichever
            // the document type allows.
            $table->string('disk', 20)->default('local');
            $table->string('path')->nullable();
            $table->string('url', 500)->nullable();

            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('uploaded_by_employee_id')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
        });

        Schema::create('recruitment_candidate_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidate_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->unsignedBigInteger('changed_by_employee_id')->nullable();
            $table->dateTime('changed_at');

            // Explicit names: the generated ones exceed MySQL's 64-character limit.
            $table->foreign('candidate_id', 'rc_status_hist_candidate_fk')
                ->references('id')->on('recruitment_candidates')->cascadeOnDelete();
            $table->foreign('changed_by_employee_id', 'rc_status_hist_changed_by_fk')
                ->references('employee_id')->on('employee')->nullOnDelete();
            $table->index(['candidate_id', 'changed_at'], 'rc_status_hist_candidate_changed_idx');
        });

        Schema::create('recruitment_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->cascadeOnDelete();
            $table->string('title', 200)->nullable(); // empty = "{stage} - {candidate}"

            // Same key as the candidate status the stage corresponds to:
            // interview_hr | interview_user | tes_teknis.
            $table->string('stage', 30);

            $table->dateTime('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(60); // derived from the start and end time entered
            $table->string('mode', 10)->default('online'); // online | onsite
            $table->string('location')->nullable();

            // A link HR supplies for an online interview (Google Meet, Zoom, ...).
            // Empty = the Teams link generated by the calendar sync (teams_meeting_url).
            $table->string('meeting_url', 500)->nullable();

            // scheduled | rescheduled | cancelled. "Completed" is not stored: an
            // interview that was not cancelled is over once its end time has passed.
            $table->string('status', 15)->default('scheduled');

            // Filled when the interview is mirrored to an outside calendar.
            // Nullable on purpose: a failed sync must never fail the save.
            $table->string('ms_graph_event_id')->nullable();
            $table->string('teams_meeting_url', 500)->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_employee_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
        });

        Schema::create('recruitment_interview_interviewers', function (Blueprint $table) {
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');

            $table->primary(['interview_id', 'employee_id']);
            $table->foreign('employee_id')->references('employee_id')->on('employee')->cascadeOnDelete();
        });

        // The compensation lines an offering letter can carry, maintained in
        // Offering Letter → Settings.
        Schema::create('recruitment_offer_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();

            // base     = the base salary (exactly one row, cannot be removed)
            // fixed    = a fixed allowance — counts in the legal base-salary ratio
            // variable = paid conditionally — left out of that ratio
            $table->string('kind', 10)->default('fixed');

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seedOfferComponents();

        Schema::create('recruitment_offers', function (Blueprint $table) {
            $table->id();
            $table->string('letter_number', 60)->unique();
            // Running number within the year of `offer_date`; the `{seq}` of the number format.
            $table->unsignedInteger('number_sequence');
            $table->date('offer_date');

            // Optional: a letter can also be written for someone typed in by
            // hand. Name, contact and position are kept on the letter itself,
            // so it stays as issued whatever happens to the candidate record.
            $table->foreignId('candidate_id')->nullable()->constrained('recruitment_candidates')->nullOnDelete();
            $table->string('candidate_name', 150);
            $table->string('candidate_email', 150)->nullable();
            $table->string('candidate_phone', 30)->nullable();
            $table->string('position_title', 150);

            $table->text('job_description')->nullable();
            $table->text('benefits')->nullable();
            $table->date('joining_date')->nullable();
            $table->boolean('has_probation')->default(true); // prints the 3-month probation clause
            $table->string('salary_type', 10)->default('gross'); // gross | nett

            // [{component_id, name, kind, amount}] as they were when the letter
            // was saved — renaming a component later does not rewrite old letters.
            $table->json('compensation');
            $table->decimal('total_compensation', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->string('signatory_name', 150)->nullable();
            $table->string('signatory_title', 150)->nullable();

            $table->dateTime('sent_at')->nullable();
            $table->string('decision', 10)->default('pending'); // pending | accepted | rejected
            $table->dateTime('decided_at')->nullable();
            $table->unsignedBigInteger('created_by_employee_id')->nullable();
            $table->unsignedBigInteger('hired_employee_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('hired_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
        });

        // One row, read through RecruitmentSetting::current().
        Schema::create('recruitment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_timezone', 50)->default('Asia/Jakarta');

            // internal = this system's own calendar; microsoft = also mirrored
            // to the Outlook calendar of `organizer_email` (empty = the shared
            // mailbox, MS_SENDER_EMAIL).
            $table->string('calendar_provider', 20)->default('internal');
            $table->string('organizer_email', 150)->nullable();

            // Offering letters (Offering Letter → Settings). The number format
            // takes {seq} {day} {month} {roman} {year} {yy}.
            $table->string('offer_number_format', 100)->default('EC/{month}/OL/IN/{day}{seq}/{year}');
            $table->unsignedTinyInteger('offer_number_digits')->default(3);
            $table->decimal('offer_base_salary_min_percent', 5, 2)->default(75);
            $table->string('offer_legal_basis')->default('PP No. 36 Tahun 2021 tentang Pengupahan, Pasal 41');
            $table->string('offer_default_benefits', 500)->default('BPJS Kesehatan & BPJS Ketenagakerjaan');
            $table->unsignedTinyInteger('offer_response_days')->default(3);
            $table->string('offer_signing_city', 100)->default('Surabaya');
            $table->timestamps();
        });

        DB::table('recruitment_settings')->insert(['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        foreach ([
            'recruitment_settings', 'recruitment_offers', 'recruitment_offer_components', 'recruitment_interview_interviewers',
            'recruitment_interviews', 'recruitment_candidate_status_histories', 'recruitment_candidate_documents',
            'recruitment_candidates', 'recruitment_job_opening_documents', 'recruitment_job_openings', 'recruitment_options',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function seedOfferComponents(): void
    {
        $now = now();
        $rows = [];

        foreach (self::OFFER_COMPONENTS as $name => $kind) {
            $rows[] = [
                'name' => $name, 'kind' => $kind, 'sort_order' => count($rows) + 1, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        DB::table('recruitment_offer_components')->insert($rows);
    }

    private function seedOptions(): void
    {
        $now = now();
        $rows = [];

        foreach (self::OPTIONS as $type => $options) {
            $order = 1;
            foreach ($options as $name => $rules) {
                $rows[] = [
                    'type'         => $type,
                    'name'         => $name,
                    'sort_order'   => $order++,
                    'is_active'    => true,
                    'requirement'  => $rules['requirement'] ?? null,
                    'submission'   => $rules['submission'] ?? null,
                    'file_formats' => isset($rules['file_formats']) ? json_encode($rules['file_formats']) : null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        DB::table('recruitment_options')->insert($rows);
    }
};
