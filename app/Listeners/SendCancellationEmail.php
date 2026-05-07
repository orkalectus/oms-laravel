<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Jobs\SendEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCancellationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;

    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        Log::info('Sending cancellation email', [
            'order_id' => $order->id,
            'reason' => $event->reason,
        ]);

        SendEmailNotification::dispatch(
            $order->user->email,
            'order_cancelled',
            [
                'order_number' => $order->order_number,
                'reason' => $event->reason,
                'total' => $order->formatted_total,
            ]
        )->onQueue('notifications');
    }
}
