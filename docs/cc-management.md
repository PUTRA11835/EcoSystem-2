# CC Management — Ticket Reply Compose Area

> **Berlaku untuk:** EcoSystem (sudah diimplementasikan) & Jarvies (perlu diimplementasikan)
> **Tanggal:** April 2026

---

## Ringkasan Fitur

Pada halaman detail ticket (channel `email`), area compose reply menampilkan:

1. **To row** — alamat email tujuan (customer), hanya tampilan, tidak bisa diedit
2. **CC row** — daftar penerima CC dalam bentuk tag pills, bisa ditambah/dihapus
3. **CC dikirim bersama reply** — saat klik "Send via Email", daftar CC aktif dikirim ke API dan disimpan ke `ticket.cc_emails`

---

## Struktur Visual Compose Area (email channel)

```
┌─────────────────────────────────────────────────────────────┐
│ ✉ Replies will be sent to customer via Email                │
├─────────────────────────────────────────────────────────────┤
│ To   customer@email.com                                     │
├─────────────────────────────────────────────────────────────┤
│ CC  [cc1@email.com ×] [cc2@email.com ×]  Add email...      │
├─────────────────────────────────────────────────────────────┤
│  [Editor Quill]                                             │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                          [Internal Note]  [Send via Email]  │
└─────────────────────────────────────────────────────────────┘
```

- **To row** hanya muncul jika `customerEmail` tersedia
- **CC row** selalu muncul untuk email channel (meski belum ada CC)
- Untuk non-email channel: baris To & CC tidak ditampilkan

---

## Data Source

### "To" Email (Read-only)
Diambil di controller dengan urutan prioritas:
1. `staging_tickets.submitted_by_email` (email login customer dari Jarvies)
2. `ticket_messages.sender_email` — pesan customer pertama
3. `customer.email` — email perusahaan (fallback)

### CC Emails
Diambil dari `ticket.cc_emails` (kolom JSON, cast `array` di model `Ticket`).

Format item CC bisa berupa:
- String: `"someone@email.com"`
- Object: `{ "address": "someone@email.com", "name": "Someone" }`

Saat render tag, selalu normalisasi ke string email saja:
```js
const email = typeof item === 'string' ? item : (item?.address ?? '');
```

---

## Implementasi HTML (EcoSystem — Blade)

```html
{{-- To Row --}}
@if(($ticket->channel === 'email' || $ticket->email_thread_id) && !empty($customerEmail))
<div class="px-4 pt-1.5">
    <div class="flex items-center gap-2 text-xs text-gray-500 px-2 py-1">
        <span class="font-semibold text-gray-500 flex-shrink-0">To</span>
        <span class="text-gray-700">{{ $customerEmail }}</span>
    </div>
</div>
@endif

{{-- CC Row --}}
@if($ticket->channel === 'email' || $ticket->email_thread_id)
<div class="px-4 pt-1.5" id="ccRow">
    <div class="flex flex-wrap items-center gap-1 min-h-[30px] border border-gray-200
                rounded-lg bg-gray-50 px-2 py-1 cursor-text"
         onclick="document.getElementById('ccInput').focus()">
        <span class="text-[11px] text-gray-500 font-semibold mr-0.5 flex-shrink-0">CC</span>
        <div id="ccTagsContainer" class="flex flex-wrap gap-1 items-center"></div>
        <input type="text" id="ccInput"
               placeholder="Add email and press Enter..."
               class="text-xs border-none bg-transparent outline-none flex-1
                      min-w-[150px] placeholder-gray-300 py-0.5"
               onkeydown="handleCcKeydown(event)"
               onblur="commitCcInput()"
               onpaste="handleCcPaste(event)">
    </div>
</div>
@endif
```

---

## Implementasi JavaScript (EcoSystem — dapat diadaptasi ke Jarvies)

### State & Inisialisasi

