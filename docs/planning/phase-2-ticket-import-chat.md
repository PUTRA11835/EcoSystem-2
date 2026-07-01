# Fase 2 — Konsistensi Subject Email `[JARVIES] #XXXX : description`

> **Konteks (yang SUDAH jalan, jangan diubah):**
> - Fitur **import tiket dari sistem lama** di EcoSystem sudah mengirim **email anchor** dengan subject `[JARVIES] #XXXX : description`.
> - Karena subject sudah terbentuk, customer di Jarvies bisa langsung chat atas tiket import, dan view EcoSystem otomatis menyesuaikan. **OK.**
>
> **Masalah sebenarnya (yang harus diperbaiki):**
> Ketika **customer mengirim chat duluan dari Jarvies**, email outbound yang dibuat Jarvies memakai subject lama `Ticket #XXXX: desc` — **bukan** `[JARVIES] #XXXX : description`. Akibatnya subject tidak konsisten tergantung siapa yang memulai, dan threading bisa pecah.
>
> **Goal:** Subject email selalu `[JARVIES] #XXXX : description` **dari arah manapun** (EcoSystem maupun Jarvies, siapa pun yang chat duluan).

## Root cause (sudah dikonfirmasi di kode)

`JARVIES/app/Http/Controllers/TicketMessageController.php` → `sendEmailReply()` **baris 229**:

```php
// SEKARANG (salah / tidak konsisten):
$subject = 'Ticket #' . $ticket->ticket_number . ': ' . ($ticket->description ? substr($ticket->description, 0, 80) : 'Update');
```

Format EcoSystem (acuan yang benar — `EcoSystem/app/Http/Controllers/TicketMessageController.php`):

```php
$subject = '[JARVIES] #' . $ticket->ticket_number . ' : ' . mb_substr($ticket->description ?? '', 0, 80);
```

Perhatikan beda detail: prefix `[JARVIES] `, spasi mengelilingi titik dua (` : `), dan `mb_substr` (bukan `substr`).

## Lingkup file
- **`JARVIES/app/Http/Controllers/TicketMessageController.php`** — `sendEmailReply()` baris 229: ganti subject jadi format `[JARVIES] #XXXX : description` identik EcoSystem.
- **`JARVIES/app/Http/Controllers/TicketController.php`** — cek pembuatan tiket/staging dari Jarvies (`store()` pakai `[No Reply] [Pending Validation] ...`). Pastikan tidak ada jalur lain yang menghasilkan subject berbeda untuk tiket yang sudah jadi `[JARVIES]`. (staging baru beda konteks — fokus utama tetap reply tiket existing.)
- (cek) `JARVIES/app/Http/Controllers/EmailController.php` → `sendTicketReply()` — pastikan tidak menambah prefix lain (mis. auto `Re:`) yang merusak match `[JARVIES]`.

## Subtask (checklist)
- [x] Ganti subject di `sendEmailReply()` Jarvies → `'[JARVIES] #' . $ticket->ticket_number . ' : ' . mb_substr($ticket->description ?? '', 0, 80)`.
- [x] Samakan persis dengan EcoSystem (spasi ` : `, `mb_substr`, fallback bila description kosong).
- [x] Pastikan `sendTicketReply()` / EmailController tidak menambah prefix yang membuat subject menyimpang dari `[JARVIES]`. **Hasil cek:** kedua project menambah `Re: ` dengan pola identik (`stripos($subject,'re:') !== 0`), dan hanya di jalur fallback `sendMail`; jalur normal `createReply` mewarisi subject asli dari Graph. `subjectTopicMatches` melepas prefix `Re:`/`Fwd:` saat membandingkan → `Re: [JARVIES] ...` tetap match `[JARVIES] ...`. Tidak perlu diubah.
- [x] Verifikasi threading tetap nyambung: `inReplyTo` = `email_message_id` pesan email terakhir tetap dipakai (jangan dihapus). **Tidak disentuh.**
- [ ] (Opsional) Sentralisasi format subject ke satu helper agar EcoSystem & Jarvies tak gampang divergen lagi. *(belum — masih duplikasi string di tiap controller)*

## Implementasi (2026-06-30)
- **File diubah:** `JARVIES/app/Http/Controllers/TicketMessageController.php` → `sendEmailReply()` (sekitar baris 229). Subject lama `'Ticket #' . $ticket->ticket_number . ': ' . substr(...)` diganti jadi `'[JARVIES] #' . $ticket->ticket_number . ' : ' . mb_substr($ticket->description ?? '', 0, 80)` — identik EcoSystem.
- **`php -l`**: no syntax errors.
- **Di luar lingkup (sengaja tidak diubah):** `JARVIES/TicketController` baris ~3847/3919 mengirim email notifikasi status `Ticket #XXXX - Closed` / `- Cancelled` lewat `sendStandaloneEmail` — itu notifikasi standalone (format ` - `, bukan reply thread `: `), bukan jalur balasan percakapan, jadi tidak termasuk lingkup Fase 2. Catat sebagai kandidat follow-up bila ingin notifikasi ini ikut nempel di thread.

