<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Tambahkan ini

class ProductController extends Controller
{
    // Predefined categories for dropdown
    const CATEGORIES = [
        'Premium',
        'Standard',
        'Grade A',
        'Grade B',
        'Grade C',
        'Organik',
        'Non-Organik',
    ];
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        
        $products = Product::paginate($perPage);
        
        // Transform each product to add full URL for images
        $products->getCollection()->transform(function ($product) {
            if ($product->images && !str_starts_with($product->images, 'http')) {
                $product->images = Storage::disk('public')->url($product->images);
            }
            return $product;
        });
        
        return response()->json($products);
    }
    
    /**
     * Search products with filters
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        $category = $request->input('category');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $perPage = $request->input('per_page', 15);
        
        $productsQuery = Product::query();
        
        // Search in name and description
        if ($query) {
            $productsQuery->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }
        
        // Filter by category
        if ($category) {
            $productsQuery->where('category', $category);
        }
        
        // Filter by price range
        if ($minPrice) {
            $productsQuery->where('price', '>=', $minPrice);
        }
        
        if ($maxPrice) {
            $productsQuery->where('price', '<=', $maxPrice);
        }
        
        $products = $productsQuery->paginate($perPage);
        
        // Transform to add full URL for images
        $products->getCollection()->transform(function ($product) {
            if ($product->images && !str_starts_with($product->images, 'http')) {
                $product->images = Storage::disk('public')->url($product->images);
            }
            return $product;
        });
        
        return response()->json($products);
    }

    public function store(Request $request)
    {
        // Validate category from dropdown
        $category = $request->input('category');
        if (!in_array($category, self::CATEGORIES)) {
            return response()->json([
                'message' => 'The selected category is invalid.',
                'errors' => [
                    'category' => ['The selected category is invalid. Allowed values: ' . implode(', ', self::CATEGORIES)]
                ]
            ], 422);
        }
        
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category'    => 'required|string',
            'image_file'  => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Max 5MB
        ]);

        $data = $request->only(['name', 'description', 'price', 'stock', 'category']);

        // Optimize and save image if uploaded
        if ($request->hasFile('image_file')) {
            $image = $request->file('image_file');
            $filename = 'product_' . time() . '_' . uniqid() . '.jpg';
            
            // Create image manager with GD driver
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            
            // Read and resize image
            $img = $manager->read($image->getPathname());
            $img->scale(width: 800); // Resize to max width 800px, maintain aspect ratio
            
            // Encode to JPEG with 75% quality
            $encoded = $img->toJpeg(75);
            
            // Save to storage
            $path = 'products/' . $filename;
            Storage::disk('public')->put($path, (string) $encoded);
            
            $data['images'] = $path;
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
        // Validate category from dropdown if provided
        if ($request->has('category')) {
            $category = $request->input('category');
            if (!in_array($category, self::CATEGORIES)) {
                return response()->json([
                    'message' => 'The selected category is invalid.',
                    'errors' => [
                        'category' => ['The selected category is invalid. Allowed values: ' . implode(', ', self::CATEGORIES)]
                    ]
                ], 422);
            }
        }
        
        $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'sometimes|required|numeric|min:0',
            'stock'       => 'sometimes|required|integer|min:0',
            'category'    => 'sometimes|required|string',
            'image_file'  => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $data = $request->only(['name', 'description', 'price', 'stock', 'category']);

        if ($request->hasFile('image_file')) {
            // Delete old image
            if ($product->images) {
                Storage::disk('public')->delete($product->images);
            }
            
            // Optimize and save new image
            $image = $request->file('image_file');
            $filename = 'product_' . time() . '_' . uniqid() . '.jpg';
            
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $img = $manager->read($image->getPathname());
            $img->scale(width: 800);
            $encoded = $img->toJpeg(75);
            
            $path = 'products/' . $filename;
            Storage::disk('public')->put($path, (string) $encoded);
            
            $data['images'] = $path;
        }

        $product->update($data);

        return response()->json($product);
    }

    /**
     * Get available categories for dropdown
     */
    public function getCategories()
    {
        return response()->json([
            'categories' => self::CATEGORIES
        ]);
    }

    public function destroy(Product $product)
    {
        // Delete image if exists
        if ($product->images) {
            Storage::disk('public')->delete($product->images);
        }
        
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}