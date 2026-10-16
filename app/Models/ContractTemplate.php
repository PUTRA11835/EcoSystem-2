<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Template teks kontrak (HR & General → Contract → Templates). Teksnya memuat {{placeholder}} yang diisi saat kontrak
 * ditampilkan (App\Support\Contracts\ContractRules::render). Template bawaan sistem boleh diedit tetapi tidak dihapus.
 */
class ContractTemplate extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Contract';

    protected $table = 'contract_templates';

    protected $fillable = [
        'name', 'contract_type', 'position', 'position_type', 'description', 'body_html', 'use_letterhead', 'signatory_employee_id', 'approver_employee_id', 'page_size', 'page_orientation', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
        'is_system_default', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'use_letterhead'    => 'boolean',
        'is_system_default' => 'boolean',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** Kosong = berlaku untuk semua Position. */
    public function appliesToLabel(): string
    {
        $scope = $this->contract_type === 'EXTERNAL' ? 'External consultants' : 'All positions';

        return $this->position_type ?: ($this->position ?: $scope);
    }
}
