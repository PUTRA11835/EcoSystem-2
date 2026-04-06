# Panduan Email Threading — EcoSystem & Jarvies

> **Tanggal:** 2026-03-06
> **Tujuan:** Memastikan semua balasan (dari EcoSystem maupun Jarvies) masuk ke thread email yang sama, sehingga customer melihat percakapan tiket sebagai satu rangkaian email.

---

## Konsep Dasar Email Threading

Microsoft Graph (M365) menggunakan dua identifier untuk mengelompokkan email dalam satu thread:

| Identifier | Kolom di DB | Keterangan |
|---|---|---|
| `conversationId` | `ticket.email_thread_id` | ID thread di M365 — semua email dalam satu thread punya ID ini |
| `internetMessageId` | `ticket_message.email_message_id` | ID unik tiap pesan email (format: `<xxx@xxx.com>`) |

Untuk membalas email dalam thread yang sama, Graph membutuhkan `internetMessageId` pesan sebelumnya (digunakan sebagai `In-Reply-To` header). Graph lalu secara otomatis mengisi header `References` dan mempertahankan `conversationId`.

---

## Alur Lengkap dari Awal hingga Balasan

### Skenario 1 — Customer submit dari Jarvies (web only, tanpa email)

```
1. Customer submit → POST /api/staging-tickets
   - staging dibuat, channel = "web"
   - ticket.email_thread_id = NULL (belum ada thread)

2. Helpdesk approve → POST /api/staging-tickets/{id}/approve
   - ticket dibuat (status: open)
   - EcoSystem kirim email approval ke customer:
       Subject: "Ticket #2603-IRNA-0001: Judul Tiket"
   - Graph mengembalikan conversationId dari email yang dikirim
   - conversationId disimpan ke ticket.email_thread_id
   - email_message_id pesan approval disimpan ke ticket_message.email_message_id

3. Helpdesk reply dari EcoSystem
   - cek: ticket.email_thread_id tidak kosong → kirim via email
   - ambil email_message_id dari pesan terakhir sebagai inReplyTo
   - Graph createReply → thread tetap sama, subject tetap sama

4. Customer reply via email
   - email masuk ke inbox M365
   - processInbox: match conversationId → ticket.email_thread_id
   - pesan ditambahkan ke ticket yang sama
```

### Skenario 2 — Customer submit dari Jarvies + connect email (email dikirim ke helpdesk)

```
1. Jarvies kirim email ke helpdesk M365
   - Subject: "Judul Tiket" (atau apapun yang customer input)
   - Email masuk ke inbox helpdesk

2. processInbox fetch email
   - tidak ada ticket terkait → buat staging (channel = "email")
   - staging.email_thread_id = conversationId email masuk
   - staging.email_message_id = internetMessageId email masuk

3. Helpdesk approve
   - ticket dibuat
   - ticket.email_thread_id = staging.email_thread_id (sudah ada dari awal)
   - EcoSystem kirim email approval sebagai REPLY dari email customer:
       inReplyTo = staging.email_message_id
       Subject: "Ticket #2603-IRNA-0001: Judul Tiket"
   - Thread sudah terbentuk sejak email pertama customer

4. Balasan selanjutnya sama seperti Skenario 1 langkah 3 & 4
```

### Skenario 3 — Customer kirim email langsung ke helpdesk (tanpa Jarvies)

```
Sama dengan Skenario 2 langkah 2 dst.
```

---

## Kolom-Kolom Penting di Database

### Tabel `ticket`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `email_thread_id` | varchar, nullable | conversationId M365 — kunci utama untuk match thread |
| `channel` | varchar | `"email"` atau `"web"` |

### Tabel `ticket_message`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `email_message_id` | varchar, nullable | internetMessageId tiap pesan email |
| `email_in_reply_to` | varchar, nullable | internetMessageId pesan yang dibalas |
| `channel` | varchar | `"email"` atau `"web"` |
| `cc_emails` | json, nullable | CC dari email asli (format: `[{name, address}]`) |

### Tabel `staging_tickets`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `email_thread_id` | varchar, nullable | conversationId dari email masuk |
| `email_message_id` | varchar, nullable | internetMessageId email masuk (untuk inReplyTo saat approve) |
| `graph_message_id` | varchar, nullable | ID message di Graph (untuk fetch attachment) |

---

## Logika EcoSystem — Cara Mengirim Reply Agar Masuk Thread

### `EmailController::sendTicketReply()`

