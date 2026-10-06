<?php

namespace AlwaysOpen\Price2SpyApi;

use AlwaysOpen\Price2SpyApi\DTOs\CurrentPricingDataResponse;
use AlwaysOpen\Price2SpyApi\DTOs\GetProductsResponse;
use AlwaysOpen\Price2SpyApi\Exceptions\MalformedResponseException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class Price2SpyApiClient
{
    protected ?string $baseUrl = null;

    protected ?string $apiKey = null;

    protected int $timeout = 300;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?int $timeout = null,
    ) {
        $this->baseUrl = $baseUrl ?? config('price2spy-api.base_url', 'https://api.price2spy.com/rest/v1/');
        $this->apiKey = $apiKey ?? config('price2spy-api.api_key') ?? '';
        $this->timeout = $timeout ?? config('price2spy-api.timeout', 300);
    }

    protected function getAuthHeader(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode($this->apiKey),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @throws Throwable
     */
    protected function makeRequest(
        string $method,
        string $uri,
        ?array $payload = null,
        ?int $retryCount = null,
    ): Response {
        $request = new Request(
            method: $method,
            uri: $uri,
            headers: $this->getAuthHeader(),
            body: $payload ? json_encode($payload) : null,
        );

        return retry($retryCount ?? 0, function () use ($request, $method, $payload): PromiseInterface|Response {
            if (strtolower($method) === 'post') {
                return Http::withHeaders($request->getHeaders())
                    ->timeout($this->timeout)
                    ->post($request->getUri(), $payload);
            } else {
                return Http::withHeaders($request->getHeaders())
                    ->timeout($this->timeout)
                    ->get($request->getUri());
            }
        }, 2000);
    }

    public function getCurrentPricingData(bool $active = true, ?int $p2sProductId = null): CurrentPricingDataResponse
    {
        try {
            $payload = ['active' => $active];

            if ($p2sProductId !== null) {
                $payload['productId'] = $p2sProductId;
            }

            $response = $this->makeRequest(
                'post',
                rtrim($this->baseUrl, '/').'/get-current-pricing-data',
                $payload,
                3,
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Price2Spy API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Price2Spy API request failed: '.$response->body(), $response->getStatusCode());
        }

        // A genuine empty result keeps the full structure ({"products":{"product":[]}}),
        // captured from a productId-filtered request; the unfiltered catalogue request is
        // assumed to share it. Anything other than a list of product objects means the
        // body is not a pricing response.
        $products = $response->json('products.product');

        if (! $this->isListOfObjects($products)) {
            throw new MalformedResponseException(
                'Price2Spy API returned a malformed pricing response: '.mb_strimwidth($response->body(), 0, 500, '...'),
                $response->status(),
            );
        }

        $products = array_map(function (array $product) {
            $product['urls'] = $product['urls']['url'] ?? [];

            return $product;
        }, $products);

        return CurrentPricingDataResponse::from([
            'products' => $products,
        ]);
    }

    public function getProducts(bool $active = true, ?int $p2sProductId = null): GetProductsResponse
    {
        try {
            $payload = ['active' => $active];

            if ($p2sProductId !== null) {
                $payload['productId'] = $p2sProductId;
            }

            $response = $this->makeRequest(
                'post',
                rtrim($this->baseUrl, '/').'/get-products',
                $payload,
                3,
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Price2Spy API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Price2Spy API request failed: '.$response->body(), $response->getStatusCode());
        }

        $products = $response->json('product', []);

        return GetProductsResponse::from([
            'products' => $products,
        ]);
    }

    /**
     * Whether a decoded JSON value is a list whose every element is itself an object
     * (decoded as an array) — the only shape the product DTO hydration accepts.
     */
    private function isListOfObjects(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
    }
}
