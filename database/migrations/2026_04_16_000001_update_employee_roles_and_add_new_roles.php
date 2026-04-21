<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ── IDs 1-7 yang direname ──────────────────────────────────────────────────
    private array $renames = [
        1 => 'EC Administrator',
        2 => 'Delivery Support User',
        3 => 'EC User',
        4 => 'Delivery Project Head',
        5 => 'Delivery Support Head',
        6 => 'Delivery Support Service Helpdesk',
        7 => 'Delivery RPMO Head',
    ];

    private array $oldNames = [
        1 => 'Admin',
        2 => 'Employee',
        3 => 'Internship',
        4 => 'Head of Project',
        5 => 'Head of Support',
        6 => 'Helpdesk',
        7 => 'RPMO',
    ];

    // ── Role baru yang ditambahkan (ID akan di-auto-increment oleh DB) ─────────
    private array $newRoles = [
        'Delivery Operation Administrator',
        'Delivery Operation Director',
        'Delivery Operation Head',
        'Delivery Operation User',
        'Delivery Project Administrator',
        'Delivery Project Manager',
        'Delivery Project PIC',
        'Delivery Project User',
        'Delivery RPMO Administrator',
        'Delivery RPMO Manager',
        'Delivery RPMO User',
        'Delivery Support Administrator',
        'Delivery Support Manager',
        'Delivery Support PIC',
        'EC Director',
        'EC Head',
        'EC System Administrator',
        'HO Accounting Administrator',
        'HO Accounting Head',
        'HO Accounting User',
        'HO Control Administrator',
        'HO Control Head',
        'HO Control User',
        'HO Finance Administrator',
        'HO Finance Head',
        'HO Finance User',
        'HO GA Administrator',
        'HO GA Head',
        'HO GA User',
        'HO HR Administrator',
        'HO HR Head',
        'HO HR User',
        'HO Legal Administrator',
        'HO Legal Head',
        'HO Legal User',
        'HO Administrator',
        'HO Director',
        'HO User',
        'Sales Engagement Administrator',
        'Sales Engagement Head',
        'Sales Engagement User',
        'Sales Marketing Administrator',
        'Sales Marketing Head',
        'Sales Marketing Manager',
        'Sales Marketing User',
        'Sales Operation Administrator',
        'Sales Operation Director',
        'Sales Operation Account Executive',
        'Sales Operation Head',
        'Sales Operation User',
        'Sales Presales Administrator',
        'Sales Presales Head',
        'Sales Presales User',
        'User System Registered',
    ];

    public function up(): void
    {
        // ── Rename existing roles 1-7 ──────────────────────────────────────────
        foreach ($this->renames as $id => $newName) {
            DB::table('employee_role')->where('id', $id)->update(['name' => $newName]);
        }

        // ── Insert new roles (skip if already exists by name) ──────────────────
        $existing = DB::table('employee_role')->pluck('name')->map(fn($n) => strtolower($n))->toArray();

        $toInsert = collect($this->newRoles)
            ->filter(fn($name) => !in_array(strtolower($name), $existing))
            ->map(fn($name) => ['name' => $name])
            ->values()
            ->toArray();

        if (!empty($toInsert)) {
            DB::table('employee_role')->insert($toInsert);
        }
    }

    public function down(): void
    {
        // Restore original names 1-7
        foreach ($this->oldNames as $id => $oldName) {
            DB::table('employee_role')->where('id', $id)->update(['name' => $oldName]);
        }

        // Remove inserted new roles
        DB::table('employee_role')->whereIn('name', $this->newRoles)->delete();
    }
};
