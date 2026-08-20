<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class CustomerCredential extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Customer';
    protected static array $auditExcept = ['notes'];

    protected $table = 'customer_credential';
    protected $primaryKey = 'credential_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'notes',
        'updated_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