```
Input:
  - toEmail        : email tujuan
  - subject        : "Ticket #XXXX: Judul"
  - body           : HTML konten pesan
  - inReplyTo      : internetMessageId pesan TERAKHIR di thread (nullable)
  - noRePrefix     : true → subject tidak diberi "Re: "

Proses:
  1. Jika inReplyTo ada:
     a. Cari message di Graph dengan filter internetMessageId = inReplyTo
     b. Jika ditemukan → POST /messages/{id}/createReply
        → Graph otomatis set In-Reply-To + References + pertahankan conversationId
     c. Patch body + subject ke draft tersebut
     d. Send draft

  2. Jika inReplyTo tidak ada (atau message tidak ditemukan di Graph):
     → Buat email baru (draft baru)
     → conversationId baru akan di-generate oleh M365

  3. Simpan conversationId dari response ke ticket.email_thread_id
     (jika belum ada)

Return:
  - graph_message_id  : ID message yang terkirim di Graph
  - conversation_id   : conversationId dari thread ini
```

### `TicketMessageController` — Kapan email dikirim

```php
// Kondisi kirim email reply:
if (
    $request->message_type === 'reply'    // bukan internal note
    && $senderType === 'employee'          // hanya helpdesk/agent yang kirim
    && !empty($ticket->email_thread_id)   // tiket punya thread email
) {
    $this->sendEmailReply($ticket, $message, $uploadedFiles);
}
```

**Catatan:** Kondisinya adalah `email_thread_id` tidak kosong — bukan `channel === 'email'`. Artinya tiket dari web Jarvies pun akan mengirim email jika sudah ada thread (setelah approval email dikirim).

### Pengambilan `inReplyTo`

```php
// Ambil email_message_id dari pesan email TERAKHIR di tiket
$lastEmailMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
    ->where('channel', 'email')
    ->whereNotNull('email_message_id')
    ->orderBy('created_at', 'desc')
    ->first();

$inReplyTo = $lastEmailMsg?->email_message_id;
```

---

## Logika EcoSystem — Cara Menerima Reply dari Customer

### `EmailController::processInbox()`

```
Fetch inbox M365 (unread + 60 menit terakhir)

Untuk setiap email masuk:
  1. Ambil conversationId + internetMessageId

  2. Cari tiket terkait:
     a. Ticket::where('email_thread_id', conversationId)   ← prioritas utama
     b. Jika tidak ada, cari via email_message_id di ticket_messages

  3. Jika tiket ditemukan:
     → Tambah TicketMessage baru ke tiket tersebut
     → Update ticket.last_customer_reply_at

  4. Jika tidak ada tiket terkait:
     → Buat StagingTicket baru (untuk divalidasi helpdesk)

  5. Tandai email sebagai read di M365
```

---

## Yang Perlu Dilakukan Jarvies

### A. Saat Customer Submit Tiket (POST /api/staging-tickets)

Kirim `sender_name` berisi nama individual user (bukan nama perusahaan):

```json
{
  "description": "Judul atau deskripsi masalah",
  "ticket_priority": "High",
  "sender_name": "Putra Palampang"
}
```

> `sender_name` akan digunakan sebagai nama pengirim di chat thread EcoSystem.

---

### B. Saat Menampilkan Chat Tiket di Jarvies

Gunakan `sender_name` dari response `GET /api/tickets/{id}/messages`:

```json
{
  "id": 1,
  "sender_type": "customer",
  "sender_name": "Putra Palampang",
  "sender_email": "putra@indoraya.com",
  "channel": "email",
  "message": "...",
  "message_html": "<div>...</div>",
  "cc_emails": "[{\"name\":\"Budi\",\"address\":\"budi@example.com\"}]",
  "created_at": "2026-03-06T08:00:00"
}
```

**Aturan rendering:**

| Kondisi | Tampilkan |
|---|---|
| `channel = "email"` dan `message_html` tidak null | Render `message_html` sebagai HTML |
| `channel = "email"` dan `message_html` null | Fallback ke `message` (plain text) |
| `channel = "web"` | Render `message` sebagai HTML (dari Quill editor) |
| `is_internal_note = true` | **JANGAN tampilkan ke customer** |

---

### C. Saat Customer Reply dari Jarvies — Endpoint Baru ⚠️ WAJIB

**Jangan gunakan** `POST /api/tickets/{id}/messages` untuk customer reply — endpoint itu khusus untuk helpdesk (EcoSystem).

Gunakan endpoint baru yang sudah disediakan:

```
POST /api/tickets/{ticketId}/customer-reply
```

**Body:**

