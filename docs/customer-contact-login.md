# Customer Contact Login — Panduan Integrasi Jarvies

> **Tanggal:** 2026-03-13
> **Berlaku untuk:** Tim Jarvies agar dapat menyesuaikan flow login customer dengan skema multi-akun baru

---

## Perubahan Arsitektur

### Sebelumnya
- 1 Customer = 1 akun login (langsung di `auth_users.customer_id`)
- Saat create customer di EcoSystem → langsung dibuat `auth_users` entry

### Sekarang
- 1 Customer (company) = **N akun login** (satu per Contact Person)
- Create customer = hanya buat data company, **tanpa auth_users**
- Login accounts dikelola via **Contact Person** di tab Contact customer
- Setiap Contact Person yang ingin bisa login ke Jarvies harus di-*grant* oleh admin EcoSystem

---

## Perubahan Database

### `customer` table
| Kolom | Perubahan |
|-------|-----------|
| `email` | Sekarang **nullable** — optional company email, bukan login email |

### `auth_users` table
| Kolom baru | Tipe | Keterangan |
|------------|------|-----------|
| `contact_id` | bigint nullable | FK ke `customer_contact.contact_id` (cascade delete) |

**Contoh data:**
```sql
-- Satu customer "Indo Raya" dengan 3 orang login
SELECT au.id, au.email, au.customer_id, au.contact_id, cc.full_name
FROM auth_users au
JOIN customer_contact cc ON au.contact_id = cc.contact_id
WHERE au.customer_id = 42;

-- Result:
-- id=10 | putra@indoraya.com | customer_id=42 | contact_id=5 | full_name=Putra
-- id=11 | el@indoraya.com    | customer_id=42 | contact_id=6 | full_name=El
-- id=12 | tio@indoraya.com   | customer_id=42 | contact_id=7 | full_name=Tio
```

---

## API Baru

### Grant login access ke contact person
```
POST /api/customers/{customerId}/contacts/{contactId}/create-login

Body:
{
  "login_email": "putra@indoraya.com"
}

Response sukses:
{
  "success": true,
  "message": "Login access granted. A password setup email has been sent to putra@indoraya.com"
}
```
Setelah endpoint ini dipanggil:
- `auth_users` entry dibuat dengan `is_already_cp = false`
- Email setup password dikirim otomatis ke `login_email`
- Contact person harus set password via link email sebelum bisa login

### Revoke login access
```
DELETE /api/customers/{customerId}/contacts/{contactId}/revoke-login

Response sukses:
{
  "success": true,
  "message": "Login access has been revoked successfully"
}
```

### Get contacts (termasuk login status)
```
GET /api/customers/{customerId}/contacts

Response sekarang menyertakan field login:
[
  {
    "contact_id": 5,
    "full_name": "Putra",
    "position": "IT Manager",
    "email_work": "putra@indoraya.com",
    ...
    "auth_user_id": 10,          // null jika belum ada login
    "login_email": "putra@indoraya.com",
    "login_active": true,
    "login_setup_done": true,    // false = belum set password
    "last_login_at": "2026-03-11 10:00:00"
  }
]
```

---

## Yang Perlu Disesuaikan di Jarvies

### 1. Login Flow — Ambil profil dari contact_id

Saat customer berhasil login di Jarvies, lookup `auth_users` by email/username, lalu:

```php
$authUser = DB::table('auth_users')->where('email', $email)->first();

$customerId = $authUser->customer_id;
$contactId  = $authUser->contact_id; // BARU — bisa null untuk akun lama

// Ambil profile contact person (nama, posisi, dll)
if ($contactId) {
    $contact = DB::table('customer_contact')
        ->where('contact_id', $contactId)
        ->first();
    // $contact->full_name, $contact->position, $contact->email_work, dll
}

// Ambil company info
$customer = Customer::with('basicData')->find($customerId);
// $customer->basicData->name_1 = nama company
```

### 2. Session data yang direkomendasikan untuk customer login

```php
$sessionData = [
    'auth_user_id' => $authUser->id,
    'customer_id'  => $authUser->customer_id,
    'contact_id'   => $authUser->contact_id,  // BARU
    'name'         => $contact->full_name ?? $customer->basicData->name_1,
    'email'        => $authUser->email,
    'position'     => $contact->position ?? null,
    'company'      => $customer->basicData->name_1,
    'type'         => 'customer',
];
```

### 3. Profil halaman di Jarvies

Tampilkan data dari `customer_contact` (bukan dari `customer`):
- Nama: `contact.full_name`
- Posisi: `contact.position`
- Department: `contact.department`
- Phone: `contact.cell_phone`
- Email: `auth_users.email` (login email)

Company info dari `customer.basicData`:
- Company name: `basicData.name_1`
- Group/Category: `basicData.customer_group`, `basicData.customer_category`

### 4. Backward compatibility — akun lama tanpa contact_id

Ada kemungkinan ada akun `auth_users` lama yang punya `customer_id` tapi `contact_id = NULL`
(dibuat sebelum migrasi ini). Handle dengan graceful fallback:

```php
if ($authUser->contact_id) {
    $profile = DB::table('customer_contact')
        ->where('contact_id', $authUser->contact_id)->first();
} else {
    // Fallback: ambil contact pertama dari customer ini
    $profile = DB::table('customer_contact')
        ->where('customer_id', $authUser->customer_id)
        ->orderBy('contact_id')->first();
}
```

### 5. Submit ticket dari Jarvies

Ketika customer submit ticket, sertakan `contact_id` sebagai informasi tambahan:

```json
{
  "customer_id": 42,
  "contact_id": 5,
  "submitted_by_email": "putra@indoraya.com",
  "sender_name": "Putra",
  ...
}
```

Ini memungkinkan EcoSystem mengetahui siapa (kontak mana) yang submit ticket.

---

## Flow Lengkap

```
Admin EcoSystem:
  1. Create customer (company) → hanya data perusahaan, tanpa login
  2. Buka detail customer → tab Contact
  3. Tambah contact person: Putra, El, Tio (nama, posisi, email, phone)
  4. Pilih Putra → klik "Grant Access" → isi login email → Submit
     → auth_users dibuat + email setup password terkirim ke Putra
  5. Ulangi untuk El dan Tio

Contact person (Putra):
  6. Buka email → klik link setup password → set password baru
  7. Login ke Jarvies dengan email + password baru
  8. Profile menampilkan: nama "Putra", posisi, company "Indo Raya"
  9. Submit ticket → ticket terhubung ke customer "Indo Raya"
```

---

## Catatan

- `contact_id` di `auth_users` adalah **cascade delete** — jika contact person dihapus dari EcoSystem, akun login-nya otomatis terhapus
- Satu contact person hanya bisa punya **satu** akun login
- `customer.email` sekarang opsional — bisa diisi sebagai email umum perusahaan (bukan untuk login)
- Semua constraint unik di `auth_users.email` tetap berlaku — email login harus unik across semua user
