<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\DuplicateOrderException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * List orders for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $orders = app(\App\Repositories\Interfaces\OrderRepositoryInterface::class)
            ->findByUserId(auth()->id(), $request->get('per_page', 15));

        return $this->success($orders, 'Orders retrieved successfully');
    }

    /**
     * Create a new order
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idempotency_key' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:500',
            'shipping' => 'nullable|array',
            'shipping.destination_city_id' => 'required_with:shipping|integer',
            'shipping.courier' => 'required_with:shipping|string|in:jne,jnt,sicepat,pos,tiki',
            'shipping.service' => 'nullable|string',
            'shipping.recipient_name' => 'nullable|string|max:255',
            'shipping.recipient_phone' => 'nullable|string|max:20',
            'shipping.recipient_address' => 'nullable|string|max:500',
            'shipping.recipient_city' => 'nullable|string|max:100',
            'shipping.recipient_province' => 'nullable|string|max:100',
            'shipping.recipient_postal_code' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $order = $this->orderService->createOrder(auth()->user(), $request->all());

            return $this->success($order->load(['items', 'shipping']), 'Order created successfully', 201);

        } catch (DuplicateOrderException $e) {
            return $this->success(
                $e->existingOrder->load(['items', 'shipping']),
                'Existing order returned (duplicate idempotency key)',
                200
            );

        } catch (InsufficientStockException $e) {
            return $this->error($e->getMessage(), 422);

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);

        } catch (\Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * Show a specific order
     */
    public function show(int $orderId): JsonResponse
    {
        $order = $this->orderService->getOrderForUser($orderId, auth()->user());

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success($order, 'Order retrieved successfully');
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderService->getOrderForUser($orderId, auth()->user());

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $order = $this->orderService->cancelOrder(
                $order,
                $request->get('reason', 'Cancelled by user')
            );

            return $this->success($order, 'Order cancelled successfully');

        } catch (InvalidStatusTransitionException $e) {
            return $this->error($e->getMessage(), 422);

        } catch (\Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * Admin: Update order status
     */
    public function updateStatus(Request $request, int $orderId): JsonResponse
    {
        $order = app(\App\Repositories\Interfaces\OrderRepositoryInterface::class)
            ->findWithRelations($orderId);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $order = $this->orderService->updateStatus(
                $order,
                OrderStatus::from($request->status),
                $request->notes
            );

            return $this->success($order, 'Order status updated successfully');

        } catch (InvalidStatusTransitionException $e) {
            return $this->error($e->getMessage(), 422);

        } catch (\Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * Get order status history
     */
    public function statusHistory(int $orderId): JsonResponse
    {
        $order = $this->orderService->getOrderForUser($orderId, auth()->user());

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success(
            $order->statusHistories()->orderBy('created_at')->get(),
            'Status history retrieved'
        );
    }
}
