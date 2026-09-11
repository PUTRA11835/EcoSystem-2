# Potongan Kode Laporan KP — Gambar 5 & Gambar 6

Kode di bawah ini adalah representasi implementasi yang dibangun selama Kerja Praktik
(28 Januari – 31 Maret 2026). Disesuaikan untuk laporan akademik.

---

## ✂️ VERSI RINGKAS (untuk Carbon / screenshot laporan)

### Gambar 5 — `EcosystemUserProvider.php` (inti)

```php
<?php

namespace App\Auth;

class EcosystemUserProvider extends EloquentUserProvider
{
    // Resolusi pengguna dari dua tabel berbeda berdasarkan guard aktif
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $guard = $credentials['guard'] ?? 'employee';

        if ($guard === 'customer') {
            return Customer::where('email', $credentials['email'])->first();
        }

        return Employee::where('email', $credentials['email'])->first();
    }

    // Identifikasi polimorfik: 'employee:5' atau 'customer:3'
    public function retrieveById($identifier): ?Authenticatable
    {
        [$type, $id] = explode(':', $identifier, 2);

        return match ($type) {
            'employee' => Employee::find($id),
            'customer' => Customer::find($id),
            default    => null,
        };
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $this->hasher->check($credentials['password'], $user->getAuthPassword());
    }
}
```

### Gambar 5 — `config/auth.php` (tiga guard)

```php
'guards' => [
    'admin' => [
        'driver'   => 'session',
        'provider' => 'employees',
    ],
    'employee' => [
        'driver'   => 'session',
        'provider' => 'employees',
    ],
    'customer' => [
        'driver'   => 'session',
        'provider' => 'customers',
    ],
],

'providers' => [
    'employees' => [
        'driver' => 'ecosystem',
        'model'  => App\Models\Employee::class,
    ],
    'customers' => [
        'driver' => 'ecosystem',
        'model'  => App\Models\Customer::class,
    ],
],
```

---

### Gambar 6 — `TicketObserver.php` (inti)

```php
<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\TicketHistory;
use Illuminate\Support\Facades\Auth;

class TicketObserver
{
    // Otomatis mencatat setiap perubahan status tiket ke ticket_histories
    public function updating(Ticket $ticket): void
    {
        if (! $ticket->isDirty('status')) {
            return;
        }

        $changedBy = Auth::guard('employee')->check()
            ? Auth::guard('employee')->id()
            : Auth::guard('customer')->id();

        TicketHistory::create([
            'ticket_id'  => $ticket->ticket_id,
            'old_status' => $ticket->getOriginal('status'),
            'new_status' => $ticket->status,
            'changed_by' => $changedBy,
        ]);
    }
}
```

---

---

## Gambar 5 — Multi-Guard Authentication & Custom UserProvider

### `app/Auth/EcosystemUserProvider.php`

```php
<?php

namespace App\Auth;

use App\Models\Employee;
use App\Models\Customer;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;

class EcosystemUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher)
    {
        // Model default tidak dipakai; resolusi dilakukan secara polimorfik
        parent::__construct($hasher, Employee::class);
    }

    /**
     * Cari pengguna berdasarkan kredensial (email + guard).
     * Guard 'admin' dan 'employee' mencari di tabel employees,
     * guard 'customer' mencari di tabel customers.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $guard = $credentials['guard'] ?? 'employee';
        $email = $credentials['email'] ?? null;

        if ($guard === 'customer') {
            return Customer::where('email', $email)->first();
        }

        return Employee::where('email', $email)->first();
    }

    /**
     * Validasi password pengguna yang ditemukan.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $this->hasher->check($credentials['password'], $user->getAuthPassword());
    }

    /**
     * Ambil pengguna berdasarkan ID.
     * Format identifier: 'employee:5' atau 'customer:3'
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        [$type, $id] = explode(':', $identifier, 2);

        return match ($type) {
            'employee' => Employee::find($id),
            'customer' => Customer::find($id),
            default    => null,
        };
    }

    /**
     * Ambil pengguna berdasarkan token "remember me".
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = $this->retrieveById($identifier);

        if ($user && $user->getRememberToken() === $token) {
            return $user;
        }

        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $user->setRememberToken($token);
        $user->save();
    }
}
```

---

### `app/Providers/AppServiceProvider.php` — Registrasi Custom Driver

```php
public function boot(): void
{
    Auth::provider('ecosystem', function ($app, array $config) {
        return new EcosystemUserProvider($app['hash']);
    });
}
```

---

### `config/auth.php` — Konfigurasi Tiga Guard

```php
'defaults' => [
    'guard'     => 'employee',
    'passwords' => 'employees',
],

'guards' => [
    'admin' => [
        'driver'   => 'session',
        'provider' => 'employees',
    ],

    'employee' => [
        'driver'   => 'session',
        'provider' => 'employees',
    ],

    'customer' => [
        'driver'   => 'session',
        'provider' => 'customers',
    ],
],

'providers' => [
    'employees' => [
        'driver' => 'ecosystem',
        'model'  => App\Models\Employee::class,
    ],

    'customers' => [
        'driver' => 'ecosystem',
        'model'  => App\Models\Customer::class,
    ],
],
```

---

## Gambar 6 — TicketObserver untuk Audit Trail

### `app/Observers/TicketObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\TicketHistory;
use Illuminate\Support\Facades\Auth;

class TicketObserver
{
    /**
     * Dipanggil sebelum model Ticket disimpan saat update.
     * Mencatat perubahan status ke tabel ticket_histories secara otomatis
     * tanpa perlu memanggil log secara manual di setiap controller.
     */
    public function updating(Ticket $ticket): void
    {
        // Hanya catat jika kolom status benar-benar berubah
        if (! $ticket->isDirty('status')) {
            return;
        }

        $changedBy = null;

        if (Auth::guard('employee')->check()) {
            $changedBy = Auth::guard('employee')->id();
        } elseif (Auth::guard('customer')->check()) {
            $changedBy = Auth::guard('customer')->id();
        }

        TicketHistory::create([
            'ticket_id'  => $ticket->ticket_id,
            'old_status' => $ticket->getOriginal('status'),
            'new_status' => $ticket->status,
            'changed_by' => $changedBy,
        ]);
    }
}
```

---

### `app/Providers/AppServiceProvider.php` — Registrasi Observer

```php
use App\Models\Ticket;
use App\Observers\TicketObserver;

public function boot(): void
{
    Ticket::observe(TicketObserver::class);
}
```

---

### `app/Models/TicketHistory.php` — Model Pendukung

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketHistory extends Model
{
    protected $table = 'ticket_histories';

    protected $fillable = [
        'ticket_id',
        'old_status',
        'new_status',
        'changed_by',
    ];

    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}
```
