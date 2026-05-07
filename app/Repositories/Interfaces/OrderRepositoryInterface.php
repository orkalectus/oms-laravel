<?php

namespace App\Repositories\Interfaces;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    public function create(array $data): Order;

    public function findById(int $id): ?Order;

    public function findByOrderNumber(string $orderNumber): ?Order;

    public function findByIdempotencyKey(string $key): ?Order;

    public function findByUserId(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function updateStatus(Order $order, OrderStatus $status, ?string $notes = null): Order;

    public function getByStatus(OrderStatus $status): Collection;

    public function getPendingOrders(): Collection;

    public function lockForUpdate(int $orderId): ?Order;

    public function findWithRelations(int $id, array $relations = []): ?Order;
}
