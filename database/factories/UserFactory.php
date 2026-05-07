<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => '08' . $this->faker->numerify('#########'),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'province' => 'Jawa Barat',
            'postal_code' => $this->faker->postcode(),
            'city_id' => $this->faker->numberBetween(1, 500),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
