<?php

namespace App\Services\Ai\Tools\Concerns;

use Illuminate\Database\Query\Builder;

/**
 * Shared filter validation + application for tools that query a table via
 * the query builder (QueryDataTool, AggregateDataTool). Every filter's
 * "field" is checked against the table's real columns before use, and the
 * operator against a fixed whitelist — all values are bound through the
 * query builder, never interpolated into raw SQL.
 */
trait FiltersTableQueries
{
    /** @var array<int, string> */
    private static array $filterOperators = ['eq', 'like', 'in', 'gt', 'gte', 'lt', 'lte', 'between', 'is_null', 'is_not_null'];

    /**
     * @param array<int, array<string, mixed>> $filters
     * @param array<int, string> $columns
     * @return array{error: string}|null null on success, an error array on validation failure
     */
    private function applyFilters(Builder $query, array $filters, array $columns, string $table): ?array
    {
        foreach ($filters as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $operator = (string) ($filter['operator'] ?? '');

            if (!in_array($field, $columns, true)) {
                return ['error' => "Unknown filter field \"{$field}\". Valid columns for {$table}: " . implode(', ', $columns)];
            }

            if (!in_array($operator, self::$filterOperators, true)) {
                return ['error' => "Unknown operator \"{$operator}\". Valid operators: " . implode(', ', self::$filterOperators)];
            }

            $this->applyFilter($query, $field, $operator, $filter['value'] ?? null);
        }

        return null;
    }

    private function applyFilter(Builder $query, string $field, string $operator, mixed $value): void
    {
        switch ($operator) {
            case 'eq':
                $query->where($field, $value);
                break;
            case 'like':
                $query->where($field, 'like', '%' . $value . '%');
                break;
            case 'in':
                $query->whereIn($field, is_array($value) ? $value : [$value]);
                break;
            case 'gt':
                $query->where($field, '>', $value);
                break;
            case 'gte':
                $query->where($field, '>=', $value);
                break;
            case 'lt':
                $query->where($field, '<', $value);
                break;
            case 'lte':
                $query->where($field, '<=', $value);
                break;
            case 'between':
                $bounds = is_array($value) ? array_values($value) : [];
                if (2 === count($bounds)) {
                    $query->whereBetween($field, $bounds);
                }
                break;
            case 'is_null':
                $query->whereNull($field);
                break;
            case 'is_not_null':
                $query->whereNotNull($field);
                break;
        }
    }

    /**
     * @return array<int, string>
     */
    private function filterOperators(): array
    {
        return self::$filterOperators;
    }
}
