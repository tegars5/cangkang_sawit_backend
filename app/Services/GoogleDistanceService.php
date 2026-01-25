<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDistanceService
{
    /**
     * Mengambil jarak dan durasi dari Kantor ke Lokasi Tujuan
     */
    public function getDistanceAndDuration($destLat, $destLng)
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        $originLat = env('WAREHOUSE_LAT');
        $originLng = env('WAREHOUSE_LNG');

        // Proteksi jika data di .env belum lengkap
        if (!$apiKey || !$originLat || !$originLng) {
            Log::error("Google Maps API: Data .env tidak lengkap (API Key/Lat/Lng Kantor)");
            return null;
        }

        try {
            $response = Http::get("https://maps.googleapis.com/maps/api/distancematrix/json", [
                'origins' => "$originLat,$originLng",
                'destinations' => "$destLat,$destLng",
                'key' => $apiKey,
                'mode' => 'driving',
            ]);

            if ($response->successful() && $response['status'] == 'OK') {
                $element = $response['rows'][0]['elements'][0];
                
                if ($element['status'] == 'OK') {
                    return [
                        'distance_km' => round($element['distance']['value'] / 1000, 2),
                        'duration_min' => round($element['duration']['value'] / 60),
                    ];
                } else {
                    Log::warning("Google API Element Error: " . $element['status']);
                }
            } else {
                Log::error("Google API Response Error: " . $response['status']);
            }
        } catch (\Exception $e) {
            Log::error("Gagal menghubungi Google API: " . $e->getMessage());
        }

        return null;
    }
}