# User Guide — rencana topik (4 kelompok penulis)

Format wajib: `_AUTHORING.md`. Slug yang boleh dipakai di `requires`: `_slugs.txt` (cari menurut nama menu; pilih slug yang membuat orang melihat menu itu di sidebar). Kolom **requires** di bawah hanyalah petunjuk awal — **periksa di kode/sidebar** dan perbaiki bila perlu. Boleh menambah/memecah/menggabung topik bila fitur sebenarnya menuntut (catat di laporan). Jangan menulis topik untuk fitur yang tidak ada.

Model contoh: `en/010-welcome.md` dan `id/010-welcome.md` (topik pembuka, sudah ditulis).

Bahasa: nama berkas, `key`, `chapter`, `order`, `requires` SAMA di en dan id.

## Kelompok A — bab `getting-started` dan `my-workspace` (fitur untuk pegawai/ESS)
| key | bab | order | requires (petunjuk) | isi |
|---|---|---|---|---|
| navigating-ecosystem | getting-started | 20 | [] | Dashboard, sidebar & pencarian menu, bagian yang bisa dilipat, Favorites, mode ikon, lonceng Notification, menu profil pojok kanan, Settings pribadi (theme, accent, language…) — verifikasi fitur yang benar-benar ada |
| statuses-and-approvals | getting-started | 30 | [] | Arti status umum (Draft, Submitted, Pending, Approved, Rejected, dst. — hanya yang benar dipakai aplikasi), alur persetujuan, apa yang dilakukan setelah ditolak/disetujui |
| access-and-menus | getting-started | 40 | [] | Mengapa menu tiap orang berbeda (role & Menu Access, ESS visibility), apa yang dilakukan bila menu tidak terlihat |
| my-profile | my-workspace | 10 | [my-profile.section.basic_data, …] | Tab My Profile, mengisi/ubah data, progres kelengkapan, kunci profil oleh HR (View Only) |
| my-attendance | my-workspace | 20 | [general.my-attendance] | Check-in/out, koreksi presensi |
| my-leave-permit | my-workspace | 30 | [ess.my_leave_permit] | |
| my-overtime | my-workspace | 40 | [general.my-overtime] | |
| my-reimbursement | my-workspace | 50 | [general.my-reimbursement] | |
| my-purchase-request | my-workspace | 60 | [general.my-purchase-request] | |
| my-cash-advance | my-workspace | 70 | [general.my-cash-advance, general.my-cash-advance-report] | CA dan CAR |
| my-paystub | my-workspace | 80 | [general.my-paystub] | |
| my-kpi | my-workspace | 90 | [general.my-kpi] | |
| my-approvals | my-workspace | 100 | [general.approvals] | Menyetujui/menolak, export |
| my-calendar-timesheet | my-workspace | 110 | [calendar, calendar.events, calendar.timesheets] | |
| my-letter-requests | my-workspace | 120 | [general.my-letter-requests] | |
| ai-tools | my-workspace | 130 | [ai-assistant, ai-research] | termasuk batas penggunaan data sensitif bila ada di kode |
| room-chat | my-workspace | 140 | [room-chat] | bila fitur benar ada |

## Kelompok B — bab `human-capital` dan `external` (HR & konsultan eksternal)
| key | bab | order | requires (petunjuk) |
|---|---|---|---|
| employee-data | human-capital | 10 | [master.employee] — Master Employee: daftar, pencarian, tab data, tambah karyawan, kunci profil |
| onboarding | human-capital | 20 | [general.onboarding] — progres, Verify & Lock, Unlock (alasan wajib) |
| recruitment | human-capital | 30 | [general.recruitment, general.recruitment.candidates, general.recruitment.jobs, general.recruitment.schedule, general.recruitment.settings] |
| offering-letter | human-capital | 40 | [general.offering-letter, general.recruitment.offers] |
| contracts | human-capital | 50 | [general.contracts, general.contracts.list] — daftar kesiapan data, buat/aktifkan, template, Settings, penandatanganan |
| letter-templates | human-capital | 60 | [general.letters, …] — Dashboard, Requests, Letter Register, Create Letter, Document Identity |
| attendance-management | human-capital | 70 | [general.attendance, general.attendance.correction, general.settings.attendance, general.settings.shifts] |
| leave-permit-management | human-capital | 80 | [hr_general.leave_permit, …tab-*] |
| overtime-management | human-capital | 90 | [general.overtime, general.settings.overtime] |
| kpi-evaluation | human-capital | 100 | [general.kpi-evaluation, …] |
| offboarding | human-capital | 110 | [general.offboarding, …] — termasuk saklar force deactivate bila ada |
| inventory-assets | human-capital | 120 | [general.inventory, …] |
| external-consultants | external | 10 | [general.external.consultants] — daftar, Add consultant (ECI), tab data, kontak darurat opsional |
| external-contracts | external | 20 | [general.external.contracts] — buat, Assignment, Work placement, penandatangan, Active, dokumen & PDF |
| external-contract-settings | external | 30 | [general.external.contracts.settings] — Contract type, Position type, Work placement |
| external-letters | external | 40 | [general.external.assignment-letters, general.external.employment-certificates, general.external.reference-letters] |
| external-timesheet-payment | external | 50 | [general.external.timesheet, general.external.timesheet.invoices, general.external.timesheet.settings] |
| my-timesheet-invoice | external | 60 | [general.external.my-timesheet] — untuk konsultan |

