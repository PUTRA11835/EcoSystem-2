# Jarvies — Panduan Implementasi Submit Ticket

> **Tujuan dokumen ini:**  
> Menjelaskan apa yang perlu Jarvies kirimkan saat customer membuat tiket baru,
> agar EcoSystem dapat menampilkan body email, inline images, attachment, dan CC
> secara lengkap di modal validasi.

---

## Gambaran Umum Flow

```
Customer isi form Jarvies
        ↓
Jarvies kirim email "[Menunggu Validasi] {subject}"
via M365 (Raditya) ke customer          ← menggunakan Graph API
        ↓
Jarvies simpan internetMessageId dari email yang baru dikirim
        ↓
Jarvies POST ke EcoSystem API
/jarvies/staging-tickets  ← sertakan semua field + internet_message_id
        ↓
EcoSystem simpan staging ticket
        ↓
EcoSystem lookup email di M365 Sent Items (via internet_message_id)
EcoSystem simpan graph_message_id, channel='email', email_body_html
        ↓
Admin buka modal validasi →
  - body email tampil (termasuk inline images)
  - attachment dari Graph API tampil & bisa didownload
```

> **Penting:** Jika `internet_message_id` tidak dikirim, EcoSystem akan tetap  
> mencarinya saat tombol "Fetch Email" ditekan (scan Sent Items otomatis tiap 60 detik).  
> Namun menyertakan `internet_message_id` langsung membuat link lebih cepat dan andal.

---

## API Endpoint

```
POST /jarvies/staging-tickets
Header: X-Api-Key: {API_KEY}
Content-Type: multipart/form-data   ← WAJIB multipart, bukan application/json
```

---

## Fields yang Harus Dikirim

### Wajib

| Field | Tipe | Keterangan |
|---|---|---|
| `description` | string (max 5000) | Subject / judul tiket. **Harus sama persis dengan subject email** (tanpa prefix `[Menunggu Validasi]`) |
| `customer_id` | integer | ID customer di EcoSystem |

### Sangat Direkomendasikan

| Field | Tipe | Keterangan |
|---|---|---|
| `submitted_by_email` | string (email) | Email login customer yang submit. Digunakan untuk matching email di Sent Items |
| `body` | string (HTML) | Isi deskripsi dari Quill editor — `quill.root.innerHTML` |
| `internet_message_id` | string | `internetMessageId` dari email yang baru dikirim Jarvies ke M365. Memungkinkan EcoSystem langsung link ke email tanpa harus menunggu scan |

### Opsional

| Field | Tipe | Keterangan |
|---|---|---|
| `sender_name` | string | Nama customer yang submit |
| `ticket_priority` | string | `Very High` / `High` / `Medium` / `Low` |
| `cc_emails` | string (JSON) | JSON string array CC. Format: `[{"name":"Nama","address":"email@domain.com"}]` |
| `contact_id` | integer | ID contact person |
| `name` | string | Nama contact person |
| `no_hp` | string | Nomor HP |
| `module` | string | Modul terkait |
| `client` | string | Nama client |
| `attachments[]` | file | File attachment (PDF, gambar, dll). Max 10MB per file |

---

## Implementasi JavaScript

### Langkah 1 — Kirim email via Graph API

```javascript
// Kirim email "[Menunggu Validasi]" ke customer via M365
async function sendTicketEmail(subject, bodyHtml, toEmail, ccList = [], attachments = []) {
  const senderEmail = 'raditya@yourdomain.com'; // M365 sender

  // 1. Buat draft
  const draft = await graphClient
    .api(`/users/${senderEmail}/messages`)
    .post({
      subject: `[Menunggu Validasi] ${subject}`,
      body: {
        contentType: 'HTML',
        content: bodyHtml,
      },
      toRecipients: [
        { emailAddress: { address: toEmail } },
      ],
      ccRecipients: ccList.map(cc => ({
        emailAddress: typeof cc === 'string'
          ? { address: cc }
          : { name: cc.name, address: cc.address },
      })),
    });

  const draftId = draft.id;

  // 2. Tambahkan attachment jika ada
  for (const file of attachments) {
    const contentBytes = await fileToBase64(file); // helper baca file → base64
    await graphClient
      .api(`/users/${senderEmail}/messages/${draftId}/attachments`)
      .post({
        '@odata.type': '#microsoft.graph.fileAttachment',
        name: file.name,
        contentType: file.type || 'application/octet-stream',
        contentBytes: contentBytes,
      });
  }

  // 3. Kirim draft
  await graphClient
    .api(`/users/${senderEmail}/messages/${draftId}/send`)
    .post({});

  // 4. Ambil internetMessageId dari draft (sebelum dikirim sudah ada)
  const draftDetail = await graphClient
    .api(`/users/${senderEmail}/messages/${draftId}`)
    .select('internetMessageId')
    .get();

  return draftDetail.internetMessageId;
}

// Helper: File → base64 string
function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload  = () => resolve(reader.result.split(',')[1]); // hapus "data:...;base64,"
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}
```

