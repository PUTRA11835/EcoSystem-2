<?php

namespace Tests\Unit\Management;

use App\Services\Management\EmployeeMasterData;
use App\Support\Management\EmployeeMasterSettings;
use Tests\TestCase;

/** Definisi setelan Management → Employee: konsisten, dan dijaga izin Create/Edit/Delete-nya. */
class EmployeeMasterSettingsTest extends TestCase
{
    public function test_every_tab_has_a_menu_slug_that_enforces_crud(): void
    {
        $enforced = config('menu_access.crud_enforced');

        foreach (EmployeeMasterSettings::SLUGS as $key => $slug) {
            $this->assertTrue(EmployeeMasterSettings::exists($key));
            $this->assertContains($slug, $enforced, "{$slug} must be listed as crud_enforced (its routes use menu.can)");
        }
    }

    public function test_definitions_are_consistent(): void
    {
        foreach (array_keys(EmployeeMasterSettings::SLUGS) as $key) {
            $def = EmployeeMasterSettings::get($key);

            foreach ($def['lists'] as $field => $list) {
                $this->assertNotEmpty($list['defaults'], "{$key}.{$field} starts with its options");
                $this->assertCount(count($list['defaults']), array_unique(array_map('strval', array_keys($list['defaults']))), "{$key}.{$field} values are unique");
            }
            foreach ($def['rules'] as $name => $rule) {
                $this->assertContains($rule['type'], ['int', 'bool', 'text'], "{$key}.{$name}");
                if ($rule['type'] === 'int') {
                    $this->assertLessThanOrEqual($rule['max'], $rule['default'], "{$key}.{$name} default inside range");
                    $this->assertGreaterThanOrEqual($rule['min'], $rule['default'], "{$key}.{$name} default inside range");
                }
            }
        }
    }

    public function test_length_limits_never_exceed_the_database_columns(): void
    {
        // The hard caps are the columns' own sizes (employee_address.street is varchar(255), postal_code varchar(20) in the validator …).
        $this->assertSame(255, EmployeeMasterSettings::get('address')['rules']['street_max_length']['max']);
        $this->assertSame(20, EmployeeMasterSettings::get('address')['rules']['postal_code_max_length']['max']);
        $this->assertSame(50, EmployeeMasterSettings::get('bank')['rules']['account_number_max_length']['max']);
    }

    public function test_options_and_rules_have_defaults_before_anything_is_saved(): void
    {
        EmployeeMasterData::forget();

        $this->assertArrayHasKey('BCA', EmployeeMasterData::options('bank', 'bank_name'));
        $this->assertArrayHasKey('spouse', EmployeeMasterData::options('family', 'relation'));
        $this->assertIsInt(EmployeeMasterData::rule('address', 'street_max_length'));
        $this->assertIsBool(EmployeeMasterData::rule('bank', 'account_number_digits_only'));
        $this->assertContains('pdf', EmployeeMasterData::allowedExtensions());
    }

    public function test_a_value_a_record_already_holds_stays_valid_when_its_option_is_off(): void
    {
        EmployeeMasterData::forget();
        $values = EmployeeMasterData::values('family', 'relation', 'cousin');

        $this->assertContains('cousin', $values);
        $this->assertContains('spouse', $values);
        $this->assertNotContains('cousin', EmployeeMasterData::values('family', 'relation'));
    }

    public function test_an_unknown_rule_is_a_programming_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmployeeMasterData::rule('address', 'no_such_rule');
    }
}
