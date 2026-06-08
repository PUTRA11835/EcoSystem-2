# Jarvies → EcoSystem SLA Integration Guide

> **Audience**: Developer Jarvies  
> **Konteks**: Jarvies adalah **portal customer** — antarmuka web yang digunakan customer untuk melihat tiket dan berkomunikasi dengan helpdesk  
> **Versi EcoSystem**: Laravel 12 / commit `99c2cf0` (2026-05-28)  
> **Tipe koneksi Jarvies**: Direct DB + REST API

---

## ⚠️ Breaking Changes — Baca Ini Dulu

Tiga perubahan besar di EcoSystem yang **langsung berdampak** ke Jarvies:

| # | Perubahan | Dampak ke Jarvies |
|---|---|---|
| 1 | Kolom `jarvies_status` **dihapus** dari tabel `ticket` | Query Jarvies yang membaca `jarvies_status` untuk display label ke customer akan error |
| 2 | `ticket.status` ganti ENUM — nilai lama tidak valid | Filter/display status lama tidak ada hasil atau error |
| 3 | 4 tabel SLA baru tersedia | Jarvies bisa membaca deadline & status SLA tiket untuk ditampilkan ke customer |

---

## 1. Perubahan Schema `ticket`

### 1.1 Sebelum & Sesudah

```sql
-- ══ SEBELUM (LAMA) ══════════════════════════════════════
ticket.status          ENUM('open','in_progress','hold','cancel','closed','reply','wait_to_close')
ticket.jarvies_status  VARCHAR(255)   ← KOLOM INI SUDAH TIDAK ADA

-- ══ SESUDAH (BARU) ══════════════════════════════════════
ticket.status          ENUM(
    'open',
    'inprocess',
    'waiting_on_customer',
    'waiting_on_3rd_party',
    'waiting_to_confirmation',
    'hold',
    'cancelled',
    'closed'
)
-- TIDAK ADA lagi kolom jarvies_status
```

### 1.2 Mapping Nilai Status Lama → Baru

| Nilai Lama (`status`) | Nilai Lama (`jarvies_status`) | Nilai Baru (`status`) |
|---|---|---|
| `open` | `sent it to support` | `open` |
| `in_progress` | `in process` | `inprocess` |
| `reply` | `author action` | `waiting_on_customer` |
| `in_progress` | `sent in to SAP` | `waiting_on_3rd_party` |
| `wait_to_close` | `proposed solution` | `waiting_to_confirmation` |
| `hold` | *(any)* | `hold` |
| `cancel` | *(any)* | `cancelled` |
| `closed` | `closed` | `closed` |

### 1.3 Label yang Ditampilkan ke Customer

Jarvies perlu mengubah label UI-nya sesuai nilai status baru:

| `ticket.status` | Label untuk Customer | Deskripsi yang Ditampilkan |
|---|---|---|
| `open` | Tiket Diterima | Tiket Anda sudah diterima dan dalam antrian |
| `inprocess` | Sedang Diproses | Tim helpdesk sedang menangani tiket Anda |
| `waiting_on_customer` | Menunggu Respons Anda | Helpdesk membutuhkan informasi tambahan dari Anda |
| `waiting_on_3rd_party` | Sedang Dieskalasi | Tiket diteruskan ke tim teknis / pihak ketiga |
| `waiting_to_confirmation` | Menunggu Konfirmasi Anda | Solusi sudah dikirim — mohon konfirmasi apakah masalah sudah teratasi |
| `hold` | Ditunda Sementara | Penanganan tiket ditunda, akan dilanjutkan segera |
| `cancelled` | Dibatalkan | Tiket telah dibatalkan |
| `closed` | Selesai | Tiket telah diselesaikan |

---

## 2. Migrasi Query Jarvies

### 2.1 Query yang Harus Diubah

**❌ Query lama — AKAN ERROR:**
```sql
-- Error: kolom jarvies_status tidak ada lagi
SELECT ticket_id, jarvies_status FROM ticket WHERE customer_id = :cust;
SELECT * FROM ticket WHERE jarvies_status = 'in process' AND customer_id = :cust;

-- Error: nilai ENUM lama tidak valid
SELECT * FROM ticket WHERE status = 'in_progress' AND customer_id = :cust;
SELECT * FROM ticket WHERE status = 'reply' AND customer_id = :cust;
SELECT * FROM ticket WHERE status = 'wait_to_close' AND customer_id = :cust;
SELECT * FROM ticket WHERE status = 'cancel' AND customer_id = :cust;
```

