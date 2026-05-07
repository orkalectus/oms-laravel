<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\PaymentSuccess;
use App\Jobs\SendEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessOrderAfterPayment implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'orders';
    public int $tries = 3;
    public int $backoff = 30;

    public function handle(PaymentSuccess $event): void
    {
        $order = $event->order;
        $payment = $event->payment;

        if (!$order) {
            Log::warning('ProcessOrderAfterPayment: Order not found', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        Log::info('Processing order after payment success', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);

        if ($order->status === OrderStatus::PAID) {
            $order->transitionTo(OrderStatus::PROCESSING, 'Order queued for fulfillment');
        }

        SendEmailNotification::dispatch(
            $order->user->email,
            'payment_success',
            [
                'order_number' => $order->order_number,
                'amount' => 'Rp ' . number_format($payment->amount, 0, ',', '.'),
                'payment_method' => $payment->payment_method,
                'payment_code' => $payment->payment_code,
            ]
        )->onQueue('notifications');
    }

    public function failed(PaymentSuccess $event, \Throwable $exception): void
    {
        Log::error('ProcessOrderAfterPayment listener failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
