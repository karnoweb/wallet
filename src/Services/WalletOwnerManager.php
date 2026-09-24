<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Karnoweb\Wallet\DTOs\CreditRules;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\DTOs\WalletOperationResult;
use Karnoweb\Wallet\DTOs\WalletSummary;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Fluent object returned by `Wallet::for($owner)` / `WalletManager::for()`.
 * Holds the owner and (optionally) a specific selected wallet, and
 * delegates every financial operation to the specialized flow services.
 *
 * The `HasWallets` Trait is a thin wrapper over this exact class: it
 * contains no duplicate financial logic.
 */
class WalletOwnerManager
{
    protected ?Wallet $selectedWallet = null;

    public function __construct(protected ?Model $owner = null)
    {
    }

    public function using(Wallet $wallet): static
    {
        $this->selectedWallet = $wallet;

        return $this;
    }

    /**
     * Resolve (and, when `$create` is true and settings allow it, create)
     * the wallet this manager operates on. When no wallet has been
     * explicitly selected via {@see using()}, this resolves the owner's
     * single wallet.
     */
    public function wallet(bool $create = true): ?Wallet
    {
        if ($this->selectedWallet) {
            return $this->selectedWallet;
        }

        if (! $this->owner) {
            return null;
        }

        $walletClass = ConfiguredModels::wallet();

        $existing = $walletClass::query()
            ->where('reference_type', $this->owner->getMorphClass())
            ->where('reference_id', $this->owner->getKey())
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $create) {
            return null;
        }

        $settings = $this->settings()->resolve($this->owner);

        if (! ($settings['auto_create'] ?? true)) {
            return null;
        }

        return $this->createWalletWithLock();
    }

    /**
     * Locks the owner row for the duration of the DB transaction so two
     * concurrent requests can never create two wallets for the same owner.
     */
    protected function createWalletWithLock(): Wallet
    {
        return DB::transaction(function () {
            $ownerClass = get_class($this->owner);

            $ownerClass::query()->whereKey($this->owner->getKey())->lockForUpdate()->first();

            $walletClass = ConfiguredModels::wallet();

            $existing = $walletClass::query()
                ->where('reference_type', $this->owner->getMorphClass())
                ->where('reference_id', $this->owner->getKey())
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = new $walletClass();
            $wallet->fill([
                'reference_type' => $this->owner->getMorphClass(),
                'reference_id' => $this->owner->getKey(),
            ]);
            $wallet->save();

            return $wallet;
        });
    }

    public function balance(): int
    {
        $wallet = $this->wallet(false);

        return $wallet ? app(BalanceService::class)->balance($wallet) : 0;
    }

    public function spendableBalance(array|WalletContext $context = []): int
    {
        $wallet = $this->wallet(false);

        return $wallet ? app(BalanceService::class)->spendableBalance($wallet, $context) : 0;
    }

    public function withdrawableBalance(array|WalletContext $context = []): int
    {
        $wallet = $this->wallet(false);

        return $wallet ? app(BalanceService::class)->withdrawableBalance($wallet, $context) : 0;
    }

    public function charge(int $amount, array|WalletContext $options = []): WalletTransaction
    {
        $wallet = $this->wallet(true);
        ['context' => $context, 'settings' => $settings] = $this->buildContext($options);

        $rules = $this->extractRules($context);

        return app(ChargeService::class)->charge($wallet, $amount, $context, $settings, $rules);
    }

    public function pay(int $amount, array|WalletContext $options = []): WalletTransaction
    {
        $wallet = $this->wallet(true);
        ['context' => $context, 'settings' => $settings] = $this->buildContext($options);

        return app(PaymentService::class)->pay($wallet, $amount, $context, $settings);
    }

    public function deduct(int $amount, array|WalletContext $options = []): WalletTransaction
    {
        $wallet = $this->wallet(true);
        ['context' => $context, 'settings' => $settings] = $this->buildContext($options);

        return app(DeductService::class)->deduct($wallet, $amount, $context, $settings);
    }

    public function transferTo(Model|Wallet $destination, int $amount, array|WalletContext $options = []): WalletOperationResult
    {
        $sourceWallet = $this->wallet(true);
        $destinationWallet = $this->resolveDestinationWallet($destination);

        ['context' => $context, 'settings' => $settings] = $this->buildContext($options);

        return app(TransferService::class)->transfer($sourceWallet, $destinationWallet, $amount, $context, $settings);
    }

    public function grantTo(Model|Wallet $destination, int $amount, array|CreditRules $rules = [], array|WalletContext $options = []): WalletOperationResult
    {
        $sourceWallet = $this->wallet(true);
        $destinationWallet = $this->resolveDestinationWallet($destination);

        ['context' => $context, 'settings' => $settings] = $this->buildContext($options);

        return app(GrantService::class)->grant($sourceWallet, $destinationWallet, $amount, CreditRules::fromArray($rules), $context, $settings);
    }

    public function refund(WalletTransaction $payment, ?int $amount = null, array $options = []): WalletTransaction
    {
        $wallet = $this->wallet(false) ?? $payment->wallet;

        ['context' => $context, 'settings' => $settings] = $this->buildContext($options);

        return app(RefundService::class)->refund($wallet, $payment, $amount, $context, $settings);
    }

    public function transactions(array $filters = []): Builder
    {
        $wallet = $this->wallet(true);

        $query = $wallet->transactions()->getQuery();

        return empty($filters) ? $query : app(WalletReportService::class)->applyFilters($query, $filters);
    }

    public function statement(array $filters = []): LengthAwarePaginator
    {
        $wallet = $this->wallet(true);

        return app(WalletReportService::class)->statement($wallet, $filters);
    }

    public function summary(array $filters = []): WalletSummary
    {
        $wallet = $this->wallet(true);

        return app(WalletReportService::class)->summary($wallet, $filters);
    }

    protected function resolveDestinationWallet(Model|Wallet $destination): Wallet
    {
        if ($destination instanceof Wallet) {
            return $destination;
        }

        return app(WalletManager::class)->for($destination)->wallet(true);
    }

    protected function buildContext(array|WalletContext $options): array
    {
        return app(OperationContextService::class)->build($this->owner, $options);
    }

    protected function extractRules(WalletContext $context): ?CreditRules
    {
        $rules = $context->option('rules');

        return $rules !== null ? CreditRules::fromArray($rules) : null;
    }

    protected function settings(): WalletSettingsService
    {
        return app(WalletSettingsService::class);
    }
}
