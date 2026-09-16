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

    /**
     * SENGAJA cuma tiga kata ini, bukan tempat menambahkan needle PII lain
     * (mis. "salary") — dibuktikan lewat tinker saat audit skema ini: begitu
     * "salary" ditambahkan ke sini, stripSecrets() membuang kolom itu dari
     * SEMUA baris tanpa syarat, TERMASUK untuk employee dengan permission
     * eksplisit (employee.section.contract.view) yang seharusnya berhak
     * melihatnya lewat gerbang SENSITIVE_TABLES di bawah — needle ini jadi
     * meniadakan izin yang baru saja diberikan, bukan menambah lapisan.
     * password/token/secret aman berada di sini karena tidak ada permission
     * apa pun yang seharusnya membuat NILAI itu (bukan keberadaan barisnya)
     * layak dikembalikan ke model — beda kategori dari data bisnis seperti
     * gaji, yang harus MUNCUL begitu tabelnya berwenang, bukan selalu hilang.
     * Proteksi kolom sejenis salary ada di tingkat tabel (SENSITIVE_TABLES),
     * bukan di sini.
     */
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
        // Ditambahkan setelah audit skema — employee_contract.salary adalah data
        // gaji personal persis seperti employee_payment, tapi belum pernah masuk
        // daftar ini sama sekali (celah nyata, bukan cuma kandidat "borderline").
        'employee_contract' => [
            'self_field' => 'employee_id',
            'self_permission' => 'my-profile.section.contract.view',
            'permission' => 'employee.section.contract.view',
        ],
        // Data pribadi anggota keluarga (nama, tanggal lahir, dll) — orangnya
        // sendiri tidak pernah memberi izin ke AI ini, jadi diperlakukan sama
        // seperti data pribadi karyawan yang lain.
        'employee_family' => [
            'self_field' => 'employee_id',
            'self_permission' => 'my-profile.section.family.view',
            'permission' => 'employee.section.family.view',
        ],
        // Metadata saja (nama berkas/tipe dokumen) — tool ini tidak pernah
        // membaca isi berkasnya — tapi nama berkas bisa menyingkap isi dokumen
        // (mis. "KTP_...pdf", "Surat_Sakit_...pdf"), jadi tetap digerbang.
        'employee_attachment' => [
            'self_field' => 'employee_id',
            'self_permission' => 'my-profile.section.attachment.view',
            'permission' => 'employee.section.attachment.view',
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
        // Catatan bebas teks tentang perubahan data customer — isinya bisa apa
        // saja tergantung siapa yang menulis, sama seperti customer_credential
        // butuh gerbang eksplisit ketimbang dibiarkan terbuka.
        'customer_history' => [
            'self_field' => null,
            'self_permission' => null,
            'permission' => 'customer.section.history.view',
        ],
        'customer_attachment' => [
            'self_field' => null,
            'self_permission' => null,
            'permission' => 'customer.section.attachment.view',
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

    // employee_history (action/description/performed_by/performed_at) SENGAJA
    // TIDAK dimasukkan ke SENSITIVE_TABLES di atas, beda dari tiga item
    // "borderline" lain yang sudah diputuskan (family/attachment/
    // customer_history). Alasannya: kebijakan ini menggerbang tiap tabel
    // dengan slug permission yang SAMA dengan yang menggerbang halaman
    // manusia untuk data itu (lihat docs/ai-assistant-sensitive-data-policy.md)
    // — tapi employee_history tidak punya halaman sama sekali (nol pemakaian
    // EmployeeHistory model di luar dirinya sendiri, dicek lewat pencarian
    // kode). Menempelkan slug yang kedengarannya cocok tapi sebenarnya tidak
    // menggerbang apa pun akan melanggar prinsip "akses AI = akses UI" itu
    // sendiri, bukan menegakkannya. Kalau tabel ini nanti benar-benar dipakai
    // UI, gerbangnya harus dibuat bersamaan dengan halamannya, lalu
    // didaftarkan di SENSITIVE_TABLES.

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
