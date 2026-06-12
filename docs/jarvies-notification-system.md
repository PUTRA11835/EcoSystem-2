# Jarvies — Notification System Integration

## Overview

Jarvies (customer portal) menggunakan **database yang sama** dengan EcoSystem. Tabel `notifications` dan `notification_sounds` sudah ada; yang diperlukan adalah:

1. Menambah kolom `customer_id` di tabel `notifications` agar notifikasi bisa dikirim ke user customer (Jarvies), bukan hanya employee (EcoSystem).
2. Menambah kolom `notification_sound_id` di tabel `auth_users` agar setiap user — baik employee maupun customer — bisa menyimpan preferensi bunyi notifikasi mereka.
3. Membuat controller Jarvies yang menggunakan `customer_id` dari sesi, bukan `employee_id`.
4. Mendaftarkan route API Jarvies terpisah.

> **Catatan penting:** Tabel `auth_users` sudah menampung kedua tipe user. User Jarvies memiliki `customer_id` berisi data, sedangkan `employee_id`-nya `NULL`. Logika autentikasi tidak berubah.

---

## 1. Perubahan Database

### 1.1 Migrasi — Tambah `customer_id` ke `notifications`

**File baru:** `database/migrations/2026_XX_XX_000001_add_customer_id_to_notifications_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // nullable — baris employee lama tidak terpengaruh (customer_id = NULL)
            $table->unsignedBigInteger('customer_id')->nullable()->after('employee_id');
            $table->index(['customer_id', 'is_read'], 'notif_customer_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notif_customer_unread_idx');
            $table->dropColumn('customer_id');
        });
    }
};
```

Setelah migrasi ini, tabel `notifications` memiliki dua FK opsional:

| Kolom | Diisi oleh |
|---|---|
| `employee_id` | Notifikasi untuk EcoSystem (employee) |
| `customer_id` | Notifikasi untuk Jarvies (customer) |

Keduanya nullable — setiap baris hanya mengisi satu.

---

### 1.2 Migrasi — Tambah `notification_sound_id` ke `auth_users`

**File baru:** `database/migrations/2026_XX_XX_000002_add_notification_sound_id_to_auth_users.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            // NULL = pakai sound default (is_default = true di notification_sounds)
            $table->unsignedBigInteger('notification_sound_id')
                  ->nullable()
                  ->after('is_active');

            $table->foreign('notification_sound_id')
                  ->references('id')
                  ->on('notification_sounds')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropForeign(['notification_sound_id']);
            $table->dropColumn('notification_sound_id');
        });
    }
};
```

> Kolom ini berlaku untuk **semua user** (`auth_users`): employee EcoSystem maupun customer Jarvies. Saat `notification_sound_id = NULL`, frontend fallback ke sound yang `is_default = true`.

---

### 1.3 Update Model `AuthUser`

Tambahkan `notification_sound_id` ke `$fillable` dan tambahkan relasi:

```php
// app/Models/AuthUser.php

protected $fillable = [
    // ... kolom yang sudah ada ...
    'notification_sound_id',
];

public function notificationSound()
{
    return $this->belongsTo(NotificationSound::class, 'notification_sound_id');
}
```

---

### 1.4 Update Model `Notification`

Tambahkan `customer_id` ke `$fillable`:

```php
// app/Models/Notification.php

protected $fillable = [
    'employee_id',
    'customer_id',   // tambahkan ini
    'type', 'ticket_id', 'message_id',
    'from_employee_id', 'from_name', 'preview', 'link',
    'is_read', 'read_at',
];
```

---

## 2. Jenis Notifikasi untuk Jarvies

Notifikasi dikirim ke customer ketika ada aktivitas pada tiket yang mereka miliki.

| Type | Pemicu | Penerima |
|---|---|---|
| `ticket_reply` | Helpdesk/employee membalas tiket customer | Customer pemilik tiket |
| `ticket_status_changed` | Status tiket berubah | Customer pemilik tiket |
| `ticket_closed` | Tiket ditutup | Customer pemilik tiket |
| `ticket_assigned` | Tiket baru berhasil dibuat/diassign | Customer pemilik tiket |

---

## 3. Cara Trigger Notifikasi dari EcoSystem ke Jarvies

Saat helpdesk membalas tiket atau mengubah status, tambahkan logika berikut di controller EcoSystem yang relevan.

### Helper function (bisa diletakkan di `NotificationHelper` atau langsung di controller)

