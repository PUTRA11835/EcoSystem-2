# Paket import flow Power Automate

Flow integrasi EcoSystem ↔ Microsoft Teams dalam bentuk siap-import.
Panduan konsep, konfigurasi `.env`, dan troubleshooting ada di
[../power-automate-integration.md](../power-automate-integration.md) — berkas ini
khusus soal cara meng-import dan mengonfigurasi flow-nya.

## Keadaan saat ini — 9 September 2026

| Flow | Bentuk | Status |
|---|---|---|
| 1. `EcoSystem - Email Greeting` | balas greeting di thread email yang sama | **Terbukti jalan** end-to-end |
| 2. `EcoSystem - Ticket Validated` | buat channel tiket + kartu + tarik anggota + mention lead modul | **ARSIP** — pernah terbukti jalan, tidak lagi dipanggil EcoSystem |
| 3. `EcoSystem - Open Ticket Reminder` | reminder berulang selama tiket Open | **Belum dikonfigurasi** |
| 4. `EcoSystem - Ticket Member Added` | PIC/member baru ditarik ke channel tiketnya + kartu | **ARSIP** — pernah terbukti jalan (8 Sep 2026), tidak lagi dipanggil EcoSystem |
| 5. `EcoSystem - Ticket Validated (Group Chat)` | buat **group chat** per tiket + kartu + mention lead modul | **Belum dibuat** (9 Sep 2026) |
| 6. `EcoSystem - Ticket Member Added (Group Chat)` | PIC/member baru ditambahkan ke **group chat** tiketnya + kartu | **Belum dibuat** (9 Sep 2026) |

**Hasil meeting 9 September 2026: wadah tiket kembali ke bentuk group chat per
tiket**, bentuk yang selama ini sudah biasa dibuat manual oleh tim support, dan
consultant yang di-assign ditambahkan ke group chat itu. Flow 5 & 6 di atas
adalah bentuk itu, dibuat sebagai **flow baru** supaya flow 2 & 4 tidak perlu
dibongkar. Panduan membuat keduanya dari nol ada di bagian *Konfigurasi flow 5*
dan *Konfigurasi flow 6* di bawah.

**Tambahan 11 September 2026:** peserta group chat kini juga memuat **tim
Delivery Support tiket** — Delivery Owner, Support Manager, CO PM, dan Support
Admin dari delivery support yang dipilih helpdesk saat validasi. Rinciannya di
bagian *Siapa saja yang masuk group chat tiket*. Tidak ada perubahan di sisi
designer: daftarnya tetap tiba sebagai `chat.members_csv` yang sama.

**Flow 2 & 4 berstatus ARSIP.** Keduanya tetap tersimpan di Power Automate dan
panduan konfigurasinya tetap ada di berkas ini, tapi **EcoSystem tidak lagi
mengirim blok `channel`** — payloadnya kini hanya membawa blok `chat`. Artinya
flow 2 & 4 tidak bisa dihidupkan begitu saja: kalau suatu saat bentuk channel
dipakai lagi, sisi EcoSystem yang menyesuaikan (kembalikan
`ticketChannelPayload()` dan `channelMembers()` dari commit `d67d446`). Itu
keputusan sadar — satu bentuk saja yang hidup di kode supaya tidak ada dua jalur
yang harus dirawat bersamaan.

URL flow 5 & 6 dipasang di variabel `.env` yang **sudah ada**, bukan variabel
baru:

| `.env` | Diisi URL flow |
|---|---|
| `POWER_AUTOMATE_FLOW_TICKET_VALIDATED` | flow 5 (group chat) |
| `POWER_AUTOMATE_FLOW_TICKET_MEMBER_ADDED` | flow 6 (group chat) |

**Semua flow sekarang dalam keadaan `Off`, dan itu disengaja.** Perubahan
kodenya sudah di-merge, tetapi `.env` produksi belum membawa variabel Power
Automate sama sekali — `POWER_AUTOMATE_ENABLED` default `false`, jadi EcoSystem
tidak memanggil apa pun. Menyalakannya nanti **tidak perlu deploy ulang**: isi
`POWER_AUTOMATE_ENABLED`, `POWER_AUTOMATE_SECRET`, dan URL flow yang diinginkan
di `.env`, lalu `php artisan optimize:clear`. Flow yang URL-nya dibiarkan kosong
otomatis dilewati, jadi bisa dinyalakan satu per satu.

Sisi EcoSystem aman dalam keadaan mati: keempat titik pemanggilan
(`StagingTicketService` untuk greeting, `StagingTicketController@approve`,
`TicketController` untuk add member/assign PIC, dan command
`tickets:open-reminders`) semuanya memeriksa `isFlowReady()` lebih dulu dan
`return` tanpa efek. Panggilan flow tiket-divalidasi juga merupakan langkah **terakhir** di
`approve()` — di luar transaksi, dibungkus `try/catch`, dan dikirim setelah
response — sehingga tidak mungkin mengganggu validasi tiket harian.

**Yang masih terbuka:** flow 3, 5, dan 6 belum dibuat di designer. Soal
visibilitas channel (bab terakhir) otomatis gugur di bentuk group chat — grup
hanya terlihat oleh pesertanya — dan digantikan satu batasan baru: maksimal 20
peserta per chat.

## Berkas mana yang dipakai

**`EcoSystem-Teams-Integration_1_0_0_0.zip`** — satu **Dataverse solution** berisi
ketiga flow sekaligus. Ini yang dipakai.

Environment PT Eclectic mengaktifkan opsi admin **"Create in Dataverse
solutions"**, yang mematikan jalur *legacy package*. Mencoba mengimpornya
menghasilkan:

> *Importing packages with flows is disabled because the "Create in Dataverse
> solutions" option was enabled by your admin. Use solution import instead.*

Paket legacy tetap disimpan di [`legacy/`](legacy/) kalau suatu saat kebijakan
environment berubah, tapi untuk sekarang **abaikan folder itu**.

| Folder | Isi |
|---|---|
| `EcoSystem-Teams-Integration_1_0_0_0.zip` | Solution — ini yang di-import |
| `solution-src/` | Isi solution dalam bentuk mentah (`solution.xml`, `customizations.xml`, `Workflows/*.json`) |
| `legacy/` + `src/` | Paket legacy dan sumbernya — ditolak environment ini |
| `build/` | Skrip pembangun; `definitions.php` adalah sumber tunggal definisi ketiga flow |
| `schemas/` | Request Body JSON Schema tiap flow (opsional - lihat catatan lapangan 3) |
| `cards/` | Adaptive Card tiap aksi Teams, siap salin-tempel ke designer |

Mengemas ulang setelah mengubah `build/definitions.php`:

```bash
php docs/power-automate/build/build-solution.php
php docs/power-automate/build/extract-schemas.php   # regenerasi schemas/
php docs/power-automate/build/extract-cards.php     # regenerasi cards/
```

