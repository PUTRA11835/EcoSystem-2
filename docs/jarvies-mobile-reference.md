# JARVIES — Dokumentasi Lengkap untuk Referensi Mobile App

> **Versi web:** https://jarvies.eclecticoffice.com
> **Stack web:** Laravel 11 + Blade + Tailwind CSS
> **Database:** MySQL, shared dengan EcoSystem (employee side)
> **Database name (production):** `eclecti2_magang`

---

## 1. ARSITEKTUR SISTEM

```
EcoSystem (employee/admin side) ←→ [Database: ecosystem] ←→ JARVIES (customer side)
```

JARVIES adalah **customer portal** — customer submit dan pantau tiket sendiri.
EcoSystem adalah **employee/admin portal** — kelola tiket, delivery, project.
Keduanya berbagi database yang sama, **tidak ada REST API antar keduanya** (direct DB).

---

## 2. AUTENTIKASI

### 2.1 Tabel & Struktur

**Tabel sentral: `auth_users`**

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | |
| `email` | VARCHAR | Login identifier utama |
| `username` | VARCHAR | Bisa digunakan login (ECI code / customer_code) |
| `phone` | VARCHAR | Bisa digunakan login |
| `password` | VARCHAR | bcrypt hash |
| `is_active` | BOOLEAN | Harus true untuk bisa login |
| `is_already_cp` | BOOLEAN | false = belum pernah set password (akun baru) |
| `employee_id` | INT FK | nullable — jika ini employee |
| `customer_id` | INT FK | nullable — jika ini customer |
| `cp_token` | VARCHAR | Token setup/reset password (64 char random) |
| `cp_token_expires_at` | DATETIME | Token expired setelah 24 jam |
| `last_login_at` | DATETIME | |

**Role system:**
- Role 1 = Admin
- Role 2 = Employee (hanya di EcoSystem)
- Role 3 = Customer (JARVIES)

### 2.2 Alur Login

```
POST /login
Body: { email, password, remember? }

Langkah di controller:
1. Cari di auth_users WHERE (email OR username OR phone) AND is_active=true
2. Jika tidak ditemukan → 401 "Invalid email or password"
3. Jika is_already_cp = FALSE:
   → Generate token 64 char, simpan ke cp_token, kirim email setup password
   → Return: { success: true, require_password_change: true, email: "ab***@domain.com" }
   → Frontend redirect ke /password/check-email
4. Jika is_already_cp = TRUE:
   → Hash::check(password, hash)
   → Jika salah → 401
   → Jika benar → buat token session, return user data
```

**Response login sukses:**
```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "<base64_token>",
    "user": {
      "id": 42,
      "type": "customer",
      "customer_code": "C-001",
      "company_name": "PT Contoh Indonesia",
      "email": "customer@example.com",
      "category": "...",
      "group": "...",
      "role": { "id": 3, "name": "Customer" }
    }
  }
}
```

### 2.3 Format Token

Token BUKAN JWT. Formatnya:
```
base64( customer_code + "|" + timestamp_unix + "|" + "customer" )
```

Contoh decode: `C-001|1740900000|customer`

**PENTING:** Token ini digunakan untuk web session (`session('auth_token')`).
Untuk mobile app, perlu mekanisme token terpisah (misal Sanctum API token atau custom token berbasis token ini).

### 2.4 Setup Password Pertama Kali

```
Flow:
1. Login → is_already_cp = false → email terkirim
2. Halaman: GET /password/check-email?email=ab***@domain.com&type=setup
3. Customer klik link di email → GET /password/change?token=<64char>
4. Isi form password baru → POST /password/change
5. Jika customer → auto-login + redirect ke /onboarding/connect-email
6. Jika employee → redirect ke /login
```

Email dikirim via **Microsoft Graph API** (bukan SMTP), dari `MS_SENDER_EMAIL`.

### 2.5 Forgot Password

```
GET  /password/forgot → form input email
POST /password/forgot
  → Cari auth_users WHERE email AND is_active=true
  → Generate token, kirim email reset (valid 24 jam)
  → Redirect ke /password/check-email?type=reset
```

### 2.6 Logout

```
POST /logout
→ session()->flush() + invalidate() + regenerateToken()
→ Redirect ke /login
```

---

## 3. SESSION STRUCTURE (Web)

