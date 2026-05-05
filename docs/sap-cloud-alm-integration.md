# SAP Cloud ALM Integration — EcoSystem-2

**Referensi Kebijakan:** SAP PartnerEdge VAR-Delivered Support, efektif April 1, 2026  
**Status Dokumen:** Perencanaan — belum ada implementasi  
**Terakhir diperbarui:** 2026-04-30  
**Prioritas:** Kritikal (compliance deadline akhir 2028, wajib sebelum 2029)

---

## 1. Latar Belakang

Eclectic adalah **SAP VAR-Delivered Support Partner** yang menyediakan layanan support SAP kepada customer. Selama ini, penerusan tiket support ke SAP (case forwarding) dilakukan melalui **SAP Solution Manager**.

Berdasarkan kebijakan SAP PartnerEdge terbaru:

| Timeline | Perubahan |
|---|---|
| **April 1, 2026** | SAP Cloud ALM Tenant Extension API tersedia sebagai alternatif SAP Solution Manager |
| **Akhir 2027** | SAP Solution Manager masuk fase *customer-specific maintenance* |
| **Akhir 2028** | Hak penggunaan SAP Solution Manager untuk VAR-delivered support **dihentikan** |
| **2029 dan seterusnya** | **Hanya** SAP Cloud ALM Tenant Extension API yang diakui untuk program VAR-delivered support |

EcoSystem-2 sebagai sistem ITSM internal harus diintegrasikan dengan SAP Cloud ALM Tenant Extension API agar tetap compliant dengan program VAR-delivered support SAP PartnerEdge.

---

## 2. Arsitektur Integrasi

### Alur Saat Ini (via SAP Solution Manager)
```
EcoSystem-2 Ticket
    → [manual/SolMan] → SAP Solution Manager
                            → SAP Support Network
```

### Alur Target (via SAP Cloud ALM API)
```
EcoSystem-2 Ticket
    → [API call] → SAP Cloud ALM Tenant Extension
                        → SAP ITSM (SAP for Me)
                            → SAP Support Network
                                → ECS / Customer System

SAP → [webhook/polling] → EcoSystem-2 (status updates, proactive cases)
```

### Komponen yang Terlibat di Sisi SAP
Berdasarkan dokumentasi SAP Cloud ALM Tenant Extension:

| Komponen | Fungsi |
|---|---|
| **ITSM Adapter API** | Interface utama untuk integrasi ITSM pihak ketiga |
| **SAP Case Management** | Pengelolaan case support di sisi SAP |
| **SAP Proactive Cases** | Case yang dibuka SAP secara proaktif ke partner |
| **Outage Data** | Data gangguan layanan SAP |
| **SAP Master Data** | Data produk, sistem, instalasi customer |
| **Knowledge Integration** | KBA (Knowledge Base Articles) SAP |

---

## 3. Persyaratan Compliance VAR-Delivered Support

Berikut seluruh requirement Partner COE yang relevan dan implikasinya terhadap EcoSystem-2:

| Requirement | Threshold | Status Saat Ini | Implikasi EcoSystem-2 |
|---|---|---|---|
| **Correction Share** | ≥ 50% | Belum ditrack otomatis | Perlu fitur tracking tiket yang diselesaikan sendiri vs diteruskan ke SAP |
| **Level 1 Support Qualification** | 3 konsultan tersertifikasi | Non-teknis | — |
| **Product-Specific Certification** | 2 konsultan per product family | Non-teknis | — |
| **Remote Connectivity** | ≥ 70% | Belum ditrack | Perlu logging sesi remote ke sistem customer |
| **SAP EarlyWatch Alert** | ≥ 70% | Belum ditrack | Perlu tracking customer dengan EWA aktif |
| **Supported ITSM Channel** | SAP Cloud ALM API | Saat ini via SolMan | **Integrasi utama yang harus dibangun** |
| **Case Forwarding** | Via SAP Cloud ALM API | Saat ini via SolMan | **Bagian dari integrasi utama** |
| **Partner Documentation** | Wajib ada | — | Dokumen ini sebagai awal |

---

## 4. Perubahan yang Diperlukan

### 4.1 Infrastruktur & Konfigurasi

#### 4.1.1 Langganan SAP Cloud ALM Tenant Extension
- Subscribe ke layanan "SAP Cloud ALM, tenant extension" dari SAP PartnerEdge (berbayar, fee terpisah)
- Dapatkan: Tenant URL, Client ID, Client Secret untuk OAuth2
- **Catatan penting dari SAP:** Tenant ini harus digunakan **eksklusif** untuk keperluan VAR-delivered support — tidak boleh digunakan untuk keperluan lain

