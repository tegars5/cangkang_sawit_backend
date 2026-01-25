<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTrack extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'lat',
        'lng',
        'status', 
        'recorded_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'recorded_at' => 'datetime',
    ];

    // Relasi balik ke DeliveryOrder
    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }
}
