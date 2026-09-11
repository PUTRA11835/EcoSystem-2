# Fase 1 — Customer Admin / Member Roles

> ✅ **STATUS: SELESAI (2026-06-30).** Ringkasan implementasi ada di bagian "Hasil Implementasi" paling bawah.


> **Goal:** Akun customer punya 2 level akses tiket:
> - **Admin** (`can_view_all_tickets = true`): lihat tiket buatannya **+ semua tiket akun lain di company yang sama**.
> - **Member** (`can_view_all_tickets = false`): hanya lihat tiket **buatannya sendiri** untuk company-nya.
>
> Company diidentifikasi via `customer_id`, dan via **email domain** (`customer.domain`) untuk email yang submit tiket tapi belum terdaftar sebagai contact.

## Keputusan kunci
- Reuse kolom `auth_users.can_view_all_tickets` (sudah ada, default `true`). **Tidak** buat kolom role baru.
- UI EcoSystem cukup tampilkan pilihan **Admin / Member** yang menulis boolean ini.
- Member filter = `submitted_by_email`; ditambah cocokkan domain company untuk tiket email tanpa contact terdaftar.

## Lingkup file (perkiraan)

### EcoSystem (admin set role saat grant/manage akun)
- `app/Http/Controllers/CustomerController.php` — endpoint grant/revoke login & update contact → tambah set `can_view_all_tickets`.
- `resources/views/master/customer/` — UI Contact Person: dropdown **Admin/Member** saat Grant Access / edit akun.
- (cek) endpoint `create-login` / contact API yang dipakai untuk provisioning `auth_users`.

### JARVIES (enforcement visibility)
- `app/Http/Controllers/TicketController.php`
  - `getTickets()` — sudah ada cabang `can_view_all_tickets`; pastikan benar & tambah logika **domain company** untuk admin (lihat tiket company termasuk email non-contact).
  - `myTickets()` — saat ini selalu filter `customer_id` saja (tanpa cek `can_view_all_tickets`); **perlu disamakan** dengan aturan member/admin.
  - `getStagingTickets()` / staging branch — pastikan member hanya lihat staging miliknya.
- `app/Http/Controllers/AuthController.php` — sudah kirim `can_view_all_tickets` ke session; pastikan konsisten.
- `resources/views/tickets/index.blade.php` — sudah baca flag; sesuaikan label/indikator role bila perlu.

## Subtask (checklist)
- [ ] **EcoSystem UI**: dropdown Admin/Member di Grant Access + edit akun contact.
- [ ] **EcoSystem API**: simpan `can_view_all_tickets` saat create-login & update; default Admin atau Member? (tentukan default).
- [ ] **JARVIES getTickets**: verifikasi cabang admin vs member; admin = semua tiket `customer_id` company; member = `submitted_by_email`.
- [ ] **JARVIES domain rule**: admin juga melihat tiket email yang domain pengirimnya = `customer.domain` company (untuk email non-contact). Pastikan tiket email ter-attribute ke `customer_id` company yang benar saat intake (cek staging/email processor EcoSystem).
- [ ] **JARVIES myTickets()**: terapkan aturan yang sama (sekarang belum cek flag).
- [ ] **JARVIES staging**: member tak lihat staging milik akun lain.
- [ ] **Konsistensi session**: `can_view_all_tickets` selalu ada di session user.
- [ ] **Test**: 1 company 3 akun (1 admin, 2 member) → admin lihat semua, member lihat sendiri; + tiket email dari domain company tanpa contact muncul untuk admin.

## Acceptance Criteria
1. Admin EcoSystem bisa set akun customer sebagai Admin / Member.
2. Customer Admin di Jarvies melihat semua tiket company (lintas akun) + tiket email dari domain company.
3. Customer Member di Jarvies hanya melihat tiket yang ia submit.
4. Aturan berlaku konsisten di list tiket, "my tickets", dan staging.

## Catatan / risiko
- Pastikan `customer.domain` terisi untuk company yang relevan.
- Hati-hati `myTickets()` vs `getTickets()` punya aturan berbeda saat ini — samakan.
- Backward-compat akun lama (`contact_id` null, default `can_view_all_tickets = true` = Admin).

---

