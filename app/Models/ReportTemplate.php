<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu template .docx tersimpan di library Word Report Generator — lihat
 * migrasi create_report_templates_table. customer_id null = template umum/
 * internal, tidak terikat customer/business partner tertentu.
 */
class ReportTemplate extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'original_filename',
        'file_path',
        'uploaded_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by', 'employee_id');
    }
}
