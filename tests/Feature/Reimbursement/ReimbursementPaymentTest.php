<?php

namespace Tests\Feature\Reimbursement;

use App\Models\Reimbursement\ReimbursementRequest;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reimbursement no longer ends at "approved": an approved document is AWAITING PAYMENT until Finance records it, then PAID. These tests
 * pin the rules — only an approved, unpaid, not-deleted document can be paid; once; never in the future; a reversal needs a reason —
 * and that the requester is told. In-memory SQLite with only the tables this reads.
 */
class ReimbursementPaymentTest extends TestCase
{
    private const REQUESTER = 11;
    private const FINANCE = 31;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not loaded (run: php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit).');
        }
        parent::setUp();

        Schema::create('reimbursement_requests', function (Blueprint $t) {
            $t->id();
            $t->string('request_no');
            $t->unsignedBigInteger('employee_id');
            $t->date('request_date');
            $t->string('title')->nullable();
            $t->string('currency', 3)->default('IDR');
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->unsignedSmallInteger('item_count')->default(1);
            $t->string('status');
            $t->unsignedSmallInteger('current_step_order')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->unsignedBigInteger('paid_by')->nullable();
            $t->string('payment_reference', 100)->nullable();
            $t->string('payment_note', 255)->nullable();
            $t->unsignedBigInteger('deleted_by')->nullable();
            $t->string('delete_reason')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('type');
            $t->unsignedBigInteger('ticket_id')->nullable();
            $t->unsignedBigInteger('from_employee_id')->nullable();
            $t->string('from_name')->nullable();
            $t->text('preview')->nullable();
            $t->string('link')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
    }

    private function request(string $status = 'approved', array $extra = []): ReimbursementRequest
    {
        $id = DB::table('reimbursement_requests')->insertGetId($extra + [
            'request_no' => 'RB/2026/10/00001', 'employee_id' => self::REQUESTER, 'request_date' => '2026-10-05', 'title' => 'Taxi to client',
            'total_amount' => 150000, 'status' => $status, 'completed_at' => $status === 'approved' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ReimbursementRequest::find($id);
    }

    private function service(): ReimbursementService
    {
        return app(ReimbursementService::class);
    }

    public function test_an_approved_document_starts_as_awaiting_payment(): void
    {
        $doc = $this->request();

        $this->assertSame('awaiting', $doc->paymentStatus());
        $this->assertTrue($doc->isPayable());
        $this->assertSame('Awaiting payment', $doc->paymentLabel());
        $this->assertSame(1, ReimbursementRequest::awaitingPayment()->count());
        $this->assertSame(0, ReimbursementRequest::paid()->count());
    }

    public function test_only_an_approved_document_has_a_payment_state(): void
    {
        foreach (['submitted', 'in_review', 'rejected'] as $status) {
            $doc = $this->request($status);
            $this->assertNull($doc->paymentStatus(), "$status has no payment state");
            $this->assertFalse($doc->isPayable());
            $result = $this->service()->markPaid($doc, self::FINANCE, []);
            $this->assertFalse($result['allowed'], "$status cannot be paid");
            $this->assertNull($doc->fresh()->paid_at);
        }
    }

    public function test_recording_a_payment_marks_it_paid_with_who_when_and_reference_and_tells_the_requester(): void
    {
        $doc = $this->request();

        $result = $this->service()->markPaid($doc, self::FINANCE, ['paid_on' => '2026-10-10', 'reference' => 'TRF-889', 'note' => 'Batch 3']);

        $this->assertTrue($result['allowed']);
        $fresh = $doc->fresh();
        $this->assertSame('paid', $fresh->paymentStatus());
        $this->assertSame('2026-10-10', $fresh->paid_at->toDateString());
        $this->assertSame(self::FINANCE, (int) $fresh->paid_by);
        $this->assertSame('TRF-889', $fresh->payment_reference);
        $this->assertSame('Paid 10 Oct 2026', $fresh->paymentLabel());
        $this->assertSame('approved', $fresh->status, 'the approval status is untouched');
        $this->assertSame(1, ReimbursementRequest::paid()->count());
        $this->assertSame(0, ReimbursementRequest::awaitingPayment()->count());

        $note = DB::table('notifications')->where('type', 'reimbursement_paid')->first();
        $this->assertNotNull($note, 'the requester is told');
        $this->assertSame(self::REQUESTER, (int) $note->employee_id);
    }

    public function test_a_document_cannot_be_paid_twice(): void
    {
        $doc = $this->request();
        $this->service()->markPaid($doc, self::FINANCE, ['paid_on' => '2026-10-10', 'reference' => 'FIRST']);

        $second = $this->service()->markPaid($doc->fresh(), self::FINANCE, ['paid_on' => '2026-10-11', 'reference' => 'SECOND']);

        $this->assertFalse($second['allowed']);
        $this->assertStringContainsString('already paid', $second['reason']);
        $this->assertSame('FIRST', $doc->fresh()->payment_reference, 'the first payment record is kept');
    }

    public function test_a_payment_date_in_the_future_is_refused(): void
    {
        $doc = $this->request();

        $result = $this->service()->markPaid($doc, self::FINANCE, ['paid_on' => now()->addDays(3)->toDateString()]);

        $this->assertFalse($result['allowed']);
        $this->assertNull($doc->fresh()->paid_at);
    }

    public function test_a_deleted_document_cannot_be_paid(): void
    {
        $doc = $this->request();
        $doc->delete();

        $result = $this->service()->markPaid($doc->fresh() ?? ReimbursementRequest::withTrashed()->find($doc->id), self::FINANCE, []);

        $this->assertFalse($result['allowed']);
    }

    public function test_reversing_a_payment_needs_a_reason_and_puts_the_document_back_to_awaiting(): void
    {
        $doc = $this->request();
        $this->service()->markPaid($doc, self::FINANCE, ['paid_on' => '2026-10-10', 'reference' => 'TRF-1']);

        $noReason = $this->service()->reversePayment($doc->fresh(), self::FINANCE, '  ');
        $this->assertFalse($noReason['allowed']);
        $this->assertTrue($doc->fresh()->isPaid(), 'still paid without a reason');

        $ok = $this->service()->reversePayment($doc->fresh(), self::FINANCE, 'Paid to the wrong account');
        $this->assertTrue($ok['allowed']);

        $fresh = $doc->fresh();
        $this->assertSame('awaiting', $fresh->paymentStatus());
        $this->assertNull($fresh->paid_at);
        $this->assertNull($fresh->payment_reference);
        $this->assertStringContainsString('Paid to the wrong account', $fresh->payment_note, 'the reason is kept on the document');
        $this->assertStringContainsString('TRF-1', $fresh->payment_note, 'and what was reversed');
        $this->assertSame(1, DB::table('notifications')->where('type', 'reimbursement_payment_reversed')->count());
    }

    public function test_there_is_nothing_to_reverse_on_an_unpaid_document(): void
    {
        $doc = $this->request();

        $this->assertFalse($this->service()->reversePayment($doc, self::FINANCE, 'No reason needed')['allowed']);
    }
}
