# Panduan Implementasi Jarvies — Ticket Tanpa OAuth Email

> **Tanggal:** 2026-03-10
> **Berlaku untuk:** Customer yang membuat tiket **tanpa** OAuth email (tidak menghubungkan akun Gmail/Outlook)
> **Tujuan:** Menjelaskan secara teknis apa yang perlu Jarvies implementasikan agar
> pesan, lampiran, dan gambar dapat dikirim dan diambil via email thread raditya (M365),
> bukan via public storage lokal.

---

## Prinsip Kerja

Karena customer tidak connect OAuth, Jarvies tidak bisa kirim email atas nama customer.
Sebagai gantinya:

1. **EcoSystem (raditya@eclectic.co.id) menjadi relay** — semua email masuk dan keluar
   melewati inbox M365 raditya.
2. **Attachment dan gambar disimpan di Microsoft Graph**, bukan di server lokal —
   tidak ada lagi URL `/storage/...` yang bisa 403.
3. **Thread email tetap konsisten** — semua pesan menggunakan subject yang sama
   `Ticket #XXXX: description`, dengan header `In-Reply-To` yang mengikat pesan ke satu thread.

---

## Alur Lengkap

```
[Jarvies] Customer submit tiket
    │  POST /api/staging-tickets
    │  { description, body, submitted_by_email, sender_name, ... }
    ▼
[EcoSystem] Simpan staging ticket
    │
    ▼ Admin approve
[EcoSystem] Buat ticket + first message
    │
    ├─► Kirim email otomatis ke customer:
    │     FROM: raditya@eclectic.co.id
    │     TO:   submitted_by_email (putrapalampangt@gmail.com)
    │     SUBJECT: Ticket #2603-IRNA-0021: description
    │     BODY:  "Baik akan disampaikan dengan Nomor Ticket ..."
    │     → Simpan conversationId → ticket.email_thread_id ✅
    │     → Simpan internetMessageId → ticket_message.email_message_id ✅
    │
    ▼
[Jarvies] Customer kirim chat reply
    │  POST /api/tickets/{id}/customer-reply
    │  { message_body, sender_email, skip_relay: false, channel: "web" }
    ▼
[EcoSystem] Simpan message ke DB
    │
    ├─► Relay: kirim email ke customer
    │     FROM: raditya@eclectic.co.id
    │     TO:   sender_email (putrapalampangt@gmail.com)
    │     SUBJECT: Ticket #2603-IRNA-0021: description
    │     In-Reply-To: {email_message_id dari pesan sebelumnya}
    │     BODY:  "[Pesan dari Budi Santoso via Jarvies]\n{isi pesan customer}"
    │     → Customer menerima pesannya sendiri via email ← TUJUAN UTAMA
    │     → Thread M365 tetap hidup
    │
    ▼
[Customer] Balas email dari Outlook/Gmail
    │
    ▼
[EcoSystem processInbox] Tangkap reply
    │  → Buat ticket_message baru (channel = 'email')
    │  → Attachment email disimpan di Graph (bukan lokal)
    ▼
[Jarvies] GET /api/tickets/{id}/messages
    └─► Pesan email masuk tampil di chat Jarvies ✅
```

---

## Yang Perlu Diimplementasikan di Jarvies

### 1. Saat Submit Tiket (POST /api/staging-tickets)

**WAJIB kirim field berikut:**

```json
{
  "description":        "Judul tiket — ditampilkan sebagai subject email",
  "body":               "<p>Detail lengkap masalah customer (HTML dari Quill)...</p>",
  "ticket_priority":    "Medium",
  "sender_name":        "Budi Santoso",
  "submitted_by_email": "putrapalampangt@gmail.com",
  "cc_emails":          "[\"cc@perusahaan.com\"]"
}
```

> **Kenapa `submitted_by_email` wajib?**
> EcoSystem menggunakan field ini untuk mengirim email approval ke customer.
> Jika tidak ada, email approval tidak terkirim → `email_thread_id` tidak terbentuk
> → relay tidak bisa berjalan → semua attachment customer harus lewat lokal (rawan 403).

### 2. Saat Customer Reply di Chat (POST /api/tickets/{id}/customer-reply)

```json
{
  "message_body":  "<p>Isi balasan customer...</p>",
  "sender_name":   "Budi Santoso",
  "sender_email":  "putrapalampangt@gmail.com",
  "customer_id":   42,
  "skip_relay":    false,
  "channel":       "web"
}
```

> **`skip_relay: false`** → EcoSystem akan relay email ke customer dengan isi pesan customer tersebut.
> Customer menerima email dari raditya@eclectic.co.id berisi pesannya sendiri.
> Ini membuat thread M365 tetap aktif dan customer bisa balas via email.

### 3. Indikator di UI Jarvies

Gunakan `email_thread_id` dari `GET /api/tickets/{id}` untuk menampilkan indikator:

