<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Waybill;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf; 

class WaybillController
{
    /**
     * Download Waybill as PDF
     * 
     * Installation:
     * composer require barryvdh/laravel-dompdf
     * 
     * Then uncomment the Pdf facade import above
     */
    public function downloadWaybillPdf(Order $order)
    {
        $user = auth()->user();

        // Authorization check
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. You do not have access to this order.',
            ], 403);
        }

        // Get waybill with relations
        $waybill = Waybill::where('order_id', $order->id)
            ->with(['order.orderItems.product', 'order.user', 'driver'])
            ->first();

        if (!$waybill) {
            return response()->json([
                'message' => 'Waybill not found.',
            ], 404);
        }

        // Prepare data for PDF
        $data = [
            'waybill' => $waybill,
            'order' => $order,
            'items' => $waybill->order->orderItems,
            'driver' => $waybill->driver,
            'mitra' => $waybill->order->user,
        ];

        // Generate PDF
        // Uncomment after installing dompdf:
        $pdf = Pdf::loadView('waybill', $data);
        return $pdf->download('waybill-' . $waybill->waybill_number . '.pdf');

        // Temporary response (remove after implementing PDF)
        // return response()->json([
        //     'message' => 'PDF generation not yet implemented. Install barryvdh/laravel-dompdf first.',
        //     'data' => $data,
        // ]);
    }
}
