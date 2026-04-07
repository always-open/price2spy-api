<?php

namespace AlwaysOpen\Price2SpyApi;

use Illuminate\Support\Facades\Facade;

class Price2SpyApiFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Price2SpyApiClient::class;
    }
}
