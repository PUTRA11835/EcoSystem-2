<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectRisk;
use Illuminate\Http\Request;

class DeliveryProjectRiskController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GET /projects/{project}/risks
    // ──────────────────────────────────────────────────────────────
    public function index(DeliveryProject $project)
    {
        $risks = DeliveryProjectRisk::where('delivery_projects_id', $project->id)
            ->orderBy('risk_number')
            ->get()
            ->map(fn($r) => $this->format($r));

        return response()->json(['risks' => $risks]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /projects/{project}/risks
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'category'          => 'required|string|max:50',
            'description'       => 'required|string',
            'cause'             => 'nullable|string',
            'project_impact'    => 'nullable|string',
            'probability'       => 'required|integer|min:1|max:5',
            'impact'            => 'required|integer|min:1|max:5',
            'response_strategy' => 'nullable|string|max:20',
            'mitigation_plan'   => 'nullable|string',
            'risk_owner'        => 'nullable|string|max:100',
            'status'            => 'required|string|in:Open,In Progress,Mitigated,Closed',
            'target_date'       => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $nextNumber = (DeliveryProjectRisk::where('delivery_projects_id', $project->id)->max('risk_number') ?? 0) + 1;

        $risk = DeliveryProjectRisk::create(array_merge($validated, [
            'delivery_projects_id' => $project->id,
            'risk_number'          => $nextNumber,
        ]));

        return response()->json([
            'message' => 'Risk added successfully.',
            'risk'    => $this->format($risk),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /projects/{project}/risks/{risk}
    // ──────────────────────────────────────────────────────────────
    public function update(Request $request, DeliveryProject $project, DeliveryProjectRisk $risk)
    {
        if ($risk->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'category'          => 'required|string|max:50',
            'description'       => 'required|string',
            'cause'             => 'nullable|string',
            'project_impact'    => 'nullable|string',
            'probability'       => 'required|integer|min:1|max:5',
            'impact'            => 'required|integer|min:1|max:5',
            'response_strategy' => 'nullable|string|max:20',
            'mitigation_plan'   => 'nullable|string',
            'risk_owner'        => 'nullable|string|max:100',
            'status'            => 'required|string|in:Open,In Progress,Mitigated,Closed',
            'target_date'       => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $risk->update($validated);

        return response()->json([
            'message' => 'Risk updated successfully.',
            'risk'    => $this->format($risk),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /projects/{project}/risks/{risk}
    // ──────────────────────────────────────────────────────────────
    public function destroy(DeliveryProject $project, DeliveryProjectRisk $risk)
    {
        if ($risk->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $risk->delete();

        return response()->json(['message' => 'Risk deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // Serialise a risk row for JSON response
    // ──────────────────────────────────────────────────────────────
    private function format(DeliveryProjectRisk $risk): array
    {
        $score = $risk->probability * $risk->impact;
        if ($score >= 12)     $level = 'High';
        elseif ($score >= 5)  $level = 'Medium';
        else                  $level = 'Low';

        return [
            'id'                => $risk->id,
            'risk_number'       => $risk->risk_number,
            'risk_id_label'     => 'RSK-' . str_pad($risk->risk_number, 3, '0', STR_PAD_LEFT),
            'category'          => $risk->category,
            'description'       => $risk->description,
            'cause'             => $risk->cause,
            'project_impact'    => $risk->project_impact,
            'probability'       => $risk->probability,
            'impact'            => $risk->impact,
            'risk_score'        => $score,
            'risk_level'        => $level,
            'response_strategy' => $risk->response_strategy,
            'mitigation_plan'   => $risk->mitigation_plan,
            'risk_owner'        => $risk->risk_owner,
            'status'            => $risk->status,
            'target_date'       => $risk->target_date?->format('Y-m-d'),
            'target_date_label' => $risk->target_date?->format('d M Y'),
            'notes'             => $risk->notes,
        ];
    }
}
