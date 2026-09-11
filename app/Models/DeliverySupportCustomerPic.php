<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliverySupportCustomerPic extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_customer_pics';

    protected $fillable = [
        'delivery_support_id',
        'contact_id',
    ];

    public function deliverySupport()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function contact()
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id', 'contact_id');
    }
}
