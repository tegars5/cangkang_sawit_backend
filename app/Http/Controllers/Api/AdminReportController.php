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
                SUM(CASE WHEN LOWER(status) = "completed" THEN total_amount ELSE 0 END) as total_revenue,
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
                    SUM(CASE WHEN LOWER(status) = "completed" THEN total_amount ELSE 0 END) as total_revenue
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
