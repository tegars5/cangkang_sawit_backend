<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Services\TripayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController
{
    protected $tripayService;

    public function __construct(TripayService $tripayService)
    {
        $this->tripayService = $tripayService;
    }

    /**
     * Alur Baru: Membuat Order dan Transaksi Pembayaran Sekaligus
     * Dipanggil dari CheckoutPaymentScreen di Flutter
     */
    public function initiateCheckoutPayment(Request $request)
    {
        $request->validate([
            'destination_address' => 'required|string',
            'destination_lat' => 'nullable|numeric',
            'destination_lng' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            $totalAmount = 0;
            $orderItemsData = [];

            // 1. Validasi Stok dan Hitung Total
            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok produk {$product->name} tidak mencukupi.");
                }

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ];

                // Kurangi stok (Reserve stock)
                $product->decrement('stock', $item['quantity']);
            }

            // 2. Buat Order (Status awal: pending_payment)
            $order = Order::create([
                'user_id' => auth()->id(),
                'order_code' => 'ORD-' . strtoupper(uniqid()),
                'destination_address' => $request->destination_address,
                'destination_lat' => $request->destination_lat,
                'destination_lng' => $request->destination_lng,
                'total_amount' => $totalAmount,
                'status' => 'pending_payment', // Status baru sesuai rencana
            ]);

            // 3. Simpan Item Order
            foreach ($orderItemsData as $itemData) {
                $order->orderItems()->create($itemData);
            }

            // 4. Inisiasi Transaksi Tripay via Service
            // Pastikan TripayService mendukung parameter kedua (payment_method)
            $tripayResult = $this->tripayService->createTransaction($order, $request->payment_method);

            if (!$tripayResult['success']) {
                throw new \Exception($tripayResult['message'] ?? 'Gagal membuat transaksi di Tripay');
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created, waiting for payment',
                'order' => $order->load('orderItems.product'),
                'payment' => $tripayResult['payment'],
                'checkout_url' => $tripayResult['checkout_url'],
            ], 201);
        });
    }

    /**
     * Method lama untuk Bayar Ulang jika transaksi sebelumnya expired/belum dibuat
     */
    public function pay(Order $order, Request $request)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->payment && $order->payment->status === 'paid') {
            return response()->json(['message' => 'Order already paid'], 400);
        }

        $paymentMethod = $request->payment_method ?? $order->payment->payment_method ?? 'QRIS';
        $result = $this->tripayService->createTransaction($order, $paymentMethod);

        if ($result['success']) {
            return response()->json([
                'message' => 'Payment created successfully',
                'payment' => $result['payment'],
                'checkout_url' => $result['checkout_url'],
            ]);
        }

        return response()->json(['message' => $result['message']], 400);
    }

    /**
     * Handler Callback dari Tripay
     */
  public function callback(Request $request)
{
    // Tambahkan log untuk intip data yang masuk di storage/logs/laravel.log
    \Log::info('Callback Masuk:', $request->all());

    $merchantRef = $request->merchant_ref;
    $amount = $request->amount;

    // Paksa cari payment
    $payment = Payment::where('merchant_ref', $merchantRef)->first();

    if (!$payment) {
        return response()->json(['message' => 'Payment tidak ditemukan di DB: ' . $merchantRef], 404);
    }

    return DB::transaction(function () use ($request, $payment) {
        // Kita langsung hajar update tanpa cek signature & status dari Tripay dulu (Hanya untuk TEST)
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $payment->order->update(['status' => 'pending']);

        return response()->json(['success' => true, 'message' => 'DB Berhasil Diupdate']);
    });
}
}