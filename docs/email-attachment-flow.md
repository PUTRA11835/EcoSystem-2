# Panduan Attachment & Gambar Email — EcoSystem

> **Tanggal:** 2026-03-09
> **Tujuan:** Menjelaskan alur lengkap attachment dan gambar inline yang dikirim maupun diterima via email (Microsoft Graph API).

---

## Prinsip Utama

**File TIDAK pernah disimpan di server lokal** (untuk email). Semua attachment disimpan di Microsoft Graph (M365) dan diambil on-demand via proxy route saat dibutuhkan. Hanya metadata (nama file, ukuran, MIME type, ID di Graph) yang disimpan ke database.

Pengecualian: attachment dari reply internal note (non-email ticket) disimpan lokal di `storage/app/public/ticket-attachments/{ticketId}/`.

---

## Kolom Database — `ticket_attachment`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `graph_message_id` | varchar, nullable | ID pesan di Graph (`/messages/{id}`) — inbox atau sent items |
| `graph_attachment_id` | varchar, nullable | ID attachment di Graph (`/attachments/{id}`) |
| `content_id` | varchar, nullable | CID untuk inline image (`<img src="cid:xxx">`) |
| `is_inline` | boolean | `true` = inline image (sudah muncul di `message_html`), `false` = attachment biasa |
| `file_name` | varchar | Nama file asli |
| `file_size` | integer | Ukuran file (bytes) |
| `mime_type` | varchar | MIME type (`image/png`, `application/pdf`, dll) |
| `file_path` | varchar, nullable | Path lokal (record lama saja — sebelum strategi berubah) |
| `attachment_type` | varchar | `image`, `pdf`, `document`, `spreadsheet`, `archive`, `file` |

**`public_url` accessor** (di model `TicketAttachment`):
```
graph_message_id + graph_attachment_id ada → /attachments/{id}  (proxy Graph)
file_path ada                               → /storage/...       (lokal lama)
fallback                                    → link_url dari DB
```

---

## A. Attachment Masuk (Email Customer → Helpdesk)

### Alur

```
Customer kirim email dengan attachment ke helpdesk M365
    ↓
processInbox() fetch email dari inbox
    ↓
TicketMessage dibuat (channel = 'email')
    ↓
Jika hasAttachments = true ATAU body mengandung 'cid:'
    → storeEmailAttachments() dipanggil
    ↓
Fetch /users/{sender}/messages/{graphMsgId}/attachments dari Graph
    ↓
Untuk setiap attachment (hanya fileAttachment):
    → Simpan metadata ke ticket_attachment (TANPA download file)
    → graph_message_id = ID pesan di Graph (inbox)
    → graph_attachment_id = ID attachment di Graph
    → Jika punya contentId → masuk cidMap
    ↓
Jika ada cidMap (inline images):
    → replaceCidReferences() ganti 'cid:xxx' di message_html
      menjadi '/attachments/{db_id}'
    → message_html di-update ke DB
```

### Kenapa Tidak Perlu `$select` untuk Attachments

Graph API tidak mendukung `$select` untuk properti derived type `fileAttachment` (seperti `contentId`, `contentBytes`). Semua field attachment di-fetch sekaligus tanpa filter:

```
GET /users/{sender}/messages/{graphMsgId}/attachments
```

`contentBytes` (base64 file) ikut ter-fetch tapi TIDAK disimpan — hanya metadata yang diambil dari response JSON.

### Inline Image (CID)

Email client memasukkan gambar inline sebagai attachment dengan `contentId` (CID). Di body HTML email, gambar tersebut direferensikan sebagai `<img src="cid:xxx">`.

EcoSystem mengganti semua referensi `cid:xxx` di `message_html` dengan URL proxy:
```
cid:xxx  →  /attachments/{db_id}
```

Sehingga gambar inline tampil di chat tanpa perlu download ke server.

**Flag `is_inline`:**
- `true` → gambar sudah ada di dalam `message_html` — JANGAN render ulang sebagai thumbnail
- `false` → attachment biasa — tampilkan sebagai daftar file yang bisa diunduh

---

## B. Attachment Keluar (Helpdesk Reply → Customer via Email)

### Alur

