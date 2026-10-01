<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentSetting;

/**
 * The calendar source Recruitment runs on, as chosen on the Settings tab
 * (`recruitment_settings.calendar_provider`).
 *
 * The interviews themselves always live in `recruitment_interviews` — that
 * table is the single source the Schedule calendar, the Dashboard and the
 * candidate page all read. With the internal source that is the whole
 * calendar. An OUTSIDE source adds a mirror on top: invitations to the
 * attendees, a generated meeting link, and the other events of the connected
 * account. external() returns that mirror, or null while there is none, and
 * is the only thing the rest of the module asks.
 *
 * Microsoft Outlook is the one outside source today. To add another, give
 * its service the same five methods MicrosoftGraphCalendarService has
 * (unavailableReason, createEvent, updateEvent, deleteEvent, listBetween),
 * add it to PROVIDERS and return it from external().
 */
class CalendarProviders
{
    public const INTERNAL  = 'internal';
    public const MICROSOFT = 'microsoft';

    /** key => label and description shown on the Settings tab. */
    public const PROVIDERS = [
        self::INTERNAL => [
            'label'       => 'This system\'s calendar',
            'description' => 'Interviews are kept and shown in EcoSystem only. Nothing is sent to Outlook or Teams; meeting links are entered by hand.',
        ],
        self::MICROSOFT => [
            'label'       => 'Microsoft Outlook / Teams calendar',
            'description' => 'Every interview is also created in the Outlook calendar of the connected account: attendees are invited by email, a Teams meeting link is generated, and the account\'s other events appear on the calendar here.',
        ],
    ];

    /** An unknown stored value counts as the internal calendar rather than failing a page. */
    public function currentKey(): string
    {
        $key = RecruitmentSetting::current()->calendar_provider;

        return isset(self::PROVIDERS[$key]) ? $key : self::INTERNAL;
    }

    /** The outside calendar interviews are mirrored to, or null while the internal calendar is the source. */
    public function external(): ?MicrosoftGraphCalendarService
    {
        return $this->currentKey() === self::MICROSOFT ? $this->microsoft() : null;
    }

    /** The Outlook connection regardless of the current source — for testing it before switching over. */
    public function microsoft(): MicrosoftGraphCalendarService
    {
        return app(MicrosoftGraphCalendarService::class);
    }
}
