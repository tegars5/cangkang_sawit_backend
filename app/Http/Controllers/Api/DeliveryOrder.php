<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'driver_id',
        'status', // assigned, on_delivery, delivered
        'waybill_pdf',
    ];

    /**
     * Relasi ke Order Utama
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relasi ke Driver (diarahkan ke tabel USERS)
     * Karena tabel drivers kosong, kita ambil data driver dari tabel users
     */
    public function driver()
    {
        // Hapus filter where('role', 'driver') di sini karena terkadang 
        // menyebabkan null saat eager loading jika role tidak ter-load sempurna
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Relasi ke riwayat koordinat GPS (untuk tracking peta)
     */
    public function deliveryTracks()
    {
        return $this->hasMany(DeliveryTrack::class);
    }
}