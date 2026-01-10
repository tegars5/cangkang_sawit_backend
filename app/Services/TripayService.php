<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class TripayService
{
    protected $merchantCode;
    protected $apiKey;
    protected $privateKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->merchantCode = config('tripay.merchant_code');
        $this->apiKey = config('tripay.api_key');
        $this->privateKey = config('tripay.private_key');
        $this->apiUrl = config('tripay.api_url');
    }

    public function createTransaction(Order $order)
    {
        $merchantRef = 'PAY-' . strtoupper(uniqid());
        $amount = (int) $order->total_amount;

        $signature = hash_hmac('sha256', $this->merchantCode . $merchantRef . $amount, $this->privateKey);

        $payload = [
            'method' => 'BRIVA',
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $order->user->name,
            'customer_email' => $order->user->email,
            'customer_phone' => $order->user->phone,
            'order_items' => $order->orderItems->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'price' => (int) $item->price,
                    'quantity' => $item->quantity,
                ];
            })->toArray(),
            'callback_url' => url('/api/payment/tripay/callback'),
            'return_url' => url('/'),
            'signature' => $signature,
        ];

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->post($this->apiUrl . '/transaction/create', $payload);

        $data = $response->json();

        if ($response->successful() && $data['success']) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'reference' => $data['data']['reference'],
                'merchant_ref' => $merchantRef,
                'amount' => $order->total_amount,
                'payment_method' => $payload['method'],
                'status' => 'unpaid',
                'expired_at' => now()->addHours(24),
                'raw_response' => $data,
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'checkout_url' => $data['data']['checkout_url'] ?? null,
                'payment_instructions' => $data['data'],
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? 'Failed to create transaction',
        ];
    }

    public function verifyCallback($callbackSignature, $merchantRef, $amount)
    {
        $signature = hash_hmac('sha256', $this->merchantCode . $merchantRef . $amount, $this->privateKey);
        return $signature === $callbackSignature;
    }
    
    /**
     * Request refund for a payment
     */
    public function requestRefund(Payment $payment)
    {
        try {
            // Check if payment is eligible for refund
            if ($payment->status !== 'paid') {
                return [
                    'success' => false,
                    'message' => 'Payment is not in paid status'
                ];
            }
            
            // Tripay refund API endpoint
            $endpoint = $this->apiUrl . '/transaction/refund';
            
            $payload = [
                'reference' => $payment->reference,
                'reason' => 'Order cancelled by customer'
            ];
            
            $signature = hash_hmac('sha256', json_encode($payload), $this->privateKey);
            
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'X-Signature' => $signature
                ])
                ->post($endpoint, $payload);
            
            if ($response->successful()) {
                $data = $response->json();
                
                \Log::info('Tripay refund successful', [
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference
                ]);
                
                return [
                    'success' => true,
                    'data' => $data
                ];
            }
            
            \Log::error('Tripay refund failed', [
                'payment_id' => $payment->id,
                'response' => $response->body()
            ]);
            
            return [
                'success' => false,
                'message' => 'Refund request failed'
            ];
            
        } catch (\Exception $e) {
            \Log::error('Tripay refund exception: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
