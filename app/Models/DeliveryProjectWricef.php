<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliveryProjectWricef extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Project';

    protected $table = 'delivery_project_wricefs';

    /**
     * Kategori WRICEF → huruf yang dipakai di Obj_Id.
     * Contoh: SAP module "PS" + Interface → PSI001.
     */
    public const CATEGORY_LETTERS = [
        'Workflow'    => 'W',
        'Report'      => 'R',
        'Interface'   => 'I',
        'Conversion'  => 'C',
        'Enhancement' => 'E',
        'Form'        => 'F',
    ];

    public const PRIORITIES = ['High', 'Medium', 'Low'];

    public const STATUSES = ['Open', 'In Progress', 'Closed'];

    public const FSD_STATUSES = ['Develop', 'Review', 'Revisi', 'Done'];

    public const DEV_STATUSES = ['Develop Scenario', 'Testing', 'Revisi', 'Done'];

    public const TEST_STATUSES = ['Develop', 'Testing', 'Revisi', 'Done'];

    /** Modul SAP yang tersedia di dropdown. */
    public const SAP_MODULES = [
        'PS', 'PP', 'MM', 'SD', 'FI', 'CO', 'QM', 'PM', 'WM', 'EWM',
        'HCM', 'HR', 'BC', 'ABAP', 'BASIS', 'BW', 'CS', 'LE', 'TM', 'IM',
    ];

    protected $fillable = [
        'delivery_projects_id',
        'sap_module',
        'category',
        'seq',
        'obj_id',
        'obj_name',
        'capability',
        'tcode',
        'priority',
        'requestor',
        'request_date',
        'effort_mandays',
        'approved_by',
        'approved_date',
        'status',
        'fsd_pic', 'fsd_start', 'fsd_end', 'fsd_status', 'fsd_remarks',
        'dev_pic', 'dev_start', 'dev_end', 'dev_status', 'dev_remarks',
        'test_pic', 'test_start', 'test_end', 'test_status', 'test_remarks',
    ];

    protected $casts = [
        'request_date'   => 'date',
        'approved_date'  => 'date',
        'fsd_start'      => 'date',
        'fsd_end'        => 'date',
        'dev_start'      => 'date',
        'dev_end'        => 'date',
        'test_start'     => 'date',
        'test_end'       => 'date',
        'effort_mandays' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    // ── Obj_Id generation ──────────────────────────────────────────

    /**
     * Prefix Obj_Id dari modul + kategori, mis. "PS" + "Interface" → "PSI".
     * Kategori di luar daftar (data lama / import) memakai huruf pertamanya.
     */
    public static function objIdPrefix(string $sapModule, string $category): string
    {
        $letter = self::CATEGORY_LETTERS[$category] ?? strtoupper(substr($category, 0, 1));

        return strtoupper(trim($sapModule)) . $letter;
    }

    /**
     * Nomor urut berikutnya untuk satu kombinasi modul + kategori dalam satu
     * project.
     *
     * Nomor diambil dari MAX(seq), bukan COUNT, supaya menghapus satu baris
     * tidak membuat Obj_Id berikutnya bentrok dengan yang pernah dipakai.
     * $ignoreId dipakai saat update agar baris yang sedang diedit tidak
     * menghitung dirinya sendiri.
     */
    public static function nextSeq(int $projectId, string $sapModule, string $category, ?int $ignoreId = null): int
    {
        $query = static::where('delivery_projects_id', $projectId)
            ->where('sap_module', $sapModule)
            ->where('category', $category);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return (int) ($query->max('seq') ?? 0) + 1;
    }

    /** Susun Obj_Id final: prefix + nomor urut 3 digit (PSI001). */
    public static function buildObjId(string $prefix, int $seq): string
    {
        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
