<?php

namespace App\Repositories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Support\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function findByOrderId(int $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)->latest()->first();
    }

    public function findByPaymentCode(string $code): ?Payment
    {
        return Payment::where('payment_code', $code)->first();
    }

    public function findByGatewayTransactionId(string $transactionId): ?Payment
    {
        return Payment::where('gateway_transaction_id', $transactionId)->first();
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment->fresh();
    }

    public function getPendingExpired(): Collection
    {
        return Payment::where('status', PaymentStatus::PENDING->value)
            ->where('expired_at', '<', now())
            ->get();
    }
}
