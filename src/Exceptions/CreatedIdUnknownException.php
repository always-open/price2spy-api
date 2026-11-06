<?php

namespace AlwaysOpen\Price2SpyApi\Exceptions;

/**
 * A create endpoint (insert-product, insert-url) answered 2xx, so Price2Spy created the
 * item, but the body did not carry its new id. The create succeeded: do not retry it, or
 * a duplicate is created. Look the item up to learn its id.
 */
class CreatedIdUnknownException extends MalformedResponseException {}
