# Multi-Role System — EcoSystem-2

Dokumen ini adalah referensi lengkap untuk AI (dan developer) yang bekerja di branch lain dan perlu memahami atau mengimplementasikan ulang sistem multi-role yang ada di branch `multi-role-system`.

---

## Daftar Isi

1. [Konsep Inti](#1-konsep-inti)
2. [Struktur Database](#2-struktur-database)
3. [Urutan Migrasi](#3-urutan-migrasi)
4. [Seeder](#4-seeder)
5. [Models](#5-models)
6. [Enum RoleId](#6-enum-roleid)
7. [Controllers](#7-controllers)
8. [Middleware](#8-middleware)
9. [Routes](#9-routes)
10. [Views / UI](#10-views--ui)
11. [Alur Lengkap (End-to-End)](#11-alur-lengkap-end-to-end)
12. [Permission Matrix Default](#12-permission-matrix-default)
13. [Function-Level Permission](#13-function-level-permission)
14. [Checklist Implementasi di Branch Baru](#14-checklist-implementasi-di-branch-baru)

---

## 1. Konsep Inti

Sistem ini mengizinkan **satu employee memiliki banyak role** (many-to-many). Permission dihitung dengan **union** — jika salah satu role mengizinkan, employee bisa melakukannya.

Ada **3 level granularitas** permission:

| Level | Tabel | Contoh |
|-------|-------|--------|
| **Page/Group** | `menu` (type=`page`/`group`) | Bisa lihat halaman `/tickets` |
| **CRUD per halaman** | `role_menu` pivot (`can_view`, `can_create`, `can_edit`, `can_delete`) | Bisa edit ticket, tapi tidak bisa delete |
| **Function / Tombol** | `menu` (type=`function`) | Tombol "Assign PIC" muncul atau tidak |

---

## 2. Struktur Database

### Tabel `employee_role`
```sql
id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name            VARCHAR(100) UNIQUE NOT NULL
description     VARCHAR(255) NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### Tabel `employee_role_assignment` (pivot many-to-many)
```sql
employee_id     BIGINT UNSIGNED  -- FK → employee.employee_id ON DELETE CASCADE
role_id         BIGINT UNSIGNED  -- FK → employee_role.id ON DELETE CASCADE
created_at      TIMESTAMP
updated_at      TIMESTAMP
PRIMARY KEY (employee_id, role_id)
```

### Tabel `menu`
```sql
id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
parent_id       BIGINT UNSIGNED NULL  -- FK → menu.id (self-referential, tree)
name            VARCHAR(100) NOT NULL
slug            VARCHAR(100) UNIQUE NOT NULL  -- identifier unik, contoh: 'tickets.inbox'
type            ENUM('group', 'page', 'function')
route_name      VARCHAR(150) NULL  -- nama Laravel route
icon            VARCHAR(100) NULL  -- heroicon class
order_seq       INT DEFAULT 0
is_active       BOOLEAN DEFAULT TRUE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### Tabel `role_menu` (pivot role ↔ menu dengan CRUD flags)
```sql
role_id         BIGINT UNSIGNED  -- FK → employee_role.id ON DELETE CASCADE
menu_id         BIGINT UNSIGNED  -- FK → menu.id ON DELETE CASCADE
can_view        BOOLEAN DEFAULT TRUE
can_create      BOOLEAN DEFAULT FALSE
can_edit        BOOLEAN DEFAULT FALSE
can_delete      BOOLEAN DEFAULT FALSE
created_at      TIMESTAMP
updated_at      TIMESTAMP
PRIMARY KEY (role_id, menu_id)
INDEX idx_role_menu_role (role_id)
INDEX idx_role_menu_menu (menu_id)
```

### Relasi Keseluruhan
```
employee ──────< employee_role_assignment >────── employee_role
                                                        │
                                                   role_menu
                                                        │
                                                      menu
                                                  (parent_id self-join)
```

---

## 3. Urutan Migrasi

Jalankan dalam urutan ini. Nomor prefix file sudah mencerminkan urutan yang benar.

### Step 1 — Tabel `employee_role` (harus ada sebelum pivot)

File: `database/migrations/xxxx_create_employee_role_table.php`

```php
Schema::create('employee_role', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->string('description', 255)->nullable();
    $table->timestamps();
});
```

> **Catatan:** Di project ini tabel `employee_role` sudah ada sejak awal (bukan dari branch ini). Yang baru adalah mengubah relasi dari single `role_id` di tabel `employee` menjadi pivot table.

---

### Step 2 — Tabel `menu`

File: `database/migrations/xxxx_create_menu_table.php`

```php
Schema::create('menu', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('parent_id')->nullable();
    $table->string('name', 100);
    $table->string('slug', 100)->unique();
    $table->enum('type', ['group', 'page', 'function'])->default('page');
    $table->string('route_name', 150)->nullable();
    $table->string('icon', 100)->nullable();
    $table->integer('order_seq')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->foreign('parent_id')->references('id')->on('menu')->onDelete('set null');
});
```

---

### Step 3 — Tabel `employee_role_assignment` (pivot many-to-many)

File: `database/migrations/2026_03_17_100001_create_employee_role_assignment_table.php`

```php
Schema::create('employee_role_assignment', function (Blueprint $table) {
    $table->unsignedBigInteger('employee_id');
    $table->unsignedBigInteger('role_id');
    $table->timestamps();

    $table->primary(['employee_id', 'role_id']);

    $table->foreign('employee_id')
          ->references('employee_id')
          ->on('employee')
          ->onDelete('cascade');

    $table->foreign('role_id')
          ->references('id')
          ->on('employee_role')
          ->onDelete('cascade');
});

// Migrasi data lama: jika employee masih punya kolom role_id tunggal
if (Schema::hasColumn('employee', 'role_id')) {
    DB::statement("
        INSERT IGNORE INTO employee_role_assignment (employee_id, role_id, created_at, updated_at)
        SELECT employee_id, role_id, NOW(), NOW()
        FROM employee
        WHERE role_id IS NOT NULL
    ");
}
```

---

### Step 4 — Tabel `role_menu` (pivot role ↔ menu + CRUD flags)

File: `database/migrations/2026_04_17_000002_create_role_menu_table.php`

```php
Schema::create('role_menu', function (Blueprint $table) {
    $table->unsignedBigInteger('role_id');
    $table->unsignedBigInteger('menu_id');
    $table->boolean('can_view')->default(true);
    $table->boolean('can_create')->default(false);
    $table->boolean('can_edit')->default(false);
    $table->boolean('can_delete')->default(false);
    $table->timestamps();

    $table->primary(['role_id', 'menu_id']);

    $table->foreign('role_id')
        ->references('id')
        ->on('employee_role')
        ->onDelete('cascade');

    $table->foreign('menu_id')
        ->references('id')
        ->on('menu')
        ->onDelete('cascade');

    $table->index('role_id', 'idx_role_menu_role');
    $table->index('menu_id', 'idx_role_menu_menu');
});
```

---

## 4. Seeder

Jalankan seeder dalam urutan ini:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=MenuSeeder
php artisan db:seed --class=FunctionPermissionSeeder
php artisan db:seed --class=AdminRoleAssignmentSeeder
```

### RoleSeeder

Mengisi 7 role default ke tabel `employee_role`:

| id | name | Deskripsi |
|----|------|-----------|
| 1 | Admin | Administrator dengan akses penuh |
| 2 | Employee | Karyawan biasa (PIC/Consultant) |
| 3 | Internship | Akses terbatas magang |
| 4 | Head of Project | Approval timesheet, kelola delivery project |
| 5 | Head of Support | Approval timesheet, kelola delivery support |
| 6 | Helpdesk | Menangani & assign ticket |
| 7 | RPMO | Resource & Project Management Office |

### MenuSeeder

Mengisi struktur menu hierarkis (parent-child) ke tabel `menu`, lalu assign permission ke tabel `role_menu`.

**Hierarki Menu:**
```
Dashboard                  (page)
Calendar                   (group)
  ├── Events               (page)
  └── Timesheets           (page)
Reporting                  (group)
  ├── MD Validation        (page)
  └── MD Recap             (page)
Master                     (group)
  ├── Employee             (page)
  └── Customer             (page)
Tiket                      (group)
  ├── Ticket               (page)  → slug: tickets.inbox
  └── Ticket Validation    (page)  → slug: tickets.staging
Delivery                   (group)
  ├── Project              (page)
  └── Support              (page)
Log Activity               (page)
Financial                  (page)
HR & General               (page)
Business Dev               (page)
RPMO                       (group)
  ├── Overview             (page)
  └── Management           (page)
Legal                      (page)
Manajemen                  (group)  ← Admin only
  ├── Role                 (page)   → slug: management.roles
  └── Akses Menu           (page)   → slug: management.permissions
```

### FunctionPermissionSeeder

Mengisi menu dengan `type='function'` — ini adalah tombol/kontrol UI level granular yang dikontrol per-role. Memanggil `RoleSeeder` di dalamnya.

Contoh function slugs:
- `calendar.events.create` — Tombol "Create Event"
- `ticket.assign-pic` — Tombol "Assign PIC"
- `ticket.view-all` — Lihat semua tiket
- `ticket.view-own` — Lihat tiket sendiri
- `ui.ticket.sidebar-tabs` — Sidebar tabs
- `delivery-support.add-new` — Tambah delivery support

### AdminRoleAssignmentSeeder

Assign role Admin (id=1) ke employee dengan `eci = 'ECI_ADMIN'`.

```php
DB::table('employee_role_assignment')->updateOrInsert(
    ['employee_id' => $admin->employee_id, 'role_id' => 1],
    ['created_at' => now(), 'updated_at' => now()]
);
```

---

## 5. Models

### `app/Models/EmployeeRole.php`

```php
class EmployeeRole extends Model
{
    protected $table = 'employee_role';

    protected $fillable = ['name', 'description'];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_role_assignment',
            'role_id', 'employee_id', 'id', 'employee_id')
            ->withTimestamps();
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'role_menu', 'role_id', 'menu_id')
            ->withPivot('can_view', 'can_create', 'can_edit', 'can_delete')
            ->withTimestamps();
    }
}
```

### `app/Models/Menu.php`

```php
class Menu extends Model
{
    protected $table = 'menu';

    protected $fillable = [
        'parent_id', 'name', 'slug', 'type',
        'route_name', 'icon', 'order_seq', 'is_active',
    ];

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('order_seq');
    }

    public function roles()
    {
        return $this->belongsToMany(EmployeeRole::class, 'role_menu', 'menu_id', 'role_id')
            ->withPivot('can_view', 'can_create', 'can_edit', 'can_delete')
            ->withTimestamps();
    }
}
```

### `app/Models/Employee.php` — method terkait role

Tambahkan method-method berikut ke Employee model:

```php
use App\Models\EmployeeRole;
use App\Models\Menu;

/** Many-to-many roles via pivot table */
public function roles()
{
    return $this->belongsToMany(EmployeeRole::class, 'employee_role_assignment',
        'employee_id', 'role_id', 'employee_id', 'id')
        ->withTimestamps();
}

/** Ambil semua menu yang dapat diakses (union dari semua role) */
public function accessibleMenus()
{
    $roleIds = $this->roles()->pluck('employee_role.id');

    return Menu::whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('employee_role.id', $roleIds)
              ->where('role_menu.can_view', true);
        })
        ->where('is_active', true)
        ->orderBy('parent_id')
        ->orderBy('order_seq')
        ->get();
}

/** Cek apakah employee boleh akses menu berdasarkan slug */
public function canAccessMenu(string $slug): bool
{
    $roleIds = $this->roles()->pluck('employee_role.id');

    return Menu::where('slug', $slug)
        ->whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('employee_role.id', $roleIds)
              ->where('role_menu.can_view', true);
        })
        ->where('is_active', true)
        ->exists();
}

/**
 * Cek permission spesifik pada menu.
 * @param string $permission 'can_view' | 'can_create' | 'can_edit' | 'can_delete'
 */
public function hasMenuPermission(string $slug, string $permission = 'can_view'): bool
{
    $roleIds = $this->roles()->pluck('employee_role.id');

    return Menu::where('slug', $slug)
        ->whereHas('roles', function ($q) use ($roleIds, $permission) {
            $q->whereIn('employee_role.id', $roleIds)
              ->where("role_menu.{$permission}", true);
        })
        ->where('is_active', true)
        ->exists();
}

/** Alias ringkas untuk can_view */
public function hasPermission(string $slug): bool
{
    return $this->hasMenuPermission($slug, 'can_view');
}

/** Semua slug permission yang dimiliki (union semua role). Untuk dikirim ke frontend. */
public function allPermissionSlugs(): array
{
    $roleIds = $this->roles()->pluck('employee_role.id');

    return Menu::whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('employee_role.id', $roleIds)
              ->where('role_menu.can_view', true);
        })
        ->where('is_active', true)
        ->pluck('slug')
        ->toArray();
}
```

---

## 6. Enum RoleId

File: `app/Enums/RoleId.php`

```php
enum RoleId: int
{
    case ADMIN           = 1;
    case EMPLOYEE        = 2;
    case CUSTOMER        = 3;
    case HEAD_OF_PROJECT = 4;
    case HEAD_OF_SUPPORT = 5;
    case HELPDESK        = 6;
    case RPMO            = 7;

    /** Semua internal (non-customer) */
    public const INTERNAL_GROUP = [1, 2, 4, 5, 6, 7];

    /** Yang bisa manage tiket */
    public const TICKET_MANAGER_GROUP = [1, 6, 7];

    /** Head roles */
    public const HEAD_GROUP = [4, 5];

    /** Helpdesk + RPMO */
    public const HELPDESK_GROUP = [6, 7];
}
```

**Cara pakai:**
```php
RoleId::ADMIN->value          // → 1
RoleId::from(1)               // → RoleId::ADMIN
RoleId::tryFrom(99)           // → null (safe)
in_array($roleId, RoleId::HELPDESK_GROUP) // → true jika 6 atau 7
```

---

## 7. Controllers

### `app/Http/Controllers/RoleController.php`

Endpoint lengkap:

| Method | URI | Fungsi |
|--------|-----|--------|
| GET | `/api/roles` | List semua role + jumlah employee |
| POST | `/api/roles` | Buat role baru |
| GET | `/api/roles/{id}` | Detail satu role |
| PUT | `/api/roles/{id}` | Update role |
| DELETE | `/api/roles/{id}` | Hapus role (gagal jika masih ada employee) |
| GET | `/api/roles/{id}/permissions` | Semua menu + pivot CRUD untuk role ini |
| PUT | `/api/roles/{id}/permissions/{menuId}` | Update permission satu menu untuk role |
| DELETE | `/api/roles/{id}/permissions/{menuId}` | Cabut akses role ke menu |
| GET | `/api/roles/{id}/employees` | Daftar employee yang punya role ini |
| GET | `/api/employees/{id}/roles` | Daftar role yang dimiliki employee |
| POST | `/api/employees/{id}/roles` | Tambah role ke employee (additive) |
| PUT | `/api/employees/{id}/roles` | Set/replace semua role employee |
| DELETE | `/api/employees/{id}/roles/{roleId}` | Cabut satu role dari employee |
| GET | `/management/roles` | Halaman web Role Management |

**Penting:** DELETE role diblokir jika masih ada employee yang pakai role tersebut.

### `app/Http/Controllers/MenuController.php`

| Method | URI | Fungsi |
|--------|-----|--------|
| GET | `/api/my-menus` | Menu tree yang accessible oleh user yang login |
| GET | `/api/menus` | Semua menu (tree, untuk admin) |
| GET | `/api/menus/all` | Semua menu + info pivot role (untuk halaman permissions) |
| GET | `/api/menus/with-roles` | Semua menu beserta role yang punya akses |
| POST | `/api/menus` | Buat menu baru |
| PUT | `/api/menus/{menuId}` | Update menu |
| DELETE | `/api/menus/{menuId}` | Hapus menu |
| PUT | `/api/menus/{menuId}/roles/{roleId}` | Update permission role terhadap menu |
| DELETE | `/api/menus/{menuId}/roles/{roleId}` | Cabut akses role dari menu |
| GET | `/management/permissions` | Halaman web Permission Management |

---

## 8. Middleware

### `app/Http/Middleware/CheckMenuAccess.php`

Middleware ini memproteksi route berdasarkan slug menu.

```php
class CheckMenuAccess
{
    public function handle(Request $request, Closure $next, string $menuSlug): Response
    {
        $user = session('user');

        // Harus login sebagai employee
        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $employee = Employee::find($user['id'] ?? null);

        // Cek apakah employee punya akses ke menu dengan slug ini
        if (!$employee || !$employee->canAccessMenu($menuSlug)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengakses menu ini.',
                ], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', 'Anda tidak memiliki izin untuk mengakses halaman tersebut.');
        }

        return $next($request);
    }
}
```

**Daftarkan di `bootstrap/app.php` atau `Kernel.php`:**
```php
'menu' => \App\Http\Middleware\CheckMenuAccess::class,
```

**Cara pakai di route:**
```php
Route::get('/tickets', ...)->middleware('menu:tickets.inbox');
Route::get('/management/roles', ...)->middleware('menu:management.roles');
```

---

## 9. Routes

### `routes/web.php` — contoh pola

```php
Route::middleware(['auth.token'])->group(function () {

    // Halaman yang diproteksi dengan middleware menu:slug
    Route::get('/tickets', [TicketController::class, 'index'])
        ->middleware('menu:tickets.inbox')
        ->name('ticket.index');

    Route::get('/staging-tickets', [StagingTicketController::class, 'view'])
        ->middleware('menu:tickets.staging')
        ->name('staging.index');

    Route::prefix('management')->name('management.')->middleware('menu:management')->group(function () {
        Route::get('/roles', [RoleController::class, 'page'])
            ->middleware('menu:management.roles')
            ->name('roles.index');

        Route::get('/permissions', [MenuController::class, 'page'])
            ->middleware('menu:management.permissions')
            ->name('permissions.index');
    });
});
```

### `routes/api.php` — endpoint role & menu

```php
Route::middleware(['auth.token'])->group(function () {

    Route::get('/my-menus', [MenuController::class, 'getMyMenus']);

    // Menu management (Admin only — proteksi via halaman web)
    Route::get('/menus', [MenuController::class, 'index']);
    Route::get('/menus/all', [MenuController::class, 'allWithPermissions']);
    Route::get('/menus/with-roles', [MenuController::class, 'withRoles']);
    Route::post('/menus', [MenuController::class, 'store']);
    Route::put('/menus/{menuId}', [MenuController::class, 'update']);
    Route::delete('/menus/{menuId}', [MenuController::class, 'destroy']);
    Route::put('/menus/{menuId}/roles/{roleId}', [MenuController::class, 'updateRolePermission']);
    Route::delete('/menus/{menuId}/roles/{roleId}', [MenuController::class, 'removeRolePermission']);

    // Role management
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::get('/roles/{id}/permissions', [RoleController::class, 'permissions']);
    Route::put('/roles/{id}/permissions/{menuId}', [RoleController::class, 'updatePermission']);
    Route::delete('/roles/{id}/permissions/{menuId}', [RoleController::class, 'removePermission']);
    Route::get('/roles/{id}/employees', [RoleController::class, 'employees']);

    // Employee ↔ Role assignment
    Route::get('/employees/{employeeId}/roles', [RoleController::class, 'employeeRoles']);
    Route::post('/employees/{employeeId}/roles', [RoleController::class, 'assignRoles']);
    Route::put('/employees/{employeeId}/roles', [RoleController::class, 'syncRoles']);
    Route::delete('/employees/{employeeId}/roles/{roleId}', [RoleController::class, 'revokeRole']);
});
```

---

## 10. Views / UI

### Halaman Role Management (`/management/roles`)

File: `resources/views/management/roles/index.blade.php`

**Fitur UI:**
- Tabel daftar role (ID, Name, Description, Jumlah Employee, Actions)
- **Tombol "Add Role"** → modal create dengan form `name` + `description`
- **Tombol "Edit"** per baris → modal edit (sama dengan create, pre-filled)
- **Tombol "Delete"** per baris → konfirmasi, gagal jika ada employee
- **Badge jumlah employee** yang clickable → modal "Members" yang menampilkan daftar employee dengan role ini
- **Tombol "Menu Access"** per baris → modal manage akses menu, dengan:
  - Filter: search nama menu, filter by type (group/page/function), filter by status (has access / no access)
  - Toggle checkbox per baris menu untuk grant/revoke `can_view`
  - Klik baris untuk expand dan set `can_create`, `can_edit`, `can_delete`

**JavaScript key functions:**
```javascript
loadRoles()              // GET /api/roles → render tabel
openCreateRoleModal()    // buka modal kosong
openEditRoleModal(id)    // buka modal dengan data role
submitRole(e)            // POST /api/roles atau PUT /api/roles/{id}
deleteRole(id)           // DELETE /api/roles/{id}
openEmployeesModal(id)   // GET /api/roles/{id}/employees → modal daftar member
openMenuAccessModal(id)  // GET /api/roles/{id}/permissions → modal kelola akses menu
```

---

### Halaman Permission Management (`/management/permissions`)

File: `resources/views/management/permissions/index.blade.php`

**Fitur UI:**
- Tabel semua menu dengan kolom: Name, Slug, Type, **"Roles with Access"**
- Filter: by type (group/page/function), search (name/slug), by role
- Kolom "Roles with Access" menampilkan badge nama role yang punya `can_view=true`
- Setiap badge role per menu bisa diklik untuk update/revoke permission

**JavaScript key functions:**
```javascript
loadData()       // GET /api/menus/all + GET /api/roles secara paralel
applyFilter()    // filter client-side berdasarkan type, search, dan role
resetFilter()    // reset semua filter
```

---

## 11. Alur Lengkap (End-to-End)

### A. Login → Session Role

Saat employee login, `AuthController::buildEmployeeSessionData()` membangun session:

```php
// Ambil role pertama (MIN role_id) dari pivot table — untuk backward compat
$employee = DB::table('employee as e')
    ->leftJoin('(SELECT employee_id, MIN(role_id) as role_id FROM employee_role_assignment GROUP BY employee_id) as era', ...)
    ->leftJoin('employee_role as r', 'r.id', '=', 'era.role_id')
    ->select('r.id as role_id', 'r.name as role_name', ...)
    ->first();

// Session menyimpan primary role (untuk display)
session(['user' => [
    'id'   => $employee->employee_id,
    'type' => 'employee',
    'role' => ['id' => $employee->role_id, 'name' => $employee->role_name],
    ...
]]);
```

> **Penting:** Session hanya menyimpan **satu role** (primary role, dengan `MIN(role_id)`) untuk keperluan display. Permission check yang sebenarnya selalu membaca **semua role dari pivot table** via `$employee->roles()`.

---

### B. Request ke Halaman yang Diproteksi

```
Browser → GET /tickets
    ↓
Middleware CheckAuthToken       → cek session auth_token valid
    ↓
Middleware CheckMenuAccess('tickets.inbox')
    ↓
  Employee::find($user['id'])
    ↓
  $employee->canAccessMenu('tickets.inbox')
    ↓
  SELECT dari `menu` WHERE slug='tickets.inbox'
    AND EXISTS (
      SELECT dari role_menu
      WHERE role_id IN (semua role employee)
        AND can_view = true
    )
    AND is_active = true
    ↓
  true → $next($request) → Controller
  false → redirect dashboard / 403 JSON
```

---

### C. Frontend Cek Permission Tombol

Di controller atau view, kirim semua slug permission ke frontend:

```php
// Di controller
$permissions = $employee->allPermissionSlugs();
// → ['dashboard', 'tickets', 'tickets.inbox', 'ticket.assign-pic', ...]
```

Di JavaScript frontend:
```javascript
// Cek apakah tombol boleh ditampilkan
if (permissions.includes('ticket.assign-pic')) {
    // tampilkan tombol Assign PIC
}
```

---

### D. Admin Assign Role ke Employee

```
Admin → POST /api/employees/{id}/roles
Body: { role_ids: [1, 4] }
    ↓
RoleController::assignRoles()
    ↓
$employee->roles()->syncWithoutDetaching([1, 4])
    ↓
INSERT IGNORE INTO employee_role_assignment (employee_id, role_id)
VALUES ({id}, 1), ({id}, 4)
```

Untuk **replace semua role** (sync):
```
PUT /api/employees/{id}/roles
Body: { role_ids: [2] }
    ↓
$employee->roles()->sync([2])
-- hapus role lama, set yang baru
```

---

### E. Admin Update Permission Menu

```
Admin → PUT /api/roles/{roleId}/permissions/{menuId}
Body: { can_view: true, can_create: false, can_edit: true, can_delete: false }
    ↓
RoleController::updatePermission()
    ↓
$role->menus()->syncWithoutDetaching([
    $menuId => ['can_view' => true, 'can_create' => false, 'can_edit' => true, 'can_delete' => false]
])
```

---

## 12. Permission Matrix Default

Legend: V=can_view, C=can_create, E=can_edit, D=can_delete | `-` = tidak punya akses

| Menu Slug | Admin | Employee | Internship | HoP | HoS | Helpdesk | RPMO |
|-----------|-------|----------|------------|-----|-----|----------|------|
| dashboard | V | V | V | V | V | V | V |
| calendar | V,C,E,D | V | V | V | V | V | V |
| calendar.timesheets | V,C,E,D | V,C,E | V,C | V,C,E | V,C,E | - | V |
| reporting | V | V | - | V | V | V | V |
| reporting.validation | V,C,E,D | V | - | V | V,C,E | V | V |
| reporting.md-recap | V,C,E,D | - | - | - | V,C,E | - | - |
| master.employee | V,C,E,D | V,E | - | V | V | - | - |
| master.customer | V,C,E,D | - | - | V | V | - | - |
| tickets.inbox | V,C,E,D | V | - | V | V,E | V,C,E | V |
| tickets.staging | V,C,E,D | V | - | - | - | V,C,E,D | V,C,E |
| delivery.project | V,C,E,D | - | - | V,C,E | - | - | V,C,E,D |
| delivery.support | V,C,E,D | - | - | - | V,C,E | V | V,C,E,D |
| log-activity | V | - | - | - | - | - | - |
| financial | V | - | - | - | - | - | - |
| general | V | - | - | - | - | - | - |
| business | V | - | - | - | - | - | - |
| rpmo.overview | V | - | - | - | - | - | V |
| management.roles | V | - | - | - | - | - | - |
| management.permissions | V | - | - | - | - | - | - |

---

## 13. Function-Level Permission

Menu dengan `type='function'` mengontrol visibilitas tombol/kontrol UI. Cek dilakukan di frontend berdasarkan slug.

| Slug | Nama | Role yang Boleh |
|------|------|-----------------|
| `calendar.events.create` | Tombol Create Event | Admin |
| `timesheet.create` | Tombol Create Timesheet | Admin, Employee, HoP, HoS |
| `reporting.export-excel` | Export Excel (MD Validation) | Admin, HoS |
| `reporting.close-period` | Tombol Close Period | Admin, HoS |
| `master.employee.create` | Tombol Create Employee | Admin |
| `master.employee.action` | Action Edit/Delete Employee | Admin |
| `master.customer.create` | Tombol Create Customer | Admin |
| `ui.ticket.btn-create` | Tombol Create Ticket | Admin |
| `ticket.view-all` | Lihat Semua Tiket | Admin |
| `ticket.view-own` | Lihat Tiket Sendiri | Employee |
| `ticket.view-team` | Lihat Tiket Tim | HoP, HoS, Helpdesk, RPMO |
| `ticket.assign-pic` | Assign PIC | Admin, HoS, Helpdesk, RPMO |
| `ticket.confirm-assignment` | Konfirmasi Assignment | Admin, Helpdesk, RPMO |
| `ui.ticket.sidebar-tabs` | Sidebar Tabs Tiket | Admin, Employee, Helpdesk, RPMO |
| `ui.ticket.edit-fields` | Edit Status/Priority/Type | Admin, Helpdesk, RPMO |
| `ui.ticket.manage-members` | Manage Members | Admin, Employee, Helpdesk, RPMO |
| `ui.ticket.internal-notes` | Section Internal Notes | Admin, Helpdesk, RPMO |
| `ticket.take` | Ambil Tiket (Take) | Employee |
| `staging.approve` | Action Validate | Admin, Employee, Helpdesk, RPMO |
| `staging.reject` | Action Reject | Admin, Employee, Helpdesk, RPMO |
| `delivery-project.add-new` | Add New Project | Admin, HoP, RPMO |
| `delivery-support.add-new` | Add Delivery Support | Admin, HoS, Helpdesk, RPMO |
| `delivery-support.edit-type` | Edit Field Type | Admin, Helpdesk, RPMO |
| `ui.menu.visibility-all` | Lihat Semua Menu (permissions page) | Admin |

---

## 14. Checklist Implementasi di Branch Baru

Gunakan checklist ini jika mengimplementasikan multi-role system dari awal di branch lain.

### Database
- [ ] Pastikan tabel `employee_role` sudah ada (dengan kolom `id`, `name`, `description`)
- [ ] Buat migration tabel `menu`
- [ ] Buat migration tabel `employee_role_assignment` (pivot employee ↔ role)
- [ ] Buat migration tabel `role_menu` (pivot role ↔ menu + CRUD flags)
- [ ] Jalankan `php artisan migrate`

### Seeder
- [ ] Buat `RoleSeeder` → isi 7 role default
- [ ] Buat `MenuSeeder` → isi struktur menu + assign ke role
- [ ] Buat `FunctionPermissionSeeder` → isi function-level permission
- [ ] Buat `AdminRoleAssignmentSeeder` → assign role Admin ke ECI_ADMIN
- [ ] Jalankan semua seeder

### Models
- [ ] Buat `app/Models/EmployeeRole.php` dengan relasi `employees()` dan `menus()`
- [ ] Buat `app/Models/Menu.php` dengan relasi `parent()`, `children()`, `roles()`
- [ ] Tambahkan ke `Employee` model: `roles()`, `accessibleMenus()`, `canAccessMenu()`, `hasMenuPermission()`, `hasPermission()`, `allPermissionSlugs()`

### Enum
- [ ] Buat `app/Enums/RoleId.php` dengan 7 case + 4 const group

### Middleware
- [ ] Buat `app/Http/Middleware/CheckMenuAccess.php`
- [ ] Daftarkan alias `'menu'` di middleware stack

### Controllers
- [ ] Buat `app/Http/Controllers/RoleController.php` (CRUD role + assignment + permissions)
- [ ] Buat atau update `app/Http/Controllers/MenuController.php` (CRUD menu + role permissions + getMyMenus)

### Routes
- [ ] Tambahkan route API untuk roles, menus, dan employee-role assignment
- [ ] Tambahkan route web untuk halaman Role Management dan Permission Management
- [ ] Pasang middleware `menu:slug` di semua route yang perlu diproteksi

### Views
- [ ] Buat `resources/views/management/roles/index.blade.php`
- [ ] Buat `resources/views/management/permissions/index.blade.php`

### Verifikasi
- [ ] Login sebagai Admin → cek sidebar menampilkan semua menu
- [ ] Login sebagai Employee → cek hanya menu yang diizinkan yang tampil
- [ ] Akses URL yang tidak diizinkan → redirect dashboard atau 403
- [ ] Tambah role ke employee → cek sidebar terupdate
- [ ] Update permission menu via halaman management → cek langsung berlaku
