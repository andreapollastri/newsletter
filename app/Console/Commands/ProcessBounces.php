<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImapBounces;
use Illuminate\Console\Command;

class ProcessBounces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:process-bounces';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process email bounces from IMAP server (requires webklex/laravel-imap and NEWSLETTER_IMAP_ENABLED=true)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('newsletter.imap.enabled')) {
            $this->warn('IMAP bounce processing is disabled. Set NEWSLETTER_IMAP_ENABLED=true to enable.');

            return self::SUCCESS;
        }

        if (! class_exists('Webklex\\IMAP\\Facades\\Client')) {
            $this->warn('Missing dependency: run composer require webklex/laravel-imap');

            return self::SUCCESS;
        }

        $this->info('Dispatching IMAP bounce processing job...');

        ProcessImapBounces::dispatch();

        $this->info('Job dispatched successfully.');

        return self::SUCCESS;
    }
}
