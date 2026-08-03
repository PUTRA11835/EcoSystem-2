<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectWricef;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * WRICEF Log — AJAX CRUD untuk section "WRICEF" di halaman detail
 * Delivery Project (tepat setelah Project Planning).
 */
class DeliveryProjectWricefController extends Controller
{
    /**
     * GET /projects/{project}/wricefs — JSON list untuk tabel WRICEF.
     */
    public function apiIndex(DeliveryProject $project)
    {
        $rows = DeliveryProjectWricef::where('delivery_projects_id', $project->id)
            ->orderBy('sap_module')
            ->orderBy('category')
            ->orderBy('seq')
            ->get()
            ->map(fn ($w) => $this->format($w, $project));

        return response()->json([
            'wricefs' => $rows,
            'company' => $this->companyName($project),
        ]);
    }

    /**
     * POST /projects/{project}/wricefs
     */
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $this->validatePayload($request);

        // Obj_Id sepenuhnya turunan modul + kategori — tidak pernah dari input.
        $seq   = DeliveryProjectWricef::nextSeq($project->id, $validated['sap_module'], $validated['category']);
        $objId = DeliveryProjectWricef::buildObjId(
            DeliveryProjectWricef::objIdPrefix($validated['sap_module'], $validated['category']),
            $seq
        );

        $wricef = DeliveryProjectWricef::create(array_merge($validated, [
            'delivery_projects_id' => $project->id,
            'seq'                  => $seq,
            'obj_id'               => $objId,
        ]));

