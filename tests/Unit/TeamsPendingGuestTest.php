<?php

namespace Tests\Unit;

use App\Models\TeamsPendingGuestMember;
use App\Services\Teams\TeamsChatService;
use App\Services\Teams\TeamsGraphException;
use App\Services\Teams\TeamsGuestService;
use PHPUnit\Framework\TestCase;

/**
 * Guest yang belum dikenal Teams: pengenalan penolakan roster dan jeda
 * percobaan ulang (tiket 26100280, 26 Okt 2026).
 */
class TeamsPendingGuestTest extends TestCase
{
    /** Body persis dari Graph saat guest baru dimasukkan ke POST /chats. */
    private const ROSTER_BODY = '{"error":{"code":"Forbidden","message":"OperationFailed","innerError":{"code":"202","message":"DisablePartialSuccess-Create Thread: One or more members cannot be added to the thread roster"}}}';

    public function test_penolakan_roster_dikenali(): void
    {
        $e = new TeamsGraphException('x', 403, 'Forbidden', self::ROSTER_BODY);

        $this->assertTrue(TeamsChatService::isRosterRejection($e));
    }

    public function test_403_izin_bukan_penolakan_roster(): void
    {
        $e = new TeamsGraphException('x', 403, 'Authorization_RequestDenied', '{"error":{"code":"Authorization_RequestDenied"}}');

        $this->assertFalse(TeamsChatService::isRosterRejection($e));
    }

    public function test_status_lain_bukan_penolakan_roster(): void
    {
        $e = new TeamsGraphException('x', 400, 'BadRequest', self::ROSTER_BODY);

        $this->assertFalse(TeamsChatService::isRosterRejection($e));
    }

    public function test_tautan_chat_menyebut_tenant_dan_sama_dengan_bentuk_weburl_graph(): void
    {
        $this->assertSame(
            'https://teams.microsoft.com/l/chat/19%3Add28553fe8044daab6c221ce779512de%40thread.v2/0?tenantId=cbcc85ec-3c81-467c-9f7a-fda77b075c13',
            TeamsGuestService::chatDeepLink('19:dd28553fe8044daab6c221ce779512de@thread.v2', 'cbcc85ec-3c81-467c-9f7a-fda77b075c13')
        );
    }

    public function test_email_pertama_memuat_langkah_terima_undangan(): void
    {
        $html = TeamsGuestService::chatInviteHtml('26100280 - Tiket <A&B>', 'https://teams/l/chat/x', 'https://login.microsoftonline.com/redeem?a=1&b=2');

        $this->assertStringContainsString('Langkah 1', $html);
        $this->assertStringContainsString('href="https://login.microsoftonline.com/redeem?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('href="https://teams/l/chat/x"', $html);
        $this->assertStringContainsString('26100280 - Tiket &lt;A&amp;B&gt;', $html);
    }

    public function test_email_berikutnya_cukup_tautan_chat(): void
    {
        $html = TeamsGuestService::chatInviteHtml('26100280', 'https://teams/l/chat/x', null);

        $this->assertStringNotContainsString('Langkah 1', $html);
        $this->assertStringContainsString('href="https://teams/l/chat/x"', $html);
    }

    public function test_jeda_merenggang_lalu_tertahan_di_langkah_terakhir(): void
    {
        $this->assertSame(2, TeamsPendingGuestMember::backoffMinutes(0));
        $this->assertSame(3, TeamsPendingGuestMember::backoffMinutes(1));
        $this->assertSame(15, TeamsPendingGuestMember::backoffMinutes(4));
        $this->assertSame(480, TeamsPendingGuestMember::backoffMinutes(9));
        $this->assertSame(480, TeamsPendingGuestMember::backoffMinutes(50));
        $this->assertSame(2, TeamsPendingGuestMember::backoffMinutes(-1));
    }
}
