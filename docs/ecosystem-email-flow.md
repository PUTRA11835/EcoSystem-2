# EcoSystem + JARVIES — Dokumentasi Alur Email & Panduan Implementasi

> Versi: April 2026  
> Cakupan: Staging → Ticket → Reply → Attachment → CC → Threading → Gambar Inline  
> **Dokumen ini ditulis untuk dibaca oleh AI (Claude) yang bekerja di codebase JARVIES.**  
> Baca seluruh dokumen sebelum menulis kode apapun yang menyentuh email, staging, atau reply.

---

## Daftar Isi

**Bagian I — EcoSystem (Sisi Helpdesk)**
1. [Arsitektur & Komponen Utama](#1-arsitektur--komponen-utama)
2. [Alur Email Masuk: Staging → Ticket](#2-alur-email-masuk-staging--ticket)
3. [Email Threading: Cara Sistem Tidak Membuat Inbox Baru](#3-email-threading-cara-sistem-tidak-membuat-inbox-baru)
4. [Alur Reply dari Helpdesk ke Customer](#4-alur-reply-dari-helpdesk-ke-customer)
5. [Alur Reply dari Customer ke Helpdesk](#5-alur-reply-dari-customer-ke-helpdesk)
6. [CC Email: Propagasi dari Staging hingga Semua Reply](#6-cc-email-propagasi-dari-staging-hingga-semua-reply)
7. [Attachment File](#7-attachment-file)
8. [Gambar Inline (Embedded Image)](#8-gambar-inline-embedded-image)
9. [Proxy Attachment: Cara File Ditampilkan](#9-proxy-attachment-cara-file-ditampilkan)
10. [Penyimpanan Data di Database](#10-penyimpanan-data-di-database)
11. [Scheduler & Manual Trigger](#11-scheduler--manual-trigger)
12. [Error Handling & Fallback](#12-error-handling--fallback)
13. [Diagram Alur Lengkap](#13-diagram-alur-lengkap)

**Bagian II — JARVIES (Sisi Customer Portal) — Panduan Implementasi**
14. [Konteks Integrasi JARVIES ↔ EcoSystem](#14-konteks-integrasi-jarvies--ecosystem)
15. [Submit Tiket Baru dari Customer](#15-submit-tiket-baru-dari-customer)
16. [Customer Reply: Mengirim Pesan Balasan](#16-customer-reply-mengirim-pesan-balasan)
17. [CC Email di JARVIES](#17-cc-email-di-jarvies)
18. [Attachment & Gambar di JARVIES](#18-attachment--gambar-di-jarvies)
19. [OAuth Email Linking](#19-oauth-email-linking)
20. [Membaca Pesan & Attachment dari Database](#20-membaca-pesan--attachment-dari-database)
21. [Environment Variables yang Dibutuhkan](#21-environment-variables-yang-dibutuhkan)
22. [Kontrak API EcoSystem yang Dipanggil JARVIES](#22-kontrak-api-ecosystem-yang-dipanggil-jarvies)
23. [Aturan Kritis: Yang Boleh dan Tidak Boleh Dilakukan JARVIES](#23-aturan-kritis-yang-boleh-dan-tidak-boleh-dilakukan-jarvies)
24. [Checklist Implementasi Fitur Email di JARVIES](#24-checklist-implementasi-fitur-email-di-jarvies)

---

## 1. Arsitektur & Komponen Utama

### Platform Email

Sistem menggunakan **Microsoft 365** sebagai platform email. Semua pengiriman dan penerimaan email dilakukan melalui **Microsoft Graph API** menggunakan autentikasi `client_credentials` (tanpa user login).

```
MS_SENDER_EMAIL   → Email akun M365 yang digunakan sebagai inbox helpdesk
MS_TENANT_ID      → Azure AD Tenant
MS_CLIENT_ID      → App Registration Client ID
MS_CLIENT_SECRET  → Client Secret
GRAPH_BASE_URL    → https://graph.microsoft.com/v1.0
```

### Komponen Kode

| Komponen | File | Tugas |
|---|---|---|
| `EmailController` | `app/Http/Controllers/EmailController.php` | Semua interaksi Graph API (kirim, terima, attachment) |
| `TicketMessageController` | `app/Http/Controllers/TicketMessageController.php` | Simpan pesan ke DB, relay email, resolve email customer |
| `StagingTicketService` | `app/Services/StagingTicketService.php` | Lifecycle staging ticket: create, approve, reject |
| `AttachmentController` | `app/Http/Controllers/AttachmentController.php` | Proxy file dari Graph API ke browser |
| `ProcessEmailInbox` | `app/Console/Commands/ProcessEmailInbox.php` | Artisan command untuk proses inbox secara terjadwal |

### Tabel Database Utama

| Tabel | Keterangan |
|---|---|
| `staging_tickets` | Holding area email/form sebelum diapprove admin |
| `ticket` | Tiket resmi yang sudah diapprove |
| `ticket_message` | Semua pesan dalam tiket (reply, note, email masuk) |
| `ticket_attachment` | Metadata attachment (file path/Graph ID disimpan di sini) |
| `staging_attachments` | Attachment sementara selama di staging |

---

## 2. Alur Email Masuk: Staging → Ticket

### 2.1 Trigger: Bagaimana Email Diterima

Email masuk diproses oleh `EmailController::processInbox()`. Proses ini dipicu oleh dua cara:
- **Scheduler**: Artisan command `email:process-inbox` dijalankan secara berkala (cron)
- **Manual**: Tombol **"Fetch Email"** di halaman Validasi Tiket → `POST /api/email/process-inbox`

`processInbox()` melakukan **dua query ke Graph API sekaligus** untuk menghindari email terlewat:

```
Query 1: Semua pesan isRead = false (belum dibaca)  → top 50
Query 2: Pesan yang diterima dalam 60 menit terakhir → top 20
```

Kedua hasil digabung dan di-dedup berdasarkan `internetMessageId`.

> **Kenapa dua query?** Jika admin membaca email di Outlook sebelum sistem memprosesnya, `isRead` sudah `true` → query 1 melewatkannya. Query 2 (berdasarkan waktu) menangkap email ini sebagai jaring pengaman.

### 2.2 Routing Email Masuk: Ticket vs Staging

Untuk setiap email, sistem mencari tiket yang sudah ada dengan urutan prioritas:

```
1. ticket.email_thread_id = email.conversationId
   → Thread yang sama → ini adalah balasan ke tiket yang sudah ada

2. ticket_message.email_message_id = email.internetMessageId
   → Message ID yang sama sudah pernah disimpan → tiket ada

3. staging_tickets (status=approved, ticket_id != null).email_thread_id = email.conversationId
   → Staging sudah diapprove → email masuk ke tiket resmi via link staging
```

**Jika tiket DITEMUKAN:**
- Email disimpan sebagai `TicketMessage` baru langsung ke tiket (tidak ke staging)
- `ticket.last_customer_reply_at` dan `last_message_at` diperbarui
- Attachment diproses dan disimpan ke `ticket_attachment`

**Jika tiket TIDAK ditemukan:**
- Email disimpan ke `staging_tickets` (status = `unvalidated`)
- Menunggu admin Helpdesk untuk mereview dan approve/reject

### 2.3 Dedup: Mencegah Email Diproses Dua Kali

Sebelum membuat staging baru, sistem mengecek:

```php
// Cek staging yang UNVALIDATED dengan thread yang sama
StagingTicket::where('email_thread_id', $conversationId)
    ->where('status', 'unvalidated')
    ->first()
```

> **Penting**: Hanya staging dengan `status = 'unvalidated'` yang dianggap duplikat. Staging yang sudah `approved` atau `rejected` TIDAK dianggap duplikat — email baru pada thread yang sama akan diteruskan ke tiket resmi.

### 2.4 Quote Stripping: Membuang Teks Balasan

Saat email masuk adalah balasan (reply), body email biasanya mengandung kutipan pesan sebelumnya. Sistem membuang kutipan ini agar yang tersimpan hanya pesan baru.

`EmailController::extractReplyBody()` menghapus semua elemen berikut menggunakan DOM parsing:

```
<blockquote>                    → RFC standard (semua klien)
.gmail_quote                    → Gmail
.yahoo_quoted                   → Yahoo Mail
.moz-cite-prefix                → Thunderbird
#divRplyFwdMsg                  → Outlook Web
.OutlookMessageHeader           → Outlook Desktop
.x_gmail_quote                  → Gmail via Outlook
```

Hasilnya adalah **HTML murni** (bukan plain text) sehingga formatting, tabel, dan gambar inline tetap terjaga.

### 2.5 Dari Staging ke Ticket: Proses Approve

Ketika admin mengklik **Approve** di halaman validasi, `StagingTicketService::approve()` dijalankan dalam satu `DB::transaction`:

```
1. Generate ticket_number (format: YYMM + 4 digit, contoh: 26040001)
2. Buat record Ticket:
   - status           = 'open'
   - jarvies_status   = 'in process'
   - email_thread_id  = staging.email_thread_id  ← kunci threading!
   - cc_emails        = staging.cc_emails (normalized dari JSON string ke array)
   - channel          = staging.channel ('email' atau 'web')

3. Buat TicketMessage pertama:
   - Jika channel 'email': dari staging.email_body_html
   - Jika channel 'web': dari staging.body

4. Update staging:
   - status     = 'approved'
   - ticket_id  = ticket.ticket_id  ← foreign key ke tiket resmi

5. Pindahkan staging_attachments → ticket_attachment (lihat bagian 7)

6. Update ticket.last_message_at dan last_customer_reply_at
```

---

## 3. Email Threading: Cara Sistem Tidak Membuat Inbox Baru

### 3.1 Konsep Threading di Email

Email client (Outlook, Gmail) mengelompokkan email dalam satu "thread" atau "conversation" berdasarkan dua mekanisme:
- **`conversationId`** (M365 internal): ID unik per thread di server Microsoft
- **SMTP headers**: `In-Reply-To` dan `References` yang berisi `internetMessageId` pesan sebelumnya

Sistem EcoSystem menggunakan kedua mekanisme ini.

### 3.2 Field Threading di Database

```
ticket.email_thread_id      → conversationId M365 (diisi saat staging approved)
ticket_message.email_message_id → internetMessageId (RFC 2822 Message-ID) per pesan
```

### 3.3 Cara Sistem Membalas dalam Thread yang Sama

Saat helpdesk mengirim reply, `sendTicketReply()` menggunakan strategi tiga lapis:

**Lapisan 1 — createReply dari pesan terakhir (cara terbaik):**
```
1. Ambil email_message_id dari ticket_message terakhir di thread
2. Cari pesan ini di Graph (Inbox → SentItems → global search)
3. Panggil createReply → Graph secara otomatis set:
   - In-Reply-To header
   - References header
   - conversationId yang sama
   - Subject "Re: {original_subject}"
4. Patch toRecipients → customer email (bukan sendiri)
5. Patch body, ccRecipients
```

**Lapisan 2 — Fallback via conversationId:**
```
Jika pesan tidak ditemukan di mana-mana tapi ticket.email_thread_id ada:
→ Cari pesan manapun dalam thread tersebut (terbaru)
→ createReply dari pesan itu
→ Thread tetap tersambung
```

**Lapisan 3 — Draft baru dengan header manual:**
```
Jika conversationId tidak ada (tiket web yang belum pernah dibalas via email):
→ Buat draft baru
→ Set manual:
   In-Reply-To: {email_message_id pesan terakhir}
   References:  {email_message_id pesan terakhir}
→ Client email biasanya tetap mengelompokkan dalam thread yang sama
```

> **Catatan penting**: Subject email **tidak pernah diubah** saat createReply. Mengubah subject menyebabkan Exchange menghitung ulang `conversationId` → email keluar dari thread. Graph sudah otomatis menambahkan "Re: " via `createReply`.

### 3.4 Penyimpanan email_thread_id

Jika tiket belum punya `email_thread_id` (tiket dari web form yang baru pertama kali dibalas via email):

```php
if (!empty($result['conversation_id']) && empty($ticket->email_thread_id)) {
    $ticket->update(['email_thread_id' => $result['conversation_id']]);
}
```

Ini memastikan balasan customer berikutnya langsung masuk ke tiket, bukan ke staging baru.

---

## 4. Alur Reply dari Helpdesk ke Customer

### 4.1 Pendekatan "Email-First"

Helpdesk mengirim reply melalui form di `ticket/show`. Sistem menggunakan pendekatan **email-first**: email dikirim lebih dulu, setelah berhasil baru disimpan ke database.

```
Helpdesk submit form
       ↓
POST /api/tickets/{id}/messages
       ↓
sendEmailThenSave() dipanggil
       ↓
1. resolveCustomerEmail()     → dapatkan email tujuan
2. Bangun HTML email          → buildEmailHtml()
3. Kirim via sendTicketReply() → dapat graph_message_id, internet_message_id
4. Simpan TicketMessage ke DB dengan:
   - channel = 'email'
   - email_message_id = internet_message_id dari Sent Items
5. Simpan TicketAttachment dengan graph_message_id = Sent Items ID (bukan draft!)
```

> **Kenapa email-first?** Jika disimpan ke DB dulu, kemudian email gagal, kita punya record yang tidak pernah terkirim. Sebaliknya jika email berhasil, record DB pasti akurat.

### 4.2 Resolve Email Customer

`resolveCustomerEmail()` mencari email tujuan dengan tiga prioritas:

```
1. staging_tickets.submitted_by_email → email login individual customer (paling akurat)
2. ticket_message.sender_email pertama dari customer → email dari first message
3. customer.email → email perusahaan di master data (fallback terakhir)
```

### 4.3 Template Email

Setiap email dari helpdesk dibungkus dalam template HTML berbranding perusahaan:

```html
Header:  Background merah #8b1a1a — "PT Eclectic Consulting" + "Ticket #XXXX"
Body:    Konten reply dari Quill editor
Footer:  "Sent by {NickName} — PT Eclectic Consulting"
         "Ticket: #XXXX — {description}"
```

Tanda tangan agent (`-NickName`) ditambahkan secara otomatis di akhir body sebelum pembungkus template. `nick_name` diambil dari session → `employee_basic_data.nick_name` → kata pertama dari `name`.

### 4.4 Setelah Email Terkirim: Sinkronisasi Sent Items ID

Setelah draft dikirim, Graph API memindahkan draft ke Sent Items dengan **ID baru**. Draft ID lama tidak bisa digunakan untuk fetch attachment.

Sistem mencari Sent Items ID dengan cara:
```
Fetch 20 pesan terbaru dari SentItems
→ Cocokkan internetMessageId (PHP-level matching, bukan OData filter)
→ Retry 3x dengan jeda 1 detik (Graph butuh waktu untuk index)
→ Update graph_message_id di TicketAttachment
```

Jika Sent Items ID tidak ditemukan, `graph_message_id` menggunakan draft ID sebagai fallback. `AttachmentController` memiliki mekanisme self-healing untuk kasus ini (lihat bagian 9).

---

## 5. Alur Reply dari Customer ke Helpdesk

### 5.1 Via Email Langsung (Customer Balas Email)

Customer membalas email helpdesk → masuk ke inbox M365 → diproses oleh `processInbox()`:

```
Email customer masuk ke inbox M365
       ↓
processInbox() dipicu (scheduler atau manual)
       ↓
Routing: cari tiket via conversationId / email_thread_id
       ↓
Tiket DITEMUKAN → simpan sebagai TicketMessage
       ↓
Proses attachment jika hasAttachments=true atau ada cid: di body
       ↓
Tandai email sebagai isRead=true di Graph
       ↓
Update ticket.last_customer_reply_at
```

### 5.2 Via Jarvies Web Portal

Customer membalas melalui form di portal Jarvies:

```
Customer submit form di Jarvies
       ↓
POST /api/tickets/{id}/customer-reply   (dengan X-Api-Key)
       ↓
Simpan TicketMessage ke DB:
   - sender_type = 'customer'
   - channel     = request.channel ('web' atau 'email')
   - email_message_id = RFC 2822 Message-ID dari OAuth email Jarvies (jika skip_relay=true)
       ↓
Relay email (jika skip_relay=false):
   → sendCustomerReplyRelay()
   → Kirim dari helpdesk M365 ke customer (bukan dari customer sendiri)
   → Menjaga thread M365 tetap hidup
   → Simpan internet_message_id relay ke ticket_message
```

**Parameter `skip_relay`:**
- `false` (default): Jarvies tidak mengirim email sendiri → EcoSystem kirim relay dari helpdesk M365
- `true`: Jarvies sudah kirim email via OAuth customer → tidak perlu relay duplikat

### 5.3 Template Relay Customer Reply

Relay email customer menggunakan template berbeda untuk membedakan bahwa ini adalah "suara customer":

```html
Header:  Background merah #8b1a1a — "PT Eclectic Consulting"
Body:    Isi pesan customer (dari Quill)
Footer:  "Sent by {nama customer} via Jarvies Customer Portal"
         "Ticket: #XXXX — {description}"
```

---

## 6. CC Email: Propagasi dari Staging hingga Semua Reply

### 6.1 Capture CC Saat Email Masuk

Saat `processInbox()` memproses email masuk, CC recipients di-capture dari header email:

```php
$ccEmails = collect($msg['ccRecipients'])
    ->map(fn($r) => [
        'name'    => $r['emailAddress']['name'],
        'address' => $r['emailAddress']['address'],
    ])
    ->filter(fn($r) => !empty($r['address']))
    ->values()->all();
$ccJson = json_encode($ccEmails);  // disimpan sebagai JSON string
```

### 6.2 Penyimpanan CC

```
Email masuk → staging_tickets.cc_emails (JSON)
                      ↓ (saat approve)
             ticket.cc_emails (array via cast)
                      ↓ (setiap reply)
             ticket_message.cc_emails (per pesan)
```

`ticket.cc_emails` adalah **sumber terpercaya** (canonical source) untuk CC. Setiap reply selalu menggunakan CC dari field ini terlebih dahulu.

### 6.3 Normalisasi CC

Karena CC bisa tersimpan sebagai string JSON atau array PHP, ada normalisasi di setiap titik penggunaan:

```php
$ccList = [];
if (!empty($ticket->cc_emails)) {
    $ccList = is_array($ticket->cc_emails)
        ? $ticket->cc_emails
        : (json_decode($ticket->cc_emails, true) ?? []);
}
if (empty($ccList)) {
    // Fallback: cari dari ticket_message pertama yang punya cc_emails
    $firstMsgWithCc = TicketMessage::where('ticket_id', ...)
        ->whereNotNull('cc_emails')
        ->orderBy('created_at', 'asc')
        ->first();
}
```

### 6.4 Format CC ke Graph API

CC dikirim ke Graph API dalam format:

```json
[
    {"emailAddress": {"address": "john@company.com", "name": "John Doe"}},
    {"emailAddress": {"address": "manager@company.com"}}
]
```

Normalisasi di `sendTicketReply()` mendukung input berupa string email maupun array `{name, address}`.

---

## 7. Attachment File

### 7.1 Dua Sumber Attachment

**A. Attachment dari Email Masuk (Graph API)**

File attachment dari email customer tidak pernah diunduh ke server EcoSystem. Yang disimpan hanya **metadata**:

```
ticket_attachment:
  graph_message_id     → ID pesan di M365 (untuk fetch via proxy)
  graph_attachment_id  → ID attachment spesifik di M365
  file_name            → nama file asli
  mime_type            → tipe MIME
  file_size            → ukuran
  is_inline            → true jika gambar yang disisipkan dalam body
  content_id           → CID untuk inline images (lihat bagian 8)
```

**B. Attachment Upload dari Helpdesk**

Saat helpdesk upload file saat reply, file **tidak disimpan ke disk server**. Alurnya:

```
1. File dibaca ke memori → base64 encode
2. Upload ke Graph sebagai attachment di draft message
3. Simpan graph_attachment_id ke ticket_attachment
4. File hanya ada di Microsoft 365 (server Microsoft)
```

Untuk internal note dan tiket non-email: file disimpan lokal ke `storage/app/public/ticket-attachments/{ticketId}/`.

### 7.2 Staging Attachments → Ticket Attachments

Saat customer upload file melalui form Jarvies bersamaan dengan staging ticket:
- File disimpan ke `storage/app/public/staging-attachments/{stagingId}/`
- Metadata disimpan di `staging_attachments`

Saat staging di-approve oleh admin, `StagingTicketService::approve()` memindahkan semua record:

```php
StagingAttachment::where('staging_id', $staging->id)->get()
    → foreach → TicketAttachment::create([
        'ticket_id'   => $ticket->ticket_id,
        'message_id'  => $firstMessage->id,
        'file_path'   => $sa->file_path,      // path lokal tetap sama
        'link_url'    => '/storage/' . $sa->file_path,
        ...
    ])
```

### 7.3 Ukuran Maksimum File

- Attachment dari helpdesk via web form: **maksimum 10 MB per file**
- Attachment dari email: tidak dibatasi oleh EcoSystem (tergantung limit M365)
- Staging attachment dari customer via Jarvies: tidak disebutkan di validasi (tergantung Jarvies)

### 7.4 Attachment Type Detection

Berdasarkan MIME type, sistem menentukan `attachment_type`:

```
image/*                    → 'image'
application/pdf            → 'pdf'
*word*, *document*         → 'document'
*excel*, *spreadsheet*     → 'spreadsheet'
*zip*, *compressed*        → 'archive'
lainnya                    → 'file'
```

### 7.5 Reprocess Attachment

Jika attachment gagal tersimpan (misal: email diproses sebelum fitur diaktifkan), admin bisa trigger ulang:

```
POST /api/email/messages/{messageId}/reprocess-attachments
```

Ini mencari Graph message ID dari `email_message_id`, lalu menjalankan ulang `storeEmailAttachments()`.

---

## 8. Gambar Inline (Embedded Image)

Gambar inline adalah gambar yang disisipkan langsung dalam body email — bukan sebagai lampiran terpisah.

### 8.1 Gambar Inline dari Email Masuk (CID Reference)

Email client menggunakan format `cid:xxx` (Content-ID) di HTML untuk mereferensikan gambar yang dilampirkan sebagai attachment:

```html
<!-- Di body email -->
<img src="cid:image001.jpg@01XXXXXXXX">

<!-- Di attachment list: contentId = "image001.jpg@01XXXXXXXX" -->
```

`storeEmailAttachments()` menyimpan metadata setiap attachment termasuk `contentId`. Setelah semua attachment tersimpan, `replaceCidReferences()` mengganti `cid:xxx` di HTML dengan URL proxy:

```
cid:image001.jpg@01XX → /attachments/42
```

Sehingga ketika pesan ditampilkan di browser, gambar di-load melalui proxy EcoSystem (bukan langsung dari M365).

### 8.2 Gambar Inline dari Staging: Preview Sebelum Approve

Untuk menampilkan preview email di halaman validasi staging, `resolveInlineImagesAsDataUris()` mengganti `cid:xxx` dengan **base64 data URI** langsung:

```
cid:xxx → data:image/png;base64,iVBORw0KGg...
```

Ini tidak menyimpan apapun ke DB — hanya untuk tampilan preview sementara.

### 8.3 Gambar Inline dari Helpdesk: Upload via Quill

Saat helpdesk menyisipkan gambar di Quill editor (paste dari clipboard atau drag & drop), browser menyimpannya sebagai base64 di dalam HTML:

```html
<img src="data:image/png;base64,iVBORw0KGg...">
```

`extractBase64Images()` memproses HTML ini sebelum dikirim ke Graph:

```
1. Temukan semua <img src="data:image/...;base64,...">
2. Generate UUID sebagai CID: img-{uuid}@ecosys
3. Ganti src dengan cid:{CID}
4. Simpan ke array inlineImages: [{cid, mime, content, name}]
```

Setiap inline image kemudian dilampirkan ke draft email sebagai `fileAttachment` dengan `isInline=true` dan `contentId={CID}`. Email client menampilkannya inline di dalam body.

### 8.4 Edge Case: hasAttachments=false tapi Ada CID

Beberapa email client melaporkan `hasAttachments=false` meskipun ada inline images. Sistem mengatasinya dengan memeriksa dua kondisi:

```php
if ($hasAttachments || str_contains($bodyHtml, 'cid:')) {
    // Proses attachment
}
```

---

## 9. Proxy Attachment: Cara File Ditampilkan

### 9.1 Route Proxy

```
GET /attachments/{id}  → AttachmentController::show()
```

Route ini dilindungi middleware `CheckAuthToken` — hanya pengguna yang sudah login yang bisa mengakses file.

### 9.2 Alur Proxy

```
Request GET /attachments/42
       ↓
Ambil TicketAttachment id=42
       ↓
Cek: punya graph_message_id + graph_attachment_id?
   ├── Ya → fetch dari Graph API
   └── Tidak, tapi punya file_path → redirect ke /storage/{file_path}
       ↓
GET {GRAPH_BASE_URL}/users/{sender}/messages/{graph_message_id}/attachments/{graph_attachment_id}
       ↓
Response: stream binary ke browser
   - Content-Type: mime_type
   - Content-Disposition: inline (gambar/PDF) atau attachment (force download)
   - Cache-Control: private, max-age=3600
```

### 9.3 Self-Healing: Draft ID Menjadi Invalid

Masalah: Setelah draft email dikirim, Graph memindahkan pesan ke Sent Items dengan **ID baru**. Draft ID lama mengembalikan 404.

Solusi di `AttachmentController`:

```
Graph mengembalikan 404
       ↓
Cari ticket_message.email_message_id dari attachment
       ↓
Cari di Sent Items: fetch 20 pesan terbaru, cocokkan internetMessageId di PHP
       ↓
Fetch daftar attachment dari Sent Items, cocokkan berdasarkan file_name
       ↓
Update DB: simpan Sent Items ID yang benar ke attachment record
       ↓
Retry fetch dengan ID yang sudah diperbaiki
       ↓
Request berikutnya langsung sukses (ID sudah benar di DB)
```

---

## 10. Penyimpanan Data di Database

### 10.1 staging_tickets

| Kolom | Tipe | Keterangan |
|---|---|---|
| `channel` | enum | `'email'` atau `'web'` |
| `email_thread_id` | string | `conversationId` M365 — kunci threading |
| `email_message_id` | string | `internetMessageId` email masuk |
| `graph_message_id` | string | Graph internal message ID (untuk fetch attachment) |
| `submitted_by_email` | string | Email individual customer |
| `sender_name` | string | Nama pengirim |
| `email_body_html` | longText | Body email dalam HTML (sudah strip quoted text) |
| `has_attachments` | boolean | Flag dari Graph (mungkin false meski ada inline image) |
| `cc_emails` | JSON | Array `[{name, address}]` dari CC recipients |
| `body` | longText | Pesan dari form web (channel=web) |

### 10.2 ticket

| Kolom | Tipe | Keterangan |
|---|---|---|
| `email_thread_id` | string | Disalin dari staging saat approve |
| `cc_emails` | JSON | Sumber terpercaya CC untuk semua reply |

### 10.3 ticket_message

| Kolom | Tipe | Keterangan |
|---|---|---|
| `channel` | string | `'email'` atau `'web'` |
| `email_message_id` | string | RFC 2822 Message-ID — digunakan sebagai `In-Reply-To` |
| `message_html` | longText | Body HTML (dengan cid: sudah diganti URL proxy) |
| `cc_emails` | JSON | CC dari email ini (bisa berbeda per pesan) |
| `is_inline` | - | (di attachment) true = gambar dalam body |

### 10.4 ticket_attachment

| Kolom | Tipe | Keterangan |
|---|---|---|
| `graph_message_id` | string | ID pesan di Sent Items (untuk proxy) |
| `graph_attachment_id` | string | ID attachment spesifik di M365 |
| `content_id` | string | CID untuk inline images (referensi di HTML) |
| `is_inline` | boolean | true = ditampilkan dalam body, false = lampiran terpisah |
| `file_path` | string | Path lokal (hanya untuk attachment non-email lama) |

---

## 11. Scheduler & Manual Trigger

### 11.1 Artisan Command

```bash
php artisan email:process-inbox
```

Command ini memanggil `EmailController::processInbox()` dan menampilkan ringkasan:
```
Memproses inbox email...
Selesai. Diproses: 3, Dilewati: 1
```

### 11.2 Konfigurasi Cron (Disarankan)

Tambahkan ke `app/Console/Kernel.php`:

```php
$schedule->command('email:process-inbox')->everyMinute();
```

Atau melalui sistem cron OS:

```cron
* * * * * cd /path/to/project && php artisan schedule:run
```

### 11.3 Manual via UI

Di halaman **Incoming Ticket Validation** (`/staging-tickets`), terdapat tombol **"Fetch Email"** yang memanggil `POST /api/email/process-inbox`. Response menampilkan jumlah email yang diproses.

### 11.4 Manual via API (Debug/Admin)

```bash
# Lihat inbox terbaru (tanpa memproses)
GET /api/email/inbox

# Proses inbox sekarang
POST /api/email/process-inbox

# Reprocess attachment untuk pesan tertentu
POST /api/email/messages/{messageId}/reprocess-attachments
```

---

## 12. Error Handling & Fallback

### 12.1 Email Gagal Terkirim

Jika `sendEmailThenSave()` gagal (exception dari Graph API), method mengembalikan `null`. Controller kemudian fallback ke menyimpan pesan sebagai `channel='web'` tanpa pengiriman email:

```php
$message = $this->sendEmailThenSave(...);
if (!$message) {
    // Fallback: simpan tanpa email
    $message = TicketMessage::create([..., 'channel' => 'web']);
}
```

### 12.2 Attachment Gagal

Kegagalan dalam memproses atau menyimpan attachment **tidak membatalkan** penyimpanan pesan. Error dicatat ke log:

```
Log::warning('storeEmailAttachments: gagal ambil metadata attachment', ...)
```

### 12.3 Relay Email Customer Gagal

`sendCustomerReplyRelay()` ditandai sebagai **non-fatal**. Jika relay gagal, pesan customer tetap tersimpan di DB:

```php
} catch (\Exception $e) {
    Log::warning('sendCustomerReplyRelay: failed (non-fatal)', ...);
    // Tidak throw — pesan sudah tersimpan di DB
}
```

### 12.4 Sent Items ID Tidak Ditemukan

Jika Sent Items ID tidak bisa ditemukan setelah 3 retry, sistem menggunakan draft ID sebagai fallback. `AttachmentController` memiliki mekanisme self-healing untuk memperbaiki ID ini saat file pertama kali diakses.

---

## 13. Diagram Alur Lengkap

### 13.1 Email Masuk → Staging → Ticket

```
Customer kirim email
        │
        ▼
M365 Inbox (helpdesk@eclectic.co.id)
        │
        ▼ (setiap menit atau manual fetch)
processInbox()
        │
        ├─ Cari tiket via conversationId ──────────► Tiket ada?
        ├─ Cari tiket via email_message_id                │
        └─ Cari tiket via approved staging            Ya  │  Tidak
                                                      │   │
                    ┌─────────────────────────────────┘   │
                    ▼                                      ▼
           Simpan TicketMessage                  Simpan StagingTicket
           Proses attachment                     (status=unvalidated)
           Update timestamps                            │
                                                        ▼
                                              Admin review di /staging-tickets
                                                        │
                                           ┌────────────┴────────────┐
                                           ▼                         ▼
                                        Approve                    Reject
                                           │                         │
                                           ▼                         ▼
                                    Buat Ticket               staging.status
                                    TicketMessage             = 'rejected'
                                    Pindahkan attachments
                                    Salin cc_emails
                                    email_thread_id → ticket
```

### 13.2 Reply Helpdesk ke Customer

```
Helpdesk tulis reply di EcoSystem
        │
        ▼
POST /api/tickets/{id}/messages
        │
        ▼
resolveCustomerEmail()   ← staging.submitted_by_email / first_message.sender_email / customer.email
        │
        ▼
sendEmailThenSave()
        │
        ├─ Ambil email_message_id terakhir (inReplyTo)
        ├─ Ambil cc_emails dari ticket
        ├─ Bangun HTML template (buildEmailHtml)
        │
        ▼
sendTicketReply()
        │
        ├─ Cari pesan inReplyTo di Inbox → SentItems → global
        ├─ createReply (Graph) → set threading header otomatis
        ├─ Patch toRecipients, ccRecipients, body
        ├─ Lampirkan file (base64 ke Graph, bukan upload ke disk)
        ├─ Kirim draft
        ├─ Cari Sent Items ID (retry 3x)
        │
        ▼
Simpan TicketMessage (channel=email, email_message_id=internetMessageId)
Simpan TicketAttachment (graph_message_id=SentItems ID)
Update ticket.email_thread_id jika belum ada
```

### 13.3 Customer Balas via Email

```
Customer reply di Gmail/Outlook
        │
        ▼
M365 Inbox (email masuk dengan conversationId yang sama)
        │
        ▼
processInbox() → cocokkan conversationId ke ticket.email_thread_id
        │
        ▼
Simpan TicketMessage (channel=email, sender_type=customer)
Proses attachment (storeEmailAttachments)
Ganti cid: dengan /attachments/{id} di message_html
Update ticket.last_customer_reply_at
```

### 13.4 File Attachment Diakses

```
Browser request GET /attachments/42
        │
        ▼
AttachmentController::show()
        │
        ├─ Ada graph_message_id + graph_attachment_id?
        │         │ Ya
        │         ▼
        │    GET /users/{sender}/messages/{graph_msg_id}/attachments/{graph_att_id}
        │         │
        │         ├─ 200 OK → stream binary ke browser
        │         │
        │         └─ 404 → self-healing:
        │               Cari Sent Items ID via internetMessageId
        │               Update DB
        │               Retry fetch → stream ke browser
        │
        └─ Ada file_path? → redirect ke /storage/{file_path}
```

---

*Dokumen ini mencerminkan implementasi aktual per April 2026. Selalu sinkronkan dengan perubahan kode di `EmailController.php` dan `TicketMessageController.php`.*

---

---

# BAGIAN II — JARVIES: Panduan Implementasi Integrasi Email

> **Untuk AI yang mengerjakan codebase JARVIES:**  
> Bagian ini adalah panduan implementasi yang tepat dan presisi. Baca dan pahami Bagian I terlebih dahulu karena semua keputusan desain di JARVIES bergantung pada perilaku EcoSystem yang sudah dijelaskan di sana.

---

## 14. Konteks Integrasi JARVIES ↔ EcoSystem

### 14.1 Hubungan Dua Sistem

```
JARVIES (customer portal)          EcoSystem (helpdesk internal)
─────────────────────────          ─────────────────────────────
Laravel app terpisah               Laravel app terpisah
Autentikasi: session customer      Autentikasi: session employee
                    ↕
            Database MySQL SAMA (ecosystem2)
            Tabel: ticket, ticket_message, staging_tickets, dll.
            Baca/tulis langsung ke DB — bukan via REST API antarsistem
```

JARVIES dan EcoSystem **berbagi database yang sama**. Karena itu:
- JARVIES dapat langsung membaca tabel `ticket`, `ticket_message`, `ticket_attachment`, dll.
- Untuk operasi **tulis yang melibatkan email** (kirim reply, submit staging), JARVIES menggunakan dua jalur:
  1. **HTTP API ke EcoSystem** — untuk operasi yang butuh logika EcoSystem (approve staging, relay email dari helpdesk M365)
  2. **Tulis langsung ke DB** — untuk operasi yang pure JARVIES (simpan pesan ke `ticket_message`)

### 14.2 Autentikasi JARVIES ke EcoSystem API

Semua request dari JARVIES ke EcoSystem API menggunakan header:

```
X-Api-Key: {EXTERNAL_TICKET_API_KEY}
```

Nilai key ini disimpan di `.env` JARVIES sebagai `EXTERNAL_TICKET_API_KEY`. EcoSystem memvalidasi header ini di middleware `CheckApiKey`.

**JANGAN gunakan `Authorization: Bearer` untuk request JARVIES → EcoSystem API.** Bearer token adalah untuk autentikasi *session* EcoSystem internal, bukan untuk JARVIES.

### 14.3 Komponen JARVIES yang Relevan

| File | Tugas |
|---|---|
| `app/Http/Controllers/TicketController.php` | Submit staging, list tiket, show tiket |
| `app/Http/Controllers/TicketMessageController.php` | Kirim reply customer, baca pesan |
| `app/Services/StagingTicketService.php` | Buat staging ke DB lokal (fallback), baca staging |
| `app/Services/CustomerEmailService.php` | Kirim email dari akun OAuth customer (Gmail/Azure) |
| `app/Services/GraphRelayService.php` | Relay email via helpdesk M365 (client_credentials) |
| `app/Services/EcosystemApiService.php` | HTTP client ke EcoSystem API |
| `app/Http/Controllers/OAuthEmailController.php` | Linking akun email OAuth customer |
| `app/Models/CustomerEmailToken.php` | Token OAuth per customer |

---

## 15. Submit Tiket Baru dari Customer

### 15.1 Dua Jalur Pengiriman

Customer yang submit tiket baru di JARVIES melalui dua jalur yang berbeda tergantung apakah mereka punya **OAuth email terhubung** atau tidak.

```
Customer submit form
        │
        ├─ Ada OAuth email (Gmail/Azure)?
        │         │ YA
        │         ▼
        │   CustomerEmailService::sendEmail()
        │   Kirim email dari akun customer ke MS_SENDER_EMAIL
        │   EcoSystem processInbox() akan membuat staging otomatis
        │   (tidak ada API call ke EcoSystem)
        │
        └─ TIDAK ada OAuth email
                  ▼
            HTTP POST ke EcoSystem API
            POST /api/jarvies/staging-tickets
            Header: X-Api-Key
            Body: JSON (description, body, cc_emails, customer_id, dll.)
```

### 15.2 Jalur 1: OAuth Email (Cara Utama)

Jika customer punya email OAuth terhubung, **JANGAN langsung POST ke EcoSystem**. Kirim email dari akun customer ke `MS_SENDER_EMAIL`:

```php
// TicketController@store — customer dengan OAuth
$emailToken = CustomerEmailToken::where('customer_id', $user['id'])->first();
$hasEmail   = $emailToken && !($emailToken->isExpired() && !$emailToken->refresh_token);

if ($hasEmail) {
    $service  = new CustomerEmailService();
    $threadId = $service->sendEmail(
        $emailToken,
        env('MS_SENDER_EMAIL'),    // TO: inbox helpdesk
        $validated['description'], // subject = judul tiket
        $emailBody,                // plain text body
        null,                      // customerThreadId: null untuk tiket baru
        null,                      // inReplyTo: null untuk tiket baru
        [],                        // fileAttachments
        [],                        // inlineImages
        '',                        // htmlBody
        $ccEmails                  // CC recipients
    );
    // Staging akan dibuat otomatis oleh EcoSystem processInbox()
    return response()->json(['success' => true, 'staging' => true, 'email_sent' => true], 201);
}
```

**Kenapa cara ini lebih baik?**
- Email tercatat di inbox M365 helpdesk dengan sender asli customer
- EcoSystem membuat staging dari email yang masuk — flow lengkap termasuk `email_thread_id`
- Reply berikutnya dari helpdesk langsung masuk ke thread email customer

**Format subject**: Gunakan langsung `$validated['description']` sebagai subject email. EcoSystem akan menggunakan subject ini sebagai `description` staging ticket.

**Body email**: Gabungkan Quill HTML body + informasi tambahan (name, no_hp, module, client) ke dalam satu body teks. Append signature `-{nama customer}` di akhir.

### 15.3 Jalur 2: API ke EcoSystem (Fallback tanpa OAuth)

Jika tidak ada OAuth email, kirim ke EcoSystem via HTTP:

```php
// POST ke EcoSystem
$apiResponse = Http::withHeaders([
    'Accept'       => 'application/json',
    'Content-Type' => 'application/json',
    'X-Api-Key'    => env('EXTERNAL_TICKET_API_KEY'),
])->timeout(15)->post(env('ECOSYSTEM_API_URL') . '/staging-tickets', [
    'description'        => $validated['description'],
    'body'               => $bodyHtml,           // HTML, bukan plain text
    'ticket_priority'    => $validated['ticket_priority'] ?? 'Medium',
    'sender_name'        => $user['name'],        // nama individual (bukan perusahaan)
    'submitted_by_email' => $user['email'],       // email login customer
    'cc_emails'          => json_encode($ccEmails), // JSON string array email
    'customer_id'        => $user['id'],           // WAJIB: EcoSystem butuh ini
    'name'               => $validated['name'] ?? null,
    'no_hp'              => $validated['no_hp'] ?? null,
    'module'             => $validated['module'] ?? null,
    'client'             => $validated['client'] ?? null,
]);
```

**Field yang wajib dikirim:**

| Field | Tipe | Keterangan |
|---|---|---|
| `customer_id` | int | ID customer dari session JARVIES — WAJIB |
| `description` | string | Judul/subject tiket |
| `body` | string\|null | Body pesan dalam HTML |
| `ticket_priority` | string | `Very High`, `High`, `Medium`, `Low` |
| `submitted_by_email` | string\|null | Email login individual customer |
| `sender_name` | string\|null | Nama individual, bukan nama perusahaan |
| `cc_emails` | string\|null | JSON-encoded array email string: `'["a@b.com","c@d.com"]'` |

**Field opsional (tambahan dari form):**

| Field | Tipe |
|---|---|
| `name` | string — nama kontak |
| `no_hp` | string — nomor HP |
| `module` | string — modul SAP |
| `client` | string — nama client akhir |

### 15.4 Fallback: Simpan Lokal jika EcoSystem API Gagal

Jika HTTP ke EcoSystem gagal, jangan biarkan customer kehilangan submisinya. Simpan ke tabel `staging_tickets` lokal:

```php
// Fallback lokal
$stagingService = new StagingTicketService();
$staging = $stagingService->createFromWeb(
    array_merge($validated, ['cc_emails' => $ccEmailsJson]),
    $user['id'],
    $user['email'],
    $user['name']
);
```

> **Catatan penting**: Staging lokal ini **tidak akan diproses** oleh EcoSystem secara otomatis karena EcoSystem tidak poll JARVIES. Ini hanya safety net agar data tidak hilang. Tim perlu monitoring jika ada staging yang stuck.

---

## 16. Customer Reply: Mengirim Pesan Balasan

### 16.1 Tiga Skenario Reply

Customer membalas tiket yang sudah approved. Ada tiga skenario:

```
Customer klik Reply di JARVIES
        │
        ├─ Ada OAuth email + tiket punya email_thread_id?
        │         ▼
        │   CustomerEmailService::sendEmail() dengan customerThreadId
        │   + POST /api/tickets/{id}/customer-reply ke EcoSystem
        │     (skip_relay=true, channel='email', email_message_id=RFC2822MsgId)
        │
        ├─ Tidak ada OAuth email + tiket punya channel='email'?
        │         ▼
        │   POST /api/tickets/{id}/customer-reply ke EcoSystem
        │     (skip_relay=false) → EcoSystem kirim relay via M365
        │
        └─ Tiket channel='web' (tidak ada email sama sekali)?
                  ▼
            Simpan langsung ke ticket_message (channel='web')
            Tidak ada email yang dikirim
```

### 16.2 Skenario A: Customer Punya OAuth Email

Ini adalah flow utama dan paling direkomendasikan:

**Langkah 1 — Kirim email dari akun customer:**

```php
$service = new CustomerEmailService();
$sentMessageId = $service->sendEmail(
    $emailToken,
    env('MS_SENDER_EMAIL'),        // TO: inbox helpdesk
    'Re: Ticket #' . $ticket->ticket_number . ': ' . $ticket->description,
    strip_tags($messageBody),      // plain text fallback
    $ticket->email_thread_id,      // customerThreadId = conversationId M365
    $lastEmailMsgId,               // inReplyTo = email_message_id terakhir
    $fileAttachments,              // [{name, content (base64), mime}]
    $inlineImages,                 // [{name, content (base64), mime, cid}]
    $messageBody,                  // HTML dari Quill
    $ccEmails                      // array email CC
);
```

**Langkah 2 — Beritahu EcoSystem agar simpan ke DB:**

```php
Http::withHeaders([
    'X-Api-Key' => env('EXTERNAL_TICKET_API_KEY'),
])->post(env('ECOSYSTEM_API_URL') . '/tickets/' . $ticketId . '/customer-reply', [
    'message_body'     => $messageBody,            // HTML dari Quill
    'sender_name'      => $user['name'],
    'sender_email'     => $emailToken->provider_email,
    'customer_id'      => $user['id'],
    'skip_relay'       => true,                    // JARVIES sudah kirim email sendiri
    'channel'          => 'email',
    'email_message_id' => $sentMessageId,          // RFC 2822 Message-ID dari Gmail/Azure
]);
```

> **Kenapa `skip_relay = true`?**  
> Jika `false`, EcoSystem akan kirim relay email dari helpdesk M365 juga → customer mendapat email dua kali (dari Gmail-nya sendiri + dari helpdesk). Set `true` agar EcoSystem hanya menyimpan ke DB tanpa kirim relay.

> **Kenapa tetap POST ke EcoSystem?**  
> Agar pesan tersimpan di `ticket_message` — EcoSystem helpdesk perlu melihatnya di inbox tiket. Tanpa ini, JARVIES kirim email tapi tidak ada rekam di sistem.

### 16.3 Skenario B: Customer Tidak Punya OAuth Email (Relay)

```php
Http::withHeaders([
    'X-Api-Key' => env('EXTERNAL_TICKET_API_KEY'),
])->post(env('ECOSYSTEM_API_URL') . '/tickets/' . $ticketId . '/customer-reply', [
    'message_body' => $messageBody,
    'sender_name'  => $user['name'],
    'sender_email' => $user['email'],   // email dari profil customer
    'customer_id'  => $user['id'],
    'skip_relay'   => false,            // EcoSystem kirim relay dari M365
    'channel'      => 'web',
]);
```

EcoSystem akan memanggil `sendCustomerReplyRelay()` secara internal — yang mengirim email dari helpdesk M365 ke customer atas nama customer, mempertahankan email thread.

### 16.4 Skenario C: Tiket Channel Web (Tidak Ada Email)

Jika `ticket->channel === 'web'` dan tidak ada `email_thread_id`, cukup simpan pesan:

```php
Http::withHeaders([
    'X-Api-Key' => env('EXTERNAL_TICKET_API_KEY'),
])->post(env('ECOSYSTEM_API_URL') . '/tickets/' . $ticketId . '/customer-reply', [
    'message_body' => $messageBody,
    'sender_name'  => $user['name'],
    'sender_email' => $user['email'],
    'customer_id'  => $user['id'],
    'skip_relay'   => true,   // tidak ada email
    'channel'      => 'web',
]);
```

### 16.5 Menentukan `inReplyTo` untuk Threading

`inReplyTo` adalah `internetMessageId` (RFC 2822 Message-ID, format `<xxx@xxx>`) dari pesan email **terakhir** dalam thread. Ini yang digunakan sebagai `In-Reply-To` header di email yang dikirim — membuat email client mengelompokkan dalam thread yang sama.

```php
// Ambil email_message_id pesan email terakhir dari DB
$lastEmailMsg = TicketMessage::where('ticket_id', $ticketId)
    ->where('channel', 'email')
    ->whereNotNull('email_message_id')
    ->orderBy('created_at', 'desc')
    ->first();

$inReplyTo = $lastEmailMsg?->email_message_id;
// Contoh: "<CABxxxxxxxxxx@mail.gmail.com>"
```

**Jangan** menggunakan `ticket->email_thread_id` sebagai `inReplyTo`. Field itu adalah `conversationId` M365, bukan RFC 2822 Message-ID.

---

## 17. CC Email di JARVIES

### 17.1 Capture CC saat Submit Tiket

Form submit tiket memiliki field CC. Validasi sebagai array of email:

```php
'cc_emails'   => 'nullable|array|max:10',
'cc_emails.*' => 'email',
```

Normalisasi sebelum dikirim:

```php
$ccEmails     = array_values(array_filter($validated['cc_emails'] ?? []));
// Untuk API EcoSystem: kirim sebagai JSON string
$ccEmailsJson = !empty($ccEmails) ? json_encode($ccEmails) : null;
// Untuk CustomerEmailService: kirim sebagai array PHP biasa
```

**Format penting**: EcoSystem `jarviesStore()` mengharapkan `cc_emails` sebagai JSON string (bukan array), karena request adalah `multipart/form-data`. EcoSystem akan decode sendiri:

```json
"cc_emails": "[\"a@b.com\",\"c@d.com\"]"
```

### 17.2 Baca CC dari Tiket untuk Reply

Saat customer membalas, CC harus dipertahankan. Ambil dari `ticket.cc_emails`:

```php
$ticket = Ticket::find($ticketId);
$ccList = $ticket->cc_emails ?? [];  // sudah di-cast sebagai array oleh model
// Format yang diterima CustomerEmailService: array of email string
```

Jika `ticket->cc_emails` null, fallback ke `ticket_message` pertama yang punya `cc_emails`:

```php
if (empty($ccList)) {
    $firstMsg = TicketMessage::where('ticket_id', $ticketId)
        ->whereNotNull('cc_emails')
        ->orderBy('created_at', 'asc')
        ->first();
    $ccList = $firstMsg?->cc_emails
        ? (is_array($firstMsg->cc_emails) ? $firstMsg->cc_emails : json_decode($firstMsg->cc_emails, true) ?? [])
        : [];
}
```

### 17.3 Format CC ke CustomerEmailService

`CustomerEmailService::sendEmail()` menerima CC sebagai `array $ccEmails`. Format yang diterima:

```php
// Array of email string (paling umum):
['a@b.com', 'c@d.com']

// Array of {name, address} object juga diterima:
[['name' => 'John', 'address' => 'john@b.com']]
```

Untuk Gmail API, CC dimasukkan ke header `Cc:` di RFC 2822 message.  
Untuk Azure/Graph, CC dimasukkan ke field `ccRecipients` di payload sendMail.

---

## 18. Attachment & Gambar di JARVIES

### 18.1 Dua Tipe Attachment

**A. File Attachment (tombol lampirkan file)**

Customer memilih file dari sistem. File dibaca sebagai binary dan di-encode base64:

```php
// Di controller: ambil dari request
$fileAttachments = [];
foreach ($request->file('attachments', []) as $file) {
    $fileAttachments[] = [
        'name'    => $file->getClientOriginalName(),
        'content' => base64_encode(file_get_contents($file->getRealPath())),
        'mime'    => $file->getMimeType() ?? 'application/octet-stream',
    ];
}
```

**B. Gambar Inline (paste atau drag & drop di Quill)**

Quill menyimpan gambar paste sebagai `data:image/...;base64,...` di dalam HTML. JARVIES harus mengekstrak ini sebelum mengirim ke `CustomerEmailService`:

```php
// Ekstrak inline images dari HTML Quill
$inlineImages = [];
$htmlBody = preg_replace_callback(
    '/<img([^>]*?)\s+src="data:(image\/[a-zA-Z+\-.]+);base64,([A-Za-z0-9+\/=\s]+)"([^>]*?)>/i',
    function ($matches) use (&$inlineImages) {
        $mime    = $matches[2];
        $content = preg_replace('/\s+/', '', $matches[3]);
        $ext     = explode('/', $mime)[1] ?? 'png';
        $cid     = 'img-' . \Illuminate\Support\Str::uuid() . '@jarvies';

        $inlineImages[] = [
            'name'    => 'image.' . $ext,
            'content' => $content,   // base64 tanpa prefix
            'mime'    => $mime,
            'cid'     => $cid,
        ];

        // Ganti src dengan cid: di HTML yang akan dikirim
        return '<img' . $matches[1] . ' src="cid:' . $cid . '"' . $matches[4] . '>';
    },
    $request->input('message_body', '')
);
```

Kemudian kirim ke `CustomerEmailService`:

```php
$service->sendEmail(
    $emailToken, $toEmail, $subject, $plainText,
    $threadId, $inReplyTo,
    $fileAttachments,   // ← file attachment biasa
    $inlineImages,      // ← gambar inline dari Quill
    $htmlBody,          // ← HTML dengan cid: reference (sudah diganti)
    $ccEmails
);
```

### 18.2 Attachment untuk Tiket Baru (Submit via OAuth Email)

Saat customer submit tiket **baru** via OAuth email dengan attachment:

```php
$service->sendEmail(
    $emailToken,
    env('MS_SENDER_EMAIL'),
    $subject,           // deskripsi tiket = subject email
    $plainBody,
    null,               // threadId: null untuk tiket baru
    null,               // inReplyTo: null untuk tiket baru
    $fileAttachments,   // file yang diupload
    $inlineImages,      // gambar paste
    $htmlBody,
    $ccEmails
);
```

EcoSystem `processInbox()` akan mengambil attachment dari inbox M365 dan menyimpan metadata ke `staging_attachments` via `storeEmailAttachments()`. Saat staging di-approve, attachment pindah ke `ticket_attachment`.

### 18.3 Attachment untuk API Submission (Tanpa OAuth)

Jika customer tidak punya OAuth email, gunakan endpoint EcoSystem yang menerima `multipart/form-data`:

```php
$request = Http::withHeaders([
    'Accept'    => 'application/json',
    'X-Api-Key' => env('EXTERNAL_TICKET_API_KEY'),
])->timeout(30)->asMultipart();

// Tambah field teks
$multipart = [
    ['name' => 'description',     'contents' => $validated['description']],
    ['name' => 'body',            'contents' => $bodyHtml ?? ''],
    ['name' => 'customer_id',     'contents' => (string) $user['id']],
    ['name' => 'ticket_priority', 'contents' => $validated['ticket_priority'] ?? 'Medium'],
    ['name' => 'submitted_by_email', 'contents' => $user['email'] ?? ''],
    ['name' => 'sender_name',     'contents' => $user['name'] ?? ''],
    ['name' => 'cc_emails',       'contents' => $ccEmailsJson ?? ''],
];

// Tambah file
foreach ($request->file('attachments', []) as $file) {
    $multipart[] = [
        'name'     => 'attachments[]',
        'contents' => fopen($file->getRealPath(), 'r'),
        'filename' => $file->getClientOriginalName(),
    ];
}

Http::withHeaders(['X-Api-Key' => env('EXTERNAL_TICKET_API_KEY')])
    ->attach(...$multipart)
    ->post(env('ECOSYSTEM_API_URL') . '/staging-tickets');
```

> EcoSystem `jarviesStore()` memproses `attachments[]` dan menyimpan ke `staging_attachments`. Gunakan `multipart/form-data` bukan JSON untuk request yang mengandung file.

### 18.4 Batas Ukuran File

| Sumber | Batas |
|---|---|
| Attachment dari helpdesk (EcoSystem) | 10 MB per file |
| Attachment dari customer via JARVIES form | Sesuaikan dengan kebutuhan (10 MB direkomendasikan) |
| Gambar inline Quill | Tidak dibatasi eksplisit, tapi besar gambar memperlambat pengiriman |

---

## 19. OAuth Email Linking

### 19.1 Apa itu OAuth Email

JARVIES memungkinkan customer menghubungkan akun Gmail atau Microsoft personal mereka. Dengan ini:
- Tiket baru dikirim dari email customer sendiri (bukan dari helpdesk)
- Thread email tercipta di inbox customer secara langsung
- Reply dari helpdesk masuk ke inbox email customer

### 19.2 Providers yang Didukung

| Provider | Kolom DB | API yang digunakan |
|---|---|---|
| `google` | `customer_email_tokens.provider = 'google'` | Gmail API RFC 2822 |
| `azure` | `customer_email_tokens.provider = 'azure'` | Microsoft Graph delegated `/me/sendMail` |

### 19.3 Cek Status OAuth Sebelum Submit/Reply

Selalu cek apakah customer punya OAuth email yang masih valid sebelum menentukan jalur pengiriman:

```php
$emailToken = CustomerEmailToken::where('customer_id', $user['id'])->first();
$hasValidEmail = $emailToken
    && !($emailToken->isExpired() && !$emailToken->refresh_token);

// isExpired(): cek apakah access_token sudah expired
// !$emailToken->refresh_token: tidak bisa refresh → token benar-benar mati
```

### 19.4 Token Refresh Otomatis

`CustomerEmailService` menangani refresh token secara otomatis jika `access_token` expired tapi `refresh_token` masih ada. Tidak perlu handle ini di controller.

### 19.5 Endpoint OAuth

```
GET  /oauth/email/status              → cek status linking
GET  /oauth/email/redirect/{provider} → mulai OAuth flow
GET  /oauth/email/callback/{provider} → OAuth callback (di luar middleware auth!)
DELETE /oauth/email/disconnect        → cabut akses
GET  /onboarding/connect-email        → halaman onboarding setelah setup password
```

---

## 20. Membaca Pesan & Attachment dari Database

### 20.1 Baca Ticket Messages

Karena JARVIES berbagi DB dengan EcoSystem, baca langsung dari tabel `ticket_message`:

```php
$messages = TicketMessage::where('ticket_id', $ticketId)
    ->where('is_internal_note', false)  // customer tidak boleh lihat internal note
    ->orderBy('created_at', 'asc')
    ->get();
```

**Field yang perlu ditampilkan di UI:**

| Field | Keterangan |
|---|---|
| `sender_type` | `'customer'` atau `'employee'` — tentukan posisi bubble chat |
| `sender_name` | Nama pengirim |
| `message` | Plain text |
| `message_html` | HTML (prioritaskan ini untuk render, sudah include gambar) |
| `channel` | `'email'` atau `'web'` |
| `created_at` | Timestamp |

### 20.2 Baca Attachment

```php
$attachments = TicketAttachment::where('ticket_id', $ticketId)
    ->where('is_inline', false)   // hanya attachment non-inline untuk daftar file
    ->get();

// URL untuk download:
foreach ($attachments as $att) {
    $url = $att->public_url;
    // public_url accessor:
    // - Jika ada graph_message_id + graph_attachment_id → route('attachments.show', $att->id)
    // - Jika ada file_path → '/storage/' . $att->file_path
}
```

**Di JARVIES**, attachment dari M365 harus di-proxy juga. Buat route dan controller yang setara dengan `AttachmentController` di EcoSystem — fetch dari Graph API dan stream ke browser, dengan cek bahwa customer yang mengakses memiliki akses ke tiket tersebut.

### 20.3 Mark as Read

Saat customer membuka tiket, tandai pesan helpdesk sebagai sudah dibaca:

```php
TicketMessage::where('ticket_id', $ticketId)
    ->where('sender_type', 'employee')
    ->where('is_read_by_customer', false)
    ->where('is_internal_note', false)
    ->update(['is_read_by_customer' => true]);
```

### 20.4 Jarvies Status (Status yang Terlihat Customer)

`ticket.jarvies_status` adalah status yang ditampilkan ke customer. Field ini dikelola sepenuhnya oleh EcoSystem/helpdesk — **JARVIES hanya read, tidak pernah write**.

| Nilai | Label untuk Customer |
|---|---|
| `in process` | Sedang Diproses |
| `author action` | Perlu Tindakan Anda |
| `proposed solution` | Solusi Diajukan |
| `sent in to SAP` | Diteruskan ke SAP |
| `sent it to support` | Diteruskan ke Support |
| `closed` | Selesai |

---

## 21. Environment Variables yang Dibutuhkan

### 21.1 File .env JARVIES

```env
# ── EcoSystem API ────────────────────────────────────────────────
ECOSYSTEM_API_URL=https://ecosystem.eclecticoffice.com/api
EXTERNAL_TICKET_API_KEY=your-secret-key-here
# Key ini HARUS sama dengan nilai di EcoSystem .env EXTERNAL_TICKET_API_KEY

# ── Microsoft Graph (shared M365 inbox helpdesk) ─────────────────
MS_TENANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
MS_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
MS_CLIENT_SECRET=your-m365-client-secret
MS_SENDER_EMAIL=helpdesk@eclecticoffice.com
GRAPH_BASE_URL=https://graph.microsoft.com/v1.0

# ── Google OAuth (untuk customer Gmail linking) ───────────────────
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxx
GOOGLE_REDIRECT_URI=https://jarvies.eclecticoffice.com/oauth/email/callback/google

# ── Azure OAuth (untuk customer Microsoft personal linking) ───────
AZURE_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
AZURE_CLIENT_SECRET=xxx
AZURE_REDIRECT_URI=https://jarvies.eclecticoffice.com/oauth/email/callback/azure
AZURE_TENANT=common
```

### 21.2 config/services.php

```php
'ecosystem' => [
    'url'     => env('ECOSYSTEM_API_URL'),
    'api_key' => env('EXTERNAL_TICKET_API_KEY'),
    'timeout' => env('ECOSYSTEM_API_TIMEOUT', 30),
    'retry'   => [
        'times' => 3,
        'sleep' => 1000,
    ],
],
```

---

## 22. Kontrak API EcoSystem yang Dipanggil JARVIES

### 22.1 Submit Staging Ticket Baru

```
POST /api/jarvies/staging-tickets
Header: X-Api-Key: {key}
Content-Type: application/json  ATAU  multipart/form-data (jika ada file)

Body (JSON):
{
    "customer_id":        123,          // WAJIB
    "description":        "string",     // WAJIB - judul tiket
    "body":               "HTML string",// opsional
    "ticket_priority":    "Medium",     // Very High|High|Medium|Low
    "submitted_by_email": "a@b.com",   // email login customer
    "sender_name":        "John Doe",   // nama individual
    "cc_emails":          "[\"x@y.com\"]", // JSON string, bukan array!
    "name":               "string",     // tambahan Jarvies
    "no_hp":              "string",
    "module":             "string",
    "client":             "string"
}

Body (multipart, jika ada attachment):
attachments[]  = file (bisa multiple)
... field lain sama seperti JSON tapi sebagai form field

Response 201:
{
    "success": true,
    "data": {
        "id":            45,
        "status":        "unvalidated",
        "ticket_priority": "Medium",
        "channel":       "web",
        "created_at":    "2026-04-08T..."
    }
}
```

### 22.2 Customer Reply ke Tiket

```
POST /api/jarvies/tickets/{ticketId}/customer-reply
Header: X-Api-Key: {key}
Content-Type: application/json

Body:
{
    "message_body":     "HTML dari Quill",   // WAJIB
    "sender_name":      "John Doe",           // WAJIB
    "sender_email":     "john@gmail.com",     // WAJIB
    "customer_id":      123,                  // opsional
    "skip_relay":       true,                 // true jika JARVIES sudah kirim email
    "channel":          "email",              // 'email' atau 'web'
    "email_message_id": "<msgid@gmail.com>"   // RFC 2822 ID dari OAuth email (jika skip_relay=true)
}

Response 201:
{
    "success": true,
    "data": {
        "id":          89,
        "ticket_id":   12,
        "sender_type": "customer",
        "sender_name": "John Doe",
        "message_body": "...",
        "channel":     "email",
        "created_at":  "2026-04-08T..."
    }
}
```

### 22.3 Ambil Data Mandays Customer

```
GET /api/jarvies/tickets/{ticketId}/mandays/customer
Header: X-Api-Key: {key}

Response:
{
    "success":                 true,
    "visible":                 true,
    "mandays_proposal_status": "sent_to_chat",
    "proposal": {
        "version":    1,
        "total_mandays": 5.0,
        "status":     "sent_to_chat",
        "details": [
            { "activity": "FI", "module": "GL", "mandays": 2.5 }
        ]
    }
}
```

---

## 23. Aturan Kritis: Yang Boleh dan Tidak Boleh Dilakukan JARVIES

### ❌ JANGAN Lakukan

```
❌ Jangan set jarvies_status dari JARVIES
   → Field ini dikelola eksklusif oleh EcoSystem

❌ Jangan approve/reject staging ticket dari JARVIES
   → Hanya EcoSystem yang boleh approve/reject

❌ Jangan kirim email dari MS_SENDER_EMAIL (helpdesk M365) di JARVIES
   → Untuk relay helpdesk, minta EcoSystem via skip_relay=false
   → JARVIES hanya kirim email dari akun OAuth customer sendiri

❌ Jangan ubah ticket.email_thread_id dari JARVIES
   → Field ini diset oleh EcoSystem saat staging approved

❌ Jangan buat ticket langsung tanpa staging (kecuali Admin)
   → Customer selalu melalui staging → approve → ticket

❌ Jangan kirim cc_emails sebagai PHP array ke endpoint staging via JSON
   → Harus json_encode() dulu: '["a@b.com"]'

❌ Jangan gunakan email_thread_id (conversationId) sebagai inReplyTo header
   → inReplyTo harus menggunakan email_message_id (RFC 2822 Message-ID)

❌ Jangan tampilkan is_internal_note=true ke customer
   → Filter selalu: where('is_internal_note', false)
```

### ✅ Yang Harus Dilakukan

```
✅ Selalu cek OAuth email sebelum menentukan jalur submit/reply
   → hasOAuth ? email langsung : API ke EcoSystem

✅ Selalu kirim customer_id dalam setiap request ke EcoSystem
   → EcoSystem butuh ini untuk assign staging ke customer yang benar

✅ Gunakan email_message_id (bukan email_thread_id) sebagai inReplyTo
   → Untuk Gmail: ini threadId Gmail
   → Untuk Graph: ini internetMessageId RFC 2822

✅ Set skip_relay=true jika JARVIES sudah kirim email sendiri via OAuth
   → Mencegah customer menerima email duplikat

✅ Sertakan email_message_id dari OAuth email di customer-reply request
   → Agar EcoSystem menyimpannya untuk dedup processInbox()

✅ Ekstrak inline images dari Quill sebelum kirim ke CustomerEmailService
   → data:image/...;base64,... harus dikonversi ke CID attachment

✅ Pertahankan CC dari ticket.cc_emails di setiap reply
   → Ambil dari ticket model, bukan dari pesan individual
```

---

## 24. Checklist Implementasi Fitur Email di JARVIES

Gunakan checklist ini saat mengimplementasi atau memodifikasi fitur yang berkaitan dengan email.

### Fitur: Submit Tiket Baru

- [ ] Form memiliki field: description (required), body (HTML editor), ticket_priority, cc_emails, name, no_hp, module, client
- [ ] Validasi `ticket_priority` hanya menerima: `Very High`, `High`, `Medium`, `Low`
- [ ] Cek OAuth email sebelum memilih jalur pengiriman
- [ ] Jika OAuth: kirim email via `CustomerEmailService` → tidak ada API call ke EcoSystem
- [ ] Jika tidak ada OAuth: POST ke `/api/jarvies/staging-tickets` dengan `X-Api-Key`
- [ ] `cc_emails` dikirim sebagai JSON string ke API, bukan array PHP
- [ ] `customer_id` selalu dikirim
- [ ] `sender_name` adalah nama individual (dari session user.name), bukan nama perusahaan
- [ ] Jika API gagal: fallback simpan ke `staging_tickets` lokal

### Fitur: Reply Tiket

- [ ] Cek apakah customer punya OAuth email yang valid
- [ ] Cek `ticket->email_thread_id` untuk menentukan apakah ada email thread
- [ ] Jika OAuth + ada thread: kirim via `CustomerEmailService` dengan `customerThreadId` + `inReplyTo`
- [ ] Selalu POST ke EcoSystem `/customer-reply` agar tersimpan di `ticket_message`
- [ ] Set `skip_relay=true` jika sudah kirim via OAuth
- [ ] Sertakan `email_message_id` dari OAuth email di request customer-reply
- [ ] Inline images dari Quill sudah diekstrak menjadi CID attachment sebelum dikirim
- [ ] CC diambil dari `ticket->cc_emails`, bukan dari form

### Fitur: Tampilkan Riwayat Pesan

- [ ] Filter `is_internal_note = false`
- [ ] Tampilkan `message_html` (jika ada) atau `message` sebagai fallback
- [ ] Bedakan bubble berdasarkan `sender_type` (`customer` vs `employee`)
- [ ] Tampilkan timestamp `created_at`
- [ ] Tandai pesan sebagai read saat tiket dibuka
- [ ] Attachment: gunakan `att->public_url` atau proxy route JARVIES sendiri

### Fitur: Attachment

- [ ] File dari form: encode base64, kirim ke `CustomerEmailService` sebagai `fileAttachments`
- [ ] Gambar paste/drag Quill: ekstrak dari data URI, konversi ke CID, kirim sebagai `inlineImages`
- [ ] Batas ukuran file: maksimum 10 MB per file
- [ ] Untuk staging tanpa OAuth: gunakan `multipart/form-data`, bukan JSON

### Fitur: OAuth Email Linking

- [ ] Tombol "Hubungkan Email" di halaman profil atau onboarding
- [ ] Support provider: `google` dan `azure`
- [ ] Route callback di luar middleware auth (tidak ada session saat callback)
- [ ] Setelah link: tampilkan email yang terhubung dan tombol disconnect
- [ ] Handle expired token: tampilkan pesan dan tawarkan re-link

---

*Dokumen ini mencerminkan implementasi aktual per April 2026.*  
*Sisi EcoSystem: `EmailController.php`, `TicketMessageController.php`, `StagingTicketService.php`*  
*Sisi JARVIES: `TicketController.php`, `TicketMessageController.php`, `CustomerEmailService.php`, `GraphRelayService.php`*
