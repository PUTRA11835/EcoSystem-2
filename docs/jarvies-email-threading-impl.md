# Panduan Implementasi Email Threading — Jarvies

> **Tanggal:** 2026-03-09
> **Tujuan:** Balasan dari Jarvies harus masuk ke thread email yang sama (bukan subject baru),
> persis seperti yang dilakukan EcoSystem.

---

## Masalah yang Ingin Diselesaikan

Saat ini reply dari Jarvies selalu membuat **subject/thread baru** di Gmail/Outlook customer.
EcoSystem sudah berhasil menjaga semua email dalam satu thread. Berikut cara yang sama
perlu diterapkan di Jarvies.

---

## Konsep Inti: Apa yang Membuat Email Masuk Thread yang Sama

Email masuk ke thread yang sama jika memenuhi **salah satu atau kedua** kondisi berikut:

1. **Header `In-Reply-To`** berisi `Message-ID` email sebelumnya dalam thread
2. **Header `References`** berisi daftar `Message-ID` dari semua email dalam thread

Email client (Gmail, Outlook) menggunakan kedua header ini untuk mengelompokkan email
ke dalam satu percakapan, **terlepas dari subject-nya**.

Microsoft Graph API (M365) menangani ini otomatis melalui endpoint `createReply` —
Jarvies hanya perlu tahu `Message-ID` (internetMessageId) dari pesan terakhir.

---

## Data yang Dibutuhkan dari EcoSystem API

### 1. `GET /api/tickets/{id}`

```json
{
  "ticket_id": 9,
  "ticket_number": "2603-IRNA-0007",
  "description": "Test Reply Last",
  "channel": "email",
  "email_thread_id": "AAQkADk5YTRlNmY5..."
}
```

| Field | Kegunaan |
|---|---|
| `ticket_number` | Untuk format subject: `"Ticket #2603-IRNA-0007: Test Reply Last"` |
| `description` | Untuk format subject (bagian setelah `#number:`) |
| `email_thread_id` | Penanda apakah tiket punya thread email aktif. Jika `null` → belum ada thread, jangan kirim email |

---

### 2. `GET /api/tickets/{id}/messages`

```json
[
  {
    "id": 10,
    "sender_type": "customer",
    "channel": "email",
    "email_message_id": "<CABxx123@mail.gmail.com>",
    "created_at": "2026-03-06T11:13:00Z"
  },
  {
    "id": 11,
    "sender_type": "employee",
    "channel": "email",
    "email_message_id": "<SY4PR01MB9876abc@SY4PR01MB.ausprd01.prod.exchangelabs.com>",
    "created_at": "2026-03-06T11:13:45Z"
  }
]
```

**Yang dibutuhkan: `email_message_id` dari pesan `channel = "email"` yang paling terakhir.**

```js
// Ambil email_message_id terakhir dari messages (filter channel=email, sort by created_at)
const lastEmailMessage = messages
  .filter(m => m.channel === 'email' && m.email_message_id)
  .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];

const inReplyTo = lastEmailMessage?.email_message_id ?? null;
```

---

## Format Subject yang Benar

```
Ticket #2603-IRNA-0007: Test Reply Last
```

**Aturan:**
- Awali dengan `Ticket #` + `ticket_number`
- Disambung `: ` + `description` (maksimal 80 karakter)
- **Tidak ada** prefix `Re:` — EcoSystem sudah menetapkan format ini sebagai subject standar
- Subject yang sama digunakan di semua email dalam thread ini

```js
const subject = `Ticket #${ticket.ticket_number}: ${ticket.description?.substring(0, 80) ?? ''}`;
```

---

## Cara Kirim Email Reply yang Masuk Thread yang Sama

### Opsi A — Jarvies punya OAuth email customer (DIREKOMENDASIKAN)

Jika customer sudah connect akun emailnya di Jarvies (OAuth Google/Microsoft):

```
FROM : customer's email (via OAuth)
TO   : helpdesk@eclectic.co.id  (MS_SENDER_EMAIL dari config)
SUBJECT: Ticket #2603-IRNA-0007: Test Reply Last
HEADER In-Reply-To: <email_message_id dari pesan terakhir>
HEADER References : <email_message_id dari pesan terakhir>
```

**Dengan OAuth Google (Gmail API):**

```js
// Contoh menggunakan Gmail API (Node.js / sendEmailWithHeaders)
const raw = createMimeMessage({
  from: customerOAuthEmail,
  to: HELPDESK_EMAIL,   // env: ECOSYSTEM_HELPDESK_EMAIL
  subject: subject,
  body: messageBody,
  headers: {
    'In-Reply-To': inReplyTo,
    'References': inReplyTo,
  }
});

await gmail.users.messages.send({
  userId: 'me',
  requestBody: { raw: base64url(raw) }
});
```

**Dengan OAuth Microsoft (Graph API):**

```js
// Cari message di Graph berdasarkan internetMessageId
const searchResult = await graph.get(
  `/users/${customerEmail}/messages?$filter=internetMessageId eq '${inReplyTo}'&$select=id&$top=1`
);
const originalMsgId = searchResult.value[0]?.id;

