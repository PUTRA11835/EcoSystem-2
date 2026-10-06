# Plan — Letters Hub (HR & General → Letter Templates)

> **Created:** 2026-10-02 · **Branch:** `nafa_hr` · **Status:** ✅ IMPLEMENTED 2026-10-05 (all 5 phases) — see §8 for where the build differs from this plan.
> **Goal:** turn the single "Letter Templates" settings page into a 5-tab hub: **Dashboard · Requests · Letter Register · Create Letter · Settings**, with every tab gated separately in Management → Roles (View / Create / Edit / Delete).
> **References:** the 8 screenshots from the reference app (Document Management, Dokumen & Surat HR, Surat Keluar, Custom Surat, Surat Masuk, and their modals).

---

## 0. Decisions

| # | Question | Decision |
|---|----------|----------|
| Q1 | Incoming and outgoing letters: one table or two? | ✅ **One table** with a `direction` column (§3.1). Each direction keeps its own number sequence. |
| Q2 | Template bodies in code or edited in the browser? | ✅ **Code-defined for v1.** ✅ **One Blade file per letter that holds both languages**, with no per-letter lang files (§3.3). |
| Q3 | Employee self-service + where HR handles requests | ✅ `My Letter Requests` page for employees. HR gets a **separate Requests tab** (the hub now has **5 tabs**). The finished letter is **downloadable in the app and also emailed** (§4, Tab 2 and ESS). |
| Q4 | What do `OF / FI / SM` and `IN` mean in `ESH/09/OF/IN/21018/2026`? | ✅ **`OF / FI / SM` are letter codes**, editable in the Settings tab (the Offering Letter uses **`OL`**). ✅ **`IN` is the language segment**: `IN` for Indonesian, `EN` for English (§3.4). ✅ **One running number** continues across every code *and* both languages. |
| Q5 | Offering Letters in the register? | ✅ Yes, read-only, `source = offering_letter`. |
| Q6 | Deleting numbered letters | ✅ **Void, no hard delete.** ✅ **Numbers are never reused.** The next number always follows the highest number ever issued, even if that letter was voided or deleted (§3.4). |
| Q7 | Generated letter on the employee profile | ✅ Yes, as a link (`letters.employee_id`), not a copy. |
| Q8 | Signing | ✅ HR generates the letter, then **signs it with the signatory's signature from the employee master data** (button), then sends it. Same flow as the Offering Letter (§1, Tab 2). |
| Q9 | Resend | ✅ **Resend appears only when the email failed.** A letter that was emailed successfully has no Resend, so an employee is never emailed twice by accident (Tab 2). |
| Q10 | Who gets *My Letter Requests* | ✅ The **User System Registered** role, which all 211 active employees hold and every new hire gets automatically when their offer is accepted (§5). |

---

## 1. Current state (what exists today)

- One page, `general/letter-templates` → `LetterTemplateController@index`, one slug `general.letter-templates` (C/E/D boxes).
  - **Letter Language:** default language per letter type (`letter_type_settings`).
  - **Letterheads:** full-page A4 background images, each ticked for letter types (`letterheads`).
- `Letterhead::LETTER_TYPES` only knows `offering_letter`.
- Letter numbering exists only for offers (`Offer::numberFor()` + `RecruitmentSetting::offer_number_format`).
- PDFs are rendered with `Barryvdh\DomPDF` (`Pdf::loadView`).

**Done on 2026-10-05 (Offering Letter). The hub reuses these:**
- **Signing from master data:** the letter picks its signatory from the employee list (`signatory_employee_id`).
  - **Sign** copies that employee's `employee_hr_profile.signature_path` onto the letter (`signature_path`, `signed_at`, `signed_by_employee_id`), and the PDF prints it.
  - Changing a signed letter removes the signature. Only a signed letter can be sent.
  - The signature file is read through `EmployeeHrProfile::signaturePathOf()`, on disk `EmployeeHrProfile::FILE_DISK` (`local`). The master-data upload screen being built separately must save there.
