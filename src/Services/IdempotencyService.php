<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Exceptions\IdempotencyConflict;
use Karnoweb\Wallet\Models\WalletOperation;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Guarantees that retried financial requests never produce duplicate
 * financial effects.
 *
 * Call sites MUST invoke {@see resolveOperation()} inside the same
 * database transaction that performs the financial writes. Creating the
 * operation outside that transaction would leave an orphan row when the
 * financial work rolls back, and a retry with the same key would then
 * fail looking up non-existent transactions.
 *
 * When the returned result is not "new", the caller MUST skip all
 * financial writes and return the transaction(s) already attached to
 * the returned operation.
 */
class IdempotencyService
{
    /**
     * @return array{operation: WalletOperation, is_new: bool}
     */
    public function resolveOperation(
        WalletOperationType|string $type,
        array $payload,
        ?string $idempotencyKey,
        bool $required
    ): array {
        $type = $type instanceof WalletOperationType ? $type->value : $type;
        $hash = $this->hashPayload($payload);

        if ($idempotencyKey === null) {
            if ($required) {
                throw new IdempotencyConflict('An idempotency key is required for this operation but none was provided.');
            }

            return [
                'operation' => $this->createOperation($type, null, $hash),
                'is_new' => true,
            ];
        }

        $operationClass = ConfiguredModels::operation();

        $existing = $operationClass::query()
            ->where('type', $type)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $this->reuseOrReclaim($existing, $type, $idempotencyKey, $hash);
        }

        try {
            return [
                'operation' => $this->createOperation($type, $idempotencyKey, $hash),
                'is_new' => true,
            ];
        } catch (UniqueConstraintViolationException|QueryException $e) {
            // Lost a race against a concurrent request creating the same
            // (type, idempotency_key) row. Under READ COMMITTED (see
            // AtomicWalletTransaction) a plain SELECT sees the winner.
            $existing = $this->waitForExistingOperation($operationClass, $type, $idempotencyKey);

            if (! $existing) {
                throw $e;
            }

            return $this->reuseOrReclaim($existing, $type, $idempotencyKey, $hash);
        }
    }

    protected function waitForExistingOperation(string $operationClass, string $type, string $idempotencyKey): ?WalletOperation
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $existing = $operationClass::query()
                ->where('type', $type)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            usleep(20_000);
        }

        return null;
    }

    /**
     * Reuse a completed operation, or reclaim an orphan row left by a
     * pre-hardening deployment that created the operation outside the
     * financial transaction.
     *
     * @return array{operation: WalletOperation, is_new: bool}
     */
    protected function reuseOrReclaim(
        WalletOperation $existing,
        string $type,
        string $idempotencyKey,
        string $hash
    ): array {
        $this->assertPayloadMatches($existing, $type, $idempotencyKey, $hash);

        if ($this->operationHasEffects($existing)) {
            return ['operation' => $existing, 'is_new' => false];
        }

        // Orphan: key claimed but no financial rows. Safe to delete and
        // recreate so a legitimate retry can proceed.
        $existing->delete();

        return [
            'operation' => $this->createOperation($type, $idempotencyKey, $hash),
            'is_new' => true,
        ];
    }

    protected function operationHasEffects(WalletOperation $operation): bool
    {
        $transactionClass = ConfiguredModels::transaction();

        return $transactionClass::query()
            ->where('operation_id', $operation->id)
            ->exists();
    }

    protected function assertPayloadMatches(WalletOperation $existing, string $type, string $idempotencyKey, string $hash): void
    {
        if ($existing->payload_hash !== $hash) {
            throw IdempotencyConflict::forKey($type, $idempotencyKey);
        }
    }

    protected function createOperation(string $type, ?string $idempotencyKey, string $hash): WalletOperation
    {
        $operation = ConfiguredModels::newOperation();
        $operation->fill([
            'type' => $type,
            'idempotency_key' => $idempotencyKey,
            'payload_hash' => $hash,
        ]);
        $operation->save();

        return $operation;
    }

    /**
     * Financial inputs only: owner/wallet ids, amount, destination, rules,
     * segment keys, external references. Server-generated timestamps must
     * never be part of the hash, otherwise identical retries would never
     * match.
     */
    public function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($this->normalize($payload)));
    }

    protected function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = array_map(fn ($v) => $this->normalize($v), $value);

            if (array_is_list($normalized)) {
                return $normalized;
            }

            ksort($normalized);

            return $normalized;
        }

        return $value;
    }
}
