<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmployeeFamily extends Model
{
    use HasFactory;

    // Nama tabel
    protected $table = 'employee_family';

    // Primary key
    protected $primaryKey = 'family_id';

    // Field yang bisa diisi (mass assignable)
    protected $fillable = [
        'employee_id',
        'name',
        'title',
        'relation',
        'gender',
        'religion',
        'country',
        'birth_place',
        'birth_date',
        'occupation',
        'is_alive',
        'valid_from',
        'valid_to',
        'verify_link',
    ];

    // Casting tipe data otomatis
    protected $casts = [
        'birth_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_alive' => 'boolean',
    ];

    /**
     * Relasi ke model Employee
     * Satu family member dimiliki oleh satu karyawan
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Accessor untuk mendapatkan umur
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        if (!$this->is_alive) {
            return null; // Tidak menghitung umur jika sudah meninggal
        }

        return $this->birth_date->diffInYears(now());
    }

    /**
     * Accessor untuk mendapatkan status hidup dalam teks
     */
    public function getLifeStatusAttribute(): string
    {
        return $this->is_alive ? 'Alive' : 'Deceased';
    }

    /**
     * Accessor untuk mendapatkan label relasi yang readable
     */
    public function getRelationLabelAttribute(): string
    {
        $relations = [
            'spouse' => 'Spouse',
            'child' => 'Child',
            'parent' => 'Parent',
            'father' => 'Father',
            'mother' => 'Mother',
            'sibling' => 'Sibling',
            'brother' => 'Brother',
            'sister' => 'Sister',
            'other' => 'Other',
        ];

        return $relations[strtolower($this->relation)] ?? ucfirst($this->relation);
    }

    /**
     * Accessor untuk mendapatkan nama lengkap dengan title
     */
    public function getFullNameAttribute(): string
    {
        return $this->title ? "{$this->title} {$this->name}" : $this->name;
    }

    /**
     * Scope untuk filter family member yang masih hidup
     */
    public function scopeAlive($query)
    {
        return $query->where('is_alive', true);
    }

    /**
     * Scope untuk filter family member yang sudah meninggal
     */
    public function scopeDeceased($query)
    {
        return $query->where('is_alive', false);
    }

    /**
     * Scope untuk filter berdasarkan relasi
     */
    public function scopeByRelation($query, $relation)
    {
        return $query->where('relation', $relation);
    }

    /**
     * Scope untuk mendapatkan spouse
     */
    public function scopeSpouse($query)
    {
        return $query->where('relation', 'spouse');
    }

    /**
     * Scope untuk mendapatkan children
     */
    public function scopeChildren($query)
    {
        return $query->where('relation', 'child');
    }

    /**
     * Scope untuk mendapatkan parents
     */
    public function scopeParents($query)
    {
        return $query->whereIn('relation', ['parent', 'father', 'mother']);
    }

    /**
     * Scope untuk mendapatkan siblings
     */
    public function scopeSiblings($query)
    {
        return $query->whereIn('relation', ['sibling', 'brother', 'sister']);
    }

    /**
     * Scope untuk mendapatkan family member yang masih valid
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', now());
        });
    }

    /**
     * Scope untuk mendapatkan family member berdasarkan umur
     */
    public function scopeByAge($query, $minAge = null, $maxAge = null)
    {
        $query->whereNotNull('birth_date')->where('is_alive', true);

        if ($minAge !== null) {
            $maxDate = now()->subYears($minAge);
            $query->where('birth_date', '<=', $maxDate);
        }

        if ($maxAge !== null) {
            $minDate = now()->subYears($maxAge + 1)->addDay();
            $query->where('birth_date', '>=', $minDate);
        }

        return $query;
    }

    /**
     * Scope untuk mendapatkan anak di bawah umur (< 18 tahun)
     */
    public function scopeMinorChildren($query)
    {
        return $query->where('relation', 'child')
                     ->where('is_alive', true)
                     ->byAge(null, 17);
    }

    /**
     * Check apakah masih minor (di bawah 18 tahun)
     */
    public function isMinor(): bool
    {
        if (!$this->birth_date || !$this->is_alive) {
            return false;
        }

        return $this->age < 18;
    }

    /**
     * Check apakah data masih valid
     */
    public function isValid(): bool
    {
        if (!$this->valid_to) {
            return true;
        }

        return now()->lte($this->valid_to);
    }
}