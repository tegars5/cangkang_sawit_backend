<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\DeliveryOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DistanceController
{
    /**
     * Calculate distance and duration from warehouse to order destination
     * 
     * @param Order $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderDistance(Order $order)
    {
        $user = auth()->user();

        // Check authorization
        if ($user->role === 'mitra' && $order->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You do not have access to this order.',
            ], 403);
        }

        // Check if destination coordinates are set
        if (is_null($order->destination_lat) || is_null($order->destination_lng)) {
            return response()->json([
                'message' => 'Order destination coordinates not set.',
            ], 400);
        }

        // Get warehouse coordinates from config
        $originLat = config('services.warehouse.lat');
        $originLng = config('services.warehouse.lng');

        // Get destination coordinates from order
        $destLat = $order->destination_lat;
        $destLng = $order->destination_lng;

        try {
            // Call Google Directions API
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destLat},{$destLng}",
                'key' => config('services.google_maps.key'),
            ]);

            // Check if API call was successful
            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Failed to fetch distance information.',
                ], 500);
            }

            $data = $response->json();

            // Check if Google API returned valid data
            if ($data['status'] !== 'OK' || empty($data['routes'])) {
                return response()->json([
                    'message' => 'Failed to fetch distance information.',
                    'error' => $data['status'] ?? 'Unknown error',
                ], 422);
            }

            // Extract distance and duration from first route
            $leg = $data['routes'][0]['legs'][0];

            return response()->json([
                'order_id' => $order->id,
                'origin' => [
                    'lat' => (float) $originLat,
                    'lng' => (float) $originLng,
                ],
                'destination' => [
                    'lat' => (float) $destLat,
                    'lng' => (float) $destLng,
                ],
                'distance_text' => $leg['distance']['text'],
                'distance_value' => $leg['distance']['value'],
                'duration_text' => $leg['duration']['text'],
                'duration_value' => $leg['duration']['value'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch distance information.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate distance and duration from driver's last location to order destination
     * 
     * @param Order $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function driverDistance(Order $order)
    {
        $user = auth()->user();

        // Check authorization
        if ($user->role === 'mitra' && $order->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You do not have access to this order.',
            ], 403);
        }

        // Check if destination coordinates are set
        if (is_null($order->destination_lat) || is_null($order->destination_lng)) {
            return response()->json([
                'message' => 'Order destination coordinates not set.',
            ], 400);
        }

        // Find delivery order for this order
        $deliveryOrder = DeliveryOrder::where('order_id', $order->id)
            ->with('driver')
            ->first();

        if (!$deliveryOrder) {
            return response()->json([
                'message' => 'No driver location available for this order.',
            ], 400);
        }

        // Get last driver location from delivery tracks
        $lastTrack = $deliveryOrder->deliveryTracks()
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$lastTrack) {
            return response()->json([
                'message' => 'No driver location available for this order.',
            ], 400);
        }

        // Get driver's last location
        $originLat = $lastTrack->lat;
        $originLng = $lastTrack->lng;

        // Get destination coordinates from order
        $destLat = $order->destination_lat;
        $destLng = $order->destination_lng;

        try {
            // Call Google Directions API
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destLat},{$destLng}",
                'key' => config('services.google_maps.key'),
            ]);

            // Check if API call was successful
            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Failed to fetch distance information.',
                ], 500);
            }

            $data = $response->json();

            // Check if Google API returned valid data
            if ($data['status'] !== 'OK' || empty($data['routes'])) {
                return response()->json([
                    'message' => 'Failed to fetch distance information.',
                    'error' => $data['status'] ?? 'Unknown error',
                ], 422);
            }

            // Extract distance and duration from first route
            $leg = $data['routes'][0]['legs'][0];

            return response()->json([
                'order_id' => $order->id,
                'driver' => [
                    'id' => $deliveryOrder->driver->id,
                    'name' => $deliveryOrder->driver->name,
                ],
                'origin' => [
                    'lat' => (float) $originLat,
                    'lng' => (float) $originLng,
                ],
                'destination' => [
                    'lat' => (float) $destLat,
                    'lng' => (float) $destLng,
                ],
                'distance_text' => $leg['distance']['text'],
                'distance_value' => $leg['distance']['value'],
                'duration_text' => $leg['duration']['text'],
                'duration_value' => $leg['duration']['value'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch distance information.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
