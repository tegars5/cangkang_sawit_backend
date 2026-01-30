Backend Request: Admin Reports API
Tujuan
Membuat endpoint baru untuk mendukung fitur Laporan Admin (Daily, Weekly, Monthly) di aplikasi Flutter. Endpoint ini akan mengembalikan statistik penjualan berdasarkan periode waktu yang dipilih.

Endpoint Baru
GET /api/admin/reports
Deskripsi: Mengambil data laporan penjualan berdasarkan periode waktu.

Headers:

Authorization: Bearer {token}
Accept: application/json
Query Parameters:

Parameter Type Required Description
period string Yes Periode laporan: daily, weekly, atau monthly
date
string No Tanggal spesifik (format: YYYY-MM-DD). Jika tidak ada, gunakan hari ini
Contoh Request:

GET /api/admin/reports?period=daily&date=2026-01-31
GET /api/admin/reports?period=weekly
GET /api/admin/reports?period=monthly
Response Format
Success Response (200 OK)
{
"status": "success",
"data": {
"period": "daily",
"date_range": {
"start": "2026-01-31",
"end": "2026-01-31"
},
"summary": {
"total_orders": 15,
"total_revenue": 125000000,
"completed_orders": 12,
"pending_orders": 2,
"cancelled_orders": 1
},
"top_products": [
{
"product_id": 1,
"product_name": "Cangkang Sawit Grade A",
"total_quantity": 150,
"total_revenue": 75000000
},
{
"product_id": 2,
"product_name": "Cangkang Sawit Grade B",
"total_quantity": 100,
"total_revenue": 50000000
}
],
"daily_breakdown": [
{
"date": "2026-01-31",
"total_orders": 15,
"total_revenue": 125000000
}
]
}
}
Weekly Response Example
Untuk period=weekly, daily_breakdown akan berisi 7 hari terakhir:

{
"daily_breakdown": [
{"date": "2026-01-25", "total_orders": 8, "total_revenue": 60000000},
{"date": "2026-01-26", "total_orders": 12, "total_revenue": 95000000},
{"date": "2026-01-27", "total_orders": 10, "total_revenue": 80000000},
{"date": "2026-01-28", "total_orders": 15, "total_revenue": 120000000},
{"date": "2026-01-29", "total_orders": 9, "total_revenue": 70000000},
{"date": "2026-01-30", "total_orders": 11, "total_revenue": 85000000},
{"date": "2026-01-31", "total_orders": 15, "total_revenue": 125000000}
]
}
Monthly Response Example
Untuk period=monthly, daily_breakdown akan berisi data per hari dalam bulan tersebut (atau bisa diganti dengan weekly_breakdown jika lebih efisien).

Implementasi Backend (Laravel)

1. Route (routes/api.php)
   Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
   Route::get('/admin/reports', [AdminReportController::class, 'index']);
   });
