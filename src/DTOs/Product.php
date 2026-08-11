<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class Product extends Data
{
    public function __construct(
        public readonly ?int $productId,
        public readonly ?string $productName,
        public readonly ?string $checkFrequencyType,
        public readonly ?int $checkFrequencyInterval,
        public readonly ?bool $active,
        public readonly ?bool $showInReports,
        public readonly ?string $sku,
        public readonly ?string $internalId,
        public readonly ?string $brandName,
        public readonly ?int $brandId,
        public readonly ?Price $minPrice,
        public readonly ?Price $maxPrice,
        public readonly ?string $lastChecked,
        public readonly ?string $nextChecked,
        public readonly ?string $lastChange,
        public readonly ?float $targetPrice,
        public readonly ?string $dateAdded,
        public readonly ?string $dateModified,
        public readonly ?int $addedById,
        public readonly ?int $modifiedById,
        public readonly ?string $categoryName,
        public readonly ?int $categoryId,
        public readonly ?float $costPrice,
        public readonly ?string $customField1,
        public readonly ?string $customField2,
        public readonly ?string $customField3,
        #[DataCollectionOf(ProductUrl::class)]
        public readonly ?array $urls,
    ) {}

}
