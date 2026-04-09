# JARVIES ↔ EcoSystem: Staging Ticket Body & Attachment Fix

> **Tanggal:** 2026-04-09
> **Scope:** Submit tiket via Jarvies (web form / `POST /jarvies/staging-tickets`) → tampilan modal validasi di EcoSystem Staging

---

## 1. Masalah yang Diperbaiki

### 1.1 Body tidak muncul di modal validasi
- **Root cause:** `formatStaging()` di `StagingTicketController` tidak menyertakan field `body` dalam response API. Field `body` tersimpan di DB (`staging_tickets.body`) tapi tidak pernah dikirim ke frontend.
- **Fix:** Tambahkan `'body' => $s->body` ke array return `formatStaging()`.

### 1.2 Attachment tidak muncul di modal validasi
- **Root cause:**
  1. `formatStaging()` tidak menyertakan relasi `attachments`.
  2. `show()` tidak eager-load relasi `attachments`.
- **Fix:**
  1. `show()` → tambah `'attachments'` ke `with([...])`.
  2. `formatStaging()` → tambah blok attachments yang map ke array `[id, original_name, file_size, mime_type, url]`.

### 1.3 Modal web ≠ modal email
- **Root cause:** `fillModal()` di `staging/index.blade.php` punya dua path terpisah — email menampilkan iframe + CC, web hanya menampilkan description sebagai plain text.
- **Fix:** Unifikasi:
  - Kedua channel sekarang menggunakan **iframe** untuk render body (`email_body_html` untuk email, `body` untuk web).
  - CC ditampilkan di meta strip untuk **kedua** channel (bukan hanya email).
  - Attachments (web uploads dari Jarvies) ditampilkan sebagai daftar file yang bisa didownload.
  - Fallback ke plain text description jika `body` kosong.

---

## 2. Payload yang Dikirim Jarvies ke EcoSystem

### Endpoint
```
POST /jarvies/staging-tickets
Header: X-Api-Key: <ecosystem_api_key>
Content-Type: multipart/form-data  (jika ada attachment)
            atau application/json   (tanpa attachment)
```

### Field yang Diterima EcoSystem

| Field | Type | Required | Keterangan |
|---|---|---|---|
| `description` | string | ✅ | Subject / judul singkat tiket (maks 5000 char) |
| `body` | string | ❌ | Isi lengkap tiket — HTML atau plain text. **Inilah yang ditampilkan di iframe modal validasi.** |
| `ticket_priority` | enum | ❌ | `Very High` / `High` / `Medium` / `Low`. Default: `Medium` |
| `customer_id` | integer | ✅ | ID customer di EcoSystem |
| `contact_id` | integer | ❌ | ID contact person (untuk referensi, belum diproses lebih lanjut) |
| `sender_name` | string | ❌ | Nama pengirim (contact person) |
| `submitted_by_email` | email | ❌ | Email login customer |
| `cc_emails` | string (JSON) | ❌ | JSON array: `["a@x.com","b@y.com"]` atau `[{"name":"A","address":"a@x.com"}]` |
| `name` | string | ❌ | Nama contact person (alternatif sender_name) |
| `no_hp` | string | ❌ | Nomor HP — ditampilkan di meta strip modal |
| `module` | string | ❌ | Nama modul terkait — ditampilkan di meta strip modal |
| `client` | string | ❌ | Nama client akhir — ditampilkan di meta strip modal |
| `attachments[]` | file[] | ❌ | File attachment (multipart). Maks 10MB/file. Ekstensi: pdf, doc, docx, jpg, jpeg, png, xlsx, xls, zip |

### Contoh Request (JSON, tanpa attachment)
```json
POST /jarvies/staging-tickets
X-Api-Key: your_api_key_here
Content-Type: application/json

{
  "description": "Error saat generate laporan bulanan",
  "body": "<p>Halo,</p><p>Kami mengalami error ketika menekan tombol <strong>Generate</strong> di modul Laporan. Pesan error: <em>Internal Server Error 500</em>.</p><p>Screenshot terlampir.</p>",
  "ticket_priority": "High",
  "customer_id": 42,
  "sender_name": "Budi Santoso",
  "submitted_by_email": "budi@client.com",
  "cc_emails": "[\"supervisor@client.com\",\"it@client.com\"]",
  "no_hp": "081234567890",
  "module": "Laporan Bulanan"
}
```

