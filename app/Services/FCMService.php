<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FCMService
{
    public static function sendToAdmins($title, $body, $data = [])
    {
        // 🛠️ FIX SSL ISSUE AUTOMATICALLY (Runtime)
        $caPath = storage_path('app/cacert.pem');
        if (file_exists($caPath)) {
            ini_set('curl.cainfo', $caPath);
            ini_set('openssl.cafile', $caPath);
        }

        // 1. Get all Admin FCM Tokens
        $tokens = User::where('role', 'admin')
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        // ⚠️ Filter tokens that are empty or null
        $tokens = array_filter($tokens);

        if (empty($tokens)) {
            Log::info("FCM: No admin tokens found.");
            return;
        }

        // 2. Setup Factory & Messaging
        // Gunakan path dari env atau fallback ke storage/app/firebase_credentials.json
        $envPath = env('FIREBASE_CREDENTIALS');
        
        if ($envPath) {
            // Jika path relative, anggap dari base_path
            $credentialsPath = file_exists($envPath) ? $envPath : base_path($envPath);
        } else {
            $credentialsPath = storage_path('app/firebase_credentials.json');
        }

        if (!file_exists($credentialsPath)) {
            Log::error("FCM Error: File credentials tidak ditemukan di: " . $credentialsPath);
            return;
        }

        try {
            // Revert to standard Factory
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            
            $messaging = $factory->createMessaging();

            // 4. Send Manual Loop (Avoid Batch API 404)
            $successCount = 0;
            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification(Notification::create($title, $body))
                        ->withData($data);
                    
                    $messaging->send($message);
                    $successCount++;
                } catch (\Exception $ex) {
                    Log::error("FCM Send Error ({$token}): " . $ex->getMessage());
                }
            }

            Log::info("FCM Loop Finished. Sent Count: " . $successCount);

        } catch (\Exception $e) {
            Log::error("FCM Exception: " . $e->getMessage());
        }
    }

    // 🆕 Method Baru: Kirim ke 1 User Spesifik (Driver/Mitra)
    public static function sendToUser($userId, $title, $body, $data = [])
    {
        $user = User::find($userId);
        if (!$user || !$user->fcm_token) {
            Log::info("FCM: User {$userId} not found or no token.");
            return;
        }

        // Setup Factory
        $envPath = env('FIREBASE_CREDENTIALS');
        $credentialsPath = file_exists($envPath) ? $envPath : base_path($envPath);
        
        // 🛠️ SSL Fix
        $caPath = storage_path('app/cacert.pem');
        if (file_exists($caPath)) {
            ini_set('curl.cainfo', $caPath);
            ini_set('openssl.cafile', $caPath);
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $messaging->send($message);
            Log::info("FCM Sent to User {$userId} ({$user->name})");

        } catch (\Exception $e) {
            Log::error("FCM User Error: " . $e->getMessage());
        }
    }
}
