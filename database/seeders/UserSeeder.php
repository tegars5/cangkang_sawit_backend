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

        // Driver Account 1 (Primary for demo)
        User::create([
            'name' => 'Budi Santoso',
            'email' => 'driver1@csawit.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
        ]);

        // Driver Account 2
        User::create([
            'name' => 'Ahmad Hidayat',
            'email' => 'driver@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
        ]);

        // Driver Account 3
        User::create([
            'name' => 'Slamet Riyadi',
            'email' => 'driver2@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
        ]);

        echo "✅ Users seeded successfully!\n";
        echo "Admin: admin@gmail.com / password123\n";
        echo "Mitra: mitra@gmail.com / password123\n";
        echo "Driver 1: driver1@csawit.com / password123\n";
        echo "Driver 2: driver@gmail.com / password123\n";
        echo "Driver 3: driver2@gmail.com / password123\n";
    }
}
