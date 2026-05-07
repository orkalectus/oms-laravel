<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        Log::info('Queueing order confirmation email', [
            'order_id' => $order->id,
            'user_email' => $order->user->email ?? 'unknown',
        ]);

        SendEmailNotification::dispatch(
            $order->user->email,
            'order_confirmation',
            [
                'order_number' => $order->order_number,
                'total' => $order->formatted_total,
                'items_count' => $order->items->count(),
            ]
        )->onQueue('notifications');
    }

    public function failed(OrderCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to queue order confirmation email', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
