# Timezone & Message Flow — EcoSystem ↔ Jarvies

> **Tanggal dibuat:** 15 Apr 2026  
> **Berlaku untuk:** EcoSystem (employee side) & Jarvies (customer side)  
> **Kritis:** Create Ticket, Send Message, Fetch Message dari Email

---

## 1. Prinsip Timezone yang Benar

### Masalah yang Pernah Terjadi

Email dikirim pukul **15:23 WIB**. Microsoft Graph API mengembalikan `receivedDateTime` dalam **UTC** (`08:23 UTC`). Kode lama menyimpan string UTC langsung ke DB tanpa konversi, sehingga tampil **08:23 WIB** padahal seharusnya **15:23 WIB** (selisih 7 jam = UTC offset Indonesia).

### Aturan Wajib

| Aturan | Benar | Salah |
|--------|-------|-------|
| Simpan timestamp ke DB | Carbon/string dalam **app timezone (Asia/Jakarta)** | String UTC tanpa konversi |
| Kembalikan timestamp ke API/JSON | `->toIso8601String()` → `"2026-04-15T15:23:46+07:00"` | `->toDateTimeString()` → `"2026-04-15 15:23:46"` (ambigu) |
| Parse timestamp di JavaScript | `new Date(isoString)` (selalu konsisten) | `new Date("2026-04-15 15:23:46")` (undefined behavior per browser) |
| Tampilkan di JavaScript | `toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })` | Tanpa `timeZone` option |
| Config `config/app.php` | `'timezone' => 'Asia/Jakarta'` | UTC atau tidak di-set |

### Kenapa `toDateTimeString()` Berbahaya

```js
// String tanpa timezone — TIDAK BOLEH dipakai di API response
new Date("2026-04-15 15:23:46")
// Chrome: kadang local time, kadang UTC → waktu berubah saat refresh!

// String ISO 8601 dengan offset — AMAN
new Date("2026-04-15T15:23:46+07:00")
// Selalu = 15:23 WIB, di semua browser, konsisten
```

---

## 2. Alur Create Ticket (Staging)

### 2a. Via Jarvies Web Form (tanpa email langsung)

```
Customer submit form Jarvies
    │
    ├─ [Step 4a] Jarvies langsung tulis ke DB shared
    │   StagingTicket::create([
    │     'created_at' => now(),  // Laravel app timezone = Asia/Jakarta → benar
    │     'email_message_id' => $internetMsgId,  // dari email [Menunggu Validasi]
    │   ])
    │
    └─ [Step 4b] Jarvies POST ke EcoSystem API
        POST /api/jarvies/staging-tickets
        body: { internet_message_id: "...", ... }
        
        EcoSystem StagingTicketService::createFromWeb():
          → cek dedup by email_message_id → return existing jika sudah ada
          → jika belum ada: StagingTicket::create([...])
```

**Dedup kritis (EcoSystem):** Jarvies Step 4a dan 4b keduanya menulis ke DB yang sama. EcoSystem `createFromWeb()` harus cek duplikat:

```php
// app/Services/StagingTicketService.php
if (!empty($data['internet_message_id'])) {
    $existing = StagingTicket::where('email_message_id', $data['internet_message_id'])
        ->where('status', 'unvalidated')
        ->first();
    if ($existing) return $existing; // ← kembalikan yang lama, jangan buat baru
}
```

### 2b. Via Email Langsung ke Helpdesk (tanpa Jarvies)

```
Customer kirim email langsung → M365 inbox Raditya
    │
    └─ EcoSystem scheduler processInbox() (tiap ~1 menit)
        │
        ├─ Ambil receivedDateTime dari Graph API (format: UTC)
        │   $receivedAt = Carbon::parse($msg['receivedDateTime'])->utc()
        │                  // → Carbon UTC: "2026-04-15T08:23:46Z" = 15:23 WIB
        │
        └─ StagingTicketService::createFromEmail([..., 'received_at' => $receivedAt])
            │
            └─ KONVERSI ke app timezone sebelum simpan:
               $appTz = config('app.timezone', 'Asia/Jakarta')
               $localTime = $receivedAt->copy()->setTimezone($appTz)
               // → "2026-04-15 15:23:46" (WIB) → tampil benar di UI
```

**PENTING:** `received_at` dari Graph API selalu UTC. **Jangan** simpan langsung sebagai string — selalu konversi ke app timezone dulu.

