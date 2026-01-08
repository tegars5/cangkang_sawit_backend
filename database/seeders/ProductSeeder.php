<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Cangkang Sawit Grade A',
                'description' => 'Cangkang sawit kualitas premium dengan kadar air rendah, cocok untuk bahan bakar boiler industri. Ukuran seragam dan bebas dari kontaminan.',
                'price' => 850000,
                'stock' => 50, // 50 tons
            ],
            [
                'name' => 'Cangkang Sawit Grade B',
                'description' => 'Cangkang sawit kualitas standar dengan kadar air sedang. Cocok untuk berbagai keperluan industri dan pembangkit listrik.',
                'price' => 650000,
                'stock' => 80, // 80 tons
            ],
            [
                'name' => 'Cangkang Sawit Grade C',
                'description' => 'Cangkang sawit kualitas ekonomis, cocok untuk bahan bakar alternatif dan keperluan non-industri.',
                'price' => 450000,
                'stock' => 120, // 120 tons
            ],
            [
                'name' => 'Cangkang Sawit Halus',
                'description' => 'Cangkang sawit yang telah dihancurkan menjadi ukuran halus. Cocok untuk campuran pupuk organik dan media tanam.',
                'price' => 350000,
                'stock' => 30, // 30 tons
            ],
            [
                'name' => 'Cangkang Sawit Curah',
                'description' => 'Cangkang sawit dalam jumlah besar (curah) untuk kebutuhan industri skala besar. Harga per ton.',
                'price' => 750000,
                'stock' => 150, // 150 tons
            ],
            [
                'name' => 'Briket Cangkang Sawit',
                'description' => 'Briket yang terbuat dari cangkang sawit terkompresi. Efisien untuk bahan bakar dengan nilai kalori tinggi.',
                'price' => 1200000,
                'stock' => 25, // 25 tons
            ],
            [
                'name' => 'Arang Cangkang Sawit',
                'description' => 'Arang berkualitas tinggi dari cangkang sawit. Cocok untuk industri metalurgi dan pemurnian.',
                'price' => 950000,
                'stock' => 40, // 40 tons
            ],
            [
                'name' => 'Cangkang Sawit Kering',
                'description' => 'Cangkang sawit yang telah dikeringkan dengan kadar air minimal. Siap pakai untuk pembakaran langsung.',
                'price' => 800000,
                'stock' => 60, // 60 tons
            ],
            [
                'name' => 'Cangkang Sawit Premium',
                'description' => 'Cangkang sawit kualitas terbaik dengan kadar air sangat rendah. Cocok untuk berbagai keperluan industri dan pembangkit listrik.',
                'price' => 650000,
                'stock' => 45, // 45 tons
                'category' => 'Premium',
                'images' => 'products/grade_b.jpg',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        echo "✅ Products seeded successfully!\n";
        echo "Total products created: " . count($products) . "\n";
    }
}