> Jangan mengemas ulang dengan klik-kanan → *Send to → Compressed folder* atau
> `Compress-Archive`: keduanya menulis pemisah path `\` di dalam zip dan importer
> menolaknya. Skrip di atas menulis `/`.

---

## Kenapa tidak copy-paste teks saja

Power Automate **Desktop** (RPA) memang bisa menyalin aksi sebagai teks. Power
Automate **cloud** — yang dipakai di sini karena butuh trigger HTTP dan konektor
Teams/Outlook — tidak punya fitur itu. Padanannya adalah import solution.

---

## Langkah import

1. Buka **Power Automate** → pastikan **Environment** di kanan atas =
   **PT ECLECTIC CONSULTING**.
2. Sidebar kiri → **Solutions**.
3. Toolbar atas → **Import solution**.
4. **Browse** → pilih `EcoSystem-Teams-Integration_1_0_0_0.zip` → **Next**.
5. Halaman ringkasan menampilkan solution *EcoSystem Teams Integration* v1.0.0.0,
   publisher *PT Eclectic Consulting* → **Next**.
6. Bila diminta memilih **connections** (Office 365 Outlook / Microsoft Teams),
   pilih koneksi yang sudah ada atau **+ New connection** dengan akun
   `@eclectic.co.id`, lalu **Refresh**.
7. **Import**. Tunggu sampai statusnya berhasil, lalu buka solution-nya — ketiga
   flow ada di dalamnya.

**Ketiga flow sengaja masuk dalam keadaan MATI (draft).** Itu disengaja: secret
dan Team/Channel harus diisi dulu, supaya tidak ada pesan terkirim sebelum
konfigurasinya benar. Nyalakan lewat **Turn on** setelah langkah di bawah selesai.

---

## Yang WAJIB dilengkapi setelah import

Empat hal yang tidak bisa dibawa paket karena khas lingkungan Anda.

### 0. Request Body JSON Schema — ketiga flow

**Schema trigger tidak ikut terbawa saat import solution** (terpantau 2 Sep 2026:
kotak *Request Body JSON Schema* kosong setelah import berhasil). Tanpa schema,
dynamic content tidak muncul dan ekspresi di dalam flow tidak punya acuan.

Buka flow → **Edit** → kartu **When a HTTP request is received** → tempel isi
berkas yang sesuai dari [`schemas/`](schemas/) ke kotak **Request Body JSON
Schema**:

| Flow | Berkas |
|---|---|
| EcoSystem - Email Greeting | `schemas/flow-1-email-greeting-request-schema.json` |
| EcoSystem - Ticket Validated | `schemas/flow-2-ticket-validated-request-schema.json` |
| EcoSystem - Open Ticket Reminder | `schemas/flow-3-open-ticket-reminder-request-schema.json` |

Tempel **isi berkasnya apa adanya** — itu sudah berupa JSON Schema, bukan contoh
payload, jadi jangan lewat tombol *Use sample payload to generate schema*.

Regenerasi berkas schema (mengikuti `build/definitions.php`):

```bash
php docs/power-automate/build/extract-schemas.php
```

### 0b. Otorisasi koneksi

Setelah import muncul banner *"Some of the connections are not authorized yet"*.
Buka aksi konektor di dalam flow (Outlook untuk flow 1, Teams untuk flow 2 & 3),
klik **⋯ → Add new connection** atau pilih koneksi yang sudah ada dengan akun
`@eclectic.co.id`. Banner hilang setelah tiap aksi punya koneksi.

### 1. Secret — ketiga flow

Buka flow → **Edit** → aksi **Cek secret EcoSystem** (Condition). Sisi kanan
perbandingan masih berisi teks harfiah:

```
GANTI_DENGAN_POWER_AUTOMATE_SECRET
```

Ganti dengan nilai `POWER_AUTOMATE_SECRET` dari `.env` EcoSystem — harus sama
persis, awas spasi ikut ter-copy. Sisi kirinya sudah terisi dan tidak perlu
diubah:

```
triggerOutputs()?['headers']?['X-EcoSystem-Secret']
```

### 2. Tujuan kartu — flow *Ticket Validated* saja

**Diputuskan 2 Sep 2026: satu CHANNEL BARU per tiket di dalam team Support MO
Team.** (Rancangan sempat memakai group chat karena app Teams tidak tersedia di
akun penyiap flow; dibatalkan atas permintaan atasan agar strukturnya rapi dalam
satu team.) Langkahnya di bagian "Konfigurasi flow 2" di bawah.

### 3. Periksa aksi balas email — flow *Email Greeting* saja

Buka aksi **Balas greeting ke pengirim**, pastikan ketiga field terisi:

| Field | Nilai yang seharusnya |
|---|---|
| Message Id | ekspresi `triggerBody()?['staging']?['graph_message_id']` |
| Body | teks greeting HTML |
| Reply All | No |

Kalau ada yang kosong setelah import, isi manual — nama parameter internal
konektor Outlook bisa berbeda antar versi. Isi Body-nya ada di
[../power-automate-integration.md](../power-automate-integration.md) bagian 4.3.

Kalau mailbox helpdesk berupa **shared mailbox** dan akun koneksi Anda tidak bisa
membalas atas namanya, ganti aksinya dengan **Send an email from a shared
mailbox (V2)** — lihat bagian 4.4 dokumen yang sama.

### 4. Salin URL trigger ke `.env`

**Save** tiap flow, lalu buka kartu trigger **When an HTTP request is received**
dan salin **HTTP POST URL**-nya (URL baru terbit setelah flow tersimpan).

```dotenv
POWER_AUTOMATE_FLOW_EMAIL_RECEIVED=<URL EcoSystem - Email Greeting>
POWER_AUTOMATE_FLOW_TICKET_VALIDATED=<URL EcoSystem - Ticket Validated>
POWER_AUTOMATE_FLOW_TICKET_OPEN_REMINDER=<URL EcoSystem - Open Ticket Reminder>
POWER_AUTOMATE_ENABLED=true
```

```bash
php artisan optimize:clear
```

Terakhir: **Turn on** ketiga flow.

---

## Yang sudah ikut di dalam paket

Supaya jelas mana yang tidak perlu dikerjakan lagi:

- Trigger HTTP **beserta schema payload-nya** — dynamic content seperti
  `ticket → number` dan `lead_emails` langsung tersedia, tidak perlu
  *Use sample payload to generate schema*.
- **Condition pemeriksa secret** + cabang *else* **Terminate (Cancelled)**.
- **Apply to each** atas `lead_emails` untuk chat pribadi ke tiap lead.
- Seluruh **Adaptive Card** lengkap dengan ekspresinya, termasuk `coalesce` untuk
  field yang boleh kosong (modul/prioritas) supaya tidak tampil kosong.
- **Tidak ada aksi Response** — disengaja. Tanpa Response, Power Automate langsung
  membalas `202 Accepted` dan EcoSystem tidak ikut menunggu flow selesai.

---

## Uji sebelum disambungkan ke EcoSystem

Di halaman flow → **Test** → **Manually**, atau kirim payload contoh dari terminal
(contohnya ada di `../power-automate-integration.md` bagian 4.2 / 5.2 / 6.2):

```bash
curl -X POST "<HTTP POST URL>" \
  -H "Content-Type: application/json" \
  -H "X-EcoSystem-Secret: <POWER_AUTOMATE_SECRET>" \
  -d @payload-contoh.json
```

Uji juga jalur negatifnya — kirim tanpa header `X-EcoSystem-Secret`. Run harus
berakhir **Cancelled**, bukan mengirim kartu.

---

## Catatan lapangan — hasil pengerjaan sungguhan (2-8 Sep 2026)

Solution berhasil di-import ke tenant PT Eclectic, dan **flow 1 sudah terbukti
jalan end-to-end**: email masuk ke `support@eclectic.co.id` → staging ticket →
greeting terkirim sebagai BALASAN di thread yang sama.

Lima hal berikut ditemukan saat pengerjaan dan berlaku untuk flow mana pun yang
di-import dari paket ini:

1. **Flow masuk dalam keadaan OFF.** Disengaja (`StateCode 0`). Memanggil URL
   trigger sebelum dinyalakan menghasilkan `HTTP 400` dengan pesan
   `WorkflowTriggerIsNotEnabled`. Nyalakan lewat **Turn on** setelah konfigurasi
   selesai.

2. **Parameter aksi konektor TIDAK ikut terbawa.** Aksinya sendiri muncul dengan
   nama yang benar (mis. *Balas greeting ke pengirim*), tetapi seluruh field-nya
   kosong dan harus diisi manual. Nama operasi (`ReplyToV3`,
   `PostCardToConversation`) sudah benar — yang meleset hanya nama parameternya.

3. **Kotak *Request Body JSON Schema* tidak selalu bisa diketik**, dan schema
   memang tidak ikut terbawa import. **Biarkan kosong** — trigger tetap menerima
   payload apa pun tanpa schema, dan justru lebih aman karena tidak ada validasi
   tipe yang bisa menolak field bernilai kosong. Konsekuensinya: daftar *dynamic
   content* ikut kosong, jadi setiap nilai diambil lewat tab **Expression**.

4. **Expression harus DIKETIK, jangan ditempel.** Menyalin dari chat/dokumen
   sering mengubah apostrof lurus `'` menjadi melengkung `'`, dan Power Automate
   menolaknya dengan *"The expression is invalid"*. Trik yang berhasil: masukkan
   `triggerBody()` dulu (pasti diterima), lalu klik token itu dan ketik
   lanjutannya di ujung kanan.

5. **Connection reference hasil import kosong.** Daftarnya muncul dengan nama
   `<Konektor> EcoSystemTeamsIntegration-xxxxx` tetapi berstatus *Invalid
   connection*. Jangan dipilih — klik **+ New connection reference** lalu buat
   koneksi baru.

   Untuk flow 1, koneksi Outlook **wajib** atas nama `support@eclectic.co.id`
   (nilai `MS_SENDER_EMAIL`): `graph_message_id` dari EcoSystem hanya berlaku di
   dalam mailbox itu. Field *Original Mailbox Address* dibiarkan kosong selama
   koneksinya memang mailbox tersebut.

Tiga hal berikut ditemukan menyusul, saat membuat flow 4 dan mengubah flow 2
(7-8 September 2026). Ketiganya berlaku untuk flow yang **dibuat manual**, bukan
hasil import — jadi relevan saat membuat flow 3 nanti:

6. **Setelan *Who can trigger the flow?* harus `Anyone`.** Flow baru lahir dengan
   *Any user in my tenant*, yang memakai autentikasi Entra ID — dan URL trigger
   yang diterbitkannya berhenti di `?api-version=1`, tanpa `sp`, `sv`, dan `sig`.
   EcoSystem memanggil dengan POST biasa berisi header rahasia, tanpa token
   OAuth, jadi akan ditolak **401**. Ubah ke **Anyone** lalu **Save**; URL akan
   terbit ulang lengkap dengan `&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=...`.
   Flow 1 dan 2 tidak kena karena dibuat dengan setelan lama.

   Bandingkan URL barunya dengan yang sudah ada di `.env`: kalau tidak berakhir
   dengan `&sig=`, ia belum benar. Salin lewat **ikon salin** di sebelah field,
   bukan dengan memblok teksnya — kotaknya sempit dan yang tersalin hanya bagian
   yang terlihat.

7. **Durasi run adalah alat baca pertama, bukan status.** Run **Succeeded**
   berdurasi 70-125 ms berarti flow keluar lewat cabang **False** yang kosong —
   hampir selalu Condition pemeriksa secret — dan **bukan** berarti berhasil. Run
   yang benar-benar menyentuh Teams butuh hitungan **detik** (flow 4 yang sehat:
   ±12 detik). `Succeeded` di Power Automate hanya berarti "tidak ada aksi yang
   error"; cabang kosong memenuhi syarat itu dengan sempurna.

   Kalau baris run history tidak bisa diklik, jangan buang waktu: buka designer →
   **Test → Manually**, lalu picu dari EcoSystem. Hasilnya tergambar langsung di
   kanvas, lengkap per aksi, dan tiap kotak bisa dibuka untuk melihat Inputs.

   Waspadai juga membuka satu flow di beberapa tab sekaligus atau berpindah
   antara designer lama dan baru — perubahan bisa terlihat benar di layar padahal
   yang berjalan versi lama. Cek stempel **Modified** di halaman detail flow;
   itu satu-satunya konfirmasi bahwa suntingan Anda sudah tersimpan.

8. **Aksi *Create a channel* hanya punya dua advanced parameter:** *Description*
   dan *Membership Type*. Tidak ada properti "favorite by default", jadi channel
   tiket **tidak bisa** dibuat otomatis tampil di daftar channel anggota lewat
   konektor — itu hanya ada di Microsoft Graph, yang berarti aksi HTTP, yang
   berarti Premium.
### Nilai yang diisikan manual di flow 1

| Field | Isi |
|---|---|
| Message Id | `triggerBody()?['staging']?['graph_message_id']` |
| Body — token setelah "Halo" | `triggerBody()?['staging']?['sender_name']` |
| Body — token di dalam tanda kutip | `triggerBody()?['staging']?['subject']` |
| Reply All | kosong (bawaan `False`) |
| Original Mailbox Address | kosong |

---

## Konfigurasi flow 2 — EcoSystem - Ticket Validated (2 Sep 2026)

> **ARSIP (9 Sep 2026).** Bentuk channel diganti bentuk group chat; panduan yang
> berlaku sekarang ada di *Konfigurasi flow 5*. Bagian ini disimpan utuh karena
> flownya masih ada di Power Automate dan bentuk ini bisa dipilih lagi sewaktu-
> waktu — tapi **EcoSystem sudah tidak mengirim blok `channel`**, jadi
> menghidupkannya kembali menuntut perubahan kode lebih dulu (kembalikan
> `ticketChannelPayload()` + `channelMembers()` dari commit `d67d446`).

Urutan yang mengikuti kelima catatan lapangan di atas; kerjakan dari atas ke
bawah dalam satu sesi **Edit**.

### 1. Trigger

Biarkan **Request Body JSON Schema** kosong (catatan 3). Konsekuensinya semua
nilai diambil lewat tab **Expression**, bukan dynamic content.

### 2. Kartu **Cek secret EcoSystem** (Condition)

| Sisi | Isi |
|---|---|
| Kiri | `triggerOutputs()?['headers']?['X-EcoSystem-Secret']` (sudah terisi) |
| Kanan | nilai `POWER_AUTOMATE_SECRET` dari `.env` — ganti teks `GANTI_DENGAN_POWER_AUTOMATE_SECRET` |

Sama persis seperti flow 1; kalau sisi kiri ikut kosong setelah import,
**ketik** ekspresinya (catatan 4: jangan tempel, apostrof bisa berubah).

### 3. Aksi **Buat channel tiket** (Teams — Create a channel)

**Diputuskan 2 Sep 2026 (revisi):** tujuannya **channel baru per tiket di dalam
satu team**, bukan group chat. Permintaan atasan: seluruh channel tiket berkumpul
rapi di team **Support MO Team**.

Koneksi: **+ New connection reference** (catatan 5 — jangan pilih yang berstatus
*Invalid connection*). Akun koneksi harus **anggota team tujuan**, karena ia yang
membuat channel-nya.

| Field | Isi |
|---|---|
| Team | pilih dari dropdown, mis. *Support MO Team* |
| Channel name / Display name | `triggerBody()?['channel']?['name']` |
| Description | `triggerBody()?['channel']?['description']` |

Nama channel **sudah dirapikan EcoSystem**: dipotong 50 karakter (batas Teams,
nomor tiket selalu utuh di depan, subjek yang dipangkas) dan dibersihkan dari
karakter terlarang `# % & * { } \ : < > ? / + | ~`. Subject utuhnya tetap terbaca
di Description, jadi tidak ada informasi yang hilang.

