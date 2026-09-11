# Microsoft Graph Config — Wajib `config()`, Jangan `env()` (JARVIES)

## Latar Belakang

JARVIES-new dan EcoSystem-2 sama-sama mengirim email via Microsoft Graph API
(OAuth2 client_credentials) untuk: email set-password/reset customer, dan
relay email tiket customer↔helpdesk. Kredensialnya (`MS_TENANT_ID`,
`MS_CLIENT_ID`, `MS_CLIENT_SECRET`, `MS_SENDER_EMAIL`, `GRAPH_BASE_URL`)
disimpan di `.env`.

**Bug yang ditemukan (2026-07-02):** di JARVIES-new, semua controller/service
yang bicara ke Graph API memanggil `env('MS_TENANT_ID')` dkk **langsung di
application code** (bukan cuma di file `config/*.php`). Begitu
`php artisan config:cache` dijalankan (langkah deploy Laravel yang normal),
Laravel berhenti membaca `.env` sama sekali — semua pemanggilan `env()` di
luar file config balik `null`. Efeknya: URL OAuth jadi
`https://login.microsoftonline.com//oauth2/v2.0/token` (tenant ID kosong),
Microsoft balas 404, exception ke-catch diam-diam → **semua email Graph API
gagal terkirim tanpa error yang terlihat user**, cuma masuk `Log::error()`.

Ditemukan cache config lama (`bootstrap/cache/config.php`) bertanggal 1 Juli
2026 di checkout ini — artinya `config:cache` memang pernah dijalankan.

EcoSystem-2 **tidak** kena bug ini — controllernya sudah benar pakai
`config('services.microsoft_graph.*')` sejak awal (ada komentar eksplisit
"Gunakan config() agar berfungsi saat config:cache di production").

---

## Aturan Wajib

| ❌ Jangan | ✅ Pakai | Kenapa |
|---|---|---|
| `env('MS_TENANT_ID')` di controller/service | `config('services.microsoft_graph.tenant_id')` | `env()` return `null` begitu `config:cache` dijalankan |
| `env('MS_CLIENT_ID')` | `config('services.microsoft_graph.client_id')` | sama |
| `env('MS_CLIENT_SECRET')` | `config('services.microsoft_graph.client_secret')` | sama |
| `env('MS_SENDER_EMAIL')` | `config('services.microsoft_graph.sender_email')` | sama |
| `env('MS_SENDER_NAME')` | `config('services.microsoft_graph.sender_name')` | sama |
| `env('GRAPH_BASE_URL', '...')` | `config('services.microsoft_graph.base_url', '...')` | sama |

`env()` **hanya boleh** dipakai di dalam file `config/*.php` sendiri (itu
memang mekanisme resminya Laravel — file config di-load sekali sebelum cache
dibuat, lalu di-cache; application code selanjutnya baca dari cache lewat
`config()`).

---

## File yang Sudah Diperbaiki (JARVIES-new)

### `config/services.php` — entry baru
```php
'microsoft_graph' => [
    'tenant_id'     => env('MS_TENANT_ID'),
    'client_id'     => env('MS_CLIENT_ID'),
    'client_secret' => env('MS_CLIENT_SECRET'),
    'sender_email'  => env('MS_SENDER_EMAIL'),
    'sender_name'   => env('MS_SENDER_NAME'),
    'base_url'      => env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),
],
```

### Controller/Service yang di-refactor (`env()` → `config()`)
| File | Method yang disentuh |
|---|---|
| `app/Services/GraphRelayService.php` | `__construct()`, `getAccessToken()` |
| `app/Http/Controllers/PasswordSetupController.php` | `generateAndSendToken()`, `getGraphToken()` |
| `app/Http/Controllers/EmailController.php` | `getAccessToken()`, `graphGet()`, `graphPost()`, `graphPatch()` |
| `app/Http/Controllers/AttachmentController.php` | `fetchFromGraph()` |
| `app/Http/Controllers/TicketController.php` | (CC/sender resolution), `graphApiGet()`, `fetchAndCacheStagingBody()`, `graphGetToken()`, `graphFetchAttachment()` |

