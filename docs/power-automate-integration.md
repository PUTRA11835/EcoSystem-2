# Integrasi EcoSystem ↔ Power Automate (Microsoft Teams)

Tiga otomasi yang dicakup dokumen ini:

| # | Kejadian di EcoSystem | Yang dilakukan Power Automate | Flow key |
|---|---|---|---|
| 1 | Email customer baru masuk (jadi staging ticket) | Balas greeting otomatis ke pengirim | `email_received` |
| 2 | Staging ticket di-approve (tiket resmi terbit) | Kartu tiket ke **channel Teams** + **chat pribadi** ke lead modul | `ticket_validated` |
| 3 | Tiket masih berstatus `open` | Reminder berulang ke lead modul sampai statusnya berubah | `ticket_open_reminder` |

---

## 1. Prinsip arsitektur

**EcoSystem yang memanggil Power Automate, bukan sebaliknya.** Setiap flow dibuat
dengan trigger **"When an HTTP request is received"**, dan URL-nya ditempel ke `.env`.

Dua alasan:

1. **Lisensi.** Trigger HTTP request adalah konektor *standard*. Arah sebaliknya —
   Power Automate menarik data lewat action **HTTP** — adalah konektor **premium**
   dan butuh lisensi berbayar per user.
2. **Ketepatan.** Yang tahu "ini email baru, bukan balasan tiket lama, bukan
   NDR/auto-reply, bukan duplikat, dan pengirimnya memang contact person customer
   terdaftar" adalah EcoSystem. Flow yang men-trigger langsung dari mailbox akan
   menyapa **semua** email termasuk balasan di tengah percakapan.

Semua flow menerima header **`X-EcoSystem-Secret`** dan wajib memeriksanya. URL
trigger Power Automate memang sudah bertanda tangan SAS, tapi siapa pun yang
pernah melihat URL itu bisa memanggilnya lagi — secret ini lapis kedua.

Kegagalan memanggil Power Automate **tidak pernah** menggagalkan operasi
EcoSystem: approve tiket tetap tersimpan, inbox tetap terproses. Kegagalannya
masuk `storage/logs/laravel.log` dengan prefix `PowerAutomate:`.

---

## 2. Persiapan sisi EcoSystem

### 2.1 Migrasi

```bash
php artisan migrate
```

Membuat tabel `ticket_open_reminders` (jejak kapan reminder terakhir dikirim per
tiket). Tabel terpisah, bukan kolom di `ticket`, karena tabel `ticket` dipakai
bersama repo JARVIES.

### 2.2 Variabel `.env`

```dotenv
POWER_AUTOMATE_ENABLED=true
POWER_AUTOMATE_SECRET=<hasil Str::random(48)>
POWER_AUTOMATE_TIMEOUT=10

POWER_AUTOMATE_FLOW_EMAIL_RECEIVED=
POWER_AUTOMATE_FLOW_TICKET_VALIDATED=
POWER_AUTOMATE_FLOW_TICKET_OPEN_REMINDER=
POWER_AUTOMATE_FLOW_TICKET_MEMBER_ADDED=

# Role yang pemegangnya selalu ikut tiap channel tiket, di samping Module Lead.
POWER_AUTOMATE_TEAMS_MEMBER_ROLE_IDS=5,6
# Alamat terlarang -- berlaku untuk jalur channel MAUPUN group chat.
POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS=admin@eclectic.co.id,support01@eclectic.co.id
# Khusus jalur group chat (tidak menyentuh keanggotaan channel).
POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS=

POWER_AUTOMATE_REMINDER_INTERVAL_MINUTES=1
POWER_AUTOMATE_REMINDER_MAX_COUNT=0
POWER_AUTOMATE_REMINDER_BATCH_LIMIT=50
POWER_AUTOMATE_REMINDER_SINCE=
POWER_AUTOMATE_REMINDER_STOP_WHEN_ASSIGNED=false
```

Membangkitkan secret:

```bash
php artisan tinker --execute="echo Str::random(48);"
```

Keempat `POWER_AUTOMATE_FLOW_*` dibiarkan kosong dulu — diisi di bagian 4, 5, 6,
dan 6b setelah flow-nya dibuat dan disimpan. **Flow yang URL-nya kosong otomatis
dilewati**, jadi keempat otomasi bisa dinyalakan bertahap satu per satu.

