# Man Days Proposal — UI States Reference

Dokumen ini menjelaskan tampilan yang seharusnya muncul di **EcoSystem (sisi employee/helpdesk)**
untuk setiap status Man Days Proposal, selaras dengan tampilan yang sudah diimplementasikan di JARVIES (sisi customer).

---

## Alur Status

```
pic_draft
    └─ [helpdesk finalize] ─► sent_to_chat
                                   ├─ [customer approve] ─► approved
                                   └─ [customer reject]  ─► pending_helpdesk
                                                               └─ [helpdesk revise] ─► sent_to_chat (v+1)
                                                                  [helpdesk cancel]  ─► canceled
```

---

## Status: `pic_draft`

**Siapa yang bisa lihat:** Employee (PIC) saja
**Siapa yang tidak bisa lihat:** Customer (JARVIES menyembunyikan panel mandays)

**EcoSystem tampilan:**
- Badge abu-abu: `Draft`
- Tabel pivot activity × module (editable)
- Tombol **"Send to Customer"** → ubah status ke `sent_to_chat`
- Tombol **"Delete / Cancel"**

---

## Status: `sent_to_chat`

**Siapa yang bisa lihat:** Employee + Customer
**Customer melihat:** Badge amber "Awaiting Your Response" + tabel + tombol Approve & Reject

**EcoSystem tampilan:**
- Badge kuning/amber: `Awaiting Customer Response`
- Tabel pivot (read-only di sisi EcoSystem saat menunggu respon)
- Total Man Days
- Catatan helpdesk (jika ada)
- Tombol **"Cancel Proposal"** → ubah status ke `canceled`
- **Tidak ada tombol Approve/Reject** (hak customer)

---

## Status: `approved` ✅

**Siapa yang bisa lihat:** Employee + Customer
**Customer melihat:** Banner hijau "Approved by Customer" + tanggal approval + tabel + total MD (tanpa tombol)

**EcoSystem tampilan yang direkomendasikan:**
```
┌──────────────────────────────────────────────────────┐
│  ✓  Approved by Customer                             │  ← banner hijau
│     17 Mar 2026, 10:49 (WIB)                         │
└──────────────────────────────────────────────────────┘
│  [tabel pivot activity × module — read-only]          │
│  Total Man Days:  8 MD                               │  ← nilai hijau/bold
└──────────────────────────────────────────────────────┘
```

- **Tidak ada tombol** (proposal sudah final, tidak bisa diubah)
- Badge: `Approved` (hijau)
- Tampilkan `customer_response_at` sebagai timestamp persetujuan
- Nilai `man_days` pada ticket sudah otomatis diupdate = `total_mandays` dari proposal ini

**Data yang tersedia di DB:**
| Kolom | Tabel | Nilai |
|---|---|---|
| `status` | `customer_mandays` | `approved` |
| `customer_response_at` | `customer_mandays` | timestamp saat customer klik Approve |
| `mandays_proposal_status` | `ticket` | `approved` |
| `man_days` | `ticket` | total mandays yang disetujui |

---

## Status: `pending_helpdesk` (Rejected by Customer)

**Siapa yang bisa lihat:** Employee saja
**Customer melihat:** Panel mandays **disembunyikan** (JARVIES return `visible: false`)

**EcoSystem tampilan yang direkomendasikan:**
```
┌──────────────────────────────────────────────────────┐
│  ✕  Rejected by Customer                            │  ← banner merah/oranye
│     17 Mar 2026, 11:00 (WIB)                         │
└──────────────────────────────────────────────────────┘
│  Reason: "Jumlah mandays terlalu tinggi, mohon       │
│  ditinjau kembali untuk modul Business Consulting."   │
│                                                      │
│  [tabel pivot — read-only]                           │
│  Total Man Days:  8 MD                               │
└──────────────────────────────────────────────────────┘
[ Revise & Resend ]    [ Cancel Proposal ]
```

- Tombol **"Revise & Resend"** → buka editor pivot baru, increment version, submit → `sent_to_chat`
- Tombol **"Cancel Proposal"** → ubah status ke `canceled`
- Tampilkan `rejection_reason` / `customer_notes` dari `customer_mandays`
- Tampilkan `customer_response_at`

**Data yang tersedia di DB:**
| Kolom | Tabel | Nilai |
|---|---|---|
| `status` | `customer_mandays` | `pending_helpdesk` |
| `rejection_reason` | `customer_mandays` | alasan penolakan dari customer |
| `customer_notes` | `customer_mandays` | sama dengan rejection_reason |
| `customer_response_at` | `customer_mandays` | timestamp saat customer klik Reject |
| `mandays_proposal_status` | `ticket` | `pending_helpdesk` |

---

## Status: `canceled`

**Siapa yang bisa lihat:** Employee + Customer
**Customer melihat:** Teks abu-abu "Proposal was canceled by the helpdesk."

**EcoSystem tampilan:**
- Badge abu-abu: `Canceled`
- Teks info: "Proposal ini telah dibatalkan."
- Tombol **"Create New Proposal"** → buat versi baru dengan status `pic_draft`

---

## Ringkasan Tombol per Status

| Status | JARVIES (Customer) | EcoSystem (Employee) |
|---|---|---|
| `pic_draft` | Panel disembunyikan | Edit tabel + Send to Customer |
| `sent_to_chat` | **Approve** + **Reject** | Cancel Proposal |
| `approved` | Tampilan read-only (hijau) | Tampilan read-only (hijau) |
| `pending_helpdesk` | Panel disembunyikan | **Revise & Resend** + Cancel |
| `canceled` | Pesan "Canceled by helpdesk" | Create New Proposal |

---

## API Endpoints (JARVIES)

| Method | URL | Keterangan |
|---|---|---|
| `GET` | `/tickets/{id}/mandays` | Ambil proposal terbaru (customer-visible) |
| `POST` | `/tickets/{id}/mandays/approve` | Customer approve proposal |
| `POST` | `/tickets/{id}/mandays/reject` | Customer reject + reason |

**Response `GET /tickets/{id}/mandays` saat `approved`:**
```json
{
  "success": true,
  "visible": true,
  "mandays_proposal_status": "approved",
  "proposal": {
    "id": 3,
    "version": 1,
    "status": "approved",
    "total_mandays": 8.0,
    "notes": "Ini say bapak/ibu",
    "customer_notes": null,
    "proposed_at": "2026-03-17T03:49:00+00:00",
    "customer_response_at": "2026-03-17T04:10:00+00:00"
  },
  "activities": ["General", "CR"],
  "modules": ["Business Consulting"],
  "grid": {
    "General": { "Business Consulting": 6.0 },
    "CR":      { "Business Consulting": 2.0 }
  },
  "column_totals": {
    "Business Consulting": 8.0
  }
}
```