```json
{
  "message_body":  "<p>Baik, saya coba restart dulu</p>",
  "sender_name":   "Putra Palampang",
  "sender_email":  "putrapalampangt@gmail.com",
  "customer_id":   5
}
```

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `message_body` | string (HTML) | Ya | Isi pesan dari Quill/rich text editor |
| `sender_name` | string | Ya | Nama individual customer yang sedang login |
| `sender_email` | string (email) | Ya | Email customer |
| `customer_id` | integer | Tidak | ID customer dari session (jika ada) |
| `skip_relay` | boolean | Tidak | `true` = Jarvies sudah kirim email via OAuth customer, EcoSystem tidak perlu relay. `false` (default) = EcoSystem kirim relay dari helpdesk M365 |
| `channel` | string | Tidak | `"email"` jika dikirim via OAuth customer, `"web"` (default) jika dari form biasa |

**Response sukses (201):**

```json
{
  "success": true,
  "data": {
    "id": 42,
    "ticket_id": 7,
    "sender_type": "customer",
    "sender_name": "Putra Palampang",
    "message_body": "Baik, saya coba restart dulu",
    "channel": "web",
    "created_at": "2026-03-06T10:05:00.000000Z"
  }
}
```

**Yang dilakukan EcoSystem secara otomatis:**
1. Simpan pesan ke DB dengan `sender_type = 'customer'`, `channel` sesuai yang dikirim
2. Update `ticket.last_customer_reply_at`
3. Jika `skip_relay = false` (default) dan `ticket.email_thread_id` ada → kirim **relay email** dari helpdesk M365 ke customer dalam thread yang sama
4. Jika `skip_relay = true` → pesan hanya disimpan ke DB, tidak ada relay (Jarvies sudah kirim email sendiri)

> **Catatan:** Jika `ticket.email_thread_id` kosong, relay tidak dikirim terlepas dari nilai `skip_relay`.

---

**Kapan pakai `skip_relay`:**

| Kondisi | `skip_relay` | `channel` |
|---|---|---|
| Customer tidak connect OAuth email | `false` (default) | `"web"` |
| Customer connect OAuth email → Jarvies kirim email sendiri | `true` | `"email"` |

---

### D. Menampilkan Status Thread di Jarvies

Dari response `GET /api/tickets/{id}`:

```json
{
  "ticket_id": 54,
  "ticket_number": "2603-IRNA-0001",
  "channel": "web",
  "email_thread_id": "AAQkADk5..."
}
```

| `email_thread_id` | Artinya |
|---|---|
| tidak null | Thread email aktif — semua reply sudah terhubung via email |
| null | Tiket hanya via web chat — belum ada email thread (jarang terjadi setelah fitur approval email aktif) |

Jarvies bisa gunakan `email_thread_id` untuk menampilkan indikator "thread email aktif" di UI tiket.

---

## Ringkasan Alur Threading (Updated)

```
Customer submit (Jarvies)
    ↓
POST /api/staging-tickets  (sertakan sender_name)
    ↓
Staging ticket dibuat
    ↓
Helpdesk approve (EcoSystem)
    ↓
Ticket dibuat + Email approval dikirim ke customer
Subject: "Ticket #XXXX: Judul"
    ↓
conversationId disimpan → ticket.email_thread_id
    ↓
─────────────────────────────────────────────────
[Balasan Selanjutnya — Semua Jalur]
─────────────────────────────────────────────────
    ↓
Helpdesk reply (EcoSystem chat)
→ cek email_thread_id → kirim email via createReply → same thread
    ↓
Customer reply via EMAIL langsung
→ processInbox match conversationId → ticket yang sama
→ pesan masuk ke thread chat EcoSystem + Jarvies
    ↓
Customer reply via Jarvies (web chat)
→ POST /api/tickets/{id}/customer-reply
→ EcoSystem simpan pesan (channel=web)
→ Jika ada email_thread_id → relay email via createReply → same thread
→ Customer dapat konfirmasi email + thread tetap hidup
```

---

## Referensi File EcoSystem

| Fungsi | File |
|---|---|
| Kirim email reply (threading logic) | `app/Http/Controllers/EmailController.php` → `sendTicketReply()` |
| Terima email dari inbox (match thread) | `app/Http/Controllers/EmailController.php` → `processInbox()` |
| Trigger email saat helpdesk reply | `app/Http/Controllers/TicketMessageController.php` → `sendEmailReply()` |
| **Customer reply dari Jarvies + relay** | **`app/Http/Controllers/TicketMessageController.php` → `customerReply()`** |
| Kirim approval email + simpan conversationId | `app/Http/Controllers/StagingTicketController.php` → `sendApprovalNotification()` |
| Promote staging → ticket | `app/Services/StagingTicketService.php` → `approve()` |