Aksi ini mengembalikan **Channel Id** — dipakai dua aksi berikutnya.

### 4. Aksi **Kartu tiket ke channel** (Teams — Post card in a chat or channel)

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Channel** |
| Team | sama dengan langkah 3 |
| Channel | **Enter custom value** → **Channel Id** keluaran langkah 3 |
| Adaptive Card | isi berkas [`cards/flow-2-ticket-validated-kartu-tiket-ke-channel.json`](cards/flow-2-ticket-validated-kartu-tiket-ke-channel.json) |

Salin kartunya **dari berkas itu**, bukan dari chat atau dokumen — isinya sengaja
ASCII murni supaya apostrof di dalam `['ticket']` tetap lurus.

### 5. Anggota channel + mention lead modul

Dua hal yang berbeda dan sering tertukar:

* **siapa yang bisa MEMBACA channel** — di Teams, *standard channel* tidak punya
  daftar anggota sendiri; yang bisa membacanya adalah anggota **team**-nya. Jadi
  "menambahkan orang ke channel tiket" pada praktiknya = **Add a member to a
  team**;
* **siapa yang DAPAT NOTIFIKASI** — hanya orang yang di-@mention.

Karena itu daftarnya dua: `channel.member_emails` (dimasukkan ke team) dan
`lead_emails` (di-mention). Yang pertama memuat yang kedua.

**Isi `channel.member_emails` (dirakit EcoSystem, sudah dedup & tersaring):**

| Sumber | Asalnya |
|---|---|
| `module_lead` | Module Lead modul tiket — sama dengan `lead_emails` |
| `role` | pemegang role di `POWER_AUTOMATE_TEAMS_MEMBER_ROLE_IDS`, default `5,6` = **Delivery Support Head** dan **Delivery Support Service Helpdesk** (6 orang per 7 Sep 2026, setelah `support01@` dikecualikan) |

Alamat di `POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS` dibuang dari keduanya, begitu
juga orang yang tidak punya Email (Work) maupun email login, dan employee yang
`is_active = 0` (mantan helpdesk tidak ikut ditarik ke channel tiket baru).

> **`POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS` TIDAK ikut di jalur channel.** Dua alasan
> keberadaannya khusus group chat: menjaga peserta ≥3 orang (syarat Teams agar
> grup boleh diberi nama) dan jadi penerima cadangan untuk modul tanpa Module
> Lead. Channel tidak punya syarat jumlah, dan anggota channel tidak menerima
> notifikasi apa pun — yang memberi notifikasi hanya @mention atas `lead_emails`
> — jadi di sini knob itu hanya akan menambah orang diam-diam. Peran "selalu ada
> yang mengawasi" dipegang role penjaga.

Keanggotaan role dibaca dari tabel pivot **`employee_role_assignment`**
(`employee_id`, `role_id`) — bukan kolom di tabel `employee`. Resolusinya
dikerjakan di EcoSystem (`PowerAutomateService::roleMembers()`), bukan lewat aksi
tambahan di designer: pemetaan role → orang adalah pengetahuan EcoSystem, dan
tiap aksi tambahan di flow berarti satu ekspresi rapuh lagi yang harus diketik
tangan.

**5a. Initialize variable** (Variables) — **di level teratas flow**, yaitu antara
trigger dan kartu *Cek secret EcoSystem*. Power Automate menolak aksi ini di
dalam Condition atau Apply to each; itu batasan produk, bukan pilihan gaya.

| Field | Isi |
|---|---|
| Name | `mentions` |
| Type | String |
| Value | (kosong) |

**5b. Apply to each anggota channel** atas
`triggerBody()?['channel']?['member_emails']` — namai loopnya *Tambah anggota
channel*. Isinya **dua** aksi:

1. **Microsoft Teams → Add a member to a team**
   * *Team*: Support MO Team (sama dengan langkah 3)
   * *A member (user)*: `items('Tambah_anggota_channel')`
   * Aksi ini **gagal kalau orangnya sudah anggota** — konektor Teams tidak punya
     aksi "cek keanggotaan", jadi kegagalan itulah yang diserap aksi berikutnya.
     Akun koneksi harus **Owner** team, bukan sekadar member.
2. **Data Operations → Compose** (namai mis. *Abaikan hasil tambah anggota*)
   * *Inputs*: bebas, mis. `ok`
   * **Settings → Run after** pada aksi ini (di designer BARU letaknya di tab
     *Settings*, bukan menu ⋯): centang **is successful**, **has failed**, dan
     **has timed out**. Inilah yang membuat "sudah anggota" berperilaku seperti
     skip — tanpa ini, satu duplikat membuat seluruh run ditandai Failed dan
     kartu tiket tidak pernah terkirim.

> Karena role penjaga (Delivery Support Head + Helpdesk) hampir selalu **sudah**
> anggota team, loop ini akan sering berisi 7-8 kegagalan yang diserap. Itu
> normal: run tetap **Succeeded**, dan biayanya ±8 aksi per tiket — masih jauh di
> bawah kuota ±6.000 aksi/24 jam.

**5b-2. Apply to each lead modul** atas `triggerBody()?['lead_emails']` — namai
loopnya *Mention lead*. Isinya **dua** aksi (penambahan ke team sudah dikerjakan
loop 5b, jadi tidak diulang di sini):

1. **Microsoft Teams → Get an @mention token for a user**
   * *User*: `items('Mention_lead')`
   * Lead WAJIB anggota team tujuan; loop 5b yang menjamin syarat itu terpenuhi,
     jadi **5b harus berada di ATAS 5b-2**.
2. **Variables → Append to string variable** — *Name* `mentions`, *Value* =
   keluaran token di aksi 1 diikuti satu spasi.

> Kalau lebih suka pengecekan sungguhan ketimbang menyerap error: team di Teams
> memakai id yang sama dengan Microsoft 365 Group-nya, jadi konektor **Office 365
> Groups → List group members** bisa dipakai untuk memeriksa lebih dulu. Lebih
> banyak aksi, butuh izin tambahan, hasil akhirnya sama — pola serap-error di
> atas yang dipakai.
**5c. Condition** — sisi kiri `length(triggerBody()?['lead_emails'])`,
operator **is greater than**, sisi kanan `0`.

Cabang **If yes**: **Microsoft Teams → Post message in a chat or channel**

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Channel** |
| Team / Channel | sama seperti langkah 4 (Channel Id dari langkah 3) |
| Message | `@{variables('mentions')}` + teks, mis. `mohon tentukan PIC atau ambil tiket ini.` |

Condition ini yang menjaga flow tetap diam untuk modul yang belum punya Module
Lead — tanpa itu, pesan tanpa mention tetap terkirim.

> **Batas yang perlu diingat:** satu team menampung **1.000 channel** (200 di
> antaranya private) dan channel tidak terhapus sendiri saat tiket closed. Dengan
> 62-129 tiket/bulan, team akan penuh dalam ±10 bulan — siapkan kebiasaan
> mengarsipkan/menghapus channel tiket yang sudah selesai.

> Blok `chat` di payload (`topic`, `members_csv`) **tetap dikirim** dan tidak
> dipakai jalur channel. Ia dipertahankan supaya jalur group chat bisa dihidupkan
> lagi tanpa perubahan kode.
>
> **Berubah 7 Sep 2026:** `_EXCLUDE_MEMBERS` kini berpengaruh pada **kedua**
> jalur — ia ikut menyaring `channel.member_emails` di langkah 5b.
> `POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS` dan `_INCLUDE_VALIDATOR` tetap **khusus
> jalur group chat**; keduanya tidak menyentuh keanggotaan channel.

### 6. Save → salin URL trigger → uji

```dotenv
POWER_AUTOMATE_FLOW_TICKET_VALIDATED=<HTTP POST URL flow 2>
```

```bash
php artisan optimize:clear
```