```php
// Customer session
session('auth_token') = "base64_encoded_string"
session('user') = [
    'id'            => 42,               // customer_id
    'type'          => 'customer',
    'customer_code' => 'C-001',
    'company_name'  => 'PT Contoh Indonesia',
    'email'         => 'customer@example.com',
    'category'      => '...',            // customer_category
    'group'         => '...',            // customer_group
    'role'          => ['id' => 3, 'name' => 'Customer']
]

// Employee session
session('user') = [
    'id'         => 10,              // employee_id
    'type'       => 'employee',
    'eci'        => 'ECI-001',
    'name'       => 'John Doe',
    'email'      => 'john@company.com',
    'phone'      => '08xx',
    'position'   => '...',
    'department' => '...',
    'role'       => ['id' => 1, 'name' => 'Admin']  // atau role 2
]
```

---

## 4. DATABASE TABLES (Ticket System)

### 4.1 `ticket` (main ticket table)

| Kolom | Tipe | Nilai |
|---|---|---|
| `ticket_id` | INT PK | |
| `ticket_number` | VARCHAR | Auto-generated |
| `customer_id` | INT FK | |
| `employee_id` | INT FK | nullable — siapa yang handle |
| `description` | TEXT | Subject/judul tiket |
| `ticket_priority` | ENUM | `Low`, `Medium`, `High` |
| `ticket_type` | VARCHAR | |
| `status` | ENUM | `open`, `in_progress`, `hold`, `cancel`, `closed`, `reply` |
| `jarvies_status` | VARCHAR | `in process`, `author action`, `proposed solution`, `closed`, `sent in to SAP`, `sent it to support` |
| `channel` | VARCHAR | `web`, `email` |
| `start_date` | DATE | |
| `end_date` | DATE | |
| `man_days` | DECIMAL(6,2) | |
| `wait_close` | DECIMAL | |
| `email_thread_id` | VARCHAR | Gmail threadId untuk reply di thread yang sama |
| `last_message_at` | DATETIME | |
| `last_customer_reply_at` | DATETIME | |
| `last_agent_reply_at` | DATETIME | |

### 4.2 `staging_tickets` (ticket menunggu validasi)

Customer tidak langsung buat `ticket`, tapi masuk ke staging dulu.

| Kolom | Tipe | Nilai |
|---|---|---|
| `id` | INT PK | |
| `customer_id` | INT FK | |
| `description` | TEXT | Subject/judul tiket |
| `body` | TEXT | Isi pesan pertama dari customer (web form) |
| `ticket_priority` | ENUM | `Low`, `Medium`, `High` — null untuk staging dari email |
| `ticket_type` | VARCHAR | nullable — diisi saat approve oleh helpdesk |
| `status` | ENUM | `unvalidated`, `approved`, `rejected` |
| `rejection_reason` | TEXT | nullable |
| `channel` | VARCHAR | `web` atau `email` |
| `email_thread_id` | VARCHAR | conversationId dari MS Graph |
| `email_message_id` | VARCHAR | Internet-Message-ID dari email |
| `graph_message_id` | VARCHAR | ID pesan di Graph API |
| `submitted_by_email` | VARCHAR | email customer yang submit |
| `sender_name` | VARCHAR | Nama pengirim (dari email) |
| `email_body_html` | LONGTEXT | HTML isi email asli (hanya channel email) |
| `has_attachments` | BOOLEAN | Apakah email punya attachment |
| `cc_emails` | TEXT | JSON array CC (hanya channel email) |
| `validated_by` | INT | employee_id yang validasi |
| `validated_at` | DATETIME | |
| `ticket_id` | INT FK | nullable — diisi jika approved |

### 4.3 `ticket_message` (chat/messages)

| Kolom | Tipe | Nilai |
|---|---|---|
| `id` | INT PK | |
| `ticket_id` | INT FK | |
| `sender_type` | ENUM | `customer`, `employee`, `system` |
| `sender_id` | INT | customer_id atau employee_id |
| `sender_email` | VARCHAR | |
| `sender_name` | VARCHAR | |
| `message` | TEXT | Pesan plain text |
| `message_html` | TEXT | Pesan HTML (dari email) |
| `cc_emails` | TEXT | |
| `is_internal_note` | BOOLEAN | true = tidak tampil ke customer |
| `channel` | ENUM | `web`, `email` |
| `email_message_id` | VARCHAR | |
| `email_in_reply_to` | VARCHAR | |
| `is_read_by_customer` | BOOLEAN | |
| `is_read_by_agent` | BOOLEAN | |
| `read_at` | DATETIME | |

### 4.4 `ticket_confirmation` (assignment employee)

