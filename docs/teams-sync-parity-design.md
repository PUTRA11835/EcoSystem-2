# Rancangan: paritas perilaku EcoSystem ↔ Teams

> Status: **PEKERJAAN 3 (lampiran arah keluar) SELESAI & TERUJI 23 September
> 2026.** Pekerjaan 1 dan 2 belum. Dokumen dibuat 23 September 2026.
> Lanjutan dari [teams-chat-sync-design.md](teams-chat-sync-design.md) yang
> langkah 0–5 sudah selesai dan kedua arahnya terbukti jalan.
> Konfigurasi flow Power Automate: [power-automate/README.md](power-automate/README.md).

## 1. Yang diminta

Harapannya: "integrasi ini benar-benar mengakomodasi perilaku user agar semuanya
sama". Tiga pekerjaan yang dipilih untuk mengejar itu:

| # | Pekerjaan | Bobot | Nilai |
|---|---|---|---|
| 1 | **Edit & hapus arah MASUK** (Teams → EcoSystem) | ringan | tinggi — paling sering terjadi |
| 2 | **Edit & hapus arah KELUAR** (EcoSystem → Teams) | berat | menengah — terbentur batas platform |
| 3 | **Lampiran arah KELUAR** (EcoSystem → Teams) | menengah | tinggi — **SUDAH DIKERJAKAN** |

Urutan kerja yang disarankan semula **1 → 3 → 2**; atas permintaan (23 Sep 2026)
**pekerjaan 3 dikerjakan lebih dulu**, pekerjaan 1 menyusul. Alasan urutan
aslinya tetap di §7.

## 2. Keadaan sekarang, supaya tidak salah duga

| Perilaku | Masuk | Keluar |
|---|---|---|
| Pesan teks | ✅ | ✅ |
| Gambar | ✅ byte diunduh, jadi `TicketAttachment` `image` | ✅ tampil langsung di badan pesan (`<img>`), terbukti dirender Teams |
| Berkas (PDF/dokumen) | ⚠️ disimpan sebagai **tautan** SharePoint, diakses lewat proxy EcoSystem | ✅ tautan bertanda tangan ke proxy EcoSystem |
| Edit | ❌ | ❌ |
| Hapus | ❌ | ❌ |
| Reply-thread | ❌ | ❌ (tidak mungkin — §8) |

Tanda ❌ pada edit/hapus/reply **bukan bug**: semuanya tercatat di
[§13 "Di luar scope (sengaja)"](teams-chat-sync-design.md) rancangan sebelumnya.
Dokumen ini yang mencabut dua di antaranya dari daftar itu.

## 3. Pagar yang tetap berlaku

Diwarisi utuh dari [§2 rancangan sebelumnya](teams-chat-sync-design.md) — tidak
ada satu pun yang dilonggarkan oleh pekerjaan ini:

- **`ticket` dan `ticket_message` tidak boleh ditambah kolom.** Keduanya dibagi
  dengan repo JARVIES. Semua metadata Teams tinggal di tabel pendamping
  (`ticket_teams_chat`, `ticket_message_teams`, `teams_outbox`) — ketiganya milik
  repo ini sendiri, jadi bebas diubah.
- **Byte gambar tidak pernah masuk DB.** Konvensi path `InlineImageService`
  (`ticket-inline-images/{ticket_id}/{uuid}.{ext}`), DB hanya menyimpan path.
- **Berkas yang tidak disentuh:** `EmailController`, `GraphRelayService`,
  `StagingTicketService`, `SlaService`, `InlineImageService`, command
  `email:process-inbox`.
- **Kegagalan Teams tidak boleh menggagalkan operasi tiket.** Semua pemanggilan
  di luar transaksi, dibungkus `try/catch`, dijalankan setelah response.
- **Pagar anti-loop tetap utama:** note ber-`direction='in'` tidak pernah dikirim
  balik ke Teams.

---

