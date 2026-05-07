<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Jobs\SendEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentFailedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;

    public function handle(PaymentFailed $event): void
    {
        $order = $event->order;
        $payment = $event->payment;

        if (!$order) {
            return;
        }

        Log::info('Sending payment failed notification', ['order_id' => $order->id]);

        SendEmailNotification::dispatch(
            $order->user->email,
            'payment_failed',
            [
                'order_number' => $order->order_number,
                'amount' => 'Rp ' . number_format($payment->amount, 0, ',', '.'),
                'reason' => $payment->failed_reason ?? 'Payment was declined',
            ]
        )->onQueue('notifications');
    }
}
