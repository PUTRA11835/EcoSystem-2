# Rancangan: sinkronisasi chat Teams ↔ internal note

> Status: **LANGKAH 0–5 SELESAI (17 September 2026), kedua arah terbukti jalan.**
> Dibuat 16 September 2026. **Direvisi 17 September 2026** dengan hasil spike
> langkah 0 (lihat §12): tiga asumsi terbukti salah sehingga §3, §5, §6, §7, dan §9
> dikoreksi. Bagian yang berubah karena spike diberi penanda tanggal di tempatnya
> masing-masing. Sisa: uji regresi flow 5 jalur lama, demo staging, lalu langkah 6.
> Konteks integrasi Teams yang sudah ada:
> [power-automate-integration.md](power-automate-integration.md).

## 1. Yang diminta

1. Chat pada group chat Teams milik sebuah tiket **ditarik ke EcoSystem** sebagai
   internal note.
2. Internal note di EcoSystem **terkirim ke group chat** tiket itu.

---

## 2. Pagar isolasi — ini yang tidak boleh dilanggar

Permintaan eksplisit: jangan menyenggol alur yang sudah jalan. Rancangan ini
menaruh pagar itu di tingkat arsitektur, bukan sekadar niat baik.

**Berkas yang TIDAK disentuh sama sekali:**

| Berkas | Alasan |
|---|---|
| `EmailController.php` | Fetch + kirim email Graph. Hanya DIPANGGIL (`getAccessTokenPublic()` sudah `public` dan sudah dipakai `StagingTicketController`), tidak diubah. |
| `GraphRelayService.php` | Relay subject `[JARVIES]`. |
| `StagingTicketService.php` | Pembuatan staging + greeting email. |
| `SlaService.php`, `InlineImageService.php` | Dipanggil lewat API publiknya, tidak diubah. |
| `email:process-inbox` | Command inbox tetap apa adanya. |

**Tabel yang TIDAK diubah strukturnya:**

- `ticket` — dibagi dengan repo Jarvies.
- `ticket_message` — dibagi dengan repo Jarvies. **Tidak ada kolom baru di sini.**
  Semua metadata Teams ditaruh di tabel pendamping.
- `ticket_attachment` — dipakai apa adanya lewat jalur yang sudah ada.

**Kredensial: satu app registration (keputusan 17 Sep 2026).** Integrasi ini
memakai app registration yang **sama** dengan email (`MS_CLIENT_ID = 9040a78f-…`),
cukup ditambah tiga permission. Alasannya operasional: dua app berarti dua Client
Secret dengan dua tanggal kedaluwarsa, dan secret kedaluwarsa adalah penyebab
kegagalan integrasi yang paling sering — dua app justru menambah cara sistem bisa
mati kalau tidak ada yang memantau tanggalnya.

Yang dibayar sebagai gantinya: satu secret jadi memegang banyak kuasa sekaligus
(seluruh mailbox support + kirim email + OneDrive + **semua chat Teams di tenant**).
Kalau suatu saat itu dianggap terlalu terkonsentrasi, pemisahannya **tidak menuntut
perubahan kode** — config dibuat dengan fallback:

```php
// config/services.php
'microsoft_graph_teams' => [
    'tenant_id'     => env('MS_TEAMS_TENANT_ID', env('MS_TENANT_ID')),
    'client_id'     => env('MS_TEAMS_CLIENT_ID', env('MS_CLIENT_ID')),
    'client_secret' => env('MS_TEAMS_CLIENT_SECRET', env('MS_CLIENT_SECRET')),
],
```

`MS_TEAMS_*` kosong → pakai app email. Diisi → pindah ke app terpisah, cukup
`.env` + `php artisan optimize:clear`.

**Konsekuensi yang harus diterima:** karena kredensialnya satu, merotasi secret
menghentikan email DAN Teams bersamaan. Tanggal kedaluwarsa secret wajib dipantau —
lihat §11.

**Satu-satunya sentuhan di berkas lama** adalah dua pemanggilan satu baris, keduanya
dibungkus `try/catch` dan dijalankan setelah response:

1. `TicketMessageController@store` — cabang internal note (sekitar
   [baris 374](../app/Http/Controllers/TicketMessageController.php#L374)): masukkan
   note ke outbox.
2. `StagingTicketController@notifyTeamsTicketValidated` (sekitar
   [baris 501](../app/Http/Controllers/StagingTicketController.php#L501)): buat group
   chat lewat Graph, simpan `chat_id`.

Keduanya sudah berada di jalur yang memang khusus Teams dan sudah memakai pola
"langkah terakhir, di luar transaksi, kegagalan ditelan".

---

## 3. Batasan Graph yang membentuk rancangan

Verifikasi ke dokumentasi Microsoft (16 Sep 2026), lalu **diuji langsung di tenant
lewat spike langkah 0 (17 Sep 2026)** — kolom terakhir adalah hasil panggilan nyata,
bukan bacaan dokumentasi:

| Operasi | Application permission | Hasil uji di tenant |
|---|---|---|
| `POST /chats` | `Chat.Create` ✅ | **HTTP 201** — chat grup + topic terbentuk |
| `GET /chats/{id}` | `Chat.Read.All` ✅ | **HTTP 200** |
| `GET /chats/{id}/members` | `ChatMember.ReadWrite.All` ✅ | **HTTP 200** — `userId` + `email` + `displayName` |
| `GET /chats/{id}/messages` | `Chat.Read.All` ✅ | **HTTP 200**, `$filter`/`$orderby`/`$top` jalan |
| `GET …/messages/{id}/hostedContents/{hcId}/$value` | `Chat.Read.All` ✅ | **HTTP 200** — byte gambar terambil (fase 2) |
| **`GET /users/{aadId}`** | butuh `User.Read.All` ❌ **tidak diberikan** | **HTTP 403** `Authorization_RequestDenied` |
| **`POST /chats/{id}/messages`** | ❌ hanya `Teamwork.Migrate.All` (migrasi) | **Kirim TIDAK bisa app-only** |

Baris terakhir itulah yang membuat arsitekturnya asimetris: **masuk lewat Graph,
keluar lewat Power Automate.** Bukan pilihan gaya — tidak ada alternatif app-only.

Baris `GET /users/{aadId}` adalah koreksi dari hasil spike: app "Jarvies Mail" tidak
punya `User.Read.All`, jadi resolusi identitas **tidak boleh** lewat direktori. Gantinya
ada di §7 — dan gantinya tidak menuntut permission baru.

Yang juga dipakai: `GET /chats/{id}/messages` mendukung
`$filter=lastModifiedDateTime gt {t}` + `$orderby=lastModifiedDateTime desc`
(`$top` maks 50) sehingga polling bisa inkremental, bukan tarik-ulang seluruh
riwayat. Terbukti presisi: kursor di atas stempel waktu pesan terakhir mengembalikan
`0` hasil, bukan mengulang riwayat.

---

## 4. Arsitektur

```
                    ┌──────────────── EcoSystem ────────────────┐
                    │                                            │
  Internal note ────┼──► teams_outbox ──► teams:flush-outbox ────┼──► Power Automate
  (web / Lite)      │      (pending)         (tiap menit)        │      flow 7
                    │                                            │         │
                    │                                            │         ▼
                    │                                            │   Group chat Teams
                    │                                            │         │
  Ticket thread ◄───┼── ticket_message ◄── teams:sync-chat ◄──────┼─────────┘
  (UI polling 15s)  │   + ticket_message_teams   (tiap menit)     │
                    └────────────────────────────────────────────┘
                                                     GET /chats/{id}/messages
```

**UI tidak perlu diubah.** Halaman tiket sudah memanggil
`/api/tickets/{id}/messages` tiap 15 detik dan me-render pesan baru secara
incremental ([show.blade.php:3842](../resources/views/ticket/show.blade.php#L3842)).
Begitu pesan Teams tersimpan sebagai `ticket_message`, ia muncul sendiri.

**Internal note tidak bocor ke customer.** Pesan dari Teams disimpan dengan
`is_internal_note = true`, dan seluruh jalur customer (Jarvies, relay email,
`EmailController`) sudah menyaring `where('is_internal_note', false)`. Tidak ada
perubahan yang diperlukan untuk menjamin ini — hanya perlu tidak merusaknya.

---

## 5. Skema data

Tiga tabel baru. Tidak ada kolom baru di tabel lama.

### `ticket_teams_chat` — satu baris per tiket yang punya group chat

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id` | bigint, **unique** | tanpa FK ke `ticket` (tabel dibagi Jarvies) |
| `chat_id` | string(255), unique | `19:…@thread.v2` |
| `topic` | string(255) | untuk diagnosa saja, bukan kunci pencarian |
| `created_via` | enum(`graph`,`manual`) | |
| `sync_enabled` | boolean, default true | kill switch per tiket |
| `last_message_at` | datetime, nullable | `lastModifiedDateTime` pesan terakhir yang sudah diserap — kursor polling |
| `last_polled_at` | datetime, nullable | |
| `poll_failures` | unsigned int, default 0 | naik tiap gagal; melewati ambang → `sync_enabled=false` + log |

### `ticket_message_teams` — jembatan pesan, sekaligus kunci anti-loop

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_message_id` | bigint, unique | baris di `ticket_message` |
| `chat_id` | string(255) | |
| `teams_message_id` | string(64), nullable | id pesan di Teams |
| `direction` | enum(`in`,`out`) | |
| **unique** | (`chat_id`, `teams_message_id`) | **idempotency** — ini yang membunuh loop & duplikat |

Dua hal yang dikonfirmasi spike soal `teams_message_id`:

- Bentuknya **epoch-milidetik 13 digit** (mis. `1789614743752`), sama persis dengan
  `createdDateTime`. Jadi ia **tidak unik lintas chat** — unique-nya memang harus
  gabungan dengan `chat_id`, bukan pada kolomnya sendiri.
- Unique itu **bukan pengaman opsional.** Pesan bergambar punya
  `lastModifiedDateTime` ~7 detik sesudah `createdDateTime` (Teams memproses gambarnya
  belakangan, `lastEditedDateTime` tetap null), sehingga pesan yang sudah diserap
  **muncul lagi** di jendela polling berikutnya. Tanpa dedupe, pesan bergambar pasti
  dobel — bukan mungkin dobel.

### `teams_outbox` — antrean kirim keluar

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id`, `chat_id` | | |
| `ticket_message_id` | bigint, unique | satu note = satu kiriman |
| `payload` | json | sudah jadi teks siap kirim |
| `status` | enum(`pending`,`sending`,`sent`,`failed`) | |
| `attempts` | unsigned tinyint | |
| `last_error` | text, nullable | |
| `sent_at` | datetime, nullable | |

Outbox dipilih daripada kirim-langsung karena tiga hal: user tidak ikut menunggu
Power Automate, kegagalan bisa di-retry, dan kegagalan **terlihat sebagai data** —
bukan hilang jadi satu baris log. Ini pelajaran dari kasus email jadi draft
diam-diam (`email_status` / `email_error`).

---

## 6. Kode baru

Semua di namespace sendiri, tidak menumpang kelas yang ada.

```
app/Services/Teams/
    TeamsGraphClient.php      token client-credentials (app registration Teams) + GET/POST Graph
    TeamsChatService.php      buat chat, tambah anggota, baca pesan, roster identitas
    TeamsInboundService.php   pesan Teams  -> ticket_message (internal note)
    TeamsOutboxService.php    internal note -> teams_outbox -> flow 7
app/Console/Commands/
    SyncTeamsChatMessages.php   `teams:sync-chat-messages`
    FlushTeamsOutbox.php        `teams:flush-outbox`
app/Models/
    TicketTeamsChat.php, TicketMessageTeams.php, TeamsOutboxItem.php
```

Scheduler ([routes/console.php](../routes/console.php)) — dua baris tambahan, pola
persis seperti `tickets:open-reminders` yang sudah ada:

```php
Schedule::command('teams:sync-chat-messages')->everyMinute()->withoutOverlapping();
Schedule::command('teams:flush-outbox')->everyMinute()->withoutOverlapping();
```

Keduanya `return` seketika kalau `TEAMS_SYNC_ENABLED=false`, sama seperti flow
Power Automate yang sudah ada.

**Pembuatan chat tidak perlu resolusi identitas.** Spike membuktikan
`user@odata.bind` menerima **UPN** langsung:

```json
"user@odata.bind": "https://graph.microsoft.com/v1.0/users('agus.priyono@eclectic.co.id')"
```

Jadi `TeamsChatService::createChat()` cukup bermodal kolom email yang sudah ada di
`employee` — tanpa `GET /users/{id}` (yang memang 403), tanpa langkah resolve object
id, dan tanpa permission tambahan. Alamat yang tidak dikenal akan ditolak oleh Graph
saat `POST /chats`, bukan gagal diam-diam.

---

## 7. Alur masuk (Teams → EcoSystem)

Tiap menit, untuk tiap `ticket_teams_chat` yang `sync_enabled` **dan** tiketnya
belum Closed/Cancelled:

1. `GET /chats/{chat_id}/messages?$top=50&$orderby=lastModifiedDateTime desc`
   `&$filter=lastModifiedDateTime gt {last_message_at}`
2. Ambil **hanya** yang `messageType === 'message'`, dan buang yang `deletedDateTime`
   terisi.

   Syaratnya ditulis **positif** (terima yang cocok), bukan negatif (buang yang
   `!= 'message'`). Alasannya dari spike: pesan sistem ternyata datang dengan
   `messageType = 'unknownFutureValue'` — bukan nama event-nya; jenis sebenarnya ada di
   `eventDetail.@odata.type` (`chatRenamedEventMessageDetail`,
   `membersAddedEventMessageDetail`). Nama `unknownFutureValue` itu sendiri isyarat
   bahwa Microsoft akan menambah jenis baru. Syarat negatif akan meloloskan jenis baru
   itu jadi internal note; syarat positif tidak.
3. Buang yang `teams_message_id`-nya sudah ada di `ticket_message_teams` — pagar
   anti-loop utama, berlaku juga untuk pesan yang kita sendiri yang kirim.
4. Petakan `from.user.id` (AAD object id) ke `employee`.
5. Simpan `ticket_message` lalu baris `ticket_message_teams` dengan
   `direction='in'` — keduanya dalam satu `DB::transaction`.
6. Majukan `last_message_at`.

> **Dikoreksi 17 Sep 2026 saat implementasi — `channel` BUKAN `'teams'`.**
>
> | Kolom | Rancangan awal | Yang dipakai | Alasan |
> |---|---|---|---|
> | `channel` | `'teams'` | **`'web'`** | Kolomnya `enum('email','web')` di `ticket_message` — tabel yang dibagi Jarvies dan yang §2 larang diubah strukturnya. Menambah nilai enum = ALTER pada tabel bersama. |
> | `message_type` | (tidak dibahas) | **`'internal_note'`** | UI tiket menentukan bentuk gelembung MURNI dari `message_type === 'internal_note'` (`show.blade.php:4179`, `:4520`). Nilai lain membuat internal note tampil sebagai balasan biasa ke customer. |
> | `sender_type` | `'employee'` | `'employee'` | Tetap. Kombinasi `employee` + `sender_id` NULL sudah dipakai 188 baris yang ada, jadi bukan wilayah baru bagi UI. |
> | `message_html` | (tidak dibahas) | **NULL** | Body Teams memuat `<img>` ber-URL Graph yang butuh bearer token; menyimpannya mentah = gambar rusak di UI. Fase pertama teks saja (§9). |
>
> Penanda asal Teams ada di tabel pendamping `ticket_message_teams` — persis
> prinsip §2 "semua metadata Teams ditaruh di tabel pendamping". Konsekuensinya
> yang disengaja: pesan dari Teams tampil sebagai internal note biasa, tanpa
> lencana khusus. Menambah lencana berarti mengubah UI, dan §4 justru berjanji
> UI tidak perlu diubah.

### Saringan tambahan: kartu Adaptive Card dari flow 5

Ditemukan saat pengujian, bukan saat perancangan: flow 5 memposting kartu
"Tiket baru" ke grup tepat setelah grupnya dibuat, dan kartu itu tiba sebagai
`messageType: 'message'` biasa dengan `body.content` hanya
`<attachment id="..."></attachment>`.

Tanpa saringan, **setiap tiket baru** langsung mendapat internal note kosong —
sampah yang muncul di semua tiket, bukan kasus pinggiran. Karena itu pesan yang
teksnya kosong **dan** seluruh lampirannya bertipe
`application/vnd.microsoft.card.*` dibuang. Saringannya sempit dengan sengaja:
orang yang mengetik komentar lalu menempelkan kartu tetap terserap, karena
teksnya tidak kosong.

### Kursor wajib dikonversi ke zona waktu aplikasi

Juga ditemukan saat pengujian. Graph selalu mengirim UTC; kolom
`last_message_at` dibaca Laravel sebagai `Asia/Jakarta`. Menyimpan jam-dinding
UTC apa adanya membuat `cursorIso()` menghasilkan nilai **7 jam terlalu awal**,
sehingga jendela polling selamanya 7 jam lebih lebar. Hasilnya tetap benar
(dedupe menangkapnya) tapi tiap menit memindai ulang pesan lama dan pagar
`max_per_poll` bisa habis olehnya. Konversinya dilakukan di satu tempat,
`TicketTeamsChat::markPolled()`.

Batas ledakan: maksimal N pesan per chat per putaran (env), supaya chat yang lama
tidak tersinkron tidak membanjiri thread tiket dalam satu menit.

**Pesan sebelum fitur menyala tidak ditarik.** Saat `ticket_teams_chat` dibuat,
`last_message_at` diisi waktu sekarang. Riwayat lama sengaja tidak diimpor — kalau
nanti dibutuhkan, itu fitur terpisah dengan konfirmasi manusia.

### Pemetaan identitas

> **Direvisi 17 Sep 2026 setelah spike.** Rancangan awal memakai
> `GET /users/{aadId}` untuk menerjemahkan AAD object id ke email. Panggilan itu
> **HTTP 403** (`Authorization_RequestDenied`) — app "Jarvies Mail" tidak punya
> `User.Read.All`. Rantai di bawah ini tidak memakainya sama sekali dan **tidak
> menuntut permission baru.**

`from.user.id` adalah AAD object id, bukan email — terkonfirmasi di spike, dan nilainya
cocok persis dengan `userId` di roster chat. Rantai resolusinya:

1. Cache lokal AAD id → `employee_id`.
2. Belum ada → **`GET /chats/{chat_id}/members`**, yang mengembalikan per anggota:

   ```json
   { "userId": "d5c24850-…", "email": "tio.pramudya@eclectic.co.id",
     "displayName": "Tio Pramudya" }
   ```

   Cocokkan `email` ke `employee`. Roster ini lebih tepat daripada direktori: isinya
   persis orang yang ada di chat tiket itu, bukan seluruh tenant — dan ia juga sumber
   yang benar untuk menambah anggota yang belum tercatat.
3. Tetap tidak ketemu (guest, akun eksternal, anggota yang sudah keluar dari chat) →
   `sender_id = null`, `sender_name` = `from.user.displayName`. Pesannya tetap masuk;
   hanya tidak ter-link ke employee.

Langkah 3 hampir tidak pernah butuh panggilan apa pun: **setiap pesan sudah membawa
`from.user.displayName`** (mis. `"Tio Pramudya"`), jadi `sender_name` selalu terisi
tanpa lookup. Roster di langkah 2 hanya diperlukan untuk menautkan ke `employee_id`,
dan hasilnya di-cache per chat.

Kolom `sender_name` sudah ada dan sudah `fillable` — tidak ada perubahan skema.

---

## 8. Alur keluar (EcoSystem → Teams)

1. Internal note tersimpan → `TeamsOutboxService::queue($message)` dipanggil dari
   `TicketMessageController@store`, dibungkus `try/catch`. Gagal antre = note tetap
   tersimpan; hanya tidak sampai ke Teams.
2. `teams:flush-outbox` mengambil batch `pending`, POST ke **flow 7** Power Automate
   dengan `chat_id` yang **sudah kita miliki** — jadi flow-nya cuma satu aksi
   "Post message in a chat or channel", tanpa List chats dan tanpa pencocokan
   `topic`. Flow 5 & 6 bisa dipensiunkan setelah ini terbukti.
3. Retry dengan backoff, maksimal N kali → `failed` + `last_error` terisi.

Bentuk pesan di Teams (teks, bukan Adaptive Card — supaya enak dibaca di HP):

```
Budi Santoso · EcoSystem
Sudah dicek di server QAS, service-nya sudah up.

26090214 → https://me.eclectic.co.id/tickets/1234
```

**Yang TIDAK dikirim ke Teams:** balasan email ke customer, pesan masuk dari
customer, pesan sistem/SLA, dan internal note yang berasal dari Teams
(`direction='in'`). Hanya internal note yang ditulis manusia di EcoSystem.

### Pengecualian: jadwal meeting (ditambahkan 17 Sep 2026)

Permintaan menyusul: jadwal meeting yang dibuat di EcoSystem juga diumumkan ke
group chat tiket.

Pesan meeting berjenis `sender_type='system'` (cabang fallback) atau balasan ke
customer (cabang email) — keduanya ada di daftar penolakan di atas. Aturan itu
tetap benar untuk jalur internal note, jadi meeting lewat **pintu sendiri**:
`TeamsOutboxService::queueMeeting()`, dipanggil dari `SlaController@startMeeting`
dan dibungkus `try/catch` sendiri. Melonggarkan `shouldSend()` akan membuka jalan
untuk semua pesan sistem lain sekaligus — itu bukan yang diminta.

Dijalankan untuk **kedua cabang**: yang menentukan perlu-tidaknya tim diberi tahu
adalah adanya jadwal meeting, bukan berhasil-tidaknya undangan email ke customer.

Bentuknya pengumuman untuk TIM, bukan salinan undangan customer:

```
Meeting dijadwalkan · EcoSystem
Oleh: Putra Palampang Tarung

Waktu: Fri, 18 Sep 2026 14:00–15:00 WIB
Link: https://teams.microsoft.com/l/meetup-join/…
Catatan: Bahas root cause error posting GR di MIGO.

26090200 → https://me.eclectic.co.id/tickets/1379
```

Jam ditampilkan dalam zona waktu aplikasi, bukan UTC — yang membacanya orang di
grup, dan "14:00" yang ternyata UTC adalah kesalahan yang baru ketahuan saat ada
yang telat meeting.

**`meeting_ended` sengaja TIDAK dikirim.** Meeting bisa berakhir otomatis pada
`meeting_end_time` tanpa ada yang menekan tombol, jadi pengumuman "meeting
selesai" di grup adalah derau yang tidak menambah informasi apa pun bagi orang
yang sudah tahu jadwalnya. Kalau nanti ternyata dibutuhkan, jalurnya sudah ada —
tinggal satu pemanggilan lagi di `endMeeting`.

`meeting_link` diisi manual oleh agent (`nullable|url`); EcoSystem tidak membuat
meeting Teams sendiri. Membuat meeting lewat Graph (`POST /users/{id}/onlineMeetings`)
adalah pekerjaan terpisah dan butuh permission baru — di luar scope ini.

> ### Dua hal yang ditemukan saat implementasi (17 Sep 2026)
>
> **1. Akun koneksi Power Automate WAJIB jadi anggota chat buatan Graph.**
>
> `support@eclectic.co.id` sengaja ada di `POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS`,
> jadi ia **pasti tidak ada** di daftar anggota dari `ticketChatPayload()`.
> Pengecualian itu benar untuk jalur lama: konektor Teams menambahkan pemilik
> koneksi sendiri saat ia yang membuat grup, dan alamat yang ikut dua kali membuat
> permintaan ditolak sebagai `Duplicate chat members`.
>
> **Graph tidak menambahkan siapa pun otomatis.** Tanpa perbaikan, grup terbentuk
> tanpa akun koneksi di dalamnya, lalu flow 5 (*Post card*) dan flow 7
> (*Post message*) sama-sama gagal — keduanya memposting *Post as User* atas nama
> akun itu, dan Teams menolak posting dari yang bukan anggota. `TeamsChatService`
> kini menambahkannya sendiri lewat `services.teams_sync.connection_email`.
>
> **2. Gema arah keluar — tanpa pagar, tiap note keluar tersimpan DOBEL.**
>
> Flow 7 memposting note kita atas nama akun koneksi tapi **tidak mengembalikan id
> pesan** yang ia buat. Jadi kita tidak pernah tahu id pesan kiriman sendiri, dan
> dedupe (`chat_id`,`teams_message_id`) di §5 — yang menangani semua kasus lain —
> tidak bisa mengenalinya saat pesan itu terbaca balik pada polling berikutnya.
> Hasilnya: satu note ditulis di EcoSystem, terkirim ke Teams, lalu terbaca balik
> jadi internal note kedua.
>
> Pagarnya: **sinkron masuk mengabaikan semua pesan yang penulisnya akun koneksi.**
> Satu aturan itu sekaligus membuang dua sumber derau lain yang juga diposting flow
> atas nama akun yang sama — kartu "Tiket baru" flow 5 dan pesan mention lead-nya.
>
> Harga yang dibayar: manusia yang mengetik di group chat tiket memakai akun itu
> tidak ikut tersinkron. Akun layanan, jadi wajar — tapi dicatat di sini supaya
> tidak jadi misteri di kemudian hari.
>
> Baris `ticket_message_teams` arah `out` tetap ditulis sebagai jejak (dengan
> `teams_message_id` NULL), dan ditulis **hanya setelah** flow menerima — ditulis
> lebih awal, note yang gagal terkirim akan terlihat seolah sudah ada di Teams.
>
> **3. Payload flow 7 wajib membawa `ticket.submitted_by.email`.** Pagar staging
> `POWER_AUTOMATE_ALLOWED_SUBMITTERS` bersifat fail-closed; tanpa blok itu,
> SELURUH note keluar akan diblokir diam-diam di server staging.

---

## 9. Lampiran dan gambar

> **Diperbarui 17 Sep 2026 — fase gambar SUDAH dikerjakan.**
> Bagian di bawah ini menjelaskan rencana fase pertama (teks saja); yang benar-
> benar berjalan sekarang ada di subbagian **9b**.

Fase pertama: **teks saja.**

- **Keluar:** `message_html` dipreteli jadi teks. Kalau note punya lampiran,
  ditambahkan satu baris "(2 lampiran — lihat tiket)" plus tautan tiket. Tidak ada
  byte yang dikirim ke Teams.
- **Masuk:** pesan Teams yang membawa gambar/berkas disimpan teksnya saja, dengan
  penanda bahwa ada lampiran yang tidak ikut.

  > **Koreksi dari spike (17 Sep 2026) — jangan cek `attachments`.**
  > Gambar inline **tidak** muncul di `attachments`; array itu tetap `[]`. Gambarnya
  > ada di dalam `body.content` sebagai tag `<img>`:
  >
  > ```html
  > <img src="https://graph.microsoft.com/v1.0/chats/19:f389…@thread.v2/messages/
  >            1789615758397/hostedContents/aWQ9…/$value" style="width:3024px; …">
  > ```
  >
  > Deteksi "pesan ini punya lampiran" **wajib** memindai `<img>` di body HTML.
  > Implementasi yang mengandalkan `attachments` akan meloloskan gambar tanpa penanda
  > apa pun.
  >
  > Ini juga alasan keras kenapa fase pertama harus teks: URL `graph.microsoft.com` itu
  > **butuh bearer token**, jadi menyimpan body HTML apa adanya ke `ticket_message`
  > menghasilkan `<img>` yang dijamin broken di UI tiket — browser tidak punya token.
  > Body HTML masuk **harus** dipreteli jadi teks, bukan disimpan mentah.

  Mengambil `hostedContents` lalu menyimpannya sebagai `TicketAttachment` adalah
  pekerjaan terpisah (fase 2) — dan harus tetap tunduk pada invariant "byte gambar
  tidak pernah masuk DB" (`TicketMessage::booted()` + `InlineImageService`).

  Kabar baiknya, fase 2 itu **tidak butuh permission tambahan**; spike sudah
  membuktikan byte-nya terambil app-only:

  ```
  GET /chats/{chat}/messages/{id}/hostedContents         → 200, 1 item
  GET .../hostedContents/{hcId}/$value                   → 200, 2.029.110 byte, JPEG
  ```

  Satu jebakan untuk saat itu: **MIME hanya ada di header respons `Content-Type`**
  (`image/jpeg`). Di daftar `hostedContents`, `contentBytes` dan `contentType`
  keduanya `null` — jangan diandalkan. Polanya mirip proxy attachment email yang sudah
  ada (`AttachmentController` → Graph `/attachments/{id}`).

- **Belum teruji:** lampiran **berkas non-gambar** (PDF/dokumen). Dugaannya lewat
  `attachments` sebagai referensi SharePoint, tapi itu belum dibuktikan — jangan
  ditulis sebagai asumsi di kode fase 2 sebelum diuji.

Membatasi fase pertama ke teks membuat seluruh fitur bisa dites tanpa menyentuh
jalur attachment sama sekali.

---

## 10. Konfigurasi

Variabel baru, semuanya default mati:

```dotenv
TEAMS_SYNC_ENABLED=false          # kill switch utama
TEAMS_SYNC_INBOUND=false          # Teams -> EcoSystem
TEAMS_SYNC_OUTBOUND=false         # EcoSystem -> Teams
TEAMS_SYNC_MAX_PER_POLL=20        # pagar ledakan per chat per putaran
TEAMS_SYNC_CHAT_BATCH=50          # jumlah chat yang dipoll per menit

# Biarkan KOSONG → otomatis memakai app registration email (MS_*), sesuai
# keputusan 17 Sep 2026. Isi hanya kalau nanti Teams dipindah ke app sendiri.
MS_TEAMS_TENANT_ID=
MS_TEAMS_CLIENT_ID=
MS_TEAMS_CLIENT_SECRET=

POWER_AUTOMATE_FLOW_TEAMS_POST_MESSAGE=   # flow 7
```

Dua arah bisa dinyalakan terpisah. Kalau ada yang aneh, matikan satu arah tanpa
mengganggu yang lain — dan mematikan keduanya mengembalikan sistem ke keadaan hari
ini, tanpa deploy ulang.

---

## 11. Kalau ada yang gagal, harus kelihatan

Pelajaran dari kasus-kasus sebelumnya: kegagalan senyap selalu lebih mahal.

- `poll_failures` di `ticket_teams_chat` — melewati ambang, sinkron chat itu
  dimatikan sendiri dan dicatat `Log::warning` dengan nomor tiket.
- `teams_outbox.status='failed'` + `last_error` — terlihat sebagai data, bukan log.
- Command `teams:sync-status` (opsional) untuk melihat ringkasannya dari CLI:
  berapa chat aktif, berapa gagal, antrean tertua umur berapa.

### Masa berlaku Client Secret

Karena email dan Teams kini berbagi satu kredensial, secret yang kedaluwarsa
menjatuhkan **keduanya** sekaligus. Ini kegagalan yang bisa diprediksi tanggalnya,
jadi tidak boleh ditunggu sampai terjadi:

- App registration-nya bernama **"Jarvies Mail"**, punya **satu** client secret
  (deskripsi `Mail`, Secret ID `8edcb98d-…`) yang **kedaluwarsa 16 April 2028**
  (dicek 17 Sep 2026). Client secret tidak bisa diperpanjang — hanya bisa dibuat
  baru lalu yang lama dihapus.
- Rotasinya dikerjakan sekitar **Februari 2028**, urutannya tanpa jeda mati:
  secret baru → update `.env` → `php artisan optimize:clear` → hapus yang lama.
  Dua secret boleh hidup bersamaan.
- **Radius rotasi meliputi repo Jarvies** kalau Jarvies memakai app registration
  yang sama (namanya mengindikasikan demikian — perlu dikonfirmasi). Kalau benar,
  `.env` kedua repo harus di-update dalam jendela yang sama.
- Idealnya sebuah command harian memeriksa sisa umurnya dan mengirim notifikasi
  30 / 14 / 7 hari sebelum jatuh tempo. Itu butuh permission `Application.Read.All`
  di app yang sama — kalau tidak diberikan, gantinya adalah reminder kalender
  manual. Pilihan sadar, bukan kelalaian.

---

## 12. Rencana eksekusi

| Langkah | Isi | Status |
|---|---|---|
| 0 | **Spike** — script sekali pakai: buat chat via Graph app-only, kirim 1 pesan manual dari Teams, baca lewat Graph. Tanpa menyentuh EcoSystem. | ✅ **selesai 17 Sep 2026** |
| 1 | Tambah `Chat.Create`, `Chat.Read.All`, `ChatMember.ReadWrite.All` ke app `9040a78f-…` + grant consent | ✅ **selesai** — terverifikasi di klaim `roles` token |
| 2 | Migrasi 3 tabel + model + `TeamsGraphClient` | ✅ **selesai 17 Sep 2026** |
| 3 | Pembuatan chat pindah ke Graph, `chat_id` tersimpan | ✅ **selesai 17 Sep 2026** — ⚠️ butuh satu perubahan manual di flow 5, lihat di bawah |
| 4 | Arah **masuk** (`teams:sync-chat-messages`) | ✅ **selesai 17 Sep 2026** |
| 5 | Arah **keluar** (outbox + flow 7) | ✅ **selesai 17 Sep 2026** — flow 7 dibuat & terbukti jalan ujung-ke-ujung |
| 6 | Pensiunkan flow 5 & 6 setelah 3–5 terbukti | belum |

### Hasil langkah 0 (17 September 2026)

Dijalankan lewat script sekali pakai di scratchpad — tidak ada berkas spike yang
masuk repo. Chat uji: `19:f389d8d63bca4a26972c5e0df37607b6@thread.v2`
("SPIKE Teams sync - tiket uji", 4 anggota).

**Terbukti jalan:**

1. `POST /chats` app-only → 201. Chat tampil wajar di klien Teams, punya nama grup,
   anggotanya benar, dan **anggota biasa bisa membalas** (dua pesan uji masuk dari
   dua orang berbeda). Kekhawatiran §14.1 tidak terbukti.
2. `GET /chats/{id}/messages` app-only → 200, pesan manual terbaca utuh, termasuk
   `from.user.id` = AAD object id yang cocok dengan roster.
3. Polling inkremental presisi: kursor sesudah pesan terakhir → `0` hasil.

**Tiga asumsi rancangan yang ternyata salah** (semuanya sudah dikoreksi di §3, §5,
§6, §7, §9):

| Asumsi awal | Kenyataan |
|---|---|
| `GET /users/{aadId}` untuk resolusi identitas | **403** — tak ada `User.Read.All`; ganti ke roster chat (§7) |
| Pesan sistem dikenali dari nama event di `messageType` | `messageType = 'unknownFutureValue'`; saring positif `=== 'message'` (§7) |
| Gambar terdeteksi lewat `attachments` | `attachments` tetap `[]`; gambar ada sebagai `<img>` di body HTML (§9) |

**Masih belum teruji:** lampiran berkas non-gambar, perilaku `deletedDateTime`, dan
arah keluar (flow 7).

### ⚠️ Langkah 3 menuntut satu perubahan MANUAL di flow 5

Sejak langkah 3, EcoSystem membuat group chat sendiri lewat Graph. Flow 5 di Power
Automate **masih** punya aksi "Create a chat" — kalau keduanya jalan, satu tiket
akan punya **dua** grup.

Payload `chat` yang dikirim ke flow 5 kini membawa dua field baru saat chat sudah
dibuat EcoSystem:

```json
"chat": {
  "id": "19:…@thread.v2",
  "created_by_ecosystem": true,
  "topic": "…", "members": [...], "members_csv": "…"
}
```

> **SUDAH DIKERJAKAN 17 September 2026 dan terbukti jalan.** Langkah designer
> lengkapnya ada di [`power-automate/README.md`](power-automate/README.md) bagian
> *Konfigurasi flow 5*. Ringkasnya: flow memakai variabel `chatId` yang diisi dari
> `chat.id` bila ada, dan dari `Create a chat` bila tidak — sehingga `Create a chat`
> hanya berjalan saat EcoSystem belum membuatkan grupnya.

Kondisi yang dipakai di flow **memeriksa `chat.id`**, bukan flag
`created_by_ecosystem`:

```
length(coalesce(triggerBody()?['chat']?['id'], ''))   is greater than   0
```

Flag `created_by_ecosystem` tetap dikirim (berguna untuk dibaca manusia saat
menelusuri run history), tapi tidak dipakai sebagai kondisi: ia boolean, dan
membandingkan boolean dengan teks `true` di designer Power Automate adalah jebakan
yang tampak benar tapi selalu bernilai False.

Selama `TEAMS_SYNC_ENABLED=false`, `chat.id` tidak pernah dikirim dan flow 5
berjalan persis seperti sebelumnya.

---

## 13. Di luar scope (sengaja)

- Sinkron **edit** dan **unsend** dua arah.
- Menarik riwayat chat sebelum fitur menyala.
- Staging "Captured from Teams" + klasifikasi AI + konfirmasi manusia sebelum jadi
  internal note. Internal note sudah informal dan bisa dihapus; lapisan persetujuan
  di depannya menambah friksi untuk masalah yang belum terbukti ada.
- Ekstraksi action item / decision / deteksi credential oleh AI.
- Reaction, mention Teams, reply-thread.
- Bentuk channel per customer / per CR besar (ranah Delivery Project).

---

## 14. Yang masih belum pasti

1. ~~**Perilaku chat yang dibuat app-only.**~~ **TERJAWAB 17 Sep 2026** — chat yang
   dibuat app-only tampil normal di klien Teams (ada nama grup, roster benar) dan
   anggotanya bisa membalas. Tidak ada perilaku aneh yang menghalangi langkah 3.
2. **`Chat.Read.All` itu izin yang luas** — aplikasi bisa membaca semua chat di
   tenant, bukan hanya chat tiket. Kodenya hanya akan memanggil `chat_id` yang
   tercatat, tapi izinnya sendiri tetap luas. Penyempitannya lewat RSC
   (`ChatMessage.Read.Chat`) menuntut packaging Teams app — itu fase 3.
3. **Batas 20 peserta** tetap berlaku dan sekarang jadi urusan EcoSystem, bukan
   konektor Power Automate. `capChatMembers()` yang sudah ada dipakai ulang.
4. **`APP_URL` produksi** harus `https://me.eclectic.co.id` supaya tautan tiket di
   pesan Teams tidak menunjuk ke localhost. (Di `.env` dev saat ini masih
   `http://localhost:8000` — jangan sampai nilai itu ikut terbawa ke produksi.)
5. **Akun tanpa mailbox M365.** `POST /chats` menolak alamat yang tidak dikenal
   sebagai kesalahan eksplisit, tapi belum diuji dengan akun seperti `admin@` yang
   pernah menggagalkan "Create a chat" Power Automate. Daftar anggota di langkah 3
   sebaiknya tetap menghormati `POWER_AUTOMATE_TEAMS_EXCLUDE_MEMBERS`.

---

## 9b. Gambar dan lampiran — yang BENAR-BENAR berjalan (17 Sep 2026)

Menggantikan rencana "fase pertama teks saja" di §9.

### Gambar: diunduh, ditampilkan di gelembung tiket

`TeamsInboundService::persistInlineImages()` mengunduh tiap gambar dan mengarahkan
ulang `<img src>`-nya:

```
<img src="https://graph.microsoft.com/v1.0/chats/…/hostedContents/…/$value">
        ↓
<img src="/storage/ticket-inline-images/{ticket_id}/{uuid}.jpg">
```

**Invariant "byte gambar tidak pernah masuk DB" ditegakkan**, dengan konvensi path
yang SAMA dengan `InlineImageService` — bukan jalur baru. Yang tersimpan di
`message_html` hanya URL pendek; hasil uji nyata: tiga gambar 24 KB–307 KB, dan
`message_html`-nya 132–141 byte.

Tiap gambar dapat baris `TicketAttachment` (`attachment_type='image'`,
`is_inline=true`, `uploaded_by_type='system'`) berisi path, ukuran, dan MIME —
MIME diambil dari **header respons** `Content-Type`, karena di daftar
`hostedContents` `contentBytes` dan `contentType` keduanya null.

URL Graph aslinya **tidak boleh** dibiarkan di HTML: ia butuh bearer token, jadi
browser yang membuka tiket pasti mendapat gambar rusak. Kalau unduhannya gagal,
tag `<img>`-nya dibuang dan pesannya diberi penanda — lebih baik daripada gambar
rusak permanen.

HTML dari Teams dilewatkan `MessageHtmlSanitizerService::sanitize()` sebelum
disimpan. `/storage/...` relatif lolos whitelist-nya.

Pagar ukuran: `TEAMS_SYNC_MAX_IMAGE_MB` (default 10). Lebih besar dari itu tidak
diunduh; pesannya tetap masuk dengan penanda.

`message` (teks polos) diisi `(gambar dari Teams)` untuk pesan yang isinya hanya
gambar — kolom itu yang dipakai preview daftar tiket dan teks notifikasi, dan
keduanya akan tampil blanko kalau dibiarkan kosong.

### `attachments` ≠ "ada lampiran"

Koreksi penting dari data nyata. Array `attachments` pada pesan Teams paling
sering justru berisi:

| `contentType` | Artinya |
|---|---|
| `messageReference` | balasan yang **mengutip** pesan lain |
| `forwardedMessageReference` | pesan yang **diteruskan** |
| `application/vnd.microsoft.card.*` | kartu (mis. kartu tiket flow 5) |

Tak satu pun lampiran. Versi pertama kode ini menghitung panjang `attachments`
sebagai jumlah lampiran, sehingga **setiap balasan-mengutip** mendapat penanda
"(1 lampiran di Teams)" yang menyesatkan. Ketiganya sekarang dilewati eksplisit.

### Lampiran berkas: disimpan sebagai TAUTAN

Berkas yang dibagikan di chat tiba sebagai attachment ber-`contentUrl`. Yang
disimpan adalah `TicketAttachment` bertipe `link` (`link_url`, `link_title`,
`file_name`), bukan byte-nya.

**Diaksesnya lewat proxy EcoSystem, bukan tautan SharePoint langsung**
(keputusan 17 Sep 2026). Membuka `contentUrl` apa adanya menuntut login Microsoft
lebih dulu dan sering berujung layar "Request access" — persis masalah yang sudah
dicatat aturan OneDrive repo ini untuk URL SharePoint mentah.

`TicketAttachment::getPublicUrlAttribute()` mengarahkan lampiran semacam ini ke
`route("attachments.show")`, dan `AttachmentController::streamSharePointFile()`
mengambilkannya lewat Graph:

```
sharing token = "u!" + base64url(contentUrl)
GET /shares/{token}/driveItem          -> nama, MIME, ukuran
GET /shares/{token}/driveItem/content  -> byte-nya
```

Byte-nya TIDAK disimpan di server — tiap request diambil ulang, sama seperti
lampiran email.

Dua pagar yang menyertainya:

1. **Host dibatasi** (`isCloudProxyable()`: `*.sharepoint.com`, `*.onedrive.com`,
   `1drv.ms`). `link_url` berasal dari pesan Teams — isinya ditentukan orang lain,
   dan proxy ini memakai token aplikasi yang aksesnya luas. Tanpa pembatasan,
   satu baris attachment berisi URL sembarang bisa memancing server mengambil
   apa pun yang bisa dijangkau token itu.
2. **Batas ukuran** `TEAMS_SYNC_MAX_PROXY_FILE_MB` (default 25). Graph
   mengembalikan isi berkas sekaligus, jadi berkas ratusan MB akan dimuat penuh
   ke memori PHP hanya untuk diteruskan. Yang melebihi batas dialihkan ke
   SharePoint-nya.

**Konsekuensi yang diterima secara sadar:** kendali akses SharePoint jadi
dilewati — siapa pun yang bisa membuka tiketnya bisa mengunduh berkas itu, walau
di SharePoint ia tidak punya izin. Untuk berkas yang memang sengaja dibagikan ke
grup tiket, audiensnya kurang lebih sama, dan itu dasar keputusannya.

Alasannya: berkas itu tinggal di OneDrive/SharePoint pengirimnya dan sudah punya
kendali akses sendiri. Menyalin byte-nya ke storage tiket berarti menduplikasi
dokumen sekaligus melepaskannya dari kendali itu — orang yang aksesnya dicabut di
SharePoint tetap bisa mengunduh salinannya lewat tiket.

**TERUJI 17 Sep 2026** dengan kiriman `.xlsx`. Bentuk aslinya:

```json
"body": { "contentType": "text",
          "content": "tes attachment<attachment id=\"db4af672-…\"></attachment>" },
"attachments": [{ "contentType": "reference",
                  "name": "EmployeeCustomer_Data_Ecosystem_V3.xlsx",
                  "contentUrl": "https://….sharepoint.com/sites/…/Data.xlsx" }]
```

Dua jebakan yang hanya ketahuan dari kiriman sungguhan:

1. **`contentType` = `"text"`, padahal body memuat tag `<attachment>`.** Jalur
   strip HTML tidak pernah menyentuhnya, jadi tagnya bocor mentah-mentah ke
   gelembung tiket (`tes attachment<attachment id="db4af672-…"></attachment>`).
   Tag itu kini dibuang LEBIH DULU, sebelum percabangan html/teks — ia cuma
   penanda posisi, sedangkan lampirannya ditangani terpisah.
2. **`file_name` WAJIB diisi walau ini lampiran tautan.** Kartu lampiran di UI
   menampilkan `file_name` sebagai judulnya
   ([show.blade.php:4059](../resources/views/ticket/show.blade.php#L4059)), jadi
   mengisi `link_title` saja membuat kartunya bertuliskan **"null"**.
