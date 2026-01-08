# Cangkang Sawit Delivery App

Aplikasi penjualan dan pengiriman cangkang sawit dengan fitur tracking pengiriman real-time menggunakan Google Maps API, terinspirasi dari alur aplikasi seperti Gojek, Grab, dan Shopee.

## Fitur Utama

### Fitur Dasar
- Registrasi & Login: Mitra, admin, dan driver dapat mendaftar dan login menggunakan email/password.
- Manajemen Produk: Admin dapat menambah, mengedit, dan menghapus produk cangkang sawit serta mengatur harga dan stok.
- Pembuatan Pesanan: Mitra dapat membuat pesanan dengan memilih produk, jumlah, dan alamat tujuan.
- Manajemen Pesanan: Admin dapat melihat, mengubah status pesanan, dan menugaskan driver.
- Surat Jalan: Admin dapat membuat dan mengelola surat jalan untuk setiap pengiriman.
- Tracking Pengiriman: Mitra dapat melihat posisi driver secara real-time di peta menggunakan Google Maps.
- Manajemen Driver: Admin dapat menambah, mengedit, dan menghapus data driver serta menugaskan pengiriman.
- Notifikasi: Driver menerima notifikasi saat ditugaskan, dan mitra mendapat update status pesanan.

### Fitur Lanjutan
- Route Optimization: Sistem menyarankan rute terbaik untuk pengiriman berdasarkan kondisi lalu lintas dan jarak.
- Push Notification: Notifikasi real-time untuk pesanan baru, penugasan driver, dan update status pengiriman.
- Dashboard Admin: Tampilan dashboard untuk melihat statistik penjualan, performa driver, dan laporan pengiriman.
- Order History: Mitra dapat melihat riwayat pesanan sebelumnya dan melakukan pemesanan ulang.
- Payment Gateway: Integrasi **Tripay Payment Gateway (mode sandbox)** untuk simulasi pembayaran digital pada project skripsi.
- Export Surat Jalan PDF: Admin dapat mengekspor surat jalan ke format PDF untuk arsip atau keperluan administrasi.
- Offline Tracking: Driver dapat tetap mengirim update lokasi meskipun dalam kondisi jaringan lemah.
- Customer Support: Fitur chat atau kontak support untuk mitra dan driver.
- Analytics & Reporting: Laporan penjualan, performa pengiriman, dan efisiensi rute.
- Personalized Recommendations: Sistem menyarankan produk atau rute berdasarkan riwayat pesanan dan preferensi mitra.
- Scheduling: Mitra dapat menjadwalkan pengiriman di tanggal dan waktu tertentu.
- Zona & Area Pengiriman: Sistem dapat membatasi atau memfilter pesanan berdasarkan area/zona pengiriman.
- Reorder & Favorit: Fitur pesanan ulang dan produk favorit untuk memudahkan mitra.

## Arsitektur dan Teknologi
- Backend: Laravel (REST API untuk auth, produk, pesanan, surat jalan, driver, tracking, dan integrasi Tripay).
- Frontend: Flutter (aplikasi mobile untuk Mitra & Driver).
- Database: MySQL.
- Maps & Tracking: Google Maps Platform (Maps SDK, Directions API, Geocoding API, dsb.).
- Payment Gateway: Tripay Payment Gateway (mode sandbox untuk pengujian).
- Autentikasi: JWT / token-based auth (misalnya Laravel Sanctum).

### 1. Prasyarat
- PHP 8.x
- Composer
- Flutter SDK
- MySQL
- API key Google Maps
- Akun Tripay (mode sandbox) beserta MERCHANT_CODE, API_KEY, dan PRIVATE_KEY.

## Arsitektur Driver

> [!IMPORTANT]
> **Driver References**: Aplikasi ini menggunakan tabel `users` dengan `role='driver'` untuk semua referensi driver. Tabel `drivers` ada dalam migrations tetapi **TIDAK DIGUNAKAN** dan dianggap deprecated.

### Mengapa Menggunakan `users` Table?

Sistem ini mengadopsi arsitektur single-table untuk semua user roles (admin, mitra, driver) dengan alasan:
- **Simplicity**: Satu tabel untuk autentikasi dan manajemen user
- **Flexibility**: Mudah menambah role baru tanpa migrasi tambahan
- **Consistency**: Semua foreign key menggunakan `users.id`