#### 4.1.2 Konfigurasi di EcoSystem-2
Perlu tabel atau entri config baru untuk menyimpan:

```
sap_cloud_alm_tenant_url      → https://{tenant}.authentication.{region}.hana.ondemand.com
sap_cloud_alm_client_id       → OAuth2 Client ID
sap_cloud_alm_client_secret   → OAuth2 Client Secret (encrypted at rest)
sap_cloud_alm_token_endpoint  → URL endpoint OAuth2 token
sap_cloud_alm_api_base_url    → Base URL ITSM Adapter API
sap_webhook_secret            → Secret untuk verifikasi webhook inbound dari SAP
```

---

### 4.2 Perubahan Database

#### 4.2.1 Tabel `ticket` — Tambah Kolom SAP

```sql
ALTER TABLE ticket ADD COLUMN sap_case_number      VARCHAR(50)  NULL;
ALTER TABLE ticket ADD COLUMN sap_case_status      VARCHAR(50)  NULL;
ALTER TABLE ticket ADD COLUMN sap_priority_code    VARCHAR(20)  NULL;
ALTER TABLE ticket ADD COLUMN sap_product_version  VARCHAR(100) NULL;
ALTER TABLE ticket ADD COLUMN is_sap_forwardable   BOOLEAN      DEFAULT FALSE;
ALTER TABLE ticket ADD COLUMN sap_forwarded_at     TIMESTAMP    NULL;
ALTER TABLE ticket ADD COLUMN sap_last_synced_at   TIMESTAMP    NULL;
ALTER TABLE ticket ADD COLUMN sap_sync_error       TEXT         NULL;
```

**Catatan kolom:**
- `sap_case_number` — diisi setelah berhasil create case di SAP, format biasanya `{prefix}-{number}`
- `sap_case_status` — mirror status dari SAP: `In Process`, `Customer Action`, `Proposed Solution`, `Closed`, dll.
- `is_sap_forwardable` — flag yang ditentukan saat tiket dibuat, berdasarkan tipe/kategori
- `sap_sync_error` — pesan error terakhir jika sync gagal, untuk troubleshooting

#### 4.2.2 Tabel `customer` — Tambah Kolom SAP System Info

```sql
ALTER TABLE customer ADD COLUMN sap_installation_number VARCHAR(20) NULL;
ALTER TABLE customer ADD COLUMN sap_system_id           VARCHAR(10) NULL;
ALTER TABLE customer ADD COLUMN sap_product_family      VARCHAR(100) NULL;
ALTER TABLE customer ADD COLUMN sap_support_level       ENUM('standard','premium','enterprise') NULL;
```

**Catatan kolom:**
- `sap_installation_number` — nomor instalasi SAP customer, **wajib ada** sebelum tiket bisa diteruskan ke SAP
- `sap_system_id` — SID SAP system (misal: `PRD`, `DEV`)
- `sap_product_family` — produk SAP yang digunakan customer (misal: `SAP S/4HANA`, `SAP Business One`)

#### 4.2.3 Tabel Baru: `sap_sync_logs`

Mencatat setiap aktivitas sinkronisasi antara EcoSystem-2 dan SAP:

```sql
CREATE TABLE sap_sync_logs (
    id             BIGINT PRIMARY KEY AUTO_INCREMENT,
    ticket_id      BIGINT       NOT NULL,
    direction      ENUM('outbound','inbound') NOT NULL,
    action         VARCHAR(50)  NOT NULL,  -- create_case, update_case, status_update, proactive_case
    request_body   LONGTEXT     NULL,
    response_body  LONGTEXT     NULL,
    http_status    SMALLINT     NULL,
    success        BOOLEAN      DEFAULT FALSE,
    error_message  TEXT         NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES ticket(ticket_id)
);
```

#### 4.2.4 Tabel Baru: `sap_proactive_cases`

Untuk menampung case yang dibuka SAP secara proaktif ke partner:

```sql
CREATE TABLE sap_proactive_cases (
    id                  BIGINT PRIMARY KEY AUTO_INCREMENT,
    sap_case_number     VARCHAR(50)  NOT NULL UNIQUE,
    customer_id         BIGINT       NULL,
    installation_number VARCHAR(20)  NULL,
    subject             VARCHAR(500) NOT NULL,
    description         LONGTEXT     NULL,
    priority            VARCHAR(20)  NULL,
    status              VARCHAR(50)  NULL,
    sap_created_at      DATETIME     NULL,
    linked_ticket_id    BIGINT       NULL,  -- jika sudah dibuatkan tiket EcoSystem-2
    acknowledged_at     TIMESTAMP    NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NULL,
    FOREIGN KEY (customer_id)      REFERENCES customer(customer_id),
    FOREIGN KEY (linked_ticket_id) REFERENCES ticket(ticket_id)
);
```

