<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Jaring pengaman: sinkronkan kolom ringkasan last_customer_reply_at /
     * last_agent_reply_at / last_internal_note_at / last_message_at di tabel
     * `ticket` langsung dari database setiap ada row baru di `ticket_message`.
     *
     * Sebelumnya kolom-kolom ini hanya di-update lewat kode aplikasi (tersebar
     * di 9+ controller/service), sehingga jalur mana pun yang lupa menulis
     * update tersebut — atau proses eksternal (mis. Jarvies) yang insert
     * langsung ke tabel ini tanpa lewat API — membuat warna unread di list
     * ticket salah (tetap kuning padahal ada balasan customer terbaru).
     * Trigger ini menjamin konsistensi terlepas dari siapa/apa yang insert.
     */
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ticket_message_sync_after_insert');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_ticket_message_sync_after_insert
            AFTER INSERT ON ticket_message
            FOR EACH ROW
            BEGIN
              IF NEW.is_internal_note = 1 THEN
                UPDATE ticket SET
                  last_internal_note_at = NEW.created_at,
                  last_internal_note_sender_id = NEW.sender_id,
                  last_message_at = GREATEST(COALESCE(last_message_at, NEW.created_at), NEW.created_at)
                WHERE ticket_id = NEW.ticket_id;
              ELSEIF NEW.sender_type = 'customer' THEN
                UPDATE ticket SET
                  last_customer_reply_at = NEW.created_at,
                  last_message_at = GREATEST(COALESCE(last_message_at, NEW.created_at), NEW.created_at)
                WHERE ticket_id = NEW.ticket_id;
              ELSEIF NEW.sender_type = 'employee' THEN
                UPDATE ticket SET
                  last_agent_reply_at = NEW.created_at,
                  last_message_at = GREATEST(COALESCE(last_message_at, NEW.created_at), NEW.created_at)
                WHERE ticket_id = NEW.ticket_id;
              END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ticket_message_sync_after_insert');
    }
};
