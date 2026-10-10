<?php

namespace Tests\Support;

use RuntimeException;

/**
 * The real-engine harness refused to continue. Either the tests were simply not switched on (skip them), or the
 * configuration is unsafe for destructive setup (fail loudly). Nothing destructive has run when this is thrown.
 */
final class RealEngineRefused extends RuntimeException
{
    private function __construct(string $message, private readonly bool $notEnabled)
    {
        parent::__construct($message);
    }

    public static function notEnabled(string $message): self
    {
        return new self($message, true);
    }

    public static function unsafe(string $message): self
    {
        return new self('REFUSED, nothing was touched: '.$message, false);
    }

    /** True when the real-engine tests were just not opted into; every other refusal is a configuration problem. */
    public function isNotEnabled(): bool
    {
        return $this->notEnabled;
    }
}