```js
const ticket = await fetch(`/api/tickets/${ticketId}`).then(r => r.json());

if (ticket.email_thread_id) {
  // Thread email aktif — pesan dikirim via email
  showIndicator("Thread email aktif — balasan helpdesk dikirimkan ke email Anda");
} else if (ticket.channel === 'email') {
  // Tiket dari email langsung
  showIndicator("Balasan akan dikirim ke tim support via Email");
} else {
  // Thread belum terbentuk (menunggu approval atau email gagal)
  showIndicator("Balasan hanya tampil di Jarvies — tidak ada email yang dikirim");
}
```

---

## Attachment dari Helpdesk (Cara Jarvies Mengambil)

Setelah EcoSystem reply dengan attachment, file disimpan di M365 Graph.
Jarvies mengambilnya via API:

```
GET /api/tickets/{id}/messages
```

Setiap message punya field `attachments`:

```json
{
  "id": 15,
  "sender_type": "employee",
  "channel": "email",
  "message_body": "Terlampir dokumen untuk referensi.\n-Ivan",
  "attachments": [
    {
      "id": 8,
      "file_name": "laporan-maret.pdf",
      "mime_type": "application/pdf",
      "file_size": 102400,
      "is_inline": false,
      "url": "/attachments/8"
    }
  ]
}
```

**URL attachment:** `/attachments/{id}` — ini proxy ke Microsoft Graph, bukan file lokal.
- Tidak perlu session khusus dari Jarvies untuk akses (proxy dihandle EcoSystem)
- `is_inline: true` → gambar sudah ada di `message_html` (render inline, jangan render ulang sebagai list)
- `is_inline: false` → tampilkan sebagai daftar file yang bisa diunduh

---

## Attachment dari Customer (via Jarvies Chat, tanpa OAuth)

> **Catatan implementasi saat ini:**
> Endpoint `POST /api/tickets/{id}/customer-reply` belum mendukung upload file.
> Customer yang ingin mengirim file/attachment bisa:
> 1. Membalas email relay yang diterima dari raditya — attachment ikut serta di reply email
>    → EcoSystem `processInbox()` menangkap attachment → tampil di chat helpdesk
> 2. Atau menunggu fitur upload di customer-reply API (future implementation)

---

## Format Subject Email — HARUS KONSISTEN

```
Ticket #2603-IRNA-0021: description tiket (maks 80 karakter)
```

EcoSystem sudah menggunakan format ini secara otomatis di semua email:
- Approval notification
- Relay customer reply
- Helpdesk reply

Jarvies **tidak perlu** set subject secara manual untuk relay flow — semua dihandle EcoSystem.

---

## Alur Attachment Helpdesk

```
Helpdesk di EcoSystem kirim reply + file attachment
    │
    ▼
sendEmailReply() → Microsoft Graph API
    │  1. Buat draft email di inbox raditya
    │  2. Upload file ke draft sebagai fileAttachment
    │  3. Send draft → email masuk ke inbox customer
    │  4. Simpan metadata (graph_attachment_id, graph_message_id) ke DB
    │     TIDAK ada file di server lokal
    ▼
Customer menerima email + attachment di Outlook/Gmail mereka

[Jarvies] GET /api/tickets/{id}/messages
    │  → attachments[].url = "/attachments/8"
    ▼
GET /attachments/8
    │  → EcoSystem proxy fetch dari Graph API
    │  → Stream file ke browser (in-memory, tidak disimpan lokal)
    ▼
Customer/Jarvies bisa download file ✅
```

---

## Checklist Implementasi Jarvies

```
SUBMIT TIKET (tanpa OAuth)
□ Kirim submitted_by_email (email login customer) — WAJIB untuk relay
□ Kirim body (HTML isi tiket dari Quill editor)
□ Kirim sender_name (nama individual, bukan nama perusahaan)
□ Kirim cc_emails jika ada (JSON string array)

CUSTOMER REPLY (tanpa OAuth)
□ Kirim skip_relay: false
□ Kirim channel: "web"
□ Kirim sender_email (sama dengan submitted_by_email)
□ Kirim sender_name (nama individual customer)
□ Kirim message_body (HTML dari Quill)

TAMPIL PESAN DI CHAT
□ Ambil messages via GET /api/tickets/{id}/messages
□ Render message_html untuk pesan dengan HTML (bukan message_body)
□ Render attachments dengan URL /attachments/{id}
□ Untuk is_inline: true → skip render di attachment list (sudah ada di message_html)
□ Untuk is_inline: false → tampilkan sebagai file yang bisa diunduh/preview

INDIKATOR EMAIL THREAD
□ Cek ticket.email_thread_id untuk tampilkan status relay
□ Refresh indikator setelah customer reply (email_thread_id bisa baru terbentuk)
```

---

## Referensi Endpoint

| Endpoint | Method | Kegunaan |
|---|---|---|
| `/api/staging-tickets` | POST | Submit tiket baru (tanpa OAuth) |
| `/api/staging-tickets/{id}` | GET | Cek status validasi |
| `/api/tickets/{id}` | GET | Data tiket + `email_thread_id` |
| `/api/tickets/{id}/messages` | GET | Semua pesan + attachments |
| `/api/tickets/{id}/customer-reply` | POST | Kirim balasan customer (non-OAuth) |
| `/attachments/{id}` | GET | Download/view attachment via proxy Graph |