**Turn on** flow-nya (catatan 1 — sebelum dinyalakan, pemanggilan URL menghasilkan
`WorkflowTriggerIsNotEnabled`), lalu uji dengan payload nyata:

```bash
curl -X POST "<HTTP POST URL>" \
  -H "Content-Type: application/json" \
  -H "X-EcoSystem-Secret: <POWER_AUTOMATE_SECRET>" \
  -d @flow-2-payload.json
```

Isi `flow-2-payload.json` bisa disalin dari contoh di
[../power-automate-integration.md](../power-automate-integration.md) bagian 5.2,
atau dibangkitkan dari tiket sungguhan lewat `php artisan tinker` memakai
`PowerAutomateService::ticketPayload()` + `moduleLeads()` + `leadEmails()`.

Uji negatifnya: kirim tanpa header `X-EcoSystem-Secret` — run harus berakhir
**Cancelled** dan tidak ada kartu terkirim.

### Kalau lead tidak ter-mention padahal channel tiket terbentuk

`lead_emails` kosong. Penyebabnya salah satu dari:

* **tiket lama** — modulnya tersimpan sebagai teks bebas yang tidak cocok dengan
  baris mana pun di `modules` (mis. `FICO`, `Modul FI`, `HCM; ABAP`,
  `SAP Connection`). Sejak 2 Sep 2026 modal validasi memakai dropdown Master →
  Module sehingga tiket baru selalu membawa `module_id`; tiket lama bisa
  diperbaiki lewat Additional Info di halaman tiket;
* modul terdaftar tapi **belum punya Module Lead** — per 2 Sep 2026: CO, HCM,
  DATA MIGRATION, DEVELOPER, N/A, OCM, PMO, SALESFORCE, SIG, SYSTEM INTEGRATION;
* lead ada tapi tidak punya Email (Work) maupun email login.

Cek isinya:

```bash
php artisan employees:backfill-email-work --dry-run
```

Channel tiket tetap terbentuk dan kartunya tetap terkirim; hanya pesan @mention
yang dilewati Condition di langkah 5c. Itu memang perilaku yang diinginkan.

---

## Kalau import solution juga gagal

Paket ini dibangun dari spesifikasi format solution dan **belum pernah diuji
import ke tenant PT Eclectic** (tidak ada akses Power Automate dari sisi
pengembangan). Yang sudah diverifikasi lokal: seluruh XML well-formed, seluruh
JSON parse bersih, dan zip memakai pemisah `/`.

Bagian yang paling mungkin berbeda antar tenant adalah nama operasi internal
konektor — `PostCardToConversation` (Teams) dan `ReplyToV3` (Outlook).

Cara tercepat memperbaikinya kalau importer menolak atau ada aksi yang tampil
rusak:

1. **Solutions** → **+ New solution** → beri nama apa saja → **Create**.
2. Di dalam solution itu: **+ New** → **Automation** → **Cloud flow** →
   **Instant**, trigger *"When an HTTP request is received"*.
3. Tambahkan **satu** aksi Teams *Post card in a chat or channel* dan **satu**
   aksi Outlook *Reply to email (V3)*, isi seadanya, **Save**.
4. Kembali ke **Solutions** → ⋯ pada solution itu → **Export solution** →
   **Unmanaged** → unduh zip-nya.
5. Kirimkan zip itu — nama operasi, nama parameter, dan bentuk
   `connectionReferences` yang persis dipakai tenant Anda ada di dalamnya, dan
   paket di folder ini bisa disesuaikan agar cocok.

Jalur manual (membangun flow dengan klik satu per satu di designer) selalu
tersedia dan dijamin bekerja:
[../power-automate-integration.md](../power-automate-integration.md) bagian 3–6.

---

## Konfigurasi flow 4 — EcoSystem - Ticket Member Added (7 Sep 2026, terbukti jalan 8 Sep 2026)

> **ARSIP (9 Sep 2026).** Panduan yang berlaku sekarang ada di *Konfigurasi flow
> 6* (bentuk group chat). Sama seperti flow 2: flownya masih tersimpan, tapi
> payload EcoSystem tidak lagi membawa blok `channel`.

**Kapan dipanggil:** saat consultant di-assign ke satu tiket, yaitu (a) tombol
**Add Member** di halaman tiket dan (b) penetapan/penggantian **PIC**
(`ticket_lead_id`, lewat *Assign Ticket Lead* maupun edit tiket). Satu panggilan
= satu orang. Pelepasan member **tidak** memicu apa pun — orang yang pernah ikut
tetap bisa membaca riwayat channel.

**Yang tidak memicu flow ini:** update member massal (sync) dan orang yang tidak
punya Email (Work) maupun email login — tanpa email, konektor Teams tidak bisa
menemukan orangnya, jadi EcoSystem memilih tidak memanggil flow sama sekali
daripada membuatnya gagal separuh jalan (dicatat di log sebagai
`penambahan ke channel dilewati, employee tanpa email kerja`).

### Masalah kuncinya: flow tidak tahu channel id tiket

Flow 2 memang menerima Channel Id dari aksi *Create a channel*, tapi id itu hidup
hanya selama run flow 2. Menyimpannya berarti Power Automate harus memanggil
balik EcoSystem lewat aksi **HTTP** — dan aksi HTTP adalah konektor **PREMIUM**.

Jalan tanpa premium: **cari channelnya berdasarkan nama.** EcoSystem mengirim
`channel.name` yang dirakit oleh fungsi yang **sama persis** dengan yang dipakai
flow 2 saat membuat channel itu (`PowerAutomateService::ticketChannelPayload()` —
potong 50 karakter, buang karakter terlarang), jadi cocoknya exact-match, bukan
tebak-tebakan. `channel.number` ikut dikirim sebagai kunci cadangan: nomor tiket
tidak pernah berubah, sedangkan nama bisa meleset kalau subject tiket **diedit
setelah** channelnya terbentuk.

### 1. Trigger — When an HTTP request is received

**Request Body JSON Schema** dibiarkan kosong (catatan lapangan 3); semua nilai
diambil lewat tab **Expression**.

Ubah **Who can trigger the flow?** menjadi **Anyone** (catatan lapangan 6).
Dengan setelan bawaan *Any user in my tenant*, URL trigger tidak membawa `sig`
dan EcoSystem akan ditolak 401.

### 2. Condition **Cek secret EcoSystem**

Sama persis seperti flow 1-3: kiri
`triggerOutputs()?['headers']?['X-EcoSystem-Secret']`, kanan nilai
`POWER_AUTOMATE_SECRET`. Semua langkah berikutnya masuk cabang **If yes**.

### 3. Microsoft Teams → **List channels**

| Field | Isi |
|---|---|
| Team | *Support MO Team* (sama dengan flow 2) |

Satu aksi mengembalikan seluruh channel team (batas team = 1.000 channel), jadi
biayanya tetap satu request berapa pun jumlah channelnya.

### 4. Data Operations → **Filter array**

| Field | Isi |
|---|---|
| From | `body('List_channels')?['value']` |
| Kiri | `item()?['displayName']` |
| Operator | **is equal to** |
| Kanan | `triggerBody()?['channel']?['name']` |

> **Kalau ingin toleran terhadap subject yang diedit setelah validasi**, ganti
> baris kondisinya menjadi *is equal to* antara
> `startsWith(item()?['displayName'], triggerBody()?['channel']?['number'])` dan
> `true`. Cocoknya jadi berdasar nomor tiket saja — lebih tahan perubahan nama,
> tapi bisa mengembalikan lebih dari satu channel kalau ada nomor yang bersarang.
> Pilihan default di atas (exact-match nama) yang dipakai.

### 5. Condition **Channel ketemu**

Kiri `length(body('Filter_array'))`, operator **is greater than**, kanan `0`.

Cabang **If no**: biarkan kosong, atau isi satu **Terminate** dengan status
*Succeeded* dan pesan `Channel tiket belum ada`. Ini kejadian yang wajar untuk
tiket yang dibuat **sebelum** flow 2 dinyalakan — jangan sampai menandai run
sebagai Failed.

Cabang **If yes** berisi langkah 6-8. Channel idnya:

```
first(body('Filter_array'))?['id']
```

### 6. Microsoft Teams → **Add a member to a team**

| Field | Isi |
|---|---|
| Team | *Support MO Team* |
| A member (user) | `triggerBody()?['person']?['email']` |

Inilah aksi yang sebenarnya "menambahkan orang ke channel": standard channel
tidak punya daftar anggota sendiri, yang menentukan akses adalah keanggotaan
team. Akun koneksi harus **Owner** team.

### 7. Data Operations → **Compose** (penyerap)

*Inputs* bebas, mis. `ok`. **Settings → Run after** aksi ini: centang **is
successful**, **has failed**, **has timed out**. Consultant yang sudah jadi
anggota team akan membuat aksi 6 gagal; tanpa penyerap ini seluruh run ditandai
Failed dan kartunya tidak pernah terkirim. Kasus "sudah anggota" ini justru yang
paling sering terjadi.

### 8. Microsoft Teams → **Post card in a chat or channel**

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Channel** |
| Team | *Support MO Team* |
| Channel | **Enter custom value** → `first(body('Filter_array'))?['id']` |
| Adaptive Card | isi [`cards/flow-4-ticket-member-added-kartu-anggota-baru.json`](cards/flow-4-ticket-member-added-kartu-anggota-baru.json) |

Ingin orangnya benar-benar mendapat notifikasi, bukan sekadar kartu? Tambahkan
**Get an @mention token for a user** (*User*: `triggerBody()?['person']?['email']`)
**sesudah** aksi 7 — urutannya penting, orangnya harus sudah jadi anggota team —
lalu satu **Post message in a chat or channel** berisi
`@{body('Get_an_@mention_token_for_a_user')}` diikuti teks pengantar.

### 9. Save → salin URL trigger → uji

```dotenv
POWER_AUTOMATE_FLOW_TICKET_MEMBER_ADDED=<HTTP POST URL flow 4>
```

```bash
php artisan optimize:clear
```

**Turn on** flownya, lalu uji dengan payload dari tiket sungguhan:

