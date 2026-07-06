<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectCost;
use App\Models\DeliveryProjectCostItem;
use App\Services\OneDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryProjectCostController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /projects/{project}/costs
    // Returns the full cost tree for a project (JSON)
    // ──────────────────────────────────────────────────────────────
    public function index(DeliveryProject $project)
    {
        $costs = DeliveryProjectCost::where('delivery_projects_id', $project->id)
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
    // POST /projects/{project}/costs
    // Create a new cost item (parent OR child)
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'parent_id'      => 'nullable|exists:delivery_project_costs,id',
            'code'           => 'nullable|string|max:20',
            'name'           => 'required|string|max:200',
            'cost_type'      => 'required|in:indirect,direct',
            'budget'         => 'nullable|numeric|min:0',
            'release_amount' => 'nullable|numeric|min:0',
            // actual_amount is NOT accepted here — it is derived from the
            // sum of expense detail items (see syncActualFromItems()).
        ]);

        // Auto order_sequence: place at the end of siblings
        $maxOrder = DeliveryProjectCost::where('delivery_projects_id', $project->id)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order_sequence') ?? 0;

        $cost = DeliveryProjectCost::create(array_merge($validated, [
            'delivery_projects_id' => $project->id,
            'order_sequence'       => $maxOrder + 1,
            // New leaf starts with no expenses → actual is 0 (shows "Rp 0").
            'actual_amount'        => 0,
        ]));

        $cost->load('children');

        return response()->json([
            'message' => 'Cost item created successfully.',
            'cost'    => $this->formatCost($cost),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /projects/{project}/costs/{cost}
    // Update a cost item
    // ──────────────────────────────────────────────────────────────
    public function update(Request $request, DeliveryProject $project, DeliveryProjectCost $cost)
    {
        // Ensure the cost belongs to this project
        if ($cost->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'code'           => 'nullable|string|max:20',
            'name'           => 'required|string|max:200',
            'cost_type'      => 'required|in:indirect,direct',
            'budget'         => 'nullable|numeric|min:0',
            'release_amount' => 'nullable|numeric|min:0',
            // actual_amount is NOT editable here — it is derived from the
            // sum of expense detail items (see syncActualFromItems()).
        ]);

        $cost->update($validated);
        $cost->load('children');

        return response()->json([
            'message' => 'Cost item updated successfully.',
            'cost'    => $this->formatCost($cost),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /projects/{project}/costs/{cost}
    // Delete a cost item (cascades to children via DB)
    // ──────────────────────────────────────────────────────────────
    public function destroy(DeliveryProject $project, DeliveryProjectCost $cost)
    {
        if ($cost->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Prevent deleting a top-level indirect/direct parent if you want to keep the structure
        // (we allow it; user can always re-create)
        $cost->delete();

        return response()->json(['message' => 'Cost item deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /projects/{project}/costs/init
    // Auto-initialise the default cost structure for a project
    // (called when section is opened and no costs exist yet)
    // ──────────────────────────────────────────────────────────────
    public function init(DeliveryProject $project)
    {
        $exists = DeliveryProjectCost::where('delivery_projects_id', $project->id)->exists();
        if ($exists) {
            return response()->json(['message' => 'Already initialised.'], 200);
        }

        DB::transaction(function () use ($project) {
            // 100 – INDIRECT COST (top-level parent only — children added manually)
            DeliveryProjectCost::create([
                'delivery_projects_id' => $project->id,
                'parent_id'            => null,
                'code'                 => '100',
                'name'                 => 'INDIRECT COST',
                'cost_type'            => 'indirect',
                'budget'               => null,
                'release_amount'       => null,
                'actual_amount'        => null,
                'order_sequence'       => 1,
            ]);

            // 200 – DIRECT COST (top-level parent only — children added manually)
            DeliveryProjectCost::create([
                'delivery_projects_id' => $project->id,
                'parent_id'            => null,
                'code'                 => '200',
                'name'                 => 'DIRECT COST',
                'cost_type'            => 'direct',
                'budget'               => null,
                'release_amount'       => null,
                'actual_amount'        => null,
                'order_sequence'       => 2,
            ]);
        });

        return response()->json(['message' => 'Cost structure initialised.'], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /projects/{project}/costs/{cost}/items
    // List all expense line-items for a cost item
    // ──────────────────────────────────────────────────────────────
    public function indexItems(DeliveryProject $project, DeliveryProjectCost $cost)
    {
        if ($cost->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $items = $cost->items()->get();

        return response()->json([
            'items' => $items->map(fn($i) => $this->formatItem($i)),
            'total' => (float) $items->sum('amount'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /projects/{project}/costs/{cost}/items
    // Add an expense line-item with optional document proof (OneDrive)
    // ──────────────────────────────────────────────────────────────
    public function storeItem(Request $request, DeliveryProject $project, DeliveryProjectCost $cost)
    {
        if ($cost->delivery_projects_id !== $project->id) {
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
            if (!$project->onedrive_folder_id) {
                return response()->json([
                    'message' => 'OneDrive folder has not been set up for this project. Please create the OneDrive folder first.',
                ], 422);
            }

            $file    = $request->file('document');
            $docName = $file->getClientOriginalName();

            try {
                $oneDrive    = new OneDriveService();
                // Get or create "Plan Cost" subfolder inside the project's OneDrive folder
                $planCostFolderId = $oneDrive->findOrCreateSubFolderById(
                    $project->onedrive_folder_id,
                    'Plan Cost'
                );
                $result  = $oneDrive->uploadFile(
                    $planCostFolderId,
                    $docName,
                    file_get_contents($file->getRealPath()),
                    $file->getMimeType() ?: 'application/octet-stream'
                );
                $docUrl = $result['webUrl'];
            } catch (\Throwable $e) {
                Log::error('Plan Cost OneDrive upload failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Failed to upload document to OneDrive: ' . $e->getMessage(),
                ], 500);
            }
        }

        $item = DeliveryProjectCostItem::create([
            'delivery_project_cost_id' => $cost->id,
            'description'              => $validated['description'],
            'amount'                   => $validated['amount'],
            'document_name'            => $docName,
            'document_url'             => $docUrl,
        ]);

        // Actual amount = sum of all expense items (single source of truth).
        $total = $this->syncActualFromItems($cost);

        return response()->json([
            'message' => 'Expense item added.',
            'item'    => $this->formatItem($item),
            'total'   => $total,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /projects/{project}/costs/{cost}/items/{item}
    // Update an expense line-item (name, amount, optional document).
    // NOTE: the frontend sends this as POST + X-HTTP-Method-Override:PUT
    // because some production edges block the PUT verb — Laravel still
    // routes it here. Multipart is parsed normally (real verb is POST).
    // ──────────────────────────────────────────────────────────────
    public function updateItem(
        Request $request,
        DeliveryProject $project,
        DeliveryProjectCost $cost,
        DeliveryProjectCostItem $item
    ) {
        if ($cost->delivery_projects_id !== $project->id
            || $item->delivery_project_cost_id !== $cost->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'description'     => 'required|string|max:200',
            'amount'          => 'required|numeric|min:0',
            'document'        => 'nullable|file|max:102400',
            // When true (and no new file), the existing document is cleared.
            'remove_document' => 'nullable|boolean',
        ]);

        // Keep current document unless the user replaces or removes it.
        $docName = $item->document_name;
        $docUrl  = $item->document_url;

        if ($request->hasFile('document')) {
            if (!$project->onedrive_folder_id) {
                return response()->json([
                    'message' => 'OneDrive folder has not been set up for this project. Please create the OneDrive folder first.',
                ], 422);
            }

            $file    = $request->file('document');
            $docName = $file->getClientOriginalName();

            try {
                $oneDrive = new OneDriveService();
                $planCostFolderId = $oneDrive->findOrCreateSubFolderById(
                    $project->onedrive_folder_id,
                    'Plan Cost'
                );
                $result  = $oneDrive->uploadFile(
                    $planCostFolderId,
                    $docName,
                    file_get_contents($file->getRealPath()),
                    $file->getMimeType() ?: 'application/octet-stream'
                );
                $docUrl = $result['webUrl'];
            } catch (\Throwable $e) {
                Log::error('Plan Cost OneDrive upload failed', ['error' => $e->getMessage()]);
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

        // Actual amount = sum of all expense items (single source of truth).
        $total = $this->syncActualFromItems($cost);

        return response()->json([
            'message' => 'Expense item updated.',
            'item'    => $this->formatItem($item),
            'total'   => $total,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /projects/{project}/costs/{cost}/items/{item}
    // Delete an expense line-item and its file (if any)
    // ──────────────────────────────────────────────────────────────
    public function destroyItem(
        DeliveryProject $project,
        DeliveryProjectCost $cost,
        DeliveryProjectCostItem $item
    ) {
        if ($cost->delivery_projects_id !== $project->id
            || $item->delivery_project_cost_id !== $cost->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // File lives on OneDrive — we only remove the DB record.
        // (OneDrive cleanup can be done manually from the folder if needed.)
        $item->delete();

        // Recompute actual amount from remaining expense items.
        $total = $this->syncActualFromItems($cost);

        return response()->json([
            'message' => 'Expense item deleted.',
            'total'   => $total,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: recompute & persist a cost item's actual_amount as the
    // sum of its expense detail items. Returns the new total.
    // (Actual is fully derived from expenses — never entered manually.)
    // ──────────────────────────────────────────────────────────────
    private function syncActualFromItems(DeliveryProjectCost $cost): float
    {
        $total = (float) $cost->items()->sum('amount');
        $cost->update(['actual_amount' => $total]);

        return $total;
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: serialise a cost item with all computed fields
    // ──────────────────────────────────────────────────────────────
    private function formatCost(DeliveryProjectCost $cost): array
    {
        $hasChildren = $cost->children->isNotEmpty();

        // For parent rows: aggregate from children
        if ($hasChildren) {
            $budget         = $cost->children->sum('budget');
            $releaseAmount  = $cost->children->sum('release_amount');
            $actualAmount   = $cost->children->sum('actual_amount');
        } else {
            $budget        = $cost->budget;
            $releaseAmount = $cost->release_amount;
            // Actual is derived from expense items → always numeric (0 = no expenses).
            $actualAmount  = (float) ($cost->actual_amount ?? 0);
        }

        // Available Budget  = Budget − Release
        $availBudget  = ($budget !== null || $releaseAmount !== null)
            ? (float)($budget ?? 0) - (float)($releaseAmount ?? 0)
            : null;

        // Available Release = Release − Actual
        // Shown when a release is set OR there has been actual spending; an
        // otherwise-empty row stays "—" (not "Rp 0") to match Avail. Budget.
        $availRelease = ($releaseAmount !== null || (float)($actualAmount ?? 0) > 0)
            ? (float)($releaseAmount ?? 0) - (float)($actualAmount ?? 0)
            : null;

        return [
            'id'               => $cost->id,
            'parent_id'        => $cost->parent_id,
            'code'             => $cost->code,
            'name'             => $cost->name,
            'cost_type'        => $cost->cost_type,
            'order_sequence'   => $cost->order_sequence,
            'has_children'     => $hasChildren,
            // Displayed (aggregated for parents, own value for leaf items)
            'display_budget'   => $budget,
            'display_release'  => $releaseAmount,
            'display_actual'   => $actualAmount,
            // budget/release/actual: always null for items-with-children
            // (they are read-only aggregates — not directly editable)
            'budget'           => $hasChildren ? null : $cost->budget,
            'release_amount'   => $hasChildren ? null : $cost->release_amount,
            'actual_amount'    => $hasChildren ? null : $cost->actual_amount,
            // Computed
            'avail_budget'     => $availBudget,
            'avail_release'    => $availRelease,
            // Children (formatted recursively)
            'children'         => $cost->children->map(fn($c) => $this->formatCost($c))->values(),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: serialise an expense line-item
    // ──────────────────────────────────────────────────────────────
    private function formatItem(DeliveryProjectCostItem $item): array
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
            'total_budget'          => $totBudget,
            'total_release'         => $totRelease,
            'total_actual'          => $totActual,
            'total_avail_budget'    => $totBudget  - $totRelease,
            'total_avail_release'   => $totRelease - $totActual,
        ];
    }
}
