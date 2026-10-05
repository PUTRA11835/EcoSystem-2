<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectContractLineItem;
use App\Models\DeliveryProjectPaymentTerm;
use App\Services\ProjectReminderService;
use App\Services\ProjectTopPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contract Line Item — acuan termin nominal tetap pada mode TOP "line_item".
 * Daftar line item ikut dikirim oleh GET payment-terms (ProjectTopPlan::payload).
 */
class DeliveryProjectContractLineItemController extends Controller
{
    // POST /projects/{project}/contract-line-items
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $this->validatePayload($request);

        $item = DeliveryProjectContractLineItem::create($validated + [
            'delivery_projects_id' => $project->id,
            'order_sequence'       => (DeliveryProjectContractLineItem::where('delivery_projects_id', $project->id)
                ->max('order_sequence') ?? 0) + 1,
        ]);

        return response()->json([
            'message'   => 'Line item added successfully.',
            'line_item' => ProjectTopPlan::formatLineItem($item),
            'warnings'  => (new ProjectTopPlan($project))->warnings(),
        ], 201);
    }

    // POST /projects/{project}/contract-line-items/{lineItem}
    public function update(Request $request, DeliveryProject $project, DeliveryProjectContractLineItem $lineItem)
    {
        if ($lineItem->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $lineItem->update($this->validatePayload($request));

        // Nilai kontrak bisa berubah → termin "% of Line Item" ikut menyesuaikan.
        $plan = new ProjectTopPlan($project);
        $plan->resyncLineItemAmounts();

        return response()->json([
            'message'   => 'Line item updated successfully.',
            'line_item' => ProjectTopPlan::formatLineItem($lineItem->fresh()),
            'warnings'  => $plan->warnings(),
        ]);
    }

    // POST /projects/{project}/contract-line-items/{lineItem}/delete
    public function destroy(DeliveryProject $project, DeliveryProjectContractLineItem $lineItem)
    {
        if ($lineItem->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Termin yang sudah dibuat dari line item ini tidak boleh kehilangan acuannya.
        $count = $lineItem->paymentTerms()->count();
        if ($count > 0) {
            return response()->json([
                'message' => "This line item still has {$count} payment term(s). Delete those payment terms first.",
            ], 422);
        }

        $lineItem->delete();

        return response()->json(['message' => 'Line item deleted successfully.']);
    }

    /**
     * POST /projects/{project}/contract-line-items/{lineItem}/generate-schedule
     *
     * Membuat semua termin berulang sekaligus (basis nominal tetap). Jadwal yang
     * dipakai disimpan balik ke line item supaya nilai kontrak tetap konsisten.
     * Periode yang sudah punya termin dilewati — aman ditekan ulang.
     */
    public function generateSchedule(Request $request, DeliveryProject $project, DeliveryProjectContractLineItem $lineItem)
    {
        if ($lineItem->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ((new ProjectTopPlan($project))->mode() !== 'line_item') {
            return response()->json(['message' => 'Switch the billing mode to Line Item first.'], 422);
        }

        $validated = $request->validate([
            'amount'       => 'required|numeric|min:0',
            'frequency'    => ['required', Rule::in(array_keys(DeliveryProjectContractLineItem::FREQUENCY_MONTHS))],
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'requirements' => 'nullable|string',
        ]);

        $periods = DeliveryProjectContractLineItem::buildPeriods(
            $validated['frequency'], $validated['start_date'], $validated['end_date']
        );
        $this->assertPeriodCount($periods);

        $existing = $lineItem->paymentTerms()->pluck('period')->filter()->map(fn($p) => mb_strtolower($p))->all();

        $created = DB::transaction(function () use ($project, $lineItem, $validated, $periods, $existing) {
            $lineItem->update([
                'type'       => 'recurring',
                'frequency'  => $validated['frequency'],
                'start_date' => $validated['start_date'],
                'end_date'   => $validated['end_date'],
                'amount'     => $validated['amount'],
            ]);

            $next    = (DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)->max('term_number') ?? 0) + 1;
            $created = 0;

            foreach ($periods as $period) {
                if (in_array(mb_strtolower($period['label']), $existing, true)) {
                    continue;
                }

                DeliveryProjectPaymentTerm::create([
                    'delivery_projects_id'  => $project->id,
                    'term_number'           => $next++,
                    'basis'                 => 'fixed',
                    'contract_line_item_id' => $lineItem->id,
                    'payment_term'          => $lineItem->name . ' – ' . $period['label'],
                    'period'                => $period['label'],
                    'payment_percentage'    => 0,
                    'amount'                => round((float) $validated['amount'], 2),
                    'requirements'          => $validated['requirements'] ?? null,
                    'estimated_date'        => $period['start']->toDateString(),
                    'status'                => 'Open',
                ]);
                $created++;
            }

            return $created;
        });

        $plan = new ProjectTopPlan($project);
        $plan->resequence();
        $plan->resyncLineItemAmounts();
        app(ProjectReminderService::class)->syncAllQuietly();

        $skipped = count($periods) - $created;

        return response()->json([
            'message'  => "{$created} payment term(s) generated" . ($skipped ? ", {$skipped} existing period(s) skipped." : '.'),
            'created'  => $created,
            'skipped'  => $skipped,
            'warnings' => $plan->warnings(),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'type'       => ['required', Rule::in(DeliveryProjectContractLineItem::TYPES)],
            'frequency'  => ['nullable', 'required_if:type,recurring', Rule::in(array_keys(DeliveryProjectContractLineItem::FREQUENCY_MONTHS))],
            'start_date' => 'nullable|required_if:type,recurring|date',
            'end_date'   => 'nullable|required_if:type,recurring|date|after_or_equal:start_date',
            'amount'     => 'required|numeric|min:0',
        ], [
            'frequency.required_if'  => 'Frequency is required for a recurring line item.',
            'start_date.required_if' => 'Start date is required for a recurring line item.',
            'end_date.required_if'   => 'End date is required for a recurring line item.',
        ]);

        if ($validated['type'] === 'milestone') {
            // Nilai kontrak tunggal; jadwal ditentukan oleh masing-masing termin.
            $validated['frequency']  = null;
            $validated['start_date'] = null;
            $validated['end_date']   = null;
        } elseif ($validated['type'] === 'one_time') {
            $validated['frequency'] = null;
            $validated['end_date']  = null;
        } else {
            $this->assertPeriodCount(DeliveryProjectContractLineItem::buildPeriods(
                $validated['frequency'], $validated['start_date'], $validated['end_date']
            ));
        }

        return $validated;
    }

    private function assertPeriodCount(array $periods): void
    {
        if (count($periods) === 0) {
            throw ValidationException::withMessages(['end_date' => 'The schedule produces no billing period.']);
        }

        if (count($periods) >= DeliveryProjectContractLineItem::MAX_PERIODS) {
            throw ValidationException::withMessages([
                'end_date' => 'The schedule is too long (max ' . (DeliveryProjectContractLineItem::MAX_PERIODS - 1) . ' periods). Check the dates.',
            ]);
        }
    }
}
