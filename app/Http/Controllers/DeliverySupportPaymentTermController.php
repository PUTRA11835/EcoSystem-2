<?php

namespace App\Http\Controllers;

use App\Models\DeliverySupport;
use App\Models\DeliverySupportPaymentTerm;
use Illuminate\Http\Request;

/**
 * Term Of Payment (TOP) plan untuk Delivery Support.
 * Mirror dari DeliveryProjectPaymentTermController — tanpa ProjectReminderService
 * karena reminder invoice/deadline hanya berlaku untuk Delivery Project.
 */
class DeliverySupportPaymentTermController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /delivery/support/{support}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function index(DeliverySupport $support)
    {
        $terms = DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
            ->orderBy('term_number')
            ->get()
            ->map(fn($t) => $this->format($t));

        return response()->json([
            'payment_terms'   => $terms,
            'support_revenue' => (float) ($support->revenue ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /delivery/support/{support}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request, DeliverySupport $support)
    {
        $validated = $this->validatePayload($request);

        if ($error = $this->checkTotalWithinRevenue($support, $validated['payment_percentage'])) {
            return $error;
        }

        $nextNumber = (DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
            ->max('term_number') ?? 0) + 1;

        $term = DeliverySupportPaymentTerm::create(array_merge($validated, [
            'delivery_support_id' => $support->id,
            'term_number'         => $nextNumber,
            'amount'              => $this->computeAmount($support, $validated['payment_percentage']),
        ]));

        return response()->json([
            'message'      => 'Payment term added successfully.',
            'payment_term' => $this->format($term),
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

        $validated = $this->validatePayload($request);

        if ($error = $this->checkTotalWithinRevenue($support, $validated['payment_percentage'], $term->id)) {
            return $error;
        }

        $term->update(array_merge($validated, [
            'amount' => $this->computeAmount($support, $validated['payment_percentage']),
        ]));

        return response()->json([
            'message'      => 'Payment term updated successfully.',
            'payment_term' => $this->format($term),
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
        DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
            ->orderBy('term_number')
            ->get()
            ->each(function ($t, $i) {
                $t->update(['term_number' => $i + 1]);
            });

        return response()->json(['message' => 'Payment term deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'payment_term'        => 'required|string|max:255',
            'payment_percentage'  => 'required|numeric|min:0|max:100',
            'requirements'        => 'nullable|string',
            'estimated_date'      => 'nullable|date',
            'submit_invoice_date' => 'nullable|date',
            // Invoice number wajib diisi ketika Submit Invoice Date terisi
            'invoice_number'      => 'nullable|required_with:submit_invoice_date|string|max:255',
            // Paid date wajib diisi ketika status = Paid
            'paid_date'           => 'nullable|required_if:status,Paid|date',
            'status'              => 'required|string|in:Open,Paid,Delay',
        ], [
            'invoice_number.required_with' => 'Invoice Number is required when Submit Invoice Date is filled.',
            'paid_date.required_if'        => 'Paid Date is required when Status is Paid.',
        ]);
    }

    /**
     * Pastikan total payment term (existing + yang sedang disimpan) tidak melebihi
     * revenue support. Karena amount = revenue × % / 100, ini setara dengan total
     * percentage tidak melebihi 100%.
     */
    private function checkTotalWithinRevenue(DeliverySupport $support, $newPercentage, $excludeId = null)
    {
        $existingPct = (float) DeliverySupportPaymentTerm::where('delivery_support_id', $support->id)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->sum('payment_percentage');

        $totalPct = $existingPct + (float) $newPercentage;

        // toleransi floating point kecil
        if ($totalPct > 100.0 + 0.001) {
            $revenue     = (float) ($support->revenue ?? 0);
            $totalAmount = round($revenue * $totalPct / 100, 2);
            $fmtRevenue  = 'Rp ' . number_format($revenue, 0, ',', '.');
            $fmtTotal    = 'Rp ' . number_format($totalAmount, 0, ',', '.');
            $fmtTotalPct = rtrim(rtrim(number_format($totalPct, 2, ',', '.'), '0'), ',');

            return response()->json([
                'message' => "Total payment terms ({$fmtTotalPct}% = {$fmtTotal}) cannot exceed the support revenue ({$fmtRevenue}). Please adjust the payment percentage.",
            ], 422);
        }

        return null;
    }

    private function computeAmount(DeliverySupport $support, $percentage): float
    {
        $revenue = (float) ($support->revenue ?? 0);
        return round($revenue * ((float) $percentage) / 100, 2);
    }

    private function format(DeliverySupportPaymentTerm $term): array
    {
        return [
            'id'                        => $term->id,
            'term_number'               => $term->term_number,
            'payment_term'              => $term->payment_term,
            'payment_percentage'        => (float) $term->payment_percentage,
            'amount'                    => (float) $term->amount,
            'requirements'              => $term->requirements,
            'estimated_date'            => $term->estimated_date?->format('Y-m-d'),
            'estimated_date_label'      => $term->estimated_date?->format('d M Y'),
            'submit_invoice_date'       => $term->submit_invoice_date?->format('Y-m-d'),
            'submit_invoice_date_label' => $term->submit_invoice_date?->format('d M Y'),
            'invoice_number'            => $term->invoice_number,
            'paid_date'                 => $term->paid_date?->format('Y-m-d'),
            'paid_date_label'           => $term->paid_date?->format('d M Y'),
            'status'                    => $term->status,
        ];
    }
}
