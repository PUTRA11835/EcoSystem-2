<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySupportDocument extends Model
{
    use HasFactory;

    protected $table = 'delivery_support_documents';

    protected $fillable = [
        'delivery_support_id',
        'document_name',
        'link_document',
        'document_type',
    ];

    public function deliverySupport()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public static function getDocumentTypes()
    {
        return [
            'BAST/BAPP',
            'Service Agreement',
            'Problem Report',
            'Solution Document',
            'Test Results',
            'Others',
        ];
    }
}
