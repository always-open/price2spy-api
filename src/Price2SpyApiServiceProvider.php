<?php

namespace AlwaysOpen\Price2SpyApi;

use Illuminate\Support\ServiceProvider;

class Price2SpyApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/price2spy-api.php', 'price2spy-api');

        $this->app->singleton(Price2SpyApiClient::class, function ($app) {
            return new Price2SpyApiClient(
                config('price2spy-api.base_url'),
                config('price2spy-api.api_key'),
                config('price2spy-api.timeout'),
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/price2spy-api.php' => config_path('price2spy-api.php'),
        ], 'config');
    }
}
