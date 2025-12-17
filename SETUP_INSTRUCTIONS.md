# Setup Instructions - Laravel Sanctum & Tripay

## 1. Install Laravel Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

## 2. Update .env File

Copy `.env.example` to `.env` (jika belum) dan tambahkan kredensial Tripay:

```env
# Database (MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cangkang_sawit
DB_USERNAME=root
DB_PASSWORD=

# Tripay Payment Gateway (Sandbox Mode)
TRIPAY_MERCHANT_CODE=your_merchant_code
TRIPAY_API_KEY=your_api_key
TRIPAY_PRIVATE_KEY=your_private_key
TRIPAY_MODE=sandbox
```

## 3. Run Migrations

```bash
php artisan migrate
```

## 4. (Optional) Configure CORS

Jika aplikasi Flutter akan mengakses API dari domain berbeda, update `config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

## 5. Test API

Gunakan Postman atau Thunder Client untuk test endpoint:

1. Register user baru
2. Login untuk mendapatkan token
3. Gunakan token di header `Authorization: Bearer {token}` untuk endpoint yang protected

## Notes

- Sanctum sudah terintegrasi di User model (HasApiTokens trait)
- Semua route API sudah menggunakan middleware `auth:sanctum` kecuali register, login, dan callback Tripay
- Migration TIDAK dijalankan otomatis, Anda harus run manual dengan `php artisan migrate`
