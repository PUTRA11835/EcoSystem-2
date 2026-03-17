# Jarvies — Mandays Integration Guide

> Dokumentasi ini ditujukan untuk developer sisi **Jarvies (Customer)** agar bisa mengintegrasikan fitur Man Days Proposal.
> EcoSystem (server) sudah menyediakan semua endpoint yang dibutuhkan.
>
> Dibuat: 2026-03-17

---

## Daftar Isi

1. [Overview](#1-overview)
2. [Autentikasi](#2-autentikasi)
3. [Endpoints](#3-endpoints)
4. [Alur Lengkap](#4-alur-lengkap)
5. [Response Format](#5-response-format)
6. [UI States — Apa yang Ditampilkan per Status](#6-ui-states)
7. [Contoh Implementasi JavaScript](#7-contoh-implementasi-javascript)

---

## 1. Overview

Man Days Proposal adalah proposal biaya konsultasi yang dibuat oleh team EcoSystem (PIC + Helpdesk) dan dikirimkan ke customer melalui Jarvies.

**Alur singkat:**
```
EcoSystem (PIC) → EcoSystem (Helpdesk) → [Email ke customer] → Jarvies (Customer)
                                                                     ├─ Approve → approved
                                                                     └─ Reject  → pending_helpdesk (dikembalikan ke Helpdesk)
```

**Yang perlu dilakukan Jarvies:**
- Tampilkan proposal mandays di halaman detail ticket
- Sediakan tombol Approve dan Reject (hanya saat status `sent_to_chat`)
- Handle tampilan untuk status `approved` dan `canceled`

---

## 2. Autentikasi

Semua endpoint menggunakan **API Key** (bukan session/OAuth).

```
Header: X-Api-Key: {JARVIES_API_KEY}
```

Base URL: `{ECOSYSTEM_BASE_URL}/api/jarvies`

---

## 3. Endpoints

### 3.1 GET — Ambil Proposal Aktif

```
GET /api/jarvies/tickets/{ticketId}/mandays
```

Mengembalikan proposal mandays yang sudah dikirim ke customer (`sent_to_chat`, `approved`, atau `canceled`).
Jika proposal belum sampai ke customer, `visible: false`.

---

### 3.2 POST — Customer Approve

```
POST /api/jarvies/tickets/{ticketId}/mandays/approve
```

Tidak memerlukan request body.

---

### 3.3 POST — Customer Reject

```
POST /api/jarvies/tickets/{ticketId}/mandays/reject
```

**Request body:**
```json
{
  "reason": "Jumlah mandays terlalu tinggi untuk modul Business Consulting."
}
```

> `reason` bersifat **opsional** (nullable). Jika tidak disertakan, tetap valid.

---

## 4. Alur Lengkap

```
1. Jarvies load halaman ticket
   └─ GET /mandays
       ├─ visible: false  → sembunyikan section mandays
       └─ visible: true   → tampilkan sesuai proposal.status

2. Customer klik Approve
   └─ POST /mandays/approve
       └─ success → refresh proposal (status berubah ke 'approved')

3. Customer klik Reject
   └─ POST /mandays/reject { reason: "..." }
       └─ success → refresh proposal (panel disembunyikan, status 'pending_helpdesk')
```

---

## 5. Response Format

### 5.1 Response `GET /mandays` — tidak visible

```json
{
  "success": true,
  "visible": false,
  "mandays_proposal_status": "pic_draft",
  "proposal": null
}
```

### 5.2 Response `GET /mandays` — visible (sent_to_chat)

```json
{
  "success": true,
  "visible": true,
  "mandays_proposal_status": "sent_to_chat",
  "proposal": {
    "id": 3,
    "version": 1,
    "status": "sent_to_chat",
    "total_mandays": 8.0,
    "notes": "Catatan dari Helpdesk (opsional)",
    "customer_notes": null,
    "proposed_at": "2026-03-17T03:49:00+00:00",
    "customer_response_at": null
  },
  "activities": ["General", "CR"],
  "modules": ["Business Consulting", "FI"],
  "grid": {
    "General": { "Business Consulting": 6.0, "FI": 0 },
    "CR":      { "Business Consulting": 2.0, "FI": 0 }
  },
  "column_totals": {
    "Business Consulting": 8.0,
    "FI": 0
  }
}
```

### 5.3 Response `GET /mandays` — visible (approved)

```json
{
  "success": true,
  "visible": true,
  "mandays_proposal_status": "approved",
  "proposal": {
    "id": 3,
    "version": 1,
    "status": "approved",
    "total_mandays": 8.0,
    "notes": "Catatan dari Helpdesk",
    "customer_notes": null,
    "proposed_at": "2026-03-17T03:49:00+00:00",
    "customer_response_at": "2026-03-17T04:10:00+00:00"
  },
  "activities": ["General", "CR"],
  "modules": ["Business Consulting"],
  "grid": {
    "General": { "Business Consulting": 6.0 },
    "CR":      { "Business Consulting": 2.0 }
  },
  "column_totals": {
    "Business Consulting": 8.0
  }
}
```

### 5.4 Response `GET /mandays` — visible (canceled)

```json
{
  "success": true,
  "visible": true,
  "mandays_proposal_status": "canceled",
  "proposal": {
    "id": 3,
    "version": 1,
    "status": "canceled",
    "total_mandays": 8.0,
    ...
  }
}
```

### 5.5 Response POST approve/reject — sukses

```json
{
  "success": true,
  "message": "Proposal approved.",
  "mandays_proposal_status": "approved"
}
```

### 5.6 Response error (proposal tidak ditemukan / status tidak sesuai)

```json
{
  "success": false,
  "message": "No active proposal to approve."
}
```
HTTP status: `422`

---

## 6. UI States

Berdasarkan nilai `proposal.status` dari response GET:

### Status: `sent_to_chat`

> Customer perlu merespons.

```
┌──────────────────────────────────────────────────────┐
│  Man Days Proposal                  [Awaiting Response]│  ← badge amber
├──────────────────────────────────────────────────────┤
│  | Activity | Module A | Module B |                   │
│  | General  |   6.0    |    -     |                   │
│  | CR       |   2.0    |    -     |                   │
│  | Total    |   8.0    |    -     |                   │
│                                                       │
│  Catatan: "..."  (jika ada notes dari Helpdesk)       │
│                                                       │
│  Total Man Days: 8.0                                  │
├──────────────────────────────────────────────────────┤
│              [ Approve ]  [ Reject ]                  │
└──────────────────────────────────────────────────────┘
```

**Tombol yang tampil:** Approve + Reject

---

### Status: `approved`

> Proposal sudah disetujui. Tidak ada tombol.

```
┌──────────────────────────────────────────────────────┐
│  ✓  Approved by You                                   │  ← banner hijau
│     17 Mar 2026, 11:10 WIB                            │
├──────────────────────────────────────────────────────┤
│  [tabel — read-only]                                  │
│  Total Man Days: 8.0                                  │
└──────────────────────────────────────────────────────┘
```

**Tombol yang tampil:** Tidak ada

> Gunakan `proposal.customer_response_at` sebagai timestamp persetujuan.

---

### Status: `canceled`

> Proposal dibatalkan oleh Helpdesk.

```
┌──────────────────────────────────────────────────────┐
│  Proposal was canceled by the helpdesk.               │  ← teks abu-abu
└──────────────────────────────────────────────────────┘
```

**Tombol yang tampil:** Tidak ada

---

### Status tidak visible (`none`, `pic_draft`, `pending_helpdesk`)

> **Sembunyikan section mandays sepenuhnya.**
> Panel tidak ditampilkan sama sekali ke customer.

---

## 7. Contoh Implementasi JavaScript

### 7.1 Load Proposal

```javascript
async function loadMandaysProposal(ticketId) {
    const res = await fetch(`/api/jarvies/tickets/${ticketId}/mandays`, {
        headers: { 'X-Api-Key': API_KEY }
    });
    const data = await res.json();

    if (!data.visible) {
        hideMandaysSection();
        return;
    }

    renderMandaysSection(data);
}
```

### 7.2 Render berdasarkan status

```javascript
function renderMandaysSection(data) {
    const { proposal, activities, modules, grid, column_totals } = data;

    switch (proposal.status) {
        case 'sent_to_chat':
            renderTable(activities, modules, grid, column_totals);
            showButtons(['approve', 'reject']);
            break;

        case 'approved':
            renderTable(activities, modules, grid, column_totals);
            showApprovedBanner(proposal.customer_response_at);
            // tidak tampilkan tombol
            break;

        case 'canceled':
            showCanceledMessage();
            break;
    }
}
```

### 7.3 Tombol Approve

```javascript
async function approveMandaysProposal(ticketId) {
    const res = await fetch(`/api/jarvies/tickets/${ticketId}/mandays/approve`, {
        method: 'POST',
        headers: { 'X-Api-Key': API_KEY }
    });
    const data = await res.json();

    if (data.success) {
        // Refresh tampilan proposal
        await loadMandaysProposal(ticketId);
    } else {
        alert(data.message || 'Failed to approve.');
    }
}
```

### 7.4 Tombol Reject

```javascript
async function rejectMandaysProposal(ticketId, reason) {
    const res = await fetch(`/api/jarvies/tickets/${ticketId}/mandays/reject`, {
        method: 'POST',
        headers: {
            'X-Api-Key': API_KEY,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ reason })
    });
    const data = await res.json();

    if (data.success) {
        // Panel disembunyikan karena status berubah ke pending_helpdesk
        hideMandaysSection();
    } else {
        alert(data.message || 'Failed to reject.');
    }
}
```

### 7.5 Render tabel Activity × Module

```javascript
function renderTable(activities, modules, grid, column_totals) {
    // Header
    let headHtml = '<tr><th>Activity</th>';
    modules.forEach(m => headHtml += `<th>${m}</th>`);
    headHtml += '</tr>';

    // Body
    let bodyHtml = '';
    activities.forEach(act => {
        bodyHtml += `<tr><td>${act}</td>`;
        modules.forEach(m => {
            const val = grid[act]?.[m] || 0;
            bodyHtml += `<td>${val > 0 ? val.toFixed(1) : '-'}</td>`;
        });
        bodyHtml += '</tr>';
    });

    // Footer (totals)
    let footHtml = '<tr><td>Total</td>';
    modules.forEach(m => footHtml += `<td>${(column_totals[m] || 0).toFixed(1)}</td>`);
    footHtml += '</tr>';

    // Inject ke DOM
    document.getElementById('mandays-head').innerHTML = headHtml;
    document.getElementById('mandays-body').innerHTML = bodyHtml;
    document.getElementById('mandays-foot').innerHTML = footHtml;
}
```

---

## Ringkasan Tombol per Status

| `proposal.status` | Jarvies tampilkan | Tombol |
|---|---|---|
| `sent_to_chat` | Proposal + tabel | **Approve** + **Reject** |
| `approved` | Banner hijau + tabel (read-only) | Tidak ada |
| `canceled` | Pesan "Canceled by helpdesk" | Tidak ada |
| `none` / `pic_draft` / `pending_helpdesk` | **Disembunyikan** | — |

---

## Catatan Penting

- Setelah customer **reject**, status berubah ke `pending_helpdesk` → `visible: false` → **panel hilang** dari Jarvies. Customer tidak perlu tahu bahwa proposalnya masih diproses oleh Helpdesk.
- Setelah customer **approve**, `ticket.man_days` di EcoSystem otomatis diisi dengan `total_mandays` dari proposal.
- Field `customer_response_at` berisi timestamp kapan customer klik Approve/Reject (dalam UTC ISO 8601). Konversi ke WIB (+07:00) untuk ditampilkan ke user.
