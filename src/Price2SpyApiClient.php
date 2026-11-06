<?php

namespace AlwaysOpen\Price2SpyApi;

use AlwaysOpen\Price2SpyApi\DTOs\CurrentPricingDataResponse;
use AlwaysOpen\Price2SpyApi\DTOs\GetProductsResponse;
use AlwaysOpen\Price2SpyApi\DTOs\GetUrlsResponse;
use AlwaysOpen\Price2SpyApi\DTOs\InsertProductRequest;
use AlwaysOpen\Price2SpyApi\DTOs\InsertUrlRequest;
use AlwaysOpen\Price2SpyApi\DTOs\UpdateProductRequest;
use AlwaysOpen\Price2SpyApi\DTOs\UpdateUrlRequest;
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
        // assumed to share it.
        $products = $this->productList($response, 'products.product', 'pricing');

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

        // The empty catalogue shape ({"product":[]}) is assumed from the pricing endpoint's
        // captured empty body, which wraps its list the same way.
        $products = $this->productList($response, 'product', 'products');

        return GetProductsResponse::from([
            'products' => $products,
        ]);
    }

    /**
     * Update one product's settings. Only the request's non-null fields are sent;
     * Price2Spy leaves the rest unchanged. The 2xx body is untyped, so nothing is read
     * from it.
     *
     * A transport failure (code 0) leaves the outcome unknown: a timed-out request may
     * still have been applied, so re-read the product with getProducts() before treating
     * it as not applied.
     *
     * @throws RuntimeException on a non-2xx response (the HTTP status is the code) or a
     *                          transport failure (code 0, the cause chained as previous)
     */
    public function updateProduct(UpdateProductRequest $request): void
    {
        $this->send('update-product', $request->toArray(), 3);
    }

    /**
     * Create a product and return its new Price2Spy product id. Only the request's
     * non-null fields are sent. Never retried: a create is not idempotent, so replaying
     * it after a lost response could create a duplicate.
     *
     * @throws RuntimeException on a non-2xx response (the HTTP status is the code) or a
     *                          transport failure (code 0, the cause chained as previous)
     * @throws MalformedResponseException when a 2xx body is not the new id
     */
    public function insertProduct(InsertProductRequest $request): int
    {
        return $this->newId($this->send('insert-product', $request->toArray(), 0), 'product');
    }

    /**
     * Add a URL to a product and return its new Price2Spy URL id. Only the request's
     * non-null fields are sent. Never retried, for the same reason as insertProduct().
     *
     * @throws RuntimeException on a non-2xx response (the HTTP status is the code) or a
     *                          transport failure (code 0, the cause chained as previous)
     * @throws MalformedResponseException when a 2xx body is not the new id
     */
    public function insertUrl(InsertUrlRequest $request): int
    {
        return $this->newId($this->send('insert-url', $request->toArray(), 0), 'url');
    }

    /**
     * Update one URL's settings. Only the request's non-null fields are sent; Price2Spy
     * leaves the rest unchanged. The 2xx body is untyped, so nothing is read from it.
     *
     * @throws RuntimeException on a non-2xx response (the HTTP status is the code) or a
     *                          transport failure (code 0, the cause chained as previous)
     */
    public function updateUrl(UpdateUrlRequest $request): void
    {
        $this->send('update-url', $request->toArray(), 3);
    }

    /**
     * The URLs matching $id, each with its check health (active, counterFailed, lastError,
     * lastChecked) and latest measurements.
     *
     * @param  'ID'|'SKU'|'INTERNAL_ID'|'NAME'|'ANY'  $idMode  which identifier $id is matched against
     * @param  'LIKE'|'EQUAL'  $searchMode
     *
     * @throws RuntimeException on a non-2xx response (the HTTP status is the code) or a
     *                          transport failure (code 0, the cause chained as previous)
     * @throws MalformedResponseException when a 2xx body is not a urls response
     */
    public function getUrls(string $id, string $idMode = 'ID', string $searchMode = 'EQUAL'): GetUrlsResponse
    {
        $response = $this->send('get-urls', [
            'id' => $id,
            'idMode' => $idMode,
            'searchMode' => $searchMode,
        ], 3);

        // The empty shape ({"url":[]}) is assumed from the pricing endpoint's captured
        // empty body, which wraps its lists the same way.
        return GetUrlsResponse::from([
            'urls' => $this->productList($response, 'url', 'urls'),
        ]);
    }

    /**
     * POST $payload to $endpoint and return the 2xx response.
     *
     * @throws RuntimeException on a non-2xx response (the HTTP status is the code) or a
     *                          transport failure (code 0, the cause chained as previous)
     */
    private function send(string $endpoint, array $payload, int $retryCount): Response
    {
        try {
            $response = $this->makeRequest(
                'post',
                rtrim($this->baseUrl, '/').'/'.$endpoint,
                $payload,
                $retryCount,
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Price2Spy API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Price2Spy API request failed: '.$response->body(), $response->status());
        }

        return $response;
    }

    /**
     * The id a create endpoint returns as its whole 2xx body: a positive JSON integer.
     *
     * @throws MalformedResponseException
     */
    private function newId(Response $response, string $kind): int
    {
        $id = $response->json();

        if (is_int($id) && $id > 0) {
            return $id;
        }

        throw new MalformedResponseException(
            "Price2Spy API did not return a new id for the created {$kind}: ".mb_strimwidth($response->body(), 0, 500, '...'),
            $response->status(),
        );
    }

    /**
     * The decoded list at $key of a 2xx response. Only a list whose every element is an
     * object (decoded as an array) hydrates into product DTOs; any other shape means the
     * body is not a $kind response.
     *
     * @throws MalformedResponseException
     */
    private function productList(Response $response, string $key, string $kind): array
    {
        $products = $response->json($key);

        $isProductList = is_array($products)
            && array_is_list($products)
            && array_filter($products, fn ($product) => ! is_array($product)) === [];

        if ($isProductList) {
            return $products;
        }

        throw new MalformedResponseException(
            "Price2Spy API returned a malformed {$kind} response: ".mb_strimwidth($response->body(), 0, 500, '...'),
            $response->status(),
        );
    }
}