#### 4.2.5 Tabel Baru: `sap_compliance_metrics` (untuk tracking correction share, dll.)

```sql
CREATE TABLE sap_compliance_metrics (
    id                       BIGINT PRIMARY KEY AUTO_INCREMENT,
    period_year              SMALLINT     NOT NULL,
    period_month             TINYINT      NOT NULL,
    total_tickets            INT          DEFAULT 0,
    self_resolved_tickets    INT          DEFAULT 0,
    forwarded_to_sap         INT          DEFAULT 0,
    correction_share_pct     DECIMAL(5,2) NULL,  -- self_resolved / total * 100
    remote_sessions_total    INT          DEFAULT 0,
    remote_sessions_logged   INT          DEFAULT 0,
    remote_connectivity_pct  DECIMAL(5,2) NULL,
    ewa_eligible_customers   INT          DEFAULT 0,
    ewa_active_customers     INT          DEFAULT 0,
    ewa_pct                  DECIMAL(5,2) NULL,
    calculated_at            TIMESTAMP    NULL,
    created_at               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_period (period_year, period_month)
);
```

---

### 4.3 Backend — Service & Controller Baru

#### 4.3.1 `SapCloudAlmService` (Service Class)

Service utama untuk semua komunikasi dengan SAP Cloud ALM API:

```
Metode yang dibutuhkan:
├── getAccessToken()              → OAuth2 token management (cache + auto-refresh)
├── createCase(ticket)            → POST ke ITSM Adapter: buat case baru di SAP
├── updateCase(sapCaseNumber, data) → PATCH: update case yang sudah ada
├── getCase(sapCaseNumber)        → GET: ambil detail case dari SAP
├── closeCase(sapCaseNumber)      → update status ke closed
├── listProactiveCases()          → ambil daftar proactive cases dari SAP
├── getOutageData()               → ambil data gangguan layanan SAP
└── testConnection()              → untuk health check di admin panel
```

**OAuth2 Flow:**
```
POST {token_endpoint}
  grant_type=client_credentials
  client_id={client_id}
  client_secret={client_secret}
  
→ Response: { access_token, expires_in }
→ Cache token sampai hampir expired (threshold: 5 menit sebelum expire)
```

**Mapping Field Tiket EcoSystem-2 → SAP Case:**

| Field EcoSystem-2 | Field SAP Case |
|---|---|
| `ticket.subject` | `subject` |
| `ticket.description` (dari pesan pertama) | `description` |
| `ticket.ticket_priority` | `priority` (mapping: Critical→1-Very High, High→2-High, Medium→3-Medium, Low→4-Low) |
| `customer.sap_installation_number` | `installationNumber` |
| `customer.sap_product_family` | `componentId` (perlu mapping per produk) |
| `ticket.ticket_id` | `externalId` (referensi balik ke EcoSystem-2) |
| `employee.eci` (PIC) | `contactPerson` (ECI sebagai identifier) |

#### 4.3.2 `SapWebhookController`

Menerima notifikasi push dari SAP ketika ada update pada case:

```
Endpoint: POST /api/sap/webhook
Auth:     Verifikasi HMAC signature dari SAP (header: X-SAP-Signature)

Event yang perlu dihandle:
├── case.status_changed     → Update sap_case_status di ticket
├── case.message_added      → Tambah pesan ke ticket messages (dari SAP support)
├── case.closed             → Update status ticket jadi resolved/closed
└── proactive_case.created  → Insert ke sap_proactive_cases
```

#### 4.3.3 `SapSyncJob` (Queue Job)

Job terjadwal untuk sinkronisasi berkala (backup dari webhook):

```
Frekuensi: setiap 30 menit (dapat dikonfigurasi)
Fungsi:
├── Sync status semua tiket yang is_sap_forwardable = true dan belum closed
├── Pull proactive cases baru dari SAP
├── Update sap_compliance_metrics bulanan
└── Retry tiket yang sap_sync_error tidak null
```

#### 4.3.4 `AdminSapController`

Controller untuk halaman konfigurasi & monitoring integrasi SAP di admin:

```
Metode:
├── page()           → Halaman utama konfigurasi SAP
├── testConnection() → POST: coba koneksi ke SAP Cloud ALM API
├── saveConfig()     → POST: simpan credentials
├── syncLogs()       → GET: list log sinkronisasi (paginated)
├── complianceReport() → GET: laporan correction share, EWA, remote connectivity
└── proactiveCases() → GET: daftar proactive cases dari SAP
```

---

### 4.4 Perubahan UI

#### 4.4.1 Halaman Detail Tiket

Panel baru **"SAP Escalation"** di sidebar kanan tiket (hanya muncul jika `is_sap_forwardable = true`):

```
┌─────────────────────────────────┐
│ 🔗 SAP Escalation               │
├─────────────────────────────────┤
│ Status: Belum diteruskan         │
│ Customer SAP: [INST-NUMBER]     │
│ SAP Product: SAP S/4HANA        │
│                                 │
│ [Forward to SAP]  ← tombol      │
└─────────────────────────────────┘

Setelah forwarding:
┌─────────────────────────────────┐
│ 🔗 SAP Case: 8004567890         │
├─────────────────────────────────┤
│ Status SAP: In Process          │
│ Terakhir sync: 2026-04-30 10:30 │
│ [Buka di SAP for Me →]          │
│ [Sync Sekarang]                 │
└─────────────────────────────────┘
```

Modal **"Forward to SAP"** — form sebelum pengiriman:
- Pilih SAP Installation Number (dari data customer, atau input manual)
- Pilih SAP Product Component (dropdown dari master data SAP)
- Konfirmasi priority mapping
- Textarea untuk tambahan context ke SAP

#### 4.4.2 List Tiket — Badge SAP

Kolom baru atau badge di list tiket:
- `[SAP]` badge abu-abu = eligible tapi belum diteruskan
- `[SAP ✓]` badge biru = sudah diteruskan, sync aktif
- `[SAP ⚠]` badge oranye = ada error sinkronisasi

#### 4.4.3 Halaman Detail Customer — SAP Info Section

Tambah section di halaman customer:

```
SAP System Information
├── Installation Number : [input]
├── System ID (SID)     : [input]
├── Product Family      : [dropdown SAP products]
├── Support Level       : [dropdown]
└── EWA Status          : [toggle aktif/tidak]
```

#### 4.4.4 Control Center — Card & Halaman SAP Integration

Card baru di hub Control Center (`/admin`):
- Status koneksi SAP Cloud ALM (connected/error)
- Jumlah tiket pending sync
- Jumlah proactive cases belum di-acknowledge
- Compliance score bulan ini (correction share %)

Halaman baru `/admin/sap-integration`:

**Tab 1: Konfigurasi**
- Form credentials SAP Cloud ALM (tenant URL, client ID, client secret)
- Tombol test connection
- Status koneksi terakhir + timestamp

**Tab 2: Sync Monitor**
- Tabel log sinkronisasi: tiket, arah, aksi, HTTP status, timestamp, error
- Filter by: status (success/error), direction, tanggal
- Tombol retry untuk tiket yang gagal sync

**Tab 3: Proactive Cases**
- Daftar proactive cases dari SAP
- Status: New / Acknowledged / Linked to Ticket
- Tombol "Buat Tiket dari Proactive Case"

**Tab 4: Compliance Report**
- Correction Share per bulan (gauge chart, threshold 50%)
- Remote Connectivity per bulan (threshold 70%)
- EWA Coverage per bulan (threshold 70%)
- Export laporan compliance ke PDF/CSV

---

### 4.5 Perubahan Route

```php
// Web routes (admin panel)
Route::prefix('admin')->group(function () {
    Route::get('/sap-integration',              [AdminSapController::class, 'page']);
    Route::get('/sap-integration/compliance',   [AdminSapController::class, 'complianceReport']);
    Route::get('/sap-integration/proactive',    [AdminSapController::class, 'proactiveCases']);
});

// API routes (internal)
Route::prefix('api/admin/sap')->group(function () {
    Route::post('/test-connection',  [AdminSapController::class, 'testConnection']);
    Route::post('/config',           [AdminSapController::class, 'saveConfig']);
    Route::get('/sync-logs',         [AdminSapController::class, 'syncLogs']);
    Route::post('/retry/{ticketId}', [AdminSapController::class, 'retrySync']);
});

// API routes (ticket forwarding)
Route::prefix('api/tickets')->group(function () {
    Route::post('/{id}/forward-to-sap',  [TicketController::class, 'forwardToSap']);
    Route::post('/{id}/sync-from-sap',   [TicketController::class, 'syncFromSap']);
});

// Webhook dari SAP (public, verifikasi via HMAC)
Route::post('/api/sap/webhook', [SapWebhookController::class, 'handle']);
```

