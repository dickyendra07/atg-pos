<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Variant cannot be sold at an outlet. getMessage() is the full sentence checkout has always shown;
 * the reason code and the short cashier wording let the Cashier UI explain the same rule briefly.
 */
class SaleNotEligibleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly string $cashierMessage,
        public readonly ?int $variantId = null,
        public readonly ?string $displayName = null,
    ) {
        parent::__construct($message);
    }
}
