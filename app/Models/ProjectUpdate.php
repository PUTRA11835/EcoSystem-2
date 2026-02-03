<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectUpdate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'highlight_issue',
        'action',
        'due_date',
        'status',
        'complexity',
        'deliverable',
        'notes',
    ];

    public function project() {
        return $this->belongsTo(Project::class);
    }
}