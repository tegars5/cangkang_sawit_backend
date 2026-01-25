<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\DeliveryTrack;
use App\Services\GoogleDistanceService;
use Illuminate\Http\Request;

class DriverOrderController extends Controller
{
    /**
     * List order untuk Driver
     */
    public function index(Request $request)
    {
        if (auth()->user()->role !== 'driver') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $perPage = $request->input('per_page', 15);
        
        $deliveryOrders = DeliveryOrder::where('driver_id', auth()->id())
            ->with(['order.orderItems.product', 'order.user', 'order.payment']) 
            ->latest()
            ->paginate($perPage);
        
        $ordersData = $deliveryOrders->getCollection()->map(function($deliveryOrder) {
            $order = $deliveryOrder->order;
            
            return [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'user_id' => $order->user_id,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'destination_address' => $order->destination_address,
                'destination_lat' => $order->destination_lat,
                'destination_lng' => $order->destination_lng,
                'distance_km' => $order->distance_km,
                'estimated_minutes' => $order->estimated_minutes,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ] : null,
                
                'order_items' => $order->orderItems ? $order->orderItems->map(function($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'price' => $item->product->price,
                            'category' => $item->product->category,
                        ] : null,
                    ];
                }) : [],
                
                'payment' => $order->payment ? [
                    'id' => $order->payment->id,
                    'status' => $order->payment->status,
                    'payment_method' => $order->payment->payment_method,
                    'amount' => $order->payment->amount,
                ] : null,
                
                'delivery_order' => [
                    'id' => $deliveryOrder->id,
                    'driver_id' => $deliveryOrder->driver_id,
                    'status' => $deliveryOrder->status,
                    'assigned_at' => $deliveryOrder->assigned_at,
                    'created_at' => $deliveryOrder->created_at,
                ],
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $deliveryOrders->currentPage(),
                'data' => $ordersData,
                'last_page' => $deliveryOrders->lastPage(),
                'total' => $deliveryOrders->total(),
                'per_page' => $deliveryOrders->perPage(),
                'from' => $deliveryOrders->firstItem(),
                'to' => $deliveryOrders->lastItem(),
            ]
        ]);
    }

    /**
     * ✅ FITUR BARU: Update Status & Hitung Jarak Otomatis
     * Ini yang membuat status berubah ke 'on_delivery' dan memicu GPS di Flutter
     */
public function updateStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:picked_up,on_delivery,delivered'
    ]);

    try {
        // Cari delivery_order. Kita coba cari lewat ID delivery_order dulu, 
        // kalau gagal kita cari lewat order_id (supaya cocok dengan yang dikirim Flutter)
        $delivery = \App\Models\DeliveryOrder::where('id', $id)
                    ->orWhere('order_id', $id)
                    ->first();

        if (!$delivery) {
            return response()->json([
                'success' => false, 
                'message' => 'Data pengiriman tidak ditemukan untuk ID: ' . $id
            ], 404);
        }

        $order = $delivery->order;

        // 1. Hitung Jarak Otomatis jika status 'on_delivery'
        if ($request->status == 'on_delivery' && $order) {
            $googleService = new \App\Services\GoogleDistanceService();
            $distData = $googleService->getDistanceAndDuration($order->destination_lat, $order->destination_lng);
            if ($distData) {
                $order->update([
                    'distance_km' => $distData['distance_km'],
                    'estimated_minutes' => $distData['duration_min']
                ]);
            }
        }

        // 2. UPDATE STATUS DI KEDUA TABEL (PENTING!)
        $delivery->update(['status' => $request->status]);
        
        if ($order) {
            $order->update(['status' => $request->status]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diperbarui ke ' . $request->status,
            'current_status' => $request->status
        ]);

    } catch (\Exception $e) {
        \Log::error("Update Status Error ID $id: " . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

    public function track(Request $request, $orderId)
    {
        // Cari delivery order berdasarkan order_id
        $deliveryOrder = DeliveryOrder::where('order_id', $orderId)->firstOrFail();

        if ($deliveryOrder->driver_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
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
            'success' => true,
            'message' => 'Location recorded successfully',
            'track' => $track,
        ]);
    }
    
    public function updateAvailability(Request $request)
    {
        $request->validate([
            'status' => 'required|in:available,busy,offline'
        ]);
        
        auth()->user()->update(['availability_status' => $request->status]);
        
        return response()->json([
            'success' => true,
            'message' => 'Availability status updated successfully',
            'status' => $request->status
        ]);
    }
}