## Kelompok C — bab `finance`, `business-legal`, `administration`
| key | bab | order | requires (petunjuk) |
|---|---|---|---|
| payroll | finance | 10 | [finance.payroll, finance.payroll.periods, finance.payroll.settings, finance.payroll.simulation] — alur Calculate → Generate Slip → Publish (verifikasi dari kode) |
| bpjs | finance | 20 | [finance.bpjs, …] |
| pph21 | finance | 30 | [finance.pph21, …] |
| reimbursement-management | finance | 40 | [general.reimbursement, general.settings.reimbursement, general.approval-workflow.reimbursement] |
| purchase-management | finance | 50 | [general.purchase-request, general.purchase-order, general.settings.purchase-request, general.approval-workflow.purchase-request] |
| cash-advance-management | finance | 60 | [general.cash-advance, general.cash-advance-report, general.cash-advance-dashboard, management.cash-advance-settings, management.approval-workflow.cash-advance, management.approval-workflow.cash-advance-report] |
| legal-basis | business-legal | 10 | [finance.legal-basis.settings, legal, …] — periksa halaman Legal Basis di sidebar Business & Legal |
| business-partners | business-legal | 20 | [master.customer] — bila halaman ada |
| roles-and-menu-access | administration | 10 | [management.roles, management.permissions] |
| ess-settings | administration | 20 | [management.ess-settings] |
| approval-workflows | administration | 30 | [general.approval-workflow.overtime, general.approval-workflow.reimbursement, general.approval-workflow.purchase-request, management.approval-workflow.cash-advance, management.approval-workflow.cash-advance-report] |
| master-employee-settings | administration | 40 | [management.employee, …] |
| holidays-branches | administration | 50 | [management.holidays, management.branches] |
| control-center | administration | 60 | [control-center, control-center.overview, …activity-log, sessions, failed-jobs, backup, sounds, login-log, ai-settings, audit-log] — pecah per kelompok bila terlalu panjang |
| module-groups-hidden-tickets | administration | 70 | [management.module-groups, management.hidden-tickets] — bila layak |

## Kelompok D — bab `work-service`, `reporting`, `reference`
| key | bab | order | requires (petunjuk) |
|---|---|---|---|
| tickets | work-service | 10 | [tickets.inbox] |
| ticket-validation | work-service | 20 | [tickets.staging] |
| my-tasks-workload | work-service | 30 | [ticket.my-tasks, ticket.consultant-workload] |
| delivery | work-service | 40 | [delivery, delivery.project, delivery.support] |
| sla | work-service | 50 | [sla, sla.report] |
| rpmo | work-service | 60 | [rpmo, rpmo.overview, rpmo.periods] |
| reports-overview | reporting | 10 | [reporting] — daftar laporan dan cara memakai filter/export (satu topik umum) |
| report-ticketing | reporting | 20 | [reporting.ticketing-overview, reporting.ticket-by-module, reporting.resolution-days, reporting.validation, reporting.md-recap, reporting.log-shifting] |
| report-delivery | reporting | 30 | [reporting.collection-outlook, reporting.collection-outlook-support, reporting.consultant-assignment, reporting.diagram-report] |
| workflow-hr-admin | reference | 10 | [general.onboarding, general.contracts, finance.payroll] — alur kerja HR end-to-end berdasarkan fitur yang ADA |
| workflow-manager | reference | 20 | [general.approvals] — tugas atasan/pemberi persetujuan |
| workflow-employee | reference | 30 | [] — daftar periksa pegawai (ESS) |
| workflow-finance | reference | 40 | [finance.payroll, general.reimbursement, general.purchase-request] |
| faq | reference | 50 | [] — 8–12 pertanyaan yang benar-benar sering (menu tidak terlihat, tombol tidak tampil, profil terkunci, status tertahan, bahasa panduan…) |
| glossary | reference | 60 | [] — tabel istilah (EN/ID) yang dipakai di aplikasi |
| whats-new | reference | 70 | [] — daftar perubahan bertanggal yang berguna bagi pengguna (Okt 2026): pemilih jam, koreksi presensi, Paystub otomatis, External Contract (Assignment, penandatangan, Work placement), kontak darurat konsultan, User Guide; tanpa istilah teknis |
