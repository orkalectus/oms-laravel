<?php

namespace App\Clients;

use Illuminate\Support\Facades\Log;

class RajaOngkirClient extends BaseApiClient
{
    protected string $serviceName = 'rajaongkir';
    protected string $baseUrl = '';
    private bool $simulate;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.rajaongkir.url', 'https://api.rajaongkir.com/starter'), '/');
        $this->simulate = config('services.rajaongkir.simulate', true);
        parent::__construct();
    }

    protected function getDefaultHeaders(): array
    {
        return array_merge(parent::getDefaultHeaders(), [
            'key' => config('services.rajaongkir.key', ''),
        ]);
    }

    public function getCities(?int $provinceId = null): array
    {
        if ($this->simulate) {
            return $this->simulatedCities($provinceId);
        }

        $query = $provinceId ? ['province' => $provinceId] : [];
        $response = $this->get('/city', $query, ['action' => 'get_cities']);

        if (!$response['success']) {
            return $this->simulatedCities($provinceId);
        }

        return $response['data']['rajaongkir']['results'] ?? [];
    }

    public function calculateShipping(
        int $originCityId,
        int $destinationCityId,
        int $weightGrams,
        string $courier = 'jne'
    ): array {
        if ($this->simulate) {
            return $this->simulatedShipping($originCityId, $destinationCityId, $weightGrams, $courier);
        }

        $response = $this->post('/cost', [
            'origin' => $originCityId,
            'destination' => $destinationCityId,
            'weight' => $weightGrams,
            'courier' => $courier,
        ], ['action' => 'calculate_shipping', 'courier' => $courier]);

        if (!$response['success']) {
            Log::warning('RajaOngkir API failed, using simulation fallback');
            return $this->simulatedShipping($originCityId, $destinationCityId, $weightGrams, $courier);
        }

        $results = $response['data']['rajaongkir']['results'][0]['costs'] ?? [];

        return array_map(function ($cost) use ($courier) {
            return [
                'courier' => strtoupper($courier),
                'service' => $cost['service'],
                'description' => $cost['description'],
                'cost' => $cost['cost'][0]['value'] ?? 0,
                'etd' => $cost['cost'][0]['etd'] ?? '?',
                'note' => $cost['cost'][0]['note'] ?? '',
            ];
        }, $results);
    }

    public function getProvinces(): array
    {
        if ($this->simulate) {
            return $this->simulatedProvinces();
        }

        $response = $this->get('/province', [], ['action' => 'get_provinces']);
        return $response['data']['rajaongkir']['results'] ?? $this->simulatedProvinces();
    }


    private function simulatedCities(?int $provinceId = null): array
    {
        $cities = [
            ['city_id' => '1', 'province_id' => '1', 'province' => 'Bali', 'type' => 'Kota', 'city_name' => 'Denpasar', 'postal_code' => '80111'],
            ['city_id' => '22', 'province_id' => '6', 'province' => 'DKI Jakarta', 'type' => 'Kota', 'city_name' => 'Jakarta Barat', 'postal_code' => '11220'],
            ['city_id' => '23', 'province_id' => '6', 'province' => 'DKI Jakarta', 'type' => 'Kota', 'city_name' => 'Jakarta Pusat', 'postal_code' => '10540'],
            ['city_id' => '24', 'province_id' => '6', 'province' => 'DKI Jakarta', 'type' => 'Kota', 'city_name' => 'Jakarta Selatan', 'postal_code' => '12010'],
            ['city_id' => '151', 'province_id' => '9', 'province' => 'Jawa Barat', 'type' => 'Kota', 'city_name' => 'Bandung', 'postal_code' => '40111'],
            ['city_id' => '152', 'province_id' => '10', 'province' => 'Jawa Tengah', 'type' => 'Kota', 'city_name' => 'Semarang', 'postal_code' => '50111'],
            ['city_id' => '399', 'province_id' => '11', 'province' => 'Jawa Timur', 'type' => 'Kota', 'city_name' => 'Surabaya', 'postal_code' => '60111'],
            ['city_id' => '114', 'province_id' => '34', 'province' => 'Yogyakarta', 'type' => 'Kota', 'city_name' => 'Yogyakarta', 'postal_code' => '55111'],
            ['city_id' => '455', 'province_id' => '21', 'province' => 'Sulawesi Selatan', 'type' => 'Kota', 'city_name' => 'Makassar', 'postal_code' => '90111'],
            ['city_id' => '318', 'province_id' => '1', 'province' => 'Sumatera Utara', 'type' => 'Kota', 'city_name' => 'Medan', 'postal_code' => '20111'],
        ];

        if ($provinceId) {
            return array_filter($cities, fn($c) => $c['province_id'] == $provinceId);
        }

        return $cities;
    }

    private function simulatedProvinces(): array
    {
        return [
            ['province_id' => '1', 'province' => 'Bali'],
            ['province_id' => '6', 'province' => 'DKI Jakarta'],
            ['province_id' => '9', 'province' => 'Jawa Barat'],
            ['province_id' => '10', 'province' => 'Jawa Tengah'],
            ['province_id' => '11', 'province' => 'Jawa Timur'],
            ['province_id' => '34', 'province' => 'Yogyakarta'],
            ['province_id' => '21', 'province' => 'Sulawesi Selatan'],
            ['province_id' => '1', 'province' => 'Sumatera Utara'],
        ];
    }

    private function simulatedShipping(
        int $originCityId,
        int $destinationCityId,
        int $weightGrams,
        string $courier
    ): array {
        $baseRates = [
            'jne' => ['REG' => 9000, 'YES' => 19000, 'OKE' => 7000],
            'jnt' => ['REG' => 8000, 'EZ' => 15000],
            'sicepat' => ['REG' => 8500, 'BEST' => 17000, 'GOKIL' => 7500],
            'pos' => ['Pos Reguler' => 7000, 'Pos Kilat Khusus' => 14000],
            'tiki' => ['REG' => 9000, 'ONS' => 19000, 'ECO' => 7500],
        ];

        $courierRates = $baseRates[strtolower($courier)] ?? $baseRates['jne'];
        $weightKg = max(1, ceil($weightGrams / 1000));
        $distanceMultiplier = ($originCityId !== $destinationCityId) ? 1.5 : 1;

        $services = [];
        foreach ($courierRates as $service => $basePrice) {
            $cost = (int) ($basePrice * $weightKg * $distanceMultiplier);
            $etd = match (true) {
                str_contains(strtolower($service), 'yes') || str_contains(strtolower($service), 'ons') => '1',
                str_contains(strtolower($service), 'reg') => '2-3',
                str_contains(strtolower($service), 'oke') || str_contains(strtolower($service), 'eco') => '4-6',
                default => '2-3',
            };

            $services[] = [
                'courier' => strtoupper($courier),
                'service' => $service,
                'description' => strtoupper($courier) . ' ' . $service,
                'cost' => $cost,
                'etd' => $etd . ' HARI',
                'note' => '[SIMULATED]',
            ];
        }

        return $services;
    }
}
