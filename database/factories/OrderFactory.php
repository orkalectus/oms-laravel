<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 50000, 5000000);
        $shippingCost = $this->faker->randomFloat(2, 9000, 50000);

        return [
            'user_id' => User::factory(),
            'idempotency_key' => Str::uuid()->toString(),
            'status' => OrderStatus::CREATED,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total_amount' => $subtotal + $shippingCost,
            'currency' => 'IDR',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(['status' => OrderStatus::PENDING_PAYMENT]);
    }

    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::PAID]);
    }

    public function processing(): static
    {
        return $this->state(['status' => OrderStatus::PROCESSING]);
    }

    public function shipped(): static
    {
        return $this->state(['status' => OrderStatus::SHIPPED]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => OrderStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_reason' => 'Cancelled by user',
        ]);
    }
}
