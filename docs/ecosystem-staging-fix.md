# EcoSystem — Fix: Pesan Pertama Customer Hilang Saat Approve Staging

> **Konteks:** JARVIES (customer side) menyimpan tiket baru ke `staging_tickets` dulu.
> EcoSystem (employee side) yang approve/reject staging tersebut.
> **Bug:** Pesan pertama customer (`body`) hilang saat staging di-approve.

---

## 3 JENIS ASAL TICKET — Penting Dipahami

| # | Asal | Masuk Staging? | Masuk Ticket Langsung? | Notes |
|---|---|---|---|---|
| 1 | Web form (tanpa OAuth email) | ✅ Ya | ❌ Tidak | Butuh approve EcoSystem |
| 2 | Web form (dengan OAuth email) | ✅ Ya | ❌ Tidak | Email prefix `[PENDING]`, update email_thread_id di staging |
| 3 | Email langsung ke helpdesk | ✅ Ya | ❌ Tidak | processInbox() buat staging (channel: email) |

**Semua jalur kini masuk staging dulu.** EcoSystem approve → buat ticket + ticket_message pertama.

**Case 2 (web + OAuth)** pernah memiliki bug duplikat:
- Email processor tidak mengenali email dari JARVIES → buat ticket duplikat
- **Sudah diperbaiki:** Subject email kini diberi prefix `[PENDING]` oleh JARVIES
- Email processor mengenali `[PENDING]` → hanya simpan `conversationId` ke staging, tidak buat ticket baru

---

## ROOT CAUSE — Analisis Bug (Case 1 & 2)

### Alur case 1 — Web tanpa OAuth (BERMASALAH, sudah difix)

```
Customer isi form:
  - description = "Judul tiket"     → disimpan ke staging_tickets.description ✅
  - body = "Isi pesan pertama..."   → TIDAK disimpan ke DB ❌ (sudah difix ✅)

EcoSystem approve staging:
  - Buat ticket ✅
  - TIDAK buat ticket_message pertama ❌ (perlu difix di EcoSystem)
  → Ticket kosong tanpa pesan awal
```

### Alur case 2 — Web dengan OAuth (setelah fix [PENDING])

```
Customer isi form:
  - description = "Judul tiket"     → staging_tickets.description ✅
  - body = "Isi pesan pertama..."   → staging_tickets.body ✅ (sudah difix)
  → Email dikirim ke MS365 dengan subject "[PENDING] Judul tiket"

Email processor (processInbox):
  - Subject starts with [PENDING] → match staging → simpan conversationId ke staging.email_thread_id
  - Tidak buat ticket baru ✅ (tidak duplikat)

EcoSystem approve staging:
  - Buat ticket (salin email_thread_id dari staging) ✅
  - PERLU buat ticket_message pertama dari staging.body ← masih perlu diimplementasi
```

### Akibat

- Employee buka ticket → chat kosong, tidak tahu konteks masalah customer
- Pesan pertama hanya ada di inbox email (jika customer punya OAuth) atau hilang sama sekali
- Customer harus menjelaskan ulang masalahnya di chat

---

## SOLUSI — 2 Bagian (JARVIES + EcoSystem)

---

## BAGIAN 1: JARVIES (Customer Side) — Perlu Diperbaiki

### Fix 1A: Tambah kolom `body` ke tabel `staging_tickets`

**Migration baru (buat di JARVIES atau EcoSystem — shared DB):**

```php
// database/migrations/xxxx_add_body_to_staging_tickets.php
Schema::table('staging_tickets', function (Blueprint $table) {
    $table->text('body')->nullable()->after('description');
});
```

### Fix 1B: Simpan `body` di `StagingTicketService::createFromWeb()`

File: `app/Services/StagingTicketService.php`

```php
// SEBELUM (hanya simpan description):
$staging = StagingTicket::create([
    'customer_id'        => $customerId,
    'description'        => $data['description'],
    'ticket_priority'    => $data['ticket_priority'] ?? 'Medium',
    'status'             => 'unvalidated',
    'channel'            => 'web',
    'submitted_by_email' => $customerEmail,
]);

// SESUDAH (tambahkan body):
$staging = StagingTicket::create([
    'customer_id'        => $customerId,
    'description'        => $data['description'],
    'body'               => $data['body'] ?? null,   // ← tambah ini
    'ticket_priority'    => $data['ticket_priority'] ?? 'Medium',
    'status'             => 'unvalidated',
    'channel'            => 'web',
    'submitted_by_email' => $customerEmail,
]);
```

**Juga tambahkan `body` ke `$fillable` di model `StagingTicket`:**

```php
protected $fillable = [
    'customer_id',
    'description',
    'body',           // ← tambah ini
    'ticket_priority',
    'status',
    ...
];
```

---

## BAGIAN 2: EcoSystem (Employee Side) — Yang Perlu Diimplementasikan

Ketika admin/employee **approve** staging ticket di EcoSystem, proses harus:
1. Buat `ticket` (sudah ada)
2. **Buat `ticket_message` pertama** dari `staging.body` (belum ada)
3. Salin `email_thread_id` dari staging ke ticket (belum ada)

### Logika di `StagingTicketController@approve` (EcoSystem):

