# Implementasi Microsoft Graph Email + Attachment — Panduan Jarvies

> **Tanggal:** 2026-03-10
> **Berlaku untuk:** Tim Jarvies yang ingin mengimplementasikan pengiriman email dengan attachment via Microsoft Graph API
> **Konteks:** Dokumen ini merekap arsitektur dan pitfalls yang ditemukan saat mengimplementasikan helpdesk reply dengan attachment di EcoSystem, agar Jarvies tidak mengulang masalah yang sama.

---

## Ringkasan Masalah yang Diselesaikan

| # | Masalah | Root Cause | Solusi |
|---|---|---|---|
| 1 | Attachment tidak bisa didownload (404) | `graph_message_id` tersimpan = **draft ID** (invalid setelah dikirim) | Email-first architecture + PHP-side SentItems matching |
| 2 | Attachment tetap 404 meski message ID sudah benar | `graph_attachment_id` juga berubah saat draft → Sent Items | Fetch ulang attachment list dari Sent Items, cocokkan by filename |
| 3 | OData `$filter` pada `internetMessageId` tidak reliable | Graph tidak selalu index email baru saat OData query dijalankan | Fetch N pesan terbaru, cocokkan `internetMessageId` di PHP |
| 4 | Email dikirim ke sender (raditya) bukan ke customer | `createReply` pada Sent Items message menyetel `toRecipients` = FROM (pengirim asli) | PATCH `toRecipients` setelah `createReply`, sebelum send |

---

## Konsep Paling Penting: Draft ID vs Sent Items ID

Ini adalah sumber masalah utama. **Wajib dipahami sebelum implementasi.**

```
Alur Graph API saat kirim email dengan attachment:

1. POST /users/{sender}/messages
   → Buat draft, dapat: draftId = "AAMkADg...DRAFTS..."

2. POST /users/{sender}/messages/{draftId}/attachments
   → Upload file, dapat: attachmentId = "AAMkADg...ATTID-DRAFT..."

3. GET /users/{sender}/messages/{draftId}
   → Ambil internetMessageId SEBELUM send (ini tetap sama setelah send)
   → internetMessageId = "<JK2P305MB0050AFF356@...PROD.OUTLOOK.COM>"

4. POST /users/{sender}/messages/{draftId}/send
   → Email dikirim
   → draftId menjadi INVALID (tidak bisa diakses lagi)
   → Message berpindah ke Sent Items dengan ID BARU:
        sentMsgId     = "AAMkADg...SENTITEMS..."   ← BERBEDA dari draftId
        sentAttId     = "AAMkADg...ATTID-SENT..."  ← BERBEDA dari attachmentId

5. internetMessageId TETAP SAMA setelah send ← Ini satu-satunya konstanta
```

**Kesimpulan:**
- Jangan simpan `draftId` ke database sebagai `graph_message_id`
- Jangan simpan `attachmentId` (dari step 2) ke database sebagai `graph_attachment_id`
- Kedua ID tersebut berubah setelah `/send`

---

## Arsitektur: Email-First

Urutan yang **benar** — simpan ke DB **setelah** email terkirim dan ID Sent Items sudah didapat:

```
[SALAH — jangan lakukan ini]
1. Simpan TicketMessage ke DB
2. Kirim email → dapat draftId
3. Update TicketMessage.email_message_id
4. Simpan TicketAttachment dengan graph_message_id = draftId  ← RUSAK

[BENAR — email-first]
1. Kirim email ke Graph:
   a. Buat draft → draftId
   b. Upload attachment ke draft → draftAttId (JANGAN simpan ini)
   c. GET draft → ambil internetMessageId (simpan ini sementara)
   d. POST /send
   e. Cari sentMsgId dari SentItems by internetMessageId
   f. GET /messages/{sentMsgId}/attachments → sentAttId (by filename)
2. Simpan TicketMessage ke DB dengan:
   - email_message_id = internetMessageId
   - channel = 'email'
3. Simpan TicketAttachment ke DB dengan:
   - graph_message_id    = sentMsgId   ← Sent Items ID (bukan draft)
   - graph_attachment_id = sentAttId   ← Sent Items attachment ID (bukan draft)
```

---

## Cara Mencari Sent Items ID Setelah Send

**Masalah:** OData `$filter` pada `internetMessageId` di SentItems tidak reliable.

```
JANGAN gunakan ini (tidak reliable):
GET /users/{sender}/mailFolders/SentItems/messages
   ?$filter=internetMessageId eq '<JK2P305MB0050...>'
   → Sering return kosong meski email sudah terkirim
```

**Solusi yang benar:** Fetch N pesan terbaru dari SentItems, cocokkan `internetMessageId` di kode PHP/sisi server:

