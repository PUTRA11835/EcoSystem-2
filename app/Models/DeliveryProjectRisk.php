<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProjectRisk extends Model
{
    use HasFactory;

    protected $table = 'delivery_project_risks';

    protected $fillable = [
        'delivery_projects_id',
        'risk_number',
        'category',
        'description',
        'cause',
        'project_impact',
        'probability',
        'impact',
        'response_strategy',
        'mitigation_plan',
        'risk_owner',
        'status',
        'target_date',
        'notes',
    ];

    protected $casts = [
        'probability' => 'integer',
        'impact'      => 'integer',
        'target_date' => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    // ── Computed Attributes ────────────────────────────────────────

    public function getRiskScoreAttribute(): int
    {
        return $this->probability * $this->impact;
    }

    public function getRiskLevelAttribute(): string
    {
        $score = $this->risk_score;
        if ($score >= 12) return 'High';
        if ($score >= 5)  return 'Medium';
        return 'Low';
    }

    public function getRiskIdLabelAttribute(): string
    {
        return 'RSK-' . str_pad($this->risk_number, 3, '0', STR_PAD_LEFT);
    }
}
