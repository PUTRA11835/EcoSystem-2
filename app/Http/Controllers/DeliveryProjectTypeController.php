<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectType;
use Illuminate\Http\Request;

/**
 * Master data "Project Type" (menu Management > Master Delivery Settings >
 * Project Type). Menggantikan daftar hardcoded lama yang tersebar di
 * DeliveryProjectController & resources/views/delivery/project/**.
 */
class DeliveryProjectTypeController extends Controller
{
    public function page()
    {
        return view('management.delivery.project.index');
    }

    /**
     * GET /api/delivery-project-types
     * Dipakai baik oleh halaman master data ini maupun dropdown "Project Type"
     * di form create/edit Delivery Project (biasanya dengan ?is_active=1).
     */
    public function index(Request $request)
    {
        $query = DeliveryProjectType::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $types = $query->orderBy('order_seq')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $types,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:delivery_project_types,name',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $validated['order_seq'] = (int) (DeliveryProjectType::max('order_seq') ?? 0) + 1;

        $type = DeliveryProjectType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Project type created successfully.',
            'data'    => $type,
        ], 201);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'data'    => DeliveryProjectType::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $type = DeliveryProjectType::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:delivery_project_types,name,' . $id,
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $type->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Project type updated successfully.',
            'data'    => $type->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $type = DeliveryProjectType::findOrFail($id);

        // project_type di delivery_projects adalah kolom string biasa (bukan
        // FK), jadi menghapus tipe di sini tidak mengubah project yang sudah
        // ada — hanya menghilangkannya dari pilihan dropdown ke depan.
        $inUseCount = DeliveryProject::where('project_type', $type->name)->count();

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => $inUseCount > 0
                ? "Project type deleted. {$inUseCount} existing project(s) keep their previous value."
                : 'Project type deleted successfully.',
        ]);
    }
}
