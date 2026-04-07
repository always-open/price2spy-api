<?php

namespace AlwaysOpen\Price2SpyApi\Tests;

use AlwaysOpen\Price2SpyApi\Price2SpyApiServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class BaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('vendor:publish', [
            '--provider' => Price2SpyApiServiceProvider::class,
            '--tag' => 'config',
        ]);

        Http::preventStrayRequests();

        $this->refreshApplication();
    }

    protected function getFixtureJsonContent(string $name): string
    {
        $content = $this->getFixtureContent($name);

        if ($content) {
            return $content;
        }

        return '{}';
    }

    protected function getFixtureContent(string $name): false|string
    {
        return file_get_contents(__DIR__."/Fixtures/{$name}");
    }

    protected function getPackageProviders($app)
    {
        return [
            Price2SpyApiServiceProvider::class,
        ];
    }
}
