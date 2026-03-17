# Ticket Chat UI — Dokumentasi Styling & Implementasi

> Dokumentasi lengkap tampilan thread percakapan tiket di EcoSystem.
> File referensi utama: `resources/views/ticket/show.blade.php`
> Dibuat: 2026-03-17

---

## Daftar Isi

1. [Layout Keseluruhan](#1-layout-keseluruhan)
2. [Tipe Bubble Chat](#2-tipe-bubble-chat)
3. [CSS Classes & Styling](#3-css-classes--styling)
4. [Struktur HTML Bubble](#4-struktur-html-bubble)
5. [Channel Badge](#5-channel-badge)
6. [CC Badge](#6-cc-badge)
7. [Attachment Rendering](#7-attachment-rendering)
8. [Sidebar Ticket List](#8-sidebar-ticket-list)
9. [Compose Area (Quill Editor)](#9-compose-area-quill-editor)
10. [API Message Format](#10-api-message-format)
11. [Incremental Polling (Append-Only)](#11-incremental-polling-append-only)
12. [Panduan Adaptasi ke Jarvies](#12-panduan-adaptasi-ke-jarvies)

---

## 1. Layout Keseluruhan

```
┌──────────────────────────────────────────────────────────────────────┐
│  Sidebar (Ticket List)          Main Content             Properties  │
│  ┌───────────────┐   ┌─────────────────────────┐   ┌─────────────┐ │
│  │ Back to Tickets│   │ Ticket Header           │   │ Status      │ │
│  │ [Search]       │   │ (title, badges, meta)   │   │ Priority    │ │
│  │                │   ├─────────────────────────┤   │ Agent (PIC) │ │
│  │ Ticket items   │   │                         │   │ Members     │ │
│  │ (scrollable)   │   │  Messages Thread        │   │ Customer    │ │
│  │                │   │  (flex-1, scrollable)   │   │ Mandays     │ │
│  │                │   │                         │   │ ...         │ │
│  │                │   ├─────────────────────────┤   └─────────────┘ │
│  │                │   │ Compose (Quill Editor)  │                   │
│  └───────────────┘   └─────────────────────────┘                   │
└──────────────────────────────────────────────────────────────────────┘
```

**Container utama:**
```html
<div class="flex gap-6" style="height: calc(100vh - 140px); min-height: 500px;">
```

**Thread area:**
```html
<div id="messagesThread" class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
```
- `space-y-4` → jarak antar bubble 16px
- `overflow-y-auto` → scroll vertikal
- Padding: `px-6 py-4` (24px horizontal, 16px vertikal)

---

## 2. Tipe Bubble Chat

Ada **tiga tipe** bubble yang berbeda tampilan dan posisinya:

| Tipe | `sender_type` | `message_type` | Posisi | Warna Background |
|---|---|---|---|---|
| **Customer** | `customer` | `reply` | Kiri | `#f3f4f6` (gray-100) |
| **Employee** | `employee` | `reply` | Kanan | `#eff6ff` (blue-50) |
| **Internal Note** | `employee` | `internal_note` | Kiri (full width) | `#fef9c3` (yellow-100) + dashed border |

### Deteksi tipe di JavaScript

```javascript
const isEmployee     = msg.sender_type === 'employee';
const isInternalNote = msg.message_type === 'internal_note';
```

---

## 3. CSS Classes & Styling

### Bubble Base Classes

```css
/* Base — max-width agar tidak melebar penuh */
.message-bubble {
    max-width: 85%;
}

/* Customer — pojok kiri bawah sharp (arah masuk dari kiri) */
.message-bubble.customer {
    background: #f3f4f6;
    border-radius: 12px 12px 12px 4px;
}

/* Employee — pojok kanan bawah sharp (arah keluar ke kanan) */
.message-bubble.employee {
    background: #eff6ff;
    border-radius: 12px 12px 4px 12px;
}

/* Internal Note — full-width feel, yellow, dashed border */
.message-bubble.internal-note {
    background: #fef9c3;
    border: 1px dashed #f59e0b;
    border-radius: 8px;
}
```

### Message Content

```css
/* Teks biasa (web/Quill) */
.message-content p           { margin-bottom: 0.25rem; }
.message-content p:last-child { margin-bottom: 0; }
.message-content ul,
.message-content ol          { padding-left: 1.5rem; margin-bottom: 0.5rem; }
.message-content blockquote  { border-left: 3px solid #d1d5db; padding-left: 0.75rem; color: #6b7280; }
```

### Email HTML Body (Scoped)

Digunakan saat `msg.channel === 'email' && msg.message_html` — render HTML dari email mentah:

```css
/* Scoped class agar tidak bocor ke luar bubble */
.email-html-body               { word-break: break-word; }
.email-html-body p             { margin-bottom: 0.3rem; }
.email-html-body a             { color: #2563eb; text-decoration: underline; }
.email-html-body ul,
.email-html-body ol            { padding-left: 1.25rem; margin-bottom: 0.4rem; }
.email-html-body blockquote    { border-left: 3px solid #d1d5db; padding-left: 0.75rem; color: #6b7280; margin: 0.25rem 0; }
.email-html-body img           { max-width: 100%; height: auto; border-radius: 6px; }
.email-html-body table         { border-collapse: collapse; font-size: 12px; max-width: 100%; }
.email-html-body td,
.email-html-body th            { border: 1px solid #e5e7eb; padding: 4px 8px; }
```

### Channel Badges

```css
.msg-channel-badge {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 600; padding: 1px 6px;
    border-radius: 4px; vertical-align: middle;
}
.msg-channel-email { background: #dbeafe; color: #1d4ed8; } /* biru */
.msg-channel-web   { background: #f0fdf4; color: #15803d; } /* hijau */
```

---

## 4. Struktur HTML Bubble

### A. Customer Bubble (kiri)

```html
<div class="flex gap-3">
    <!-- Avatar: inisial nama, bg abu -->
    <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">
        C
    </div>
    <div>
        <!-- Header: nama, channel badge, waktu -->
        <div class="flex flex-col mb-1">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-900">Charli Palangan</span>
                <!-- channel badge (email/web) -->
                <span class="msg-channel-badge msg-channel-email">📧 Email</span>
                <span class="text-xs text-gray-400">13 Mar 2026, 05:02 (WIB)</span>
            </div>
            <!-- CC badge (jika ada) -->
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 mt-0.5">
                CC: <span>nama@example.com</span>
            </span>
        </div>
        <!-- Bubble body -->
        <div class="message-bubble customer p-3 inline-block text-left">
            <div class="message-content text-sm text-gray-700">
                Isi pesan...
            </div>
            <!-- Attachments (jika ada) -->
        </div>
    </div>
</div>
```

### B. Employee Bubble (kanan)

Perbedaan dari customer:
- Container wrapper: tambah `flex-row-reverse` → avatar di kanan
- Teks header: tambah `items-end` dan `justify-end`
- Bubble class: `employee` (background biru muda, radius pojok kanan bawah)

```html
<div class="flex gap-3 flex-row-reverse">
    <!-- Avatar: inisial, bg biru -->
    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">
        H
    </div>
    <div class="text-right">
        <div class="flex flex-col mb-1 items-end">
            <div class="flex items-center gap-2 justify-end">
                <span class="text-sm font-semibold text-gray-900">Helpdesk Support</span>
                <span class="msg-channel-badge msg-channel-email">📧 Email</span>
                <span class="text-xs text-gray-400">13 Mar 2026, 11:59 (WIB)</span>
            </div>
        </div>
        <div class="message-bubble employee p-3 inline-block text-left">
            <div class="message-content text-sm text-gray-700 email-html-body">
                <!-- HTML dari email -->
            </div>
        </div>
    </div>
</div>
```

### C. Internal Note

Tidak memiliki arah kiri/kanan — tampil full-width dengan background kuning:

```html
<div class="flex gap-3">
    <!-- Avatar: kuning/amber -->
    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-200 text-amber-800 text-xs font-bold">
        A
    </div>
    <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
            <span class="text-sm font-semibold text-gray-900">Admin</span>
            <!-- Badge khusus Internal Note -->
            <span class="text-[10px] bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded font-semibold">
                Internal Note
            </span>
            <span class="msg-channel-badge msg-channel-email">📧 Email</span>
            <span class="text-xs text-gray-400">13 Mar 2026, 11:59 (WIB)</span>
        </div>
        <!-- Bubble dashed kuning -->
        <div class="message-bubble internal-note p-3">
            <div class="message-content text-sm text-gray-700">
                Catatan internal...
            </div>
        </div>
    </div>
</div>
```

### D. Fallback Message (saat API kosong)

Jika `GET /api/tickets/{id}/messages` mengembalikan array kosong, tampilkan deskripsi tiket sebagai "Initial" message:

```html
<div class="flex gap-3">
    <div class="w-8 h-8 bg-gray-400 rounded-full ...">C</div>
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="text-sm font-semibold text-gray-900">Customer Name</span>
            <span class="text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded font-semibold">Initial</span>
            <span class="text-xs text-gray-400">Mar 13, 2026 12:45 PM</span>
        </div>
        <div class="message-bubble customer p-3 inline-block">
            <div class="message-content text-sm text-gray-700">
                Deskripsi tiket...
            </div>
        </div>
    </div>
</div>
```

---

## 5. Channel Badge

Setiap bubble memiliki badge kecil untuk menunjukkan dari mana pesan berasal:

```javascript
const channelBadge = msg.channel === 'email'
    ? `<span class="msg-channel-badge msg-channel-email">
           <svg ...><!-- envelope icon --></svg> Email
       </span>`
    : `<span class="msg-channel-badge msg-channel-web">
           <svg ...><!-- globe icon --></svg> Web
       </span>`;
```

| `channel` | Badge | Warna |
|---|---|---|
| `email` | 📧 Email | Biru (`#dbeafe` / `#1d4ed8`) |
| `web` | 🌐 Web | Hijau (`#f0fdf4` / `#15803d`) |

---

## 6. CC Badge

Ditampilkan di bawah baris nama pengirim, hanya jika `msg.cc_emails` array tidak kosong:

```javascript
const ccList  = msg.cc_emails || [];
const ccBadge = ccList.length > 0
    ? `<span class="inline-flex items-center gap-1 text-[10px] text-gray-400 mt-0.5">
           <svg ...><!-- users icon --></svg>
           <span class="font-medium text-gray-500">CC:</span>
           ${ccList.map(c => `<span title="${c.address || c}">${c.name || c.address || c}</span>`).join(', ')}
       </span>`
    : '';
```

Format `cc_emails` dari API:
```json
[
  { "name": "Budi", "address": "budi@example.com" },
  "plain@example.com"
]
```

---

## 7. Attachment Rendering

### Konten Pesan (`messageContent`)

```javascript
function messageContent(msg) {
    // Email dengan HTML body → render langsung (sudah bersih dari CID)
    if (msg.channel === 'email' && msg.message_html) {
        return `<div class="message-content text-sm text-gray-700 email-html-body">
                    ${msg.message_html}
                </div>`;
    }
    // Web/plain text
    if (!msg.message_body) return '';
    return `<div class="message-content text-sm text-gray-700">
                ${msg.message_body}
            </div>`;
}
```

### Attachment List (`renderAttachments`)

```javascript
function renderAttachments(attachments, isEmailWithHtml = false) {
    if (!attachments || attachments.length === 0) return '';

    // Inline images sudah ada di message_html → jangan render ulang sebagai thumbnail
    const inlineImgs = isEmailWithHtml
        ? []
        : attachments.filter(a => a.is_inline && a.mime_type?.startsWith('image/'));
    const files = isEmailWithHtml
        ? attachments.filter(a => !a.is_inline)
        : attachments.filter(a => !inlineImgs.includes(a));

    let html = '';

    // Inline images → thumbnail yang bisa diklik
    if (inlineImgs.length > 0) {
        html += `<div class="mt-2 flex flex-wrap gap-2">`;
        inlineImgs.forEach(img => {
            html += `<a href="${img.url}" target="_blank" title="${img.file_name}">
                <img src="${img.url}" alt="${img.file_name}"
                     class="max-h-48 max-w-xs rounded-lg border border-gray-200
                            cursor-zoom-in hover:opacity-90 transition-opacity"
                     onerror="this.style.display='none'">
            </a>`;
        });
        html += `</div>`;
    }

    // File biasa → card dengan ikon, nama, ukuran, dan link download
    if (files.length > 0) {
        html += `<div class="mt-2 space-y-1">`;
        files.forEach(file => {
            const icon = attachmentIcon(file.attachment_type, file.mime_type);
            const size = formatFileSize(file.file_size);
            const isImg = file.mime_type?.startsWith('image/');
            html += `
                <div class="flex items-center gap-2 bg-white border border-gray-200
                            rounded-lg px-3 py-2 max-w-xs">
                    <span class="text-lg flex-shrink-0">${icon}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-700 truncate">${file.file_name}</p>
                        ${size ? `<p class="text-[10px] text-gray-400">${size}</p>` : ''}
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        ${isImg ? `<a href="${file.url}" target="_blank" class="text-xs text-blue-500 hover:underline">View</a>` : ''}
                        <a href="${file.url}" download="${file.file_name}"
                           class="text-xs text-blue-500 hover:underline">Download</a>
                    </div>
                </div>`;
        });
        html += `</div>`;
    }

    return html;
}
```

### Ikon per Tipe File

```javascript
function attachmentIcon(type, mime) {
    if (mime?.startsWith('image/')) return '🖼️';
    if (type === 'pdf')             return '📄';
    if (type === 'document')        return '📝';
    if (type === 'spreadsheet')     return '📊';
    if (type === 'archive')         return '🗜️';
    return '📎';
}
```

| `attachment_type` | Ikon |
|---|---|
| image/* (mime) | 🖼️ |
| `pdf` | 📄 |
| `document` | 📝 |
| `spreadsheet` | 📊 |
| `archive` | 🗜️ |
| lainnya | 📎 |

### Format Ukuran File

```javascript
function formatFileSize(bytes) {
    if (!bytes)          return '';
    if (bytes < 1024)    return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}
```

---

## 8. Sidebar Ticket List

```css
.sidebar-ticket-item {
    display: block;
    padding: 8px 10px 8px 12px;
    border-radius: 7px;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    text-decoration: none;
    background: rgba(255,255,255,0.92);
    border: 1px solid rgba(255,255,255,0.5);
    border-left: 3px solid transparent;     /* left accent, transparent default */
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.sidebar-ticket-item:hover {
    background: rgba(255,255,255,1);
    border-color: rgba(255,255,255,0.8);
    border-left-color: #b91c1c;             /* merah saat hover */
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

.sidebar-ticket-item.active {
    background: rgba(255,255,255,1);
    border-color: rgba(255,255,255,0.9);
    border-left-color: #ffffff;             /* putih saat aktif */
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
```

### HTML Ticket Item

```javascript
// Priority dot colors
const prioColors = {
    'Very High': 'bg-purple-500',
    'High':      'bg-red-400',
    'Medium':    'bg-blue-400',
    'Low':       'bg-green-400'
};

// Template
`<a href="/ticket/${t.ticket_id}" class="sidebar-ticket-item ${isActive ? 'active' : ''}">
    <div class="flex items-center justify-between mb-0.5">
        <span class="text-xs font-semibold text-gray-800 truncate max-w-[140px]">
            ${customerName}
        </span>
        <span class="text-[10px] text-gray-400">${timeAgo}</span>  <!-- "3d", "5d" -->
    </div>
    <p class="text-[11px] text-gray-500 truncate mb-1">#${t.ticket_id} - ${shortDesc}</p>
    <div class="flex items-center gap-1.5">
        <div class="w-1.5 h-1.5 rounded-full ${prioDot}"></div>  <!-- priority dot -->
        <span class="text-[10px] text-gray-400">${t.ticket_priority || 'Medium'}</span>
    </div>
</a>`
```

---

## 9. Compose Area (Quill Editor)

### CSS Override

```css
/* Toolbar */
.ql-toolbar.ql-snow {
    border: none !important;
    border-bottom: 1px solid #e5e7eb !important;
    padding: 4px 8px !important;
    background: #f9fafb;
}

/* Editor container */
.ql-container.ql-snow { border: none !important; font-size: 13px; }
.ql-editor             { min-height: 80px; max-height: 180px; overflow-y: auto; padding: 8px 12px; }

/* Placeholder */
.ql-editor.ql-blank::before {
    font-style: normal;
    color: #9ca3af;
    font-size: 13px;
}

/* Tooltips di toolbar saat hover */
.ql-toolbar button[title]:hover::after,
.ql-toolbar .ql-picker[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: calc(100% + 5px);
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 5px;
    white-space: nowrap;
    z-index: 9999;
    pointer-events: none;
}
```

### Inisialisasi Quill

```javascript
const quillEditor = new Quill('#quillEditor', {
    theme: 'snow',
    placeholder: 'Type your reply here...',
    modules: {
        toolbar: {
            container: [
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ header: [1, 2, 3, false] }],
                ['link'],
                ['clean'],
                // 'attachment' diinject manual via JS
            ]
        }
    }
});
```

### Tombol Kirim

```html
<!-- Admin & PIC (role 1, 2) -->
<button onclick="sendReply('internal_note')"
        class="... bg-amber-500 text-white ...">
    Internal Note
</button>
<button onclick="sendReply('reply')"
        class="... bg-red-700 text-white ...">
    Send Reply
</button>

<!-- Helpdesk (role 6, 7) -->
<button onclick="sendReply('reply')"
        class="... bg-red-700 text-white ...">
    Send via Email
</button>
```

### Attachment Preview (Compose)

Sebelum kirim, file yang dipilih tampil sebagai chip kecil:

```html
<div id="attachmentPreview" style="display:none" class="mt-2 flex-wrap gap-2">
    <!-- chip: ikon + nama + tombol ✕ -->
    <div class="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1.5 text-xs max-w-[180px]">
        <span>📎</span>
        <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-700 truncate">filename.pdf</p>
            <p class="text-[10px] text-gray-400">245.3 KB</p>
        </div>
        <button onclick="removeAttachment(0)" class="text-gray-400 hover:text-red-500">✕</button>
    </div>
</div>
```

---

## 10. API Message Format

`GET /api/tickets/{ticketId}/messages` mengembalikan:

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "ticket_id": "2603-IRNA-0027",
      "sender_type": "employee",
      "sender_id": 5,
      "sender_name": "Helpdesk Support",
      "sender_email": null,
      "message": "Plain text versi pesan",
      "message_body": "<p>HTML dari Quill (web reply)</p>",
      "message_html": "<table>...</table>",
      "message_type": "reply",
      "channel": "email",
      "is_internal_note": false,
      "is_read_by_customer": false,
      "is_read_by_agent": true,
      "email_message_id": "<CABcd123@mail.gmail.com>",
      "cc_emails": [
        { "name": "Budi", "address": "budi@example.com" }
      ],
      "created_at": "2026-03-17T09:30:00.000000Z",
      "attachments": [
        {
          "id": 1,
          "file_name": "laporan.pdf",
          "file_size": 245300,
          "mime_type": "application/pdf",
          "attachment_type": "pdf",
          "is_inline": false,
          "url": "/api/attachments/1/download"
        }
      ]
    }
  ]
}
```

### Field Penting untuk Render Bubble

| Field | Digunakan Untuk |
|---|---|
| `sender_type` | Tentukan posisi (kiri/kanan) dan warna avatar |
| `message_type` | Deteksi internal note |
| `channel` | Channel badge (email/web) |
| `message_html` | Render HTML dari email |
| `message_body` | Render teks dari Quill/web |
| `cc_emails` | CC badge |
| `attachments` | Render attachment list |
| `created_at` | Timestamp WIB |
| `is_inline` | Attachment sudah ada di HTML body → skip thumbnail |

---

## 11. Incremental Polling (Append-Only)

EcoSystem menggunakan **incremental rendering** — tidak re-render seluruh thread, hanya append pesan baru:

```javascript
let lastMessageId = null;   // ID pesan terakhir yang sudah dirender

async function loadMessages() {
    const thread = document.getElementById('messagesThread');
    const response = await fetch(`/api/tickets/${ticketId}/messages`, { ... });
    const data = await response.json();

    if (lastMessageId === null) {
        // INITIAL LOAD: render semua pesan
        const messages = data.data || [];
        thread.innerHTML = '';
        if (messages.length === 0) {
            thread.innerHTML = createFallbackMessage();
        } else {
            messages.forEach(msg => {
                thread.insertAdjacentHTML('beforeend', createMessageBubble(msg));
            });
            lastMessageId = messages[messages.length - 1].id;
        }
        thread.scrollTop = thread.scrollHeight;
    } else {
        // POLLING: hanya append pesan setelah lastMessageId
        const allMessages = data.data || [];
        const newMessages = allMessages.filter(m => m.id > lastMessageId);
        if (newMessages.length > 0) {
            newMessages.forEach(msg => {
                thread.insertAdjacentHTML('beforeend', createMessageBubble(msg));
            });
            lastMessageId = newMessages[newMessages.length - 1].id;
            thread.scrollTop = thread.scrollHeight;
        }
    }
}

// Polling setiap N detik (atau dipanggil setelah sendReply)
setInterval(loadMessages, 10000);
```

**Keuntungan:** tidak ada flicker/re-render, tidak ada scroll jump, attachment tidak di-fetch ulang.

---

## 12. Panduan Adaptasi ke Jarvies

### Perbedaan Konteks

| | EcoSystem (Employee) | Jarvies (Customer) |
|---|---|---|
| Posisi Employee bubble | Kanan | Kiri (mereka adalah "lawan bicara") |
| Posisi Customer bubble | Kiri | Kanan (customer adalah "diri sendiri") |
| Internal Note | Tampil (dengan label) | **Tidak ditampilkan** |
| Tombol Send | Send Reply + Internal Note | Hanya Send Reply |
| Attachment download URL | `/api/attachments/{id}/download` | Lewat Jarvies API proxy atau EcoSystem API key |

### Konfigurasi Bubble Jarvies

```javascript
// Di Jarvies: "employee" adalah lawan bicara → tampil di kiri
// "customer" adalah diri sendiri → tampil di kanan
const isSelf     = msg.sender_type === 'customer';
const isEmployee = msg.sender_type === 'employee';

// Sama seperti EcoSystem tapi dibalik:
const bubbleClass = isSelf ? 'customer-self' : 'employee-agent';
```

**CSS tambahan yang perlu didefinisikan di Jarvies:**
```css
/* Pesan customer (diri sendiri) → kanan, warna berbeda */
.message-bubble.customer-self {
    background: #dcfce7;   /* hijau muda (bisa disesuaikan brand Jarvies) */
    border-radius: 12px 12px 4px 12px;
}

/* Pesan dari agent/helpdesk → kiri */
.message-bubble.employee-agent {
    background: #f3f4f6;
    border-radius: 12px 12px 12px 4px;
}
```

### Filter Pesan di Jarvies

```javascript
// Jangan tampilkan internal note ke customer
const messages = data.data.filter(m => !m.is_internal_note);
```

### Timestamp Format

Format yang digunakan:
```javascript
const date = new Date(msg.created_at).toLocaleString('en-GB', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: false
}) + ' (WIB)';
// Output: "13 Mar 2026, 05:02 (WIB)"
```

### Status Badge Tiket (Header)

```javascript
// Warna status untuk badge header ticket
const statusColors = {
    'open':        'bg-blue-100 text-blue-700',
    'in_progress': 'bg-yellow-100 text-yellow-700',
    'hold':        'bg-orange-100 text-orange-700',
    'cancel':      'bg-gray-100 text-gray-500',
    'closed':      'bg-green-100 text-green-700',
    'reply':       'bg-purple-100 text-purple-700',
};

// Warna ticket type badge
const typeColors = {
    'Incident':        'bg-red-100 text-red-700',
    'Service Request': 'bg-indigo-100 text-indigo-700',
    'Change Request':  'bg-amber-100 text-amber-700',
    'Consult':         'bg-teal-100 text-teal-700',
};
```

### Priority Dot (Sidebar)

```javascript
const prioColors = {
    'Very High': '#8b5cf6',  /* purple */
    'High':      '#f87171',  /* red */
    'Medium':    '#60a5fa',  /* blue */
    'Low':       '#4ade80',  /* green */
};
```
