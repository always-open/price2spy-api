# price2spy-api

A Laravel client for the [Price2Spy](https://www.price2spy.com/) REST API. It reads your product
catalogue, current pricing data, and competitor URLs, creates products and URLs, and updates
their settings. Responses hydrate into typed
[laravel-data](https://spatie.be/docs/laravel-data) objects.

## Requirements

- PHP 8.3 (8.3.1 or later), 8.4 (8.4.1 or later), or 8.5
- Laravel 10, 11, or 12

## Installation

```bash
composer require always-open/price2spy-api
```

Laravel discovers the package automatically. The service provider registers
`Price2SpyApiClient` as a singleton, and the `Price2SpyApi` facade alias points to it.

## Configuration

Set your API key in `.env`:

```dotenv
PRICE2SPY_API_KEY=your-api-key
```

| Variable | Default | Purpose |
|---|---|---|
| `PRICE2SPY_API_KEY` | none | Your Price2Spy API key, sent as HTTP Basic auth |
| `PRICE2SPY_API_BASE_URL` | `https://api.price2spy.com/rest/v1/` | API base URL |
| `PRICE2SPY_API_TIMEOUT` | `300` | Per-request timeout, in seconds |

To customize the config file, publish it to `config/price2spy-api.php`:

```bash
php artisan vendor:publish --provider="AlwaysOpen\Price2SpyApi\Price2SpyApiServiceProvider" --tag=config
```

## Usage

Call the API through the facade:

```php
use AlwaysOpen\Price2SpyApi\Price2SpyApiFacade;

$products = Price2SpyApiFacade::getProducts()->products;
```

Or inject the client, or build one yourself when you need a different key or timeout. Any
argument you leave out falls back to the config:

```php
use AlwaysOpen\Price2SpyApi\Price2SpyApiClient;

$client = new Price2SpyApiClient(apiKey: 'another-key', timeout: 60);
```

## Methods

| Method | Endpoint | Returns |
|---|---|---|
| [`getProducts()`](#getproducts) | `POST /get-products` | `GetProductsResponse` |
| [`getCurrentPricingData()`](#getcurrentpricingdata) | `POST /get-current-pricing-data` | `CurrentPricingDataResponse` |
| [`updateProduct()`](#updateproduct) | `POST /update-product` | nothing |
| [`insertProduct()`](#insertproduct) | `POST /insert-product` | the new product ID (`int`) |
| [`getUrls()`](#geturls) | `POST /get-urls` | `GetUrlsResponse` |
| [`insertUrl()`](#inserturl) | `POST /insert-url` | the new URL ID (`int`) |
| [`updateUrl()`](#updateurl) | `POST /update-url` | nothing |

### getProducts()

```php
getProducts(bool $active = true, ?int $p2sProductId = null): GetProductsResponse
```

Returns your product catalogue: each product's settings, without its competitor URLs.

- `$active`: sent as Price2Spy's `active` filter. The default, `true`, returns active
  products.
- `$p2sProductId`: when set, returns only the product with this Price2Spy product ID.

```php
// Every active product.
$products = Price2SpyApiFacade::getProducts()->products;

// One product, by its Price2Spy ID.
$product = Price2SpyApiFacade::getProducts(p2sProductId: 550487637)->products[0] ?? null;
```

`products` is a list of `Product` objects, and it's empty when nothing matches.

### getCurrentPricingData()

```php
getCurrentPricingData(bool $active = true, ?int $p2sProductId = null): CurrentPricingDataResponse
```

Returns products with their competitor URLs. Each URL carries its two most recent price
checks. The parameters work the same as `getProducts()`.

```php
$response = Price2SpyApiFacade::getCurrentPricingData(p2sProductId: 550487637);

foreach ($response->products as $product) {
    foreach ($product->urls as $url) {
        $price = $url->lastMeasurement?->price;

        echo "{$url->siteHumanName}: {$price?->amount} {$price?->currency}\n";
    }
}
```

### updateProduct()

```php
updateProduct(UpdateProductRequest $request): void
```

Updates one product's settings. Only the fields you set are sent; Price2Spy leaves every
other field unchanged. To clear a field, pass an empty string or `0`.

```php
use AlwaysOpen\Price2SpyApi\DTOs\UpdateProductRequest;

// Stop checking a product.
Price2SpyApiFacade::updateProduct(new UpdateProductRequest(productId: 550487637, active: false));

// Check it every 12 hours, and clear its SKU.
Price2SpyApiFacade::updateProduct(new UpdateProductRequest(
    productId: 550487637,
    checkFrequencyType: 'HOURS',
    checkFrequencyInterval: 12,
    sku: '',
));
```

`productId` is required. The optional fields are `active`, `sku`, `internalId`, `productName`,
`brandName`, `categoryName`, `supplierName`, `checkFrequencyType` (`'DAYS'` or `'HOURS'`),
`checkFrequencyInterval`, `targetPrice`, `costPrice`, `comment1`, `comment2`, `customField1`
through `customField10`, and `metadata1Name` through `metadata3Name`.

### insertProduct()

```php
insertProduct(InsertProductRequest $request): int
```

Creates a product and returns its new Price2Spy product ID. Only the fields you set are sent.

```php
use AlwaysOpen\Price2SpyApi\DTOs\InsertProductRequest;

$productId = Price2SpyApiFacade::insertProduct(new InsertProductRequest(
    productName: 'SYSTANE COMPLETE Lubricant Eye Drops 10ml',
    sku: 'SYS-10',
    brandName: 'Systane',
));
```

`productName` is required. The optional fields are the same as `updateProduct()`'s, without
`productId`.

### getUrls()

```php
getUrls(string $id, string $idMode = 'ID', string $searchMode = 'EQUAL'): GetUrlsResponse
```

Returns competitor URLs with their check health: whether each is active, how many checks in a
row failed, the last error, and the last two price checks.

- `$id`: the value to search for.
- `$idMode`: which identifier `$id` is matched against: `'ID'`, `'SKU'`, `'INTERNAL_ID'`,
  `'NAME'`, or `'ANY'`.
- `$searchMode`: `'EQUAL'` for an exact match, or `'LIKE'` for a partial one.

```php
$urls = Price2SpyApiFacade::getUrls('SYS-10', idMode: 'SKU')->urls;

foreach ($urls as $url) {
    if ($url->counterFailed > 0) {
        echo "{$url->url}: {$url->lastError}\n";
    }
}
```

`urls` is a list of `ProductUrl` objects, and it's empty when nothing matches.

### insertUrl()

```php
insertUrl(InsertUrlRequest $request): int
```

Adds a competitor URL to a product and returns its new Price2Spy URL ID. Only the fields you
set are sent.

```php
use AlwaysOpen\Price2SpyApi\DTOs\InsertUrlRequest;

$urlId = Price2SpyApiFacade::insertUrl(new InsertUrlRequest(
    productId: $productId,
    url: 'https://www.example.com/p/systane-complete',
));
```

`productId` and `url` are required. The optional fields are `active`, `showInReports`, and
`note1` through `note3`.

### updateUrl()

```php
updateUrl(UpdateUrlRequest $request): void
```

Updates one URL's settings. Only the fields you set are sent; Price2Spy leaves every other
field unchanged.

```php
use AlwaysOpen\Price2SpyApi\DTOs\UpdateUrlRequest;

// Stop checking a URL, and reset its failure count.
Price2SpyApiFacade::updateUrl(new UpdateUrlRequest(urlId: $urlId, active: false, counterFailed: 0));
```

`urlId` is required. The optional fields are `url`, `active`, `showInReports`,
`counterFailed`, and `note1` through `note3`.

## Response objects

All response objects live in `AlwaysOpen\Price2SpyApi\DTOs`. Every property is nullable,
because Price2Spy omits fields that have no value.

- `GetProductsResponse` and `CurrentPricingDataResponse` each have a `products` list of
  `Product` objects.
- `Product` holds a product's settings: `productId`, `productName`, `active`, `sku`,
  `internalId`, `brandName`, `categoryName`, `targetPrice`, `costPrice`, `minPrice`,
  `maxPrice`, `checkFrequencyType`, `checkFrequencyInterval`, `lastChecked`, `nextChecked`,
  `customField1` through `customField3`, and more. On `getCurrentPricingData()`, `urls`
  holds the product's `ProductUrl` objects.
- `GetUrlsResponse` has a `urls` list of `ProductUrl` objects.
- `ProductUrl` is one competitor URL: `urlId`, `url`, `siteHumanName`, `siteCountryISOCode`,
  `active`, `showInReports`, `counterFailed`, `lastChecked`, `lastError`, `lastMeasurement`,
  and `secondToLastMeasurement`.
- `Measurement` is one price check: `dateChecked`, `price`, `available`, `stockStatus`, and
  `isOnPromotion`.
- `Price` is an `amount` and its `currency`.

Dates are the strings Price2Spy returns.

## Errors and retries

Every method throws a `RuntimeException` for these failures:

| Failure | Exception | Code |
|---|---|---|
| Non-2xx response | `RuntimeException`, with the response body in its message | The HTTP status |
| Transport failure, such as a timeout or a refused connection | `RuntimeException`, with the `ConnectionException` as its previous exception | `0` |
| 2xx response whose body isn't the expected shape: a list for the read methods, a positive integer ID for `insertProduct()` and `insertUrl()` | `MalformedResponseException` | The HTTP status |

`MalformedResponseException` extends `RuntimeException`, so catching `RuntimeException`
catches all three.

The shape check covers the list, not each field's type. When a product in a 2xx response has a
field of an unexpected type, hydration fails and laravel-data's exception reaches you
unchanged.

A request that hits a transport failure is attempted up to 3 times in total, 2 seconds apart,
before the exception is thrown. `insertProduct()` and `insertUrl()` are the exception: they're
attempted once, because a create that timed out may have been applied, and repeating it could
create a duplicate. A non-2xx response isn't retried.

For `updateProduct()`, code `0` means the outcome is unknown, not that the update was skipped:
a request that timed out may still have been applied. Before you treat it as a failure,
re-read the product with `getProducts(p2sProductId: ...)`. Retrying is safe, because every
field sets an absolute value. The same holds for `updateUrl()`; re-read the URL with
`getUrls()`.

For `insertProduct()` and `insertUrl()`, code `0` also leaves the outcome unknown. Before you
create the item again, look for it with `getUrls()` or `getProducts()`, or you may end up with
a duplicate.

## Testing

```bash
composer test
```

## License

MIT
