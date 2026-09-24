<?php

namespace Karnoweb\Wallet\Enums;

enum CreditExpireAction: string
{
    case None = 'none';
    case Return = 'return';
    case Burn = 'burn';
}