| Kolom | Tipe | |
|---|---|---|
| `confirmation_id` | INT PK | |
| `ticket_id` | INT FK | |
| `employee_id` | INT FK | |
| `member_ids` | JSON | array employee_id anggota tim |
| `man_days` | DECIMAL | |
| `status` | ENUM | `pending`, `confirmed`, `rejected` |
| `confirmed_by` | INT | admin employee_id |
| `confirmed_at` | DATETIME | |

### 4.5 `customer_email_tokens` (OAuth linking)

| Kolom | Tipe | |
|---|---|---|
| `id` | INT PK | |
| `customer_id` | INT FK | |
| `provider` | ENUM | `google`, `azure` |
| `provider_email` | VARCHAR | email yang terhubung |
| `provider_user_id` | VARCHAR | |
| `access_token` | TEXT | encrypted/stored |
| `refresh_token` | TEXT | |
| `token_expires_at` | DATETIME | |

---

## 5. TICKET FLOW

### 5.1 Customer Submit Tiket

```
POST /tickets
Body: { description, ticket_priority, body? }

Flow Customer (role 3):
1. Validasi: description required, priority optional, body optional
2. StagingTicketService::createFromWeb() → simpan ke staging_tickets (status: unvalidated)
3. Jika customer punya OAuth token (email linked):
   → CustomerEmailService::sendEmail() → kirim email ke MS_SENDER_EMAIL
   → Simpan email_thread_id ke staging
4. Return 201: { success: true, staging: true, email_sent: bool, data: {...} }

Flow Admin (role 1):
1. Langsung buat di tabel ticket (bypass staging)
2. Return 200: { success: true, staging: false, data: {...} }
```

**Staging ticket harus diapprove dulu di EcoSystem (employee side) sebelum jadi `ticket`.**

### 5.2 Customer Lihat Tiket

```
GET /tickets/ajax/fetch
→ Ambil semua ticket WHERE customer_id = session.user.id
→ Termasuk: customer info, employee PIC, members, pending confirmation count

GET /tickets/staging  (AJAX)
→ Ambil staging_tickets milik customer yang login

GET /tickets/pending  (halaman web)
→ View daftar staging tickets dengan status (unvalidated/approved/rejected)
```

### 5.3 Detail Tiket

```
GET /tickets/{id}        → HTML view atau JSON jika Accept: application/json
GET /my/tickets/{id}     → Redirect ke /tickets?open={id} (JS auto-open modal)

Ticket data yang dikembalikan:
{
  ticket_id, ticket_number, description, ticket_priority,
  ticket_type, jarvies_status, status,
  start_date, end_date, man_days,
  customer: { customer_id, customer_name },
  employee: { employee_id, employee_name },  // PIC
  members: [ { employee_id, employee_name } ],
  pending_confirmations_count,
  confirmation: { ... }
}
```

### 5.4 Chat/Messages per Tiket

```
GET /tickets/{id}/messages
→ Ambil semua pesan tiket (customer hanya lihat is_internal_note=false)
→ Auto-mark messages dari employee sebagai is_read_by_customer=true

Response:
[{
  id, sender_type, sender_name, sender_email,
  message, message_html, cc_emails, channel,
  attachments: [{ id, file_name, attachment_type, url }],
  created_at
}]
```

### 5.5 Customer Reply/Comment

```
POST /tickets/{id}/comment
Body: { comment: "teks pesan..." }

Flow:
1. Hanya customer (3) dan admin (1) bisa comment
2. Customer hanya bisa reply ke tiket miliknya
3. Jika customer punya linked email (OAuth):
   → CustomerEmailService::sendEmail() → kirim ke MS_SENDER_EMAIL
   → Jika berhasil: channel = 'email', update email_thread_id di ticket
   → Jika gagal: lanjut, channel tetap 'web' (non-fatal)
4. Simpan TicketMessage ke DB
5. Update ticket: last_message_at, last_customer_reply_at

Response:
{ success: true, message: "Message sent." }
```

---

## 6. OAUTH EMAIL LINKING (Socialite)

### 6.1 Tujuan

Customer bisa menghubungkan akun Google/Microsoft mereka ke JARVIES agar:
- Tiket dikirim **dari email customer** ke mailbox helpdesk
- Reply di tiket juga dikirim **dari email customer**
- Semua thread terekam di email customer

### 6.2 Provider yang Didukung

| Provider | Library | Scope |
|---|---|---|
| Google | `laravel/socialite` driver `google` | `gmail.send` |
| Microsoft Azure | `metrogistics/laravel-azure-socialite` driver `azure` | `Mail.Send`, `offline_access` |

### 6.3 Config (.env)

