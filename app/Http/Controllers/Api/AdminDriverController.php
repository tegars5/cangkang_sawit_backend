<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDriverController extends Controller
{
    /**
     * List all drivers with their availability status
     */
    public function index(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }
        
        $perPage = $request->input('per_page', 15);
        
        $drivers = User::where('role', 'driver')
            ->withCount([
                'deliveryOrders',
                'deliveryOrders as completed_deliveries' => function($q) {
                    $q->where('status', 'completed');
                }
            ])
            ->paginate($perPage);
        
        return response()->json($drivers);
    }
    
    /**
     * List only available drivers
     */
    public function available()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }
        
        $drivers = User::where('role', 'driver')
            ->where('availability_status', 'available')
            ->get();
        
        return response()->json($drivers);
    }
    
    /**
     * Create a new driver account (Admin only)
     */
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'vehicle_type' => 'nullable|string|max:50',
            'vehicle_number' => 'nullable|string|max:20',
        ]);
        
        $driver = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Hash::make($request->password),
            'role' => 'driver', // Hardcoded to driver
            'phone' => $request->phone,
            'vehicle_type' => $request->vehicle_type,
            'vehicle_number' => $request->vehicle_number,
            'availability_status' => 'available', // Default status
        ]);
        
        return response()->json([
            'message' => 'Driver created successfully',
            'driver' => $driver,
        ], 201);
    }
}