```php
// Ambil 20 pesan terbaru dari SentItems
for ($attempt = 1; $attempt <= 3; $attempt++) {
    if ($attempt > 1) sleep(1); // beri jeda untuk Graph indexing

    $result = $this->graphGet("/users/{$sender}/mailFolders/SentItems/messages", [
        '$orderby' => 'sentDateTime desc',
        '$select'  => 'id,internetMessageId',
        '$top'     => 20,
    ]);

    foreach ($result['value'] ?? [] as $msg) {
        if ($msg['internetMessageId'] === $internetMessageId) {
            $sentMsgId = $msg['id'];
            break 2; // keluar dari dua loop
        }
    }
}
```

> **Kenapa 3x retry dengan sleep(1)?**
> Microsoft Graph membutuhkan beberapa detik untuk mengindeks pesan baru ke SentItems setelah `/send`. Request langsung setelah send sering return kosong.

---

## Cara Mendapatkan Sent Items Attachment ID

Setelah `sentMsgId` ditemukan, fetch ulang daftar attachment dari Sent Items dan cocokkan berdasarkan nama file:

```php
$sentAttachments = $this->graphGet(
    "/users/{$sender}/messages/{$sentMsgId}/attachments",
    ['$select' => 'id,name']
);

$sentAttMap = [];
foreach ($sentAttachments['value'] ?? [] as $att) {
    $sentAttMap[strtolower($att['name'])] = $att['id'];
}

// Untuk setiap attachment yang di-upload ke draft:
foreach ($uploadedFiles as $file) {
    $fileName = $file->getClientOriginalName();
    $sentAttId = $sentAttMap[strtolower($fileName)] ?? null;

    // Simpan ke DB dengan sentAttId (bukan draftAttId)
    TicketAttachment::create([
        'graph_message_id'    => $sentMsgId,
        'graph_attachment_id' => $sentAttId,
        'file_name'           => $fileName,
        ...
    ]);
}
```

---

## Proxy Attachment: Auto-Recovery pada Akses Pertama

Meskipun sudah pakai email-first, ada kemungkinan edge case (mis. search Sent Items gagal, fallback ke draft ID). Implementasikan **auto-recovery** di attachment proxy:

```php
// AttachmentController::show()
public function show(int $id)
{
    $attachment = TicketAttachment::findOrFail($id);

    // 1. File lokal (fallback, bukan dari Graph)
    if (!$attachment->graph_message_id && $attachment->file_path) {
        return redirect('/storage/' . $attachment->file_path);
    }

    // 2. Validasi ada graph IDs
    if (!$attachment->graph_message_id || !$attachment->graph_attachment_id) {
        abort(404);
    }

    $response = Http::withToken($token)->get(
        "{$baseUrl}/users/{$sender}/messages/{$attachment->graph_message_id}/attachments/{$attachment->graph_attachment_id}"
    );

    // 3. Jika 404: kemungkinan masih pakai draft ID → coba cari di Sent Items
    if ($response->status() === 404 && $attachment->message_id) {
        $ticketMsg = DB::table('ticket_message')
            ->where('id', $attachment->message_id)
            ->whereNotNull('email_message_id')
            ->first();

        if ($ticketMsg?->email_message_id) {
            // Cari sentMsgId via PHP matching (bukan OData filter)
            $sentMsgId = null;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                if ($attempt > 1) sleep(1);
                $search = Http::withToken($token)->get(
                    "{$baseUrl}/users/{$sender}/mailFolders/SentItems/messages",
                    ['$orderby' => 'sentDateTime desc', '$select' => 'id,internetMessageId', '$top' => 20]
                );
                foreach ($search->json('value') ?? [] as $msg) {
                    if ($msg['internetMessageId'] === $ticketMsg->email_message_id) {
                        $sentMsgId = $msg['id'];
                        break 2;
                    }
                }
            }

            if ($sentMsgId) {
                // Ambil juga attachment ID yang benar dari Sent Items
                $sentAttId = $attachment->graph_attachment_id; // fallback
                $attList = Http::withToken($token)->get(
                    "{$baseUrl}/users/{$sender}/messages/{$sentMsgId}/attachments",
                    ['$select' => 'id,name']
                );
                foreach ($attList->json('value') ?? [] as $att) {
                    if (strtolower($att['name']) === strtolower($attachment->file_name)) {
                        $sentAttId = $att['id'];
                        break;
                    }
                }

                // Update DB — request berikutnya langsung berhasil
                $attachment->update([
                    'graph_message_id'    => $sentMsgId,
                    'graph_attachment_id' => $sentAttId,
                ]);

                // Retry dengan ID yang benar
                $response = Http::withToken($token)->get(
                    "{$baseUrl}/users/{$sender}/messages/{$sentMsgId}/attachments/{$sentAttId}"
                );
            }
        }
    }

    if (!$response->successful()) {
        abort(404, 'File tidak dapat diambil dari Microsoft Graph.');
    }

    $data     = $response->json();
    $content  = base64_decode($data['contentBytes'] ?? '');
    $mime     = $data['contentType'] ?? $attachment->mime_type ?? 'application/octet-stream';
    $filename = $data['name'] ?? $attachment->file_name ?? 'attachment';

    $disposition = $attachment->is_inline ? 'inline' : 'attachment';

    return response($content, 200)
        ->header('Content-Type', $mime)
        ->header('Content-Disposition', $disposition . '; filename="' . rawurlencode($filename) . '"')
        ->header('Content-Length', strlen($content))
        ->header('Cache-Control', 'private, max-age=3600');
}
```

