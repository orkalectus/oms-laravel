<?php

namespace App\Services;

use App\Clients\RajaOngkirClient;
use App\Enums\OrderStatus;
use App\Enums\ShippingStatus;
use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly RajaOngkirClient $rajaOngkirClient
    ) {}

    /**
     * Calculate shipping costs
     */
    public function calculateShipping(
        int $originCityId,
        int $destinationCityId,
        int $weightGrams,
        string $courier = 'jne'
    ): array {
        $cacheKey = "shipping:cost:{$originCityId}:{$destinationCityId}:{$weightGrams}:{$courier}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $originCityId, $destinationCityId, $weightGrams, $courier
        ) {
            return $this->rajaOngkirClient->calculateShipping(
                $originCityId,
                $destinationCityId,
                $weightGrams,
                $courier
            );
        });
    }

    /**
     * Get all available couriers' rates
     */
    public function getMultipleCourierRates(
        int $originCityId,
        int $destinationCityId,
        int $weightGrams
    ): array {
        $couriers = ['jne', 'jnt', 'sicepat', 'pos'];
        $results = [];

        foreach ($couriers as $courier) {
            $rates = $this->calculateShipping($originCityId, $destinationCityId, $weightGrams, $courier);
            $results = array_merge($results, $rates);
        }

        // Sort by cost ascending
        usort($results, fn($a, $b) => $a['cost'] <=> $b['cost']);

        return $results;
    }

    /**
     * Create shipping record for an order
     */
    public function createShippingRecord(Order $order, array $shippingData): Shipping
    {
        return $order->shipping()->create([
            'status' => ShippingStatus::PENDING,
            'courier' => strtolower($shippingData['courier'] ?? 'jne'),
            'service' => $shippingData['service'] ?? 'REG',
            'service_description' => $shippingData['description'] ?? '',
            'origin_city_id' => $shippingData['origin_city_id'] ?? null,
            'origin_city' => $shippingData['origin_city'] ?? null,
            'destination_city_id' => $shippingData['destination_city_id'] ?? null,
            'destination_city' => $shippingData['destination_city'] ?? null,
            'recipient_name' => $shippingData['recipient_name'] ?? $order->user->name,
            'recipient_phone' => $shippingData['recipient_phone'] ?? $order->user->phone,
            'recipient_address' => $shippingData['recipient_address'] ?? $order->user->address,
            'recipient_city' => $shippingData['recipient_city'] ?? $order->user->city,
            'recipient_province' => $shippingData['recipient_province'] ?? $order->user->province,
            'recipient_postal_code' => $shippingData['recipient_postal_code'] ?? $order->user->postal_code,
            'weight_grams' => $shippingData['weight_grams'] ?? 500,
            'cost' => $shippingData['cost'] ?? 0,
            'estimated_days' => $shippingData['etd'] ?? '2-3',
            'rajaongkir_response' => $shippingData['rajaongkir_response'] ?? null,
        ]);
    }

    /**
     * Ship an order - generate tracking number
     */
    public function shipOrder(Order $order, array $shipData = []): Shipping
    {
        $shipping = $order->shipping;

        if (!$shipping) {
            throw new \RuntimeException('Shipping record not found for order');
        }

        $trackingNumber = $shipData['tracking_number'] ?? $shipping->generateTrackingNumber();

        $shipping->update([
            'status' => ShippingStatus::IN_TRANSIT,
            'tracking_number' => $trackingNumber,
            'shipped_at' => now(),
            'notes' => $shipData['notes'] ?? null,
        ]);

        $shipping->addTrackingEvent(
            ShippingStatus::IN_TRANSIT->value,
            'Package picked up and in transit',
            $shipData['location'] ?? null
        );

        $order->transitionTo(OrderStatus::SHIPPED, "Shipped via {$shipping->courier} - {$trackingNumber}");

        return $shipping->fresh();
    }

    /**
     * Update tracking status
     */
    public function updateTracking(Shipping $shipping, string $status, string $description, ?string $location = null): void
    {
        $shippingStatus = ShippingStatus::from($status);
        $shipping->update(['status' => $shippingStatus]);
        $shipping->addTrackingEvent($status, $description, $location);

        // Auto-complete order when delivered
        if ($shippingStatus === ShippingStatus::DELIVERED) {
            $shipping->update(['delivered_at' => now()]);
            $order = $shipping->order;
            if ($order && $order->status === OrderStatus::SHIPPED) {
                $order->transitionTo(OrderStatus::COMPLETED, 'Package delivered');
            }
        }
    }

    /**
     * Get cities list
     */
    public function getCities(?int $provinceId = null): array
    {
        $cacheKey = "shipping:cities:" . ($provinceId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL * 24, function () use ($provinceId) {
            return $this->rajaOngkirClient->getCities($provinceId);
        });
    }

    /**
     * Get provinces list
     */
    public function getProvinces(): array
    {
        return Cache::remember('shipping:provinces', self::CACHE_TTL * 24, function () {
            return $this->rajaOngkirClient->getProvinces();
        });
    }
}
