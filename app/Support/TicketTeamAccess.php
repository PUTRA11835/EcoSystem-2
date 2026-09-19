<?php

namespace App\Support;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\ModuleLead;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Akses "team lead di lapangan" untuk sebuah tiket — dipakai bareng oleh
 * TicketController (endpoint assign Ticket Lead & add/remove member) dan
 * TicketViewController/ticket.show (menampilkan tombol + daftar kandidat).
 *
 * Aturannya murni data-driven, TIDAK lewat menu permission:
 *   - Ticket Lead tiket itu sendiri (ticket_lead_id), ATAU
 *   - Employee yang jadi Module Lead di module MANA PUN (tabel module_leads) —
 *     berlaku untuk semua tiket, tidak dibatasi ticket.module_id.
 *
 * Kandidat Ticket Lead / member: kalau tiket sudah punya modul (satu atau
 * lebih — lihat Ticket::modules()), dibatasi ke gabungan "anggota module" dari
 * SEMUA modul tiket itu (employee ber-qualification di salah satu modul +
 * module lead-nya). Kalau tiket tidak punya modul sama sekali (tiket lama),
 * pakai daftar eligible penuh. Role manajemen (ticket.assign-pic) tidak lewat
 * sini — mereka selalu pakai daftar eligible penuh seperti sebelumnya.
 */
final class TicketTeamAccess
{
    /**
     * Apakah $employeeId boleh mengelola tim tiket ini lewat jalur lead.
     */
    public static function canManageAsLead(?int $employeeId, Ticket $ticket): bool
    {
        if (!$employeeId) {
            return false;
        }

        if ((int) $ticket->ticket_lead_id === (int) $employeeId) {
            return true;
        }

        return self::isModuleLead($employeeId);
    }

    /**
     * Apakah $employeeId adalah Ticket Lead ATAU member aktif tiket ini —
     * beda dari canManageAsLead() di atas: TIDAK ikut menghitung Module Lead
     * sistem-wide yang belum tentu tersangkut tiket ini, dan TIDAK menghitung
     * member yang sudah tidak aktif (di-remove) dari tim. Dipakai untuk
     * gerbang fitur yang memang harus dibatasi ke "orang yang benar-benar
     * mengerjakan tiket ini", bukan sekadar hak manajemen tim.
     */
    public static function isLeadOrMember(?int $employeeId, Ticket $ticket): bool
    {
        if (!$employeeId) {
            return false;
        }

        if ((int) $ticket->ticket_lead_id === (int) $employeeId) {
            return true;
        }

        // wherePivot(), bukan where(): members() adalah belongsToMany yang
        // JOIN ke tabel employee — 'employee_id' polos ambigu (ada di kedua
        // tabel employee DAN ticket_member), wherePivot() menegaskan kolom
        // di tabel pivot (ticket_member) yang dimaksud.
        return $ticket->members()->wherePivot('employee_id', $employeeId)->exists();
    }

    /**
     * Apakah employee ini jadi Module Lead di module mana pun.
     */
    public static function isModuleLead(?int $employeeId): bool
    {
        if (!$employeeId) {
            return false;
        }

        return ModuleLead::where('employee_id', $employeeId)->exists();
    }

