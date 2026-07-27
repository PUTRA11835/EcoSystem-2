<?php

namespace App\Http\Controllers;

use App\Models\DeliverySupport;
use App\Models\DeliverySupportCost;
use App\Models\DeliverySupportCostItem;
use App\Services\OneDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Plan Cost untuk Delivery Support — mirror dari DeliveryProjectCostController.
 */
class DeliverySupportCostController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /delivery/support/{support}/costs
    // ──────────────────────────────────────────────────────────────
    public function index(DeliverySupport $support)
    {
        $costs = DeliverySupportCost::where('delivery_support_id', $support->id)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('order_sequence')
            ->get();

        return response()->json([
            'costs'   => $costs->map(fn($c) => $this->formatCost($c)),
            'summary' => $this->buildSummary($costs),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /delivery/support/{support}/costs
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'parent_id'      => 'nullable|exists:delivery_support_costs,id',
            'code'           => 'nullable|string|max:20',
            'name'           => 'required|string|max:200',
            'cost_type'      => 'required|in:indirect,direct',
            'budget'         => 'nullable|numeric|min:0',
            'release_amount' => 'nullable|numeric|min:0',
            // actual_amount is NOT accepted here — it is derived from the
            // sum of expense detail items (see syncActualFromItems()).
        ]);

        $maxOrder = DeliverySupportCost::where('delivery_support_id', $support->id)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order_sequence') ?? 0;

        $cost = DeliverySupportCost::create(array_merge($validated, [
            'delivery_support_id' => $support->id,
            'order_sequence'      => $maxOrder + 1,
            'actual_amount'       => 0,
        ]));

        $cost->load('children');

        return response()->json([
            'message' => 'Cost item created successfully.',
            'cost'    => $this->formatCost($cost),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /delivery/support/{support}/costs/{cost}
    // ──────────────────────────────────────────────────────────────
    public function update(Request $request, DeliverySupport $support, DeliverySupportCost $cost)
    {
        if ($cost->delivery_support_id !== $support->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'code'           => 'nullable|string|max:20',
            'name'           => 'required|string|max:200',
            'cost_type'      => 'required|in:indirect,direct',
            'budget'         => 'nullable|numeric|min:0',
            'release_amount' => 'nullable|numeric|min:0',
        ]);

        $cost->update($validated);
        $cost->load('children');

        return response()->json([
            'message' => 'Cost item updated successfully.',
            'cost'    => $this->formatCost($cost),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /delivery/support/{support}/costs/{cost}
    // ──────────────────────────────────────────────────────────────
    public function destroy(DeliverySupport $support, DeliverySupportCost $cost)
    {
        if ($cost->delivery_support_id !== $support->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $cost->delete();

        return response()->json(['message' => 'Cost item deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /delivery/support/{support}/costs/init
    // ──────────────────────────────────────────────────────────────
    public function init(DeliverySupport $support)
    {
        $exists = DeliverySupportCost::where('delivery_support_id', $support->id)->exists();
        if ($exists) {
            return response()->json(['message' => 'Already initialised.'], 200);
        }

        DB::transaction(function () use ($support) {
            DeliverySupportCost::create([
                'delivery_support_id' => $support->id,
                'parent_id'           => null,
                'code'                => '100',
                'name'                => 'INDIRECT COST',
                'cost_type'           => 'indirect',
                'budget'              => null,
                'release_amount'      => null,
                'actual_amount'       => null,
                'order_sequence'      => 1,
            ]);

            DeliverySupportCost::create([
                'delivery_support_id' => $support->id,
                'parent_id'           => null,
                'code'                => '200',
                'name'                => 'DIRECT COST',
                'cost_type'           => 'direct',
                'budget'              => null,
                'release_amount'      => null,
                'actual_amount'       => null,
                'order_sequence'      => 2,
            ]);
        });

        return response()->json(['message' => 'Cost structure initialised.'], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /delivery/support/{support}/costs/{cost}/items
    // ──────────────────────────────────────────────────────────────
    public function indexItems(DeliverySupport $support, DeliverySupportCost $cost)
    {
        if ($cost->delivery_support_id !== $support->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $items = $cost->items()->get();

        return response()->json([
            'items' => $items->map(fn($i) => $this->formatItem($i)),
            'total' => (float) $items->sum('amount'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /delivery/support/{support}/costs/{cost}/items
    // ──────────────────────────────────────────────────────────────
    public function storeItem(Request $request, DeliverySupport $support, DeliverySupportCost $cost)
    {
        if ($cost->delivery_support_id !== $support->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'description' => 'required|string|max:200',
            'amount'      => 'required|numeric|min:0',
            'document'    => 'nullable|file|max:102400',
        ]);

        $docName = null;
        $docUrl  = null;

        if ($request->hasFile('document')) {
            if (!$support->onedrive_folder_id) {
                return response()->json([
                    'message' => 'OneDrive folder has not been set up for this support. Please create the OneDrive folder first.',
                ], 422);
            }

            $file    = $request->file('document');
            $docName = $file->getClientOriginalName();

            try {
                $oneDrive         = new OneDriveService();
                $planCostFolderId = $oneDrive->findOrCreateSubFolderById(
                    $support->onedrive_folder_id,
                    'Plan Cost'
                );
                $result = $oneDrive->uploadFile(
                    $planCostFolderId,
                    $docName,
                    file_get_contents($file->getRealPath()),
                    $file->getMimeType() ?: 'application/octet-stream'
                );
                $docUrl = $result['webUrl'];
            } catch (\Throwable $e) {
                Log::error('Plan Cost OneDrive upload failed (support)', ['error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Failed to upload document to OneDrive: ' . $e->getMessage(),
                ], 500);
            }
        }

        $item = DeliverySupportCostItem::create([
            'delivery_support_cost_id' => $cost->id,
            'description'              => $validated['description'],
            'amount'                   => $validated['amount'],
            'document_name'            => $docName,
            'document_url'             => $docUrl,
        ]);

        $total = $this->syncActualFromItems($cost);

        return response()->json([
            'message' => 'Expense item added.',
            'item'    => $this->formatItem($item),
            'total'   => $total,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /delivery/support/{support}/costs/{cost}/items/{item}
    // (frontend kirim POST + X-HTTP-Method-Override:PUT)
    // ──────────────────────────────────────────────────────────────
    public function updateItem(
        Request $request,
        DeliverySupport $support,
        DeliverySupportCost $cost,
        DeliverySupportCostItem $item
    ) {
        if ($cost->delivery_support_id !== $support->id
            || $item->delivery_support_cost_id !== $cost->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'description'     => 'required|string|max:200',
            'amount'          => 'required|numeric|min:0',
            'document'        => 'nullable|file|max:102400',
            'remove_document' => 'nullable|boolean',
        ]);

        $docName = $item->document_name;
        $docUrl  = $item->document_url;

        if ($request->hasFile('document')) {
            if (!$support->onedrive_folder_id) {
                return response()->json([
                    'message' => 'OneDrive folder has not been set up for this support. Please create the OneDrive folder first.',
                ], 422);
            }

            $file    = $request->file('document');
            $docName = $file->getClientOriginalName();

            try {
                $oneDrive         = new OneDriveService();
                $planCostFolderId = $oneDrive->findOrCreateSubFolderById(
                    $support->onedrive_folder_id,
                    'Plan Cost'
                );
                $result = $oneDrive->uploadFile(
                    $planCostFolderId,
                    $docName,
                    file_get_contents($file->getRealPath()),
                    $file->getMimeType() ?: 'application/octet-stream'
                );
                $docUrl = $result['webUrl'];
            } catch (\Throwable $e) {
                Log::error('Plan Cost OneDrive upload failed (support)', ['error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Failed to upload document to OneDrive: ' . $e->getMessage(),
                ], 500);
            }
        } elseif ($request->boolean('remove_document')) {
            $docName = null;
            $docUrl  = null;
        }

        $item->update([
            'description'   => $validated['description'],
            'amount'        => $validated['amount'],
            'document_name' => $docName,
            'document_url'  => $docUrl,
        ]);

        $total = $this->syncActualFromItems($cost);

        return response()->json([
            'message' => 'Expense item updated.',
            'item'    => $this->formatItem($item),
            'total'   => $total,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /delivery/support/{support}/costs/{cost}/items/{item}
    // ──────────────────────────────────────────────────────────────
    public function destroyItem(
        DeliverySupport $support,
        DeliverySupportCost $cost,
        DeliverySupportCostItem $item
    ) {
        if ($cost->delivery_support_id !== $support->id
            || $item->delivery_support_cost_id !== $cost->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $item->delete();

        $total = $this->syncActualFromItems($cost);

        return response()->json([
            'message' => 'Expense item deleted.',
            'total'   => $total,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: recompute & persist actual_amount = sum of expense items.
    // ──────────────────────────────────────────────────────────────
    private function syncActualFromItems(DeliverySupportCost $cost): float
    {
        $total = (float) $cost->items()->sum('amount');
        $cost->update(['actual_amount' => $total]);

        return $total;
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: serialise a cost item with all computed fields
    // ──────────────────────────────────────────────────────────────
    private function formatCost(DeliverySupportCost $cost): array
    {
        $hasChildren = $cost->children->isNotEmpty();

        if ($hasChildren) {
            $budget        = $cost->children->sum('budget');
            $releaseAmount = $cost->children->sum('release_amount');
            $actualAmount  = $cost->children->sum('actual_amount');
        } else {
            $budget        = $cost->budget;
            $releaseAmount = $cost->release_amount;
            $actualAmount  = (float) ($cost->actual_amount ?? 0);
        }

        $availBudget = ($budget !== null || $releaseAmount !== null)
            ? (float)($budget ?? 0) - (float)($releaseAmount ?? 0)
            : null;

        $availRelease = ($releaseAmount !== null || (float)($actualAmount ?? 0) > 0)
            ? (float)($releaseAmount ?? 0) - (float)($actualAmount ?? 0)
            : null;

        return [
            'id'              => $cost->id,
            'parent_id'       => $cost->parent_id,
            'code'            => $cost->code,
            'name'            => $cost->name,
            'cost_type'       => $cost->cost_type,
            'order_sequence'  => $cost->order_sequence,
            'has_children'    => $hasChildren,
            'display_budget'  => $budget,
            'display_release' => $releaseAmount,
            'display_actual'  => $actualAmount,
            'budget'          => $hasChildren ? null : $cost->budget,
            'release_amount'  => $hasChildren ? null : $cost->release_amount,
            'actual_amount'   => $hasChildren ? null : $cost->actual_amount,
            'avail_budget'    => $availBudget,
            'avail_release'   => $availRelease,
            'children'        => $cost->children->map(fn($c) => $this->formatCost($c))->values(),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: serialise an expense line-item
    // ──────────────────────────────────────────────────────────────
    private function formatItem(DeliverySupportCostItem $item): array
    {
        return [
            'id'            => $item->id,
            'description'   => $item->description,
            'amount'        => $item->amount,
            'document_name' => $item->document_name,
            'document_url'  => $item->document_url,
            'created_at'    => $item->created_at?->format('d M Y'),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: grand total summary row
    // ──────────────────────────────────────────────────────────────
    private function buildSummary($costs): array
    {
        $totBudget  = 0;
        $totRelease = 0;
        $totActual  = 0;

        foreach ($costs as $c) {
            if ($c->children->isNotEmpty()) {
                $totBudget  += $c->children->sum('budget');
                $totRelease += $c->children->sum('release_amount');
                $totActual  += $c->children->sum('actual_amount');
            } else {
                $totBudget  += (float)($c->budget ?? 0);
                $totRelease += (float)($c->release_amount ?? 0);
                $totActual  += (float)($c->actual_amount ?? 0);
            }
        }

        return [
            'total_budget'        => $totBudget,
            'total_release'       => $totRelease,
            'total_actual'        => $totActual,
            'total_avail_budget'  => $totBudget  - $totRelease,
            'total_avail_release' => $totRelease - $totActual,
        ];
    }
}
