<?php

/*
 * Offering letter (PDF and emails) in Bahasa Indonesia.
 * The English text is in lang/en/offering_letter.php — keep the two in step.
 * :placeholders are filled in escaped; tags around them are part of the text.
 */
return [
    'title'          => 'OFFERING LETTER',
    'to'             => 'Kepada Yth,<br>Bapak/Ibu/Saudara/i <strong>:name</strong><br>Di Tempat',
    'greeting'       => 'Dengan Hormat,',
    'selected'       => 'Kami mewakili <strong>:company</strong> menyampaikan bahwa Anda terpilih untuk mengisi posisi <strong>:position</strong> di perusahaan kami.',
    'duties'         => 'Anda akan bertugas untuk :description',
    'duties_default' => 'Anda akan bertugas sesuai dengan posisi <strong>:position</strong>.',
    'joining'        => 'Kami berharap Anda dapat bergabung pada tanggal <strong>:date</strong>.',
    'compensation'   => 'Kompensasi:',
    'total'          => 'Total kompensasi setiap bulan: <strong>:amount</strong> (:type)',
    'benefits'       => 'Benefit: :benefits',
    'probation'      => 'Masa percobaan berlangsung selama 3 (tiga) bulan sejak tanggal bergabung.',
    'notes'          => 'Catatan: :notes',
    'respond'        => 'Apabila Anda menerima tawaran ini, mohon menandatangani surat ini dan mengembalikannya maksimal :days hari sejak tanggal dibuat.',
    'closing'        => 'Hormat kami,',
    'approval'       => 'Menyetujui,',

    // Email that carries the letter as a PDF.
    'email' => [
        'subject'  => 'Offering Letter - :position',
        'body'     => '<p>Yth. :name,</p>'
            . '<p>Terlampir kami sampaikan Offering Letter No. :number untuk posisi <strong>:position</strong>.</p>'
            . '<p>Apabila Anda menerima tawaran ini, mohon menandatangani surat tersebut dan mengembalikannya maksimal :days hari sejak tanggal surat dibuat.</p>'
            . '<p>Hormat kami,<br>:signatory<br>:signatory_title</p>',
        'filename' => 'Offering Letter.pdf',
    ],

    // Email with the sign-in details, sent when the offer is accepted.
    'account_email' => [
        'subject' => 'Selamat bergabung — akun :app Anda',
        'body'    => '<p>Yth. :name,</p>'
            . '<p>Selamat bergabung! Akun <strong>:app</strong> Anda sudah dibuat. Gunakan data berikut untuk masuk:</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;">'
            . '<tr><td style="padding:4px 16px 4px 0;">Alamat masuk</td><td style="padding:4px 0;"><a href=":url">:url</a></td></tr>'
            . '<tr><td style="padding:4px 16px 4px 0;">Username (ECI)</td><td style="padding:4px 0;"><strong>:eci</strong></td></tr>'
            . '<tr><td style="padding:4px 16px 4px 0;">Email</td><td style="padding:4px 0;"><strong>:email</strong></td></tr>'
            . '<tr><td style="padding:4px 16px 4px 0;">Password awal</td><td style="padding:4px 0;"><strong style="font-family:monospace;">:password</strong></td></tr>'
            . '</table>'
            . '<p>Setelah masuk dengan password awal ini, Anda akan diminta membuat password Anda sendiri melalui tautan yang kami kirim ke email ini. Jangan bagikan password awal ini kepada siapa pun.</p>'
            . '<p>Hormat kami,<br>Tim HR</p>',
    ],
];
