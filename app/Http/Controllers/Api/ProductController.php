<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductAggregatorService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProductAggregatorService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->getProducts(
            (int) $request->get('limit', 20),
            (int) $request->get('page', 1)
        );

        return $this->success([
            'products' => $products,
            'count' => count($products),
        ], 'Products retrieved successfully');
    }

    public function show(string $productId): JsonResponse
    {
        $product = $this->productService->getProduct($productId);

        if (!$product) {
            return $this->notFound('Product not found');
        }

        return $this->success($product, 'Product retrieved successfully');
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (empty($query)) {
            return $this->error('Search query is required', 422);
        }

        $products = $this->productService->searchProducts($query, (int) $request->get('limit', 20));

        return $this->success([
            'products' => array_values($products),
            'query' => $query,
            'count' => count($products),
        ], 'Search results');
    }

    public function categories(): JsonResponse
    {
        $categories = $this->productService->getCategories();
        return $this->success($categories, 'Categories retrieved successfully');
    }
}
