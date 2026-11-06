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
