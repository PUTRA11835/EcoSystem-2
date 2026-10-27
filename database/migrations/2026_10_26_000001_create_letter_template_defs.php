<?php

use App\Support\Letters\DefaultDesigns;
use App\Support\Letters\LetterTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Letter templates as data: the wording (Bahasa Indonesia + English, written in the Word-style editor), the fields
 * a letter of it asks for, and its paper settings — so HR can view and adjust them instead of a developer editing Blade.
 * The five built-in internal templates are seeded with the wording they had in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_template_defs', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 60)->unique();
            $table->string('label', 150);
            $table->string('label_en', 150);
            $table->text('description')->nullable();
            $table->string('employee', 10)->default('optional');      // required | optional | none
            $table->json('fields')->nullable();
            $table->longText('body_id')->nullable();
            $table->longText('body_en')->nullable();
            $table->string('closing_id', 100)->nullable();
            $table->string('closing_en', 100)->nullable();
            $table->string('page_size', 10)->nullable();
            $table->string('page_orientation', 10)->nullable();
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $table->decimal("margin_{$side}", 5, 2)->nullable();
            }
            $table->boolean('is_system')->default(false);              // built in: can be edited and reset, not deleted
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $bodies = DefaultDesigns::bodies();
        $now = now();

        foreach ($bodies as $key => $design) {
            $built = LetterTemplates::BUILT_IN[$key];
            $fields = $built['fields'];
            foreach ($design['empty'] ?? [] as $field => $text) {
                $fields[$field]['empty'] = $text;
            }

            DB::table('letter_template_defs')->insert([
                'template_key' => $key,
                'label'        => $built['label'],
                'label_en'     => $built['label_en'],
                'description'  => $built['description'],
                'employee'     => $built['employee'] === 'required' ? 'required' : ($built['employee'] ? 'optional' : 'none'),
                'fields'       => json_encode($fields),
                'body_id'      => $design['body_id'],
                'body_en'      => $design['body_en'],
                'closing_id'   => $design['closing']['id'] ?? null,
                'closing_en'   => $design['closing']['en'] ?? null,
                'is_system'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_template_defs');
    }
};
