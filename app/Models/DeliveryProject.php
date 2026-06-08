<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AuthUser;
use App\Models\DeliveryProjectCost;


class DeliveryProject extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'approval_date' => 'datetime',
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
                    ->withPivot('module', 'role', 'employee_type', 'vendor_name', 'start_date', 'end_date', 'notes')
                    ->withTimestamps();
    }

    public function deliveryOwner()
    {
        return $this->belongsTo(Employee::class, 'delivery_owner_id', 'employee_id');
    }

    public function deliveryManager()
    {
        return $this->belongsTo(Employee::class, 'delivery_manager_id', 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(AuthUser::class, 'created_by_id');
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
        // Ambil fase dengan flag Go-Live
        $goLivePhases = $this->phases()
            ->where('is_golive_phase', true)
            ->where('is_visible', true)
            ->get();
        
        if ($goLivePhases->isEmpty()) {
            Log::info("ℹ️ No Go-Live phase found");
            return;
        }
        
        // Ambil tanggal paling akhir dari semua aktivitas di fase Go-Live
        $latestDate = null;
        
        foreach ($goLivePhases as $phase) {
            $plannings = $this->plannings()
                ->where('phase_id', $phase->id)
                ->where('is_group', false)
                ->whereNotNull('end_date')
                ->get();
            
            foreach ($plannings as $planning) {
                if (!$latestDate || $planning->end_date > $latestDate) {
                    $latestDate = $planning->end_date;
                }
            }
        }
        
        if ($latestDate) {
            $this->go_live_estimated = $latestDate;
            Log::info("✅ Go-Live date updated", ['date' => $latestDate]);
        }
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
        
        if ($plannings->isEmpty()) {
            $this->category = 'Open';
            $this->status = 'Monitoring';
            return;
        }
        
        $allCompleted = $plannings->where('status', '!=', 'completed')->isEmpty();
        $hasInProgress = $plannings->whereIn('status', ['in_progress', 'delayed'])->isNotEmpty();
        $allNotStarted = $plannings->where('status', '!=', 'not_started')->isEmpty();
        
        if ($allCompleted) {
            $this->category = 'Closed';
            $this->status = 'On Track';
        } elseif ($hasInProgress) {
            $this->category = 'In Process';

            $hasDelayed = $plannings->where('status', 'delayed')->isNotEmpty();
            $this->status = $hasDelayed ? 'At Risk' : 'On Track';
        } else {
            $this->category = 'Open';
            $this->status = 'Monitoring';
        }
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
        $groups = $this->plannings->where('is_group', true);

        foreach ($groups as $group) {
            $group->loadMissing('stages');
        }
        
        $visiblePhases = $this->phases()
            ->where('is_visible', true)
            ->get();
        
        if ($visiblePhases->isEmpty()) {
            return 0;
        }
        
        $totalPhaseWeight = 0;
        $weightedPhaseProgress = 0;
        
        foreach ($visiblePhases as $phase) {
            $phaseWeight = $phase->weight ?? 0;
            $phaseGroups = $groups->where('phase_id', $phase->id);
            
            if ($phaseGroups->count() > 0) {
                $totalGroupWeight = 0;
                $weightedGroupProgress = 0;
                
                foreach ($phaseGroups as $group) {
                    $groupWeight = $group->calculated_weight ?? $group->weight ?? 0;
                    $groupProgress = $group->calculated_progress ?? $group->progress_percentage ?? 0;
                    
                    $totalGroupWeight += $groupWeight;
                    $weightedGroupProgress += ($groupProgress * $groupWeight);
                }
                
                $phaseProgress = $totalGroupWeight > 0 
                    ? ($weightedGroupProgress / $totalGroupWeight) 
                    : 0;
            } else {
                $phaseProgress = 0;
            }
            
            $totalPhaseWeight += $phaseWeight;
            $weightedPhaseProgress += ($phaseProgress * $phaseWeight);
        }
        
        return $totalPhaseWeight > 0 
            ? round($weightedPhaseProgress / $totalPhaseWeight, 1) 
            : 0;
    }


    protected static function booted()
    {
        static::updated(function ($project) {
            $project->updateStatusAutomatically();
        });
    }
}
