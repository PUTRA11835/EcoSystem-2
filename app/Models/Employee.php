<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use App\Models\EmployeeRole;
use App\Models\Menu;
use App\Models\DeliveryProjectActivity;

class Employee extends Model
{
    use HasApiTokens;
    
    protected $table = 'employee';
    protected $primaryKey = 'employee_id';
    public $timestamps = true;

    protected $fillable = [
        'eci',
        'is_active',
        'monthly_capacity_md',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    /** Many-to-many roles via pivot table */
    public function roles()
    {
        return $this->belongsToMany(EmployeeRole::class, 'employee_role_assignment', 'employee_id', 'role_id', 'employee_id', 'id')
                    ->withTimestamps();
    }

    /** Cek apakah employee memiliki role tertentu (by ID) */
    public function hasRole(int $roleId): bool
    {
        return $this->roles()->where('employee_role.id', $roleId)->exists();
    }

    /** Cek apakah employee memiliki salah satu dari beberapa role */
    public function hasAnyRole(array $roleIds): bool
    {
        return $this->roles()->whereIn('employee_role.id', $roleIds)->exists();
    }

    /** Ambil semua role ID yang dimiliki employee */
    public function getRoleIds(): array
    {
        return $this->roles()->pluck('employee_role.id')->map(fn($id) => (int) $id)->toArray();
    }

    /** Scope: filter employee yang memiliki role tertentu (via assignment) */
    public function scopeWithRole(\Illuminate\Database\Eloquent\Builder $query, int $roleId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('roles', fn($q) => $q->where('employee_role.id', $roleId));
    }

    /** Scope: filter employee yang memiliki salah satu role (via assignment) */
    public function scopeWithAnyRole(\Illuminate\Database\Eloquent\Builder $query, array $roleIds): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('roles', fn($q) => $q->whereIn('employee_role.id', $roleIds));
    }

    /**
     * Scope: filter employee yang salah satu role-nya diberi izin (can_view) atas menu slug
     * tertentu — dipakai untuk eligibility dropdown (mis. siapa saja yang boleh muncul sebagai
     * pilihan Ticket Lead / Ticket Member), bukan hardcode role_id.
     */
    public function scopeWithMenuPermission(\Illuminate\Database\Eloquent\Builder $query, string $slug): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('roles.menus', function ($q) use ($slug) {
            $q->where('menu.slug', $slug)->where('role_menu.can_view', true);
        });
    }

    /** Semua menu yang dapat diakses (union dari semua role) */
    public function accessibleMenus()
    {
        $roleIds = $this->roles()->pluck('employee_role.id');

        return Menu::whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('employee_role.id', $roleIds)
                  ->where('role_menu.can_view', true);
            })
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('order_seq')
            ->get();
    }

    /** Cek apakah employee boleh akses menu berdasarkan slug */
    public function canAccessMenu(string $slug): bool
    {
        $roleIds = $this->roles()->pluck('employee_role.id');

        return Menu::where('slug', $slug)
            ->whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('employee_role.id', $roleIds)
                  ->where('role_menu.can_view', true);
            })
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Cek permission spesifik pada menu.
     * @param string $permission 'can_view' | 'can_create' | 'can_edit' | 'can_delete'
     */
    public function hasMenuPermission(string $slug, string $permission = 'can_view'): bool
    {
        $roleIds = $this->roles()->pluck('employee_role.id');

        return Menu::where('slug', $slug)
            ->whereHas('roles', function ($q) use ($roleIds, $permission) {
                $q->whereIn('employee_role.id', $roleIds)
                  ->where("role_menu.{$permission}", true);
            })
            ->where('is_active', true)
            ->exists();
    }

    /** Alias ringkas untuk can_view */
    public function hasPermission(string $slug): bool
    {
        return $this->hasMenuPermission($slug, 'can_view');
    }

    /** Semua slug permission yang dimiliki (union semua role). Untuk dikirim ke frontend. */
    public function allPermissionSlugs(): array
    {
        $roleIds = $this->roles()->pluck('employee_role.id');

        return Menu::whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('employee_role.id', $roleIds)
                  ->where('role_menu.can_view', true);
            })
            ->where('is_active', true)
            ->pluck('slug')
            ->toArray();
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

    public function moduleLeaderships()
    {
        return $this->hasMany(ModuleLead::class, 'employee_id', 'employee_id');
    }

    public function ledModules()
    {
        return $this->belongsToMany(Module::class, 'module_leads', 'employee_id', 'module_id', 'employee_id', 'id')
                    ->withTimestamps();
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
