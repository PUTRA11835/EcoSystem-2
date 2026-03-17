# Panduan Web-to-Email Relay Flow — Jarvies

> **Tanggal:** 2026-03-10
> **Berlaku untuk:** Customer yang membuat tiket **tanpa** OAuth email (tidak menghubungkan akun Gmail/Outlook)
> **Tujuan:** Semua chat/balasan tetap tercatat di thread email yang sama menggunakan email helpdesk (raditya) sebagai pengirim,
> sehingga attachment dan file bisa disimpan di Microsoft Graph dan diambil kapan saja.

---

## Prinsip Utama

Ketika customer **tidak** punya OAuth email terhubung, Jarvies tidak bisa mengirim email atas nama customer.
Sebagai gantinya, **EcoSystem (helpdesk) bertindak sebagai relay** — semua pesan dari dan ke customer
dikirim FROM email helpdesk M365 (`raditya.budi@eclectic.co.id`).

```
Customer (Jarvies web) ──POST API──► EcoSystem DB
                                         │
                                         ▼
                               EcoSystem kirim email
                               FROM: raditya@eclectic.co.id
                               TO:   customer@email.com
                               SUBJECT: Ticket #XXXX: description
                                         │
                                         ▼
                               Customer reply via email
                               FROM: customer@email.com
                               TO:   raditya@eclectic.co.id
                                         │
                                         ▼
                               processInbox() tangkap reply
                               → tampil di chat Jarvies
```

---

## Alur Lengkap

### 1. Customer Submit Tiket (tanpa OAuth email)

Jarvies kirim ke EcoSystem:

```http
POST /api/staging-tickets
Content-Type: application/json
```

```json
{
  "description":        "Judul / subject tiket",
  "body":               "<p>Detail masalah customer...</p>",
  "ticket_priority":    "Medium",
  "sender_name":        "Budi Santoso",
  "submitted_by_email": "budi@perusahaan.com",
  "cc_emails":          "[\"cc1@perusahaan.com\",\"cc2@vendor.com\"]"
}
```

> **WAJIB:** `submitted_by_email` harus diisi dengan email login customer.
> EcoSystem menggunakan field ini untuk mengirim email approval ke customer.
> Jika tidak dikirim, EcoSystem akan fallback ke `customer.email` dari database.

---

### 2. Admin EcoSystem Approve → Email Otomatis Dikirim

Saat admin mem-validasi staging ticket, EcoSystem **otomatis** mengirim email approval:

```
FROM:    raditya.budi@eclectic.co.id  (helpdesk M365)
TO:      budi@perusahaan.com           (submitted_by_email)
CC:      cc1@perusahaan.com, cc2@vendor.com  (jika ada)
SUBJECT: Ticket #2603-IRNA-0007: Judul / subject tiket
BODY:    "Baik akan disampaikan dengan Nomor Ticket 2603-IRNA-0007
          Best Regards, [nama helpdesk]"
```

Setelah ini:
- `ticket.email_thread_id` diisi dengan `conversationId` dari Microsoft Graph
- Pesan approval tersimpan di `ticket_message` dengan `email_message_id` (RFC 2822 Message-ID)

---

### 3. Customer Balas via Chat Jarvies (tanpa OAuth)

Jarvies kirim ke EcoSystem:

```http
POST /api/tickets/{id}/customer-reply
Content-Type: application/json
```

```json
{
  "message_body":  "<p>Balasan customer...</p>",
  "sender_name":   "Budi Santoso",
  "sender_email":  "budi@perusahaan.com",
  "customer_id":   42,
  "skip_relay":    false,
  "channel":       "web"
}
```

> `skip_relay: false` → EcoSystem akan mengirim relay email ke customer:
>
> ```
> FROM:    raditya.budi@eclectic.co.id
> TO:      budi@perusahaan.com
> SUBJECT: Ticket #2603-IRNA-0007: Judul tiket   ← subject SAMA
> In-Reply-To: <email_message_id pesan terakhir>  ← masuk thread yang sama
> BODY:    "[Pesan dari Budi Santoso]\nBalasan customer..."
> ```

