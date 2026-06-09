# Dokumentasi SLA — Perhitungan Waktu, Bola, dan Aturan Perpindahan

---

## 1. Apa itu SLA?

SLA (**Service Level Agreement**) adalah perjanjian waktu respons dan penyelesaian tiket antara helpdesk dan customer. Sistem ini mengukur seberapa cepat tim helpdesk merespons dan menyelesaikan tiket, dengan mempertimbangkan waktu menunggu balasan dari customer (**waktu tunggu tidak dihitung ke SLA**).

---

## 2. Mode SLA

| Mode | Berlaku Untuk | Yang Diukur |
|------|---------------|-------------|
| `full` | Incident, Service Request | Response Time + Resolution Time |
| `response_only` | Change Request, Konsultasi | Hanya Response Time |

---

## 3. Perhitungan Waktu

### 3.1 Dua Jenis Waktu SLA

#### Response Time (Waktu Respons)
Mengukur seberapa cepat helpdesk **memvalidasi** tiket setelah tiket masuk.

```
Response Duration = first_responded_at − sla_start_at
```

> `sla_start_at` = waktu email/request masuk (immutable, tidak pernah berubah)  
> `first_responded_at` = waktu helpdesk memvalidasi tiket

---

#### Resolution Time (Waktu Penyelesaian)
Mengukur waktu **bersih** penyelesaian tiket, dikurangi waktu menunggu customer.

```
Net Resolution = Gross Resolution − Total Waiting Hours

Gross Resolution = resolved_at − sla_start_at
Total Waiting Hours = jumlah akumulasi waktu bola di sisi customer/SAP
```

---

### 3.2 Formula Perhitungan Jam — `calcHours()`

Terdapat dua mode perhitungan jam:

#### Mode 24 Jam (`is_24_hours = true`)
```
hours = (endTime − startTime) dalam menit / 60
```
Semua jam dihitung, termasuk malam dan weekend.

#### Mode Business Hours (`is_24_hours = false`)
```
Untuk setiap hari dari startTime → endTime:
  jika BUKAN hari weekend (Sabtu/Minggu):
    hitung menit yang berada dalam rentang 09:00 − 18:00

hours = total_menit / 60
```
Hanya jam kerja (Senin–Jumat, 09:00–18:00) yang dihitung.

---

### 3.3 Deadline SLA

| Deadline | Formula |
|----------|---------|
| `response_due_at` | `sla_start_at + policy.response_hours` |
| `resolution_due_at` | `sla_start_at + policy.resolution_hours` |

---

## 4. Bola (Ball)

### 4.1 Apa itu Bola?

**Bola** adalah penanda tanggung jawab — siapa yang saat ini "memegang" tiket dan bertanggung jawab untuk bergerak. Posisi bola menentukan apakah jam SLA sedang berjalan atau sedang dijeda.

### 4.2 Tiga Posisi Bola

| Nilai `ball_holder` | Artinya | Status Jam SLA |
|---------------------|---------|----------------|
| `helpdesk` | Tim helpdesk sedang mengerjakan | **Jam berjalan** |
| `customer` | Menunggu balasan customer | **Jam dijeda** |
| `sap` | Eskalasi ke sistem SAP | **Jam dijeda** |

> Selama bola berada di `customer` atau `sap`, waktu yang berlalu **tidak dihitung** ke total resolusi SLA.

---

## 5. Aturan Perpindahan Bola

### 5.1 Tabel Aturan Lengkap

| Event | Bola Sebelum | Bola Sesudah | Efek |
|-------|-------------|--------------|------|
| Tiket dibuat / divalidasi | — | `helpdesk` | SLA mulai berjalan |
| **Agent mengirim balasan** | `helpdesk` | `customer` | `sla_paused_at` dicatat, jam mulai dijeda |
| **Customer membalas (pertama dalam burst)** | `customer` | `helpdesk` | Waktu tunggu dihitung dan ditambahkan ke `total_waiting_hours` |
| Customer membalas lagi (masih dalam satu burst) | `helpdesk` | `helpdesk` (tidak berubah) | Diabaikan, tidak ada perubahan state |
| **Meeting dimulai** | `helpdesk` | `customer` | Waktu meeting dianggap waktu tunggu |
| **Meeting selesai** | `customer` | `helpdesk` | Waktu dari meeting start → end dihitung sebagai waiting |
| **Tiket ditutup/resolved** | apapun | `helpdesk` | Waiting terakhir dihitung, SLA ditutup |

---

### 5.2 Detail Mekanisme Waktu Tunggu

