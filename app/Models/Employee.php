<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use App\Models\EmployeeRole;
use App\Models\DeliveryProjectActivity;

class Employee extends Model
{
    use HasApiTokens;
    
    protected $table = 'employee';
    protected $primaryKey = 'employee_id';
    public $timestamps = true;

    protected $fillable = [
        'role_id',
        'eci',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'role_id', 'id');
    }

    public function basicData()
    {
        return $this->hasOne(EmployeeBasicData::class, 'employee_id', 'employee_id');
    }
    
    public function hasBasicData()
    {
        return $this->basicData()->exists();
    }

    public function getOrCreateBasicData()
    {
        if (!$this->hasBasicData()) {
            return $this->basicData()->create([
                'employee_id' => $this->id,
                'full_name' => $this->full_name,
                'is_active' => true,
                'is_blocked' => false,
                'deletion_flag' => false,
            ]);
        }
        
        return $this->basicData;
    }

    public function addresses()
    {
        return $this->hasMany(EmployeeAddress::class, 'employee_id', 'employee_id');
    }
    
    public function identification()
    {
        return $this->hasOne(EmployeeIdentification::class, 'employee_id', 'employee_id');
    }

    public function families()
    {
        return $this->hasMany(EmployeeFamily::class, 'employee_id', 'employee_id');
    }

    public function educations()
    {
        return $this->hasMany(EmployeeEducation::class, 'employee_id', 'employee_id');
    }

    public function qualifications()
    {
        return $this->hasMany(EmployeeQualification::class, 'employee_id', 'employee_id');
    }
    

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class, 'employee_id', 'employee_id');
    }

    public function banks()
    {
        return $this->hasMany(EmployeeBank::class, 'employee_id', 'employee_id');
    }

    public function payments()
    {
        return $this->hasMany(EmployeePayment::class, 'employee_id', 'employee_id');
    }

    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class, 'user_id', 'employee_id');
    }

    public function ticketMembers()
    {
        return $this->hasMany(TicketMember::class, 'employee_id', 'employee_id');
    }

    /**
     * Get activities assigned to this employee
     */
    public function assignedActivities()
    {
        return $this->belongsToMany(DeliveryProjectActivity::class, 'activity_employee', 'employee_id', 'delivery_project_activity_id', 'employee_id', 'id')
                    ->withPivot('role', 'assigned_date', 'notes')
                    ->withTimestamps();
    }

    /**
     * Get timesheets for this employee
     */
    public function timesheets()
    {
        return $this->hasMany(Timesheet::class, 'employee_id', 'employee_id');
    }

}