```js
// Inisialisasi dari data ticket (PHP → JS)
// Di EcoSystem (Blade):
let ccEmails = @json(
    collect($ticket->cc_emails ?? [])
        ->map(fn($c) => is_array($c) ? ($c['address'] ?? '') : (string)$c)
        ->filter()
        ->values()
);

// Di Jarvies (dari API response ticket):
// const ticket = await fetch('/api/tickets/{id}');
// let ccEmails = (ticket.cc_emails ?? []).map(c =>
//     typeof c === 'string' ? c : (c?.address ?? '')
// ).filter(Boolean);
```

### Render Tags

```js
function renderCcTags() {
    const container = document.getElementById('ccTagsContainer');
    if (!container) return;
    container.innerHTML = ccEmails.map((email, i) =>
        `<span class="inline-flex items-center gap-1 bg-blue-50 border border-blue-200
                      text-blue-700 text-[11px] rounded-full px-2 py-0.5 max-w-[200px]">
            <span class="truncate">${escHtml(email)}</span>
            <button type="button" onclick="removeCcTag(${i})"
                    class="text-blue-300 hover:text-red-500 transition-colors
                           flex-shrink-0 leading-none ml-0.5">&times;</button>
        </span>`
    ).join('');
}

function removeCcTag(index) {
    ccEmails.splice(index, 1);
    renderCcTags();
}
```

### Input Handling

```js
function handleCcKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        commitCcInput();
    } else if (e.key === 'Backspace' && e.target.value === '' && ccEmails.length > 0) {
        // Backspace saat input kosong → hapus tag terakhir
        ccEmails.pop();
        renderCcTags();
    }
}

function commitCcInput() {
    const input = document.getElementById('ccInput');
    if (!input) return;
    // Pisahkan berdasarkan koma, titik koma, atau spasi (paste banyak sekaligus)
    const parts = input.value.split(/[,;\s]+/).map(s => s.trim()).filter(Boolean);
    let added = false;
    for (const email of parts) {
        if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && !ccEmails.includes(email)) {
            ccEmails.push(email);
            added = true;
        }
    }
    if (added) renderCcTags();
    input.value = '';
}

function handleCcPaste(e) {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text');
    const input = document.getElementById('ccInput');
    if (input) { input.value = text; commitCcInput(); }
}
```

### Kirim CC bersama Reply

```js
async function sendReply(messageType) {
    // ...

    if (hasFiles) {
        // multipart/form-data
        const formData = new FormData();
        formData.append('message_body', htmlContent);
        formData.append('message_type', messageType);
        formData.append('cc_emails', JSON.stringify(ccEmails)); // ← tambahkan ini
        // ...
    } else {
        // JSON
        requestBody = JSON.stringify({
            message_body: htmlContent,
            message_type: messageType,
            cc_emails: ccEmails, // ← tambahkan ini
            // ...
        });
    }
}
```

Panggil `renderCcTags()` saat halaman pertama kali dimuat:
```js
document.addEventListener('DOMContentLoaded', () => {
    renderCcTags();
    // ... inisialisasi lainnya
});
```

---

## API Endpoint — Employee Reply (EcoSystem internal)

```
POST /api/tickets/{ticketId}/messages
```

**Request Body (JSON):**
```json
{
    "message_body": "<p>Isi pesan HTML</p>",
    "message_type": "reply",
    "cc_emails": ["cc1@email.com", "cc2@email.com"]
}
```

**Request Body (multipart/form-data — jika ada attachment):**
```
message_body   = <html>
message_type   = reply
cc_emails      = ["cc1@email.com","cc2@email.com"]   ← JSON string
attachments[]  = <file>
```

**Perilaku backend:**
1. Parse `cc_emails` dari request (bisa JSON string atau array)
2. Update `ticket.cc_emails` dengan nilai baru (persistent untuk reply berikutnya)
3. Gunakan CC tersebut saat kirim email via Graph API
4. Response: `{ "success": true, "data": { ... } }`

---