```bash
php artisan tinker --execute="
\$s = app(App\Services\PowerAutomateService::class);
\$t = App\Models\Ticket::find(<TICKET_ID>);
file_put_contents('flow-4-payload.json', json_encode(
    \$s->ticketMemberPayload(\$t, \$s->employeeContact(<EMPLOYEE_ID>), 'member', ['name' => 'Uji']),
    JSON_PRETTY_PRINT
));"

curl -X POST "<HTTP POST URL>" \
  -H "Content-Type: application/json" \
  -H "X-EcoSystem-Secret: <POWER_AUTOMATE_SECRET>" \
  -d @flow-4-payload.json
```

Pakai `<TICKET_ID>` yang channelnya **memang sudah ada** di Support MO Team,
supaya jalur If yes yang teruji. Uji negatifnya: kirim ulang untuk tiket yang
belum punya channel — run harus **Succeeded** tanpa mengirim apa pun.

### Kalau nanti tenant punya lisensi Premium

Jalur yang lebih rapi terbuka: flow 2 memanggil balik EcoSystem lewat aksi
**HTTP** untuk menyimpan Channel Id ke kolom baru di tabel `ticket`, dan flow 4
memakai id itu langsung — langkah 3, 4, dan 5 di atas hilang seluruhnya
(3 aksi lebih sedikit per event, dan kebal terhadap perubahan nama channel).
Perubahan di sisi EcoSystem: satu migration kolom `teams_channel_id`, satu
endpoint bertanda secret yang sama, dan `ticketMemberPayload()` mengirim id itu
alih-alih `channel.name`. Belum dikerjakan — cek dulu lisensinya lewat
**Power Platform admin center → Environments → <env> → Settings → Users +
permissions → Licenses**, atau lebih cepat: buka designer, cari aksi **HTTP**,
dan lihat apakah ada lencana **Premium** di sampingnya (lencana muncul untuk
semua orang; yang menentukan adalah apakah flow tetap jalan setelah disimpan).


---

## Siapa saja yang masuk group chat tiket (11 Sep 2026)

Daftar peserta dirakit **di EcoSystem**, bukan di Power Automate, dan tiba di
flow sebagai satu string siap pakai (`chat.members_csv`, pemisah titik-koma).
Flow tidak pernah memutuskan siapa pun — ia hanya menempelkan string itu ke field
*Members to add*. Itu disengaja: tiap keputusan yang dipindah ke designer berarti
satu ekspresi yang harus diketik tangan dan tidak bisa diuji (catatan lapangan 4).

| Kelompok | Dari mana | Sama untuk semua tiket? |
|---|---|---|
| **Module Lead** | `module_leads` untuk `module_id` tiket | tidak — per modul |
| **Tim Delivery Support** | delivery support yang dipilih helpdesk saat validasi: Delivery Owner, Support Manager (bisa >1), CO PM, Support Admin | tidak — per tiket |
| **Role penjaga** | pemegang role di `POWER_AUTOMATE_TEAMS_MEMBER_ROLE_IDS` (default 5 = Delivery Support Head, 6 = Delivery Support Service Helpdesk) | ya |
| **Anggota tetap** | `POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS` | ya |
| **Validator** | akun yang menekan Validate, hanya bila `POWER_AUTOMATE_TEAMS_INCLUDE_VALIDATOR=true` | — |

**Tim Delivery Support baru ditambahkan 11 September 2026** (sebelumnya grup hanya
berisi lead modul + role penjaga, sehingga pemilik delivery-nya sendiri tidak ada
di dalam grup tiket miliknya).

Tiga hal yang perlu diketahui tentang kelompok itu:

* **Tiket tidak punya kolom `delivery_support_id`.** Kaitannya lewat tabel
  `delivery_support_activities` — memilih delivery support di modal validasi
  membuat satu baris activity di sana. Karena EcoSystem membacanya dari tabel itu
  (bukan dari request), tiket yang di-assign ke delivery support **belakangan**
  juga ikut terbaca benar.
* **Support Manager bisa lebih dari satu** dan tersimpan di tabel pivot
  `delivery_support_managers`. Kolom lama `delivery_support.support_manager_id`
  masih ada di skema tapi sudah tidak dipakai dan isinya bisa basi — jangan
  dipakai sebagai acuan saat memverifikasi lewat SQL.
* **Sales tidak ikut.** `sales_id` sengaja dilewati: Sales bukan pelaksana tiket.
  Kalau suatu saat perlu, tambahkan di `PowerAutomateService::deliverySupportTeam()`.

Semua alamat lalu di-dedup **case-insensitive**, dibuang yang ada di
`POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS`, dan dipotong di
`POWER_AUTOMATE_TEAMS_MAX_MEMBERS` (default 20 = batas konektor Teams).

> **Batas 20 peserta itu keras.** Kelebihan satu orang membuat aksi *Create a
> chat* menolak **seluruh** permintaan dengan `BadRequest`, jadi EcoSystem
> memotongnya lebih dulu dan menulis `PowerAutomate: peserta group chat melebihi
> batas` ke `laravel.log`. Yang dipotong adalah yang paling belakang — anggota
> tetap dan role penjaga — bukan lead modul atau tim Delivery Support. Hitung
> anggarannya: 2 lead + 4 tim support + 2 role penjaga + anggota tetap, sisanya
> baru slot consultant yang ditambahkan flow 6. Kalau grup sering penuh, kecilkan
> `POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS` lebih dulu, bukan menaikkan batasnya.

Melihat hasil rakitannya tanpa mengirim apa pun:

```bash
php artisan tinker --execute="
\$s = app(App\Services\PowerAutomateService::class);
\$t = App\Models\Ticket::find(<TICKET_ID>);
\$p = \$s->ticketPayload(\$t);
print_r(\$s->ticketChatPayload(\$p, \$s->leadEmails(\$s->moduleLeads(\$p['module_id'], \$p['module']))));
print_r(\$s->deliverySupportPayload(\$t->ticket_id));"
```

---

## Konfigurasi flow 5 — EcoSystem - Ticket Validated (Group Chat)

**Varian group chat dari flow 2.** Dipicu kejadian yang sama persis (staging
ticket di-approve) dengan payload yang sama persis; bedanya flow ini memakai blok
`chat`, bukan blok `channel`. Bentuk ini pernah dibuat dan **terbukti jalan** 2
September 2026 sebelum sempat diganti bentuk channel — langkah di bawah adalah
bentuk itu, ditambah tim Delivery Support yang kini ikut di `chat.members_csv`.

Buat sebagai **flow baru**; jangan mengubah flow 2 (status ARSIP).

> ### ⚠️ Diubah 17 September 2026 — grup kini dibuat EcoSystem, bukan flow ini
>
> Sejak [sinkronisasi chat Teams](../teams-chat-sync-design.md) langkah 3,
> EcoSystem membuat group chat sendiri lewat Graph dan **memegang `chat_id`**-nya.
> Kalau `Create a chat` di flow ini tetap jalan, satu tiket akan punya **dua** grup.
>
> Struktur flow sesudah diubah (langkah 4-7 di bawah tetap berlaku, hanya
> posisinya berpindah):
>
> ```
> Init "mentions"  (String, kosong)
> Init "chatId"    (String, kosong)      ← BARU, wajib di level teratas
> Condition "Cek Secret Ecosystem"
>   └─ True:
>        Condition "Chat sudah dibuat EcoSystem"          ← BARU
>          ├─ True : Set variable chatId = triggerBody()?['chat']?['id']
>          └─ False: Create a chat  →  Set variable chatId = Conversation ID
>        Post card in a chat or channel   → Group chat = variables('chatId')
>        Apply to each lead_emails (mention)
>        Condition "Ada lead modul" → Post message → Group chat = variables('chatId')
> ```
>
> Ekspresi Condition barunya — **ketik, jangan tempel** (catatan lapangan 4):
>
> ```
> length(coalesce(triggerBody()?['chat']?['id'], ''))   is greater than   0
> ```
>
> **Kenapa memeriksa `chat.id`, bukan `chat.created_by_ecosystem`.** Payload
> membawa keduanya, tapi `created_by_ecosystem` bertipe boolean, dan
> membandingkannya dengan teks `true` yang diketik di kotak kanan designer adalah
> jebakan klasik Power Automate: tampak benar, selalu False. Memeriksa panjang
> `chat.id` menghindarinya, dan polanya sama dengan Condition `Ada lead modul`
> yang sudah terbukti jalan di flow ini.
>
> **Kenapa lewat variabel, bukan menunjuk output `Create a chat` langsung.**
> Begitu `Create a chat` pindah ke dalam cabang, aksi di luar cabang tidak boleh
> lagi bergantung padanya. Variabel `chatId` membuat `Post card` dan
> `Post message` tidak peduli grupnya datang dari EcoSystem atau dari flow.
>
> **Urutan mengerjakannya penting:** ganti dulu field **Group chat** pada
> `Post card` dan `Post message` menjadi `variables('chatId')`, BARU pindahkan
> `Create a chat` ke cabang False. Terbalik, akan ada rujukan menggantung dan Save
> ditolak.
>
> **Terbukti jalan 17 September 2026.** Diuji dengan payload ber-`chat.id` dan
> `members_csv` SENGAJA dikosongkan: kartu mendarat di grup yang id-nya dikirim,
> dan `Create a chat` tidak jalan — kalau ia jalan, members kosong pasti
> menggagalkannya sebelum kartu sempat terkirim. Selama `TEAMS_SYNC_ENABLED=false`,
> `chat.id` tidak pernah dikirim dan flow berjalan persis seperti sebelumnya.
>
> Catatan lapangan dari pengerjaannya: kegagalan pertama ternyata karena
> **connection Teams ke `support@eclectic.co.id` terputus** — gejalanya flow
> membalas 202 seperti biasa tapi tidak ada apa pun yang sampai ke Teams.
> Periksa status koneksi sebelum mencurigai logika flow.

### Sebelum mulai — siapkan empat hal

1. **Akun koneksi Teams.** Harus akun `@eclectic.co.id` yang punya mailbox
   sungguhan. Grup yang dibuat flow akan berisi akun ini sebagai pembuat, jadi
   pilih akun layanan yang memang boleh terlihat di semua grup tiket.
