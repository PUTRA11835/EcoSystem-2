# Delivery Planning - Entity Relationship Diagram (ERD)

## Overview

Dokumentasi struktur database untuk sistem **Delivery Planning** yang mendukung hierarki fleksibel untuk project management.

---

## Hierarki yang Didukung

```
PROJECT
    │
    └── DELIVERY PHASES (Template fase yang reusable)
            │
            └── DELIVERY GROUPS (Grup dalam fase)
                    │
                    ├── SUB-GROUPS (Nested groups - unlimited level)
                    │       │
                    │       └── ... (recursive)
                    │
                    ├── DELIVERY STAGES (Tahapan dalam grup) [OPTIONAL]
                    │       │
                    │       └── DELIVERY ACTIVITIES (via stage_id)
                    │
                    └── DELIVERY ACTIVITIES (langsung via group_id) ← NEW!
```

### Catatan Penting:
- **Activity bisa langsung di bawah Group** tanpa harus melalui Stage
- **Sub-groups unlimited level** dengan self-reference `parent_id`
- **Phases adalah template** yang bisa dipakai ulang di berbagai project

---

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                   │
│                              ╔═══════════════════╗                                │
│                              ║     PROJECTS      ║                                │
│                              ╠═══════════════════╣                                │
│                              ║ PK id             ║                                │
│                              ║    name           ║                                │
│                              ║    client_id      ║                                │
│                              ║    status         ║                                │
│                              ║    ...            ║                                │
│                              ╚═════════╤═════════╝                                │
│                                        │                                          │
│                    ┌───────────────────┼───────────────────┐                      │
│                    │                   │                   │                      │
│                    ▼                   │                   ▼                      │
│    ╔═══════════════════════╗          │      ╔═══════════════════════════╗       │
│    ║   DELIVERY_PHASES     ║          │      ║    PROJECT_EMPLOYEES      ║       │
│    ║  (Template/Master)    ║          │      ╠═══════════════════════════╣       │
│    ╠═══════════════════════╣          │      ║ FK project_id             ║       │
│    ║ PK id                 ║          │      ║ FK employee_id            ║       │
│    ║    name               ║          │      ║    role                   ║       │
│    ║    code               ║          │      ║    ...                    ║       │
│    ║    color              ║          │      ╚═══════════════════════════╝       │
│    ║    order_sequence     ║          │                                          │
│    ║    is_system_default  ║          │                                          │
│    ║ FK parent_phase_id    ║──┐       │                                          │
│    ╚═════════╤═════════════╝  │       │                                          │
│              │                │       │                                          │
│              │ (self-ref)     │       │                                          │
│              └────────────────┘       │                                          │
│              │                        │                                          │
│              │    M : M               │                                          │
│              │                        │                                          │
│              ▼                        ▼                                          │
│    ╔═══════════════════════════════════════╗                                     │
│    ║     PROJECT_DELIVERY_PHASES           ║                                     │
│    ║  (Junction: Project ←→ Phase)         ║                                     │
│    ╠═══════════════════════════════════════╣                                     │
│    ║ PK id                                 ║                                     │
│    ║ FK project_id ────────────────────────╫──→ projects.id                      │
│    ║ FK delivery_phase_id ─────────────────╫──→ delivery_phases.id               │
│    ║    weight                             ║                                     │
│    ║    order_sequence                     ║                                     │
│    ║    is_visible                         ║                                     │
│    ║    is_golive_phase                    ║                                     │
│    ║    calculated_progress (cached)       ║                                     │
│    ╚══════════════════╤════════════════════╝                                     │
│                       │                                                          │
│                       │ 1 : N                                                    │
│                       │                                                          │
│                       ▼                                                          │
│    ╔═════════════════════════════════════════════════════════════════╗           │
│    ║                    DELIVERY_GROUPS                               ║           │
│    ║  (Grup & Sub-Grup dalam Fase - Self-referencing)                 ║           │
│    ╠═════════════════════════════════════════════════════════════════╣           │
│    ║ PK id                                                           ║           │
│    ║ FK project_id ──────────────────────────────────────────────────╫─→ proj   │
│    ║ FK phase_id ────────────────────────────────────────────────────╫─→ phase  │
│    ║ FK project_phase_id ────────────────────────────────────────────╫─→ prj_ph │
│    ║ FK parent_id ───────────────────────────────────────────────────╫──┐       │
│    ║    name                                                         ║  │       │
│    ║    code                                                         ║  │       │
│    ║    level (0=root, 1=child, 2=grandchild, ...)                   ║  │ self  │
│    ║    path (materialized: "1/5/12/" untuk query cepat)             ║  │ ref   │
│    ║    order_sequence                                               ║  │       │
│    ║    start_date, end_date                                         ║  │       │
│    ║    actual_start_date, actual_end_date                           ║  │       │
│    ║    weight, status, progress_percentage                          ║  │       │
│    ╚═══════════════════╤═══════════════════════════════╤═════════════╝  │       │
│                        │                               │                 │       │
│                        │ (unlimited nesting)           └─────────────────┘       │
│                        │                                                         │
│         ┌──────────────┴──────────────┐                                          │
│         │                             │                                          │
│         │ 1 : N                       │ 1 : N                                    │
│         ▼                             ▼                                          │
│  ╔════════════════════════╗    ╔═══════════════════════════════════════════╗    │
│  ║   DELIVERY_STAGES      ║    ║        DELIVERY_ACTIVITIES                 ║    │
│  ║  (OPTIONAL)            ║    ║  (Direct under Group - parent_type='group')║    │
│  ╠════════════════════════╣    ╠═══════════════════════════════════════════╣    │
│  ║ PK id                  ║    ║ PK id                                      ║    │
│  ║ FK group_id ───────────╫──┐ ║ FK project_id                              ║    │
│  ║ FK project_id          ║  │ ║ FK phase_id                                ║    │
│  ║    name                ║  │ ║                                            ║    │
│  ║    order_sequence      ║  │ ║ parent_type = 'group' ◄─── KEY FEATURE!   ║    │
│  ║    planned_start_date  ║  │ ║ FK group_id ──────────────────────────────╫─┐  │
│  ║    planned_end_date    ║  │ ║ FK stage_id = NULL                        ║ │  │
│  ║    weight, progress    ║  │ ║                                            ║ │  │
│  ╚══════════╤═════════════╝  │ ║    name, code, description                 ║ │  │
│             │                │ ║    module, tcode, complexity               ║ │  │
│             │ 1 : N          │ ║    start_date, end_date                    ║ │  │
│             │                │ ║    weight, status, progress_percentage     ║ │  │
│             ▼                │ ╚═══════════════════════════════════════════╝ │  │
│  ╔════════════════════════╗  │                                               │  │
│  ║  DELIVERY_ACTIVITIES   ║  └───────────────────────────────────────────────┘  │
│  ║ (Under Stage)          ║                                                     │
│  ╠════════════════════════╣                                                     │
│  ║ PK id                  ║                                                     │
│  ║ FK project_id          ║                                                     │
│  ║ FK phase_id            ║                                                     │
│  ║                        ║                                                     │
│  ║ parent_type = 'stage'  ║                                                     │
│  ║ FK stage_id ───────────╫──→ delivery_stages.id                               │
│  ║ FK group_id = NULL     ║                                                     │
│  ║                        ║                                                     │
│  ║    name, description   ║                                                     │
│  ║    module, tcode       ║                                                     │
│  ║    complexity          ║                                                     │
│  ║    weight, status      ║                                                     │
│  ║    progress_percentage ║                                                     │
│  ╚════════════╤═══════════╝                                                     │
│               │                                                                  │
│               │ M : M                                                           │
│               │                                                                  │
│               ▼                                                                  │
│  ╔═══════════════════════════════════════════╗      ╔══════════════════════╗    │
│  ║    DELIVERY_ACTIVITY_EMPLOYEES            ║      ║      EMPLOYEE        ║    │
│  ╠═══════════════════════════════════════════╣      ╠══════════════════════╣    │
│  ║ PK id                                     ║      ║ PK employee_id       ║    │
│  ║ FK activity_id ───────────────────────────╫──┐   ║    eci               ║    │
│  ║ FK employee_id ───────────────────────────╫──┼──→║    ...               ║    │
│  ║    role (lead/member/reviewer/support)    ║  │   ╚══════════════════════╝    │
│  ║    allocation_percentage                  ║  │                               │
│  ║    assigned_date                          ║  │                               │
│  ║    is_active                              ║  │                               │
│  ╚═══════════════════════════════════════════╝  │                               │
│                                                  │                               │
│               ┌──────────────────────────────────┘                               │
│               │                                                                  │
│               │ 1 : N                                                           │
│               ▼                                                                  │
│  ╔═══════════════════════════════════════════╗                                  │
│  ║            TIMESHEETS                     ║                                  │
│  ╠═══════════════════════════════════════════╣                                  │
│  ║ PK id                                     ║                                  │
│  ║ FK employee_id                            ║                                  │
│  ║ FK project_id                             ║                                  │
│  ║ FK activity_id ───────────────────────────╫──→ delivery_activities.id        │
│  ║    date, start_time, end_time             ║                                  │
│  ║    description                            ║                                  │
│  ║    status                                 ║                                  │
│  ╚═══════════════════════════════════════════╝                                  │
│                                                                                  │
└──────────────────────────────────────────────────────────────────────────────────┘
```

---

## Tabel Detail

### 1. `delivery_phases` - Template Fase

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Primary key |
| name | VARCHAR(255) | Nama fase (Planning, Development, UAT, dll) |
| code | VARCHAR(20) | Kode singkat (PLAN, DEV, UAT) |
| description | TEXT | Deskripsi fase |
| color | VARCHAR(7) | Warna hex (#3B82F6) |
| icon | VARCHAR(50) | Icon class/name |
| order_sequence | INT | Urutan tampilan |
| orientation | ENUM | vertical / horizontal |
| is_system_default | BOOL | Template bawaan sistem |
| is_optional | BOOL | Fase opsional |
| is_active | BOOL | Status aktif |
| parent_phase_id | BIGINT FK | Self-ref untuk nested phases |
| settings | JSON | Custom settings |
| metadata | JSON | Additional metadata |

**Indexes:**
- `is_active`
- `is_system_default`
- `(is_active, order_sequence)`

---

### 2. `project_delivery_phases` - Junction Table

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Primary key |
| project_id | BIGINT FK | → projects.id |
| delivery_phase_id | BIGINT FK | → delivery_phases.id |
| weight | DECIMAL(5,2) | Bobot fase dalam project |
| order_sequence | INT | Urutan tampilan |
| is_visible | BOOL | Tampil/hidden |
| is_golive_phase | BOOL | Tandai fase go-live |
| orientation | ENUM | vertical / horizontal |
| custom_settings | JSON | Override settings |
| calculated_progress | DECIMAL(5,2) | Progress terkalkulasi (cached) |
| calculated_start_date | DATE | Tanggal mulai terkalkulasi |
| calculated_end_date | DATE | Tanggal selesai terkalkulasi |

**Indexes:**
- `UNIQUE(project_id, delivery_phase_id)`
- `(project_id, is_visible, order_sequence)`

---

### 3. `delivery_groups` - Grup & Sub-Grup

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Primary key |
| project_id | BIGINT FK | → projects.id |
| phase_id | BIGINT FK | → delivery_phases.id |
| project_phase_id | BIGINT FK | → project_delivery_phases.id |
| **parent_id** | BIGINT FK | → delivery_groups.id (SELF-REF) |
| name | VARCHAR(255) | Nama grup |
| code | VARCHAR(30) | Kode singkat |
| description | TEXT | Deskripsi |
| **level** | INT | Kedalaman: 0=root, 1=child, ... |
| order_sequence | INT | Urutan tampilan |
| **path** | VARCHAR(500) | Materialized path: "1/5/12/" |
| start_date | DATE | Tanggal mulai planned |
| end_date | DATE | Tanggal selesai planned |
| actual_start_date | DATE | Tanggal mulai actual |
| actual_end_date | DATE | Tanggal selesai actual |
| weight | DECIMAL(5,2) | Bobot |
| status | ENUM | not_started/in_progress/completed/delayed/on_hold |
| progress_percentage | DECIMAL(5,2) | Progress |
| color | VARCHAR(7) | Warna |
| notes | TEXT | Catatan |

**Indexes:**
- `(project_id, phase_id, parent_id)` - Query hierarki
- `(project_id, level, order_sequence)` - Query per level
- `(project_id, status)` - Filter status
- `path` - Materialized path query

---

### 4. `delivery_stages` - Tahapan (OPTIONAL)

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Primary key |
| **group_id** | BIGINT FK | → delivery_groups.id |
| project_id | BIGINT FK | → projects.id |
| name | VARCHAR(255) | Nama stage |
| code | VARCHAR(30) | Kode singkat |
| description | TEXT | Deskripsi |
| order_sequence | INT | Urutan |
| color | VARCHAR(7) | Warna |
| planned_start_date | DATE | Planned start |
| planned_end_date | DATE | Planned end |
| actual_start_date | DATE | Actual start |
| actual_end_date | DATE | Actual end |
| weight | DECIMAL(5,2) | Bobot |
| status | ENUM | Status |
| progress | DECIMAL(5,2) | Progress |

**Indexes:**
- `(group_id, order_sequence)`
- `(project_id, status)`

---

### 5. `delivery_activities` - Aktivitas (FLEXIBLE PARENT)

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Primary key |
| project_id | BIGINT FK | → projects.id |
| phase_id | BIGINT FK | → delivery_phases.id |
| **parent_type** | ENUM | **'stage' atau 'group'** ← KEY! |
| **stage_id** | BIGINT FK | → delivery_stages.id (jika parent_type='stage') |
| **group_id** | BIGINT FK | → delivery_groups.id (jika parent_type='group') |
| name | VARCHAR(255) | Nama aktivitas |
| code | VARCHAR(50) | Kode |
| description | TEXT | Deskripsi |
| order_sequence | INT | Urutan |
| module | VARCHAR(100) | SAP Module (FI, CO, MM, dll) |
| tcode | VARCHAR(50) | Transaction code |
| complexity | ENUM | low/medium/high/very_high |
| receive_type | VARCHAR(50) | Tipe penerimaan |
| new_requirement | BOOL | Requirement baru |
| functional_sinergi | TEXT | Sinergi fungsional |
| technical_sinergi | TEXT | Sinergi teknis |
| deliverable | TEXT | Deliverable |
| start_date | DATE | Planned start |
| end_date | DATE | Planned end |
| actual_start_date | DATE | Actual start |
| actual_end_date | DATE | Actual end |
| weight | DECIMAL(5,2) | Bobot |
| status | ENUM | Status |
| progress_percentage | DECIMAL(5,2) | Progress |
| notes | TEXT | Catatan |
| acceptance_criteria | TEXT | Kriteria penerimaan |

**Indexes:**
- `(project_id, phase_id)`
- `(stage_id, order_sequence)` - Query via stage
- `(group_id, order_sequence)` - Query langsung via group
- `(project_id, status)`
- `(project_id, parent_type, status)`
- `(parent_type, stage_id, group_id)` - Query berdasarkan parent

---

### 6. `delivery_activity_employees` - Assignment

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Primary key |
| activity_id | BIGINT FK | → delivery_activities.id |
| employee_id | BIGINT FK | → employee.employee_id |
| role | ENUM | lead/member/reviewer/support/observer |
| allocation_percentage | DECIMAL(5,2) | % alokasi waktu |
| assigned_date | DATE | Tanggal assign |
| start_date | DATE | Tanggal mulai |
| end_date | DATE | Tanggal selesai |
| is_active | BOOL | Status aktif |
| notes | TEXT | Catatan |

**Indexes:**
- `UNIQUE(activity_id, employee_id)`
- `(employee_id, is_active)`

---

## Query Patterns

### 1. Get semua Groups dalam Phase (termasuk sub-groups)

```sql
SELECT * FROM delivery_groups
WHERE project_id = ?
  AND phase_id = ?
