# Hari Libur Nasional Indonesia di Kalender

## Ringkasan

Fitur ini menampilkan hari libur nasional Indonesia secara otomatis pada kalender Events (`/calendar/events`). Data libur diambil dari API publik **Nager.Date** yang mencakup seluruh tahun tanpa perlu konfigurasi tambahan atau API key.

---

## Sumber Data

**API:** [https://date.nager.at/api/v3/PublicHolidays/{year}/ID](https://date.nager.at/api/v3/PublicHolidays/2026/ID)

| Properti | Keterangan |
|---|---|
| Provider | Nager.Date (open source, gratis) |
| Format | JSON |
| API Key | Tidak diperlukan |
| Cakupan | Semua tahun, semua negara (kode `ID` untuk Indonesia) |
| Update | Otomatis oleh Nager.Date setiap tahun |

Contoh response untuk satu entri:
```json
{
  "date": "2026-01-01",
  "localName": "Tahun Baru",
  "name": "New Year's Day",
  "countryCode": "ID",
  "fixed": true,
  "global": true,
  "counties": null,
  "launchYear": null,
  "types": ["Public"]
}
```

---

## File yang Dimodifikasi

| File | Perubahan |
|---|---|
| `public/js/calendar-events.js` | Logika utama: fetch, cache, render holiday |
| `resources/views/calendar/events.blade.php` | Tambah item legend "Hari Libur Nasional" |

---

## Flow Logic

```
User buka halaman /calendar/events
          │
          ▼
DOMContentLoaded → loadEvents()
          │
          ├─ loadHolidays(tahun sekarang)  ←── fetch ke Nager.Date API
          │         │
          │         └─ simpan ke holidayCache[year] = [...]
          │
          ├─ fetch /api/events  ←── events internal dari database
          │
          └─ renderCalendar()
                    │
          ┌─────────┼──────────┐
          ▼         ▼          ▼
    Month View  Week View   Day View
          │         │          │
    getHoliday()  header     banner
    per sel      merah      merah


User navigasi ke bulan/tahun lain
          │
          ▼
previousPeriod() / nextPeriod()
          │
          ├─ loadHolidays(tahun baru)  ←── hanya jika tahun berubah
          │    (jika sudah di-cache, langsung skip)
          │
          └─ renderCalendar()
```

---

## Caching

Holiday di-cache **per tahun** di variabel JavaScript `holidayCache`:

```js
const holidayCache = {};
// Setelah fetch: { 2025: [...], 2026: [...] }
```

- Fetch hanya dilakukan **sekali per tahun per sesi**
- Navigasi antar bulan dalam tahun yang sama tidak re-fetch
- Cache hidup selama sesi browser (tab terbuka)
- Saat user refresh halaman, cache direset dan fetch ulang

---

## Tampilan Visual

### Month View
- Cell tanggal libur: background `bg-red-50` (merah muda)
- Nomor tanggal: warna merah `text-red-600`
- Badge kecil di bawah tanggal: nama libur dalam Bahasa Indonesia (`localName`)
- Hover tooltip pada badge: nama libur dalam Bahasa Inggris (`name`)

### Week View
- Header hari libur: background `bg-red-50`, teks merah
- Nama libur singkat ditampilkan di bawah nomor tanggal (9px, truncate)

### Day View
- Banner merah di atas daftar event:
  `🚩 Hari Libur Nasional: Hari Raya Idul Fitri (Eid al-Fitr)`

### Legend
- Ditambahkan item baru di bagian bawah legenda: kotak merah + label **"Hari Libur Nasional"**

---

## Penanganan Error

Jika API Nager.Date tidak dapat dijangkau (timeout, offline, dll):

```js
} catch {
    holidayCache[year] = []; // array kosong, tidak crash
}
```

- Kalender tetap berfungsi normal tanpa holiday data
- Tidak ada error yang ditampilkan ke user
- Saat navigasi ke tahun berikutnya, fetch akan dicoba ulang

---

## Menambah Sumber Data Libur Cuti Bersama

Nager.Date hanya mencakup libur resmi nasional. Untuk cuti bersama (yang diumumkan pemerintah tiap tahun), dapat ditambahkan sebagai events manual melalui fitur "Create Event" di kalender, dengan tipe **Reminder** atau tipe baru `holiday` yang bisa ditambahkan di masa mendatang.

---

## Referensi

- Nager.Date GitHub: https://github.com/nager/Nager.Date
- Nager.Date API Docs: https://date.nager.at/swagger/index.html
- Daftar kode negara: ISO 3166-1 alpha-2 (`ID` = Indonesia)
