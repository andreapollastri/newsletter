<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'template_id' => null,
            'subject' => fake()->sentence(),
            'html_content' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'status' => MessageStatus::Draft,
        ];
    }

    /**
     * Indicate that the message is ready to send.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::Ready,
        ]);
    }

    /**
     * Indicate that the message is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::Ready,
            'scheduled_at' => now()->addHour(),
        ]);
    }

    /**
     * Indicate that the message has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the message uses a template.
     */
    public function withTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => \App\Models\Template::factory(),
        ]);
    }
}
