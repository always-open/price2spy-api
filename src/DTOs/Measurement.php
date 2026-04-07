<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use Spatie\LaravelData\Data;

class Measurement extends Data
{
    public function __construct(
        public readonly ?string $dateChecked,
        public readonly ?Price $price,
        public readonly ?bool $available,
        public readonly ?string $stockStatus,
        public readonly ?bool $isOnPromotion,
    ) {}
}
