<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A KPI team. Assigning an employee to a team copies the team's lead onto the
 * employee's reporting line (employee_basic_data.direct_supervision), and
 * changing a team's lead re-points every team member whose lead came from the
 * team.
 */
class KpiTeam extends Model
{
    protected $table = 'kpi_teams';

    protected $fillable = [
        'name',
        'lead_employee_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(Employee::class, 'lead_employee_id', 'employee_id')->with('basicData');
    }

    public function memberBasicData()
    {
        return $this->hasMany(EmployeeBasicData::class, 'kpi_team_id');
    }
}
