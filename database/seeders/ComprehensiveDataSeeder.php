<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\DeliveryOrder;
use App\Models\DeliveryTrack;
use App\Models\Waybill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ComprehensiveDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates comprehensive sample data for demonstration:
     * - Orders with various statuses
     * - Order items
     * - Payments (paid, unpaid, failed)
     * - Delivery orders with assigned drivers
     * - Delivery tracks with realistic GPS coordinates (3-5 points per route)
     * - Waybills for deliveries
     */
    public function run(): void
    {
        echo "🌱 Starting comprehensive data seeding...\n";

        // Get users
        $mitra = User::where('email', 'mitra@gmail.com')->first();
        $driver1 = User::where('email', 'driver1@csawit.com')->first();
        $driver2 = User::where('email', 'driver@gmail.com')->first();
        $driver3 = User::where('email', 'driver2@gmail.com')->first();

        if (!$mitra || !$driver1) {
            echo "❌ Error: Please run UserSeeder first!\n";
            return;
        }

        // Get products
        $products = Product::all();
        if ($products->isEmpty()) {
            echo "❌ Error: Please run ProductSeeder first!\n";
            return;
        }

        echo "📦 Creating orders...\n";

        // Order 1: Completed order with full delivery tracking
        $order1 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-001',
            'total_amount' => 0, // Will be calculated
            'status' => 'completed',
            'destination_address' => 'Jl. Gatot Subroto No. 123, Bandung, Jawa Barat',
            'destination_lat' => -6.9175,
            'destination_lng' => 107.6191,
            'created_at' => Carbon::now()->subDays(7),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $products[0]->id, // Grade A
            'quantity' => 5,
            'price' => $products[0]->price,
            'subtotal' => 5 * $products[0]->price,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $products[1]->id, // Grade B
            'quantity' => 10,
            'price' => $products[1]->price,
            'subtotal' => 10 * $products[1]->price,
        ]);

        $order1->update(['total_amount' => $order1->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order1->id,
            'reference' => 'PAY-' . date('Ymd') . '-001',
            'merchant_ref' => 'TRIPAY-' . uniqid(),
            'amount' => $order1->total_amount,
            'payment_method' => 'QRIS',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subDays(7),
            'expired_at' => Carbon::now()->subDays(6),
        ]);

        $delivery1 = DeliveryOrder::create([
            'order_id' => $order1->id,
            'driver_id' => $driver1->id,
            'status' => 'completed',
            'assigned_at' => Carbon::now()->subDays(6),
            'completed_at' => Carbon::now()->subDays(2),
        ]);

        // Tracking route: Jakarta -> Bandung (5 points)
        $route1 = [
            ['lat' => -6.2088, 'lng' => 106.8456, 'time' => Carbon::now()->subDays(6)->addHours(1)], // Jakarta start
            ['lat' => -6.3547, 'lng' => 106.9339, 'time' => Carbon::now()->subDays(6)->addHours(2)], // Cibubur
            ['lat' => -6.5571, 'lng' => 107.0290, 'time' => Carbon::now()->subDays(6)->addHours(3)], // Cileunyi
            ['lat' => -6.7324, 'lng' => 107.3145, 'time' => Carbon::now()->subDays(6)->addHours(4)], // Nagreg
            ['lat' => -6.9175, 'lng' => 107.6191, 'time' => Carbon::now()->subDays(6)->addHours(5)], // Bandung arrived
        ];

        foreach ($route1 as $point) {
            DeliveryTrack::create([
                'delivery_order_id' => $delivery1->id,
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'recorded_at' => $point['time'],
            ]);
        }

        Waybill::create([
            'order_id' => $order1->id,
            'driver_id' => $driver1->id,
            'waybill_number' => 'WB-' . date('Ymd') . '-001',
            'notes' => 'Pengiriman Grade A dan B ke Bandung - Selesai',
        ]);

        // Order 2: On delivery (currently in transit)
        $order2 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-002',
            'total_amount' => 0,
            'status' => 'on_delivery',
            'destination_address' => 'Jl. Raya Darmo No. 88, Surabaya, Jawa Timur',
            'destination_lat' => -7.2575,
            'destination_lng' => 112.7521,
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subHours(3),
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $products[4]->id, // Curah
            'quantity' => 20,
            'price' => $products[4]->price,
            'subtotal' => 20 * $products[4]->price,
        ]);

        $order2->update(['total_amount' => $order2->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order2->id,
            'reference' => 'PAY-' . date('Ymd') . '-002',
            'merchant_ref' => 'TRIPAY-' . uniqid(),
            'amount' => $order2->total_amount,
            'payment_method' => 'Virtual Account BCA',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subDays(2),
            'expired_at' => Carbon::now()->subDays(1),
        ]);

        $delivery2 = DeliveryOrder::create([
            'order_id' => $order2->id,
            'driver_id' => $driver2->id,
            'status' => 'on_the_way',
            'assigned_at' => Carbon::now()->subDays(1),
            'completed_at' => null,
        ]);

        // Tracking route: Semarang -> Surabaya (3 points so far)
        $route2 = [
            ['lat' => -6.9667, 'lng' => 110.4167, 'time' => Carbon::now()->subHours(5)], // Semarang start
            ['lat' => -7.0245, 'lng' => 110.9241, 'time' => Carbon::now()->subHours(3)], // Demak
            ['lat' => -7.1478, 'lng' => 111.5234, 'time' => Carbon::now()->subHours(1)], // Tuban
        ];

        foreach ($route2 as $point) {
            DeliveryTrack::create([
                'delivery_order_id' => $delivery2->id,
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'recorded_at' => $point['time'],
            ]);
        }

        Waybill::create([
            'order_id' => $order2->id,
            'driver_id' => $driver2->id,
            'waybill_number' => 'WB-' . date('Ymd') . '-002',
            'notes' => 'Pengiriman Curah ke Surabaya - Dalam Perjalanan',
        ]);

        // Order 3: Confirmed, waiting for driver assignment
        $order3 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-003',
            'total_amount' => 0,
            'status' => 'confirmed',
            'destination_address' => 'Jl. Malioboro No. 56, Yogyakarta',
            'destination_lat' => -7.7956,
            'destination_lng' => 110.3695,
            'created_at' => Carbon::now()->subHours(12),
            'updated_at' => Carbon::now()->subHours(6),
        ]);

        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $products[5]->id, // Briket
            'quantity' => 8,
            'price' => $products[5]->price,
            'subtotal' => 8 * $products[5]->price,
        ]);

        $order3->update(['total_amount' => $order3->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order3->id,
            'reference' => 'PAY-' . date('Ymd') . '-003',
            'merchant_ref' => 'TRIPAY-' . uniqid(),
            'amount' => $order3->total_amount,
            'payment_method' => 'QRIS',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subHours(12),
            'expired_at' => Carbon::now()->subHours(11),
        ]);

        // Order 4: Pending payment
        $order4 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-004',
            'total_amount' => 0,
            'status' => 'pending',
            'destination_address' => 'Jl. Asia Afrika No. 8, Jakarta Pusat',
            'destination_lat' => -6.2088,
            'destination_lng' => 106.8456,
            'created_at' => Carbon::now()->subHours(3),
            'updated_at' => Carbon::now()->subHours(3),
        ]);

        OrderItem::create([
            'order_id' => $order4->id,
            'product_id' => $products[2]->id, // Grade C
            'quantity' => 15,
            'price' => $products[2]->price,
            'subtotal' => 15 * $products[2]->price,
        ]);

        OrderItem::create([
            'order_id' => $order4->id,
            'product_id' => $products[3]->id, // Halus
            'quantity' => 5,
            'price' => $products[3]->price,
            'subtotal' => 5 * $products[3]->price,
        ]);

        $order4->update(['total_amount' => $order4->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order4->id,
            'reference' => 'PAY-' . date('Ymd') . '-004',
            'merchant_ref' => null,
            'amount' => $order4->total_amount,
            'payment_method' => null,
            'status' => 'unpaid',
            'paid_at' => null,
            'expired_at' => Carbon::now()->addHours(21),
        ]);

        // Order 5: Failed payment
        $order5 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-005',
            'total_amount' => 0,
            'status' => 'pending',
            'destination_address' => 'Jl. Sudirman No. 45, Medan, Sumatera Utara',
            'destination_lat' => 3.5952,
            'destination_lng' => 98.6722,
            'created_at' => Carbon::now()->subDays(1),
            'updated_at' => Carbon::now()->subDays(1),
        ]);

        OrderItem::create([
            'order_id' => $order5->id,
            'product_id' => $products[6]->id, 
            'quantity' => 12,
            'price' => $products[6]->price,
            'subtotal' => 12 * $products[6]->price,
        ]);

        $order5->update(['total_amount' => $order5->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order5->id,
            'reference' => 'PAY-' . date('Ymd') . '-005',
            'merchant_ref' => 'TRIPAY-' . uniqid(),
            'amount' => $order5->total_amount,
            'payment_method' => 'Virtual Account BNI',
            'status' => 'failed',
            'paid_at' => null,
            'expired_at' => Carbon::now()->subHours(1),
        ]);

        // Order 6: Completed with different driver
        $order6 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-006',
            'total_amount' => 0,
            'status' => 'completed',
            'destination_address' => 'Jl. Gajah Mada No. 100, Semarang, Jawa Tengah',
            'destination_lat' => -6.9667,
            'destination_lng' => 110.4167,
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subDays(3),
        ]);

        OrderItem::create([
            'order_id' => $order6->id,
            'product_id' => $products[7]->id, // Kering
            'quantity' => 7,
            'price' => $products[7]->price,
            'subtotal' => 7 * $products[7]->price,
        ]);

        $order6->update(['total_amount' => $order6->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order6->id,
            'reference' => 'PAY-' . date('Ymd') . '-006',
            'merchant_ref' => 'TRIPAY-' . uniqid(),
            'amount' => $order6->total_amount,
            'payment_method' => 'E-Wallet OVO',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subDays(5),
            'expired_at' => Carbon::now()->subDays(4),
        ]);

        $delivery6 = DeliveryOrder::create([
            'order_id' => $order6->id,
            'driver_id' => $driver3->id,
            'status' => 'completed',
            'assigned_at' => Carbon::now()->subDays(4),
            'completed_at' => Carbon::now()->subDays(3),
        ]);

        // Tracking route: Solo -> Semarang (4 points)
        $route6 = [
            ['lat' => -7.5755, 'lng' => 110.8243, 'time' => Carbon::now()->subDays(4)->addHours(1)], // Solo start
            ['lat' => -7.3011, 'lng' => 110.7783, 'time' => Carbon::now()->subDays(4)->addHours(2)], // Boyolali
            ['lat' => -7.1478, 'lng' => 110.6234, 'time' => Carbon::now()->subDays(4)->addHours(3)], // Salatiga
            ['lat' => -6.9667, 'lng' => 110.4167, 'time' => Carbon::now()->subDays(4)->addHours(4)], // Semarang arrived
        ];

        foreach ($route6 as $point) {
            DeliveryTrack::create([
                'delivery_order_id' => $delivery6->id,
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'recorded_at' => $point['time'],
            ]);
        }

        Waybill::create([
            'order_id' => $order6->id,
            'driver_id' => $driver3->id,
            'waybill_number' => 'WB-' . date('Ymd') . '-006',
            'notes' => 'Pengiriman Cangkang Kering ke Semarang - Selesai',
        ]);

        // Order 7: Cancelled order
        $order7 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-007',
            'total_amount' => 0,
            'status' => 'cancelled',
            'destination_address' => 'Jl. Diponegoro No. 77, Malang, Jawa Timur',
            'destination_lat' => -7.9666,
            'destination_lng' => 112.6326,
            'created_at' => Carbon::now()->subDays(4),
            'updated_at' => Carbon::now()->subDays(4),
        ]);

        OrderItem::create([
            'order_id' => $order7->id,
            'product_id' => $products[1]->id,
            'quantity' => 10,
            'price' => $products[1]->price,
            'subtotal' => 10 * $products[1]->price,
        ]);

        $order7->update(['total_amount' => $order7->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order7->id,
            'reference' => 'PAY-' . date('Ymd') . '-007',
            'merchant_ref' => null,
            'amount' => $order7->total_amount,
            'payment_method' => null,
            'status' => 'expired',
            'paid_at' => null,
            'expired_at' => Carbon::now()->subDays(3),
        ]);

        // Order 8: Another pending order
        $order8 = Order::create([
            'user_id' => $mitra->id,
            'order_code' => 'ORD-' . date('Ymd') . '-008',
            'total_amount' => 0,
            'status' => 'pending',
            'destination_address' => 'Jl. Ahmad Yani No. 234, Bekasi, Jawa Barat',
            'destination_lat' => -6.2383,
            'destination_lng' => 106.9756,
            'created_at' => Carbon::now()->subHours(6),
            'updated_at' => Carbon::now()->subHours(6),
        ]);

        OrderItem::create([
            'order_id' => $order8->id,
            'product_id' => $products[0]->id,
            'quantity' => 3,
            'price' => $products[0]->price,
            'subtotal' => 3 * $products[0]->price,
        ]);

        $order8->update(['total_amount' => $order8->orderItems()->sum('subtotal')]);

        Payment::create([
            'order_id' => $order8->id,
            'reference' => 'PAY-' . date('Ymd') . '-008',
            'merchant_ref' => null,
            'amount' => $order8->total_amount,
            'payment_method' => null,
            'status' => 'unpaid',
            'paid_at' => null,
            'expired_at' => Carbon::now()->addHours(18),
        ]);

        echo "\n✅ Comprehensive data seeding completed!\n\n";
        echo "📊 Summary:\n";
        echo "   Orders created: 8\n";
        echo "   - Completed: 2\n";
        echo "   - On Delivery: 1\n";
        echo "   - Confirmed: 1\n";
        echo "   - Pending: 3\n";
        echo "   - Cancelled: 1\n";
        echo "\n";
        echo "   Order Items: " . OrderItem::count() . "\n";
        echo "   Payments: 8\n";
        echo "   - Paid: 4\n";
        echo "   - Unpaid: 2\n";
        echo "   - Failed: 1\n";
        echo "   - Expired: 1\n";
        echo "\n";
        echo "   Delivery Orders: 3\n";
        echo "   Delivery Tracks: " . DeliveryTrack::count() . " GPS points\n";
        echo "   Waybills: 3\n";
        echo "\n";
        echo "🚚 Drivers assigned:\n";
        echo "   - {$driver1->name} (driver1@csawit.com): 1 delivery\n";
        echo "   - {$driver2->name} (driver@gmail.com): 1 delivery\n";
        echo "   - {$driver3->name} (driver2@gmail.com): 1 delivery\n";
        echo "\n";
    }
}
