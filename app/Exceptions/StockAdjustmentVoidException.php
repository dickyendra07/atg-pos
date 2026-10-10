<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Stock Adjustment VOID was refused. Thrown inside the VOID transaction, so everything written so far is
 * rolled back; the reasons are meant to be shown to the user as they are.
 */
class StockAdjustmentVoidException extends RuntimeException
{
    /** @param string[] $reasons */
    public function __construct(public readonly array $reasons, public readonly bool $alreadyVoid = false)
    {
        parent::__construct(implode(' ', $reasons));
    }

    public static function alreadyVoid(): self
    {
        return new self(['Adjustment ini sudah berstatus VOID dan tidak bisa di-VOID lagi.'], true);
    }
}
