# Status Implementasi Integrasi JARVIES ↔ EcoSystem

> **Tanggal:** 2026-03-09
> **Tujuan:** Dokumen ini mencatat semua yang sudah diimplementasikan di JARVIES
> dan apa yang masih perlu ditambahkan/diperbaiki di **EcoSystem** agar integrasi
> berjalan penuh — khususnya fitur email threading.

---

## 1. Ringkasan Status

| Fitur | JARVIES | EcoSystem | Status Keseluruhan |
|---|---|---|---|
| Submit tiket (web form) | ✅ | ✅ | ✅ Selesai |
| Submit tiket via OAuth email | ✅ | ✅ processInbox | ✅ Selesai |
| Customer reply (web chat) | ✅ via API | ✅ customerReply() | ✅ Selesai |
| Customer reply (via OAuth email) | ✅ kirim + API | ✅ email_message_id disimpan | ✅ Selesai |
| Deduplication processInbox | — | ✅ Ada | ✅ Selesai |
| Relay email non-OAuth customer | ✅ skip_relay=false | ✅ | ✅ Selesai |
| Onboarding connect email | ✅ | — | ✅ Selesai |
| sender_name individual | ✅ | ✅ | ✅ Selesai |

---

## 2. Yang Sudah Diimplementasikan di JARVIES

### 2.1 Submit Tiket Baru (`POST /tickets`)

**File:** `app/Http/Controllers/TicketController.php` → `store()`

| Kondisi | Perilaku |
|---|---|
| Customer punya OAuth email | Kirim email FROM customer email TO helpdesk M365 via `CustomerEmailService`. Tidak buat staging langsung — staging dibuat oleh `processInbox` EcoSystem. |
| Customer tidak punya OAuth email | Buat `staging_ticket` langsung di DB (via `StagingTicketService::createFromWeb()`). |

**Field yang dikirim ke staging:**
```
description     → subject tiket
body            → isi pesan
ticket_priority → prioritas (Low/Medium/High)
channel         → 'web'
sender_name     → nama individual dari auth_users.username (bukan nama perusahaan)
submitted_by_email → email login customer
```

---

### 2.2 Customer Reply Tiket (`POST /tickets/{id}/comment`)

**File:** `app/Http/Controllers/TicketController.php` → `addComment()`

**Alur lengkap:**

```
Customer klik Send Reply
    ↓
Cek: customer punya OAuth email? + ticket punya email_thread_id?
    │
    ├─ Ada OAuth + ada email_thread_id
    │   ├─ Ambil email_message_id dari ticket_messages
    │   │   (channel=email, sender_type != customer, paling terakhir)
    │   │   → digunakan sebagai In-Reply-To header
    │   │
    │   ├─ Format subject: "Ticket #<ticket_number>: <description>"
    │   │   (maks 80 karakter description, TANPA prefix "Re:")
    │   │
    │   ├─ Kirim email via CustomerEmailService:
    │   │     FROM : customer OAuth email (Gmail/Outlook)
    │   │     TO   : ECOSYSTEM_HELPDESK_EMAIL (.env)
    │   │     SUBJECT: "Ticket #XXXX: description"
    │   │     In-Reply-To: <email_message_id dari helpdesk>
    │   │     References : <email_message_id dari helpdesk>
    │   │
    │   └─ POST /api/tickets/{id}/customer-reply:
    │         message_body  = isi pesan
    │         sender_name   = nama individual
    │         sender_email  = OAuth email customer
    │         customer_id   = id dari session
    │         skip_relay    = true   ← Jarvies sudah kirim email sendiri
    │         channel       = "email"
    │
    ├─ Ada OAuth TAPI email_thread_id NULL
    │   → skip kirim email (belum ada thread aktif)
    │   └─ POST /api/tickets/{id}/customer-reply:
    │         skip_relay = true, channel = "email"
    │
    └─ Tidak ada OAuth
        └─ POST /api/tickets/{id}/customer-reply:
              skip_relay = false, channel = "web"
              → EcoSystem relay dari helpdesk M365
```