```php
// SALAH ❌ — menyimpan UTC string, dibaca sebagai WIB → 7 jam meleset
DB::table('staging_tickets')->update(['created_at' => $receivedAt->toDateTimeString()]);
//   "2026-04-15 08:23:46" disimpan → dibaca sebagai 08:23 WIB ← SALAH

// BENAR ✅ — konversi ke WIB dulu
$localTime = $receivedAt->copy()->setTimezone('Asia/Jakarta')->toDateTimeString();
DB::table('staging_tickets')->update(['created_at' => $localTime]);
//   "2026-04-15 15:23:46" disimpan → dibaca sebagai 15:23 WIB ← BENAR
```

---

## 3. Alur Send Message (Agent Reply dari EcoSystem)

### 3a. Email-first Architecture

```
Agent ketik balasan di EcoSystem chat → klik "Send via Email"
    │
    └─ TicketMessageController::sendEmailThenSave()
        │
        ├─ 1. Kirim email via Graph API
        │      sendTicketReply(toEmail, subject, bodyHtml, inReplyTo, ...)
        │      → Graph: createReply dari email terakhir (In-Reply-To otomatis)
        │      → Graph returns: internet_message_id, conversation_id
        │
        ├─ 2. Simpan TicketMessage SETELAH email berhasil
        │      TicketMessage::create([
        │        'channel'          => 'email',
        │        'email_message_id' => $internetMsgId,  // dari Sent Items
        │        'created_at'       => now(),  // app timezone Asia/Jakarta = WIB
        │      ])
        │
        └─ 3. Simpan TicketAttachment dengan graph_message_id dari Sent Items
               (bukan draft ID — draft ID berubah setelah /send)
```

### 3b. Format Email Body

Hanya konten pesan + tanda tangan kecil, **tanpa** branded box HTML:

```php
// SEKARANG (benar) ✅
return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;...">
    {$body}
    <p style="border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;">
        Sent by <strong>{$agent}</strong> — PT Eclectic Consulting Yogyakarta<br>
        Ticket: <strong>#{$ticketNum}</strong>
    </p>
</div>
HTML;
```

### 3c. Threading Email (In-Reply-To)

```
sendTicketReply($to, $subject, $body, $inReplyTo, $files, $ccList, $noRePrefix, $threadId)
    │
    ├─ Cari original message di Graph by $inReplyTo (internetMessageId)
    │   Cari di: Inbox → SentItems → global /messages
    │
    ├─ createReply via Graph → Exchange auto-set In-Reply-To + References headers
    │   (JANGAN set In-Reply-To manual di internetMessageHeaders → Graph MENOLAK)
    │
    ├─ Patch: subject, body, toRecipients, ccRecipients
    │
    └─ /send → email terkirim dalam thread yang benar
```

**KRITIS:** Graph API melarang set header standard (`In-Reply-To`, `References`) via `internetMessageHeaders`. Hanya header dengan prefix `x-` atau `X-` yang diizinkan. Gunakan `createReply` endpoint untuk threading yang benar.

---

## 4. Alur Fetch Message dari Email (Inbox → Chat)

### 4a. processInbox Flow

```
EcoSystem scheduler (tiap ~1 menit) → EmailController::processInbox()
    │
    ├─ Fetch unread messages dari M365 inbox
    │   $select = 'id,subject,from,ccRecipients,receivedDateTime,body,
    │              internetMessageId,conversationId,hasAttachments,
    │              internetMessageHeaders'   ← wajib untuk threading
    │
    ├─ Extract SMTP headers untuk threading:
    │   In-Reply-To  → $inReplyToId
    │   References   → $referencesIds[]
    │
    ├─ 5 strategi matching ke ticket (urutan prioritas):
    │   1. conversationId  → ticket.email_thread_id
    │   2. In-Reply-To     → ticket_messages.email_message_id  ← KRITIS untuk Gmail reply
    │   3. References      → ticket_messages.email_message_id (loop dari terbaru)
    │   4. internetMessageId → ticket_messages.email_message_id (dedup check)
    │   5. conversationId  → staging_tickets.email_thread_id (approved staging)
    │
    ├─ [Ticket ditemukan] → Tambah sebagai TicketMessage
    │   - cc_emails: kirim PHP array (bukan JSON string) ke TicketMessage::create()
    │   - created_at: konversi UTC → Asia/Jakarta sebelum raw DB update
    │
    └─ [Ticket tidak ditemukan] → Buat StagingTicket baru
        - received_at: Carbon UTC → dikonversi ke Asia/Jakarta di createFromEmail()
```

