<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProjectPaymentTerm;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportContractLineItem;
use App\Models\DeliverySupportPaymentTerm;
use App\Services\ProjectTopPlan;
use App\Services\SupportTopPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Term Of Payment (TOP) plan untuk Delivery Support.
 * Mirror dari DeliveryProjectPaymentTermController — dua mode penagihan
 * (lihat SupportTopPlan):
 *   - percentage : amount = revenue × % / 100 (total % ≤ 100, diblokir).
 *   - line_item  : termin wajib dikaitkan ke Contract Line Item; amount diambil
 *                  dari nilai kontrak line item — basis "line_item" (% × nilai
 *                  line item) ATAU "fixed" (nominal). Basis % dari revenue
 *                  dikunci (hanya termin lama yang sudah memakainya boleh tetap).
 *                  Total > revenue hanya diperingatkan.
 * Tanpa ProjectReminderService karena reminder invoice/deadline hanya berlaku
 * untuk Delivery Project.
 */
class DeliverySupportPaymentTermController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /delivery/support/{support}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function index(DeliverySupport $support)
    {
        // Amount basis % adalah nilai TURUNAN (revenue × % / 100) yang ikut disimpan.
        // Term yang dibuat saat revenue masih kosong tersimpan 0; kalau revenue
        // diisi belakangan lewat jalur apa pun, nilai lama jadi basi → self-heal.
        DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
            ->get()
            ->each(fn(DeliverySupportPaymentTerm $t) => $this->resyncAmount($support, $t));

        $plan = new SupportTopPlan($support);
        $plan->resyncLineItemAmounts();

        return response()->json($plan->payload());
    }

    // ──────────────────────────────────────────────────────────────
    // POST /delivery/support/{support}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request, DeliverySupport $support)
    {
        $validated = $this->validatePayload($request, $support);

        if ($error = $this->checkPercentageWithinRevenue($support, $validated)) {
            return $error;
        }

        $nextNumber = (DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
            ->max('term_number') ?? 0) + 1;

        $term = DeliverySupportPaymentTerm::create(array_merge($validated, [
            'delivery_support_id' => $support->id,
            'term_number'         => $nextNumber,
        ]));

        $plan = new SupportTopPlan($support);
        $plan->resequence();

        return response()->json([
            'message'      => 'Payment term added successfully.',
            'payment_term' => SupportTopPlan::formatTerm($term->fresh(), $plan->revenue()),
            'warnings'     => $plan->warnings(),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /delivery/support/{support}/payment-terms/{term}
    // ──────────────────────────────────────────────────────────────
    public function update(Request $request, DeliverySupport $support, DeliverySupportPaymentTerm $term)
    {
        if ($term->delivery_support_id !== $support->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $this->validatePayload($request, $support, $term);

        if ($error = $this->checkPercentageWithinRevenue($support, $validated, $term->id)) {
            return $error;
        }

        $term->update($validated);

        $plan = new SupportTopPlan($support);
        $plan->resequence();

        return response()->json([
            'message'      => 'Payment term updated successfully.',
            'payment_term' => SupportTopPlan::formatTerm($term->fresh(), $plan->revenue()),
            'warnings'     => $plan->warnings(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /delivery/support/{support}/payment-terms/{term}
    // ──────────────────────────────────────────────────────────────
    public function destroy(DeliverySupport $support, DeliverySupportPaymentTerm $term)
    {
        if ($term->delivery_support_id !== $support->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $term->delete();

        // Re-sequence remaining term numbers so "No" stays 1..N
        (new SupportTopPlan($support))->resequence();

        return response()->json(['message' => 'Payment term deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /delivery/support/{support}/top-mode
    // Ganti mode penagihan: percentage ↔ line_item
    // ──────────────────────────────────────────────────────────────
    public function updateMode(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'top_mode' => ['required', Rule::in(SupportTopPlan::MODES)],
        ]);

        // Mode % hanya mengenal % dari revenue — termin yang amount-nya diambil
        // dari line item harus dihapus dulu supaya tidak ada termin yang
        // amount-nya tiba-tiba berubah.
        if ($validated['top_mode'] === 'percentage') {
            $fromLineItem = DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
                ->whereIn('basis', ['fixed', 'line_item'])
                ->count();

            if ($fromLineItem > 0) {
                return response()->json([
                    'message' => "Cannot switch to \"% of Revenue\": {$fromLineItem} payment term(s) take their amount from a contract line item. Delete them first.",
                ], 422);
            }
        }

        $support->update(['top_mode' => $validated['top_mode']]);

        $plan = new SupportTopPlan($support);
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
    private function validatePayload(Request $request, DeliverySupport $support, ?DeliverySupportPaymentTerm $existing = null): array
    {
        $lineItemMode = (new SupportTopPlan($support))->mode() === 'line_item';
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
            'basis'                 => ['required', Rule::in(DeliverySupportPaymentTerm::BASES)],
            'contract_line_item_id' => [
                $lineItemMode ? 'required' : 'nullable',
                'integer',
                Rule::exists('delivery_support_contract_line_items', 'id')
                    ->where('delivery_support_id', $support->id),
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
            'status'              => ['required', 'string', Rule::in(DeliverySupportPaymentTerm::STATUSES)],
        ], [
            'submit_invoice_date.required_if'    => 'Submit Invoice Date is required when Status is Invoiced.',
            'contract_line_item_id.required'     => 'Line Item is required in Line Item billing mode. Add a contract line item first.',
            'contract_line_item_id.exists'       => 'The selected line item does not belong to this support.',
            'payment_percentage.required_unless' => 'Payment % is required.',
            'amount.required_if'                 => 'Amount is required for a fixed-amount term.',
            'invoice_number.required_with'       => 'Invoice Number is required when Submit Invoice Date is filled.',
            'paid_date.required_if'              => 'Paid Date is required when Status is Paid.',
        ]);

        $basis = $validated['basis'];
        $validated['contract_line_item_id'] = $lineItemMode ? (int) $validated['contract_line_item_id'] : null;
        // Label periode hanya bermakna di mode line item.
        $validated['period'] = $lineItemMode ? ($validated['period'] ?? null) : null;

        if ($basis === 'fixed') {
            // Nominal tetap: TIDAK diambil dari revenue, persentase tidak dipakai.
            $validated['amount']             = round((float) $validated['amount'], 2);
            $validated['payment_percentage'] = 0;
        } elseif ($basis === 'line_item') {
            // % dari nilai kontrak line item (bukan dari revenue).
            $lineItem = DeliverySupportContractLineItem::find($validated['contract_line_item_id']);
            $validated['payment_percentage'] = (float) $validated['payment_percentage'];
            $validated['amount'] = DeliveryProjectPaymentTerm::lineItemAmount($validated['payment_percentage'], $lineItem?->total() ?? 0);
        } else {
            $validated['payment_percentage'] = (float) $validated['payment_percentage'];
            $validated['amount']             = $this->computeAmount($support, $validated['payment_percentage']);
        }

        return $validated;
    }

    /**
     * Termin basis % tidak boleh membuat total % melebihi 100 (= revenue).
     * Termin basis line item tidak dihitung di sini — kelebihannya hanya
     * diperingatkan lewat SupportTopPlan::warnings().
     */
    private function checkPercentageWithinRevenue(DeliverySupport $support, array $validated, $excludeId = null)
    {
        if ($validated['basis'] !== 'percentage') {
            return null;
        }

        $existingPct = (float) DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
            ->where('basis', 'percentage')
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->sum('payment_percentage');

        $totalPct = $existingPct + (float) $validated['payment_percentage'];

        // toleransi floating point kecil
        if ($totalPct > 100.0 + 0.001) {
            $revenue     = (float) ($support->revenue ?? 0);
            $totalAmount = round($revenue * $totalPct / 100, 2);
            $fmtTotalPct = rtrim(rtrim(number_format($totalPct, 2, ',', '.'), '0'), ',');

            return response()->json([
                'message' => "Total payment terms ({$fmtTotalPct}% = " . ProjectTopPlan::rp($totalAmount)
                    . ') cannot exceed the support revenue (' . ProjectTopPlan::rp($revenue) . '). Please adjust the payment percentage.',
            ], 422);
        }

        return null;
    }

    /**
     * Perbarui amount tersimpan termin basis % bila tidak lagi sesuai revenue
     * support saat ini. Termin basis line item tidak disentuh. Timestamps
     * sengaja dimatikan agar audit trail term tidak berubah hanya karena
     * halaman dibuka.
     */
    private function resyncAmount(DeliverySupport $support, DeliverySupportPaymentTerm $term): void
    {
        // Hanya basis % dari revenue; basis line item disinkronkan oleh
        // SupportTopPlan::resyncLineItemAmounts().
        if (!$term->isRevenueShare()) {
            return;
        }

        $amount = $this->computeAmount($support, $term->payment_percentage);

        if (abs((float) $term->amount - $amount) > 0.001) {
            $term->amount = $amount;
            $term->timestamps = false;
            $term->save();
            $term->timestamps = true;
        }
    }

    private function computeAmount(DeliverySupport $support, $percentage): float
    {
        return DeliveryProjectPaymentTerm::amountFor('percentage', $percentage, 0, $support->revenue);
    }
}
