# Roadmap — Customer Roles & Ticket Import Chat

> **Dibuat:** 2026-06-30
> **Project:** EcoSystem (employee/admin side) + JARVIES (customer side) — berbagi DB & `auth_users`
> **Tujuan:** 3 kerjaan dipecah jadi 2 fase agar bisa dikerjakan di room chat terpisah (hemat token).

## Ringkasan Kerjaan

| # | Kerjaan | Fase |
|---|---------|------|
| 1 | Customer **Admin** role: lihat tiket sendiri + semua tiket akun di company-nya | Fase 1 |
| 2 | Customer **Member** role: hanya lihat tiket buatannya sendiri (untuk company-nya) | Fase 1 |
| 3 | **Konsistensi subject email**: subject `[JARVIES] #XXXX : desc` dari arah manapun — perbaiki Jarvies yang masih kirim `Ticket #XXXX:` saat customer chat duluan | Fase 2 |

## Keputusan Desain (sudah dikonfirmasi user)

1. **Model role** = pakai ulang kolom `auth_users.can_view_all_tickets` (Admin = `true`, Member = `false`). Tidak buat kolom role baru.
2. **Filter Member** = `submitted_by_email`. Tambahan: identifikasi company juga via **email domain** (`customer.domain`) untuk email yang submit tiket tapi belum terdaftar di `customer_contact` — berlaku walau pengirim tak punya akses Jarvies.
3. **Import & subject** = fitur import EcoSystem (kirim anchor email `[JARVIES] #XXXX : description`) **sudah jalan, jangan diubah**. Yang diperbaiki: saat customer chat **duluan dari Jarvies**, subject outbound masih `Ticket #XXXX:` (lihat `JARVIES/TicketMessageController@sendEmailReply` ~baris 229) → harus disamakan jadi `[JARVIES] #XXXX : description` agar konsisten dari arah manapun.

## Konteks Arsitektur (penting)

- 1 Customer (company) = `customer.customer_id` = **N akun login** (`auth_users`), tiap akun 1 `contact_id` → `customer_contact`.
- Akun customer di-*grant* admin EcoSystem via "Grant Access" pada Contact Person.
- Role customer di Jarvies = `role.id = 3`. Visibility tiket saat ini sudah pakai `can_view_all_tickets` di `JARVIES/app/Http/Controllers/TicketController.php@getTickets`.
- Subject standar EcoSystem saat ini: `[JARVIES] #<ticket_number> : <desc 80 char>` (lihat `EcoSystem/app/Http/Controllers/TicketMessageController.php`).

## Status Tracking

- [x] **Fase 1** — Customer Admin/Member roles ✅ SELESAI 2026-06-30 → [phase-1-customer-roles.md](phase-1-customer-roles.md)
- [x] **Fase 2** — Konsistensi subject `[JARVIES] #XXXX : desc` (fix Jarvies chat-duluan) ✅ SELESAI 2026-06-30 → [phase-2-ticket-import-chat.md](phase-2-ticket-import-chat.md)

> Tiap file fase berisi **kickoff prompt** siap-tempel untuk room chat baru.
