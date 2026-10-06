<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Services\MessageDispatchService;
use Illuminate\Console\Command;

class SendScheduledMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled newsletter messages';

    /**
     * Execute the console command.
     */
    public function handle(MessageDispatchService $dispatcher): int
    {
        $messages = Message::where('status', MessageStatus::Ready)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->with(['tags', 'excludedTags'])
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No scheduled messages to send.');

            return self::SUCCESS;
        }

        foreach ($messages as $message) {
            $this->processMessage($message, $dispatcher);
        }

        $this->info("Processed {$messages->count()} scheduled message(s).");

        return self::SUCCESS;
    }

    protected function processMessage(Message $message, MessageDispatchService $dispatcher): void
    {
        $this->info("Processing message: {$message->subject}");

        $queued = $dispatcher->dispatch($message);

        if ($queued === 0 && $message->status === MessageStatus::Ready) {
            $dispatcher->returnToDraftWithoutRecipients($message);
            $this->warn("No recipients for message: {$message->subject} (moved back to draft)");

            return;
        }

        $this->info("Queued {$queued} job(s) for message: {$message->subject}");
    }
}
