<?php

namespace Karnoweb\Wallet\Enums;

enum WalletOperationType: string
{
    case Charge = 'charge';
    case Payment = 'payment';
    case Transfer = 'transfer';
    case Deduct = 'deduct';
    case Refund = 'refund';
    case Grant = 'grant';
    case Expiration = 'expiration';
}