```env
# Google
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://jarvies.eclecticoffice.com/oauth/email/callback/google

# Azure
AZURE_CLIENT_ID=...
AZURE_CLIENT_SECRET=...
AZURE_REDIRECT_URI=https://jarvies.eclecticoffice.com/oauth/email/callback/azure
AZURE_TENANT_ID=common
```

### 6.4 Routes OAuth

```
GET /oauth/email/status              → Cek status email linked (JSON)
GET /oauth/email/redirect/{provider} → Mulai OAuth flow (?return=/path)
GET /oauth/email/callback/{provider} → Callback dari Google/Azure (di luar middleware auth)
DELETE /oauth/email/disconnect       → Hapus token (unlink)

GET /onboarding/connect-email        → Halaman pilih Google/Microsoft (setelah setup password)
```

### 6.5 Alur OAuth

```
1. Customer klik "Connect Google" → GET /oauth/email/redirect/google?return=/dashboard
2. Session disimpan:
   - oauth_email_intent = 'link_email'
   - oauth_email_provider = 'google'
   - oauth_email_return = '/dashboard'
3. Redirect ke Google OAuth consent screen
   - Scope: gmail.send
   - access_type: offline (untuk refresh token)
   - prompt: consent (paksa izin setiap kali)
4. Google redirect ke /oauth/email/callback/google
5. Controller:
   a. Cek session user masih ada (jika tidak → redirect login)
   b. Cek error param (user deny) → redirect ke onboarding dengan error msg
   c. Ambil socialUser via Socialite::driver('google')->user()
   d. Validasi scope gmail.send ada di response
   e. CustomerEmailToken::updateOrCreate() → simpan token ke DB
   f. Redirect ke oauth_email_return dengan flash 'oauth_success'
```

### 6.6 Kirim Email via OAuth (CustomerEmailService)

```php
// Google (Gmail API)
POST https://gmail.googleapis.com/gmail/v1/users/me/messages/send
Authorization: Bearer {access_token}
Body: { raw: "<RFC2822 base64url encoded>", threadId?: "..." }

// Microsoft (Graph API delegated /me)
POST https://graph.microsoft.com/v1.0/me/sendMail
Authorization: Bearer {access_token}
Body: {
  message: {
    subject,
    body: { contentType: "Text", content: "..." },
    toRecipients: [{ emailAddress: { address: "..." } }]
  },
  saveToSentItems: true
}
```

**Token refresh otomatis** jika expired:
- Google: POST `https://oauth2.googleapis.com/token` (grant_type: refresh_token)
- Azure: POST `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token`

### 6.7 Status Email Check

```
GET /oauth/email/status

Response jika linked:
{
  "linked": true,
  "provider": "google",
  "email": "customer@gmail.com",
  "expired": false
}

Response jika tidak linked:
{ "linked": false }
```

---

## 7. ONBOARDING FLOW (Setelah Setup Password)

```
Customer baru → Login pertama kali → is_already_cp = false
→ Email setup password terkirim
→ Klik link email → /password/change?token=...
→ Isi password baru → POST /password/change
→ AUTO-LOGIN (session dibuat langsung)
→ Redirect ke /onboarding/connect-email

Halaman /onboarding/connect-email:
- Tombol "Connect Google" → /oauth/email/redirect/google?return=/dashboard
- Tombol "Connect Microsoft" → /oauth/email/redirect/azure?return=/dashboard
- Link "Lewati" → /dashboard

Setelah OAuth selesai → redirect ke /dashboard dengan flash success
```

---

## 8. EMAIL HELPDESK (Microsoft 365 — server side)

Ini adalah email helpdesk **JARVIES sendiri** (bukan email customer):

```env
MS_TENANT_ID=cbcc85ec-3c81-467c-9f7a-fda77b075c13
MS_CLIENT_ID=...
MS_CLIENT_SECRET=...
MS_SENDER_EMAIL=Raditya@eclecticonsulting.onmicrosoft.com
MS_SENDER_NAME="JARVIES Portal"
GRAPH_BASE_URL=https://graph.microsoft.com/v1.0
```

**Kegunaan:**
1. Kirim email setup/reset password ke user baru
2. *(Di EcoSystem)* Pull inbox masuk → auto-create ticket dari email pelanggan

Token diambil via **client_credentials** (application-level, bukan delegated):
```
POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
grant_type: client_credentials
scope: https://graph.microsoft.com/.default
```

---

## 9. API ENDPOINTS LENGKAP

