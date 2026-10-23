<?php

namespace Tests\Unit;

use App\Services\Teams\TeamsFileService;
use App\Services\Teams\TeamsOutboxService;
use PHPUnit\Framework\TestCase;

/**
 * Perakitan pesan Graph untuk kartu berkas asli Teams
 * (docs/teams-sync-parity-design.md §6c).
 */
class TeamsNativeFileTest extends TestCase
{
    private const GUID = '2115455D-58E9-4045-8E2E-840C7B649192';

    private function file(string $guid = self::GUID, string $name = 'laporan.pdf'): array
    {
        return [
            'id'          => $guid,
            'contentType' => 'reference',
            'contentUrl'  => 'https://contoso-my.sharepoint.com/personal/support/Documents/' . $name,
            'name'        => $name,
        ];
    }

    public function test_guid_diambil_dari_etag(): void
    {
        $this->assertSame(self::GUID, TeamsFileService::attachmentGuid(['eTag' => '"{' . self::GUID . '},4"']));
    }

    public function test_guid_jatuh_ke_list_item_unique_id(): void
    {
        $item = ['eTag' => 'aBcD', 'sharepointIds' => ['listItemUniqueId' => strtolower(self::GUID)]];

        $this->assertSame(strtolower(self::GUID), TeamsFileService::attachmentGuid($item));
    }

    public function test_guid_null_kalau_tidak_ada_sumbernya(): void
    {
        $this->assertNull(TeamsFileService::attachmentGuid(['eTag' => 'bukan-guid']));
    }

    public function test_tiap_lampiran_punya_tag_sepadan_di_badan_pesan(): void
    {
        $items = [['attachment_id' => 7, 'name' => 'laporan.pdf', 'url' => 'https://eco.test/a/7?signature=x']];

        $message = TeamsOutboxService::graphMessage('Tio · EcoSystem<br>cek', $items, [7 => $this->file()]);

        $this->assertSame('html', $message['body']['contentType']);
        $this->assertSame(
            'Tio · EcoSystem<br>cek<attachment id="' . self::GUID . '"></attachment>',
            $message['body']['content']
        );
        $this->assertSame([$this->file()], $message['attachments']);
    }

    public function test_berkas_yang_gagal_disalin_tetap_ikut_sebagai_tautan(): void
    {
        $items = [
            ['attachment_id' => 7, 'name' => 'laporan.pdf', 'url' => 'https://eco.test/a/7?signature=x'],
            ['attachment_id' => 8, 'name' => 'a&b.txt', 'url' => 'https://eco.test/a/8?signature=y&z=1'],
        ];

        $message = TeamsOutboxService::graphMessage('badan', $items, [7 => $this->file()]);

        $this->assertCount(1, $message['attachments']);
        $this->assertStringContainsString(
            '<br><br><a href="https://eco.test/a/8?signature=y&amp;z=1">a&amp;b.txt</a>',
            $message['body']['content']
        );
        $this->assertStringEndsWith('<attachment id="' . self::GUID . '"></attachment>', $message['body']['content']);
    }

    public function test_null_kalau_tidak_ada_berkas_yang_berhasil_disalin(): void
    {
        $items = [['attachment_id' => 7, 'name' => 'laporan.pdf', 'url' => 'https://eco.test/a/7']];

        $this->assertNull(TeamsOutboxService::graphMessage('badan', $items, []));
    }
}
