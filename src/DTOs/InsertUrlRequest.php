<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use AlwaysOpen\Price2SpyApi\DTOs\Concerns\OmitsNullProperties;
use Spatie\LaravelData\Data;

/**
 * Body of POST /insert-url. Only the non-null properties are sent.
 */
class InsertUrlRequest extends Data
{
    use OmitsNullProperties;

    public function __construct(
        public readonly int $productId,
        public readonly string $url,
        public readonly ?bool $active = null,
        public readonly ?bool $showInReports = null,
        public readonly ?string $note1 = null,
        public readonly ?string $note2 = null,
        public readonly ?string $note3 = null,
    ) {}
}