**✅ Query baru yang benar:**
```sql
-- Ambil semua tiket milik customer (untuk daftar tiket di Jarvies)
SELECT
    ticket_id,
    ticket_number,
    description,
    status,                          -- baca langsung, tidak perlu jarvies_status
    ticket_priority,
    ticket_type,
    created_at,
    updated_at
FROM ticket
WHERE customer_id = :cust_id
ORDER BY updated_at DESC;

-- Filter berdasarkan status (nilai baru)
SELECT * FROM ticket WHERE customer_id = :cust AND status = 'inprocess';
SELECT * FROM ticket WHERE customer_id = :cust AND status = 'waiting_on_customer';
```

### 2.2 Referensi Cepat Nilai Lama → Baru

```
-- LAMA (jarvies_status)              → BARU (status)
jarvies_status = 'in process'         → status = 'inprocess'
jarvies_status = 'author action'      → status = 'waiting_on_customer'
jarvies_status = 'sent in to SAP'     → status = 'waiting_on_3rd_party'
jarvies_status = 'proposed solution'  → status = 'waiting_to_confirmation'
jarvies_status = 'closed'             → status = 'closed'

-- LAMA (status)                      → BARU (status)
status = 'in_progress'                → status = 'inprocess'
status = 'reply'                      → status = 'waiting_on_customer'
status = 'wait_to_close'              → status = 'waiting_to_confirmation'
status = 'cancel'                     → status = 'cancelled'
status = 'hold'                       → status = 'hold'    (tidak berubah)
status = 'closed'                     → status = 'closed'  (tidak berubah)
```

---

## 3. Peran Customer dalam SLA

Jarvies perlu memahami ini: **ketika customer membalas pesan via Jarvies, SLA clock otomatis resume**.

### 3.1 Alur yang Terjadi

```
Customer mengirim pesan via Jarvies
         │
         ▼ (EcoSystem menerima pesan)
  SlaService::recordMessageEvent() dipanggil
         │
         ▼
  Jika sebelumnya clock sedang BERHENTI (ball di customer/sap):
    → total_waiting_hours += jam yang sudah berlalu
    → ball_holder kembali ke 'helpdesk'
    → SLA clock BERJALAN lagi
    → ticket_sla_pauses: ended_at dan duration_hours diisi
```

**Jarvies tidak perlu melakukan apa-apa** untuk ini — EcoSystem menanganinya otomatis saat pesan customer diterima. Yang penting: pesan dikirim via endpoint yang sudah ada, bukan direct insert ke `ticket_message`.

### 3.2 Status yang Mengindikasikan "Bola di Customer"

Ketika status tiket adalah salah satu dari ini, artinya **helpdesk sedang menunggu customer**:

| Status | Artinya untuk Customer |
|---|---|
| `waiting_on_customer` | Customer harus membalas / memberikan informasi |
| `waiting_to_confirmation` | Customer harus mengkonfirmasi apakah solusi sudah berhasil |

> Jarvies bisa menampilkan **notifikasi/highlight khusus** untuk tiket dengan status ini, karena customer perlu bertindak.

---

## 4. Tabel SLA — Data yang Bisa Dibaca Jarvies

Jarvies boleh **membaca** tabel SLA untuk menampilkan informasi ke customer. Jangan pernah menulis langsung ke tabel ini.

### 4.1 `ticket_sla` — Deadline & Status SLA per Ticket

```sql
-- Ambil info SLA untuk ditampilkan ke customer
SELECT
    ts.sla_mode,
    ts.response_due_at,       -- deadline respons awal helpdesk
    ts.resolution_due_at,     -- deadline penyelesaian (NULL jika response_only)
    ts.response_status,       -- 'pending' | 'met' | 'breached'
    ts.resolution_status,     -- 'pending' | 'paused' | 'met' | 'breached'
    ts.resolved_at,           -- kapan tiket diselesaikan
    ts.ball_holder,           -- siapa yang "pegang bola" sekarang
    sp.response_hours,        -- target respons (jam)
    sp.resolution_hours       -- target resolusi (jam)
FROM ticket_sla ts
LEFT JOIN sla_policies sp ON sp.id = ts.sla_policy_id
WHERE ts.ticket_id = :ticket_id;
```

