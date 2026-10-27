<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One dropdown option of the contract forms (HR & General → Contract → Settings, External → External Contract Settings).
 * `contract_type` rows are the engine's types (HR renames them and switches them on / off); `position_type` rows are HR's own list.
 */
class ContractOption extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Contract';

    protected $table = 'contract_options';

    protected $fillable = ['scope', 'kind', 'code', 'base_type', 'label', 'description', 'sort_order', 'is_active', 'is_default', 'is_system'];

    protected $casts = ['is_active' => 'boolean', 'is_default' => 'boolean', 'is_system' => 'boolean', 'sort_order' => 'integer'];

    public const SCOPE_INTERNAL = 'internal';
    public const SCOPE_EXTERNAL = 'external';
    public const KIND_CONTRACT_TYPE = 'contract_type';
    public const KIND_POSITION_TYPE = 'position_type';
    /** Penempatan kerja (hanya scope external): daftar tambahan di samping cabang dari menu Branches. */
    public const KIND_WORK_PLACEMENT = 'work_placement';
}
