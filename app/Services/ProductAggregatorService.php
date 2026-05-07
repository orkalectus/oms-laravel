<?php

namespace App\Services;

use App\Clients\DummyJsonApiClient;
use App\Clients\FakeStoreApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductAggregatorService
{
    private const CACHE_PREFIX = 'products';
    private int $cacheTtl;
    private string $primaryProvider;

    public function __construct(
        private readonly FakeStoreApiClient $fakeStoreClient,
        private readonly DummyJsonApiClient $dummyJsonClient
    ) {
        $this->cacheTtl = (int) config('services.product.cache_ttl', 3600);
        $this->primaryProvider = config('services.product.provider', 'fakestore');
    }

    /**
     * Get products with cache and fallback
     */
    public function getProducts(int $limit = 20, int $page = 1): array
    {
        $cacheKey = self::CACHE_PREFIX . ":list:{$this->primaryProvider}:{$limit}:{$page}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($limit, $page) {
            return $this->fetchProductsWithFallback($limit, $page);
        });
    }

    /**
     * Get single product with cache and fallback
     */
    public function getProduct(string $productId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . ":single:{$productId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($productId) {
            return $this->fetchProductWithFallback($productId);
        });
    }

    /**
     * Get product for order - must be real/recent data
     */
    public function getProductForOrder(string $productId): ?array
    {
        // For orders, use shorter cache or bypass cache for price accuracy
        $cacheKey = self::CACHE_PREFIX . ":order:{$productId}";

        return Cache::remember($cacheKey, 300, function () use ($productId) {
            return $this->fetchProductWithFallback($productId);
        });
    }

    /**
     * Search products across providers
     */
    public function searchProducts(string $query, int $limit = 20): array
    {
        $cacheKey = self::CACHE_PREFIX . ":search:" . md5($query) . ":{$limit}";

        return Cache::remember($cacheKey, 600, function () use ($query, $limit) {
            // DummyJSON has better search support
            try {
                $results = $this->dummyJsonClient->searchProducts($query, $limit);
                if (!empty($results)) {
                    return $results;
                }
            } catch (\Throwable $e) {
                Log::warning('DummyJSON search failed, trying FakeStore', ['error' => $e->getMessage()]);
            }

            // Fallback: FakeStore doesn't support search, so filter from all products
            $allProducts = $this->getProducts(100);
            return array_filter($allProducts, function ($product) use ($query) {
                return str_contains(strtolower($product['name']), strtolower($query))
                    || str_contains(strtolower($product['category'] ?? ''), strtolower($query));
            });
        });
    }

    /**
     * Get categories
     */
    public function getCategories(): array
    {
        $cacheKey = self::CACHE_PREFIX . ':categories';

        return Cache::remember($cacheKey, $this->cacheTtl * 2, function () {
            try {
                if ($this->primaryProvider === 'dummyjson') {
                    return $this->dummyJsonClient->getCategories();
                }
                return $this->fakeStoreClient->getCategories();
            } catch (\Throwable $e) {
                Log::error('Failed to fetch categories', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Invalidate product cache
     */
    public function invalidateCache(string $productId): void
    {
        Cache::forget(self::CACHE_PREFIX . ":single:{$productId}");
        Cache::forget(self::CACHE_PREFIX . ":order:{$productId}");
    }

    /**
     * Fetch products with automatic fallback
     */
    private function fetchProductsWithFallback(int $limit, int $page): array
    {
        $offset = ($page - 1) * $limit;

        // Try primary provider
        try {
            if ($this->primaryProvider === 'fakestore') {
                $products = $this->fakeStoreClient->getProducts($limit, $offset);
                if (!empty($products)) {
                    return $products;
                }
            } else {
                $products = $this->dummyJsonClient->getProducts($limit, $offset);
                if (!empty($products)) {
                    return $products;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Primary provider [{$this->primaryProvider}] failed", [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to secondary provider
        try {
            Log::info('Falling back to secondary product provider');
            if ($this->primaryProvider === 'fakestore') {
                return $this->dummyJsonClient->getProducts($limit, $offset);
            } else {
                return $this->fakeStoreClient->getProducts($limit, $offset);
            }
        } catch (\Throwable $e) {
            Log::error('Both product providers failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch single product with fallback
     */
    private function fetchProductWithFallback(string $productId): ?array
    {
        // Parse source and ID
        [$source, $id] = $this->parseProductId($productId);

        if ($source) {
            return $this->fetchFromSpecificSource($source, (int) $id);
        }

        // Try primary then fallback
        try {
            $numericId = (int) $productId;

            if ($this->primaryProvider === 'fakestore') {
                $product = $this->fakeStoreClient->getProduct($numericId);
            } else {
                $product = $this->dummyJsonClient->getProduct($numericId);
            }

            if ($product) {
                return $product;
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch product {$productId} from primary provider");
        }

        // Fallback
        try {
            if ($this->primaryProvider === 'fakestore') {
                return $this->dummyJsonClient->getProduct((int) $productId);
            } else {
                return $this->fakeStoreClient->getProduct((int) $productId);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to fetch product {$productId} from all providers");
            return null;
        }
    }

    private function fetchFromSpecificSource(string $source, int $id): ?array
    {
        return match($source) {
            'fakestore' => $this->fakeStoreClient->getProduct($id),
            'dummyjson' => $this->dummyJsonClient->getProduct($id),
            default => null,
        };
    }

    private function parseProductId(string $productId): array
    {
        if (str_contains($productId, '_')) {
            $parts = explode('_', $productId, 2);
            return [$parts[0], $parts[1]];
        }
        return [null, $productId];
    }
}