---

## 5. Dependency Teknis

### Library PHP yang Dibutuhkan
```
guzzlehttp/guzzle    → sudah ada di Laravel, untuk HTTP client ke SAP API
illuminate/queue     → sudah ada, untuk SapSyncJob
```

Tidak ada library tambahan yang perlu diinstall — semua bisa menggunakan yang sudah tersedia di Laravel 12.

### Environment Variables Baru
```env
SAP_CLOUD_ALM_TENANT_URL=
SAP_CLOUD_ALM_TOKEN_ENDPOINT=
SAP_CLOUD_ALM_CLIENT_ID=
SAP_CLOUD_ALM_CLIENT_SECRET=
SAP_CLOUD_ALM_API_BASE_URL=
SAP_WEBHOOK_SECRET=
SAP_SYNC_ENABLED=false   # toggle global, aktifkan setelah setup selesai
```

---

## 6. Risiko & Hal yang Perlu Dikonfirmasi

| Risiko | Keterangan | Mitigasi |
|---|---|---|
| **Field mapping tidak match** | Setiap SAP component punya struktur case yang berbeda | Perlu akses ke API documentation SAP Cloud ALM (API Hub) untuk validasi field |
| **Customer belum punya installation number** | Tidak bisa forward tiket tanpa data ini | Perlu audit & pengisian data customer sebelum go-live |
| **Webhook tidak tersedia** | Jika SAP tidak push ke EcoSystem-2, perlu polling | Implementasi fallback polling otomatis setiap 30 menit |
| **Token OAuth2 expire saat job berjalan** | Race condition pada concurrent requests | Implementasi token refresh dengan mutex/cache lock |
| **SAP API rate limit** | SAP mungkin ada limit request per menit | Implementasi exponential backoff + queue throttling |
| **Data sensitif credentials** | Client secret tidak boleh tersimpan plain text | Enkripsi menggunakan `encrypt()`/`decrypt()` Laravel |

---

## 7. Urutan Implementasi yang Disarankan

### Fase 1 — Pondasi (Estimasi: 2–3 minggu)
1. Subscribe SAP Cloud ALM Tenant Extension (non-teknis)
2. Buat `SapCloudAlmService` dengan OAuth2 + test connection
3. Tambah kolom SAP ke tabel `ticket` dan `customer`
4. Halaman admin konfigurasi + test connection UI
5. Buat migration untuk tabel baru (`sap_sync_logs`, dll.)

### Fase 2 — Case Forwarding (Estimasi: 2 minggu)
1. Implementasi `createCase()` dan `updateCase()` di service
2. Tombol "Forward to SAP" di halaman detail tiket
3. Modal form forwarding + validasi (installation number wajib ada)
4. Logging ke `sap_sync_logs`

### Fase 3 — Sync Balik dari SAP (Estimasi: 2 minggu)
1. Implementasi `SapWebhookController` + verifikasi HMAC
2. Implementasi `SapSyncJob` untuk polling fallback
3. Update UI tiket dengan status SAP real-time
4. Notifikasi internal jika ada update dari SAP

### Fase 4 — Compliance & Monitoring (Estimasi: 1–2 minggu)
1. Proactive cases inbox
2. Compliance metrics calculation & laporan
3. Control Center card SAP integration
4. Export compliance report

---

## 8. Pertanyaan yang Perlu Dijawab Sebelum Implementasi

1. **Apakah Eclectic sudah/akan subscribe SAP Cloud ALM Tenant Extension?** — Ini prerequisite utama
2. **Tenant URL SAP Cloud ALM sudah ada?** — Diperlukan untuk setup config awal
3. **Apakah ada SAP Solution Manager yang saat ini digunakan?** — Perlu dipastikan migrasi yang mulus, tidak langsung putus
4. **Data `sap_installation_number` customer sudah tersedia di mana?** — Perlu diisi sebelum bisa forwarding tiket
5. **Siapa yang memiliki akses ke SAP API documentation** (API Hub) untuk konfirmasi field mapping?
6. **Apakah SAP mendukung webhook push ke URL kita**, atau hanya polling? — Menentukan arsitektur sync

---

*Dokumen ini bersifat perencanaan. Implementasi dimulai setelah konfirmasi dari pihak manajemen dan tersedianya credentials SAP Cloud ALM.*
