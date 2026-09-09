<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

/**
 * Stakeholder Register — satu baris per stakeholder Delivery Project.
 *
 * Dipetakan dari sheet "Stakeholder Register" pada template Excel
 * PMP/PMBOK aligned. Kolom "Kuadran Power-Interest" tidak disimpan: nilainya
 * diturunkan otomatis dari `power` + `interest` lewat accessor `quadrant`,
 * mengikuti matriks 2x2 pada sheet "Panduan".
 */
class DeliveryProjectStakeholder extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Project';

    protected $table = 'delivery_project_stakeholders';

    /** Internal (bagian dari organisasi/tim proyek) atau Eksternal. */
    public const CATEGORIES = ['Internal', 'Eksternal'];

    /** Peran generik stakeholder dalam tata kelola proyek. */
    public const CLASSIFICATIONS = [
        'Project Sponsor',
        'Steering Committee',
        'Project Owner',
        'Project Manager',
        'Business Owner',
        'Key User',
        'End User',
        'Vendor / Partner',
        'Regulator',
        'Other',
    ];

    /** Tingkat Power & Interest. Matriks kuadran memakai Tinggi vs (Sedang|Rendah). */
    public const LEVELS = ['Tinggi', 'Sedang', 'Rendah'];

    /** Tingkat keterlibatan stakeholder (Stakeholder Engagement Assessment Matrix). */
    public const ATTITUDES = ['Unaware', 'Resistant', 'Neutral', 'Supportive', 'Leading'];

    /** Frekuensi stakeholder menerima update. */
    public const FREQUENCIES = [
        'Harian',
        'Mingguan',
        'Dua Mingguan',
        'Bulanan',
        'Triwulanan',
        'Sesuai Kebutuhan',
    ];

    public const STATUSES = ['Aktif', 'Tidak Aktif', 'Selesai'];

    /**
     * Kuadran Power-Interest → label & strategi (PMBOK Power/Interest Grid).
     * Kunci = "power|interest" dengan nilai sudah dinormalkan ke high/low.
     */
    public const QUADRANTS = [
        'high|high' => 'Kelola Intensif (Manage Closely)',
        'high|low'  => 'Jaga Kepuasan (Keep Satisfied)',
        'low|high'  => 'Selalu Diinformasikan (Keep Informed)',
        'low|low'   => 'Pantau Seperlunya (Monitor)',
    ];

    protected $fillable = [
        'delivery_projects_id',
        'seq',
        'stakeholder_id',
        'name',
        'role_title',
        'organization',
        'category',
        'classification',
        'email',
        'phone',
        'power',
        'interest',
        'current_attitude',
        'expected_attitude',
        'key_expectations',
        'information_needs',
        'engagement_strategy',
        'communication_frequency',
        'communication_method',
        'pic_internal',
        'stakeholder_risk',
        'status',
        'identified_date',
        'last_updated_date',
        'notes',
    ];

    protected $casts = [
        'identified_date'   => 'date',
        'last_updated_date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    // ── Kuadran Power-Interest ───────────────────────────────────

    /**
     * Normalisasi level ke sisi matriks 2x2: hanya "Tinggi" yang dihitung
     * sebagai high; "Sedang" & "Rendah" masuk low (sesuai grid pada Panduan).
     */
    private static function side(?string $level): ?string
    {
        if ($level === null || $level === '') {
            return null;
        }

        return strtolower(trim($level)) === 'tinggi' ? 'high' : 'low';
    }

    /** Label kuadran, atau null bila power/interest belum diisi. */
    public function getQuadrantAttribute(): ?string
    {
        $power    = self::side($this->power);
        $interest = self::side($this->interest);

        if ($power === null || $interest === null) {
            return null;
        }

        return self::QUADRANTS["{$power}|{$interest}"] ?? null;
    }

    // ── Penomoran stakeholder_id ─────────────────────────────────

    /**
     * Nomor urut berikutnya untuk satu proyek. Diambil dari MAX(seq), bukan
     * COUNT, supaya menghapus satu baris tidak membuat SH-xxx berikutnya
     * bentrok dengan yang pernah dipakai. $ignoreId dipakai saat update.
     */
    public static function nextSeq(int $projectId, ?int $ignoreId = null): int
    {
        $query = static::where('delivery_projects_id', $projectId);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return (int) ($query->max('seq') ?? 0) + 1;
    }

    /** Susun ID final: SH-001, SH-012, ... */
    public static function buildStakeholderId(int $seq): string
    {
        return 'SH-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
