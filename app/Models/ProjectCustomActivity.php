<?php

// File: app/Models/ProjectCustomActivity.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectCustomActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'project_phase_id',
        'name',
        'description',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function phase()
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }

    public function plannings()
    {
        return $this->hasMany(ProjectPlanning::class, 'project_custom_activity_id');
    }

    public function planning()
    {
        return $this->hasOne(ProjectPlanning::class, 'project_custom_activity_id');
    }
}