<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin Account
        User::create([
            'name' => 'Admin Cangkang Sawit',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // Mitra Account
        User::create([
            'name' => 'Mitra Cangkang Sawit',
            'email' => 'mitra@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'mitra',
        ]);

        // Driver Account
        User::create([
            'name' => 'Driver Cangkang Sawit',
            'email' => 'driver@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
        ]);

        // Additional Driver Account
        User::create([
            'name' => 'Driver 2 Cangkang Sawit',
            'email' => 'driver2@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
        ]);

        echo "✅ Users seeded successfully!\n";
        echo "Admin: admin@gmail.com / password123\n";
        echo "Mitra: mitra@gmail.com / password123\n";
        echo "Driver 1: driver@gmail.com / password123\n";
        echo "Driver 2: driver2@gmail.com / password123\n";
    }
}
