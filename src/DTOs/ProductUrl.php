<?php

namespace AlwaysOpen\Price2SpyApi\DTOs;

use Spatie\LaravelData\Data;

class ProductUrl extends Data
{
    public function __construct(
        public readonly ?int $urlId,
        public readonly ?string $url,
        public readonly ?string $productName,
        public readonly ?string $siteHumanName,
        public readonly ?string $siteCountryISOCode,
        public readonly ?bool $showInReports,
        public readonly ?bool $active,
        public readonly ?string $image1Url,
        public readonly ?int $counterFailed,
        public readonly ?string $lastError,
        public readonly ?string $lastChecked,
        public readonly ?Measurement $lastMeasurement,
        public readonly ?Measurement $secondToLastMeasurement,
        public readonly ?string $dateAdded,
        public readonly ?string $dateModified,
        public readonly ?int $addedById,
        public readonly ?int $modifiedById,
    ) {}
}
