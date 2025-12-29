Tolong sesuaikan backend Laravel project cangkang_sawit_backend supaya mendukung data untuk dashboard admin baru di mobile app.

Fokus di:

App\Http\Controllers\Api\AdminDashboardController

Model dan query terkait: Order, Product, User (Mitra).

Route: /api/admin/dashboard-summary.

1. Perluasan Dashboard Summary API
Endpoint GET /api/admin/dashboard-summary saat ini sudah mengembalikan:

total_orders

in_delivery

completed

Tambahkan field-field berikut supaya frontend bisa menampilkan dashboard seperti desain baru:

new_orders

Jumlah pesanan dengan status pending.

pending_shipments

Jumlah pesanan dengan status on_delivery (atau status lain yang kamu gunakan untuk “sedang dikirim”).

active_partners

Jumlah user dengan role mitra yang aktif.

Kalau belum ada flag “active”, untuk sekarang pakai jumlah semua user dengan role mitra.

inventory_tons

Perkiraan stok total dalam ton.

Asumsi: jika products.price dan products.stock sudah ada:

Tambahkan kolom unit_weight di tabel products atau gunakan asumsi 1 stok = 1 ton untuk sekarang.

Untuk sementara, boleh kembalikan total stock sebagai angka saja dan frontend menampilkan “Inventory (Unit)” sampai nanti ada unit berat yang jelas.

Ringkasan status orders untuk Order Status section:

orders_completed = jumlah status completed.

orders_processing = jumlah status confirmed (atau sejenis).

orders_in_transit = jumlah status on_delivery.

orders_awaiting = jumlah status pending.

last_updated_at

Timestamp saat summary di-generate (pakai now()), agar frontend bisa menampilkan “Last updated: 10:45 AM”.

Contoh bentuk response JSON:

json
{
  "total_orders": 48,
  "new_orders": 12,
  "pending_shipments": 8,
  "active_partners": 54,
  "inventory_tons": 1200,
  "orders_completed": 20,
  "orders_processing": 10,
  "orders_in_transit": 8,
  "orders_awaiting": 10,
  "last_updated_at": "2025-12-27T03:00:00Z"
}
2. Implementasi di Controller
Di AdminDashboardController@summary:

Tambah query efisien (gunakan agregasi sekali jalan bila memungkinkan, bukan banyak query terpisah).

Pastikan:

Hanya bisa diakses user dengan role admin (cek auth()->user()->role seperti sekarang).

Return dalam bentuk response()->json([...]) seperti contoh di atas.

3. (Opsional) Dukungan Inventory yang Lebih Realistis
Jika memungkinkan:

Tambah kolom unit_weight (decimal) di tabel products untuk menyimpan berat per unit (dalam ton).

Migration:

php
Schema::table('products', function (Blueprint $table) {
    $table->decimal('unit_weight', 8, 3)->default(1);
});
Di summary:

Hitung inventory_tons sebagai:

php
Product::sum(DB::raw('stock * unit_weight'))
Sesuaikan Product model dan seeder untuk mengisi unit_weight default yang masuk akal.

4. Dokumentasi
Update README atau implementation_plan.md/fitur_app.md untuk menambahkan:

Struktur JSON terbaru dari /admin/dashboard-summary.

Penjelasan singkat setiap field dan cara menghitungnya.

Pastikan perubahan ini tidak mengubah endpoint lain (orders, products, waybill, tracking). Frontend existing hanya akan membaca field baru; field lama (total_orders, in_delivery, completed) tetap ada untuk kompatibilitas.