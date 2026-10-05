<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\Offer;
use App\Models\Recruitment\RecruitmentSetting;
use Illuminate\Support\Facades\Http;

/**
 * Emails a generated offer-letter PDF via Microsoft Graph. Same
 * client-credentials token pattern as MicrosoftGraphCalendarService /
 * OneDriveService; kept as its own small class rather than reusing
 * EmailController, whose Graph helpers are private and whose attachment path
 * is coupled to the ticket-reply draft flow.
 */
class OfferMailer
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');
    }

    protected function getAccessToken(): string
    {
        $tenantId = config('services.microsoft_graph.tenant_id');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.microsoft_graph.client_id'),
                'client_secret' => config('services.microsoft_graph.client_secret'),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to obtain Microsoft Graph access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * The email a letter starts with, as plain text for HR to adjust before it
     * is sent: in the letter's own language (lang/{id,en}/offering_letter.php).
     *
     * @return array{subject: string, body: string}
     */
    public function defaultEmail(Offer $offer): array
    {
        $settings = RecruitmentSetting::current();
        $lang = $offer->languageCode();
        $html = __('offering_letter.email.body', [
            'name'            => $offer->candidate_name,
            'number'          => $offer->letter_number,
            'position'        => $offer->position_title,
            'days'            => $settings->offer_response_days,
            'signatory'       => $offer->signatory_name,
            'signatory_title' => $offer->signatory_title,
        ], $lang);

        // Paragraphs become blank lines and <br> a line break; the rest of the markup goes.
        $text = preg_replace(['#<br\s*/?>#i', '#</p>\s*#i'], ["\n", "\n\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [
            'subject' => __('offering_letter.email.subject', ['position' => $offer->position_title], $lang),
            'body'    => trim(preg_replace("/\n{3,}/", "\n\n", $text)),
        ];
    }

    /** Plain text as typed by HR, as the HTML of an email: escaped, one paragraph per blank-line block. */
    public static function textToHtml(string $text): string
    {
        $paragraphs = preg_split("/\R{2,}/", trim(str_replace("\r\n", "\n", $text)));

        return collect($paragraphs)
            ->map(fn (string $paragraph) => '<p>' . nl2br(e(trim($paragraph)), false) . '</p>')
            ->implode('');
    }

    /** Emails the signed letter as a PDF, with the subject and message HR wrote. */
    public function sendOfferLetter(Offer $offer, string $pdfBytes, string $subject, string $bodyText): void
    {
        if (!$offer->candidate_email) {
            throw new \RuntimeException('The letter has no candidate email address — cannot send it.');
        }

        $sender = RecruitmentSetting::current()->effectiveOrganizerEmail();
        if (!$sender) {
            throw new \RuntimeException('No Microsoft Graph sender mailbox configured.');
        }

        $this->send($sender, [
            'subject'      => $subject,
            'body'         => ['contentType' => 'HTML', 'content' => self::textToHtml($bodyText)],
            'toRecipients' => [
                ['emailAddress' => ['address' => $offer->candidate_email, 'name' => $offer->candidate_name]],
            ],
            'attachments' => [[
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => __('offering_letter.email.filename', [], $offer->languageCode()),
                'contentType'  => 'application/pdf',
                'contentBytes' => base64_encode($pdfBytes),
            ]],
        ], 'Failed to send offer letter email');
    }

    /**
     * Emails a newly hired candidate how to sign in: the sign-in page, their
     * username (ECI), email and the default password HR set — in the language
     * of their offering letter. Signing in with that password asks them to set
     * their own (CandidateHireService).
     */
    public function sendAccountDetails(Offer $offer, string $name, string $eci, string $email, string $password): void
    {
        $sender = RecruitmentSetting::current()->effectiveOrganizerEmail();
        if (!$sender) {
            throw new \RuntimeException('No Microsoft Graph sender mailbox configured.');
        }

        $lang = $offer->languageCode();
        $app = config('app.name', 'ECoSystem');
        $body = __('offering_letter.account_email.body', array_map(fn ($value) => e($value), [
            'name'     => $name,
            'app'      => $app,
            'url'      => rtrim(config('app.url'), '/') . '/auth/login',
            'eci'      => $eci,
            'email'    => $email,
            'password' => $password,
        ]), $lang);

        $this->send($sender, [
            'subject'      => __('offering_letter.account_email.subject', ['app' => $app], $lang),
            'body'         => ['contentType' => 'HTML', 'content' => $body],
            'toRecipients' => [['emailAddress' => ['address' => $email, 'name' => $name]]],
        ], 'Failed to send the sign-in details email', keepCopy: false);
    }

    /**
     * @param  array  $message   a Graph `message` resource
     * @param  bool   $keepCopy  keep it in the sender's Sent Items — not for an email that carries a password
     */
    protected function send(string $sender, array $message, string $failure, bool $keepCopy = true): void
    {
        $response = Http::withToken($this->getAccessToken())->post("{$this->baseUrl}/users/{$sender}/sendMail", [
            'message'         => $message,
            'saveToSentItems' => $keepCopy,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("{$failure}: " . $response->body());
        }
    }
}
