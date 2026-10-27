<?php

use App\Support\Letters\DefaultDesigns;
use App\Support\Letters\LetterTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Offering Letter as a letter template (Template Designs), so HR can write an offering letter from the Letter Templates hub
 * without a candidate going through the offering process. Adds every built-in design that is not in the table yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DefaultDesigns::bodies() as $key => $design) {
            if (DB::table('letter_template_defs')->where('template_key', $key)->exists()) {
                continue;
            }

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
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('letter_template_defs')->where('template_key', 'offering_letter')->delete();
    }
};
