<?php

namespace Karnoweb\Wallet\Tests\Concurrency;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Real concurrency proofs against MySQL/MariaDB/PostgreSQL.
 *
 * SQLite cannot model concurrent row-level locking across independent
 * connections, so this suite is skipped unless WALLET_TEST_CONCURRENCY_DSN
 * is set.
 *
 * How to run:
 *
 *   # PowerShell
 *   $env:WALLET_TEST_CONCURRENCY_DSN="mysql://root:@127.0.0.1:3306/wallet_concurrency"
 *   vendor\bin\phpunit --testsuite=Concurrency
 *
 *   # bash
 *   WALLET_TEST_CONCURRENCY_DSN=mysql://root:@127.0.0.1:3306/wallet_concurrency \
 *     vendor/bin/phpunit --testsuite=Concurrency
 *
 * Workers are separate OS processes (proc_open + PHP CLI), each with its
 * own DB connection — not sequential calls in one process.
 */
class DoubleSpendTest extends TestCase
{
    protected function setUp(): void
    {
        $dsn = getenv('WALLET_TEST_CONCURRENCY_DSN') ?: ($_ENV['WALLET_TEST_CONCURRENCY_DSN'] ?? null);

        parent::setUp();

        if (! $dsn) {
            $this->markTestSkipped(
                'Concurrency suite requires WALLET_TEST_CONCURRENCY_DSN '.
                '(e.g. mysql://root:@127.0.0.1:3306/wallet_concurrency). Skipped on SQLite by design.'
            );
        }

        // Switch the live app onto a real row-locking database, then rebuild
        // schema there. parent::setUp() already migrated SQLite; that schema
        // is irrelevant for this suite.
        $this->configureConcurrencyConnection($dsn);
        \Illuminate\Support\Facades\DB::purge();
        \Illuminate\Support\Facades\DB::reconnect('wallet_concurrency');

        $this->dropPackageTables();

        $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php');
        sort($files);
        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }

