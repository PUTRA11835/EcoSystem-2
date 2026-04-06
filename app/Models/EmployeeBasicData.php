<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBasicData extends Model
{
    use HasFactory;

    protected $table = 'employee_basic_data';
    protected $primaryKey = 'basic_data_id';

    protected $fillable = [
        'employee_id',
        
        // Identitas Pribadi
        'title',
        'nick_name',
        'gender',
        'religion',
        'first_name',
        'last_name',
        'search_term_1',
        'search_term_2',
        'marital_status',
        'birth_date',
        'birth_place',
        'since_date',
        
        // Informasi Pencatatan
        'created_by',
        'created_on',
        'last_changed_by',
        'last_changed_on',
        
        // Informasi Kepegawaian
        'personnel_area',
        'personnel_subarea',
        'employee_group',
        'employee_subgroup',
        'position',
        'division',
        'department',
        'direct_supervision',
        'manager',
        'authorization_group',
        
        // Status Administrasi
        'block',
        'deletion_flag',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'since_date' => 'date',
        'created_on' => 'datetime',
        'last_changed_on' => 'datetime',
        'block' => 'boolean',
        'deletion_flag' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     * This ensures full_name is included in JSON responses.
     */
    protected $appends = ['full_name'];

    /**
     * Relationship with Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Accessor for full_name (virtual attribute)
     * Menggabungkan first_name dan last_name
     */
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Accessor for search_name (virtual attribute)
     * Menggabungkan search_term_1 dan search_term_2
     */
    public function getSearchNameAttribute()
    {
        return trim(($this->search_term_1 ?? '') . ' ' . ($this->search_term_2 ?? ''));
    }

    /**
     * Scope: Get active employees only (not blocked and not flagged for deletion)
     */
    public function scopeActive($query)
    {
        return $query->where('block', false)
                     ->where('deletion_flag', false);
    }

    /**
     * Scope: Get blocked employees
     */
    public function scopeBlocked($query)
    {
        return $query->where('block', true);
    }

    /**
     * Scope: Get employees flagged for deletion
     */
    public function scopeFlaggedForDeletion($query)
    {
        return $query->where('deletion_flag', true);
    }

    /**
     * Scope: Filter by position
     */
    public function scopeByPosition($query, $position)
    {
        return $query->where('position', $position);
    }

    /**
     * Scope: Filter by department
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope: Filter by employee_subgroup
     */
    public function scopeBySubgroup($query, $subgroup)
    {
        return $query->where('employee_subgroup', $subgroup);
    }

    /**
     * Scope: Search by name (first_name, last_name, search_term)
     */
    public function scopeSearchByName($query, $searchTerm)
    {
        $searchTerm = strtoupper($searchTerm);
        return $query->where(function($q) use ($searchTerm) {
            $q->whereRaw('UPPER(first_name) LIKE ?', ["%{$searchTerm}%"])
              ->orWhereRaw('UPPER(last_name) LIKE ?', ["%{$searchTerm}%"])
              ->orWhereRaw('UPPER(search_term_1) LIKE ?', ["%{$searchTerm}%"])
              ->orWhereRaw('UPPER(search_term_2) LIKE ?', ["%{$searchTerm}%"]);
        });
    }

    /**
     * Boot method untuk auto-generate search terms
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto generate search_term_1 from first_name if not provided
            if (empty($model->search_term_1) && !empty($model->first_name)) {
                $model->search_term_1 = strtoupper($model->first_name);
            }
            
            // Auto generate search_term_2 from last_name if not provided
            if (empty($model->search_term_2) && !empty($model->last_name)) {
                $model->search_term_2 = strtoupper($model->last_name);
            }

            // Set created_on if creating new record and not provided
            if (!$model->exists && empty($model->created_on)) {
                $model->created_on = now();
            }

            // Always update last_changed_on when updating
            if ($model->exists && $model->isDirty()) {
                $model->last_changed_on = now();
            }
        });
    }

    /**
     * Check if employee is active (not blocked and not flagged for deletion)
     */
    public function isActive()
    {
        return !$this->block && !$this->deletion_flag;
    }

    /**
     * Check if employee is blocked
     */
    public function isBlocked()
    {
        return $this->block;
    }

    /**
     * Check if employee is flagged for deletion
     */
    public function isFlaggedForDeletion()
    {
        return $this->deletion_flag;
    }

    /**
     * Get employee's full information with related data
     */
    public function getFullInformation()
    {
        return [
            'basic_data_id' => $this->basic_data_id,
            'employee_id' => $this->employee_id,
            'full_name' => $this->full_name,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'nick_name' => $this->nick_name,
            'gender' => $this->gender,
            'religion' => $this->religion,
            'marital_status' => $this->marital_status,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'birth_place' => $this->birth_place,
            'position' => $this->position,
            'department' => $this->department,
            'division' => $this->division,
            'employee_subgroup' => $this->employee_subgroup,
            'employee_group' => $this->employee_group,
            'manager' => $this->manager,
            'direct_supervision' => $this->direct_supervision,
            'personnel_area' => $this->personnel_area,
            'personnel_subarea' => $this->personnel_subarea,
            'since_date' => $this->since_date?->format('Y-m-d'),
            'is_active' => $this->isActive(),
            'is_blocked' => $this->isBlocked(),
            'is_flagged_for_deletion' => $this->isFlaggedForDeletion(),
            'created_by' => $this->created_by,
            'created_on' => $this->created_on?->format('Y-m-d H:i:s'),
            'last_changed_by' => $this->last_changed_by,
            'last_changed_on' => $this->last_changed_on?->format('Y-m-d H:i:s'),
        ];
    }
}