## 4. Pekerjaan 1 — edit & hapus arah MASUK

### Yang sudah tersedia

Tiga hal membuat pekerjaan ini murah:

1. **Polling sudah memakai `lastModifiedDateTime`**, bukan `createdDateTime`
   ([TeamsInboundService.php](../app/Services/Teams/TeamsInboundService.php),
   `syncChat()`): `$filter=lastModifiedDateTime gt {cursor}`. Pesan yang diedit
   **otomatis muncul lagi** di jendela polling berikutnya. Tidak perlu mekanisme
   baru untuk menemukannya.
2. **Pemetaan pesan sudah tersimpan.** `ticket_message_teams` memegang
   `teams_message_id` ↔ `ticket_message_id`, unik per `(chat_id, teams_message_id)`.
3. **`ticket_message` sudah punya `is_deleted`** (boolean, sudah di `$fillable`
   dan `$casts`) — penghapusan tidak butuh kolom baru di tabel terlarang itu.

### Kenapa sekarang tidak jalan

Dua penyaring yang sengaja dipasang, dan harus dilonggarkan **dengan hati-hati**:

- `syncChat()` mem-`continue` setiap pesan yang id-nya sudah ada di
  `ticket_message_teams`. Ini yang membuang pesan hasil edit.
- `humanMessages()` membuang pesan ber-`deletedDateTime`. Ini yang membuat
  penghapusan tak terlihat.

### Jebakan terbesar: `lastModifiedDateTime` berubah TANPA ada yang mengedit

Ini bagian terpenting dokumen ini, dan sudah terbukti di lapangan (17 Sep 2026,
tercatat di migrasi `ticket_message_teams`):

> Pesan bergambar punya `lastModifiedDateTime` **~7 detik sesudah**
> `createdDateTime` — Teams memproses gambarnya belakangan. Yang terjadi bukan
> edit oleh manusia.

Kalau setiap perubahan `lastModifiedDateTime` diperlakukan sebagai edit, **setiap
pesan bergambar akan diproses ulang**, gambarnya diunduh dua kali, dan baris
`TicketAttachment`-nya berlipat.

**Pembedanya `lastEditedDateTime`**, yang pada kasus pemrosesan gambar itu tetap
`null` dan hanya terisi kalau manusia benar-benar mengedit. Jadi syarat edit yang
benar:

```
lastEditedDateTime != null  DAN  lastEditedDateTime > yang tersimpan
```

**Bukan** `lastModifiedDateTime > yang tersimpan`.

### Perubahan yang dibutuhkan

**Migrasi** — kolom baru di `ticket_message_teams` (tabel milik repo ini):

| Kolom | Tipe | Guna |
|---|---|---|
| `teams_last_edited_at` | `dateTime` nullable | pembanding "versi keberapa yang sudah diserap" |
| `deleted_in_teams_at` | `dateTime` nullable | jejak penghapusan; berguna saat menelusuri kenapa sebuah note hilang |

**`TeamsInboundService::syncChat()`** — ganti `continue` menjadi percabangan:
pesan yang id-nya sudah dikenal diperiksa `lastEditedDateTime`-nya; kalau lebih
baru dari `teams_last_edited_at`, jalankan jalur update.

**Penghapusan** harus ditangani **sebelum** `humanMessages()` membuangnya — kalau
tidak, pesan terhapus tidak pernah sampai ke logika mana pun. Jalur paling bersih:
satu lintasan terpisah atas `$all` yang hanya mencari `deletedDateTime` + id yang
dikenal, dijalankan sebelum penyaringan.

### Yang harus diputuskan sebelum koding

