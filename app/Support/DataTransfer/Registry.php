<?php

namespace App\Support\DataTransfer;

use App\Models\Attendance\Branch;
use App\Models\Attendance\Shift;
use App\Models\Employee;
use App\Models\InventoryAsset;
use App\Models\InventoryItem;
use App\Models\InventoryOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Daftar tabel HR & General yang dapat diekspor / diimpor.
 *
 * Izin memakai slug menu halamannya sendiri — tidak ada slug baru:
 *   ekspor  = boleh MEMBUKA halaman tersebut
 *   impor   = hak Create pada halaman itu (atau slug `.manage`-nya)
 */
class Registry
{
    private static ?array $cache = null;
    private static Employee|false|null $employee = null;

    public static function find(string $key): ?Dataset
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, Dataset> */
    public static function all(): array
    {
        return self::$cache ??= collect([
            self::branches(), self::shifts(), self::inventoryItems(), self::inventoryAssets(),
            self::corrections(), self::leavePermits(),
            RecruitmentDatasets::jobs(), RecruitmentDatasets::candidates(), self::interviews(), RecruitmentDatasets::offers(),
            LetterDatasets::templates(), LetterDatasets::codes(), LetterDatasets::requestTypes(),
            self::lettersRegister(), self::letterRequests(), self::contracts(), self::kpiTemplates(),
            RecapDatasets::recruitment(), RecapDatasets::inventory(),
        ])->keyBy('key')->all();
    }

    public static function canExport(Dataset $dataset): bool
    {
        $employee = self::employee();

        return $employee && $employee->canAccessMenu($dataset->viewSlug);
    }

    public static function canImport(Dataset $dataset): bool
    {
        if (!$dataset->importable()) {
            return false;
        }

        [$slug, $action] = $dataset->import['permission'];
        $employee = self::employee();

        if (!$employee) {
            return false;
        }

        return $action === 'view'
            ? $employee->canAccessMenu($slug)
            : $employee->hasMenuPermission($slug, 'can_' . $action);
    }