if (originalMsgId) {
  // createReply → Graph otomatis set In-Reply-To + References
  const draft = await graph.post(
    `/users/${customerEmail}/messages/${originalMsgId}/createReply`, {}
  );

  // Update body + subject
  await graph.patch(`/users/${customerEmail}/messages/${draft.id}`, {
    subject: subject,
    body: { contentType: 'HTML', content: messageBodyHtml }
  });

  // Send
  await graph.post(`/users/${customerEmail}/messages/${draft.id}/send`, {});
}
```

**Setelah berhasil kirim, panggil EcoSystem untuk simpan ke DB:**

```js
await ecosystemApi.post(`/api/tickets/${ticketId}/customer-reply`, {
  message_body:  messageBodyHtml,
  sender_name:   currentUser.name,      // nama individual, bukan perusahaan
  sender_email:  customerOAuthEmail,
  customer_id:   currentUser.customer_id,
  skip_relay:    true,    // Jarvies sudah kirim email sendiri
  channel:       'email', // ditandai sebagai email channel
});
```

---

### Opsi B — Customer tidak connect OAuth email

Jarvies tidak bisa kirim email atas nama customer. Serahkan ke EcoSystem untuk relay:

```js
await ecosystemApi.post(`/api/tickets/${ticketId}/customer-reply`, {
  message_body:  messageBodyHtml,
  sender_name:   currentUser.name,
  sender_email:  currentUser.email,
  customer_id:   currentUser.customer_id,
  skip_relay:    false,   // EcoSystem akan kirim relay dari helpdesk M365
  channel:       'web',
});
```

EcoSystem akan otomatis kirim relay email dari helpdesk M365 ke customer dalam thread yang sama.

---

## Helpdesk Email Address

Email tujuan untuk Opsi A (TO field) adalah alamat inbox helpdesk M365.
Simpan sebagai environment variable di Jarvies:

```env
ECOSYSTEM_HELPDESK_EMAIL=raditya.budi@eclectic.co.id
```

> Konfirmasi nilai ini ke tim EcoSystem / lihat di `.env` EcoSystem: `MS_SENDER_EMAIL`

---

## Alur Lengkap dengan Kondisi OAuth

```
Customer klik "Send Reply" di Jarvies
    ↓
Cek apakah customer sudah connect OAuth email
    │
    ├─ Ada OAuth (Google/Microsoft)
    │   ├─ GET /api/tickets/{id}/messages
    │   │   → ambil email_message_id terakhir (channel=email)
    │   ├─ Kirim email via OAuth:
    │   │     FROM: customer OAuth email
    │   │     TO:   helpdesk M365 email
    │   │     SUBJECT: "Ticket #XXXX: description"
    │   │     In-Reply-To: {email_message_id terakhir}
    │   └─ POST /api/tickets/{id}/customer-reply
    │         { skip_relay: true, channel: "email" }
    │
    └─ Tidak ada OAuth
        └─ POST /api/tickets/{id}/customer-reply
              { skip_relay: false, channel: "web" }
              → EcoSystem relay email dari helpdesk M365
```

---

## Checklist Implementasi

```
PERSIAPAN
□ Simpan ECOSYSTEM_HELPDESK_EMAIL di .env Jarvies
□ Pastikan GET /api/tickets/{id} mengembalikan email_thread_id
□ Pastikan GET /api/tickets/{id}/messages mengembalikan email_message_id per pesan

FORMAT SUBJECT
□ Gunakan format: "Ticket #XXXX: description" (maks 80 karakter description)
□ Tidak ada prefix "Re:"

OPSI A — OAUTH EMAIL
□ Ambil email_message_id terakhir dari messages (filter channel=email)
□ Kirim email dengan header In-Reply-To = email_message_id terakhir
□ Gunakan Gmail API / Microsoft Graph /createReply endpoint
□ Panggil customer-reply API dengan skip_relay: true, channel: "email"

OPSI B — NON-OAUTH
□ Panggil customer-reply API dengan skip_relay: false, channel: "web"
□ EcoSystem handle relay otomatis

VALIDASI
□ Cek email_thread_id tidak null sebelum kirim — jika null, skip email (hanya simpan ke DB)
□ Jika email_message_id tidak ada (tidak ada pesan email sebelumnya), tetap kirim
  email tapi tanpa In-Reply-To (akan buat thread baru — normal untuk tiket baru)
```

---

## Contoh Respons API yang Dibutuhkan (Setelah Update EcoSystem)

### `GET /api/tickets/9` — field baru

```json
{
  "ticket_id": 9,
  "ticket_number": "2603-IRNA-0007",
  "description": "Test Reply Last",
  "channel": "email",
  "email_thread_id": "AAQkADk5YTRlNmY5LWUyODct..."
}
```

### `GET /api/tickets/9/messages` — field baru per message

```json
[
  {
    "id": 11,
    "sender_type": "employee",
    "sender_name": "Helpdesk Support",
    "channel": "email",
    "email_message_id": "<SY4PR01MB9876abc@SY4PR01MB.ausprd01.prod.exchangelabs.com>",
    "message_body": "Baik akan disampaikan...",
    "created_at": "2026-03-06T11:13:45Z"
  }
]
```

---

## Referensi

| Topik | File |
|---|---|
| Alur threading lengkap EcoSystem↔Jarvies | `docs/email-thread-flow.md` |
| Endpoint customer-reply | `app/Http/Controllers/TicketMessageController.php` → `customerReply()` |
| Cara EcoSystem kirim reply (referensi) | `app/Http/Controllers/EmailController.php` → `sendTicketReply()` |
| Field `email_message_id` di messages API | `app/Http/Controllers/TicketMessageController.php` → `index()` |
| Field `email_thread_id` di ticket API | `app/Http/Controllers/TicketController.php` → `index()`/`show()` |
