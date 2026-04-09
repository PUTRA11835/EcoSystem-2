<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * TicketNumberService
 *
 * Satu-satunya tempat yang menghasilkan ticket_number baru.
 * Menggunakan SELECT ... FOR UPDATE di dalam DB::transaction agar
 * dua request bersamaan tidak bisa membaca angka terakhir yang sama
 * dan menghasilkan ticket_number duplikat.
 *
 * Format: YYMM + 4-digit sequence (global per-tahun, bukan per-bulan)
 * Contoh: 2604 → bulan April 2026, nomor urut 1 → "26040001"
 *
 * PENTING: metode generate() harus selalu dipanggil di dalam DB::transaction().
 * Jika sudah ada transaksi berjalan (mis. StagingTicketService::approve()),
 * Laravel secara otomatis bergabung dengan transaksi tersebut (savepoint).
 */
class TicketNumberService
{
    /**
     * Hasilkan ticket_number unik berikutnya.
     *
     * @return string  contoh: "26040001"
     */
    public function generate(): string
    {
        return DB::transaction(function () {
            $year      = date('y');   // "26"
            $yearMonth = date('ym');  // "2604"

            // Kunci baris terakhir agar concurrent transaction tidak bisa baca
            // sampai transaksi ini selesai (SELECT ... FOR UPDATE).
            $lastNumber = DB::table('ticket')
                ->where('ticket_number', 'like', $year . '%')
                ->whereRaw("ticket_number NOT LIKE '%-%'")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(ticket_number, 5, 4) AS UNSIGNED) DESC')
                ->value('ticket_number');

            $next = $lastNumber ? ((int) substr($lastNumber, -4)) + 1 : 1;

            return $yearMonth . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }
}