`POWER_AUTOMATE_TEAMS_MEMBER_ROLE_IDS` berisi `role_id` dari tabel
`employee_role` yang pemegangnya selalu ditarik ke setiap channel tiket baru —
default `5` (Delivery Support Head) dan `6` (Delivery Support Service Helpdesk).
Keanggotaan role dibaca dari pivot `employee_role_assignment`, bukan kolom di
tabel `employee`. Kosongkan untuk mematikan penambahan berbasis role.

Setelah tiap perubahan `.env` di produksi:

```bash
php artisan optimize:clear
```

### 2.3 Pastikan scheduler jalan

Otomasi #3 bergantung pada Laravel scheduler. Di repo ini `supervisord.conf` hanya
menjalankan php-fpm dan queue worker, jadi `schedule:run` datang dari cron di luar
container. Verifikasi bahwa entri ini ada di crontab server:

```cron
* * * * * cd /path/ke/ecosystem && php artisan schedule:run >> /dev/null 2>&1
```

Cek cepat bahwa scheduler hidup: `email:process-inbox` sudah terjadwal tiap menit
sejak lama — kalau email masuk masih otomatis jadi staging ticket, scheduler hidup.

### 2.4 Lengkapi data pendukung

Notifikasi hanya sampai kalau dua data ini terisi:

1. **Lead modul** — `Master → Module → Module Lead`. Modul tanpa lead dilewati
   (dicatat sebagai `Log::debug`, bukan error).
2. **Email kerja lead** — field **Email (Work)** di `Master → Employee → Address`
   (alamat *primary*). Kalau kosong, sistem jatuh ke email login `auth_users.email`.
   Untuk mengisi massal dari email login:

   ```bash
   php artisan employees:backfill-email-work --dry-run   # lihat dulu
   php artisan employees:backfill-email-work
   ```

   Email inilah yang dipakai konektor Teams untuk menemukan orangnya — harus email
   Microsoft 365 `@eclectic.co.id` yang sama dengan akun Teams-nya.

---

## 3. Dua cara membangun flow-nya

**Jalur cepat — import solution siap pakai.** Ketiga flow tersedia dalam satu
Dataverse solution `docs/power-automate/EcoSystem-Teams-Integration_1_0_0_0.zip`,
lengkap dengan trigger, schema payload, pemeriksaan secret, Apply to each, dan
seluruh Adaptive Card. Import lewat **Solutions → Import solution**, lalu isi
empat hal (secret, Team, Channel, URL trigger ke `.env`). Langkahnya di
[docs/power-automate/README.md](power-automate/README.md).

> Environment PT Eclectic mengaktifkan opsi admin **"Create in Dataverse
> solutions"**, sehingga jalur *Import Package (Legacy)* ditolak. Harus lewat
> **Solutions → Import solution**.

**Jalur manual — bagian 3.1 sampai 6 di bawah.** Lebih lama, tapi dijamin bekerja
dan berguna untuk memahami/memperbaiki flow hasil import. Solution di atas
dibangun dari spesifikasi format dan belum pernah diuji import ke tenant PT
Eclectic, jadi jalur ini tetap jadi cadangan.

### 3.1 Pola dasar tiap flow (dikerjakan tiga kali)

Semua flow dibangun dengan kerangka yang sama. Baca sekali di sini, lalu bagian
4–6 hanya menambahkan isinya.

#### Buat flow

1. Buka **Power Automate** → pastikan **Environment** di kanan atas =
   **PT ECLECTIC CONSULTING**.
2. **Create** → **Instant cloud flow**.
3. Beri nama, pilih trigger **"When an HTTP request is received"** → **Create**.

> Jangan pakai panel **Copilot** untuk merakit flow ini. Copilot punya katalog
> konektor sendiri yang lebih sempit — itu sebabnya ia menjawab *"the required
> connector or operation is not available in your current environment"* untuk
> Office 365 Outlook. Tambahkan action secara manual lewat tombol **+ New step**.

#### Pasang schema payload

Pada kartu trigger, klik **Use sample payload to generate schema**, lalu tempel
contoh payload dari bagian 4/5/6. Ini yang membuat field seperti
`ticket → number` muncul sebagai *dynamic content* yang bisa diklik.

#### Pemeriksaan secret (wajib, langkah pertama setelah trigger)

**+ New step** → cari **Condition** (Control).

* Kiri (expression):

  ```
  triggerOutputs()?['headers']?['X-EcoSystem-Secret']
  ```

* Operator: **is equal to**
* Kanan: nilai `POWER_AUTOMATE_SECRET` Anda (ketik langsung)

Di cabang **If no** → **+ Add an action** → **Terminate** (Control) →
Status: **Cancelled**.

Seluruh isi flow diletakkan di cabang **If yes**.

