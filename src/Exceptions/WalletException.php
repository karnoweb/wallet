<?php

namespace Karnoweb\Wallet\Exceptions;

use RuntimeException;

/**
 * Base class for every domain exception raised by the Wallet package.
 * Application code may catch this type to handle any wallet-domain
 * failure generically, or catch the specific subclasses for precise
 * handling.
 */
class WalletException extends RuntimeException
{
}