2. **Isi `POWER_AUTOMATE_SECRET`** di `.env` — nilai yang sama akan diketik di
   Condition langkah 3.
3. **Isi `POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS`** dengan minimal
   `admin@eclectic.co.id,support01@eclectic.co.id` **plus alamat akun koneksi
   Teams flow ini** (mis. `support@eclectic.co.id`). Dua yang pertama punya email
   di database tapi **tidak punya mailbox M365**; yang ketiga punya mailbox tapi
   sudah otomatis jadi peserta sebagai pemilik koneksi. Ketiganya sama-sama
   menggagalkan *Create a chat* seluruhnya — yang pertama dengan alamat tak
   dikenal, yang terakhir dengan `Duplicate chat members`.
4. **`POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS` boleh dikosongkan.** Gunanya menjamin
   peserta ≥3 (Teams hanya mengizinkan pemberian nama grup untuk group chat ≥3
   orang) dan tetap ada penerima saat modul tiket belum punya Module Lead — dua
   syarat yang sudah dipenuhi 6 pemegang role penjaga + akun koneksi. Isi hanya
   kalau role penjaga dikosongkan, dan **jangan** dengan alamat akun koneksi.

### 0. Buat flow-nya

1. Buka **Power Automate** → pastikan **Environment** kanan atas =
   **PT ECLECTIC CONSULTING**.
2. Sidebar kiri → **My flows** → **+ New flow** → **Instant cloud flow**.
3. *Flow name*: `EcoSystem - Ticket Validated (Group Chat)`.
4. Di daftar trigger, cari dan pilih **When an HTTP request is received** →
   **Create**.

### 1. Trigger — When an HTTP request is received

| Field | Isi |
|---|---|
| Request Body JSON Schema | **biarkan kosong** (catatan lapangan 3) |
| Who can trigger the flow? | **Anyone** |

*Who can trigger* ada di **Settings** kartu trigger (ikon ⚙ / menu ⋯ →
*Settings*). **Wajib `Anyone`** — bawaannya *Any user in my tenant*, yang
menerbitkan URL tanpa `sp`/`sv`/`sig` sehingga EcoSystem kena **401** (catatan
lapangan 6).

Schema dibiarkan kosong berarti **tidak ada dynamic content sama sekali**: semua
nilai di langkah berikutnya diambil lewat tab **Expression**, dan harus
**diketik, bukan ditempel** (catatan lapangan 4).

**Save** sekarang juga — URL trigger baru terbit setelah save pertama.

### 2. Initialize variable — `mentions`

| Field | Isi |
|---|---|
| Name | `mentions` |
| Type | String |
| Value | *(kosong)* |

**Harus di level teratas flow**, tepat sesudah trigger: Power Automate menolak
*Initialize variable* di dalam Condition maupun Apply to each. Variabel ini
menampung token @mention lead modul yang dikumpulkan di langkah 6.

### 3. Condition — Cek secret EcoSystem

| Sisi | Isi |
|---|---|
| Kiri (Expression) | `triggerOutputs()?['headers']?['X-EcoSystem-Secret']` |
| Operator | **is equal to** |
| Kanan | nilai `POWER_AUTOMATE_SECRET` **diketik apa adanya** |

Seluruh langkah 4-7 masuk cabang **If yes**. Cabang **If no** dibiarkan kosong.

> Cabang False yang kosong inilah sumber salah baca paling sering: run yang
> ditolak di sini tetap berstatus **Succeeded**. Bedakan dari durasinya —
> 70-125 ms berarti keluar lewat False, run yang benar-benar menyentuh Teams
> butuh hitungan detik (catatan lapangan 7).

### 4. Microsoft Teams → **Create a chat**

| Field (label di designer) | Isi |
|---|---|
| Members to add | `triggerBody()?['chat']?['members_csv']` |
| Title | `triggerBody()?['chat']?['topic']` |

Perhatikan labelnya: **"Members to add"** (bukan *Members*) dan **"Title"**
(bukan *Topic*). Isi `members_csv` sudah dirakit EcoSystem — lihat tabel peserta
di atas — sudah dedup, sudah bersih dari alamat terlarang, dan sudah dipotong di
batas 20.

`chat.topic` berbentuk `26080149 - Standard Cost Type S salah harga`. Ini bukan
sekadar nama: **flow 6 mencari grupnya lagi dengan mencocokkan string ini persis**,
jadi jangan menambahkan awalan/akhiran apa pun di designer.

> **Jebakan terbesar bentuk ini (2 run gagal `BadRequest` pada 2 Sep 2026).**
> Teams menolak **seluruh** permintaan kalau (a) pesertanya kurang dari 2 orang
> selain akun koneksi, atau (b) ada **satu** alamat yang bukan mailbox Microsoft
> 365. Dua knob peredamnya sudah disiapkan di langkah persiapan 3 dan 4.

> **Jebakan ketiga, terbukti 11 Sep 2026: `Duplicate chat members is specified in
> the request body`.** Akun **pemilik koneksi Teams** otomatis menjadi pembuat
> sekaligus peserta grup — Teams menambahkannya sendiri. Kalau alamat itu ikut
> lagi di `members_csv`, permintaannya ditolak seluruhnya sebagai anggota dobel.
>
> Ini muncul justru setelah koneksi flow dipindah ke `support@eclectic.co.id`
> (supaya grup tiket dibuat atas nama akun layanan, bukan akun pribadi), sementara
> alamat yang sama masih terdaftar di `POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS`.
>
> **Aturannya: alamat pemilik koneksi harus masuk
> `POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS`, bukan `_EXTRA_MEMBERS`.** Exclude
> menutup SEMUA jalur sekaligus — anggota tetap, Module Lead, role penjaga, tim
> Delivery Support, maupun validator — jadi alamat itu tidak bisa menyelinap lewat
> pintu lain kalau suatu saat orangnya terdaftar sebagai lead atau anggota tim.
>
> Konsekuensi lain yang menguntungkan: `_EXTRA_MEMBERS` boleh dikosongkan. Ia
> dulu ada untuk menjamin peserta ≥3 (syarat Teams agar grup boleh diberi nama),
> dan syarat itu kini sudah dipenuhi 6 pemegang role penjaga + akun koneksi.

### 5. Microsoft Teams → **Post card in a chat or channel**

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Group chat** |
| Chat | Chat Id dari aksi *Create a chat* |
| Adaptive Card | isi [`cards/flow-5-ticket-validated-group-chat.json`](cards/flow-5-ticket-validated-group-chat.json) |

Kartu flow 5 memuat dua baris yang tidak ada di kartu flow 2: **Delivery Support**
(`delivery_support.name`) dan **Tim Support** (`delivery_support.team_csv`, sudah
berupa teks `Budi (Delivery Owner), Ani (Support Manager)` — dirakit di EcoSystem
supaya kartunya tidak perlu Apply to each). Keduanya menampilkan `-` bila tiket
belum di-assign ke delivery support mana pun.

Salin kartunya dari berkas JSON itu, **bukan** dari chat atau dokumen: apostrof
di dalam ekspresi kartu rawan berubah melengkung dan Power Automate menolaknya.

### 6. Apply to each `lead_emails` — kumpulkan token mention

| Field | Isi |
|---|---|
| Select an output from previous steps | `triggerBody()?['lead_emails']` |

Dua aksi di dalam loop:

1. **Microsoft Teams → Get an @mention token for a user** — *User* =
   `items('Apply_to_each')`.
2. **Variables → Append to string variable** — *Name* `mentions`, *Value* =
   `@{body('Get_an_@mention_token_for_a_user')}` diikuti **satu spasi**.

Tidak ada aksi *Add a member* di sini: pesertanya sudah ditentukan saat chat
dibuat di langkah 4. Loop ini murni soal siapa yang di-**mention** — dan mention
hanya untuk lead modul, karena merekalah yang diminta menindaklanjuti.

### 7. Condition — Ada lead modul → Post message

| Sisi | Isi |
|---|---|
| Kiri (Expression) | `length(triggerBody()?['lead_emails'])` |
| Operator | **is greater than** |
| Kanan | `0` |

Cabang **If yes** berisi satu **Microsoft Teams → Post message in a chat or
channel**:

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Group chat** |
| Chat | Chat Id dari aksi *Create a chat* |
| Message | `@{variables('mentions')}` diikuti teks pengantar, mis. "mohon tindak lanjuti tiket ini." |

Condition ini perlu karena beberapa modul belum punya Module Lead (per 2 Sep
2026: CO, HCM, DATA MIGRATION, DEVELOPER, N/A, OCM, PMO, SALESFORCE, SIG, SYSTEM
INTEGRATION). Tiket di modul itu tetap dapat grup + kartu; hanya baris mention
yang dilewati — tanpa Condition, *Post message* dengan pesan kosong akan gagal.

### 8. Save → salin URL trigger → pasang di `.env`

**Save**, buka kembali kartu trigger, salin **HTTP POST URL** lewat **ikon
salin** di sebelah field (jangan memblok teksnya — kotaknya sempit dan yang
tersalin hanya bagian yang terlihat). Pastikan URL-nya berakhir dengan `&sig=...`;
kalau tidak, *Who can trigger* belum `Anyone`.

```dotenv
POWER_AUTOMATE_ENABLED=true
POWER_AUTOMATE_FLOW_TICKET_VALIDATED=<HTTP POST URL flow 5>
```

```bash
php artisan optimize:clear
```

Variabelnya **sama** dengan yang dulu dipakai flow 2 — cukup ganti isinya dengan
URL flow 5. Pastikan flow 2 dalam keadaan **Off**.

Terakhir: **Turn on** flow 5. Memanggil URL sebelum flow dinyalakan menghasilkan
`HTTP 400 WorkflowTriggerIsNotEnabled` (catatan lapangan 1).

### 9. Uji

Pakai tiket sungguhan yang **sudah punya delivery support**, supaya baris Tim
Support di kartunya ikut terisi:

```bash
php artisan tinker --execute="
\$s = app(App\Services\PowerAutomateService::class);
\$t = App\Models\Ticket::find(<TICKET_ID>);
\$p = \$s->ticketPayload(\$t);
\$leads = \$s->moduleLeads(\$p['module_id'], \$p['module']);
\$emails = \$s->leadEmails(\$leads);
file_put_contents('flow-5-payload.json', json_encode([
    'ticket'           => \$p,
    'module_leads'     => \$leads,
    'lead_emails'      => \$emails,
    'chat'             => \$s->ticketChatPayload(\$p, \$emails),
    'delivery_support' => \$s->deliverySupportPayload(\$t->ticket_id),
], JSON_PRETTY_PRINT));"

curl -X POST "<HTTP POST URL flow 5>" \
  -H "Content-Type: application/json" \
  -H "X-EcoSystem-Secret: <POWER_AUTOMATE_SECRET>" \
  -d @flow-5-payload.json
```

Yang harus terlihat: grup baru bernama `<nomor> - <subject>` berisi lead modul +
tim Delivery Support + role penjaga, satu kartu tiket, dan satu pesan mention.
Periksa `flow-5-payload.json` dulu sebelum mengirim — `chat.members` di situ
adalah daftar orang yang akan benar-benar ditarik ke grup.

Uji sesudahnya lewat jalur sungguhnya: validasi satu staging ticket di EcoSystem.

---

## Konfigurasi flow 6 — EcoSystem - Ticket Member Added (Group Chat)

**Varian group chat dari flow 4**, dengan pola yang sama persis: cari wadahnya
lewat aksi *List*, cocokkan namanya, lalu masukkan orangnya.

| | flow 4 (channel) | flow 6 (group chat) |
|---|---|---|
| cari wadah | **List channels** → cocokkan `displayName` == `channel.name` | **List chats** → cocokkan `topic` == `chat.topic` |
| masukkan orang | **Add a member to a team** | **Add a user to a chat** (Conversation ID) |
| kartu | Post card → Channel | Post card → Group chat |

Aksi **Add a user to a chat** (`AddMemberToChat`) dan **List chats** (`GetChats`)
dua-duanya ada di konektor Microsoft Teams kelas **Standard** — jalur ini tidak
butuh lisensi Premium.

> **Flow 6 harus memakai akun koneksi Teams yang SAMA dengan flow 5.** *List
> chats* hanya mengembalikan chat milik akun koneksi; kalau flow 5 membuat grup
> dengan akun A dan flow 6 mencarinya dengan akun B, pencarian selalu nihil dan
> run berakhir **Succeeded** tanpa melakukan apa pun.

### 0. Buat flow-nya

**My flows** → **+ New flow** → **Instant cloud flow** → nama
`EcoSystem - Ticket Member Added (Group Chat)` → trigger **When an HTTP request
is received** → **Create**.

### 1. Trigger — When an HTTP request is received

Sama persis dengan flow 5 langkah 1: schema **kosong**, **Who can trigger the
flow? → Anyone**, lalu **Save**.

### 2. Condition — Cek secret EcoSystem

Sama persis dengan flow 5 langkah 3: kiri
`triggerOutputs()?['headers']?['X-EcoSystem-Secret']`, kanan nilai
`POWER_AUTOMATE_SECRET`. Semua langkah berikutnya masuk cabang **If yes**.

Flow ini **tidak** perlu *Initialize variable*: tidak ada mention yang
dikumpulkan.

### 3. Microsoft Teams → **List chats**

| Field | Isi |
|---|---|
| Chat Types | **Group** |
| Topic | **Chats with topic** (hanya grup bernama) |

> **Wajib nyalakan pagination.** Aksi ini mengembalikan chat *terbaru* milik akun
> koneksi. Dengan 62-129 tiket/bulan, chat tiket lama akan jatuh di luar halaman
> pertama dan flow akan diam-diam melaporkan "chat tidak ketemu". Buka menu ⋯
> kartu aksi → **Settings** → **Pagination → On**, *Threshold* setinggi mungkin
> (mis. 5000). Ini perbedaan nyata dari flow 4: *List channels* mengembalikan
> seluruh channel dalam satu panggilan, *List chats* tidak.

### 4. Data Operations → **Filter array**

| Field | Isi |
|---|---|
| From | `body('List_chats')?['value']` |
| Kiri (mode *Edit in advanced mode* tidak perlu) | `item()?['topic']` |
| Operator | **is equal to** |
| Kanan | `triggerBody()?['chat']?['topic']` |

`chat.topic` dirakit fungsi yang **sama persis** dengan yang dipakai flow 5 saat
membuat grup (`PowerAutomateService::ticketChatTopic()`), jadi cocoknya
exact-match, bukan tebak-tebakan prefix.

> **Kalau subject tiket diedit SETELAH grupnya terbentuk**, topic rakitan tidak
> lagi sama dengan nama grup yang terlanjur ada. Kunci cadangannya `chat.number`
> (nomor tiket tidak pernah berubah): ganti baris kondisinya menjadi
> `startsWith(item()?['topic'], triggerBody()?['chat']?['number'])` *is equal to*
> `true`.

### 5. Condition — Chat ketemu

| Sisi | Isi |
|---|---|
| Kiri (Expression) | `length(body('Filter_array'))` |
| Operator | **is greater than** |
| Kanan | `0` |

Cabang **If no** dibiarkan **kosong** (atau isi **Terminate** status
*Succeeded*): tiket yang divalidasi sebelum flow 5 dinyalakan memang tidak punya
grup, dan itu bukan kegagalan.

Chat id-nya, dipakai di langkah 6 dan 8:

```
first(body('Filter_array'))?['id']
```

### 6. Microsoft Teams → **Add a user to a chat**

| Field | Isi |
|---|---|
| Conversation ID | `first(body('Filter_array'))?['id']` |
| User | `triggerBody()?['person']?['email']` |
| Set user as chat owner | *(biarkan default)* |
| Visible history start date time | `0001-01-01T00:00:00Z` |

> **Isi *Visible history start date time*.** Dibiarkan kosong, orang yang baru
> masuk **tidak melihat pesan sebelumnya** — padahal justru riwayat diskusi tiket
> itu yang dibutuhkan consultant. Nilai `0001-01-01T00:00:00Z` berarti "seluruh
> riwayat". Field ini tidak punya padanan di bentuk channel.

> **Grup bisa penuh.** Batas 20 peserta berlaku di sini juga: kalau grup sudah
> penuh, aksi ini gagal dan penyerap di langkah 7 membuat run tetap Succeeded —
> orangnya tidak masuk grup, tapi kartunya tetap terkirim. Kalau ini mulai
> sering terjadi, kecilkan `POWER_AUTOMATE_TEAMS_EXTRA_MEMBERS` supaya slot
> consultant lebih lega.

### 7. Data Operations → **Compose** (penyerap)

*Inputs* bebas, mis. `ok`. Lalu menu ⋯ → **Settings → Run after**: centang
**is successful**, **has failed**, **has timed out**.

Tanpa penyerap ini, orang yang **sudah** jadi peserta grup membuat aksi 6 gagal,
seluruh run ditandai Failed, dan kartunya tidak terkirim. Kasus "sudah anggota"
ini yang paling sering terjadi di lapangan.

### 8. Microsoft Teams → **Post card in a chat or channel**

| Field | Isi |
|---|---|
| Post as | **Flow bot** |
| Post in | **Group chat** |
| Chat | **Enter custom value** → `first(body('Filter_array'))?['id']` |
| Adaptive Card | isi [`cards/flow-4-ticket-member-added-kartu-anggota-baru.json`](cards/flow-4-ticket-member-added-kartu-anggota-baru.json) |

Kartunya dipakai ulang dari flow 4 — isinya (nama orang, perannya, nomor tiket)
tidak berbeda antara channel dan group chat.

Field *Chat* harus lewat **Enter custom value**: daftar dropdown-nya berisi chat
yang sudah ada saat designer dibuka, bukan hasil *Filter array* saat run.

Berbeda dari channel, kartu di group chat **memang memberi notifikasi** ke semua
pesertanya, jadi aksi *Get an @mention token* tambahan tidak diperlukan di sini.

### 9. Save → salin URL trigger → pasang di `.env` → **Turn on**

```dotenv
POWER_AUTOMATE_FLOW_TICKET_MEMBER_ADDED=<HTTP POST URL flow 6>
```

```bash
php artisan optimize:clear
```

### 10. Uji

```bash
php artisan tinker --execute="
\$s = app(App\Services\PowerAutomateService::class);
\$t = App\Models\Ticket::find(<TICKET_ID>);
file_put_contents('flow-6-payload.json', json_encode(
    \$s->ticketMemberPayload(\$t, \$s->employeeContact(<EMPLOYEE_ID>), 'member', ['name' => 'Uji']),
    JSON_PRETTY_PRINT
));"

curl -X POST "<HTTP POST URL flow 6>" \
  -H "Content-Type: application/json" \
  -H "X-EcoSystem-Secret: <POWER_AUTOMATE_SECRET>" \
  -d @flow-6-payload.json
```

Pakai `<TICKET_ID>` yang grupnya **memang sudah ada** (yaitu tiket yang tadi
dibuat flow 5). Tiga uji yang layak dijalankan:

| Uji | Harapan |
|---|---|
| Orang baru, grup ada | run **Succeeded** ±10 detik, orangnya masuk grup, kartu terkirim |
| Orang yang **sudah** anggota | run **Succeeded**, aksi 6 merah tapi ditelan penyerap, kartu tetap terkirim |
| Tiket **tanpa** grup | run **Succeeded** cepat, tidak ada apa pun terkirim |

Ingat catatan lapangan 7: run **Succeeded** berdurasi ~100 ms artinya flow keluar
lewat cabang False, bukan berhasil.

Uji jalur sungguhnya: tambahkan member atau set PIC di halaman tiket EcoSystem.

---

## Batasan yang belum terpecahkan — visibilitas channel (8 Sep 2026)

Ditemukan saat menguji flow 4 dengan PIC sungguhan (Agus Dwi Priyono). Belum
diputuskan; dicatat di sini supaya tidak ditelusuri ulang dari nol.

> **Berlaku untuk bentuk CHANNEL saja (flow 2 & 4).** Bentuk group chat (flow 5 &
> 6) tidak punya masalah ini: group chat hanya terlihat oleh pesertanya, dan
> kartunya langsung memberi notifikasi. Harganya batas 20 peserta per chat dan
> aksi *List chats* yang harus dipaginasi. Bagian ini tetap disimpan karena
> bentuk channel dipertahankan sebagai pilihan.