Selain itu, `PasswordSetupController::getGraphToken()` (EcoSystem-2 & JARVIES-new,
keduanya) sekarang **cache token OAuth selama 55 menit**
(`Cache::remember('password_setup.ms_graph_token', 3300, ...)`) supaya tidak
selalu round-trip OAuth baru tiap kirim email set-password/reset. Ini
optimasi terpisah, hanya di `PasswordSetupController` — `GraphRelayService`
dan controller lain masih fetch token baru tiap panggilan (belum di-cache,
di luar scope perbaikan ini).

---

## Cara Verifikasi (Reproduce & Confirm Fix)

Jalankan via `php artisan tinker --execute="require '...php';"` (bukan
tinker interaktif, supaya bisa dijalankan non-interaktif):

```php
// 1. Simulasikan production: cache config dulu
// (jalankan `php artisan config:cache` di shell sebelum script ini)

// 2. Buktikan env() sudah tidak bisa diandalkan
echo env('MS_TENANT_ID') ? 'SET' : 'NULL';               // harus NULL saat config di-cache
echo config('services.microsoft_graph.tenant_id') ? 'SET' : 'NULL'; // harus SET

// 3. Panggil getGraphToken() via reflection (method private static)
$ref = new ReflectionMethod(\App\Http\Controllers\PasswordSetupController::class, 'getGraphToken');
$ref->setAccessible(true);
$token = $ref->invoke(null); // harus berhasil, bukan RuntimeException
```

Setelah selesai testing, jalankan `php artisan config:clear` supaya `.env`
lokal kembali normal untuk development (jangan biarkan config ke-cache di
environment dev — nanti perubahan `.env` tidak ke-detect sampai di-clear
manual).

---

## Checklist Sebelum Deploy JARVIES-new ke Production

1. Grep pastikan tidak ada `env('MS_` atau `env('GRAPH_BASE_URL'` baru di luar `config/services.php`:
   ```
   grep -rn "env('MS_\|env('GRAPH_BASE_URL" app/ --include=*.php
   ```
   Harus **kosong**. Kalau ada file baru yang manggil Graph API, wajib pakai `config('services.microsoft_graph.*')`.
2. Kalau deploy script menjalankan `php artisan config:cache`, pastikan urutannya: `.env` di-update dulu → baru `config:cache` dijalankan (config cache membekukan nilai `.env` saat itu).
3. Setelah deploy, test kirim 1 email (misal trigger forgot-password) dan cek `storage/logs/laravel.log` untuk `PasswordSetupController: email terkirim` — kalau yang muncul `gagal kirim email`, config kemungkinan belum ke-refresh.

---

## Token Expiry — Standardisasi 24 Jam (2026-07-02)

Sebelumnya EcoSystem-2 pakai `addMinutes(30)` sementara JARVIES-new pakai
`addHours(24)` — padahal keduanya menulis ke kolom `cp_token_expires_at` di
tabel `auth_users` yang **sama** (shared antar 2 aplikasi). Expired time
token jadi tergantung aplikasi mana yang mengirim, bukan jenis alurnya
(setup vs reset). Sudah disamakan jadi **24 jam** di kedua aplikasi:

| File | Sebelum | Sesudah |
|---|---|---|
| `EcoSystem-2/app/Http/Controllers/PasswordSetupController.php` → `generateAndSendToken()` | `now()->addMinutes(30)` | `now()->addHours(24)` |
| `EcoSystem-2/app/Http/Controllers/PasswordSetupController.php` → copy email (2 tempat: setup & reset) | `"...valid for <strong>30 minutes</strong>."` | `"...valid for <strong>24 hours</strong>."` |
| `JARVIES-new/app/Http/Controllers/PasswordSetupController.php` | sudah `addHours(24)` | tidak berubah |
| `JARVIES-new` copy email & `check-email.blade.php` | sudah "24 hours" | tidak berubah |

Dipicu dari 2 flow yang sama-sama panggil `generateAndSendToken()`:
- **Grant Access** (admin EcoSystem klik di `CustomerContactController::createLogin`, atau `AuthController` saat provisioning akun baru)
- **First login self-service** (JARVIES `ProvisionsContactLogin` — customer login pertama kali dengan email yang dikenal di `customer_contact` tapi belum punya `auth_users`)

Kalau ada perubahan expiry di masa depan, **ubah di kedua tempat** (dua
`PasswordSetupController.php` yang terpisah repo) supaya tidak drift lagi.
