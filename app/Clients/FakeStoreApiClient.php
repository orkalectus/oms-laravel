<?php

namespace App\Clients;

class FakeStoreApiClient extends BaseApiClient
{
    protected string $serviceName = 'fakestore';
    protected string $baseUrl = '';

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.fakestore.url', 'https://fakestoreapi.com'), '/');
        parent::__construct();
    }

    public function getProducts(int $limit = 20, int $offset = 0): array
    {
        $response = $this->get('/products', [
            'limit' => $limit,
        ], ['action' => 'get_products', 'limit' => $limit]);

        if (!$response['success']) {
            return [];
        }

        return array_map(fn($p) => $this->normalizeProduct($p), $response['data']);
    }

    public function getProduct(int $productId): ?array
    {
        $response = $this->get("/products/{$productId}", [], ['action' => 'get_product', 'product_id' => $productId]);

        if (!$response['success'] || empty($response['data'])) {
            return null;
        }

        return $this->normalizeProduct($response['data']);
    }

    public function getCategories(): array
    {
        $response = $this->get('/products/categories', [], ['action' => 'get_categories']);
        return $response['success'] ? $response['data'] : [];
    }

    public function getProductsByCategory(string $category): array
    {
        $response = $this->get("/products/category/{$category}", [], [
            'action' => 'get_products_by_category',
            'category' => $category,
        ]);

        if (!$response['success']) {
            return [];
        }

        return array_map(fn($p) => $this->normalizeProduct($p), $response['data']);
    }

    /**
     * Normalize FakeStore product to common format
     */
    private function normalizeProduct(array $product): array
    {
        return [
            'id' => 'fakestore_' . $product['id'],
            'external_id' => $product['id'],
            'source' => 'fakestore',
            'name' => $product['title'] ?? '',
            'description' => $product['description'] ?? '',
            'price' => (float) ($product['price'] ?? 0),
            'price_idr' => (float) ($product['price'] ?? 0) * 15000, // Simulate IDR conversion
            'category' => $product['category'] ?? '',
            'image' => $product['image'] ?? '',
            'rating' => [
                'rate' => $product['rating']['rate'] ?? 0,
                'count' => $product['rating']['count'] ?? 0,
            ],
            'stock' => rand(10, 100), // FakeStore doesn't have stock
            'weight' => rand(100, 2000), // simulate weight in grams
            'sku' => 'FSK-' . str_pad($product['id'], 5, '0', STR_PAD_LEFT),
        ];
    }
}
