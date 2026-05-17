# Notification System

## Ringkasan

Sistem notifikasi EcoSystem menggunakan pendekatan **pull-based polling** — frontend melakukan request ke server setiap 30 detik untuk mengecek jumlah notifikasi yang belum dibaca. Notifikasi disimpan di database dan dipicu oleh event bisnis tertentu (mention, proposal, approval, dll).

---

## Database

### Tabel: `notifications`

| Kolom | Tipe | Default | Keterangan |
|---|---|---|---|
| `id` | bigint, PK | auto | Primary key |
| `employee_id` | unsignedBigInteger | — | ID penerima notifikasi |
| `type` | string(50) | `'mention'` | Kategori notifikasi |
| `ticket_id` | unsignedBigInteger, nullable | null | Ticket terkait |
| `message_id` | unsignedBigInteger, nullable | null | Pesan ticket terkait |
| `from_employee_id` | unsignedBigInteger, nullable | null | ID pengirim |
| `from_name` | string(255), nullable | null | Nama pengirim (display) |
| `preview` | text, nullable | null | Cuplikan isi notifikasi |
| `link` | string(500), nullable | null | URL tujuan saat diklik |
| `is_read` | boolean | `false` | Status baca |
| `read_at` | timestamp, nullable | null | Waktu dibaca |
| `created_at` | timestamp | — | Waktu dibuat |
| `updated_at` | timestamp | — | Waktu diperbarui |

**Index:**
- `[employee_id, is_read]` — untuk query notifikasi belum dibaca per user
- `ticket_id` — untuk query terkait ticket

**Migrations:**
- `2026_04_01_000002_create_notifications_table.php`
- `2026_04_24_000003_add_link_to_notifications_table.php`

---

## Model

**File:** `app/Models/Notification.php`

```php
class Notification extends Model {
    protected $table    = 'notifications';
    protected $fillable = [
        'employee_id', 'type', 'ticket_id', 'message_id',
        'from_employee_id', 'from_name', 'preview', 'link',
        'is_read', 'read_at',
    ];
    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];
}
```

---

## Jenis Notifikasi

### 1. `mention`
- **Pemicu:** Employee menulis pesan ticket dengan mention `@nama` atau `@role`
- **Penerima:** Semua employee yang di-mention
- **Controller:** `TicketMessageController::store()`
- **Preview:** Cuplikan isi pesan yang mengandung mention

### 2. `timesheet_submitted`
- **Pemicu:** Consultant submit timesheet untuk approval
- **Penerima:** Semua Head aktif sesuai domain timesheet
- **Controller:** `TimesheetController`
- **Preview:** `"{Nama} submitted a [project/office/support] timesheet for approval"`

### 3. `late_exception_submitted`
- **Pemicu:** Employee mengajukan late access request ke periode yang sudah tutup
- **Penerima:** Head of Support / Head of Project (sesuai domain)
- **Controller:** `PeriodManagementController`
- **Preview:** `"{Employee} submitted a late access request for {Period} ({Domain}). Notes: {notes}"`

### 4. `late_exception_head_approved`
- **Pemicu:** Head menyetujui late access request
- **Penerima:** Employee pengaju
- **Controller:** `PeriodManagementController`

### 5. `late_exception_pending_rpmo`
- **Pemicu:** Head menyetujui → request diteruskan ke RPMO untuk review final
- **Penerima:** Semua employee RPMO aktif
- **Controller:** `PeriodManagementController`

### 6. `late_exception_head_rejected`
- **Pemicu:** Head menolak late access request
- **Penerima:** Employee pengaju
- **Controller:** `PeriodManagementController`

### 7. `late_exception_approved`
- **Pemicu:** RPMO menyetujui late access request (final approval)
- **Penerima:** Employee pengaju
- **Controller:** `PeriodManagementController`

### 8. `late_exception_rejected`
- **Pemicu:** RPMO menolak late access request
- **Penerima:** Employee pengaju
- **Controller:** `PeriodManagementController`

### 9. `customer_mandays_proposed`
- **Pemicu:** PIC submit proposal customer mandays untuk review helpdesk
- **Penerima:** Semua employee dengan role Helpdesk yang aktif
- **Controller:** `MandaysController`
- **Preview:** `"Ticket #{number} — Customer mandays proposal submitted for review"`

### 10. `internal_mandays_proposed`
- **Pemicu:** PIC submit proposal internal consultant mandays untuk review head
- **Penerima:** Semua employee dengan role Head of Support atau Head of Project yang aktif
- **Controller:** `MandaysController`
- **Preview:** `"Ticket #{number} — Resolution Days proposal submitted for your review"`

