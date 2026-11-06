<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use AlwaysOpen\Price2SpyApi\DTOs\Concerns\OmitsNullProperties;
use Spatie\LaravelData\Data;

/**
 * Body of POST /update-product. Only the non-null properties are sent.
 */
class UpdateProductRequest extends Data
{
    use OmitsNullProperties;

    /**
     * @param  'DAYS'|'HOURS'|null  $checkFrequencyType
     */
    public function __construct(
        public readonly int $productId,
        public readonly ?bool $active = null,
        public readonly ?string $sku = null,
        public readonly ?string $internalId = null,
        public readonly ?string $productName = null,
        public readonly ?string $brandName = null,
        public readonly ?string $categoryName = null,
        public readonly ?string $supplierName = null,
        public readonly ?string $checkFrequencyType = null,
        public readonly ?int $checkFrequencyInterval = null,
        public readonly ?float $targetPrice = null,
        public readonly ?float $costPrice = null,
        public readonly ?string $comment1 = null,
        public readonly ?string $comment2 = null,
        public readonly ?string $customField1 = null,
        public readonly ?string $customField2 = null,
        public readonly ?string $customField3 = null,
        public readonly ?string $customField4 = null,
        public readonly ?string $customField5 = null,
        public readonly ?string $customField6 = null,
        public readonly ?string $customField7 = null,
        public readonly ?string $customField8 = null,
        public readonly ?string $customField9 = null,
        public readonly ?string $customField10 = null,
        public readonly ?string $metadata1Name = null,
        public readonly ?string $metadata2Name = null,
        public readonly ?string $metadata3Name = null,
    ) {}
}