```php
// Pseudocode — implementasi di EcoSystem
public function approve(Request $request, $stagingId)
{
    $staging = StagingTicket::findOrFail($stagingId);

    DB::beginTransaction();
    try {
        // 1. Buat ticket dari staging
        $ticket = Ticket::create([
            'customer_id'    => $staging->customer_id,
            'description'    => $staging->description,
            'ticket_priority'=> $staging->ticket_priority,
            'status'         => 'open',
            'jarvies_status' => 'in process',
            'channel'        => $staging->channel,
            'email_thread_id'=> $staging->email_thread_id,  // ← salin thread id
        ]);

        // 2. Buat ticket_message pertama dari body staging
        if (!empty($staging->body)) {
            TicketMessage::create([
                'ticket_id'           => $ticket->ticket_id,
                'sender_type'         => 'customer',
                'sender_id'           => $staging->customer_id,
                'sender_email'        => $staging->submitted_by_email,
                'sender_name'         => $staging->customer->basicData->name_1 ?? 'Customer',
                'message'             => $staging->body,
                'is_internal_note'    => false,
                'channel'             => $staging->channel,    // 'web' atau 'email'
                'is_read_by_customer' => true,
                'is_read_by_agent'    => false,
            ]);

            $ticket->update(['last_message_at' => now(), 'last_customer_reply_at' => now()]);
        }

        // 3. Update staging → approved + link ticket_id
        $staging->update([
            'status'       => 'approved',
            'ticket_id'    => $ticket->ticket_id,
            'validated_by' => auth()->id(),         // atau session employee
            'validated_at' => now(),
        ]);

        DB::commit();
        return response()->json(['success' => true, 'ticket_id' => $ticket->ticket_id]);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
```

---

## SKEMA DATA LENGKAP

### Alur setelah fix diterapkan:

```
Customer submit form (description + body):
  → staging_tickets:
      id: 1
      customer_id: 42
      description: "Error saat login SAP"    ← subject / judul
      body: "Ketika saya klik login..."       ← BARU: isi pesan pertama
      ticket_priority: "High"
      status: "unvalidated"
      channel: "web"
      email_thread_id: null (atau threadId jika OAuth)
      submitted_by_email: "customer@gmail.com"

  → Jika OAuth linked: kirim email ke MS365 inbox (opsional, non-blocking)

EcoSystem admin approve staging:
  → ticket:
      ticket_id: 10
      customer_id: 42
      description: "Error saat login SAP"
      email_thread_id: (disalin dari staging)
      status: "open"

  → ticket_message (BARU):
      ticket_id: 10
      sender_type: "customer"
      sender_id: 42
      message: "Ketika saya klik login..."
      channel: "web"
      is_internal_note: false
      is_read_by_agent: false    ← employee belum baca

Customer buka ticket di JARVIES → pesan pertama tampil ✅
Employee buka ticket di EcoSystem → ada konteks dari customer ✅
```

---

## PERTIMBANGAN TAMBAHAN

### Double message (jika email juga di-pull)

Jika customer punya OAuth dan mengirim email, DAN EcoSystem juga menarik inbox email:
- Bisa terjadi **2 ticket_message** untuk pesan yang sama
- Solusi: cek `email_thread_id` sebelum membuat message dari staging body
  ```php
  // Jika staging sudah punya email_thread_id → kemungkinan sudah ada dari inbox
  // Cek dulu apakah ticket_message dengan channel 'email' sudah ada
  $alreadyFromEmail = TicketMessage::where('ticket_id', $ticket->ticket_id)
      ->where('channel', 'email')
      ->exists();

  if (!$alreadyFromEmail && !empty($staging->body)) {
      // buat ticket_message dari body
  }
  ```

### Email thread linking

Jika `staging.email_thread_id` ada:
- Salin ke `ticket.email_thread_id`
- Balasan employee di EcoSystem bisa di-reply ke thread yang sama di Gmail/Outlook customer

### Channel

| Kondisi | channel di ticket_message |
|---|---|
| Customer tidak punya OAuth | `web` |
| Customer punya OAuth, email terkirim | `email` |
| Customer punya OAuth, email gagal | `web` |

Gunakan `staging.channel` yang sudah ter-set saat pembuatan (defaultnya `web`).

---

## CHECKLIST IMPLEMENTASI DI ECOSYSTEM

- [ ] **Migration:** Tambah kolom `body TEXT NULL` ke tabel `staging_tickets`
- [ ] **JARVIES Fix:** Update `StagingTicketService::createFromWeb()` — simpan `body`
- [ ] **JARVIES Fix:** Update `$fillable` model `StagingTicket` — tambah `body`
- [ ] **EcoSystem:** Update `StagingTicketController@approve`:
  - [ ] Salin `email_thread_id` dari staging ke ticket
  - [ ] Buat `ticket_message` dari `staging.body` (jika tidak kosong)
  - [ ] Set `is_read_by_agent = false` agar employee tahu ada pesan baru
  - [ ] Update `ticket.last_message_at` dan `last_customer_reply_at`
- [ ] **EcoSystem (opsional):** Cegah double message jika email sudah di-pull dari inbox

---

## RELASI TABLE (Shared DB)

```
staging_tickets
  └── ticket_id FK → ticket.ticket_id      (diisi saat approved)
  └── customer_id FK → customer.customer_id

ticket
  └── customer_id FK → customer.customer_id
  └── email_thread_id                        (salin dari staging)

ticket_message
  └── ticket_id FK → ticket.ticket_id
  └── sender_id → customer.customer_id (jika sender_type='customer')
```
