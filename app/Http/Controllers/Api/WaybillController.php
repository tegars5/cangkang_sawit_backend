<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Waybill;
use App\Models\User;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class WaybillController extends Controller
{
 public function downloadWaybillPdf(Order $order)
{
    $waybill = $order->waybill; // Asumsi relasi hasOne di model Order

    if (!$waybill || !$waybill->pdf_path) {
        return response()->json(['message' => 'PDF tidak ditemukan'], 404);
    }

    $path = storage_path('app/public/waybills/' . $waybill->pdf_path);

    if (!file_exists($path)) {
        return response()->json(['message' => 'File fisik tidak ditemukan'], 404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="waybill.pdf"'
    ]);
}
}