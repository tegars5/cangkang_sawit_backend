<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kita menggunakan DB::statement karena Laravel Blueprint tidak mendukung update ENUM secara native
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending_payment', 
            'pending', 
            'confirmed', 
            'on_delivery', 
            'completed', 
            'cancelled'
        ) DEFAULT 'pending_payment'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       // Kembalikan ke struktur awal jika rollback
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending', 
            'confirmed', 
            'on_delivery', 
            'completed', 
            'cancelled'
        ) DEFAULT 'pending'");
    }
};