## Koreksi root cause (2026-06-30, setelah testing local)

Testing menunjukkan email relay pesan customer **masih** subject `Ticket #XXXX: desc`. Ternyata ada **dua** jalur, dan jalur yang dipakai saat customer chat duluan dari Jarvies **bukan** `sendEmailReply`:

- `TicketMessageController::sendEmailReply()` → hanya dipanggil untuk reply **employee** (`senderType === 'employee'`). Sudah diperbaiki (lihat di atas).
- **`JARVIES/app/Services/GraphRelayService.php` → `sendRelayEmail()` baris ~407** → inilah yang merelay **pesan customer** ("[Message from … via Jarvies]") ke email. Subject lama `'Ticket #' . (...) . ': ' . mb_substr(...)` diganti jadi `'[JARVIES] #' . ($ticket->ticket_number ?? $ticket->ticket_id) . ' : ' . mb_substr($ticket->description ?? '', 0, 80)`.

Catatan threading: di `GraphRelayService`, subject hanya dipakai pada **`createNewDraft`** (saat belum ada `email_thread_id` — yaitu customer chat duluan). Pada **`createReplyDraft`** (thread sudah ada) subject **sengaja tidak di-PATCH** agar Exchange tidak generate conversationId baru — Graph mewarisi `Re: {original}`. Jadi perubahan ini hanya memengaruhi anchor thread baru, threading existing aman.

> **PENTING saat retest:** thread/email yang sudah terlanjur dibuat dengan subject lama akan **tetap** memakai subject lama (Graph mewarisinya pada reply). Uji dengan **tiket baru** di mana customer mengirim chat **pertama** dari Jarvies → email anchor harus muncul `[JARVIES] #XXXX : desc`.

## Status: ✅ SELESAI 2026-06-30 (revisi: fix utama di `GraphRelayService::sendRelayEmail`)

## Acceptance Criteria
1. Customer chat duluan dari Jarvies → email outbound subject = `[JARVIES] #XXXX : description` (identik EcoSystem).
2. EcoSystem chat duluan / anchor import → subject tetap `[JARVIES] #XXXX : description` (tidak berubah, sudah benar).
3. Balasan dua arah (email ↔ Jarvies) tetap berada di satu thread, subject konsisten.
4. Tidak ada lagi subject `Ticket #XXXX: ...` versi lama.

## Catatan / risiko (dari memory project)
- **Threading Outlook/Exchange**: hati-hati PATCH subject reset Thread-Index (memory `email-threading-outlook-jun2026`); perubahan ini justru menyamakan subject sehingga `subjectTopicMatches` lebih konsisten.
- **Jangan ubah** logika `inReplyTo` / anchor import yang sudah jalan — hanya samakan string subject.
- Pastikan tidak ada double-prefix (`Re: [JARVIES] ...` vs `[JARVIES] ...`) bila EmailController menambah `Re:`.

---

## KICKOFF PROMPT (tempel di room chat Fase 2)

```
Lanjutkan Fase 2 dari docs/planning/phase-2-ticket-import-chat.md.

Konteks: EcoSystem (cwd) + JARVIES di D:\Magang\PT Eclectic Consulting Yogyakarta\Project\JARVIES-main, berbagi DB & auth_users. Email via Microsoft Graph.

Yang SUDAH jalan (jangan diubah): import tiket di EcoSystem sudah kirim anchor email subject "[JARVIES] #XXXX : description", customer bisa chat di Jarvies & view EcoSystem nyesuaikan.

Bug yang diperbaiki: saat customer chat DULUAN dari Jarvies, JARVIES/app/Http/Controllers/TicketMessageController.php sendEmailReply() baris ~229 memakai subject lama "Ticket #XXXX: desc". Ganti agar identik EcoSystem: "[JARVIES] #" . $ticket->ticket_number . " : " . mb_substr($ticket->description ?? "", 0, 80). Pastikan EmailController->sendTicketReply tidak menambah prefix yang merusak match, dan inReplyTo/threading existing tetap dipakai. Tujuan: subject konsisten "[JARVIES] #XXXX : description" dari arah manapun.

Baca dulu file planning + TicketMessageController kedua project, lalu implement.
```
