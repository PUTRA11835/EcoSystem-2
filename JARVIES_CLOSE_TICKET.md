# Jarvies — Perubahan: Close Ticket oleh Customer

## Latar Belakang

Saat customer klik "close ticket" di Jarvies, EcoSystem perlu tahu bahwa tiket ditutup
agar SLA event log mencatat `jarvies_status = closed` (bukan tetap "in process").

EcoSystem sudah menyediakan endpoint baru khusus untuk ini.

---

## Endpoint Baru (EcoSystem)

```
POST /api/jarvies/tickets/{ticket_id}/close
```

**Headers:**
```
X-Jarvies-Key: <api_key>
Content-Type: application/json
```

**Body:** _(kosong / tidak perlu body)_

**Response sukses (200):**
```json
{
  "success": true,
  "message": "Tiket berhasil ditutup."
}
```

**Response error — tiket sudah closed (422):**
```json
{
  "success": false,
  "message": "Tiket sudah ditutup."
}
```

---

## Yang Perlu Diubah di Jarvies

### Temukan fungsi / handler saat customer klik tombol "Close Ticket" / "Tutup Tiket"

Kemungkinan besar kodenya sekarang melakukan salah satu dari ini:
- Memanggil `PATCH /tickets/{id}` dengan `status: 'closed'`
- Atau mengirim `customer-reply` dengan pesan tertentu

**Ganti** (atau **tambahkan setelah**) pemanggilan tersebut dengan:

```js
// Contoh implementasi (sesuaikan dengan http client yang dipakai di Jarvies)
async function closeTicket(ticketId) {
  const response = await fetch(`${ECOSYSTEM_BASE_URL}/api/jarvies/tickets/${ticketId}/close`, {
    method: 'POST',
    headers: {
      'X-Jarvies-Key': JARVIES_API_KEY,
      'Content-Type': 'application/json',
    },
  });

  const data = await response.json();

  if (!data.success) {
    // Tampilkan error ke user
    throw new Error(data.message ?? 'Gagal menutup tiket');
  }

  return data;
}
```

---

## Alur Lengkap yang Diharapkan

```
Customer klik "Close Ticket" di Jarvies
        ↓
POST /api/jarvies/tickets/{id}/close
        ↓
EcoSystem:
  - ticket.jarvies_status = 'closed'
  - ticket.status         = 'closed'
  - SLA event log: ticket_closed dicatat dengan net_resolution_hours final
        ↓
SLA Event Log di report EcoSystem menampilkan:
  EVENT          | JARVIS STATUS | BOLA
  Tiket Ditutup  | closed        | ▶ Helpdesk
```

---

## Catatan

- Endpoint ini sudah terproteksi dengan middleware `jarvies.api_key` — tidak perlu auth tambahan, cukup kirim `X-Jarvies-Key` seperti endpoint Jarvies lainnya.
- Jika customer mencoba close tiket yang sudah closed, endpoint mengembalikan `422` — Jarvies bisa tampilkan pesan "Tiket ini sudah ditutup."
- Jangan lagi set `jarvies_status` secara manual dari Jarvies — biarkan EcoSystem yang mengatur agar SLA event selalu sinkron.
