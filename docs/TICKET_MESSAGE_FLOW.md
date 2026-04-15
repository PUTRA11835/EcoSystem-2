# Dokumentasi: Ticket Message Flow — Email, Gambar, Attachment & Timezone
**EcoSystem-2 — QA Technical Reference**
**Terakhir diperbarui: 2026-04-15**

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Skema Database](#2-skema-database)
3. [Flow Email Masuk (Inbound)](#3-flow-email-masuk-inbound)
4. [Flow Email Keluar (Outbound Reply)](#4-flow-email-keluar-outbound-reply)
5. [Flow Staging — Validasi Email Baru](#5-flow-staging--validasi-email-baru)
6. [Flow Internal Note](#6-flow-internal-note)
7. [Flow Reply dari Customer (via Jarvies)](#7-flow-reply-dari-customer-via-jarvies)
8. [Handling Gambar & Attachment](#8-handling-gambar--attachment)
9. [Proxy Route Attachment](#9-proxy-route-attachment)
10. [Sistem Timezone (WIB)](#10-sistem-timezone-wib)
11. [Referensi Endpoint](#11-referensi-endpoint)
12. [Struktur File Penting](#12-struktur-file-penting)
13. [Diagram Alur Lengkap](#13-diagram-alur-lengkap)
14. [Checklist QA](#14-checklist-qa)

---

## 1. Gambaran Umum

EcoSystem-2 mengintegrasikan sistem tiket helpdesk dengan **Microsoft Graph API (M365)** untuk pengiriman dan penerimaan email. Setiap tiket memiliki "thread" pesan yang terdiri dari:

- **Email masuk** dari customer (diproses oleh scheduler)
- **Balasan helpdesk** yang dikirim melalui Graph API
- **Internal note** (tidak terkirim ke customer, hanya terlihat staf)
- **Balasan customer** melalui portal Jarvies

Attachment (file dan gambar inline) disimpan atau diproxy melalui Graph API, tidak disimpan semua ke lokal.

---

## 2. Skema Database

### Tabel `ticket`
File migrasi: `database/migrations/2026_02_09_100000_create_ticket_system_tables.php`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `ticket_id` | PK | Identifikasi tiket |
| `channel` | enum('email','web') | Asal tiket |
| `email_thread_id` | string nullable | `conversationId` dari Exchange/Outlook |
| `cc_emails` | JSON | Daftar penerima CC (salin dari staging saat approval) |
| `last_message_at` | timestamp | Pesan terakhir masuk (semua pihak) |
| `last_customer_reply_at` | timestamp | Balasan terakhir dari customer |
| `last_agent_reply_at` | timestamp | Balasan terakhir dari helpdesk |

### Tabel `ticket_message`
File migrasi: `database/migrations/2026_02_09_100000_create_ticket_system_tables.php`  
Diperluas: `database/migrations/2026_02_23_200000_extend_ticket_tables_for_email_attachments.php`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | PK | |
| `ticket_id` | FK | Referensi ke tiket |
| `sender_type` | enum | `'employee'` / `'customer'` / `'system'` |
| `sender_id` | int nullable | ID pengirim sesuai `sender_type` |
| `sender_email` | string | Email pengirim |
| `sender_name` | string | Nama pengirim |
| `message` | text | Pesan plain text (tanpa HTML) |
| `message_html` | longText nullable | Pesan dalam format HTML, berisi gambar inline |
| `is_internal_note` | boolean | Jika true = hanya terlihat staf internal |
| `channel` | enum | `'email'` / `'web'` |
| `email_message_id` | string nullable | RFC 2822 Message-ID untuk threading (`<xxx@domain>`) |
| `email_in_reply_to` | string nullable | Header `In-Reply-To` SMTP |
| `cc_emails` | array (JSON cast) | Penerima CC pesan ini |
| `is_read_by_customer` | boolean | Status baca oleh customer |
| `is_read_by_agent` | boolean | Status baca oleh agen/helpdesk |
| `read_at` | datetime nullable | |
| `mentioned_employee_ids` | array | ID employee yang di-mention (internal note) |
| `mentioned_role_ids` | array | ID role yang di-mention (internal note) |

### Tabel `ticket_attachment`
File migrasi: `database/migrations/2026_02_23_200000_*` dan `2026_02_24_000001_*`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | PK | |
| `ticket_id` | FK | |
| `message_id` | FK | Referensi ke `ticket_message.id` |
| `uploaded_by_type` / `uploaded_by_id` | polymorphic | Siapa yang upload |
| `attachment_type` | enum | `'image'` / `'pdf'` / `'document'` / `'spreadsheet'` / `'archive'` / `'file'` |
| `file_name` | string | Nama file asli |
| `file_size` | int | Ukuran dalam bytes |
| `mime_type` | string | MIME type |
| `is_inline` | boolean | `true` = gambar CID inline dalam email |
| `file_path` | string nullable | Path lokal relatif (`ticket-inline-images/...`), untuk inline images |
| `graph_message_id` | string nullable | ID pesan di Sent Items Graph API |
| `graph_attachment_id` | string nullable | ID attachment di Graph API |
| `content_id` | string nullable | Content-ID header untuk gambar inline (`<xxx>`) |

### Tabel `staging_tickets`
Menyimpan email baru yang belum divalidasi helpdesk.

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `status` | enum | `'unvalidated'` / `'approved'` / `'rejected'` |
| `email_message_id` | string | internetMessageId dari Graph |
| `graph_message_id` | string nullable | ID pesan di mailbox M365 |
| `email_thread_id` | string nullable | conversationId Exchange |
| `email_body_html` | longText | Body email HTML asli (lengkap dengan inline images) |
| `has_attachments` | boolean | |
| `cc_emails` | array | |
| `ticket_id` | FK nullable | Diisi setelah approve |

### Tabel `staging_attachments`
Attachment sementara untuk staging (sebelum divalidasi).

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `staging_id` | FK | |
| `file_name`, `file_path`, `file_size`, `mime_type` | | |
| `original_name` | string | Nama file asli |

---

## 3. Flow Email Masuk (Inbound)

### Trigger
- **Scheduler/Cron** memanggil artisan command `email:process-inbox`
- Atau dipanggil manual via `POST /api/email/process-inbox`

### Entry Point
`app/Http/Controllers/EmailController.php` → method `processInbox()` (sekitar baris 291)

### Langkah-langkah Detail

```
1. Fetch 50 pesan terbaru yang belum dibaca dari Inbox (Graph API)
   + Fetch pesan dari 60 menit terakhir (safety net)

2. Dedup: gabungkan & hilangkan duplikat berdasarkan internetMessageId

3. Untuk setiap pesan:
   a. Parse: subject, from, CC, body HTML
   b. extractReplyBody() → hapus quoted text / blockquotes
   c. Fetch header SMTP (In-Reply-To, References) dari Graph
   d. Cari tiket terkait (threading — lihat di bawah)

4. Handling Gambar:
   storeEmailAttachments() → lihat Bagian 8

5. Simpan ke DB:
   - Jika tiket ditemukan → buat TicketMessage
   - Jika tidak ditemukan → buat StagingTicket

6. Mark as Read di Graph API (PATCH isRead=true)
```

### Strategi Threading (5 Tingkatan Prioritas)

Sistem mencoba menemukan tiket yang sesuai dengan urutan:

| Prioritas | Metode | Keterangan |
|-----------|--------|-----------|
| 1 (tertinggi) | `email_thread_id` (conversationId) | Hanya works Outlook-ke-Outlook |
| 2 | Header `In-Reply-To` | Reliable lintas platform (Gmail, Yahoo) |
| 3 | Header `References` | Chain referensi thread, cek ID terbaru dulu |
| 4 | `internetMessageId` self-check | Dedup — cek apakah sudah ada di DB |
| 5 (fallback) | Staging link | Cek `staging.email_thread_id` → tiket dari staging |

Jika tidak ada yang cocok → buat **StagingTicket** baru.

### Record yang Dibuat (jika tiket ditemukan)

**TicketMessage:**
```
sender_type      = 'customer'
sender_email     = email pengirim
message          = plain text (HTML di-strip)
message_html     = HTML body (sudah diganti cid: → /storage/...)
channel          = 'email'
email_message_id = internetMessageId dari Graph
is_read_by_customer = true  (mereka yang kirim)
is_read_by_agent    = false (belum dibaca helpdesk)
cc_emails        = array {name, address} dari header CC
```

Timestamp tiket diupdate menggunakan waktu asli email (`receivedDateTime` Graph, UTC).

---

## 4. Flow Email Keluar (Outbound Reply)

### Trigger
Employee/helpdesk membalas tiket dari halaman `ticket/show`.

### Entry Point
`app/Http/Controllers/TicketMessageController.php` → method `store()` (baris 99)  
→ memanggil `EmailController@sendTicketReply()`

### Langkah-langkah Detail

```
1. Validasi input:
   - message_body (HTML dari Quill editor) ATAU attachments wajib ada

2. Identitas pengirim:
   - Selalu "Helpdesk Support" di email (bukan nama asli karyawan)
   - Signature: "-{nick_name}" dari employee_basic_data.nick_name

3. sendTicketReply() — buat & kirim email:

   a. extractBase64Images(HTML) → temukan <img src="data:image/...;base64,...">
      - Ganti setiap base64 dengan cid:xxx reference
      - Simpan sebagai array {cid, mime, content, name}

   b. Cari pesan asli di Graph (via email_message_id):
      - Cek Inbox → Sent Items → global fallback
      - Buat reply draft: POST /messages/{id}/createReply

   c. PATCH draft:
      - subject (dengan/tanpa "Re:" prefix)
      - body.contentType = HTML
      - toRecipients = customer email
      - ccRecipients = daftar CC

   d. Attach gambar inline:
      - POST /messages/{draftId}/attachments
      - isInline=true, contentId="{cid}"

   e. Attach file biasa:
      - Baca file → base64 encode
      - POST /messages/{draftId}/attachments

   f. POST /messages/{draftId}/send
      → draft pindah ke Sent Items dengan ID baru

   g. Temukan pesan di Sent Items (retry 3x, jeda 1 detik):
      - GET /mailFolders/SentItems/messages?$top=20
      - Cocokkan via internetMessageId
      → sentMessageId (ID valid permanen)

   h. Update attachment IDs:
      - Draft attachment IDs tidak valid setelah dikirim
      - Fetch attachment dari Sent Items
      - Cocokkan per nama file → update graph_attachment_id

4. Simpan ke DB (TicketMessageController):
   - Buat TicketMessage:
     channel          = 'email'
     email_message_id = internet_message_id (dari Graph)
     message_html     = HTML body dengan signature
     sender_type      = 'employee'

   - Buat TicketAttachment per file:
     graph_message_id     = sentMessageId
     graph_attachment_id  = ID dari Sent Items
     file_name, size, mime dari respons Graph

5. Update tiket:
   - last_agent_reply_at = now()
   - last_message_at     = now()
```

### Fallback Strategi Pengiriman

Jika `createReply` gagal (pesan asli tidak ditemukan di inbox/sent):

| Kondisi | Tindakan |
|---------|----------|
| Punya `threadId` (conversationId) | Cari pesan mana saja di thread → createReply dari situ |
| Tidak ada sama sekali | Buat draft baru (POST /messages) — tidak ada In-Reply-To header |

---

## 5. Flow Staging — Validasi Email Baru

### Kapan Terjadi
Email masuk yang **tidak cocok** dengan tiket manapun → masuk antrian staging.

### Flow Approval

```
Admin/helpdesk buka halaman Staging
  ↓
GET /api/staging-tickets/{id}/preview-body
  → Tampilkan email HTML (gambar inline dirender sebagai data URI)

GET /api/staging-tickets/{id}/email-attachments
  → Daftar attachment yang menyertai email

Admin klik "Approve"
  ↓
POST /api/staging-tickets/{id}/approve
  ↓
StagingTicketController@approve():
  1. Buat Ticket baru dari data staging
     - jarvies_status = 'sent it to support'
     - cc_emails disalin dari staging.cc_emails

  2. Buat TicketMessage pertama dari staging.email_body_html

  3. Proses attachment email:
     EmailController@processAttachmentsForMessage()
       → Fetch attachment dari Graph via staging.graph_message_id
       → Simpan ke TicketAttachment (graph_attachment_id)
       → Ganti cid: di message_html → /attachments/{id}

  4. Update staging: status='approved', ticket_id diisi
```

---

## 6. Flow Internal Note

### Trigger
Employee membuat note internal dari halaman ticket/show (toggle "Internal Note").

### Perbedaan dari Reply

| Aspek | Reply | Internal Note |
|-------|-------|---------------|
| Terkirim ke customer? | Ya (via email) | Tidak |
| `is_internal_note` | false | true |
| `channel` | 'email' | 'web' |
| @mentions | Tidak | Bisa mention employee/role |
| Attachment | Via Graph | Via local storage |

### Flow @Mentions
```
Pesan internal disimpan dengan:
  mentioned_employee_ids = [1, 2, 3]
  mentioned_role_ids     = [5]

→ Fan-out ke tabel Notification untuk setiap ID
→ Employee yang di-mention mendapat notifikasi
→ Role di-mention: lookup semua member via employee_role_assignment
```

---

## 7. Flow Reply dari Customer (via Jarvies)

### Trigger
Customer membalas tiket melalui portal Jarvies (sistem eksternal).

### Entry Point
`POST /api/tickets/{ticketId}/customer-reply`  
Controller: `TicketMessageController@customerReply()` (baris 291)

### Parameter Input
```
message_body    - HTML string
sender_name     - nama customer
sender_email    - email customer
customer_id     - (opsional)
skip_relay      - boolean: jika true, Jarvies sudah kirim email sendiri
channel         - 'web' | 'email'
email_message_id - RFC 2822 ID jika dari OAuth email Jarvies
```

### Flow
```
1. Buat TicketMessage:
   sender_type         = 'customer'
   is_read_by_customer = true
   is_read_by_agent    = false

2. Jika skip_relay = false:
   sendCustomerReplyRelay()
     → Bungkus pesan dalam template email
     → Cari pesan email terakhir untuk inReplyTo
     → Panggil sendTicketReply() — kirim sebagai relay
     → Simpan internet_message_id ke ticket_message

3. Update tiket:
   last_customer_reply_at = now()
   last_message_at        = now()

4. Jika email_thread_id tiket masih kosong:
   → Simpan conversationId baru dari reply relay
```

---

## 8. Handling Gambar & Attachment

### A. Gambar Inline dari Email Masuk

Gambar inline biasanya dikirim sebagai attachment dengan header `Content-ID` dan flag `isInline=true`.

**Proses `storeEmailAttachments()` — file `EmailController.php` baris ~862:**

```
Untuk setiap attachment di email:

JIKA inline (isInline=true) DAN mime=image/* DAN punya contentId:
  → Download binary dari Graph API
  → Simpan ke: storage/public/ticket-inline-images/{ticketId}/{uuid}.{ext}
  → Buat TicketAttachment:
      is_inline   = true
      file_path   = 'ticket-inline-images/{ticketId}/{uuid}.{ext}'
      content_id  = '{xxx}'
  → URL publik = '/storage/ticket-inline-images/{ticketId}/{uuid}.{ext}'
  → Tambahkan ke cidMap: 'cid:{xxx}' → '/storage/...'

JIKA bukan inline (file biasa):
  → Simpan metadata saja (TIDAK download file)
  → Buat TicketAttachment:
      graph_attachment_id = ID dari Graph API
      graph_message_id    = ID pesan di mailbox
  → URL publik = '/attachments/{id}' (proxy route)
  → Tambahkan ke cidMap jika ada contentId

Setelah semua attachment diproses:
  → Ganti semua 'cid:xxx' di message_html dengan URL yang sesuai
```

### B. Gambar Inline dari Reply Helpdesk (Web)

Saat karyawan menyisipkan gambar di Quill editor:

```
Image base64 ter-embed dalam HTML: <img src="data:image/png;base64,...">

extractBase64Images(HTML):
  → Temukan semua <img> dengan src base64
  → Generate CID: {uuid}@ecosystem
  → Ganti img src dengan: cid:{uuid}@ecosystem
  → Simpan array: [{cid, mime, content (base64), name}]

Saat kirim ke Graph:
  → Attach setiap gambar sebagai MIME attachment
    isInline=true, contentId="{cid}"
```

Gambar yang dikirim ini tidak disimpan di server lokal — hanya ada di Sent Items Graph.

### C. File Attachment Upload dari Web

```
Employee upload file saat membalas:
  → File dikirim ke server sebagai multipart upload
  → Dibaca ke memory (tidak disimpan ke disk)
  → base64 encode
  → Attach ke draft Graph API
  → Setelah dikirim: simpan metadata di TicketAttachment
    (graph_message_id, graph_attachment_id, file_name, size, mime)
```

### D. URL Publik Attachment

Model `TicketAttachment` (`app/Models/TicketAttachment.php`) punya computed attribute `public_url`:

```php
// Prioritas URL:
if ($this->graph_message_id)       → route('attachments.show', $this->id)  // proxy
elseif ($this->file_path)          → '/storage/' . $this->file_path         // lokal
elseif ($this->link_url)           → $this->link_url                        // legacy
```

> **Penting:** Jangan gunakan `Storage::disk('public')->url()` karena mengembalikan URL absolut
> dengan `APP_URL` (mis. `http://localhost:8000/storage/...`) yang gagal di production.
> Selalu gunakan **`'/storage/' . $file_path`** (relative path).

### E. Staging Preview (Modal Validasi)

```
GET /api/staging-tickets/{id}/preview-body

→ Fetch email HTML dari staging.email_body_html
→ Untuk setiap attachment di staging:
   - Fetch attachment dari Graph
   - Decode base64
   - Ganti cid:{contentId} → data:{mime};base64,{content}
   → Render sebagai data URI langsung di browser (tanpa simpan file)
```

---

## 9. Proxy Route Attachment

### Route
`GET /attachments/{id}` — **membutuhkan session login**

### Controller
`app/Http/Controllers/AttachmentController.php`

### Flow

```
Terima request /attachments/{id}

1. Load TicketAttachment dari DB

2. Cek apakah punya graph_message_id:
   YES → Fetch dari Graph API:
          GET /messages/{graph_message_id}/attachments/{graph_attachment_id}
          Decode base64 contentBytes → stream ke client

   NO  → Redirect ke /storage/{file_path} (legacy)

3. Jika Graph API return 404:
   Fallback recovery:
     → Cari TicketMessage via email_message_id
     → Search di Sent Items (retry 3x, jeda 1 detik):
         GET /mailFolders/SentItems/messages?$filter=...
     → Cocokkan attachment per nama file (case-insensitive)
     → Update graph_attachment_id di DB
     → Retry fetch dari Graph

4. Jika berhasil:
   Headers yang dikirim:
     Content-Type        = mime_type
     Content-Disposition = 'inline'    (gambar)
                         = 'attachment' (file lain)
     Cache-Control       = 'private, max-age=3600'
```

---

## 10. Sistem Timezone (WIB)

### Konfigurasi Backend (Laravel)

**File:** `config/app.php` baris 68
```php
'timezone' => 'Asia/Jakarta',
```

Efeknya:
- Semua `Carbon::now()` menggunakan WIB
- Model timestamp (`created_at`, `updated_at`) disimpan UTC di DB, tapi di-cast ke WIB saat akses

### Penting: Graph API Selalu UTC

Graph API mengembalikan timestamp dalam format ISO 8601 UTC, contoh:
```
"receivedDateTime": "2026-04-15T07:30:00Z"
```

Kode yang benar (`EmailController.php` baris ~315):
```php
// BENAR — parse UTC eksplisit dulu, lalu Laravel konversi ke timezone app
$receivedAt = Carbon::parse($msg['receivedDateTime'])->utc();

// SALAH — Carbon::parse tanpa ->utc() bisa salah interpretasi jika server timezone berbeda
$receivedAt = Carbon::parse($msg['receivedDateTime']);
```

### Konfigurasi Frontend (JavaScript)

**Halaman ticket/show.blade.php:**

Format pesan di thread tiket (baris ~2037):
```javascript
// Format WIB untuk tampilan pesan
timestamp.toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
}) + ' WIB'
```

Format timestamp mandays (baris ~3553, 3562):
```javascript
new Date(ts).toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta',
    // ...
}) + ' WIB'
```

Format fallback (baris ~1681):
```javascript
// en-GB format dengan label WIB eksplisit
date.toLocaleString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
}) + ' (WIB)'
```

### User Preferences Timezone

File `app/Http/Controllers/SettingsController.php`:
```php
// Default timezone per user
'timezone' => 'Asia/Jakarta'
```

Tersimpan per user di tabel preferences.

### Ringkasan Timezone Flow

```
Graph API (UTC) → Carbon::parse()->utc() → disimpan di DB (UTC)
                                          ↓
                                    Model akses → Laravel auto-convert ke Asia/Jakarta
                                          ↓
                              API response → timestamp string WIB
                                          ↓
                              Frontend JS → toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })
                                          ↓
                              Tampilan → "15 Apr 2026, 14:30 WIB"
```

---

## 11. Referensi Endpoint

### Email Processing

| Method | URL | Fungsi | Auth |
|--------|-----|--------|------|
| `POST` | `/api/email/process-inbox` | Proses email masuk dari Graph API | Session |
| `POST` | `/api/email/process-sent` | Link email approval ke staging | Session |
| `GET` | `/api/email/inbox` | Debug: lihat pesan terbaru di Inbox | Session |
| `POST` | `/api/email/send` | Kirim email manual | Session |
| `POST` | `/api/email/reply` | Reply email manual | Session |
| `POST` | `/api/email/messages/{messageId}/reprocess-attachments` | Retry ekstraksi attachment | Session |

### Tiket & Pesan

| Method | URL | Fungsi | Auth |
|--------|-----|--------|------|
| `GET` | `/api/tickets` | Daftar tiket | Session |
| `POST` | `/api/tickets` | Buat tiket (admin) | Session |
| `GET` | `/api/tickets/{id}` | Detail tiket | Session |
| `GET` | `/api/tickets/{ticketId}/messages` | Semua pesan tiket | Session |
| `POST` | `/api/tickets/{ticketId}/messages` | Kirim reply / internal note | Session |
| `POST` | `/api/tickets/{ticketId}/customer-reply` | Balas dari customer (Jarvies) | X-Api-Key / Session |
| `PUT` | `/api/tickets/{ticketId}/messages/mark-all-read` | Tandai semua pesan terbaca | Session |

### Staging

| Method | URL | Fungsi | Auth |
|--------|-----|--------|------|
| `GET` | `/api/staging-tickets` | Daftar staging belum divalidasi | Session |
| `GET` | `/api/staging-tickets/{id}` | Detail staging | Session |
| `GET` | `/api/staging-tickets/{id}/preview-body` | Preview HTML (gambar inline as data URI) | Session |
| `GET` | `/api/staging-tickets/{id}/email-attachments` | Daftar attachment email staging | Session |
| `POST` | `/api/staging-tickets/{id}/approve` | Approve → buat tiket | Session |
| `POST` | `/api/staging-tickets/{id}/reject` | Tolak staging | Session |
| `POST` | `/jarvies/staging-tickets` | Customer submit via Jarvies | X-Api-Key |

### Storage & Attachment

| Method | URL | Fungsi | Auth |
|--------|-----|--------|------|
| `GET` | `/attachments/{id}` | Proxy: ambil file dari Graph API | Session (wajib) |
| `GET` | `/storage/{path}` | File lokal langsung (gambar inline legacy) | Public |
| `GET` | `/staging-email-attachments/{stagingId}/{attId}` | Proxy attachment untuk preview staging | Session |

---

## 12. Struktur File Penting

```
app/Http/Controllers/
├── EmailController.php           (~1500 baris)
│   ├── processInbox()            — ambil & proses email masuk
│   ├── storeEmailAttachments()   — simpan gambar/file dari email
│   ├── extractReplyBody()        — bersihkan quoted text
│   ├── sendTicketReply()         — kirim balasan via Graph API
│   ├── extractBase64Images()     — ekstrak gambar Quill editor
│   ├── processSentItems()        — link approval email ke staging
│   └── processAttachmentsForMessage() — proses attachment saat approve staging
│
├── TicketMessageController.php   (~600+ baris)
│   ├── store()                   — kirim reply / internal note
│   ├── customerReply()           — balas dari Jarvies customer
│   └── sendCustomerReplyRelay()  — relay email ke customer
│
├── StagingTicketController.php   (~300+ baris)
│   ├── approve()                 — validasi & buat tiket dari staging
│   └── reject()                  — tolak staging
│
└── AttachmentController.php      (~191 baris)
    └── show()                    — proxy route untuk attachment

app/Models/
├── Ticket.php                    — relasi: messages, customer, employee, members
├── TicketMessage.php             — relasi: ticket, sender (polymorphic), attachments
├── TicketAttachment.php          — computed: public_url, is_image
├── StagingTicket.php             — relasi: customer, validator, ticket, attachments
└── StagingAttachment.php         — computed: public_url → '/storage/{file_path}'

database/migrations/
├── 2026_02_09_100000_*           — tabel ticket, ticket_message (dasar)
├── 2026_02_23_200000_*           — tambah message_html, kolom file di attachment
├── 2026_02_24_000001_*           — tambah graph_message_id, content_id
└── 2026_02_25_000001_*           — tambah cc_emails ke ticket_message

config/app.php                    — 'timezone' => 'Asia/Jakarta'

resources/views/ticket/
├── show.blade.php                — thread pesan, format WIB, proxy attachment
└── index.blade.php               — daftar tiket

resources/views/staging/
└── index.blade.php               — daftar staging, modal preview email
```

---

## 13. Diagram Alur Lengkap

```
╔══════════════════════════════════════════════════════════════════╗
║                   EMAIL MASUK (INBOUND)                          ║
║                                                                  ║
║  Customer Email (Gmail/Outlook/Yahoo)                            ║
║         ↓                                                        ║
║  [Scheduler: email:process-inbox]                                ║
║         ↓                                                        ║
║  Graph API → Ambil 50 pesan unread + 60 menit terakhir           ║
║         ↓                                                        ║
║  Dedup (per internetMessageId)                                   ║
║         ↓                                                        ║
║  Threading (5 tier: conversationId → In-Reply-To → References    ║
║             → self-check → staging fallback)                     ║
║         ↙             ↘                                          ║
║  Tiket ditemukan   Tidak ditemukan                               ║
║         ↓                ↓                                       ║
║  Buat TicketMessage  Buat StagingTicket                          ║
║         ↓                                                        ║
║  storeEmailAttachments():                                        ║
║    • Inline image → download → /storage/ticket-inline-images/    ║
║    • File biasa   → metadata saja (graph_attachment_id)          ║
║    • Ganti cid: di message_html dengan URL                       ║
║         ↓                                                        ║
║  Mark as Read di Graph                                           ║
╚══════════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════════╗
║                   STAGING APPROVAL                               ║
║                                                                  ║
║  Admin buka Staging                                              ║
║    → GET /preview-body (gambar render sebagai data URI)          ║
║    → GET /email-attachments (daftar file)                        ║
║         ↓                                                        ║
║  Admin klik Approve                                              ║
║    → POST /approve                                               ║
║    → Buat Ticket (jarvies_status = 'sent it to support')         ║
║    → Buat TicketMessage dari email_body_html                     ║
║    → processAttachmentsForMessage():                             ║
║        Fetch dari Graph → simpan TicketAttachment                ║
║        Ganti cid: → /attachments/{id}                            ║
║    → Staging status = 'approved'                                 ║
╚══════════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════════╗
║               REPLY HELPDESK (OUTBOUND)                          ║
║                                                                  ║
║  Helpdesk ketik balasan di ticket/show                           ║
║         ↓                                                        ║
║  POST /api/tickets/{id}/messages                                 ║
║         ↓                                                        ║
║  extractBase64Images() → cid: references                         ║
║         ↓                                                        ║
║  sendTicketReply():                                              ║
║    1. Cari pesan asli di Graph (Inbox → SentItems → global)      ║
║    2. createReply draft                                          ║
║    3. PATCH draft (subject, body, to, cc)                        ║
║    4. Attach inline images (isInline=true)                       ║
║    5. Attach files                                               ║
║    6. POST /send                                                 ║
║    7. Temukan di Sent Items (retry 3x)                           ║
║    8. Update attachment IDs                                      ║
║         ↓                                                        ║
║  Simpan TicketMessage + TicketAttachment ke DB                   ║
║  Update last_agent_reply_at                                      ║
╚══════════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════════╗
║               AKSES ATTACHMENT (PROXY)                           ║
║                                                                  ║
║  Browser request GET /attachments/{id}                           ║
║         ↓                                                        ║
║  Load TicketAttachment                                           ║
║         ↙                    ↘                                   ║
║  graph_message_id ada     Tidak ada                              ║
║         ↓                    ↓                                   ║
║  Fetch dari Graph        Redirect /storage/{file_path}           ║
║         ↓                                                        ║
║  Jika 404:                                                       ║
║    Cari di Sent Items via email_message_id                       ║
║    Update DB, retry fetch                                        ║
║         ↓                                                        ║
║  Decode base64 → stream ke browser                               ║
║  Headers: Content-Type, Content-Disposition, Cache-Control       ║
╚══════════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════════╗
║               TIMEZONE (WIB / Asia/Jakarta)                      ║
║                                                                  ║
║  Graph API (UTC) ──→ Carbon::parse()->utc() ──→ DB (UTC)         ║
║                                                   ↓              ║
║                              Model access: auto-convert ke WIB   ║
║                                                   ↓              ║
║                              API response: timestamp string WIB   ║
║                                                   ↓              ║
║                              Frontend JS: toLocaleString(         ║
║                                'id-ID', { timeZone:'Asia/Jakarta'}║
║                              ) + ' WIB'                          ║
╚══════════════════════════════════════════════════════════════════╝
```

---

## 14. Checklist QA

### Email Masuk
- [ ] Email dari Gmail masuk → tiket/staging dibuat
- [ ] Email dari Outlook masuk → tiket/staging dibuat
- [ ] Thread reply dari customer cocok dengan tiket yang benar
- [ ] CC recipients disimpan di `ticket_message.cc_emails`
- [ ] Quoted text dari balasan di-strip (tidak muncul di thread)
- [ ] Email yang sama tidak diproses dua kali (dedup)

### Gambar Inline (Email)
- [ ] Gambar inline dari email Outlook muncul di thread tiket
- [ ] Gambar inline dari email Gmail muncul di thread tiket
- [ ] `cid:xxx` di HTML sudah terganti dengan URL `/storage/...`
- [ ] File gambar tersimpan di `storage/public/ticket-inline-images/`
- [ ] Gambar inline di modal staging preview tampil (data URI)

### Gambar dari Web (Reply Helpdesk)
- [ ] Copy-paste gambar di Quill editor → terkirim sebagai attachment email
- [ ] Gambar dari Quill muncul di thread tiket setelah kirim
- [ ] Gambar dari Quill muncul di email yang diterima customer
- [ ] Attachment file (PDF, Excel) berhasil dikirim dan tersimpan di DB

### Proxy Attachment
- [ ] `GET /attachments/{id}` bisa diakses oleh user yang login
- [ ] `GET /attachments/{id}` redirect jika tidak ada session (401 / redirect login)
- [ ] Jika graph_attachment_id tidak valid, fallback recovery bekerja
- [ ] Cache-Control header dikirim agar tidak re-fetch terus

### URL Relatif (Production Fix)
- [ ] TIDAK ada `http://localhost:8000/storage/` dalam `message_html` di DB
- [ ] `StagingAttachment.public_url` mengembalikan `/storage/{path}` (bukan absolute URL)
- [ ] `EmailController` menggunakan `'/storage/' . $filePath` bukan `Storage::disk('public')->url()`
- [ ] SQL check: `SELECT COUNT(*) FROM ticket_message WHERE message_html LIKE '%localhost:8000%'` → 0

### Timezone
- [ ] Timestamp pesan di thread tampil sebagai WIB (bukan UTC)
- [ ] Format: "15 Apr 2026, 14:30 WIB" (bukan "07:30 UTC")
- [ ] Email masuk: waktu pesan menggunakan `receivedDateTime` dari Graph (di-parse sebagai UTC)
- [ ] Tiket dibuat dari staging: timestamp sesuai WIB

### Staging & Approval
- [ ] Setelah approval, tiket muncul di daftar tiket
- [ ] `jarvies_status` tiket baru = `'sent it to support'`
- [ ] Attachment email tersimpan di `ticket_attachment` dengan `graph_attachment_id`
- [ ] `cid:` dalam message_html sudah terganti `/attachments/{id}`

### Auto Status PIC Assign
- [ ] Tiket unassigned → assign PIC → `jarvies_status` otomatis jadi `'in process'`
- [ ] Tiket yang sudah punya PIC → ganti PIC → `jarvies_status` TIDAK berubah

---

*Dibuat oleh: QA Team — EcoSystem-2*
*Tanggal: 2026-04-15*
