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
            if (!$order) return null;

            // --- ✅ 1. PASTIKAN BAGIAN INI ADA ---
            // Generate Signed URL valid selama 60 menit
            $waybillUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'api.waybill.generate', 
                now()->addMinutes(60),
                ['id' => $order->id]
            );
            // ------------------------------------

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

                'waybill_url' => $waybillUrl, 
                'has_waybill' => in_array($order->status, ['picked_up', 'on_delivery', 'completed', 'delivered']),
                
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
                    'waybill_pdf' => $deliveryOrder->waybill_pdf, // ✅ Added for fallback
                    'assigned_at' => $deliveryOrder->assigned_at,
                    'created_at' => $deliveryOrder->created_at,
                ],
            ];
        })->filter();
        
        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $deliveryOrders->currentPage(),
                'data' => $ordersData->values(),
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

    public function track(Request $request, $id)
    {
        // Cari delivery order berdasarkan order_id
        $deliveryOrder = DeliveryOrder::where('order_id', $id)->firstOrFail();

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
    
    /**
     * Complete Delivery - Selesaikan Pesanan dengan Validasi Radius (Geofencing)
     * Called when driver clicks "SELESAIKAN PESANAN" button
     * Validates that driver is within acceptable radius of destination
     */
    public function completeDelivery(Request $request, $id)
    {
        // 1. Validate driver's current location (required from Flutter)
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        // Start database transaction for data integrity
        \DB::beginTransaction();
        
        try {
            // Find delivery order by order_id and ensure it belongs to authenticated driver
            $delivery = DeliveryOrder::where('order_id', $id)
                        ->where('driver_id', auth()->id())
                        ->lockForUpdate() // Lock row to prevent concurrent updates
                        ->first();

            if (!$delivery) {
                \DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan atau akses ditolak.'
                ], 404);
            }

            $order = $delivery->order;

            // --- GEOFENCING VALIDATION ---
            
            // Get destination coordinates from order
            $destLat = $order->destination_lat;
            $destLng = $order->destination_lng;
            
            // Get driver's current coordinates from request
            $driverLat = $request->lat;
            $driverLng = $request->lng;

            // Calculate distance in kilometers using Haversine formula
            $distanceKm = $this->calculateDistance($driverLat, $driverLng, $destLat, $destLng);
            
            // Configuration: Tolerance radius (0.5 KM = 500 meters)
            $radiusKm = 0.5; 

            // If distance exceeds radius, reject completion
            if ($distanceKm > $radiusKm) {
                \DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal! Anda belum sampai di lokasi tujuan. Jarak Anda masih ' . number_format($distanceKm, 2) . ' km lagi.',
                    'distance_km' => round($distanceKm, 2),
                    'required_radius_km' => $radiusKm,
                ], 400); // 400 Bad Request
            }

            // ------------------------------------------

            // If validation passes, proceed with completion
            
            // 1. Update delivery status to 'delivered'
            $delivery->update(['status' => 'delivered']);

            // 2. Update order status to 'completed' and record arrival time
            if ($order) {
                $order->update([
                    'status' => 'completed',
                    'arrived_at' => now(),
                ]);
            }

            // 3. IMPORTANT: Set driver availability back to 'available'
            // So driver can receive new orders
            $user = auth()->user();
            $user->update(['availability_status' => 'available']);

            // 4. Log activity for audit trail with location info
            \App\Services\ActivityLogger::logOrderCompleted($order, $user);

            // Commit all changes
            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil diselesaikan!',
                'distance_from_destination' => round($distanceKm, 2) . ' km',
            ]);

        } catch (\Exception $e) {
            // Rollback all changes if error occurs
            \DB::rollBack();
            \Log::error("Complete Delivery Error: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Terjadi kesalahan sistem.'
            ], 500);
        }
    }
    
    /**
     * Calculate distance between two GPS coordinates using Haversine Formula
     * Returns distance in kilometers
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lon1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lon2 Longitude of point 2
     * @return float Distance in kilometers
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        // If coordinates are identical, distance is 0
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        }
        
        // Haversine formula
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) 
                + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        
        // Convert miles to kilometers
        return ($miles * 1.609344);
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