<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get admin user and create token
$admin = \App\Models\User::where('email', 'admin@gmail.com')->first();
if (!$admin) {
    echo "❌ Admin user not found!\n";
    exit(1);
}

$token = $admin->createToken('dashboard-test')->plainTextToken;
echo "✅ Admin Token Generated\n";
echo "Token: {$token}\n\n";

// Simulate the dashboard request
$orderStats = \DB::table('orders')
    ->selectRaw('
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN status = "on_delivery" THEN 1 ELSE 0 END) as on_delivery_count,
        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count
    ')
    ->first();

$activePartners = \DB::table('users')
    ->where('role', 'mitra')
    ->count();

$inventoryTons = \DB::table('products')
    ->sum('stock') ?? 0;

$lastUpdatedAt = now()->toIso8601String();

$dashboardData = [
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
];

echo "📊 Dashboard Summary Response:\n";
echo json_encode($dashboardData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";

// Additional verification
echo "🔍 Verification Details:\n";
echo "   Orders by status:\n";
echo "   - Pending: {$orderStats->pending_count}\n";
echo "   - Confirmed: {$orderStats->confirmed_count}\n";
echo "   - On Delivery: {$orderStats->on_delivery_count}\n";
echo "   - Completed: {$orderStats->completed_count}\n";
echo "   - Total: {$orderStats->total_orders}\n\n";

echo "   Inventory: {$inventoryTons} tons (SUM of product stock)\n";
echo "   Active Partners: {$activePartners}\n\n";

// Check delivery tracking data
$deliveryOrders = \DB::table('delivery_orders')->count();
$trackingPoints = \DB::table('delivery_tracks')->count();
$waybills = \DB::table('waybills')->count();

echo "   Delivery Data:\n";
echo "   - Delivery Orders: {$deliveryOrders}\n";
echo "   - GPS Tracking Points: {$trackingPoints}\n";
echo "   - Waybills: {$waybills}\n";

echo "\n✅ Test completed successfully!\n";
