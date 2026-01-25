<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_code',
        'total_amount',
        'status',
        'destination_address',
        'destination_lat',
        'destination_lng',
        'distance_km',
        'estimated_minutes',
        'cancelled_at',
        'arrived_at',
    ];

 protected $casts = [
    'total_amount' => 'double',    
    'destination_lat' => 'double', 
    'destination_lng' => 'double', 
    'distance_km' => 'double',      
    'estimated_minutes' => 'integer',
    'cancelled_at' => 'datetime',
    'arrived_at' => 'datetime',
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryOrder()
    {
        return $this->hasOne(DeliveryOrder::class, 'order_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function waybill()
    {
        return $this->hasOne(Waybill::class);
    }
}