---

## Fix: createReply Mengirim ke Pengirim Sendiri

Ketika menggunakan `createReply` pada message di Sent Items, Graph salah menyetel `toRecipients`:

```
Email asli: FROM raditya → TO customer
createReply pada email ini di SentItems:
  → Graph asumsikan kita "balas ke diri sendiri" (raditya)
  → toRecipients = raditya  ← SALAH
```

**Fix:** Selalu PATCH `toRecipients` setelah `createReply`, sebelum send:

```php
// Setelah createReply:
$draft = graphPost("/users/{$sender}/messages/{$originalMsgId}/createReply", []);
$draftId = $draft->json('id');

// WAJIB: patch toRecipients ke customer, bukan ke sender
graphPatch("/users/{$sender}/messages/{$draftId}", [
    'toRecipients' => [
        ['emailAddress' => ['address' => $customerEmail]]
    ],
    'body'    => ['contentType' => 'HTML', 'content' => $htmlBody],
    'subject' => $replySubject,
    // cc jika ada:
    'ccRecipients' => [
        ['emailAddress' => ['address' => $ccEmail]]
    ],
]);

// Baru send:
graphPost("/users/{$sender}/messages/{$draftId}/send", []);
```

> Ini berlaku baik untuk `createReply` dari Inbox maupun Sent Items — selalu PATCH `toRecipients`.

---

## Threading Email (In-Reply-To)

Agar balasan masuk ke thread yang sama di Gmail/Outlook:

```php
// Saat buat draft (fallback — jika createReply tidak bisa digunakan):
$payload = [
    'subject'  => "Ticket #{$ticketNumber}: {$description}",
    'body'     => ['contentType' => 'HTML', 'content' => $htmlBody],
    'toRecipients' => [['emailAddress' => ['address' => $customerEmail]]],
    'internetMessageHeaders' => [
        ['name' => 'In-Reply-To', 'value' => $inReplyToMessageId],
        ['name' => 'References',  'value' => $inReplyToMessageId],
    ],
];
```

`$inReplyToMessageId` = `email_message_id` dari pesan sebelumnya di thread (format: `<xxx@PROD.OUTLOOK.COM>`).

---

## Format Database yang Diperlukan

### Tabel `ticket_message`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `email_message_id` | varchar | RFC 2822 Message-ID (`<xxx@PROD.OUTLOOK.COM>`) — untuk In-Reply-To |
| `channel` | enum | `email` jika dikirim/diterima via email, `web` jika hanya chat |

### Tabel `ticket_attachment`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `graph_message_id` | varchar | ID message di Sent Items (bukan draft ID) |
| `graph_attachment_id` | varchar | ID attachment di Sent Items (bukan draft att ID) |
| `content_id` | varchar | CID untuk inline images (format: `xxx@domain`) |
| `is_inline` | boolean | `true` = gambar sudah ada di `message_html` (jangan render ulang) |
| `file_path` | varchar | Path lokal (untuk fallback non-Graph, mis. internal note) |

---

## Inline Images (is_inline)

Saat email dari customer masuk dengan inline images (`<img src="cid:xxx">`):

1. EcoSystem simpan attachment dengan `is_inline = true` dan `content_id = 'xxx'`
2. Ganti `cid:xxx` di `message_html` dengan URL proxy: `/attachments/{id}`
3. Saat Jarvies render `message_html` → inline images otomatis muncul via proxy

```php
// Contoh replacement CID di PHP:
foreach ($cidMap as $cid => $attachmentId) {
    $proxyUrl = route('attachments.show', $attachmentId);
    $cleanCid = trim($cid, '<>');
    $html = str_replace('cid:' . $cleanCid, $proxyUrl, $html);
    $html = str_replace('cid:<' . $cleanCid . '>', $proxyUrl, $html);
}
```

**Di UI Jarvies:**
- Jangan render attachment `is_inline: true` sebagai list file — sudah ada di HTML body
- Hanya render `is_inline: false` sebagai daftar yang bisa diunduh

