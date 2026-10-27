<?php

namespace App\Support\Letters;

/**
 * The wording the built-in letter templates start with — the same text they had when it was written in
 * Blade (resources/views/hr-general/letters/pdf/{key}.blade.php), now as editable HTML with {{placeholders}}.
 * Seeded into `letter_template_defs` by the migration; "Reset to default" in the designer writes it back.
 */
class DefaultDesigns
{
    /** @return array<string, array{body_id: string, body_en: string, empty?: array, closing?: array}> */
    public static function bodies(): array
    {
        $all = [
            'employment_certificate' => [
                'body_id' => self::title('SURAT KETERANGAN KERJA', 'Nomor')
                    . '<p>Yang bertanda tangan di bawah ini:</p>'
                    . self::details([['Nama', '<strong>{{signer_name}}</strong>'], ['Jabatan', '<strong>{{signer_title}}</strong>']])
                    . '<p>Dengan ini menerangkan bahwa:</p><p>{{employee_details}}</p>'
                    . '<p>adalah benar karyawan {{company}} yang bekerja sejak {{join_date}} sampai dengan tanggal surat ini dibuat.</p>'
                    . '<p>Surat keterangan ini dibuat untuk keperluan {{purpose}}.</p>'
                    . '<p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>'
                    . '<p>{{signature_block}}</p>',
                'body_en' => self::title('EMPLOYMENT CERTIFICATE', 'Number')
                    . '<p>The undersigned:</p>'
                    . self::details([['Name', '<strong>{{signer_name}}</strong>'], ['Position', '<strong>{{signer_title}}</strong>, {{company}}']])
                    . '<p>hereby certifies that:</p><p>{{employee_details}}</p>'
                    . '<p>is an employee of {{company}}, employed since {{join_date}} up to the date of this letter.</p>'
                    . '<p>This certificate is issued for the purpose of {{purpose}}.</p>'
                    . '<p>This certificate is issued truthfully, to be used as appropriate.</p>'
                    . '<p>{{signature_block}}</p>',
            ],
            'employment_reference' => [
                'body_id' => self::title('SURAT REFERENSI KERJA', 'Nomor')
                    . '<p>Kepada pihak yang berkepentingan,</p>'
                    . '<p>Yang bertanda tangan di bawah ini, <strong>{{signer_name}}</strong>, {{signer_title}} {{company}}, dengan ini menerangkan bahwa:</p>'
                    . '<p>{{employee_details}}</p>'
                    . '<p>telah bekerja di {{company}} sejak {{join_date}} {{end_date}}.</p>'
                    . '<p>{{remarks}}</p>'
                    . '<p>Kami merekomendasikan yang bersangkutan kepada pihak mana pun yang membutuhkan, dan mendoakan kesuksesannya.</p>'
                    . '<p>{{signature_block}}</p>',
                'body_en' => self::title('EMPLOYMENT REFERENCE LETTER', 'Number')
                    . '<p>To whom it may concern,</p>'
                    . '<p>The undersigned, <strong>{{signer_name}}</strong>, {{signer_title}} of {{company}}, hereby states that:</p>'
                    . '<p>{{employee_details}}</p>'
                    . '<p>has worked at {{company}} from {{join_date}} {{end_date}}.</p>'
                    . '<p>{{remarks}}</p>'
                    . '<p>We recommend {{employee_name}} to any organisation that may consider them, and wish them every success.</p>'
                    . '<p>{{signature_block}}</p>',
                // What {{end_date}} / {{remarks}} print when they were left empty.
                'empty' => [
                    'end_date' => ['id' => 'sampai dengan saat ini', 'en' => 'up to the present'],
                    'remarks'  => ['id' => '', 'en' => ''],
                ],
            ],
            'assignment_letter' => [
                'body_id' => self::title('SURAT TUGAS', 'Nomor')
                    . '<p>Yang bertanda tangan di bawah ini, <strong>{{signer_name}}</strong>, {{signer_title}} {{company}}, dengan ini menugaskan:</p>'
                    . '<p>{{employee_details}}</p><p>untuk melaksanakan tugas sebagai berikut:</p>'
                    . self::details([['Tugas', '{{task}}'], ['Lokasi', '{{location}}'], ['Periode', '{{period_from}} s.d. {{period_to}}']])
                    . '<p>Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab dan dilaporkan setelah selesai.</p>'
                    . '<p>{{signature_block}}</p>',
                'body_en' => self::title('ASSIGNMENT LETTER', 'Number')
                    . '<p>The undersigned, <strong>{{signer_name}}</strong>, {{signer_title}} of {{company}}, hereby assigns:</p>'
                    . '<p>{{employee_details}}</p><p>to carry out the following assignment:</p>'
                    . self::details([['Assignment', '{{task}}'], ['Location', '{{location}}'], ['Period', '{{period_from}} – {{period_to}}']])
                    . '<p>Please carry out this assignment responsibly and report on it upon completion.</p>'
                    . '<p>{{signature_block}}</p>',
            ],
            'goods_receipt' => [
                'closing' => ['id' => 'Yang menerima,', 'en' => 'Received by,'],
                'body_id' => self::title('TANDA TERIMA', 'Nomor')
                    . '<p>Telah diterima dari <strong>{{received_from}}</strong> barang-barang sebagai berikut:</p>'
                    . '<p>{{items}}</p>{{employee_concerned_line}}'
                    . '<p>Demikian tanda terima ini dibuat sebagai bukti bahwa barang-barang di atas telah diterima dengan baik.</p>'
                    . '<p>{{signature_block}}</p>',
                'body_en' => self::title('GOODS RECEIPT', 'Number')
                    . '<p>The following items have been received from <strong>{{received_from}}</strong>:</p>'
                    . '<p>{{items}}</p>{{employee_concerned_line}}'
                    . '<p>This receipt is made as proof that the items above were received in good condition.</p>'
                    . '<p>{{signature_block}}</p>',
            ],
            'payment_receipt' => [
                'closing' => ['id' => 'Yang menerima,', 'en' => 'Received by,'],
                'body_id' => self::title('KWITANSI', 'Nomor')
                    . self::details([['Telah terima dari', '<strong>{{received_from}}</strong>'], ['Uang sejumlah', '<em>{{amount_words}}</em>'], ['Untuk pembayaran', '{{purpose}}']], 0)
                    . '{{employee_concerned_line}}'
                    . '<table style="border:1pt solid #000000"><tbody><tr><td style="border:1pt solid #000000;padding-left:14pt;font-size:13pt;font-weight:bold">{{amount}}</td></tr></tbody></table>'
                    . '<p>{{signature_block}}</p>',
                'body_en' => self::title('PAYMENT RECEIPT', 'Number')
                    . self::details([['Received from', '<strong>{{received_from}}</strong>'], ['The sum of', '<em>{{amount_words}}</em>'], ['For payment of', '{{purpose}}']], 0)
                    . '{{employee_concerned_line}}'
                    . '<table style="border:1pt solid #000000"><tbody><tr><td style="border:1pt solid #000000;padding-left:14pt;font-size:13pt;font-weight:bold">{{amount}}</td></tr></tbody></table>'
                    . '<p>{{signature_block}}</p>',
            ],
        ];

        return $all + ['offering_letter' => self::offering()];
    }

