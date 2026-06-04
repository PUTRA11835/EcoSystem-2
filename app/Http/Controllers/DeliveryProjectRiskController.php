<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectRisk;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryProjectRiskController extends Controller
{
    // Response strategies per risk type. Opportunity excludes "Exploit".
    private const STRATEGIES_THREAT      = ['Avoid', 'Mitigate', 'Transfer', 'Accept', 'Exploit', 'Enhance', 'Share'];
    private const STRATEGIES_OPPORTUNITY = ['Avoid', 'Mitigate', 'Transfer', 'Accept', 'Enhance', 'Share'];

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
        $validated = $this->validatePayload($request);

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

        $validated = $this->validatePayload($request);

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
    // Shared validation — all fields mandatory.
    // response_strategy options depend on risk_type (Opportunity has no
    // "Exploit"); actual_end_date is required only when status = Closed.
    // ──────────────────────────────────────────────────────────────
    private function validatePayload(Request $request): array
    {
        $allowedStrategies = $request->input('risk_type') === 'Opportunity'
            ? self::STRATEGIES_OPPORTUNITY
            : self::STRATEGIES_THREAT;

        return $request->validate([
            'risk_type'         => ['required', 'string', Rule::in(['Threat', 'Opportunity'])],
            'category'          => 'required|string|max:50',
            'description'       => 'required|string',
            'cause'             => 'required|string',
            'project_impact'    => 'required|string',
            'probability'       => 'required|integer|min:1|max:5',
            'impact'            => 'required|integer|min:1|max:5',
            'response_strategy' => ['required', 'string', Rule::in($allowedStrategies)],
            'mitigation_plan'   => 'required|string',
            'risk_owner'        => 'required|string|max:100',
            'status'            => 'required|string|in:Open,In Progress,Mitigated,Closed',
            'target_date'       => 'required|date',
            'actual_end_date'   => 'required_if:status,Closed|nullable|date',
            'notes'             => 'required|string',
        ], [
            'risk_type.required'         => 'Risk Type is required.',
            'response_strategy.in'       => 'The selected response strategy is not valid for this risk type.',
            'actual_end_date.required_if'=> 'Actual End Date is required when status is Closed.',
        ]);
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
            'risk_type'         => $risk->risk_type,
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
            'status'              => $risk->status,
            'target_date'         => $risk->target_date?->format('Y-m-d'),
            'target_date_label'   => $risk->target_date?->format('d M Y'),
            'actual_end_date'     => $risk->actual_end_date?->format('Y-m-d'),
            'actual_end_date_label' => $risk->actual_end_date?->format('d M Y'),
            'notes'               => $risk->notes,
        ];
    }
}
