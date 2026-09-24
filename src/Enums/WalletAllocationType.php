<?php

namespace Karnoweb\Wallet\Enums;

enum WalletAllocationType: string
{
    case Consume = 'consume';
    case Restore = 'restore';
}
