<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Every model used by the package can be overridden here. Custom models
    | MUST extend the package base model so relations/casts keep working.
    |
    */
    'models' => [
        'wallet' => \Karnoweb\Wallet\Models\Wallet::class,
        'operation' => \Karnoweb\Wallet\Models\WalletOperation::class,
        'transaction' => \Karnoweb\Wallet\Models\WalletTransaction::class,
        'credit' => \Karnoweb\Wallet\Models\WalletCredit::class,
        'credit_scope' => \Karnoweb\Wallet\Models\WalletCreditScope::class,
        'allocation' => \Karnoweb\Wallet\Models\WalletAllocation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Lowest-priority settings. May be overridden per owner class, per model
    | instance (walletSettings()) or per operation option.
    |
    */
    'defaults' => [
        'auto_create' => true,
        'allow_negative' => false,
        'cash_withdrawable_default' => true,
        'club_required' => false,
        'idempotency_required' => false,
        'credit_selection_strategy' => \Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Owners
    |--------------------------------------------------------------------------
    |
    | Per owner-model-class setting overrides. Example:
    |
    | \App\Models\Contract::class => [
    |     'allow_negative' => true,
    | ],
    |
    */
    'owners' => [
        // App\Models\User::class => [...]
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolvers
    |--------------------------------------------------------------------------
    |
    | Contracts resolved from the container. Host applications may replace
    | any of these with their own implementation.
    |
    */
    'resolvers' => [
        'club' => \Karnoweb\Wallet\Resolvers\DefaultClubResolver::class,
        'causer' => \Karnoweb\Wallet\Resolvers\DefaultCauserResolver::class,
        'settings' => \Karnoweb\Wallet\Resolvers\DefaultWalletSettingsResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'default_per_page' => 30,
        'max_per_page' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiration
    |--------------------------------------------------------------------------
    */
    'expiration' => [
        'chunk_size' => 500,
    ],
];