| Keputusan | Pilihan | Saran |
|---|---|---|
| Isi note saat diedit | (a) timpa; (b) timpa + penanda "(diedit di Teams)"; (c) simpan riwayat | **(b)** — timpa supaya isinya benar, penanda supaya orang tidak bingung melihat isi berubah sendiri |
| Note saat pesannya dihapus | (a) `is_deleted = true`; (b) biarkan + penanda "(dihapus di Teams)" | **(a)** — perlakuan sama dengan note yang dihapus di EcoSystem |
| Gambar pada pesan yang diedit | (a) unduh ulang, buang baris lama; (b) biarkan gambar lama | **(a)**, tapi hanya kalau daftar `<img>`-nya benar-benar berubah — mengunduh ulang tiap edit teks itu pemborosan |
| Lampiran tautan pada pesan yang diedit | samakan dengan gambar | ikuti keputusan di atas |

### Risiko

- **Regresi anti-duplikat.** Penyaring yang dilonggarkan inilah yang selama ini
  menahan pesan bergambar agar tidak dobel. Uji regresi wajib — lihat §9 butir 1.
- **Note yang sudah dibalas orang lalu isinya berubah.** Tidak ada solusi teknis;
  penanda "(diedit di Teams)" adalah mitigasi sosialnya.

---

## 5. Pekerjaan 2 — edit & hapus arah KELUAR

### Batas platform: edit/unsend sungguhan TIDAK MUNGKIN

Tiga pagar, semuanya di luar kendali kita:

1. **Graph app-only tidak bisa menulis pesan chat.** `POST /chats/{id}/messages`
   tidak tersedia untuk izin aplikasi (hanya `Teamwork.Migrate.All`, untuk
   migrasi). Ini yang sejak awal memaksa arah keluar lewat Power Automate. Hal
   yang sama berlaku untuk `PATCH` dan `DELETE`.
2. **Konektor Teams di Power Automate tidak punya aksi "edit message" maupun
   "delete message"** untuk group chat.
3. Aksi yang ada (*Post message in a chat or channel*) hanya bisa membuat pesan
   **baru**.

Kesimpulannya: yang bisa dikerjakan adalah **pesan susulan**, bukan mengubah
pesan yang sudah terkirim. Ini harus dikomunikasikan apa adanya ke tim — menjual
ini sebagai "edit tersinkron" akan menimbulkan harapan yang pasti meleset.

### Bentuk yang disarankan

```
Tio Pramudya · EcoSystem (note diperbarui)
Service sudah up — ternyata masih intermiten di node 2.
```

dan untuk penghapusan:

```
Tio Pramudya · EcoSystem
Note sebelumnya dihapus di EcoSystem.
```

Penghapusan lebih penting daripada pengeditan: informasi yang salah perlu
ditarik, dan orang di grup tidak punya cara lain mengetahuinya.

### Penghalang teknis yang harus dibereskan lebih dulu

**`teams_outbox.ticket_message_id` bersifat `unique`.** Satu note = satu kiriman,
selamanya. Pesan susulan berarti baris kedua untuk `ticket_message_id` yang sama,
jadi constraint itu **harus** diubah:

```
unique(ticket_message_id)  →  kolom `kind` baru + unique(ticket_message_id, kind)
```

dengan `kind` ∈ `note` | `meeting` | `edited` | `deleted`. Ini sekaligus merapikan
jalur meeting yang sekarang menumpang tanpa penanda jenis.

> Jangan sekadar membuang `unique`-nya. Constraint itu yang menahan note terkirim
> dua kali kalau jalur antre kebetulan terpanggil ulang — pagar yang masih
> dibutuhkan, hanya perlu diperhalus.

### Yang harus diputuskan sebelum koding

| Keputusan | Catatan |
|---|---|
| Edit dikirim susulan, atau tidak sama sekali? | Tiap penyuntingan kecil (typo) memicu satu pesan grup. Pertimbangkan **jeda**: hanya kirim kalau note diedit lebih dari N menit setelah terkirim |
| Hapus dikirim susulan? | Saran: **ya**, tanpa jeda |
| Susulan membawa isi baru, atau cuma pemberitahuan? | Membawa isi = grup tidak perlu buka tiket; tapi menggandakan isi note di grup |

