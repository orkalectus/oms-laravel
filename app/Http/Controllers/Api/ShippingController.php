<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\ShippingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ShippingService $shippingService,
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Calculate shipping cost
     */
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin_city_id' => 'required|integer',
            'destination_city_id' => 'required|integer',
            'weight' => 'required|integer|min:1',
            'courier' => 'nullable|string|in:jne,jnt,sicepat,pos,tiki,all',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $courier = $request->get('courier', 'jne');

        if ($courier === 'all') {
            $rates = $this->shippingService->getMultipleCourierRates(
                $request->origin_city_id,
                $request->destination_city_id,
                $request->weight
            );
        } else {
            $rates = $this->shippingService->calculateShipping(
                $request->origin_city_id,
                $request->destination_city_id,
                $request->weight,
                $courier
            );
        }

        return $this->success([
            'rates' => $rates,
            'origin_city_id' => $request->origin_city_id,
            'destination_city_id' => $request->destination_city_id,
            'weight_grams' => $request->weight,
        ], 'Shipping rates calculated');
    }

    /**
     * Get provinces list
     */
    public function provinces(): JsonResponse
    {
        $provinces = $this->shippingService->getProvinces();
        return $this->success($provinces, 'Provinces retrieved');
    }

    /**
     * Get cities, optionally filtered by province
     */
    public function cities(Request $request): JsonResponse
    {
        $cities = $this->shippingService->getCities($request->get('province_id'));
        return $this->success($cities, 'Cities retrieved');
    }

    /**
     * Track order shipping
     */
    public function track(int $orderId): JsonResponse
    {
        $order = $this->orderRepository->findWithRelations($orderId, ['shipping', 'user']);

        if (!$order || $order->user_id !== auth()->id()) {
            return $this->notFound('Order not found');
        }

        $shipping = $order->shipping;

        if (!$shipping) {
            return $this->notFound('Shipping information not available');
        }

        return $this->success([
            'tracking_number' => $shipping->tracking_number,
            'courier' => strtoupper($shipping->courier),
            'service' => $shipping->service,
            'status' => $shipping->status,
            'estimated_delivery' => $shipping->estimated_delivery_date,
            'shipped_at' => $shipping->shipped_at,
            'delivered_at' => $shipping->delivered_at,
            'recipient' => [
                'name' => $shipping->recipient_name,
                'address' => $shipping->recipient_address,
                'city' => $shipping->recipient_city,
            ],
            'tracking_history' => $shipping->tracking_history ?? [],
        ], 'Tracking information retrieved');
    }

    /**
     * Admin: Ship an order
     */
    public function ship(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderRepository->findWithRelations($orderId, ['shipping', 'user']);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $validator = Validator::make($request->all(), [
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $shipping = $this->shippingService->shipOrder($order, $request->all());

            return $this->success([
                'shipping' => $shipping,
                'order' => $order->fresh(['shipping']),
            ], 'Order shipped successfully');

        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
