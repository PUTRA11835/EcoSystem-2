# Audit: Multi-Role Assignment & Primary Role Problem

**Tanggal:** 2026-07-01  
**Status:** Investigasi selesai, belum di-fix sepenuhnya

---

## Latar Belakang Masalah

Seorang user bisa memiliki **lebih dari 1 role** di sistem (contoh: DS User + DS Head).  
Sistem menyimpan semua role di `session('user')['role_ids']`, namun hampir semua controller hanya membaca **role utama** via `session('user')['role']['id']`.

### Kenapa Role Utama Bisa Salah?

Query di `AuthController::buildEmployeeSessionData()` tidak menggunakan `ORDER BY`:

```php
$allRoles = DB::table('employee_role_assignment as era')
    ->join('employee_role as er', 'era.role_id', '=', 'er.id')
    ->where('era.employee_id', $employeeId)
    ->select('er.id', 'er.name')
    ->get();

$primaryRoleId = $roleIds[0] ?? 0; // ← urutan tidak dijamin!
```

MySQL tidak menjamin urutan tanpa `ORDER BY`, sehingga role pertama yang dikembalikan bisa role mana saja — bukan yang paling tinggi secara struktural.

### Kenapa `ORDER BY id` Tidak Cukup?

ID role tidak mencerminkan hierarki jabatan:

| Role | ID | Hierarki Struktural |
|---|---|---|
| EC Administrator | 1 | ← Tertinggi |
| DS Head | 5 | ← Tinggi |
| DS Manager | 14 | ← Menengah-Tinggi |
| DS Helpdesk | 6 | ← Menengah |
| RPMO Head | 7 | ← Menengah |
| DS User | 2 | ← Lebih Rendah |
| EC User | 3 | ← Lebih Rendah |

`ORDER BY er.id ASC` → DS User (2) jadi primary, padahal DS Head (5) lebih tinggi.

---

## Fondasi yang Sudah Ada (Belum Dipakai Konsisten)

Codebase sudah memiliki infrastruktur untuk multi-role:

| Komponen | File | Status |
|---|---|---|
| `SessionUser` wrapper class | `app/Support/SessionUser.php` | ✅ Ada |
| `role_ids` array di session | `AuthController` | ✅ Selalu di-set |
| `hasRole(int $roleId)` | `app/Models/Employee.php` | ✅ Ada |
| `hasAnyRole(array $roleIds)` | `app/Models/Employee.php` | ✅ Ada |
| `getRoleIds()` | `app/Models/Employee.php` | ✅ Ada |

**Yang sudah menggunakan dengan benar:**
- `ReportingController` — pakai `$sessionUser['role_ids'] ?? [$sessionUser['role']['id']]`
- `TimesheetController` — pakai `$user->hasAnyRole()`
- `StagingTicketController` — pakai `SessionUser::fromSession()`

---

## Skala Permasalahan

**~330+ occurrences** di 50-60 file controller yang hanya mengecek primary role.

### Controller Paling Terdampak

| File | Jumlah Tempat | Prioritas |
|---|---|---|
| `TicketController.php` | 40+ | 🔴 Tinggi |
| `CalendarController.php` | 15+ | 🔴 Tinggi |
| `TimesheetController.php` | 16+ | 🔴 Tinggi |
| `MandaysController.php` | 14+ | 🔴 Tinggi |
| `ReportingController.php` | 8+ | 🟡 Sebagian sudah benar |
| `StagingTicketController.php` | 8+ | 🟡 Sebagian sudah benar |
| `PeriodManagementController.php` | 6+ | 🟠 Sedang |
| `SlaController.php` | 6+ | 🟠 Sedang + hardcoded int |
| `AdminBackupController.php` | 3+ | 🟢 Rendah (admin only) |
| `AdminJobController.php` | 2+ | 🟢 Rendah (admin only) |
| `ActivityLogController.php` | 2+ | 🟢 Rendah (admin only) |

### Masalah Tambahan: Hardcoded Role ID

Beberapa file masih pakai angka langsung, bukan enum:

```php
// SlaController.php
if (in_array($roleId, [1, 5, 6], true)) { }  // ❌

// AdminNotificationSoundController.php
if ((int) session('user.role.id') !== 1) { }  // ❌ Harus pakai RoleId::EC_ADMINISTRATOR->value
```

### Masalah di Service Layer

```php
// PeriodService.php - Line 59
$roleId = $roleIds[0] ?? 0;  // ❌ Hanya check role pertama, bukan yang paling tinggi
$domain = $this->getDomainForRole($roleId);
```

---

## Fix yang Sudah Diterapkan (2026-07-01)

### `TicketController::removeMember()` & `addMember()`

Diubah dari cek primary role menjadi cek semua role:

```php
// SEBELUM
$roleId     = $sessionUser['role']['id'];
$isHelpdesk = in_array($roleId, RoleId::HELPDESK_GROUP, true);

// SESUDAH
$roleIds    = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id']]);
$isHelpdesk = (bool) array_intersect($roleIds, RoleId::HELPDESK_GROUP);
```

### JS `toggleMemberBtn` di `ticket/show.blade.php`

Error handling diperbaiki agar menampilkan pesan error yang lebih informatif:

```javascript
// SEBELUM
} catch {
    showNotification('Error updating member.', 'error');
}

// SESUDAH
try { data = await res.json(); } catch { showNotification(`Server error (HTTP ${res.status})`, 'error'); return; }
...
} catch (err) {
    showNotification('Network error: ' + (err?.message || 'unknown'), 'error');
}
```

---

## Rencana Perbaikan Bertahap

### Phase 1 — Controller Tiket (Prioritas Tinggi)
- [ ] `TicketController.php` — migrate semua 40+ role check ke `role_ids`
- Estimasi: 2-3 hari

### Phase 2 — Controller Lain yang Sering Dipakai
- [ ] `MandaysController.php`
- [ ] `CalendarController.php`
- [ ] `TimesheetController.php`
- Estimasi: 1-2 hari

### Phase 3 — Service Layer
- [ ] `PeriodService.php` — fix `$roleIds[0]` menjadi cek semua role
- Estimasi: 0.5 hari

### Phase 4 — Cleanup Hardcoded Values
- [ ] `SlaController.php` — ganti `[1, 5, 6]` dengan RoleId enum
- [ ] `AdminNotificationSoundController.php` — ganti `1` dengan `RoleId::EC_ADMINISTRATOR->value`
- Estimasi: 0.5 hari

### Phase 5 — Standardisasi Pattern
- [ ] Semua controller migrate ke `SessionUser::fromSession()` + `hasAnyRole()`
- [ ] Hapus direct access `$sessionUser['role']['id']` kecuali untuk display saja
- Estimasi: 2-3 hari

**Total estimasi: 4-6 hari**

---

## Pattern yang Direkomendasikan

```php
// ✅ CARA YANG BENAR — cek semua role
$roleIds    = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id']]);
$isAdmin    = in_array(RoleId::EC_ADMINISTRATOR->value, $roleIds, true);
$isHelpdesk = (bool) array_intersect($roleIds, RoleId::HELPDESK_GROUP);

// ✅ ATAU menggunakan SessionUser wrapper
$user = SessionUser::fromSession();
if ($user->hasAnyRole([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value])) { }

// ❌ JANGAN — hanya cek primary role
$roleId     = $sessionUser['role']['id'];
$isHelpdesk = in_array($roleId, RoleId::HELPDESK_GROUP, true);
```
