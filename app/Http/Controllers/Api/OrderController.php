<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Waybill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController
{
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.product', 'deliveryOrder', 'payment'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'destination_address' => 'required|string',
            'destination_lat' => 'nullable|numeric',
            'destination_lng' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_code' => 'ORD-' . strtoupper(uniqid()),
                'destination_address' => $request->destination_address,
                'destination_lat' => $request->destination_lat,
                'destination_lng' => $request->destination_lng,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($request->items as $item) {
                $product = \App\Models\Product::findOrFail($item['product_id']);
                $subtotal = $product->price * $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            $order->update(['total_amount' => $totalAmount]);

            return response()->json($order->load('orderItems.product'), 201);
        });
    }

    public function show(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(
            $order->load(['orderItems.product', 'deliveryOrder.driver', 'payment', 'waybill'])
        );
    }

    public function cancel(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (in_array($order->status, ['on_delivery', 'completed'])) {
            return response()->json([
                'message' => 'Cannot cancel order that is already on delivery or completed',
            ], 400);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order,
        ]);
    }

    public function tracking(Order $order)
    {
        $user = auth()->user();

        // Authorization check
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. You do not have access to this order.',
            ], 403);
        }

        // Load delivery order with driver relationship
        $deliveryOrder = $order->deliveryOrder()->with('driver')->first();

        // Initialize response structure
        $response = [
            'driver_location' => null,
            'order_status' => $this->mapOrderStatus($order->status),
            'distance_km' => $order->distance_km ? (float) $order->distance_km : null,
            'estimated_minutes' => $order->estimated_minutes,
            'driver' => null,
        ];

        // If driver is assigned, get driver info and location
        if ($deliveryOrder && $deliveryOrder->driver) {
            // Get latest driver location from delivery tracks
            $lastLocation = $deliveryOrder->deliveryTracks()
                ->orderBy('recorded_at', 'desc')
                ->first();

            if ($lastLocation) {
                $response['driver_location'] = [
                    'latitude' => (float) $lastLocation->lat,
                    'longitude' => (float) $lastLocation->lng,
                ];
            }

            $response['driver'] = [
                'name' => $deliveryOrder->driver->name,
                'phone' => $deliveryOrder->driver->phone ?? null,
            ];
        }

        return response()->json($response);
    }

    /**
     * Map order status to tracking-friendly status
     */
    private function mapOrderStatus($status)
    {
        $statusMap = [
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'on_delivery' => 'on_the_way',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
        ];

        return $statusMap[$status] ?? $status;
    }

    public function showWaybill(Order $order)
    {
        $user = auth()->user();

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. You do not have access to this order.',
            ], 403);
        }

        $waybill = Waybill::where('order_id', $order->id)
            ->with(['order.orderItems.product', 'order.user', 'driver'])
            ->first();

        if (!$waybill) {
            return response()->json([
                'message' => 'Waybill not found.',
            ], 404);
        }

        return response()->json([
            'waybill' => $waybill,
            'order' => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'destination_address' => $order->destination_address,
            ],
            'items' => $waybill->order->orderItems->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }),
            'driver' => $waybill->driver ? [
                'id' => $waybill->driver->id,
                'name' => $waybill->driver->name,
                'email' => $waybill->driver->email,
            ] : null,
            'mitra' => [
                'id' => $waybill->order->user->id,
                'name' => $waybill->order->user->name,
                'email' => $waybill->order->user->email,
            ],
        ]);
    }
}
