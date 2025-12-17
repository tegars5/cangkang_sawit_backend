<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\DeliveryOrder;
use App\Models\User;
use App\Models\Waybill;
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

    public function createWaybill(Order $order, Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $deliveryOrder = $order->deliveryOrder()->with('driver')->first();

        if (!$deliveryOrder) {
            return response()->json([
                'message' => 'No delivery assigned for this order.',
            ], 400);
        }

        // Generate unique waybill number: WB-YYYYMMDD-XXXX
        $waybillNumber = 'WB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        // Check if waybill already exists, if yes keep the same number
        $existingWaybill = Waybill::where('order_id', $order->id)->first();
        if ($existingWaybill) {
            $waybillNumber = $existingWaybill->waybill_number;
        }

        $waybill = Waybill::updateOrCreate(
            ['order_id' => $order->id],
            [
                'driver_id' => $deliveryOrder->driver_id,
                'waybill_number' => $waybillNumber,
                'notes' => $request->notes,
            ]
        );

        return response()->json([
            'message' => $existingWaybill ? 'Waybill updated successfully' : 'Waybill created successfully',
            'waybill' => $waybill->load(['order.orderItems.product', 'order.user', 'driver']),
        ]);
    }
}