EcoSystem menyimpan `email_message_id` dari relay ini ke `ticket_message`.
Sehingga helpdesk reply berikutnya bisa menggunakan `In-Reply-To` yang benar.

---

### 4. Helpdesk (EcoSystem) Balas → Email Terkirim ke Customer

Saat helpdesk membalas dari EcoSystem:

```
FROM:    raditya.budi@eclectic.co.id
TO:      budi@perusahaan.com
CC:      cc1@perusahaan.com  (jika ada cc di thread)
SUBJECT: Ticket #2603-IRNA-0007: Judul tiket
In-Reply-To: <email_message_id dari relay customer terakhir>
```

Balasan helpdesk ini:
- Tersimpan di `ticket_message` dengan `email_message_id`-nya
- Attachment (jika ada) disimpan di Microsoft Graph, dapat diakses via `/attachments/{id}`
- Tampil di chat Jarvies via `GET /api/tickets/{id}/messages`

---

### 5. Customer Balas via Email Langsung (bukan Jarvies)

Customer membalas email dari Outlook/Gmail-nya. Email masuk ke inbox M365 raditya.

EcoSystem `processInbox()` menangkap email masuk:
- Mencocokkan thread via `conversationId`
- Membuat `ticket_message` baru dengan `channel = 'email'`
- Attachment email disimpan metadata-nya ke `ticket_attachment` (file tetap di Graph)
- Tampil otomatis di chat Jarvies

---

## Format Subject — HARUS SAMA

Semua email dalam satu thread menggunakan subject yang **identik**:

```
Ticket #2603-IRNA-0007: Judul tiket (maks 80 karakter)
```

**Jangan** tambah `Re:`, `Fwd:`, atau prefix lain. EcoSystem sudah menangani format ini.

Untuk di Jarvies — jika menampilkan subject di UI, gunakan format yang sama.

---

## Attachment

### Attachment dari Helpdesk (EcoSystem → Customer)

Helpdesk bisa attach file saat reply. File akan:
1. Diupload ke Microsoft Graph sebagai fileAttachment di draft email
2. Email dikirim via M365 (customer menerima attachment di emailnya)
3. Metadata disimpan di `ticket_attachment` (bukan file lokal)

Jarvies mengambil attachment via:
```
GET /api/tickets/{id}/messages  → setiap message punya array `attachments`
GET /attachments/{id}           → download/view file via proxy Graph
```

### Attachment dari Customer (non-OAuth, via chat Jarvies)

> **Catatan:** Saat ini `customer-reply` API belum mendukung upload file attachment untuk direlaykan via email.
> File yang customer lampirkan di chat Jarvies tersimpan lokal di EcoSystem tetapi **tidak** dikirim dalam relay email.
>
> Jika attachment di relay email dibutuhkan, ini perlu diimplementasikan di tahap selanjutnya dengan menambah
> support multipart/form-data di endpoint `POST /api/tickets/{id}/customer-reply`.

Untuk sementara: attachment dari customer (non-OAuth) bisa dikirim langsung via reply email (bukan via Jarvies chat).

---

## Yang Harus Dikirim Jarvies — Checklist

### Saat Submit Tiket (`POST /api/staging-tickets`)

```
✅ description        — judul/subject tiket (wajib)
✅ body               — isi detail tiket dalam HTML (Quill output)
✅ ticket_priority    — Very High | High | Medium | Low
✅ sender_name        — nama individual customer (bukan nama perusahaan)
✅ submitted_by_email — email login customer (wajib untuk notifikasi approval)
✅ cc_emails          — JSON array email CC (jika ada): "[\"a@x.com\"]"
```

### Saat Customer Reply (`POST /api/tickets/{id}/customer-reply`)

