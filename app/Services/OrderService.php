<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Exceptions\DuplicateOrderException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductAggregatorService $productService,
        private readonly ShippingService $shippingService
    ) {}

    /**
     * Create a new order - IDEMPOTENT
     */
    public function createOrder(User $user, array $data): Order
    {
        $idempotencyKey = $data['idempotency_key'];

        // Check idempotency - return existing order if key already used
        $existingOrder = $this->orderRepository->findByIdempotencyKey($idempotencyKey);
        if ($existingOrder) {
            Log::info('Duplicate order request detected, returning existing order', [
                'idempotency_key' => $idempotencyKey,
                'order_id' => $existingOrder->id,
            ]);
            throw new DuplicateOrderException(
                'Order with this idempotency key already exists',
                $existingOrder
            );
        }

        return DB::transaction(function () use ($user, $data, $idempotencyKey) {
            // Acquire distributed lock to prevent concurrent duplicate creation
            $lockKey = "order:create:{$idempotencyKey}";
            $lock = Cache::lock($lockKey, 10);

            if (!$lock->get()) {
                throw new \RuntimeException('Order creation in progress, please wait');
            }

            try {
                // Double-check after acquiring lock
                $existingOrder = $this->orderRepository->findByIdempotencyKey($idempotencyKey);
                if ($existingOrder) {
                    throw new DuplicateOrderException('Duplicate order detected after lock', $existingOrder);
                }

                // Validate and enrich items with product data
                $items = $this->validateAndEnrichItems($data['items']);

                // Calculate shipping if origin city provided
                $shippingCost = 0;
                $shippingData = null;
                if (!empty($data['shipping'])) {
                    $totalWeight = array_sum(array_map(
                        fn($item) => ($item['product']['weight'] ?? 500) * $item['quantity'],
                        $items
                    ));

                    $shippingOptions = $this->shippingService->calculateShipping(
                        originCityId: $data['shipping']['origin_city_id'] ?? 23,
                        destinationCityId: $data['shipping']['destination_city_id'],
                        weightGrams: (int) $totalWeight,
                        courier: $data['shipping']['courier'] ?? 'jne'
                    );

                    $selectedService = $data['shipping']['service'] ?? null;
                    if ($selectedService && !empty($shippingOptions)) {
                        $shippingOption = collect($shippingOptions)->firstWhere('service', $selectedService)
                            ?? $shippingOptions[0];
                        $shippingCost = $shippingOption['cost'];
                        $shippingData = array_merge($data['shipping'], $shippingOption);
                    }
                }

                // Calculate totals
                $subtotal = array_sum(array_map(
                    fn($item) => $item['unit_price'] * $item['quantity'],
                    $items
                ));
                $total = $subtotal + $shippingCost;

                // Create order
                $order = $this->orderRepository->create([
                    'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => OrderStatus::CREATED,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total_amount' => $total,
                    'currency' => 'IDR',
                    'notes' => $data['notes'] ?? null,
                    'metadata' => [
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ],
                ]);

                // Create order items with product snapshot
                foreach ($items as $item) {
                    $order->items()->create([
                        'product_id' => $item['product']['id'],
                        'product_snapshot' => $item['product'], // Save snapshot to avoid price changes
                        'product_name' => $item['product']['name'],
                        'product_sku' => $item['product']['sku'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'weight' => $item['product']['weight'] ?? 500,
                    ]);
                }

                // Create shipping record if data available
                if ($shippingData) {
                    $this->shippingService->createShippingRecord($order, $shippingData);
                }

                // Record initial status history
                $order->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => OrderStatus::CREATED->value,
                    'notes' => 'Order created',
                    'changed_by' => $user->id,
                ]);

                // Fire event
                event(new OrderCreated($order));

                Log::info('Order created successfully', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $total,
                ]);

                return $order->load(['items', 'shipping']);

            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Order $order, string $reason = 'Cancelled by user'): Order
    {
        if (!$order->is_cancellable) {
            throw new InvalidStatusTransitionException(
                "Order in status [{$order->status->value}] cannot be cancelled"
            );
        }

        DB::transaction(function () use ($order, $reason) {
            $order->transitionTo(OrderStatus::CANCELLED, $reason);
            event(new OrderCancelled($order, $reason));
        });

        return $order->fresh(['items', 'payment', 'shipping']);
    }

    /**
     * Get order for user (with ownership check)
     */
    public function getOrderForUser(int $orderId, User $user): ?Order
    {
        $order = $this->orderRepository->findWithRelations($orderId);

        if (!$order || $order->user_id !== $user->id) {
            return null;
        }

        return $order;
    }

    /**
     * Update order status (admin action)
     */
    public function updateStatus(Order $order, OrderStatus $newStatus, ?string $notes = null): Order
    {
        DB::transaction(function () use ($order, $newStatus, $notes) {
            $order->transitionTo($newStatus, $notes);
            event(new OrderStatusChanged($order, $newStatus));
        });

        return $order->fresh(['items', 'payment', 'shipping', 'statusHistories']);
    }

    /**
     * Validate items and fetch product data with stock check
     */
    private function validateAndEnrichItems(array $requestItems): array
    {
        $enrichedItems = [];

        foreach ($requestItems as $requestItem) {
            $productId = $requestItem['product_id'];
            $quantity = (int) $requestItem['quantity'];

            // Fetch product from aggregator (with cache)
            $product = $this->productService->getProductForOrder($productId);

            if (!$product) {
                throw new \InvalidArgumentException("Product [{$productId}] not found");
            }

            // Stock check (simulated since external APIs don't have real stock management)
            if (isset($product['stock']) && $product['stock'] < $quantity) {
                throw new InsufficientStockException(
                    "Insufficient stock for product [{$product['name']}]. Available: {$product['stock']}, Requested: {$quantity}"
                );
            }

            $enrichedItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $product['price_idr'] ?? $product['price'],
            ];
        }

        return $enrichedItems;
    }
}
