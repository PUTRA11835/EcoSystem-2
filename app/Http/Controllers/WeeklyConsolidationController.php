<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Exports\WeeklyConsolidationExport;
use App\Models\Employee;
use App\Models\Module;
use App\Models\Ticket;
use App\Models\WeeklyConsolidation;
use App\Models\WeeklyConsolidationTicket;
use App\Support\SessionUser;
use App\Support\TicketTeamAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Weekly Consolidation (Reporting → Support): recon tiket mingguan per modul.
 *
 * Dipisah dari ReportingController (yang sudah ~3.500 baris) karena fitur ini
 * punya permukaan endpoint sendiri (generate/refresh/notes/export) plus logika
 * otorisasi per-modul yang tidak dipakai controller reporting lain.
 *
 * Akses murni data-driven seperti TicketTeamAccess: module lead (module_leads)
 * hanya boleh kelola modul yang dia pimpin; role privileged (Admin/HOS/
 * Helpdesk/RPMO) boleh kelola semua modul. Menu slug reporting.weekly-
 * consolidation sendiri dibagikan ke semua role terkait (lihat migrasi
 * menu) — pembatasan sebenarnya terjadi di sini, bukan di menu permission.
 */
class WeeklyConsolidationController extends Controller
{
    private const MENU_SLUG = 'reporting.weekly-consolidation';