> **Jangan tambahkan action "Response".** Tanpa Response, Power Automate langsung
> membalas `202 Accepted` dan EcoSystem tidak ikut menunggu flow selesai. Dengan
> Response, pemanggil menunggu sampai flow beres — dan `POWER_AUTOMATE_TIMEOUT=10`
> detik akan sering terlampaui.

#### Ambil URL-nya

**Save** dulu. URL baru muncul **setelah** flow tersimpan: buka kembali kartu
trigger → salin **HTTP POST URL** → tempel ke variabel `.env` yang sesuai →
`php artisan optimize:clear`.

---

## 4. Flow 1 — Greeting email otomatis

**Nama:** `EcoSystem - Email Greeting`
**Env:** `POWER_AUTOMATE_FLOW_EMAIL_RECEIVED`

### 4.1 Kapan EcoSystem memanggilnya

Dari `StagingTicketService::createFromEmail()` — persis saat email customer baru
tersimpan sebagai staging ticket, **sebelum** divalidasi. Jadi greeting terkirim
duluan, sesuai permintaan.

Yang **tidak** memicu greeting (dan memang tidak boleh): balasan email pada tiket
yang sudah ada, NDR/undeliverable, email duplikat, dan email dari pengirim yang
bukan contact person customer terdaftar. Semua sudah tersaring lebih dulu di
`EmailController::processInbox()`.

### 4.2 Sample payload untuk schema

```json
{
  "event": "email_received",
  "sent_at": "2026-09-02T09:15:00+07:00",
  "source": "EcoSystem",
  "staging": {
    "id": 512,
    "subject": "Error saat posting GR di MIGO",
    "status": "unvalidated",
    "channel": "email",
    "customer": "PT Contoh Sejahtera",
    "customer_id": 31,
    "sender_name": "Budi Santoso",
    "sender_email": "budi@contoh.co.id",
    "cc_emails": ["atasan@contoh.co.id"],
    "graph_message_id": "AAMkAGI2...",
    "internet_message_id": "<CAF...@mail.contoh.co.id>",
    "conversation_id": "AAQkAGI2...",
    "has_attachments": true,
    "received_at": "2026-09-02T09:14:30+07:00"
  }
}
```

### 4.3 Isi flow (cabang **If yes**)

**+ New step** → konektor **Office 365 Outlook** → action **Reply to email (V3)**.

| Field | Isi |
|---|---|
| Message Id | dynamic content `graph_message_id` |
| Body | teks greeting (contoh di bawah) |
| Reply All | No |

Contoh isi Body (klik **Code view** `</>` pada field Body agar HTML tidak di-escape):

```html
<p>Halo @{triggerBody()?['staging']?['sender_name']},</p>

<p>Terima kasih telah menghubungi Helpdesk PT Eclectic Consulting. Email Anda
dengan subjek "<b>@{triggerBody()?['staging']?['subject']}</b>" sudah kami terima
dan sedang diverifikasi oleh tim kami.</p>

<p>Anda akan menerima nomor tiket resmi begitu laporan ini selesai divalidasi.
Untuk mempercepat penanganan, mohon balas email ini bila ada informasi tambahan
seperti tangkapan layar atau nomor dokumen terkait.</p>

<p>Salam,<br>Helpdesk PT Eclectic Consulting</p>
```

**Mengapa "Reply to email", bukan "Send an email":** greeting harus menempel pada
thread yang sama. Kalau dikirim sebagai email baru, balasan customer terhadap
greeting akan dibaca EcoSystem sebagai **tiket baru** dan muncul lagi di staging.
`graph_message_id` dikirim di payload justru untuk ini.

### 4.4 Kalau mailbox helpdesk adalah shared mailbox

Bila `MS_SENDER_EMAIL` menunjuk shared mailbox dan akun koneksi Anda tidak bisa
membalas atas namanya, ganti action-nya menjadi **"Send an email from a shared
mailbox (V2)"**:

| Field | Isi |
|---|---|
| Original Mailbox Address | alamat shared mailbox, mis. `helpdesk@eclectic.co.id` |
| To | dynamic content `sender_email` |
| Subject | `RE: @{triggerBody()?['staging']?['subject']}` |
| Body | sama seperti di atas |

Threading di sini bergantung pada kecocokan subjek — EcoSystem punya pencocokan
subjek (`subjectTopicMatches`), tapi tetap kurang kuat dibanding Reply. Pakai ini
hanya kalau Reply benar-benar tidak bisa.

### 4.5 Kalau konektor Office 365 Outlook benar-benar diblokir

