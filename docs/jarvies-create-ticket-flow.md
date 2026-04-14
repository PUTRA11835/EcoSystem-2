# Dokumentasi Alur Create Ticket — JARVIES → EcoSystem

> **Tanggal dibuat:** 2026-04-14  
> **Konteks:** JARVIES (Customer Portal) → shared DB `ecosystem` ← EcoSystem (Employee Portal)

---

## Gambaran Umum

Saat customer membuat tiket di JARVIES, prosesnya **tidak langsung membuat `ticket`**.  
Semua tiket baru masuk sebagai **`staging_ticket`** terlebih dahulu, lalu divalidasi oleh admin/employee di EcoSystem sebelum menjadi tiket resmi.

```
Customer (JARVIES)
    ↓  isi form create ticket
    ↓  klik "Submit Ticket"
    ↓
JARVIES (TicketController@store)
    ├─ [Step 1] Ekstrak inline images dari Quill HTML
    ├─ [Step 2] Baca file attachment dari request
    ├─ [Step 3] Kirim email via Microsoft Graph API (M365)
    ├─ [Step 4a] Simpan staging ke DB langsung (tabel staging_tickets)
    └─ [Step 4b] POST ke EcoSystem API /jarvies/staging-tickets
                     ↓
             EcoSystem simpan staging (dengan internet_message_id)
             EcoSystem linkStagingToEmail() fetch body/attachment dari M365 Sent Items
                     ↓
             Admin EcoSystem review & approve staging
                     ↓
             Ticket resmi dibuat di tabel `ticket`
```

---

## 1. Form Create Ticket (Frontend)

**URL:** `GET /tickets/create`  
**View:** `resources/views/tickets/create.blade.php`

### Field yang tersedia:

| Field | ID HTML | Required | Keterangan |
|---|---|---|---|
| Subject | `#subject` | ✅ Ya | Judul tiket (min 5 char, max 5000 char) — dikirim sebagai `description` |
| Name | `#name` | Tidak | Nama contact person |
| No HP | `#no_hp` | Tidak | Nomor telepon |
| Module | `#module` | Tidak | Modul terkait |
| Client | `#client` | Tidak | Nama klien |
| Priority | radio `ticket_priority` | Tidak | Very High / High / Medium / Low (default: Medium) |
| CC | `cc_emails[]` | Tidak | Hingga 10 alamat email CC (bisa tambah baris) |
| Details | Quill editor `#detailsEditor` | ✅ Ya | Isi pesan (min 10 char) — mendukung rich text & paste gambar |
| Attachments | `#attachInput` | Tidak | File-file lampiran (max 10 file, max 20 MB per file) |

### Validasi di frontend (sebelum dikirim ke server):
1. Subject wajib diisi (min 5, max 5000 karakter)
2. Details wajib diisi (min 10 karakter)
3. Setiap CC email harus format valid (regex)
4. CC email tidak boleh duplikat

### Request yang dikirim ke server:

```
POST /tickets
Content-Type: multipart/form-data

description      = "Subject tiket"      ← isi field Subject
body_html        = "<p>HTML dari Quill</p>"
body             = "Plain text dari Quill"
ticket_priority  = "Medium"
name             = "Budi Santoso"       ← opsional
no_hp            = "08123456789"        ← opsional
module           = "Payroll"            ← opsional
client           = "PT ABC"             ← opsional
cc_emails[]      = "a@email.com"        ← bisa multiple, opsional
cc_emails[]      = "b@email.com"
attachments[]    = [file]               ← bisa multiple, opsional
_token           = "csrf_token"
```

---

## 2. Proses di Server (TicketController@store)

**File:** `app/Http/Controllers/TicketController.php` — method `store()`  
**Route:** `POST /tickets` (hanya untuk role Customer, `role_id = 3`)

---

### Step 1 — Ekstrak Inline Images dari Quill

Quill editor menyimpan gambar yang di-paste sebagai **base64 data URI** di dalam HTML:
```html
<img src="data:image/png;base64,iVBORw0K...">
```

Gambar ini tidak bisa dikirim langsung via email (ukuran besar, tidak semua client mendukung).  
JARVIES mengekstrak setiap gambar dan mengubahnya ke format **CID (Content-ID)**:

