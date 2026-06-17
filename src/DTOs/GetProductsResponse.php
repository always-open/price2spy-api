<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class GetProductsResponse extends Data
{
    public function __construct(
        #[DataCollectionOf(Product::class)]
        public readonly ?array $products,
    ) {}
}
