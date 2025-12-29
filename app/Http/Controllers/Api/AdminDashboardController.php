<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Get dashboard summary statistics for admin
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $user = auth()->user();

        // Check if user is admin
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Calculate order statistics efficiently using aggregation
        $orderStats = \DB::table('orders')
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN status = "on_delivery" THEN 1 ELSE 0 END) as on_delivery_count,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count
            ')
            ->first();

        // Count active partners (users with role 'mitra')
        $activePartners = \DB::table('users')
            ->where('role', 'mitra')
            ->count();

        // Calculate total inventory (sum of all product stock)
        $inventoryTons = \DB::table('products')
            ->sum('stock') ?? 0;

        // Get current timestamp
        $lastUpdatedAt = now()->toIso8601String();

        return response()->json([
            // Existing fields (for backward compatibility)
            'total_orders' => (int) $orderStats->total_orders,
            'in_delivery' => (int) $orderStats->on_delivery_count,
            'completed' => (int) $orderStats->completed_count,
            
            // New fields for enhanced dashboard
            'new_orders' => (int) $orderStats->pending_count,
            'pending_shipments' => (int) $orderStats->on_delivery_count,
            'active_partners' => (int) $activePartners,
            'inventory_tons' => (int) $inventoryTons,
            
            // Detailed order status breakdown
            'orders_completed' => (int) $orderStats->completed_count,
            'orders_processing' => (int) $orderStats->confirmed_count,
            'orders_in_transit' => (int) $orderStats->on_delivery_count,
            'orders_awaiting' => (int) $orderStats->pending_count,
            
            // Timestamp
            'last_updated_at' => $lastUpdatedAt,
        ]);
    }
}
