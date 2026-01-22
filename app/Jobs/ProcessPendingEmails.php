<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
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
    public function handle(): void
    {
        $pendingSends = MessageSend::whereNull('sent_at')
            ->whereNull('failed_at')
            ->with(['message', 'subscriber'])
            ->get();

        if ($pendingSends->isEmpty()) {
            return;
        }

        $processed = 0;
        $failed = 0;

        foreach ($pendingSends as $messageSend) {
            try {
                // Dispatch individual email job
                SendNewsletterEmail::dispatch($messageSend->id)->onQueue('newsletters');
                $processed++;
            } catch (\Exception $e) {
                // Mark as failed if dispatch fails
                $messageSend->update([
                    'failed_at' => now(),
                    'error_message' => 'Failed to queue: ' . $e->getMessage(),
                ]);
                $failed++;
            }
        }

        // Update message statuses for completed ones
        $messagesToCheck = Message::where('status', MessageStatus::Sending)->get();
        foreach ($messagesToCheck as $message) {
            $pendingForMessage = MessageSend::where('message_id', $message->id)
                ->whereNull('sent_at')
                ->whereNull('failed_at')
                ->count();

            if ($pendingForMessage === 0) {
                $totalSends = $message->sends()->count();
                $sentSends = $message->sends()->whereNotNull('sent_at')->count();
                $failedSends = $message->sends()->whereNotNull('failed_at')->count();

                if ($sentSends > 0 && $failedSends === 0) {
                    $message->update([
                        'status' => MessageStatus::Sent,
                        'sent_at' => now()
                    ]);
                }
            }
        }
    }
}
