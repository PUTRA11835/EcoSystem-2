<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;
use App\Services\Ai\Tools\Concerns\FiltersTableQueries;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Generic read-only lookup against any table in the app's database. Exists
 * so the assistant can answer questions about data that isn't covered by
 * get_tickets / get_sla_summary / get_delivery_projects, without a bespoke
 * tool per table.
 *
 * Current state: NO per-table permission check — any authenticated employee
 * can query any non-excluded table (see TableAccess::EXCLUDED_TABLES for the
 * technical-only exclusions: session/token/queue/migration tables). This is
 * intentional for now; business-data access restrictions are a deliberately
 * separate follow-up once broad access has been validated.
 *
 * Every query goes through the query builder with bound parameters (never
 * raw SQL), every table/column name is validated against the real schema
 * before use, and every returned row has secret-shaped columns stripped
 * (TableAccess::stripSecrets) regardless of what was asked for.
 */
class QueryDataTool implements AiTool
{
    use FiltersTableQueries;

    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function definition(): array
    {
        return [
            'name' => 'query_data',
            'description' => 'Read-only row lookup against any database table not already covered by get_tickets, '
                . 'get_sla_summary, or get_delivery_projects. Returns at most 100 rows — for "how many" / "total" / '
                . '"average" questions use aggregate_data instead, which gives an exact number via SQL rather than '
                . 'a capped row list. If you are not sure of the exact table name, call list_tables first — table '
                . 'names are snake_case and don\'t always match the obvious English word (e.g. tickets live in '
                . '"ticket", not "tickets"). This tool never writes data — it only reads.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'table' => [
                        'type' => 'string',
                        'description' => 'Exact table name, e.g. "employee" or "customer_bank". Use list_tables if unsure.',
                    ],
                    'filters' => [
                        'type' => 'array',
                        'description' => 'Conditions to narrow the results. "field" must be a real column on the '
                            . 'chosen table — if a filter is rejected for an unknown column, the error lists the '
                            . 'real columns so you can retry.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'field' => ['type' => 'string'],
                                'operator' => ['type' => 'string', 'enum' => $this->filterOperators()],
                                'value' => [
                                    'description' => 'Comparison value: string, number, boolean, or an array of '
                                        . 'those for "in" (list) / "between" (two bounds).',
                                ],
                            ],
                            'required' => ['field', 'operator'],
                        ],
                    ],
                    'fields' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional subset of columns to return. Omit to return all columns.',
                    ],
                    'order_by' => ['type' => 'string', 'description' => 'Optional column to sort by.'],
                    'order_dir' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'description' => 'Default desc.'],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum rows to return. Default 20, maximum 100.',
                    ],
                ],
                'required' => ['table'],
            ],
        ];
    }

    public function run(Employee $employee, array $input): array
    {
        $table = (string) ($input['table'] ?? '');

        if (!TableAccess::isQueryable($table)) {
            return [
                'error' => "Table \"{$table}\" doesn't exist or isn't queryable. Call list_tables to see valid table names.",
            ];
        }

        $columns = TableAccess::columns($table);

        $fields = $input['fields'] ?? [];
        if (!empty($fields)) {
            $unknown = array_diff($fields, $columns);
            if (!empty($unknown)) {
                return ['error' => 'Unknown field(s): ' . implode(', ', $unknown) . '. Valid columns for ' . $table . ': ' . implode(', ', $columns)];
            }
        }

        $query = DB::table($table);

        if ($error = TableAccess::authorizeQuery($employee, $table, $query)) {
            return $error;
        }

        if ($error = $this->applyFilters($query, (array) ($input['filters'] ?? []), $columns, $table)) {
            return $error;
        }

        if (!empty($input['order_by'])) {
            $orderBy = (string) $input['order_by'];
            if (!in_array($orderBy, $columns, true)) {
                return ['error' => "Unknown order_by field \"{$orderBy}\". Valid columns for {$table}: " . implode(', ', $columns)];
            }
            $query->orderBy($orderBy, 'asc' === ($input['order_dir'] ?? 'desc') ? 'asc' : 'desc');
        }

        if (!empty($fields)) {
            $query->select($fields);
        }

        $limit = (int) ($input['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        try {
            $rows = $query->limit($limit)->get();
        } catch (Throwable $e) {
            return ['error' => 'Query failed: ' . $e->getMessage()];
        }

        $rows = $rows->map(fn ($row) => TableAccess::stripSecrets((array) $row))->all();

        return [
            'table' => $table,
            'count' => count($rows),
            'rows' => $rows,
        ];
    }
}
