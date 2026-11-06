# price2spy-api
pulls the data from price2spy api

## Updating a product

`updateProduct()` calls `POST /update-product`. Only the fields you set are sent; Price2Spy
leaves every other field unchanged. To clear a field, pass an empty string or `0`.

```php
use AlwaysOpen\Price2SpyApi\DTOs\UpdateProductRequest;
use AlwaysOpen\Price2SpyApi\Price2SpyApiFacade;

// Stop checking a product.
Price2SpyApiFacade::updateProduct(new UpdateProductRequest(productId: 550487637, active: false));
```

A non-2xx response throws a `RuntimeException` whose code is the HTTP status. A transport
failure throws a `RuntimeException` with code `0` and the `ConnectionException` as its
previous exception.

Code `0` means the outcome is unknown, not that the update was skipped: a request that
timed out may still have been applied. Before you treat it as a failure, re-read the product
with `getProducts(p2sProductId: ...)`. Retrying is safe, because every field sets an absolute
value.

## Creating products and URLs

`insertProduct()` and `insertUrl()` return the new Price2Spy id. They are never retried: a
create is not idempotent, so a replay after a lost response could create a duplicate.

```php
use AlwaysOpen\Price2SpyApi\DTOs\InsertProductRequest;
use AlwaysOpen\Price2SpyApi\DTOs\InsertUrlRequest;
use AlwaysOpen\Price2SpyApi\Price2SpyApiFacade;

$productId = Price2SpyApiFacade::insertProduct(new InsertProductRequest(productName: 'Lubricant Eye Drops 10ml', sku: 'SYS-10'));
$urlId = Price2SpyApiFacade::insertUrl(new InsertUrlRequest(productId: $productId, url: 'https://www.example.com/p/123'));
```

A 2xx response whose body is not a positive integer throws `MalformedResponseException`.

## Updating and reading URLs

```php
use AlwaysOpen\Price2SpyApi\DTOs\UpdateUrlRequest;

// Stop checking a URL.
Price2SpyApiFacade::updateUrl(new UpdateUrlRequest(urlId: $urlId, active: false));

// URLs with their check health: active, counterFailed, lastError, lastChecked.
$urls = Price2SpyApiFacade::getUrls((string) $productId)->urls;

// Other identifiers: idMode is ID, SKU, INTERNAL_ID, NAME, or ANY; searchMode is EQUAL or LIKE.
$urls = Price2SpyApiFacade::getUrls('SYS-10', idMode: 'SKU')->urls;
```

Errors follow `updateProduct()`: a non-2xx response's code is the HTTP status, and a
transport failure has code `0` with the `ConnectionException` as its previous exception.
