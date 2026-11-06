<?php

namespace AlwaysOpen\Price2SpyApi\DTOs\Concerns;

/**
 * For request bodies: Price2Spy leaves every omitted field unchanged and clears a field
 * given an empty string or 0, never null, so null properties are dropped and an unset
 * field is never sent.
 */
trait OmitsNullProperties
{
    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn ($value) => $value !== null);
    }
}
