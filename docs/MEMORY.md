# EcoSystem Project Memory

> **Proyek:** JARVIES — PT Eclectic Consulting Yogyakarta (Customer Portal)
> **Stack:** Laravel 11 + PHP + Blade + Tailwind CSS + MySQL
> **Path:** `d:\Magang\PT Eclectic Consulting Yogyakarta\Project\JARVIES-main`

---

## KONTEKS SISTEM (PENTING)

JARVIES adalah **Customer Side** dari sistem EcoSystem.
- **EcoSystem** (terpisah) → Employee/Admin side, kelola ticket, delivery, project
- **JARVIES (ini)** → Customer side, submit & pantau ticket sendiri
- Keduanya **berbagi database yang sama** (`ecosystem`)

Lihat detail arsitektur di: [architecture.md](../architecture.md)

---

## AUTENTIKASI (CUSTOM — BUKAN LARAVEL AUTH)

- Tabel sentral: `auth_users` (bukan `users`)
- Login via: email, username, atau phone
- Token disimpan di Laravel session (`session('auth_token')`, `session('user')`)
- Middleware: `JarviesAuth` (bukan `auth`) — allow role 1 & 3 saja
- **JANGAN** gunakan `Auth::user()` — selalu gunakan `session('user')`
- Role: 1 = Admin, 3 = Customer (role 2 = Employee hanya di EcoSystem)

### Session user structure untuk Customer:
```php
session('user') = [
    'id'           => customer_id,
    'type'         => 'customer',
    'customer_code'=> '...',
    'company_name' => '...',
    'email'        => '...',
    'role'         => ['id' => 3, 'name' => 'Customer'],
]
```

---

## FITUR TICKET (STATUS IMPLEMENTASI)

| Fitur | Route | Controller Method | Status |
|---|---|---|---|
| List tickets | `GET /tickets` | `index()` + `getTickets()` AJAX | ✅ |
| Buat ticket (form email-style) | `GET /tickets/create` | `create()` | ✅ |
| Submit ticket | `POST /tickets` | `store()` | ✅ |
| Detail ticket (modal) | JS `viewTicketDetail()` | - | ✅ |
| Load messages per ticket | `GET /tickets/{id}/messages` | `getMessages()` | ✅ |
| Reply/comment ticket | `POST /tickets/{id}/comment` | `addComment()` | ✅ |
| My ticket redirect | `GET /my/tickets/{id}` | `showMyTicket()` | ✅ |

### Pola penting store():
- Customer: `customer_id` auto-set dari session, `channel: 'web'`
- `body` field opsional → disimpan sebagai `ticket_message` pertama
- `description` = subject email, `body` = isi pesan

---

## EMAIL INTEGRATION (Microsoft Graph API)

- MS365 mailbox: `MS_SENDER_EMAIL` di `.env`
- `processInbox()` → pull email masuk → buat ticket/message di DB
- `sendTicketReply()` → kirim email balasan ke customer
- **Scheduler:** `email:process-inbox` setiap 5 menit (lihat `routes/console.php`)
- Jalankan manual: `php artisan email:process-inbox`

### Alur email customer:
```
Customer kirim email ke MS_SENDER_EMAIL
    → email:process-inbox pull inbox
    → Ticket baru / TicketMessage ditambahkan ke DB
    → EcoSystem (employee) lihat di DB yang sama
```

---

## POLA PENTING

- Views: `@extends('layouts.app')`, `@section('content')`, `@push('scripts')`
- AJAX JSON response untuk semua form action
- Customer hanya lihat tiket miliknya (`customer_id = session('user.id')`)
- `is_internal_note = true` → TIDAK ditampilkan ke customer di `getMessages()`
- Kirim email: **Microsoft Graph API** (bukan SMTP) → `EmailController`
- Model `Employee` dan `Customer` gunakan `HasApiTokens` dari `laravel/sanctum` (sudah diinstall)

---

## DEPENDENSI TAMBAHAN

- `laravel/sanctum` → diinstall untuk `HasApiTokens` di Customer & Employee model

## 3 JENIS ASAL TICKET — SEMUA VIA STAGING

| # | Asal | Via Staging? | Notes |
|---|---|---|---|
| 1 | Web form (tanpa OAuth) | ✅ Ya | body disimpan ke staging.body |
| 2 | Web form (dengan OAuth) | ✅ Ya | Subject email prefix `[PENDING]`, update email_thread_id di staging |
| 3 | Email langsung ke helpdesk | ✅ Ya | processInbox() buat staging (bukan ticket langsung) |

**Semua jalur masuk staging dulu, EcoSystem yang approve → buat ticket.**

**Fix JARVIES (sudah diterapkan):**
- Migration: tambah kolom `body TEXT NULL` ke `staging_tickets` ✅
- `StagingTicketService::createFromWeb()` → simpan `body` ✅
- `StagingTicket::$fillable` → tambah `'body'` ✅
- `TicketController@store` → email OAuth subject diberi prefix `[PENDING]` ✅
- `EmailController@processInbox` → email baru buat staging, bukan ticket langsung ✅

**Fix EcoSystem (harus diimplementasikan):**
→ Saat approve staging: buat `ticket_message` pertama dari `staging.body`
→ Salin `email_thread_id` dari staging ke ticket

Detail lengkap: [ecosystem-staging-fix.md](ecosystem-staging-fix.md)

---

## DOKUMENTASI MOBILE

Dokumentasi lengkap sistem JARVIES untuk referensi mobile app:
→ [jarvies-mobile-reference.md](jarvies-mobile-reference.md)

Mencakup: auth flow, token format, DB tables, ticket flow, OAuth Socialite, semua endpoints.

---

## REFERENSI CEPAT

| Kebutuhan | File |
|---|---|
| Auth login/logout | `app/Http/Controllers/AuthController.php` |
| Password setup/reset | `app/Http/Controllers/PasswordSetupController.php` |
| Ticket CRUD + messages | `app/Http/Controllers/TicketController.php` |
| Email inbox processor | `app/Http/Controllers/EmailController.php` |
| Artisan email command | `app/Console/Commands/ProcessEmailInbox.php` |
| Scheduler | `routes/console.php` |
| Routes utama | `routes/web.php` |
| Ticket list + modal | `resources/views/tickets/index.blade.php` |
| Ticket create (email form) | `resources/views/tickets/create.blade.php` |
