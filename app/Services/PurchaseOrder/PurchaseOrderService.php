<?php

namespace App\Services\PurchaseOrder;

use App\Models\PurchaseOrder\PurchaseOrder;
use App\Models\PurchaseOrder\PurchaseOrderSetting;
use App\Models\PurchaseRequest\PurchaseRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purchase Order: penomoran, perhitungan pajak, dan keputusan.
 *
 * PO memakai satu keputusan oleh penyetuju yang DIPILIH pada dokumen
 * (approver_id) — bukan alur bertingkat seperti PR. Pemegang `.manage` dapat
 * bertindak mewakilinya.
 *
 * Perhitungan per baris:
 *   subtotal = qty x harga satuan
 *   diskon   = persen dari subtotal, atau nominal (tidak melebihi subtotal)
 *   dasar    = subtotal - diskon
 *   VAT      = dasar x tarif (menambah)
 *   PPh/PP   = dasar x tarif (dipotong dari pembayaran)
 *   total    = dasar + VAT - potongan
 * Nilainya DISIMPAN per baris, jadi mengganti tarif di Settings tidak mengubah
 * dokumen yang sudah ada.
 */
class PurchaseOrderService
{
    /**
     * Normalisasi baris dari form dan hitung totalnya.
     *
     * @param  array<int|string, array>  $rawItems
     * @return array{items: array<int, array>, totals: array<string, float>}
     */
    public function compute(array $rawItems, PurchaseOrderSetting $settings): array
    {
        $taxes  = $settings->taxTable();
        $items  = [];
        $totals = ['subtotal' => 0.0, 'discount' => 0.0, 'vat' => 0.0, 'withholding' => 0.0, 'grand' => 0.0];
        $line   = 1;

        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $description = trim((string) ($raw['description'] ?? ''));
            $qty         = max(0.0, (float) ($raw['qty'] ?? 0));
            $price       = max(0.0, (float) ($raw['unit_price'] ?? 0));

            if ($description === '' && $price <= 0) {
                continue;
            }

            $subtotal = round($qty * $price, 2);

            $discountType  = ($raw['discount_type'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
            $discountValue = max(0.0, (float) ($raw['discount_value'] ?? 0));
            $discount      = $discountType === 'percent'
                ? round($subtotal * min($discountValue, 100) / 100, 2)
                : min($discountValue, $subtotal);

            $base = $subtotal - $discount;

            $keys = array_values(array_intersect(array_keys($taxes), (array) ($raw['tax_keys'] ?? [])));

            $vat = 0.0;
            $withholding = 0.0;

            foreach ($keys as $key) {
                $amount = round($base * $taxes[$key]['rate'] / 100, 2);

                if ($taxes[$key]['kind'] === 'add') {
                    $vat += $amount;
                } else {
                    $withholding += $amount;
                }
            }

            $total = round($base + $vat - $withholding, 2);

            $items[] = [
                'line_no'            => $line++,
                'description'        => $description,
                'qty'                => $qty,
                'unit'               => strtoupper(trim((string) ($raw['unit'] ?? 'PC'))) ?: 'PC',
                'currency'           => strtoupper(trim((string) ($raw['currency'] ?? 'IDR'))) ?: 'IDR',
                'unit_price'         => $price,
                'project_label'      => trim((string) ($raw['project_label'] ?? '')) ?: null,
                'period_from'        => ($raw['period_from'] ?? '') ?: null,
                'period_to'          => ($raw['period_to'] ?? '') ?: null,
                'discount_type'      => $discountType,
                'discount_value'     => $discountValue,
                'tax_keys'           => $keys,
                'line_subtotal'      => $subtotal,
                'discount_amount'    => $discount,
                'vat_amount'         => $vat,
                'withholding_amount' => $withholding,
                'line_total'         => $total,
            ];

            $totals['subtotal']    += $subtotal;
            $totals['discount']    += $discount;
            $totals['vat']         += $vat;
            $totals['withholding'] += $withholding;
            $totals['grand']       += $total;
        }

        return ['items' => $items, 'totals' => array_map(fn ($v) => round($v, 2), $totals)];
    }

    /**
     * @param  array  $data  tervalidasi (lihat PurchaseOrderController)
     * @return array{allowed: bool, reason: string, order: ?PurchaseOrder}
     */
    public function create(array $data, int $actorId): array
    {
        $settings = PurchaseOrderSetting::current();

        if ($settings->requires_pr && empty($data['purchase_request_id'])) {
            return ['allowed' => false, 'reason' => 'A purchase request is required for every purchase order.', 'order' => null];
        }

        $computed = $this->compute($data['items'] ?? [], $settings);

        if ($computed['items'] === []) {
            return ['allowed' => false, 'reason' => 'Add at least one item.', 'order' => null];
        }

        $order = DB::transaction(function () use ($data, $computed, $actorId) {
            $order = PurchaseOrder::create($this->header($data, $computed['totals']) + [
                'po_no'      => $this->nextNo(Carbon::parse($data['po_date'])),
                'status'     => PurchaseOrder::STATUS_SUBMITTED,
                'created_by' => $actorId,
            ]);

            $order->items()->createMany($computed['items']);

            return $order;
        });

        return ['allowed' => true, 'reason' => '', 'order' => $order->fresh('items')];
    }

    /** @return array{allowed: bool, reason: string} */
    public function update(PurchaseOrder $order, array $data): array
    {
        if (!$order->isSubmitted()) {
            return ['allowed' => false, 'reason' => 'Only a submitted purchase order can be edited.'];
        }

        $settings = PurchaseOrderSetting::current();

        if ($settings->requires_pr && empty($data['purchase_request_id'])) {
            return ['allowed' => false, 'reason' => 'A purchase request is required for every purchase order.'];
        }

        $computed = $this->compute($data['items'] ?? [], $settings);

        if ($computed['items'] === []) {
            return ['allowed' => false, 'reason' => 'Add at least one item.'];
        }

        DB::transaction(function () use ($order, $data, $computed) {
            $order->update($this->header($data, $computed['totals']));
            $order->items()->delete();
            $order->items()->createMany($computed['items']);
        });

        return ['allowed' => true, 'reason' => ''];
    }

    /** @return array{allowed: bool, reason: string} */
    public function approve(PurchaseOrder $order, int $actorId, bool $canManage): array
    {
        return $this->decide($order, $actorId, $canManage, PurchaseOrder::STATUS_APPROVED, null);
    }

    /** @return array{allowed: bool, reason: string} */
    public function reject(PurchaseOrder $order, int $actorId, bool $canManage, string $notes): array
    {
        return $this->decide($order, $actorId, $canManage, PurchaseOrder::STATUS_REJECTED, $notes);
    }

    /** Boleh bertindak: penyetuju yang dipilih pada dokumen, atau pemegang `.manage`. */
    public function canDecide(PurchaseOrder $order, int $actorId, bool $canManage): bool
    {
        return $order->isSubmitted()
            && !$order->trashed()
            && ($canManage || (int) $order->approver_id === $actorId);
    }

    /** @return array{allowed: bool, reason: string} */
    public function softDelete(PurchaseOrder $order, int $actorId, string $reason): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            return ['allowed' => false, 'reason' => 'Please state why this document is being deleted.'];
        }