**Environment variables yang dibutuhkan di `.env` JARVIES:**
```env
ECOSYSTEM_API_URL=http://localhost:8000/api       # URL EcoSystem API
EXTERNAL_TICKET_API_KEY=<api_key>                 # API key untuk customer-reply endpoint
ECOSYSTEM_HELPDESK_EMAIL=Raditya@eclecticonsulting.onmicrosoft.com  # Tujuan email TO
```

---

### 2.3 OAuth Email Linking

**File:** `app/Http/Controllers/OAuthEmailController.php`

| Route | Method | Keterangan |
|---|---|---|
| `GET /oauth/email/redirect/{provider}` | `redirect()` | Mulai OAuth flow. Simpan `return_to` di session. |
| `GET /oauth/email/callback/{provider}` | `callback()` | Terima token, simpan ke `customer_email_tokens`. |
| `GET /oauth/email/status` | `status()` | Cek apakah email sudah terhubung (JSON). |
| `DELETE /oauth/email/disconnect` | `disconnect()` | Hapus token. |
| `GET /onboarding/connect-email` | `onboarding()` | Halaman onboarding setelah setup password. |

**Provider yang didukung:** `google` (Gmail API), `azure` (Microsoft Graph delegated)

**Scope yang diminta:**
- Google: `https://www.googleapis.com/auth/gmail.send`
- Azure: `https://graph.microsoft.com/Mail.Send offline_access`

---

### 2.4 Onboarding Flow (Akun Baru)

**File:** `app/Http/Controllers/PasswordSetupController.php` → `submitChangePassword()`

```
Customer klik link setup password dari email
    ↓
Isi password baru → submit
    ↓
Jika customer (bukan employee):
    → Auto-login (buat session sama seperti login normal)
    → Session includes: id, type, customer_code, name (username), company_name, email, role
    → Redirect ke /onboarding/connect-email
        ├─ Pilih Google/Microsoft → OAuth flow → /dashboard
        └─ Skip → /dashboard langsung

Jika employee:
    → Redirect ke /login (tidak ada onboarding)
```

---

### 2.5 CustomerEmailService

**File:** `app/Services/CustomerEmailService.php`

| Method | Keterangan |
|---|---|
| `sendEmail(token, to, subject, body, threadId, inReplyTo)` | Entry point. Dispatch ke Gmail atau Graph. |
| `sendViaGmail(...)` | Kirim via Gmail API. RFC 2822, base64url encoded. Tambah `In-Reply-To` + `References` jika ada. |
| `buildRfc2822Message(...)` | Build raw email dengan header lengkap termasuk `In-Reply-To`. |
| `sendViaMicrosoftGraph(...)` | Kirim via `/me/sendMail`. Sertakan `conversationId` jika ada. |
| `getValidAccessToken(token)` | Cek expiry, refresh jika perlu. |
| `refreshGoogleToken(token)` | Refresh via `oauth2.googleapis.com/token`. |
| `refreshAzureToken(token)` | Refresh via `login.microsoftonline.com/{tenant}/oauth2/v2.0/token`. |

---

## 3. Yang WAJIB Ditambahkan di EcoSystem

> ⚠️ Tanpa implementasi ini, email threading tidak akan berfungsi dan akan ada duplikat chat.

---

### 3.1 ✅ Simpan `email_message_id` untuk Email Keluar

**Lokasi:** `EmailController::sendTicketReply()` dan `StagingTicketController::sendApprovalNotification()`