### 11. `customer_mandays_canceled`
- **Pemicu:** Helpdesk membatalkan proposal customer mandays
- **Penerima:** PIC ticket + semua member ticket
- **Controller:** `MandaysController`
- **Preview:** `"Ticket #{number} — Customer mandays proposal has been canceled: {cancel notes}"`

---

## API Endpoints

Semua endpoint memerlukan sesi login yang valid (middleware `auth.session`). Employee hanya bisa mengakses notifikasi miliknya sendiri.

| Method | URL | Fungsi |
|---|---|---|
| `GET` | `/notifications` | Halaman daftar notifikasi (web, paginated 30/hal) |
| `GET` | `/api/notifications` | JSON — 20 notifikasi terbaru + unread count |
| `GET` | `/api/notifications/unread-count` | JSON — hanya jumlah belum dibaca (ringan, untuk polling) |
| `PUT` | `/api/notifications/{id}/read` | Tandai satu notifikasi sebagai sudah dibaca |
| `PUT` | `/api/notifications/read-all` | Tandai semua notifikasi sebagai sudah dibaca |
| `DELETE` | `/api/notifications/bulk-delete` | Hapus semua notifikasi yang sudah dibaca |

### Response: `GET /api/notifications`

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "type": "mention",
      "ticket_id": 456,
      "message_id": 789,
      "from_name": "Alif Hidayat",
      "preview": "Tolong cek tiket ini...",
      "link": "/ticket/456",
      "is_read": false,
      "created_at": "2 minutes ago"
    }
  ],
  "unread_count": 3
}
```

### Response: `GET /api/notifications/unread-count`

```json
{
  "success": true,
  "count": 3
}
```

---

## Alur Kerja (End-to-End)

### Contoh: Mention di pesan ticket

```
1. Employee menulis pesan dengan "@nama" di ticket
       ↓
2. TicketMessageController::store() dipanggil
       ↓
3. createMentionNotifications() dipanggil:
   - Parse mention dari teks pesan
   - Resolve employee/role yang di-mention
   - Skip pengirim (tidak notif ke diri sendiri)
   - Buat 1 record Notification per penerima
       ↓
4. Frontend polling (setiap 30 detik):
   GET /api/notifications/unread-count
   → Badge bell diperbarui
       ↓
5. User buka bell dropdown:
   GET /api/notifications
   → Notifikasi ditampilkan dengan icon @ berwarna merah
       ↓
6. User klik notifikasi:
   PUT /api/notifications/{id}/read
   → is_read = true, read_at = now()
   → Navigasi ke link tujuan (URL ticket)
       ↓
7. Notifikasi tetap tersimpan di DB (status: dibaca)
   Bisa dihapus via "Clear read" (bulk delete)
```

### Alur Late Access Request (2-level approval)

```
Employee ajukan request
       ↓
Notif → Head (late_exception_submitted)
       ↓
Head setuju → Notif ke Employee (late_exception_head_approved)
           → Notif ke RPMO (late_exception_pending_rpmo)
       ↓
RPMO setuju → Notif ke Employee (late_exception_approved)
RPMO tolak  → Notif ke Employee (late_exception_rejected)

Head tolak  → Notif ke Employee (late_exception_head_rejected)
              [alur berhenti]
```

---

## Frontend: Bell Notification

**File:** `resources/views/dashboard.blade.php`

### Struktur HTML

```html
<div id="bellWrapper">
  <button id="bellBtn" onclick="toggleBellDropdown()">
    <i class="fas fa-bell"></i>
    <span id="bellBadge">5</span>  <!-- badge unread count -->
  </button>

  <div id="bellDropdown" class="hidden">
    <!-- Header: judul + tombol "Mark all read" -->
    <div id="bellNotifList">
      <!-- notifikasi di-render oleh JS -->
    </div>
  </div>
