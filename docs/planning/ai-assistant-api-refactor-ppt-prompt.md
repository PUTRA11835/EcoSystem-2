# PROMPT: Buatkan PPT — Refactor AI Assistant ke Arsitektur Berbasis API

> Salin seluruh isi file ini dan kirimkan ke Claude (claude.ai) untuk dibuatkan file PowerPoint (.pptx).

---

## Instruksi untuk Claude

Tolong buatkan presentasi PowerPoint (.pptx) dari outline di bawah ini dengan ketentuan:

- **Bahasa**: Indonesia, formal tapi tetap enak dibaca (hindari jargon teknis berat tanpa penjelasan).
- **Audiens**: campuran — tim engineering dan management/stakeholder non-teknis. Jadi setiap slide teknis harus tetap punya penjelasan yang bisa dipahami orang non-developer.
- **Jumlah slide**: sekitar 15–17 slide, jangan terlalu padat teks per slide (maksimal 4–6 bullet per slide, kalau lebih pecah jadi slide baru).
- **Gaya visual**: tema warna profesional-teknologi (biru tua/teal + aksen putih/abu terang), gunakan ikon sederhana untuk mewakili konsep (database, API, gear, shield/keamanan, roadmap/panah), gunakan diagram sederhana (box & arrow) untuk slide arsitektur "sebelum vs sesudah".
- **Slide perbandingan** (Before vs After) harus dibuat sebagai tabel visual yang jelas, jangan hanya bullet list biasa — ini slide paling penting, buat menonjol.
- **Slide roadmap/fase migrasi** dibuat dalam bentuk timeline horizontal atau step-by-step visual, bukan bullet biasa.
- Tambahkan **speaker notes singkat** di tiap slide (1-2 kalimat) untuk membantu presenter menjelaskan.
- Tutup dengan slide Q&A / Next Steps yang actionable.

Berikut konten & outline slide yang harus digunakan sebagai dasar (boleh dirapikan kalimatnya, tapi jangan menghilangkan poin substansi):

---

## Slide 1 — Cover

**Judul:** Refactor AI Assistant: Dari Direct Database Query ke Arsitektur Berbasis API
**Sub-judul:** Roadmap migrasi bertahap untuk akses data yang lebih aman, scalable, dan maintainable
**Footer:** EcoSystem — Internal Engineering Initiative

---

## Slide 2 — Agenda

1. Apa itu AI Assistant & kenapa dibahas
2. Struktur / cara kerja saat ini
3. Kelebihan & kekurangan pendekatan saat ini
4. Visi arsitektur baru berbasis API
5. Perbandingan Before vs After
6. Roadmap migrasi bertahap
7. Persiapan yang dibutuhkan
8. Peluang peningkatan lain
9. Risiko & mitigasi
10. Kesimpulan & langkah selanjutnya

---

## Slide 3 — Apa itu AI Assistant?

- Fitur chat AI internal di EcoSystem yang bisa dipakai karyawan untuk bertanya seputar data operasional secara natural language: ticket, SLA, delivery project, dsb.
- AI bisa "memanggil" beberapa fungsi (tools) untuk mengambil data nyata dari sistem sebelum menjawab — bukan sekadar jawab dari pengetahuan umum.
- Contoh pertanyaan: *"Berapa ticket yang SLA-nya lewat minggu ini?"*, *"Tampilkan ringkasan delivery project customer X"*.
- Dipakai lintas fitur: AI Assistant (chat utama), AI Research, Ticket Analyzer, AI Summarize ticket, Word Report Generator.

*Speaker note: fokus slide ini memberi gambaran fungsi, bukan teknis dulu.*

---

## Slide 4 — Struktur Saat Ini (Arsitektur High-Level)

Gambarkan diagram sederhana:

```
User (Chat UI)
     |
     v
AiChatService (tool-use loop)
     |
     +--> [ Model AI: Claude / OpenAI ]   <-- ini SUDAH lewat API resmi provider
     |
     +--> [ Tools: get_tickets, get_sla_summary, get_delivery_projects,
     |        list_tables, query_data, aggregate_data, explain_workflow ]
     |             |
     |             v
     |      LANGSUNG query ke Database (Eloquent / Query Builder)
```

**Poin kunci (penting, tekankan di slide ini):**
- Ada 2 lapisan "API" yang perlu dibedakan:
  1. **Lapisan model AI** (Claude/OpenAI) — ini sudah memanggil API resmi provider, sudah baik.
  2. **Lapisan akses data internal** (ticket, SLA, delivery project, dll) — ini yang **BELUM** lewat API, tools AI langsung mengeksekusi query ke database di dalam satu aplikasi monolith yang sama.
- Refactor yang dibahas presentasi ini fokus ke lapisan kedua.

---

## Slide 5 — Cara Kerja Tools Saat Ini

Daftar 7 tools yang dipakai AI Assistant, masing-masing 1 baris penjelasan:

