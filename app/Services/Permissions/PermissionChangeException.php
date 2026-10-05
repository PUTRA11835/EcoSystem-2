<?php

namespace App\Services\Permissions;

/** Kegagalan terduga saat mengubah izin role (validasi/pengaman) — dikonversi layanan menjadi hasil ['ok' => false, 'code' => …]. */
final class PermissionChangeException extends \RuntimeException
{
    /** @param array<int, array> $extra mis. daftar konflik */
    public function __construct(public readonly string $codeName, string $message, public readonly array $extra = [])
    {
        parent::__construct($message);
    }
}
