<?php

namespace Karnoweb\Wallet\Exceptions;

class ClubRequired extends WalletException
{
    public static function make(): self
    {
        return new self('A club_id is required for this wallet operation but could not be resolved.');
    }
}