Kalau setelah dicoba manual action Outlook tetap tidak muncul (kebijakan DLP
tenant), urutan opsinya:

1. **Minta admin M365** melonggarkan kebijakan DLP untuk environment ini —
   opsi paling benar.
2. Konektor **Mail** → **"Send an email notification (V3)"** — gratis dan selalu
   ada, tapi terkirim dari `microsoft@powerapps.com`, tidak nyambung thread, dan
   terlihat tidak profesional ke customer. Anggap darurat saja.
3. Kirim greeting dari EcoSystem sendiri lewat Microsoft Graph (`EmailController`
   sudah punya seluruh infrastrukturnya). Paling andal, tapi keluar dari Power
   Automate. Belum diimplementasi — beri tahu kalau opsi ini yang dipilih.

---

## 5. Flow 2 — Tiket divalidasi → kartu Teams + chat lead modul

**Nama:** `EcoSystem - Ticket Validated`
**Env:** `POWER_AUTOMATE_FLOW_TICKET_VALIDATED`

### 5.1 Kapan EcoSystem memanggilnya

Dari `StagingTicketController::approve()`, setelah tiket resmi tersimpan.
Pemanggilan terjadi **setelah response dikirim** ke browser, jadi tombol
"Validate" tidak terasa lambat.

### 5.2 Sample payload untuk schema

```json
{
  "event": "ticket_validated",
  "sent_at": "2026-09-02T09:30:00+07:00",
  "source": "EcoSystem",
  "ticket": {
    "id": 1204,
    "number": "26090012",
    "subject": "Error saat posting GR di MIGO",
    "status": "open",
    "status_label": "Open",
    "priority": "High",
    "type": "Incident",
    "scale": "Medium",
    "channel": "email",
    "customer": "PT Contoh Sejahtera",
    "end_customer": null,
    "module": "MM",
    "module_id": 4,
    "submitted_by": {
      "name": "Budi Santoso",
      "email": "budi@contoh.co.id",
      "phone": "08123456789"
    },
    "pic": null,
    "members": [],
    "is_assigned": false,
    "created_at": "2026-09-02T09:14:30+07:00",
    "age_minutes": 16,
    "url": "https://ecosystem.eclectic.co.id/ticket/1204"
  },
  "module_leads": [
    { "employee_id": 58, "name": "Rina Kartika", "email": "rina@eclectic.co.id" }
  ],
  "lead_emails": ["rina@eclectic.co.id"],
  "channel": {
    "name": "26090012 - Error saat posting GR di MIGO",
    "description": "Error saat posting GR di MIGO | Customer: PT Contoh Sejahtera | Modul: MM",
    "members": [
      { "name": null, "email": "rina@eclectic.co.id", "source": "module_lead" },
      { "name": "Antonius Cahyadi Sutanto", "email": "antonius.cs@eclectic.co.id", "source": "role" },
      { "name": "Santo Suharyono", "email": "santo.suharyono@eclectic.co.id", "source": "role" }
    ],
    "member_emails": [
      "rina@eclectic.co.id",
      "antonius.cs@eclectic.co.id",
      "santo.suharyono@eclectic.co.id"
    ],
    "member_csv": "rina@eclectic.co.id;antonius.cs@eclectic.co.id;santo.suharyono@eclectic.co.id",
    "member_count": 3
  },
  "validated_by": { "id": 12, "name": "Helpdesk Support" }
}
```

`module_leads` dan `lead_emails` sengaja sudah jadi di payload. Pemetaan modul →
lead → email kerja adalah pengetahuan EcoSystem; flow tidak perlu menebaknya.

Begitu pula `channel.member_emails` (ditambahkan 7 Sep 2026): daftar orang yang
harus bisa membaca channel tiket = Module Lead **+** pemegang role di
`POWER_AUTOMATE_TEAMS_MEMBER_ROLE_IDS` (default `5,6` = Delivery Support Head dan
Delivery Support Service Helpdesk) — hanya dua sumber itu. Sudah dedup, sudah
dibuang yang masuk `_EXCLUDE_MEMBERS`, sudah dibuang yang tidak punya email kerja
dan yang employee-nya non-aktif. `POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS` **tidak**
ikut: knob itu khusus jalur group chat (menjaga peserta ≥3 agar grup boleh diberi
nama, dan penerima cadangan untuk modul tanpa lead) — di jalur channel keduanya
tidak berlaku. Langkah pemakaiannya di
[`power-automate/README.md`](power-automate/README.md) bagian *Konfigurasi flow 2
→ langkah 5*.

