<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySupport extends Model
{
    use HasFactory;

    protected $table = 'delivery_support';

    protected $fillable = [
        'id_delivery_list',
        'client_id',
        'start_date',
        'end_date',
        'resolution_estimated',
        'calculated_progress',
        'name',
        'type',
        'delivery_owner_id',
        'co_pm_id',
        'support_admin_id',
        'sales_id',
        'support_method',
        'total_mandays',
        'io_number',
        'revenue',
        'plan_cost',
        'gross_profit',
        'gross_profit_percentage',
        'created_by_id',
        'approval_date',
        'approval_name',
        'onedrive_folder_id',
        'onedrive_folder_url',
        'onedrive_deliverable_folder_id',
        'onedrive_deliverable_folder_url',
        'service_window_start',
        'service_window_end',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'resolution_estimated' => 'date',
        'approval_date' => 'date',
        'calculated_progress' => 'decimal:2',
        'revenue' => 'decimal:2',
        'plan_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'gross_profit_percentage' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function deliveryList()
    {
        return $this->belongsTo(DeliveryList::class, 'id_delivery_list');
    }

    public function client()
    {
        return $this->belongsTo(Customer::class, 'client_id', 'customer_id');
    }

    /**
     * Get tickets assigned to this delivery support through activities
     */
    public function tickets()
    {
        return Ticket::whereIn('ticket_id',
            $this->activities()->whereNotNull('ticket_id')->pluck('ticket_id')
        );
    }

    public function deliveryOwner()
    {
        return $this->belongsTo(Employee::class, 'delivery_owner_id', 'employee_id');
    }

    public function supportManagers()
    {
        return $this->belongsToMany(Employee::class, 'delivery_support_managers', 'delivery_support_id', 'employee_id', 'id', 'employee_id')
            ->withTimestamps();
    }

    public function coPm()
    {
        return $this->belongsTo(Employee::class, 'co_pm_id', 'employee_id');
    }

    public function supportAdmin()
    {
        return $this->belongsTo(Employee::class, 'support_admin_id', 'employee_id');
    }

    public function sales()
    {
        return $this->belongsTo(Employee::class, 'sales_id', 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by_id', 'employee_id');
    }

    public function phases()
    {
        return $this->hasMany(DeliverySupportPhase::class, 'delivery_support_id')
            ->orderBy('order_sequence');
    }

    public function visiblePhases()
    {
        return $this->phases()->where('is_visible', true);
    }

    public function viewConfiguration()
    {
        return $this->hasOne(DeliverySupportViewConfiguration::class, 'delivery_support_id');
    }

    /**
     * Term Of Payment (TOP) plan entries — mirror of DeliveryProject::paymentTerms.
     */
    public function paymentTerms()
    {
        return $this->hasMany(DeliverySupportPaymentTerm::class, 'delivery_support_id')
            ->orderBy('term_number');
    }

    /**
     * Plan Cost tree (top-level rows only) — mirror of DeliveryProject::costs.
     */
    public function costs()
    {
        return $this->hasMany(DeliverySupportCost::class, 'delivery_support_id')
            ->whereNull('parent_id')
            ->orderBy('order_sequence');
    }

    public function planning()
    {
        return $this->hasMany(DeliverySupportPlanning::class, 'delivery_support_id');
    }

    public function activities()
    {
        return $this->hasMany(DeliverySupportActivity::class, 'delivery_support_id');
    }

    public function documents()
    {
        return $this->hasMany(DeliverySupportDocument::class, 'delivery_support_id');
    }

    public function updates()
    {
        return $this->hasMany(DeliverySupportUpdate::class, 'delivery_support_id');
    }

    public function customerPics()
    {
        return $this->hasMany(DeliverySupportCustomerPic::class, 'delivery_support_id')
                    ->with('contact');
    }

    /**
     * Get team members from activities assigned to this delivery support
     */
    public function teamMembers()
    {
        return $this->activities()
            ->with('employees')
            ->get()
            ->pluck('employees')
            ->flatten()
            ->unique('employee_id');
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Customer deliverable folder name (per client), e.g. "125 DEMOGRP2".
     * Returns null when client / basic data is missing.
     * NOTE: kept identical to the historical format so existing folders still match.
     */
    public function customerDeliverableFolderName(): ?string
    {
        $this->loadMissing('client.basicData');

        if (!$this->client || !$this->client->basicData) {
            return null;
        }

        return str_pad((string) $this->client_id, 3, '0', STR_PAD_LEFT)
            . ' ' . strtoupper($this->client->basicData->name_1);
    }

    /**
     * Per-support folder name inside the customer folder, e.g. "ATS DEMOGRP2".
     * Uses the support name as-is (sanitized). Support names are kept unique per
     * client by convention, so no ID prefix is added.
     */
    public function supportDeliverableFolderName(): string
    {
        return \App\Services\OneDriveService::sanitizeSegment(
            $this->name ?? '',
            'Support-' . $this->id
        );
    }

    public function calculateProgress()
    {
        $phases = $this->visiblePhases;
        if ($phases->isEmpty()) {
            return 0;
        }

        $totalWeight = $phases->sum('weight');
        if ($totalWeight <= 0) {
            return 0;
        }

        $weightedProgress = 0;
        foreach ($phases as $phase) {
            $phaseProgress = $this->calculatePhaseProgress($phase);
            $weightedProgress += ($phase->weight / $totalWeight) * $phaseProgress;
        }

        return round($weightedProgress, 2);
    }

    protected function calculatePhaseProgress($phase)
    {
        $activities = $this->activities()
            ->where('delivery_support_phase_id', $phase->id)
            ->get();

        if ($activities->isEmpty()) {
            return 0;
        }

        $totalWeight = $activities->sum('weight');
        if ($totalWeight <= 0) {
            return $activities->avg('progress_percentage') ?? 0;
        }

        $weightedProgress = 0;
        foreach ($activities as $activity) {
            $weightedProgress += ($activity->weight / $totalWeight) * $activity->progress_percentage;
        }

        return $weightedProgress;
    }

    public function updateCalculatedProgress()
    {
        $this->calculated_progress = $this->calculateProgress();
        $this->save();
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeActive($query)
    {
        return $query->where('calculated_progress', '<', 100);
    }

    public function scopeCompleted($query)
    {
        return $query->where('calculated_progress', '>=', 100);
    }

    // ========================================
    // ACCESSORS
    // ========================================

    public function getStatusAttribute()
    {
        if ($this->calculated_progress >= 100) {
            return 'completed';
        } elseif ($this->calculated_progress > 0) {
            return 'in_progress';
        }
        return 'not_started';
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'completed' => 'Completed',
            'in_progress' => 'In Progress',
            default => 'Not Started'
        };
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'completed' => 'green',
            'in_progress' => 'blue',
            default => 'gray'
        };
    }
}
