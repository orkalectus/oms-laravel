<?php

namespace App\Clients;

class DummyJsonApiClient extends BaseApiClient
{
    protected string $serviceName = 'dummyjson';
    protected string $baseUrl = '';

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.dummyjson.url', 'https://dummyjson.com'), '/');
        parent::__construct();
    }

    public function getProducts(int $limit = 20, int $skip = 0): array
    {
        $response = $this->get('/products', [
            'limit' => $limit,
            'skip' => $skip,
        ], ['action' => 'get_products', 'limit' => $limit, 'skip' => $skip]);

        if (!$response['success'] || empty($response['data']['products'])) {
            return [];
        }

        return array_map(fn($p) => $this->normalizeProduct($p), $response['data']['products']);
    }

    public function getProduct(int $productId): ?array
    {
        $response = $this->get("/products/{$productId}", [], [
            'action' => 'get_product',
            'product_id' => $productId,
        ]);

        if (!$response['success'] || empty($response['data'])) {
            return null;
        }

        return $this->normalizeProduct($response['data']);
    }

    public function searchProducts(string $query, int $limit = 20): array
    {
        $response = $this->get('/products/search', [
            'q' => $query,
            'limit' => $limit,
        ], ['action' => 'search_products', 'query' => $query]);

        if (!$response['success'] || empty($response['data']['products'])) {
            return [];
        }

        return array_map(fn($p) => $this->normalizeProduct($p), $response['data']['products']);
    }

    public function getCategories(): array
    {
        $response = $this->get('/products/category-list', [], ['action' => 'get_categories']);
        return $response['success'] ? $response['data'] : [];
    }

    /**
     * Normalize DummyJSON product to common format
     */
    private function normalizeProduct(array $product): array
    {
        return [
            'id' => 'dummyjson_' . $product['id'],
            'external_id' => $product['id'],
            'source' => 'dummyjson',
            'name' => $product['title'] ?? '',
            'description' => $product['description'] ?? '',
            'price' => (float) ($product['price'] ?? 0),
            'price_idr' => (float) ($product['price'] ?? 0) * 15000,
            'category' => $product['category'] ?? '',
            'image' => $product['thumbnail'] ?? ($product['images'][0] ?? ''),
            'images' => $product['images'] ?? [],
            'rating' => [
                'rate' => $product['rating'] ?? 0,
                'count' => $product['reviews'] ? count($product['reviews']) : 0,
            ],
            'stock' => (int) ($product['stock'] ?? 0),
            'weight' => (int) (($product['weight'] ?? 0.5) * 1000), // convert kg to grams
            'sku' => $product['sku'] ?? ('DJX-' . str_pad($product['id'], 5, '0', STR_PAD_LEFT)),
            'brand' => $product['brand'] ?? null,
            'discount_percentage' => $product['discountPercentage'] ?? 0,
            'availability_status' => $product['availabilityStatus'] ?? 'In Stock',
        ];
    }
}