---

## 6. Pekerjaan 3 — lampiran arah KELUAR

> **DIKERJAKAN 23 September 2026.** Jalur A (tautan bertanda tangan) yang
> dipakai, dengan dua keputusan: **lampiran inline ikut** dan **tautan tanpa
> batas waktu**. Rinciannya di §6a; bagian di bawahnya adalah rancangan
> aslinya, disimpan karena alasan-alasannya masih berlaku.

### 6a. Yang benar-benar dikerjakan

| Berkas | Perubahan |
|---|---|
| [routes/web.php](../routes/web.php) | route baru `attachments.teams` — **di luar** grup ber-`CheckAuthToken`, dijaga middleware `signed` |
| [AttachmentController.php](../app/Http/Controllers/AttachmentController.php) | badan `show()` dipindah ke `streamAttachment()`; `show()` (login) dan `showForTeams()` (bertanda tangan) sama-sama memakainya — logika streaming tidak digandakan |
| [TeamsOutboxService.php](../app/Services/Teams/TeamsOutboxService.php) | `attachmentLinks()`, `attachmentHtml()`, `attachmentText()`; `wrap()` kini menerima tambahan HTML dan teks secara terpisah |
| [config/services.php](../config/services.php) + `.env.example` | `TEAMS_ATTACHMENT_LINK_DAYS` (0 = tanpa batas) |

**Tidak ada perubahan di Power Automate.** Flow 7 tetap mengirim
`message_html` apa adanya — gambar dan tautan menumpang di field yang sudah
dipakai.

**Kenapa badan note dan blok lampiran dipisah di `wrap()`:** badan note
di-escape (teks ketikan manusia), blok lampiran tidak (memang HTML). Kalau
digabung lebih dulu, `<img>` ikut ter-escape dan tampil sebagai teks mentah.

### Hasil uji 23 September 2026 — `<img>` JANGAN dibungkus `<a>`

Teams **merender** `<img>` ber-URL eksternal di pesan chat. Tapi itu baru
ketahuan di percobaan kedua, dan percobaan pertamanya menyesatkan:

| Yang dikirim | Yang terjadi di Teams |
|---|---|
| `<a href="URL"><img src="URL"></a>` | **seluruh blok hilang** — bukan gambar rusak, tidak ada bekas apa pun |
| `<img src="URL">` + baris URL telanjang | gambar dirender; URL jadi tautan yang bisa diklik |

Blok pertama yang lenyap tanpa jejak sempat disimpulkan sebagai "Teams membuang
`<img>`". Yang sebenarnya dibuang adalah **anchor yang membungkus gambar**.
Karena itu "klik untuk membuka penuh" diwakili baris URL terpisah, bukan dengan
membungkus gambarnya.

Dua pelajaran lain dari uji yang sama:

1. **Baris tautan wajib ada di `message_html`, bukan cuma di `message`.** Versi
   pertama menaruh daftar tautan hanya di `message` (teks cadangan), jadi ketika
   blok HTML-nya dibuang, pesan yang sampai tidak menyisakan petunjuk apa pun
   bahwa note itu punya lampiran.
2. **Entitas HTML ter-escape dua kali.** Kolom `message` menyimpan hasil
   `strip_tags`, yang tidak menyentuh entitas — note berisi `->` tersimpan
   sebagai `-&gt;`, lalu `e()` mengubahnya jadi `-&amp;gt;` di Teams. Diperbaiki
   dengan `html_entity_decode()` sebelum perakitan. Bug ini **sudah ada sejak
   sebelum pekerjaan ini**; baru terlihat karena belum pernah ada note yang
   memuat `<`, `>`, atau `&`.