> **Catatan:** Ambil `internetMessageId` SEBELUM send (dari draft), bukan sesudah.
> Setelah `/send` dipanggil, Graph tidak langsung mengembalikan data sent item —
> perlu fetch ulang ke Sent Items yang butuh waktu propagasi.

### Langkah 2 — Submit ke EcoSystem API

```javascript
async function submitTicketToEcoSystem({
  subject,
  bodyHtml,
  customerEmail,
  customerId,
  senderName,
  priority,
  ccEmails = [],       // array of {name, address} atau string[]
  extraFields = {},    // { name, no_hp, module, client, contact_id }
  attachments = [],    // File[] dari <input type="file">
  internetMessageId,   // dari langkah 1
}) {
  const formData = new FormData();

  // Wajib
  formData.append('description', subject);       // HARUS sama dengan subject email
  formData.append('customer_id', customerId);

  // Sangat direkomendasikan
  formData.append('submitted_by_email', customerEmail);
  formData.append('body', bodyHtml);
  if (internetMessageId) {
    formData.append('internet_message_id', internetMessageId);
  }

  // Opsional
  if (senderName)  formData.append('sender_name', senderName);
  if (priority)    formData.append('ticket_priority', priority);
  if (ccEmails.length > 0) {
    // Kirim sebagai JSON string
    formData.append('cc_emails', JSON.stringify(
      ccEmails.map(cc =>
        typeof cc === 'string'
          ? { address: cc }
          : { name: cc.name ?? cc.address, address: cc.address }
      )
    ));
  }

  // Extra fields
  const { name, no_hp, module, client, contact_id } = extraFields;
  if (name)       formData.append('name', name);
  if (no_hp)      formData.append('no_hp', no_hp);
  if (module)     formData.append('module', module);
  if (client)     formData.append('client', client);
  if (contact_id) formData.append('contact_id', contact_id);

  // Attachment files — HARUS dikirim dengan key 'attachments[]'
  for (const file of attachments) {
    formData.append('attachments[]', file);
  }

  const response = await fetch(`${ECOSYSTEM_BASE_URL}/jarvies/staging-tickets`, {
    method: 'POST',
    headers: {
      'X-Api-Key': ECOSYSTEM_API_KEY,
      // JANGAN set Content-Type — biarkan browser set multipart boundary otomatis
    },
    body: formData,
  });

  if (!response.ok) {
    throw new Error(`EcoSystem API error: ${response.status}`);
  }

  return await response.json(); // { success: true, id: <staging_id>, message: '...' }
}
```

### Langkah 3 — Gabungkan dalam form submit handler

```javascript
async function handleTicketSubmit(formData) {
  const {
    subject, bodyHtml, customerEmail, customerId,
    senderName, priority, ccEmails, attachmentFiles, extraFields,
  } = formData;

  try {
    // Kirim email dulu
    const internetMessageId = await sendTicketEmail(
      subject,
      buildEmailBody(subject, bodyHtml, extraFields), // lihat bagian Email Body di bawah
      customerEmail,
      ccEmails,
      attachmentFiles,
    );

    // Submit ke EcoSystem dengan internetMessageId
    const result = await submitTicketToEcoSystem({
      subject,
      bodyHtml,
      customerEmail,
      customerId,
      senderName,
      priority,
      ccEmails,
      extraFields,
      attachments: attachmentFiles,
      internetMessageId,
    });

    return result; // { success: true, id: stagingId }

  } catch (err) {
    console.error('Ticket submission failed:', err);
    throw err;
  }
}
```

---

## Format Email Body (`buildEmailBody`)

Email yang dikirim Jarvies sebaiknya menggunakan HTML bersih agar ditampilkan
dengan baik di modal validasi EcoSystem.

