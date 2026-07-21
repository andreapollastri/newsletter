<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\MessageSend;
use App\Services\MessageCompletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPendingEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('newsletter-admin');
    }

    /**
     * Execute the job.
     */
    public function handle(MessageCompletionService $completionService): void
    {
        $pendingSends = MessageSend::query()
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->with(['message', 'subscriber'])
            ->get();

        foreach ($pendingSends as $messageSend) {
            try {
                SendNewsletterEmail::dispatch($messageSend->id)->onQueue('newsletters');
            } catch (\Exception $e) {
                $messageSend->update([
                    'failed_at' => now(),
                    'error_message' => 'Failed to queue: '.$e->getMessage(),
                ]);
            }
        }

        Message::query()
            ->where('status', MessageStatus::Sending)
            ->get()
            ->each(fn (Message $message) => $completionService->completeIfFinished($message));
    }
}