```html
<!-- Sebelum -->
<img src="data:image/png;base64,iVBOR...">

<!-- Sesudah -->
<img src="cid:img-1@jarvies">
```

Gambar disimpan sebagai array `$emailInlineImages`:
```php
[
    ['name' => 'image-1.png', 'content' => <binary>, 'mime' => 'image/png', 'cid' => 'img-1@jarvies'],
]
```

---

### Step 2 — Baca File Attachment

File attachment yang diupload customer dibaca secara binary dari request:
```php
$emailFileAttachments[] = [
    'name'    => $file->getClientOriginalName(),
    'content' => file_get_contents($file->getRealPath()),
    'mime'    => $file->getMimeType(),
];
```

Binary dibaca di sini karena akan dipakai **dua kali**:
- (a) dikirim ke M365 via GraphRelayService
- (b) dikirim ulang ke EcoSystem API sebagai multipart

---

### Step 3 — Kirim Email via Microsoft Graph API

**Service:** `GraphRelayService::sendStandaloneEmail()`  
**Pengirim:** M365 mailbox (`MS_SENDER_EMAIL` di `.env`)  
**Penerima:** Email customer (`auth_users.email` → login email customer)

**Subject email:**
```
[Menunggu Validasi] {description}
```
Contoh: `[Menunggu Validasi] Error saat generate laporan payroll`

**Body email** dibangun oleh `buildTicketEmailBody()`:
```html
<p><em>[Tiket baru dari PT Indoraya via Jarvies]</em></p>
<table>
  <tr><td>Phone</td><td>: 08123456789</td></tr>
  <tr><td>Module</td><td>: Payroll</td></tr>
  <tr><td>Client</td><td>: PT ABC</td></tr>
</table>
<div>
  <strong>Description:</strong>
  <div style="background:#f9f9f9;...">
    [Isi Quill HTML dengan CID images]
  </div>
</div>
```

**Return value dari Graph API:**
```php
$emailResult = [
    'internet_message_id' => '<unique-msg-id@outlook.com>',
    'conversation_id'     => 'AAQkAGFi...',
]
```

Kedua ID ini **sangat penting** — dipakai EcoSystem untuk mencocokkan staging ticket dengan email di M365.

---

### Step 4a — Simpan Staging ke DB JARVIES (Failsafe)

**Service:** `StagingTicketService::createFromWeb()`  
**Tabel:** `staging_tickets` (shared DB `ecosystem`)

Ini menjamin staging **selalu tersimpan** di DB meskipun EcoSystem API tidak tersedia.

```php
StagingTicket::create([
    'customer_id'        => $user['id'],
    'description'        => "Subject tiket",
    'body'               => "<p>HTML dari Quill</p>",
    'cc_emails'          => '["a@email.com","b@email.com"]',  // JSON string
    'name'               => "Budi Santoso",
    'no_hp'              => "08123456789",
    'module'             => "Payroll",
    'client'             => "PT ABC",
    'ticket_priority'    => "Medium",
    'status'             => "unvalidated",
    'channel'            => "web",
    'submitted_by_email' => "customer@gmail.com",
    'sender_name'        => "PT Indoraya",
    'email_thread_id'    => "AAQkAGFi...",     // conversationId dari Graph
    'email_message_id'   => "<msg@outlook.com>", // internet_message_id dari Graph
    'customer_thread_id' => "AAQkAGFi...",     // sama dengan email_thread_id
]);
```

**Catatan penting:** `cc_emails` disimpan sebagai **JSON string** (bukan array), karena kolom DB bertipe `text`.

---

### Step 4b — POST ke EcoSystem API

**Endpoint:** `POST {ECOSYSTEM_URL}/jarvies/staging-tickets`  
**Auth:** Header `X-Api-Key: {ECOSYSTEM_API_KEY}`  
**Content-Type:** `multipart/form-data`

