<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\DeliveryOrder;
use App\Models\User;
use App\Models\Waybill;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
public function show(Order $order)
{
    try {
        $order->load([
            'user', 
            'orderItems.product', 
            'payment'
        ]);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal memuat detail pesanan: ' . $e->getMessage()
        ], 500);
    }
}
public function assignDriver(Request $request, $id)
{
    // 1. Validasi Input: Pastikan driver ada di tabel users dan file adalah PDF
    $request->validate([
        'driver_id' => 'required|exists:users,id',
        'waybill_pdf' => 'required|mimes:pdf|max:5120', // Maksimal 5MB
    ]);

    try {
        $order = Order::findOrFail($id);

        // 2. Proses Simpan File PDF
        if ($request->hasFile('waybill_pdf')) {
            $file = $request->file('waybill_pdf');
            
            // Nama file unik: waybill_IDORDER_WAKTU.pdf
            $fileName = 'waybill_' . $order->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Simpan ke storage/app/public/waybills
            $path = $file->storeAs('waybills', $fileName, 'public');

            // 3. Simpan/Update data ke tabel waybills
            // Kolom pdf_path diisi dengan $fileName sesuai struktur phpMyAdmin tadi
            Waybill::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'driver_id' => $request->driver_id,
                    'waybill_number' => 'WB-' . strtoupper(Str::random(10)),
                    'pdf_path' => $fileName, 
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            // 4. Update Status Order menjadi 'on_delivery' (dalam perjalanan)
            $order->update(['status' => 'on_delivery']);

            // 5. Update Status Driver menjadi 'busy' (sedang sibuk)
            User::where('id', $request->driver_id)->update(['availability_status' => 'busy']);

            return response()->json([
                'success' => true,
                'message' => 'Driver assigned and waybill uploaded successfully',
                'data' => $order->load('deliveryOrder.driver', 'waybill')
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to assign driver: ' . $e->getMessage()
        ], 500);
    }
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
