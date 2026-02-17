<?php

namespace App\Console\Commands\Newsletter;

use App\Services\EmailRateLimiter;
use Illuminate\Console\Command;

class CheckRateLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:rate-limits
                            {--reset : Reset all rate limit counters}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display current email rate limit statistics or reset counters';

    /**
     * Execute the console command.
     */
    public function handle(EmailRateLimiter $rateLimiter): int
    {
        if ($this->option('reset')) {
            $rateLimiter->reset();
            $this->info('✓ All rate limit counters have been reset.');

            return self::SUCCESS;
        }

        $stats = $rateLimiter->getStats();

        $this->newLine();
        $this->info('Email Rate Limit Statistics');
        $this->newLine();

        $tableData = [];

        foreach ($stats as $period => $data) {
            $limit = $data['limit'];
            $current = $data['current'];
            $resetsAt = $data['resets_at'];

            $status = $limit > 0 ? ($current >= $limit ? '<fg=red>LIMIT REACHED</>' : '<fg=green>OK</>') : '<fg=yellow>UNLIMITED</>';
            $limitDisplay = $limit > 0 ? "{$current} / {$limit}" : 'Unlimited';
            $resetsAtDisplay = $resetsAt ? $resetsAt->diffForHumans() : 'N/A';

            $tableData[] = [
                'Period' => ucwords(str_replace('_', ' ', $period)),
                'Usage' => $limitDisplay,
                'Status' => $status,
                'Resets' => $resetsAtDisplay,
            ];
        }

        $this->table(
            ['Period', 'Usage', 'Status', 'Resets'],
            $tableData
        );

        $this->newLine();
        $this->comment('Tip: Use --reset flag to reset all counters');
        $this->newLine();

        return self::SUCCESS;
    }
}