        DB::transaction(function () use ($order, $actorId, $reason) {
            $order->update(['deleted_by' => $actorId, 'delete_reason' => $reason]);
            $order->delete();
        });

        Log::info('Purchase order deleted.', ['po_no' => $order->po_no, 'status' => $order->status, 'actor_id' => $actorId, 'reason' => $reason]);

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * PR yang sudah disetujui dan belum dipakai PO lain (kecuali PO yang sedang
     * diedit), untuk dropdown "PR No".
     *
     * @return Collection<int, PurchaseRequest>
     */
    public function availableRequests(?int $exceptOrderId = null): Collection
    {
        $used = PurchaseOrder::query()
            ->whereNotNull('purchase_request_id')
            ->when($exceptOrderId, fn ($q) => $q->where('id', '!=', $exceptOrderId))
            ->pluck('purchase_request_id');

        return PurchaseRequest::query()
            ->where('status', PurchaseRequest::STATUS_APPROVED)
            ->whereNotIn('id', $used)
            ->with('items')
            ->orderByDesc('request_date')
            ->get();
    }

    // ── internal ────────────────────────────────────────────────────────────

    /** @return array{allowed: bool, reason: string} */
    private function decide(PurchaseOrder $order, int $actorId, bool $canManage, string $status, ?string $notes): array
    {
        if (!$order->isSubmitted()) {
            return ['allowed' => false, 'reason' => 'This purchase order is already ' . $order->status . '.'];
        }

        if (!$this->canDecide($order, $actorId, $canManage)) {
            return ['allowed' => false, 'reason' => 'Only the approver chosen on this purchase order can decide it.'];
        }

        $order->update([
            'status'         => $status,
            'decided_by'     => $actorId,
            'decided_at'     => now(),
            'decision_notes' => $notes,
        ]);

        return ['allowed' => true, 'reason' => ''];
    }

    private function header(array $data, array $totals): array
    {
        return [
            'purchase_request_id' => $data['purchase_request_id'] ?: null,
            'po_date'             => $data['po_date'],
            'supplier_name'       => trim($data['supplier_name']),
            'transaction_no'      => $data['transaction_no'] ?? null,
            'invoice_no'          => $data['invoice_no'] ?? null,
            'invoice_date'        => $data['invoice_date'] ?: null,
            'requester_id'        => (int) $data['requester_id'],
            'accounting_id'       => $data['accounting_id'] ?: null,
            'cashier_id'          => $data['cashier_id'] ?: null,
            'approver_id'         => $data['approver_id'] ?: null,
            'delivered_date'      => $data['delivered_date'] ?: null,
            'notes'               => $data['notes'] ?? null,
            'subtotal'            => $totals['subtotal'],
            'discount_total'      => $totals['discount'],
            'vat_total'           => $totals['vat'],
            'withholding_total'   => $totals['withholding'],
            'grand_total'         => $totals['grand'],
        ];
    }

    /** Nomor berikutnya: 01/PO/10/2026 (urut per bulan; yang terhapus tidak dipakai ulang). */
    private function nextNo(Carbon $date): string
    {
        $suffix = sprintf('/PO/%s/%s', $date->format('m'), $date->format('Y'));

        $last = PurchaseOrder::withTrashed()
            ->where('po_no', 'like', '%' . $suffix)
            ->lockForUpdate()
            ->pluck('po_no')
            ->map(fn ($no) => (int) explode('/', $no)[0])
            ->max();

        return str_pad((string) (($last ?? 0) + 1), 2, '0', STR_PAD_LEFT) . $suffix;
    }
}