        $this->createFixtureTables();
    }

    protected function configureConcurrencyConnection(string $dsn): void
    {
        $parts = parse_url($dsn);

        if ($parts === false || empty($parts['scheme'])) {
            $this->fail('Invalid WALLET_TEST_CONCURRENCY_DSN: '.$dsn);
        }

        $driver = $parts['scheme'] === 'postgres' ? 'pgsql' : $parts['scheme'];

        if (! in_array($driver, ['mysql', 'pgsql', 'mariadb'], true)) {
            $this->fail('Concurrency DSN driver must be mysql, mariadb, or pgsql; got '.$driver);
        }

        if ($driver === 'mariadb') {
            $driver = 'mysql';
        }

        $database = isset($parts['path']) ? ltrim($parts['path'], '/') : null;

        config()->set('database.default', 'wallet_concurrency');
        config()->set('database.connections.wallet_concurrency', [
            'driver' => $driver,
            'host' => $parts['host'] ?? '127.0.0.1',
            'port' => $parts['port'] ?? ($driver === 'pgsql' ? 5432 : 3306),
            'database' => $database,
            'username' => $parts['user'] ?? 'root',
            'password' => $parts['pass'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);
    }

    protected function dropPackageTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'wallet_allocations',
            'wallet_credit_scopes',
            'wallet_credits',
            'wallet_transactions',
            'wallet_operations',
            'wallets',
            'users',
            'organizations',
            'orders',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    /** CC001 */
    public function test_two_simultaneous_payments_never_both_succeed_beyond_available_credit(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $walletId = $user->wallet()->id;

        $results = $this->runTwoWorkers('pay', [
            ['wallet_id' => $walletId, 'amount' => 70_000],
            ['wallet_id' => $walletId, 'amount' => 70_000],
        ]);

        $successes = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
        $failures = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === false));

        $this->assertCount(1, $successes, 'Exactly one payment must succeed. Results: '.json_encode($results));
        $this->assertCount(1, $failures, 'Exactly one payment must fail. Results: '.json_encode($results));

        $remaining = (int) ConfiguredModels::credit()::query()->where('wallet_id', $walletId)->sum('remaining_amount');
        $this->assertSame(30_000, $remaining);
        $this->assertSame(30_000, $user->fresh()->balance());
        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());
    }

    /** CC002 */
    public function test_a_concurrent_payment_and_deduct_never_double_spend(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(50_000);
        $walletId = $user->wallet()->id;

        $results = $this->runTwoWorkers('mixed', [
            ['op' => 'pay', 'wallet_id' => $walletId, 'amount' => 40_000],
            ['op' => 'deduct', 'wallet_id' => $walletId, 'amount' => 40_000],
        ]);

        $successes = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(1, $successes, 'Exactly one of pay/deduct must succeed. Results: '.json_encode($results));

        $remaining = (int) ConfiguredModels::credit()::query()->where('wallet_id', $walletId)->sum('remaining_amount');
        $this->assertSame(10_000, $remaining);
        $this->assertGreaterThanOrEqual(0, $remaining);
    }

    /** CC003 */
    public function test_a_concurrent_transfer_pair_never_deadlocks_and_preserves_totals(): void
    {
        $a = User::create(['name' => 'A']);
        $b = User::create(['name' => 'B']);
        $a->charge(100_000);
        $b->charge(100_000);

        $results = $this->runTwoWorkers('transfer', [
            ['source_wallet_id' => $a->wallet()->id, 'destination_wallet_id' => $b->wallet()->id, 'amount' => 40_000],
            ['source_wallet_id' => $b->wallet()->id, 'destination_wallet_id' => $a->wallet()->id, 'amount' => 40_000],
        ]);

        $successes = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(2, $successes, 'Both opposite transfers should succeed without deadlock. Results: '.json_encode($results));

        $this->assertSame(100_000, $a->fresh()->balance());
        $this->assertSame(100_000, $b->fresh()->balance());
    }

    /** CC004 */
    public function test_concurrent_full_refunds_never_double_restore(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $payment = $user->pay(100_000);

        $results = $this->runTwoWorkers('refund', [
            ['wallet_id' => $user->wallet()->id, 'payment_id' => $payment->id, 'amount' => 100_000],
            ['wallet_id' => $user->wallet()->id, 'payment_id' => $payment->id, 'amount' => 100_000],
        ]);

        $successes = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(1, $successes, 'Exactly one full refund must succeed. Results: '.json_encode($results));

        $this->assertSame(100_000, $user->fresh()->balance());
        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'refund')->count());
    }

    /** CC005 */
    public function test_concurrent_partial_refunds_never_exceed_payment(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $payment = $user->pay(100_000);

        $results = $this->runTwoWorkers('refund', [
            ['wallet_id' => $user->wallet()->id, 'payment_id' => $payment->id, 'amount' => 70_000],
            ['wallet_id' => $user->wallet()->id, 'payment_id' => $payment->id, 'amount' => 70_000],
        ]);

        $successes = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
        $failures = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === false));

        $this->assertCount(1, $successes, 'Only one 70k refund may succeed. Results: '.json_encode($results));
        $this->assertCount(1, $failures);

        $refunded = (int) ConfiguredModels::transaction()::query()->where('type', 'refund')->sum('amount');
        $this->assertSame(70_000, $refunded);
        $this->assertSame(70_000, $user->fresh()->balance());
    }

    /** CC006 */
    public function test_concurrent_identical_idempotency_keys_produce_one_effect(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $walletId = $user->wallet()->id;

        $results = $this->runTwoWorkers('pay', [
            ['wallet_id' => $walletId, 'amount' => 40_000, 'idempotency_key' => 'concurrent-pay-1'],
            ['wallet_id' => $walletId, 'amount' => 40_000, 'idempotency_key' => 'concurrent-pay-1'],
        ]);

        $successes = array_values(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(2, $successes, 'Both callers should succeed (one create, one replay). Results: '.json_encode($results));

        $txIds = array_unique(array_map(fn ($r) => $r['transaction_id'] ?? null, $successes));
        $this->assertCount(1, $txIds);
        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());
        $this->assertSame(60_000, $user->fresh()->balance());
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    protected function runTwoWorkers(string $mode, array $jobs): array
    {
        $php = PHP_BINARY;
        $worker = __DIR__.DIRECTORY_SEPARATOR.'concurrent_worker.php';
        $autoload = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

        $connection = config('database.connections.wallet_concurrency');

        $processes = [];
        $pipes = [];

        foreach ($jobs as $index => $job) {
            $payload = json_encode([
                'mode' => $mode,
                'job' => $job,
                'connection' => $connection,
                'autoload' => $autoload,
            ], JSON_THROW_ON_ERROR);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open([$php, $worker], $descriptors, $pipeSet, dirname(__DIR__, 2));

            if (! is_resource($process)) {
                $this->fail('Failed to spawn concurrency worker process.');
            }

            fwrite($pipeSet[0], $payload);
            fclose($pipeSet[0]);

            stream_set_blocking($pipeSet[1], false);
            stream_set_blocking($pipeSet[2], false);

            $processes[$index] = $process;
            $pipes[$index] = $pipeSet;
        }

        usleep(50_000);

        $results = [];

        foreach ($processes as $index => $process) {
            $stdout = '';
            $stderr = '';
            $start = microtime(true);

            do {
                $stdout .= stream_get_contents($pipes[$index][1]) ?: '';
                $stderr .= stream_get_contents($pipes[$index][2]) ?: '';
                $status = proc_get_status($process);
                if (! $status['running']) {
                    break;
                }
                usleep(10_000);
            } while (microtime(true) - $start < 45);

            $stdout .= stream_get_contents($pipes[$index][1]) ?: '';
            $stderr .= stream_get_contents($pipes[$index][2]) ?: '';

            fclose($pipes[$index][1]);
            fclose($pipes[$index][2]);
            proc_close($process);

            $decoded = json_decode(trim($stdout), true);

            if (! is_array($decoded)) {
                $results[$index] = [
                    'ok' => false,
                    'error' => 'invalid_worker_output',
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                ];
            } else {
                $results[$index] = $decoded;
            }
        }

        return $results;
    }
}
