<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\DeliveryOrder;
use App\Models\User;
use App\Models\Waybill;
use Illuminate\Http\Request;

class AdminOrderController
{
    /**
     * List all orders for admin with pagination
     */
    public function index(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }
        
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        
        $query = Order::with(['orderItems.product', 'deliveryOrder.driver', 'user', 'payment']);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $orders = $query->latest()->paginate($perPage);
        
        return response()->json($orders);
    }
    
    public function approve(Order $order, Request $request)
{
    // Hanya admin
    if (auth()->user()->role !== 'admin') {
        return response()->json([
            'message' => 'Unauthorized. Admin access required.',
        ], 403);
    }

    // Hanya boleh approve order dengan status pending
    if ($order->status !== 'pending') {
        return response()->json([
            'message' => 'Only pending orders can be approved.',
        ], 400);
    }

    // Ubah status ke confirmed (atau nama status yang kamu pakai)
    $order->update([
        'status' => 'confirmed',
    ]);
    
    // Send notification to mitra
    $notificationService = app(\App\Services\NotificationService::class);
    $notificationService->sendOrderNotification(
        $order,
        'Order Approved',
        "Your order {$order->order_code} has been approved by admin",
        'order.approved'
    );
    
    // Log activity
    \App\Services\ActivityLogger::logOrderApproved($order);

    return response()->json([
        'message' => 'Order approved successfully',
        'order'   => $order->load(['orderItems.product', 'deliveryOrder.driver', 'user']),
    ]);
}
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
        
        // Update driver availability to busy
        $driver->update(['availability_status' => 'busy']);
        
        // Send notifications
        $notificationService = app(\App\Services\NotificationService::class);
        
        // Notify mitra
        $notificationService->sendOrderNotification(
            $order,
            'Driver Assigned',
            "Driver {$driver->name} has been assigned to your order",
            'driver.assigned'
        );
        
        // Notify driver
        $notificationService->sendDriverNotification(
            $driver->id,
            'New Delivery Assignment',
            "You have been assigned to deliver order {$order->order_code}",
            ['delivery_order_id' => $deliveryOrder->id, 'order_code' => $order->order_code]
        );
        
        // Log activity
        \App\Services\ActivityLogger::logDriverAssigned($order, $driver);

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
