<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectContractLineItem;
use App\Models\DeliveryProjectPaymentTerm;
use App\Services\ProjectReminderService;
use App\Services\ProjectTopPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Term Of Payment Plan — dua mode penagihan (lihat ProjectTopPlan):
 *   - percentage : amount = revenue × % / 100 (total % ≤ 100, diblokir).
 *   - line_item  : termin wajib dikaitkan ke Contract Line Item; amount diambil
 *                  dari nilai kontrak line item — basis "line_item" (% × nilai
 *                  line item) ATAU "fixed" (nominal). Basis % dari revenue
 *                  dikunci (hanya termin lama yang sudah memakainya boleh tetap).
 *                  Total > revenue hanya diperingatkan.
 */
class DeliveryProjectPaymentTermController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /projects/{project}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function index(DeliveryProject $project)
    {
        // Amount basis % = nilai turunan. Term yang dibuat saat revenue masih
        // kosong tersimpan 0 dan tetap basi sampai form Financial di-save ulang
        // → self-heal saat dibaca.
        DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
            ->get()
            ->each(fn(DeliveryProjectPaymentTerm $t) => $this->resyncAmount($project, $t));

        $plan = new ProjectTopPlan($project);
        $plan->resyncLineItemAmounts();

        return response()->json($plan->payload());
    }

    // ──────────────────────────────────────────────────────────────
    // POST /projects/{project}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $this->validatePayload($request, $project);

        if ($error = $this->checkPercentageWithinRevenue($project, $validated)) {
            return $error;
        }

        $nextNumber = (DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
            ->max('term_number') ?? 0) + 1;

        $term = DeliveryProjectPaymentTerm::create(array_merge($validated, [
            'delivery_projects_id' => $project->id,
            'term_number'          => $nextNumber,
        ]));

        $plan = new ProjectTopPlan($project);
        $plan->resequence();

        // A new term may already be due/overdue → refresh the invoice reminders now.
        app(ProjectReminderService::class)->syncAllQuietly();

        return response()->json([
            'message'      => 'Payment term added successfully.',
            'payment_term' => ProjectTopPlan::formatTerm($term->fresh(), $plan->revenue()),
            'warnings'     => $plan->warnings(),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /projects/{project}/payment-terms/{term}
    // ──────────────────────────────────────────────────────────────
    public function update(Request $request, DeliveryProject $project, DeliveryProjectPaymentTerm $term)
    {
        if ($term->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $this->validatePayload($request, $project, $term);

        if ($error = $this->checkPercentageWithinRevenue($project, $validated, $term->id)) {
            return $error;
        }

        $term->update($validated);

        $plan = new ProjectTopPlan($project);
        $plan->resequence();

        // estimated_date / submit_invoice_date may have changed → re-evaluate reminders.
        app(ProjectReminderService::class)->syncAllQuietly();

        return response()->json([
            'message'      => 'Payment term updated successfully.',
            'payment_term' => ProjectTopPlan::formatTerm($term->fresh(), $plan->revenue()),
            'warnings'     => $plan->warnings(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /projects/{project}/payment-terms/{term}
    // ──────────────────────────────────────────────────────────────
    public function destroy(DeliveryProject $project, DeliveryProjectPaymentTerm $term)
    {
        if ($term->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $term->delete();

        // Re-sequence remaining term numbers so "No" stays 1..N
        (new ProjectTopPlan($project))->resequence();

        // A deleted term must drop its reminder too.
        app(ProjectReminderService::class)->syncAllQuietly();

        return response()->json(['message' => 'Payment term deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /projects/{project}/top-mode
    // Ganti mode penagihan: percentage ↔ line_item
    // ──────────────────────────────────────────────────────────────
    public function updateMode(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'top_mode' => ['required', Rule::in(ProjectTopPlan::MODES)],
        ]);

        // Mode % hanya mengenal % dari revenue — termin yang amount-nya diambil
        // dari line item harus dihapus dulu supaya tidak ada termin yang
        // amount-nya tiba-tiba berubah.
        if ($validated['top_mode'] === 'percentage') {
            $fromLineItem = DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
                ->whereIn('basis', ['fixed', 'line_item'])
                ->count();

            if ($fromLineItem > 0) {
                return response()->json([
                    'message' => "Cannot switch to \"% of Revenue\": {$fromLineItem} payment term(s) take their amount from a contract line item. Delete them first.",
                ], 422);
            }
        }

        $project->update(['top_mode' => $validated['top_mode']]);

        $plan = new ProjectTopPlan($project);
        $plan->resequence();

        return response()->json([
            'message'  => $validated['top_mode'] === 'line_item'
                ? 'TOP type switched to Contract Line Item.'
                : 'TOP type switched to % of Revenue.',
            'top_mode' => $plan->mode(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Validasi + normalisasi. Hasilnya siap disimpan:
     *   - basis percentage → payment_percentage diisi, amount diturunkan dari revenue.
     *   - basis line_item  → payment_percentage diisi, amount = % × nilai line item.
     *   - basis fixed      → amount diisi user, payment_percentage = 0.
     * Mode percentage selalu memaksa basis percentage tanpa line item. Mode
     * line_item mengunci basis percentage, kecuali termin lama yang memang
     * sudah memakainya (supaya data lama tetap bisa diedit).
     */
    private function validatePayload(Request $request, DeliveryProject $project, ?DeliveryProjectPaymentTerm $existing = null): array
    {
        $lineItemMode = (new ProjectTopPlan($project))->mode() === 'line_item';
        $requested    = $request->input('basis');

        // Normalisasi SEBELUM validasi (aturan required_if/required_unless di
        // bawah bergantung pada nilai ini).
        if (!$lineItemMode) {
            $basis = 'percentage';
        } elseif (in_array($requested, ['line_item', 'fixed'], true)) {
            $basis = $requested;
        } elseif ($existing && $existing->isRevenueShare()) {
            $basis = 'percentage';
        } else {
            throw ValidationException::withMessages([
                'basis' => '"% of Revenue" is locked in Contract Line Item mode. Use "% of Line Item" or "Amount".',
            ]);
        }
        $request->merge(['basis' => $basis]);

        $validated = $request->validate([
            'basis'                 => ['required', Rule::in(DeliveryProjectPaymentTerm::BASES)],
            'contract_line_item_id' => [
                $lineItemMode ? 'required' : 'nullable',
                'integer',
                Rule::exists('delivery_project_contract_line_items', 'id')
                    ->where('delivery_projects_id', $project->id),
            ],
            'payment_term'        => 'required|string|max:255',
            'period'              => 'nullable|string|max:50',
            'payment_percentage'  => 'nullable|required_unless:basis,fixed|numeric|min:0|max:100',
            'amount'              => 'nullable|required_if:basis,fixed|numeric|min:0',
            'requirements'        => 'nullable|string',
            'estimated_date'      => 'nullable|date',
            // Status Invoiced = invoice sudah dikirim → tanggalnya wajib ada
            'submit_invoice_date' => 'nullable|required_if:status,Invoiced|date',
            // Invoice number wajib diisi ketika Submit Invoice Date terisi
            'invoice_number'      => 'nullable|required_with:submit_invoice_date|string|max:255',
            // Paid date wajib diisi ketika status = Paid
            'paid_date'           => 'nullable|required_if:status,Paid|date',
            'status'              => ['required', 'string', Rule::in(DeliveryProjectPaymentTerm::STATUSES)],
        ], [
            'submit_invoice_date.required_if' => 'Submit Invoice Date is required when Status is Invoiced.',
            'contract_line_item_id.required' => 'Line Item is required in Line Item billing mode. Add a contract line item first.',
            'contract_line_item_id.exists'   => 'The selected line item does not belong to this project.',
            'payment_percentage.required_unless' => 'Payment % is required.',
            'amount.required_if'           => 'Amount is required for a fixed-amount term.',
            'invoice_number.required_with' => 'Invoice Number is required when Submit Invoice Date is filled.',
            'paid_date.required_if'        => 'Paid Date is required when Status is Paid.',
        ]);

        $basis = $validated['basis'];
        $validated['contract_line_item_id'] = $lineItemMode ? (int) $validated['contract_line_item_id'] : null;

        if ($basis === 'fixed') {
            // Nominal tetap: TIDAK diambil dari revenue, persentase tidak dipakai.
            $validated['amount']             = round((float) $validated['amount'], 2);
            $validated['payment_percentage'] = 0;
        } elseif ($basis === 'line_item') {
            // % dari nilai kontrak line item (bukan dari revenue).
            $lineItem = DeliveryProjectContractLineItem::find($validated['contract_line_item_id']);
            $validated['payment_percentage'] = (float) $validated['payment_percentage'];
            $validated['amount'] = DeliveryProjectPaymentTerm::lineItemAmount($validated['payment_percentage'], $lineItem?->total() ?? 0);
        } else {
            $validated['payment_percentage'] = (float) $validated['payment_percentage'];
            $validated['amount']             = $this->computeAmount($project, $validated['payment_percentage']);
        }

        return $validated;
    }

    /**
     * Termin basis % tetap tidak boleh membuat total % melebihi 100 (= revenue).
     * Termin nominal tetap tidak dihitung di sini — kelebihannya hanya
     * diperingatkan lewat ProjectTopPlan::warnings().
     */
    private function checkPercentageWithinRevenue(DeliveryProject $project, array $validated, $excludeId = null)
    {
        if ($validated['basis'] !== 'percentage') {
            return null;
        }

        $existingPct = (float) DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
            ->where('basis', 'percentage')
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->sum('payment_percentage');

        $totalPct = $existingPct + (float) $validated['payment_percentage'];

        // toleransi floating point kecil
        if ($totalPct > 100.0 + 0.001) {
            $revenue     = (float) ($project->revenue ?? 0);
            $totalAmount = round($revenue * $totalPct / 100, 2);
            $fmtTotalPct = rtrim(rtrim(number_format($totalPct, 2, ',', '.'), '0'), ',');

            return response()->json([
                'message' => "Total payment terms ({$fmtTotalPct}% = " . ProjectTopPlan::rp($totalAmount)
                    . ') cannot exceed the project revenue (' . ProjectTopPlan::rp($revenue) . '). Please adjust the payment percentage.',
            ], 422);
        }

        return null;
    }

    /**
     * Perbarui amount tersimpan termin basis % bila tidak lagi sesuai revenue
     * project saat ini. Termin nominal tetap tidak disentuh. Timestamps sengaja
     * dimatikan agar audit trail term tidak berubah hanya karena halaman dibuka
     * — begitu juga "Last Update Date" project.
     */
    private function resyncAmount(DeliveryProject $project, DeliveryProjectPaymentTerm $term): void
    {
        // Hanya basis % dari revenue; basis line item disinkronkan oleh
        // ProjectTopPlan::resyncLineItemAmounts().
        if (!$term->isRevenueShare()) {
            return;
        }

        $amount = $this->computeAmount($project, $term->payment_percentage);

        if (abs((float) $term->amount - $amount) > 0.001) {
            $term->amount = $amount;
            $term->timestamps = false;
            DeliveryProject::withoutActivityTracking(fn () => $term->save());
            $term->timestamps = true;
        }
    }

    private function computeAmount(DeliveryProject $project, $percentage): float
    {
        return DeliveryProjectPaymentTerm::amountFor('percentage', $percentage, 0, $project->revenue);
    }
}
