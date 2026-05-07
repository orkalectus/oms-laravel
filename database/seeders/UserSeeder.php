<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin / Test user
        User::firstOrCreate(
            ['email' => 'admin@oms.com'],
            [
                'name' => 'OMS Admin',
                'password' => Hash::make('password'),
                'phone' => '081234567890',
                'address' => 'Jl. Sudirman No. 1',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12190',
                'city_id' => 24,
            ]
        );

        // Sample customer
        User::firstOrCreate(
            ['email' => 'customer@oms.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'phone' => '08198765432',
                'address' => 'Jl. Gatot Subroto No. 45, RT 02/RW 03',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40111',
                'city_id' => 151,
            ]
        );

        // Second customer from Surabaya
        User::firstOrCreate(
            ['email' => 'customer2@oms.com'],
            [
                'name' => 'Siti Rahayu',
                'password' => Hash::make('password'),
                'phone' => '082112345678',
                'address' => 'Jl. Pemuda No. 17, Blok A',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60111',
                'city_id' => 399,
            ]
        );

        $this->command->info('Users seeded successfully.');
        $this->command->table(
            ['Email', 'Password'],
            [
                ['admin@oms.com', 'password'],
                ['customer@oms.com', 'password'],
                ['customer2@oms.com', 'password'],
            ]
        );
    }
}