> Ingat bedanya: di Teams, *standard channel* tidak punya daftar anggota sendiri.
> Yang menentukan siapa bisa membaca channel adalah keanggotaan **team**-nya —
> jadi `member_emails` dipakai untuk aksi **Add a member to a team**, bukan aksi
> "add to channel" (yang memang tidak ada).

### 5.3 Langkah A — kartu ke channel tim

**+ New step** → **Microsoft Teams** → **Post card in a chat or channel**.

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Channel** |
| Team | pilih tim, mis. *Helpdesk Eclectic* |
| Channel | pilih channel, mis. *Ticket Masuk* |
| Adaptive Card | JSON di bawah |

```json
{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.4",
  "body": [
    {
      "type": "TextBlock",
      "text": "🎫 Tiket Baru: @{triggerBody()?['ticket']?['number']}",
      "weight": "Bolder",
      "size": "Large",
      "wrap": true
    },
    {
      "type": "TextBlock",
      "text": "@{triggerBody()?['ticket']?['subject']}",
      "wrap": true,
      "spacing": "None"
    },
    {
      "type": "FactSet",
      "facts": [
        { "title": "Customer", "value": "@{triggerBody()?['ticket']?['customer']}" },
        { "title": "Modul", "value": "@{triggerBody()?['ticket']?['module']}" },
        { "title": "Prioritas", "value": "@{triggerBody()?['ticket']?['priority']}" },
        { "title": "Tipe", "value": "@{triggerBody()?['ticket']?['type']}" },
        { "title": "Pelapor", "value": "@{triggerBody()?['ticket']?['submitted_by']?['name']}" },
        { "title": "Divalidasi oleh", "value": "@{triggerBody()?['validated_by']?['name']}" },
        { "title": "Lead Modul", "value": "@{join(triggerBody()?['lead_emails'], ', ')}" }
      ]
    }
  ],
  "actions": [
    {
      "type": "Action.OpenUrl",
      "title": "Buka di EcoSystem",
      "url": "@{triggerBody()?['ticket']?['url']}"
    }
  ]
}
```

> Field yang bisa kosong (`module`, `priority`) akan tampil kosong, bukan error.
> Kalau ingin teks pengganti, bungkus dengan `coalesce`, mis.
> `@{coalesce(triggerBody()?['ticket']?['module'], 'Belum ditentukan')}`.

### 5.4 Langkah B — chat pribadi ke tiap lead modul

> **Diganti 2 Sep 2026.** Konfigurasi yang dipakai sekarang bukan chat pribadi,
> melainkan **@mention lead di thread kartu channel** — satu channel tetap, tiap
> tiket jadi thread balasan di bawah kartunya. Langkah pastinya ada di
> [power-automate/README.md](power-automate/README.md) bagian "Konfigurasi flow 2"
> langkah 4. Bagian di bawah ini disimpan sebagai alternatif kalau chat pribadi
> diinginkan lagi.

**+ New step** → **Control** → **Apply to each**.

* **Select an output from previous steps**: dynamic content **`lead_emails`**
  (atau expression `triggerBody()?['lead_emails']`).

Di dalam **Apply to each** → **Add an action** → **Microsoft Teams** →
**Post card in a chat or channel**:

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Chat with Flow bot** |
| Recipient | **Current item** (dynamic content dari Apply to each) |
| Adaptive Card | JSON di bawah |

```json
{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.4",
  "body": [
    {
      "type": "TextBlock",
      "text": "Tiket baru di modul Anda",
      "weight": "Bolder",
      "size": "Medium"
    },
    {
      "type": "TextBlock",
      "text": "**@{triggerBody()?['ticket']?['number']}** — @{triggerBody()?['ticket']?['subject']}",
      "wrap": true
    },
    {
      "type": "FactSet",
      "facts": [
        { "title": "Customer", "value": "@{triggerBody()?['ticket']?['customer']}" },
        { "title": "Modul", "value": "@{triggerBody()?['ticket']?['module']}" },
        { "title": "Prioritas", "value": "@{triggerBody()?['ticket']?['priority']}" }
      ]
    },
    {
      "type": "TextBlock",
      "text": "Mohon tentukan PIC atau ambil tiket ini.",
      "wrap": true
    }
  ],
  "actions": [
    {
      "type": "Action.OpenUrl",
      "title": "Assign / Ambil Tiket",
      "url": "@{triggerBody()?['ticket']?['url']}"
    }
  ]
}
```

