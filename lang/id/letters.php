<?php

/*
 * Words every letter of the Letter Templates hub shares, in Bahasa Indonesia.
 * The English text is in lang/en/letters.php — keep the two in step.
 * A letter's own wording is in its Blade (resources/views/hr-general/letters/pdf/),
 * both languages side by side.
 */
return [
    'number'      => 'Nomor',
    'subject'     => 'Perihal',
    'to'          => 'Kepada Yth.',
    'place'       => 'Di Tempat',
    'closing'     => 'Hormat kami,',
    'preview'     => '(nomor diberikan saat surat dibuat)',

    'name'        => 'Nama',
    'employee_id' => 'No. Karyawan',
    'position'    => 'Jabatan',
    'department'  => 'Departemen',
    'join_date'   => 'Tanggal Bergabung',

    'titles' => [
        'employment_certificate' => 'SURAT KETERANGAN KERJA',
        'employment_reference'   => 'SURAT REFERENSI KERJA',
        'assignment_letter'      => 'SURAT TUGAS',
        'goods_receipt'          => 'TANDA TERIMA',
        'payment_receipt'        => 'KWITANSI',
        'bpjs_deactivation'      => 'SURAT PENONAKTIFAN KEPESERTAAN BPJS KESEHATAN',
    ],

    // Email that carries a letter as a PDF.
    'email' => [
        'subject' => ':subject — :number',
        'body'    => "Yth. :name,\n\nTerlampir kami sampaikan surat :subject dengan nomor :number.\n\nHormat kami,\n:signatory\n:signatory_title",
    ],
];