### Foreign Key References

Semua tabel yang membutuhkan referensi driver menggunakan `users.id`:
- `delivery_orders.driver_id` → `users.id` (where role='driver')
- `waybills.driver_id` → `users.id` (where role='driver')

### Query Driver

```php
// Mendapatkan semua driver
$drivers = User::where('role', 'driver')->get();

// Mendapatkan delivery orders untuk driver tertentu
$deliveries = DeliveryOrder::where('driver_id', $driverId)->get();
```

## Alur Status Order

### Status Orders vs Delivery Orders

Sistem menggunakan dua tabel dengan status yang berbeda untuk tracking order:

#### 1. Orders Table (`orders.status`)

Status utama pesanan dari perspektif customer:

| Status | Deskripsi | Kapan Diset |
|--------|-----------|-------------|
| `pending` | Menunggu pembayaran | Order baru dibuat |
| `confirmed` | Pembayaran berhasil, menunggu pengiriman | Payment status = 'paid' |
| `on_delivery` | Sedang dalam pengiriman | Delivery order dibuat dan driver assigned |
| `completed` | Pesanan selesai diterima | Delivery order status = 'completed' |
| `cancelled` | Pesanan dibatalkan | Manual cancellation atau payment expired |

#### 2. Delivery Orders Table (`delivery_orders.status`)

Status detail pengiriman dari perspektif driver:

| Status | Deskripsi | Kapan Diset |
|--------|-----------|-------------|
| `assigned` | Driver ditugaskan | Admin assign driver ke order |
| `on_the_way` | Driver dalam perjalanan | Driver mulai pengiriman |
| `arrived` | Driver tiba di lokasi | Driver sampai di destination |
| `completed` | Pengiriman selesai | Customer konfirmasi penerimaan |
| `cancelled` | Pengiriman dibatalkan | Order dibatalkan atau driver tidak tersedia |

### Flow Diagram

```
Order Created (pending)
    ↓
Payment Success
    ↓
Order Status: confirmed
    ↓
Admin Assigns Driver
    ↓
DeliveryOrder Created (assigned)
Order Status: on_delivery
    ↓
Driver Starts Journey
    ↓
DeliveryOrder Status: on_the_way
(GPS tracking active)
    ↓
Driver Arrives
    ↓
DeliveryOrder Status: arrived
    ↓
Customer Confirms Receipt
    ↓
DeliveryOrder Status: completed
Order Status: completed
```

### Tracking Availability

- **Tracking tersedia**: Hanya ketika `delivery_orders` record exists (status order = 'on_delivery')
- **GPS Coordinates**: Disimpan di `delivery_tracks` table dengan timestamp
- **Real-time Updates**: Driver mengirim lokasi setiap beberapa menit selama `on_the_way`

## Sample Data untuk Testing

### Menjalankan Seeder

Untuk menambahkan data contoh yang lengkap (orders, payments, delivery tracking, waybills):

```bash
# Jalankan seeder dasar (users & products)
php artisan db:seed

# Jalankan seeder data lengkap (opsional, untuk demo)
php artisan db:seed --class=ComprehensiveDataSeeder
```

### Data yang Dibuat

ComprehensiveDataSeeder membuat:
- **8 Orders** dengan berbagai status (completed, on_delivery, confirmed, pending, cancelled)
- **Order Items** untuk setiap order (1-3 produk per order)
- **8 Payments** dengan status bervariasi (paid, unpaid, failed, expired)
- **3 Delivery Orders** dengan driver assigned
- **12+ GPS Tracking Points** (3-5 titik per delivery route)
- **3 Waybills** untuk deliveries yang aktif

### Koordinat GPS Sample

Sample data menggunakan koordinat realistis di Indonesia:
- Jakarta → Bandung (5 tracking points)
- Semarang → Surabaya (3 tracking points, ongoing)
- Solo → Semarang (4 tracking points)

### Testing Credentials

```
Admin: admin@gmail.com / password123
Mitra: mitra@gmail.com / password123
Driver 1: driver1@csawit.com / password123
Driver 2: driver@gmail.com / password123
Driver 3: driver2@gmail.com / password123
```

