<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Log;

class LogOrderStatusChange
{
    public function handle(OrderStatusChanged $event): void
    {
        Log::info('Order status changed', [
            'order_id' => $event->order->id,
            'order_number' => $event->order->order_number,
            'new_status' => $event->newStatus->value,
            'user_id' => $event->order->user_id,
        ]);
    }
}
