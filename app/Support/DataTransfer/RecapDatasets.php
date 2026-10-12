<?php

namespace App\Support\DataTransfer;

use App\Models\InventoryAsset;
use App\Models\InventoryItem;
use App\Models\InventoryOption;
use App\Models\Recruitment\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recap (dashboard) data as flat, exportable tables. Same figures as the
 * Overview pages, one row per figure: Section / Item / Count / Units / Value.
 */
class RecapDatasets
{
    private static function row(string $section, string $item, $count = null, $units = null, $value = null, string $detail = ''): object
    {
        return (object) compact('section', 'item', 'count', 'units', 'value', 'detail');
    }

    private static function columns(): array
    {
        return [
            ['key' => 'section', 'label' => 'Section', 'get' => fn ($r) => $r->section],
            ['key' => 'item', 'label' => 'Item', 'get' => fn ($r) => $r->item],
            ['key' => 'count', 'label' => 'Count', 'format' => 'int', 'get' => fn ($r) => $r->count],
            ['key' => 'units', 'label' => 'Units', 'format' => 'int', 'get' => fn ($r) => $r->units],
            ['key' => 'value', 'label' => 'Value', 'format' => 'money', 'get' => fn ($r) => $r->value],
            ['key' => 'detail', 'label' => 'Detail', 'get' => fn ($r) => $r->detail],
        ];
    }

    public static function recruitment(): Dataset
    {
        return new Dataset(
            'recruitment.recap', 'Recruitment Recap', 'general.recruitment',
            self::columns(),
            function (Request $request) {
                $rows = collect();

                $total  = DB::table('recruitment_candidates')->count();
                $active = DB::table('recruitment_candidates')->whereIn('status', Candidate::ACTIVE_STATUSES)->count();
                $offers = DB::table('recruitment_offers');
                $offered  = (clone $offers)->count();
                $accepted = (clone $offers)->where('decision', 'accepted')->count();
                $open = DB::table('recruitment_job_openings')
                    ->where('opens_at', '<=', now())
                    ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', now()))
                    ->count();

                $rows->push(self::row('Summary', 'Total candidates', $total));
                $rows->push(self::row('Summary', 'Active candidates', $active));
                $rows->push(self::row('Summary', 'Open job openings', $open));
                $rows->push(self::row('Summary', 'Offering letters', $offered));
                $rows->push(self::row('Summary', 'Offers accepted', $accepted, null, null,
                    $offered > 0 ? round($accepted / $offered * 100) . '% of offered' : ''));

                $byStatus = DB::table('recruitment_candidates')->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');
                foreach (Candidate::STATUSES as $key => $label) {
                    $rows->push(self::row('Candidates by status', $label, (int) ($byStatus[$key] ?? 0)));
                }

                DB::table('recruitment_candidates as c')
                    ->leftJoin('recruitment_options as o', 'o.id', '=', 'c.source_id')
                    ->select(DB::raw("COALESCE(o.name, IF(c.source_detail IS NULL, 'Unknown', 'Other')) as label"), DB::raw('COUNT(*) as n'))
                    ->groupBy('label')->orderByDesc('n')->get()
                    ->each(fn ($r) => $rows->push(self::row('Candidates by source', $r->label, (int) $r->n)));

                DB::table('recruitment_candidates as c')
                    ->leftJoin('positions as p', 'p.id', '=', 'c.position_id')
                    ->select(DB::raw("COALESCE(p.name, 'Unspecified') as label"), DB::raw('COUNT(*) as n'))
                    ->groupBy('label')->orderByDesc('n')->get()
                    ->each(fn ($r) => $rows->push(self::row('Candidates by position', $r->label, (int) $r->n)));

                return $rows;
            }
        );
    }

    public static function inventory(): Dataset
    {
        return new Dataset(
            'inventory.recap', 'Inventory & Assets Recap', 'general.inventory.overview',
            self::columns(),
            function (Request $request) {
                $status = InventoryItem::statusSql();
                $stock  = InventoryItem::query()->whereRaw("{$status} != ?", [InventoryItem::STATUS_INACTIVE]);
                $assets = InventoryAsset::query()->where('status', '!=', InventoryAsset::STATUS_DISPOSED);
                $rows   = collect();

                $rows->push(self::row('Summary', 'Inventory lines', (clone $stock)->count()));
                $rows->push(self::row('Summary', 'Stock value', null, null, (float) (clone $stock)->sum(DB::raw('unit_price * quantity'))));
                $rows->push(self::row('Summary', 'Assets', (clone $assets)->count()));
                $rows->push(self::row('Summary', 'Asset value (purchase)', null, null, (float) (clone $assets)->sum('purchase_price')));

                (clone $stock)->select('category', DB::raw('COUNT(*) as n'), DB::raw('SUM(quantity) as units'), DB::raw('SUM(unit_price * quantity) as v'))
                    ->groupBy('category')->orderBy('category')->get()
                    ->each(fn ($r) => $rows->push(self::row('Inventory by category',
                        InventoryOption::labels('item_category')[$r->category] ?? $r->category, (int) $r->n, (int) $r->units, (float) $r->v)));

                (clone $assets)->select('category', DB::raw('COUNT(*) as n'), DB::raw('SUM(purchase_price) as v'))
                    ->groupBy('category')->orderBy('category')->get()
                    ->each(fn ($r) => $rows->push(self::row('Assets by category',
                        InventoryOption::labels('asset_category')[$r->category] ?? $r->category, (int) $r->n, null, (float) $r->v)));

                (clone $assets)->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->get()
                    ->each(fn ($r) => $rows->push(self::row('Assets by status',
                        InventoryOption::labels('asset_status')[$r->status] ?? $r->status, (int) $r->n)));

                (clone $stock)->whereRaw("{$status} IN ('low_stock', 'out_of_stock')")->orderBy('quantity')->limit(50)->get()
                    ->each(fn ($i) => $rows->push(self::row('Needs attention', $i->name . ' (' . $i->code . ')', null, (int) $i->quantity, null,
                        InventoryItem::STATUSES[$i->status()] . " — min {$i->min_stock} {$i->unit}")));

                (clone $assets)->where(fn ($q) => $q->where('condition', 'damaged')->orWhere('status', 'maintenance'))->limit(50)->get()
                    ->each(fn ($a) => $rows->push(self::row('Needs attention', $a->name . ' (' . $a->code . ')', null, null, null,
                        $a->condition === 'damaged' ? 'Damaged' : 'Under maintenance')));

                return $rows;
            }
        );
    }
}
