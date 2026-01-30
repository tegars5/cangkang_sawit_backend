<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Waybill;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $query = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.product', 'deliveryOrder', 'payment']);
        
        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        
        $orders = $query->latest()->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Create a new order and decrement product stock.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'destination_address' => 'required|string',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
            'payment_method' => 'required|in:cash,transfer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
            'shipping_cost' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // 2. Mulai Transaksi Database
        DB::beginTransaction();
        try {
            // Validasi Minimum Order (10 Ton) - Opsional jika masih diperlukan
            $totalQuantity = collect($request->items)->sum('quantity');
            if ($totalQuantity < 10000) { // Asumsi satuan Kg
                 // throw new \Exception('Minimum order adalah 10 Ton (10.000 Kg).');
            }
            // 3. Buat Order
            $order = Order::create([
                'user_id' => auth()->id(),
                'status' => 'pending', // Atau 'processed' tergantung flow
                'destination_address' => $request->destination_address,
                'destination_lat' => $request->destination_lat,
                'destination_lng' => $request->destination_lng,
                'payment_method' => $request->payment_method,
                'shipping_cost' => $request->shipping_cost,
                'total_amount' => $request->total_amount,
                'invoice_number' => 'INV/' . date('Ymd') . '/' . mt_rand(1000, 9999),
            ]);
            // 4. Proses Setiap Item & Kurangi Stok
            foreach ($request->items as $item) {
                // Lock product row for update (mencegah race condition)
                $product = Product::lockForUpdate()->find($item['product_id']);
                if (!$product) {
                    throw new \Exception("Produk dengan ID {$item['product_id']} tidak ditemukan.");
                }
                // Cek Stok Cukup
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok tidak cukup untuk produk: {$product->name}. Sisa stok: {$product->stock}");
                }
                // Kurangi Stok
                $product->decrement('stock', $item['quantity']);
                // Buat Order Item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $product->price * $item['quantity'],
                ]);
            }
            // 5. Commit Transaksi (Simpan Perubahan)
            DB::commit();
            return response()->json([
                'message' => 'Order berhasil dibuat dan stok berkurang.',
                'order' => $order->load('items.product'),
            ], 201);
        } catch (\Exception $e) {
            // Rollback jika ada error (stok kembali semula)
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat order: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Detail satu order
     */
    public function show(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(
            $order->load(['orderItems.product', 'deliveryOrder.driver', 'payment', 'waybill'])
        );
    }

    /**
     * Cancel order with refund logic
     */
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

        return DB::transaction(function () use ($order) {
            // 1. Check if payment exists and is paid
            $payment = $order->payment;
            
            if ($payment && $payment->status === 'paid') {
                // 2. Process refund via Tripay
                $tripayService = app(\App\Services\TripayService::class);
                $refundResult = $tripayService->requestRefund($payment);
                
                if ($refundResult['success']) {
                    $payment->update([
                        'status' => 'refunded',
                        'refunded_at' => now()
                    ]);
                }
            }
            
            // 3. Return stock to products
            foreach ($order->orderItems as $item) {
                $product = $item->product;
                $product->increment('stock', $item->quantity);
            }
            
            // 4. Cancel delivery if exists
            if ($order->deliveryOrder) {
                $order->deliveryOrder->update(['status' => 'cancelled']);
            }
            
            // 5. Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);
            
            // 6. Log activity
            \App\Services\ActivityLogger::logOrderCancelled($order, 'Cancelled by user');

            return response()->json([
                'message' => 'Order cancelled successfully' . ($payment && $payment->status === 'refunded' ? ' and refund processed' : ''),
                'order' => $order->fresh()->load(['orderItems.product', 'deliveryOrder', 'payment']),
            ]);
        });
    }

 /**
     * Tracking lokasi driver
     */
    public function tracking($id)
    {
        $user = auth()->user();

        // Load order with relations (User Request: Explicit fresh load)
        $order = Order::with(['deliveryOrder.driver'])->findOrFail($id);
        
        // Authorization: Allow order owner, admin, or assigned driver
        $isOrderOwner = $order->user_id === $user->id;
        $isAdmin = $user->role === 'admin';
        
        // Check if user is the assigned driver
        $isAssignedDriver = false;
        if ($user->role === 'driver') {
            $deliveryOrder = $order->deliveryOrder;
            $isAssignedDriver = $deliveryOrder && $deliveryOrder->driver_id === $user->id;
        }
        
        if (!$isOrderOwner && !$isAdmin && !$isAssignedDriver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 1. Fetch latest location from 'delivery_tracks' table (User Request Fix)
        $latestTrack = null;
        if ($order->deliveryOrder) {
            $latestTrack = \App\Models\DeliveryTrack::where('delivery_order_id', $order->deliveryOrder->id)
                ->latest('recorded_at')
                ->first();
        }

        $driverLat = $latestTrack ? (float) $latestTrack->lat : null;
        $driverLng = $latestTrack ? (float) $latestTrack->lng : null;
        $destLat = (float) $order->destination_lat;
        $destLng = (float) $order->destination_lng;

        // 2. Calculate Dynamic Distance & ETA
        $distanceKm = 0;
        $estimatedMinutes = 0;

        if ($driverLat && $driverLng) {
            // Calculate Straight Distance (Haversine)
            $distanceKm = $this->calculateDistance($driverLat, $driverLng, $destLat, $destLng);

            // Add 40% buffer for road curves
            $distanceKm = $distanceKm * 1.4;
            
            // Estimate time (Avg speed 40 km/h)
            $speedKmH = 40; 
            $estimatedMinutes = ($distanceKm / $speedKmH) * 60;
        } else {
            // Fallback to static data
            $distanceKm = (float) ($order->distance_km ?? 0);
            $estimatedMinutes = (int) ($order->estimated_minutes ?? 0);
        }

        return response()->json([
            'order_status' => $this->mapOrderStatus($order->status),
            
            'driver_location' => $latestTrack ? [
                'latitude' => $driverLat,
                'longitude' => $driverLng
            ] : null,
            
            'destination_location' => [
                'latitude' => $destLat,
                'longitude' => $destLng
            ],
            
            // FIX: Real-time values
            'distance_km' => round($distanceKm, 1),
            'estimated_minutes' => round($estimatedMinutes),
            
            'driver' => ($order->deliveryOrder && $order->deliveryOrder->driver) ? [
                'name' => $order->deliveryOrder->driver->name,
                'phone' => (string) ($order->deliveryOrder->driver->phone ?? '-'),
            ] : null,
        ]);
    }

    // ⬇️ ADD THIS HELPER FUNCTION INSIDE THE CLASS ⬇️
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Radius of earth in KM
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Helper status mapping (Gunakan Versi Indonesia agar bagus di UI Flutter)
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
    /**
     * Tampilkan Surat Jalan (Waybill)
     */
    public function showWaybill(Order $order)
    {
        $user = auth()->user();

        // Authorization: Allow order owner, admin, or assigned driver
        $isOrderOwner = $order->user_id === $user->id;
        $isAdmin = $user->role === 'admin';
        
        // Check if user is the assigned driver
        $isAssignedDriver = false;
        if ($user->role === 'driver') {
            $deliveryOrder = $order->deliveryOrder;
            $isAssignedDriver = $deliveryOrder && $deliveryOrder->driver_id === $user->id;
        }

        if (!$isOrderOwner && !$isAdmin && !$isAssignedDriver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $waybill = Waybill::where('order_id', $order->id)
            ->with(['order.orderItems.product', 'order.user', 'driver'])
            ->first();

        if (!$waybill) {
            return response()->json(['message' => 'Waybill not found.'], 404);
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

    /**
     * Upload foto terkait order (BARU)
     */
    public function uploadPhoto(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:5120', // Max 5MB
            'description' => 'nullable|string|max:255',
        ]);

        // Optimize and save image
        $image = $request->file('photo');
        $filename = 'order_' . $order->id . '_' . time() . '_' . uniqid() . '.jpg';
        
        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        $img = $manager->read($image->getPathname());
        $img->scale(width: 1200); // Larger size for order photos
        $encoded = $img->toJpeg(80);
        
        $path = 'order_photos/' . $filename;
        Storage::disk('public')->put($path, (string) $encoded);
        
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'order_id' => $order->id,
            'url' => $url,
            'path' => $path
        ], 201);
    }

    /**
     * List semua foto order (BARU)
     */
    public function photos(Order $order)
    {
        $user = auth()->user();

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $directory = 'order_photos';
        $files = Storage::disk('public')->files($directory);

        $orderPhotos = collect($files)->filter(function ($file) use ($order) {
            return str_contains($file, 'order_' . $order->id . '_');
        })->map(function ($file) {
            return [
                'filename' => basename($file),
                'url' => Storage::disk('public')->url($file),
                'size' => Storage::disk('public')->size($file),
            ];
        })->values(); // Reset keys array agar rapi di JSON

        return response()->json([
            'order_id' => $order->id,
            'photos' => $orderPhotos,
        ]);
    }
    /**
 * Endpoint untuk Driver mengupdate lokasi GPS
 */
public function updateDriverLocation(Request $request, $orderId)
{
    // 1. Validasi Input
    $request->validate([
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
    ]);

    // 2. Cari DeliveryOrder yang terhubung dengan Order ini
    $deliveryOrder = \App\Models\DeliveryOrder::where('order_id', $orderId)->first();

    if (!$deliveryOrder) {
        return response()->json([
            'success' => false, 
            'message' => 'Data pengiriman tidak ditemukan'
        ], 404);
    }

    // 3. Verify that the authenticated user is the assigned driver
    if ($deliveryOrder->driver_id !== auth()->id()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. You are not the assigned driver for this order.'
        ], 403);
    }

    // 4. Simpan koordinat baru ke tabel delivery_tracks
    $track = \App\Models\DeliveryTrack::create([
        'delivery_order_id' => $deliveryOrder->id,
        'lat' => $request->lat,
        'lng' => $request->lng,
        'recorded_at' => now(), 
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Lokasi berhasil diperbarui',
        'data' => [
            'lat' => $track->lat,
            'lng' => $track->lng,
            'time' => $track->recorded_at->format('H:i:s')
        ]
    ]);
}
}