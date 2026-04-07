<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use Spatie\LaravelData\Data;

class Price extends Data
{
    public function __construct(
        public readonly ?string $currency,
        public readonly ?float $amount,
    ) {}
}
