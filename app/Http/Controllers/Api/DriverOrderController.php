<?php

namespace App\Http\Controllers\Api;

use App\Models\DeliveryOrder;
use App\Models\DeliveryTrack;
use Illuminate\Http\Request;

class DriverOrderController
{
    public function index()
    {
        if (auth()->user()->role !== 'driver') {
            return response()->json([
                'message' => 'Unauthorized. Driver access required.',
            ], 403);
        }

        $deliveryOrders = DeliveryOrder::where('driver_id', auth()->id())
            ->with(['order.orderItems.product', 'order.user'])
            ->latest()
            ->get();

        return response()->json([
            'delivery_orders' => $deliveryOrders,
        ]);
    }

    public function updateStatus(DeliveryOrder $deliveryOrder, Request $request)
    {
        if ($deliveryOrder->driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized. You are not assigned to this delivery.',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:assigned,on_the_way,arrived,completed,cancelled',
        ]);

        $deliveryOrder->update([
            'status' => $request->status,
            'completed_at' => $request->status === 'completed' ? now() : $deliveryOrder->completed_at,
        ]);

        if ($request->status === 'completed') {
            $deliveryOrder->order->update(['status' => 'completed']);
        } elseif ($request->status === 'on_the_way') {
            $deliveryOrder->order->update(['status' => 'on_delivery']);
        } elseif ($request->status === 'cancelled') {
            $deliveryOrder->order->update(['status' => 'cancelled']);
        }

        return response()->json([
            'message' => 'Delivery status updated successfully',
            'delivery_order' => $deliveryOrder->fresh()->load('order'),
        ]);
    }

    public function track(DeliveryOrder $deliveryOrder, Request $request)
    {
        if ($deliveryOrder->driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized. You are not assigned to this delivery.',
            ], 403);
        }

        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $track = DeliveryTrack::create([
            'delivery_order_id' => $deliveryOrder->id,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Location recorded successfully',
            'track' => $track,
        ]);
    }
}
