# Customer Email Resolution — Urutan Prioritas

## Latar Belakang

Setiap kali EcoSystem mengirim email ke customer (reply ticket, proposal mandays, deliverable, dll), sistem harus mencari alamat email customer. Masalahnya: tidak ada satu kolom tunggal yang selalu terisi untuk semua skenario pembuatan ticket. Ticket bisa masuk via email, via portal Jarvies, via import CSV, atau dibuat manual oleh agent — masing-masing menyimpan email di tempat berbeda.

Solusinya adalah **resolve bertahap**: cek sumber paling reliable dulu, turun ke fallback berikutnya jika kosong.

---

## Urutan Prioritas (4 Langkah)

| Urutan | Sumber | Kolom | Kapan Terisi |
|--------|--------|-------|--------------|
| 1 | `tickets.submitted_by_email` | `tickets.submitted_by_email` | Saat agent klik **Initiate Email** di ticket, atau ticket dibuat via import CSV dengan email customer |
| 2 | `staging_tickets.submitted_by_email` | `staging_tickets.submitted_by_email` | Saat customer submit ticket dari portal Jarvies (form staging) dan ticket diapprove |
| 3 | `ticket_messages.sender_email` (pesan pertama dari customer) | `ticket_messages.sender_email` | Saat email masuk dari customer diproses oleh webhook inbound email — `sender_email` diisi dari header `From:` email asli |
| 4 | `customers.email` | `customers.email` | Email login akun customer di sistem — sering kosong karena tidak semua customer punya akun portal |

> **Penting:** Langkah 4 (`customers.email`) adalah email login perusahaan/akun, bukan alamat kontak personal. Field ini sering NULL, jadi tidak boleh dijadikan sumber utama.

---

## Skenario Nyata

### Ticket dari email customer (paling umum)
- Customer kirim email → masuk ke inbound webhook → `staging_tickets` dibuat
- Setelah diapprove → `ticket.submitted_by_email` diisi dari `staging_tickets.submitted_by_email`
- **Langkah 1 berhasil**

### Ticket dari portal Jarvies
- Customer isi form di Jarvies → `staging_tickets.submitted_by_email` terisi
- Setelah diapprove → ticket dibuat, tapi `ticket.submitted_by_email` mungkin tidak diset
- **Langkah 2 berhasil**

### Ticket dibuat manual + pernah ada balasan email dari customer
- Ticket dibuat manual oleh agent, lalu customer pernah balas via email
- `ticket_messages` punya row dengan `sender_type = 'customer'` dan `sender_email` terisi
- **Langkah 3 berhasil**

### Ticket dibuat manual, customer terdaftar di sistem
- Tidak ada email masuk sama sekali, tapi customer punya akun dengan `customers.email` terisi
- **Langkah 4 berhasil**

### Tidak ada email sama sekali
- Semua langkah null → email tidak dikirim → pesan/proposal tetap tersimpan di chat internal
- Sistem menampilkan warning: *"No customer email address found. Proposal saved to chat only."*

---

## Implementasi Per Controller

### `TicketMessageController` — method `resolveCustomerEmail()`
Digunakan untuk: reply agent ke customer, sistem email (meeting invitation, dll).

```php
private function resolveCustomerEmail(Ticket $ticket): ?string
{
    // 1. submitted_by_email langsung di ticket
    if (!empty($ticket->submitted_by_email)) {
        return $ticket->submitted_by_email;
    }

    // 2. submitted_by_email dari staging ticket
    $submittedEmail = DB::table('staging_tickets')
        ->where('ticket_id', $ticket->ticket_id)
        ->whereNotNull('submitted_by_email')
        ->value('submitted_by_email');
    if ($submittedEmail) return $submittedEmail;

    // 3. sender_email dari pesan pertama customer
    $firstMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
        ->where('sender_type', 'customer')
        ->whereNotNull('sender_email')
        ->orderBy('created_at', 'asc')
        ->first();
    if ($firstMsg?->sender_email) return $firstMsg->sender_email;

    // 4. Fallback ke customer.email
    if ($ticket->customer_id) {
        return Customer::find($ticket->customer_id)?->email;
    }

    return null;
}
```

---

### `TicketDeliverableController` — method `resolveCustomerEmail()`
Digunakan untuk: pengiriman dokumen deliverable ke customer.

Implementasi identik dengan `TicketMessageController` (4 langkah sama).

---

### `MandaysController` — method `resolveCustomerEmailForTicket()`
Digunakan untuk: pengiriman proposal mandays ke customer via tombol **Send to Customer**.

Implementasi identik dengan `TicketMessageController` (4 langkah sama, langkah 1 ditambahkan pada 2026-07-02 setelah ditemukan bug warning *"No customer email address found"*).

---

## Catatan untuk Implementasi Baru

Setiap kali ada fitur baru yang perlu mengirim email ke customer, ikuti pola yang sama persis — 4 langkah, urutan sama. Jangan hanya pakai `customer.email` karena sering kosong.

Template yang bisa di-copy:

```php
private function resolveCustomerEmail(Ticket $ticket): ?string
{
    if (!empty($ticket->submitted_by_email)) {
        return $ticket->submitted_by_email;
    }

    $submittedEmail = DB::table('staging_tickets')
        ->where('ticket_id', $ticket->ticket_id)
        ->whereNotNull('submitted_by_email')
        ->value('submitted_by_email');
    if ($submittedEmail) return $submittedEmail;

    $firstMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
        ->where('sender_type', 'customer')
        ->whereNotNull('sender_email')
        ->orderBy('created_at', 'asc')
        ->first();
    if ($firstMsg?->sender_email) return $firstMsg->sender_email;

    if ($ticket->customer_id) {
        return Customer::find($ticket->customer_id)?->email;
    }

    return null;
}
```

---

## Checklist Debugging

Jika muncul warning *"No customer email address found"* pada ticket tertentu:

1. Cek `tickets.submitted_by_email` → kosong?
2. Cek `staging_tickets` dengan `ticket_id` tersebut → ada? `submitted_by_email` terisi?
3. Cek `ticket_messages` dengan `sender_type = 'customer'` → ada yang punya `sender_email`?
4. Cek `customers.email` untuk customer di ticket tersebut → terisi?
5. Jika semua kosong → ticket memang tidak punya jejak email customer. Solusi: agent tambahkan email customer secara manual (fitur `initiateEmail` untuk set `ticket.submitted_by_email`).
