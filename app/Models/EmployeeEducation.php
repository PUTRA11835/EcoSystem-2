<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeEducation extends Model
{
    use HasFactory;

    // Nama tabel
    protected $table = 'employee_education';

    // Primary key
    protected $primaryKey = 'education_id';

    // Field yang bisa diisi (mass assignable)
    protected $fillable = [
        'employee_id',
        'education_type',
        'institute_place',
        'country',
        'degree',
        'duration_of_course',
        'final_grade',
        'branch_of_study',
        'start_year',
        'graduation_year',
        'unit',
        'valid_from',
        'valid_to',
        'attachment_name',
        'attachment_verify_link',
        'attachment_drive_link',
    ];

    // Casting tipe data otomatis
    protected $casts = [
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
        'start_year' => 'integer',
        'graduation_year' => 'integer',
    ];

    /**
     * Relasi ke model Employee
     * Satu riwayat pendidikan dimiliki oleh satu karyawan
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Scope untuk mendapatkan pendidikan yang masih valid
     */
    public function scopeValid($query)
    {
        $today = now()->toDateString();
        return $query->where(function($q) use ($today) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $today);
        })->where(function($q) use ($today) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', $today);
        });
    }

    /**
     * Scope untuk mendapatkan pendidikan berdasarkan tipe
     */
    public function scopeByType($query, $type)
    {
        return $query->where('education_type', $type);
    }

    /**
     * Scope untuk mendapatkan pendidikan berdasarkan tahun kelulusan
     */
    public function scopeByGraduationYear($query, $year)
    {
        return $query->where('graduation_year', $year);
    }

    /**
     * Accessor untuk periode pendidikan (format: 2015 - 2019)
     */
    public function getEducationPeriodAttribute()
    {
        if (!$this->start_year && !$this->graduation_year) {
            return '-';
        }
        
        $start = $this->start_year ?: '?';
        $end = $this->graduation_year ?: 'Present';
        
        return "{$start} - {$end}";
    }

    /**
     * Accessor untuk full education info
     * Format: "Bachelor of Computer Science at MIT"
     */
    public function getFullEducationInfoAttribute()
    {
        $parts = array_filter([
            $this->degree,
            $this->branch_of_study ? "of {$this->branch_of_study}" : null,
            $this->institute_place ? "at {$this->institute_place}" : null,
        ]);
        
        return implode(' ', $parts) ?: '-';
    }

    /**
     * Accessor untuk education level label
     */
    public function getEducationLevelLabelAttribute()
    {
        $labels = [
            'SD' => 'Elementary School',
            'SMP' => 'Junior High School',
            'SMA' => 'Senior High School',
            'SMK' => 'Vocational High School',
            'D1' => 'Diploma 1',
            'D2' => 'Diploma 2',
            'D3' => 'Diploma 3',
            'D4' => 'Diploma 4',
            'S1' => 'Bachelor Degree',
            'S2' => 'Master Degree',
            'S3' => 'Doctoral Degree',
        ];

        return $labels[$this->education_type] ?? $this->education_type;
    }

    /**
     * Check if education has attachment
     */
    public function hasAttachment()
    {
        return !empty($this->attachment_verify_link) || !empty($this->attachment_drive_link);
    }

    /**
     * Check if education is still valid
     */
    public function isValid()
    {
        $today = now()->toDateString();
        
        $validFrom = $this->valid_from ? $this->valid_from->toDateString() : null;
        $validTo = $this->valid_to ? $this->valid_to->toDateString() : null;
        
        $fromCheck = !$validFrom || $validFrom <= $today;
        $toCheck = !$validTo || $validTo >= $today;
        
        return $fromCheck && $toCheck;
    }
}