### 4b. Kenapa In-Reply-To Wajib Ada di $select

`conversationId` Exchange TIDAK RELIABLE lintas email system:
- Email dikirim dari M365 `conversationId_A`
- Customer reply dari Gmail → M365 assigns **`conversationId_B`** (berbeda!)
- Matching via `conversationId` gagal → email masuk sebagai staging baru ← BUG LAMA

Solusi: `In-Reply-To` SMTP header berisi `internetMessageId` email sebelumnya — bersifat globally unique dan konsisten di semua email client (Gmail, Yahoo, Outlook, dll).

```php
// Tambahkan internetMessageHeaders ke $select di Graph query
$select = 'id,subject,from,ccRecipients,receivedDateTime,body,
           internetMessageId,conversationId,hasAttachments,
           internetMessageHeaders';  // ← WAJIB
```

### 4c. cc_emails — Jangan Double-Encode

Model `TicketMessage` punya `'cc_emails' => 'array'` cast. Eloquent handles encode/decode otomatis:

```php
// SALAH ❌ — double-encode: array → json_encode → string → Eloquent encode lagi
$ccJson = json_encode($ccEmails);
TicketMessage::create(['cc_emails' => $ccJson]);
// DB: "\"[{\\\"name\\\":\\\"..\\\"}]\"" (double-encoded)

// BENAR ✅ — kirim PHP array, Eloquent encode sekali
TicketMessage::create(['cc_emails' => $ccEmails]);
// DB: "[{\"name\":\"...\"}]" (single-encoded, benar)
```

---

## 5. API Response Format — Timestamps

### EcoSystem (StagingTicketController)

```php
// BENAR ✅ — ISO 8601 dengan offset timezone
'created_at'  => $s->created_at?->toIso8601String(),   // "2026-04-15T15:23:46+07:00"
'validated_at' => $s->validated_at?->toIso8601String(), // "2026-04-15T16:10:00+07:00"

// SALAH ❌ — string tanpa timezone (ambigu di JS)
'created_at'  => $s->created_at?->toDateTimeString(),   // "2026-04-15 15:23:46"
```

### TicketMessageController — created_at

```php
// Carbon object langsung → Laravel json_encode → ISO 8601 UTC dengan Z
'created_at' => $message->created_at,
// → "2026-04-14T05:45:11.000000Z" (UTC)
// JS: new Date("...Z") dengan timeZone:'Asia/Jakarta' → 12:45 WIB ✅
```

### JavaScript (Frontend)

```js
// ✅ BENAR — selalu konsisten
const date = new Date(msg.created_at).toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: false
}) + ' WIB';

// ❌ SALAH — bergantung timezone browser, berubah saat refresh
const date = new Date(msg.created_at).toLocaleString(); // no timeZone option
```

---

## 6. Checklist Implementasi untuk Jarvies

### A. Create Ticket (Submit ke EcoSystem)

- [ ] Kirim `internet_message_id` dari email `[Menunggu Validasi]` sebagai `internet_message_id` di POST body ke EcoSystem API
- [ ] Kirim `submitted_by_email` (email login customer) — wajib untuk approval notification
- [ ] Kirim `body` sebagai HTML isi tiket (dari Quill editor)
- [ ] Kirim `cc_emails` sebagai array (bukan JSON string)
- [ ] Simpan `email_message_id` dari email [Menunggu Validasi] ke staging (Jarvies DB) — dipakai sebagai kunci dedup

### B. Fetch Messages dari API EcoSystem

```
GET /api/tickets/{ticket_id}/messages
Authorization: Bearer {token}

Response tiap message:
{
  "id": 192,
  "sender_type": "employee",     // "customer" | "employee"
  "sender_name": "Helpdesk Support",
  "message_body": "...",
  "message_html": "...",         // null jika bukan email/internal_note
  "message_type": "reply",       // "reply" | "internal_note"
  "channel": "email",            // "email" | "web"
  "created_at": "2026-04-14T05:45:11.000000Z",  // UTC, selalu dengan Z
  "cc_emails": [...],            // array of {name, address}
  "email_message_id": "<...@...>"
}
```

**Parsing timestamp di Jarvies:**
```dart
// Flutter/Dart
final dt = DateTime.parse(message['created_at']).toLocal();
// .toLocal() → convert UTC ke device timezone (WIB jika device di Indonesia)

// Atau eksplisit:
final dt = DateTime.parse(message['created_at']);
final wib = dt.toUtc().add(Duration(hours: 7));
```

