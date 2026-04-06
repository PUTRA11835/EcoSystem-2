<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySupportViewConfiguration extends Model
{
    use HasFactory;

    protected $table = 'delivery_support_view_configurations';

    protected $fillable = [
        'delivery_support_id',
        'default_view',
        'gantt_settings',
        'table_settings',
        'column_visibility',
    ];

    protected $casts = [
        'gantt_settings' => 'array',
        'table_settings' => 'array',
        'column_visibility' => 'array',
    ];

    public function deliverySupport()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public static function getDefaultSettings()
    {
        return [
            'default_view' => 'table',
            'gantt_settings' => [
                'zoom_level' => 'week',
                'show_dependencies' => true,
                'show_progress' => true,
            ],
            'table_settings' => [
                'rows_per_page' => 20,
                'sortable' => true,
            ],
            'column_visibility' => [
                'name' => true,
                'status' => true,
                'progress' => true,
                'start_date' => true,
                'end_date' => true,
                'weight' => true,
            ],
        ];
    }
}
