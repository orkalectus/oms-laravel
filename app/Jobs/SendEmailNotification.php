<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly string $email,
        private readonly string $template,
        private readonly array $data = []
    ) {}

    public function handle(): void
    {
        Log::info('Sending email notification', [
            'to' => $this->email,
            'template' => $this->template,
        ]);

        // Log email (since using mail driver = log in development)
        $subject = $this->getSubject();
        $body = $this->getBody();

        // In production, use proper Mailable class
        // Mail::to($this->email)->send(new OrderMail($this->template, $this->data));

        Log::channel('single')->info("[EMAIL SENT] To: {$this->email} | Subject: {$subject}", [
            'template' => $this->template,
            'data' => $this->data,
            'body_preview' => substr($body, 0, 200),
        ]);
    }

    private function getSubject(): string
    {
        return match($this->template) {
            'order_confirmation' => "Order Confirmed - #{$this->data['order_number']}",
            'payment_success' => "Payment Successful - #{$this->data['order_number']}",
            'payment_failed' => "Payment Failed - #{$this->data['order_number']}",
            'order_shipped' => "Your Order is on the Way - #{$this->data['order_number']}",
            'order_cancelled' => "Order Cancelled - #{$this->data['order_number']}",
            'order_completed' => "Order Completed - #{$this->data['order_number']}",
            default => "OMS Notification",
        };
    }

    private function getBody(): string
    {
        return match($this->template) {
            'order_confirmation' => "Your order #{$this->data['order_number']} has been received. Total: {$this->data['total']}",
            'payment_success' => "Payment of {$this->data['amount']} for order #{$this->data['order_number']} was successful.",
            'payment_failed' => "Payment for order #{$this->data['order_number']} failed. Reason: {$this->data['reason']}",
            'order_shipped' => "Order #{$this->data['order_number']} has been shipped via {$this->data['courier']}. Tracking: {$this->data['tracking_number']}",
            'order_cancelled' => "Order #{$this->data['order_number']} has been cancelled. Reason: {$this->data['reason']}",
            default => "Notification from OMS",
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendEmailNotification job failed', [
            'email' => $this->email,
            'template' => $this->template,
            'error' => $exception->getMessage(),
        ]);
    }
}
