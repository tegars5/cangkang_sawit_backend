<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DeliveryOrder;
use App\Models\User;
use App\Models\Waybill;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminOrderController extends Controller
{
    /**
     * Menampilkan semua pesanan untuk Admin dengan paginasi
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

    /**
     * Menyetujui pesanan (Approved)
     */
    public function approve(Order $order, Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending orders can be approved.',
            ], 400);
        }

        $order->update([
            'status' => 'confirmed',
        ]);
        
        // Kirim notifikasi jika service tersedia
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendOrderNotification(
                $order,
                'Order Approved',
                "Your order {$order->order_code} has been approved by admin",
                'order.approved'
            );
        } catch (\Exception $e) {
            \Log::error("Notification failed: " . $e->getMessage());
        }
        
        return response()->json([
            'message' => 'Order approved successfully',
            'order'   => $order->load(['orderItems.product', 'deliveryOrder.driver', 'user']),
        ]);
    }

    /**
     * ✅ FIXED: Menampilkan detail pesanan dengan data Driver lengkap
     */
    public function show(Order $order)
    {
        try {
            // Memuat semua relasi termasuk deliveryOrder dan driver-nya
            $order->load([
                'user', 
                'orderItems.product', 
                'payment',
                'deliveryOrder.driver', // Kunci agar nama driver tidak "null" di Admin
                'waybill'               // Memuat data surat jalan
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

    /**
     * ✅ FIXED: Menugaskan Driver dan membuat jatah order (DeliveryOrder)
     */
    public function assignDriver(Request $request, $id)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
            'waybill_pdf' => 'required|mimes:pdf|max:5120',
        ]);

        try {
            $order = Order::findOrFail($id);

            if ($request->hasFile('waybill_pdf')) {
                $file = $request->file('waybill_pdf');
                $fileName = 'waybill_' . $order->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('waybills', $fileName, 'public');

                // 1. Simpan ke tabel Waybills
                Waybill::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'driver_id' => $request->driver_id,
                        'waybill_number' => 'WB-' . strtoupper(Str::random(10)),
                        'pdf_path' => $fileName, 
                    ]
                );

                // 2. Simpan ke tabel DeliveryOrders (Jatah Order Driver)
                DeliveryOrder::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'driver_id' => $request->driver_id,
                        'status' => 'assigned',
                        'waybill_pdf' => $fileName,
                        'assigned_at' => now(),
                    ]
                );

                // 3. Update Status Order Utama
                $order->update(['status' => 'on_delivery']);

                // 4. Update Status Ketersediaan Driver
                User::where('id', $request->driver_id)->update(['availability_status' => 'busy']);

                return response()->json([
                    'success' => true,
                    'message' => 'Driver assigned successfully',
                    'data' => $order->load(['deliveryOrder.driver', 'waybill'])
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign driver: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Membuat atau memperbarui data Surat Jalan (Waybill)
     */
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

        $existingWaybill = Waybill::where('order_id', $order->id)->first();
        $waybillNumber = $existingWaybill ? $existingWaybill->waybill_number : 'WB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

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