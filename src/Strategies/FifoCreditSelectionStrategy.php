<?php

namespace Karnoweb\Wallet\Strategies;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Contracts\CreditSelectionStrategy;

/**
 * Consumes the oldest eligible credit first: `created_at ASC, id ASC`.
 *
 * Receives already-filtered candidate credits; it only plans amounts, it
 * never mutates the database. Locking and mutation belong to
 * {@see \Karnoweb\Wallet\Services\AllocationService}.
 */
class FifoCreditSelectionStrategy implements CreditSelectionStrategy
{
    public function plan(Collection $candidates, int $amountRequired): Collection
    {
        $ordered = $candidates->sortBy([
            ['created_at', 'asc'],
            ['id', 'asc'],
        ])->values();

        $plan = collect();
        $remaining = $amountRequired;

        foreach ($ordered as $credit) {
            if ($remaining <= 0) {
                break;
            }

            $available = $credit->remaining_amount;

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);

            $plan->push([
                'credit' => $credit,
                'amount' => $take,
            ]);

            $remaining -= $take;
        }

        return $plan;
    }
}