### Auth
| Method | URL | Middleware | Keterangan |
|---|---|---|---|
| GET | `/` | guest | Redirect ke login |
| GET | `/login` | guest | Halaman login |
| POST | `/login` | guest | Proses login (JSON) |
| POST | `/logout` | auth | Logout |
| GET | `/password/check-email` | - | Info "cek email" |
| GET | `/password/change` | - | Form set password |
| POST | `/password/change` | - | Proses set password |
| GET | `/password/forgot` | - | Form forgot password |
| POST | `/password/forgot` | - | Kirim email reset |

### Dashboard
| Method | URL | Middleware | |
|---|---|---|---|
| GET | `/dashboard` | auth | Dashboard utama |

### Tickets
| Method | URL | Middleware | |
|---|---|---|---|
| GET | `/tickets` | auth | Halaman list tiket |
| GET | `/tickets/create` | auth | Form buat tiket (customer) |
| POST | `/tickets` | auth | Submit tiket |
| GET | `/tickets/{id}` | auth | Detail tiket |
| PUT | `/tickets/{id}` | auth | Update tiket (admin) |
| DELETE | `/tickets/{id}` | auth | Hapus tiket |
| GET | `/tickets/ajax/fetch` | auth | AJAX list tiket |
| GET | `/tickets/staging` | auth | AJAX staging tiket |
| GET | `/tickets/pending` | auth | Halaman staging tiket |
| GET | `/tickets/{id}/messages` | auth | AJAX ambil pesan |
| POST | `/tickets/{id}/comment` | auth | Kirim reply |
| GET | `/my/tickets` | auth | My tickets |
| GET | `/my/tickets/{id}` | auth | Redirect ke /tickets?open={id} |

### Onboarding & OAuth
| Method | URL | Middleware | |
|---|---|---|---|
| GET | `/onboarding/connect-email` | auth | Halaman onboarding OAuth |
| GET | `/oauth/email/status` | auth | Status linked email |
| GET | `/oauth/email/redirect/{provider}` | auth | Mulai OAuth |
| DELETE | `/oauth/email/disconnect` | auth | Putuskan link |
| GET | `/oauth/email/callback/{provider}` | **none** | Callback OAuth |

---

## 10. MIDDLEWARE

| Middleware | Alias | Keterangan |
|---|---|---|
| `JarviesAuth` | `jarvies.auth` | Cek session('auth_token') dan session('user'), allow role 1 & 3 |
| `JarviesGuest` | `jarvies.guest` | Redirect ke /dashboard jika sudah login |

**JANGAN gunakan** `auth` middleware Laravel standar atau `Auth::user()`.
Selalu gunakan `session('user')` untuk mendapatkan user yang login.

---

## 11. MODELS PENTING

| Model | Table | PK |
|---|---|---|
| `Customer` | `customer` | `customer_id` |
| `CustomerBasicData` | `customer_basic_data` | `customer_id` |
| `CustomerEmailToken` | `customer_email_tokens` | `id` |
| `Ticket` | `ticket` | `ticket_id` |
| `TicketMessage` | `ticket_message` | `id` |
| `StagingTicket` | `staging_tickets` | `id` |
| `Employee` | `employee` | `employee_id` |
| `EmployeeBasicData` | `employee_basic_data` | `employee_id` |

---

## 12. CATATAN PENTING UNTUK MOBILE

### Autentikasi Mobile
Web JARVIES menggunakan **session cookie**, bukan token API.
Untuk mobile app, opsi yang bisa dipertimbangkan:
1. **Tambahkan API routes** dengan autentikasi berbasis Bearer token (Sanctum sudah diinstall)
2. **Decode token web** (`base64(customer_code|timestamp|customer)`) dan validasi sendiri
3. Gunakan `AuthController::me()` yang sudah ada — menerima Bearer token dan return user data

### me() endpoint
```
GET /any-route (dengan header Authorization: Bearer <token>)
→ Controller me() membaca Bearer token
→ Decode: identifier|timestamp|type
→ Query DB berdasarkan type (customer/employee)
→ Return user data JSON
```

### Response Pattern
Semua AJAX/JSON endpoints menggunakan format:
```json
{ "success": true/false, "message": "...", "data": {...} }
```

### CORS / Mobile Consideration
Saat ini tidak ada CORS config khusus. Untuk mobile, perlu tambahkan:
```php
// config/cors.php atau middleware
'allowed_origins' => ['*']  // atau domain spesifik mobile
```

### File Attachment
Attachment tiket ada di `ticket_attachment` table, dengan field:
- `file_name`, `attachment_type`, `url`, `link_url`, `is_inline`

### Notifikasi Real-time
Tidak ada WebSocket/Push di web versi ini. Polling atau FCM perlu ditambahkan untuk mobile.