**Yang belum diuji di lingkungan sebenarnya:** gambarnya baru terbukti *dirender*,
belum terbukti *tampil*, karena uji dilakukan dari `127.0.0.1` yang tidak bisa
dijangkau klien Teams — yang muncul kotak gambar rusak. Menjalankannya di server
yang bisa diakses dari internet semestinya melengkapi yang terakhir ini.

**Jebakan lapangan:** tautan dibangun dari `APP_URL`. Server yang `APP_URL`-nya
masih `http://localhost` akan mengirim tautan yang tidak bisa dibuka siapa pun,
dan itu baru ketahuan setelah pesannya terlanjur sampai di grup.

### 6b. Rancangan asli


### Keadaan sekarang

`TeamsOutboxService::buildPayload()` hanya menghitung lampiran non-inline lalu
menambahkan satu baris:

```
(2 lampiran — lihat tiket)
```

**Ada utang kecil yang ikut dibereskan di sini.** Sejak baris `nomor → tautan`
dihapus (keputusan meeting 18 Sep 2026), kalimat "lihat tiket" tidak lagi punya
tautan yang dimaksud. Tiga pilihan: (a) biarkan, (b) ubah jadi "(2 lampiran di
tiket)", (c) kembalikan tautan **khusus** untuk note berlampiran. **Keputusan ini
masih terbuka**; kalau pekerjaan 3 dikerjakan penuh, (a)/(b) gugur sendiri karena
lampirannya benar-benar ikut.

### Batasan

Aksi *Post message in a chat or channel* **tidak punya parameter lampiran**. Jadi
berkasnya tidak bisa dititipkan ke konektor — yang bisa dikirim hanya **tautan**
di dalam badan pesan, atau **gambar** di dalam Adaptive Card. Keduanya menuntut
URL yang bisa diambil klien Teams **tanpa sesi login EcoSystem**.

### Dua jalur

**Jalur A — tautan bertanda tangan ke EcoSystem (disarankan).**

