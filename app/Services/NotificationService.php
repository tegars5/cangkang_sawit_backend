<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    protected $messaging;
    
   public function __construct()
{
    try {
        if (config('firebase.credentials')) {
            $factory = (new Factory)->withServiceAccount(
                base_path(config('firebase.credentials'))
            );
            $this->messaging = $factory->createMessaging();
        }
    } catch (\Throwable $e) {
        \Log::error('Firebase init failed: ' . $e->getMessage());
        $this->messaging = null;
    }
}

    
    /**
     * Send notification to a specific user
     */
    public function sendToUser($userId, $title, $body, $data = [])
    {
        if (!$this->messaging) {
            \Log::warning('Firebase not configured, skipping notification');
            return false;
        }
        
        $user = User::find($userId);
        
        if (!$user || !$user->fcm_token) {
            return false;
        }
        
        try {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);
                
            $this->messaging->send($message);
            return true;
        } catch (\Exception $e) {
            \Log::error('FCM notification failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send order notification to mitra
     */
    public function sendOrderNotification($order, $title, $body, $type)
    {
        return $this->sendToUser(
            $order->user_id,
            $title,
            $body,
            [
                'type' => $type,
                'order_id' => $order->id,
                'order_code' => $order->order_code
            ]
        );
    }
    
    /**
     * Send driver notification
     */
    public function sendDriverNotification($driverId, $title, $body, $data = [])
    {
        return $this->sendToUser($driverId, $title, $body, array_merge($data, ['type' => 'driver_notification']));
    }
}
