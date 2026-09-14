<?php

namespace App\Http\Controllers;

use App\Models\DeliverableDocumentType;
use App\Models\TicketDeliverable;
use Illuminate\Http\Request;

/**
 * Master data "Deliverable Document Type" (menu Management > Master Ticket
 * Settings > Document Type). Menggantikan daftar hardcoded lama di
 * TicketDeliverableController::DOC_TYPES — dipakai untuk mengisi dropdown
 * "Doc Type" di modal "New Document" pada Deliverable Panel ticket.
 */
class DeliverableDocumentTypeController extends Controller
{
    public function page()
    {
        return view('management.ticket.document-type.index');
    }

    /**
     * GET /api/deliverable-document-types
     * Dipakai baik oleh halaman master data ini maupun dropdown "Doc Type"
     * di ticket (biasanya dengan ?is_active=1, order_seq ASC).
     */
    public function index(Request $request)
    {
        $query = DeliverableDocumentType::query();

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
            'name'        => 'required|string|max:50|unique:deliverable_document_types,name',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        // Tipe baru ditaruh di urutan paling akhir dropdown.
        $validated['order_seq'] = (int) (DeliverableDocumentType::max('order_seq') ?? 0) + 1;

        $type = DeliverableDocumentType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document type created successfully.',
            'data'    => $type,
        ], 201);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'data'    => DeliverableDocumentType::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $type = DeliverableDocumentType::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:50|unique:deliverable_document_types,name,' . $id,
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $type->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document type updated successfully.',
            'data'    => $type->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $type = DeliverableDocumentType::findOrFail($id);

        // doc_type di ticket_deliverables adalah kolom string biasa (bukan FK),
        // jadi menghapus tipe di sini tidak mengubah dokumen yang sudah ada —
        // hanya menghilangkannya dari pilihan dropdown ke depan. Beri tahu
        // jumlahnya supaya admin tidak kaget bila tipe ini masih dipakai.
        $inUseCount = TicketDeliverable::where('doc_type', $type->name)->count();

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => $inUseCount > 0
                ? "Document type deleted. {$inUseCount} existing deliverable document(s) keep their previous value."
                : 'Document type deleted successfully.',
        ]);
    }
}
