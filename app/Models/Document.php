<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'document_name',
        'link_document',
        'document_type',
    ];

    public function delivery_project()
    {
        return $this->belongsTo(DeliveryProject::class);
    }
}