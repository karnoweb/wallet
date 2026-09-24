<?php

namespace Karnoweb\Wallet\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\DTOs\CreditRules;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\DTOs\WalletOperationResult;
use Karnoweb\Wallet\DTOs\WalletSummary;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Services\WalletManager;
use Karnoweb\Wallet\Services\WalletOwnerManager;
use Karnoweb\Wallet\Services\WalletSettingsService;

/**
 * Adds wallet access, balance methods, financial operations and reports
 * to any Eloquent model. Every method here is a thin wrapper over
 * {@see WalletOwnerManager}; there is no duplicate financial logic in
 * this Trait (the same manager backs the `Wallet` Facade).
 *
 * One owner has exactly one wallet. There is no multi-wallet relation
 * surface on the Trait.
 *
 * A basic consumer never needs to know about WalletCredit, WalletAllocation,
 * strategies, resolvers or events to use this Trait.
 */
trait HasWallets
{
    /**
     * When `auto_create` is enabled for this owner, create the wallet
     * immediately after the owner row is persisted. Lazy create via
     * {@see wallet()} remains as a safety net for owners that predate
     * the Trait or temporarily had `auto_create=false`.
     */
    protected static function bootHasWallets(): void
    {
        static::created(function (Model $owner): void {
            $settings = app(WalletSettingsService::class)->resolve($owner);

            if (! ($settings['auto_create'] ?? true)) {
                return;
            }

            app(WalletManager::class)->for($owner)->wallet(true);
        });
    }

    /**
     * Get (or, when `$create` is true and settings allow it, create)
     * this model's wallet. Creation is race-safe: see
     * {@see WalletOwnerManager}.
     */
    public function wallet(bool $create = true): ?Wallet
    {
        return $this->walletManager()->wallet($create);
    }

    public function balance(?Wallet $wallet = null): int
    {
        return $this->walletManagerFor($wallet)->balance();
    }

    public function spendableBalance(array|WalletContext $context = [], ?Wallet $wallet = null): int
    {
        return $this->walletManagerFor($wallet)->spendableBalance($context);
    }

    public function withdrawableBalance(array|WalletContext $context = [], ?Wallet $wallet = null): int
    {
        return $this->walletManagerFor($wallet)->withdrawableBalance($context);
    }

    public function charge(int $amount, array|WalletContext $options = [], ?Wallet $wallet = null): WalletTransaction
    {
        return $this->walletManagerFor($wallet)->charge($amount, $options);
    }

    public function pay(int $amount, array|WalletContext $options = [], ?Wallet $wallet = null): WalletTransaction
    {
        return $this->walletManagerFor($wallet)->pay($amount, $options);
    }

    public function deduct(int $amount, array|WalletContext $options = [], ?Wallet $wallet = null): WalletTransaction
    {
        return $this->walletManagerFor($wallet)->deduct($amount, $options);
    }

    public function transferTo(Model|Wallet $destination, int $amount, array|WalletContext $options = [], ?Wallet $sourceWallet = null): WalletOperationResult
    {
        return $this->walletManagerFor($sourceWallet)->transferTo($destination, $amount, $options);
    }

    public function refund(WalletTransaction $payment, ?int $amount = null, array $options = []): WalletTransaction
    {
        return $this->walletManager()->refund($payment, $amount, $options);
    }

    public function grantTo(Model|Wallet $destination, int $amount, array|CreditRules $rules = [], array|WalletContext $options = [], ?Wallet $sourceWallet = null): WalletOperationResult
    {
        return $this->walletManagerFor($sourceWallet)->grantTo($destination, $amount, $rules, $options);
    }

    public function transactions(?Wallet $wallet = null): Builder
    {
        return $this->walletManagerFor($wallet)->transactions();
    }

    public function statement(array $filters = [], ?Wallet $wallet = null): LengthAwarePaginator
    {
        return $this->walletManagerFor($wallet)->statement($filters);
    }

    public function summary(array $filters = [], ?Wallet $wallet = null): WalletSummary
    {
        return $this->walletManagerFor($wallet)->summary($filters);
    }

    /**
     * Lowest-but-one priority owner-level settings override. Highest
     * priority is an explicit per-operation option; lowest is
     * `config('wallet.defaults')`. See the settings precedence in the
     * implementation blueprint, section 3.
     *
     * Kept as `walletSettings()` (not `settings()`) deliberately: this is
     * a consumer-override hook, and a bare `settings()` name collides with
     * many host-model relations/accessors.
     */
    public function walletSettings(): array
    {
        return [];
    }

    protected function walletManager(): WalletOwnerManager
    {
        return app(WalletManager::class)->for($this);
    }

    protected function walletManagerFor(?Wallet $wallet): WalletOwnerManager
    {
        $manager = $this->walletManager();

        if ($wallet) {
            $manager->using($wallet);
        }

        return $manager;
    }
}
