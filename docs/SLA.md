# SLA Feature — Complete Technical Documentation

> **Audience**: Claude Code, developers, maintainers  
> **Project**: ECoSystem (Laravel, c:\EcoSystem-2)  
> **Last updated**: 2026-05-28  
> **Status**: Production

---

## Table of Contents

1. [Overview](#1-overview)
2. [Database Schema & Migrations](#2-database-schema--migrations)
3. [Eloquent Models](#3-eloquent-models)
4. [SlaService — Core Engine](#4-slaservice--core-engine)
5. [SlaController — HTTP Layer](#5-slacontroller--http-layer)
6. [Routes](#6-routes)
7. [Views & Frontend UI](#7-views--frontend-ui)
8. [Artisan Commands](#8-artisan-commands)
9. [Seeder](#9-seeder)
10. [Integration Points (Ticket lifecycle)](#10-integration-points)
11. [State Machine](#11-state-machine)
12. [Business Rules Reference](#12-business-rules-reference)
13. [How to Extend or Modify](#13-how-to-extend-or-modify)

---

## 1. Overview

The SLA (Service Level Agreement) feature tracks **response** and **resolution** time commitments on every support ticket. It answers two questions:

| Question | Metric |
|---|---|
| How quickly did helpdesk acknowledge the request? | `response_status` + `validation_duration_hours` |
| How quickly did helpdesk fully resolve it (net of waiting)? | `resolution_status` + `net_resolution_hours` |

### Core Concepts

**Ball Holder** — At any point in time, one of three parties holds the "ball" (responsibility). SLA clock only runs when helpdesk holds the ball.

| Ball Holder | Meaning |
|---|---|
| `helpdesk` | SLA clock running — helpdesk must act |
| `customer` | Clock paused — waiting for customer response or confirmation |
| `sap` | Clock paused — issue escalated to SAP, waiting for their reply |

**Waiting Time** — All time the ball was NOT with helpdesk is accumulated in `total_waiting_hours`. It is subtracted from gross elapsed time to produce `net_resolution_hours`.

**SLA Mode** — Determines which deadlines are tracked:

| Mode | Ticket Types | Tracks |
|---|---|---|
| `full` | Incident, Service Request | Response deadline + Resolution deadline |
| `response_only` | Change Request, Consultation, others | Response deadline only |

**Time Calculation** — Controlled per-policy by `is_24_hours`:

| Flag | Calculation |
|---|---|
| `is_24_hours = true` | All calendar hours (7 days × 24 h) |
| `is_24_hours = false` | Business hours only: Mon–Fri 09:00–18:00 |

---

## 2. Database Schema & Migrations

### 2.1 Migration Files (in execution order)

| # | File | Creates |
|---|---|---|
| 1 | `2026_05_07_000001_create_sla_policies_table.php` | `sla_policies` |
| 2 | `2026_05_07_000002_create_ticket_sla_table.php` | `ticket_sla` |
| 3 | `2026_05_07_000003_create_ticket_sla_pauses_table.php` | `ticket_sla_pauses` |
| 4 | `2026_05_07_000004_create_ticket_sla_events_table.php` | `ticket_sla_events` |
| 5 | `2026_05_07_153134_add_sla_mode_to_ticket_sla_table.php` | Adds `sla_mode` to `ticket_sla` |
| 6 | `2026_05_07_155425_add_agent_replied_to_ticket_sla_events_enum.php` | Extends `event_type` ENUM |
| 7 | `2026_05_08_161017_add_session_start_to_ticket_sla_table.php` | Adds `session_start_at` |
| 8 | `2026_05_15_000001_add_solution_started_at_to_ticket_sla.php` | Adds `solution_started_at` |
| 9 | `2026_05_19_140052_add_ball_holder_to_ticket_sla_table.php` | Adds `ball_holder`, `sla_paused_at` |
| 10 | `2026_05_19_140136_add_missing_columns_to_sla_tables.php` | Various column additions across tables |

---

### 2.2 Table: `sla_policies`

**Purpose**: Defines SLA time targets per customer, priority, and ticket complexity.

```
sla_policies
├── id                  BIGINT UNSIGNED PK AUTO_INCREMENT
├── customer_id         BIGINT UNSIGNED NULL  → customer.customer_id (nullOnDelete)
│                       NULL = global default (applies to any customer without a specific policy)
├── priority            ENUM('Low','Medium','High','Very High')
├── scale               ENUM('Simple','Medium','Complex')
├── response_hours      DECIMAL(6,2)    — target first-response time in hours
├── resolution_hours    DECIMAL(6,2)    — target resolution time in hours
├── is_24_hours         TINYINT(1) DEFAULT 0
│                       0 = business hours (Mon-Fri 09:00-18:00)
│                       1 = 24/7 calendar hours
├── is_active           TINYINT(1) DEFAULT 1
├── created_by          BIGINT UNSIGNED NULL  → employee.employee_id (nullOnDelete)
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

UNIQUE KEY  sla_policies_unique        (customer_id, priority, scale)
INDEX       sla_policies_lookup_idx    (customer_id, priority, scale, is_active)
```

**Policy Lookup Rule**: Customer-specific policy wins over global. If a ticket belongs to Customer #5, the system first looks for `customer_id = 5`; if not found, falls back to `customer_id IS NULL`.

---

### 2.3 Table: `ticket_sla`

**Purpose**: Tracks the full SLA lifecycle for each ticket. One row per ticket.

```
ticket_sla
├── id                          BIGINT UNSIGNED PK AUTO_INCREMENT
├── staging_ticket_id           BIGINT UNSIGNED NULL UNIQUE → staging_tickets.id (nullOnDelete)
│                               Filled from email arrival; NULL if ticket was created directly
├── ticket_id                   BIGINT UNSIGNED NULL UNIQUE → ticket.ticket_id (nullOnDelete)
│                               Filled after helpdesk validates the ticket
├── sla_policy_id               BIGINT UNSIGNED NULL → sla_policies.id (nullOnDelete)
│                               NULL if no matching policy was found
│
│   ── TIMING ─────────────────────────────────────────────────────────────────
├── sla_mode                    ENUM('full','response_only') DEFAULT 'full'
├── sla_start_at                TIMESTAMP  — T=0, NEVER CHANGES after creation
│                               Source: staging_tickets.created_at (email arrival)
│                               Fallback: ticket.created_at (direct ticket creation)
├── response_due_at             TIMESTAMP NULL  = sla_start_at + policy.response_hours
├── resolution_due_at           TIMESTAMP NULL  = sla_start_at + policy.resolution_hours
│                               NULL when sla_mode = 'response_only'
│
│   ── RESPONSE RESULT ────────────────────────────────────────────────────────
├── first_responded_at          TIMESTAMP NULL  — when ticket was validated/created by helpdesk
├── validation_duration_hours   DECIMAL(8,2) NULL  — actual hours from sla_start_at to first_responded_at
├── response_status             ENUM('pending','met','breached') DEFAULT 'pending'
│
│   ── RESOLUTION TRACKING ───────────────────────────────────────────────────
├── session_start_at            TIMESTAMP NULL  — start of current helpdesk working session
│                               Resets every time ball returns to helpdesk
├── solution_started_at         TIMESTAMP NULL  — when solution phase began (set by controller)
├── resolved_at                 TIMESTAMP NULL  — when ticket was closed
├── total_waiting_hours         DECIMAL(8,2) DEFAULT 0  — accumulated non-helpdesk time
├── net_resolution_hours        DECIMAL(8,2) NULL  — gross - total_waiting_hours (set on close)
├── resolution_status           ENUM('pending_validation','pending','paused','met','breached')
│                               DEFAULT 'pending_validation'
│
│   ── LIVE STATE ─────────────────────────────────────────────────────────────
├── ball_holder                 ENUM('helpdesk','customer','sap') DEFAULT 'helpdesk'
├── sla_paused_at               TIMESTAMP NULL  — when current waiting period started
│                               Used for live waiting calculation: now() - sla_paused_at
├── created_at                  TIMESTAMP
└── updated_at                  TIMESTAMP

INDEX  ticket_sla_active_idx    (resolution_status, resolved_at)
INDEX  ticket_sla_response_idx  (response_status, first_responded_at)
INDEX  ticket_sla_policy_idx    (sla_policy_id)
```

**resolution_status Lifecycle**:
```
pending_validation  →  pending  →  paused  ↔  pending  →  met
                                                          →  breached
```

---

### 2.4 Table: `ticket_sla_pauses`

**Purpose**: Immutable audit log of every pause/resume cycle. Never updated — only inserted (started) and "closed" (ended_at filled on resume).

```
ticket_sla_pauses
├── id                      BIGINT UNSIGNED PK AUTO_INCREMENT
├── ticket_id               BIGINT UNSIGNED → ticket.ticket_id (cascadeOnDelete)
├── pause_reason            ENUM('waiting_customer','sent_to_sap','sent_to_support','on_hold')
├── triggered_by_status     VARCHAR(100) NULL  — the exact jarvis_status that triggered this pause
│                           Stored for audit trail
├── started_at              TIMESTAMP  — when pause began
├── ended_at                TIMESTAMP NULL  — NULL = pause still active
├── duration_hours          DECIMAL(8,2) NULL  — calculated on resume (business or 24h hours)
├── started_by_message_id   INT UNSIGNED NULL → ticket_message.id (nullOnDelete)
├── ended_by_message_id     INT UNSIGNED NULL → ticket_message.id (nullOnDelete)
├── resumed_by              BIGINT UNSIGNED NULL → employee.employee_id (nullOnDelete)
│                           Non-null if agent manually resumed (e.g., phone confirmation)
└── created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP  (NO updated_at)

INDEX  sla_pauses_active_idx  (ticket_id, ended_at)
```

---

### 2.5 Table: `ticket_sla_events`

**Purpose**: Chronological, immutable event log for compliance documentation and the SLA timeline UI.

```
ticket_sla_events
├── id                  BIGINT UNSIGNED PK AUTO_INCREMENT
├── ticket_id           BIGINT UNSIGNED NULL → ticket.ticket_id (nullOnDelete)
│                       NULL on 'email_received' (ticket does not exist yet)
├── staging_ticket_id   BIGINT UNSIGNED NULL → staging_tickets.id (nullOnDelete)
├── message_id          INT UNSIGNED NULL → ticket_message.id (nullOnDelete)
│                       The message that triggered this event
├── event_type          ENUM (see values below)
├── jarvis_status       VARCHAR(50) NULL  — the jarvies_status at time of agent reply
├── event_at            TIMESTAMP  — when this event occurred (message.created_at, not now())
│
│   ── CALCULATION COLUMNS (only filled on specific event types) ────────────
├── waiting_hours       DECIMAL(6,2) NULL  — filled on: customer_replied
│                       = hours from pause start to this customer reply
├── response_hours      DECIMAL(6,2) NULL  — filled on: ticket_validated
│                       = hours from sla_start_at to validation
├── resolution_hours    DECIMAL(6,2) NULL  — filled on: agent_replied, ticket_closed
│                       = net helpdesk work hours up to this point
│
├── notes               TEXT NULL  — auto-generated human description
├── triggered_by        BIGINT UNSIGNED NULL  — employee_id or customer_id
├── triggered_by_type   ENUM('employee','customer','system') NULL
└── created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP  (NO updated_at)

INDEX  sla_events_ticket_idx  (ticket_id, event_at)
INDEX  sla_events_type_idx    (event_type, ticket_id)
```

**event_type values**:

| Value | When | Key columns filled |
|---|---|---|
| `email_received` | T=0, staging ticket created | — |
| `ticket_validated` | Helpdesk approves & creates ticket | `response_hours` |
| `agent_replied` | Helpdesk sends any message | `resolution_hours`, `jarvis_status` |
| `customer_replied` | Customer sends first reply after a pause | `waiting_hours` |
| `resolution_sent` | *(legacy)* proposed solution set | `resolution_hours` |
| `escalated_to_sap` | *(legacy)* sent to SAP | — |
| `escalated_to_support` | *(legacy)* sent to support | — |
| `sla_warning` | Scheduler: ≤20% time remaining | — |
| `sla_breached` | Scheduler: deadline passed | — |
| `ticket_closed` | Ticket closed | `resolution_hours` (final net) |

---

## 3. Eloquent Models

### 3.1 `App\Models\SlaPolicy`
**File**: `app/Models/SlaPolicy.php`  
**Table**: `sla_policies`

```php
// Relationships
customer()      → BelongsTo(Customer, customer_id)
createdBy()     → BelongsTo(Employee, created_by)
ticketSlas()    → HasMany(TicketSla, sla_policy_id)

// Static helper — use this for policy lookup
SlaPolicy::findFor(int $customerId, string $priority, string $scale): ?SlaPolicy
// Returns customer-specific first, then global fallback
```

**Casts**: `response_hours` → `decimal:2`, `resolution_hours` → `decimal:2`, `is_24_hours` → `boolean`, `is_active` → `boolean`

---

### 3.2 `App\Models\TicketSla`
**File**: `app/Models/TicketSla.php`  
**Table**: `ticket_sla`

```php
// Relationships
ticket()         → BelongsTo(Ticket, ticket_id)
stagingTicket()  → BelongsTo(StagingTicket, staging_ticket_id)
policy()         → BelongsTo(SlaPolicy, sla_policy_id)
pauses()         → HasMany(TicketSlaPause, ticket_id)
events()         → HasMany(TicketSlaEvent, ticket_id) [ordered by event_at]

// Instance helpers
activePause(): ?TicketSlaPause   — returns pause where ended_at IS NULL
isCurrentlyPaused(): bool        — resolution_status === 'paused'
isClosed(): bool                 — resolution_status in ['met', 'breached']
```

**All timestamp columns are cast to `datetime`**. Duration columns cast to `decimal:2`.

---

### 3.3 `App\Models\TicketSlaEvent`
**File**: `app/Models/TicketSlaEvent.php`  
**Table**: `ticket_sla_events`  
**Note**: `$timestamps = false` (only `created_at` exists, no `updated_at`)

```php
// Relationships
ticket()   → BelongsTo(Ticket, ticket_id)
message()  → BelongsTo(TicketMessage, message_id)

// Accessor
getEventLabelAttribute(): string  — Indonesian human-readable label
```

---

### 3.4 `App\Models\TicketSlaPause`
**File**: `app/Models/TicketSlaPause.php`  
**Table**: `ticket_sla_pauses`  
**Note**: `$timestamps = false` (only `created_at` exists)

```php
// Relationships
ticket()               → BelongsTo(Ticket, ticket_id)
startedByMessage()     → BelongsTo(TicketMessage, started_by_message_id)
endedByMessage()       → BelongsTo(TicketMessage, ended_by_message_id)
resumedBy()            → BelongsTo(Employee, resumed_by)

// Instance helper
isActive(): bool  — ended_at IS NULL
```

---

## 4. SlaService — Core Engine

**File**: `app/Services/SlaService.php`

This is the heart of the SLA feature. It is the only class that writes to `ticket_sla`, `ticket_sla_events`, and orchestrates ball-holder state transitions.

### 4.1 Constants

```php
// These ticket types get FULL SLA (response + resolution tracked)
FULL_SLA_TYPES = ['Incident', 'Service Request']

// jarvis_status → SLA effect mapping
STOP_STATUSES = ['author action', 'proposed solution', 'sent in to SAP', 'wait to close', 'in process']
// Effect: ball moves to customer/SAP, waiting clock starts

RUN_STATUSES = ['sent it to support']
// Effect: ball stays with / returns to helpdesk

END_STATUSES = ['closed']
// Effect: SLA finalized

// jarvis_status → ball_holder value (only for STOP_STATUSES)
BALL_HOLDER_MAP = [
    'author action'     => 'customer',
    'proposed solution' => 'customer',
    'wait to close'     => 'customer',
    'sent in to SAP'    => 'sap',
    'in process'        => 'customer',
]
```

---

### 4.2 `calcHours(Carbon $from, Carbon $to, bool $is24h): float`

Calculates elapsed hours between two timestamps.

```
is24h = true  → abs(to - from) in hours (simple calendar time)
is24h = false → only counts Mon-Fri 09:00-18:00 segments
                iterates day by day, clips each day's contribution
Returns: float, rounded to 2 decimal places
Edge:    if from >= to, returns 0.0
```

**Business hours per day = 9 hours** (09:00 to 18:00). A working week = 45 hours.

---

### 4.3 `attachToTicket(Ticket $ticket, ?StagingTicket $staging = null): void`

Called once per ticket when it is validated from staging. Idempotent — exits immediately if `ticket.sla()` already exists.

**Algorithm**:
```
1. Guard: ticket_type must be set
2. Guard: ticket.sla() must not already exist
3. Determine scale (default: 'Simple'), priority, customer_id
4. Look up SlaPolicy: customer-specific first, then global fallback
5. If no policy found → log + return (no SLA created)
6. Determine sla_mode: 'full' if ticket_type in FULL_SLA_TYPES, else 'response_only'
7. sla_start_at = staging.created_at if staging exists, else ticket.created_at
8. Clamp: if sla_start_at > ticket.created_at, set sla_start_at = ticket.created_at
9. Calculate validation_duration_hours (sla_start_at → ticket.created_at)
10. Set response_status = 'met' if within response_hours, else 'breached'
11. Create ticket_sla record (resolution_status = 'pending')
12. Insert 'email_received' event at sla_start_at
13. Insert 'ticket_validated' event at ticket.created_at with response_hours
```

---

### 4.4 `recordMessageEvent(Ticket, TicketMessage, string $senderType, ?string $jarviesStatus): void`

Called every time a non-internal message is created. This is the main SLA state machine trigger.

```
Guards:
- Skip if ticket has no TicketSla record
- Skip if message.is_internal_note = true
- Skip if sla.isClosed() (met or breached)

Routes:
- senderType = 'customer' → handleCustomerBurst()
- senderType = anything else → handleEmployeeBurst()
  (default jarviesStatus = 'sent it to support' if null passed)
```

---

### 4.5 `handleCustomerBurst` (private)

Called when a customer sends a message.

```
IF ball_holder IN ['customer', 'sap'] AND sla_paused_at IS SET:
    // First customer message in this burst — end the waiting period
    waitingHours = calcHours(sla_paused_at, now)
    ticket_sla.total_waiting_hours += waitingHours
    ticket_sla.ball_holder = 'helpdesk'
    ticket_sla.sla_paused_at = NULL
    ticket_sla.session_start_at = now  // new helpdesk session starts

ELSE:
    // Ball already with helpdesk — customer is continuing a burst
    // No state change (SLA already running)

INSERT event: customer_replied, waiting_hours = computed or null
```

---

### 4.6 `handleEmployeeBurst` (private)

Called when a helpdesk agent sends a message. Calculates `resolutionHours` from `session_start_at → now` then routes:

| jarvis_status | Route |
|---|---|
| in STOP_STATUSES | `handleStop()` |
| in RUN_STATUSES | `handleRun()` |
| in END_STATUSES | `closeTicketSla()` |

---

### 4.7 `handleStop` (private)

Handles jarvis_status that passes the ball to customer or SAP.

```
IF ball_holder = 'helpdesk':
    // First stop in this burst
    ball_holder = BALL_HOLDER_MAP[jarviesStatus]
    sla_paused_at = now  // waiting clock starts here

ELSE (already paused):
    lastStatus = last agent_replied event's jarvis_status
    IF lastStatus != jarviesStatus:
        // Status changed → reset pause baseline to this message
        ball_holder = BALL_HOLDER_MAP[jarviesStatus]
        sla_paused_at = now  // waiting clock resets
    ELSE:
        // Same status as before → no change, waiting measured from first pause

INSERT event: agent_replied, resolution_hours, jarvis_status
```

**Burst coalescing example**:
```
Agent sends: [proposed_solution] → [proposed_solution] → [author_action]
Waiting clock starts at message 1
Message 2: same status → no reset
Message 3: different status → reset to message 3 timestamp
```

---

### 4.8 `handleRun` (private)

Handles `sent it to support` — helpdesk self-resume.

```
IF ball_holder != 'helpdesk' AND sla_paused_at IS SET:
    // Agent sent RUN while ball was with customer/SAP (e.g., forwarding to support)
    waitingHours = calcHours(sla_paused_at, now)
    total_waiting_hours += waitingHours
    ball_holder = 'helpdesk'
    sla_paused_at = NULL
    session_start_at = now  // new mini-session
    resolutionHours = 0     // recalculate from new session start

INSERT event: agent_replied, waiting_hours, resolution_hours, jarvis_status
```

---

### 4.9 `closeTicketSla(TicketSla, Ticket, ?TicketMessage, Carbon $closedAt, ?float $lastSessionHours): void`

Finalizes the SLA record when a ticket is closed.

```
1. If ball was not with helpdesk at close:
   finalWaiting = calcHours(sla_paused_at, closedAt)
   total_waiting_hours += finalWaiting

2. Refresh sla record (get updated totals)
3. grossHours = calcHours(sla_start_at, closedAt)
4. netHours = max(0, grossHours - total_waiting_hours)

5. resTarget = policy.resolution_hours (or null if no policy)
6. resStatus = (netHours <= resTarget) ? 'met' : 'breached'
   (if no policy → always 'met')

7. Update ticket_sla:
   resolved_at = closedAt
   net_resolution_hours = netHours
   resolution_status = resStatus
   ball_holder = 'helpdesk'
   sla_paused_at = NULL
   session_start_at = NULL

8. INSERT event: ticket_closed, resolution_hours = lastSessionHours, notes = 'Net SLA: X hrs'
```

---

### 4.10 `ensureTicketsHaveSla(): void`

Auto-sync method called by `SlaController::getReport()` before returning data.

```
1. SELECT all ticket_ids that already have a ticket_sla record
2. SELECT all tickets WITHOUT a record (ticket_type and priority must be set)
3. If none missing → return immediately (single COUNT query, fast path)
4. For each unsynced ticket → attachToTicket()
5. backfillEventsForTickets(new ticket IDs)
6. autoCloseSlaForClosedTickets(new ticket IDs)
```

This is **transparent to the user** — reports are always accurate even if tickets were created before SLA was set up.

---

### 4.11 `liveWaitingHours(TicketSla): float`

Returns current total waiting including any ongoing pause:

```php
total = ticket_sla.total_waiting_hours
if ball_holder != 'helpdesk' AND sla_paused_at IS SET:
    total += calcHours(sla_paused_at, now())
return round(total, 2)
```

---

## 5. SlaController — HTTP Layer

**File**: `app/Http/Controllers/SlaController.php`  
**Authorization**: All methods call `assertAdmin()` — requires `session('user.role.id') === 1`

### 5.1 Page Controllers

| Method | Route | View |
|---|---|---|
| `configPage()` | `GET /sla/config` | `admin.sla.config` |
| `reportPage()` | `GET /sla/report` | `admin.sla.report` |

Both pass `$customers` (active customers with `basicData`) to the view.

---

### 5.2 API — Policy Management

**`GET /api/admin/sla/policies`**  
Returns all policies, ordered: global first, then by customer, then Very High → Low priority, then Simple → Complex scale.  
Optional filter: `?customer_id=5` or `?customer_id=global`

**`POST /api/admin/sla/policies`**  
Validation rules:
```
customer_id      nullable|exists:customer,customer_id
priority         required|in:Low,Medium,High,Very High
scale            required|in:Simple,Medium,Complex
response_hours   required|numeric|min:0.1|max:999
resolution_hours required|numeric|min:0.1|max:999
is_24_hours      boolean
```
Duplicate check: returns 422 if `(customer_id, priority, scale)` already exists.

**`PUT /api/admin/sla/policies/{id}`**  
Allowed updates: `response_hours`, `resolution_hours`, `is_24_hours`, `is_active`.  
Cannot change `customer_id`, `priority`, or `scale`.

**`DELETE /api/admin/sla/policies/{id}`**  
Blocked if any `ticket_sla` row references this policy (`sla_policy_id`). Returns 422 with suggestion to deactivate instead.

---

### 5.3 API — Per-Ticket SLA Detail

**`GET /api/tickets/{id}/sla`**

Returns the full SLA event log and status for a single ticket. Events are reconstructed live (not served from `ticket_sla_events` — that table is for persistence; this endpoint reads `ticket_message` and replays state).

Response structure:
```json
{
  "success": true,
  "data": {
    "sla_mode": "full",
    "response": {
      "status": "met",
      "target_hours": 4,
      "actual_hours": 1.5,
      "due_at": "2026-05-10 13:00:00",
      "responded_at": "2026-05-10 11:30:00"
    },
    "resolution": {
      "status": "pending",
      "target_hours": 24,
      "actual_hours": null,
      "due_at": "2026-05-11 09:00:00",
      "resolved_at": null,
      "net_hours": null,
      "waiting_hours": 3.5
    },
    "events": [
      {
        "event_type": "email_received",
        "event_at": "...",
        "label": "...",
        "jarvis_status": null,
        "waiting_hours": null,
        "resolution_hours": null,
        "notes": "..."
      }
    ]
  }
}
```

---

### 5.4 API — SLA Report

**`GET /api/admin/sla/report`**

Called by the report page UI. Auto-syncs missing SLA records before returning.

Query parameters:
```
customer_id       — filter by customer
month             — numeric month (1-12)
year              — numeric year
resolution_status — pending | paused | met | breached
```

Response structure:
```json
{
  "success": true,
  "data": {
    "summary": {
      "total": 150,
      "active": 80,
      "met": 55,
      "breached": 15,
      "compliance_rate": 78.57,
      "avg_response_hours": 2.3,
      "avg_resolution_hours": 18.4
    },
    "tickets": [ ...up to 200 rows... ]
  }
}
```

Each ticket row includes: ticket_id, customer, type, delivery, priority, scale, sla_mode, sla_start_at, response (actual/target), resolution (actual/target), waiting_hours, status.

---

### 5.5 PDF Export

**`GET /admin/sla/tickets/{id}/pdf`**  
Route name: `sla.ticket.pdf`

Generates a formal PDF using `barryvdh/laravel-dompdf`.  
View: `resources/views/admin/sla/ticket-pdf.blade.php`

PDF sections:
1. Company letterhead + document number
2. Title band ("Service Level Agreement Report")
3. Ticket metadata (type, customer, priority, scale, dates)
4. SLA status boxes (Response vs Resolution, actual vs target)
5. Metrics strip (waiting hours, event count, pause count, calc mode, due dates)
6. Pause history table (if any pauses exist)
7. Event history table (chronological)
8. Signature area (Prepared by / Acknowledged by / Approved by)
9. Confidentiality footer

---

## 6. Routes

### Web Routes (`routes/web.php`)

```php
Route::get('/sla/config', [SlaController::class, 'configPage'])
    ->name('sla.config');

Route::get('/sla/report', [SlaController::class, 'reportPage'])
    ->name('sla.report');

Route::get('/admin/sla/tickets/{id}/pdf', [SlaController::class, 'downloadTicketPdf'])
    ->name('sla.ticket.pdf');
```

### API Routes (`routes/api.php`)

```php
// Per-ticket SLA detail (nested under ticket resource)
Route::get('/tickets/{id}/sla', [SlaController::class, 'getTicketSla']);

// Policy CRUD (admin only)
Route::get('/admin/sla/policies',        [SlaController::class, 'getPolicies']);
Route::post('/admin/sla/policies',       [SlaController::class, 'storePolicy']);
Route::put('/admin/sla/policies/{id}',   [SlaController::class, 'updatePolicy']);
Route::delete('/admin/sla/policies/{id}',[SlaController::class, 'destroyPolicy']);

// Report data
Route::get('/admin/sla/report', [SlaController::class, 'getReport']);
```

---

## 7. Views & Frontend UI

### 7.1 SLA Config Page
**File**: `resources/views/admin/sla/config.blade.php`

**Layout**: Full-page admin table with modals.

**Features**:
- Customer filter dropdown (All / Global / specific customer)
- Policy table columns: Customer, Priority (colored badge), Scale, Response Target, Resolution Target, Mode (24h/Business), Status, Actions
- **Add Policy** modal: customer selector, priority, scale, response hours, resolution hours, 24h toggle
- **Edit Policy** modal: modify hours and flags; cannot change customer/priority/scale
- **Delete** button: shows confirmation, disabled (with explanation) if policy is used

**Priority badge colors**:
```
Very High → red
High      → orange
Medium    → yellow
Low       → blue
```

**JS Functions**:
```javascript
loadPolicies()        // GET /api/admin/sla/policies → render table
submitAddPolicy()     // POST /api/admin/sla/policies
submitEditPolicy()    // PUT /api/admin/sla/policies/{id}
deletePolicy(id)      // DELETE with confirmation dialog
```

---

### 7.2 SLA Report Page
**File**: `resources/views/admin/sla/report.blade.php`

**Layout**: Filter bar → summary cards → sortable table → detail modal.

**Filter bar**: Customer, Month, Year, Status — triggers `loadReport()` on change.

**Summary cards** (6 cards):
- Total Tickets
- Active (pending + paused)
- Met
- Breached
- Compliance Rate % = met / (met + breached) × 100
- Avg Response Hours
- Avg Resolution Hours (net)

**Table columns**: Ticket #, Customer, Type, Delivery, Priority, Scale, SLA Mode, SLA Start, Response (actual / target), Resolution (actual / target), Waiting Hours, Status, Actions.

**Auto-refresh**: `setInterval(loadReport, 60000)` — refreshes every 60 seconds.

**Event log modal**: Clicking the "detail" button for any ticket fetches `GET /api/tickets/{id}/sla` and renders a timeline table with:
- Timestamp, Event type (icon + label), Jarvis status badge, Ball holder badge, Waiting hours, Resolution hours, Message preview

**JS Constants** (defined in-view):
```javascript
PRIORITY_COLORS     = { Low, Medium, High, 'Very High' }
TICKET_TYPE_COLORS  = { Incident, 'Service Request', ... }
SLA_MODE_CONFIG     = { full, response_only }
STATUS_CONFIG       = { pending, paused, met, breached, ... }
EVENT_ICONS         = { email_received, ticket_validated, agent_replied, ... }
BALL_BADGE          = { helpdesk, customer, sap }
JARVIS_BADGE        = { 'in process', 'proposed solution', ... }
```

---

### 7.3 SLA PDF Template
**File**: `resources/views/admin/sla/ticket-pdf.blade.php`

A self-contained HTML/CSS document rendered by DomPDF. Uses inline styles (no Tailwind) because DomPDF requires static CSS.

Data passed from controller:
```php
$ticket       // Ticket model
$sla          // TicketSla model (with policy, pauses, events loaded)
$policy       // SlaPolicy model
$events       // Collection of TicketSlaEvent
$pauses       // Collection of TicketSlaPause (only ended ones shown)
```

---

## 8. Artisan Commands

### 8.1 `sla:backfill-events`
**File**: `app/Console/Commands/BackfillMissingSlaEvents.php`

```bash
php artisan sla:backfill-events           # Process messages from last 24 hours
php artisan sla:backfill-events --all     # Process all messages
php artisan sla:backfill-events --recalculate  # Delete and regenerate all events
```

**Logic**: Finds `ticket_message` rows not yet in `ticket_sla_events`, walks each ticket's message history chronologically, and inserts `customer_replied` / `agent_replied` events using the same state machine as `SlaService::backfillEventsForTickets()`.

Use when: new tickets were created while service was down, or you need to rebuild event history after a schema change.

---

### 8.2 `sla:sync`
**File**: `app/Console/Commands/SyncSlaFromTickets.php`

```bash
php artisan sla:sync               # Sync only tickets missing SLA records
php artisan sla:sync --force       # Drop all SLA data, regenerate from scratch (DESTRUCTIVE)
php artisan sla:sync --skip-events # Create SLA records but skip backfilling events
```

**Logic**:
1. Load all tickets with matching SLA policy (priority + scale + customer)
2. Call `SlaService::attachToTicket()` for each unsynced ticket
3. Call `BackfillMissingSlaEvents` for event generation
4. Auto-close SLA for already-closed tickets

Use `--force` only in development or initial setup — it destroys all existing SLA data.

---

## 9. Seeder

**File**: `database/seeders/SlaSeeder.php`

Populates initial SLA policies for known customers:

| Customer | Policies | Notes |
|---|---|---|
| Global default (`NULL`) | 12 policies | All priority × scale combinations |
| Pegadaian (id=1) | 12 policies | Custom time targets |
| Airnav (id=4) | 4 policies | Medium scale only |
| Indo Raya (id=37) | 12 policies | Full matrix |
| Aptaworks (id=39) | 12 policies | Full matrix |

**Also runs**:
1. Sets `ticket_type = 'Incident'` for tickets missing a type
2. Sets `scale = 'Simple'` for tickets missing a scale
3. Calls `php artisan sla:sync` to generate `ticket_sla` records
4. Calls `php artisan sla:backfill-events --all` to populate events

---

## 10. Integration Points

### 10.1 Ticket Model SLA Relationship
**File**: `app/Models/Ticket.php`

```php
public function sla(): HasOne
{
    return $this->hasOne(TicketSla::class, 'ticket_id', 'ticket_id');
}

public function isSlaEligible(): bool
{
    // Returns true if ticket has type, priority, and a matching policy
}

public function getSlaMode(): string
{
    // Returns 'full' or 'response_only' based on ticket_type
}

public static function slaFullTypes(): array
{
    return ['Incident', 'Service Request'];
}
```

### 10.2 Where `attachToTicket` is Called

Called inside `StagingTicketService::validateTicket()` (or equivalent) when a staging ticket is validated and converted to a real ticket:

```php
// Somewhere in ticket validation flow
$slaService = app(SlaService::class);
$slaService->attachToTicket($ticket, $stagingTicket);
```

### 10.3 Where `recordMessageEvent` is Called

Called in `TicketMessageController` (or observer) after a message is stored:

```php
$slaService->recordMessageEvent(
    $ticket,
    $message,
    $senderType,  // 'customer' or 'employee'
    $jarviesStatus // from message.jarvies_status or request input
);
```

### 10.4 Where `closeTicketSla` is Called

Called when ticket status is changed to `closed` / `done`:

```php
if ($ticket->sla && !$ticket->sla->isClosed()) {
    $slaService->closeTicketSla(
        $ticket->sla,
        $ticket,
        $lastMessage,    // nullable
        Carbon::now(),
        $lastSessionHours // nullable float
    );
}
```

---

## 11. State Machine

### 11.1 Full Lifecycle Diagram

```
[Email arrives]
     │
     ▼ sla_start_at = staging.created_at
  pending_validation
     │
     │ helpdesk validates (attachToTicket)
     │ first_responded_at = ticket.created_at
     │ response_status = met|breached
     ▼
  pending  ← ball_holder = helpdesk
     │
     ├─ agent replies (STOP status) ──────────────────────────────────────────►
     │  ball_holder = customer|sap                                              │
     │  sla_paused_at = now                                              paused │
     │                                                                          │
     │  ◄─ customer replies (first in burst) ──────────────────────────────────┘
     │     waiting_hours += (now - sla_paused_at)
     │     ball_holder = helpdesk
     │     session_start_at = now
     │
     ├─ agent replies (RUN status) → ball stays with helpdesk
     │
     └─ ticket closed ──────────────────────────────────────────────────────────
           net = gross - total_waiting_hours
           ┌─ net ≤ resolution_hours → met
           └─ net > resolution_hours → breached
```

### 11.2 Ball Holder Transition Table

| Current Ball | Event | New Ball | Action |
|---|---|---|---|
| helpdesk | agent sends STOP status | customer / sap | `sla_paused_at = now` |
| customer / sap | customer sends message | helpdesk | `total_waiting += elapsed`, `session_start_at = now` |
| customer / sap | agent sends RUN status | helpdesk | same as above |
| helpdesk | agent sends RUN status | helpdesk | no change |
| any | ticket closed | helpdesk | finalize SLA |

### 11.3 Burst Coalescing Rule

When an agent sends multiple messages in a row (without customer reply between them):

```
Message 1: status=A → sla_paused_at = T1, ball=customer
Message 2: status=A → same status → sla_paused_at stays at T1 (no reset)
Message 3: status=B → different status → sla_paused_at resets to T3

Waiting measured from: T3 (the last message with a status change)
```

This prevents inflating the waiting time when an agent clarifies or resends.

---

## 12. Business Rules Reference

| Rule | Implementation |
|---|---|
| T=0 is email arrival, not ticket creation | `sla_start_at` = `staging_tickets.created_at` |
| Customer-specific policy overrides global | `orderByRaw('customer_id IS NULL ASC')` in query |
| Response SLA: staging → validation time | `validation_duration_hours` vs `response_hours` |
| Resolution SLA: net of waiting | `gross - total_waiting_hours` |
| Waiting = customer/SAP ball time | Ball holder tracking in `ticket_sla` |
| Burst coalescing on same status | `lastAgentJarvisStatus()` comparison |
| Business hours = Mon-Fri 09:00-18:00 | `calcHours()` with `is24h = false` |
| Only Incident/SR get resolution SLA | `FULL_SLA_TYPES` constant |
| Internal notes don't trigger SLA | `if ($message->is_internal_note) return` |
| Closed SLA is immutable | `if ($sla->isClosed()) return` in `recordMessageEvent` |
| Auto-sync on report load | `ensureTicketsHaveSla()` in `getReport()` |
| Events and pauses are append-only | No `updated_at` on those tables; never UPDATE |

---

## 13. How to Extend or Modify

### Add a new jarvis_status to STOP_STATUSES
1. Add the status string to `SlaService::STOP_STATUSES`
2. Add its `ball_holder` value to `SlaService::BALL_HOLDER_MAP`
3. Add a description to `SlaService::noteForStatus()`
4. Update the `JARVIS_BADGE` JS constant in `report.blade.php`

### Add a new ticket type to FULL_SLA_TYPES
1. Add the type string to `SlaService::FULL_SLA_TYPES`
2. Add to `Ticket::slaFullTypes()` static method
3. Update ticket_type ENUM in the tickets migration (or add migration)

### Add a new SLA Policy for a customer
Via UI: `/sla/config` → Add Policy  
Via seeder: Add to `SlaSeeder.php` and run `php artisan db:seed --class=SlaSeeder`  
Via tinker:
```php
SlaPolicy::create([
    'customer_id'      => 5,
    'priority'         => 'High',
    'scale'            => 'Simple',
    'response_hours'   => 4,
    'resolution_hours' => 24,
    'is_24_hours'      => false,
    'is_active'        => true,
]);
```

### Rebuild SLA data from scratch
```bash
php artisan sla:sync --force          # Destroys and rebuilds all ticket_sla records
php artisan sla:backfill-events --all # Rebuilds all events from ticket_message history
```

### Debug a specific ticket's SLA
```bash
php artisan tinker
>>> $ticket = Ticket::with('sla.policy', 'sla.events', 'sla.pauses')->find(12345);
>>> $ticket->sla->ball_holder         // current ball holder
>>> $ticket->sla->total_waiting_hours // accumulated waiting
>>> $ticket->sla->events              // chronological event log
>>> app(App\Services\SlaService::class)->liveWaitingHours($ticket->sla)
```

### Run the report sync manually (without opening the browser)
```bash
php artisan tinker
>>> app(App\Services\SlaService::class)->ensureTicketsHaveSla();
```
