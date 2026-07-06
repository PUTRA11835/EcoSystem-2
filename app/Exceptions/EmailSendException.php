<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Dilempar saat email ticket GAGAL dikirim via Microsoft Graph.
 *
 * Berbeda dari RuntimeException generik: pesan (getMessage) pada exception ini
 * sudah berupa ALASAN yang ramah pengguna (Bahasa Inggris), siap ditampilkan
 * di bubble chat — mis. "The destination email address is invalid or was not found."
 *
 * Detail teknis mentah dari Graph disimpan di $rawDetail untuk log/debug.
 */
class EmailSendException extends RuntimeException
{
    /** Detail error teknis mentah (body response Graph) untuk log. */
    public readonly ?string $rawDetail;

    /** Alamat tujuan yang GAGAL (mis. invalid pra-kirim) — untuk ditandai di bubble. */
    public readonly array $failedRecipients;

    public function __construct(string $friendlyReason, ?string $rawDetail = null, ?Throwable $previous = null, array $failedRecipients = [])
    {
        parent::__construct($friendlyReason, 0, $previous);
        $this->rawDetail        = $rawDetail;
        $this->failedRecipients = $failedRecipients;
    }

    /** Ambil daftar alamat gagal dari sembarang Throwable (kosong bila bukan EmailSendException). */
    public static function failedRecipientsFrom(Throwable $e): array
    {
        return $e instanceof self ? $e->failedRecipients : [];
    }

    /**
     * Ambil alasan ramah pengguna dari sembarang Throwable.
     * Jika bukan EmailSendException, kembalikan pesan generik.
     */
    public static function reasonFrom(Throwable $e): string
    {
        return $e instanceof self
            ? $e->getMessage()
            : 'The email could not be delivered to the customer.';
    }
}
