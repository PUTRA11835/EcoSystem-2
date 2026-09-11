<?php

namespace App\Http\Controllers;

use App\Models\DeliverySupport;
use App\Models\DeliverySupportType;
use Illuminate\Http\Request;

/**
 * Master data "Support Type" (menu Management > Master Delivery Settings >
 * Support Type). Menggantikan daftar hardcoded lama yang tersebar di
 * Delivery\DeliverySupportController, TicketController &
 * resources/views/delivery/support/**.
 */
class DeliverySupportTypeController extends Controller
{
    public function page()
    {
        return view('management.delivery.support.index');
    }

    /**
     * GET /api/delivery-support-types
     * Dipakai baik oleh halaman master data ini maupun dropdown "Type" di
     * form create/edit Delivery Support & filter list (biasanya dengan
     * ?is_active=1).
     */
    public function index(Request $request)
    {
        $query = DeliverySupportType::query();

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
            'name'        => 'required|string|max:100|unique:delivery_support_types,name',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $validated['order_seq'] = (int) (DeliverySupportType::max('order_seq') ?? 0) + 1;

        $type = DeliverySupportType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Support type created successfully.',
            'data'    => $type,
        ], 201);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'data'    => DeliverySupportType::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $type = DeliverySupportType::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:delivery_support_types,name,' . $id,
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $type->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Support type updated successfully.',
            'data'    => $type->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $type = DeliverySupportType::findOrFail($id);

        // `type` di delivery_support adalah kolom string biasa (bukan FK),
        // jadi menghapus tipe di sini tidak mengubah support yang sudah ada —
        // hanya menghilangkannya dari pilihan dropdown ke depan.
        $inUseCount = DeliverySupport::where('type', $type->name)->count();

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => $inUseCount > 0
                ? "Support type deleted. {$inUseCount} existing support(s) keep their previous value."
                : 'Support type deleted successfully.',
        ]);
    }
}