```
✅ message_body   — HTML konten pesan
✅ sender_name    — nama customer
✅ sender_email   — email customer (sama dengan submitted_by_email)
✅ customer_id    — ID customer
✅ skip_relay     — false  (biarkan EcoSystem kirim relay email)
✅ channel        — "web"
```

### JANGAN kirim

```
❌ skip_relay: true  — ini hanya untuk jalur OAuth email (customer kirim sendiri via Gmail/Outlook OAuth)
```

---

## Mengambil Pesan di Jarvies

```http
GET /api/tickets/{id}/messages
```

Response per pesan:
```json
{
  "id":              15,
  "sender_type":     "employee",
  "sender_name":     "Helpdesk Support",
  "channel":         "email",
  "message_body":    "Baik, kami akan segera proses...",
  "message_html":    "<p>Baik, kami akan segera proses...</p>",
  "email_message_id": "<SY4PR01MB9876@SY4PR01MB.ausprd.exchangelabs.com>",
  "cc_emails":       "[\"cc1@perusahaan.com\"]",
  "attachments": [
    {
      "id":            8,
      "file_name":     "laporan.pdf",
      "mime_type":     "application/pdf",
      "file_size":     102400,
      "is_inline":     false,
      "public_url":    "/attachments/8"
    }
  ],
  "created_at": "2026-03-10T09:30:00Z"
}
```

> Pesan dari `channel = "email"` berasal langsung dari email inbox (customer balas via email).
> Pesan dari `channel = "web"` berasal dari Jarvies chat.
> Keduanya tampil di satu thread chat yang sama.

---

## Diagram Lengkap

```
Customer (Jarvies)                    EcoSystem                      Email (M365)
─────────────────                    ─────────────                  ────────────
POST /api/staging-tickets
  description, body,        ──────►  Simpan staging_tickets
  submitted_by_email                  status: unvalidated
  cc_emails

                                     Admin approve
                                     ↓
                                     Buat ticket + first message
                                     ↓
                                     sendApprovalNotification()    ──► FROM: helpdesk
                                       ↓                               TO: customer@mail
                                       simpan email_thread_id          SUBJECT: Ticket #XXXX: ...
                                       simpan email_message_id

POST /api/tickets/{id}/customer-reply
  skip_relay: false         ──────►  Simpan ticket_message
  channel: web                       ↓
                                     sendCustomerReplyRelay()      ──► FROM: helpdesk
                                       ↓                               TO: customer@mail
                                       simpan email_message_id         In-Reply-To: <last msg id>
                                                                        SUBJECT: Ticket #XXXX: ...

                            GET /api/tickets/{id}/messages
                            ◄──────  [{id,sender_type,message,...}]

                                     processInbox() (cron/webhook)
Customer balas via email    ──────►                                ◄── FROM: customer@mail
                                     Simpan ticket_message             TO: helpdesk
                                     channel = email                   In-Reply-To: ...

                            GET /api/tickets/{id}/messages
                            ◄──────  [..., {channel:"email", message:"..."}]

                                     Helpdesk reply dari EcoSystem ──► FROM: helpdesk
                                                                        TO: customer@mail
                                                                        In-Reply-To: <last msg id>
```

---

## Referensi EcoSystem

| Fungsi | File | Method |
|---|---|---|
| Terima submit staging dari Jarvies | `StagingTicketController.php` | `store()` |
| Kirim email approval saat validasi | `StagingTicketController.php` | `sendApprovalNotification()` |
| Terima customer reply + kirim relay | `TicketMessageController.php` | `customerReply()` + `sendCustomerReplyRelay()` |
| Kirim email dari helpdesk ke customer | `EmailController.php` | `sendTicketReply()` |
| Tangkap email masuk dari M365 inbox | `EmailController.php` | `processInbox()` |
| Download/view attachment via proxy | `AttachmentController.php` | `show()` |
| List semua pesan (termasuk dari email) | `TicketMessageController.php` | `index()` |
