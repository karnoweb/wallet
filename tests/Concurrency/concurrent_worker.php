<?php

/**
 * Standalone concurrency worker. Spawned by DoubleSpendTest via proc_open.
 * Bootstraps Orchestra Testbench so container/facades/config match the
 * parent test process, then runs one financial operation on a separate
 * OS-level PHP process (independent DB connection).
 */

$raw = stream_get_contents(STDIN);
$payload = json_decode($raw ?: '', true);

if (! is_array($payload)) {
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => 'invalid_payload']));
    exit(1);
}

require $payload['autoload'];

use Carbon\Carbon;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Services\DeductService;
use Karnoweb\Wallet\Services\PaymentService;
use Karnoweb\Wallet\Services\RefundService;
use Karnoweb\Wallet\Services\TransferService;
use Karnoweb\Wallet\WalletServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

try {
    $connection = $payload['connection'];

    $harness = new class('ConcurrentWorker') extends OrchestraTestCase
    {
        public static array $connection = [];

        protected function getPackageProviders($app)
        {
            return [WalletServiceProvider::class];
        }

        protected function defineEnvironment($app)
        {
            $app['config']->set('database.default', 'wallet_concurrency');
            $app['config']->set('database.connections.wallet_concurrency', self::$connection);
        }

        public function bootWorker(): \Illuminate\Foundation\Application
        {
            $this->setUp();

            return $this->app;
        }

        protected function tearDown(): void
        {
            // Keep the shared concurrency DB intact for the parent process.
        }
    };

    $harness::$connection = $connection;
    $app = $harness->bootWorker();

    $mode = $payload['mode'];
    $job = $payload['job'];

    usleep(random_int(0, 40_000));

    $settings = [
        'auto_create' => true,
        'allow_negative' => false,
        'cash_withdrawable_default' => true,
        'club_required' => false,
        'idempotency_required' => false,
        'credit_selection_strategy' => \Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy::class,
    ];

    $context = new WalletContext(
        clubId: null,
        causerId: null,
        transactionId: null,
        transactionable: null,
        description: 'concurrency-worker',
        idempotencyKey: $job['idempotency_key'] ?? null,
        occurredAt: Carbon::now(),
        scopes: [],
        segments: [],
        metadata: [],
        raw: $job,
    );

    $op = $mode === 'mixed' ? ($job['op'] ?? 'pay') : $mode;

    $result = match ($op) {
        'pay' => (function () use ($app, $job, $context, $settings) {
            $wallet = Wallet::query()->findOrFail($job['wallet_id']);
            $tx = $app->make(PaymentService::class)->pay($wallet, (int) $job['amount'], $context, $settings);

            return ['ok' => true, 'transaction_id' => $tx->id, 'op' => 'pay'];
        })(),
        'deduct' => (function () use ($app, $job, $context, $settings) {
            $wallet = Wallet::query()->findOrFail($job['wallet_id']);
            $tx = $app->make(DeductService::class)->deduct($wallet, (int) $job['amount'], $context, $settings);

            return ['ok' => true, 'transaction_id' => $tx->id, 'op' => 'deduct'];
        })(),
        'transfer' => (function () use ($app, $job, $context, $settings) {
            $source = Wallet::query()->findOrFail($job['source_wallet_id']);
            $destination = Wallet::query()->findOrFail($job['destination_wallet_id']);
            $transfer = $app->make(TransferService::class)->transfer(
                $source,
                $destination,
                (int) $job['amount'],
                $context,
                $settings
            );

            return [
                'ok' => true,
                'transaction_id' => $transfer->sourceTransaction->id,
                'op' => 'transfer',
            ];
        })(),
        'refund' => (function () use ($app, $job, $context, $settings) {
            $wallet = Wallet::query()->findOrFail($job['wallet_id']);
            $payment = WalletTransaction::query()->findOrFail($job['payment_id']);
            $tx = $app->make(RefundService::class)->refund(
                $wallet,
                $payment,
                isset($job['amount']) ? (int) $job['amount'] : null,
                $context,
                $settings
            );

            return ['ok' => true, 'transaction_id' => $tx->id, 'op' => 'refund'];
        })(),
        default => throw new RuntimeException('Unknown op: '.$op),
    };

    fwrite(STDOUT, json_encode($result));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'error' => $e::class,
        'message' => $e->getMessage(),
    ]));
    exit(0);
}
