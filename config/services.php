<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External Services Configuration
    |--------------------------------------------------------------------------
    */

    // FakeStore API
    'fakestore' => [
        'url' => env('FAKESTORE_API_URL', 'https://fakestoreapi.com'),
    ],

    // DummyJSON API
    'dummyjson' => [
        'url' => env('DUMMYJSON_API_URL', 'https://dummyjson.com'),
    ],

    // Product Aggregator Settings
    'product' => [
        'provider' => env('PRODUCT_API_PROVIDER', 'fakestore'), // fakestore | dummyjson
        'cache_ttl' => (int) env('PRODUCT_CACHE_TTL', 3600),
    ],

    // RajaOngkir - Shipping API
    'rajaongkir' => [
        'key' => env('RAJAONGKIR_API_KEY', ''),
        'url' => env('RAJAONGKIR_API_URL', 'https://api.rajaongkir.com/starter'),
        'simulate' => (bool) env('RAJAONGKIR_SIMULATE', true),
    ],

    // Payment Gateway (Simulated)
    'payment' => [
        'url' => env('PAYMENT_GATEWAY_URL', 'https://payment-simulator.local'),
        'key' => env('PAYMENT_GATEWAY_KEY', 'simulated-payment-key'),
        'secret' => env('PAYMENT_GATEWAY_SECRET', 'simulated-payment-secret'),
        'simulate' => (bool) env('PAYMENT_SIMULATE', true),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', 'webhook-secret-key'),
    ],

    // OMS Settings
    'oms' => [
        'idempotency_ttl' => (int) env('OMS_IDEMPOTENCY_TTL', 86400),
        'order_expiry_hours' => (int) env('OMS_ORDER_EXPIRY_HOURS', 24),
        'max_retry_attempts' => (int) env('OMS_MAX_RETRY_ATTEMPTS', 3),
    ],

    // Mailgun (for production email)
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