Setiap kali bola berpindah dari `customer` → `helpdesk`, sistem menghitung:

```
waiting_hours = calcHours(sla_paused_at, sekarang, is_24_hours)
total_waiting_hours += waiting_hours
sla_paused_at = NULL  ← direset
```

**Net Resolution** selalu dihitung ulang sebagai:

```
net_resolution_hours = gross_resolution_hours − total_waiting_hours
```

---

### 5.3 Ilustrasi Alur Bola

```
Tiket Masuk
     │
     ▼
[helpdesk] ← Jam SLA berjalan
     │
     │ Agent balas customer
     ▼
[customer] ← Jam SLA JEDA, sla_paused_at dicatat
     │
     │ Customer balas balik
     ▼
[helpdesk] ← Jam SLA lanjut, waiting dihitung & diakumulasi
     │
     │ Meeting dimulai
     ▼
[customer] ← Jam SLA JEDA
     │
     │ Meeting selesai
     ▼
[helpdesk] ← Jam SLA lanjut
     │
     │ Tiket diselesaikan
     ▼
  CLOSED    ← Net SLA = Gross − Total Waiting
```

---

## 6. Status SLA

### Response Status

| Status | Kondisi |
|--------|---------|
| `pending` | Belum direspons, deadline belum lewat |
| `met` | Direspons sebelum `response_due_at` |
| `breached` | Direspons setelah `response_due_at` |

### Resolution Status

| Status | Kondisi |
|--------|---------|
| `pending` | Belum resolved, deadline belum lewat |
| `paused` | Bola sedang di customer/SAP |
| `met` | Resolved sebelum `resolution_due_at` |
| `breached` | Resolved setelah `resolution_due_at` |

---

## 7. Kolom-Kolom Penting di `ticket_sla`

| Kolom | Keterangan |
|-------|-----------|
| `sla_start_at` | Waktu tiket masuk (tidak pernah berubah) |
| `response_due_at` | Batas waktu respons |
| `resolution_due_at` | Batas waktu penyelesaian |
| `first_responded_at` | Kapan helpdesk pertama merespons |
| `validation_duration_hours` | Durasi dari masuk → respons pertama |
| `resolved_at` | Kapan tiket ditutup |
| `net_resolution_hours` | Waktu resolusi bersih (gross − waiting) |
| `total_waiting_hours` | Total akumulasi waktu tunggu customer |
| `ball_holder` | Pemegang bola saat ini |
| `sla_paused_at` | Kapan bola terakhir berpindah ke customer/SAP |
| `session_start_at` | Awal sesi helpdesk aktif saat ini |
| `sla_mode` | `full` atau `response_only` |
| `response_status` | `pending` / `met` / `breached` |
| `resolution_status` | `pending` / `paused` / `met` / `breached` |

---

## 8. SLA Policy

Policy menentukan target jam per tiket berdasarkan kombinasi:
- **Priority**: Low / Medium / High / Very High
- **Scale**: Simple / Medium / Complex
- **is_24_hours**: `true` (24 jam) / `false` (business hours)

Urutan lookup policy:
1. Policy spesifik customer (customer_id + priority match)
2. Fallback ke policy global (customer_id = NULL)

---

## 9. Artisan Commands

| Command | Fungsi |
|---------|--------|
| `php artisan sla:sync` | Sinkronisasi SLA untuk tiket yang belum diproses |
| `php artisan sla:sync --force` | Regenerasi semua data SLA |
| `php artisan sla:sync --skip-events` | Skip backfill event log |
| `php artisan sla:backfill-events` | Recreate SLA events dari timestamp pesan (24 jam terakhir) |
| `php artisan sla:backfill-events --all` | Proses seluruh histori |
| `php artisan sla:backfill-events --recalculate` | Regenerasi ulang dengan formula terbaru |

---

## 10. Ringkasan Aturan Kunci

1. **Bola di helpdesk = jam jalan.** Bola di customer/SAP = jam berhenti.
2. **Agent balas → bola ke customer.** Customer balas → bola balik ke helpdesk.
3. **Burst customer diabaikan.** Hanya balasan customer *pertama* setelah agent yang memindahkan bola.
4. **Waktu meeting = waktu tunggu.** Durasi meeting dikurangi dari total resolusi.
5. **Net Resolution = Gross − Waiting.** SLA yang dinilai adalah waktu bersih, bukan waktu kalender mentah.
6. **Mode `response_only`** tidak memiliki resolution deadline — hanya mengukur kecepatan respons pertama.
