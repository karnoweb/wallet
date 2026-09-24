<?php

namespace Karnoweb\Wallet\Enums;

enum WalletTransactionType: string
{
    case Charge = 'charge';
    case Payment = 'payment';
    case Transfer = 'transfer';
    case Deduct = 'deduct';
    case Refund = 'refund';
    case System = 'system';
}
