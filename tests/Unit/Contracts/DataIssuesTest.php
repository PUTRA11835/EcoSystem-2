<?php

namespace Tests\Unit\Contracts;

use App\Exceptions\ContractDataException;
use App\Support\Contracts\DataIssues;
use PHPUnit\Framework\TestCase;

class DataIssuesTest extends TestCase
{
    private const URL = 'https://app.test/general/external/consultants/7/edit';

    public function test_missing_items_become_links_to_the_right_tab(): void
    {
        $issues = DataIssues::fromMissing([
            ['key' => 'nik', 'label' => 'NIK', 'section' => 'identification'],
            ['key' => 'street', 'label' => 'Domicile address', 'section' => 'address'],
            ['key' => 'x', 'label' => 'Something', 'section' => 'basic_data'],
        ], self::URL);

        $this->assertSame(self::URL . '?section=identification&field=nik', $issues[0]['url']);
        $this->assertSame('Identification', $issues[0]['tab_label']);
        $this->assertSame(self::URL . '?section=address', $issues[1]['url']);      // tanpa kolom bernama: hanya tab
        $this->assertSame('basic-data', $issues[2]['tab']);                          // id tab memakai tanda hubung
        $this->assertSame('Basic Data', $issues[2]['tab_label']);
    }

    public function test_document_gaps_flag_only_the_blank_values(): void
    {
        $gaps = DataIssues::documentGaps(['alamat' => '', 'npwp' => '123', 'nomor_identitas' => null, 'tempat_tanggal_lahir' => ' '], self::URL);

        $this->assertSame(['alamat', 'nomor_identitas', 'tempat_tanggal_lahir'], array_column($gaps, 'key'));
        $this->assertStringContainsString('address', $gaps[0]['url']);
        $this->assertNotEmpty($gaps[0]['why']);
    }

    public function test_unknown_keys_are_ignored(): void
    {
        $this->assertSame([], DataIssues::documentGaps(['lainnya' => ''], self::URL));
    }

    public function test_exception_is_a_domain_exception_that_carries_the_items(): void
    {
        $e = new ContractDataException('pesan', [['key' => 'nik']]);

        $this->assertInstanceOf(\DomainException::class, $e);   // pemanggil lama yang menangkap DomainException tetap bekerja
        $this->assertSame('pesan', $e->getMessage());
        $this->assertSame('nik', $e->issues[0]['key']);
    }
}
