<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\DeliveryOrder;
use App\Models\User;
use Illuminate\Http\Request;

class AdminOrderController
{
    public function assignDriver(Order $order, Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $request->validate([
            'driver_id' => 'required|exists:users,id',
        ]);

        $driver = User::findOrFail($request->driver_id);

        if ($driver->role !== 'driver') {
            return response()->json([
                'message' => 'Selected user is not a driver.',
            ], 400);
        }

        $deliveryOrder = DeliveryOrder::updateOrCreate(
            ['order_id' => $order->id],
            [
                'driver_id' => $request->driver_id,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]
        );

        $order->update(['status' => 'on_delivery']);

        return response()->json([
            'message' => 'Driver assigned successfully',
            'order' => $order->load(['orderItems.product', 'deliveryOrder.driver', 'user']),
            'delivery_order' => $deliveryOrder->load('driver'),
        ]);
    }
}
