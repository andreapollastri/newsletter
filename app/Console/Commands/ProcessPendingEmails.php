<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use Illuminate\Console\Command;

class ProcessPendingEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:process-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all pending newsletter emails directly (bypassing queue)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Dispatching job to process pending newsletter emails...');

        $pendingSends = MessageSend::whereNull('sent_at')
            ->whereNull('failed_at')
            ->count();

        if ($pendingSends === 0) {
            $this->info('✅ No pending emails to process.');
            return self::SUCCESS;
        }

        $this->info("📧 Found {$pendingSends} pending emails to process.");
        $this->info('📦 Dispatching ProcessPendingEmails job to queue...');

        // Dispatch the job to queue instead of processing directly
        \App\Jobs\ProcessPendingEmails::dispatch();

        $this->info('✅ Job dispatched! The worker will process pending emails.');
        $this->info('📊 Monitor progress with: php artisan queue:status');

        return self::SUCCESS;
    }
}
