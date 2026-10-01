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
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');
    }

    private function getAccessToken(): string
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

    public function sendOfferLetter(Offer $offer, string $pdfBytes): void
    {
        if (!$offer->candidate_email) {
            throw new \RuntimeException('The letter has no candidate email address — cannot send it.');
        }

        $settings = RecruitmentSetting::current();
        $sender = $settings->effectiveOrganizerEmail();
        if (!$sender) {
            throw new \RuntimeException('No Microsoft Graph sender mailbox configured.');
        }

        $body = '<p>Yth. ' . e($offer->candidate_name) . ',</p>'
            . '<p>Terlampir kami sampaikan Offering Letter No. ' . e($offer->letter_number) . ' untuk posisi <strong>'
            . e($offer->position_title) . '</strong>.</p>'
            . '<p>Apabila Anda menerima tawaran ini, mohon menandatangani surat tersebut dan mengembalikannya maksimal '
            . $settings->offer_response_days . ' hari sejak tanggal surat dibuat.</p>'
            . '<p>Hormat kami,<br>' . e($offer->signatory_name) . '<br>' . e($offer->signatory_title) . '</p>';

        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post("{$this->baseUrl}/users/{$sender}/sendMail", [
            'message' => [
                'subject'      => "Offering Letter - {$offer->position_title}",
                'body'         => ['contentType' => 'HTML', 'content' => $body],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $offer->candidate_email, 'name' => $offer->candidate_name]],
                ],
                'attachments' => [[
                    '@odata.type'  => '#microsoft.graph.fileAttachment',
                    'name'         => 'Offering Letter.pdf',
                    'contentType'  => 'application/pdf',
                    'contentBytes' => base64_encode($pdfBytes),
                ]],
            ],
            'saveToSentItems' => true,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to send offer letter email: ' . $response->body());
        }
    }
}
