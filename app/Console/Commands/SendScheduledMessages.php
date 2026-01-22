<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
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
    public function handle(): int
    {
        $messages = Message::where('status', MessageStatus::Ready)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No scheduled messages to send.');

            return self::SUCCESS;
        }

        foreach ($messages as $message) {
            $this->processMessage($message);
        }

        $this->info("Processed {$messages->count()} scheduled message(s).");

        return self::SUCCESS;
    }

    protected function processMessage(Message $message): void
    {
        $this->info("Processing message: {$message->subject}");

        // Update status to sending
        $message->update(['status' => MessageStatus::Sending]);

        // Get target subscribers
        $query = Subscriber::where('status', SubscriberStatus::Confirmed);

        // Filter by tags if specified
        if ($message->tags->isNotEmpty()) {
            $tagIds = $message->tags->pluck('id');
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        $subscribers = $query->get();

        $this->info("Found {$subscribers->count()} target subscriber(s).");

        $created = 0;
        foreach ($subscribers as $subscriber) {
            // Check if MessageSend already exists
            $exists = MessageSend::where('message_id', $message->id)
                ->where('subscriber_id', $subscriber->id)
                ->exists();

            if (! $exists) {
                $messageSend = MessageSend::create([
                    'message_id' => $message->id,
                    'subscriber_id' => $subscriber->id,
                ]);

                // Dispatch to queue instead of processing immediately
                SendNewsletterEmail::dispatch($messageSend->id);
                $created++;
            }
        }

        $this->info("Queued {$created} job(s) for message: {$message->subject}");
    }
}
