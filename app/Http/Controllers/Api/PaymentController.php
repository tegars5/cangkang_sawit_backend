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
    $json = $request->getContent();
    $data = json_decode($json);

    if (!$data) return response()->json(['message' => 'Data Kosong'], 400);

    // Cari payment berdasarkan merchant_ref (ORD-xxx)
    $payment = \App\Models\Payment::where('merchant_ref', $data->merchant_ref)->first();

    if (!$payment) {
        \Log::error("Callback Gagal: Ref {$data->merchant_ref} tidak ada di DB.");
        return response()->json(['message' => 'Payment not found'], 404);
    }

    if ($data->status === 'PAID') {
        \DB::transaction(function () use ($payment) {
            // 1. Update Payment jadi PAID
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // 2. Update Order jadi PENDING (untuk diproses admin)
            if ($payment->order) {
                $payment->order->update(['status' => 'pending']);
            }
        });

        \Log::info("DATABASE BERHASIL UPDATE: " . $data->merchant_ref);
        return response()->json(['success' => true]);
    }

    return response()->json(['message' => 'Status is ' . $data->status]);
}
}