- **Send** opens a modal where HR adjusts the email subject and message. The starting text comes from `lang/{id,en}/offering_letter.php` in the letter's language, and the signed PDF is attached.
- Statuses are now **Draft → Signed → Sent → Accepted / Rejected**.
- **`{lang}` token** in the offer number format (`IN` / `EN`). The saved format `EC/{month}/OL/IN/…` became `EC/{month}/OL/{lang}/…`, and the running number stays one sequence for both languages.
- **Hiring:** accepting an offer sets the new employee's **join date** (`employee_basic_data.since_date`, "Since" / "Join Date") to the day the account is created, and their position to the offer's position. A new contract in Master Employee starts from that join date.
- Nothing exists yet for incoming letters, an outgoing register, custom letters, template letters other than offers, or employee requests.

---

## 2. Target structure

```
HR & General
 ├─ Letter Templates  (sidebar entry; label → "Letters")
 │    ├─ Dashboard        /general/letters                general.letters
 │    ├─ Requests         /general/letters/requests       general.letters.requests
 │    ├─ Letter Register  /general/letters/register       general.letters.register
 │    ├─ Create Letter    /general/letters/compose        general.letters.compose
 │    └─ Settings         /general/letters/settings       general.letter-templates   (existing slug, kept)
 └─ My Letter Requests    /general/my-letter-requests     general.my-letter-requests (ESS, Q3)
```

**Why Requests is its own tab, not part of the Dashboard:**
- The Dashboard stays **view-only**: a summary everyone with access may see.
- Handling requests becomes a separate right. You can give a junior HR role the request queue without the Register or Settings, and give a manager the Dashboard without the power to reject requests.
- The queue needs its own filters and history (done/rejected), which would crowd a dashboard.
- The tab shows a yellow "N pending" badge, like Leave & Permit's Approval Inbox.

- The tabs use the shared `partials.hub-tabs` partial. Each tab has its own `gate`, so **a tab the user can't view is not rendered at all**.
- The sidebar entry shows when the user holds **any** of the five slugs. Clicking it goes to `/general/letters`, which **redirects to the first tab the user can view**, so a Settings-only user never lands on a 403.
- The existing slug `general.letter-templates` is **kept for Settings** because it was already committed and may already be granted. Only its display name changes, to `Letters — Settings`. The old URL `/general/letter-templates` redirects to `/general/letters/settings`.

---

## 3. Data model

### 3.1 Why one register table for incoming + outgoing (Q1)

| | One table + `direction` ✅ | Two tables |
|---|---|---|
| Shared fields (number, date, counterparty, subject, attachment, classification, notes) | defined once | duplicated |
| "Search every letter about X" / dashboard totals | one query | a `UNION` everywhere |
| Generated letters (template/custom/offer) landing in the register | same table, `source` column | must always go to the outgoing table |
| Replying to an incoming letter (`reply_to_id`) | self-reference | cross-table FK |
| Direction-specific fields | nullable columns (4–5 of them) | clean |
| Separate number books (*buku agenda masuk / keluar*) | sequence keyed by `direction` | natural |

Only the last two rows favour two tables, and both are cheap to handle in one table. Records-management practice keeps **separate number books**, not separate storage, and that is what the `direction`-keyed sequence gives us.

### 3.2 Tables (one new migration for tables, one for menus)

**`letters`** — the register; every letter in or out is one row.

