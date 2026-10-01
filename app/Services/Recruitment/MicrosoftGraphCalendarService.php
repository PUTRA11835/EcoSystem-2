<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\Interview;
use App\Models\Recruitment\RecruitmentSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sinkronisasi jadwal interview Rekrutmen ke Microsoft Teams/Outlook Calendar
 * lewat Microsoft Graph API, dengan client-credentials flow yang sama dengan
 * OneDriveService — akun bersama `services.microsoft_graph.sender_email`
 * bertindak sebagai organizer, kecuali RecruitmentSetting::organizer_email
 * diisi.
 *
 * Sinkronisasi bersifat best-effort dan SINKRON (tidak di-queue — codebase ini
 * belum punya worker queue). Pemanggil WAJIB membungkus pemanggilan method di
 * sini dengan try/catch supaya kegagalan Graph API tidak menggagalkan
 * penyimpanan jadwal interview itu sendiri (lihat InterviewScheduler).
 */
class MicrosoftGraphCalendarService
{
    private const TOKEN_CACHE_KEY = 'recruitment_ms_graph_token';

    /** Pages run this synchronously, so a slow Graph must not hang them. */
    private const TIMEOUT_SECONDS = 10;

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');
    }

    /** True when the server has the app credentials Graph needs (MS_TENANT_ID / MS_CLIENT_ID / MS_CLIENT_SECRET). */
    public function isConfigured(): bool
    {
        return config('services.microsoft_graph.tenant_id')
            && config('services.microsoft_graph.client_id')
            && config('services.microsoft_graph.client_secret');
    }

    /** What is missing on OUR side; a refusal by Microsoft only shows when a call is made (see failure()). */
    public function unavailableReason(): ?string
    {
        if (!$this->isConfigured()) {
            return 'Microsoft Graph credentials are not set on the server (MS_TENANT_ID / MS_CLIENT_ID / MS_CLIENT_SECRET).';
        }

        if (!RecruitmentSetting::current()->effectiveOrganizerEmail()) {
            return 'No Microsoft account is set. Enter one on the Recruitment Settings tab.';
        }

        return null;
    }

    private function getAccessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $tenantId = config('services.microsoft_graph.tenant_id');

        $response = Http::asForm()->timeout(self::TIMEOUT_SECONDS)->post(
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

        $token = $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->getAccessToken())->timeout(self::TIMEOUT_SECONDS);
    }

    private function organizerMailbox(): string
    {
        $mailbox = RecruitmentSetting::current()->effectiveOrganizerEmail();

        if (!$mailbox) {
            throw new \RuntimeException('No Microsoft Teams calendar account is set. Set one on the Recruitment Settings tab.');
        }

        return $mailbox;
    }

    /**
     * Logs the raw Graph response and returns an exception whose message says
     * what to do about it — this text is shown to HR on the Settings and
     * Schedule pages, so it names the cause instead of quoting Graph's JSON.
     */
    private function failure(string $action, Response $response, string $mailbox, array $context = []): \RuntimeException
    {
        Log::error("Recruitment: Graph {$action} failed", $context + [
            'mailbox' => $mailbox,
            'status'  => $response->status(),
            'body'    => $response->body(),
        ]);

        return new \RuntimeException(match ($response->status()) {
            401, 403 => "Microsoft denied access to the calendar of {$mailbox}. The system's Azure app registration needs the "
                . '"Calendars.ReadWrite" application permission with admin consent — ask the Microsoft 365 administrator to add it.',
            404 => "Microsoft could not find the mailbox {$mailbox}. Check the Teams account on the Settings tab.",
            default => "Microsoft Graph returned an error (HTTP {$response->status()}) for the calendar of {$mailbox}.",
        });
    }

    private function timezone(): string
    {
        return RecruitmentSetting::current()->default_timezone ?: 'Asia/Jakarta';
    }

    /**
     * Interviewers and the candidate, as required attendees: Outlook sends each
     * an invitation and the event lands in their own calendar. People without
     * an email address on file are left out.
     *
     * @return array<int, array{emailAddress: array{address: string, name: string}, type: string}>
     */
    private function attendeesFor(Interview $interview): array
    {
        $attendees = [];
        $emails = self::attendeeEmails($interview->interviewers->pluck('employee_id')->all());

        foreach ($interview->interviewers as $interviewer) {
            $email = $emails[$interviewer->employee_id] ?? null;

            if ($email) {
                $attendees[] = [
                    'emailAddress' => ['address' => $email, 'name' => $interviewer->basicData->full_name ?? $email],
                    'type'         => 'required',
                ];
            }
        }

        $candidateEmail = $interview->candidate->email ?? null;
        if ($candidateEmail) {
            $attendees[] = [
                'emailAddress' => ['address' => $candidateEmail, 'name' => $interview->candidate->name],
                'type'         => 'required',
            ];
        }

        return $attendees;
    }

    /**
     * Which email address an employee is invited on. In order of preference:
     * the work email of the primary address, any other work email, then the
     * login email of the account. The interviewer picker uses the same lookup
     * for its "no email" hint, so the form never promises an invitation the
     * sync cannot send.
     *
     * @param  int[]|null  $employeeIds  null = every employee
     * @return array<int, string>  [employee_id => email]
     */
    public static function attendeeEmails(?array $employeeIds = null): array
    {
        $scoped = fn ($query) => $employeeIds === null ? $query : $query->whereIn('employee_id', $employeeIds);

        $login = $scoped(DB::table('auth_users'))
            ->whereNotNull('employee_id')
            ->whereNotNull('email')->where('email', '!=', '')
            ->pluck('email', 'employee_id');

        // Primary address last, so it overwrites the others in the keyed result.
        $work = $scoped(DB::table('employee_address'))
            ->whereNotNull('email_work')->where('email_work', '!=', '')
            ->orderBy('is_primary')
            ->pluck('email_work', 'employee_id');

        return $work->union($login)->all();
    }

    private function eventPayload(Interview $interview): array
    {
        $interview->loadMissing(['candidate', 'interviewers.basicData']);

        $timezone = $this->timezone();
        $online = $interview->mode === 'online';
        // With a link of HR's own (Meet, Zoom, ...) no Teams meeting is generated;
        // the link travels in the location and the body instead.
        $ownLink = $online ? $interview->meeting_url : null;

        $body = nl2br(e($interview->notes ?? ''));
        if ($ownLink) {
            $body = 'Meeting link: <a href="' . e($ownLink) . '">' . e($ownLink) . '</a>' . ($body ? '<br><br>' . $body : '');
        }

        return [
            'subject'  => $interview->displayTitle(),
            'body'     => [
                'contentType' => 'HTML',
                'content'     => $body ?: 'Recruitment interview schedule.',
            ],
            'start' => ['dateTime' => $interview->scheduled_at->format('Y-m-d\TH:i:s'), 'timeZone' => $timezone],
            'end'   => ['dateTime' => $interview->endsAt()->format('Y-m-d\TH:i:s'), 'timeZone' => $timezone],
            'location' => ['displayName' => $online ? ($ownLink ?: 'Microsoft Teams') : ($interview->location ?: 'Onsite')],
            'attendees' => $this->attendeesFor($interview),
            'isOnlineMeeting'       => $online && !$ownLink,
            'onlineMeetingProvider' => $online && !$ownLink ? 'teamsForBusiness' : 'unknown',
        ];
    }

    /** Buat event baru di kalender organizer dan simpan id + link Teams-nya ke $interview. */
    public function createEvent(Interview $interview): void
    {
        $mailbox = $this->organizerMailbox();

        $response = $this->client()->post(
            "{$this->baseUrl}/users/{$mailbox}/events",
            $this->eventPayload($interview)
        );

        if (!$response->successful()) {
            throw $this->failure('createEvent', $response, $mailbox, ['interview_id' => $interview->id]);
        }

        $interview->forceFill([
            'ms_graph_event_id'  => $response->json('id'),
            'teams_meeting_url'  => $response->json('onlineMeeting.joinUrl'),
        ])->save();
    }

    /** Perbarui event Graph yang sudah ada (mis. reschedule). */
    public function updateEvent(Interview $interview): void
    {
        if (!$interview->hasTeamsSync()) {
            $this->createEvent($interview);
            return;
        }

        $mailbox = $this->organizerMailbox();

        $response = $this->client()->patch(
            "{$this->baseUrl}/users/{$mailbox}/events/{$interview->ms_graph_event_id}",
            $this->eventPayload($interview)
        );

        if (!$response->successful()) {
            throw $this->failure('updateEvent', $response, $mailbox, ['interview_id' => $interview->id]);
        }

        $interview->forceFill([
            'teams_meeting_url' => $response->json('onlineMeeting.joinUrl') ?: $interview->teams_meeting_url,
        ])->save();
    }

    /** Hapus event Graph terkait (mis. saat interview dibatalkan). 404 dianggap sukses. */
    public function deleteEvent(Interview $interview): void
    {
        if (!$interview->hasTeamsSync()) {
            return;
        }

        $mailbox = $this->organizerMailbox();

        $response = $this->client()->delete(
            "{$this->baseUrl}/users/{$mailbox}/events/{$interview->ms_graph_event_id}"
        );

        if (!$response->successful() && $response->status() !== 404) {
            throw $this->failure('deleteEvent', $response, $mailbox, ['interview_id' => $interview->id]);
        }

        $interview->forceFill([
            'ms_graph_event_id' => null,
            'teams_meeting_url' => null,
        ])->save();
    }

    /**
     * Every event on the organizer's Teams calendar between two moments.
     * Times come back in the recruitment timezone, as wall-clock values — the
     * same convention interviews are stored in.
     *
     * Unlike the write methods this one throws on failure instead of returning
     * an empty list, so the caller can tell "no events" from "could not load".
     *
     * @return array<int, array{id: string, subject: string, start: Carbon, end: Carbon, join_url: ?string, location: ?string, organizer: ?string, attendees: string[]}>
     */
    public function listBetween(Carbon $from, Carbon $to): array
    {
        $mailbox = $this->organizerMailbox();

        $response = $this->client()
            ->withHeaders(['Prefer' => 'outlook.timezone="' . $this->timezone() . '"'])
            ->get("{$this->baseUrl}/users/{$mailbox}/calendarView", [
                'startDateTime' => $from->format('Y-m-d\TH:i:s'),
                'endDateTime'   => $to->format('Y-m-d\TH:i:s'),
                '$orderby'      => 'start/dateTime',
                '$select'       => 'id,subject,start,end,location,organizer,attendees,onlineMeeting,isCancelled',
                '$top'          => 250,
            ]);

        if (!$response->successful()) {
            throw $this->failure('calendarView', $response, $mailbox, []);
        }

        return collect($response->json('value', []))
            ->reject(fn ($event) => $event['isCancelled'] ?? false)
            ->map(fn ($event) => [
                'id'        => $event['id'],
                'subject'   => $event['subject'] ?: '(No subject)',
                'start'     => Carbon::parse($event['start']['dateTime']),
                'end'       => Carbon::parse($event['end']['dateTime']),
                'join_url'  => $event['onlineMeeting']['joinUrl'] ?? null,
                'location'  => $event['location']['displayName'] ?? null,
                'organizer' => $event['organizer']['emailAddress']['name'] ?? null,
                'attendees' => collect($event['attendees'] ?? [])
                    ->map(fn ($a) => $a['emailAddress']['name'] ?? $a['emailAddress']['address'] ?? null)
                    ->filter()->values()->all(),
            ])
            ->values()
            ->all();
    }
}
