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

