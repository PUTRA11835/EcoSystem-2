<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;
use App\Services\Ai\Tools\Concerns\FiltersTableQueries;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Exact COUNT/SUM/AVG/MIN/MAX over a table, optionally grouped by one or
 * more columns — for "how many", "total", "average" questions that
 * query_data structurally cannot answer (it returns at most 100 rows, which
 * is a sample, not a total).
 *
 * The aggregate function and the field/group_by columns are all validated
 * against a fixed function whitelist and the table's real column list
 * before being placed into the SQL, so this stays injection-safe despite
 * building a raw SELECT expression.
 */
class AggregateDataTool implements AiTool
{
    use FiltersTableQueries;

    private const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max'];
    private const SQL_FUNCTIONS = ['count' => 'COUNT', 'sum' => 'SUM', 'avg' => 'AVG', 'min' => 'MIN', 'max' => 'MAX'];
    private const DEFAULT_GROUP_LIMIT = 50;
    private const MAX_GROUP_LIMIT = 200;

    public function definition(): array
    {
        return [
            'name' => 'aggregate_data',
            'description' => 'Get an exact COUNT, SUM, AVG, MIN, or MAX over a database table — use this for "how '
                . 'many", "total", "average" questions instead of query_data, which only returns a capped sample of '
                . 'rows and cannot give an accurate total. Optionally group by one or more columns to get the '
                . 'aggregate per group in a single call (e.g. ticket count per status) instead of querying each '
                . 'value separately. If you are not sure of the exact table name, call list_tables first.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'table' => [
                        'type' => 'string',
                        'description' => 'Exact table name, e.g. "ticket" or "employee". Use list_tables if unsure.',
                    ],
                    'aggregate' => [
                        'type' => 'string',
                        'enum' => self::AGGREGATES,
                        'description' => 'Which aggregate to compute. Default "count".',
                    ],
                    'field' => [
                        'type' => 'string',
                        'description' => 'Numeric column to aggregate. Required for sum/avg/min/max; ignored for count.',
                    ],
                    'filters' => [
                        'type' => 'array',
                        'description' => 'Conditions to narrow the rows before aggregating. "field" must be a real '
                            . 'column on the chosen table.',
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
                    'group_by' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional columns to group by, e.g. ["status"] for a per-status breakdown.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum groups to return when group_by is used. Default 50, maximum 200.',
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

        $aggregate = (string) ($input['aggregate'] ?? 'count');
        if (!in_array($aggregate, self::AGGREGATES, true)) {
            return ['error' => "Unknown aggregate \"{$aggregate}\". Valid values: " . implode(', ', self::AGGREGATES)];
        }

        $columns = TableAccess::columns($table);

        $field = (string) ($input['field'] ?? '');
        if ('count' !== $aggregate) {
            if ('' === $field || !in_array($field, $columns, true)) {
                return ['error' => "aggregate \"{$aggregate}\" requires a valid \"field\". Valid columns for {$table}: " . implode(', ', $columns)];
            }
        }

        $groupBy = (array) ($input['group_by'] ?? []);
        $unknownGroup = array_diff($groupBy, $columns);
        if (!empty($unknownGroup)) {
            return ['error' => 'Unknown group_by field(s): ' . implode(', ', $unknownGroup) . '. Valid columns for ' . $table . ': ' . implode(', ', $columns)];
        }

        $query = DB::table($table);

        if ($error = TableAccess::authorizeQuery($employee, $table, $query)) {
            return $error;
        }

        if ($error = $this->applyFilters($query, (array) ($input['filters'] ?? []), $columns, $table)) {
            return $error;
        }

        $sqlFunction = self::SQL_FUNCTIONS[$aggregate];
        $expression = 'count' === $aggregate ? "{$sqlFunction}(*)" : "{$sqlFunction}({$field})";

        try {
            if (empty($groupBy)) {
                $value = $query->selectRaw("{$expression} as agg_value")->value('agg_value');

                return [
                    'table' => $table,
                    'aggregate' => $aggregate,
                    'field' => $field ?: null,
                    'value' => $value,
                ];
            }

            $limit = (int) ($input['limit'] ?? self::DEFAULT_GROUP_LIMIT);
            $limit = max(1, min($limit, self::MAX_GROUP_LIMIT));

            $rows = $query
                ->select($groupBy)
                ->selectRaw("{$expression} as agg_value")
                ->groupBy($groupBy)
                ->orderByDesc('agg_value')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => TableAccess::stripSecrets((array) $row))
                ->all();

            return [
                'table' => $table,
                'aggregate' => $aggregate,
                'field' => $field ?: null,
                'group_by' => $groupBy,
                'count' => count($rows),
                'rows' => $rows,
            ];
        } catch (Throwable $e) {
            return ['error' => 'Query failed: ' . $e->getMessage()];
        }
    }
}
