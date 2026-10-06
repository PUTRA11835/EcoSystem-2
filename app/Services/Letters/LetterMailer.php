<?php

namespace App\Services\Letters;

use App\Models\Letters\Letter;
use App\Models\Recruitment\RecruitmentSetting;
use App\Services\Recruitment\OfferMailer;

/**
 * Emails a signed letter of the Letter Templates hub as a PDF, the same way
 * offering letters are sent: Microsoft Graph, from the mailbox set in
 * Recruitment → Settings, with a subject and message HR can adjust first.
 */
class LetterMailer extends OfferMailer
{
    /**
     * The email a letter starts with, as plain text for HR to adjust, in the
     * letter's language (lang/{id,en}/letters.php).
     *
     * @return array{subject: string, body: string}
     */
    public function defaultLetterEmail(Letter $letter): array
    {
        $lang = $letter->languageCode();
        $values = [
            'name'            => $letter->counterparty ?: '',
            'subject'         => $letter->subject,
            'number'          => $letter->letter_number,
            'signatory'       => $letter->signatory_name,
            'signatory_title' => $letter->signatory_title,
        ];

        return [
            'subject' => __('letters.email.subject', $values, $lang),
            'body'    => __('letters.email.body', $values, $lang),
        ];
    }

    /** Emails the letter to its recipient with the PDF attached. */
    public function sendLetter(Letter $letter, string $pdfBytes, string $subject, string $bodyText): void
    {
        if (!$letter->recipient_email) {
            throw new \RuntimeException('The letter has no recipient email address.');
        }

        $sender = RecruitmentSetting::current()->effectiveOrganizerEmail();
        if (!$sender) {
            throw new \RuntimeException('No Microsoft Graph sender mailbox configured.');
        }

        $this->send($sender, [
            'subject'      => $subject,
            'body'         => ['contentType' => 'HTML', 'content' => self::textToHtml($bodyText)],
            'toRecipients' => [
                ['emailAddress' => ['address' => $letter->recipient_email, 'name' => $letter->counterparty ?: $letter->recipient_email]],
            ],
            'attachments' => [[
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => $letter->fileName(),
                'contentType'  => 'application/pdf',
                'contentBytes' => base64_encode($pdfBytes),
            ]],
        ], 'Failed to send the letter email');
    }
}
