<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliveryProjectUpdate extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Project';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'delivery_projects_id',
        'highlight_issue',
        'action',
        'due_date',
        'status',
        'complexity',
        'deliverable',
        'notes',
    ];

    public function delivery_project() {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }
}