| Column | Notes |
|---|---|
| `id` | |
| `direction` | `incoming` \| `outgoing` |
| `source` | `manual` \| `template` \| `custom` \| `offering_letter` |
| `template_key` | key of the template registry (only for `source = template`) |
| `source_id` | e.g. `recruitment_offers.id` for offering letters |
| `letter_number` | outgoing: generated or manual · incoming: the sender's number. Unique per direction. |
| `number_sequence`, `number_year` | used for the sequence; outgoing and incoming each have their own |
| `agenda_number` | incoming only (generated from the agenda format) |
| `classification_code_id` | FK `letter_classification_codes` (OF/FI/SM…), nullable |
| `letter_date` | |
| `received_date` | incoming only |
| `counterparty` | outgoing: recipient · incoming: sender |
| `subject` | *perihal* |
| `employee_id` | nullable; the letter is *about* this employee (SKK, reference, assignment…) |
| `language` | `id` \| `en` |
| `letterhead_id` | nullable = plain paper; copied when generated so later settings changes don't alter old letters |
| `use_letterhead` | bool (custom letters can switch it off) |
| `fields` | JSON: a template's field values, or a custom letter's body |
| `signer_name`, `signer_title` | printed under the signature |
| `signer_employee_id` | whose master-data signature the letter is signed with |
| `signature_path`, `signed_at`, `signed_by` | the signature copied onto the letter when HR signs it (same as the Offering Letter) |
| `delivered_via` | outgoing (*Diterima oleh* / courier / email) |
| `receipt_number` | outgoing (*Resi*) |
| `generated_path` | the system-generated PDF (private disk) |
| `final_path` | the signed/stamped scan uploaded afterwards (incoming: the received scan) |
| `notes` | archive note |
| `status` | `draft` \| `issued` \| `void` |
| `void_reason`, `voided_by`, `voided_at` | Q6 |
| `letter_request_id` | nullable; set when the letter fulfils an employee request |
| `created_by`, `updated_by`, timestamps, soft deletes | |

**`letter_requests`** — employee self-service requests.

| Column | Notes |
|---|---|
| `id`, `employee_id` | |
| `template_key` | only templates marked *requestable* in Settings |
| `language` | the employee's choice, defaulting to the template's language |
| `purpose` | e.g. "visa application", "bank loan" |
| `needed_by` | date |
| `notes` | |
| `status` | `pending` → `in_progress` → `done` \| `rejected` \| `cancelled` |
| `handled_by`, `handled_at`, `reject_reason` | |
| `letter_id` | the issued letter (on `done`) |
| `email_status` | `null` (not sent yet) \| `sent` \| `failed` — decides whether *Resend* is shown |
| `emailed_at`, `email_error` | when it was delivered; why the last attempt failed |
| timestamps | |

**`letter_number_sequences`** — the counter behind every generated number (§3.4).
`direction`, `year`, `last_value`, unique on (`direction`, `year`).

**`letter_classification_codes`** — the letter codes printed in the number (`OF`, `FI`, `SM`, `OL`…): `code`, `name` (what it stands for), `is_active`, `sort`. Edited in the Settings tab.

**`letter_type_settings`** (exists) — extended with one row per template, all edited in Settings:
`is_active`, `is_requestable`, `default_signer_name`, `default_signer_title`, `classification_code_id`, and the existing `language`.

**Numbering settings** — added to the same settings store: `outgoing_number_format` (default `ESH/{month}/{code}/{lang}/{day}{seq}/{year}`), `outgoing_number_digits` (3), `incoming_agenda_format`, `incoming_agenda_digits`. Sequences restart every year.

### 3.3 Template registry and languages (Q2)

`app/Support/Letters/LetterTemplates.php` holds one entry per template: key, label, whether it needs an employee, a **field schema** (name, type, rules, label), and its Blade. `Letterhead::LETTER_TYPES` is built from this registry plus `custom_letter` and `offering_letter`, so letterhead and language settings automatically cover every template.

**Where the Indonesian and English text lives: one Blade per letter, holding both languages.**

There are two kinds of text in a letter, and each gets the approach that suits it:

| Kind of text | Example | Where it lives | Why |
|---|---|---|---|
| **The letter body**: long prose, written differently in each language, not just translated word for word | *"Yang bertanda tangan di bawah ini…"* vs *"This is to certify that…"* | **In the letter's own Blade, both versions side by side** (`@if($language === 'en') … @else … @endif`) | Splitting a paragraph into many lang keys makes it unreadable. Keeping both versions next to each other makes it obvious when one changed and the other didn't. One letter = one file to open. |
| **Shared pieces**: the same in every letter | "Nomor / Number", "Perihal / Subject", "Hormat kami / Sincerely", month names, the amount in words (*terbilang*) | **One shared file per language**: `lang/id/letters.php` and `lang/en/letters.php` | Written once, used by every letter, and also by UI labels and emails. |

The resulting files:

```
resources/views/hr-general/letters/pdf/
    layout.blade.php                   ← letterhead background, number, date, recipient, signature block
    employment_certificate.blade.php   ← @extends layout; body in id + en
    employment_reference.blade.php
    assignment_letter.blade.php
    goods_receipt.blade.php
    payment_receipt.blade.php
    custom_letter.blade.php            ← prints the body HR typed
lang/id/letters.php                    ← shared labels only (one file for all letters)
lang/en/letters.php
```

So: **no lang file per letter.** Adding a new template means writing one Blade and adding one entry to the registry.

- **Language switching in code:** the letter is rendered inside `App::setLocale($letter->language)`, then the locale is restored. `__('letters.subject')` and `Carbon::translatedFormat()` (for "2 Oktober 2026" / "2 October 2026") pick the right language without passing it around.
- **The Offering Letter stays as it is** (`lang/{id,en}/offering_letter.php`). Its texts are also used by its two emails, which is exactly the "shared pieces" case. Moving it would gain nothing.
- **Adding a third language later** means adding one more `@elseif` per letter and one more shared lang file.

### 3.4 Numbering (Q6): numbers are never reused

Best practice for an official letter register: **once a number has been given out, it is spent**, whether the letter is later voided, deleted, or was never sent.
- The register then shows every number in order, and a missing number always has a visible reason (a void row with its reason).
- An auditor or the recipient can trust that `…/21018/2026` points to exactly one letter forever.
- Reusing a number can leave two different letters out in the world with the same number. That is the case you can't fix later.

How it works:
- The next number comes from the counter table `letter_number_sequences`, **not** from "the highest number in `letters` + 1". With "highest + 1", deleting the newest letter would hand its number to the next letter.
- Generating a letter, inside one DB transaction: lock the counter row (`lockForUpdate`) → `last_value + 1` → save it → build the number from the format → save the letter. Two HR users generating at the same moment can't get the same number.
- **Preview never touches the counter.** It prints "(number assigned on generate)".
- A **manually typed number** is checked for uniqueness but doesn't move the counter.
- The counter restarts every year (one row per `direction` + `year`). Incoming agenda numbers have their own counter.
- **Existing gap in the Offering Letter:** `Offer::nextSequence()` currently uses `max(number_sequence) + 1`, and offers aren't soft-deleted, so deleting the newest offer reuses its number. Phase 4 moves the Offering Letter onto the **same outgoing counter** with code `OL`.

**Reading `ESH/09/OF/IN/21018/2026`:**

| Segment | Token | Meaning |
|---|---|---|
| `ESH` | (fixed text) | company |
| `09` | `{month}` | month of the letter date |
| `OF` | `{code}` | letter code: `OF` / `FI` / `SM` / `OL`…, editable in Settings |
| `IN` | `{lang}` | language: `IN` = Indonesian, `EN` = English |
| `21` + `018` | `{day}{seq}` | day of the letter date, then the running number (3 digits) |
| `2026` | `{year}` | |

