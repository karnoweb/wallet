<?php

namespace Karnoweb\Wallet\Exceptions;

class IdempotencyConflict extends WalletException
{
    public static function forKey(string $type, string $key): self
    {
        return new self(
            "Idempotency conflict for operation type [{$type}] and key [{$key}]: the payload differs from the original request."
        );
    }
}
