# Admin Dashboard API - Field Reference

## Endpoint
```
GET /api/admin/dashboard-summary
Authorization: Bearer {token}
```

## Response Fields (13 Total)

### 📊 KPI Cards (Top Section)
```dart
'total_orders'        // int - Total semua pesanan
'new_orders'          // int - Pesanan baru (status: pending)
'pending_shipments'   // int - Pengiriman pending (status: on_delivery)
'active_partners'     // int - Jumlah mitra aktif (role: mitra)
'inventory_tons'      // int - Total stok produk (dalam unit)
```

### 📈 Order Status Breakdown
```dart
'orders_completed'    // int - Pesanan selesai (status: completed)
'orders_processing'   // int - Pesanan diproses (status: confirmed)
'orders_in_transit'   // int - Pesanan dalam pengiriman (status: on_delivery)
'orders_awaiting'     // int - Pesanan menunggu (status: pending)
```

### 🕐 Metadata
```dart
'last_updated_at'     // string - ISO 8601 timestamp
```

### ⚠️ Legacy Fields (Backward Compatibility)
```dart
'in_delivery'         // int - Sama dengan pending_shipments
'completed'           // int - Sama dengan orders_completed
```

## Example Response
```json
{
  "total_orders": 48,
  "in_delivery": 8,
  "completed": 20,
  "new_orders": 12,
  "pending_shipments": 8,
  "active_partners": 54,
  "inventory_tons": 1200,
  "orders_completed": 20,
  "orders_processing": 10,
  "orders_in_transit": 8,
  "orders_awaiting": 10,
  "last_updated_at": "2025-12-27T03:00:00+07:00"
}
```

## Order Status Mapping

| Status di Database | Field yang Menghitung |
|-------------------|----------------------|
| `pending` | `new_orders`, `orders_awaiting` |
| `confirmed` | `orders_processing` |
| `on_delivery` | `pending_shipments`, `orders_in_transit`, `in_delivery` |
| `completed` | `orders_completed`, `completed` |

## Testing Credentials
```
Admin: admin@gmail.com / password123
Mitra: mitra@gmail.com / password123
Driver 1: driver1@csawit.com / password123
```

## Sample Data

### Running the Seeder

Untuk mendapatkan data demo yang lengkap dengan tracking, payment, dan waybill:

```bash
php artisan db:seed --class=ComprehensiveDataSeeder
```

### What's Included

- **8 Orders** dengan berbagai status untuk testing semua fitur
- **Realistic GPS Tracking**: 3-5 koordinat per delivery route (Jakarta-Bandung, Semarang-Surabaya, dll)
- **Payment Records**: Berbagai status (paid, unpaid, failed, expired)
- **Waybills**: Surat jalan untuk deliveries yang aktif
- **3 Drivers**: Assigned ke berbagai delivery orders

### Inventory Calculation

Field `inventory_tons` menggunakan `SUM(stock)` dari tabel `products`. Stock values merepresentasikan unit (tons) dengan nilai realistis:
- Grade A: 50 tons
- Grade B: 80 tons
- Grade C: 120 tons
- Total: ~600 tons

## Order Status vs Delivery Status

### Relationship Table

| Order Status | Delivery Status | Tracking Available | Description |
|--------------|-----------------|-------------------|-------------|
| `pending` | - | ❌ | Menunggu pembayaran |
| `confirmed` | - | ❌ | Paid, menunggu driver assignment |
| `on_delivery` | `assigned` | ✅ | Driver assigned, belum berangkat |
| `on_delivery` | `on_the_way` | ✅ | Driver dalam perjalanan (GPS active) |
| `on_delivery` | `arrived` | ✅ | Driver sudah sampai lokasi |
| `completed` | `completed` | ✅ | Pengiriman selesai |
| `cancelled` | `cancelled` / - | ❌ | Order dibatalkan |

### Key Points

- **Delivery Order dibuat** ketika admin assign driver → `orders.status` berubah ke `on_delivery`
- **GPS Tracking** hanya tersedia ketika `delivery_orders` record exists
- **Status transitions** harus mengikuti flow: pending → confirmed → on_delivery → completed
- **Waybill** dibuat bersamaan dengan delivery order assignment