Cerminan dari arah masuk, yang juga menyimpan berkas sebagai tautan, bukan
salinan. Butuh **route publik bertanda tangan** yang baru: `attachments.show`
yang sekarang berada **di dalam grup ber-`CheckAuthToken`**
([routes/web.php:657](../routes/web.php#L657)), jadi tidak bisa dipakai apa
adanya — orang di grup Teams akan mendapat layar login. Bentuknya
`URL::temporarySignedRoute()` dengan masa berlaku terbatas.

**Jalur B — unggah ke SharePoint lewat Graph app-only, kirim tautannya.**

Lebih "asli" (berkasnya hidup di tempat yang sama dengan lampiran Teams lain),
tapi menuntut permission baru (`Files.ReadWrite.All` atau `Sites.ReadWrite.All`),
satu lokasi penyimpanan yang harus disepakati, dan kebijakan retensinya sendiri.
**Jangan mulai dari sini.**

### Yang harus diputuskan sebelum koding

| Keputusan | Saran |
|---|---|
| Masa berlaku tautan | 7–14 hari. Jangan tanpa batas |
| Gambar: tautan biasa atau Adaptive Card `Image`? | Fase 1 tautan saja; kartu menyusul kalau memang diminta |
| Ukuran maksimum | Ikuti pagar yang sudah ada (`TEAMS_SYNC_MAX_IMAGE_MB`), atau tetapkan sendiri untuk berkas |
| Lampiran inline (gambar di badan note) | Fase 1 **lewati** — yang dikirim hanya lampiran non-inline, sama dengan penghitungan sekarang |

### Risiko

- **Tautan bertanda tangan itu kapabilitas, bukan izin.** Siapa pun yang
  meneruskannya bisa membuka berkasnya tanpa akun EcoSystem. Masa berlaku pendek
  adalah mitigasi utamanya; pertimbangkan juga mencatat aksesnya.
- Grup chat tiket berisi karyawan internal, jadi risikonya menengah — tapi
  lampiran tiket bisa memuat data customer.

---

## 7. Urutan kerja yang disarankan

1. **Pekerjaan 1 (edit & hapus masuk)** — paling murah, paling sering terpakai,
   dan tidak menyentuh Power Automate sama sekali. Bisa selesai tanpa menunggu
   keputusan siapa pun kecuali empat butir di §4.
2. **Pekerjaan 3 (lampiran keluar)** — menyelesaikan utang kalimat "lihat tiket"
   sekaligus. Butuh satu route baru dan satu keputusan keamanan.
3. **Pekerjaan 2 (edit & hapus keluar)** — terakhir, karena hasilnya paling tidak
   memuaskan (susulan, bukan edit sungguhan) dan menuntut perubahan constraint
   `teams_outbox` yang sebaiknya dilakukan saat dua pekerjaan lain sudah stabil.

## 8. Yang TETAP di luar scope

- **Reply-thread dua arah.** Arah masuk sebenarnya murah — `messageReference`
  sudah tiba di payload (sekarang sengaja dilewati di `fileAttachments()`),
  `ticket_message` sudah punya `reply_to_id`, dan pemetaan id sudah ada di
  `ticket_message_teams`. Tapi arah keluar **tidak mungkin**: group chat Teams
  tidak punya thread sama sekali (hanya quote-reply, yang tidak diekspos
  konektor), dan balasan berjenjang hanya ada di bentuk *channel* yang sudah
  ditinggalkan sejak meeting 9 Sep 2026. Ditunda atas permintaan (23 Sep 2026).
- Reaction dan mention Teams arah masuk.
- Menarik riwayat chat sebelum fitur menyala.
- Membuat meeting Teams sendiri lewat Graph.

## 9. Uji yang harus lulus sebelum dianggap selesai

**Regresi — wajib, ini yang paling mudah rusak:**

1. Pesan **bergambar** dari Teams → tunggu **dua** putaran polling penuh → tetap
   satu note, satu baris `TicketAttachment`, gambar tampil di gelembung tiket.
2. Internal note biasa dari EcoSystem → tetap satu pesan di grup, tidak jadi
   kartu, tidak berulang.
3. Jadwal meeting → tetap kartu dengan tombol *Gabung Meeting*.
4. Balasan-mengutip di Teams → tetap **tidak** mendapat penanda "1 lampiran".

**Fitur baru:**

5. Edit pesan di Teams → note ikut berubah, penanda "(diedit di Teams)" muncul,
   **tidak** ada note baru.
6. Hapus pesan di Teams → note hilang dari tampilan tiket.
7. Note berlampiran dari EcoSystem → tautan di grup bisa dibuka **tanpa login**
   oleh orang yang belum pernah membuka EcoSystem.
8. Tautan yang sama sesudah masa berlakunya lewat → ditolak dengan rapi, bukan
   error 500.

## 10. Titik masuk kode

| Berkas | Perannya di pekerjaan ini |
|---|---|
| [TeamsInboundService.php](../app/Services/Teams/TeamsInboundService.php) | `syncChat()` (penyaring id dikenal), `humanMessages()` (penyaring `deletedDateTime`), `store()`, `persistInlineImages()`, `fileAttachments()` |
| [TeamsOutboxService.php](../app/Services/Teams/TeamsOutboxService.php) | `buildPayload()` (penanda lampiran), `wrap()`, `queue()`, `queueMeeting()` |
| [TicketMessageTeams.php](../app/Models/TicketMessageTeams.php) | jembatan id; `existingIds()` yang perlu diperluas |
| [TeamsOutboxItem.php](../app/Models/TeamsOutboxItem.php) | antrean; constraint `unique(ticket_message_id)` |
| [AttachmentController.php](../app/Http/Controllers/AttachmentController.php) | `streamSharePointFile()` sebagai contoh proxy yang sudah ada |
| [routes/web.php](../routes/web.php) | `attachments.show` (baris 657) — di dalam grup ber-auth |
