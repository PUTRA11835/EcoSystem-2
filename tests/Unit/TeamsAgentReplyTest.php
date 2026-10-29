<?php

namespace Tests\Unit;

use App\Models\TicketMessage;
use App\Services\Teams\TeamsOutboxService;
use Tests\TestCase;

/**
 * Penyaring queueAgentReply(): hanya balasan karyawan ke customer yang emailnya
 * tidak gagal yang boleh diteruskan ke group chat Teams. Semua kasus di sini
 * berhenti sebelum menyentuh database.
 */
class TeamsAgentReplyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.teams_sync.enabled'  => true,
            'services.teams_sync.outbound' => true,
        ]);
    }

    private function reply(array $attributes = []): TicketMessage
    {
        return (new TicketMessage())->forceFill($attributes + [
            'ticket_id'        => 1,
            'sender_type'      => 'employee',
            'sender_name'      => 'Rina Pratiwi',
            'message_html'     => '<p>Sudah kami perbaiki, silakan dicoba lagi.</p>',
            'is_internal_note' => false,
            'is_deleted'       => false,
            'email_status'     => null,
        ]);
    }

    public function test_internal_note_tidak_lewat_jalur_balasan(): void
    {
        $this->assertNull(app(TeamsOutboxService::class)->queueAgentReply($this->reply(['is_internal_note' => true])));
    }

    public function test_pesan_customer_tidak_lewat_jalur_balasan(): void
    {
        $this->assertNull(app(TeamsOutboxService::class)->queueAgentReply($this->reply(['sender_type' => 'customer'])));
    }

    public function test_pesan_sistem_tidak_diteruskan(): void
    {
        $this->assertNull(app(TeamsOutboxService::class)->queueAgentReply($this->reply(['sender_type' => 'system'])));
    }

    public function test_balasan_yang_dihapus_tidak_diteruskan(): void
    {
        $this->assertNull(app(TeamsOutboxService::class)->queueAgentReply($this->reply(['is_deleted' => true])));
    }

    public function test_balasan_yang_emailnya_gagal_tidak_diteruskan(): void
    {
        $this->assertNull(app(TeamsOutboxService::class)->queueAgentReply($this->reply(['email_status' => 'failed'])));
    }

    public function test_tidak_ada_yang_diteruskan_saat_sync_keluar_mati(): void
    {
        config(['services.teams_sync.outbound' => false]);

        $this->assertNull(app(TeamsOutboxService::class)->queueAgentReply($this->reply()));
    }
}
