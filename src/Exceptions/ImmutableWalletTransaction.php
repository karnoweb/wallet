<?php

namespace Karnoweb\Wallet\Exceptions;

class ImmutableWalletTransaction extends WalletException
{
    public static function make(string $action): self
    {
        return new self("WalletTransaction records are immutable and cannot be {$action}.");
    }
}