```php
use App\Models\Notification;
use App\Models\Ticket;

/**
 * Kirim notifikasi ke semua customer_id yang terhubung ke sebuah tiket.
 * Satu tiket bisa dimiliki oleh satu customer — ambil dari ticket->customer_id.
 */
function notifyCustomer(Ticket $ticket, string $type, string $fromName, string $preview, string $link): void
{
    try {
        // Cari auth_user yang merupakan customer pemilik tiket ini
        $customerUserId = \App\Models\AuthUser::where('customer_id', $ticket->customer_id)
            ->where('is_active', true)
            ->value('id');   // ambil auth_users.id (bukan customer_id)

        if (!$customerUserId) {
            return; // customer belum punya akun Jarvies, skip
        }

        // Simpan ke notifications dengan customer_id, bukan employee_id
        Notification::create([
            'customer_id' => $ticket->customer_id,
            'type'        => $type,
            'ticket_id'   => $ticket->id,
            'from_name'   => $fromName,
            'preview'     => $preview,
            'link'        => $link,
            'is_read'     => false,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::warning('Jarvies notification creation failed (non-fatal)', [
            'error'       => $e->getMessage(),
            'ticket_id'   => $ticket->id,
            'type'        => $type,
        ]);
    }
}
```

### Contoh — Trigger saat helpdesk reply tiket

```php
// Di TicketMessageController::store() — setelah pesan tersimpan
notifyCustomer(
    ticket: $ticket,
    type: 'ticket_reply',
    fromName: $senderName,
    preview: \Illuminate\Support\Str::limit(strip_tags($messageText), 100),
    link: '/jarvies/tickets/' . $ticket->id,
);
```

### Contoh — Trigger saat status tiket berubah

```php
// Di TicketController saat update status
notifyCustomer(
    ticket: $ticket,
    type: 'ticket_status_changed',
    fromName: $changedByName,
    preview: "Your ticket status has been updated to: " . ucfirst(str_replace('_', ' ', $newStatus)),
    link: '/jarvies/tickets/' . $ticket->id,
);
```

---

## 4. Controller Jarvies

**File baru:** `app/Http/Controllers/Jarvies/JarviesNotificationController.php`

> Seluruh endpoint menggunakan sesi Jarvies — `session('jarvies_user')` atau middleware Jarvies yang sudah ada.

```php
<?php

namespace App\Http\Controllers\Jarvies;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationSound;
use App\Models\AuthUser;
use Illuminate\Http\Request;

class JarviesNotificationController extends Controller
{
    // Helper: ambil customer_id dari sesi Jarvies saat ini
    private function customerId(): ?int
    {
        return session('jarvies_user.customer_id') ?? null;
    }

    private function authUserId(): ?int
    {
        return session('jarvies_user.auth_user_id') ?? null;
    }

    private function guardCustomer()
    {
        if (!$this->customerId()) {
            abort(response()->json(['success' => false, 'message' => 'Unauthorized'], 401));
        }
    }

    /**
     * GET /jarvies/api/notifications
     * Daftar 20 notifikasi terbaru + unread count untuk bell dropdown.
     */
    public function index()
    {
        $this->guardCustomer();
        $customerId = $this->customerId();

        $notifications = Notification::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'ticket_id'  => $n->ticket_id,
                'from_name'  => $n->from_name,
                'preview'    => $n->preview,
                'link'       => $n->link,
                'is_read'    => $n->is_read,
                'created_at' => $n->created_at?->diffForHumans(),
            ]);

        $unreadCount = Notification::where('customer_id', $customerId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * GET /jarvies/api/notifications/unread-count
     * Polling ringan untuk badge bell.
     */
    public function unreadCount()
    {
        $this->guardCustomer();

        $count = Notification::where('customer_id', $this->customerId())
            ->where('is_read', false)
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * PUT /jarvies/api/notifications/{id}/read
     */
    public function markRead($id)
    {
        $this->guardCustomer();

        Notification::where('id', $id)
            ->where('customer_id', $this->customerId())
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * PUT /jarvies/api/notifications/read-all
     */
    public function markAllRead()
    {
        $this->guardCustomer();

        Notification::where('customer_id', $this->customerId())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /jarvies/api/notifications/bulk-delete
     * Hapus semua notifikasi yang sudah dibaca.
     */
    public function bulkDelete()
    {
        $this->guardCustomer();

        Notification::where('customer_id', $this->customerId())
            ->where('is_read', true)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /jarvies/api/notification-sounds
     * Daftar semua sound yang tersedia + sound yang sedang dipilih user.
     */
    public function sounds()
    {
        $this->guardCustomer();

        $sounds = NotificationSound::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'url'        => '/sounds/' . $s->filename,
                'is_default' => $s->is_default,
            ]);

        // Ambil preferensi sound user saat ini
        $authUser      = AuthUser::find($this->authUserId());
        $selectedSoundId = $authUser?->notification_sound_id
            ?? NotificationSound::where('is_default', true)->value('id');

        return response()->json([
            'success'           => true,
            'data'              => $sounds,
            'selected_sound_id' => $selectedSoundId,
        ]);
    }

    /**
     * PUT /jarvies/api/notification-sounds/preference
     * Simpan pilihan sound user.
     * Body: { "sound_id": 2 }
     */
    public function saveSound(Request $request)
    {
        $this->guardCustomer();

        $soundId = $request->input('sound_id');

        // Validasi: sound harus ada, atau null (reset ke default)
        if ($soundId !== null && !NotificationSound::find($soundId)) {
            return response()->json(['success' => false, 'message' => 'Sound not found.'], 422);
        }

        AuthUser::where('id', $this->authUserId())
            ->update(['notification_sound_id' => $soundId]);

        return response()->json(['success' => true]);
    }
}
```

