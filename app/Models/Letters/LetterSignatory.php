<?php

namespace App\Models\Letters;

use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may sign letters, set in Letter Templates → Settings → Signers, role
 * first: everyone holding a role (employee_id empty), or one person of that
 * role — e.g. the Finance Manager. HR confirms with a signer outside HR before
 * applying their master-data signature; the letter records who applied it.
 */
class LetterSignatory extends Model
{
    protected $table = 'letter_signatories';

    protected $fillable = ['role_id', 'employee_id', 'title', 'is_active', 'sort_order', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'role_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Everyone holding the role, rather than one person of it. */
    public function isWholeRole(): bool
    {
        return $this->employee_id === null;
    }
}