**Kolom yang relevan untuk ditampilkan ke customer:**

| Kolom | Tampilkan sebagai |
|---|---|
| `response_due_at` | "Batas waktu respons helpdesk" |
| `resolution_due_at` | "Estimasi batas penyelesaian" |
| `response_status` | Badge: `met` = ✅ Tepat Waktu / `breached` = ⚠️ Terlambat |
| `resolution_status` | Status penyelesaian SLA |
| `ball_holder` | Indikator siapa yang perlu bertindak |

### 4.2 `ticket_sla_pauses` — Riwayat Penundaan (opsional)

Jarvies bisa menampilkan ini jika ingin menunjukkan transparansi ke customer mengapa penyelesaian memakan waktu:

```sql
SELECT
    pause_reason,
    started_at,
    ended_at,
    duration_hours,
    CASE
        WHEN ended_at IS NULL THEN 'Masih berlangsung'
        ELSE CONCAT(ROUND(duration_hours, 1), ' jam')
    END AS durasi
FROM ticket_sla_pauses
WHERE ticket_id = :ticket_id
  AND ended_at IS NOT NULL  -- hanya tampilkan pause yang sudah selesai
ORDER BY started_at ASC;
```

### 4.3 `ticket_sla_events` — Timeline SLA (opsional)

Untuk fitur "Riwayat Penanganan" di sisi customer:

```sql
SELECT
    event_type,
    event_at,
    notes
FROM ticket_sla_events
WHERE ticket_id = :ticket_id
  AND event_type IN (
      'email_received',
      'ticket_validated',
      'customer_replied',
      'ticket_closed'
      -- 'agent_replied' bisa disembunyikan dari customer jika tidak ingin detail internal
  )
ORDER BY event_at ASC;
```

---

## 5. REST API Endpoint yang Relevan untuk Jarvies

> **Catatan:** Customer tidak bisa mengubah status tiket. Hanya helpdesk yang bisa. Jarvies hanya perlu endpoint untuk **membaca** data.

### 5.1 Baca SLA Detail per Ticket

```http
GET /api/tickets/{id}/sla
```

**Response:**
```json
{
  "success": true,
  "data": {
    "sla_mode": "full",
    "ball_holder": "customer",
    "response": {
      "status": "met",
      "target_hours": 4,
      "actual_hours": 1.5,
      "due_at": "2026-05-10 13:00:00",
      "responded_at": "2026-05-10 11:30:00"
    },
    "resolution": {
      "status": "paused",
      "target_hours": 24,
      "actual_hours": null,
      "due_at": "2026-05-11 09:00:00",
      "resolved_at": null,
      "net_hours": null,
      "waiting_hours": 3.5
    },
    "events": [ ... ]
  }
}
```

**Interpretasi `ball_holder` untuk customer:**

| `ball_holder` | Tampilkan ke customer |
|---|---|
| `helpdesk` | "Tim helpdesk sedang menangani tiket Anda" |
| `customer` | "Kami menunggu respons Anda" |
| `sap` | "Tiket sedang ditangani oleh tim teknis eksternal" |

---

## 6. Checklist Migrasi untuk Tim Jarvies

### 6.1 Perbaikan Database Query

- [ ] Hapus semua referensi ke kolom `ticket.jarvies_status`
- [ ] Ganti nilai `ticket.status` lama dengan nilai baru (lihat mapping section 1.2)
- [ ] Tidak ada lagi `SELECT jarvies_status` di kode Jarvies
- [ ] Query INSERT ke `ticket_message` tidak berubah — tetap insert pesan normal
- [ ] **Tidak ada** `INSERT` atau `UPDATE` langsung ke tabel `ticket_sla`, `ticket_sla_pauses`, `ticket_sla_events`

### 6.2 Perbaikan Tampilan UI

