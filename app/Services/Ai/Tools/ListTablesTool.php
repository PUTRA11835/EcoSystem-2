<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;

/**
 * Lets the model discover the exact table name to pass to query_data,
 * instead of guessing — table names don't always match the obvious English
 * word (e.g. tickets live in "ticket", not "tickets"; SLA policies in
 * "sla_policies").
 */
class ListTablesTool implements AiTool
{
    public function definition(): array
    {
        return [
            'name' => 'list_tables',
            'description' => 'List database table names available to query_data, optionally filtered by a substring. '
                . 'Call this first if you are not sure of the exact table name for something.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Optional substring to filter table names by (case-insensitive). Omit to list all.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(Employee $employee, array $input): array
    {
        $tables = TableAccess::allTables();

        $search = trim((string) ($input['search'] ?? ''));
        if ('' !== $search) {
            $needle = strtolower($search);
            $tables = array_values(array_filter(
                $tables,
                fn (string $table) => str_contains(strtolower($table), $needle),
            ));
        }

        return ['count' => count($tables), 'tables' => $tables];
    }
}
