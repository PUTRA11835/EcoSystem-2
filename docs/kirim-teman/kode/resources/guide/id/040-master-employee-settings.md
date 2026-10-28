---
key: master-employee-settings
chapter: administration
order: 40
title: Master Employee Settings
summary: Kelola pilihan dropdown dan batas data yang dipakai tiap tab profil Employee.
requires: [management.employee, management.employee.basic-data, management.employee.address, management.employee.identification, management.employee.family, management.employee.education, management.employee.qualification, management.employee.contract, management.employee.bank, management.employee.payment, management.employee.attachment]
updated: 2026-10-27
---
Master Employee Settings mengatur pilihan yang ditawarkan formulir Employee (misalnya gelar, agama, negara, jenis dokumen, atau bank) dan batas penyimpanan datanya (misalnya panjang maksimum). Ada satu halaman untuk setiap tab profil Employee. Topik ini ditujukan untuk administrator dan HR.

## Siapa yang menggunakan

Administrator dan pemilik data HR. Di `Management` › `Employee` Anda melihat satu entri per tab, seperti `Basic Data`, `Address`, `Identification`, `Family`, `Education`, `Qualification`, `Contract`, `Bank`, `Payment`, dan `Attachment`. Anda hanya melihat tab yang diizinkan untuk Role Anda. `View` membuka tab, `Create` menambah pilihan, `Edit` mengubah pilihan dan aturan, dan `Delete` menghapus pilihan.

## Sebelum Anda mulai

- Tentukan nama pilihan sebelum menambahkannya. Nama yang sudah tersimpan pada data Employee sulit dirapikan kemudian.
- Daftar posisi, departemen, dan daftar organisasi lain adalah data induk tersendiri dan tidak dikelola di sini.

## Langkah demi langkah

### Menambah pilihan

1. Buka `Management` › `Employee` lalu pilih tab.
2. Temukan daftarnya, misalnya `Titles` atau `Countries`.
3. Di `Add an option`, ketik label yang ditampilkan kepada pengguna. Bila halaman meminta `Saved as`, isi nilai yang disimpan bersama data.
4. Konfirmasi untuk menambahkannya. Pilihan muncul di daftar.

### Mengubah, mematikan, atau menghapus pilihan

1. Ubah label lalu klik ikon simpan (`Save ...`) pada barisnya.
2. Gunakan kontrol pindah pada baris untuk mengubah urutan di dropdown.
3. Untuk berhenti menawarkan sebuah pilihan, matikan.
4. Untuk menghapusnya, gunakan ikon hapus. Pilihan yang sudah dipakai data Employee tidak bisa dihapus. Kolom `Used by` menunjukkan berapa data yang memakainya, jadi matikan saja.

### Mengubah aturan

1. Gulir ke batas dan saklar pada tab, seperti panjang maksimum atau apakah sesuatu wajib.
2. Ubah nilai dalam rentang yang diizinkan.
3. Klik `Save changes`.

Saklar `Strict list` membuat sistem menolak nilai yang tidak ada di daftar. Biarkan mati untuk kolom yang diketik bebas, agar data impor dengan nilai lain tetap tersimpan.

> [!WARNING]
> Mengganti nama atau menghapus pilihan mengubah apa yang ditawarkan semua formulir Employee. Data yang sudah ada tetap memakai nilai saat disimpan. Uji perubahan pada satu Employee terlebih dahulu.

## Apa yang terjadi selanjutnya

Formulir dan impor langsung memakai pilihan dan batas yang baru. Misalnya, bank baru muncul pada dropdown bank di formulir Employee.

## Pertanyaan umum

**Mengapa saya tidak bisa menghapus pilihan?** Data Employee memakainya. Matikan saja.

**Mengapa saya tidak melihat sebuah tab?** Setiap tab memiliki akses tersendiri, hubungi administrator.

## Topik terkait

- [[employee-data]]
- [[roles-and-menu-access]]
- [[my-profile]]

<!-- sources: routes/web.php, app/Support/Management/EmployeeMasterSettings.php, app/Http/Controllers/Management/EmployeeSettingsController.php, resources/views/management/employee/settings.blade.php, resources/views/management/employee/components/settings-panel.blade.php -->
