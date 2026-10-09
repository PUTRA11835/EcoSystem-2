<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Employee;
use App\Models\InventoryAsset;
use App\Models\InventoryItem;
use App\Models\InventoryOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * General Affairs → Inventory & Assets, three tabs of one page (the Settings tab is InventorySettingController):
 *
 *   Overview   (general.inventory.overview)  the recap of both registers
 *   Inventory  (general.inventory.items)     office inventory & consumables kept as stock quantities
 *   Assets     (general.inventory.assets)    one row per tracked company asset; the assignee is a searchable dropdown
 *
 * V list · C add · E edit (items: adjust stock · assets: assign) · D delete. Items made inactive and assets disposed of
 * stay in their register but leave every total on the Overview. Add / edit happen in a modal on the list page.
 *
 * What the two forms share: an optional photo (private disk, served through a route guarded by the tab's slug) and money
 * typed as "15.000.000" (only the digits are kept). Columns sort only where it means something: names (A–Z), numbers
 * and dates; dropdown columns are filtered, not sorted.
 */
class InventoryController extends Controller
{
    use HandlesRecruitmentTables;

    private const PHOTO_RULES = ['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'remove_photo' => ['nullable', 'boolean']];

    /** Tab the sidebar link opens: the first one this person may see. */
    public function home()
    {
        $employee = Employee::find(session('user.id'));
        $route = collect([
            'general.inventory.overview' => 'general.inventory.overview',
            'general.inventory.items'    => 'general.inventory.items.index',
            'general.inventory.assets'   => 'general.inventory.assets.index',
            'general.inventory.settings' => 'general.inventory.settings.index',
        ])->first(fn ($route, $slug) => $employee?->canAccessMenu($slug));

        abort_if($route === null, 403);

        return redirect()->route($route);
    }

    // ── Overview ────────────────────────────────────────────────────────────

    public function overview()
    {
        $status = InventoryItem::statusSql();
        $stock = InventoryItem::query()->whereRaw("{$status} != ?", [InventoryItem::STATUS_INACTIVE]);
        $assets = InventoryAsset::query()->where('status', '!=', InventoryAsset::STATUS_DISPOSED);

        $summary = [
            'lines'       => (clone $stock)->count(),
            'stock_value' => (float) (clone $stock)->sum(DB::raw('unit_price * quantity')),
            'assets'      => (clone $assets)->count(),
            'asset_value' => (float) (clone $assets)->sum('purchase_price'),
        ];

        $stockByCategory = (clone $stock)
            ->select('category', DB::raw('COUNT(*) as line_count'), DB::raw('SUM(quantity) as units'), DB::raw('SUM(unit_price * quantity) as total_value'))
            ->groupBy('category')->orderBy('category')->get();

        $assetsByCategory = (clone $assets)
            ->select('category', DB::raw('COUNT(*) as unit_count'), DB::raw('SUM(purchase_price) as total_value'))
            ->groupBy('category')->orderBy('category')->get();

        $assetsByStatus = (clone $assets)->select('status', DB::raw('COUNT(*) as unit_count'))->groupBy('status')->pluck('unit_count', 'status');
        // Every status a person can see on the Overview: the ones on offer, and any other that still holds assets.
        $statusLabels = array_diff_key(InventoryOption::labels('asset_status'), [InventoryAsset::STATUS_DISPOSED => 1]);
        $statusLabels = array_filter($statusLabels, fn ($label, $key) => isset(InventoryOption::choices('asset_status')[$key]) || ($assetsByStatus[$key] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);

        $attention = collect()
            ->concat((clone $stock)->whereRaw("{$status} IN ('low_stock', 'out_of_stock')")->orderBy('quantity')->limit(8)->get()
                ->map(fn ($i) => ['name' => $i->name, 'code' => $i->code, 'issue' => $i->status(), 'detail' => "{$i->quantity} {$i->unit} left (min {$i->min_stock})"]))
            ->concat((clone $assets)->where(fn ($q) => $q->where('condition', 'damaged')->orWhere('status', 'maintenance'))->limit(8)->get()
                ->map(fn ($a) => ['name' => $a->name, 'code' => $a->code, 'issue' => $a->condition === 'damaged' ? 'damaged' : 'maintenance', 'detail' => $a->serial_number ? "S/N {$a->serial_number}" : '']));

        return view('hr-general.inventory.overview', compact('summary', 'stockByCategory', 'assetsByCategory', 'assetsByStatus', 'statusLabels', 'attention'));
    }

    // ── Inventory ───────────────────────────────────────────────────────────

    public function items(Request $request)
    {
        $filters = [
            'search'   => trim((string) $request->query('search')),
            'category' => (string) $request->query('category'),
            'location' => (string) $request->query('location'),
            'status'   => (string) $request->query('status'),
        ];
        $ranges = ['qty' => 'quantity', 'min_stock' => 'min_stock', 'price' => 'unit_price', 'value' => 'unit_price * quantity'];
        foreach ($ranges as $key => $expression) {
            $filters[$key . '_min'] = $this->digits($request, $key . '_min');
            $filters[$key . '_max'] = $this->digits($request, $key . '_max');
        }
        [$sort, $dir, $sorts] = $this->sortOf($request, [
            'name' => 'name', 'quantity' => 'quantity', 'min_stock' => 'min_stock', 'unit_price' => 'unit_price', 'stock_value' => 'unit_price * quantity',
        ]);
        $filters += ['sort' => $sort, 'dir' => $dir];
        $perPage = $this->perPage($request);

        $rows = InventoryItem::query()
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $like = '%' . $filters['search'] . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like));
            })
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['location'] !== '', fn ($q) => $q->where('location', $filters['location']))
            ->when(isset(InventoryItem::STATUSES[$filters['status']]), fn ($q) => $q->whereRaw(InventoryItem::statusSql() . ' = ?', [$filters['status']]))
            ->tap(function ($q) use ($ranges, $filters) {
                foreach ($ranges as $key => $expression) {
                    $this->applyRange($q, $expression, $filters[$key . '_min'], $filters[$key . '_max']);
                }
            })
            ->when($sort !== '', fn ($q) => $q->orderByRaw($sorts[$sort] . ' ' . $dir))
            ->orderBy('name')->orderBy('id')
            ->paginate($perPage)->withQueryString();

        return view('hr-general.inventory.items', [
            'rows'           => $rows,
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->contains(fn ($v) => $v !== ''),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'categories'     => InventoryOption::labels('item_category'),
            'locations'      => InventoryOption::labels('location'),
            'choices'        => ['category' => InventoryOption::choices('item_category'), 'unit' => InventoryOption::choices('item_unit'), 'location' => InventoryOption::choices('location')],
            'filterForm'     => 'inventoryFilters',
        ]);
    }

    public function storeItem(Request $request)
    {
        $this->create(InventoryItem::class, $this->itemData($request), $request, 'items', 'INV');

        return redirect()->route('general.inventory.items.index')->with('success', 'Inventory item added.');
    }

    public function updateItem(Request $request, InventoryItem $item)
    {
        $this->change($item, $this->itemData($request, $item), $request, 'items');

        return redirect()->route('general.inventory.items.index')->with('success', 'Inventory item updated.');
    }

    public function destroyItem(InventoryItem $item)
    {
        $this->remove($item);

        return redirect()->route('general.inventory.items.index')->with('success', 'Inventory item deleted.');
    }

    public function itemPhoto(InventoryItem $item)
    {
        return $this->photoResponse($item->photo_path);
    }

    private function itemData(Request $request, ?InventoryItem $item = null): array
    {
        $this->keepDigits($request, ['unit_price']);

        return $this->withoutPhotoFields($request->validate([
            'code'            => ['nullable', 'string', 'max:30', Rule::unique('inventory_items', 'code')->ignore($item?->id)],
            'name'            => ['required', 'string', 'max:150'],
            'category'        => ['required', Rule::in(InventoryOption::allowed('item_category', $item?->category))],
            'unit'            => ['required', Rule::in(InventoryOption::allowed('item_unit', $item?->unit))],
            'quantity'        => ['required', 'integer', 'min:0', 'max:1000000'],
            'min_stock'       => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit_price'      => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'location'        => ['nullable', Rule::in(InventoryOption::allowed('location', $item?->location))],
            'status_override' => ['nullable', Rule::in(array_keys(InventoryItem::STATUSES))],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ] + self::PHOTO_RULES));
    }

    // ── Assets ──────────────────────────────────────────────────────────────

    public function assets(Request $request)
    {
        $filters = [
            'search'    => trim((string) $request->query('search')),
            'category'  => (string) $request->query('category'),
            'assignee'  => trim((string) $request->query('assignee')),
            'condition' => (string) $request->query('condition'),
            'status'    => (string) $request->query('status'),
            'location'  => (string) $request->query('location'),
            'price_min' => $this->digits($request, 'price_min'),
            'price_max' => $this->digits($request, 'price_max'),
            'date_from' => $this->date($request, 'date_from'),
            'date_to'   => $this->date($request, 'date_to'),
        ];
        [$sort, $dir, $sorts] = $this->sortOf($request, [
            'name' => 'inventory_assets.name', 'purchase_date' => 'inventory_assets.purchase_date', 'purchase_price' => 'inventory_assets.purchase_price',
        ]);
        $filters += ['sort' => $sort, 'dir' => $dir];
        $perPage = $this->perPage($request);
        $holder = "TRIM(CONCAT_WS(' ', b.first_name, b.last_name))";

        $rows = InventoryAsset::query()
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'inventory_assets.assignee_employee_id')
            ->select('inventory_assets.*', DB::raw("{$holder} as assignee_name"))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $like = '%' . $filters['search'] . '%';
                $q->where(fn ($w) => $w->where('inventory_assets.name', 'like', $like)->orWhere('inventory_assets.code', 'like', $like)
                    ->orWhere('inventory_assets.serial_number', 'like', $like)->orWhere('inventory_assets.brand', 'like', $like));
            })
            ->when($filters['category'] !== '', fn ($q) => $q->where('inventory_assets.category', $filters['category']))
            ->when($filters['assignee'] !== '', fn ($q) => $q->whereRaw("{$holder} like ?", ['%' . $filters['assignee'] . '%']))
            ->when(isset(InventoryOption::labels('asset_condition')[$filters['condition']]), fn ($q) => $q->where('inventory_assets.condition', $filters['condition']))
            ->when(isset(InventoryOption::labels('asset_status')[$filters['status']]), fn ($q) => $q->where('inventory_assets.status', $filters['status']))
            ->when($filters['location'] !== '', fn ($q) => $q->where('inventory_assets.location', $filters['location']))
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('inventory_assets.purchase_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('inventory_assets.purchase_date', '<=', $filters['date_to']))
            ->tap(fn ($q) => $this->applyRange($q, 'inventory_assets.purchase_price', $filters['price_min'], $filters['price_max']))
            ->when($sort !== '', fn ($q) => $q->orderByRaw($sorts[$sort] . ' ' . $dir))
            ->orderBy('inventory_assets.name')->orderBy('inventory_assets.id')
            ->paginate($perPage)->withQueryString();

        return view('hr-general.inventory.assets', [
            'rows'           => $rows,
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->contains(fn ($v) => $v !== ''),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'categories'     => InventoryOption::labels('asset_category'),
            'conditions'     => InventoryOption::labels('asset_condition'),
            'statuses'       => InventoryOption::labels('asset_status'),
            'locations'      => InventoryOption::labels('location'),
            'choices'        => [
                'category' => InventoryOption::choices('asset_category'), 'condition' => InventoryOption::choices('asset_condition'),
                'status' => InventoryOption::choices('asset_status'), 'location' => InventoryOption::choices('location'),
            ],
            'employees'      => $this->employeeChoices(),
            'filterForm'     => 'assetFilters',
        ]);
    }

    public function storeAsset(Request $request)
    {
        $this->create(InventoryAsset::class, $this->assetData($request), $request, 'assets', 'AST');

        return redirect()->route('general.inventory.assets.index')->with('success', 'Asset added.');
    }

    public function updateAsset(Request $request, InventoryAsset $asset)
    {
        $this->change($asset, $this->assetData($request, $asset), $request, 'assets');

        return redirect()->route('general.inventory.assets.index')->with('success', 'Asset updated.');
    }

    public function destroyAsset(InventoryAsset $asset)
    {
        $this->remove($asset);

        return redirect()->route('general.inventory.assets.index')->with('success', 'Asset deleted.');
    }

    public function assetPhoto(InventoryAsset $asset)
    {
        return $this->photoResponse($asset->photo_path);
    }

    /** Who an asset can be handed to: every active employee, plus anyone who already holds one (even if no longer active). */
    private function employeeChoices(): array
    {
        $held = InventoryAsset::query()->whereNotNull('assignee_employee_id')->distinct()->pluck('assignee_employee_id');

        return DB::table('employee as e')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('e.is_active', 1)->where(fn ($d) => $d->where('b.deletion_flag', 0)->orWhereNull('b.deletion_flag')))
                ->orWhereIn('e.employee_id', $held))
            ->orderBy('b.first_name')->orderBy('b.last_name')
            ->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name'])
            ->mapWithKeys(fn ($r) => [$r->employee_id => trim($r->first_name . ' ' . $r->last_name) . ' (' . $r->eci . ')'])
            ->all();
    }

    private function assetData(Request $request, ?InventoryAsset $asset = null): array
    {
        $this->keepDigits($request, ['purchase_price']);

        $data = $this->withoutPhotoFields($request->validate([
            'code'                 => ['nullable', 'string', 'max:30', Rule::unique('inventory_assets', 'code')->ignore($asset?->id)],
            'name'                 => ['required', 'string', 'max:150'],
            'category'             => ['required', Rule::in(InventoryOption::allowed('asset_category', $asset?->category))],
            'brand'                => ['nullable', 'string', 'max:100'],
            'serial_number'        => ['nullable', 'string', 'max:100'],
            'assignee_employee_id' => ['nullable', 'integer', Rule::exists('employee', 'employee_id')],
            'purchase_date'        => ['nullable', 'date'],
            'purchase_price'       => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'condition'            => ['required', Rule::in(InventoryOption::allowed('asset_condition', $asset?->condition))],
            'status'               => ['required', Rule::in(InventoryOption::allowed('asset_status', $asset?->status))],
            'location'             => ['nullable', Rule::in(InventoryOption::allowed('location', $asset?->location))],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ] + self::PHOTO_RULES));

        // The assignee and the status agree: handing an asset over makes it In use, taking it back makes it Available.
        if ($data['assignee_employee_id'] ?? null) {
            $data['status'] = $data['status'] === 'available' ? 'in_use' : $data['status'];
        } elseif ($data['status'] === 'in_use') {
            $data['status'] = 'available';
        }

        return $data;
    }

    // ── Shared by both registers ────────────────────────────────────────────

    /** Saves a new record; its code is made from the id (INV-00007) unless one was typed. */
    private function create(string $model, array $data, Request $request, string $dir, string $prefix): void
    {
        $record = $model::create(array_merge($data, ['code' => 'PENDING-' . uniqid(), 'created_by' => session('user.id'), 'updated_by' => session('user.id')]));
        $record->update([
            'code'       => $data['code'] ?? $prefix . '-' . str_pad((string) $record->id, 5, '0', STR_PAD_LEFT),
            'photo_path' => $this->savedPhotoPath($request, "inventory/{$dir}/{$record->id}", null),
        ]);
    }

    private function change($record, array $data, Request $request, string $dir): void
    {
        $record->update(array_merge($data, [
            'code'       => $data['code'] ?? $record->code,
            'photo_path' => $this->savedPhotoPath($request, "inventory/{$dir}/{$record->id}", $record->photo_path),
            'updated_by' => session('user.id'),
        ]));
    }

    private function remove($record): void
    {
        $this->deletePhoto($record->photo_path);
        $record->delete();
    }

    private function withoutPhotoFields(array $data): array
    {
        unset($data['photo'], $data['remove_photo']);

        return $data;
    }

    /** The path to store after a save: a new upload replaces the old file, "remove" drops it, otherwise unchanged. */
    private function savedPhotoPath(Request $request, string $dir, ?string $current): ?string
    {
        if ($request->hasFile('photo')) {
            $this->deletePhoto($current);

            return $request->file('photo')->store($dir, 'local');
        }

        if ($request->boolean('remove_photo')) {
            $this->deletePhoto($current);

            return null;
        }

        return $current;
    }

    private function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    private function photoResponse(?string $path)
    {
        abort_if(!$path || !Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /** Keeps only the digits of the money fields of the request before it is validated. */
    private function keepDigits(Request $request, array $fields): void
    {
        $request->merge(collect($fields)->filter(fn ($f) => $request->has($f))
            ->mapWithKeys(fn ($f) => [$f => preg_replace('/\D/', '', (string) $request->input($f))])->all());
    }

    /** A numeric filter from the query string: digits only ('' = not set). */
    private function digits(Request $request, string $key): string
    {
        return preg_replace('/\D/', '', (string) $request->query($key));
    }

    /** A date filter from the query string: Y-m-d or '' (anything else is ignored). */
    private function date(Request $request, string $key): string
    {
        $value = (string) $request->query($key);

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : '';
    }

    /** `$expression` between $min and $max (either may be ''). The expression is always code, never user input. */
    private function applyRange($query, string $expression, string $min, string $max): void
    {
        if ($min !== '') {
            $query->whereRaw("{$expression} >= ?", [(int) $min]);
        }
        if ($max !== '') {
            $query->whereRaw("{$expression} <= ?", [(int) $max]);
        }
    }

    /** [sort, direction, columns]: the column asked for in `sort` / `dir` if it is one of $columns, else ['', '']. */
    private function sortOf(Request $request, array $columns): array
    {
        $sort = (string) $request->query('sort');
        $known = isset($columns[$sort]);

        return [$known ? $sort : '', $known ? ($request->query('dir') === 'desc' ? 'desc' : 'asc') : '', $columns];
    }
}