```javascript
function buildEmailBody(subject, bodyHtml, extra = {}) {
  const rows = [
    extra.no_hp  ? `<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555">Phone</td><td>: ${extra.no_hp}</td></tr>` : '',
    extra.module ? `<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555">Module</td><td>: ${extra.module}</td></tr>` : '',
    extra.client ? `<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555">Client</td><td>: ${extra.client}</td></tr>` : '',
  ].filter(Boolean).join('');

  const metaTable = rows
    ? `<table style="border-collapse:collapse;margin-bottom:16px">${rows}</table>`
    : '';

  const bodySection = bodyHtml
    ? `<div style="margin-bottom:16px">
         <strong>Description:</strong>
         <div style="margin-top:8px;padding:12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px">
           ${bodyHtml}
         </div>
       </div>`
    : '';

  return `
    <p>[Tiket baru dari Direktur via Jarvies]</p>
    ${metaTable}
    ${bodySection}
    <br>
    <img src="https://your-jarvies-domain.com/logo.png" alt="Jarvies Portal System" width="160">
  `;
}
```

> **Inline images di body:** Jika `bodyHtml` dari Quill mengandung gambar base64
> (`<img src="data:image/...;base64,...">`), Graph API akan mengonversinya ke
> `cid:` references secara otomatis saat dilampirkan sebagai file attachment
> dalam proses `sendTicketReply`. EcoSystem akan me-resolve CID ini saat
> membuka modal validasi via endpoint `previewBody`.

---

## Aturan Penting

### ✅ Subject email HARUS sama dengan `description`

```
Email subject : "[Menunggu Validasi] ISU TCODE VL03"
                 ↑ prefix          ↑ ini harus sama
API description: "ISU TCODE VL03"
```

EcoSystem mencocokkan staging ke email menggunakan `LOWER(description) = LOWER(clean_subject)`.
Jika berbeda, scan Sent Items tidak akan menemukan pasangannya.

### ✅ Request HARUS `multipart/form-data`

Jangan gunakan `JSON.stringify` untuk body request jika ada file attachment.
Gunakan `FormData` dan biarkan browser mengatur `Content-Type` boundary.

### ✅ CC dikirim sebagai JSON string

```javascript
// Benar
formData.append('cc_emails', JSON.stringify([
  { name: 'Nama CC', address: 'cc@domain.com' },
  { address: 'cc2@domain.com' },
]));

// Salah — tidak akan terbaca
formData.append('cc_emails', 'cc@domain.com');
```

### ✅ `attachments[]` bukan `attachments`

```javascript
// Benar
files.forEach(f => formData.append('attachments[]', f));

// Salah — tidak dikenali sebagai array oleh Laravel
files.forEach(f => formData.append('attachments', f));
```

### ⚠️ Jangan kirim `Content-Type` header manual

```javascript
// Benar — browser otomatis set multipart/form-data dengan boundary yang benar
fetch(url, { method: 'POST', headers: { 'X-Api-Key': key }, body: formData });

// Salah — boundary tidak akan terbentuk dengan benar
fetch(url, { method: 'POST', headers: { 'Content-Type': 'multipart/form-data' }, body: formData });
```

---

## Apa yang Terjadi Jika `internet_message_id` Tidak Dikirim

EcoSystem akan tetap bisa menemukan email melalui **scan Sent Items otomatis**
yang berjalan setiap 60 detik saat halaman validasi dibuka.

Scan mencocokkan berdasarkan:
1. `LOWER(staging.description)` == subject email (tanpa prefix)
2. `staging.submitted_by_email` == `toRecipients[0]` email (jika tersedia)
3. Email dikirim dalam 7 hari terakhir

Untuk hasil terbaik, tetap sertakan `internet_message_id`.

---

## Contoh Response Sukses

```json
{
  "success": true,
  "id": 97,
  "message": "Staging ticket created successfully"
}
```

## Contoh Response Error

```json
{
  "success": false,
  "message": "Failed to submit ticket: The description field is required."
}
```

---

## Checklist Implementasi Jarvies

- [ ] Request menggunakan `multipart/form-data` (bukan JSON)
- [ ] `description` dikirim dan nilainya sama dengan subject email (tanpa prefix)
- [ ] `customer_id` dikirim
- [ ] `submitted_by_email` diisi dengan email login customer
- [ ] `body` berisi HTML dari Quill editor (`quill.root.innerHTML`)
- [ ] Email dikirim via Graph API (draft → attach → send) sebelum memanggil EcoSystem API
- [ ] `internet_message_id` diambil dari draft sebelum send, dan disertakan di API call
- [ ] `cc_emails` dikirim sebagai JSON string jika ada CC
- [ ] File attachment dikirim dengan key `attachments[]`
- [ ] `Content-Type` header tidak di-set manual (biarkan browser)
