<?php

namespace App\Support\Management;

/**
 * What Management → Employee manages, tab by tab: the dropdown lists of the employee forms (so HR can add a country, a bank, a document
 * type…) and the rules their data is saved under (maximum lengths, what is required, upload limits).
 *
 * This is the definition only. The values live in `employee_field_options` and `employee_section_settings` and are read through
 * App\Services\Management\EmployeeMasterData — by the forms (what a dropdown offers) and by the API controllers (what is accepted).
 *
 * A list
 *   defaults   [value => label, …] the options it starts with — exactly what the form offered before it became manageable
 *   column     [table, column] where employees' records keep the chosen value: it says how many records use each option, and a used
 *              option can be switched off but not deleted
 *   restrict   whether the API refuses a value that is not on the list. Off for free-typed fields (a record imported with another
 *              value keeps saving); on where the API already refused anything else (family relation, payment status)
 *   internal   the list can mark options that only Internal employees get (an External consultant has no BPJS)
 *
 * A rule: type int | bool | text, a default, and for int the range it may be set in — `max` never goes beyond what the database
 * column can hold.
 */
class EmployeeMasterSettings
{
    /** tab key => menu slug (the View box of Management → Roles opens the tab). */
    public const SLUGS = [
        'basic-data'     => 'management.employee.basic-data',
        'address'        => 'management.employee.address',
        'identification' => 'management.employee.identification',
        'family'         => 'management.employee.family',
        'education'      => 'management.employee.education',
        'qualification'  => 'management.employee.qualification',
        'contract'       => 'management.employee.contract',
        'bank'           => 'management.employee.bank',
        'payment'        => 'management.employee.payment',
        'attachment'     => 'management.employee.attachment',
    ];

    public static function exists(string $key): bool
    {
        return isset(self::SLUGS[$key]);
    }