| Tool | Fungsi |
|---|---|
| `get_tickets` | Ambil data ticket sesuai filter |
| `get_sla_summary` | Ambil ringkasan status SLA |
| `get_delivery_projects` | Ambil data delivery project |
| `explain_workflow` | Menjelaskan alur kerja/status |
| `list_tables` | Melihat daftar tabel yang tersedia |
| `query_data` | Query data generik/fleksibel ke DB |
| `aggregate_data` | Agregasi data (sum/count/avg) generik ke DB |

**Sorot:** `query_data` dan `aggregate_data` adalah tools paling fleksibel — AI bisa menyusun query sendiri ke database. Fleksibel = powerful, tapi juga paling berisiko dari sisi keamanan & kontrol akses.

---

## Slide 6 — Kelebihan Struktur Saat Ini

- **Cepat dikembangkan** — tidak perlu membangun & maintain lapisan API terpisah.
- **Fleksibel** — AI bisa menyusun query ad-hoc sesuai kebutuhan pertanyaan user, tanpa harus ada endpoint khusus untuk tiap kombinasi.
- **Overhead minim** — tidak ada tambahan HTTP call internal, langsung ke DB.
- **Cocok untuk tahap awal/MVP** — cara tercepat membuktikan value fitur AI Assistant sebelum investasi arsitektur besar.

---

## Slide 7 — Kekurangan & Risiko Struktur Saat Ini

- **Tight coupling ke skema database** — kalau ada perubahan struktur tabel, tools AI berisiko langsung rusak (tidak ada lapisan pelindung/kontrak).
- **Risiko keamanan** — tool generik (`query_data`, `aggregate_data`) berpotensi mengakses data/field yang seharusnya dibatasi, kalau tidak dijaga ketat.
- **Tidak reusable** — logic akses data ini hanya bisa dipakai AI Assistant di EcoSystem, tidak bisa dipakai ulang oleh sistem lain (mis. JARVIES) tanpa duplikasi kode.
- **Sulit di-test terpisah** — logic query menyatu dengan orkestrasi AI, susah diuji independen dari infrastruktur AI/DB.
- **Otorisasi kurang granular** — kontrol akses per role/permission tidak konsisten dengan yang dipakai di halaman web biasa (controller), berpotensi celah.
- **Tidak ada rate limiting / caching** di level akses data ini.

---

## Slide 8 — Visi Baru: AI Assistant Berbasis API

Diagram "sesudah":

```
User (Chat UI)
     |
     v
AiChatService (tool-use loop)
     |
     +--> [ Model AI: Claude / OpenAI ]  (tetap sama)
     |
     +--> [ Tools ]
              |
              v
     Internal API Layer (REST, versioned, ada auth & rate limit)
              |
              v
     Business Logic & Validasi (reuse dari controller yang sudah ada)
              |
              v
           Database
```

**Ide inti:** tools AI tidak lagi bicara langsung ke database, tapi memanggil API internal — API yang sama yang bisa dipakai juga oleh fitur lain (web app, JARVIES, dsb).

---

## Slide 9 — Perbandingan: Sekarang vs Setelah Refactor (SLIDE PENTING — buat sebagai tabel visual menonjol)

| Aspek | Sekarang (Direct DB Query) | Setelah Refactor (Berbasis API) |
|---|---|---|
| Coupling ke skema DB | Tinggi — perubahan tabel langsung berisiko | Rendah — API jadi kontrak stabil, DB bisa berubah di baliknya |
| Keamanan & kontrol akses | Bergantung pada disiplin kode tiap tool | Terpusat: auth, validasi, scoping per endpoint |
| Reusability lintas aplikasi | Tidak bisa, logic terkunci di AI service | Bisa dipakai app lain (JARVIES, dashboard, dll) |
| Kecepatan development awal | Cepat | Lebih lambat di awal (butuh desain API) |
| Performa (latency) | Sedikit lebih cepat (tanpa hop tambahan) | Ada overhead kecil, bisa dikompensasi caching |
| Observability/monitoring | Terbatas (log tool & ukuran data saja) | Bisa granular: request/response, error rate, latency per endpoint |
| Testability | Sulit diuji terpisah | Mudah — endpoint API bisa di-unit/integration test |
| Maintenance saat skema DB berubah | Berisiko tinggi, banyak titik yang harus diperbaiki | Cukup ubah implementasi API, kontrak ke AI tidak berubah |
| Rate limiting & caching | Tidak ada | Bisa diterapkan di level API gateway/middleware |
| Risiko kebocoran data | Lebih tinggi (query generik) | Lebih rendah (endpoint spesifik, field terkontrol) |

---

## Slide 10 — Roadmap Migrasi Bertahap (buat sebagai timeline/step visual)

**Fase 0 — Audit & Inventarisasi**
Daftar semua tools AI yang saat ini akses DB langsung + query yang dijalankan.