ORDER BY level, order_sequence;
```

### 2. Get Activities di bawah Group (termasuk via Stage DAN langsung)

```sql
-- Activities langsung di Group
SELECT * FROM delivery_activities
WHERE parent_type = 'group' AND group_id = ?
ORDER BY order_sequence;

-- Activities via Stages
SELECT da.* FROM delivery_activities da
JOIN delivery_stages ds ON da.stage_id = ds.id
WHERE da.parent_type = 'stage' AND ds.group_id = ?
ORDER BY ds.order_sequence, da.order_sequence;

-- Combined
SELECT da.*, 'direct' as source FROM delivery_activities da
WHERE da.parent_type = 'group' AND da.group_id = ?
UNION ALL
SELECT da.*, 'via_stage' as source FROM delivery_activities da
JOIN delivery_stages ds ON da.stage_id = ds.id
WHERE da.parent_type = 'stage' AND ds.group_id = ?;
```

### 3. Get Activities yang di-assign ke Employee

```sql
SELECT da.* FROM delivery_activities da
JOIN delivery_activity_employees dae ON da.id = dae.activity_id
WHERE dae.employee_id = ?
  AND dae.is_active = true
  AND da.project_id = ?;
```

### 4. Get Sub-groups menggunakan Materialized Path

```sql
-- Semua descendants dari group dengan ID 5
SELECT * FROM delivery_groups
WHERE path LIKE '5/%'
ORDER BY level, order_sequence;

