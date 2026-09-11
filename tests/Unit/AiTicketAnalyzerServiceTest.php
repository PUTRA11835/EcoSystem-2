<?php

namespace Tests\Unit;

use App\Services\Ai\AiTicketAnalyzerService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Pure-logic coverage for AiTicketAnalyzerService's response parsing and
 * sanitization — the parts that don't touch the database or a real AI
 * provider. resolveAssignees() (DB-bound: Module/EmployeeQualification/
 * Employee/ConsultantWorkloadController) is deliberately NOT covered here —
 * this app's Feature test harness has never been verified against the
 * sqlite :memory: DB phpunit.xml configures (tests/Feature/ExampleTest.php
 * fails today on an unrelated redirect, and RefreshDatabase is commented out
 * there), so a DB-backed test needs that groundwork first rather than being
 * bolted on here.
 */
class AiTicketAnalyzerServiceTest extends TestCase
{
    private AiTicketAnalyzerService $service;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new ReflectionClass(AiTicketAnalyzerService::class);
        // None of the methods under test touch $this->drivers, so building the
        // real AiDriverFactory (which needs the Laravel container) is unnecessary.
        $this->service = $this->ref->newInstanceWithoutConstructor();
    }

    private function callPrivate(string $method, array $args = []): mixed
    {
        $m = $this->ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->service, $args);
    }

    // ── extractJson() ───────────────────────────────────────────────────────

    public function test_extract_json_from_fenced_code_block(): void
    {
        $text = "Here's the analysis:\n```json\n{\"overview\":\"test\",\"confidence\":0.8}\n```\ndone";

        $this->assertSame(
            ['overview' => 'test', 'confidence' => 0.8],
            $this->callPrivate('extractJson', [$text])
        );
    }

    public function test_extract_json_plain_json_body(): void
    {
        $this->assertSame(
            ['overview' => 'plain'],
            $this->callPrivate('extractJson', ['{"overview":"plain"}'])
        );
    }

    public function test_extract_json_with_surrounding_prose(): void
    {
        $this->assertSame(
            ['overview' => 'loose'],
            $this->callPrivate('extractJson', ['noise before {"overview":"loose"} noise after'])
        );
    }

    public function test_extract_json_returns_null_for_unparseable_text(): void
    {
        $this->assertNull($this->callPrivate('extractJson', ['this is not json at all']));
    }

    // ── sanitizeStringList() ────────────────────────────────────────────────

    public function test_sanitize_string_list_filters_blanks_and_non_strings(): void
    {
        $this->assertSame(
            ['a', 'b'],
            $this->callPrivate('sanitizeStringList', [['a', '', ' b ', 123, null, '  ']])
        );
    }

    public function test_sanitize_string_list_returns_empty_array_for_non_array(): void
    {
        $this->assertSame([], $this->callPrivate('sanitizeStringList', [null]));
        $this->assertSame([], $this->callPrivate('sanitizeStringList', ['not an array']));
    }

    // ── sanitizeEnum() ──────────────────────────────────────────────────────

    public function test_sanitize_enum_accepts_allowed_value(): void
    {
        $this->assertSame('High', $this->callPrivate('sanitizeEnum', ['High', ['Low', 'Medium', 'High']]));
    }

    public function test_sanitize_enum_rejects_value_outside_allowlist(): void
    {
        $this->assertNull($this->callPrivate('sanitizeEnum', ['Bogus', ['Low', 'Medium', 'High']]));
    }

    public function test_sanitize_enum_rejects_non_string(): void
    {
        $this->assertNull($this->callPrivate('sanitizeEnum', [42, ['Low', 'Medium', 'High']]));
        $this->assertNull($this->callPrivate('sanitizeEnum', [null, ['Low', 'Medium', 'High']]));
    }

    // ── sanitizeModuleId() ──────────────────────────────────────────────────

    public function test_sanitize_module_id_accepts_id_present_in_list(): void
    {
        $modules = collect([(object) ['id' => 4, 'name' => 'SD'], (object) ['id' => 6, 'name' => 'FI']]);

        $this->assertSame(4, $this->callPrivate('sanitizeModuleId', [4, $modules]));
        $this->assertSame(4, $this->callPrivate('sanitizeModuleId', ['4', $modules]));
    }

    public function test_sanitize_module_id_rejects_id_not_in_list(): void
    {
        $modules = collect([(object) ['id' => 4, 'name' => 'SD']]);

        $this->assertNull($this->callPrivate('sanitizeModuleId', [99, $modules]));
    }

    public function test_sanitize_module_id_rejects_non_numeric(): void
    {
        $modules = collect([(object) ['id' => 4, 'name' => 'SD']]);

        $this->assertNull($this->callPrivate('sanitizeModuleId', [null, $modules]));
        $this->assertNull($this->callPrivate('sanitizeModuleId', ['not a number', $modules]));
    }
}
