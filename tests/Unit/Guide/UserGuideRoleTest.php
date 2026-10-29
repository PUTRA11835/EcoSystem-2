<?php

namespace Tests\Unit\Guide;

use App\Services\Guide\UserGuideService;
use Tests\TestCase;

/** Penargetan topik per role ("@role:Nama Role") di samping izin menu: gabungan semua role pengguna, dan EN dan ID harus sama. */
class UserGuideRoleTest extends TestCase
{
    public function test_role_pseudo_permission_opens_only_the_topics_of_that_role(): void
    {
        $svc = new UserGuideService();
        $topics = [
            'rpmo-guide' => ['requires' => ['@role:Delivery RPMO Head']],
            'ds-guide'   => ['requires' => ['@role:Delivery Support Head']],
            'menu-topic' => ['requires' => ['rpmo.periods']],
            'everyone'   => ['requires' => []],
        ];

        $this->assertSame(['menu-topic', 'everyone'], array_keys($svc->visible($topics, ['rpmo.periods'])));
        $this->assertSame(['rpmo-guide', 'everyone'], array_keys($svc->visible($topics, [], ['@role:Delivery RPMO Head'])));
        // Pengguna dengan dua role melihat gabungan keduanya.
        $this->assertSame(['rpmo-guide', 'ds-guide', 'everyone'], array_keys($svc->visible($topics, [], ['@role:Delivery RPMO Head', '@role:Delivery Support Head'])));
        // Menu yang sama tidak membuka panduan role lain.
        $this->assertSame(['menu-topic', 'everyone'], array_keys($svc->visible($topics, ['rpmo.periods'], ['@role:Delivery Project Head'])));
    }

    public function test_role_requirements_are_well_formed_and_identical_in_both_languages(): void
    {
        $svc = new UserGuideService();
        $en = $svc->load('en', false);
        $id = $svc->load('id', false);

        foreach ($en as $key => $t) {
            $this->assertSame($t['requires'], $id[$key]['requires'] ?? null, "requires $key harus sama di EN dan ID");
            foreach ($t['requires'] as $req) {
                if (str_starts_with($req, '@role:')) {
                    $this->assertNotSame('', trim(substr($req, 6)), "nama role kosong pada $key");
                }
            }
        }
    }

    public function test_role_guides_target_their_role_and_not_a_shared_menu(): void
    {
        $en = (new UserGuideService())->load('en', false);

        foreach (['guide-ds-user' => '@role:Delivery Support User', 'guide-ds-head' => '@role:Delivery Support Head', 'guide-rpmo-head' => '@role:Delivery RPMO Head'] as $key => $role) {
            $this->assertSame([$role], $en[$key]['requires'], $key);
        }
        foreach (['guide-project-user' => '@role:Delivery Project User', 'guide-project-head' => '@role:Delivery Project Head', 'guide-helpdesk' => '@role:Delivery Support Service Helpdesk', 'guide-modul-lead' => '@role:Modul Lead'] as $key => $role) {
            $this->assertSame([$role], $en[$key]['requires'], $key);
        }
        $this->assertSame(['@role:Delivery Project Administrator'], $en['guide-project-admin']['requires']);
        $this->assertSame(['@role:EC User'], $en['guide-ec-user']['requires']);
        foreach (['guide-operation-director' => '@role:Delivery Operation Director', 'guide-rpmo-admin' => '@role:Delivery RPMO Administrator', 'guide-ho-hr-user' => '@role:HO HR User', 'guide-external-consultant' => '@role:External Consultant', 'guide-section-head' => '@role:Delivery Support Section Head'] as $key => $role) {
            $this->assertSame([$role], $en[$key]['requires'], $key);
        }
        // Topik administrasi mengikuti izin menu masing-masing.
        $this->assertSame(['control-center.user-guide'], $en['user-guide-editor']['requires']);
        $this->assertSame(['control-center.ai-settings'], $en['ai-settings']['requires']);
        $this->assertSame(['tickets.staging'], $en['ticket-validation']['requires']);
        // Satu topik untuk setiap submenu Reporting, mengikuti slug menunya.
        foreach (['report-md-validation' => ['reporting.validation'], 'report-md-recap' => ['reporting.md-recap'], 'report-resolution-days' => ['reporting.resolution-days'], 'report-ticketing-overview' => ['reporting.ticketing-overview'], 'report-ticket-by-module' => ['reporting.ticket-by-module'], 'report-log-shifting' => ['reporting.log-shifting'], 'report-collection-outlook' => ['reporting.collection-outlook', 'reporting.collection-outlook-support'], 'report-consultant-assignment' => ['reporting.consultant-assignment'], 'report-diagram' => ['reporting.diagram-report']] as $key => $slugs) {
            $this->assertSame($slugs, $en[$key]['requires'], $key);
        }
        $this->assertSame(['ui.ticket.btn-ai-summarize', 'ui.ticket.btn-ai-research'], $en['ticket-ai-tools']['requires']);
        $this->assertCount(3, $en['delivery-types']['requires']);
        // Satu panduan untuk seluruh keluarga role Sales dan Marketing.
        $this->assertCount(5, $en['guide-sales']['requires']);
        $this->assertContains('@role:Sales Marketing User', $en['guide-sales']['requires']);
        $this->assertContains('@role:Delivery Support Manager', $en['guide-ds-manager']['requires']);
        $this->assertSame(['rpmo.periods'], $en['period-management']['requires']);
        // Fitur tiap laporan mengikuti izin menu masing-masing; Home untuk semua orang.
        $this->assertSame(['reporting.weekly-consolidation'], $en['weekly-consolidation']['requires']);
        $this->assertSame(['reporting.customer-md'], $en['customer-md']['requires']);
        $this->assertSame(['reporting.resource-timeline'], $en['resource-timeline']['requires']);
        $this->assertSame(['word-report-generator'], $en['word-report-generator']['requires']);
        $this->assertSame([], $en['home-dashboard']['requires']);
    }
}