```js
// React Native / JavaScript
const date = new Date(msg.created_at).toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta', ...
});
```

### C. Send Message dari Jarvies

```
POST /api/tickets/{ticket_id}/messages
Authorization: Bearer {token}
Content-Type: application/json

{
  "message_body": "<p>isi balasan customer</p>",  // HTML dari Quill
  "message_type": "reply"
}
```

Jarvies tidak perlu kirim `created_at` — EcoSystem generates `now()` (app timezone Asia/Jakarta).

### D. Tampilkan Waktu di UI Jarvies

Selalu gunakan `created_at` dari response API (bukan generate sendiri di client). Parse sebagai UTC lalu convert ke WIB:

```dart
// Flutter
String formatWIB(String isoString) {
  final utc = DateTime.parse(isoString).toUtc();
  final wib = utc.add(const Duration(hours: 7));
  return DateFormat('dd MMM yyyy HH:mm').format(wib) + ' WIB';
}
```

---

## 7. Ringkasan File yang Diubah (EcoSystem)

| File | Perubahan | Alasan |
|------|-----------|--------|
| `app/Http/Controllers/EmailController.php` | `receivedAt` raw DB update: tambah `.setTimezone('Asia/Jakarta')` sebelum `toDateTimeString()` | UTC string langsung → 7 jam meleset |
| `app/Http/Controllers/EmailController.php` | Pass `'received_at' => $receivedAt` ke `createFromEmail()` | Staging `created_at` pakai `now()` scheduler, bukan waktu email sebenarnya |
| `app/Http/Controllers/EmailController.php` | Tambah `internetMessageHeaders` ke Graph `$select` | Threading lintas email system (Gmail reply tidak dikenali) |
| `app/Http/Controllers/EmailController.php` | 5 strategi matching (tambah In-Reply-To, References) | `conversationId` tidak reliable lintas Gmail↔M365 |
| `app/Http/Controllers/EmailController.php` | `cc_emails` di `TicketMessage::create()`: kirim `$ccEmails` array, bukan `$ccJson` string | Double-encode → JS `.map()` crash → semua pesan hilang |
| `app/Services/StagingTicketService.php` | `createFromEmail()` terima `received_at`, konversi UTC→WIB, override `created_at` | Staging tampil 7 jam lebih awal dari waktu email |
| `app/Services/StagingTicketService.php` | `createFromWeb()` tambah dedup by `email_message_id` | Jarvies Step 4a + Step 4b dua-duanya tulis DB → duplikat staging |
| `app/Http/Controllers/StagingTicketController.php` | `toDateTimeString()` → `toIso8601String()` di API response | Waktu berubah saat refresh (JS ambiguous parsing) |
| `app/Http/Controllers/TicketMessageController.php` | `cc_emails` normalisasi: handle string (double-encoded lama) | Backward compat untuk data lama di DB |
| `app/Http/Controllers/StagingTicketController.php` | `sendApprovalNotification`: `forceNewDraft=false` | Graph menolak `In-Reply-To` di `internetMessageHeaders` → 500 error |
| `app/Http/Controllers/TicketMessageController.php` | `buildEmailHtml()` / `buildCustomerRelayHtml()` disederhanakan | Hapus branded box, tampilkan teks langsung seperti di website |
| `resources/views/ticket/show.blade.php` | `trimQuillHtml()` sebelum submit | Enter kosong di Quill → `<p><br></p>` → whitespace besar di pesan |
| `resources/views/staging/rejected.blade.php` | Tambah email body iframe di modal rejected | Modal hanya tampilkan rejection reason, tidak ada body email |

---

## 8. Konfigurasi Wajib

### EcoSystem (`config/app.php`)
```php
'timezone' => 'Asia/Jakarta',
```

### Jarvies (`config/app.php` atau `.env`)
```php
'timezone' => 'Asia/Jakarta',
// atau
APP_TIMEZONE=Asia/Jakarta
```

### Database MySQL
MySQL menyimpan DATETIME tanpa timezone info. Laravel Eloquent menggunakan `config('app.timezone')` untuk membaca/menulis. Pastikan semua query raw (`DB::table()`) menggunakan string dalam app timezone, **bukan UTC**.

---

*Dibuat berdasarkan debugging sesi Apr 2026. Untuk pertanyaan teknis, lihat file sumber di path yang tercantum di tabel Section 7.*
