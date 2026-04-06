<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeQualification extends Model
{
    use HasFactory;

    protected $table = 'employee_qualification';
    protected $primaryKey = 'qualification_id';

    /**
     * Kolom yang bisa diisi massal
     */
    protected $fillable = [
        'employee_id',
        'qualification_type',
        'module',
        'language',
        'qualification_level',
        'first_year',
        'certified',
        'dpm',
        'dsm',
        'valid_to',
        'valid_from',
        'drive_link',
        'verify_link',
    ];

    /**
     * Konversi otomatis tipe data
     */
    protected $casts = [
        'valid_to' => 'date',
        'valid_from' => 'date',
        'certified' => 'boolean',
        'dpm' => 'boolean',
        'dsm' => 'boolean',
    ];

    /**
     * Relasi ke model Employee
     * Satu kualifikasi dimiliki oleh satu karyawan
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Scope: Filter by qualification type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('qualification_type', $type);
    }

    /**
     * Scope: Get education qualifications
     */
    public function scopeEducation($query)
    {
        return $query->where('qualification_type', 'Education');
    }

    /**
     * Scope: Get certification qualifications
     */
    public function scopeCertification($query)
    {
        return $query->where('qualification_type', 'Certification');
    }

    /**
     * Scope: Get language qualifications
     */
    public function scopeLanguage($query)
    {
        return $query->where('qualification_type', 'Language');
    }

    /**
     * Scope: Get valid qualifications (not expired)
     */
    public function scopeValid($query, $date = null)
    {
        $checkDate = $date ?? now();
        
        return $query->where(function($q) use ($checkDate) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', $checkDate);
        });
    }

    /**
     * Scope: Get expired qualifications
     */
    public function scopeExpired($query, $date = null)
    {
        $checkDate = $date ?? now();
        
        return $query->whereNotNull('valid_to')
                     ->where('valid_to', '<', $checkDate);
    }

    /**
     * Scope: Get certified qualifications
     */
    public function scopeCertified($query)
    {
        return $query->where('certified', true);
    }

    /**
     * Check if qualification is valid
     */
    public function isValid($date = null)
    {
        $checkDate = $date ?? now();
        
        if ($this->valid_to === null) {
            return true;
        }
        
        return $this->valid_to >= $checkDate;
    }

    /**
     * Check if qualification is expired
     */
    public function isExpired($date = null)
    {
        return !$this->isValid($date);
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration()
    {
        if ($this->valid_to === null) {
            return null;
        }
        
        return now()->diffInDays($this->valid_to, false);
    }

    /**
     * Accessor: Get type label
     */
    public function getTypeLabelAttribute()
    {
        $labels = [
            'Education' => 'Education',
            'Certification' => 'Certification',
            'Language' => 'Language',
            'Skill' => 'Skill',
            'Training' => 'Training',
        ];

        return $labels[$this->qualification_type] ?? $this->qualification_type;
    }

    /**
     * Get qualification's full information
     */
    public function getFullInformation()
    {
        return [
            'qualification_id' => $this->qualification_id,
            'employee_id' => $this->employee_id,
            'qualification_type' => $this->qualification_type,
            'type_label' => $this->type_label,
            'module' => $this->module,
            'language' => $this->language,
            'qualification_level' => $this->qualification_level,
            'first_year' => $this->first_year,
            'certified' => $this->certified,
            'dpm' => $this->dpm,
            'dsm' => $this->dsm,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'is_valid' => $this->isValid(),
            'is_expired' => $this->isExpired(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'drive_link' => $this->drive_link,
            'verify_link' => $this->verify_link,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Constants for qualification types
     */
    const TYPE_EDUCATION = 'Education';
    const TYPE_CERTIFICATION = 'Certification';
    const TYPE_LANGUAGE = 'Language';
    const TYPE_SKILL = 'Skill';
    const TYPE_TRAINING = 'Training';

    /**
     * Get all available qualification types
     */
    public static function getQualificationTypes()
    {
        return [
            self::TYPE_EDUCATION => 'Education',
            self::TYPE_CERTIFICATION => 'Certification',
            self::TYPE_LANGUAGE => 'Language',
            self::TYPE_SKILL => 'Skill',
            self::TYPE_TRAINING => 'Training',
        ];
    }


}