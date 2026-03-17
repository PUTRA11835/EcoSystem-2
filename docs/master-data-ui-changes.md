# Dokumentasi Perubahan UI Master Data & Login

Tanggal: 2026-03-16
Branch: `el_branch`

---

## Daftar Isi

1. [Toggle Lihat Password](#1-toggle-lihat-password)
2. [Master Data Employee](#2-master-data-employee)
3. [Master Data Customer](#3-master-data-customer)
4. [Perbedaan Create vs Update](#4-perbedaan-create-vs-update)

---

## 1. Toggle Lihat Password

### 1.1 Halaman Login (`resources/views/auth/login.blade.php`)

Tombol toggle password berada di sisi kanan field password. Menggunakan dua ikon SVG yang bergantian:

- **`eyeClosed`** — ditampilkan secara default saat password tersembunyi
- **`eyeOpen`** — ditampilkan saat password terlihat (mode teks)

**Implementasi:**

```html
<input type="password" id="password" ... />
<button type="button" id="togglePassword" ...>
    <svg id="eyeOpen" class="h-5 w-5 hidden" ...><!-- mata terbuka --></svg>
    <svg id="eyeClosed" class="h-5 w-5" ...><!-- mata coret --></svg>
</button>
```

```js
document.getElementById('togglePassword').addEventListener('click', function () {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    document.getElementById('eyeOpen').classList.toggle('hidden', !isPassword);
    document.getElementById('eyeClosed').classList.toggle('hidden', isPassword);
});
```

**Perilaku:**
- Klik pertama: `type="password"` → `type="text"`, ikon berubah ke eyeOpen
- Klik kedua: `type="text"` → `type="password"`, ikon kembali ke eyeClosed

---

### 1.2 Form Modal Create Employee (`resources/views/master/employee/index.blade.php`)

Modal Employee memiliki **dua field password** (Password + Confirm Password), masing-masing dengan tombol toggle sendiri.

**Implementasi (satu fungsi generik):**

```js
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const iconId = 'eyeIcon' + fieldId.charAt(0).toUpperCase() + fieldId.slice(1);
    const icon   = document.getElementById(iconId);

    if (field.type === 'password') {
        field.type = 'text';
        icon.innerHTML = `/* SVG mata coret */`;
    } else {
        field.type = 'password';
        icon.innerHTML = `/* SVG mata normal */`;
    }
}
```

Dipanggil dari HTML dengan:
```html
<!-- Field Password -->
<button type="button" onclick="togglePassword('password')">
    <svg id="eyeIconPassword" ...>...</svg>
</button>

<!-- Field Confirm Password -->
<button type="button" onclick="togglePassword('confirmPassword')">
    <svg id="eyeIconConfirmPassword" ...>...</svg>
</button>
```

**Catatan khusus Employee:**
- Saat **Create**: field `password` dan `confirmPassword` berstatus `required`
- Saat **Edit** (load dari tombol edit): kedua field dikosongkan, `required` dihapus, muncul hint *"Leave blank to keep current password"*

---

### 1.3 Form Modal Create Customer (`resources/views/master/customer/index.blade.php`)

Sama persis dengan Employee — menggunakan fungsi `togglePassword(fieldId)` yang sama dan dua SVG ikon per field.

---

### 1.4 Form Employee Detail (`resources/views/master/employee/show.blade.php`)

Pada halaman detail employee, ada bagian **Change Password** yang juga menggunakan toggle password untuk field baru dan konfirmasi password.

---

## 2. Master Data Employee

### 2.1 Halaman Daftar (`/master/employee`)

**File:** `resources/views/master/employee/index.blade.php`

#### Filter

| Elemen | ID | Keterangan |
|---|---|---|
| Dropdown Status | `filterStatus` | Filter: All / Active / Blocked |
| Input Employee | `filterEmployee` | Cari berdasarkan ECI atau nama |
| Input Department | `filterDepartment` | Cari berdasarkan department |
| Tombol **GO** | — | Jalankan filter (`applyFilters()`) |
| Tombol **Reset** | — | Kosongkan semua filter (`resetFilters()`) |

> Tombol filter sebelumnya berlabel **"Apply"**, sekarang diganti menjadi **"GO"**.

#### Tabel Daftar Employee

Kolom yang ditampilkan: ECI | Full Name | Position | Division | Department | Since Date | Status | Actions

Setiap baris dapat diklik (class `employee-row`) untuk navigasi ke halaman detail:

```js
function navigateToDetail(employeeId, event) {
    if (event.target.closest('.action-buttons')) return; // kecuali tombol aksi
    window.location.href = `/master/employee/${employeeId}`;
}
```

Hover effect: baris berubah warna merah muda (`#fef2f2`) dan sedikit membesar (`scale(1.002)`).

#### Tombol "Create Employee"

Tombol berada di atas tabel (kanan atas). Klik memanggil `openCreateModal()`.

---

### 2.2 Flow Create Employee

```
Klik "Create Employee"
    └── openCreateModal()
            - Reset form
            - Set country default = "Indonesia"
            - Password field menjadi required
            - Tampilkan modal (#employeeModal)
                └── Isi form (3 kolom: General Data | Address | Organizational Data)
                        └── Klik tombol "Save"
                                └── saveEmployee()
                                        - Validasi password match & min 6 karakter
                                        - POST /api/employees
                                        - Jika berhasil: tutup modal, refresh tabel
```

**Field wajib saat Create:**
- ECI (Employee ID)
- Password & Confirm Password
- First Name
- Work Email

**API:** `POST /api/employees` dengan `role_id: 2` (Employee/PIC) di-hardcode di payload.

---

### 2.3 Flow Update Employee

```
Klik baris employee di tabel
    └── navigateToDetail(employeeId)
            └── Halaman detail /master/employee/{id}
                    └── Tab aktif: "Basic Data" (default)
                            └── loadSectionData('basic-data')
                                    └── loadEmployeeBasicData(employeeId)
                                            - GET /api/employees/{id}/basic-data
                                            - Isi semua field form otomatis
                                                └── Edit field yang ingin diubah
                                                        └── Klik "Save Changes"
                                                                └── saveCurrentSection()
                                                                        └── saveEmployeeBasicData(employeeId)
                                                                                - POST /api/employees/{id}/basic-data
                                                                                - Jika berhasil: reload data (timestamp diperbarui)
```

**Tombol "Save Changes"** ada di bagian atas setiap section, bukan di footer halaman.

> Tombol submit form sebelumnya berlabel **"Apply"** / **"Update"**, sekarang diganti menjadi **"Save"** / **"Save Changes"** secara konsisten.

---

### 2.4 Tabs/Section pada Halaman Detail Employee

Navigasi antar bagian menggunakan tab horizontal (class `section-tab`). Klik tab memanggil `switchSection(sectionName)`.

| Tab | Section ID | Endpoint Data |
|---|---|---|
| Basic Data | `section-basic-data` | `GET /api/employees/{id}/basic-data` |
| Address | `section-address` | `GET /api/employees/{id}/addresses` |
| Identification | `section-identification` | `GET /api/employees/{id}/identification` |
| Family | `section-family` | `GET /api/employees/{id}/family` |
| Education | `section-education` | `GET /api/employees/{id}/education` |
| Qualification | `section-qualification` | `GET /api/employees/{id}/qualifications` |
| Contract | `section-contract` | `GET /api/employees/{id}/contracts` |
| Bank | `section-bank` | `GET /api/employees/{id}/bank-accounts` |
| Payment | `section-payment` | `GET /api/employees/{id}/payment-methods` |
| Attachment | `section-attachment` | `GET /api/employees/{id}/attachments` |

Setiap kali tab diklik, data di-load dari API secara dinamis dan mengisi form. Perubahan disimpan per-section secara independen.

**Fungsi `switchSection`:**
```js
function switchSection(sectionName) {
    // Sembunyikan semua section
    document.querySelectorAll('.section-content').forEach(s => s.classList.add('hidden'));
    // Hapus active dari semua tab
    document.querySelectorAll('.section-tab').forEach(t => {
        t.classList.remove('border-red-800', 'text-red-800');
        t.classList.add('border-transparent', 'text-gray-600');
    });
    // Tampilkan section yang dipilih
    document.getElementById('section-' + sectionName)?.classList.remove('hidden');
    // Set tab aktif
    document.querySelector(`[data-section="${sectionName}"]`)
        ?.classList.add('border-red-800', 'text-red-800');
    // Load data
    loadSectionData(sectionName);
}
```

---

### 2.5 Section Basic Data — Detail Field

**Tersimpan di `POST /api/employees/{id}/basic-data`:**

| Grup | Field |
|---|---|
| General Information | Title, First Name, Last Name, Nick Name, Gender, Religion, Marital Status, Birth Date, Birth Place, Since Date, Search Term 1 (auto), Search Term 2 (auto) |
| Employee Information | Personnel Area, Personnel Subarea, Employee Group, Employee Subgroup, Position, Division, Department, Direct Supervision, Manager, Authorization Group |
| Status Flags | Block Employee (checkbox), Deletion Flag (checkbox) |
| Audit (read-only) | Created By, Created On, Last Changed By, Last Changed On |

**Search Term** di-generate otomatis dari First Name (Term 1) dan Last Name (Term 2) saat mengetik.

---

### 2.6 Delete Employee

Di kolom Actions pada baris tabel terdapat tombol **Delete** (ikon tong sampah merah). Klik membuka modal konfirmasi (`#confirmDeleteModal`):

```
Klik tombol Delete (di kolom Actions)
    └── deleteEmployee(id)
            └── Tampilkan modal konfirmasi
                    └── Klik "Delete" di modal
                            └── confirmDelete()
                                    - DELETE /api/employees/{id}
                                    - Jika berhasil: refresh tabel
```

**Catatan:** Klik baris tidak akan trigger navigate jika yang diklik adalah tombol di kolom Actions (dicek via `event.target.closest('.action-buttons')`).

---

## 3. Master Data Customer

### 3.1 Halaman Daftar (`/master/customer`)

**File:** `resources/views/master/customer/index.blade.php`

Struktur dan pola identik dengan Employee:

| Elemen | ID | Keterangan |
|---|---|---|
| Dropdown Status | `filterStatus` | All / Active / Blocked |
| Input Customer | `filterCustomer` | Cari email atau nama perusahaan |
| Input Customer Group | `filterCustomerGroup` | Cari customer group |
| Tombol **GO** | — | `applyFilters()` |
| Tombol **Reset** | — | `resetFilters()` |

Kolom tabel: Email | Company Name | Customer Group | Customer Category | Industry Sector | Status | Actions

Setiap baris diklik → `navigateToDetail(customerId)` → `/master/customer/{id}`

---

### 3.2 Flow Create Customer

```
Klik "Create Customer"
    └── openCreateModal()
            - Reset form
            - Password menjadi required
            - Tampilkan modal (#customerModal)
                └── Isi form (3 kolom: General Data | Address | Sales Data)
                        └── Klik "Save"
                                └── saveCustomer()
                                        - Validasi password match
                                        - POST /api/customers
                                        - Jika berhasil: tutup modal, refresh tabel
```

**Field wajib saat Create:**
- Email
- Password & Confirm Password
- Company Name

---

### 3.3 Flow Update Customer

Sama dengan Employee — klik baris → halaman detail → pilih tab → data terisi otomatis → edit → **"Save Changes"**.

---

### 3.4 Tabs/Section pada Halaman Detail Customer

**File:** `resources/views/master/customer/show.blade.php`

| Tab | Section | Endpoint |
|---|---|---|
| Basic Data | `section-basic-data` | `GET /api/customers/{id}/basic-data` |
| Address | `section-address` | `GET /api/customers/{id}/addresses` |
| Contact | `section-contact` | `GET /api/customers/{id}/contacts` |
| Identification | `section-identification` | `GET /api/customers/{id}/identification` |
| Bank | `section-bank` | `GET /api/customers/{id}/bank-accounts` |
| Attachment | `section-attachment` | `GET /api/customers/{id}/attachments` |

---

## 4. Perbedaan Create vs Update

### 4.1 Ringkasan Perbedaan

| Aspek | Create | Update |
|---|---|---|
| Cara akses | Tombol "Create Employee / Customer" di atas tabel | Klik baris di tabel |
| UI | Modal popup | Halaman detail dengan tab navigasi |
| Password | Wajib diisi | Opsional (kosong = tidak diubah) |
| Simpan | Tombol **"Save"** di footer modal | Tombol **"Save Changes"** di header setiap section |
| API | `POST /api/employees` atau `POST /api/customers` | `POST /api/employees/{id}/basic-data` dst. |
| Setelah berhasil | Tutup modal + refresh tabel | Tetap di halaman detail, data di-reload |

### 4.2 Tombol Save — Perubahan Label

Sebelum perubahan, berbagai tombol simpan menggunakan label yang tidak konsisten ("Apply", "Update", "Create"). Setelah perubahan:

| Konteks | Label Sekarang |
|---|---|
| Tombol filter (jalankan pencarian) | **GO** |
| Tombol simpan di modal Create/Edit | **Save** |
| Tombol simpan per-section di halaman detail | **Save Changes** |
| Tombol simpan password di halaman detail | **Save Password** |
| Tombol simpan role di halaman detail | **Save Role** |

### 4.3 Validasi Password di Modal

Berlaku untuk Create Employee dan Create Customer:

```js
// 1. Kedua field harus match
if (password !== confirmPassword) {
    showNotification('Passwords do not match!', 'error');
    return;
}

// 2. Minimal 6 karakter
if (password.length < 6) {
    showNotification('Password must be at least 6 characters long!', 'error');
    return;
}
```

Saat **Edit** (load data ke form), field password dikosongkan dan `required` dihapus agar tidak wajib diisi ulang.

---

## Lampiran: Struktur File Terkait

```
resources/views/
├── auth/
│   └── login.blade.php                     # Toggle password login
├── master/
│   ├── employee/
│   │   ├── index.blade.php                 # Daftar, filter, create modal
│   │   ├── show.blade.php                  # Detail employee + tab navigation
│   │   └── sections/
│   │       ├── basicdata.blade.php         # Tab Basic Data (form + save)
│   │       ├── address.blade.php           # Tab Address
│   │       ├── identification.blade.php    # Tab Identification
│   │       ├── bank.blade.php              # Tab Bank
│   │       └── ...
│   └── customer/
│       ├── index.blade.php                 # Daftar, filter, create modal
│       ├── show.blade.php                  # Detail customer + tab navigation
│       └── sections/
│           ├── basicdata.blade.php
│           ├── address.blade.php
│           ├── contact.blade.php
│           ├── bank.blade.php
│           └── ...
```
