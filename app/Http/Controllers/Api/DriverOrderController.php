<?php

namespace App\Http\Controllers\Api;

use App\Models\DeliveryOrder;
use App\Models\DeliveryTrack;
use Illuminate\Http\Request;

class DriverOrderController
{
    public function index(Request $request)
    {
        if (auth()->user()->role !== 'driver') {
            return response()->json([
                'message' => 'Unauthorized. Driver access required.',
            ], 403);
        }
        
        $perPage = $request->input('per_page', 15);

        $deliveryOrders = DeliveryOrder::where('driver_id', auth()->id())
            ->with(['order.orderItems.product', 'order.user'])
            ->latest()
            ->paginate($perPage);

        return response()->json($deliveryOrders);
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
            
            // Update driver availability back to available
            auth()->user()->update(['availability_status' => 'available']);
            
            // Send notification to mitra
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendOrderNotification(
                $deliveryOrder->order,
                'Order Delivered',
                "Your order {$deliveryOrder->order->order_code} has been delivered successfully",
                'order.delivered'
            );
        } elseif ($request->status === 'on_the_way') {
            $deliveryOrder->order->update(['status' => 'on_delivery']);
            
            // Send notification to mitra
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendOrderNotification(
                $deliveryOrder->order,
                'Order On The Way',
                "Your order {$deliveryOrder->order->order_code} is on the way",
                'order.on_the_way'
            );
        } elseif ($request->status === 'cancelled') {
            $deliveryOrder->order->update(['status' => 'cancelled']);
            
            // Update driver availability back to available
            auth()->user()->update(['availability_status' => 'available']);
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
    
    /**
     * Update driver availability status
     */
    public function updateAvailability(Request $request)
    {
        if (auth()->user()->role !== 'driver') {
            return response()->json([
                'message' => 'Unauthorized. Driver access required.',
            ], 403);
        }
        
        $request->validate([
            'status' => 'required|in:available,busy,offline'
        ]);
        
        auth()->user()->update(['availability_status' => $request->status]);
        
        return response()->json([
            'message' => 'Availability status updated successfully',
            'status' => $request->status
        ]);
    }
}
