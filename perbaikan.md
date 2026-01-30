✅ Fitur Pengurangan Stok Siap
Untuk membuat stok berkurang saat Mitra membeli (membuat order), logika tersebut harus diterapkan di Backend (Laravel). Karena saya tidak memiliki akses langsung ke folder backend Anda, saya telah menyiapkan kode Controller yang sudah diperbaiki.

Yang harus Anda lakukan:
Buka file OrderController.php di project backend Laravel Anda (app/Http/Controllers/Api/OrderController.php).
Ganti method store (fungsi pembuatan order) dengan kode yang ada di file stock_reduction_order_controller.php (lihat artifacts).
Apa yang saya tambahkan:
Validasi Stok: Sistem mengecek apakah stok cukup sebelum order dibuat.
Pengurangan Stok: Stok otomatis berkurang (decrement) saat order berhasil dibuat.
Database Transaction: Memastikan order hanya terbentuk jika stok berhasil dikurangi (mencegah data tidak sinkron).
Silakan update backend Anda dengan kode tersebut. 🚀

