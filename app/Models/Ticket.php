<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Ticket extends Model
{
    use HasFactory;
    use \App\Models\Concerns\HasOneDriveShareLink;

    protected $table = 'ticket';
    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'end_customer_id',
        'ticket_lead_id',
        'pic',
        'description',
        'start_date',
        'end_date',
        'ticket_priority',
        'ticket_type',
        'scale',
        'status',
        'wait_close',
        'folder',
        'file_log',
        'man_days',
        'channel',
        'email_thread_id',
        'last_message_at',
        'last_customer_reply_at',
        'last_agent_reply_at',
        'last_internal_note_at',
        'last_internal_note_sender_id',
        // Field tambahan dari form Jarvies
        'name',
        'no_hp',
        'module',
        'module_id',
        'client',
        'submitted_by_email',
        'submitted_by_name',
        // Mandays status
        'mandays_proposal_status',
        'resolution_days_status',
        // CC recipients (disalin dari staging saat approve)
        'cc_emails',
        // TO recipients (primary customer + recipient tambahan dari form reply)
        'to_emails',
        // Progress tracking
        'progress_percentage',
        'progress_note',
        'last_progress_at',
        'progress_updated_by',
        // OneDrive
        'onedrive_folder_id',
        'onedrive_folder_url',
        'onedrive_deliverable_folder_id',
        'onedrive_link_scope',
        'onedrive_link_expires_at',
        'onedrive_link_checked_at',
        // Visibility
        'is_hidden',
    ];

    protected $casts = [
        'onedrive_link_expires_at' => 'datetime',
        'onedrive_link_checked_at' => 'datetime',
        'start_date'             => 'date',
        'end_date'               => 'date',
        'man_days'               => 'decimal:2',
        'wait_close'             => 'decimal:2',
        'progress_percentage'    => 'decimal:2',
        'last_message_at'        => 'datetime',
        'last_customer_reply_at' => 'datetime',
        'last_agent_reply_at'    => 'datetime',
        'last_internal_note_at'        => 'datetime',
        'last_internal_note_sender_id' => 'integer',
        'cc_emails'                    => 'array',
        'to_emails'                    => 'array',
    ];

    // ── Ticket type ──────────────────────────────────────────────────────────

    /**
     * Tiket yang murni urusan internal Eclectic — TIDAK ditampilkan ke customer
     * di JARVIES (tabel `ticket` dipakai bersama kedua aplikasi).
     *
     * Ini satu-satunya pembeda visibilitas: tiket buatan EcoSystem dengan type
     * lain (Incident, Change Request, dst.) tetap terlihat customer.
     */
    public const TYPE_INTERNAL = 'Internal';

    public static function types(): array
    {
        return [
            'Incident',
            'Change Request',
            'Service Request',
            'EWA',
            'RISE',
            'Consult',
            self::TYPE_INTERNAL,
        ];
    }

    public function isInternal(): bool
    {
        return $this->ticket_type === self::TYPE_INTERNAL;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    // Relasi ke tabel modules (master). Nama relasi sengaja beda dari kolom
    // string `module` (legacy free text) supaya keduanya bisa diakses terpisah.
    public function moduleMaster()
    {
        return $this->belongsTo(Module::class, 'module_id', 'id');
    }

    // Nama modul untuk ditampilkan: prioritaskan modul master (module_id),
    // fallback ke teks lama (module) untuk tiket yang belum diassign manual.
    public function getModuleNameAttribute(): ?string
    {
        return $this->moduleMaster?->name ?? $this->module;
    }

    public function endCustomer()
    {
        return $this->belongsTo(Customer::class, 'end_customer_id', 'customer_id');
    }

    /**
     * Customer deliverable folder name (per customer), e.g. "125 DEMOGRP2".
     * Returns null when customer / basic data is missing.
     *
     * Deliverable ticket disimpan di level customer ({root}/{customer}/TICKETING/...),
     * sehingga folder-nya diturunkan langsung dari customer ticket — TIDAK lagi
     * bergantung pada delivery support. Format dijaga identik dengan
     * DeliverySupport::customerDeliverableFolderName() agar folder existing tetap match.
     */
    public function customerDeliverableFolderName(): ?string
    {
        $this->loadMissing('customer.basicData');

        if (!$this->customer || !$this->customer->basicData) {
            return null;
        }

        return str_pad((string) $this->customer_id, 3, '0', STR_PAD_LEFT)
            . ' ' . strtoupper($this->customer->basicData->name_1);
    }

    // Relasi ke Employee (Ticket Lead)
    public function ticketLead()
    {
        return $this->belongsTo(Employee::class, 'ticket_lead_id', 'employee_id');
    }

    public function ticketMembers()
    {
        return $this->hasMany(TicketMember::class, 'ticket_id', 'ticket_id');
    }

    public function confirmation()
    {
        return $this->hasOne(TicketConfirmation::class, 'ticket_id', 'ticket_id')
            ->latest();
    }

    public function mandaysHistory()
    {
        return $this->hasMany(MandaysHistory::class, 'ticket_id', 'ticket_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Isi man_days sementara (1 MD per orang: lead + member aktif) supaya progress
     * tidak tampil "-" sebelum ada man_days "asli". Tidak menambah kolom baru —
     * status "sudah asli atau belum" diturunkan dari data yang sudah ada:
     * proposal mandays yang approved, riwayat man_days manual, atau konfirmasi
     * take-ticket yang sudah confirmed. Kalau salah satu itu ada, method ini
     * tidak menyentuh man_days sama sekali.
     */
    public function refreshPlaceholderManDays(): void
    {
        if ($this->hasRealManDays()) {
            return;
        }

        $headcount = ($this->ticket_lead_id ? 1 : 0) + $this->members()->count();

        if ($headcount === 0) {
            return;
        }

        $this->update(['man_days' => $headcount]);
    }

    /**
     * True kalau man_days berasal dari nilai asli (bukan placeholder headcount):
     * proposal Resolution Days / Customer Mandays yang approved, atau take-ticket
     * yang sudah dikonfirmasi (man_days dari form assignment).
     *
     * Catatan: admin yang mengedit man_days manual lewat form edit tiket
     * (bukan lewat salah satu alur di atas) tidak punya penanda persisten di
     * skema saat ini, jadi nilai itu bisa tertimpa lagi oleh placeholder kalau
     * lead/member berubah sesudahnya.
     */
    public function hasRealManDays(): bool
    {
        if ($this->resolution_days_status === 'approved' || $this->mandays_proposal_status === 'approved') {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('ticket_confirmation')
            ->where('ticket_id', $this->ticket_id)
            ->where('status', 'confirmed')
            ->exists();
    }

    /**
     * Jaga proposal Resolution Days berstatus draft supaya punya 1 baris
     * consultant_mandays_detail per orang aktif (lead + member). mandays
     * disimpan 0 (bukan 1) supaya form "Propose Resolution Days" tampil
     * kosong/baru bagi PIC — angka "1 md" yang tampil di My Task/Consultant
     * Workload dihasilkan terpisah oleh freeze display, bukan dari kolom ini.
     * Tujuannya supaya tombol expand/edit progress per-orang di My Task &
     * Consultant Workload bisa dipakai sebelum PIC benar-benar mengajukan
     * proposal. Tidak membuat tabel baru — reuse consultant_mandays/
     * consultant_mandays_detail yang sudah ada. Tidak pernah menyentuh
     * proposal yang statusnya bukan draft (sedang direview/sudah diputuskan).
     */
    public function syncDraftResolutionMembers(): void
    {
        if ($this->hasRealManDays() || !$this->ticket_lead_id) {
            return;
        }

        $targetIds = \Illuminate\Support\Facades\DB::table('ticket_member')
            ->where('ticket_id', $this->ticket_id)
            ->where('is_active', true)
            ->pluck('employee_id')
            ->push($this->ticket_lead_id)
            ->unique()
            ->values();

        $proposal = \App\Models\ConsultantMandays::where('ticket_id', $this->ticket_id)
            ->latestPerTicket()
            ->first();

        if ($proposal && $proposal->status !== 'draft') {
            return;
        }

        if (!$proposal) {
            $proposal = \App\Models\ConsultantMandays::create([
                'ticket_id'            => $this->ticket_id,
                'proposed_by_agent_id' => $this->ticket_lead_id,
                'proposed_at'          => now(),
                'last_edited_at'       => now(),
                'status'               => 'draft',
                'total_mandays'        => 0,
            ]);
        }

        $existingDetails = $proposal->details()->get()->keyBy('employee_id');

        foreach ($targetIds as $empId) {
            if (!$existingDetails->has($empId)) {
                // mandays disimpan 0 (bukan 1) supaya form "Propose Resolution Days"
                // tampil kosong/baru bagi PIC — angka "1 md" yang ditampilkan di
                // My Task/Consultant Workload dihasilkan terpisah oleh freeze display
                // (lihat ConsultantWorkloadController::consultantDetailsForTickets()),
                // bukan dari kolom ini.
                \App\Models\ConsultantMandaysDetail::create([
                    'consultant_mandays_id' => $proposal->id,
                    'employee_id'           => $empId,
                    'mandays'               => 0,
                    'additional_mandays'    => 0,
                    'approved_additional'   => 0,
                ]);
            }
        }

        $proposal->details()->whereNotIn('employee_id', $targetIds)->delete();

        $proposal->update([
            'total_mandays'  => $proposal->details()->sum('mandays'),
            'last_edited_at' => now(),
        ]);
    }

    // Relasi Many-to-Many ke Employee (Support Members/Pendamping)
    /** Active members only — digunakan di semua logika bisnis dan tampilan ticket list */
    public function members()
    {
        return $this->belongsToMany(
            Employee::class,
            'ticket_member',
            'ticket_id',
            'employee_id',
            'ticket_id',
            'employee_id'
        )->withTimestamps()->withPivot('is_active')->wherePivot('is_active', true);
    }

    /** Semua members (aktif + nonaktif) — digunakan untuk manajemen member di ticket detail */
    public function allMembers()
    {
        return $this->belongsToMany(
            Employee::class,
            'ticket_member',
            'ticket_id',
            'employee_id',
            'ticket_id',
            'employee_id'
        )->withTimestamps()->withPivot('is_active');
    }

    /**
     * ID tiket di mana employee ini ditugaskan — sebagai Lead, Member
     * (ticket_member aktif), atau punya alokasi di consultant_mandays_detail.
     * Tidak memfilter status/deleted/hidden — caller yang menerapkan filter itu
     * di query final.
     */
    public static function assignedTicketIds(int $employeeId): \Illuminate\Support\Collection
    {
        $leadIds = static::query()
            ->where('ticket_lead_id', $employeeId)
            ->pluck('ticket_id');

        $memberIds = \Illuminate\Support\Facades\DB::table('ticket_member')
            ->where('employee_id', $employeeId)
            ->pluck('ticket_id');

        $mandaysIds = \Illuminate\Support\Facades\DB::table('consultant_mandays_detail as cmd')
            ->join('consultant_mandays as cm', 'cm.id', '=', 'cmd.consultant_mandays_id')
            ->where('cmd.employee_id', $employeeId)
            ->pluck('cm.ticket_id');

        return $leadIds->merge($memberIds)->merge($mandaysIds)->unique()->values();
    }

    // Scope untuk filter berdasarkan status
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'inprocess');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    // Scope untuk filter berdasarkan priority
    public function scopeHighPriority($query)
    {
        return $query->where('ticket_priority', 'High');
    }

    // Accessor untuk status label
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'open'                    => 'Open',
            'inprocess'               => 'Inprocess',
            'waiting_on_customer'     => 'Waiting on Customer',
            'waiting_on_3rd_party'    => 'Waiting on 3rd Party',
            'waiting_to_confirmation' => 'Waiting to Confirmation',
            'hold'                    => 'Hold',
            'cancelled'               => 'Cancelled',
            'closed'                  => 'Closed',
            default                   => ucfirst($this->status ?? 'Unknown'),
        };
    }

    // Accessor untuk priority badge color
    public function getPriorityColorAttribute()
    {
        return match ($this->ticket_priority) {
            'Low' => 'gray',
            'High' => 'red',
            'Medium' => 'blue',
            default => 'gray'
        };
    }

    // Relasi ke pesan-pesan tiket
    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id', 'ticket_id')
            ->orderBy('created_at', 'asc');
    }

    // Relasi ke Delivery Support melalui activities
    public function deliverySupportActivities()
    {
        return $this->hasMany(DeliverySupportActivity::class, 'ticket_id', 'ticket_id');
    }

    // ── SLA ──────────────────────────────────────────────────────────────────

    public function sla()
    {
        return $this->hasOne(TicketSla::class, 'ticket_id', 'ticket_id');
    }

    public function isSlaEligible(): bool
    {
        // Tiket internal tidak punya komitmen SLA ke customer.
        return !$this->isInternal()
            && $this->ticket_type !== null
            && $this->ticket_priority !== null
            && $this->scale !== null;
    }

    public function getSlaMode(): string
    {
        return in_array($this->ticket_type, self::slaFullTypes()) ? 'full' : 'response_only';
    }

    public static function slaFullTypes(): array
    {
        return ['Incident', 'Service Request'];
    }

    // Get delivery supports via activities
    public function deliverySupports()
    {
        return DeliverySupport::whereHas('activities', function ($query) {
            $query->where('ticket_id', $this->ticket_id);
        });
    }
}
