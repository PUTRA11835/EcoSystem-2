# Ticket Status Refactor — Unifikasi Status & Penghapusan `jarvies_status`

> **Tanggal:** 2026-05-25 (migration) / 2026-05-28 (deploy)
> **Migration:** `2026_05_25_113524_refactor_ticket_status_merge_jarvies.php`
> **Commit:** `99c2cf0`

---

## Latar Belakang

Sebelumnya sistem ticket menggunakan **dua kolom terpisah** untuk melacak workflow:

| Kolom | Tipe | Nilai Lama |
|---|---|---|
| `status` | ENUM | `open`, `in_progress`, `hold`, `cancel`, `closed`, `reply`, `wait_to_close` |
| `jarvies_status` | VARCHAR(255) | `sent it to support`, `in process`, `author action`, `sent in to SAP`, `proposed solution`, `closed` |

Pendekatan dua kolom ini menyebabkan:
- **Inkonsistensi** — state workflow tersebar di dua tempat yang berbeda
- **Duplikasi logika** — setiap query perlu memeriksa kedua kolom
- **Ambiguitas** — `status = open` bisa berarti berbeda tergantung `jarvies_status`

---

## Perubahan yang Dilakukan

### 1. Database — Kolom `ticket`

`jarvies_status` **dihapus** dan `status` diubah menjadi ENUM tunggal dengan nilai baru:

```sql
-- Sebelum
status       ENUM('open','in_progress','hold','cancel','closed','reply','wait_to_close')
jarvies_status VARCHAR(255)

-- Sesudah
status       ENUM('open','inprocess','waiting_on_customer','waiting_on_3rd_party',
                  'waiting_to_confirmation','hold','cancelled','closed')
```

### 2. Mapping Migrasi Data

Data lama dipetakan ke nilai baru dengan prioritas berikut:

| Kondisi Lama | Status Baru |
|---|---|
| `status = 'cancel'` | `cancelled` |
| `status = 'closed'` | `closed` |
| `status = 'hold'` | `hold` |
| `jarvies_status = 'in process'` | `inprocess` |
| `jarvies_status = 'author action'` | `waiting_on_customer` |
| `jarvies_status = 'sent in to SAP'` | `waiting_on_3rd_party` |
| `jarvies_status = 'proposed solution'` | `waiting_to_confirmation` |
| `jarvies_status = 'closed'` | `closed` |
| `status = 'in_progress'` (fallback) | `inprocess` |
| `status = 'reply'` (fallback) | `waiting_on_customer` |
| `status = 'wait_to_close'` (fallback) | `waiting_to_confirmation` |
| Semua yang tidak terpetakan | `open` |

---

## Nilai Status Baru (Lengkap)

| Value | Label UI | Deskripsi |
|---|---|---|
| `open` | Open | Ticket baru masuk, belum diproses |
| `inprocess` | Inprocess | Helpdesk sedang aktif mengerjakan |
| `waiting_on_customer` | Waiting on Customer | Menunggu respons / konfirmasi dari customer |
| `waiting_on_3rd_party` | Waiting on 3rd Party | Di-eskalasi ke SAP atau pihak ketiga |
| `waiting_to_confirmation` | Waiting to Confirmation | Solusi sudah dikirim, menunggu persetujuan customer |
| `hold` | Hold | Ticket ditahan sementara |
| `cancelled` | Cancelled | Ticket dibatalkan |
| `closed` | Closed | Ticket selesai dan ditutup |

---

## Perubahan Kode

### `app/Models/Ticket.php`

- **Dihapus:** `jarvies_status` dari `$fillable`
- **Ditambah:** Accessor `getStatusLabelAttribute()` untuk label UI
- **Diperbarui:** Scopes `scopeOpen()`, `scopeInProgress()`, `scopeClosed()`

```php
// Accessor — kembalikan label human-readable
public function getStatusLabelAttribute(): string
{
    return match ($this->status) {
        'open'                    => 'Open',
        'inprocess'               => 'Inprocess',
        'waiting_on_customer'     => 'Waiting on Customer',
        'waiting_on_3rd_party'    => 'Waiting on 3rd Party',
        'waiting_to_confirmation' => 'Waiting to Confirmation',
        'hold'                    => 'Hold',
        'cancelled'               => 'Cancelled',
        'closed'                  => 'Closed',
        default                   => ucfirst($this->status ?? 'Unknown'),
    };
}
```

