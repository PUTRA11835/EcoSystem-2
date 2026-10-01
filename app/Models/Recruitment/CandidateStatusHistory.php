<?php

namespace App\Models\Recruitment;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class CandidateStatusHistory extends Model
{
    protected $table = 'recruitment_candidate_status_histories';

    public $timestamps = false;

    protected $fillable = ['candidate_id', 'from_status', 'to_status', 'changed_by_employee_id', 'changed_at'];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(Employee::class, 'changed_by_employee_id', 'employee_id');
    }

    public function toLabel(): string
    {
        return Candidate::STATUSES[$this->to_status] ?? $this->to_status;
    }

    public function fromLabel(): ?string
    {
        return $this->from_status ? (Candidate::STATUSES[$this->from_status] ?? $this->from_status) : null;
    }
}