    private static function employee(): ?Employee
    {
        if (self::$employee === null) {
            $user = session('user');
            self::$employee = ($user['type'] ?? null) === 'employee'
                ? (Employee::find($user['id'] ?? null) ?: false)
                : false;
        }

        return self::$employee ?: null;
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private static function plain(string $label, string $prop, string $format = 'text'): array
    {
        return [
            'key'    => $prop,
            'label'  => $label,
            'get'    => fn ($r) => data_get($r, $prop),
            'format' => $format,
        ];
    }

    private static function yn(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    private static function person(string $alias = 'b'): string
    {
        return "TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name))";
    }

    private static function like($query, string $term, array $columns): void
    {
        if ($term === '') {
            return;
        }

        $query->where(function ($q) use ($term, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', '%' . $term . '%');
            }
        });
    }

    private static function search(Request $request): string
    {
        return trim((string) $request->query('search', $request->query('q', '')));
    }

    private static function dayNames(array $numbers): string
    {
        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return implode(',', array_map(fn ($n) => $names[$n] ?? $n, $numbers));
    }

    /** "Mon,Tue,Wed", "Mon-Fri" or "1,2,3" → [1,2,3]. */
    private static function parseDays(mixed $raw): array
    {
        $text = strtolower((string) Cell::text($raw));

        if ($text === '') {
            throw new \InvalidArgumentException('select at least one day, e.g. Mon-Fri');
        }

        $map = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7,
                'senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'sabtu' => 6, 'minggu' => 7];

        $toNumber = function (string $part) use ($map) {
            $part = trim($part);

            if (isset($map[substr($part, 0, 3)]) && !is_numeric($part)) {
                return $map[substr($part, 0, 3)];
            }

            if (isset($map[$part])) {
                return $map[$part];
            }

            if (ctype_digit($part) && (int) $part >= 1 && (int) $part <= 7) {
                return (int) $part;
            }

            throw new \InvalidArgumentException("\"{$part}\" is not a weekday");
        };

        $days = [];

        foreach (preg_split('/[,;\/ ]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
            if (str_contains($chunk, '-')) {
                [$from, $to] = array_pad(explode('-', $chunk, 2), 2, '');
                $a = $toNumber($from);
                $b = $toNumber($to);

                for ($d = $a; ; $d = $d % 7 + 1) {
                    $days[] = $d;
                    if ($d === $b) {
                        break;
                    }
                }
            } else {
                $days[] = $toNumber($chunk);
            }
        }

        return array_values(array_unique($days));
    }

    // ── Master data: export + import ────────────────────────────────────────

    private static function branches(): Dataset
    {
        return new Dataset(
            'attendance.branches', 'Branches', 'management.branches',
            [
                ['key' => 'code', 'label' => 'Code', 'required' => true, 'get' => fn ($r) => $r->code,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:50'],
                 'hint' => 'Unique branch code. An existing code is updated.', 'example' => 'JKT-HO'],
                ['key' => 'name', 'label' => 'Name', 'required' => true, 'get' => fn ($r) => $r->name,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'],
                 'hint' => 'Branch / office name.', 'example' => 'Jakarta Head Office'],
                ['key' => 'city', 'label' => 'City', 'get' => fn ($r) => $r->city,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:100'], 'example' => 'Jakarta'],
                ['key' => 'province', 'label' => 'Province', 'get' => fn ($r) => $r->province,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:100'], 'example' => 'DKI Jakarta'],
                ['key' => 'address', 'label' => 'Address', 'get' => fn ($r) => $r->address,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:1000'], 'example' => 'Jl. Contoh No. 1'],
                ['key' => 'phone', 'label' => 'Phone', 'get' => fn ($r) => $r->phone,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:50'], 'example' => '021-5550000'],
                ['key' => 'latitude', 'label' => 'Latitude', 'required' => true, 'get' => fn ($r) => (float) $r->latitude,
                 'parse' => fn ($v) => Cell::number($v), 'rules' => ['required', 'numeric', 'between:-90,90'],
                 'hint' => 'Decimal degrees, -90 to 90.', 'example' => -6.2],
                ['key' => 'longitude', 'label' => 'Longitude', 'required' => true, 'get' => fn ($r) => (float) $r->longitude,
                 'parse' => fn ($v) => Cell::number($v), 'rules' => ['required', 'numeric', 'between:-180,180'],
                 'hint' => 'Decimal degrees, -180 to 180.', 'example' => 106.816666],
                ['key' => 'radius_meters', 'label' => 'Radius (m)', 'format' => 'int', 'get' => fn ($r) => (int) $r->radius_meters,
                 'parse' => fn ($v) => Cell::number($v) ?? 100,
                 'rules' => ['required', 'integer', 'between:' . Branch::RADIUS_MIN . ',' . Branch::RADIUS_MAX],
                 'hint' => 'Geofence radius, ' . Branch::RADIUS_MIN . '–' . Branch::RADIUS_MAX . '. Blank = 100.', 'example' => 100],
                ['key' => 'geofence_override', 'label' => 'Geofence Override', 'get' => fn ($r) => $r->geofence_override,
                 'parse' => fn ($v) => ($t = strtolower((string) Cell::text($v))) === '' ? null : $t,
                 'rules' => ['nullable', Rule::in(Branch::GEOFENCE_OVERRIDES)],
                 'hint' => 'off, flag or enforce. Blank = follow the global attendance policy.', 'example' => ''],
                ['key' => 'is_head_office', 'label' => 'Head Office', 'get' => fn ($r) => self::yn($r->is_head_office),
                 'parse' => fn ($v) => Cell::yesNo($v), 'rules' => ['boolean'],
                 'hint' => 'Yes/No. Only one head office; setting Yes moves the flag.', 'example' => 'No'],
                ['key' => 'is_active', 'label' => 'Active', 'get' => fn ($r) => self::yn($r->is_active),
                 'parse' => fn ($v) => Cell::yesNo($v, true), 'rules' => ['boolean'],
                 'hint' => 'Yes/No. Blank = Yes.', 'example' => 'Yes'],
            ],
            function (Request $request) {
                $status = $request->query('status', 'all');

                return Branch::query()
                    ->tap(fn ($q) => self::like($q, self::search($request), ['name', 'code', 'city', 'province']))
                    ->when($status === 'active', fn ($q) => $q->where('is_active', true))
                    ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
                    ->orderByDesc('is_head_office')->orderBy('name')->get();
            },
            [
                'permission' => ['management.branches.manage', 'view'],
                'key'        => 'code',
                'find'       => fn (array $v) => Branch::where('code', $v['code'])->first(),
                'save'       => function (array $v, ?Branch $existing, int $actor) {
                    $data = [
                        'code' => $v['code'], 'name' => $v['name'], 'city' => $v['city'], 'province' => $v['province'],
                        'address' => $v['address'], 'phone' => $v['phone'],
                        'latitude' => $v['latitude'], 'longitude' => $v['longitude'],
                        'radius_meters' => (int) $v['radius_meters'],
                        'geofence_override' => $v['geofence_override'] ?: null,
                        'is_head_office' => (bool) $v['is_head_office'], 'is_active' => (bool) $v['is_active'],
                    ];

                    if ($data['is_head_office']) {
                        Branch::where('is_head_office', true)
                            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                            ->update(['is_head_office' => false]);
                    }

                    return $existing
                        ? tap($existing)->update($data)
                        : Branch::create($data + ['created_by' => $actor]);
                },
            ]
        );
    }

    private static function shifts(): Dataset
    {
        return new Dataset(
            'attendance.shifts', 'Shifts', 'general.settings.shifts',
            [
                ['key' => 'name', 'label' => 'Name', 'required' => true, 'get' => fn ($r) => $r->name,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:100'],
                 'hint' => 'Unique shift name. An existing name is updated.', 'example' => 'Office Hours'],
                ['key' => 'check_in_time', 'label' => 'Check In', 'required' => true, 'get' => fn ($r) => substr((string) $r->check_in_time, 0, 5),
                 'parse' => fn ($v) => Cell::time($v), 'rules' => ['required', 'date_format:H:i'],
                 'hint' => '24-hour time, HH:MM.', 'example' => '08:00'],
                ['key' => 'check_out_time', 'label' => 'Check Out', 'required' => true, 'get' => fn ($r) => substr((string) $r->check_out_time, 0, 5),
                 'parse' => fn ($v) => Cell::time($v), 'rules' => ['required', 'date_format:H:i'],
                 'hint' => '24-hour time, HH:MM.', 'example' => '17:00'],
                ['key' => 'late_tolerance_minutes', 'label' => 'Late Tolerance (min)', 'format' => 'int',
                 'get' => fn ($r) => (int) $r->late_tolerance_minutes,
                 'parse' => fn ($v) => Cell::number($v) ?? 0, 'rules' => ['required', 'integer', 'between:0,480'],
                 'hint' => '0–480. Blank = 0.', 'example' => 15],
                ['key' => 'break_minutes', 'label' => 'Break (min)', 'format' => 'int', 'get' => fn ($r) => (int) $r->break_minutes,
                 'parse' => fn ($v) => Cell::number($v) ?? 0, 'rules' => ['required', 'integer', 'between:0,480'],
                 'hint' => '0–480. Blank = 0.', 'example' => 60],
                ['key' => 'work_days', 'label' => 'Work Days', 'required' => true,
                 'get' => fn ($r) => self::dayNames($r->workDayNumbers()),
                 'parse' => fn ($v) => self::parseDays($v), 'rules' => ['required', 'array', 'min:1'],
                 'hint' => 'Mon,Tue,Wed… or a range like Mon-Fri (or numbers 1–7, 1 = Monday).', 'example' => 'Mon-Fri'],
                ['key' => 'crosses_midnight', 'label' => 'Crosses Midnight', 'get' => fn ($r) => self::yn($r->crosses_midnight),
                 'parse' => fn ($v) => Cell::yesNo($v), 'rules' => ['boolean'],
                 'hint' => 'Yes/No. Yes when check-out falls on the next day.', 'example' => 'No'],
                ['key' => 'is_default', 'label' => 'Default', 'get' => fn ($r) => self::yn($r->is_default),
                 'parse' => fn ($v) => Cell::yesNo($v), 'rules' => ['boolean'],
                 'hint' => 'Yes/No. Only one default; Yes moves it. The default must be active.', 'example' => 'Yes'],
                ['key' => 'is_active', 'label' => 'Active', 'get' => fn ($r) => self::yn($r->is_active),
                 'parse' => fn ($v) => Cell::yesNo($v, true), 'rules' => ['boolean'],
                 'hint' => 'Yes/No. Blank = Yes.', 'example' => 'Yes'],
                ['key' => 'notes', 'label' => 'Notes', 'get' => fn ($r) => $r->notes,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:1000'], 'example' => ''],
            ],
            function (Request $request) {
                return Shift::query()
                    ->tap(fn ($q) => self::like($q, self::search($request), ['name']))
                    ->orderByDesc('is_default')->orderBy('name')->get();
            },
            [
                'permission' => ['general.settings.shifts.manage', 'view'],
                'key'        => 'name',
                'check'      => function (array $v) {
                    if (!$v['crosses_midnight'] && $v['check_out_time'] <= $v['check_in_time']) {
                        return 'Check Out must be later than Check In, unless Crosses Midnight is Yes.';
                    }

                    if ($v['is_default'] && !$v['is_active']) {
                        return 'The default shift must be active.';
                    }

                    $existing = Shift::where('name', $v['name'])->first();
                    if ($existing && $existing->is_default && !$v['is_active']) {
                        return 'This is the default shift and cannot be deactivated. Set another shift as default first.';
                    }

                    return null;
                },
                'find'       => fn (array $v) => Shift::where('name', $v['name'])->first(),
                'save'       => function (array $v, ?Shift $existing, int $actor) {
                    $data = [
                        'name' => $v['name'], 'check_in_time' => $v['check_in_time'], 'check_out_time' => $v['check_out_time'],
                        'late_tolerance_minutes' => (int) $v['late_tolerance_minutes'], 'break_minutes' => (int) $v['break_minutes'],
                        'work_days' => implode(',', $v['work_days']),
                        'crosses_midnight' => (bool) $v['crosses_midnight'], 'is_default' => (bool) $v['is_default'],
                        'is_active' => (bool) $v['is_active'], 'notes' => $v['notes'],
                    ];

                    $saved = $existing ? tap($existing)->update($data) : Shift::create($data);

                    if ($data['is_default']) {
                        Shift::where('id', '!=', $saved->id)->where('is_default', true)->update(['is_default' => false]);
                    }

                    return $saved;
                },
            ]
        );
    }

    private static function inventoryItems(): Dataset
    {
        $option = fn (string $field, string $what) => fn ($v) => Cell::choice($v, InventoryOption::choices($field), $what);
        $label  = fn (string $field) => fn ($r) => InventoryOption::labels($field)[$r->{self::fieldColumn($field)}]
            ?? $r->{self::fieldColumn($field)};

        return new Dataset(
            'inventory.items', 'Inventory Items', 'general.inventory.items',
            [
                ['key' => 'code', 'label' => 'Code', 'get' => fn ($r) => $r->code,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:30'],
                 'hint' => 'Leave blank to generate one (INV-00001). An existing code is updated.', 'example' => ''],
                ['key' => 'name', 'label' => 'Name', 'required' => true, 'get' => fn ($r) => $r->name,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'], 'example' => 'A4 Paper 80gsm'],
                ['key' => 'category', 'label' => 'Category', 'required' => true, 'get' => $label('item_category'),
                 'parse' => $option('item_category', 'category'), 'rules' => ['required'],
                 'hint' => 'One of: ' . implode(', ', InventoryOption::choices('item_category')), 'example' => array_values(InventoryOption::choices('item_category'))[0] ?? ''],
                ['key' => 'unit', 'label' => 'Unit', 'required' => true, 'get' => $label('item_unit'),
                 'parse' => $option('item_unit', 'unit'), 'rules' => ['required'],
                 'hint' => 'One of: ' . implode(', ', InventoryOption::choices('item_unit')), 'example' => array_values(InventoryOption::choices('item_unit'))[0] ?? ''],
                ['key' => 'quantity', 'label' => 'Quantity', 'required' => true, 'format' => 'int', 'get' => fn ($r) => (int) $r->quantity,
                 'parse' => fn ($v) => Cell::number($v), 'rules' => ['required', 'integer', 'min:0', 'max:1000000'], 'example' => 20],
                ['key' => 'min_stock', 'label' => 'Min Stock', 'format' => 'int', 'get' => fn ($r) => (int) $r->min_stock,
                 'parse' => fn ($v) => Cell::number($v) ?? 0, 'rules' => ['required', 'integer', 'min:0', 'max:1000000'],
                 'hint' => 'Blank = 0.', 'example' => 5],
                ['key' => 'unit_price', 'label' => 'Unit Price', 'format' => 'money', 'get' => fn ($r) => (float) $r->unit_price,
                 'parse' => fn ($v) => Cell::number($v) ?? 0, 'rules' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
                 'hint' => 'Blank = 0.', 'example' => 45000],
                ['key' => 'location', 'label' => 'Location', 'get' => $label('location'),
                 'parse' => $option('location', 'location'), 'rules' => ['nullable'],
                 'hint' => 'One of: ' . implode(', ', InventoryOption::choices('location')) . '. Optional.', 'example' => ''],
                ['key' => 'status_override', 'label' => 'Status Override',
                 'get' => fn ($r) => $r->status_override ? (InventoryItem::STATUSES[$r->status_override] ?? $r->status_override) : '',
                 'parse' => fn ($v) => Cell::choice($v, InventoryItem::STATUSES, 'status'),
                 'rules' => ['nullable', Rule::in(array_keys(InventoryItem::STATUSES))],
                 'hint' => 'Optional. Leave blank so the status follows the stock.', 'example' => ''],
                ['key' => 'notes', 'label' => 'Notes', 'get' => fn ($r) => $r->notes,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:2000'], 'example' => ''],
            ],
            function (Request $request) {
                return InventoryItem::query()
                    ->tap(fn ($q) => self::like($q, self::search($request), ['name', 'code', 'location']))
                    ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
                    ->when($request->query('location'), fn ($q, $l) => $q->where('location', $l))
                    ->orderBy('name')->get();
            },
            [
                'permission' => ['general.inventory.items', 'create'],
                'key'        => 'code',
                'find'       => fn (array $v) => !empty($v['code']) ? InventoryItem::where('code', $v['code'])->first() : null,
                'save'       => function (array $v, ?InventoryItem $existing, int $actor) {
                    $data = collect($v)->only(['name', 'category', 'unit', 'quantity', 'min_stock', 'unit_price', 'location', 'status_override', 'notes'])
                        ->map(fn ($x) => $x === '' ? null : $x)->all();
                    $data['quantity']  = (int) $data['quantity'];
                    $data['min_stock'] = (int) $data['min_stock'];

                    if ($existing) {
                        return tap($existing)->update($data + ['updated_by' => $actor]);
                    }

                    $record = InventoryItem::create($data + [
                        'code' => 'PENDING-' . uniqid(), 'created_by' => $actor, 'updated_by' => $actor,
                    ]);
                    $record->update(['code' => $v['code'] ?: 'INV-' . str_pad((string) $record->id, 5, '0', STR_PAD_LEFT)]);

                    return $record;
                },
            ]
        );
    }

    private static function inventoryAssets(): Dataset
    {
        $option = fn (string $field, string $what) => fn ($v) => Cell::choice($v, InventoryOption::choices($field), $what);
        $label  = fn (string $field, string $column) => fn ($r) => InventoryOption::labels($field)[$r->{$column}] ?? $r->{$column};

        return new Dataset(
            'inventory.assets', 'Assets', 'general.inventory.assets',
            [
                ['key' => 'code', 'label' => 'Code', 'get' => fn ($r) => $r->code,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:30'],
                 'hint' => 'Leave blank to generate one (AST-00001). An existing code is updated.', 'example' => ''],
                ['key' => 'name', 'label' => 'Name', 'required' => true, 'get' => fn ($r) => $r->name,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'], 'example' => 'Laptop ThinkPad T14'],
                ['key' => 'category', 'label' => 'Category', 'required' => true, 'get' => $label('asset_category', 'category'),
                 'parse' => $option('asset_category', 'category'), 'rules' => ['required'],
                 'hint' => 'One of: ' . implode(', ', InventoryOption::choices('asset_category')), 'example' => array_values(InventoryOption::choices('asset_category'))[0] ?? ''],
                ['key' => 'brand', 'label' => 'Brand', 'get' => fn ($r) => $r->brand,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:100'], 'example' => 'Lenovo'],
                ['key' => 'serial_number', 'label' => 'Serial Number', 'get' => fn ($r) => $r->serial_number,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:100'], 'example' => ''],
                ['key' => 'assignee_eci', 'label' => 'Assignee ECI', 'get' => fn ($r) => $r->assignee_eci ?? '',
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:50'],
                 'hint' => 'ECI of the employee holding the asset. Blank = unassigned.', 'example' => ''],
                ['key' => 'purchase_date', 'label' => 'Purchase Date', 'get' => fn ($r) => $r->purchase_date?->toDateString(),
                 'parse' => fn ($v) => Cell::date($v), 'rules' => ['nullable', 'date'],
                 'hint' => 'YYYY-MM-DD.', 'example' => '2026-01-15'],
                ['key' => 'purchase_price', 'label' => 'Purchase Price', 'format' => 'money', 'get' => fn ($r) => (float) $r->purchase_price,
                 'parse' => fn ($v) => Cell::number($v) ?? 0, 'rules' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
                 'hint' => 'Blank = 0.', 'example' => 15000000],
                ['key' => 'condition', 'label' => 'Condition', 'required' => true, 'get' => $label('asset_condition', 'condition'),
                 'parse' => $option('asset_condition', 'condition'), 'rules' => ['required'],
                 'hint' => 'One of: ' . implode(', ', InventoryOption::choices('asset_condition')), 'example' => array_values(InventoryOption::choices('asset_condition'))[0] ?? ''],
                ['key' => 'status', 'label' => 'Status', 'required' => true, 'get' => $label('asset_status', 'status'),
                 'parse' => $option('asset_status', 'status'), 'rules' => ['required'],
                 'hint' => 'One of: ' . implode(', ', InventoryOption::choices('asset_status')), 'example' => array_values(InventoryOption::choices('asset_status'))[0] ?? ''],
                ['key' => 'location', 'label' => 'Location', 'get' => $label('location', 'location'),
                 'parse' => $option('location', 'location'), 'rules' => ['nullable'],
                 'hint' => 'One of: ' . implode(', ', InventoryOption::choices('location')) . '. Optional.', 'example' => ''],
                ['key' => 'notes', 'label' => 'Notes', 'get' => fn ($r) => $r->notes,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:2000'], 'example' => ''],
            ],
            function (Request $request) {
                return InventoryAsset::query()
                    ->leftJoin('employee as e', 'e.employee_id', '=', 'inventory_assets.assignee_employee_id')
                    ->select('inventory_assets.*', 'e.eci as assignee_eci')
                    ->tap(fn ($q) => self::like($q, self::search($request), [
                        'inventory_assets.name', 'inventory_assets.code', 'inventory_assets.serial_number', 'inventory_assets.brand',
                    ]))
                    ->when($request->query('category'), fn ($q, $c) => $q->where('inventory_assets.category', $c))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('inventory_assets.status', $s))
                    ->when($request->query('condition'), fn ($q, $c) => $q->where('inventory_assets.condition', $c))
                    ->when($request->query('location'), fn ($q, $l) => $q->where('inventory_assets.location', $l))
                    ->orderBy('inventory_assets.name')->get();
            },
            [
                'permission' => ['general.inventory.assets', 'create'],
                'key'        => 'code',
                'check'      => function (array $v) {
                    if (!empty($v['assignee_eci']) && !Employee::where('eci', $v['assignee_eci'])->exists()) {
                        return "Assignee ECI \"{$v['assignee_eci']}\" does not match any employee.";
                    }

                    return null;
                },
                'find'       => fn (array $v) => !empty($v['code']) ? InventoryAsset::where('code', $v['code'])->first() : null,
                'save'       => function (array $v, ?InventoryAsset $existing, int $actor) {
                    $assignee = !empty($v['assignee_eci'])
                        ? Employee::where('eci', $v['assignee_eci'])->value('employee_id')
                        : null;

                    $status = $v['status'];
                    if ($assignee) {
                        $status = $status === 'available' ? 'in_use' : $status;
                    } elseif ($status === 'in_use') {
                        $status = 'available';
                    }

                    $data = [
                        'name' => $v['name'], 'category' => $v['category'], 'brand' => $v['brand'],
                        'serial_number' => $v['serial_number'], 'assignee_employee_id' => $assignee,
                        'purchase_date' => $v['purchase_date'], 'purchase_price' => $v['purchase_price'],
                        'condition' => $v['condition'], 'status' => $status, 'location' => $v['location'] ?: null,
                        'notes' => $v['notes'],
                    ];

                    if ($existing) {
                        return tap($existing)->update($data + ['updated_by' => $actor]);
                    }

                    $record = InventoryAsset::create($data + [
                        'code' => 'PENDING-' . uniqid(), 'created_by' => $actor, 'updated_by' => $actor,
                    ]);
                    $record->update(['code' => $v['code'] ?: 'AST-' . str_pad((string) $record->id, 5, '0', STR_PAD_LEFT)]);

                    return $record;
                },
            ]
        );
    }

    /** DB column that holds the value of an inventory option field. */
    private static function fieldColumn(string $field): string
    {
        return match ($field) {
            'item_category', 'asset_category' => 'category',
            'item_unit'                       => 'unit',
            'asset_condition'                 => 'condition',
            'asset_status'                    => 'status',
            default                           => $field,
        };
    }

    // ── Export only ─────────────────────────────────────────────────────────

    private static function corrections(): Dataset
    {
        return new Dataset(
            'attendance.corrections', 'Attendance Corrections', 'general.attendance.correction',
            [
                self::plain('ECI', 'eci'), self::plain('Employee', 'employee_name'),
                self::plain('Attendance Date', 'attendance_date'),
                self::plain('Requested Check In', 'requested_check_in'), self::plain('Requested Check Out', 'requested_check_out'),
                self::plain('Original Check In', 'original_check_in'), self::plain('Original Check Out', 'original_check_out'),
                self::plain('Reason', 'reason'), self::plain('Status', 'status'),
                self::plain('HR Note', 'hr_note'), self::plain('Decided At', 'approved_at'), self::plain('Submitted At', 'created_at'),
            ],
            function (Request $request) {
                return DB::table('attendance_corrections as c')
                    ->join('employee as e', 'e.employee_id', '=', 'c.employee_id')
                    ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'c.employee_id')
                    ->select('c.*', 'e.eci', DB::raw(self::person() . ' as employee_name'))
                    ->tap(fn ($q) => self::like($q, self::search($request), ['e.eci', 'b.first_name', 'b.last_name', 'c.reason']))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('c.status', $s))
                    ->orderByDesc('c.attendance_date')->orderByDesc('c.id')->get();
            }
        );
    }

    private static function leavePermits(): Dataset
    {
        return new Dataset(
            'leave-permit.applications', 'Leave & Permit Applications', 'hr_general.leave_permit',
            [
                self::plain('Application No.', 'application_no'), self::plain('ECI', 'eci'), self::plain('Employee', 'employee_name'),
                self::plain('Type', 'type_name'), self::plain('Start', 'start_date'), self::plain('End', 'end_date'),
                self::plain('Days', 'total_days', 'money'), self::plain('Reason', 'reason'), self::plain('Status', 'status'),
                self::plain('Reviewed At', 'reviewed_at'), self::plain('Submitted At', 'created_at'),
            ],
            function (Request $request) {
                return DB::table('leave_permit_applications as a')
                    ->join('employee as e', 'e.employee_id', '=', 'a.employee_id')
                    ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'a.employee_id')
                    ->leftJoin('leave_permit_types as t', 't.id', '=', 'a.leave_permit_type_id')
                    ->select('a.*', 'e.eci', 't.name as type_name', DB::raw(self::person() . ' as employee_name'))
                    ->tap(fn ($q) => self::like($q, self::search($request), ['a.application_no', 'e.eci', 'b.first_name', 'b.last_name']))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('a.status', $s))
                    // Monthly / annual recap: `year` alone = the whole year, `year` + `month` = that month (by start date).
                    ->when($request->query('year'), fn ($q, $y) => $q->whereYear('a.start_date', (int) $y))
                    ->when($request->query('month'), fn ($q, $m) => $q->whereMonth('a.start_date', (int) $m))
                    ->orderByDesc('a.start_date')->orderByDesc('a.id')->get();
            }
        );
    }

    private static function interviews(): Dataset
    {
        return new Dataset(
            'recruitment.interviews', 'Interview Schedule', 'general.recruitment.schedule',
            [
                self::plain('Candidate', 'candidate_name'), self::plain('Title', 'title'), self::plain('Stage', 'stage'),
                self::plain('Scheduled At', 'scheduled_at'), self::plain('Duration (min)', 'duration_minutes', 'int'),
                self::plain('Mode', 'mode'), self::plain('Location', 'location'), self::plain('Meeting URL', 'meeting_url'),
                self::plain('Status', 'status'), self::plain('Notes', 'notes'),
            ],
            function (Request $request) {
                return DB::table('recruitment_interviews as i')
                    ->join('recruitment_candidates as c', 'c.id', '=', 'i.candidate_id')
                    ->select('i.*', 'c.name as candidate_name')
                    ->tap(fn ($q) => self::like($q, self::search($request), ['c.name', 'i.title', 'i.location']))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('i.status', $s))
                    ->orderByDesc('i.scheduled_at')->get();
            }
        );
    }

    private static function lettersRegister(): Dataset
    {
        return new Dataset(
            'letters.register', 'Letter Register', 'general.letters.register',
            [
                self::plain('Direction', 'direction'), self::plain('Letter Number', 'letter_number'),
                self::plain('Agenda Number', 'agenda_number'), self::plain('Letter Date', 'letter_date'),
                self::plain('Received Date', 'received_date'), self::plain('Counterparty', 'counterparty'),
                self::plain('Subject', 'subject'), self::plain('Delivered Via', 'delivered_via'),
                self::plain('Status', 'status'), self::plain('Sent At', 'sent_at'), self::plain('Notes', 'notes'),
            ],
            function (Request $request) {
                return DB::table('letters')->whereNull('deleted_at')
                    ->tap(fn ($q) => self::like($q, self::search($request), ['letter_number', 'subject', 'counterparty', 'agenda_number']))
                    ->when($request->query('direction'), fn ($q, $d) => $q->where('direction', $d))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                    ->orderByDesc('letter_date')->orderByDesc('id')->get();
            }
        );
    }

    private static function letterRequests(): Dataset
    {
        return new Dataset(
            'letters.requests', 'Letter Requests', 'general.letters.requests',
            [
                self::plain('ECI', 'eci'), self::plain('Employee', 'employee_name'),
                self::plain('Request Type', 'request_type_name'), self::plain('Language', 'language'),
                self::plain('Purpose', 'purpose'), self::plain('Needed By', 'needed_by'),
                self::plain('Status', 'status'), self::plain('Handled At', 'handled_at'),
                self::plain('Reject Reason', 'reject_reason'), self::plain('Submitted At', 'created_at'),
            ],
            function (Request $request) {
                return DB::table('letter_requests as r')
                    ->join('employee as e', 'e.employee_id', '=', 'r.employee_id')
                    ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'r.employee_id')
                    ->select('r.*', 'e.eci', DB::raw(self::person() . ' as employee_name'))
                    ->tap(fn ($q) => self::like($q, self::search($request), ['e.eci', 'b.first_name', 'b.last_name', 'r.purpose', 'r.request_type_name']))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('r.status', $s))
                    ->orderByDesc('r.id')->get();
            }
        );
    }

    private static function contracts(): Dataset
    {
        return new Dataset(
            'contracts.list', 'Contracts', 'general.contracts.list',
            [
                self::plain('Contract No.', 'contract_number'), self::plain('ECI', 'eci'), self::plain('Employee', 'employee_name'),
                self::plain('Contract Name', 'contract_name'), self::plain('Type', 'contract_type'),
                self::plain('Position', 'position'), self::plain('Department', 'department'),
                self::plain('Work Location', 'work_location'), self::plain('Start Date', 'start_date'),
                self::plain('End Date', 'end_date'), self::plain('Signed Date', 'signed_date'),
                self::plain('Status', 'lifecycle_status'),
            ],
            function (Request $request) {
                return DB::table('employee_contract as c')
                    ->join('employee as e', 'e.employee_id', '=', 'c.employee_id')
                    ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'c.employee_id')
                    ->select('c.*', 'e.eci', DB::raw(self::person() . ' as employee_name'))
                    ->tap(fn ($q) => self::like($q, self::search($request), ['c.contract_number', 'e.eci', 'b.first_name', 'b.last_name', 'c.position']))
                    ->when($request->query('status'), fn ($q, $s) => $q->where('c.lifecycle_status', $s))
                    ->orderByDesc('c.start_date')->get();
            }
        );
    }

    private static function kpiTemplates(): Dataset
    {
        return new Dataset(
            'kpi.templates', 'KPI Templates', 'general.kpi-evaluation.templates',
            [
                self::plain('Name', 'name'), self::plain('Description', 'description'),
                self::plain('Period', 'period_type'), self::plain('Target Type', 'target_type'),
                self::plain('Anonymous', 'anonymous'), self::plain('Score Divisor', 'score_divisor', 'int'),
                self::plain('Deadline Day', 'deadline_day', 'int'), self::plain('Deadline Month', 'deadline_month', 'int'),
                self::plain('Deadline Year', 'deadline_year', 'int'), self::plain('Active', 'active'),
            ],
            function (Request $request) {
                return DB::table('kpi_templates')
                    ->selectRaw("kpi_templates.*, IF(is_anonymous, 'Yes', 'No') as anonymous, IF(is_active, 'Yes', 'No') as active")
                    ->tap(fn ($q) => self::like($q, self::search($request), ['name', 'description']))
                    ->orderBy('name')->get();
            }
        );
    }
}