    /** @return array{title: string, about: string, applies: string, lists: array, rules: array} */
    public static function get(string $key): array
    {
        $list = fn (string $label, string $hint, array $defaults, ?array $column = null, bool $restrict = false, bool $internal = false) => [
            'label' => $label, 'hint' => $hint, 'defaults' => $defaults, 'column' => $column, 'restrict' => $restrict, 'internal' => $internal,
        ];
        $int = fn (string $label, string $hint, int $default, int $min, int $max) => ['label' => $label, 'hint' => $hint, 'type' => 'int', 'default' => $default, 'min' => $min, 'max' => $max];
        $bool = fn (string $label, string $hint, bool $default) => ['label' => $label, 'hint' => $hint, 'type' => 'bool', 'default' => $default];
        $text = fn (string $label, string $hint, string $default) => ['label' => $label, 'hint' => $hint, 'type' => 'text', 'default' => $default];
        $same = fn (array $values) => array_combine($values, $values);

        return [
            'basic-data' => [
                'title' => 'Employee Basic Data', 'applies' => 'Master → Employee → Basic Data tab',
                'about' => 'The choices on the Basic Data form and the limits on a name. Position, department and the other organisation lists are master data of their own.',
                'lists' => [
                    'title'           => $list('Titles', 'Mr., Mrs., Dr. …', $same(['Mr.', 'Mrs.', 'Ms.', 'Dr.']), ['employee_basic_data', 'title']),
                    'gender'          => $list('Gender', 'The choices for gender.', $same(['Male', 'Female']), ['employee_basic_data', 'gender']),
                    'religion'        => $list('Religion', 'The choices for religion.', $same(['Islam', 'Christian', 'Catholic', 'Hindu', 'Buddhist', 'Confucian']), ['employee_basic_data', 'religion']),
                    'marital_status'  => $list('Marital status', 'The choices for marital status.', $same(['Single', 'Married', 'Divorced', 'Widow/Widower']), ['employee_basic_data', 'marital_status']),
                    'education_level' => $list('Education level', 'The highest education, as a short code. Saving a level that is not on the list is refused.', $same(['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3']), ['employee_basic_data', 'education_level'], true),
                ],
                'rules' => [
                    'first_name_max_length' => $int('First name — maximum length', 'Characters. Longer names are refused.', 255, 20, 255),
                    'nick_name_max_length'  => $int('Nickname — maximum length', 'Characters. The nickname must also be unique.', 100, 10, 100),
                ],
            ],
            'address' => [
                'title' => 'Employee Address', 'applies' => 'Master → Employee → Address tab',
                'about' => 'The address types and countries an address can use, and how long its lines may be.',
                'lists' => [
                    'address_type' => $list('Address types', 'Offered as suggestions: Home, KTP, Domicile, Office …', $same(['Home', 'KTP', 'Domicile', 'Office']), ['employee_address', 'address_type']),
                    'country'      => $list('Countries', 'The countries of the Country dropdown. Region, city and district are looked up for Indonesia only.', ['Indonesia' => 'Indonesia'], ['employee_address', 'country']),
                ],
                'rules' => [
                    'street_max_length'      => $int('Street / address line — maximum length', 'Characters.', 255, 20, 255),
                    'house_number_max_length' => $int('House number — maximum length', 'Characters.', 50, 5, 50),
                    'postal_code_max_length' => $int('Postal code — maximum length', 'Characters.', 20, 4, 20),
                    'require_street'         => $bool('Require the street', 'An address cannot be saved without its street / address line.', false),
                    'require_postal_code'    => $bool('Require the postal code', 'An address cannot be saved without a postal code.', false),
                ],
            ],
            'identification' => [
                'title' => 'Employee Identification', 'applies' => 'Master → Employee → Identification tab',
                'about' => 'The kinds of identification an employee can hold, and the limit on the number.',
                'lists' => [
                    'identification_type' => $list('Identification types', 'ID card, passport, tax ID … Tick “Internal only” for documents an External consultant does not have.', [
                        'KTP' => 'ID Card (KTP)', 'PASSPORT' => 'Passport', 'NPWP' => 'Tax ID (NPWP)', 'DRIVER_LICENSE' => 'Driver License (SIM)',
                        'BPJS_KESEHATAN' => 'BPJS Kesehatan', 'BPJS_KETENAGAKERJAAN' => 'BPJS Ketenagakerjaan', 'KITAS' => 'KITAS', 'KITAP' => 'KITAP', 'OTHER' => 'Other',
                    ], ['employee_identification', 'identification_type'], false, true),
                ],
                'rules' => [
                    'number_max_length'      => $int('Identification number — maximum length', 'Characters.', 100, 8, 100),
                    'institution_max_length' => $int('Responsible institution — maximum length', 'Characters.', 255, 20, 255),
                ],
            ],
            'family' => [
                'title' => 'Employee Family', 'applies' => 'Master → Employee → Family tab',
                'about' => 'The relations, titles and other choices for a family member.',
                'lists' => [
                    'relation' => $list('Relations', 'How the family member relates to the employee. Saving a relation that is not on the list is refused.', [
                        'spouse' => 'Spouse', 'child' => 'Child', 'parent' => 'Parent', 'father' => 'Father', 'mother' => 'Mother',
                        'sibling' => 'Sibling', 'brother' => 'Brother', 'sister' => 'Sister', 'other' => 'Other',
                    ], ['employee_family', 'relation'], true),
                    'title'    => $list('Titles', 'Mr., Mrs., Dr. …', $same(['Mr.', 'Mrs.', 'Ms.', 'Dr.']), ['employee_family', 'title']),
                    'gender'   => $list('Gender', 'The choices for gender.', $same(['Male', 'Female']), ['employee_family', 'gender'], true),
                    'religion' => $list('Religion', 'The choices for religion.', $same(['Islam', 'Christianity', 'Catholicism', 'Hinduism', 'Buddhism', 'Confucianism', 'Other']), ['employee_family', 'religion']),
                ],
                'rules' => [
                    'name_max_length'       => $int('Name — maximum length', 'Characters.', 255, 20, 255),
                    'occupation_max_length' => $int('Occupation — maximum length', 'Characters.', 255, 20, 255),
                ],
            ],
            'education' => [
                'title' => 'Employee Education', 'applies' => 'Master → Employee → Education tab',
                'about' => 'The education levels, and the range of years an education may be dated in.',
                'lists' => [
                    'education_type' => $list('Education levels', 'SD, SMP, S1 … with their names.', [
                        'SD' => 'SD - Elementary', 'SMP' => 'SMP - Junior High', 'SMA' => 'SMA - Senior High', 'SMK' => 'SMK - Vocational', 'D1' => 'D1 - Diploma 1',
                        'D2' => 'D2 - Diploma 2', 'D3' => 'D3 - Diploma 3', 'D4' => 'D4 - Diploma 4', 'S1' => 'S1 - Bachelor', 'S2' => 'S2 - Master', 'S3' => 'S3 - Doctoral',
                    ], ['employee_education', 'education_type']),
                ],
                'rules' => [
                    'year_min'             => $int('Earliest year', 'Start and graduation years before this are refused.', 1900, 1900, 2100),
                    'year_max'             => $int('Latest year', 'Start and graduation years after this are refused.', 2100, 1900, 2100),
                    'institute_max_length' => $int('Institute — maximum length', 'Characters.', 255, 20, 255),
                    'grade_max_length'     => $int('Final grade — maximum length', 'Characters.', 50, 4, 50),
                ],
            ],
            'qualification' => [
                'title' => 'Employee Qualification', 'applies' => 'Master → Employee → Qualification tab',
                'about' => 'The kinds of qualification and the languages a qualification can name. The module list above is managed on this page too.',
                'lists' => [
                    'qualification_type'     => $list('Qualification types', 'Education, certification, language …', $same(['Education', 'Certification', 'Language', 'Skill', 'Training']), ['employee_qualification', 'qualification_type']),
                    'qualification_language' => $list('Languages', 'The languages offered for a language qualification.', $same(['English', 'Indonesian', 'Mandarin', 'Japanese', 'Korean', 'Arabic', 'French', 'German', 'Spanish', 'Other']), ['employee_qualification', 'language']),
                ],
                'rules' => [],
            ],
            'contract' => [
                'title' => 'Employee Contract', 'applies' => 'My Profile → Contract tab',
                'about' => 'What an employee sees on the Contract tab of their own profile. Contracts themselves, types and templates are in HR & General → Contract.',
                'lists' => [],
                'rules' => [
                    'show_salary'          => $bool('Show the salary total', 'The Salary column. Compensation stays on its own tab.', true),
                    'show_contract_number' => $bool('Show the contract number', 'The number under the contract name.', true),
                    'show_past_contracts'  => $bool('Show expired and inactive contracts', 'Off: only the contract in force and upcoming ones.', true),
                    'ending_soon_days'     => $int('“Ending soon” window', 'Days before the end date. Also used by the HR contract lists and the Legal dashboard.', 30, 7, 365),
                ],
            ],
            'bank' => [
                'title' => 'Employee Bank Account', 'applies' => 'Master → Employee → Bank tab',
                'about' => 'The banks an account can be at, and how an account number is checked.',
                'lists' => [
                    'bank_name' => $list('Banks', 'The Bank dropdown.', [
                        'BCA' => 'Bank Central Asia (BCA)', 'Mandiri' => 'Bank Mandiri', 'BNI' => 'Bank Negara Indonesia (BNI)', 'BRI' => 'Bank Rakyat Indonesia (BRI)',
                        'CIMB Niaga' => 'CIMB Niaga', 'Danamon' => 'Bank Danamon', 'Permata' => 'Bank Permata', 'BTN' => 'Bank Tabungan Negara (BTN)', 'OCBC NISP' => 'Bank OCBC NISP',
                        'Maybank' => 'Maybank Indonesia', 'Panin' => 'Bank Panin', 'BTPN' => 'Bank BTPN', 'Jenius' => 'Jenius (BTPN)', 'Bank Jago' => 'Bank Jago', 'Seabank' => 'Seabank',
                        'Blu BCA' => 'Blu by BCA Digital', 'Allo Bank' => 'Allo Bank', 'Neobank' => 'Neobank', 'Other' => 'Other',
                    ], ['employee_bank', 'bank_name']),
                ],
                'rules' => [
                    'account_number_max_length' => $int('Account number — maximum length', 'Characters.', 50, 8, 50),
                    'account_number_digits_only' => $bool('Account number: digits only', 'Refuse spaces, dashes and letters in the account number.', false),
                ],
            ],
            'payment' => [
                'title' => 'Employee Basic Payment', 'applies' => 'Master → Employee → Payment tab',
                'about' => 'The payment methods and statuses, and the limit on a reference number.',
                'lists' => [
                    'payment_method' => $list('Payment methods', 'Transfer, cash, e-wallet …', $same(['Transfer', 'Cash', 'E-Wallet', 'Cheque', 'Credit Card']), ['employee_payment', 'payment_method']),
                    'payment_status' => $list('Payment statuses', 'Saving a status that is not on the list is refused.', $same(['Pending', 'Processing', 'Completed', 'Failed', 'Cancelled']), ['employee_payment', 'payment_status'], true),
                ],
                'rules' => [
                    'reference_max_length' => $int('Reference number — maximum length', 'Characters.', 100, 8, 100),
                ],
            ],
            'attachment' => [
                'title' => 'Employee Attachment', 'applies' => 'Master → Employee → Attachment tab',
                'about' => 'The kinds of document that can be uploaded, and what an upload may be.',
                'lists' => [
                    'document_type' => $list('Document types', 'KTP, diploma, contract …', [
                        'KTP' => 'KTP / ID Card', 'NPWP' => 'NPWP', 'Paspor' => 'Paspor / Passport', 'BPJS Kesehatan' => 'BPJS Kesehatan', 'BPJS Ketenagakerjaan' => 'BPJS Ketenagakerjaan',
                        'Ijazah' => 'Ijazah / Diploma', 'Transkrip Nilai' => 'Transkrip Nilai', 'Sertifikat' => 'Sertifikat', 'Kontrak Kerja' => 'Kontrak Kerja', 'SK Pengangkatan' => 'SK Pengangkatan',
                        'Surat Referensi' => 'Surat Referensi', 'CV' => 'CV / Resume', 'Akta Lahir' => 'Akta Lahir', 'Kartu Keluarga' => 'Kartu Keluarga', 'Foto' => 'Foto', 'Lainnya' => 'Lainnya',
                    ], ['employee_attachment', 'document_type']),
                ],
                'rules' => [
                    'max_file_size_mb'   => $int('Maximum file size', 'Megabytes per upload. The server may set a lower limit of its own.', 10, 1, 50),
                    'allowed_extensions' => $text('Allowed file types', 'Comma separated extensions, e.g. pdf,jpg,png,docx.', 'pdf,doc,docx,jpg,jpeg,png,xlsx,xls'),
                ],
            ],
        ][$key];
    }
}