2. Controller (app/Http/Controllers/Api/AdminReportController.php)
 <?php
 namespace App\Http\Controllers\Api;
 use App\Http\Controllers\Controller;
 use Illuminate\Http\Request;
 use Illuminate\Support\Facades\DB;
 use Carbon\Carbon;
 class AdminReportController extends Controller
 {
     public function index(Request $request)
     {
         $request->validate([
             'period' => 'required|in:daily,weekly,monthly',
             'date' => 'nullable|date_format:Y-m-d',
         ]);
         $period = $request->input('period');
         $date = $request->input('date', now()->format('Y-m-d'));
         $targetDate = Carbon::parse($date);
         // Tentukan range tanggal
         switch ($period) {
             case 'daily':
                 $startDate = $targetDate->copy()->startOfDay();
                 $endDate = $targetDate->copy()->endOfDay();
                 break;
             case 'weekly':
                 $startDate = $targetDate->copy()->subDays(6)->startOfDay();
                 $endDate = $targetDate->copy()->endOfDay();
                 break;
             case 'monthly':
                 $startDate = $targetDate->copy()->startOfMonth();
                 $endDate = $targetDate->copy()->endOfMonth();
                 break;
         }
         // 1. Summary Statistics
         $summary = DB::table('orders')
             ->whereBetween('created_at', [$startDate, $endDate])
             ->selectRaw('
                 COUNT(*) as total_orders,
                 SUM(CASE WHEN LOWER(status) = "completed" THEN total_price ELSE 0 END) as total_revenue,
                 SUM(CASE WHEN LOWER(status) = "completed" THEN 1 ELSE 0 END) as completed_orders,
                 SUM(CASE WHEN LOWER(status) = "pending" THEN 1 ELSE 0 END) as pending_orders,
                 SUM(CASE WHEN LOWER(status) = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders
             ')
             ->first();
         // 2. Top Products
         $topProducts = DB::table('order_items')
             ->join('orders', 'orders.id', '=', 'order_items.order_id')
             ->join('products', 'products.id', '=', 'order_items.product_id')
             ->whereBetween('orders.created_at', [$startDate, $endDate])
             ->where('orders.status', 'completed')
             ->select(
                 'products.id as product_id',
                 'products.name as product_name',
                 DB::raw('SUM(order_items.quantity) as total_quantity'),
                 DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
             )
             ->groupBy('products.id', 'products.name')
             ->orderByDesc('total_revenue')
             ->limit(5)
             ->get();
         // 3. Daily Breakdown
         $dailyBreakdown = [];
         $currentDate = $startDate->copy();
         
         while ($currentDate <= $endDate) {
             $dayData = DB::table('orders')
                 ->whereDate('created_at', $currentDate->format('Y-m-d'))
                 ->selectRaw('
                     COUNT(*) as total_orders,
                     SUM(CASE WHEN LOWER(status) = "completed" THEN total_price ELSE 0 END) as total_revenue
                 ')
                 ->first();
             $dailyBreakdown[] = [
                 'date' => $currentDate->format('Y-m-d'),
                 'total_orders' => (int) ($dayData->total_orders ?? 0),
                 'total_revenue' => (float) ($dayData->total_revenue ?? 0),
             ];
             $currentDate->addDay();
         }
         return response()->json([
             'status' => 'success',
             'data' => [
                 'period' => $period,
                 'date_range' => [
                     'start' => $startDate->format('Y-m-d'),
                     'end' => $endDate->format('Y-m-d'),
                 ],
                 'summary' => [
                     'total_orders' => (int) ($summary->total_orders ?? 0),
                     'total_revenue' => (float) ($summary->total_revenue ?? 0),
                     'completed_orders' => (int) ($summary->completed_orders ?? 0),
                     'pending_orders' => (int) ($summary->pending_orders ?? 0),
                     'cancelled_orders' => (int) ($summary->cancelled_orders ?? 0),
                 ],
                 'top_products' => $topProducts->map(function ($product) {
                     return [
                         'product_id' => $product->product_id,
                         'product_name' => $product->product_name,
                         'total_quantity' => (float) $product->total_quantity,
                         'total_revenue' => (float) $product->total_revenue,
                     ];
                 }),
                 'daily_breakdown' => $dailyBreakdown,
             ],
         ]);
     }
 }
 Testing
 Test dengan Postman/cURL:
 Daily Report:

curl -X GET "http://localhost:8000/api/admin/reports?period=daily&date=2026-01-31" \
 -H "Authorization: Bearer YOUR_TOKEN" \
 -H "Accept: application/json"
Weekly Report:

curl -X GET "http://localhost:8000/api/admin/reports?period=weekly" \
 -H "Authorization: Bearer YOUR_TOKEN" \
 -H "Accept: application/json"
Monthly Report:

curl -X GET "http://localhost:8000/api/admin/reports?period=monthly" \
 -H "Authorization: Bearer YOUR_TOKEN" \
 -H "Accept: application/json"
Catatan Penting
Tidak ada perubahan database - Endpoint ini menggunakan tabel yang sudah ada (orders, order_items, products).
Authorization - Pastikan middleware role:admin sudah terpasang untuk membatasi akses hanya untuk admin.
Performance - Untuk data yang sangat besar, pertimbangkan untuk menambahkan index pada kolom created_at di tabel orders.
Timezone - Pastikan timezone server Laravel sesuai dengan timezone aplikasi (WIB/Asia/Jakarta).
Timeline Estimasi
Implementasi Backend: 1-2 jam
Testing: 30 menit
Terima kasih! 🙏
