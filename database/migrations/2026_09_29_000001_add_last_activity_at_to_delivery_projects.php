<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Kolom "Last Update Date" di list Project Delivery dulu membaca
 * delivery_projects.updated_at. Kolom itu hanya berubah kalau BARIS HEADER
 * project yang disimpan, sehingga aktivitas di section anak (Issue, Risk,
 * WRICEF, Cost, Term of Payment, Document, Team, Planning/Activity) tidak
 * pernah tercatat — sementara proses sistem (buka halaman detail, scheduler
 * OneDrive) justru bisa menggesernya.
 *
 * `last_activity_at` = kapan terakhir USER mengubah sesuatu di project ini,
 * diisi oleh DeliveryProject::markActivity() (lihat juga trait
 * App\Models\Concerns\TouchesProjectActivity). updated_at tetap murni
 * timestamp teknis Laravel.
 *
 * Backfill: nilai terbaru antara updated_at/created_at project dan updated_at
 * setiap tabel anak, supaya list langsung akurat sejak hari pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('updated_at')->index();
        });

        $latest = [];

        $bump = function ($projectId, $value) use (&$latest) {
            if (!$projectId || !$value) {
                return;
            }
            $value = (string) $value;
            if (!isset($latest[$projectId]) || $value > $latest[$projectId]) {
                $latest[$projectId] = $value;
            }
        };

        foreach (DB::table('delivery_projects')->get(['id', 'created_at', 'updated_at']) as $p) {
            $bump($p->id, $p->created_at);
            $bump($p->id, $p->updated_at);
        }

        // Tabel anak yang punya delivery_projects_id langsung.
        $direct = [
            'documents',
            'delivery_project_issues',
            'delivery_project_risks',
            'delivery_project_wricefs',
            'delivery_project_costs',
            'delivery_project_payment_terms',
            'delivery_project_planning',
            'delivery_project_activities',
            'delivery_project_employee',
            'delivery_project_updates',
            'delivery_project_phases',
        ];

        foreach ($direct as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)
                ->whereNotNull('delivery_projects_id')
                ->selectRaw('delivery_projects_id AS pid, MAX(updated_at) AS m')
                ->groupBy('delivery_projects_id')
                ->get()
                ->each(fn ($r) => $bump($r->pid, $r->m));
        }

        // Tabel anak tidak langsung: cost item (via cost) dan activity stage (via planning).
        if (Schema::hasTable('delivery_project_cost_items')) {
            DB::table('delivery_project_cost_items as i')
                ->join('delivery_project_costs as c', 'c.id', '=', 'i.delivery_project_cost_id')
                ->selectRaw('c.delivery_projects_id AS pid, MAX(i.updated_at) AS m')
                ->groupBy('c.delivery_projects_id')
                ->get()
                ->each(fn ($r) => $bump($r->pid, $r->m));
        }

        if (Schema::hasTable('activity_stages')) {
            DB::table('activity_stages as s')
                ->join('delivery_project_planning as pl', 'pl.id', '=', 's.planning_id')
                ->selectRaw('pl.delivery_projects_id AS pid, MAX(s.updated_at) AS m')
                ->groupBy('pl.delivery_projects_id')
                ->get()
                ->each(fn ($r) => $bump($r->pid, $r->m));
        }

        foreach ($latest as $projectId => $value) {
            DB::table('delivery_projects')
                ->where('id', $projectId)
                ->update(['last_activity_at' => $value]);
        }
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};
