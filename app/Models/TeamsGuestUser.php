<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Consultant eksternal yang sudah diundang sebagai guest tenant, beserta object
 * id Entra-nya. Lihat App\Services\Teams\TeamsGuestService.
 */
class TeamsGuestUser extends Model
{
    protected $table = 'teams_guest_user';

    protected $fillable = [
        'email',
        'aad_object_id',
        'invite_status',
        'invited_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];
}
