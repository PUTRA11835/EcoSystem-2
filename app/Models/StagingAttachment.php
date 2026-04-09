<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StagingAttachment extends Model
{
    protected $table = 'staging_attachments';

    protected $fillable = [
        'staging_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'original_name',
    ];

    public function staging()
    {
        return $this->belongsTo(StagingTicket::class, 'staging_id');
    }

    /**
     * URL publik untuk download file.
     */
    public function getPublicUrlAttribute(): string
    {
        return '/storage/' . $this->file_path;
    }
}