**Apply to each atas array kosong tidak error** — kalau modul tiket belum punya
lead, langkah B otomatis dilewati dan kartu channel tetap terkirim. Itu memang
perilaku yang diinginkan.

---

## 6. Flow 3 — Reminder tiket masih Open

**Nama:** `EcoSystem - Open Ticket Reminder`
**Env:** `POWER_AUTOMATE_FLOW_TICKET_OPEN_REMINDER`

### 6.1 Kapan EcoSystem memanggilnya

Dari command `tickets:open-reminders` yang terjadwal **tiap menit**. Command
menghitung ulang daftar tiket dari status **saat itu juga**, jadi "berhenti kalau
status bukan `open` lagi" tidak butuh pembatalan apa pun: begitu helpdesk
mengubah status, tiketnya hilang sendiri dari hasil query dan reminder berhenti
pada siklus berikutnya (paling lambat 1 menit).

Satu panggilan flow = satu tiket. Tiket yang gagal dikirim otomatis dicoba lagi
pada siklus berikutnya karena `last_sent_at`-nya tidak diperbarui.

### 6.2 Sample payload untuk schema

```json
{
  "event": "ticket_open_reminder",
  "sent_at": "2026-09-02T09:45:00+07:00",
  "source": "EcoSystem",
  "ticket": {
    "id": 1204,
    "number": "26090012",
    "subject": "Error saat posting GR di MIGO",
    "status": "open",
    "status_label": "Open",
    "priority": "High",
    "type": "Incident",
    "scale": "Medium",
    "channel": "email",
    "customer": "PT Contoh Sejahtera",
    "end_customer": null,
    "module": "MM",
    "module_id": 4,
    "submitted_by": {
      "name": "Budi Santoso",
      "email": "budi@contoh.co.id",
      "phone": "08123456789"
    },
    "pic": null,
    "members": [],
    "is_assigned": false,
    "created_at": "2026-09-02T09:14:30+07:00",
    "age_minutes": 31,
    "url": "https://ecosystem.eclectic.co.id/ticket/1204"
  },
  "module_leads": [
    { "employee_id": 58, "name": "Rina Kartika", "email": "rina@eclectic.co.id" }
  ],
  "lead_emails": ["rina@eclectic.co.id"],
  "reminder": {
    "count": 7,
    "interval_minutes": 1,
    "open_for_minutes": 31,
    "is_first": false
  }
}
```

### 6.3 Isi flow (cabang **If yes**)

**+ New step** → **Control** → **Apply to each** atas `lead_emails`. Di dalamnya:
**Microsoft Teams** → **Post card in a chat or channel**, **Post in** =
**Chat with Flow bot**, **Recipient** = **Current item**.

```json
{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.4",
  "body": [
    {
      "type": "TextBlock",
      "text": "⏰ Tiket masih Open",
      "weight": "Bolder",
      "size": "Medium",
      "color": "Attention"
    },
    {
      "type": "TextBlock",
      "text": "**@{triggerBody()?['ticket']?['number']}** — @{triggerBody()?['ticket']?['subject']}",
      "wrap": true
    },
    {
      "type": "FactSet",
      "facts": [
        { "title": "Customer", "value": "@{triggerBody()?['ticket']?['customer']}" },
        { "title": "Modul", "value": "@{triggerBody()?['ticket']?['module']}" },
        { "title": "Prioritas", "value": "@{triggerBody()?['ticket']?['priority']}" },
        { "title": "Terbuka selama", "value": "@{triggerBody()?['reminder']?['open_for_minutes']} menit" },
        { "title": "Pengingat ke-", "value": "@{triggerBody()?['reminder']?['count']}" }
      ]
    },
    {
      "type": "TextBlock",
      "text": "Tiket ini belum diproses. Mohon assign member atau ambil sendiri — pengingat berhenti otomatis begitu statusnya berubah dari Open.",
      "wrap": true
    }
  ],
  "actions": [
    {
      "type": "Action.OpenUrl",
      "title": "Buka Tiket",
      "url": "@{triggerBody()?['ticket']?['url']}"
    }
  ]
}
```

### 6.4 Knob pengendali kebisingan

Interval 1 menit tanpa batas berarti tiket yang dibiarkan `open` semalaman
menghasilkan **±480 pesan Teams per lead per tiket**. Empat variabel berikut
mengendalikannya tanpa perlu ubah kode:

