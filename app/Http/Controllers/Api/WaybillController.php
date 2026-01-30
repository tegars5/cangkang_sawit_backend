<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Waybill;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class WaybillController extends Controller
{
    /**
     * Generate waybill PDF for driver (Download)
     * Route: GET /api/waybill/{id}/download
     * Parameter: $id = order_id
     */
    public function generate($id)
    {
        // Load order with all necessary relations
        $order = Order::with([
            'user',
            'orderItems.product',
            'deliveryOrder.driver',
            'waybill'
        ])->findOrFail($id);
        
        $waybill = $order->waybill;
        
        if (!$waybill) {
            return response()->json([
                'message' => 'Waybill belum dibuat untuk order ini.'
            ], 404);
        }
        
        // Generate PDF on-the-fly
        $pdf = $this->generatePdf($order, $waybill);
        
        // Return as download
        return $pdf->download('waybill-' . $order->order_code . '.pdf');
    }
    
    /**
     * Download waybill PDF for admin/user (Preview inline)
     * Route: GET /api/orders/{order}/waybill/pdf
     */
    public function downloadWaybillPdf($orderId)
    {
        // Load order with all necessary relations
        $order = Order::with([
            'user',
            'orderItems.product',
            'deliveryOrder.driver',
            'waybill'
        ])->findOrFail($orderId);
        
        $waybill = $order->waybill;
        
        if (!$waybill) {
            return response()->json([
                'message' => 'Waybill belum dibuat untuk order ini.'
            ], 404);
        }
        
        // Generate PDF on-the-fly
        $pdf = $this->generatePdf($order, $waybill);
        
        // Return PDF inline (for preview in browser)
        return $pdf->stream('waybill-' . $order->order_code . '.pdf');
    }
    
    /**
     * Helper: Generate PDF from order data
     * This method creates PDF dynamically from database data
     * No file storage needed!
     */
    private function generatePdf(Order $order, Waybill $waybill)
    {
        // Prepare data for PDF view
        $data = [
            'waybill' => $waybill,
            'order' => $order,
            'user' => $order->user,
            'items' => $order->orderItems,
            'driver' => $order->deliveryOrder?->driver,
        ];
        
        // Generate PDF from blade view
        return Pdf::loadView('waybill', $data)
            ->setPaper('a4', 'portrait');
    }
}
