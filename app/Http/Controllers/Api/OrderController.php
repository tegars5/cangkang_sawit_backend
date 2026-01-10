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
     * Simpan order baru
     */
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
                $product = Product::findOrFail($item['product_id']);
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
    public function tracking(Order $order)
    {
        $user = auth()->user();

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $deliveryOrder = $order->deliveryOrder()->with('driver')->first();

        $response = [
            'driver_location' => null,
            'order_status' => $this->mapOrderStatus($order->status),
            'distance_km' => $order->distance_km ? (float) $order->distance_km : null,
            'estimated_minutes' => $order->estimated_minutes,
            'driver' => null,
        ];

        if ($deliveryOrder && $deliveryOrder->driver) {
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
     * Tampilkan Surat Jalan (Waybill)
     */
    public function showWaybill(Order $order)
    {
        $user = auth()->user();

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
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
     * Helper status mapping
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
}