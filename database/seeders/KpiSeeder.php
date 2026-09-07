<?php

namespace Database\Seeders;

use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationDetail;
use App\Models\KpiIndicator;
use App\Models\KpiScoringScale;
use App\Models\KpiTemplate;
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
        KpiScoringScale::truncate();
        KpiTemplate::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        /*
         * Question sets transcribed from the "Form Penilaian PA Tahun 2026 —
         * Penilaian 360" spreadsheets.
         *
         * Each row: [question, category ("Desc"), answer scale]
         *   'rating5' → Rating 1 (Tidak mencapai ekspektasi) … 5 (Jauh melebihi ekspektasi)
         *   'rating3' → Rating 1. Tidak mencapai ekspektasi; 2. Kurang dari ekspektasi; 3. Sesuai ekspektasi
         *   'text'    → Paragraph (qualitative, not scored — weight 0)
         */

        $capabilityBlock = [
            ['Kompeten dalam pekerjaan yang diminta dan mengetahui cara mengerjakannya', 'Kapabilitas - Pengetahuan dan Keterampilan Kerja', 'rating5'],
            ['Menunjukkan kemampuan untuk belajar dan mengaplikasikan kemampuan baru', 'Kapabilitas - Pengetahuan dan Keterampilan Kerja', 'rating5'],
            ['Membutuhkan bimbingan dan pengawasan yang minimal', 'Kapabilitas - Pengetahuan dan Keterampilan Kerja', 'rating5'],
            ['Menunjukkan hasil kerja yang diinginkan dalam waktu yang singkat', 'Kapabilitas - Efisiensi Kerja', 'rating5'],
            ['Mampu mempertahankan hasil kerja yang baik walau bekerja di bawah tekanan', 'Kapabilitas - Efisiensi Kerja', 'rating5'],
            ['Menunjukkan komitmen yang tinggi dalam bekerja', 'Kapabilitas - Efisiensi Kerja', 'rating5'],
            ['Kuantitas pekerjaan sesuai dengan target yang ditetapkan', 'Kapabilitas - Efisiensi Kerja', 'rating5'],
            ['Melaksanakan tugas dan tanggung jawab sesuai sistem dan prosedur', 'Kapabilitas - Kualitas Hasil Kerja', 'rating5'],
            ['Menunjukkan ketepatan kerja dan kesempurnaan hasil kerja', 'Kapabilitas - Kualitas Hasil Kerja', 'rating5'],
            ['Selalu mencari jalan baru untuk memperbaiki kualitas kerja', 'Kapabilitas - Kualitas Hasil Kerja', 'rating5'],
            ['Monitor dan melakukan evaluasi pekerjaan sendiri untuk memastikan mutunya', 'Kapabilitas - Kualitas Hasil Kerja', 'rating5'],
            ['Mampu merencanakan dan mengorganisir pekerjaan dengan baik', 'Kapabilitas - Perencanaan dan Pengorganisasian', 'rating5'],
            ['Menggunakan waktu secara efisien', 'Kapabilitas - Perencanaan dan Pengorganisasian', 'rating5'],
            ['Mengimplementasikan perubahan dengan baik', 'Kapabilitas - Perencanaan dan Pengorganisasian', 'rating5'],
        ];

        $leadershipBlock = [
            ['Mampu mengelola dan memotivasi tim / orang lain untuk bekerja dan mencapai target', 'Kapabilitas - Kepemimpinan', 'rating5'],
            ['Mampu mengembangkan kemampuan tim / orang lain untuk dapat berkontribusi lebih baik', 'Kapabilitas - Kepemimpinan', 'rating5'],
            ['Memiliki kemampuan menyelesaikan masalah dan memutuskan solusi permasalahan dan memastikan implementasi dari solusi tersebut berjalan dengan baik', 'Kapabilitas - Kepemimpinan', 'rating5'],
        ];

        $integrityBlock = [
            ['Mematuhi ketentuan dan peraturan yang berlaku di lingkungan kerja dan perintah atasan', 'Integritas - Kepatuhan terhadap Peraturan dan Etika Kerja', 'rating3'],
            ['Datang bekerja tepat waktu', 'Integritas - Kepatuhan terhadap Peraturan dan Etika Kerja', 'rating3'],
            ['Memakai pakaian kerja dan ID card sesuai dengan ketentuan yang ada', 'Integritas - Kepatuhan terhadap Peraturan dan Etika Kerja', 'rating3'],
            ['Tidak pernah absen tanpa alasan yang kuat dan jelas', 'Integritas - Kepatuhan terhadap Peraturan dan Etika Kerja', 'rating3'],
            ['Mempunyai rasa memiliki yang tinggi akan Perusahaan sehingga selalu mengutamakan kepentingan Perusahaan', 'Integritas - Kepatuhan terhadap Peraturan dan Etika Kerja', 'rating5'],
            ['Menjunjung tinggi ketulusan dan kejujuran dalam lingkungan kerja', 'Integritas - Pemahaman dan Penerapan Falsafah', 'rating5'],
            ['Bersikap saling menghormati dalam berinteraksi dan bekerja sama dengan rekan kerja, atasan, bawahan dan pihak eksternal', 'Integritas - Pemahaman dan Penerapan Falsafah', 'rating5'],
            ['Melayani dan bekerja dengan penuh rasa syukur, sepenuh hati dan cinta kasih', 'Integritas - Pemahaman dan Penerapan Falsafah', 'rating5'],
            ['Secara sukarela ikut aktif dalam kegiatan sosial yang diselenggarakan oleh yayasan', 'Integritas - Pemahaman dan Penerapan Falsafah', 'rating5'],
            ['Secara sukarela ikut berpartisipasi membantu pekerjaan yang bukan menjadi tanggung jawab utamanya', 'Integritas - Pemahaman dan Penerapan Falsafah', 'rating5'],
        ];

        $proactiveBlock = [
            ['Mampu mengidentifikasikan masalah dan aktif mencari solusi terhadap permasalahan', 'Proaktif - Proaktif dalam Bekerja', 'rating5'],
            ['Berani mengemukakan ide-ide dalam memecahkan masalah', 'Proaktif - Proaktif dalam Bekerja', 'rating5'],
            ['Bisa menerima ide-ide baru dan berani mencoba hal baru untuk meningkatkan efektifitas dan efisiensi pekerjaan', 'Proaktif - Proaktif dalam Bekerja', 'rating5'],
        ];

        $developmentBlock = [
            ['Saran untuk Pengembangan Diri di Tahun 2026', 'Saran Pengembangan', 'text'],
            ['Kebutuhan Pelatihan untuk Pengembangan Perusahaan di Tahun 2026', 'Kebutuhan Pelatihan', 'text'],
        ];

        $kpiRow = [['Penilaian KPI Bulan Berjalan', 'Penilaian Target Kerja Tahun 2026', 'rating5']];

        // 1. Self-Assessment — no leadership block
        $selfQuestions = array_merge(
            $kpiRow,
            $capabilityBlock,
            $integrityBlock,
            $proactiveBlock,
            $developmentBlock
        );

        // 2. Leader Assessment — capability block + leadership block
        $leadQuestions = array_merge(
            $kpiRow,
            $capabilityBlock,
            $leadershipBlock,
            $integrityBlock,
            $proactiveBlock,
            $developmentBlock
        );

        $templateSelf = KpiTemplate::create([
            'name'          => 'Form Penilaian PA 2026 — Self-Assessment (Penilaian 360)',
            'description'   => 'Formulir penilaian mandiri (self assessment) tahun 2026. Skala jawaban: Rating 1 (Tidak mencapai ekspektasi) sampai 5 (Jauh melebihi ekspektasi); sebagian item skala 1-3; dua item terakhir berupa paragraf.',
            'role_id'       => null,
            'period_type'   => 'monthly',
            'target_type'   => 'self',
            'score_divisor' => 5,
            'is_active'     => true,
            'created_by'    => 1,
            'updated_by'    => 1,
        ]);

        $templateLead = KpiTemplate::create([
            'name'          => 'Form Penilaian PA 2026 — Leader Assessment (Penilaian 360)',
            'description'   => 'Formulir penilaian oleh atasan / leader (leader assessment) tahun 2026. Sama dengan self-assessment ditambah blok "Kapabilitas - Kepemimpinan".',
            'role_id'       => null,
            'period_type'   => 'monthly',
            'target_type'   => 'supervisor',
            'score_divisor' => 5,
            'is_active'     => true,
            'created_by'    => 1,
            'updated_by'    => 1,
        ]);

        // ── Shared helpers ──────────────────────────────────────────────────────

        // Attach the standard 5-point "SKALA PENILAIAN" to a template.
        $seedScale = function (KpiTemplate $tmpl): void {
            foreach (KpiScoringScale::defaultRows() as $row) {
                KpiScoringScale::create(array_merge(['template_id' => $tmpl->id], $row));
            }
        };

        // Scored questions share 100% equally; the first (overall KPI) absorbs the
        // rounding remainder. Paragraph questions carry weight 0.
        $seedIndicators = function (KpiTemplate $tmpl, array $questions): void {
            $ratingCount = count(array_filter($questions, fn($q) => $q[2] !== 'text'));
            $base        = $ratingCount ? floor(10000 / $ratingCount) / 100 : 0;
            $ratingSeen  = 0;

            foreach ($questions as $i => [$name, $category, $scale]) {
                $isText = $scale === 'text';

                if ($isText) {
                    $weight = 0;
                } else {
                    $ratingSeen++;
                    $weight = $ratingSeen === 1
                        ? round(100 - $base * ($ratingCount - 1), 2)
                        : $base;
                }

                KpiIndicator::create([
                    'template_id'      => $tmpl->id,
                    'name'             => $name,
                    'answer_type'      => $isText ? 'paragraph' : 'rating',
                    'rating_max'       => $scale === 'rating3' ? 3 : null,
                    'description'      => $category,
                    'measurement_unit' => null,
                    'target_value'     => null,
                    'weight'           => $weight,
                    'order_seq'        => $i + 1,
                ]);
            }
        };

        // Explicit-weight indicator list: [name, weight, description, unit?].
        $seedWeighted = function (KpiTemplate $tmpl, array $rows): void {
            foreach ($rows as $i => $row) {
                KpiIndicator::create([
                    'template_id'      => $tmpl->id,
                    'name'             => $row[0],
                    'answer_type'      => 'rating',
                    'rating_max'       => null,
                    'description'      => $row[2] ?? null,
                    'measurement_unit' => $row[3] ?? null,
                    'target_value'     => null,
                    'weight'           => $row[1],
                    'order_seq'        => $i + 1,
                ]);
            }
        };

        $seedIndicators($templateSelf, $selfQuestions);
        $seedIndicators($templateLead, $leadQuestions);
        $seedScale($templateSelf);
        $seedScale($templateLead);

        // ── Role-specific lead-assessment templates (from the KPI spreadsheets) ──

        $benchConsultant = KpiTemplate::create([
            'name'          => 'KPI Bench Consultant',
            'description'   => 'Template KPI untuk consultant yang sedang berada dalam periode bench, dengan fokus pengembangan capability, kesiapan proyek, kontribusi internal, dukungan pre-sales, dan disiplin profesional. Skala 1-5.',
            'role_id'       => null,
            'period_type'   => 'monthly',
            'target_type'   => 'supervisor',
            'target_positions' => ['Konsultan', 'Internship', 'Management Trainee (MT)'],
            'score_divisor' => 5,
            'is_active'     => true,
            'created_by'    => 1,
            'updated_by'    => 1,
        ]);
        $seedWeighted($benchConsultant, [
            ['Learning & Certification / Skill Development', 25, 'Menilai apakah consultant memanfaatkan waktu bench untuk meningkatkan capability.'],
            ['Project Readiness & Resource Readiness', 25, 'Seberapa siap consultant untuk masuk project baru.'],
            ['Internal Contribution / Knowledge Sharing', 20, 'Knowledge sharing, training material, template, SOP, reusable solution, internal workshop.'],
            ['Internal Support / Pre-Sales / Proposal Support', 15, 'Proposal, demo, POC, solution presentation, estimation, RFP/RFI, presales, requirement assessment.'],
            ['Proactiveness & Professional Discipline', 15, 'Attendance, responsiveness, availability, discipline, communication, initiative, respons terhadap assignment baru.'],
        ]);
        $seedScale($benchConsultant);

        $consultantPhase1 = KpiTemplate::create([
            'name'          => 'KPI Consultant [In Project] ERP — Fase 1 (Requirement, Blueprint, Configuration & Development)',
            'description'   => 'Fase 1: Understand → Design → Build. Total bobot 100%. Skala 1-5.',
            'role_id'       => null,
            'period_type'   => 'monthly',
            'target_type'   => 'supervisor',
            'target_positions' => ['Konsultan'],
            'score_divisor' => 5,
            'is_active'     => true,
            'created_by'    => 1,
            'updated_by'    => 1,
        ]);
        $seedWeighted($consultantPhase1, [
            ['Requirement & Business Process Understanding', 25, 'Business process, requirement clarification, end-to-end process, business rules, cross-module dependency, gap identification.'],
            ['Solution Design & Solution Fit', 25, 'Solution fit, standard ERP utilization, customization justification, alternative solution, integration impact.'],
            ['Configuration & Development Quality', 25, 'Configuration accuracy, development quality, business rule implementation, integration, minimization of rework.'],
            ['Deliverable Quality & Completeness', 10, 'Blueprint, functional spec, technical spec, configuration document, process flow.'],
            ['Delivery & Timeliness', 5, 'Ketepatan waktu penyelesaian deliverable fase.'],
            ['Communication & Collaboration', 5, 'Koordinasi dengan tim, client, dan stakeholder.'],
            ['Proactiveness & Issue Management', 5, 'Identifikasi dan eskalasi issue secara proaktif.'],
        ]);
        $seedScale($consultantPhase1);

        $consultantPhase2 = KpiTemplate::create([
            'name'          => 'KPI Consultant [In Project] ERP — Fase 2 (SIT, UAT, Training & User Readiness)',
            'description'   => 'Fase 2: Test → Fix → Validate → Train → Prepare User. Total bobot 100%. Skala 1-5.',
            'role_id'       => null,
            'period_type'   => 'monthly',
            'target_type'   => 'supervisor',
            'target_positions' => ['Konsultan'],
            'score_divisor' => 5,
            'is_active'     => true,
            'created_by'    => 1,
            'updated_by'    => 1,
        ]);
        $seedWeighted($consultantPhase2, [
            ['Testing Quality & Coverage', 25, 'Test scenario coverage, positive/negative scenario, end-to-end scenario, integration & regression testing.'],
            ['Defect & Issue Resolution', 25, 'Response time, resolution time, root cause analysis, fix quality, recurring issue, SLA achievement.'],
            ['UAT Support & Closure', 25, 'User support, troubleshooting, defect follow-up, retest, UAT closure.'],
            ['Training & Knowledge Transfer', 15, 'Training material, training delivery, Q&A, hands-on, knowledge transfer.'],
            ['Communication & Collaboration', 5, 'Koordinasi dengan tim, client, dan stakeholder.'],
            ['Documentation', 5, 'Kelengkapan dan kualitas dokumentasi fase.'],
        ]);
        $seedScale($consultantPhase2);

        $consultantPhase3 = KpiTemplate::create([
            'name'          => 'KPI Consultant [In Project] ERP — Fase 3 (Go-Live & Hypercare)',
            'description'   => 'Fase 3: Ready → Go-Live → Stabilize → Handover. Total bobot 100%. Skala 1-5.',
            'role_id'       => null,
            'period_type'   => 'monthly',
            'target_type'   => 'supervisor',
            'target_positions' => ['Konsultan'],
            'score_divisor' => 5,
            'is_active'     => true,
            'created_by'    => 1,
            'updated_by'    => 1,
        ]);
        $seedWeighted($consultantPhase3, [
            ['Go-Live Readiness & Execution', 20, 'Configuration readiness, data migration, authorization, interface, report, cutover, business readiness.'],
            ['Production Issue Resolution', 30, 'Critical incident handling, root cause analysis, resolution time, fix quality, number of recurring issues.'],
            ['SLA & Responsiveness', 15, 'Response time, resolution time, SLA achievement.'],
            ['Business & User Support', 15, 'User assistance, troubleshooting, hypercare support.'],
            ['System Stability & Issue Prevention', 10, 'Recurring issues, critical incidents, production defect impact.'],
            ['Documentation & Handover', 5, 'Kelengkapan dokumentasi dan proses handover ke tim support.'],
            ['Continuous Improvement', 5, 'Usulan perbaikan proses dan lesson learned.'],
        ]);
        $seedScale($consultantPhase3);

        $pmo = KpiTemplate::create([
            'name'          => 'KPI PMO',
            'description'   => 'Template KPI PMO untuk penilaian konsultan dalam proyek ERP berbasis fase. Skala acuan: 5 Outstanding (>=120%), 4 Exceeds Expectations (105%-119%), 3 Meets Expectations (90%-104%), 2 Needs Improvement (70%-89%), 1 Unsatisfactory (<70%).',
            'role_id'       => null,
            'period_type'   => 'monthly',
            'target_type'   => 'supervisor',
            'target_positions' => ['Supervisor', 'Head of RPMO', 'PMO'],
            'score_divisor' => 5,
            'is_active'     => true,
            'created_by'    => 1,
            'updated_by'    => 1,
        ]);
        $seedWeighted($pmo, [
            ['Project Monitoring & Control', 25, 'Menilai kemampuan PMO dalam memastikan project tetap on track dan terkontrol.'],
            ['Reporting & Project Documentation', 20, 'Menilai kualitas project reporting dan kelengkapan dokumentasi proyek.'],
            ['Risk, Issue & Dependency Management', 20, 'Identifikasi dan kontrol risk, issue, dependency, blocker, escalation.'],
            ['Stakeholder Coordination & Communication', 15, 'Meeting coordination, follow-up, communication, stakeholder alignment, escalation, cross-module coordination.'],
            ['Project Governance & Process Compliance', 10, 'Menilai apakah PMO memastikan project mengikuti governance yang telah ditetapkan.'],
            ['Proactiveness & Continuous Improvement', 10, 'Inisiatif perbaikan proses dan mitigasi dini.'],
        ]);
        $seedScale($pmo);

        // ── 3. Seed sample Evaluations ─────────────────────────────────────────
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

        $this->command?->info('KPI Seeder completed successfully!');
    }
}