**Implementasi:**
Sebelum send draft, EcoSystem fetch `internetMessageId` dan `conversationId` dari Graph via:
```
GET /users/{sender}/messages/{draftId}?$select=internetMessageId,conversationId
```
Kemudian:
- `sendTicketReply()` menyertakan `internet_message_id` di return value-nya
- `TicketMessageController::sendEmailReply()` (baris 499–503) menyimpan nilai tersebut ke `ticket_message.email_message_id`
- `StagingTicketController::sendApprovalNotification()` menyimpan `internet_message_id` ke pesan approval

**Dampak:** JARVIES kini bisa mengambil `email_message_id` dari pesan helpdesk terakhir dan menggunakannya sebagai `In-Reply-To` → reply customer masuk thread yang sama di Gmail/Outlook ✅

---

### 3.2 ✅ Deduplication di `processInbox()`

**Lokasi:** `EmailController::processInbox()` (baris 344–350)

**Implementasi:**
Sebelum menyimpan pesan masuk, `processInbox` cek apakah `internetMessageId` email yang masuk sudah ada di `ticket_messages`:

```php
if ($internetMsgId && TicketMessage::where('email_message_id', $internetMsgId)->exists()) {
    $this->graphPatch(..., ['isRead' => true]);
    $skipped++;
    continue;
}
```

**Dampak:** Dedup bekerja **hanya jika** `ticket_message.email_message_id` sudah terisi sebelum processInbox berjalan → lihat poin 3.4 di bawah.

---

### 3.4 ✅ Terima `email_message_id` di `customerReply()`

**Lokasi:** `TicketMessageController::customerReply()`

**Implementasi:**
`customerReply()` menerima field `email_message_id` (RFC 2822 Message-ID dari Gmail/Outlook OAuth) dan menyimpannya ke `ticket_message.email_message_id`. JARVIES mengirimkan field ini setelah berhasil kirim email:

```json
{
  "message_body": "baik mas, terimakasih.",
  "sender_name":  "IRNA",
  "sender_email": "putrapalampangt@gmail.com",
  "customer_id":  37,
  "skip_relay":   true,
  "channel":      "email",
  "email_message_id": "<CADer5x=a4HErYxoAr+...@mail.gmail.com>"
}
```

**Dampak:** `processInbox` menerima email dari Gmail → cek `email_message_id` → menemukan match (sudah disimpan via `customerReply`) → skip → tidak duplikat ✅

---

### 3.3 ✅ (Sudah Ada) Support `skip_relay` dan `channel` di `customerReply()`

**Lokasi:** `TicketMessageController::customerReply()`

JARVIES mengirim field berikut ke endpoint ini:

```json
{
  "message_body": "isi pesan",
  "sender_name":  "IRNA",
  "sender_email": "putrapalampangt@gmail.com",
  "customer_id":  37,
  "skip_relay":   true,
  "channel":      "email"
}
```

| Field | Behavior EcoSystem |
|---|---|
| `skip_relay: true` | Simpan ke DB, JANGAN kirim relay email dari helpdesk (Jarvies sudah kirim sendiri) |
| `skip_relay: false` | Simpan ke DB + kirim relay email dari helpdesk M365 ke customer (thread yang sama) |
| `channel: "email"` | Simpan `channel = 'email'` di `ticket_message` |
| `channel: "web"` | Simpan `channel = 'web'` di `ticket_message` |

---

## 4. Status Keseluruhan

| Komponen | Status |
|---|---|
| JARVIES — kirim OAuth email + In-Reply-To | ✅ |
| JARVIES — fetch Message-ID setelah Gmail send | ✅ |
| JARVIES — pass `email_message_id` ke customer-reply API | ✅ |
| EcoSystem — simpan `email_message_id` untuk email keluar (3.1) | ✅ |
| EcoSystem — deduplication di `processInbox` (3.2) | ✅ |
| EcoSystem — terima + simpan `email_message_id` di `customerReply()` (3.4) | ✅ |

---

## 5. Alur Lengkap Setelah Semua Implementasi Selesai

