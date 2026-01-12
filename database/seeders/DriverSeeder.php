<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverSeeder extends Seeder
{
    public function run()
    {
        $drivers = [
            ['name' => 'Budi Santoso', 'email' => 'budi@driver.com'],
            ['name' => 'Agus Setiawan', 'email' => 'agus@driver.com'],
            ['name' => 'Iwan Fals', 'email' => 'iwan@driver.com'],
            ['name' => 'Slamet Riyadi', 'email' => 'slamet@driver.com'],
            ['name' => 'Dedi Cahyadi', 'email' => 'dedi@driver.com'],
            ['name' => 'Rahmat Hidayat', 'email' => 'rahmat@driver.com'],
            ['name' => 'Eko Prasetyo', 'email' => 'eko@driver.com'],
            ['name' => 'Heri Susanto', 'email' => 'heri@driver.com'],
            ['name' => 'Andi Wijaya', 'email' => 'andi@driver.com'],
            ['name' => 'Yanto Gombloh', 'email' => 'yanto@driver.com'],
        ];

        foreach ($drivers as $driver) {
            User::create([
                'name' => $driver['name'],
                'email' => $driver['email'],
                'password' => Hash::make('password'), // password default: password
                'role' => 'driver',
                'phone' => '0812' . rand(10000000, 99999999),
                'availability_status' => 'available', // Pastikan statusnya available agar muncul di Flutter
            ]);
        }
    }
}