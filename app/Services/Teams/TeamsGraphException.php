<?php

namespace App\Services\Teams;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Kegagalan panggilan Graph, dengan status dan body errornya dibawa utuh.
 *
 * Body-nya sengaja tidak diringkas: kode error Graph itulah yang membedakan
 * masalah yang penanganannya berbeda jauh — 403 Authorization_RequestDenied
 * (permission belum di-grant) vs 404 (chat sudah dihapus, sync-nya harus
 * dimatikan) vs 429 (dipanggil terlalu sering). Meringkasnya jadi "Graph gagal"
 * membuat ketiganya tidak bisa dibedakan saat kejadian.
 */
class TeamsGraphException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $graphCode = null,
        public readonly ?string $body = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(string $method, string $path, Response $response): self
    {
        $body = $response->body();
        $code = $response->json('error.code');
        $msg  = $response->json('error.message');

        return new self(
            sprintf(
                'Graph %s %s gagal: HTTP %d%s%s',
                $method,
                $path,
                $response->status(),
                $code ? " [{$code}]" : '',
                $msg ? " {$msg}" : ''
            ),
            $response->status(),
            is_string($code) ? $code : null,
            $body,
        );
    }

    /** Chat sudah tidak ada / tidak bisa diakses — sinkronnya percuma diteruskan. */
    public function isGone(): bool
    {
        return in_array($this->status, [403, 404], true);
    }

    /** Dipanggil terlalu sering — putaran berikutnya saja, jangan hitung sebagai gagal. */
    public function isThrottled(): bool
    {
        return $this->status === 429;
    }
}
