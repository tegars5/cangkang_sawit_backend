<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProductController extends Controller
{
    const CATEGORIES = [
        'Premium', 'Standard', 'Grade A', 'Grade B', 'Grade C', 'Organik', 'Non-Organik',
    ];

    /**
     * Helper untuk membuat URL Full secara konsisten
     */
    private function getFullUrl($path)
    {
        if (!$path) return null;
        // asset() lebih aman daripada Storage::url() jika APP_URL di .env sudah benar
        return asset('storage/' . $path);
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $products = Product::latest()->paginate($perPage);
        
        $products->getCollection()->transform(function ($product) {
            // Kita simpan path aslinya di field lain jika butuh, 
            // tapi timpa field images dengan URL lengkap untuk Flutter
            if ($product->images) {
                $product->images = $this->getFullUrl($product->images);
            }
            return $product;
        });
        
        return response()->json($products);
    }

    /**
     * Search products by name
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $perPage = $request->input('per_page', 15);
        
        $products = Product::where('name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->latest()
            ->paginate($perPage);
        
        $products->getCollection()->transform(function ($product) {
            if ($product->images) {
                $product->images = $this->getFullUrl($product->images);
            }
            return $product;
        });
        
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category'    => 'required|string|in:' . implode(',', self::CATEGORIES),
            'image_file'  => 'nullable|image|mimes:jpeg,png,jpg|max:5120', 
        ]);

        $data = $request->only(['name', 'description', 'price', 'stock', 'category']);

        if ($request->hasFile('image_file')) {
            $image = $request->file('image_file');
            $filename = 'product_' . time() . '_' . uniqid() . '.jpg';
            
            // Proses Gambar
            $manager = new ImageManager(new Driver());
            $img = $manager->read($image->getPathname());
            $img->scale(width: 800); 
            $encoded = $img->toJpeg(75);
            
            $path = 'products/' . $filename;
            Storage::disk('public')->put($path, (string) $encoded);
            
            $data['images'] = $path;
        }

        $product = Product::create($data);
        
        // Return dengan URL Lengkap
        $product->images = $this->getFullUrl($product->images);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'product' => $product
        ], 201);
    }

    public function show(Product $product)
    {
        $product->images = $this->getFullUrl($product->images);
        return response()->json($product);
    }

 public function update(Request $request, Product $product)
{
    // Log untuk debugging
    \Log::info('Product Update Request', [
        'product_id' => $product->id,
        'has_file' => $request->hasFile('image_file'),
        'all_data' => $request->all(),
        'files' => $request->allFiles(),
    ]);

    $request->validate([
        'name'        => 'sometimes|required|string|max:255',
        'description' => 'nullable|string',
        'price'       => 'sometimes|required|numeric|min:0',
        'stock'       => 'sometimes|required|integer|min:0',
        'category'    => 'sometimes|required|string|in:' . implode(',', self::CATEGORIES),
        'image_file'  => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
    ]);

    $data = $request->only(['name', 'description', 'price', 'stock', 'category']);

    if ($request->hasFile('image_file')) {
        try {
            // 1. Ambil path asli dari DB (pastikan bukan URL http://...)
            $oldImagePath = $product->getRawOriginal('images');

            // 2. Hapus hanya jika path ada di DB dan file fisiknya ada
            if (!empty($oldImagePath) && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
                \Log::info('Deleted old image: ' . $oldImagePath);
            }
            
            // 3. Proses Upload Baru
            $image = $request->file('image_file');
            $filename = 'product_' . time() . '_' . uniqid() . '.jpg';
            
            $manager = new ImageManager(new Driver());
            $img = $manager->read($image->getPathname());
            $img->scale(width: 800);
            $encoded = $img->toJpeg(75);
            
            $path = 'products/' . $filename;
            Storage::disk('public')->put($path, (string) $encoded);
            
            $data['images'] = $path;
            
            \Log::info('Uploaded new image: ' . $path);
        } catch (\Exception $e) {
            \Log::error('Image upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal upload gambar: ' . $e->getMessage()
            ], 500);
        }
    }

    $product->update($data);

    // Kirim response balik dengan URL Lengkap
    $product->refresh();
    $product->images = $this->getFullUrl($product->images);

    return response()->json([
        'message' => 'Produk berhasil diperbarui',
        'product' => $product
    ]);
}

    public function destroy(Product $product)
    {
        // Gunakan getRawOriginal agar tidak terganggu accessor jika ada
        $imagePath = $product->getRawOriginal('images') ?? $product->images;
        
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
        
        $product->delete();
        return response()->json(['message' => 'Produk berhasil dihapus']);
    }

    public function getCategories()
    {
        return response()->json(['categories' => self::CATEGORIES]);
    }
}