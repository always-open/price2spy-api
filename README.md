# price2spy-api

A Laravel client for the [Price2Spy](https://www.price2spy.com/) REST API. It reads your product
catalogue and current pricing data, and updates product settings. Responses hydrate into typed
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
- `ProductUrl` is one competitor URL: `url`, `siteHumanName`, `siteCountryISOCode`,
  `active`, `lastChecked`, `lastError`, `lastMeasurement`, and `secondToLastMeasurement`.
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
| 2xx response whose body isn't the expected shape (read methods only) | `MalformedResponseException` | The HTTP status |

`MalformedResponseException` extends `RuntimeException`, so catching `RuntimeException`
catches all three.

The shape check covers the list, not each field's type. When a product in a 2xx response has a
field of an unexpected type, hydration fails and laravel-data's exception reaches you
unchanged.

A request that hits a transport failure is attempted up to 3 times in total, 2 seconds apart,
before the exception is thrown. A non-2xx response isn't retried.

For `updateProduct()`, code `0` means the outcome is unknown, not that the update was skipped:
a request that timed out may still have been applied. Before you treat it as a failure,
re-read the product with `getProducts(p2sProductId: ...)`. Retrying is safe, because every
field sets an absolute value.

## Testing

```bash
composer test
```

## License

MIT
