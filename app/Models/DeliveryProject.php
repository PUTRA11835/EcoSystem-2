<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AuthUser;
use App\Models\DeliveryProjectCost;
use App\Traits\Auditable;


class DeliveryProject extends Model
{
    use HasFactory, Auditable;
    use \App\Models\Concerns\HasOneDriveShareLink;

    protected static ?string $auditModule = 'Delivery Project';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'project_owner',
        'project_type',
        'high_level_risk',
        'io_number',
        'name',
        'description',
        'category',
        'status',
        'is_closed',
        'closed_at',
        'closed_by',
        'calculated_progress',
        'phase',
        'go_live_estimated',
        'contract_start_date',
        'contract_end_date',
        // Delivery Information
        'ae_type',
        'ae_name',
        'ae_phone',
        'ae_email',
        'delivery_owner_id',
        'delivery_manager_id',
        'project_manager_id',
        'co_pm_id',
        'project_admin_id',
        'revenue',
        'plan_cost',
        'gross_profit',
        'gross_profit_percentage',
        'delivery_method',
        'warranty_period',
        'total_mandays',
        'created_by_id',
        'approval_date',
        'approval_name',
        // Location Information
        'location_name',
        'location_type',
        'location_country',
        'location_geographical',
        'location_region',
        'location_city',
        'location_street',
        'location_valid_from',
        'location_valid_to',
        'onedrive_folder_id',
        'onedrive_folder_url',
        'onedrive_link_scope',
        'onedrive_link_expires_at',
        'onedrive_link_checked_at',
    ];

    protected $casts = [
        'onedrive_link_expires_at' => 'datetime',
        'onedrive_link_checked_at' => 'datetime',
        'approval_date' => 'datetime',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
        'location_valid_from' => 'date',
        'location_valid_to' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
    ];

    // Existing relationships
    // ECOSYSTEM Integration: Customer table with customer_id as PK
    public function client() {
        return $this->belongsTo(Customer::class, 'client_id', 'customer_id');
    }

    public function updates() {
        return $this->hasMany(DeliveryProjectUpdate::class, 'delivery_projects_id');
    }

    public function issues() {
        return $this->hasMany(DeliveryProjectIssue::class, 'delivery_projects_id')
                    ->orderBy('issue_number');
    }

    public function wricefs() {
        return $this->hasMany(DeliveryProjectWricef::class, 'delivery_projects_id')
                    ->orderBy('obj_id');
    }

    public function stakeholders() {
        return $this->hasMany(DeliveryProjectStakeholder::class, 'delivery_projects_id')
                    ->orderBy('seq');
    }

    public function activities() {
        return $this->hasMany(DeliveryProjectActivity::class, 'delivery_projects_id');
    }

    public function plannings() {
        return $this->hasMany(DeliveryProjectPlanning::class, 'delivery_projects_id');
    }

    public function documents() {
        return $this->hasMany(Document::class, 'delivery_projects_id');
    }

    public function phases(){
        return $this->hasMany(DeliveryProjectPhase::class, 'delivery_projects_id')
                    ->where('is_visible', true)
                    ->orderBy('order_sequence');
    }

    public function costs()
    {
        return $this->hasMany(DeliveryProjectCost::class, 'delivery_projects_id')
                    ->whereNull('parent_id')
                    ->with('children')
                    ->orderBy('order_sequence');
    }

    public function teamMembers()
    {
        return $this->belongsToMany(Employee::class, 'delivery_project_employee', 'delivery_projects_id', 'employee_id', 'id', 'employee_id')
                    ->withPivot('module', 'role', 'employee_type', 'vendor_id', 'vendor_name', 'member_name', 'member_position', 'start_date', 'end_date', 'notes')
                    ->withTimestamps();
    }

    public function deliveryOwner()
    {
        return $this->belongsTo(Employee::class, 'delivery_owner_id', 'employee_id');
    }

    /**
     * Apakah employee ini adalah Project Owner dari project ini?
     *
     * PENTING: kolom `project_owner` menyimpan NAMA (EmployeeBasicData::$full_name),
     * bukan foreign key — dropdown "Project Owner" di form Add/Edit Project mengirim
     * full_name sebagai value. Jadi pencocokan terpaksa dilakukan by nama
     * (trim + case-insensitive). Konsekuensinya: kalau ada dua employee dengan
     * full_name identik, keduanya dianggap owner.
     *
     * Catatan: `full_name` adalah ACCESSOR (first_name + last_name), bukan kolom —
     * jadi tidak bisa di-query lewat where/value(), harus dibaca dari model.
     */
    public function isOwnedByEmployee($employeeId): bool
    {
        $owner = trim((string) $this->project_owner);

        if ($owner === '' || empty($employeeId)) {
            return false;
        }

        $basic = EmployeeBasicData::where('employee_id', $employeeId)->first();

        if (!$basic) {
            return false;
        }

        return mb_strtolower(trim($basic->full_name)) === mb_strtolower($owner);
    }

    public function deliveryManager()
    {
        return $this->belongsTo(Employee::class, 'delivery_manager_id', 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(AuthUser::class, 'created_by_id');
    }

    /**
     * Employee yang meng-close project ini (null saat project masih terbuka).
     */
    public function closedBy()
    {
        return $this->belongsTo(Employee::class, 'closed_by', 'employee_id');
    }

    public function updateFromPlanning()
    {
        Log::info("🔄 Updating project from planning", ['delivery_projects_id' => $this->id]);
        
        try {
            $this->seedLocationDefaultsFromPlanning();

            $this->updateGoLiveDate();

            $this->updateCurrentPhase();

            $this->updateDeliveryProjectCategory();

            $this->saveQuietly();

            Log::info("✅ Project updated successfully", [
                'delivery_projects_id' => $this->id,
                'go_live' => $this->go_live_estimated,
                'phase' => $this->phase,
                'category' => $this->category
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Error updating project from planning", [
                'delivery_projects_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Seed the location validity window from the planning span the first time only
     * (does not overwrite values already set). The project's own date columns were
     * removed in favour of the manually-entered contract window.
     */
    private function seedLocationDefaultsFromPlanning()
    {
        if ($this->location_valid_from && $this->location_valid_to) {
            return;
        }

        $allDates = collect();

        $plannings = $this->plannings()
            ->where('is_group', false)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get();

        foreach ($plannings as $planning) {
            if ($planning->start_date) {
                $allDates->push($planning->start_date);
            }
            if ($planning->end_date) {
                $allDates->push($planning->end_date);
            }
        }

        if ($allDates->isNotEmpty()) {
            if (!$this->location_valid_from) {
                $this->location_valid_from = $allDates->min();
            }
            if (!$this->location_valid_to) {
                $this->location_valid_to = $allDates->max();
            }
        }
    }

    private function updateGoLiveDate()
    {
        // Go-Live kini ditandai di level ACTIVITY (planning leaf), bukan fase.
        // Go Live Estimated = Planned Start Date dari activity yang ditandai go-live.
        // Hanya satu activity per project yang boleh go-live (dijaga di controller).
        $goLiveLeaf = $this->plannings()
            ->where('is_golive', true)
            ->where('is_group', false)
            ->with('activity')
            ->first();

        if (!$goLiveLeaf) {
            // Tidak ada activity go-live (atau tanda dilepas) → kosongkan estimasi.
            $this->go_live_estimated = null;
            Log::info("ℹ️ No Go-Live activity found; go_live_estimated cleared");
            return;
        }

        // Tanggal otoritatif ada di tabel activities (planning leaf bisa lag karena
        // jalur update type=activity tidak men-sync tanggal). Fallback ke planning.
        $startDate = $goLiveLeaf->activity?->start_date ?: $goLiveLeaf->start_date;

        $this->go_live_estimated = $startDate;
        Log::info("✅ Go-Live date updated from activity", [
            'planning_id' => $goLiveLeaf->id,
            'date'        => $startDate,
        ]);
    }
    
    private function updateCurrentPhase()
    {
        // Get all visible phases for this project
        $phases = $this->phases()
            ->where('is_visible', true)
            ->orderBy('order_sequence')
            ->get();

        // If no phases configured, set phase to null
        if ($phases->isEmpty()) {
            $this->phase = null;
            Log::info("ℹ️ No phases configured for project", ['delivery_projects_id' => $this->id]);
            return;
        }

        // Check for phases with in_progress activities (currently active)
        $activePhases = $phases->filter(function($phase) {
            return $this->plannings()
                ->where('phase_id', $phase->id)
                ->where('is_group', false)
                ->where('status', 'in_progress')
                ->exists();
        });

        if ($activePhases->isNotEmpty()) {
            // Get the phase with highest order_sequence that is active
            $currentPhase = $activePhases->sortByDesc('order_sequence')->first();
            $this->phase = $currentPhase->name;
            Log::info("✅ Current phase (in_progress)", ['phase' => $currentPhase->name]);
            return;
        }

        // Find phase that has started but not fully completed
        foreach ($phases as $phase) {
            $plannings = $this->plannings()
                ->where('phase_id', $phase->id)
                ->where('is_group', false)
                ->get();

            if ($plannings->isEmpty()) {
                continue;
            }

            $allCompleted = $plannings->where('status', '!=', 'completed')->isEmpty();
            $hasStarted = $plannings->where('status', '!=', 'not_started')->isNotEmpty();

            // If phase has started but not all completed, this is the current phase
            if ($hasStarted && !$allCompleted) {
                $this->phase = $phase->name;
                Log::info("✅ Current phase (partially completed)", ['phase' => $phase->name]);
                return;
            }
        }

        // Find the first phase that is not yet completed
        foreach ($phases as $phase) {
            $plannings = $this->plannings()
                ->where('phase_id', $phase->id)
                ->where('is_group', false)
                ->get();

            if ($plannings->isEmpty()) {
                // Phase exists but no planning items yet
                $this->phase = $phase->name;
                Log::info("✅ Current phase (no planning)", ['phase' => $phase->name]);
                return;
            }

            $allCompleted = $plannings->where('status', '!=', 'completed')->isEmpty();

            if (!$allCompleted) {
                $this->phase = $phase->name;
                Log::info("✅ Current phase (not completed)", ['phase' => $phase->name]);
                return;
            }
        }

        // All phases completed, show the last phase
        $lastPhase = $phases->last();
        if ($lastPhase) {
            $this->phase = $lastPhase->name;
            Log::info("✅ Current phase (all completed)", ['phase' => $lastPhase->name]);
        }
    }

    /**
     * ✅ Update Category berdasarkan progress planning
     */
    private function updateDeliveryProjectCategory()
    {
        $plannings = $this->plannings()
            ->where('is_group', false)
            ->get();

        // Manual close overrides the auto-derived category: once a project is
        // explicitly closed it stays Closed regardless of planning progress,
        // until it is reopened.
        if ($this->is_closed) {
            $this->category = 'Closed';
        } elseif ($plannings->isEmpty()) {
            $this->category = 'Open';
        } else {
            $allCompleted = $plannings->where('status', '!=', 'completed')->isEmpty();
            $hasInProgress = $plannings->whereIn('status', ['in_progress', 'delayed'])->isNotEmpty();

            if ($allCompleted) {
                $this->category = 'Closed';
            } elseif ($hasInProgress) {
                $this->category = 'In Process';
            } else {
                $this->category = 'Open';
            }
        }

        // Status now tracks the Schedule Performance Index (SPI) band so the
        // project-list badge matches the SPI card on the detail page. When there
        // is no schedule baseline yet (SPI null) there is no variance to report,
        // so fall back to On Track.
        $this->status = $this->spiStatusLabel() ?? 'On Track';
    }

    /**
     * Schedule Performance Index (SPI) = actual overall progress / planned
     * overall progress. Mirrors the calculation in progress-overview.blade.php
     * so the list badge and the detail-page SPI card stay in sync.
     * Returns null when there is no schedule baseline (planned progress == 0).
     */
    public function calculateSpi(): ?float
    {
        ['actual' => $actual, 'planned' => $planned] = $this->weightedProgressRaw();

        if ($actual === null || $planned === null || $planned <= 0) {
            return null;
        }

        return round($actual / $planned, 2);
    }

    /**
     * Bobot-tertimbang mentah (belum dibulatkan) untuk progres AKTUAL & RENCANA:
     * Σ(bobot phase × progres phase) ÷ Σbobot phase, dengan progres phase sendiri
     * = Σ(bobot group × progres group) ÷ Σbobot group.
     *
     * Satu-satunya sumber angka Overall Progress / Planned Progress / SPI —
     * calculateOverallProgress(), calculatePlannedProgress() dan calculateSpi()
     * semuanya membacanya dari sini, supaya list, halaman detail dan export
     * tidak pernah menampilkan tiga angka berbeda.
     *
     * @return array{actual: float|null, planned: float|null} null = belum ada
     *         baseline (tidak ada phase visible, atau total bobotnya 0).
     */
    private function weightedProgressRaw(): array
    {
        $empty = ['actual' => null, 'planned' => null];

        $groups = $this->plannings->where('is_group', true);

        foreach ($groups as $group) {
            $group->loadMissing('stages');
        }

        $visiblePhases = $this->phases()
            ->where('is_visible', true)
            ->get();

        if ($visiblePhases->isEmpty()) {
            return $empty;
        }

        $totalPhaseWeight      = 0;
        $weightedPhaseProgress = 0;
        $weightedPhasePlanned  = 0;

        foreach ($visiblePhases as $phase) {
            $phaseWeight = $phase->weight ?? 0;
            $phaseGroups = $groups->where('phase_id', $phase->id);

            $phaseProgress = 0;
            $phasePlanned  = 0;

            if ($phaseGroups->count() > 0) {
                $totalGroupWeight      = 0;
                $weightedGroupProgress = 0;
                $weightedGroupPlanned  = 0;

                foreach ($phaseGroups as $group) {
                    $groupWeight   = $group->calculated_weight ?? $group->weight ?? 0;
                    $groupProgress = $group->calculated_progress ?? $group->progress_percentage ?? 0;
                    $groupPlanned  = $group->planned_progress ?? 0;

                    $totalGroupWeight      += $groupWeight;
                    $weightedGroupProgress += ($groupProgress * $groupWeight);
                    $weightedGroupPlanned  += ($groupPlanned * $groupWeight);
                }

                if ($totalGroupWeight > 0) {
                    $phaseProgress = $weightedGroupProgress / $totalGroupWeight;
                    $phasePlanned  = $weightedGroupPlanned / $totalGroupWeight;
                } else {
                    // Bobot group belum diisi sama sekali — bagi rata, jangan
                    // diperlakukan 0%. Bobot phase-nya tetap menekan penyebut
                    // overall, jadi menol-kan progresnya menarik turun angka
                    // proyek tanpa sebab. Aturan yang sama dipakai
                    // progress-overview.blade.php.
                    $phaseProgress = (float) ($phaseGroups->avg(fn ($g) => (float) ($g->calculated_progress ?? $g->progress_percentage ?? 0)) ?? 0);
                    $phasePlanned  = (float) ($phaseGroups->avg(fn ($g) => (float) ($g->planned_progress ?? 0)) ?? 0);
                }
            }

            $totalPhaseWeight      += $phaseWeight;
            $weightedPhaseProgress += ($phaseProgress * $phaseWeight);
            $weightedPhasePlanned  += ($phasePlanned * $phaseWeight);
        }

        if ($totalPhaseWeight <= 0) {
            return $empty;
        }

        return [
            'actual'  => $weightedPhaseProgress / $totalPhaseWeight,
            'planned' => $weightedPhasePlanned / $totalPhaseWeight,
        ];
    }

    /**
     * Map the SPI value to its status band label. Shared band with the SPI card:
     * >=0.95 On Track, 0.80-0.949 At Risk, <0.80 At Critical. Returns null when
     * there is no baseline yet so the caller can decide on a fallback.
     */
    public function spiStatusLabel(): ?string
    {
        $spi = $this->calculateSpi();

        if ($spi === null) {
            return null;
        }
        if ($spi >= 0.95) {
            return 'On Track';
        }
        if ($spi >= 0.80) {
            return 'At Risk';
        }
        return 'At Critical';
    }

    public function getOverallProgressAttribute()
    {
        $phases = $this->phases;

        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($phases as $phase) {
            $phaseProgress = $phase->calculateProgress($this->id); // ✅ FIX
            $phaseWeight = $phase->weight ?? 0;

            $weightedSum += ($phaseProgress * $phaseWeight);
            $totalWeight += $phaseWeight;
        }

        if ($totalWeight == 0) {
            return 0;
        }

        return round($weightedSum / $totalWeight, 1);
    }

    public function updateStatusAutomatically()
    {
        $plannings = $this->plannings;

        if ($plannings->isEmpty()) {
            return;
        }

        $this->updateFromPlanning();
    }

    public function calculateOverallProgress()
    {
        return round($this->weightedProgressRaw()['actual'] ?? 0, 1);
    }

    /**
     * Progres RENCANA keseluruhan — pasangan dari calculateOverallProgress().
     * Dipakai export/laporan untuk menampilkan deviasi rencana vs aktual di
     * samping SPI (SPI = aktual ÷ rencana).
     */
    public function calculatePlannedProgress()
    {
        return round($this->weightedProgressRaw()['planned'] ?? 0, 1);
    }

    /**
     * Aktual, rencana, deviasi dan SPI dalam SEKALI hitung. Dipakai export &
     * laporan yang butuh keempatnya: memanggil calculateOverallProgress() +
     * calculatePlannedProgress() + calculateSpi() satu per satu akan menghitung
     * (dan meng-query) hal yang persis sama tiga kali per project.
     *
     * @return array{actual: float, planned: float, deviation: float, spi: float|null}
     */
    public function progressSnapshot(): array
    {
        ['actual' => $actual, 'planned' => $planned] = $this->weightedProgressRaw();

        $actualRounded  = round($actual ?? 0, 1);
        $plannedRounded = round($planned ?? 0, 1);

        return [
            'actual'    => $actualRounded,
            'planned'   => $plannedRounded,
            // Deviasi dihitung dari angka yang DITAMPILKAN, supaya kolomnya
            // selalu sama dengan selisih dua kolom di sebelahnya.
            'deviation' => round($actualRounded - $plannedRounded, 2),
            'spi'       => ($actual !== null && $planned !== null && $planned > 0)
                ? round($actual / $planned, 2)
                : null,
        ];
    }


    protected static function booted()
    {
        static::updated(function ($project) {
            $project->updateStatusAutomatically();
        });
    }
}
