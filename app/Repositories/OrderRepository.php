<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::where('order_number', $orderNumber)->first();
    }

    public function findByIdempotencyKey(string $key): ?Order
    {
        return Order::where('idempotency_key', $key)->first();
    }

    public function findByUserId(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Order::with(['items', 'payment', 'shipping'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function updateStatus(Order $order, OrderStatus $status, ?string $notes = null): Order
    {
        $order->transitionTo($status, $notes);
        return $order->fresh();
    }

    public function getByStatus(OrderStatus $status): Collection
    {
        return Order::where('status', $status->value)->get();
    }

    public function getPendingOrders(): Collection
    {
        return Order::pending()->with(['items', 'payment'])->get();
    }

    /**
     * Lock order for update to prevent race conditions
     */
    public function lockForUpdate(int $orderId): ?Order
    {
        return Order::lockForUpdate()->find($orderId);
    }

    public function findWithRelations(int $id, array $relations = []): ?Order
    {
        $relations = $relations ?: ['items', 'payment', 'shipping', 'statusHistories', 'user'];
        return Order::with($relations)->find($id);
    }
}
