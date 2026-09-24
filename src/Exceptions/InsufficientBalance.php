<?php

namespace Karnoweb\Wallet\Exceptions;

class InsufficientBalance extends WalletException
{
    public static function forAmount(int $requested, int $available): self
    {
        return new self(
            "Insufficient wallet balance: requested {$requested}, available {$available}."
        );
    }
}
