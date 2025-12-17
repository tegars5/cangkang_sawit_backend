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
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'destination_lat' => 'decimal:8',
        'destination_lng' => 'decimal:8',
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
        return $this->hasOne(DeliveryOrder::class);
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
