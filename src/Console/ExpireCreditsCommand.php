<?php

namespace Karnoweb\Wallet\Console;

use Illuminate\Console\Command;
use Karnoweb\Wallet\Services\ExpirationService;

/**
 * `php artisan wallet:expire-credits`
 *
 * Processes every credit whose `expires_at <= now` and
 * `remaining_amount > 0` (burn/return), in configured chunks. Idempotent:
 * running it twice never duplicates a financial effect.
 */
class ExpireCreditsCommand extends Command
{
    protected $signature = 'wallet:expire-credits';

    protected $description = 'Process due wallet credit expirations (burn/return).';

    public function handle(ExpirationService $service): int
    {
        $chunkSize = (int) config('wallet.expiration.chunk_size', 500);

        $summary = $service->expireDueCredits($chunkSize);

        $this->info(sprintf(
            'Wallet credit expiration: processed=%d skipped=%d failed=%d',
            $summary['processed'],
            $summary['skipped'],
            count($summary['failed'])
        ));

        foreach ($summary['failed'] as $failure) {
            $this->error("Credit #{$failure['credit_id']}: {$failure['message']}");
        }

        return empty($summary['failed']) ? self::SUCCESS : self::FAILURE;
    }
}
