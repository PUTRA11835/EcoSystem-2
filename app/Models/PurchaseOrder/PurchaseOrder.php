<?php

namespace App\Models\PurchaseOrder;

use App\Models\Employee;
use App\Models\PurchaseRequest\PurchaseRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_no', 'purchase_request_id', 'po_date', 'supplier_name',
        'transaction_no', 'invoice_no', 'invoice_date',
        'requester_id', 'accounting_id', 'cashier_id', 'approver_id',
        'delivered_date', 'notes',
        'subtotal', 'discount_total', 'vat_total', 'withholding_total', 'grand_total',
        'status', 'decided_by', 'decided_at', 'decision_notes',
        'created_by', 'deleted_by', 'delete_reason',
    ];

    protected $casts = [
        'po_date'           => 'date',
        'invoice_date'      => 'date',
        'delivered_date'    => 'date',
        'decided_at'        => 'datetime',
        'subtotal'          => 'float',
        'discount_total'    => 'float',
        'vat_total'         => 'float',
        'withholding_total' => 'float',
        'grand_total'       => 'float',
    ];

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_APPROVED  => 'Approved',
        self::STATUS_REJECTED  => 'Rejected',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_no');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function requester()
    {
        return $this->belongsTo(Employee::class, 'requester_id', 'employee_id');
    }

    public function accounting()
    {
        return $this->belongsTo(Employee::class, 'accounting_id', 'employee_id');
    }

    public function cashier()
    {
        return $this->belongsTo(Employee::class, 'cashier_id', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_id', 'employee_id');
    }

    public function decider()
    {
        return $this->belongsTo(Employee::class, 'decided_by', 'employee_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public static function money($amount): string
    {
        return 'Rp ' . number_format((float) $amount, 2, ',', '.');
    }
}
