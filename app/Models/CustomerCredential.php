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

    /**
     * Catatan credential customer, siap ditempel sebagai konteks prompt AI —
     * atau null kalau $actor tidak punya izin, atau tidak ada catatan sama
     * sekali. SATU-SATUNYA titik yang menggerbangi akses ini dari kode AI
     * (dipakai AiTicketAnalyzerService & AiTicketQaService): memakai slug
     * yang PERSIS sama dengan yang sudah menjaga tabel ini untuk tool AI
     * Assistant (lihat TableAccess::authorizeQuery() &
     * docs/ai-assistant-sensitive-data-policy.md), supaya kedua konsumen AI
     * ini tidak bisa diam-diam melenceng satu sama lain kalau suatu saat
     * slug atau aturannya berubah.
     *
     * @param array<int, int|null> $customerIds boleh memuat null/duplikat — dibuang sebelum query
     */
    public static function contextNotesFor(array $customerIds, ?Employee $actor): ?string
    {
        if (!$actor?->hasMenuPermission('customer.section.credential.view')) {
            return null;
        }

        $ids = array_values(array_unique(array_filter($customerIds)));

        if (empty($ids)) {
            return null;
        }

        $notes = static::whereIn('customer_id', $ids)
            ->pluck('notes')
            ->map(static fn ($note) => trim((string) $note))
            ->filter(static fn (string $note) => '' !== $note)
            ->implode("\n---\n");

        return '' !== $notes ? $notes : null;
    }
}