    /**
     * The offering letter of the Offering Letter menu (lang/{id,en}/offering_letter.php), written here as editable text. The
     * signing area has two columns — the signatory on the left, the candidate's acceptance on the right.
     */
    private static function offering(): array
    {
        $sign = fn (string $closing, string $approval) => '<table style="width:100%"><tbody>'
            . '<tr><td>{{city}}, {{letter_date}}<br>' . $closing . '</td><td style="width:36%"><br>' . $approval . '</td></tr>'
            . '<tr><td style="height:70pt;vertical-align:middle">{{signature}}</td><td></td></tr>'
            . '<tr><td><strong><u>{{signer_name}}</u></strong><br>{{signer_title}}</td><td><strong>{{candidate_name}}</strong></td></tr>'
            . '</tbody></table>';

        return [
            'body_id' => self::title('OFFERING LETTER', 'Nomor')
                . '<p>Kepada Yth,<br>Bapak/Ibu/Saudara/i <strong>{{candidate_name}}</strong><br>Di Tempat</p>'
                . '<p>Dengan Hormat,</p>'
                . '<p>Kami mewakili <strong>{{company}}</strong> menyampaikan bahwa Anda terpilih untuk mengisi posisi <strong>{{position_title}}</strong> di perusahaan kami.</p>'
                . '<p>Anda akan bertugas untuk {{job_description}}</p>'
                . '<p>Kami berharap Anda dapat bergabung pada tanggal <strong>{{joining_date}}</strong>.</p>'
                . '<p><strong>Kompensasi:</strong></p><p>{{compensation}}</p>'
                . '<p>Total kompensasi setiap bulan: <strong>{{compensation_total}}</strong> ({{salary_type}})</p>'
                . '<p>Benefit: {{benefits}}</p><p>{{probation}}</p><p>Catatan: {{notes}}</p>'
                . '<p>Apabila Anda menerima tawaran ini, mohon menandatangani surat ini dan mengembalikannya maksimal {{response_days}} hari sejak tanggal dibuat.</p>'
                . $sign('Hormat kami,', 'Menyetujui,'),
            'body_en' => self::title('OFFERING LETTER', 'Number')
                . '<p>To:<br><strong>{{candidate_name}}</strong></p>'
                . '<p>Dear {{candidate_name}},</p>'
                . '<p>On behalf of <strong>{{company}}</strong>, we are pleased to inform you that you have been selected for the position of <strong>{{position_title}}</strong> at our company.</p>'
                . '<p>Your responsibilities will be to {{job_description}}</p>'
                . '<p>We look forward to having you join us on <strong>{{joining_date}}</strong>.</p>'
                . '<p><strong>Compensation:</strong></p><p>{{compensation}}</p>'
                . '<p>Total monthly compensation: <strong>{{compensation_total}}</strong> ({{salary_type}})</p>'
                . '<p>Benefits: {{benefits}}</p><p>{{probation}}</p><p>Notes: {{notes}}</p>'
                . '<p>If you accept this offer, please sign this letter and return it within {{response_days}} days of the date of this letter.</p>'
                . $sign('Sincerely,', 'Agreed and accepted by,'),
            // What a field prints when it was left empty.
            'empty' => [
                'job_description' => ['id' => 'menjalankan tugas sesuai dengan posisi yang ditawarkan.', 'en' => 'carry out the duties of the position offered.'],
                'salary_type'     => ['id' => 'Gross', 'en' => 'Gross'],
                'benefits'        => ['id' => '-', 'en' => '-'],
                'notes'           => ['id' => '-', 'en' => '-'],
                'response_days'   => ['id' => '3', 'en' => '3'],
            ],
        ];
    }

    private static function title(string $title, string $numberLabel): string
    {
        return '<p style="text-align:center"><strong><u>' . $title . '</u></strong></p>'
            . '<p style="text-align:center">' . $numberLabel . ': {{letter_number}}</p>';
    }

    /** @param array<int, array{0:string,1:string}> $rows */
    private static function details(array $rows, int $indentPt = 20): string
    {
        $html = '<table style="margin-left:' . $indentPt . 'pt"><tbody>';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><td style="width:120pt">' . $label . '</td><td style="width:12pt">:</td><td>' . $value . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }
}
