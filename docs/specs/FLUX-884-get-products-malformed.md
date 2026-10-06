# FLUX-884 — Throw on a malformed `get-products` response

## Operational Summary

Today a `get-products` call that comes back with a 200 status but the wrong body shape (an error message, a maintenance page, a renamed key, a single product where a list belongs) is reported to pec-platform as "the catalogue is empty" or as a PHP type error. The product-state sync already refuses to deactivate anything in either case, so no links are wrongly deactivated; the problem is that the log line says "zero products" with no body excerpt, so an operator cannot tell a vendor outage from a genuinely empty catalogue. After this change the client throws the same `MalformedResponseException` that `get-current-pricing-data` already throws, carrying the HTTP status and the first 500 characters of the body. A real empty catalogue still returns an empty list. Nothing changes for a well-formed response.

## 1) Summary

Apply the FLUX-876 shape guard to `Price2SpyApiClient::getProducts`. The `product` key must be a list of objects; anything else throws `MalformedResponseException`.

## 2) Goals

- G1: `getProducts` throws `MalformedResponseException` on a 2xx body that is not a product list.
- G2: A genuine empty catalogue (`{"product": []}`) returns an empty `products` list.

## 3) Non-goals

- NG1: No change to `SyncPrice2SpyProductState` in this PR. It catches `Throwable` and aborts with no writes; the pec-platform bump PR verifies that against the new exception (AC3).
- NG2: No change to the exception's shape (no body accessor, no base exception class) — FLUX-876 settled that as YAGNI.
- NG3: No retry-classification change in pec-platform; `isTransientApiFailure` is a `getCurrentPricingData` concern and is handled in the bump PR under FLUX-876.

## 4) Scope

Files modified in `always-open/price2spy-api`:
- `src/Price2SpyApiClient.php` — `getProducts` reads `product` without a default and guards with `isListOfObjects()`.
- `tests/Feature/Price2SpyApiClientTest.php` — empty-response test reads a fixture; new data-provider test for malformed bodies.

Files created:
- `tests/Fixtures/get_products_empty.json` — `{"product": []}`.

## 5) Acceptance criteria

- AC1: A 200 `get-products` response whose body is not JSON, is empty, is an error envelope, lacks the `product` key, or whose `product` value is null, a scalar, a single object, or a list containing a scalar throws `MalformedResponseException` with `getCode() === 200` and a message containing `malformed products response`.
- AC2: A 200 `get-products` response of `{"product": []}` returns a `GetProductsResponse` whose `products` is `[]`.
- AC3: `SyncPrice2SpyProductState` exits FAILURE with no writes when `getProducts` throws `MalformedResponseException`. Verified in the pec-platform bump PR, not here (NG1).

## 6) Design

`getProducts` mirrors `getCurrentPricingData`:

```php
$products = $response->json('product');

if (! $this->isListOfObjects($products)) {
    throw new MalformedResponseException(
        'Price2Spy API returned a malformed products response: '.mb_strimwidth($response->body(), 0, 500, '...'),
        $response->status(),
    );
}
```

The empty shape is **assumed**, not captured: the pricing endpoint's captured empty body is `{"products": {"product": []}}`, and `get-products` uses the same serializer and the same list-wrapping convention (`product` is the list key in every fixture). The empty-response test and the client comment say so. If the real empty shape differs, the consumer still aborts with no writes, so the cost of a wrong assumption is a louder failure rather than a wrong deactivation.

## 7) UI implementation mode

Backend-only.

## 8) Risk classification

LOW. One method in a thin client; the only consumer already treats both a thrown exception and an empty catalogue as a no-write abort. The behavioural change is which message the consumer logs.

## 9) Test plan

- AC1 → `test_get_products_malformed_response` (data provider `malformedProductsBodies`, eight cases) in `tests/Feature/Price2SpyApiClientTest.php`.
- AC2 → `test_get_products_empty_response`, reading `tests/Fixtures/get_products_empty.json`.
- AC3 → deferred to the pec-platform bump PR (NG1); existing `SyncPrice2SpyProductState` tests for the thrown-exception path cover it once the package is bumped.
- Red proof: with the guard absent, the single-object and list-of-scalars cases fail with a `TypeError` and the remaining cases return an empty list instead of throwing.

## 10) Rollout

Lands on PR #6 with FLUX-876. One tag (`v0.2.0`) covers both; pec-platform bumps in a separate PR.

## 11) Open questions

- Q1: Is `{"product": []}` the real empty shape? **Assumed yes** by serializer convention; confirm by capturing a `get-products` call with a `productId` that is inactive, and replace the fixture if it differs.