```
Helpdesk klik Send di EcoSystem dengan attachment
    ↓
TicketMessageController@store() → sendEmailReply()
    ↓
EmailController@sendTicketReply(files=[...])
    ↓
1. Buat draft (createReply atau message baru) di Graph
2. Lampirkan setiap file sebagai fileAttachment ke draft:
   POST /users/{sender}/messages/{draftId}/attachments
   Body: { contentBytes: base64, name, contentType }
   File dibaca ke memori → base64 → langsung ke Graph (TIDAK disimpan lokal)
3. Ambil internetMessageId dari draft sebelum send
4. Send draft: POST /users/{sender}/messages/{draftId}/send
    ↓
Kembali ke sendEmailReply():
    → Simpan metadata attachment ke ticket_attachment
      graph_message_id = draftId (message yang dikirim)
      graph_attachment_id = ID attachment dari response Graph
```

### Gambar Inline dari Quill Editor

Jika helpdesk paste atau insert gambar inline di Quill editor, gambar tersebut ada dalam body HTML sebagai `data URI`:
```html
<img src="data:image/png;base64,iVBORw0...">
```

`extractBase64Images()` dipanggil sebelum send untuk:
1. Ganti semua `data:image/...;base64,...` dengan `cid:{uuid}@ecosys`
2. Simpan gambar tersebut sebagai array `inlineImages`
3. Upload setiap inline image ke draft sebagai `fileAttachment` dengan `isInline: true` dan `contentId`

Hasilnya: email diterima customer dengan gambar inline yang proper (bukan data URI yang diblok email client).

---

## C. Proxy Route — Ambil File dari Graph

```
GET /attachments/{id}   (middleware: CheckAuthToken)
```

**Controller:** `AttachmentController@show()`

**Alur:**
```
Request masuk → wajib login (session('user'))
    ↓
Ambil record TicketAttachment dari DB
    ↓
Jika graph_message_id + graph_attachment_id ada:
    → Fetch dari Graph:
      GET /users/{sender}/messages/{graph_message_id}/attachments/{graph_attachment_id}
    → Decode base64 contentBytes
    → Stream ke browser dengan Content-Type dan Content-Disposition
      is_inline = true  → 'inline'    (browser tampilkan langsung)
      is_inline = false → 'attachment' (browser paksa download)

Jika hanya file_path ada (record lama):
    → Redirect ke /storage/{file_path}
```

**Header response:**
```
Content-Type: image/png (atau sesuai mime_type)
Content-Disposition: inline; filename="screenshot.png"
Cache-Control: private, max-age=3600
```

---

## D. Reprocess Attachment

Jika ada pesan email yang masuk sebelum fitur attachment aktif (atau attachment gagal diproses):

```
POST /api/email/reprocess-attachments/{messageId}
```

- Cari Graph message berdasarkan `email_message_id` yang tersimpan di `ticket_message`
- Jalankan ulang `storeEmailAttachments()` dan `replaceCidReferences()`
- Hanya bisa diakses oleh role 1, 2, 6, 7

---

## Ringkasan File-File Relevan

| Fungsi | File | Method |
|---|---|---|
| Fetch + simpan metadata attachment dari inbox | `EmailController.php` | `storeEmailAttachments()` |
| Ganti `cid:xxx` di HTML dengan URL proxy | `EmailController.php` | `replaceCidReferences()` |
| Ekstrak inline base64 dari Quill sebelum send | `EmailController.php` | `extractBase64Images()` |
| Upload attachment ke draft Graph + send | `EmailController.php` | `sendTicketReply()` |
| Stream file dari Graph ke browser | `AttachmentController.php` | `show()` |
| Reprocess attachment gagal | `EmailController.php` | `reprocessAttachments()` |
| Model + `public_url` accessor | `TicketAttachment.php` | `getPublicUrlAttribute()` |
| Simpan metadata setelah helpdesk reply | `TicketMessageController.php` | `sendEmailReply()` |

---

## Referensi API Microsoft Graph yang Digunakan

| Endpoint | Kegunaan |
|---|---|
| `GET /users/{u}/messages/{id}/attachments` | Ambil semua attachment beserta contentBytes dari email masuk |
| `POST /users/{u}/messages/{draftId}/attachments` | Upload attachment ke draft sebelum send |
| `GET /users/{u}/messages/{id}/attachments/{attId}` | Ambil satu attachment (via proxy, saat user akses file) |
| `POST /users/{u}/messages/{id}/createReply` | Buat draft reply (pertahankan thread + In-Reply-To) |
| `PATCH /users/{u}/messages/{draftId}` | Set body + subject + CC ke draft |
| `POST /users/{u}/messages/{draftId}/send` | Kirim draft |