- [ ] Label status di halaman daftar tiket sudah menggunakan nilai baru (section 1.3)
- [ ] Badge/highlight khusus untuk status `waiting_on_customer` dan `waiting_to_confirmation` — ini tandanya customer perlu bertindak
- [ ] Jika ada filter tiket berdasarkan status, gunakan nilai ENUM baru
- [ ] Info SLA (deadline, status) dibaca dari tabel `ticket_sla`, bukan dari `ticket`

### 6.3 Tampilan SLA untuk Customer (jika ada)

- [ ] Tampilkan `response_due_at` sebagai "batas waktu respons helpdesk"
- [ ] Tampilkan `resolution_due_at` sebagai "estimasi penyelesaian"
- [ ] Tampilkan `response_status` dan `resolution_status` dengan label yang customer-friendly
- [ ] `ball_holder = 'customer'` → tampilkan notifikasi bahwa customer perlu membalas

---

## 7. Contoh: Menampilkan Info Tiket di Jarvies

### Query Daftar Tiket Customer (dengan SLA)

```sql
SELECT
    t.ticket_id,
    t.ticket_number,
    t.description,
    t.status,
    t.ticket_priority,
    t.created_at,
    ts.response_due_at,
    ts.resolution_due_at,
    ts.resolution_status,
    ts.ball_holder,
    CASE
        WHEN ts.ball_holder = 'customer' THEN 1
        ELSE 0
    END AS needs_customer_action
FROM ticket t
LEFT JOIN ticket_sla ts ON ts.ticket_id = t.ticket_id
WHERE t.customer_id = :customer_id
ORDER BY needs_customer_action DESC, t.updated_at DESC;
```

### Fetch SLA via API

```javascript
async function getTicketSla(ticketId) {
    const res = await fetch(`/api/tickets/${ticketId}/sla`);
    const { data } = await res.json();

    if (!data) return null; // tiket ini tidak punya SLA

    const needsAction = data.ball_holder === 'customer';

    return {
        needsCustomerAction: needsAction,
        responseDeadline:    data.response?.due_at,
        resolutionDeadline:  data.resolution?.due_at,
        responseStatus:      data.response?.status,   // 'met' | 'breached' | 'pending'
        resolutionStatus:    data.resolution?.status, // 'pending' | 'paused' | 'met' | 'breached'
        waitingHours:        data.resolution?.waiting_hours,
    };
}
```

---

## 8. FAQ

**Q: Customer bisa mengubah status tiket via Jarvies?**  
A: Tidak. Customer hanya bisa membaca status dan mengirim pesan. Perubahan status (`inprocess`, `waiting_on_customer`, dll.) hanya bisa dilakukan oleh helpdesk.

**Q: Apa yang terjadi ke SLA saat customer membalas pesan di Jarvies?**  
A: SLA clock otomatis resume. EcoSystem mendeteksi bahwa pengirim adalah customer dan langsung menghentikan waktu tunggu, mengembalikan ball ke helpdesk. Jarvies tidak perlu melakukan apa-apa secara eksplisit.

**Q: Jarvies perlu membuat record di tabel SLA saat customer submit tiket baru?**  
A: Tidak. Record SLA dibuat otomatis oleh EcoSystem ketika helpdesk memvalidasi tiket (bukan saat customer submit). Jarvies cukup mengirim pesan/tiket seperti biasa.

**Q: Apakah `ticket_sla_events.jarvis_status` masih ada?**  
A: Ya, kolom itu masih ada di tabel `ticket_sla_events`, tapi sekarang menyimpan nilai `ticket.status` baru (bukan `jarvies_status` lama). Ini hanya untuk audit trail, tidak perlu ditampilkan ke customer.

**Q: Tiket customer di Jarvies tidak muncul di SLA report EcoSystem — kenapa?**  
A: Pastikan tiket sudah terisi `ticket_type` dan `ticket_priority`. Tanpa keduanya, EcoSystem tidak membuat record SLA. Juga pastikan ada SLA policy yang cocok untuk kombinasi customer + priority + scale tiket tersebut.

**Q: Bagaimana cara tahu apakah tiket customer sudah melampaui SLA?**  
A: Cek kolom `resolution_status = 'breached'` di tabel `ticket_sla`, atau gunakan endpoint `GET /api/tickets/{id}/sla` dan periksa `data.resolution.status`.
