<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectStakeholder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stakeholder Register — AJAX CRUD untuk section "Stakeholders" di halaman
 * detail Delivery Project (antara Delivery Information dan Team Members).
 */
class DeliveryProjectStakeholderController extends Controller
{
    /**
     * GET /projects/{project}/stakeholders — JSON list untuk tabel.
     */
    public function apiIndex(DeliveryProject $project)
    {
        $rows = DeliveryProjectStakeholder::where('delivery_projects_id', $project->id)
            ->orderBy('seq')
            ->get()
            ->map(fn ($s) => $this->format($s));

        return response()->json(['stakeholders' => $rows]);
    }

    /**
     * POST /projects/{project}/stakeholders
     */
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $this->validatePayload($request);

        $seq = DeliveryProjectStakeholder::nextSeq($project->id);

        $stakeholder = DeliveryProjectStakeholder::create(array_merge($validated, [
            'delivery_projects_id' => $project->id,
            'seq'                  => $seq,
            'stakeholder_id'       => DeliveryProjectStakeholder::buildStakeholderId($seq),
        ]));

        return response()->json([
            'message'     => 'Stakeholder added successfully.',
            'stakeholder' => $this->format($stakeholder),
        ], 201);
    }

    /**
     * PUT /projects/{project}/stakeholders/{stakeholder}
     */
    public function update(Request $request, DeliveryProject $project, DeliveryProjectStakeholder $stakeholder)
    {
        if ($stakeholder->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $this->validatePayload($request);

        $stakeholder->update($validated);

        return response()->json([
            'message'     => 'Stakeholder updated successfully.',
            'stakeholder' => $this->format($stakeholder->fresh()),
        ]);
    }

    /**
     * DELETE /projects/{project}/stakeholders/{stakeholder}
     */
    public function destroy(DeliveryProject $project, DeliveryProjectStakeholder $stakeholder)
    {
        if ($stakeholder->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $stakeholder->delete();

        return response()->json(['message' => 'Stakeholder deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // Validation
    //  - stakeholder_id & seq TIDAK divalidasi: keduanya di-generate server-side.
    //  - Kuadran Power-Interest tidak diterima dari input: diturunkan dari
    //    power + interest oleh model.
    // ──────────────────────────────────────────────────────────────
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'role_title'              => ['nullable', 'string', 'max:255'],
            'organization'            => ['nullable', 'string', 'max:255'],
            'category'                => ['required', 'string', Rule::in(DeliveryProjectStakeholder::CATEGORIES)],
            'classification'          => ['nullable', 'string', 'max:60'],
            'email'                   => ['nullable', 'email', 'max:255'],
            'phone'                   => ['nullable', 'string', 'max:50'],
            'power'                   => ['nullable', 'string', Rule::in(DeliveryProjectStakeholder::LEVELS)],
            'interest'                => ['nullable', 'string', Rule::in(DeliveryProjectStakeholder::LEVELS)],
            'current_attitude'        => ['nullable', 'string', Rule::in(DeliveryProjectStakeholder::ATTITUDES)],
            'expected_attitude'       => ['nullable', 'string', Rule::in(DeliveryProjectStakeholder::ATTITUDES)],
            'key_expectations'        => ['nullable', 'string'],
            'information_needs'       => ['nullable', 'string'],
            'engagement_strategy'     => ['nullable', 'string'],
            'communication_frequency' => ['nullable', 'string', Rule::in(DeliveryProjectStakeholder::FREQUENCIES)],
            'communication_method'    => ['nullable', 'string', 'max:255'],
            'pic_internal'            => ['nullable', 'string', 'max:255'],
            'stakeholder_risk'        => ['nullable', 'string'],
            'status'                  => ['required', 'string', Rule::in(DeliveryProjectStakeholder::STATUSES)],
            'identified_date'         => ['nullable', 'date'],
            'last_updated_date'       => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string'],
        ], [
            'category.in' => 'Category must be Internal or Eksternal.',
            'power.in'    => 'Power must be Tinggi, Sedang or Rendah.',
            'interest.in' => 'Interest must be Tinggi, Sedang or Rendah.',
            'status.in'   => 'Status must be Aktif, Tidak Aktif or Selesai.',
        ]);
    }

    /**
     * Serialisasi satu baris stakeholder untuk response JSON.
     */
    private function format(DeliveryProjectStakeholder $s): array
    {
        return [
            'id'                       => $s->id,
            'stakeholder_id'           => $s->stakeholder_id,
            'name'                     => $s->name,
            'role_title'               => $s->role_title,
            'organization'             => $s->organization,
            'category'                 => $s->category,
            'classification'           => $s->classification,
            'email'                    => $s->email,
            'phone'                    => $s->phone,
            'power'                    => $s->power,
            'interest'                 => $s->interest,
            'quadrant'                 => $s->quadrant,
            'current_attitude'         => $s->current_attitude,
            'expected_attitude'        => $s->expected_attitude,
            'key_expectations'         => $s->key_expectations,
            'information_needs'        => $s->information_needs,
            'engagement_strategy'      => $s->engagement_strategy,
            'communication_frequency'  => $s->communication_frequency,
            'communication_method'     => $s->communication_method,
            'pic_internal'             => $s->pic_internal,
            'stakeholder_risk'         => $s->stakeholder_risk,
            'status'                   => $s->status,
            'identified_date'          => $s->identified_date?->format('Y-m-d'),
            'identified_date_label'    => $s->identified_date?->format('d M Y'),
            'last_updated_date'        => $s->last_updated_date?->format('Y-m-d'),
            'last_updated_date_label'  => $s->last_updated_date?->format('d M Y'),
            'notes'                    => $s->notes,
        ];
    }
}
