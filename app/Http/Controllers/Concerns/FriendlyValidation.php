<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Validation\Validator;

/**
 * Pesan galat validasi yang JELAS bagi pengguna: nama kolom seperti di formulir ("Account Number", bukan "account_number"), penyebab dan
 * batasnya ("maksimum 10 karakter", "harus antara 1900 dan 2100"), bukan kalimat umum "data is invalid".
 *
 * Pakai: setelah Validator::make(...) dan sebelum $validator->fails():  $this->friendly($validator);
 * lalu di respons gagal:  'message' => $validator->errors()->first(), 'errors' => $validator->errors().
 *
 * Aturan yang sudah diberi pesan khusus oleh pemanggil tidak ditimpa (pesan khusus selalu didahulukan).
 */
trait FriendlyValidation
{
    /** Nama tampilan kolom yang tidak cukup dengan "Title Case" dari nama kolomnya. */
    private static array $friendlyLabels = [
        'rural_urban_village' => 'Rural / Urban Village', 'email_work' => 'Email (Work)', 'email_personal' => 'Email (Personal)',
        'cell_phone_country' => 'Cell Phone Country', 'telephone_country' => 'Telephone Country', 'fax_country' => 'Fax Country',
        'telephone_extension' => 'Telephone Extension', 'fax_extension' => 'Fax Extension', 'identification_number' => 'Identification Number',
        'identification_type' => 'Identification Type', 'responsible_institution' => 'Responsible Institution', 'drive_link' => 'Drive Link',
        'verify_link' => 'Verify Link', 'attachment_verify_link' => 'Verify Link', 'attachment_drive_link' => 'Drive Link',
        'institute_place' => 'Institute / Place', 'duration_of_course' => 'Duration of Course', 'branch_of_study' => 'Branch of Study',
        'valid_from' => 'Valid From', 'valid_to' => 'Valid To', 'bank_key' => 'Bank Key', 'account_number' => 'Account Number',
        'account_holder' => 'Account Holder', 'reference_number' => 'Reference Number', 'paid_at' => 'Paid At', 'birth_place' => 'Birth Place',
        'birth_date' => 'Birth Date', 'start_year' => 'Start Year', 'graduation_year' => 'Graduation Year', 'first_year' => 'First Year',
    ];

    protected function friendly(Validator $validator): Validator
    {
        $labels = [];
        $messages = [];

        foreach ($validator->getRules() as $field => $rules) {
            $last = (string) \Illuminate\Support\Arr::last(explode('.', (string) $field));
            $label = self::$friendlyLabels[$last] ?? ucwords(str_replace('_', ' ', $last));
            $labels[$field] = $label;

            $rules = array_values(array_filter((array) $rules, 'is_string'));
            $numeric = (bool) array_intersect(array_map(fn ($r) => strtolower(explode(':', $r)[0]), $rules), ['integer', 'numeric']);

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $name = strtolower($name);
                if ($name === 'max' && $arg !== null) {
                    $messages["$field.max"] = $numeric ? ":Attribute must be at most {$arg}." : ":Attribute is too long (maximum {$arg} characters).";
                } elseif ($name === 'min' && $arg !== null) {
                    $messages["$field.min"] = $numeric ? ":Attribute must be at least {$arg}." : ":Attribute is too short (minimum {$arg} characters).";
                } elseif ($name === 'in' && $arg !== null) {
                    $messages["$field.in"] = ':Attribute must be one of: ' . str_replace(',', ', ', $arg) . '.';
                }
            }
        }

        $messages += [
            'required' => ':Attribute is required.',
            'email'    => ':Attribute must be a valid email address, e.g. name@company.com.',
            'url'      => ':Attribute must be a full web address starting with http:// or https://.',
            'date'     => ':Attribute is not a valid date.',
            'integer'  => ':Attribute must be a whole number.',
            'numeric'  => ':Attribute must be a number.',
            'boolean'  => ':Attribute must be yes or no.',
            'unique'   => 'This :attribute is already in use. Enter a different one.',
            'exists'   => 'The selected :attribute is not valid.',
            'before_or_equal' => ':Attribute cannot be in the future.',
        ];

        // Pesan khusus dari pemanggil (customMessages / customAttributes) menang atas yang dibuat di sini.
        return $validator
            ->setAttributeNames($validator->customAttributes + $labels)
            ->setCustomMessages($validator->customMessages + $messages);
    }
}