---

## Alur Lengkap Email-First (Ringkasan Kode)

```php
// Di TicketMessageController — employee reply dengan attachment

private function sendEmailThenSave(Ticket $ticket, array $msgData, array $files): ?TicketMessage
{
    // 1. Kirim email via Graph (email-first)
    $result = app(EmailController::class)->sendTicketReply(
        customerEmail: $customerEmail,
        subject:       "Ticket #{$ticketNumber}: {$description}",
        body:          $htmlBody,
        inReplyTo:     $lastEmailMessageId,  // dari ticket_message sebelumnya
        files:         $files,
        ccList:        $ccList,
    );
    // $result = [
    //   'graph_message_id'    => $sentMsgId,    // Sent Items ID
    //   'internet_message_id' => $inetMsgId,    // RFC 2822 Message-ID
    //   'conversation_id'     => $convId,
    //   'attachments'         => [
    //       ['name' => 'file.pdf', 'mime' => '...', 'size' => 1024, 'graph_att_id' => $sentAttId],
    //   ],
    // ]

    // 2. Simpan message ke DB SETELAH email berhasil
    $message = TicketMessage::create([
        'channel'          => 'email',
        'email_message_id' => $result['internet_message_id'],
        ...
    ]);

    // Update email_thread_id jika belum ada
    if (!empty($result['conversation_id']) && empty($ticket->email_thread_id)) {
        $ticket->update(['email_thread_id' => $result['conversation_id']]);
    }

    // 3. Simpan attachment dengan Sent Items ID (bukan draft ID)
    $graphMessageId = $result['graph_message_id'];
    foreach ($result['attachments'] as $att) {
        if (empty($att['graph_att_id'])) continue; // skip jika upload gagal
        TicketAttachment::create([
            'graph_message_id'    => $graphMessageId,
            'graph_attachment_id' => $att['graph_att_id'],  // sudah Sent Items att ID
            'file_name'           => $att['name'],
            'mime_type'           => $att['mime'],
            'file_size'           => $att['size'],
            'is_inline'           => false,
            ...
        ]);
    }

    return $message;
}
```

---

## Checklist Implementasi

```
EMAIL-FIRST ARCHITECTURE
□ Kirim email DULU, baru simpan ke DB
□ Jangan simpan draftId sebagai graph_message_id
□ Jangan simpan draft attachmentId sebagai graph_attachment_id

CARI SENT ITEMS ID
□ Ambil internetMessageId dari draft SEBELUM send
□ Setelah send, cari sentMsgId via PHP matching (fetch top 20 SentItems, loop)
□ Retry 3x dengan sleep(1) antar attempt

CARI SENT ITEMS ATTACHMENT ID
□ Setelah sentMsgId ditemukan, fetch GET /messages/{sentMsgId}/attachments
□ Cocokkan berdasarkan nama file (case-insensitive)
□ Simpan sentAttId ke DB, bukan draftAttId

PROXY ATTACHMENT
□ Semua attachment akses via proxy route (/attachments/{id})
□ Proxy fetch dari Graph: GET /messages/{msgId}/attachments/{attId}
□ Implement auto-recovery: jika 404, cari Sent Items ID + att ID ulang
□ Update DB saat auto-recovery berhasil (agar request berikutnya langsung dapat)

THREADING
□ Simpan email_message_id (internetMessageId) di setiap ticket_message
□ Gunakan email_message_id pesan terakhir sebagai In-Reply-To untuk pesan berikutnya
□ Subject SAMA di semua email dalam satu thread

INLINE IMAGES
□ Ganti cid:xxx di message_html dengan URL proxy /attachments/{id}
□ is_inline: true → sudah ada di message_html, jangan render ulang sebagai list
□ is_inline: false → tampilkan sebagai file yang bisa diunduh

FIX: createReply bug
□ Setelah createReply, SELALU PATCH toRecipients ke customer email
□ Jangan langsung send setelah createReply tanpa patch
```

---

## Referensi File EcoSystem

| Fungsi | File | Method |
|---|---|---|
| Kirim email helpdesk reply + attachment | `app/Http/Controllers/EmailController.php` | `sendTicketReply()` |
| Email-first flow, simpan message + attachment | `app/Http/Controllers/TicketMessageController.php` | `sendEmailThenSave()` |
| Proxy download attachment dari Graph | `app/Http/Controllers/AttachmentController.php` | `show()` |
| Ganti CID reference dengan URL proxy | `app/Http/Controllers/EmailController.php` | `replaceCidReferences()` |
| Simpan attachment dari email masuk | `app/Http/Controllers/EmailController.php` | `storeEmailAttachments()` |
| Model attachment dengan `public_url` accessor | `app/Models/TicketAttachment.php` | `getPublicUrlAttribute()` |
