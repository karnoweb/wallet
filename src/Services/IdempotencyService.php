<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Database\QueryException;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Exceptions\IdempotencyConflict;
use Karnoweb\Wallet\Models\WalletOperation;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Guarantees that retried financial requests never produce duplicate
 * financial effects.
 *
 * Every call site MUST call {@see resolveOperation()} before doing any
 * financial write. When the returned result is not "new", the caller
 * MUST skip all financial writes and instead return the transaction(s)
 * already attached to the returned operation.
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
            $this->assertPayloadMatches($existing, $type, $idempotencyKey, $hash);

            return ['operation' => $existing, 'is_new' => false];
        }

        try {
            return [
                'operation' => $this->createOperation($type, $idempotencyKey, $hash),
                'is_new' => true,
            ];
        } catch (QueryException $e) {
            // Lost a race against a concurrent request creating the same
            // (type, idempotency_key) row. Reload and validate instead of
            // failing the request.
            $existing = $operationClass::query()
                ->where('type', $type)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing) {
                throw $e;
            }

            $this->assertPayloadMatches($existing, $type, $idempotencyKey, $hash);

            return ['operation' => $existing, 'is_new' => false];
        }
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