---

## 5. Route Registrasi

Tambahkan di `routes/api.php` (atau file route Jarvies yang sudah ada), di dalam middleware Jarvies auth:

```php
// routes/api.php  — di dalam Route::middleware(['jarvies.auth'])->prefix('jarvies')->group(...)

Route::get('/notifications',             [JarviesNotificationController::class, 'index']);
Route::get('/notifications/unread-count',[JarviesNotificationController::class, 'unreadCount']);
Route::put('/notifications/read-all',    [JarviesNotificationController::class, 'markAllRead']);
Route::put('/notifications/{id}/read',   [JarviesNotificationController::class, 'markRead']);
Route::delete('/notifications/bulk-delete', [JarviesNotificationController::class, 'bulkDelete']);

Route::get('/notification-sounds',              [JarviesNotificationController::class, 'sounds']);
Route::put('/notification-sounds/preference',   [JarviesNotificationController::class, 'saveSound']);
```

---

## 6. API Endpoints Ringkasan

| Method | URL | Fungsi |
|---|---|---|
| `GET` | `/jarvies/api/notifications` | 20 notif terbaru + unread count |
| `GET` | `/jarvies/api/notifications/unread-count` | Hanya jumlah belum dibaca (polling) |
| `PUT` | `/jarvies/api/notifications/{id}/read` | Tandai satu notifikasi dibaca |
| `PUT` | `/jarvies/api/notifications/read-all` | Tandai semua dibaca |
| `DELETE` | `/jarvies/api/notifications/bulk-delete` | Hapus semua yang sudah dibaca |
| `GET` | `/jarvies/api/notification-sounds` | Daftar sound + sound yang dipilih |
| `PUT` | `/jarvies/api/notification-sounds/preference` | Simpan pilihan sound |

---

## 7. Frontend Jarvies — Panduan Implementasi

### 7.1 Polling Badge

Jalankan setiap 30 detik. Jika `count > 0`, mainkan sound.

```javascript
const POLL_INTERVAL = 30_000;
let lastCount = 0;

async function pollUnreadCount() {
    try {
        const res  = await fetch('/jarvies/api/notifications/unread-count', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        const count = json.count;
        updateBellBadge(count);

        // Mainkan sound hanya jika ada notifikasi baru sejak poll terakhir
        if (count > lastCount) {
            playNotificationSound();
        }
        lastCount = count;
    } catch (_) {}
}

setInterval(pollUnreadCount, POLL_INTERVAL);
pollUnreadCount(); // panggil langsung saat halaman load
```

### 7.2 Fungsi Sound

```javascript
let currentAudio = null;

async function loadSelectedSound() {
    try {
        const res  = await fetch('/jarvies/api/notification-sounds', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        const selectedId = json.selected_sound_id;
        const sound = json.data.find(s => s.id === selectedId) ?? json.data.find(s => s.is_default);
        if (sound) {
            currentAudio = new Audio(sound.url);
            currentAudio.volume = 0.6;
        }
    } catch (_) {}
}

function playNotificationSound() {
    if (!currentAudio) return;
    // Clone agar bisa overlap jika dipanggil cepat
    currentAudio.cloneNode().play().catch(() => {});
}

// Panggil saat halaman pertama load
loadSelectedSound();
```

