<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProjectIssue extends Model
{
    use HasFactory;

    protected $table = 'delivery_project_issues';

    protected $fillable = [
        'delivery_projects_id',
        'issue_number',
        'delivery_project_risk_id',
        'issue_description',
        'module',
        'date_identified',
        'closed_date',
        'status',
        'risk_to_project',
        'priority',
        'originator',
        'owner',
        'estimated_closed',
        'escalation_needed',
        'impact_of_issue',
        'tracking_comments',
    ];

    protected $casts = [
        'date_identified'   => 'date',
        'closed_date'       => 'date',
        'estimated_closed'  => 'date',
        'escalation_needed' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    // Alias kept for parity with other delivery models / global issues page.
    public function delivery_project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    public function risk()
    {
        return $this->belongsTo(DeliveryProjectRisk::class, 'delivery_project_risk_id');
    }

    // ── Computed Attributes ────────────────────────────────────────

    public function getIssueIdLabelAttribute(): string
    {
        return 'ISS-' . str_pad($this->issue_number, 3, '0', STR_PAD_LEFT);
    }
}