### Contoh Request (multipart, dengan attachment)
```
POST /jarvies/staging-tickets
X-Api-Key: your_api_key_here
Content-Type: multipart/form-data

description=Error generate laporan
body=<p>Detail error...</p>
ticket_priority=High
customer_id=42
sender_name=Budi Santoso
attachments[]=@screenshot.png
attachments[]=@error_log.txt
```

### Response Sukses (201)
```json
{
  "success": true,
  "id": 123,
  "message": "Staging ticket created successfully"
}
```

---

## 3. Alur Data

```
Jarvies                     EcoSystem
  │                              │
  │  POST /jarvies/staging-tickets
  │ ─────────────────────────────►
  │                              │
  │                         jarviesStore()
  │                              │─ validate payload
  │                              │─ decode cc_emails (JSON string → array)
  │                              │─ StagingTicketService::createFromWeb()
  │                              │    → INSERT staging_tickets (channel='web')
  │                              │─ foreach attachments → store to disk
  │                              │    → INSERT staging_attachments
  │                              │
  │  { success:true, id:123 }   │
  │ ◄─────────────────────────────
  │                              │
                                 │
Admin/Helpdesk buka Staging UI  │
  │  GET /api/staging-tickets/123
  │ ─────────────────────────────►
  │                              │─ load with(['attachments'])
  │                              │─ formatStaging() → include body + attachments
  │ ◄─────────────────────────────
  │  { id, description, body,   │
  │    cc_emails, attachments,  │
  │    channel:'web', ... }     │
  │                              │
Modal fillModal():               │
  - Meta strip: From, Date, CC, Subject
  - iframe srcdoc = s.body (HTML body dari Jarvies)
  - Attachments list: download links
  - Validation panel: pilih Type + Priority
  - Footer: Cancel | Reject | Approve & Create Ticket
```

---

## 4. Hal Penting untuk Jarvies

### 4.1 Field `body` WAJIB diisi
Field `body` adalah konten yang ditampilkan di modal validasi EcoSystem (dalam iframe). Tanpa `body`, modal hanya menampilkan `description` sebagai plain text fallback. **Selalu kirim `body` dengan konten HTML atau minimal plain text yang detail.**

### 4.2 Format `cc_emails`
EcoSystem menerima dua format:
- Array email string: `["a@x.com","b@y.com"]`
- Array object: `[{"name":"Nama","address":"email@x.com"}]`

Encode sebagai JSON string dalam body request:
```js
cc_emails: JSON.stringify(selectedCCs)
```

### 4.3 Attachment via multipart
Jika ada attachment, request harus `multipart/form-data`. Field `attachments[]` (array). EcoSystem menyimpan ke `storage/app/public/staging_attachments/{id}/` dan mencatat ke tabel `staging_attachments`.

### 4.4 `customer_id` harus valid
EcoSystem memvalidasi `customer_id` harus ada di tabel `customer`. Jika tidak ditemukan, request akan gagal 422. Pastikan customer sudah terdaftar di EcoSystem sebelum submit tiket.

### 4.5 API Key Authentication
Header `X-Api-Key` wajib. Key dikonfigurasi di `config/ecosystem.php` atau `.env`:
```
ECOSYSTEM_API_KEY=your_key_here
```
Middleware `CheckApiKey` memvalidasi setiap request ke `/jarvies/*`.

---

## 5. File yang Diubah

| File | Perubahan |
|---|---|
| `app/Http/Controllers/StagingTicketController.php` | `show()` — tambah `'attachments'` ke eager load; `formatStaging()` — tambah `body` dan `attachments` ke response |
| `resources/views/staging/index.blade.php` | `fillModal()` — unifikasi tampilan email/web: iframe untuk body, CC di web meta strip, attachments list; `renderFooter()` — standardisasi button style |

---

## 6. Checklist Validasi

- [ ] Jarvies mengirim field `body` saat customer submit tiket
- [ ] Modal validasi EcoSystem menampilkan body dalam iframe (web & email sama)
- [ ] CC muncul di meta strip untuk tiket via web (Jarvies) jika diisi
- [ ] Attachment yang diupload via Jarvies tampil sebagai daftar file downloadable di modal
- [ ] Approve tetap berjalan normal setelah perubahan
- [ ] Reject tetap berjalan normal setelah perubahan