### `app/Http/Controllers/TicketController.php`

- **Dihapus:** Semua referensi `jarvies_status` di seluruh method (`index`, `show`, `store`, `myTickets`, `confirmAssignment`, dll.)
- **Diperbarui:** `store()` — ticket baru langsung dibuat dengan `status = 'inprocess'` (bukan `open` + `jarvies_status = 'in process'`)
- **Diperbarui:** `storeExternalQuery()` — sama, `status = 'inprocess'`
- **Diperbarui:** `confirmAssignment()` — assign PIC tidak lagi set `jarvies_status`
- **Ditambah:** Method `updateTicketStatus()` — endpoint tunggal untuk update status dengan validasi ENUM

```php
// Endpoint: PUT /api/tickets/{id}/status
// Akses: TICKET_MANAGER_GROUP (Admin, Support Head, Helpdesk, RPMO)
$allowed = 'open,inprocess,waiting_on_customer,waiting_on_3rd_party,
             waiting_to_confirmation,hold,cancelled,closed';
```

### `app/Http/Controllers/TicketMessageController.php`

- **Dihapus:** Referensi `jarvies_status` saat membuat pesan
- **Diperbarui:** Status ticket diupdate via field `status` tunggal ketika agent mengirim pesan dengan status tertentu

### SLA Integration

Method `updateTicketStatus()` sekarang **secara otomatis memicu** `SlaService::handleStatusChange()` setelah setiap perubahan status:

```
Status → SLA Effect
────────────────────────────────────────────────────────
waiting_on_customer     → pause SLA clock (ball → customer)
waiting_on_3rd_party    → pause SLA clock (ball → sap)
waiting_to_confirmation → pause SLA clock (ball → customer)
hold                    → pause SLA clock (ball → customer)
inprocess / open        → resume SLA clock (ball → helpdesk)
closed / cancelled      → finalize SLA (hitung net hours)
```

### Resource: `app/Http/Resources/Mobile/TicketListResource.php` & `TicketDetailResource.php`

- **Dihapus:** Field `jarvies_status` dari response API mobile

---

## Rollback

Migration menyediakan `down()` yang mengembalikan:
1. `jarvies_status` ditambah kembali sebagai VARCHAR
2. Nilai `status` dikembalikan ke ENUM lama (best-effort mapping)
3. **Catatan:** Rollback bersifat *lossy* — beberapa granularitas state mungkin tidak dapat dipulihkan sepenuhnya

---

## Impact pada API

### Endpoint yang terpengaruh

| Method | Endpoint | Perubahan |
|---|---|---|
| `GET` | `/api/tickets` | Response tidak lagi mengandung `jarvies_status` |
| `GET` | `/api/tickets/{id}` | Response tidak lagi mengandung `jarvies_status` |
| `POST` | `/api/tickets` | Ticket baru dibuat dengan `status = 'inprocess'` |
| `PUT` | `/api/tickets/{id}/status` | Field tunggal `status` dengan ENUM baru |
| `GET` | `/api/my-tickets` | Response tidak lagi mengandung `jarvies_status` |

### Contoh Request Update Status

```http
PUT /api/tickets/123/status
Content-Type: application/json

{
  "status": "waiting_on_customer"
}
```

```json
// Response sukses
{
  "success": true,
  "message": "Status updated to waiting_on_customer"
}
```

---

## Catatan untuk Developer

1. **Jangan gunakan `jarvies_status`** — kolom sudah tidak ada di database
2. **Gunakan nilai ENUM yang tepat** saat membuat query raw SQL atau Eloquent
3. **Accessor `$ticket->status_label`** tersedia untuk tampilan UI (tidak perlu switch/match manual)
4. **SLA otomatis terpicu** — tidak perlu memanggil `SlaService` manual saat update status via `updateTicketStatus()`
5. **Ticket baru** selalu dimulai dengan `status = 'inprocess'`, bukan `open`
