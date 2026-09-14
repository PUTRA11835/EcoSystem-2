<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin untuk tombol "Ask AI (Research)" di panel kanan halaman detail
 * tiket (lihat ticket/show.blade.php, AiResearchController::openForTicket()).
 *
 * Sesuai aturan baku proyek, slug baru lahir aktif HANYA untuk EC Administrator
 * dan mati untuk seluruh role lain — pemilik sistem yang memutuskan role mana
 * lagi boleh memakainya lewat Control Center > Menu Access. Tombol ini memicu
 * panggilan AI berbayar (AI Research), jadi justru tidak boleh menyebar
 * diam-diam — sama alasannya dengan ui.ticket.btn-ai-summarize.
 *
 * Slug ini BUKAN pengganti gerbang TicketTeamAccess::isLeadOrMember() yang
 * sudah ada di AiResearchController::openForTicket() dan ticket/show.blade.php
 * — keduanya jalan BERLAPIS: slug ini menentukan role mana yang BOLEH memakai
 * fitur ini sama sekali (kontrol biaya/governance, diatur admin), sedangkan
 * isLeadOrMember() tetap membatasi KE TIKET MANA (cuma tiket yang orang itu
 * benar-benar tangani sebagai lead/member — atau EC Administrator, yang selalu
 * lolos kedua gerbang berkat grant admin-only di atas).
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('tickets.inbox', [
            'ui.ticket.btn-ai-research' => 'Ask AI (Research) on Ticket',
        ], 28);
    }

    public function down(): void
    {
        MenuRegistrar::remove(['ui.ticket.btn-ai-research']);
    }
};
