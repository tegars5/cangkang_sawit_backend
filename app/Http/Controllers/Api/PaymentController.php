<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Payment;
use App\Services\TripayService;
use Illuminate\Http\Request;

class PaymentController
{
    protected $tripayService;

    public function __construct(TripayService $tripayService)
    {
        $this->tripayService = $tripayService;
    }

    public function pay(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->payment && $order->payment->status === 'paid') {
            return response()->json([
                'message' => 'Order already paid',
            ], 400);
        }

        $result = $this->tripayService->createTransaction($order);

        if ($result['success']) {
            return response()->json([
                'message' => 'Payment created successfully',
                'payment' => $result['payment'],
                'checkout_url' => $result['checkout_url'],
                'payment_instructions' => $result['payment_instructions'],
            ]);
        }

        return response()->json([
            'message' => $result['message'],
        ], 400);
    }

    public function callback(Request $request)
    {
        $callbackSignature = $request->server('HTTP_X_CALLBACK_SIGNATURE');
        $merchantRef = $request->merchant_ref;
        $amount = $request->amount;

        if (!$this->tripayService->verifyCallback($callbackSignature, $merchantRef, $amount)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::where('merchant_ref', $merchantRef)->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($request->status === 'PAID') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'raw_response' => $request->all(),
            ]);

            $payment->order->update(['status' => 'confirmed']);
        } elseif ($request->status === 'EXPIRED') {
            $payment->update([
                'status' => 'expired',
                'raw_response' => $request->all(),
            ]);
        } elseif ($request->status === 'FAILED') {
            $payment->update([
                'status' => 'failed',
                'raw_response' => $request->all(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
