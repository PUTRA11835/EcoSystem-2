<?php

namespace Tests\Unit;

use App\Support\Initials;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InitialsTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_inisial(?string $name, string $expected): void
    {
        $this->assertSame($expected, Initials::make($name));
    }

    public static function cases(): array
    {
        return [
            'dua kata'              => ['System Administrator', 'SA'],
            'tiga kata'             => ['Said Akmal Rizqullah', 'SR'],
            'satu kata'             => ['Admin', 'AD'],
            'huruf kecil'           => ['budi santoso', 'BS'],
            'spasi berlebih'        => ["  Intan   Demo \n", 'ID'],
            'nama belakang strip'   => ['Nofiardini -', 'NO'],
            'tanda baca di depan'   => ["Nur'alif Nafilah", 'NN'],
            'huruf non-ASCII'       => ['Édouard Çelik', 'ÉÇ'],
            'kosong'                => ['', '?'],
            'null'                  => [null, '?'],
            'hanya simbol'          => ['- ..', '?'],
        ];
    }

    public function test_cadangan_khusus(): void
    {
        $this->assertSame('U', Initials::make('', 'U'));
    }
}
