<?php

namespace App\Clients;

use App\Models\ApiLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

abstract class BaseApiClient
{
    protected Client $httpClient;
    protected string $serviceName;
    protected string $baseUrl;
    protected int $timeout = 30;
    protected int $maxRetries = 3;

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'http_errors' => false,
            'headers' => $this->getDefaultHeaders(),
        ]);
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'OMS-Laravel/1.0',
        ];
    }

    /**
     * Make HTTP request with logging and retry logic
     */
    protected function request(
        string $method,
        string $endpoint,
        array $options = [],
        array $context = []
    ): array {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $startTime = microtime(true);
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            try {
                $response = $this->httpClient->request($method, $endpoint, $options);
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();
                $decoded = json_decode($body, true) ?? [];
                $isSuccess = $statusCode >= 200 && $statusCode < 300;

                $this->logApiCall(
                    method: $method,
                    url: $url,
                    requestBody: $options['json'] ?? $options['form_params'] ?? [],
                    responseCode: $statusCode,
                    responseBody: $decoded,
                    duration: $duration,
                    isSuccess: $isSuccess,
                    context: $context
                );

                if (!$isSuccess) {
                    Log::warning("API call failed [{$this->serviceName}] {$method} {$url}", [
                        'status' => $statusCode,
                        'attempt' => $attempt,
                    ]);
                }

                return [
                    'success' => $isSuccess,
                    'status' => $statusCode,
                    'data' => $decoded,
                    'raw' => $body,
                ];

            } catch (ConnectException $e) {
                $lastException = $e;
                Log::error("Connection failed [{$this->serviceName}] attempt {$attempt}", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    usleep(500000 * $attempt); // exponential backoff
                }
            } catch (GuzzleException $e) {
                $lastException = $e;
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $this->logApiCall(
                    method: $method,
                    url: $url,
                    requestBody: [],
                    responseCode: 0,
                    responseBody: [],
                    duration: $duration,
                    isSuccess: false,
                    context: $context,
                    errorMessage: $e->getMessage()
                );
                break;
            }
        }

        Log::error("All retry attempts failed [{$this->serviceName}]", [
            'url' => $url,
            'attempts' => $attempt,
        ]);

        return [
            'success' => false,
            'status' => 0,
            'data' => [],
            'error' => $lastException?->getMessage() ?? 'Unknown error',
        ];
    }

    protected function get(string $endpoint, array $query = [], array $context = []): array
    {
        $options = $query ? ['query' => $query] : [];
        return $this->request('GET', $endpoint, $options, $context);
    }

    protected function post(string $endpoint, array $data = [], array $context = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data], $context);
    }

    private function logApiCall(
        string $method,
        string $url,
        array $requestBody,
        int $responseCode,
        array $responseBody,
        int $duration,
        bool $isSuccess,
        array $context = [],
        ?string $errorMessage = null
    ): void {
        try {
            ApiLog::create([
                'service' => $this->serviceName,
                'method' => $method,
                'url' => $url,
                'request_body' => $requestBody,
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'duration_ms' => $duration,
                'is_success' => $isSuccess,
                'error_message' => $errorMessage,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log API call: ' . $e->getMessage());
        }
    }
}
