<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Tambahkan ini

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all()->map(function ($product) {
            // Menambahkan URL lengkap untuk gambar agar Flutter mudah menampilkan
            if ($product->images && !str_starts_with($product->images, 'http')) {
                $product->images = Storage::disk('public')->url($product->images);
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
            'category'    => 'nullable|string|max:100',
            'image_file'  => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Validasi file
        ]);

        $data = $request->only(['name', 'description', 'price', 'stock', 'category']);

        // Cek jika ada file yang diupload
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('products', 'public');
            $data['images'] = $path; // Simpan path ke kolom images
        }

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        // Berikan URL lengkap saat show detail
        if ($product->images && !str_starts_with($product->images, 'http')) {
            $product->images = Storage::disk('public')->url($product->images);
        }
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'sometimes|required|numeric|min:0',
            'stock'       => 'sometimes|required|integer|min:0',
            'category'    => 'nullable|string|max:100',
            'image_file'  => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = $request->only(['name', 'description', 'price', 'stock', 'category']);

        if ($request->hasFile('image_file')) {
            if ($product->images) {
                Storage::disk('public')->delete($product->images);
            }

            $path = $request->file('image_file')->store('products', 'public');
            $data['images'] = $path;
        }

        $product->update($data);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        // Hapus file gambar dari folder saat data produk dihapus
        if ($product->images) {
            Storage::disk('public')->delete($product->images);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}