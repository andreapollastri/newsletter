<?php

namespace Database\Factories;

use App\Enums\SubscriberStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscriber>
 */
class SubscriberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'status' => SubscriberStatus::Confirmed,
        ];
    }

    /**
     * Indicate that the subscriber is pending confirmation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriberStatus::Pending,
            'confirmation_token' => Str::random(32),
        ]);
    }

    /**
     * Indicate that the subscriber has confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriberStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the subscriber has unsubscribed.
     */
    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Indicate that the subscriber has bounced.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriberStatus::Bounced,
        ]);
    }
}
