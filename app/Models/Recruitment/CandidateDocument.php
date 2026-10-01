<?php

namespace App\Models\Recruitment;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A document of a candidate: an uploaded file (`path`) or a link (`url`),
 * whichever its document type allows. Uploads go to the private `local`
 * disk and are only reachable through the permission-checked download route;
 * `disk` is stored per row because files uploaded before that change are
 * still on the `public` disk.
 */
class CandidateDocument extends Model
{
    protected $table = 'recruitment_candidate_documents';

    protected $fillable = [
        'candidate_id', 'document_type_id', 'original_name', 'disk', 'path', 'url',
        'mime_type', 'size', 'uploaded_by_employee_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $document) {
            if ($document->path) {
                Storage::disk($document->disk)->delete($document->path);
            }
        });
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function type()
    {
        return $this->belongsTo(RecruitmentOption::class, 'document_type_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_employee_id', 'employee_id');
    }

    public function isLink(): bool
    {
        return $this->path === null && $this->url !== null;
    }

    public function typeLabel(): string
    {
        return $this->type->name ?? 'Document';
    }
}
