<?php

namespace App\Repositories\Interfaces;

use App\Models\Payment;
use Illuminate\Support\Collection;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;

    public function findByOrderId(int $orderId): ?Payment;

    public function findByPaymentCode(string $code): ?Payment;

    public function findByGatewayTransactionId(string $transactionId): ?Payment;

    public function update(Payment $payment, array $data): Payment;

    public function getPendingExpired(): Collection;
}