## API Endpoint — Customer Reply (Jarvies → EcoSystem)

```
POST /api/tickets/{ticketId}/customer-reply
```

**Request Body:**
```json
{
    "message_body": "<p>Balasan customer</p>",
    "sender_name": "Nama Customer",
    "sender_email": "customer@email.com",
    "skip_relay": false,
    "channel": "email"
}
```

> **Catatan:** Untuk customer reply, CC tidak perlu dikirim dari Jarvies.
> EcoSystem otomatis menggunakan `ticket.cc_emails` yang tersimpan saat mengirim relay email.

---

## API Response — GET Messages

```
GET /api/tickets/{ticketId}/messages
```

Setiap message dalam response menyertakan `cc_emails`:

```json
{
    "id": 123,
    "sender_type": "customer",
    "sender_name": "John Doe",
    "message_body": "...",
    "channel": "email",
    "cc_emails": [
        "cc1@email.com",
        "cc2@email.com"
    ],
    "created_at": "2026-04-20T10:00:00Z"
}
```

`cc_emails` selalu array (sudah dinormalisasi di backend). Jika kosong, nilai adalah `[]`.

---

## Tampilan CC pada Pesan (Message Bubble)

Setiap pesan yang memiliki CC ditampilkan badge kecil di bawah header pengirim.

**EcoSystem (JavaScript):**
```js
const ccList = Array.isArray(msg.cc_emails) ? msg.cc_emails
    : (typeof msg.cc_emails === 'string'
        ? (() => { try { return JSON.parse(msg.cc_emails); } catch(e) { return []; } })()
        : []);

const ccBadge = ccList.length > 0
    ? `<span class="inline-flex items-center gap-1 text-[10px] text-gray-400 mt-0.5">
        <svg ...></svg>
        <span class="font-medium text-gray-500">CC:</span>
        ${ccList.map(c => `<span>${c.name || c.address || c}</span>`).join(', ')}
       </span>`
    : '';
```

**Jarvies — perlu diimplementasikan hal yang sama:**
- Ambil `cc_emails` dari setiap message object
- Tampilkan sebagai teks kecil di bawah sender name
- Format: `CC: email1@example.com, email2@example.com`

---

## Checklist Implementasi Jarvies

### Tampilan Compose Area (ticket channel = email)

- [ ] Tampilkan **"To: {email customer}"** di atas editor (read-only, ambil dari data ticket/user login)
- [ ] Tampilkan **CC row** dengan tag pills dari `ticket.cc_emails`
- [ ] Tag pills bisa dihapus (klik ×)
- [ ] Input untuk tambah CC baru (Enter / koma untuk konfirmasi)
- [ ] Backspace saat input kosong → hapus tag terakhir
- [ ] Validasi format email sebelum menambahkan

### Saat Kirim Reply

- [ ] Sertakan `cc_emails` (array string) pada request body ke `POST /api/tickets/{id}/customer-reply`
  - **Catatan:** Endpoint `customer-reply` saat ini belum menerima `cc_emails` — koordinasikan dengan EcoSystem jika customer diizinkan mengubah CC

### Tampilan Message Bubble

- [ ] Tampilkan badge CC di bawah sender info untuk setiap message yang memiliki `cc_emails`
- [ ] Normalisasi: item CC bisa string atau `{address, name}` — gunakan `c.address ?? c`

---

## Format Data CC di Database

Kolom `cc_emails` di tabel `ticket` dan `ticket_messages` bertipe JSON, cast ke `array` di Laravel.

**Format yang disimpan:**
```json
["email1@example.com", "email2@example.com"]
```

**Format lama (dari email masuk via Graph)** — bisa berupa object, tangani di frontend:
```json
[
    { "address": "email1@example.com", "name": "Person One" },
    { "address": "email2@example.com", "name": "Person Two" }
]
```

Selalu normalize ke string email saat render:
```js
const emailStr = typeof item === 'string' ? item : (item?.address ?? '');
```
