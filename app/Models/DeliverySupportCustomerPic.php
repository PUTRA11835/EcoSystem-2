<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliverySupportCustomerPic extends Model
{
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