-- Semua ancestors (parent chain)
SELECT * FROM delivery_groups
WHERE '1/5/12/' LIKE CONCAT(path, '%')
ORDER BY level;
```

---

## Progress Calculation Flow

```
BOTTOM-UP PROPAGATION:
┌────────────────────────────────────────────────────────────────────┐
│                                                                    │
│   Activity (manual update progress)                                │
│       │                                                            │
│       ▼                                                            │
│   Stage (auto-calculate dari activities)                           │
│       │                                                            │
│       ▼                                                            │
│   Group (auto-calculate dari stages + direct activities + sub)     │
│       │                                                            │
│       ▼                                                            │
│   Project Phase (auto-calculate dari groups - cached)              │
│       │                                                            │
│       ▼                                                            │
│   Project (overall progress)                                       │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘

FORMULA:
weighted_progress = SUM(item.progress × item.weight) / SUM(item.weight)
```

---

## File Locations

| File | Path |
|------|------|
| Migration | `database/migrations/2026_02_04_000001_create_delivery_planning_tables.php` |
| DeliveryPhase Model | `app/Models/DeliveryPhase.php` |
| ProjectDeliveryPhase Model | `app/Models/ProjectDeliveryPhase.php` |
| DeliveryGroup Model | `app/Models/DeliveryGroup.php` |
| DeliveryStage Model | `app/Models/DeliveryStage.php` |
| DeliveryActivity Model | `app/Models/DeliveryActivity.php` |

---

## Migration Commands

```bash
# Create tables
php artisan migrate

# Rollback
php artisan migrate:rollback

# Fresh migration (WARNING: drops all tables)
php artisan migrate:fresh

# Check migration status
php artisan migrate:status
```

---

## Versi

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-02-04 | Initial design dengan flexible parent (stage/group) |

---

*Dokumentasi ini dibuat oleh Claude AI Assistant*
