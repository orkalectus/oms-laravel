<?php

namespace App\Jobs;

use App\Clients\PaymentGatewayClient;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 60;

    public function __construct(
        private readonly Payment $payment,
        private readonly string $paymentMethod,
        private readonly array $options = []
    ) {}

    public function handle(PaymentGatewayClient $gatewayClient, PaymentRepositoryInterface $paymentRepository): void
    {
        Log::info('Processing payment via queue', [
            'payment_id' => $this->payment->id,
            'method' => $this->paymentMethod,
        ]);

        try {
            $gatewayResponse = $gatewayClient->createPayment([
                'order_id' => $this->payment->order_id,
                'payment_code' => $this->payment->payment_code,
                'payment_method' => $this->paymentMethod,
                'amount' => $this->payment->amount,
                'currency' => 'IDR',
                'customer' => [
                    'name' => $this->payment->order->user->name ?? 'Customer',
                    'email' => $this->payment->order->user->email ?? '',
                ],
                'metadata' => $this->options,
            ]);

            if (empty($gatewayResponse)) {
                throw new \RuntimeException('Empty response from payment gateway');
            }

            $paymentRepository->update($this->payment, [
                'status' => PaymentStatus::PROCESSING,
                'gateway_transaction_id' => $gatewayResponse['transaction_id'] ?? null,
                'gateway_reference' => $gatewayResponse['va_number'] ?? $gatewayResponse['qr_code'] ?? null,
                'payment_channel' => $gatewayResponse['payment_channel'] ?? null,
                'gateway_response' => $gatewayResponse,
                'expired_at' => isset($gatewayResponse['expired_at'])
                    ? \Carbon\Carbon::parse($gatewayResponse['expired_at'])
                    : now()->addHours(24),
            ]);

            Log::info('Payment processed successfully', [
                'payment_id' => $this->payment->id,
                'transaction_id' => $gatewayResponse['transaction_id'] ?? 'N/A',
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment processing failed', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $paymentRepository->update($this->payment, [
                    'status' => PaymentStatus::FAILED,
                    'failed_reason' => 'Gateway connection failed after ' . $this->tries . ' attempts',
                ]);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessPayment job permanently failed', [
            'payment_id' => $this->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
