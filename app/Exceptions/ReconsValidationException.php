<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kegagalan aturan bisnis saat menyimpan Recons (Delivery Support).
 *
 * Dilempar dari DALAM transaksi database supaya perubahan yang sudah terlanjur
 * ditulis ikut di-rollback, sementara respons ke pengguna tetap 422 dengan
 * pesan yang bisa langsung dibaca (bukan 500 generik).
 *
 * Pesannya sudah berupa kalimat siap tampil dalam Bahasa Inggris, konsisten
 * dengan bahasa yang dipakai view Delivery Support.
 */
class ReconsValidationException extends RuntimeException
{
    /** Respons JSON standar untuk endpoint AJAX Recons. */
    public function toResponse()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
