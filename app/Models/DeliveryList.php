<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryList extends Model
{
    use HasFactory;

    protected $table = 'delivery_list';

    protected $fillable = [];

    public function project()
    {
        return $this->hasOne(DeliveryProject::class, 'id_delivery_list');
    }

    public function support()
    {
        return $this->hasOne(DeliverySupport::class, 'id_delivery_list');
    }
}
