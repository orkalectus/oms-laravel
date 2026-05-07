<?php

namespace App\Clients;

use Illuminate\Support\Str;

class PaymentGatewayClient extends BaseApiClient
{
    protected string $serviceName = 'payment_gateway';
    protected string $baseUrl = '';
    private bool $simulate;

    public function __construct()
    {
        $this->baseUrl = config('services.payment.url', 'https://payment-simulator.local');
        $this->simulate = config('services.payment.simulate', true);
        parent::__construct();
    }


    public function createPayment(array $payload): array
    {
        if ($this->simulate) {
            return $this->simulateCreatePayment($payload);
        }

        $response = $this->post('/v1/charge', $payload, [
            'action' => 'create_payment',
            'order_id' => $payload['order_id'] ?? null,
        ]);

        return $response['success'] ? $response['data'] : [];
    }


    public function getPaymentStatus(string $transactionId): array
    {
        if ($this->simulate) {
            return $this->simulateGetStatus($transactionId);
        }

        $response = $this->get("/v1/transaction/{$transactionId}/status", [], [
            'action' => 'check_payment_status',
            'transaction_id' => $transactionId,
        ]);

        return $response['success'] ? $response['data'] : [];
    }


    public function cancelPayment(string $transactionId): array
    {
        if ($this->simulate) {
            return ['success' => true, 'message' => 'Payment cancelled [SIMULATED]'];
        }

        $response = $this->post("/v1/transaction/{$transactionId}/cancel", [], [
            'action' => 'cancel_payment',
            'transaction_id' => $transactionId,
        ]);

        return $response['success'] ? $response['data'] : ['success' => false];
    }


    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('services.payment.webhook_secret', 'webhook-secret-key');
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }


    private function simulateCreatePayment(array $payload): array
    {
        $transactionId = 'TXN-' . strtoupper(Str::random(12));
        $method = $payload['payment_method'] ?? 'bank_transfer';

        return [
            'transaction_id' => $transactionId,
            'order_id' => $payload['order_id'] ?? null,
            'payment_code' => $payload['payment_code'] ?? null,
            'status' => 'pending',
            'payment_method' => $method,
            'payment_channel' => $this->getPaymentChannel($method),
            'amount' => $payload['amount'] ?? 0,
            'currency' => 'IDR',
            'va_number' => $method === 'bank_transfer' ? '8877' . rand(100000000, 999999999) : null,
            'qr_code' => $method === 'qris' ? 'https://api.qrserver.com/v1/create-qr-code/?data=' . $transactionId : null,
            'payment_url' => 'https://payment-simulator.local/pay/' . $transactionId,
            'expired_at' => now()->addHours(24)->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'simulated' => true,
        ];
    }

    private function simulateGetStatus(string $transactionId): array
    {
        return [
            'transaction_id' => $transactionId,
            'status' => 'pending',
            'simulated' => true,
        ];
    }

    private function getPaymentChannel(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'bca_va',
            'credit_card' => 'visa',
            'e_wallet' => 'gopay',
            'qris' => 'qris',
            'convenience_store' => 'indomaret',
            default => 'bank_transfer',
        };
    }
}
