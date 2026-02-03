<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

// ECOSYSTEM Integration: Using Customer and Employee models
// Customer table: 'customer' with PK 'customer_id'
// Employee table: 'employee' with PK 'employee_id'

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'pic',
        'project_type',
        'name',
        'description',
        'category',
        'status',
        'calculated_progress',
        'phase',
        'start_date',
        'end_date',
        'go_live_estimated',
        // Delivery Information
        'delivery_type',
        'delivery_subtype',
        'ae_type',
        'ae_name',
        'ae_phone',
        'ae_email',
        'delivery_owner_id',
        'delivery_manager_id',
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
    ];

    protected $casts = [
        'approval_date' => 'datetime',
        'location_valid_from' => 'date',
        'location_valid_to' => 'date',
    ];

    // Existing relationships
    // ECOSYSTEM Integration: Customer table with customer_id as PK
    public function client() {
        return $this->belongsTo(Customer::class, 'client_id', 'customer_id');
    }

    public function updates() {
        return $this->hasMany(ProjectUpdate::class);
    }

    public function activities() {
        return $this->hasMany(ProjectActivity::class);
    }

    public function plannings() {
        return $this->hasMany(ProjectPlanning::class);
    }

    public function documents() {
        return $this->hasMany(Document::class);
    }

    public function phases()
    {
        return $this->belongsToMany(ProjectPhase::class, 'project_project_phase')
            ->withPivot(['weight', 'order_sequence', 'is_visible', 'orientation', 'custom_settings'])
            ->withTimestamps()
            ->orderBy('project_phases.order_sequence');
    }

    // ECOSYSTEM Integration: Employee table with employee_id as PK
    public function teamMembers()
    {
        return $this->belongsToMany(Employee::class, 'project_employee', 'project_id', 'employee_id', 'id', 'employee_id')
                    ->withPivot('module', 'assignment', 'start_date', 'end_date')
                    ->withTimestamps();
    }

    // New relationships for delivery information
    // ECOSYSTEM Integration: Employee table with employee_id as PK
    public function deliveryOwner()
    {
        return $this->belongsTo(Employee::class, 'delivery_owner_id', 'employee_id');
    }

    // ECOSYSTEM Integration: Employee table with employee_id as PK
    public function deliveryManager()
    {
        return $this->belongsTo(Employee::class, 'delivery_manager_id', 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updateFromPlanning()
    {
        Log::info("🔄 Updating project from planning", ['project_id' => $this->id]);
        
        try {
            // 1. ✅ Update Start Date & End Date dari Planning
            $this->updateProjectDates();
            
            // 2. ✅ Update Go-Live Estimated dari fase Go-Live
            $this->updateGoLiveDate();
            
            // 3. ✅ Update Current Phase berdasarkan planning aktif
            $this->updateCurrentPhase();
            
            // 4. ✅ Update Category berdasarkan progress
            $this->updateProjectCategory();
            
            // Save without triggering events
            $this->saveQuietly();
            
            Log::info("✅ Project updated successfully", [
                'project_id' => $this->id,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'go_live' => $this->go_live_estimated,
                'phase' => $this->phase,
                'category' => $this->category
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Error updating project from planning", [
                'project_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function updateProjectDates()
    {
        $allDates = collect();
        
        // Ambil semua planning items yang visible
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
            $this->start_date = $allDates->min();
            $this->end_date = $allDates->max();
            
            // Update location valid dates jika belum diisi
            if (!$this->location_valid_from) {
                $this->location_valid_from = $this->start_date;
            }
            if (!$this->location_valid_to) {
                $this->location_valid_to = $this->end_date;
            }
        }
    }

    private function updateGoLiveDate()
    {
        // Ambil fase dengan flag Go-Live
        $goLivePhases = $this->phases()
            ->wherePivot('is_golive_phase', true)
            ->wherePivot('is_visible', true)
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
        $today = Carbon::today();
        
        // Cari fase yang sedang aktif (ada aktivitas in_progress)
        $activePhases = $this->phases()
            ->wherePivot('is_visible', true)
            ->get()
            ->filter(function($phase) {
                $hasActiveActivities = $this->plannings()
                    ->where('phase_id', $phase->id)
                    ->where('is_group', false)
                    ->where('status', 'in_progress')
                    ->exists();
                
                return $hasActiveActivities;
            });
        
        if ($activePhases->isNotEmpty()) {
            // Ambil fase dengan order_sequence tertinggi yang aktif
            $currentPhase = $activePhases->sortByDesc('order_sequence')->first();
            $this->phase = $currentPhase->name;
            
            Log::info("✅ Current phase updated", ['phase' => $currentPhase->name]);
            return;
        }
        
        // Jika tidak ada yang in_progress, cari berdasarkan tanggal
        $phases = $this->phases()
            ->wherePivot('is_visible', true)
            ->orderBy('order_sequence')
            ->get();
        
        foreach ($phases as $phase) {
            $plannings = $this->plannings()
                ->where('phase_id', $phase->id)
                ->where('is_group', false)
                ->get();
            
            $allCompleted = $plannings->where('status', '!=', 'completed')->isEmpty();
            $hasStarted = $plannings->where('status', '!=', 'not_started')->isNotEmpty();
            
            if ($hasStarted && !$allCompleted) {
                $this->phase = $phase->name;
                return;
            }
        }
        
        // Default ke fase pertama
        if ($phases->isNotEmpty()) {
            $this->phase = $phases->first()->name;
        }
    }

    /**
     * ✅ Update Category berdasarkan progress planning
     */
    private function updateProjectCategory()
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
            
            // Update status berdasarkan ada tidaknya delayed activities
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
            $phaseProgress = $phase->progress;
            $phaseWeight = $phase->pivot->weight;
            $weightedSum += ($phaseProgress * $phaseWeight);
            $totalWeight += $phaseWeight;
        }

        if ($totalWeight == 0) {
            return 0;
        }

        return $weightedSum / $totalWeight;
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
        
        // Load stages jika belum
        foreach ($groups as $group) {
            $group->loadMissing('stages');
        }
        
        $visiblePhases = $this->phases()
            ->withPivot(['weight', 'is_visible', 'orientation'])
            ->wherePivot('is_visible', true)
            ->get();
        
        if ($visiblePhases->isEmpty()) {
            return 0;
        }
        
        $totalPhaseWeight = 0;
        $weightedPhaseProgress = 0;
        
        foreach ($visiblePhases as $phase) {
            $phaseWeight = $phase->pivot->weight ?? 0;
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