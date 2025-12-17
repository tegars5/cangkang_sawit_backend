# User Credentials - Cangkang Sawit Backend

Database telah di-seed dengan akun-akun berikut:

## 👤 Admin Account
- **Email**: admin@gmail.com
- **Password**: password123
- **Role**: admin
- **Akses**: Semua fitur admin termasuk assign driver ke order

## 🏢 Mitra Account
- **Email**: mitra@gmail.com
- **Password**: password123
- **Role**: mitra
- **Akses**: Membuat order, melihat order, tracking pengiriman, pembayaran

## 🚚 Driver Account 1
- **Email**: driver@gmail.com
- **Password**: password123
- **Role**: driver
- **Akses**: Melihat tugas pengiriman, update status, kirim lokasi GPS

## 🚚 Driver Account 2
- **Email**: driver2@gmail.com
- **Password**: password123
- **Role**: driver
- **Akses**: Melihat tugas pengiriman, update status, kirim lokasi GPS

---

## 🔄 Cara Menggunakan

### 1. Login sebagai Admin
```bash
POST /api/login
{
  "email": "admin@gmail.com",
  "password": "password123"
}
```

### 2. Login sebagai Mitra
```bash
POST /api/login
{
  "email": "mitra@gmail.com",
  "password": "password123"
}
```

### 3. Login sebagai Driver
```bash
POST /api/login
{
  "email": "driver@gmail.com",
  "password": "password123"
}
```

---

## 📝 Testing Flow

### Flow 1: Mitra Membuat Order
1. Login sebagai **mitra@gmail.com**
2. Buat order baru: `POST /api/orders`
3. Lihat order: `GET /api/orders`

### Flow 2: Admin Assign Driver
1. Login sebagai **admin@gmail.com**
2. Assign driver ke order: `POST /api/admin/orders/{order}/assign-driver`
   ```json
   {
     "driver_id": 3  // ID dari driver@gmail.com
   }
   ```

### Flow 3: Driver Menjalankan Pengiriman
1. Login sebagai **driver@gmail.com**
2. Lihat tugas: `GET /api/driver/orders`
3. Update status: `POST /api/driver/delivery-orders/{id}/status`
   ```json
   {
     "status": "on_the_way"
   }
   ```
4. Kirim lokasi: `POST /api/driver/delivery-orders/{id}/track`
   ```json
   {
     "lat": -6.2088,
     "lng": 106.8456
   }
   ```

### Flow 4: Mitra Tracking Order
1. Login sebagai **mitra@gmail.com**
2. Track order: `GET /api/orders/{order}/tracking`
3. Lihat lokasi driver real-time

---

## 🔧 Re-seed Database

Jika ingin reset dan seed ulang:

```bash
php artisan migrate:fresh --seed
```

Atau seed user saja:

```bash
php artisan db:seed --class=UserSeeder
```

---

## ⚠️ Catatan Keamanan

- Password `password123` hanya untuk development/testing
- Untuk production, gunakan password yang lebih kuat
- Jangan commit file ini ke repository public jika berisi kredensial production