## KICKOFF PROMPT (tempel di room chat Fase 1)

```
Lanjutkan Fase 1 dari docs/planning/phase-1-customer-roles.md.

Konteks: EcoSystem (cwd) + JARVIES di D:\Magang\PT Eclectic Consulting Yogyakarta\Project\JARVIES-main, berbagi DB & auth_users. Customer = role.id 3.

Tugas: implement Customer Admin/Member role pakai ulang kolom auth_users.can_view_all_tickets (Admin=true, Member=false). 
- EcoSystem: dropdown Admin/Member saat Grant Access / edit akun contact + simpan flag.
- JARVIES TicketController: getTickets/myTickets/staging enforce — Admin lihat semua tiket company (termasuk tiket email yg domain pengirim = customer.domain, walau pengirim bukan contact terdaftar), Member hanya submitted_by_email miliknya.

Baca dulu file planning + TicketController kedua project, lalu mulai implement. Ikuti pola repo (session('user'), AJAX JSON, dll).
```

---

## Hasil Implementasi (2026-06-30)

### Temuan: EcoSystem side sudah ada sebelumnya
Mekanisme admin-side **sudah terbangun**:
- Endpoint `CustomerContactController@toggleViewAllTickets` + route `PATCH /api/customers/{c}/contacts/{contactId}/toggle-view-all`.
- `index()` contact sudah mengembalikan `can_view_all_tickets`.
- UI `resources/views/master/customer/sections/contact.blade.php` punya kolom **"Ticket Access"** berupa toggle switch.
- `createLogin` tidak set flag → memakai default kolom DB `true` (= **Admin**).

### Yang diubah

**EcoSystem** — `resources/views/master/customer/sections/contact.blade.php`
- Label toggle "Regular" → **"Member"** + tooltip disesuaikan (Admin/Member). (Mekanisme tak berubah; hanya istilah agar sesuai permintaan.)

**JARVIES** — `app/Http/Controllers/TicketController.php`
- Tambah 3 helper privat:
  - `customerCanViewAll($sessionUser)` — baca `session('user')['can_view_all_tickets']` (fallback query `auth_users`), default `true` untuk akun lama.
  - `applyCustomerTicketVisibility($query, $sessionUser)` — `where customer_id`, dan untuk Member tambah `where submitted_by_email = email`.
  - `customerCanAccessTicket($sessionUser, $ticket)` — guard akses 1 tiket (Admin: semua company; Member: hanya submit sendiri, `strcasecmp`).
- Diterapkan di: `getTickets` (refactor pakai helper), `myTickets` (sebelumnya TIDAK cek flag — diperbaiki), `byStatus`, `statistics`, `show`, `getMessages`, `addComment` (reply), `updateManDays`, `getMandaysHistory`, `showMyTicket`, serta staging (`pendingPage`, `getStagingTickets`).

**JARVIES** — `app/Services/StagingTicketService.php`
- `getByCustomer($customerId, ?string $submittedByEmail = null)` — Member difilter `submitted_by_email`.

### Domain rule
Sudah terpenuhi di **intake** EcoSystem: `EmailController@processInbox` me-resolve `customer_id` via `customer.domain` (`LOWER(domain)` match). Tiket email dari domain company (termasuk pengirim non-contact / tanpa akses Jarvies) otomatis ber-`customer_id` company → Admin melihatnya lewat filter `customer_id`. Tidak perlu perubahan tambahan di JARVIES.

### Verifikasi
- `php -l` bersih untuk `TicketController.php` & `StagingTicketService.php`.
- Belum diuji runtime (perlu data 1 company multi-akun: 1 Admin + ≥1 Member, plus 1 tiket email domain-company). Lihat checklist test di atas.

### Follow-up minor (belum dikerjakan, low-risk)
- Endpoint download attachment staging (`downloadStagingAttachment`, `serveStagingImage`) masih scoped `customer_id` saja → antar-Member dalam company masih bisa akses bila tahu URL. Tidak membocorkan lintas-company. Bisa diberi guard `submitted_by_email` bila diperlukan.
- Default role saat Grant Access = Admin (ikut default kolom). Bila ingin default Member, set `can_view_all_tickets = false` di `createLogin` atau tambahkan pilihan di modal Grant.
