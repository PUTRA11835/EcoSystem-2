<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Customer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'customer';
    protected $primaryKey = 'customer_id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_code',
        'email',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    // ==================== AUTHENTICATION RELATIONSHIPS ====================

    /**
     * Customer has no role in the new schema.
     */

    /**
     * Get login activities for the customer
     * 
     * @return HasMany
     */
    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class, 'user_id', 'customer_id')
            ->orderBy('created_at', 'desc');
    }

    // ==================== DATA MANAGEMENT RELATIONSHIPS ====================

    /**
     * Get the basic data for the customer (one-to-one)
     * 
     * @return HasOne
     */
    public function basicData(): HasOne
    {
        return $this->hasOne(CustomerBasicData::class, 'customer_id', 'customer_id');
    }

    /**
     * Get all addresses for the customer (one-to-many)
     * 
     * @return HasMany
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id', 'customer_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the primary/home address
     * 
     * @return HasOne
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class, 'customer_id', 'customer_id')
            ->where(function($query) {
                $query->where('address_type', 'Home')
                    ->orWhere('address_type', 'Primary');
            })
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get the contact information for the customer (one-to-one)
     * 
     * @return HasOne
     */
    public function contact(): HasOne
    {
        return $this->hasOne(CustomerContact::class, 'customer_id', 'customer_id');
    }

    /**
     * Get all identifications for the customer (one-to-many)
     * 
     * @return HasMany
     */
    public function identifications(): HasMany
    {
        return $this->hasMany(CustomerIdentification::class, 'customer_id', 'customer_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get all bank accounts for the customer (one-to-many)
     * 
     * @return HasMany
     */
    public function banks(): HasMany
    {
        return $this->hasMany(CustomerBank::class, 'customer_id', 'customer_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the primary/first bank account
     * 
     * @return HasOne
     */
    public function primaryBank(): HasOne
    {
        return $this->hasOne(CustomerBank::class, 'customer_id', 'customer_id')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get all attachments for the customer (one-to-many)
     * 
     * @return HasMany
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerAttachment::class, 'customer_id', 'customer_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get all history records for the customer (one-to-many)
     * 
     * @return HasMany
     */
    public function history(): HasMany
    {
        return $this->hasMany(CustomerHistory::class, 'customer_id', 'customer_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get recent history records (last 10)
     * 
     * @return HasMany
     */
    public function recentHistory(): HasMany
    {
        return $this->hasMany(CustomerHistory::class, 'customer_id', 'customer_id')
            ->orderBy('created_at', 'desc')
            ->limit(10);
    }

    // ==================== AUTHENTICATION METHODS ====================

    /**
     * Check if customer can login
     * 
     * @return bool
     */
    public function canLogin(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->basicData && ($this->basicData->block || $this->basicData->deletion_flag)) {
            return false;
        }

        return true;
    }

    /**
     * Record login activity
     * 
     * @param string $ipAddress
     * @param string $userAgent
     * @return LoginActivity
     */
    public function recordLogin(string $ipAddress, string $userAgent)
    {
        return $this->loginActivities()->create([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_at' => now(),
        ]);
    }

    // ==================== SCOPES ====================

    /**
     * Scope: Get active customers only (can login)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereHas('basicData', function($q) {
                $q->where('deletion_flag', false)
                  ->where('block', false);
            });
    }

    /**
     * Scope: Get customers with addresses
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAddresses($query)
    {
        return $query->whereHas('addresses');
    }

    /**
     * Scope: Get customers with complete data
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeComplete($query)
    {
        return $query->whereHas('basicData')
            ->whereHas('contact')
            ->whereHas('addresses');
    }

    /**
     * Scope: Get customers by customer group
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $group
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGroup($query, $group)
    {
        return $query->whereHas('basicData', function($q) use ($group) {
            $q->where('customer_group', $group);
        });
    }

    /**
     * Scope: Get customers by customer category
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, $category)
    {
        return $query->whereHas('basicData', function($q) use ($category) {
            $q->where('customer_category', $category);
        });
    }

    /**
     * Scope: Search customers by name, code, or email
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('customer_code', 'like', '%' . $search . '%')
              ->orWhere('email', 'like', '%' . $search . '%')
              ->orWhereHas('basicData', function($subQ) use ($search) {
                  $subQ->where('name_1', 'like', '%' . $search . '%')
                       ->orWhere('name_2', 'like', '%' . $search . '%')
                       ->orWhere('search_term_1', 'like', '%' . $search . '%')
                       ->orWhere('search_term_2', 'like', '%' . $search . '%');
              });
        });
    }

    // ==================== ACCESSOR METHODS ====================

    /**
     * Get customer display name
     * 
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->basicData->name_1 ?? $this->email ?? 'Unknown Customer';
    }

    /**
     * Get full address as string
     * 
     * @return string
     */
    public function getFullAddressAttribute(): string
    {
        $address = $this->primaryAddress;
        if (!$address) {
            return 'No address';
        }

        return implode(', ', array_filter([
            $address->street,
            $address->house_number ? 'No. ' . $address->house_number : null,
            $address->city,
            $address->postal_code,
            $address->country
        ]));
    }

    /**
     * Get customer status label
     * 
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if (isset($this->basicData->deletion_flag) && $this->basicData->deletion_flag) {
            return 'Deleted';
        } elseif (isset($this->basicData->block) && $this->basicData->block) {
            return 'Blocked';
        }
        
        return 'Active';
    }

    /**
     * Get customer status color class
     * 
     * @return string
     */
    public function getStatusColorAttribute(): string
    {
        if (!$this->is_active) {
            return 'bg-gray-100 text-gray-800';
        }

        if (isset($this->basicData->deletion_flag) && $this->basicData->deletion_flag) {
            return 'bg-red-100 text-red-800';
        } elseif (isset($this->basicData->block) && $this->basicData->block) {
            return 'bg-orange-100 text-orange-800';
        }
        
        return 'bg-green-100 text-green-800';
    }

    // ==================== HELPER METHODS ====================

    /**
     * Generate unique customer code
     * 
     * @return string
     */
    public static function generateCustomerCode(): string
    {
        do {
            // Format: CUST + Year(2) + Month(2) + Random(4)
            // Example: CUST24110001
            $code = 'CUST' . date('ym') . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('customer_code', $code)->exists());

        return $code;
    }

    /**
     * Check if customer has any addresses
     * 
     * @return bool
     */
    public function hasAddresses(): bool
    {
        return $this->addresses()->exists();
    }

    /**
     * Check if customer has contact information
     * 
     * @return bool
     */
    public function hasContact(): bool
    {
        return $this->contact()->exists();
    }

    /**
     * Check if customer has any identifications
     * 
     * @return bool
     */
    public function hasIdentifications(): bool
    {
        return $this->identifications()->exists();
    }

    /**
     * Check if customer has any bank accounts
     * 
     * @return bool
     */
    public function hasBanks(): bool
    {
        return $this->banks()->exists();
    }

    /**
     * Check if customer has any attachments
     * 
     * @return bool
     */
    public function hasAttachments(): bool
    {
        return $this->attachments()->exists();
    }

    /**
     * Get total count of all related records
     * 
     * @return array
     */
    public function getRelatedCounts(): array
    {
        return [
            'addresses' => $this->addresses()->count(),
            'identifications' => $this->identifications()->count(),
            'banks' => $this->banks()->count(),
            'attachments' => $this->attachments()->count(),
            'history' => $this->history()->count(),
            'login_activities' => $this->loginActivities()->count(),
        ];
    }

    /**
     * Log customer activity
     * 
     * @param string $action
     * @param string $description
     * @param string $section
     * @return CustomerHistory
     */
    public function logActivity(string $action, string $description, string $section = 'general')
    {
        return $this->history()->create([
            'action' => $action,
            'description' => $description,
            'section' => $section,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->email ?? $this->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Get customer summary data
     * 
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'customer_id' => $this->customer_id,
            'customer_code' => $this->customer_code,
            'name' => $this->display_name,
            'email' => $this->email,
            'role' => 'Customer',
            'status' => $this->status_label,
            'can_login' => $this->canLogin(),
            'has_contact' => $this->hasContact(),
            'addresses_count' => $this->addresses()->count(),
            'identifications_count' => $this->identifications()->count(),
            'banks_count' => $this->banks()->count(),
            'attachments_count' => $this->attachments()->count(),
            'last_login' => $this->loginActivities()->latest()->first()?->login_at,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get customer with all relationships loaded
     * 
     * @param int $customerId
     * @return Customer|null
     */
    public static function getWithAllRelations(int $customerId): ?Customer
    {
        return self::with([
            'basicData',
            'addresses',
            'contact',
            'identifications',
            'banks',
            'attachments',
            'recentHistory',
            'loginActivities' => function($query) {
                $query->limit(5);
            }
        ])->find($customerId);
    }

    /**
     * Create customer with basic data in transaction
     *
     * @param array $customerData (email, role_id)
     * @param array $basicData (name_1, customer_group, etc)
     * @return Customer
     * @throws \Exception
     */
    public static function createWithBasicData(array $customerData, array $basicData): Customer
    {
        return DB::transaction(function () use ($customerData, $basicData) {
            // Generate customer code
            $customerCode = self::generateCustomerCode();

            // Create customer
            $customer = self::create([
                'customer_code' => $customerCode,
                'email' => $customerData['email'],
                'is_active' => $customerData['is_active'] ?? true,
            ]);

            // Create basic data (tanpa customer_code karena sudah pindah ke tabel customer)
            $customer->basicData()->create($basicData);

            // Log creation
            $customer->logActivity('create', 'Customer created', 'customer');

            return $customer->fresh(['basicData']);
        });
    }

    /**
     * Delete customer with all related data (soft delete through deletion_flag)
     * 
     * @return bool
     */
    public function softDeleteCustomer(): bool
    {
        return DB::transaction(function () {
            // Mark as deleted in basic data
            if ($this->basicData) {
                $this->basicData->update([
                    'deletion_flag' => true,
                    'last_changed_by' => auth()->user()->email ?? 'system',
                    'last_changed_on' => now()
                ]);
            }

            // Mark customer as inactive (can't login)
            $this->update(['is_active' => false]);

            // Log deletion
            $this->logActivity('delete', 'Customer marked as deleted', 'customer');

            return true;
        });
    }

    /**
     * Restore deleted customer
     * 
     * @return bool
     */
    public function restoreCustomer(): bool
    {
        return DB::transaction(function () {
            // Restore in basic data
            if ($this->basicData) {
                $this->basicData->update([
                    'deletion_flag' => false,
                    'last_changed_by' => auth()->user()->email ?? 'system',
                    'last_changed_on' => now()
                ]);
            }

            // Mark customer as active (can login)
            $this->update(['is_active' => true]);

            // Log restoration
            $this->logActivity('restore', 'Customer restored', 'customer');

            return true;
        });
    }

    /**
     * Hard delete customer with all related data
     * 
     * @return bool
     * @throws \Exception
     */
    public function hardDeleteCustomer(): bool
    {
        return DB::transaction(function () {
            // Delete all related data
            $this->addresses()->delete();
            $this->identifications()->delete();
            $this->banks()->delete();
            $this->history()->delete();
            $this->loginActivities()->delete();
            
            // Delete attachments and files
            foreach ($this->attachments as $attachment) {
                // Delete file from storage
                if ($attachment->file_path && \Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }

            // Delete contact
            $this->contact()->delete();

            // Delete basic data
            $this->basicData()->delete();

            // Delete customer
            return $this->delete();
        });
    }

    // ==================== QUERY HELPERS ====================

    /**
     * Get paginated customers with basic data
     * 
     * @param int $perPage
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getPaginated(int $perPage = 15, array $filters = [])
    {
        $query = self::with(['basicData']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['customer_group'])) {
            $query->byGroup($filters['customer_group']);
        }

        if (!empty($filters['customer_category'])) {
            $query->byCategory($filters['customer_category']);
        }

        if (!empty($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get customer statistics
     * 
     * @return array
     */
    public static function getStatistics(): array
    {
        return [
            'total' => self::count(),
            'active' => self::where('is_active', true)->count(),
            'inactive' => self::where('is_active', false)->count(),
            'can_login' => self::active()->count(),
            'with_addresses' => self::withAddresses()->count(),
            'with_contact' => self::whereHas('contact')->count(),
            'complete' => self::complete()->count(),
            'created_today' => self::whereDate('created_at', today())->count(),
            'created_this_week' => self::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'created_this_month' => self::whereMonth('created_at', now()->month)->count(),
            'logged_in_today' => self::whereHas('loginActivities', function($q) {
                $q->whereDate('login_at', today());
            })->count(),
        ];
    }
}
