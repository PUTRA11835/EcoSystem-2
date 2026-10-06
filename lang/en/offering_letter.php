<?php

/*
 * Offering letter (PDF and emails) in English.
 * The Indonesian text is in lang/id/offering_letter.php — keep the two in step.
 * :placeholders are filled in escaped; tags around them are part of the text.
 */
return [
    'title'          => 'OFFERING LETTER',
    'to'             => 'To:<br><strong>:name</strong>',
    'greeting'       => 'Dear :name,',
    'selected'       => 'On behalf of <strong>:company</strong>, we are pleased to inform you that you have been selected for the position of <strong>:position</strong> at our company.',
    'duties'         => 'Your responsibilities will be to :description',
    'duties_default' => 'You will carry out the duties of the <strong>:position</strong> position.',
    'joining'        => 'We look forward to having you join us on <strong>:date</strong>.',
    'compensation'   => 'Compensation:',
    'total'          => 'Total monthly compensation: <strong>:amount</strong> (:type)',
    'benefits'       => 'Benefits: :benefits',
    'probation'      => 'A probation period of 3 (three) months applies from your joining date.',
    'notes'          => 'Notes: :notes',
    'respond'        => 'If you accept this offer, please sign this letter and return it within :days days of the date of this letter.',
    'closing'        => 'Sincerely,',
    'approval'       => 'Agreed and accepted by,',

    // Email that carries the letter as a PDF.
    'email' => [
        'subject'  => 'Offering Letter - :position',
        'body'     => '<p>Dear :name,</p>'
            . '<p>Please find attached Offering Letter No. :number for the position of <strong>:position</strong>.</p>'
            . '<p>If you accept this offer, please sign the letter and return it within :days days of the date of the letter.</p>'
            . '<p>Sincerely,<br>:signatory<br>:signatory_title</p>',
        'filename' => 'Offering Letter.pdf',
    ],

    // Email with the sign-in details, sent when the offer is accepted.
    'account_email' => [
        'subject' => 'Welcome aboard — your :app account',
        'body'    => '<p>Dear :name,</p>'
            . '<p>Welcome aboard! Your <strong>:app</strong> account has been created. Use these details to sign in:</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;">'
            . '<tr><td style="padding:4px 16px 4px 0;">Sign-in page</td><td style="padding:4px 0;"><a href=":url">:url</a></td></tr>'
            . '<tr><td style="padding:4px 16px 4px 0;">Username (ECI)</td><td style="padding:4px 0;"><strong>:eci</strong></td></tr>'
            . '<tr><td style="padding:4px 16px 4px 0;">Email</td><td style="padding:4px 0;"><strong>:email</strong></td></tr>'
            . '<tr><td style="padding:4px 16px 4px 0;">Initial password</td><td style="padding:4px 0;"><strong style="font-family:monospace;">:password</strong></td></tr>'
            . '</table>'
            . '<p>After you sign in with this initial password, you will be asked to create your own password through a link we send to this email address. Please do not share this initial password with anyone.</p>'
            . '<p>Sincerely,<br>The HR Team</p>',
    ],
];
