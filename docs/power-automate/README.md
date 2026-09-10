# Paket import flow Power Automate

Flow integrasi EcoSystem ↔ Microsoft Teams dalam bentuk siap-import.
Panduan konsep, konfigurasi `.env`, dan troubleshooting ada di
[../power-automate-integration.md](../power-automate-integration.md) — berkas ini
khusus soal cara meng-import dan mengonfigurasi flow-nya.

## Keadaan saat ini — 8 September 2026

| Flow | Bentuk | Status |
|---|---|---|
| 1. `EcoSystem - Email Greeting` | balas greeting di thread email yang sama | **Terbukti jalan** end-to-end |
| 2. `EcoSystem - Ticket Validated` | buat channel tiket + kartu + tarik anggota + mention lead modul | **Terbukti jalan** |
| 3. `EcoSystem - Open Ticket Reminder` | reminder berulang selama tiket Open | **Belum dikonfigurasi** |
| 4. `EcoSystem - Ticket Member Added` | PIC/member baru ditarik ke channel tiketnya + kartu | **Terbukti jalan** (8 Sep 2026) |

**Keempat flow sekarang dalam keadaan `Off`, dan itu disengaja.** Perubahan
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
`return` tanpa efek. Panggilan flow 2 juga merupakan langkah **terakhir** di
`approve()` — di luar transaksi, dibungkus `try/catch`, dan dikirim setelah
response — sehingga tidak mungkin mengganggu validasi tiket harian.

**Yang masih terbuka:** flow 3 belum dibuat, dan soal visibilitas channel belum
diputuskan (lihat *Batasan yang belum terpecahkan* di bawah).

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

## Batasan yang belum terpecahkan — visibilitas channel (8 Sep 2026)

Ditemukan saat menguji flow 4 dengan PIC sungguhan (Agus Dwi Priyono). Belum
diputuskan; dicatat di sini supaya tidak ditelusuri ulang dari nol.

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