```
[Ticket Baru via Web]
Customer submit form Jarvies
    → staging dibuat (via StagingTicketService atau processInbox)
    → Helpdesk approve (EcoSystem)
    → Ticket dibuat
    → EcoSystem kirim email approval TO customer
    → ticket_message.email_message_id = internetMessageId email approval  ← WAJIB 3.1

[Reply dari Helpdesk]
Helpdesk kirim reply dari EcoSystem chat
    → EcoSystem kirim email TO customer (createReply / sendMail)
    → ticket_message.email_message_id = internetMessageId email tersebut  ← WAJIB 3.1
    → Email masuk ke customer (Gmail/Outlook) dalam thread yang sama ✅

[Reply dari Customer via JARVIES + OAuth email]
Customer ketik reply → klik Send
    → JARVIES ambil email_message_id dari ticket_message terakhir (sender != customer)
    → JARVIES kirim email FROM customer Gmail TO helpdesk M365
          Subject  : "Ticket #XXXX: description"
          In-Reply-To: <email_message_id dari langkah sebelumnya>
    → Email masuk ke M365 inbox helpdesk dalam thread yang sama ✅
    → JARVIES panggil customer-reply API (skip_relay=true, channel=email)
    → EcoSystem simpan ke DB, skip relay
    → processInbox menerima email → cek email_message_id → skip jika sudah ada  ← WAJIB 3.2
    → Tidak ada duplikat ✅

[Reply dari Customer via JARVIES tanpa OAuth email]
Customer ketik reply → klik Send
    → JARVIES panggil customer-reply API (skip_relay=false, channel=web)
    → EcoSystem simpan ke DB
    → EcoSystem kirim relay email dari helpdesk M365 TO customer
    → Email masuk ke customer dalam thread yang sama ✅
```

---

## 6. Environment Variables JARVIES (Referensi)

```env
# EcoSystem API
ECOSYSTEM_API_URL=http://localhost:8000/api
EXTERNAL_TICKET_API_KEY=<key>
ECOSYSTEM_HELPDESK_EMAIL=Raditya@eclecticonsulting.onmicrosoft.com

# Microsoft Graph (helpdesk outgoing email)
MS_TENANT_ID=<tenant_id>
MS_CLIENT_ID=<client_id>
MS_CLIENT_SECRET=<secret>
MS_SENDER_EMAIL=Raditya@eclecticonsulting.onmicrosoft.com
GRAPH_BASE_URL=https://graph.microsoft.com/v1.0

# Google OAuth (customer email linking)
GOOGLE_CLIENT_ID=<client_id>
GOOGLE_CLIENT_SECRET=<secret>
GOOGLE_REDIRECT_URI=http://localhost:8001/oauth/email/callback/google

# Azure OAuth (customer email linking)
AZURE_CLIENT_ID=<client_id>
AZURE_CLIENT_SECRET=<secret>
AZURE_REDIRECT_URI=http://localhost:8001/oauth/email/callback/azure
AZURE_TENANT_ID=common
```

---

## 7. Referensi File JARVIES

| Fungsi | File |
|---|---|
| Submit tiket + OAuth email send | `app/Http/Controllers/TicketController.php` → `store()` |
| Customer reply + OAuth email send | `app/Http/Controllers/TicketController.php` → `addComment()` |
| Kirim email via Gmail/Outlook OAuth | `app/Services/CustomerEmailService.php` |
| OAuth email link/unlink | `app/Http/Controllers/OAuthEmailController.php` |
| Onboarding halaman connect email | `resources/views/onboarding/connect-email.blade.php` |
| Auto-login setelah setup password | `app/Http/Controllers/PasswordSetupController.php` → `submitChangePassword()` |
| Staging ticket (dari web form) | `app/Services/StagingTicketService.php` |
| Model OAuth token | `app/Models/CustomerEmailToken.php` |
| Model ticket | `app/Models/Ticket.php` |
| Model ticket message | `app/Models/TicketMessage.php` |
