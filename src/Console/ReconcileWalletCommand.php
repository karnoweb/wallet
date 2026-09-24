<?php

namespace Karnoweb\Wallet\Console;

use Illuminate\Console\Command;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * `php artisan wallet:reconcile {wallet?} {--json}`
 *
 * Verifies, for one wallet or every wallet, that:
 *
 * - `credit.remaining_amount == credit.original_amount - consume + restore`
 * - `SUM(transaction.amount * transaction.sign) == SUM(credit.remaining_amount)`
 *
 * Reports mismatches with ids and expected/current amounts. This
 * command never auto-fixes anything; it is a read-only diagnostic tool.
 */
class ReconcileWalletCommand extends Command
{
    protected $signature = 'wallet:reconcile {wallet?} {--json}';

    protected $description = 'Check wallet transaction/credit/allocation consistency without auto-fixing.';

    public function handle(): int
    {
        $walletId = $this->argument('wallet');

        $walletClass = ConfiguredModels::wallet();

        $query = $walletClass::query();

        if ($walletId) {
            $query->whereKey($walletId);
        }

        $mismatches = [];

        $query->orderBy('id')->chunkById(100, function ($wallets) use (&$mismatches) {
            foreach ($wallets as $wallet) {
                $mismatches = array_merge($mismatches, $this->reconcileWallet($wallet));
            }
        });

        if ($this->option('json')) {
            $this->line(json_encode(['mismatches' => $mismatches], JSON_PRETTY_PRINT));
        } elseif (empty($mismatches)) {
            $this->info('No mismatches found.');
        } else {
            foreach ($mismatches as $mismatch) {
                $this->error(json_encode($mismatch));
            }
        }

        return empty($mismatches) ? self::SUCCESS : self::FAILURE;
    }

    protected function reconcileWallet(object $wallet): array
    {
        $mismatches = [];

        $transactionClass = ConfiguredModels::transaction();
        $creditClass = ConfiguredModels::credit();
        $allocationClass = ConfiguredModels::allocation();

        $credits = $creditClass::query()->where('wallet_id', $wallet->id)->get();

        foreach ($credits as $credit) {
            $consumed = (int) $allocationClass::query()
                ->where('wallet_credit_id', $credit->id)
                ->where('type', 'consume')
                ->sum('amount');

            $restored = (int) $allocationClass::query()
                ->where('wallet_credit_id', $credit->id)
                ->where('type', 'restore')
                ->sum('amount');

            $expectedRemaining = $credit->original_amount - $consumed + $restored;

            if ($expectedRemaining !== $credit->remaining_amount) {
                $mismatches[] = [
                    'wallet_id' => $wallet->id,
                    'check' => 'credit_remaining_amount',
                    'credit_id' => $credit->id,
                    'expected_remaining_amount' => $expectedRemaining,
                    'actual_remaining_amount' => $credit->remaining_amount,
                ];
            }
        }

        $transactionBalance = (int) ($transactionClass::query()
            ->where('wallet_id', $wallet->id)
            ->selectRaw('COALESCE(SUM(amount * sign), 0) as aggregate_balance')
            ->value('aggregate_balance') ?? 0);

        $creditTotal = (int) $creditClass::query()->where('wallet_id', $wallet->id)->sum('remaining_amount');

        if ($transactionBalance !== $creditTotal) {
            $mismatches[] = [
                'wallet_id' => $wallet->id,
                'check' => 'transaction_balance_vs_credit_total',
                'transaction_balance' => $transactionBalance,
                'credit_total' => $creditTotal,
            ];
        }

        return $mismatches;
    }
}