</div>
```

### Fungsi JavaScript

| Fungsi | Keterangan |
|---|---|
| `fetchUnreadCount()` | Poll `/api/notifications/unread-count`, update badge. Dijalankan setiap 30 detik via `setInterval` |
| `toggleBellDropdown()` | Buka/tutup dropdown bell. Saat buka pertama kali, load notifikasi |
| `loadBellNotifications()` | Fetch `/api/notifications`, render list dengan icon per tipe |
| `markNotifRead(id, e)` | `PUT /api/notifications/{id}/read`, tandai baca lalu navigasi ke link |
| `markAllNotificationsRead()` | `PUT /api/notifications/read-all`, refresh badge dan dropdown |
| `escapeHtml(str)` | Sanitasi output untuk mencegah XSS pada `from_name` dan `preview` |

### Mapping Icon per Tipe (Bell Dropdown)

| Tipe | Icon | Warna |
|---|---|---|
| `mention` | `@` | Merah |
| `timesheet_submitted` | file | Ungu |
| `late_exception_submitted` | jam | Kuning |
| `late_exception_pending_rpmo` | jam | Biru |
| `late_exception_head_approved` | centang | Hijau |
| `late_exception_head_rejected` | silang | Merah |
| `late_exception_approved` | kunci terbuka | Hijau |
| `late_exception_rejected` | ban | Merah |
| `customer_mandays_proposed` | invoice | Biru |
| `customer_mandays_canceled` | silang | Oranye |
| `internal_mandays_proposed` | users | Indigo |

---

## Halaman Daftar Notifikasi

**File:** `resources/views/notifications/index.blade.php`
**URL:** `/notifications`

### Fitur
- Daftar notifikasi paginasi (30 per halaman), diurutkan terbaru di atas
- Setiap item menampilkan: icon berwarna, judul tipe, nama pengirim, preview teks (max 2 baris), waktu relatif (`diffForHumans()`), dan dot merah jika belum dibaca
- Tombol per item:
  - **View** — navigasi ke `link` atau ke halaman ticket terkait
  - **Mark as read** — hanya muncul jika belum dibaca
- Tombol halaman:
  - **Mark all as read** — `PUT /api/notifications/read-all`
  - **Clear read** — `DELETE /api/notifications/bulk-delete` (dengan konfirmasi)

---

## Pola Pembuatan Notifikasi di Controller

### Satu penerima

```php
Notification::create([
    'employee_id'      => $recipientId,
    'type'             => 'mention',
    'ticket_id'        => $ticketId,
    'message_id'       => $messageId,
    'from_employee_id' => $senderId,
    'from_name'        => $senderName,
    'preview'          => Str::limit($messageText, 120),
    'link'             => "/ticket/{$ticketId}",
    'is_read'          => false,
]);
```

### Banyak penerima berdasarkan role

```php
Employee::whereIn('role_id', [RoleId::HELPDESK->value])
    ->where('is_active', true)
    ->pluck('employee_id')
    ->each(function ($empId) use ($type, $fromName, $preview, $link) {
        Notification::create([
            'employee_id' => $empId,
            'type'        => $type,
            'from_name'   => $fromName,
            'preview'     => $preview,
            'link'        => $link,
            'is_read'     => false,
        ]);
    });
```

### Error handling

Pembuatan notifikasi selalu dibungkus `try-catch` dan dicatat sebagai warning — kegagalan notifikasi tidak menghentikan proses utama.

```php
try {
    // buat notifikasi...
} catch (\Exception $e) {
    Log::warning('Notification creation failed (non-fatal)', [
        'error' => $e->getMessage(),
    ]);
}
```

---

## Keamanan

- Semua route notifikasi menggunakan middleware `auth.session` — hanya user yang login bisa mengakses
- Query selalu difilter `WHERE employee_id = session('user')['id']` — tidak bisa akses notifikasi milik orang lain
- Output frontend di-escape dengan `escapeHtml()` untuk mencegah XSS pada nama pengirim dan preview

---

## File Terkait

| File | Peran |
|---|---|
| `app/Models/Notification.php` | Model Eloquent |
| `app/Http/Controllers/NotificationController.php` | Controller utama (CRUD, polling) |
| `app/Http/Controllers/TicketMessageController.php` | Trigger: mention |
| `app/Http/Controllers/TimesheetController.php` | Trigger: timesheet submission |
| `app/Http/Controllers/PeriodManagementController.php` | Trigger: late access request workflow |
| `app/Http/Controllers/MandaysController.php` | Trigger: mandays proposal & cancelation |
| `routes/web.php` | Route halaman notifikasi |
| `routes/api.php` | Route API notifikasi |
| `resources/views/notifications/index.blade.php` | Halaman daftar notifikasi |
| `resources/views/dashboard.blade.php` | Bell UI + polling JS |
| `database/migrations/2026_04_01_000002_*` | Migrasi tabel notifications |
| `database/migrations/2026_04_24_000003_*` | Migrasi tambah kolom `link` |