| Variabel | Arti | Saran |
|---|---|---|
| `POWER_AUTOMATE_REMINDER_INTERVAL_MINUTES` | Jarak antar reminder untuk satu tiket | `1` sesuai permintaan; `15`–`30` jauh lebih manusiawi |
| `POWER_AUTOMATE_REMINDER_MAX_COUNT` | Batas jumlah reminder per tiket, `0` = tanpa batas | `0` sesuai permintaan; `10` mencegah spam semalaman |
| `POWER_AUTOMATE_REMINDER_STOP_WHEN_ASSIGNED` | Berhenti begitu tiket punya PIC/member walau masih Open | `true` kalau tujuannya memang "supaya di-assign" |
| `POWER_AUTOMATE_REMINDER_SINCE` | Hanya tiket yang dibuat sejak waktu ini | **Isi waktu go-live** kalau sudah ada backlog tiket Open lama |

`POWER_AUTOMATE_REMINDER_BATCH_LIMIT` (default 50) membatasi berapa tiket
diproses per siklus. Tiket yang belum pernah dikirimi reminder didahulukan, lalu
yang paling lama tidak dikirimi — jadi batas ini memotong antrean secara adil,
bukan mengunci tiket yang sama terus.

> **Peringatan Power Automate:** akun non-premium punya kuota **aksi per 24 jam**
> (umumnya 6.000). Satu reminder = beberapa aksi. Dengan interval 1 menit, 5 tiket
> open sepanjang hari kerja sudah bisa menghabiskan kuota dan flow akan
> di-*throttle* — reminder berhenti diam-diam. Ini alasan paling praktis untuk
> menaikkan interval atau memasang `MAX_COUNT`.

---

## 6b. Flow 4 — Consultant di-assign → masuk channel tiket

Langkah designer lengkapnya ada di
[`power-automate/README.md`](power-automate/README.md) bagian *Konfigurasi flow 4*.
Yang perlu diketahui di sini: kapan dipanggil dan bentuk payloadnya.

### 6b.1 Kapan EcoSystem memanggilnya

Satu panggilan = satu orang, dari dua tempat di `TicketController`:

| Pemicu | Method | `person.role` |
|---|---|---|
| Tombol **Add Member** di halaman tiket | `addMember()` | `member` |
| Penetapan/penggantian **PIC** | `assignPic()` dan `update()` | `pic` |

Yang **tidak** memicu: update member massal (sync), pelepasan member, dan orang
tanpa Email (Work) maupun email login — tanpa email, konektor Teams tidak bisa
menemukan orangnya, jadi flow sengaja tidak dipanggil sama sekali. PIC yang
disimpan ulang dengan orang yang sama juga tidak memicu apa pun.

### 6b.2 Sample payload

```json
{
  "event": "ticket_member_added",
  "sent_at": "2026-09-07T14:05:00+07:00",
  "source": "EcoSystem",
  "ticket": { "...": "sama persis dengan blok ticket di flow 2" },
  "channel": {
    "name": "26090012 - Error saat posting GR di MIGO",
    "number": "26090012"
  },
  "person": {
    "employee_id": 35,
    "name": "Santo Suharyono",
    "email": "santo.suharyono@eclectic.co.id",
    "role": "member",
    "role_label": "Member"
  },
  "added_by": { "id": 12, "name": "Helpdesk Support", "email": "helpdesk@eclectic.co.id" }
}
```

`channel.name` dirakit ulang oleh fungsi yang **sama** dengan yang dipakai flow 2
saat membuat channelnya, jadi flow bisa mencocokkannya persis lewat aksi Teams
**List channels** + **Filter array** — tanpa perlu menyimpan Channel Id, yang
akan menuntut aksi HTTP berlisensi Premium. `channel.number` adalah kunci
cadangan untuk kasus subject tiket diedit setelah channelnya terbentuk.

---
## 7. Pengujian bertahap

### 7.1 Uji flow sendirian, tanpa EcoSystem

Di halaman flow → **Test** → **Manually**, atau kirim dari terminal (ganti URL dan
secret; payload contoh ada di bagian 4.2 / 5.2 / 6.2):

```bash
curl -X POST "<HTTP POST URL flow>" \
  -H "Content-Type: application/json" \
  -H "X-EcoSystem-Secret: <POWER_AUTOMATE_SECRET>" \
  -d @payload-contoh.json
```

Uji juga jalur negatifnya: kirim tanpa header `X-EcoSystem-Secret` — run harus
berakhir **Cancelled**, bukan mengirim kartu.

### 7.2 Uji reminder dari EcoSystem tanpa mengirim apa pun

```bash
php artisan tickets:open-reminders --dry-run
```

