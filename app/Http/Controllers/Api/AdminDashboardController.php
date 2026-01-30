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

        // 1. Order Statistics
        $orderStats = \DB::table('orders')
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN status = "on_delivery" THEN 1 ELSE 0 END) as on_delivery_count,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count
            ')
            ->first();

        // 2. Driver Statistics
        $totalDrivers = \DB::table('users')->where('role', 'driver')->count();
        $activeDrivers = \DB::table('users')
            ->where('role', 'driver')
            ->whereIn('availability_status', ['available', 'busy']) // available or currently working
            ->count();

        // 3. Active Partners (Mitra)
        $activePartners = \DB::table('users')->where('role', 'mitra')->count();

        // 4. Inventory
        $inventoryTons = \DB::table('products')->sum('stock') ?? 0;

        // 5. Best Selling Product (Based on COMPLETED orders)
        $bestSeller = \DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', 'completed')
            ->select('products.name', \DB::raw('SUM(order_items.quantity) as total_qty'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->first();

        // 6. Weekly Orders Chart (Last 7 Days)
        $endDate = now();
        $startDate = now()->subDays(6);
        
        $weeklyStats = \DB::table('orders')
            ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Fill missing days with 0
        $filledWeeklyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $filledWeeklyStats[] = $weeklyStats[$date] ?? 0;
        }

        // 7. Active Fleet Locations (Frontend Request)
        $activeFleet = \DB::table('users')
            ->join('delivery_orders', 'users.id', '=', 'delivery_orders.driver_id')
            ->join('orders', 'delivery_orders.order_id', '=', 'orders.id')
            ->where('users.role', 'driver')
            ->where('orders.status', 'on_delivery')
            ->select('users.id', 'users.name', 'orders.id as order_id')
            ->get();

        $fleetAndLocations = $activeFleet->map(function ($driver) {
            // Get latest track for this delivery
            $latestTrack = \DB::table('delivery_tracks')
                ->join('delivery_orders', 'delivery_orders.id', '=', 'delivery_tracks.delivery_order_id')
                ->where('delivery_orders.driver_id', $driver->id)
                ->where('delivery_orders.order_id', $driver->order_id)
                ->latest('delivery_tracks.recorded_at')
                ->first(['delivery_tracks.lat', 'delivery_tracks.lng']);
            
            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'order_id' => $driver->order_id,
                'latitude' => $latestTrack ? (float)$latestTrack->lat : null,
                'longitude' => $latestTrack ? (float)$latestTrack->lng : null,
            ];
        })->filter(function ($item) {
            return $item['latitude'] != null;
        })->values();

        return response()->json([
            // --- Requested Keys Section ---
            'pending_orders' => (int) $orderStats->pending_count,
            'processed_orders' => (int) $orderStats->confirmed_count,
            'active_drivers' => (int) $activeDrivers,
            'total_drivers' => (int) $totalDrivers,
            'best_selling_product' => $bestSeller ? $bestSeller->name : '-',
            'best_selling_product_qty' => $bestSeller ? (int) $bestSeller->total_qty : 0,
            'best_selling_qty' => $bestSeller ? (int) $bestSeller->total_qty : 0,
            'weekly_orders' => $filledWeeklyStats,
            'active_fleet_locations' => $fleetAndLocations, // New Key for Map
            
            // --- Legacy/Existing Keys ---
            'total_orders' => (int) $orderStats->total_orders,
            'in_delivery' => (int) $orderStats->on_delivery_count,
            'completed' => (int) $orderStats->completed_count,
            'new_orders' => (int) $orderStats->pending_count,
            'pending_shipments' => (int) $orderStats->on_delivery_count,
            'active_partners' => (int) $activePartners,
            'inventory_tons' => (int) $inventoryTons,
            
            'orders_completed' => (int) $orderStats->completed_count,
            'orders_processing' => (int) $orderStats->confirmed_count,
            'orders_in_transit' => (int) $orderStats->on_delivery_count,
            'orders_awaiting' => (int) $orderStats->pending_count,
            
            'last_updated_at' => now()->toIso8601String(),
        ]);
    }
}
