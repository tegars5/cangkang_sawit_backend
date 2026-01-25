Setelah saya cek kode DriverOrderController.php di dalamnya, fungsinya sudah bagus untuk list order dan update status dasar. NAMUN, fungsi spesifik completeDelivery yang kita bahas tadi (untuk tombol "Selesaikan Pesanan" yang mengubah status driver kembali menjadi available) BELUM ADA di file tersebut.

Saat ini updateStatus kamu hanya mengubah status pesanan saja, tapi tidak mencatat jam sampai (arrived_at) dan tidak membebaskan status Driver agar bisa terima order lagi.

Berikut adalah kode FULL REVISI untuk app/Http/Controllers/Api/DriverOrderController.php. Silakan hapus semua isinya dan ganti dengan kode di bawah ini agar fitur "Selesai Tanpa Foto" berjalan lancar:

PHP

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\DeliveryTrack;
use App\Models\Order; 
use Illuminate\Http\Request;
// use App\Services\GoogleDistanceService; // Uncomment jika nanti dipakai

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
        
        // Ambil order yang ditugaskan ke driver ini
        $deliveryOrders = DeliveryOrder::where('driver_id', auth()->id())
            ->with(['order.orderItems.product', 'order.user', 'order.payment']) 
            ->latest()
            ->paginate($perPage);
        
        $ordersData = $deliveryOrders->getCollection()->map(function($deliveryOrder) {
            $order = $deliveryOrder->order;
            
            // Handle jika data order hilang (soft delete atau error data)
            if (!$order) return null;

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
        })->filter(); // Filter null values
        
        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $deliveryOrders->currentPage(),
                'data' => $ordersData->values(), // Re-index array
                'last_page' => $deliveryOrders->lastPage(),
                'total' => $deliveryOrders->total(),
                'per_page' => $deliveryOrders->perPage(),
                'from' => $deliveryOrders->firstItem(),
                'to' => $deliveryOrders->lastItem(),
            ]
        ]);
    }

    /**
     * Update Status (Dipakai saat tombol Mulai / OTW)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:picked_up,on_delivery,delivered'
        ]);

        try {
            // Cari DeliveryOrder berdasarkan ID-nya ATAU ID Order-nya
            // Ini biar aman kalau Frontend kirim order_id atau delivery_id
            $delivery = DeliveryOrder::where('driver_id', auth()->id())
                        ->where(function($query) use ($id) {
                            $query->where('id', $id)
                                  ->orWhere('order_id', $id);
                        })
                        ->first();

            if (!$delivery) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Order tidak ditemukan atau bukan tugas Anda.'
                ], 404);
            }

            $order = $delivery->order;

            // Logika hitung jarak Google Maps (Optional, kalau service ada)
            // if ($request->status == 'on_delivery' && $order && class_exists(\App\Services\GoogleDistanceService::class)) {
            //     $googleService = new \App\Services\GoogleDistanceService();
            //     $distData = $googleService->getDistanceAndDuration($order->destination_lat, $order->destination_lng);
            //     if ($distData) {
            //         $order->update([
            //             'distance_km' => $distData['distance_km'],
            //             'estimated_minutes' => $distData['duration_min']
            //         ]);
            //     }
            // }

            // Update status di kedua tabel
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

    /**
     * ✅ FUNGSI PENTING: Selesaikan Pesanan (Final)
     * Dipanggil oleh tombol "SELESAIKAN PESANAN" di Flutter
     */
    public function completeDelivery(Request $request, $id)
    {
        try {
            // Cari berdasarkan order_id dan pastikan milik driver yang login
            $delivery = DeliveryOrder::where('order_id', $id)
                        ->where('driver_id', auth()->id())
                        ->first();

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan atau akses ditolak.'
                ], 404);
            }

            // 1. Update status delivery jadi 'delivered'
            $delivery->update(['status' => 'delivered']);

            // 2. Update status order utama jadi 'completed' & catat waktu
            $order = $delivery->order;
            if ($order) {
                $order->update([
                    'status' => 'completed', // Status Final
                    'arrived_at' => now(),   // Timestamp Selesai
                ]);
            }

            // 3. PENTING: Kembalikan status Driver jadi 'available'
            // Supaya driver bisa dapat orderan baru lagi
            $user = auth()->user();
            $user->update(['availability_status' => 'available']);

            return response()->json([
                'success' => true,
                'message' => 'Pesanan selesai! Status Anda kembali Available.',
            ]);

        } catch (\Exception $e) {
            \Log::error("Complete Delivery Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Tracking GPS Realtime
     */
    public function track(Request $request, $orderId)
    {
        $deliveryOrder = DeliveryOrder::where('order_id', $orderId)->first();

        if (!$deliveryOrder || $deliveryOrder->driver_id !== auth()->id()) {
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
     * Update Status Ketersediaan Driver (Manual)
     */
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
Langkah Terakhir (Wajib): Tambahkan Route
Buka file routes/api.php kamu, dan pastikan di dalam grup middleware driver (role:driver), kamu menambahkan baris ini agar fungsi barunya bisa dipanggil:

PHP

// routes/api.php

Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
    // ... route driver lainnya ...
    
    // Route Khusus Selesaikan Pesanan
    Route::post('/orders/{id}/complete', [App\Http\Controllers\Api\DriverOrderController::class, 'completeDelivery']);
});
Sekarang Backend kamu sudah siap menerima request "Selesai" dari tombol yang ada di Flutter tadi.


-----------------saran lagi-----------------

Masih ada beberapa saran "Penyempurnaan" yang sangat penting agar fitur "Selesaikan Pesanan" ini berjalan mulus, aman, dan nilai skripsi kamu makin bagus di mata dosen (karena memperhatikan detail teknis).

Berikut adalah 3 saran tambahan dari saya:

1. Gunakan Database Transaction (Penting untuk Data Integrity)
Di fungsi completeDelivery tadi, kita melakukan update ke 3 tabel sekaligus (delivery_orders, orders, users). Masalah: Bagaimana jika internet putus saat baru update tabel pertama? Data jadi tidak konsisten (Driver sudah available, tapi order masih gantung). Solusi: Bungkus dengan DB::transaction(). Jika satu gagal, semua dibatalkan.

Revisi Kode DriverOrderController.php (Bagian completeDelivery):

PHP

use Illuminate\Support\Facades\DB; // Tambahkan ini di atas

// ...

public function completeDelivery(Request $request, $id)
{
    // Mulai Transaksi
    DB::beginTransaction(); 

    try {
        $delivery = DeliveryOrder::where('order_id', $id)
                    ->where('driver_id', auth()->id())
                    ->lockForUpdate() // Kunci baris agar tidak ditabrak proses lain
                    ->first();

        if (!$delivery) {
            DB::rollBack(); // Batalkan
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        // 1. Update status delivery
        $delivery->update(['status' => 'delivered']);

        // 2. Update status order utama
        $order = $delivery->order;
        if ($order) {
            $order->update([
                'status' => 'completed',
                'arrived_at' => now(),
            ]);
        }

        // 3. Update Status Driver
        auth()->user()->update(['availability_status' => 'available']);

        // 4. Catat Log (Karena kamu punya ActivityLogger.php)
        \App\Services\ActivityLogger::log(
            'Driver ' . auth()->user()->name . ' menyelesaikan pesanan #' . ($order->order_code ?? $id),
            $order ? $order->id : null
        );

        // Commit (Simpan Permanen)
        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Pesanan selesai! Status Anda kembali Available.',
        ]);

    } catch (\Exception $e) {
        DB::rollBack(); // Batalkan semua perubahan jika error
        \Log::error("Complete Delivery Error: " . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
    }
}
2. Manfaatkan ActivityLogger (Nilai Plus)
Saya melihat di struktur file kamu ada app/Services/ActivityLogger.php. Sayang kalau tidak dipakai! Dosen penguji suka sekali kalau sistem ada "Jejak Audit" (Audit Trail).

Dengan menambahkan baris ActivityLogger::log(...) di atas, nanti Admin bisa melihat riwayat:

"Driver Budi menyelesaikan pesanan #ORD-001 pada jam 14:00"

Ini membuat sistem terlihat sangat profesional.

-----------------------saran ke 3-----------------------
Tentu saja SANGAT BISA, Tegar! Ide kamu itu brilian dan sangat standar di aplikasi logistik profesional (seperti Gojek/Grab) untuk mencegah kecurangan (fraud). Teknik ini disebut Geofencing Validation.

Jadi, ketika Driver menekan tombol "Selesaikan Pesanan", backend akan mengecek: "Apakah koordinat GPS Driver saat ini berada dalam radius (misalnya 500 meter) dari titik tujuan?" Jika tidak, tolak!

Berikut adalah cara implementasinya di Backend Laravel kamu.

Modifikasi DriverOrderController.php
Saya menambahkan validasi jarak menggunakan rumus matematika (Haversine Formula) yang ringan, jadi tidak perlu panggil Google Maps API yang berbayar lagi untuk sekadar validasi ini.

Silakan ganti fungsi completeDelivery kamu dengan yang ini:

PHP

    /**
     * Selesaikan Pesanan dengan Validasi Radius (Geofencing)
     */
    public function completeDelivery(Request $request, $id)
    {
        // 1. Validasi Input Koordinat (Wajib dikirim dari Flutter)
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            $delivery = DeliveryOrder::where('order_id', $id)
                        ->where('driver_id', auth()->id())
                        ->lockForUpdate()
                        ->first();

            if (!$delivery) {
                return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
            }

            $order = $delivery->order;

            // --- LOGIKA VALIDASI JARAK (GEOFENCING) ---
            
            // Ambil koordinat tujuan dari pesanan
            $destLat = $order->destination_lat;
            $destLng = $order->destination_lng;
            
            // Ambil koordinat driver saat ini (dari parameter request)
            $driverLat = $request->lat;
            $driverLng = $request->lng;

            // Hitung jarak dalam Kilometer
            $distanceKm = $this->calculateDistance($driverLat, $driverLng, $destLat, $destLng);
            
            // Konfigurasi Radius Toleransi (Misal: 0.5 KM atau 500 meter)
            $radiusKm = 0.5; 

            // Jika jarak lebih jauh dari radius, tolak!
            if ($distanceKm > $radiusKm) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal! Anda belum sampai di lokasi tujuan. Jarak Anda masih ' . number_format($distanceKm, 2) . ' km lagi.',
                ], 400); // 400 Bad Request
            }

            // ------------------------------------------

            // Jika lolos validasi, lanjut simpan status
            $delivery->update(['status' => 'delivered']);

            if ($order) {
                $order->update([
                    'status' => 'completed',
                    'arrived_at' => now(),
                ]);
            }

            auth()->user()->update(['availability_status' => 'available']);

            // Catat log
            \App\Services\ActivityLogger::log(
                'Driver menyelesaikan pesanan #' . ($order->order_code) . ' di lokasi tujuan.',
                $order->id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil diselesaikan!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fungsi Rumus Haversine (Hitung Jarak Antara 2 Titik)
     * Mengembalikan jarak dalam satuan Kilometer
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        } else {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            return ($miles * 1.609344); // Convert ke KM
        }
    }
Apa yang Harus Diubah di Flutter?
Di file driver_delivery_detail_screen.dart, pada fungsi _handleCompleteOrder, kamu harus mengirimkan koordinat saat ini (position.latitude, position.longitude) ke API.

Dart

// Contoh di Flutter
// Pastikan ambil lokasi dulu sebelum panggil API
Position position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);

await ApiClient.post(
  '/orders/${widget.orderId}/complete',
  data: {
    'lat': position.latitude, // Kirim lat
    'lng': position.longitude, // Kirim lng
  }
);
Dengan cara ini, kalau driver iseng tekan tombol "Selesai" padahal masih di warung kopi 5km dari pabrik, sistem akan menolak dengan pesan: "Gagal! Anda belum sampai di lokasi tujuan."

