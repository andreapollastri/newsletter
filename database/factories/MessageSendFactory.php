<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MessageSend>
 */
class MessageSendFactory extends Factory
{
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
            'opens_count' => 0,
            'clicks_count' => 0,
        ];
    }

    /**
     * Indicate that the send was successful.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the send failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'failed_at' => now(),
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the email was opened.
     */
    public function opened(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => now()->subHour(),
            'opens_count' => $count,
        ]);
    }

    /**
     * Indicate that a link was clicked.
     */
    public function clicked(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => now()->subHour(),
            'clicks_count' => $count,
        ]);
    }
}