```
POST https://ecosystemtest.org/api/jarvies/staging-tickets
X-Api-Key: your-api-key-here
Content-Type: multipart/form-data

description          = "Subject tiket"
customer_id          = "123"
submitted_by_email   = "customer@gmail.com"
body                 = "<p>HTML dari Quill</p>"
internet_message_id  = "<unique-msg-id@outlook.com>"    ← KRITIS untuk linkStagingToEmail()
sender_name          = "PT Indoraya"
ticket_priority      = "Medium"
channel              = "email"
cc_emails            = '[{"name":"a@email.com","address":"a@email.com"}]'  ← JSON string
name                 = "Budi Santoso"          ← jika diisi
no_hp                = "08123456789"           ← jika diisi
module               = "Payroll"               ← jika diisi
client               = "PT ABC"                ← jika diisi
attachments[]        = [file binary]           ← jika ada lampiran
```

**Catatan:** Kegagalan Step 4b **tidak membatalkan** create ticket.  
Log warning ditulis, tapi response ke customer tetap sukses (staging sudah tersimpan di Step 4a).

---

### Response ke Customer (Sukses):

```json
{
    "success": true,
    "staging": true,
    "email_sent": true,
    "message": "Tiket Anda telah dikirim dan sedang menunggu validasi admin."
}
```

Frontend menerima response ini, menampilkan toast success, lalu redirect ke `/tickets`.

---

## 3. Tampilan di JARVIES Setelah Submit

Staging ticket muncul di halaman `/tickets` dengan badge **"Menunggu Validasi"** (warna amber).  
Customer tidak bisa membalas atau mengubah tiket yang masih berstatus staging.

Di list tiket, staging dibedakan dari tiket biasa lewat field `is_staging: true` di response `GET /tickets/ajax/fetch`.

---

## 4. Apa yang Diharapkan EcoSystem

### 4.1 Field Wajib dari JARVIES

| Field | Keterangan |
|---|---|
| `description` | Subject tiket — dicocokkan dengan subject email oleh `linkStagingToEmail()` |
| `customer_id` | ID customer dari tabel `customer` |
| `internet_message_id` | ID unik email dari M365 — dipakai EcoSystem untuk fetch body & attachment dari Sent Items |
| `channel` | Harus `"email"` agar EcoSystem tahu ini masuk via email |

### 4.2 Proses EcoSystem setelah menerima staging

```
EcoSystem menerima POST /jarvies/staging-tickets
    ↓
Simpan ke tabel staging_tickets (via StagingTicketController)
    ↓
linkStagingToEmail() dipanggil:
    - Gunakan internet_message_id untuk fetch email dari M365 Sent Items
    - Ekstrak body HTML, attachment, inline images dari email
    - Update staging dengan data dari email
    - Simpan email_thread_id (conversationId) ke staging
    ↓
Admin EcoSystem melihat staging di dashboard
    ↓
Admin klik "Approve" staging:
    - Buat record baru di tabel `ticket`
    - Buat ticket_message pertama dari staging.body
    - Copy email_thread_id dari staging ke ticket (untuk reply email selanjutnya)
    - Update staging.status = 'validated' / 'approved'
    - Update staging.ticket_id = ID tiket baru
    ↓
Customer JARVIES lihat tiket sudah muncul sebagai tiket resmi (bukan staging lagi)
```

### 4.3 Cocokkan Subject Email dengan Staging

EcoSystem menggunakan `description` untuk mencocokkan staging dengan email di inbox.  
Email yang masuk ke inbox helpdesk (dari customer reply) dicocokkan dengan staging via:

```sql
LOWER(staging_tickets.description) = LOWER(clean_subject_from_email)
```

`clean_subject_from_email` = subject email dikurangi prefix seperti `[Menunggu Validasi]`, `Re:`, `Fwd:`.

**Penting:** Subject email yang dikirim JARVIES adalah:
```
[Menunggu Validasi] {description}
```

EcoSystem harus strip prefix `[Menunggu Validasi]` sebelum cocokkan.

### 4.4 Format `cc_emails` yang Diharapkan EcoSystem

JARVIES mengirim `cc_emails` sebagai JSON string dengan format:
```json
[
    {"name": "a@email.com", "address": "a@email.com"},
    {"name": "b@email.com", "address": "b@email.com"}
]
```

EcoSystem harus parse JSON ini untuk menyimpan CC ke staging.

---

