<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationDetail;
use App\Models\KpiIndicator;
use App\Models\KpiTemplate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KpiSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding KPI Templates, Indicators, and Evaluation data...');

        // Clear existing KPI data to allow clean re-seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        KpiEvaluationDetail::truncate();
        KpiEvaluation::truncate();
        KpiIndicator::truncate();
        KpiTemplate::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── 1. Default ESS / USR Self-Assessment Template (Global for All Staff) ──
        $templateSelf = KpiTemplate::create([
            'name'        => 'Default ESS / USR Staff Self-Assessment',
            'description' => 'Evaluasi mandiri bulanan untuk seluruh karyawan (ESS/USR) tanpa memandang role khusus.',
            'role_id'     => null, // global / all staff
            'period_type' => 'monthly',
            'target_type' => 'self',
            'is_active'   => true,
            'created_by'  => 1,
            'updated_by'  => 1,
        ]);

        $indicatorsSelf = [
            ['name' => 'Penyelesaian Deliverable & Task Bulanan',   'description' => '>= 90% task selesai tepat waktu dan sesuai acceptance criteria', 'measurement_unit' => '%',     'target_value' => 90, 'weight' => 20, 'order_seq' => 1],
            ['name' => 'Penguasaan Materi & Domain',              'description' => 'Learning plan bulanan selesai; skor coaching/quiz >= 80',         'measurement_unit' => 'score', 'target_value' => 80, 'weight' => 15, 'order_seq' => 2],
            ['name' => 'Analisis Masalah & Kualitas Solusi',      'description' => 'Analisis terdokumentasi; opsi solusi jelas; minim revisi',        'measurement_unit' => 'score', 'target_value' => 85, 'weight' => 15, 'order_seq' => 3],
            ['name' => 'Dokumentasi, Komunikasi & Reporting',    'description' => 'Report mingguan/bulanan lengkap dan update stakeholder',          'measurement_unit' => '%',     'target_value' => 90, 'weight' => 15, 'order_seq' => 4],
            ['name' => 'Kolaborasi Tim & Profesionalisme',         'description' => 'Aktif berkoordinasi, responsif, dan menindaklanjuti feedback',     'measurement_unit' => 'score', 'target_value' => 85, 'weight' => 10, 'order_seq' => 5],
            ['name' => 'Client Orientation & Service Mindset',    'description' => 'Komunikasi client/user profesional dan kebutuhan tercatat',       'measurement_unit' => 'score', 'target_value' => 80, 'weight' => 10, 'order_seq' => 6],
            ['name' => 'Disiplin, Attendance & Timesheet',         'description' => 'Attendance dan timesheet bulanan lengkap serta tepat waktu',      'measurement_unit' => '%',     'target_value' => 95, 'weight' => 10, 'order_seq' => 7],
            ['name' => 'Initiative & Continuous Improvement',     'description' => 'Minimal 1 ide perbaikan atau lesson learned per bulan',            'measurement_unit' => 'score', 'target_value' => 80, 'weight' => 5,  'order_seq' => 8],
        ];

        foreach ($indicatorsSelf as $ind) {
            KpiIndicator::create(array_merge(['template_id' => $templateSelf->id], $ind));
        }

        // ── 2. Supervisor Evaluation Templates ─────────────────────────────
        $templateSup1 = KpiTemplate::create([
            'name'        => 'General Staff Supervisor Evaluation',
            'description' => 'Penilaian kinerja bulanan oleh atasan langsung untuk staf umum.',
            'role_id'     => null, // global
            'period_type' => 'monthly',
            'target_type' => 'supervisor',
            'is_active'   => true,
            'created_by'  => 1,
            'updated_by'  => 1,
        ]);

        $indicators1 = [
            ['name' => 'Task & Project Delivery Quality',    'description' => 'Quality and timeliness of assigned tasks',            'measurement_unit' => '%',     'target_value' => 95, 'weight' => 30, 'order_seq' => 1],
            ['name' => 'Attendance & Punctuality',          'description' => 'On-time attendance and shift adherence',              'measurement_unit' => '%',     'target_value' => 100,'weight' => 20, 'order_seq' => 2],
            ['name' => 'Team Collaboration & Communication','description' => 'Effectiveness in cross-team communication',          'measurement_unit' => 'score', 'target_value' => 85, 'weight' => 20, 'order_seq' => 3],
            ['name' => 'Skill Development & Innovation',     'description' => 'Participation in training and process improvements',   'measurement_unit' => 'score', 'target_value' => 80, 'weight' => 15, 'order_seq' => 4],
            ['name' => 'SLA & Customer Satisfaction',       'description' => 'Meeting SLA targets and stakeholder satisfaction',    'measurement_unit' => '%',     'target_value' => 90, 'weight' => 15, 'order_seq' => 5],
        ];

        foreach ($indicators1 as $ind) {
            KpiIndicator::create(array_merge(['template_id' => $templateSup1->id], $ind));
        }

        // ── 3. Seed Evaluations ──────────────────────────────────────────────
        $employeeIds = [1, 4, 6, 7, 8, 10, 12, 14, 15, 209];
        $supervisorId = 1;
        $periods = ['2026-08', '2026-07', '2026-06', '2026-05', '2026-04'];

        $sampleNotes = [
            'self' => [
                'Completed all assigned tasks on schedule.',
                'Maintained 100% attendance and timesheet entries.',
                'Active coordination with project stakeholders.',
                'Achieved high customer satisfaction on deliverables.',
            ],
            'supervisor' => [
                'Excellent performance. Strong initiative.',
                'High quality deliverables and positive user feedback.',
                'Good technical skills, keep up the proactive work.',
                'Solid overall contribution.',
            ],
        ];

        foreach ($periods as $pIndex => $period) {
            foreach ($employeeIds as $empIdx => $empId) {
                $tmpl = ($empIdx % 2 === 0) ? $templateSelf : $templateSup1;
                $tmpl->load('indicators');

                if ($period === '2026-08') {
                    $statusMap = [
                        1   => KpiEvaluation::STATUS_DRAFT, // Unanswered for Employee 1
                        4   => KpiEvaluation::STATUS_HR_APPROVED,
                        6   => KpiEvaluation::STATUS_HR_APPROVED,
                        7   => KpiEvaluation::STATUS_SELF_ASSESSED,
                        8   => KpiEvaluation::STATUS_COMPLETED,
                        10  => KpiEvaluation::STATUS_HR_APPROVED,
                        12  => KpiEvaluation::STATUS_REVIEWED,
                        14  => KpiEvaluation::STATUS_DRAFT,
                        15  => KpiEvaluation::STATUS_HR_APPROVED,
                        209 => KpiEvaluation::STATUS_DRAFT, // Unanswered for ESS Female (Siti Rahma)
                    ];
                    $status = $statusMap[$empId] ?? KpiEvaluation::STATUS_HR_APPROVED;
                } else {
                    $status = KpiEvaluation::STATUS_HR_APPROVED;
                }

                $now = now();
                $hasSelf = in_array($status, ['self_assessed', 'reviewed', 'completed', 'hr_approved']);
                $hasSup  = in_array($status, ['reviewed', 'completed', 'hr_approved']);

                $eval = KpiEvaluation::create([
                    'employee_id'     => $empId,
                    'template_id'     => $tmpl->id,
                    'period_month'    => $period,
                    'supervisor_id'   => $supervisorId,
                    'status'          => $status,
                    'overall_score'   => null,
                    'self_assessed_at'=> $hasSelf ? $now->copy()->subDays(5) : null,
                    'reviewed_at'     => $hasSup ? $now->copy()->subDays(2) : null,
                    'hr_approved_at'  => $status === 'hr_approved' ? $now->copy()->subDay() : null,
                    'hr_approved_by'  => $status === 'hr_approved' ? 1 : null,
                    'hr_notes'        => $status === 'hr_approved' ? 'Approved by HR.' : null,
                    'created_by'      => 1,
                ]);

                $totalScore = 0;
                foreach ($tmpl->indicators as $ind) {
                    $star = rand(3, 5);
                    $score = $star * 20;
                    $weighted = $hasSup ? round(($ind->weight * $score) / 100, 2) : null;

                    if ($weighted) {
                        $totalScore += $weighted;
                    }

                    KpiEvaluationDetail::create([
                        'evaluation_id'           => $eval->id,
                        'indicator_id'            => $ind->id,
                        'star_rating'             => $hasSup ? $star : ($hasSelf ? $star : null),
                        'self_achievement'        => $hasSelf ? $score : null,
                        'actual_achievement'      => $hasSelf ? 'Bagus' : null,
                        'self_notes'              => $hasSelf ? $sampleNotes['self'][rand(0, 3)] : null,
                        'self_submitted_at'       => $hasSelf ? $now->copy()->subDays(5) : null,
                        'supervisor_score'        => $hasSup ? $score : null,
                        'supervisor_notes'        => $hasSup ? $sampleNotes['supervisor'][rand(0, 3)] : null,
                        'supervisor_submitted_at' => $hasSup ? $now->copy()->subDays(2) : null,
                        'weighted_score'          => $weighted,
                    ]);
                }

                if ($status === KpiEvaluation::STATUS_HR_APPROVED) {
                    $eval->overall_score = round($totalScore, 2);
                    $eval->save();
                }
            }
        }

        $this->command?->info('KPI Seeder completed successfully!');
    }
}