    /**
     * Daftar kandidat (Ticket Lead / member) untuk jalur lead: employee aktif
     * yang punya qualification di SALAH SATU modul yang diberikan, digabung
     * dengan module lead dari modul-modul itu. Satu tiket bisa punya lebih
     * dari satu modul (Ticket::modules()) — kandidatnya adalah UNION lintas
     * semua modul itu, bukan irisan.
     *
     * @param array<int, int> $moduleIds
     * @return array<int, array{employee_id:int, name:string}>
     */
    public static function moduleCandidates(array $moduleIds): array
    {
        $moduleIds = array_values(array_unique(array_filter($moduleIds)));

        if (empty($moduleIds)) {
            return [];
        }

        $qualified = DB::table('employee as e')
            ->join('employee_qualification as eq', 'eq.employee_id', '=', 'e.employee_id')
            ->whereIn('eq.module_id', $moduleIds)
            ->where('e.is_active', true)
            ->pluck('e.employee_id');

        $leads = ModuleLead::whereIn('module_id', $moduleIds)->pluck('employee_id');

        $ids = $qualified->merge($leads)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('employee as e')
            ->leftJoin('employee_basic_data as bd', 'e.employee_id', '=', 'bd.employee_id')
            ->whereIn('e.employee_id', $ids)
            ->where('e.is_active', true)
            // Employee yang di-block atau ditandai untuk dihapus di Basic Data
            // tidak boleh muncul sebagai kandidat, meskipun dia qualified/module lead.
            // Employee TANPA basic data (leftJoin, bd.employee_id NULL) tetap
            // eligible — disamakan persis dengan Employee::scopeEligibleForTicketTeam()
            // supaya dropdown kandidat ini dan validasi assignment akhir
            // (TicketTeamAccess::isEligibleEmployee(), yang lewat scope itu) tidak
            // pernah berselisih soal siapa yang dianggap eligible.
            ->where(function ($q) {
                $q->whereNull('bd.employee_id')
                  ->orWhere(function ($q2) {
                      $q2->where('bd.block', false)->where('bd.deletion_flag', false);
                  });
            })
            ->select(
                'e.employee_id',
                DB::raw("TRIM(CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))) as name")
            )
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'employee_id' => (int) $row->employee_id,
                'name'        => $row->name !== '' ? $row->name : ('Employee #' . $row->employee_id),
            ])
            ->all();
    }

    /**
     * Set employee_id kandidat module — untuk validasi server-side.
     *
     * @param array<int, int> $moduleIds
     * @return array<int, int>
     */
    public static function moduleCandidateIds(array $moduleIds): array
    {
        return array_map(
            static fn (array $c) => $c['employee_id'],
            self::moduleCandidates($moduleIds)
        );
    }

    /**
     * Kandidat (Ticket Lead / member) untuk jalur lead di sebuah tiket:
     *  - tiket punya modul (satu atau lebih) → gabungan anggota semua modul itu
     *  - tiket tanpa modul sama sekali       → daftar eligible penuh (fallback
     *    tiket lama), memakai menu permission yang relevan
     *    ('ticket.eligible-ticket-lead' atau 'ticket.eligible-ticket-member').
     *
     * @return array<int, array{employee_id:int, name:string}>
     */
    public static function candidatesForTicket(Ticket $ticket, string $eligibleMenuSlug): array
    {
        if ($ticket->module_id) {
            return self::moduleCandidates($ticket->modules->pluck('id')->all());
        }

        return Employee::withMenuPermission($eligibleMenuSlug)
            ->eligibleForTicketTeam()
            ->with('basicData:employee_id,first_name,last_name')
            ->get()
            ->map(fn ($e) => [
                'employee_id' => (int) $e->employee_id,
                'name'        => trim(($e->basicData->first_name ?? '') . ' ' . ($e->basicData->last_name ?? '')),
            ])
            ->filter(fn ($e) => $e['name'] !== '')
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Validasi akhir sebelum benar-benar meng-assign employee sebagai Ticket
     * Lead / member — di luar filter dropdown, supaya request langsung ke API
     * (bypass UI) tidak bisa menunjuk employee yang nonaktif, diblokir, atau
     * ditandai untuk dihapus.
     */
    public static function isEligibleEmployee(int $employeeId): bool
    {
        return Employee::eligibleForTicketTeam()->where('employee_id', $employeeId)->exists();
    }

    /**
     * Siapa boleh pakai AI Research untuk tiket ini: Ticket Lead/member tiket
     * ini, ATAU salah satu role "privileged" (lihat isPrivilegedForAiResearch()
     * — EC Administrator + role yang sama-sama bisa approve di Ticket
     * Validation). Dipakai bareng oleh AiResearchController::openForTicket()
     * dan assertCanAccessConversation() (gerbang endpoint) dan
     * ticket/show.blade.php (tampil/sembunyi tombol) — satu tempat supaya
     * ketiganya tidak bisa diam-diam melenceng kalau aturan bypass ini
     * berubah nanti. $isPrivileged dihitung oleh caller lewat
     * isPrivilegedForAiResearch() sendiri (Employee/SessionUser punya
     * hasRole()/hasAnyRole() masing-masing, tidak ada tipe yang sama-sama
     * dipakai semua caller untuk digenggam di sini).
     */
    public static function canAccessAiResearch(?int $employeeId, Ticket $ticket, bool $isPrivileged): bool
    {
        return $isPrivileged || self::isLeadOrMember($employeeId, $ticket);
    }

    /**
     * Role yang otomatis lolos gerbang AI Research untuk SEMUA tiket,
     * terlepas dari status lead/member-nya di tiket itu:
     *   - EC Administrator (sudah begitu sejak awal), DITAMBAH
     *   - RoleId::STAGING_GROUP — Delivery Support Head, Delivery Helpdesk,
     *     Delivery RPMO Head, Delivery Support Manager (MO Support): persis
     *     role yang bisa approve di Ticket Validation.
     *
     * Alasan grup kedua: merekalah yang men-trigger pembuatan room BERSAMA
     * ini lewat approve() (lihat StagingTicketController::
     * createSharedAiResearchRoom()) — tidak masuk akal kalau justru mereka
     * sendiri tidak bisa membuka lagi room yang baru saja mereka buat, hanya
     * karena kebetulan bukan Ticket Lead/member tiket itu.
     *
     * hasAnyRole() dicek (bukan $employee->role->role_id / primary role
     * saja) supaya employee dengan banyak role tetap kebagian akses walau
     * role privileged itu bukan role utamanya.
     *
     * Parameter sengaja TIDAK diketik Employee — dua caller-nya pakai tipe
     * berbeda yang sama-sama punya hasRole(int):bool/hasAnyRole(array):bool
     * (Employee di controller, SessionUser di Blade), lihat catatan di
     * canAccessAiResearch() di atas. Duck typing di sini, bukan interface
     * baru yang memaksa kedua kelas itu saling terikat cuma demi satu method.
     *
     * @param Employee|\App\Support\SessionUser $actor
     */
    public static function isPrivilegedForAiResearch($actor): bool
    {
        return $actor->hasRole(RoleId::EC_ADMINISTRATOR->value)
            || $actor->hasAnyRole(RoleId::STAGING_GROUP);
    }
}
