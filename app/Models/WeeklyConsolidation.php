<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Header satu batch recon "Weekly Consolidation" (Reporting → Support) untuk
 * satu modul pada satu periode. Baris tiketnya ada di WeeklyConsolidationTicket
 * — lihat docblock di sana untuk aturan live-join (data tiket TIDAK disnapshot).
 */
class WeeklyConsolidation extends Model
{
    protected $table = 'weekly_consolidations';

    protected $fillable = [
        'module_id',
        'is_all_modules',
        'period_start',
        'period_end',
        'period_label',
        'generated_by_id',
        'last_refreshed_at',
        'last_refreshed_by_id',
    ];

    protected $casts = [
        'period_start'      => 'date',
        'period_end'        => 'date',
        'last_refreshed_at' => 'datetime',
        'is_all_modules'    => 'boolean',
    ];

    /** Status tiket yang dianggap "masih terbuka" untuk keperluan recon ini. */
    public const OPEN_STATUSES = [
        'open',
        'inprocess',
        'waiting_on_customer',
        'waiting_to_confirmation',
        'hold',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id', 'id');
    }

    /**
     * Daftar LENGKAP modul batch ini (bisa >1 kalau digenerate sebagai gabungan
     * modul oleh role privileged) — module_id di atas cuma modul utama/
     * representatif. Sama persis pola Ticket::module_id vs Ticket::modules().
     */
    public function modules()
    {
        return $this->belongsToMany(Module::class, 'weekly_consolidation_modules', 'weekly_consolidation_id', 'module_id')
            ->withTimestamps();
    }

    public function generatedBy()
    {
        return $this->belongsTo(Employee::class, 'generated_by_id', 'employee_id');
    }

    public function lastRefreshedBy()
    {
        return $this->belongsTo(Employee::class, 'last_refreshed_by_id', 'employee_id');
    }

    public function lines()
    {
        return $this->hasMany(WeeklyConsolidationTicket::class, 'weekly_consolidation_id');
    }
}
