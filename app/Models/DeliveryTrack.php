<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTrack extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'lat',
        'lng',
        'recorded_at',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'recorded_at' => 'datetime',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }
}
