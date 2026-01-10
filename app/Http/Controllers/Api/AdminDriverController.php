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
}
