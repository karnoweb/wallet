<?php

namespace Karnoweb\Wallet\Tests\Support;

use Karnoweb\Wallet\Models\Wallet;

/**
 * Host-application Wallet subclass used to prove `config('wallet.models.wallet')`
 * is honored everywhere a Wallet is instantiated.
 */
class CustomWallet extends Wallet
{
}
