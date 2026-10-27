<?php

namespace App\Exceptions;

/**
 * Kontrak ditolak karena DATA orangnya belum lengkap. Selain pesan teks (tetap dipakai pemanggil lama), membawa daftar butir
 * yang kurang beserta tab dan tautan penyelesaiannya (App\Support\Contracts\DataIssues) agar layar dapat menampilkan
 * "apa yang kurang" dan "klik untuk mengisinya".
 */
class ContractDataException extends \DomainException
{
    /** @param  array<int,array{key:string,label:string,tab:string,tab_label:string,url:?string,field:?string}>  $issues */
    public function __construct(string $message, public readonly array $issues = [])
    {
        parent::__construct($message);
    }
}
