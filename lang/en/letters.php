<?php

/*
 * Words every letter of the Letter Templates hub shares, in English.
 * The Indonesian text is in lang/id/letters.php — keep the two in step.
 * A letter's own wording is in its Blade (resources/views/hr-general/letters/pdf/),
 * both languages side by side.
 */
return [
    'number'      => 'Number',
    'subject'     => 'Subject',
    'to'          => 'To:',
    'place'       => '',
    'closing'     => 'Sincerely,',
    'preview'     => '(number given when the letter is generated)',

    'name'        => 'Name',
    'employee_id' => 'Employee ID',
    'position'    => 'Position',
    'department'  => 'Department',
    'join_date'   => 'Join Date',

    'titles' => [
        'employment_certificate' => 'EMPLOYMENT CERTIFICATE',
        'employment_reference'   => 'EMPLOYMENT REFERENCE LETTER',
        'assignment_letter'      => 'ASSIGNMENT LETTER',
        'goods_receipt'          => 'GOODS RECEIPT',
        'payment_receipt'        => 'PAYMENT RECEIPT',
        'bpjs_deactivation'      => 'BPJS HEALTH MEMBERSHIP DEACTIVATION LETTER',
    ],

    // Email that carries a letter as a PDF.
    'email' => [
        'subject' => ':subject — :number',
        'body'    => "Dear :name,\n\nPlease find attached the letter :subject, number :number.\n\nSincerely,\n:signatory\n:signatory_title",
    ],
];
