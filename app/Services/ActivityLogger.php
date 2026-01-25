<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogger
{
    /**
     * Log an activity
     */
    public static function log($action, $model = null, $modelId = null, $details = [])
    {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'details' => $details,
        ]);
    }
    
    /**
     * Log order creation
     */
    public static function logOrderCreated($order)
    {
        return self::log('order.created', 'Order', $order->id, [
            'order_code' => $order->order_code,
            'total_amount' => $order->total_amount,
        ]);
    }
    
    /**
     * Log order approval
     */
    public static function logOrderApproved($order)
    {
        return self::log('order.approved', 'Order', $order->id, [
            'order_code' => $order->order_code,
        ]);
    }
    
    /**
     * Log driver assignment
     */
    public static function logDriverAssigned($order, $driver)
    {
        return self::log('order.driver_assigned', 'Order', $order->id, [
            'order_code' => $order->order_code,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
        ]);
    }
    
    /**
     * Log order cancellation
     */
    public static function logOrderCancelled($order, $reason = null)
    {
        return self::log('order.cancelled', 'Order', $order->id, [
            'order_code' => $order->order_code,
            'reason' => $reason,
        ]);
    }
    
    /**
     * Log order completion by driver
     */
    public static function logOrderCompleted($order, $driver)
    {
        return self::log('order.completed', 'Order', $order->id, [
            'order_code' => $order->order_code,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'completed_at' => now()->toDateTimeString(),
        ]);
    }
}