**Fase 1 — Bangun API untuk Tools Berisiko Rendah & Sering Dipakai**
Mulai dari `get_tickets`, `get_sla_summary`, `get_delivery_projects` — buat endpoint API internal, reuse logic dari controller yang sudah ada.

**Fase 2 — Migrasi Tools Satu per Satu (Paralel + Feature Flag)**
Ganti implementasi tool agar memanggil API baru, bukan Eloquent langsung. Jalankan berdampingan dulu untuk membandingkan hasil sebelum full switch.

**Fase 3 — Perketat/Depresiasi Tools Generik**
`query_data` dan `aggregate_data` diganti endpoint spesifik dengan allowlist, atau dibungkus validasi ketat di balik API.

**Fase 4 — Standarisasi Lapisan API**
Tambahkan autentikasi, rate limiting, versioning (`/api/v1/...`), logging terstruktur.

**Fase 5 — Perluasan Pemakaian**
Buka API yang sama untuk konsumen lain: AI Research, Ticket Analyzer, bahkan aplikasi eksternal seperti JARVIES — 1 API dipakai banyak fitur.

**Fase 6 (opsional, jangka panjang)** — Evaluasi apakah orkestrasi AI perlu dipisah jadi service tersendiri di luar monolith EcoSystem, jika skala kebutuhan makin besar.

---

## Slide 11 — Apa yang Perlu Disiapkan Sebelum Migrasi

- Inventarisasi & dokumentasi lengkap semua tools beserta query yang dijalankan saat ini.
- Desain kontrak API: endpoint, format request/response, strategi versioning.
- Kebijakan otorisasi & scoping data per role/permission (reuse sistem role/permission yang sudah ada).
- Strategi testing: unit test + integration test tiap endpoint, plus regression test (bandingkan hasil API baru vs query lama).
- Mekanisme rollback / feature flag supaya bisa switch balik ke direct-query bila API bermasalah saat transisi.
- Observability: logging request/response, monitoring performa & error rate per endpoint.
- Rencana komunikasi & training internal untuk tim yang terdampak.
- Update dokumentasi kebijakan data sensitif AI yang sudah ada, disesuaikan dengan arsitektur baru.

---

## Slide 12 — Peluang Peningkatan Lain (di Luar Refactor API)

- Tambahkan tracking **token/biaya pemakaian** per user/percakapan (saat ini belum ada).
- Tambahkan **retry logic** saat streaming jawaban AI gagal (saat ini error langsung ditampilkan ke user tanpa retry).
- **Rate limiting** pemakaian AI Assistant per user/per hari.
- Satukan dua lapisan riwayat chat yang saat ini terpisah (cache sementara vs arsip database) supaya konteks percakapan panjang tidak hilang.
- Perbaiki ketidaksesuaian dukungan attachment (dokumen Word disebut didukung tapi sebenarnya belum diproses sistem).
- Satukan kebijakan retensi data ke satu tempat terpusat, bukan diatur manual per provider AI.
- Pertimbangkan memindahkan proses streaming jawaban AI keluar dari siklus request web biasa (misalnya via queue/websocket) agar lebih scalable saat pemakaian meningkat.

---

## Slide 13 — Risiko Refactor & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Downtime/bug saat masa transisi | Migrasi bertahap per tool + feature flag, tidak big-bang |
| API lebih lambat dari query langsung | Caching, optimasi query, load testing sebelum go-live |
| Effort development lebih besar di awal | Prioritaskan tool berisiko keamanan tertinggi dulu |
| Perilaku AI berubah tidak sengaja | Regression test otomatis: bandingkan output lama vs baru |

---

## Slide 14 — Manfaat Jangka Panjang

- Keamanan & kontrol akses data lebih terjamin.
- Kode lebih maintainable, tidak rapuh terhadap perubahan skema database.
- API yang dibangun bisa dipakai ulang oleh fitur atau produk lain (efisiensi jangka panjang).
- Mempermudah audit, monitoring, dan compliance terhadap data sensitif.
- Fondasi lebih siap kalau suatu saat AI Assistant perlu diskalakan atau dipisah jadi service sendiri.

---

## Slide 15 — Kesimpulan & Next Steps

- AI Assistant saat ini **sudah bekerja dengan baik**, tapi lapisan akses datanya masih menyatu langsung dengan database — ini titik yang perlu diperkuat.
- Refactor ke arsitektur berbasis API dilakukan **bertahap**, bukan sekaligus, untuk menjaga stabilitas fitur yang sudah berjalan.
- **Next steps yang diusulkan:**
  1. Approval roadmap & alokasi effort tim.
  2. Mulai Fase 0 (audit & inventarisasi tools) minggu depan.
  3. Review progress berkala tiap akhir fase.

---

## Slide 16 — Q&A

**Terima kasih — Diskusi & Pertanyaan**

---

*(Akhir outline. Claude, silakan buatkan file .pptx dari struktur di atas dengan mengikuti instruksi desain di bagian atas.)*