- **One running number for all outgoing letters**, whatever the code or the language. An English `OL` letter after an Indonesian `OF` letter still takes the next number: `…/OF/IN/21018/…` → `…/OL/EN/22019/…`. That is also what the reference register shows (016 → OL 017 → OF 018).
- **`IN` or `ID`?** Keep **`IN`**. The ISO 639-1 language code is `id` (that's what the app stores internally), but the letters already issued carry `IN`. Switching now would make one year's register use two codes for the same language. The printed code is a setting (`LetterTypeSetting::NUMBER_CODES`, made editable in Settings), so it can be changed at the start of a year if ever needed.
- The reference register shows `016` twice and `012` twice. Those are the reused/duplicate numbers this counter is designed to prevent.

Initial templates, taken from the reference app:

| Key | Template | Employee? | Extra fields |
|---|---|---|---|
| `employment_certificate` | Surat Keterangan Kerja | yes | purpose |
| `employment_reference` | Surat Referensi Kerja | yes | (optional) end date, remarks |
| `assignment_letter` | Surat Tugas | yes | location, period from/to, assignment description |
| `goods_receipt` | Tanda Terima | optional | items (repeatable: name, qty, note), received from |
| `payment_receipt` | Kwitansi | optional | amount, amount in words (auto, editable), payment purpose, received from |

Employee data (name, ID, position, department, join date) and company data are **pulled when the letter is generated and saved into `fields`**, so a reprint looks the same even after the employee changes position.

---

## 4. Tabs in detail

### Tab 1 — Dashboard (`general.letters`)
Its layout follows screenshot 1 (hero with KPI tiles), adapted to app styling.

**View-only**: no buttons that change anything.
- **KPI tiles:** Outgoing this month · Incoming this month · Pending requests · Requests due ≤ 3 days · Voided this year.
- **Requests needing attention:** the 5 oldest pending requests. Each links to the Requests tab, and the link only shows if the user can view that tab.
- **Recent letters:** the last 10 register rows (in/out). Clicking one goes to the register filtered to it.
- **By template:** letters generated this year per template (small bar list).

### Tab 2 — Requests (`general.letters.requests`)
- **Table:** every employee request, with column-header funnel filters (status, template, employee, needed-by) and KPI-style pagination. It opens filtered to *Pending + In progress*, and done/rejected/cancelled requests stay visible as history.
- **Tab badge:** "N pending" (hub-tabs `badge` closure).
- **Row actions** (icon-only):
  - **Process** (blue): opens *Create Letter* pre-filled with the employee, template, language and purpose, and sets the request to `in_progress`. When the letter is generated it is linked to the request.
  - **Reject** (red): asks for a reason, which the employee sees.
  - **Sign** (green, on `in_progress` with a linked letter): signs it with the master-data signature.
  - **Complete & Send** (indigo, once the letter is signed). See the delivery flow below.
  - **Resend** (amber, only when `email_status = failed`).
  - **View** (gray): request details, the linked letter, and the email log.

**Delivery flow:** the same as the Offering Letter. The letter is **always in the app, and also emailed.**
1. **Generate:** HR generates the letter from the request, and the request stays `in_progress`.
2. **Sign:** HR clicks **Sign with master data signature**.
   - The signer's signature (`employee_hr_profile.signature_path`) is copied onto the letter and printed on the PDF.
   - Without a signature in master data, the button explains that it has to be uploaded there first.
   - Changing the letter afterwards removes the signature.
3. **Complete & Send** (only once signed):
   - Opens a modal with the email subject and message, starting from the template text in the letter's language and editable.
   - The request becomes `done`, and the letter shows under the employee's *My Letter Requests* with a **Download** button.
   - The signed PDF is emailed as an attachment to the employee's work email through Microsoft Graph, the same way offering letters are sent.
   - The employee gets an in-app notification.
4. The employee can **download the same file anytime** from *My Letter Requests*, only for their own requests.

**Resend: only when the email failed.**
- `email_status` is stored per request:
  - `sent` (with `emailed_at`): **no Resend button**, so the employee can never be emailed twice by accident. If they lost the email, the file is always in *My Letter Requests*.
  - `failed` (with `email_error`, for example a missing Graph permission): the request is **still `done`**, the file is **still downloadable in the app**, and HR sees a **Resend** button plus the error. A successful resend switches it to `sent`, and the button disappears.
- So Resend never affects an email that was delivered. It only retries one that wasn't.

Why both channels:
- The app is the **record**: it is always available and can be downloaded again.
- Email is the **convenience**: the employee gets it without logging in.

### Tab 3 — Letter Register (`general.letters.register`)
One table for screenshots 3 and 7, with a segmented switch **All · Outgoing · Incoming**.

- **Columns:** No · Direction badge · Number (outgoing number / incoming agenda + sender no.) · Date · Counterparty · Subject · Source (Manual / Template / Custom / Offering) · Status (Issued / Void) · Attachment · Actions.
- **Filters** use column-header funnels: direction, source, classification, date range, status, and a debounced search.
- **Buttons on the page:** `+ Incoming Letter` and `+ Outgoing Letter`, for manually logging a letter that wasn't produced in the system. Each opens its own create/edit page (per our convention for multi-field forms): the incoming form takes the fields from screenshot 7, the outgoing form the fields from screenshot 6.
- **Row actions:** Download generated PDF · Download final scan · Upload final scan · Edit metadata · Void (outgoing) / Delete (manual incoming).
- Rows produced by Create Letter or Offering Letter have read-only content here. The edit pencil on those rows sends the user to the place that owns the content.

### Tab 4 — Create Letter (`general.letters.compose`)
Screenshots 2, 4 and 5 merged into one page, with a mode switch **From Template · Custom Letter**.

- **From Template**
  - Left column, the form:
    - Template, then language (preset from Settings, switchable).
    - Employee picker, if the template needs one.
    - **The template's own fields, rendered from its schema**, so only the fields that template needs are shown.
    - Letter date · manual number (empty = auto) · letter code (OF/FI/SM…) · **signer, picked from the employee list** (name/title preset from Settings, editable) · archive note.
  - Right column: the catalogue of active templates (cards as in screenshot 2) with a short description and the fields each one pulls automatically.
  - Buttons: **Preview** (renders the PDF inline without saving or consuming a number) · **Generate & Open PDF** · Reset.
- **Custom Letter** (screenshot 5 as a page instead of a modal)
  - Fields: classification code, number (auto), date, subject, recipient, signer name/title, body, *use letterhead* toggle, language, archive note.
  - The body is a textarea with paragraph breaks preserved (same as the reference). A rich-text editor is optional; see §7.
- **Below the form, "Letters I can manage":** the generated template and custom letters (screenshots 2 and 4 tables), with header filters. Status is Draft → Signed → Sent (→ Void).
  - Actions: Download PDF · **Sign with master data signature** · Send (once signed; editable email) · Edit & regenerate (removes the signature) · Void.
- **Language** changes the letter text (lang files), the date format (`2 Oktober 2026` / `October 2, 2026`), and the closing/signature labels.

### Tab 5 — Settings (`general.letter-templates`, existing page extended)
Sections, top to bottom:
1. **Templates:** one row per template with Active, Requestable by employees, default language, default letter code, default signer (employee). (This absorbs the current *Letter Language* card.)
2. **Numbering:**
   - Outgoing number format and incoming agenda format, with tokens `{seq} {code} {lang} {day} {month} {roman} {year} {yy}`.
   - A live preview like the Offering Letter settings.
   - The printed language codes (`IN` / `EN`).
3. **Letter codes:** the `OF / FI / SM / OL…` list. Add, rename what a code stands for, deactivate. A code already used on a letter can be deactivated but not deleted, so old numbers keep their meaning.
4. **Letterheads:** the current cards, unchanged, but the type toggles now list every template plus Custom and Offering.

### ESS — My Letter Requests (`general.my-letter-requests`)
**Who:** every employee. The slug is granted to the **User System Registered** role, held by all 211 active employees and given automatically to every new hire (`CandidateHireService`). It sits with the other self-service pages (My Attendance, My Reimbursement…).

- A list of my requests showing status, the date it was emailed, and the reject reason.
- A `+ Request Letter` button: template (from the *requestable* list), language, purpose, needed-by, notes.
- Cancel while `pending`. **Download** once `done`, which serves the same file that was emailed.
- Notifications, using the existing `Notification` model:
  - HR users who can view the Requests tab are notified of a new request.
  - The employee is notified when the request is done (plus the email) or rejected.

---

## 5. Permissions (Management → Roles)

Every tab is a separate slug, so each role can be given any combination. The routes enforce this with `menu:{slug}` (View) and `menu.can:{slug},{action}` (C/E/D); views use `$canDo()` to hide buttons the user can't use.

| Slug (Roles label) | View | Create | Edit | Delete |
|---|---|---|---|---|
| `general.letters` — *Letters — Dashboard* | see KPIs, pending summary, recent letters | — | — | — |
| `general.letters.requests` — *Letters — Requests* | see all employee requests + history | — | **process, reject, sign, complete & send, resend a failed email** | — |
| `general.letters.register` — *Letters — Register* | see and download every register row | log a manual incoming/outgoing letter | edit metadata, upload the final scan | **void** an outgoing letter, delete a manual incoming one |
| `general.letters.compose` — *Letters — Create Letter* | open the tab, preview, download generated letters | generate a template or custom letter | edit & regenerate, **sign**, send | void a generated letter |
| `general.letter-templates` — *Letters — Settings* | see settings | add letterhead / letter code | change templates, numbering, language codes, letter codes, letterheads | delete letterhead / unused letter code |
| `general.my-letter-requests` — *My Letter Requests* | see own requests | submit | edit while pending | cancel while pending |

Notes:
- Processing a request needs **Requests E *and* Compose C**, because processing generates a letter. The Process button is hidden unless both are held.
- The Dashboard has no C/E/D meaning. Those boxes in Roles are simply unused for it.
- Ownership on ESS is checked in the controller (`employee_id = me`), not only by slug, following the same rule as My Reimbursement.
- **Starting grants (migration):**
  - **Hub tabs:** EC Administrator only (the `MenuRegistrar` rule). After that, give them to HR roles in Management → Roles.
  - ***My Letter Requests*:** EC Administrator **and User System Registered** (view + create + edit + delete). This is the one deliberate exception to the "EC Administrator only" rule, because the page is useless unless every employee has it. It is the same grant as the other *My …* pages.
- The labels use the `Module — Tab` naming like Recruitment, so they sit together in the Roles screen.

---

## 6. Implementation phases

| Phase | Scope | Main files |
|---|---|---|
| **1. Foundation** | Migrations (tables + menus), models (`Letter`, `LetterRequest`, `LetterClassificationCode`, `LetterNumberSequence`), template registry, numbering service (`LetterNumberService`: counter table + `lockForUpdate`, numbers never reused), routes + hub tabs + sidebar redirect | `database/migrations/…_create_letters_tables.php`, `…_add_letters_menus.php`, `app/Models/Letters/*`, `app/Support/Letters/LetterTemplates.php`, `app/Services/Letters/*`, `routes/hr-general.php`, `partials/sidebar.blade.php` |
| **2. Settings** | Extend the current page: templates, numbering, classification codes, letterheads | `LetterTemplateController` → `LetterSettingController`, `letters/settings.blade.php` |
| **3. Create Letter** | Template mode (5 templates, id/en), custom mode, preview, PDF Blades sharing one base layout with letterhead | `LetterComposeController`, `letters/compose.blade.php`, `letters/pdf/*.blade.php` (one per letter, id + en inside), `lang/{id,en}/letters.php` (shared labels) |
| **4. Register** | Unified table, manual incoming/outgoing pages, final-scan upload, void; Offering Letter writes its row (Q5) and moves onto the shared counter (§3.4) | `LetterRegisterController`, `letters/register*.blade.php`, hook in `RecruitmentOfferController`, `Offer::nextSequence()` |
| **5. Requests + Dashboard** | ESS page (granted to User System Registered), Requests tab (process / reject / sign / complete & send / resend-if-failed), email delivery via Graph, notifications, KPIs, tab badge, employee-profile "Letters" list (Q7) | `MyLetterRequestController`, `LetterRequestController`, `LetterDashboardController`, `app/Services/Letters/LetterMailer.php`, views |
| *(later)* | HR-editable template bodies, bulk generation, e-signature | — |

Each phase can be reviewed and merged on its own. Phase 1+2 doesn't change what users see beyond the new tab bar.

---

## 7. Risks & open points

- **Number collisions:** handled by the counter row lock (§3.4). Preview never consumes a number.
- **Email delivery depends on Microsoft Graph `Mail.Send`.** If it fails, the request still completes and the file stays downloadable in the app, with a Resend action that only exists for failed emails.
- **Signatures depend on the master-data upload** being built separately. Until an employee's signature is uploaded there, letters they sign can't be signed or sent. The Sign button says so. The upload must store files on `EmployeeHrProfile::FILE_DISK` (`local`).
- **Old route names** `general.letter-templates.*` are used in `sidebar.blade.php` and `offering/settings.blade.php`. These are renamed in Phase 1, and the old URL redirects.
- **Custom letter body formatting:** a plain textarea is the safest for DomPDF. A rich-text editor (the repo already has one on a few pages) needs HTML sanitising (`MessageHtmlSanitizerService` exists). I'd start with the textarea.
- **Storage:** generated PDFs and scans go to the private `local` disk under `letters/{year}/{id}/`, served through the controller after the permission check, like letterhead images.
- **Offering Letter history:** register rows for already-numbered offers can be backfilled once in the migration if you want them in the register (Q5).

---

## 8. As built (2026-10-05)

All five phases are in. The differences from the plan above:

| Plan | As built | Why |
|---|---|---|
| Dashboard slug `general.letters` | **`general.letters.dashboard`** | `general.letters` already exists as the *group* row in Menu Access, and a group is given to every role that can open any of its tabs. Using it would show the Dashboard to everyone with any tab. |
| `letter_requests.email_status` | **`letters.email_status` / `email_error` / `sent_at`** | The email belongs to the letter. The same rule (Resend only after a failure) then covers request letters and letters sent from Create Letter. |
| `letters.generated_path` (stored PDF) | **PDF rendered on demand** from what was saved with the letter | `fields` holds the employee/company snapshot, and the letterhead is fixed when the letter is generated, so a reprint is identical. There's no second file to keep in step with the signature. |
| `signer_*` columns | **`signatory_*`, as on offering letters** | One trait (`App\Models\Concerns\SignsWithMasterSignature`) signs both kinds of letter. |
| Outgoing format default `ESH/…` | **`EC/{month}/{code}/{lang}/{day}{seq}/{year}`** | Matches this app's existing offer prefix. Editable in Settings. |
| Employee-profile "Letters" list (Q7) | **"Letters About Me" in My Letter Requests**, plus an employee filter on the Register (`?employee=`) | Master Employee is being built by someone else. This gives the employee their letters without touching that page. |
| `LetterTemplateController` → `LetterSettingController` | Name kept | It already *is* the Settings tab, and renaming would only churn. The view moved to `hr-general/letters/settings.blade.php`. |
| Offering letters move onto the counter | Done. **Offers keep their own format** (Offering Settings) but take `{seq}` from the shared outgoing counter. | No change for Recruitment users. One running number across all outgoing letters. |

**Files**

| Layer | Files |
|---|---|
| Migrations | `2026_10_05_000002_create_letters_tables.php` (tables + offer backfill + counter seed), `2026_10_05_000003_add_letters_menus.php` (slugs + grants) |
| Models | `app/Models/Letters/{Letter, LetterRequest, LetterCode, LetterSetting}.php`, `app/Models/Concerns/SignsWithMasterSignature.php`, extended `LetterTypeSetting`, `Letterhead::letterTypes()` |
| Services | `app/Services/Letters/{LetterNumberService, LetterService, LetterMailer}.php` |
| Templates | `app/Support/Letters/LetterTemplates.php`, `resources/views/hr-general/letters/pdf/*`, `lang/{id,en}/letters.php` |
| Controllers | `LetterDashboardController`, `LetterRequestController`, `LetterRegisterController`, `LetterComposeController`, `LetterTemplateController` (Settings), `MyLetterRequestController` |
| Views | `resources/views/hr-general/letters/*` (+ `components/tabs`, `components/modals`) |
| Navigation | `partials/sidebar.blade.php` (hub entry + ESS item), `EssSettingsController::ESS_ITEMS['my_letter_requests']` |

**Verified** with an end-to-end run through real HTTP requests (all middleware), rolled back afterwards, with Graph faked: **131/131 checks passed**. They covered:
- Every page and the permission refusals.
- The request → generate → sign → complete & send → download flow.
- All 5 templates in both languages.
- Custom letters (edit, renumber IN↔EN, signature removed on change).
- Email failure → Resend, and no resend after delivery.
- Void (number never reused), Register logging, scans, offer numbering on the shared counter, Settings, reject.

**Follow-up (2026-10-05, same day)**
- **What employees can request is a list kept in Settings → Employee Request Options** (`letter_request_types`), not the templates' "requestable" toggle.
  - Each option is linked to a template (Process opens it pre-filled) or to none (Process opens a custom letter titled with the option).
  - **"Other"** lets an employee type a letter that isn't on the list, answered with a custom letter. It can be switched off in Settings.
  - A request keeps the name it was asked under (`request_type_name`).
- **Signatory on Create Letter** defaults to the logged-in user and can only be an HR / admin member: an active employee whose role can open Create Letter or Requests (`LetterService::signatoryOptions()`), enforced on save. The per-template default signatory was removed from Settings.