    private function authorize(): ?Employee
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return null;
        }

        $employee = Employee::find($sessionUser->id);
        if (!$employee || !$employee->canAccessMenu(self::MENU_SLUG)) {
            return null;
        }

        return $employee;
    }

    /** null = tidak dibatasi (role privileged, boleh semua modul). */
    private function accessibleModuleIds(Employee $employee): ?array
    {
        if ($employee->hasAnyRole(RoleId::TICKET_MANAGER_GROUP)) {
            return null;
        }

        return TicketTeamAccess::ledModuleIds($employee->employee_id);
    }

    private function assertModuleAccess(Employee $employee, int $moduleId): void
    {
        $allowed = $this->accessibleModuleIds($employee);
        if ($allowed !== null && !in_array($moduleId, $allowed, true)) {
            abort(403, 'You are not a module lead for this module.');
        }
    }

    /**
     * Kombinasi >1 modul ("all" atau module_ids lebih dari satu) hanya untuk
     * role privileged (Admin/HOS/Helpdesk/RPMO) — module lead biasa tetap satu
     * modul per batch meski dia kebetulan memimpin lebih dari satu modul,
     * sesuai spek awal fitur ini (satu lead, satu modul, satu laporan).
     */
    private function canCombineModules(Employee $employee): bool
    {
        return $this->accessibleModuleIds($employee) === null;
    }

    /**
     * Semua modul yang tercakup dalam satu batch — dipakai untuk otorisasi
     * (assertBatchAccess) dan query live-join (syncMatchingTickets). Fallback
     * ke module_id tunggal untuk jaga-jaga kalau pivot-nya kosong.
     *
     * @return array<int,int>
     */
    private function batchModuleIds(WeeklyConsolidation $consolidation): array
    {
        $ids = $consolidation->modules()->pluck('modules.id')->map(fn ($id) => (int) $id)->all();

        return !empty($ids) ? $ids : [(int) $consolidation->module_id];
    }

    /**
     * Akses ke SATU batch yang sudah tersimpan (show/refresh/notes/export):
     * employee harus punya akses ke SEMUA modul yang tercakup di batch itu,
     * bukan cuma salah satu — batch gabungan ABAP+BASIS TIDAK boleh dibuka
     * oleh module lead yang cuma memimpin salah satunya, karena dia akan ikut
     * melihat baris tiket modul yang bukan haknya.
     */
    private function assertBatchAccess(Employee $employee, WeeklyConsolidation $consolidation): void
    {
        $allowed = $this->accessibleModuleIds($employee);
        if ($allowed === null) {
            return;
        }

        $missing = array_diff($this->batchModuleIds($consolidation), $allowed);
        if (!empty($missing)) {
            abort(403, 'You do not have access to all modules in this recon.');
        }
    }

    /**
     * Resolve & validasi set module_id dari request — dipakai bareng oleh
     * preview() dan generate(). Terima `all=1` ATAU `module_ids[]`. Memilih
     * lebih dari satu modul (atau "all") wajib lolos canCombineModules();
     * setiap id tetap dicek satu-satu lewat assertModuleAccess() apapun
     * rolenya — jangan pernah percaya array dari client sebagai sudah valid.
     *
     * @return array<int,int>
     */
    private function resolveModuleIds(Request $request, Employee $employee): array
    {
        if ($request->boolean('all')) {
            if (!$this->canCombineModules($employee)) {
                abort(403, 'You are not allowed to combine modules.');
            }

            $allowed = $this->accessibleModuleIds($employee);
            $query = Module::query()->active();
            if ($allowed !== null) {
                $query->whereIn('id', $allowed);
            }

            return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $validated = $request->validate([
            'module_ids'   => ['required', 'array', 'min:1'],
            'module_ids.*' => ['integer', 'exists:modules,id'],
        ]);

        $moduleIds = array_values(array_unique(array_map('intval', $validated['module_ids'])));

        if (count($moduleIds) > 1 && !$this->canCombineModules($employee)) {
            abort(403, 'You are not allowed to combine modules.');
        }

        foreach ($moduleIds as $moduleId) {
            $this->assertModuleAccess($employee, $moduleId);
        }

        return $moduleIds;
    }

    // ── Web: page shell ──────────────────────────────────────────────────────

    public function index()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        return view('reporting.weekly-consolidation', ['user' => session('user')]);
    }

    // ── API: modules this employee may generate for ─────────────────────────

    public function modules()
    {
        $employee = $this->authorize();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $allowed = $this->accessibleModuleIds($employee);

        $query = Module::query()->active()->orderBy('name');
        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return response()->json([
            'success'      => true,
            'can_combine'  => $this->canCombineModules($employee),
            'data'         => $query->get(['id', 'name']),
        ]);
    }

    // ── API: preview — live-query matching tickets for a module, nothing saved ──
    // Dipakai layar "View" sebelum user memutuskan Generate. Tidak menyentuh
    // database sama sekali (read-only), jadi aman dipanggil berkali-kali saat
    // user ganti-ganti pilihan modul.

    public function preview(Request $request)
    {
        $employee = $this->authorize();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $moduleIds = $this->resolveModuleIds($request, $employee);

        $tickets = $this->liveTicketRowsQuery($moduleIds)
            ->with(['ticketLead.basicData', 'members.basicData', 'moduleMaster'])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'module_ids' => $moduleIds,
                'rows'       => $tickets->map(fn (Ticket $t) => $this->shapeTicketRow($t))->values(),
            ],
        ]);
    }

    // ── API: history of past batches ────────────────────────────────────────

    public function history(Request $request)
    {
        $employee = $this->authorize();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $allowed = $this->accessibleModuleIds($employee);

        $query = WeeklyConsolidation::with(['module:id,name', 'modules:id,name', 'generatedBy.basicData', 'lastRefreshedBy.basicData'])
            ->withCount('lines')
            ->orderByDesc('created_at');

        if ($allowed !== null) {
            $query->whereHas('modules', fn ($q) => $q->whereIn('modules.id', $allowed));
        }
        if ($moduleId = $request->query('module_id')) {
            $query->whereHas('modules', fn ($q) => $q->where('modules.id', (int) $moduleId));
        }

        $batches = $query->get()->map(fn (WeeklyConsolidation $b) => $this->summarize($b));

        return response()->json(['success' => true, 'data' => $batches]);
    }

    // ── API: generate a new batch ────────────────────────────────────────────

    public function generate(Request $request)
    {
        $employee = $this->authorize();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'period_label' => ['nullable', 'string', 'max:255'],
        ]);

        $isAllModules = $request->boolean('all');
        $moduleIds    = $this->resolveModuleIds($request, $employee);

        // Modul utama/representatif = yang pertama secara alfabet, dipakai untuk
        // kolom module_id (backward-compat) — daftar lengkapnya ada di pivot modules().
        $sortedModules   = Module::whereIn('id', $moduleIds)->orderBy('name')->get(['id', 'name']);
        $primaryModuleId = $sortedModules->first()->id ?? $moduleIds[0];

        $consolidation = WeeklyConsolidation::create([
            'module_id'        => $primaryModuleId,
            'is_all_modules'   => $isAllModules,
            'period_start'     => $validated['period_start'],
            'period_end'       => $validated['period_end'],
            'period_label'     => $validated['period_label']
                ?: (\Carbon\Carbon::parse($validated['period_start'])->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($validated['period_end'])->format('d/m/Y')),
            'generated_by_id'  => $employee->employee_id,
        ]);

        $consolidation->modules()->sync($moduleIds);

        $this->syncMatchingTickets($consolidation, $employee);

        return response()->json(['success' => true, 'data' => $this->show($consolidation->id)->getData(true)['data']]);
    }

    // ── API: one batch + live-joined ticket rows ─────────────────────────────

    public function show($id)
    {
        $employee = $this->authorize();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $consolidation = WeeklyConsolidation::with(['module:id,name', 'modules:id,name', 'generatedBy.basicData', 'lastRefreshedBy.basicData'])->findOrFail($id);
        $this->assertBatchAccess($employee, $consolidation);

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->summarize($consolidation), [
                'rows' => $this->shapeRows($consolidation),
            ]),
        ]);
    }

    // ── API: refresh — insert newly-matching tickets, keep existing notes ──

    public function refresh(Request $request, $id)
    {
        $employee = $this->authorize();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $consolidation = WeeklyConsolidation::findOrFail($id);
        $this->assertBatchAccess($employee, $consolidation);

        $this->syncMatchingTickets($consolidation, $employee);

        $consolidation->update([
            'last_refreshed_at'     => now(),
            'last_refreshed_by_id'  => $employee->employee_id,
        ]);

        return $this->show($id);
    }

    // ── API: update one line's notes ─────────────────────────────────────────

    public function updateNote(Request $request, $id, $ticketId)
    {
        $employee = $this->authorize();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $consolidation = WeeklyConsolidation::findOrFail($id);
        $this->assertBatchAccess($employee, $consolidation);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $line = $consolidation->lines()->where('ticket_id', $ticketId)->firstOrFail();
        $line->update([
            'notes'               => $validated['notes'] ?? null,
            'notes_updated_by_id' => $employee->employee_id,
            'notes_updated_at'    => now(),
        ]);

        return response()->json(['success' => true]);
    }

    // ── Web: export current batch to Excel ───────────────────────────────────

    public function export($id)
    {
        $employee = $this->authorize();
        if (!$employee) {
            abort(403);
        }

        $consolidation = WeeklyConsolidation::with(['module:id,name', 'modules:id,name'])->findOrFail($id);
        $this->assertBatchAccess($employee, $consolidation);

        $rows = $this->shapeRows($consolidation);

        $generatedBy = trim(($employee->basicData->first_name ?? '') . ' ' . ($employee->basicData->last_name ?? '')) ?: ($employee->eci ?? 'System');

        $moduleNamesSorted = $consolidation->modules->pluck('name')->sort()->values();
        $bannerModuleLabel = $consolidation->is_all_modules
            ? 'ALL MODULES'
            : ($moduleNamesSorted->implode(', ') ?: ($consolidation->module->name ?? 'Module'));

        $meta = [
            'module_name'   => $bannerModuleLabel,
            'period_label'  => $consolidation->period_label,
            'generated_by'  => $generatedBy,
            'generated_at'  => now()->timezone('Asia/Jakarta'),
        ];

        // Format sengaja ddMMyy ("dmy"), BUKAN mengikuti konvensi export lain di
        // aplikasi ini (dmY/dmY_Hi) — sesuai permintaan eksplisit: Logistic_230926.
        // Gabungan modul diurutkan alfabet supaya nama file deterministik terlepas
        // urutan klik (ABAP_BASIS, bukan tergantung mana yang dicentang duluan).
        $filenameBase = $consolidation->is_all_modules
            ? 'ALL'
            : ($moduleNamesSorted->isNotEmpty()
                ? $moduleNamesSorted->map(fn ($n) => str_replace(' ', '', $n))->implode('_')
                : 'Module');
        $filename = "{$filenameBase}_" . now()->timezone('Asia/Jakarta')->format('dmy') . '.xlsx';

        return Excel::download(new WeeklyConsolidationExport($rows, $meta), $filename);
    }

    // ── internal helpers ──────────────────────────────────────────────────────

    private function liveTicketRowsQuery(array $moduleIds)
    {
        return Ticket::whereNull('deleted_at')
            ->whereNull('is_hidden')
            ->whereIn('module_id', $moduleIds)
            ->whereIn('status', WeeklyConsolidation::OPEN_STATUSES);
    }

    private function syncMatchingTickets(WeeklyConsolidation $consolidation, Employee $employee): void
    {
        $candidateIds = $this->liveTicketRowsQuery($this->batchModuleIds($consolidation))->pluck('ticket_id');
        $existingIds  = $consolidation->lines()->pluck('ticket_id');
        $newIds       = $candidateIds->diff($existingIds);

        foreach ($newIds as $ticketId) {
            WeeklyConsolidationTicket::create([
                'weekly_consolidation_id' => $consolidation->id,
                'ticket_id'               => $ticketId,
            ]);
        }
    }

    private function summarize(WeeklyConsolidation $c): array
    {
        $modules     = $c->relationLoaded('modules') ? $c->modules : collect();
        $moduleNames = $modules->pluck('name')->sort()->values();

        return [
            'id'                 => $c->id,
            'module_id'          => $c->module_id,
            'module_ids'         => $modules->isNotEmpty() ? $modules->pluck('id')->map(fn ($id) => (int) $id)->all() : [(int) $c->module_id],
            'module_name'        => $c->module->name ?? '—',
            'module_names'       => $moduleNames->isNotEmpty() ? $moduleNames->implode(', ') : ($c->module->name ?? '—'),
            'is_all_modules'     => (bool) $c->is_all_modules,
            'period_start'       => optional($c->period_start)->format('Y-m-d'),
            'period_end'         => optional($c->period_end)->format('Y-m-d'),
            'period_label'       => $c->period_label,
            'generated_by'       => $this->fullName($c->generatedBy),
            'generated_at'       => $c->created_at,
            'last_refreshed_at'  => $c->last_refreshed_at,
            'last_refreshed_by'  => $this->fullName($c->lastRefreshedBy),
            'ticket_count'       => $c->lines_count ?? $c->lines()->count(),
        ];
    }

    private function shapeRows(WeeklyConsolidation $consolidation): \Illuminate\Support\Collection
    {
        return $consolidation->lines()
            ->with(['ticket' => function ($q) {
                $q->with(['ticketLead.basicData', 'members.basicData', 'moduleMaster']);
            }])
            ->get()
            ->map(fn (WeeklyConsolidationTicket $line) => $this->shapeTicketRow($line->ticket, $line->id, $line->notes))
            ->values();
    }

    /**
     * Satu baris tampilan tiket — dipakai bareng oleh preview() (belum ada
     * batch/notes) dan shapeRows() (sudah ada batch tersimpan). $lineId/$notes
     * null berarti ini masih preview (belum di-generate).
     */
    private function shapeTicketRow(Ticket $t, ?int $lineId = null, ?string $notes = null): array
    {
        $teamLead = $this->fullName($t->ticketLead);
        // Buang member yang employee_id-nya sama dengan Team Lead — orang yang
        // rangkap jadi lead SEKALIGUS member tidak boleh muncul dua kali di
        // kolom gabungan (dedupe by id, bukan by nama, supaya nama kembar beda
        // orang tidak ikut kebuang).
        $members = $t->members
            ->reject(fn ($m) => $t->ticket_lead_id && (int) $m->employee_id === (int) $t->ticket_lead_id)
            ->map(fn ($m) => $this->fullName($m))
            ->filter()
            ->values();

        // Lead & Member digabung satu kolom (Lead dulu, baru Member) — format
        // tabel yang diminta, dipakai bareng oleh tabel web dan Excel export.
        $leadMember = collect([$teamLead])->filter()->merge($members)->implode(', ');

        return [
            'line_id'             => $lineId,
            'ticket_id'           => $t->ticket_id,
            'ticket_number'       => $t->ticket_number ?? '—',
            'module_id'           => $t->module_id,
            'module_name'         => $t->module_name,
            'description'         => $t->description,
            'start_date'          => $t->created_at,
            'ticket_type'         => $t->ticket_type,
            'status'              => $t->status,
            'status_label'        => $t->status_label,
            'team_lead'           => $teamLead,
            'members'             => $members->implode(', '),
            'lead_member'         => $leadMember,
            'pic'                 => $t->pic,
            'progress_percentage' => $t->progress_percentage,
            'progress_note'       => $t->progress_note,
            'deliverable_status'  => $this->deliverableStatus($t),
            'notes'               => $notes,
        ];
    }

    /**
     * "OK"/"Not OK" dihitung LIVE dari data sistem — reuse sumber kebenaran
     * yang sama persis dengan checklist modal "Deliverable Documents" di
     * ticket/show.blade.php (App\Support\DeliverableDocumentRequirements):
     * semua doc_type MANDATORY untuk tipe tiket ini harus sudah pernah
     * diupload (ada baris TicketDeliverable dengan doc_type yang cocok).
     * null = tipe tiket ini tidak punya aturan dokumen (N/A, bukan "Not OK").
     */
    private function deliverableStatus(Ticket $t): ?string
    {
        $requirements = \App\Support\DeliverableDocumentRequirements::forTicket($t);
        if (!$requirements) {
            return null;
        }

        $mandatoryTypes = collect($requirements)
            ->where('mandatory', true)
            ->pluck('doc_type')
            ->map(fn ($type) => strtoupper(trim($type)));

        if ($mandatoryTypes->isEmpty()) {
            return 'ok';
        }

        $uploadedTypes = \App\Models\TicketDeliverable::where('ticket_id', $t->ticket_id)
            ->pluck('doc_type')
            ->map(fn ($type) => strtoupper(trim($type)));

        return $mandatoryTypes->every(fn ($type) => $uploadedTypes->contains($type)) ? 'ok' : 'not_ok';
    }

    private function fullName($employee): ?string
    {
        if (!$employee) {
            return null;
        }

        $name = trim(($employee->basicData->first_name ?? '') . ' ' . ($employee->basicData->last_name ?? ''));

        return $name !== '' ? $name : ($employee->eci ?? null);
    }
}
