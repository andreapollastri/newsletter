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
    protected $description = 'Process email bounces from IMAP server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Dispatching IMAP bounce processing job...');

        ProcessImapBounces::dispatch();

        $this->info('Job dispatched successfully.');

        return self::SUCCESS;
    }
}
