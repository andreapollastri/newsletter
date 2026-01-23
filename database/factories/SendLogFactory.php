<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\SendLog;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SendLog>
 */
class SendLogFactory extends Factory
{
    protected $model = SendLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'subscriber_id' => Subscriber::factory(),
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the email was sent successfully.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'queued_at' => now()->subMinutes(5),
            'sent_at' => now(),
            'message_id_header' => '<'.fake()->uuid().'@newsletter.test>',
        ]);
    }

    /**
     * Indicate that the email failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'queued_at' => now()->subMinutes(5),
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the email bounced.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'bounced',
            'queued_at' => now()->subMinutes(10),
            'sent_at' => now()->subMinutes(5),
            'error_message' => 'Mailbox not found',
        ]);
    }

    /**
     * Indicate that the email was opened.
     */
    public function opened(): static
    {
        return $this->sent()->state(fn (array $attributes) => [
            'open_count' => fake()->numberBetween(1, 5),
            'first_opened_at' => now()->subHours(2),
            'last_opened_at' => now(),
        ]);
    }
}
