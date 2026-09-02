<?php

namespace App\Support;

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
 * Kandidat Ticket Lead / member: kalau tiket sudah punya module_id, dibatasi ke
 * "anggota module" itu (employee ber-qualification di module tsb + module
 * lead-nya). Kalau module_id kosong (tiket lama), pakai daftar eligible penuh.
 * Role manajemen (ticket.assign-pic) tidak lewat sini — mereka selalu pakai
 * daftar eligible penuh seperti sebelumnya.
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
     * yang punya qualification di module tsb, digabung dengan module lead-nya.
     *
     * @return array<int, array{employee_id:int, name:string}>
     */
    public static function moduleCandidates(?int $moduleId): array
    {
        if (!$moduleId) {
            return [];
        }

        $qualified = DB::table('employee as e')
            ->join('employee_qualification as eq', 'eq.employee_id', '=', 'e.employee_id')
            ->where('eq.module_id', $moduleId)
            ->where('e.is_active', true)
            ->pluck('e.employee_id');

        $leads = ModuleLead::where('module_id', $moduleId)->pluck('employee_id');

        $ids = $qualified->merge($leads)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('employee as e')
            ->leftJoin('employee_basic_data as bd', 'e.employee_id', '=', 'bd.employee_id')
            ->whereIn('e.employee_id', $ids)
            ->where('e.is_active', true)
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
     * @return array<int, int>
     */
    public static function moduleCandidateIds(?int $moduleId): array
    {
        return array_map(
            static fn (array $c) => $c['employee_id'],
            self::moduleCandidates($moduleId)
        );
    }

    /**
     * Kandidat (Ticket Lead / member) untuk jalur lead di sebuah tiket:
     *  - tiket punya module_id  → anggota module itu
     *  - tiket tanpa module_id  → daftar eligible penuh (fallback tiket lama),
     *    memakai menu permission yang relevan
     *    ('ticket.eligible-ticket-lead' atau 'ticket.eligible-ticket-member').
     *
     * @return array<int, array{employee_id:int, name:string}>
     */
    public static function candidatesForTicket(Ticket $ticket, string $eligibleMenuSlug): array
    {
        if ($ticket->module_id) {
            return self::moduleCandidates($ticket->module_id);
        }

        return Employee::withMenuPermission($eligibleMenuSlug)
            ->where('is_active', 1)
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
}
