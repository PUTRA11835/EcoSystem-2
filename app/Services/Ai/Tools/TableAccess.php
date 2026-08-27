<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backing for the assistant's database-wide tools (list_tables, query_data,
 * aggregate_data).
 *
 * Three layers of protection, in increasing order of nuance:
 *
 *   - EXCLUDED_TABLES: framework/session/token tables. These don't hold
 *     business data — they hold live credentials (a session or personal
 *     access token row IS a working login for whoever reads it) or
 *     framework plumbing (queue/cache/migration bookkeeping). Exposing them
 *     would be an account-takeover primitive, not a data-access question.
 *     Always blocked, no exceptions.
 *   - SECRET_COLUMN_NEEDLES: column-name substrings stripped from every
 *     returned row on every table, so a secret column added to some other
 *     table in the future doesn't get exposed just because it wasn't in an
 *     exclusion list — matches app\Models\AuthUser's own $hidden intent
 *     (password / remember_token / cp_token) but applies universally.
 *   - SENSITIVE_TABLES: business-data tables gated by the menu permission
 *     slugs already used elsewhere in the app for the same data (see
 *     docs/ai-assistant-sensitive-data-policy.md for the rationale per
 *     table). Where a "my-profile.section.*" self-service slug exists
 *     (employee bank/payment/identification), an employee who only has
 *     that slug still gets to see their OWN row — authorizeQuery() forces
 *     a `self_field = current employee` filter onto the query rather than
 *     denying outright, mirroring how GetTicketsTool falls back to "my
 *     tickets" instead of refusing. Every other table not listed here
 *     stays open to any authenticated employee, same as today.
 */
class TableAccess
{
    private const EXCLUDED_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'api_refresh_tokens',
    ];

    private const SECRET_COLUMN_NEEDLES = ['password', 'token', 'secret'];

    /**
     * @var array<string, array{self_field: ?string, self_permission: ?string, permission: string}>
     */
    private const SENSITIVE_TABLES = [
        'employee_bank' => [
            'self_field' => 'employee_id',
            'self_permission' => 'my-profile.section.bank.view',
            'permission' => 'employee.section.bank.view',
        ],
        'employee_payment' => [
            'self_field' => 'employee_id',
            'self_permission' => 'my-profile.section.payment.view',
            'permission' => 'employee.section.payment.view',
        ],
        'employee_identification' => [
            'self_field' => 'employee_id',
            'self_permission' => 'my-profile.section.identification.view',
            'permission' => 'employee.section.identification.view',
        ],
        'customer_bank' => [
            'self_field' => null,
            'self_permission' => null,
            'permission' => 'customer.section.bank.view',
        ],
        'customer_credential' => [
            'self_field' => null,
            'self_permission' => null,
            'permission' => 'customer.section.credential.view',
        ],
        'customer_identification' => [
            'self_field' => null,
            'self_permission' => null,
            'permission' => 'customer.section.identification.view',
        ],
        'login_activity' => [
            'self_field' => null,
            'self_permission' => null,
            'permission' => 'control-center.login-log',
        ],
        'auth_users' => [
            'self_field' => null,
            'self_permission' => null,
            'permission' => 'control-center.login-log',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function allTables(): array
    {
        $db = DB::connection()->getDatabaseName();
        $key = 'Tables_in_' . $db;

        $tables = array_map(
            fn (object $row) => $row->$key,
            DB::select('SHOW TABLES'),
        );

        $tables = array_values(array_diff($tables, self::EXCLUDED_TABLES));
        sort($tables);

        return $tables;
    }

    public static function isQueryable(string $table): bool
    {
        return !in_array($table, self::EXCLUDED_TABLES, true) && Schema::hasTable($table);
    }

    /**
     * @return array<int, string>
     */
    public static function columns(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    /**
     * Enforce SENSITIVE_TABLES for one table, mutating $query in place with
     * a forced self-scope filter when that's the applicable case.
     *
     * @return array{error: string}|null null if the query may proceed (as-is
     *   or with a self-scope filter now applied to it), an error array if it
     *   must be refused outright.
     */
    public static function authorizeQuery(Employee $employee, string $table, Builder $query): ?array
    {
        $rule = self::SENSITIVE_TABLES[$table] ?? null;

        if (!$rule) {
            return null;
        }

        if ($employee->hasMenuPermission($rule['permission'])) {
            return null;
        }

        if ($rule['self_permission'] && $employee->hasMenuPermission($rule['self_permission'])) {
            $query->where($rule['self_field'], $employee->employee_id);

            return null;
        }

        return [
            'error' => "The current user doesn't have permission to view {$table} data.",
        ];
    }

    /**
     * Strip secret-shaped columns from a row (keyed array) before it's
     * returned to the model.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function stripSecrets(array $row): array
    {
        foreach (array_keys($row) as $column) {
            $lower = strtolower($column);
            foreach (self::SECRET_COLUMN_NEEDLES as $needle) {
                if (str_contains($lower, $needle)) {
                    unset($row[$column]);
                    break;
                }
            }
        }

        return $row;
    }
}
