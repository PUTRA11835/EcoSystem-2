<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectIssue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryProjectIssueController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // GLOBAL PAGES (Delivery → Issues sub-nav)
    // ──────────────────────────────────────────────────────────────

    /**
     * List the latest issue of every project (newest first) for the
     * global "All Project Issues" page.
     */
    public function index()
    {
        $issues = DeliveryProjectIssue::whereIn('id', function ($query) {
                $query->selectRaw('max(id)')
                    ->from('delivery_project_issues')
                    ->groupBy('delivery_projects_id');
            })
            ->with(['delivery_project.client.basicData'])
            ->latest('created_at')
            ->get();

        return view('delivery.project.issues.index', compact('issues'));
    }

    /**
     * Show every issue belonging to a single project.
     */
    public function show(DeliveryProject $project)
    {
        $project->load(['issues.risk']);

        return view('delivery.project.issues.show', compact('project'));
    }

    // ──────────────────────────────────────────────────────────────
    // AJAX CRUD (Project Detail → Project Issues section)
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /projects/{project}/issues — JSON list for the issue log table.
     */
    public function apiIndex(DeliveryProject $project)
    {
        $issues = DeliveryProjectIssue::where('delivery_projects_id', $project->id)
            ->with('risk')
            ->orderBy('issue_number')
            ->get()
            ->map(fn ($i) => $this->format($i));

        return response()->json(['issues' => $issues]);
    }

    /**
     * POST /projects/{project}/issues
     */
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $this->validatePayload($request, $project);

        $nextNumber = (DeliveryProjectIssue::where('delivery_projects_id', $project->id)->max('issue_number') ?? 0) + 1;

        $issue = DeliveryProjectIssue::create(array_merge($validated, [
            'delivery_projects_id' => $project->id,
            'issue_number'         => $nextNumber,
        ]));

        return response()->json([
            'message' => 'Issue added successfully.',
            'issue'   => $this->format($issue->load('risk')),
        ], 201);
    }

    /**
     * PUT /projects/{project}/issues/{issue}
     */
    public function update(Request $request, DeliveryProject $project, DeliveryProjectIssue $issue)
    {
        if ($issue->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $this->validatePayload($request, $project);

        $issue->update($validated);

        return response()->json([
            'message' => 'Issue updated successfully.',
            'issue'   => $this->format($issue->fresh('risk')),
        ]);
    }

    /**
     * DELETE /projects/{project}/issues/{issue}
     */
    public function destroy(DeliveryProject $project, DeliveryProjectIssue $issue)
    {
        if ($issue->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $issue->delete();

        return response()->json(['message' => 'Issue deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // Shared validation.
    //  - closed_date is required only when status = Closed
    //  - delivery_project_risk_id is optional, but if present it must
    //    belong to the same project (Risk Register of this project)
    // ──────────────────────────────────────────────────────────────
    private function validatePayload(Request $request, DeliveryProject $project): array
    {
        return $request->validate([
            'issue_description'        => 'required|string',
            'module'                   => 'nullable|string|max:100',
            'date_identified'          => 'required|date',
            'closed_date'              => 'required_if:status,Closed|nullable|date',
            'status'                   => ['required', 'string', Rule::in(['Open', 'Closed'])],
            'risk_to_project'          => 'nullable|string',
            'priority'                 => ['required', 'string', Rule::in(['High', 'Medium', 'Low'])],
            'originator'               => 'required|string|max:150',
            'owner'                    => 'required|string|max:150',
            'estimated_closed'         => 'nullable|date',
            'escalation_needed'        => 'required|boolean',
            'impact_of_issue'          => 'nullable|string',
            'tracking_comments'        => 'nullable|string',
            'delivery_project_risk_id' => [
                'nullable',
                Rule::exists('delivery_project_risks', 'id')
                    ->where('delivery_projects_id', $project->id),
            ],
        ], [
            'closed_date.required_if' => 'Closed Date is required when status is Closed.',
            'priority.in'             => 'Priority must be High, Medium or Low.',
            'status.in'               => 'Status must be Open or Closed.',
            'delivery_project_risk_id.exists' => 'The selected Project Risk ID is not valid for this project.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Serialise an issue row for JSON responses.
    // ──────────────────────────────────────────────────────────────
    private function format(DeliveryProjectIssue $issue): array
    {
        return [
            'id'                       => $issue->id,
            'issue_number'             => $issue->issue_number,
            'issue_id_label'           => $issue->issue_id_label,
            'delivery_project_risk_id' => $issue->delivery_project_risk_id,
            'risk_id_label'            => $issue->risk?->risk_id_label,
            'issue_description'        => $issue->issue_description,
            'module'                   => $issue->module,
            'date_identified'          => $issue->date_identified?->format('Y-m-d'),
            'date_identified_label'    => $issue->date_identified?->format('d M Y'),
            'closed_date'              => $issue->closed_date?->format('Y-m-d'),
            'closed_date_label'        => $issue->closed_date?->format('d M Y'),
            'status'                   => $issue->status,
            'risk_to_project'          => $issue->risk_to_project,
            'priority'                 => $issue->priority,
            'originator'               => $issue->originator,
            'owner'                    => $issue->owner,
            'estimated_closed'         => $issue->estimated_closed?->format('Y-m-d'),
            'estimated_closed_label'   => $issue->estimated_closed?->format('d M Y'),
            'escalation_needed'        => (bool) $issue->escalation_needed,
            'impact_of_issue'          => $issue->impact_of_issue,
            'tracking_comments'        => $issue->tracking_comments,
        ];
    }
}