### 7.3 Halaman Pengaturan Bunyi

Tampilkan daftar sound dari `GET /jarvies/api/notification-sounds`. Berikan tombol preview dan simpan pilihan via `PUT /jarvies/api/notification-sounds/preference`.

```javascript
async function loadSoundSettings() {
    const res  = await fetch('/jarvies/api/notification-sounds', { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.success) return;

    // json.data        → array sound { id, name, url, is_default }
    // json.selected_sound_id → id sound yang sedang dipilih user

    renderSoundOptions(json.data, json.selected_sound_id);
}

async function saveSoundPreference(soundId) {
    await fetch('/jarvies/api/notification-sounds/preference', {
        method: 'PUT',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ sound_id: soundId }),
    });
    // Reload audio object supaya sound berikutnya langsung pakai yang baru
    await loadSelectedSound();
}

// Preview sound sebelum disimpan
function previewSound(url) {
    new Audio(url).play().catch(() => {});
}
```

### 7.4 Mapping Icon per Tipe (Bell Dropdown)

| Type | Icon yang disarankan | Warna |
|---|---|---|
| `ticket_reply` | `fa-reply` | Biru |
| `ticket_status_changed` | `fa-sync-alt` | Kuning |
| `ticket_closed` | `fa-check-circle` | Hijau |
| `ticket_assigned` | `fa-ticket-alt` | Indigo |

---

## 8. Skema Data Lengkap Setelah Migrasi

### Tabel `notifications` (sesudah migrasi)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | — |
| `employee_id` | bigint, nullable | Penerima EcoSystem |
| `customer_id` | bigint, nullable | **Penerima Jarvies (baru)** |
| `type` | string(50) | Kategori notifikasi |
| `ticket_id` | bigint, nullable | Tiket terkait |
| `message_id` | bigint, nullable | Pesan terkait |
| `from_employee_id` | bigint, nullable | ID pengirim (employee) |
| `from_name` | string(255), nullable | Nama pengirim (display) |
| `preview` | text, nullable | Cuplikan teks |
| `link` | string(500), nullable | URL tujuan |
| `is_read` | boolean | Status baca |
| `read_at` | timestamp, nullable | Waktu dibaca |

### Tabel `auth_users` (sesudah migrasi)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | — |
| `employee_id` | bigint, nullable | Link ke employee EcoSystem |
| `customer_id` | bigint, nullable | Link ke customer Jarvies |
| `notification_sound_id` | bigint, nullable | **Preferensi bunyi (baru)** |
| ... | ... | Kolom lain tidak berubah |

---

## 9. Keamanan

- Semua endpoint `/jarvies/api/notifications/*` wajib diproteksi middleware autentikasi Jarvies — pastikan `customer_id` diambil dari sesi server-side, **bukan** dari request body/query string.
- Query `notifications` selalu difilter `WHERE customer_id = ?` — customer tidak bisa membaca notifikasi customer lain.
- Endpoint `saveSound` memvalidasi bahwa `sound_id` yang dikirim benar-benar ada di tabel `notification_sounds` sebelum disimpan.
- Output `from_name` dan `preview` di frontend harus di-escape untuk mencegah XSS (gunakan `textContent` atau fungsi escapeHtml yang sudah ada di EcoSystem).

---

## 10. File yang Perlu Dibuat / Diubah

| File | Aksi |
|---|---|
| `database/migrations/2026_XX_XX_000001_add_customer_id_to_notifications_table.php` | **Buat baru** |
| `database/migrations/2026_XX_XX_000002_add_notification_sound_id_to_auth_users.php` | **Buat baru** |
| `app/Models/Notification.php` | Tambah `customer_id` ke `$fillable` |
| `app/Models/AuthUser.php` | Tambah `notification_sound_id` ke `$fillable` + relasi |
| `app/Http/Controllers/Jarvies/JarviesNotificationController.php` | **Buat baru** |
| `routes/api.php` (atau file route Jarvies) | Daftarkan 7 route baru |
| Controller EcoSystem yang memicu notifikasi | Tambah panggilan `notifyCustomer()` |
| Frontend Jarvies (bell, settings page) | Implementasi polling + sound picker |
