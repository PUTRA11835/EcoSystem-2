<?php

namespace App\Http\Controllers;

use App\Models\DeliverableDocumentType;
use App\Models\Ticket;
use App\Models\TicketDeliverable;
use Illuminate\Http\Request;

/**
 * Master data "Deliverable Document Type" (menu Management > Master Ticket
 * Settings > Document Type). Menggantikan daftar hardcoded lama di
 * TicketDeliverableController::DOC_TYPES — dipakai untuk mengisi dropdown
 * "Doc Type" di modal "New Document" pada Deliverable Panel ticket.
 *
 * Halaman ini juga mengelola konfigurasi mandatory/optional per ticket type
 * (deliverable_document_type_ticket_types) — digabung di sini daripada jadi
 * submenu terpisah karena keduanya tidak berguna dipisah: aturan mandatory
 * itu properti dari document type itu sendiri, bukan entitas berdiri
 * sendiri. Lihat App\Services\TicketDeliverableRequirementSync untuk
 * bagaimana aturan ini di-snapshot per tiket.
 */
class DeliverableDocumentTypeController extends Controller
{
    public function page()
    {
        return view('management.ticket.document-type.index', [
            // Full ticket_type enum (includes "Internal") — NOT
            // TicketClassification::TYPES, which is deliberately scoped to
            // just the AI/staging classification flow and excludes it.
            'ticketTypes' => Ticket::types(),
        ]);
    }

    /**
     * GET /api/deliverable-document-types
     * Dipakai baik oleh halaman master data ini maupun dropdown "Doc Type"
     * di ticket (biasanya dengan ?is_active=1, order_seq ASC).
     */
    public function index(Request $request)
    {
        $query = DeliverableDocumentType::query()->with('ticketTypeLinks');

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
        $this->syncTicketTypeLinks($type, $request);

        return response()->json([
            'success' => true,
            'message' => 'Document type created successfully.',
            'data'    => $type->load('ticketTypeLinks'),
        ], 201);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'data'    => DeliverableDocumentType::with('ticketTypeLinks')->findOrFail($id),
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
        $this->syncTicketTypeLinks($type, $request);

        return response()->json([
            'success' => true,
            'message' => 'Document type updated successfully.',
            'data'    => $type->fresh('ticketTypeLinks'),
        ]);
    }

    /**
     * Ganti aturan mandatory/optional per ticket type untuk document type ini
     * (deliverable_document_type_ticket_types). Body opsional:
     * `ticket_type_links` = [{ticket_type, is_mandatory}, ...]. Tidak
     * mengirim field ini sama sekali membiarkan aturan yang sudah ada — hanya
     * array kosong `[]` yang menghapus semuanya.
     *
     * INI HANYA MENGUBAH KONFIGURASI LIVE. Tiket yang sudah ada TIDAK
     * terpengaruh — checklist-nya dibaca dari snapshot masing-masing tiket
     * (ticket_deliverable_requirements), bukan dari tabel ini secara
     * langsung. Lihat App\Support\DeliverableDocumentRequirements.
     */
    private function syncTicketTypeLinks(DeliverableDocumentType $type, Request $request): void
    {
        if (!$request->has('ticket_type_links')) {
            return;
        }

        $validated = $request->validate([
            'ticket_type_links'                => 'array',
            'ticket_type_links.*.ticket_type'   => 'required|string|in:' . implode(',', Ticket::types()),
            'ticket_type_links.*.is_mandatory'  => 'boolean',
        ]);

        $type->ticketTypeLinks()->delete();

        foreach ($validated['ticket_type_links'] as $row) {
            $type->ticketTypeLinks()->create([
                'ticket_type'  => $row['ticket_type'],
                'is_mandatory' => (bool) ($row['is_mandatory'] ?? false),
            ]);
        }
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