        return response()->json([
            'message' => 'WRICEF added successfully.',
            'wricef'  => $this->format($wricef, $project),
        ], 201);
    }

    /**
     * PUT /projects/{project}/wricefs/{wricef}
     */
    public function update(Request $request, DeliveryProject $project, DeliveryProjectWricef $wricef)
    {
        if ($wricef->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $this->validatePayload($request);

        // Ganti modul atau kategori berarti Obj_Id ikut berubah: ambil nomor
        // urut baru pada kombinasi tujuan, nomor lama dilepas begitu saja.
        if ($validated['sap_module'] !== $wricef->sap_module || $validated['category'] !== $wricef->category) {
            $seq = DeliveryProjectWricef::nextSeq(
                $project->id,
                $validated['sap_module'],
                $validated['category'],
                $wricef->id
            );

            $validated['seq']    = $seq;
            $validated['obj_id'] = DeliveryProjectWricef::buildObjId(
                DeliveryProjectWricef::objIdPrefix($validated['sap_module'], $validated['category']),
                $seq
            );
        }

        $wricef->update($validated);

        return response()->json([
            'message' => 'WRICEF updated successfully.',
            'wricef'  => $this->format($wricef->fresh(), $project),
        ]);
    }

    /**
     * DELETE /projects/{project}/wricefs/{wricef}
     */
    public function destroy(DeliveryProject $project, DeliveryProjectWricef $wricef)
    {
        if ($wricef->delivery_projects_id !== $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $wricef->delete();

        return response()->json(['message' => 'WRICEF deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────
    // Validation
    //  - obj_id & seq TIDAK divalidasi: keduanya di-generate server-side.
    //  - tanggal akhir tiap tahap tidak boleh mendahului tanggal mulainya.
    // ──────────────────────────────────────────────────────────────
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'sap_module'     => ['required', 'string', 'max:20'],
            'category'       => ['required', 'string', Rule::in(array_keys(DeliveryProjectWricef::CATEGORY_LETTERS))],
            'obj_name'       => ['required', 'string', 'max:255'],
            'capability'     => ['nullable', 'string'],
            'tcode'          => ['nullable', 'string', 'max:100'],
            'priority'       => ['required', 'string', Rule::in(DeliveryProjectWricef::PRIORITIES)],
            'requestor'      => ['nullable', 'string', 'max:150'],
            'request_date'   => ['nullable', 'date'],
            'effort_mandays' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'approved_by'    => ['nullable', 'string', 'max:150'],
            'approved_date'  => ['nullable', 'date'],
            'status'         => ['required', 'string', Rule::in(DeliveryProjectWricef::STATUSES)],

            'fsd_pic'      => ['nullable', 'string', 'max:150'],
            'fsd_start'    => ['nullable', 'date'],
            'fsd_end'      => ['nullable', 'date', 'after_or_equal:fsd_start'],
            'fsd_status'   => ['nullable', 'string', Rule::in(DeliveryProjectWricef::FSD_STATUSES)],
            'fsd_remarks'  => ['nullable', 'string'],

            'dev_pic'      => ['nullable', 'string', 'max:150'],
            'dev_start'    => ['nullable', 'date'],
            'dev_end'      => ['nullable', 'date', 'after_or_equal:dev_start'],
            'dev_status'   => ['nullable', 'string', Rule::in(DeliveryProjectWricef::DEV_STATUSES)],
            'dev_remarks'  => ['nullable', 'string'],

            'test_pic'     => ['nullable', 'string', 'max:150'],
            'test_start'   => ['nullable', 'date'],
            'test_end'     => ['nullable', 'date', 'after_or_equal:test_start'],
            'test_status'  => ['nullable', 'string', Rule::in(DeliveryProjectWricef::TEST_STATUSES)],
            'test_remarks' => ['nullable', 'string'],
        ], [
            'category.in'          => 'Category must be one of Workflow, Report, Interface, Conversion, Enhancement or Form.',
            'priority.in'          => 'Priority must be High, Medium or Low.',
            'status.in'            => 'Status must be Open, In Progress or Closed.',
            'fsd_end.after_or_equal'  => 'FSD End cannot be earlier than FSD Start.',
            'dev_end.after_or_equal'  => 'Development End cannot be earlier than Development Start.',
            'test_end.after_or_equal' => 'Testing End cannot be earlier than Testing Start.',
        ]);
    }

    /**
     * Company selalu ikut customer project — tidak pernah disimpan per baris.
     */
    private function companyName(DeliveryProject $project): ?string
    {
        return $project->client->basicData->name_1 ?? null;
    }

    /**
     * Serialisasi satu baris WRICEF untuk response JSON.
     */
    private function format(DeliveryProjectWricef $w, DeliveryProject $project): array
    {
        return [
            'id'                  => $w->id,
            'company'             => $this->companyName($project),
            'sap_module'          => $w->sap_module,
            'category'            => $w->category,
            'obj_id'              => $w->obj_id,
            'obj_name'            => $w->obj_name,
            'capability'          => $w->capability,
            'tcode'               => $w->tcode,
            'priority'            => $w->priority,
            'requestor'           => $w->requestor,
            'request_date'        => $w->request_date?->format('Y-m-d'),
            'request_date_label'  => $w->request_date?->format('d M Y'),
            'effort_mandays'      => $w->effort_mandays !== null ? (float) $w->effort_mandays : null,
            'approved_by'         => $w->approved_by,
            'approved_date'       => $w->approved_date?->format('Y-m-d'),
            'approved_date_label' => $w->approved_date?->format('d M Y'),
            'status'              => $w->status,

            'fsd_pic'         => $w->fsd_pic,
            'fsd_start'       => $w->fsd_start?->format('Y-m-d'),
            'fsd_start_label' => $w->fsd_start?->format('d M Y'),
            'fsd_end'         => $w->fsd_end?->format('Y-m-d'),
            'fsd_end_label'   => $w->fsd_end?->format('d M Y'),
            'fsd_status'      => $w->fsd_status,
            'fsd_remarks'     => $w->fsd_remarks,

            'dev_pic'         => $w->dev_pic,
            'dev_start'       => $w->dev_start?->format('Y-m-d'),
            'dev_start_label' => $w->dev_start?->format('d M Y'),
            'dev_end'         => $w->dev_end?->format('Y-m-d'),
            'dev_end_label'   => $w->dev_end?->format('d M Y'),
            'dev_status'      => $w->dev_status,
            'dev_remarks'     => $w->dev_remarks,

            'test_pic'         => $w->test_pic,
            'test_start'       => $w->test_start?->format('Y-m-d'),
            'test_start_label' => $w->test_start?->format('d M Y'),
            'test_end'         => $w->test_end?->format('Y-m-d'),
            'test_end_label'   => $w->test_end?->format('d M Y'),
            'test_status'      => $w->test_status,
            'test_remarks'     => $w->test_remarks,
        ];
    }
}