## 5. Struktur Tabel `staging_tickets` (Kolom Relevan)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | int | PK auto-increment |
| `customer_id` | int | FK → `customer.customer_id` |
| `description` | text | Subject tiket (wajib) |
| `body` | text | HTML body dari Quill (opsional) |
| `cc_emails` | text | JSON string array CC emails |
| `name` | varchar(255) | Nama contact person |
| `no_hp` | varchar(255) | Nomor telepon |
| `module` | varchar(255) | Modul terkait |
| `client` | varchar(255) | Nama klien |
| `ticket_priority` | enum/varchar | Very High / High / Medium / Low |
| `status` | varchar | `unvalidated` → `validated` / `rejected` |
| `channel` | varchar | `web` (dari staging JARVIES) / `email` (dari inbox) |
| `submitted_by_email` | varchar | Email customer |
| `sender_name` | varchar | Nama perusahaan customer |
| `email_thread_id` | varchar | conversationId dari M365 Graph API |
| `email_message_id` | varchar | internet_message_id dari M365 Graph API |
| `customer_thread_id` | varchar | Sama dengan email_thread_id (untuk customer-side reference) |
| `ticket_id` | int | Diisi EcoSystem setelah approve → FK ke `ticket` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## 6. Error yang Mungkin Terjadi

| Error | Penyebab | Dampak |
|---|---|---|
| Graph API gagal | Token M365 expired, konfigurasi salah, network | Tiket TIDAK jadi, customer diberi pesan error |
| `cc_emails` Array to string | Array PHP dimasukkan langsung ke kolom text | Fixed: sekarang di-JSON encode di `StagingTicketService` |
| EcoSystem API 401 | `ECOSYSTEM_URL` pakai `http://` (redirect ke HTTPS hilangkan header) | Staging tidak masuk EcoSystem, tapi masuk DB JARVIES. Fix: gunakan `https://` di `.env` |
| EcoSystem API timeout | EcoSystem down / lambat | Non-critical — staging sudah tersimpan di JARVIES DB |
| Email customer tidak ada | `auth_users.email` NULL | Tiket TIDAK jadi, customer diberi pesan error 422 |
| **Duplikat staging (Web + Email)** | Step 4a (Jarvies tulis langsung ke DB) + Step 4b (POST ke EcoSystem) keduanya memanggil `createFromWeb()` → dua staging berbeda | **Fixed (Apr 2026):** `StagingTicketService::createFromWeb()` sekarang cek dedup via `email_message_id` sebelum insert. Jika staging dengan `internet_message_id` yang sama sudah ada dan masih `unvalidated`, kembalikan yang lama. `linkStagingToEmail()` tetap dipanggil untuk memperkaya staging lama. |

---

## 7. Konfigurasi `.env` yang Diperlukan

```env
# Microsoft Graph API — pengirim email
MS_TENANT_ID=...
MS_CLIENT_ID=...
MS_CLIENT_SECRET=...
MS_SENDER_EMAIL=helpdesk@company.com

# EcoSystem API — staging endpoint
ECOSYSTEM_URL=https://ecosystemtest.org/api    ← wajib HTTPS
ECOSYSTEM_API_KEY=your-secret-key-here
```

---

## 8. Ringkasan Alur Singkat

```
1. Customer isi form di /tickets/create
        ↓
2. JARVIES ekstrak gambar Quill (base64 → CID)
        ↓
3. JARVIES kirim email via M365 Graph API
   Subject: "[Menunggu Validasi] {subject}"
   To: email login customer
   Lampiran: file + inline images
        ↓
4. Graph API return internet_message_id & conversation_id
        ↓
5. JARVIES simpan staging_ticket ke DB (dengan email_thread_id)
        ↓
6. JARVIES POST ke EcoSystem API /jarvies/staging-tickets
   Field kritis: internet_message_id, channel=email
        ↓
7. EcoSystem linkStagingToEmail():
   - Fetch email dari M365 Sent Items via internet_message_id
   - Ambil body HTML dan attachment dari email
   - Simpan ke staging
        ↓
8. Admin EcoSystem approve staging
   - Buat ticket di tabel ticket
   - Buat ticket_message pertama dari body staging
   - Salin email_thread_id ke ticket
        ↓
9. Customer lihat tiket di /tickets (bukan staging lagi)
```
