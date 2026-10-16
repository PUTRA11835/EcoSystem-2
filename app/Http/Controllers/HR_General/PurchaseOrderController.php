<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Employee;
use App\Models\PurchaseOrder\PurchaseOrder;
use App\Models\PurchaseOrder\PurchaseOrderSetting;
use App\Models\PurchaseRequest\PurchaseRequestSetting;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Purchase Order — tab pada menu Purchase Management.
 *
 *   general.purchase-order          membuka halaman dan membaca dokumen
 *   general.purchase-order.create   membuat PO
 *   general.purchase-order.manage   mengubah / menghapus / memutuskan mewakili
 *
 * Keputusan (approve / reject) dipegang penyetuju yang DIPILIH pada dokumen.
 * Karyawan yang dipilih itu otomatis dapat membuka halaman ini (lihat
 * CheckMenuOrDesignatedApprover) tanpa diberi slug menu terpisah.
 */
class PurchaseOrderController extends Controller
{
    use HandlesRecruitmentTables;

    public function index(Request $request)
    {
        $filters = [
            'number'    => trim((string) $request->query('number', '')),
            'supplier'  => trim((string) $request->query('supplier', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to'   => trim((string) $request->query('date_to', '')),
            'status'    => (string) $request->query('status', 'all'),
        ];

        $actorId = (int) session('user.id');
        $perPage = $this->perPage($request);

        $orders = PurchaseOrder::query()
            ->with(['requester.basicData', 'purchaseRequest'])
            ->when($filters['number'] !== '', fn ($q) => $q->where('po_no', 'like', '%' . $filters['number'] . '%'))
            ->when($filters['supplier'] !== '', fn ($q) => $q->where('supplier_name', 'like', '%' . $filters['supplier'] . '%'))
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('po_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('po_date', '<=', $filters['date_to']))
            ->when(isset(PurchaseOrder::STATUS_LABELS[$filters['status']]), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $counts = PurchaseOrder::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('hr-general.purchase-order.index', [
            'orders'         => $orders,
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->except('status')->filter()->isNotEmpty() || $filters['status'] !== 'all',
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'counts'         => [
                'mine'      => PurchaseOrder::where('approver_id', $actorId)->where('status', PurchaseOrder::STATUS_SUBMITTED)->count(),
                'submitted' => (int) ($counts[PurchaseOrder::STATUS_SUBMITTED] ?? 0),
                'approved'  => (int) ($counts[PurchaseOrder::STATUS_APPROVED] ?? 0),
                'rejected'  => (int) ($counts[PurchaseOrder::STATUS_REJECTED] ?? 0),
            ],
            'canManage'      => $this->holds('general.purchase-order.manage'),
            'canCreate'      => $this->holds('general.purchase-order.create'),
        ]);
    }

    public function create(PurchaseOrderService $orders, ReimbursementService $employees)
    {
        $settings = PurchaseOrderSetting::current();

        return view('hr-general.purchase-order.form', $this->formData($orders, $employees, $settings, null) + [
            'mode'   => 'create',
            'order'  => null,
            'action' => route('general.purchase-order.store'),
        ]);
    }

    public function store(Request $request, PurchaseOrderService $orders)
    {
        $result = $orders->create($this->validated($request), (int) session('user.id'));

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.purchase-order.show', $result['order'])
            ->with('success', 'Purchase order ' . $result['order']->po_no . ' submitted.');
    }

    public function show(PurchaseOrder $purchaseOrder, PurchaseOrderService $orders)
    {
        $purchaseOrder->load([
            'items', 'purchaseRequest', 'requester.basicData', 'accounting.basicData',
            'cashier.basicData', 'approver.basicData', 'decider.basicData',
        ]);

        $actorId   = (int) session('user.id');
        $canManage = $this->holds('general.purchase-order.manage');

        return view('hr-general.purchase-order.show', [
            'order'      => $purchaseOrder,
            'canDecide'  => $orders->canDecide($purchaseOrder, $actorId, $canManage),
            'canEdit'    => $purchaseOrder->isSubmitted() && !$purchaseOrder->trashed()
                && ($canManage || (int) $purchaseOrder->created_by === $actorId),
            'canDelete'  => $canManage && !$purchaseOrder->trashed(),
            'taxLabels'  => collect(PurchaseOrderSetting::TAXES)->map(fn ($t) => $t[0])->all(),
        ]);
    }

    public function print(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'items', 'purchaseRequest', 'requester.basicData', 'accounting.basicData',
            'cashier.basicData', 'approver.basicData',
        ]);

        return view('hr-general.purchase-order.print', [
            'order'     => $purchaseOrder,
            'company'   => PurchaseOrderSetting::current()->company_name,
            'taxLabels' => collect(PurchaseOrderSetting::TAXES)->map(fn ($t) => $t[0])->all(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder, PurchaseOrderService $orders, ReimbursementService $employees)
    {
        if (!$this->canEdit($purchaseOrder)) {
            return redirect()->route('general.purchase-order.show', $purchaseOrder)
                ->with('error', 'This purchase order can no longer be edited.');
        }

        $purchaseOrder->load('items');

        return view('hr-general.purchase-order.form', $this->formData($orders, $employees, PurchaseOrderSetting::current(), $purchaseOrder) + [
            'mode'   => 'edit',
            'order'  => $purchaseOrder,
            'action' => route('general.purchase-order.update', $purchaseOrder),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $orders)
    {
        if (!$this->canEdit($purchaseOrder)) {
            return redirect()->route('general.purchase-order.show', $purchaseOrder)
                ->with('error', 'This purchase order can no longer be edited.');
        }

        $result = $orders->update($purchaseOrder, $this->validated($request));

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()->route('general.purchase-order.show', $purchaseOrder)
            ->with('success', 'Purchase order ' . $purchaseOrder->po_no . ' updated.');
    }

    public function approve(PurchaseOrder $purchaseOrder, PurchaseOrderService $orders)
    {
        $result = $orders->approve($purchaseOrder, (int) session('user.id'), $this->holds('general.purchase-order.manage'));

        return back()->with($result['allowed'] ? 'success' : 'error',
            $result['allowed'] ? 'Purchase order ' . $purchaseOrder->po_no . ' approved.' : $result['reason']);
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $orders)
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:255'],
        ], ['notes.required' => 'Please explain why the purchase order is rejected.']);

        $result = $orders->reject($purchaseOrder, (int) session('user.id'), $this->holds('general.purchase-order.manage'), $validated['notes']);

        return back()->with($result['allowed'] ? 'success' : 'error',
            $result['allowed'] ? 'Purchase order rejected.' : $result['reason']);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $orders)
    {
        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $result = $orders->softDelete($purchaseOrder, (int) session('user.id'), $validated['delete_reason']);

        return $result['allowed']
            ? redirect()->route('general.purchase-order.index')->with('success', 'Purchase order ' . $purchaseOrder->po_no . ' deleted.')
            : back()->with('error', $result['reason']);
    }

    /** Pengaturan PO — satu bagian pada tab Settings Purchase Management. */
    public function updateSettings(Request $request)
    {
        $rate = ['required', 'numeric', 'between:0,100'];

        $data = $request->validate([
            'company_name'           => ['required', 'string', 'max:150'],
            'vat_rate'               => $rate,
            'pph23_rate'             => $rate,
            'pph4_2_rate'            => $rate,
            'pp23_rate'              => $rate,
            'pph21_rate'             => $rate,
            'pph26_rate'             => $rate,
            'accounting_employee_id' => ['nullable', 'integer', 'exists:employee,employee_id'],
            'cashier_employee_id'    => ['nullable', 'integer', 'exists:employee,employee_id'],
        ]);

        $data['requires_pr'] = $request->boolean('requires_pr');
        $data['updated_by']  = session('user.id');

        PurchaseOrderSetting::current()->update($data);
        PurchaseOrderSetting::forgetCache();

        return back()->with('success', 'Purchase order settings saved.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function formData(PurchaseOrderService $orders, ReimbursementService $employees, PurchaseOrderSetting $settings, ?PurchaseOrder $order): array
    {
        return [
            'settings'    => $settings,
            'taxes'       => $settings->taxTable(),
            'people'      => $employees->employeeOptions(),
            'requests'    => $orders->availableRequests($order?->id),
            'units'       => PurchaseRequestSetting::current()->unitOptions(),
            'suppliers'   => PurchaseOrder::withTrashed()->distinct()->orderBy('supplier_name')->limit(300)->pluck('supplier_name'),
            'defaultAccountingId' => (int) $settings->accounting_employee_id,
            'defaultCashierId'    => (int) $settings->cashier_employee_id,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'po_date'             => ['required', 'date'],
            'supplier_name'       => ['required', 'string', 'max:150'],
            'transaction_no'      => ['nullable', 'string', 'max:100'],
            'invoice_no'          => ['nullable', 'string', 'max:100'],
            'invoice_date'        => ['nullable', 'date'],
            'requester_id'        => ['required', 'integer', 'exists:employee,employee_id'],
            'accounting_id'       => ['nullable', 'integer', 'exists:employee,employee_id'],
            'cashier_id'          => ['nullable', 'integer', 'exists:employee,employee_id'],
            'approver_id'         => ['required', 'integer', 'exists:employee,employee_id'],
            'delivered_date'      => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:255'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty'         => ['required', 'numeric', 'min:0.01'],
            'items.*.unit'        => ['required', 'string', 'max:20'],
            'items.*.currency'    => ['required', 'string', Rule::in(['IDR', 'USD', 'EUR', 'SGD'])],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.project_label' => ['nullable', 'string', 'max:150'],
            'items.*.period_from' => ['nullable', 'date'],
            'items.*.period_to'   => ['nullable', 'date'],
            'items.*.discount_type'  => ['nullable', Rule::in(['percent', 'amount'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_keys'    => ['nullable', 'array'],
            'items.*.tax_keys.*'  => [Rule::in(array_keys(PurchaseOrderSetting::TAXES))],
        ], [
            'approver_id.required' => 'Choose the approver of this purchase order.',
            'items.required'       => 'Add at least one item.',
        ]);
    }

    private function canEdit(PurchaseOrder $order): bool
    {
        return $order->isSubmitted() && !$order->trashed()
            && ($this->holds('general.purchase-order.manage') || (int) $order->created_by === (int) session('user.id'));
    }

    private function holds(string $slug): bool
    {
        $employee = Employee::where('employee_id', session('user.id'))->first();

        return $employee?->canAccessMenu($slug) ?? false;
    }
}
