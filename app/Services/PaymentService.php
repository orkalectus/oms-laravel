<?php

namespace App\Services;

use App\Clients\PaymentGatewayClient;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSuccess;
use App\Jobs\ProcessPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayClient $gatewayClient,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Initiate payment for an order
     */
    public function initiatePayment(Order $order, string $paymentMethod, array $options = []): Payment
    {
        if ($order->status !== OrderStatus::CREATED) {
            throw new \RuntimeException(
                "Cannot initiate payment for order in status: {$order->status->value}"
            );
        }

        // Check for existing pending payment
        $existingPayment = $this->paymentRepository->findByOrderId($order->id);
        if ($existingPayment && $existingPayment->status === PaymentStatus::PENDING) {
            return $existingPayment;
        }

        return DB::transaction(function () use ($order, $paymentMethod, $options) {
            // Create payment record first
            $payment = $this->paymentRepository->create([
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'status' => PaymentStatus::PENDING,
                'amount' => $order->total_amount,
                'currency' => 'IDR',
                'expired_at' => now()->addHours(24),
                'metadata' => $options,
            ]);

            // Transition order to PENDING_PAYMENT
            $order->transitionTo(OrderStatus::PENDING_PAYMENT, 'Payment initiated');

            // Dispatch async job to call gateway
            ProcessPayment::dispatch($payment, $paymentMethod, $options)
                ->onQueue('payments');

            Log::info('Payment initiated', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'method' => $paymentMethod,
            ]);

            return $payment;
        });
    }

    /**
     * Handle payment webhook callback
     */
    public function handleWebhook(array $payload, string $signature): bool
    {
        // Verify signature
        $rawPayload = json_encode($payload);
        if (!$this->gatewayClient->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Invalid webhook signature received', ['payload' => $payload]);
            return false;
        }

        $transactionId = $payload['transaction_id'] ?? null;
        $status = $payload['transaction_status'] ?? null;

        if (!$transactionId || !$status) {
            Log::error('Invalid webhook payload', ['payload' => $payload]);
            return false;
        }

        $payment = $this->paymentRepository->findByGatewayTransactionId($transactionId);
        if (!$payment) {
            Log::warning('Payment not found for transaction', ['transaction_id' => $transactionId]);
            return false;
        }

        return DB::transaction(function () use ($payment, $payload, $status) {
            // Update payment with webhook data
            $this->paymentRepository->update($payment, [
                'webhook_payload' => $payload,
                'gateway_response' => $payload,
            ]);

            return match($status) {
                'settlement', 'capture', 'success' => $this->handlePaymentSuccess($payment, $payload),
                'deny', 'cancel', 'expire', 'failure' => $this->handlePaymentFailure($payment, $payload),
                'pending' => true, // No action needed
                default => false,
            };
        });
    }

    /**
     * Simulate payment success (for testing)
     */
    public function simulateSuccess(Payment $payment): void
    {
        $this->handlePaymentSuccess($payment, [
            'transaction_id' => $payment->gateway_transaction_id,
            'transaction_status' => 'settlement',
            'payment_type' => $payment->payment_method,
            'settlement_time' => now()->toDateTimeString(),
            'simulated' => true,
        ]);
    }

    /**
     * Simulate payment failure (for testing)
     */
    public function simulateFailure(Payment $payment, string $reason = 'Payment declined'): void
    {
        $this->handlePaymentFailure($payment, [
            'transaction_id' => $payment->gateway_transaction_id,
            'transaction_status' => 'deny',
            'status_message' => $reason,
            'simulated' => true,
        ]);
    }

    private function handlePaymentSuccess(Payment $payment, array $payload): bool
    {
        if ($payment->status === PaymentStatus::SUCCESS) {
            return true; // Already processed
        }

        $this->paymentRepository->update($payment, [
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
            'gateway_response' => $payload,
        ]);

        $order = $payment->order;
        if ($order && $order->status === OrderStatus::PENDING_PAYMENT) {
            $order->transitionTo(OrderStatus::PAID, 'Payment confirmed');
        }

        event(new PaymentSuccess($payment, $order));

        Log::info('Payment successful', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
        ]);

        return true;
    }

    private function handlePaymentFailure(Payment $payment, array $payload): bool
    {
        $this->paymentRepository->update($payment, [
            'status' => PaymentStatus::FAILED,
            'failed_reason' => $payload['status_message'] ?? 'Payment failed',
            'gateway_response' => $payload,
        ]);

        $order = $payment->order;
        if ($order && $order->status === OrderStatus::PENDING_PAYMENT) {
            $order->transitionTo(OrderStatus::FAILED, 'Payment failed: ' . ($payload['status_message'] ?? ''));
        }

        event(new PaymentFailed($payment, $order));

        Log::warning('Payment failed', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'reason' => $payload['status_message'] ?? 'Unknown',
        ]);

        return true;
    }
}
