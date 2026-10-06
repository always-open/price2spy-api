<?php

namespace AlwaysOpen\Price2SpyApi\Exceptions;

use RuntimeException;

/**
 * A successful (2xx) response whose body does not have the documented shape:
 * not JSON, an error envelope, or a renamed key. Distinct from an empty result,
 * which Price2Spy returns with the full structure and an empty list.
 */
class MalformedResponseException extends RuntimeException {}