### Masalah 1 — anggota melihat SEMUA channel tiket, bukan hanya miliknya

Di Teams, *standard channel* tidak punya daftar anggota sendiri: yang menentukan
akses adalah keanggotaan **team**. Begitu seseorang ditarik ke Support MO Team
oleh flow 2 atau flow 4, seluruh channel tiket di dalamnya terbuka untuknya —
termasuk tiket customer lain.

Satu-satunya mekanisme pembatas di Teams adalah **private channel**, dan dua hal
menghalanginya:

* **Batas jumlah.** Standard channel 1.000 per team, private channel jauh lebih
  sedikit — catatan sesi sebelumnya menulis 200, dokumentasi Microsoft yang
  diingat menyebut 30. **Kedua angka itu bertabrakan dan belum diverifikasi.**
  Dengan 62-129 tiket/bulan, batas 30 habis dalam hitungan minggu dan batas 200
  dalam 2-3 bulan, dibanding ±10 bulan untuk standard channel.
* **Tidak ada aksinya.** Konektor Teams punya *Add a member to a team*, bukan
  *add a member to a channel*. Menambah anggota ke private channel tampaknya
  hanya bisa lewat Microsoft Graph = aksi HTTP = Premium. Perlu diverifikasi di
  designer sebelum jalur ini benar-benar ditutup.

Sebelum menempuh salah satunya, perlu dipastikan dulu dorongan sebenarnya:
**kerahasiaan** (konsultan tidak boleh tahu tiket customer lain) atau
**kerapian** (daftar channel jadi panjang). Kalau kerapian, keadaan sekarang
sudah memadai — Teams tidak otomatis menampilkan channel baru, jadi daftar
channel orang tetap pendek sampai ia sendiri menekan *Show*. Kalau kerahasiaan,
satu team berisi semua tiket memang bentuk yang salah, dan pilihannya bukan
private channel melainkan pemisahan team per customer — konsekuensinya jauh lebih
besar dan perlu dibahas tersendiri.

### Masalah 2 — di mobile, anggota baru hanya melihat General

Akibat langsung dari hal yang sama: channel tiket berstatus tersembunyi sampai
anggotanya menekan **Show**. Di aplikasi mobile, membuka team langsung mendarat
di *General*, dan channel tiket harus dicari lewat *See all channels*. Catatan
lapangan 8 menutup jalan otomatisasinya — konektor tidak mengekspos properti
"favorite by default".

**Yang sebenarnya menjawab kebutuhan ini bukan daftar channel, melainkan
notifikasi.** Kartu yang diposting flow 4 tidak memberi notifikasi kepada
siapa pun; hanya @mention yang melakukannya. Bandingkan dengan flow 2, di mana
lead modul memang di-mention dan karena itu selalu menemukan channelnya.

**Usulan yang BELUM dikerjakan** — dua aksi tambahan di flow 4, di dalam cabang
*If yes* milik *Channel ketemu*, **sesudah** Compose penyerap (wajib sesudah
*Add a member to a team*: token mention hanya bisa dibuat untuk orang yang sudah
jadi anggota team):

1. **Teams → Get an @mention token for a user** — *User*:
   `triggerBody()?['person']?['email']`
2. **Teams → Post message in a chat or channel** — Post as *Flow bot*, Post in
   *Channel*, Team *Support MO Team*, Channel
   `first(body('Filter_array'))?['id']`, Message: token dari aksi 1 diikuti teks
   pengantar.

Yang belum diketahui dan hanya bisa dilihat di perangkat sungguhan: apakah
channel tersembunyi otomatis ikut ditampilkan setelah pemiliknya di-mention.
Perilakunya berbeda antar versi klien Teams. Kalau tidak, penerimanya masih bisa
memakukannya sekali lewat ⋯ → Pin.

---

## Konfigurasi flow 7 — EcoSystem - Teams Post Message (Internal Note)

**Dibuat 17 September 2026, terbukti jalan ujung-ke-ujung hari itu juga.**
Ini arah KELUAR sinkronisasi chat Teams: internal note yang ditulis di EcoSystem
diposting ke group chat tiket. Rancangannya:
[`teams-chat-sync-design.md`](../teams-chat-sync-design.md) §8.

Kenapa lewat Power Automate padahal arah masuknya lewat Graph langsung:
`POST /chats/{id}/messages` **tidak tersedia untuk izin aplikasi** (hanya
`Teamwork.Migrate.All`, untuk migrasi). Asimetri itu bukan pilihan gaya — tidak
ada alternatif app-only.

Flow ini jauh lebih sederhana dari flow 5: tidak ada variabel, loop, maupun
pencarian chat. `chat.id` datang jadi di dalam payload, karena EcoSystem yang
membuat grupnya dan menyimpan id-nya.

```
Trigger (When an HTTP request is received)
└─ Condition "Cek Secret Ecosystem"
     └─ True: Post message in a chat or channel
```

### 1. Trigger

| Field | Isi |
|---|---|
| Request Body JSON Schema | **biarkan kosong** |
| Who can trigger the flow? | **Anyone** |

`Anyone` wajib — bawaannya *Any user in my tenant* menerbitkan URL tanpa
`sp`/`sv`/`sig` dan EcoSystem kena 401 (catatan lapangan 6).

> Flow tidak bisa di-Save selama belum punya satu aksi pun, dan **HTTP URL baru
> terbit setelah Save pertama**. Jadi urutannya: pasang Condition dulu, Save,
> baru salin URL-nya.

### 2. Condition — Cek Secret Ecosystem

| Sisi | Isi |
|---|---|
| Kiri (Expression) | `triggerOutputs()?['headers']?['X-EcoSystem-Secret']` |
| Operator | **is equal to** |
| Kanan | nilai `POWER_AUTOMATE_SECRET` **diketik apa adanya** |

Cabang **False** dibiarkan kosong. Tetap dipasang walau flow ini "cuma"
memposting pesan: URL triggernya bisa dipanggil siapa saja yang tahu alamatnya,
dan `chat.id` datang dari payload — tanpa cek secret, siapa pun yang punya URL
itu bisa menulis ke group chat tiket internal mana pun.

### 3. Microsoft Teams → **Post message in a chat or channel**

> **Bukan "Post card in a chat or channel".** Dua aksi itu bersebelahan di daftar
> konektor dan namanya beda satu kata. Penandanya: kalau yang terpilih *card*, ia
> meminta field **Adaptive Card**; yang benar berujung pada field **Message**
> berupa kotak teks kaya.

| Field | Isi |
|---|---|
| Post as | **User** |
| Post in | **Group chat** |
| Group chat | Expression: `triggerBody()?['chat']?['id']` |
| Message | Expression: `triggerBody()?['message_html']` |

**`Post as: User`, bukan Flow bot.** Note tampil atas nama akun koneksi
(`EC Support`), konsisten dengan kartu tiket yang sudah ada di grup itu.

**Koneksinya harus `support@eclectic.co.id`** — akun yang sama dengan flow 5.
EcoSystem kini memasukkan akun itu sebagai anggota tiap grup tiket secara
otomatis (lihat design §8), jadi ia memang yang berhak memposting ke sana. Akun
lain akan ditolak Teams karena bukan anggota grup.

**`message_html`, bukan `message`.** Field *Message* diperlakukan sebagai HTML:
`\n` diabaikan dan pesan tiga baris jadi gepeng satu baris. EcoSystem mengirim
versi ber-`<br>` (sudah di-escape) supaya designer tidak perlu ekspresi
`replace()` yang rapuh. Payload tetap membawa `message` versi teks polos sebagai
cadangan kalau suatu saat field itu berubah perilaku.

### 4. Save → salin URL trigger → `.env`

```dotenv
POWER_AUTOMATE_FLOW_TEAMS_POST_MESSAGE=<HTTP POST URL flow 7>
```

### 5. Bentuk payload yang dikirim EcoSystem

```json
{
  "event": "teams_post_message",
  "chat":    { "id": "19:…@thread.v2" },
  "message_html": "Tio Pramudya · EcoSystem<br>\nService sudah up.<br>\n<br>\n26090199 → https://me.eclectic.co.id/tickets/1378",
  "message": "Tio Pramudya · EcoSystem\nService sudah up.\n\n26090199 → …",
  "ticket":  { "id": 1378, "number": "26090199", "subject": "…", "url": "…",
               "submitted_by": { "name": "…", "email": "…" } },
  "note":    { "id": 11500, "author": "Tio Pramudya", "text": "Service sudah up." }
}
```

`ticket.submitted_by.email` ikut karena pagar staging
`POWER_AUTOMATE_ALLOWED_SUBMITTERS` bersifat fail-closed — lihat bagian 7.0.
Tanpa blok itu, seluruh note keluar diblokir diam-diam di server staging.

### 6. Hasil uji 17 September 2026

Diuji lewat jalur penuh (`teams:flush-outbox`, bukan POST manual). Pesan mendarat
di grup yang benar atas nama `EC Support`, dengan baris terjaga:

```json
"body": { "contentType": "html",
  "content": "<p>Tio Pramudya · EcoSystem<br>\nSudah dicek di server QAS…</p>" }
```

Lalu sinkron masuk dijalankan dengan kursor **sebelum** pesan itu: **0 pesan
diserap**. Pagar anti-gema bekerja — tanpa itu, tiap note keluar akan terbaca
balik dan tersimpan dobel di tiketnya.

### Jebakan yang sudah memakan waktu

* **Connection Teams putus.** Gejalanya menyesatkan: flow tetap membalas 202 dan
  tidak ada error di sisi EcoSystem, tapi tidak ada apa pun yang sampai ke Teams.
  Periksa status koneksi sebelum mencurigai logika flow.
* **`202 Accepted` bukan bukti berhasil.** Power Automate membalas 202 sebelum
  flow-nya jalan. Satu-satunya bukti adalah pesannya benar-benar ada di Teams
  (atau run history-nya hijau sampai aksi terakhir).
* **Lupa Save.** Flow yang belum di-Save tetap punya URL aktif dari versi
  sebelumnya, jadi pengujian "berhasil" padahal menjalankan versi lama.
