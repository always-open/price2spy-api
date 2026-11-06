<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use Spatie\LaravelData\Data;

/**
 * Body of POST /update-product. Price2Spy leaves every omitted field unchanged and
 * clears a field given an empty string or 0, never null, so toArray() drops the
 * null properties and an unset field is never sent.
 */
class UpdateProductRequest extends Data
{
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

    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn ($value) => $value !== null);
    }
}
