<?php

namespace Karnoweb\Wallet\Exceptions;

class ImmutableWalletAllocation extends WalletException
{
    public static function make(string $action): self
    {
        return new self("WalletAllocation records are immutable and cannot be {$action}.");
    }
}
