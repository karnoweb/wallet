<?php

namespace Karnoweb\Wallet\Enums;

/**
 * The database column `sign` is a native integer (1 or -1). This enum is
 * int-backed so the case values map exactly onto the persisted column
 * without any string<->int translation layer.
 */
enum WalletSign: int
{
    case Credit = 1;
    case Debit = -1;
}
