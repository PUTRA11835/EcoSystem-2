<?php

namespace Database\Seeders\Kpi;

use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationDetail;
use App\Models\KpiTemplate;
use Illuminate\Database\Seeder;

/**
 * DEMO / TESTING ONLY — fake sample KPI evaluations for a fixed set of
 * employee IDs (1, 4, 6, 7, 8, 10, 12, 14, 15, 209), covering the last 5
 * months at various statuses (draft, self-assessed, reviewed, approved).
 *
 * Do NOT run this on a production install: the employee IDs are specific to
 * this project's seeded test data (see EssTestAccountsSeeder / EmployeeSeeder)
 * and are not portable — on another database those IDs may belong to
 * different real employees, or not exist at all.
 *
 * Requires KpiTemplateSeeder to have run first (creates the Self-Assessment
 * and Leader Assessment templates this seeder assigns evaluations against).
 */
class KpiDemoEvaluationSeeder extends Seeder
{
    public function run(): void
    {
        $templateSelf = KpiTemplate::where('target_type', 'self')
            ->where('name', 'like', 'Form Penilaian PA 2026%')
            ->first();
        $templateLead = KpiTemplate::where('target_type', 'supervisor')
            ->where('name', 'like', 'Form Penilaian PA 2026%')
            ->first();

        if (!$templateSelf || !$templateLead) {
            $this->command?->error('Run KpiTemplateSeeder first — the Self-Assessment / Leader Assessment templates were not found.');
            return;
        }

        $this->command?->info('Seeding demo KPI evaluations for test employees...');

        $employeeIds  = [1, 4, 6, 7, 8, 10, 12, 14, 15, 209];
        $supervisorId = 1;
        $periods      = ['2026-08', '2026-07', '2026-06', '2026-05', '2026-04'];

        $sampleNotes = [
            'self' => [
                'Sudah menyelesaikan seluruh target bulan ini tepat waktu.',
                'Kehadiran dan pengisian timesheet lengkap.',
                'Aktif berkoordinasi dengan stakeholder proyek.',
                'Hasil kerja mendapat umpan balik positif dari pengguna.',
            ],
            'supervisor' => [
                'Kinerja sangat baik, inisiatif tinggi.',
                'Kualitas deliverable baik dan umpan balik pengguna positif.',
                'Kemampuan teknis baik, pertahankan sikap proaktif.',
                'Kontribusi keseluruhan solid.',
            ],
        ];

        foreach ($periods as $period) {
            foreach ($employeeIds as $empIdx => $empId) {
                $tmpl = ($empIdx % 2 === 0) ? $templateSelf : $templateLead;
                $tmpl->load('indicators');

                if ($period === '2026-08') {
                    $statusMap = [
                        1   => KpiEvaluation::STATUS_DRAFT,
                        4   => KpiEvaluation::STATUS_HR_APPROVED,
                        6   => KpiEvaluation::STATUS_HR_APPROVED,
                        7   => KpiEvaluation::STATUS_SELF_ASSESSED,
                        8   => KpiEvaluation::STATUS_COMPLETED,
                        10  => KpiEvaluation::STATUS_HR_APPROVED,
                        12  => KpiEvaluation::STATUS_REVIEWED,
                        14  => KpiEvaluation::STATUS_DRAFT,
                        15  => KpiEvaluation::STATUS_HR_APPROVED,
                        209 => KpiEvaluation::STATUS_DRAFT,
                    ];
                    $status = $statusMap[$empId] ?? KpiEvaluation::STATUS_HR_APPROVED;
                } else {
                    $status = KpiEvaluation::STATUS_HR_APPROVED;
                }

                $now     = now();
                $hasSelf = in_array($status, ['self_assessed', 'reviewed', 'completed', 'hr_approved']);
                $hasSup  = in_array($status, ['reviewed', 'completed', 'hr_approved']);

                $eval = KpiEvaluation::create([
                    'employee_id'      => $empId,
                    'template_id'      => $tmpl->id,
                    'period_month'     => $period,
                    'supervisor_id'    => $supervisorId,
                    'status'           => $status,
                    'overall_score'    => null,
                    'self_assessed_at' => $hasSelf ? $now->copy()->subDays(5) : null,
                    'reviewed_at'      => $hasSup ? $now->copy()->subDays(2) : null,
                    'hr_approved_at'   => $status === 'hr_approved' ? $now->copy()->subDay() : null,
                    'hr_approved_by'   => $status === 'hr_approved' ? 1 : null,
                    'hr_notes'         => $status === 'hr_approved' ? 'Disetujui oleh HR.' : null,
                    'created_by'       => 1,
                ]);

                $totalScore = 0;
                foreach ($tmpl->indicators as $ind) {
                    // Paragraph indicators are qualitative — no numeric score / weight.
                    if ($ind->isParagraph()) {
                        KpiEvaluationDetail::create([
                            'evaluation_id'     => $eval->id,
                            'indicator_id'      => $ind->id,
                            'self_notes'        => $hasSelf ? $sampleNotes['self'][rand(0, 3)] : null,
                            'self_submitted_at' => $hasSelf ? $now->copy()->subDays(5) : null,
                            'supervisor_notes'  => $hasSup ? $sampleNotes['supervisor'][rand(0, 3)] : null,
                        ]);
                        continue;
                    }

                    $maxStar  = $ind->effectiveMax() ?: 5;
                    $star     = rand((int) ceil($maxStar / 2), $maxStar);
                    $score    = round($star / $maxStar * 100, 2);
                    $weighted = $hasSup ? round(($ind->weight * $score) / 100, 2) : null;

                    if ($weighted) {
                        $totalScore += $weighted;
                    }

                    KpiEvaluationDetail::create([
                        'evaluation_id'           => $eval->id,
                        'indicator_id'            => $ind->id,
                        'star_rating'             => $hasSelf || $hasSup ? $star : null,
                        'self_achievement'        => $hasSelf ? $score : null,
                        'actual_achievement'      => $hasSelf ? 'Sesuai ekspektasi' : null,
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

        $this->command?->info('KPI Demo Evaluation Seeder completed successfully!');
    }
}