Menampilkan tiket mana yang akan dikirimi, ke email siapa, dan reminder ke berapa.
Kalau daftarnya kosong padahal ada tiket Open, cek berurutan: status benar `open`,
modulnya punya lead, lead-nya punya Email (Work), dan `POWER_AUTOMATE_REMINDER_SINCE`
tidak lebih baru dari tanggal tiket.

Sekali jalan sungguhan:

```bash
php artisan tickets:open-reminders --limit=1
```

### 7.3 Uji end-to-end

1. **Greeting** — kirim email dari alamat contact person customer terdaftar ke
   mailbox helpdesk. Dalam ≤1 menit muncul di `Staging Ticket` **dan** greeting
   masuk ke inbox pengirim. Balas greeting itu, pastikan balasannya menempel di
   staging/tiket yang sama, **bukan** menjadi staging baru.
2. **Validasi** — approve staging itu. Kartu muncul di channel Teams dan di chat
   pribadi lead modul.
3. **Reminder** — biarkan tiketnya `open` beberapa menit, pastikan reminder datang
   berulang. Ubah statusnya ke `inprocess`, pastikan reminder **berhenti** pada
   siklus berikutnya.

---

## 8. Pemecahan masalah

| Gejala | Penyebab yang paling sering |
|---|---|
| Copilot bilang konektor tidak tersedia | Katalog Copilot lebih sempit dari designer. Tambahkan action manual lewat **+ New step**. |
| Tidak ada apa pun terkirim | `POWER_AUTOMATE_ENABLED=false`, URL flow kosong, atau `php artisan optimize:clear` belum dijalankan setelah ubah `.env`. |
| Log berisi `PowerAutomate: flow menolak request` status 401/403 | `X-EcoSystem-Secret` tidak sama dengan yang di Condition flow. |
| Run Power Automate berstatus **Cancelled** | Terminate cabang *If no* — artinya secret tidak cocok. Bandingkan huruf per huruf, awas spasi di ujung nilai `.env`. |
| Kartu ke channel jalan, chat pribadi tidak | `lead_emails` kosong: modul belum punya lead, atau lead-nya belum punya Email (Work). Cek `Master → Module → Module Lead`. |
| Teams: *recipient not found* | Email kerja di EcoSystem bukan akun Microsoft 365 yang aktif, atau alias, bukan UPN utama. |
| Reminder tidak pernah datang | Cron `schedule:run` mati, atau `POWER_AUTOMATE_REMINDER_SINCE` lebih baru dari tanggal tiket. Uji `php artisan tickets:open-reminders --dry-run`. |
| Reminder tidak berhenti setelah status diubah | Beri waktu sampai satu siklus scheduler (≤1 menit). Kalau tetap, statusnya kemungkinan masih `open` di DB. |
| Balasan customer atas greeting jadi staging baru | Flow memakai *Send an email*, bukan *Reply to email*. Lihat bagian 4.3. |
| Flow tiba-tiba berhenti jalan | Kuota aksi 24 jam terlampaui (lihat peringatan di 6.4), atau koneksi Teams/Outlook di flow kedaluwarsa dan perlu di-*reauthenticate*. |

Log sisi EcoSystem:

```bash
tail -f storage/logs/laravel.log | grep -i "PowerAutomate\|open-reminders"
```

---

## 9. Peta berkas

| Berkas | Peran |
|---|---|
| `config/services.php` → `power_automate` | Seluruh konfigurasi & URL flow |
| `app/Services/PowerAutomateService.php` | Pengirim webhook + pembangun payload + resolusi lead & email kerja |
| `app/Services/StagingTicketService.php` | Pemicu flow 1 (`createFromEmail`) |
| `app/Http/Controllers/StagingTicketController.php` | Pemicu flow 2 (`approve` → `notifyTeamsTicketValidated`) |
| `app/Console/Commands/SendOpenTicketReminders.php` | Pemicu flow 3 |
| `app/Models/TicketOpenReminder.php` + migration `..._create_ticket_open_reminders_table` | Jejak pengiriman reminder |
| `routes/console.php` | Jadwal `tickets:open-reminders` tiap menit |
| `docs/power-automate/EcoSystem-Teams-Integration_1_0_0_0.zip` | Dataverse solution berisi ketiga flow |
| `docs/power-automate/solution-src/` | Isi solution dalam bentuk mentah |
| `docs/power-automate/build/definitions.php` | Sumber tunggal definisi ketiga flow |
| `docs/power-automate/legacy/` | Paket legacy — ditolak environment PT Eclectic |
| `docs/power-automate/README.md` | Langkah import + apa yang harus dilengkapi sesudahnya |
