<?php

namespace Karnoweb\Wallet\DTOs;

use Karnoweb\Wallet\Models\WalletOperation;
use Karnoweb\Wallet\Models\WalletTransaction;

/**
 * Result of a multi-transaction operation (transfer, grant). Exposes both
 * sides of the ledger movement plus the shared idempotency envelope.
 */
final class WalletOperationResult
{
    public function __construct(
        public readonly WalletOperation $operation,
        public readonly WalletTransaction $sourceTransaction,
        public readonly WalletTransaction $destinationTransaction,
    ) {
    }
}
