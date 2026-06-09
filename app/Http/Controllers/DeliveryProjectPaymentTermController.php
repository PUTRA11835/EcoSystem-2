<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPaymentTerm;
use Illuminate\Http\Request;

class DeliveryProjectPaymentTermController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /projects/{project}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function index(DeliveryProject $project)
    {
        $terms = DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
            ->orderBy('term_number')
            ->get()
            ->map(fn($t) => $this->format($t));

        return response()->json([
            'payment_terms'  => $terms,
            'project_revenue' => (float) ($project->revenue ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /projects/{project}/payment-terms
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $this->validatePayload($request);

        if ($error = $this->checkTotalWithinRevenue($project, $validated['payment_percentage'])) {
            return $error;
        }

        $nextNumber = (DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
            ->max('term_number') ?? 0) + 1;

        $term = DeliveryProjectPaymentTerm::create(array_merge($validated, [
            'delivery_projects_id' => $project->id,
            'term_number'          => $nextNumber,
            'amount'               => $this->computeAmount($project, $validated['payment_percentage']),
        ]));

        return response()->json([
            'message'      => 'Payment term added successfully.',
            'payment_term' => $this->format($term),
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

        $validated = $this->validatePayload($request);

        if ($error = $this->checkTotalWithinRevenue($project, $validated['payment_percentage'], $term->id)) {
            return $error;
        }

        $term->update(array_merge($validated, [
            'amount' => $this->computeAmount($project, $validated['payment_percentage']),
        ]));

        return response()->json([
            'message'      => 'Payment term updated successfully.',
            'payment_term' => $this->format($term),
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
        DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
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
            'paid_date'           => 'nullable|date',
            'status'              => 'required|string|in:Open,Paid,Delay',
        ], [
            'invoice_number.required_with' => 'Invoice Number is required when Submit Invoice Date is filled.',
        ]);
    }

    /**
     * Pastikan total payment term (existing + yang sedang disimpan) tidak melebihi
     * revenue project. Karena amount = revenue × % / 100, ini setara dengan total
     * percentage tidak melebihi 100%. Mengembalikan JsonResponse 422 bila melanggar,
     * atau null bila aman. $excludeId dipakai saat update agar termin yang diedit
     * tidak ikut dihitung dua kali.
     */
    private function checkTotalWithinRevenue(DeliveryProject $project, $newPercentage, $excludeId = null)
    {
        $existingPct = (float) DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->sum('payment_percentage');

        $totalPct = $existingPct + (float) $newPercentage;

        // toleransi floating point kecil
        if ($totalPct > 100.0 + 0.001) {
            $revenue       = (float) ($project->revenue ?? 0);
            $totalAmount   = round($revenue * $totalPct / 100, 2);
            $fmtRevenue    = 'Rp ' . number_format($revenue, 0, ',', '.');
            $fmtTotal      = 'Rp ' . number_format($totalAmount, 0, ',', '.');
            $fmtTotalPct   = rtrim(rtrim(number_format($totalPct, 2, ',', '.'), '0'), ',');

            return response()->json([
                'message' => "Total payment terms ({$fmtTotalPct}% = {$fmtTotal}) cannot exceed the project revenue ({$fmtRevenue}). Please adjust the payment percentage.",
            ], 422);
        }

        return null;
    }

    private function computeAmount(DeliveryProject $project, $percentage): float
    {
        $revenue = (float) ($project->revenue ?? 0);
        return round($revenue * ((float) $percentage) / 100, 2);
    }

    private function format(DeliveryProjectPaymentTerm $term): array
    {
        return [
            'id'                  => $term->id,
            'term_number'         => $term->term_number,
            'payment_term'        => $term->payment_term,
            'payment_percentage'  => (float) $term->payment_percentage,
            'amount'              => (float) $term->amount,
            'requirements'        => $term->requirements,
            'estimated_date'      => $term->estimated_date?->format('Y-m-d'),
            'estimated_date_label'=> $term->estimated_date?->format('d M Y'),
            'submit_invoice_date'       => $term->submit_invoice_date?->format('Y-m-d'),
            'submit_invoice_date_label' => $term->submit_invoice_date?->format('d M Y'),
            'invoice_number'      => $term->invoice_number,
            'paid_date'           => $term->paid_date?->format('Y-m-d'),
            'paid_date_label'     => $term->paid_date?->format('d M Y'),
            'status'              => $term->status,
        ];
    }
}
