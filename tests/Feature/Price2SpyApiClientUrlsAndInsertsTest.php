<?php

namespace AlwaysOpen\Price2SpyApi\Tests\Feature;

use AlwaysOpen\Price2SpyApi\DTOs\InsertProductRequest;
use AlwaysOpen\Price2SpyApi\DTOs\InsertUrlRequest;
use AlwaysOpen\Price2SpyApi\DTOs\UpdateUrlRequest;
use AlwaysOpen\Price2SpyApi\Exceptions\MalformedResponseException;
use AlwaysOpen\Price2SpyApi\Price2SpyApiClient;
use AlwaysOpen\Price2SpyApi\Price2SpyApiFacade;
use AlwaysOpen\Price2SpyApi\Tests\BaseTest;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class Price2SpyApiClientUrlsAndInsertsTest extends BaseTest
{
    private const BASE = 'https://api.price2spy.com/rest/v1/';

    public function test_insert_product_sends_only_the_set_fields_and_returns_the_new_id()
    {
        Http::fake([self::BASE.'insert-product' => Http::response('551000123', 201)]);

        $client = new Price2SpyApiClient(apiKey: 'test-key');

        $productId = $client->insertProduct(new InsertProductRequest(
            productName: 'SYSTANE COMPLETE Lubricant Eye Drops 10ml',
            active: false,
            brandName: 'Systane',
            customField2: '',
        ));

        $this->assertSame(551000123, $productId);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === self::BASE.'insert-product'
                && $request->body() === '{"productName":"SYSTANE COMPLETE Lubricant Eye Drops 10ml","active":false,"brandName":"Systane","customField2":""}'
                && $request->header('Authorization') === ['Basic '.base64_encode('test-key')]
                && $request->header('Content-Type') === ['application/json'];
        });
    }

    public function test_insert_url_sends_only_the_set_fields_and_returns_the_new_id()
    {
        Http::fake([self::BASE.'insert-url' => Http::response('900003', 201)]);

        $client = new Price2SpyApiClient;

        $urlId = $client->insertUrl(new InsertUrlRequest(
            productId: 550487637,
            url: 'https://www.walgreens.com/store/c/systane/ID=300',
            showInReports: false,
        ));

        $this->assertSame(900003, $urlId);
        Http::assertSent(function (Request $request) {
            return $request->url() === self::BASE.'insert-url'
                && $request->body() === '{"productId":550487637,"url":"https:\/\/www.walgreens.com\/store\/c\/systane\/ID=300","showInReports":false}';
        });
    }

    public function test_update_url_sends_only_the_set_fields()
    {
        Http::fake([self::BASE.'update-url' => Http::response('{}', 200)]);

        $client = new Price2SpyApiClient;

        $client->updateUrl(new UpdateUrlRequest(urlId: 900002, active: false, counterFailed: 0, note1: ''));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->url() === self::BASE.'update-url'
                && $request->body() === '{"urlId":900002,"active":false,"counterFailed":0,"note1":""}';
        });
    }

    public function test_get_urls_sends_the_search_and_hydrates_the_urls()
    {
        Http::fake([self::BASE.'get-urls' => Http::response($this->getFixtureJsonContent('get_urls.json'), 200)]);

        $client = new Price2SpyApiClient;

        $response = $client->getUrls('550487637');

        Http::assertSent(function (Request $request) {
            return $request->url() === self::BASE.'get-urls'
                && $request->data() === ['id' => '550487637', 'idMode' => 'ID', 'searchMode' => 'EQUAL'];
        });

        $this->assertCount(2, $response->urls);
        $this->assertSame(900001, $response->urls[0]->urlId);
        $this->assertSame('Target', $response->urls[0]->siteHumanName);
        $this->assertTrue($response->urls[0]->active);
        $this->assertSame(14.99, $response->urls[0]->lastMeasurement->price->amount);
        $this->assertSame(900002, $response->urls[1]->urlId);
        $this->assertFalse($response->urls[1]->active);
        $this->assertFalse($response->urls[1]->showInReports);
        $this->assertSame(7, $response->urls[1]->counterFailed);
        $this->assertSame('HTTP 404', $response->urls[1]->lastError);
        $this->assertNull($response->urls[1]->lastMeasurement);
    }

    public function test_get_urls_passes_the_search_modes()
    {
        Http::fake([self::BASE.'get-urls' => Http::response($this->getFixtureJsonContent('get_urls_empty.json'), 200)]);

        (new Price2SpyApiClient)->getUrls('SYS-10', idMode: 'SKU', searchMode: 'LIKE');

        Http::assertSent(fn (Request $request) => $request->data() === ['id' => 'SYS-10', 'idMode' => 'SKU', 'searchMode' => 'LIKE']);
    }

    public function test_get_urls_empty_response()
    {
        Http::fake([self::BASE.'get-urls' => Http::response($this->getFixtureJsonContent('get_urls_empty.json'), 200)]);

        $this->assertSame([], (new Price2SpyApiClient)->getUrls('550487637')->urls);
    }

    #[DataProvider('malformedUrlsBodies')]
    public function test_get_urls_malformed_response(string $body)
    {
        Http::fake([self::BASE.'get-urls' => Http::response($body, 200)]);

        try {
            (new Price2SpyApiClient)->getUrls('550487637');
            $this->fail('Expected a MalformedResponseException.');
        } catch (MalformedResponseException $e) {
            $this->assertSame(200, $e->getCode());
            $this->assertStringContainsString('malformed urls response', $e->getMessage());
        }
    }

    public static function malformedUrlsBodies(): array
    {
        return [
            'not json' => ['<html>Service Unavailable</html>'],
            'empty body' => [''],
            'error envelope' => [json_encode(['error' => 'Invalid API key'])],
            'url key missing' => [json_encode(['urls' => []])],
            'url null' => [json_encode(['url' => null])],
            'url is an object' => [json_encode(['url' => ['urlId' => 1]])],
            'url is a list of scalars' => [json_encode(['url' => ['none']])],
        ];
    }

    #[DataProvider('malformedIdBodies')]
    public function test_insert_without_a_usable_id_throws(string $method, string $body)
    {
        Http::fake(['*' => Http::response($body, 201)]);

        try {
            $this->calls()[$method](new Price2SpyApiClient);
            $this->fail('Expected a MalformedResponseException.');
        } catch (MalformedResponseException $e) {
            $this->assertSame(201, $e->getCode());
            $this->assertStringContainsString('did not return a new id', $e->getMessage());
        }
    }

    public static function malformedIdBodies(): array
    {
        $bodies = [
            'empty body' => '',
            'json object' => '{"productId":1}',
            'string id' => '"551000123"',
            'zero' => '0',
            'negative' => '-5',
            'decimal' => '12.5',
            'html' => '<html>ok</html>',
        ];

        $cases = [];
        foreach (['insertProduct', 'insertUrl'] as $method) {
            foreach ($bodies as $name => $body) {
                $cases["{$method} {$name}"] = [$method, $body];
            }
        }

        return $cases;
    }

    #[DataProvider('failedRequests')]
    public function test_failed_request_carries_the_status_as_the_code(string $method, int $status)
    {
        Http::fake(['*' => Http::response('error body', $status)]);

        try {
            $this->calls()[$method](new Price2SpyApiClient);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(MalformedResponseException::class, $e);
            $this->assertSame($status, $e->getCode());
            $this->assertSame('Price2Spy API request failed: error body', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public static function failedRequests(): array
    {
        $cases = [];
        foreach (['insertProduct', 'insertUrl', 'updateUrl', 'getUrls'] as $method) {
            foreach ([401, 404, 429, 500] as $status) {
                $cases["{$method} {$status}"] = [$method, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('transportFailures')]
    public function test_transport_failure_chains_the_connection_exception(string $method, int $expectedAttempts)
    {
        Sleep::fake();
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        try {
            $this->calls()[$method](new Price2SpyApiClient);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(ConnectionException::class, $e);
            $this->assertSame(0, $e->getCode());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }

        $this->assertSame($expectedAttempts, $attempts);
    }

    /**
     * A create is not idempotent, so a lost response is never replayed; a state-setting
     * update and a read are.
     */
    public static function transportFailures(): array
    {
        return [
            'insertProduct is not retried' => ['insertProduct', 1],
            'insertUrl is not retried' => ['insertUrl', 1],
            'updateUrl is retried' => ['updateUrl', 3],
            'getUrls is retried' => ['getUrls', 3],
        ];
    }

    public function test_new_methods_are_callable_through_the_facade()
    {
        Http::fake([
            self::BASE.'insert-product' => Http::response('551000123', 201),
            self::BASE.'insert-url' => Http::response('900003', 201),
            self::BASE.'update-url' => Http::response('{}', 200),
            self::BASE.'get-urls' => Http::response($this->getFixtureJsonContent('get_urls_empty.json'), 200),
        ]);

        $this->assertSame(551000123, Price2SpyApiFacade::insertProduct(new InsertProductRequest(productName: 'A')));
        $this->assertSame(900003, Price2SpyApiFacade::insertUrl(new InsertUrlRequest(productId: 1, url: 'https://example.com')));
        Price2SpyApiFacade::updateUrl(new UpdateUrlRequest(urlId: 900003, active: true));
        $this->assertSame([], Price2SpyApiFacade::getUrls('1')->urls);

        Http::assertSentCount(4);
    }

    /**
     * @return array<string, Closure(Price2SpyApiClient): mixed>
     */
    private function calls(): array
    {
        return [
            'insertProduct' => fn (Price2SpyApiClient $client) => $client->insertProduct(new InsertProductRequest(productName: 'A')),
            'insertUrl' => fn (Price2SpyApiClient $client) => $client->insertUrl(new InsertUrlRequest(productId: 1, url: 'https://example.com')),
            'updateUrl' => fn (Price2SpyApiClient $client) => $client->updateUrl(new UpdateUrlRequest(urlId: 1, active: false)),
            'getUrls' => fn (Price2SpyApiClient $client) => $client->getUrls('1'),
        ];
    }
}
