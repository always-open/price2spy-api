<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class GetUrlsResponse extends Data
{
    public function __construct(
        #[DataCollectionOf(ProductUrl::class)]
        public readonly ?array $urls,
    ) {}
}
