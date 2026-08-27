<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu permintaan generate laporan .docx dari template Word (fitur
 * ReportGeneratorService) — lihat migrasi create_word_reports_table.
 *
 * template_path/template_original_name adalah SNAPSHOT template yang benar-
 * benar dipakai generate ini (dibaca driver AI); report_template_id cuma
 * penanda "dari template mana asalnya" di library (lihat ReportTemplate).
 */
class WordReport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_AWAITING_INPUT = 'awaiting_input';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Fase generate yang SEDANG/BELUM dikerjakan (lihat ReportGeneratorService) --
     * null berarti semua fase sudah selesai (docx/pdf sudah jadi).
     */
    public const PHASE_STRUCTURE = 'structure';
    public const PHASE_DATA = 'data';
    public const PHASE_DOCUMENT = 'document';

    protected $fillable = [
        'employee_id',
        'report_template_id',
        'template_original_name',
        'template_path',
        'instructions',
        'question',
        'qa_log',
        'status',
        'phase',
        'structure_map',
        'pulled_data',
        'docx_path',
        'pdf_path',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'qa_log' => 'array',
        'structure_map' => 'array',
        'pulled_data' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }
}
