<?php

namespace AlwaysOpen\Price2SpyApi\Tests\Feature;

use AlwaysOpen\Price2SpyApi\Price2SpyApiClient;
use AlwaysOpen\Price2SpyApi\Tests\BaseTest;
use Illuminate\Support\Facades\Http;

class Price2SpyApiClientTest extends BaseTest
{
    public function test_get_current_pricing_data()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-current-pricing-data' => Http::response(
                $this->getFixtureJsonContent('current_pricing_data.json'),
                200,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $response = $client->getCurrentPricingData();

        $this->assertNotEmpty($response->products);
        $this->assertCount(1, $response->products);

        $product = $response->products[0];
        $this->assertEquals(550487637, $product->productId);
        $this->assertEquals('SYSTANE COMPLETE Lubricant Eye Drops 10ml', $product->productName);
        $this->assertEquals('Systane', $product->brandName);
        $this->assertEquals(14.79, $product->minPrice->amount);
        $this->assertEquals(19.99, $product->maxPrice->amount);
        $this->assertEquals(14.95, $product->targetPrice);

        $this->assertCount(2, $product->urls);
        $this->assertEquals('Target', $product->urls[0]->siteHumanName);
        $this->assertEquals(14.99, $product->urls[0]->lastMeasurement->price->amount);
        $this->assertEquals('Kroger', $product->urls[1]->siteHumanName);
        $this->assertEquals(16.79, $product->urls[1]->lastMeasurement->price->amount);
        $this->assertNotNull($product->urls[1]->secondToLastMeasurement);
    }

    public function test_get_current_pricing_data_empty_response()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-current-pricing-data' => Http::response(
                json_encode(['products' => ['product' => []]]),
                200,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $response = $client->getCurrentPricingData();

        $this->assertEmpty($response->products);
    }

    public function test_get_current_pricing_data_failed_request()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-current-pricing-data' => Http::response(
                'Internal Server Error',
                500,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Price2Spy API request failed');

        $client->getCurrentPricingData();
    }

    public function test_get_products()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-products' => Http::response(
                $this->getFixtureJsonContent('get_products.json'),
                200,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $response = $client->getProducts();

        $this->assertNotEmpty($response->products);
        $this->assertCount(2, $response->products);

        $product = $response->products[0];
        $this->assertEquals(550487637, $product->productId);
        $this->assertEquals('SYSTANE COMPLETE Lubricant Eye Drops 10ml', $product->productName);
        $this->assertEquals('Systane', $product->brandName);
        $this->assertTrue($product->active);
        $this->assertEquals(14.79, $product->minPrice->amount);
        $this->assertEquals(19.99, $product->maxPrice->amount);
        $this->assertEquals(14.95, $product->targetPrice);
        $this->assertEquals('AISLE-12', $product->customField1);
        $this->assertEquals('Vision Care', $product->customField2);
        $this->assertNull($product->customField3);

        // Custom fields are optional; absent keys hydrate as null.
        $this->assertNull($response->products[1]->customField1);
    }

    public function test_get_products_casts_numeric_custom_fields_to_string()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-products' => Http::response(
                json_encode(['product' => [[
                    'productId' => 550487637,
                    'customField1' => 12345,
                    'customField2' => 9.99,
                ]]]),
                200,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $product = $client->getProducts()->products[0];

        $this->assertSame('12345', $product->customField1);
        $this->assertSame('9.99', $product->customField2);
    }

    public function test_get_products_sends_active_and_product_id()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-products' => Http::response(
                $this->getFixtureJsonContent('get_products.json'),
                200,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $client->getProducts(active: false, p2sProductId: 550487637);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.price2spy.com/rest/v1/get-products'
                && $request['active'] === false
                && $request['productId'] === 550487637;
        });
    }

    public function test_get_products_defaults_active_true_without_product_id()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-products' => Http::response(
                $this->getFixtureJsonContent('get_products.json'),
                200,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $client->getProducts();

        Http::assertSent(function ($request) {
            return $request['active'] === true
                && ! isset($request['productId']);
        });
    }

    public function test_get_products_empty_response()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-products' => Http::response(
                json_encode(['product' => []]),
                200,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $response = $client->getProducts();

        $this->assertEmpty($response->products);
    }

    public function test_get_products_failed_request()
    {
        Http::fake([
            'https://api.price2spy.com/rest/v1/get-products' => Http::response(
                'Internal Server Error',
                500,
            ),
        ]);

        $client = new Price2SpyApiClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Price2Spy API request failed');

        $client->getProducts();
    }
}
