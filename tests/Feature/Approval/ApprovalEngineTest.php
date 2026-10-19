<?php

namespace Tests\Feature\Approval;

use App\Models\PurchaseRequest\PurchaseRequest;
use App\Services\Approval\ApprovalEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The shared approval engine: who is named on a workflow, what waits for whom, and which documents a designated approver (who
 * holds no HR menu) may open. Purchase Request stands in for the five modules — their step snapshots share one shape.
 *
 * Runs on an in-memory SQLite with just the tables the engine reads; the app's full legacy schema is not needed.
 */
class ApprovalEngineTest extends TestCase
{
    private const REQUESTER = 10;
    private const HEAD = 20;      // named on step 1 by name
    private const FINANCE = 30;   // named on step 2 by position
    private const OUTSIDER = 40;  // on no step at all
    private const FINANCE_ROLE = 7;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not loaded (enable it in php.ini, or run: php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit).');
        }

        parent::setUp();

        Schema::create('employee_role_assignment', function (Blueprint $t) {
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('role_id');
        });
        Schema::create('purchase_requests', function (Blueprint $t) {
            $t->id();
            $t->string('request_no')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->string('title')->nullable();
            $t->string('status');
            $t->unsignedSmallInteger('current_step_order')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('purchase_request_approvals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('purchase_request_id');
            $t->unsignedSmallInteger('order_seq');
            $t->string('step_name');
            $t->string('approver_type');
            $t->unsignedBigInteger('approver_role_id')->nullable();
            $t->json('approver_employee_ids')->nullable();
            $t->boolean('chosen_by_requester')->default(false);
            $t->string('status')->default('waiting');
            $t->unsignedBigInteger('acted_by')->nullable();
            $t->timestamp('acted_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_request_approval_steps', function (Blueprint $t) {
            $t->id();
            $t->string('module');
            $t->unsignedSmallInteger('order_seq');
            $t->string('name');
            $t->string('approver_type');
            $t->unsignedBigInteger('approver_role_id')->nullable();
            $t->json('approver_employee_ids')->nullable();
            $t->boolean('requester_selectable')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        DB::table('employee_role_assignment')->insert(['employee_id' => self::FINANCE, 'role_id' => self::FINANCE_ROLE]);
    }

    /** A request in review: step 1 (head, by name) then step 2 (finance, by position). */
    private function request(int $currentStep = 1, string $status = 'in_review'): PurchaseRequest
    {
        $id = DB::table('purchase_requests')->insertGetId([
            'request_no' => 'PR/2026/10/00001', 'employee_id' => self::REQUESTER, 'created_by' => self::REQUESTER,
            'status' => $status, 'current_step_order' => $currentStep, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('purchase_request_approvals')->insert([
            ['purchase_request_id' => $id, 'order_seq' => 1, 'step_name' => 'Department head', 'approver_type' => 'employee',
                'approver_role_id' => null, 'approver_employee_ids' => json_encode([self::HEAD]),
                'status' => $currentStep > 1 ? 'approved' : 'waiting', 'acted_by' => $currentStep > 1 ? self::HEAD : null,
                'created_at' => now(), 'updated_at' => now()],
            ['purchase_request_id' => $id, 'order_seq' => 2, 'step_name' => 'Finance', 'approver_type' => 'role',
                'approver_role_id' => self::FINANCE_ROLE, 'approver_employee_ids' => null,
                'status' => 'waiting', 'acted_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return PurchaseRequest::find($id);
    }

    private function engine(): ApprovalEngine
    {
        return new ApprovalEngine();
    }

    public function test_a_step_named_by_employee_or_by_position_lets_exactly_those_people_act(): void
    {
        $step = $this->request()->approvals;
        $engine = $this->engine();

        $this->assertTrue($engine->stepAllows($step[0], self::HEAD));
        $this->assertFalse($engine->stepAllows($step[0], self::FINANCE));
        $this->assertTrue($engine->stepAllows($step[1], self::FINANCE));
        $this->assertFalse($engine->stepAllows($step[1], self::HEAD));
        $this->assertFalse($engine->stepAllows($step[0], self::OUTSIDER));
    }

    public function test_a_requester_chosen_approver_narrows_a_position_step_to_that_one_person(): void
    {
        $step = $this->request()->approvals[1];
        $step->chosen_by_requester = true;
        $step->approver_employee_ids = [self::OUTSIDER];

        // The position holder no longer qualifies; the chosen person does.
        $this->assertFalse($this->engine()->stepAllows($step, self::FINANCE));
        $this->assertTrue($this->engine()->stepAllows($step, self::OUTSIDER));
    }

    public function test_only_the_person_on_the_current_step_has_something_waiting(): void
    {
        $this->request(1);

        $this->assertTrue($this->engine()->isPending('purchase-request', self::HEAD));
        // Finance is on step 2, which is not current yet — nothing waits for them.
        $this->assertFalse($this->engine()->isPending('purchase-request', self::FINANCE));
        $this->assertFalse($this->engine()->isPending('purchase-request', self::OUTSIDER));
    }

    public function test_the_wait_moves_to_the_next_approver_when_the_step_advances(): void
    {
        $this->request(2);

        $this->assertFalse($this->engine()->isPending('purchase-request', self::HEAD));
        $this->assertTrue($this->engine()->isPending('purchase-request', self::FINANCE));
    }

    public function test_a_closed_document_waits_for_nobody(): void
    {
        $this->request(1, 'rejected');

        $this->assertFalse($this->engine()->isPending('purchase-request', self::HEAD));
    }

    public function test_a_person_named_on_the_configured_workflow_is_a_designated_approver_before_any_document_exists(): void
    {
        DB::table('purchase_request_approval_steps')->insert([
            ['module' => 'purchase_request', 'order_seq' => 1, 'name' => 'Director', 'approver_type' => 'employee',
                'approver_role_id' => null, 'approver_employee_ids' => json_encode([self::HEAD]), 'is_active' => true,
                'created_at' => now(), 'updated_at' => now()],
            ['module' => 'purchase_request', 'order_seq' => 2, 'name' => 'Old step', 'approver_type' => 'role',
                'approver_role_id' => self::FINANCE_ROLE, 'approver_employee_ids' => null, 'is_active' => false,
                'created_at' => now(), 'updated_at' => now()],
        ]);

        $engine = $this->engine();
        $this->assertTrue($engine->isDesignatedApprover('purchase-request', self::HEAD));
        // An inactive step designates nobody.
        $this->assertFalse($engine->isDesignatedApprover('purchase-request', self::FINANCE));
        $this->assertFalse($engine->isDesignatedApprover('purchase-request', self::OUTSIDER));
        // The other modules' workflows are separate: being on the Purchase Request flow says nothing about Reimbursement.
        Schema::create('reimbursement_approval_steps', function (Blueprint $t) {
            $t->id();
            $t->string('module');
            $t->string('approver_type');
            $t->unsignedBigInteger('approver_role_id')->nullable();
            $t->json('approver_employee_ids')->nullable();
            $t->boolean('is_active')->default(true);
        });
        $this->assertFalse($engine->isConfiguredApprover('reimbursement', self::HEAD));
    }

    public function test_a_designated_approver_may_open_only_the_documents_they_take_part_in(): void
    {
        $mine = $this->request();
        $other = $this->request();
        // Re-point the second document's first step at somebody else.
        DB::table('purchase_request_approvals')->where('purchase_request_id', $other->id)->where('order_seq', 1)
            ->update(['approver_employee_ids' => json_encode([self::OUTSIDER])]);
        DB::table('purchase_request_approvals')->where('purchase_request_id', $other->id)->where('order_seq', 2)
            ->update(['approver_role_id' => 99]);

        $engine = $this->engine();
        $this->assertTrue($engine->participates('purchase-request', $mine, self::HEAD));
        $this->assertFalse($engine->participates('purchase-request', $other, self::HEAD), 'being an approver somewhere is not a licence to read every document');
        // The requester always sees their own document.
        $this->assertTrue($engine->participates('purchase-request', $mine, self::REQUESTER));
        $this->assertFalse($engine->participates('purchase-request', $mine, self::OUTSIDER));
    }

    public function test_someone_who_already_acted_still_sees_the_document(): void
    {
        $document = $this->request(2);    // step 1 (HEAD) is done, the document waits for finance

        $this->assertTrue($this->engine()->participates('purchase-request', $document, self::HEAD));
    }

    public function test_an_unknown_module_is_never_an_approver(): void
    {
        $this->assertFalse($this->engine()->isDesignatedApprover('payroll', self::HEAD));
        $this->assertFalse($this->engine()->has('payroll'));
        $this->assertCount(0, $this->engine()->waitingFor('payroll', self::HEAD));
    }
}
